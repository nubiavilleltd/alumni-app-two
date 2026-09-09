<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * JWT Helper Library for CodeIgniter 3
 * =====================================
 * Works with firebase/php-jwt v5.5 (CI3 compatible)
 *
 * Install: composer require firebase/php-jwt:^5.5
 *
 * Place this file in: application/libraries/Jwt_helper.php
 */

require_once FCPATH . 'vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;

class Jwt_helper
{
    private $CI;
    private $secret;
    private $algorithm = 'HS256';
    private $access_expiry  = 86400;      // 24 hours  — access token
    //private $access_expiry = 30;         // 30 seconds — testing only
    private $refresh_expiry = 604800;     // 7 days    — refresh token

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->config->load('jwt', TRUE);
        $this->secret = $this->CI->config->item('jwt_secret', 'jwt');

        if (empty($this->secret)) {
            log_message('error', 'JWT: jwt_secret is not set in config/jwt.php');
        }
    }

    // =========================================================
    // TOKEN GENERATION
    // =========================================================

    /**
     * Generate ACCESS token (short-lived, 24h)
     * Contains: user_id, email, user_role, token_type
     *
     * @param  array  $payload  associative array of user data
     * @return string           signed JWT string
     */
    public function generateAccessToken($payload)
    {
        $now = time();

        $token = array(
            'iss'  => base_url(),               // issuer
            'iat'  => $now,                     // issued at
            'exp'  => $now + $this->access_expiry,
            'type' => 'access',
            'data' => $payload
        );

        return JWT::encode($token, $this->secret, $this->algorithm);
    }
public function get_expiry_time() {
    return $this->access_expiry;
}
    /**
     * Generate REFRESH token (long-lived, 7 days)
     * Contains only user_id — used to get a new access token
     *
     * @param  int    $user_id
     * @return string  signed JWT string
     */
    public function generateRefreshToken($user_id)
    {
        $now = time();

        $token = array(
            'iss'     => base_url(),
            'iat'     => $now,
            'exp'     => $now + $this->refresh_expiry,
            'type'    => 'refresh',
            'user_id' => $user_id
        );

        return JWT::encode($token, $this->secret, $this->algorithm);
    }

    // =========================================================
    // TOKEN VALIDATION
    // =========================================================

    /**
     * Validate any JWT token
     * Returns structured result array — never throws
     *
     * @param  string $token
     * @return array  ['valid' => bool, 'data' => object|null, 'error' => string]
     */
    public function validate($token)
    {
        if (empty($token)) {
            return array(
                'valid' => FALSE,
                'data'  => NULL,
                'error' => 'No token provided'
            );
        }

        try {
            $decoded = JWT::decode($token, $this->secret, array($this->algorithm));

            return array(
                'valid' => TRUE,
                'data'  => isset($decoded->data) ? $decoded->data : $decoded,
                'type'  => isset($decoded->type) ? $decoded->type : 'access',
                'error' => NULL
            );

        } catch (ExpiredException $e) {
            log_message('error', 'JWT expired: ' . $e->getMessage());
            return array(
                'valid' => FALSE,
                'data'  => NULL,
                'error' => 'token_expired'
            );

        } catch (SignatureInvalidException $e) {
            log_message('error', 'JWT signature invalid: ' . $e->getMessage());
            return array(
                'valid' => FALSE,
                'data'  => NULL,
                'error' => 'token_invalid'
            );

        } catch (BeforeValidException $e) {
            log_message('error', 'JWT not yet valid: ' . $e->getMessage());
            return array(
                'valid' => FALSE,
                'data'  => NULL,
                'error' => 'token_not_yet_valid'
            );

        } catch (Exception $e) {
            log_message('error', 'JWT error: ' . $e->getMessage());
            return array(
                'valid' => FALSE,
                'data'  => NULL,
                'error' => 'token_invalid'
            );
        }
    }

    /**
     * Validate ACCESS token specifically
     * Rejects refresh tokens even if valid
     *
     * @param  string $token
     * @return array
     */
    public function validateAccessToken($token)
    {
        $result = $this->validate($token);

        if ($result['valid'] && isset($result['type']) && $result['type'] !== 'access') {
            return array(
                'valid' => FALSE,
                'data'  => NULL,
                'error' => 'token_invalid'
            );
        }

        return $result;
    }

    /**
     * Validate REFRESH token specifically
     * Rejects access tokens even if valid
     *
     * @param  string $token
     * @return array
     */
    public function validateRefreshToken($token)
    {
        $result = $this->validate($token);

        if ($result['valid'] && isset($result['type']) && $result['type'] !== 'refresh') {
            return array(
                'valid' => FALSE,
                'data'  => NULL,
                'error' => 'token_invalid'
            );
        }

        return $result;
    }

    // =========================================================
    // HEADER EXTRACTION
    // =========================================================

    /**
     * Extract Bearer token from Authorization header
     * Handles Apache, Nginx, FastCGI, and header casing differences
     *
     * @return string|null  raw token string or NULL
     */
    public function getFromHeader()
    {
        $auth = '';

        // Method 1: Standard server variable
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];

        // Method 2: Redirect header (some Apache configs)
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];

        // Method 3: apache_request_headers() fallback
        } elseif (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();

            // Case-insensitive check
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'authorization') {
                    $auth = $value;
                    break;
                }
            }
        }

        // Must start with "Bearer "
        if (substr($auth, 0, 7) === 'Bearer ') {
            return trim(substr($auth, 7));
        }

        return NULL;
    }

    // =========================================================
    // UTILITY
    // =========================================================

    /**
     * Get remaining seconds until token expires
     *
     * @param  string $token
     * @return int|null  seconds remaining, or null if invalid
     */
    public function getExpiresIn($token)
    {
        try {
            // Decode without verification just to read exp claim
            $parts   = explode('.', $token);
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), TRUE);

            if (isset($payload['exp'])) {
                return max(0, $payload['exp'] - time());
            }
        } catch (Exception $e) {
            // silent
        }

        return NULL;
    }
}
