<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Socials extends MY_Controller
{
    private $cached_request_body = NULL;
    
    // ── Fill these in from your provider dashboards ───────────
    // Google: console.cloud.google.com -> Credentials -> OAuth Client ID (Web)    
    protected $google_client_id = '637486102873-2eua6021flh70c575brpnd1i5i5cntr1.apps.googleusercontent.com';
    // Facebook: developers.facebook.com -> App settings -> Basic
    protected $facebook_app_id     = '856165983804869';
    protected $facebook_app_secret = 'f7ab95df380ae65f85c7d34893682b85';
   
    public function __construct()
    {                
        parent::__construct();

        $this->load->library('email');
        $this->load->library('ion_auth');        
        $this->load->model('api_model');
        $this->load->model('base_model');        
    }

    /*=======================================================
        SOCIAL LOGIN / SIGNUP (UNIFIED)
        POST /api/social_login
        Body:
          token         (X-API-Key, same as other endpoints)
          provider      = "google" | "facebook"
          id_token      (Google: required)
          access_token  (Facebook: required)
    ========================================================
    */

    public function social_login()
    {
        header('Content-Type: application/json');           
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents('php://input'), true)
            : $this->input->post();

        if (!$this->checkAPI_token_from_header()) {
            $this->output->set_status_header(401);
            echo json_encode(['status' => 401, 'message' => 'API key is invalid!']);
            return;
        }

        $provider = strtolower(trim($object['provider'] ?? ''));

        if (!in_array($provider, ['google', 'facebook'])) {
            $this->output->set_status_header(400);
            echo json_encode(['status' => 400, 'message' => 'provider must be "google" or "facebook"']);
            return;
        }

        // ── 1. Verify with the provider, get back a trusted identity ──
        if ($provider === 'google') {
            $id_token = trim($object['id_token'] ?? '');
            if (!$id_token) {
                $this->output->set_status_header(400);
                echo json_encode(['status' => 400, 'message' => 'id_token is required for google']);
                return;
            }
            $identity = $this->_verifyGoogleToken($id_token);
        } else {
            $access_token = trim($object['access_token'] ?? '');
            if (!$access_token) {
                $this->output->set_status_header(400);
                echo json_encode(['status' => 400, 'message' => 'access_token is required for facebook']);
                return;
            }
            $identity = $this->_verifyFacebookToken($access_token);
            log_message('error', var_export($identity, true));
        }

        if (!$identity['valid']) {
            $this->output->set_status_header(401);
            echo json_encode(['status' => 401, 'message' => $identity['error']]);
            return;
        }

        $provider_user_id = $identity['provider_user_id'];
        $email             = strtolower(trim($identity['email']));
        $name              = trim($identity['name'] ?? '');
        $avatar_url        = $identity['avatar_url'] ?? null;

        if (empty($email)) {
            $this->output->set_status_header(422);
            echo json_encode([
                'status'  => 422,
                'message' => 'Your ' . ucfirst($provider) . ' account has no verified email. Please use email/password registration instead.',
            ]);
            return;
        }

        // ── 2. Already linked? 
        $link = $this->db->get_where('user_social_accounts', [
            'provider'         => $provider,
            'provider_user_id' => $provider_user_id,
        ])->row();

        $is_new_user = false;

        if ($link) {
            $userInfo = $this->base_model->getUserById($link->user_id);

            if (!$userInfo) {
                // Link exists but user record vanished — shouldn't happen, fail loudly
                $this->output->set_status_header(500);
                echo json_encode(['status' => 500, 'message' => 'Linked account is inconsistent. Please contact support.']);
                return;
            }

        } else {
            // ── 3. Not linked yet — find by email or create new ──
            $existingUser = $this->db->get_where('users', ['email' => $email])->row();
            if ($existingUser) {
                // Auto-link this provider to the existing email/password account
                $userInfo = $existingUser;
                $this->_linkSocialAccount($existingUser->id, $provider, $provider_user_id, $email);
            } else {             
                $this->output->set_status_header(406);    
                echo json_encode([
                    'status'  => 406,
                    'message' => 'No account found. Please sign up to continue.',
                ]);
                return;
            }
        }   
        
        $jwtPayload = [
            'user_id'   => $userInfo->id,
            'email'     => $userInfo->email,
            'user_role' => $userInfo->user_role,
            'fullname'  => $userInfo->fullname,
        ];
        $access_token  = $this->jwt_helper->generateAccessToken($jwtPayload);
        $refresh_token = $this->jwt_helper->generateRefreshToken($userInfo->id);
        $this->_storeRefreshToken($userInfo->id, $refresh_token);

        if ((int)$userInfo->onboarding_completion === 0) {
            $this->output->set_status_header(406);
            echo json_encode([
                'status'  => 406,
                'message' => 'Account profile is incomplete. Please continue your onboarding process.',
                'user_id' => $userInfo->id,
                'email'       => $userInfo->email,
                'fullname'    => $userInfo->fullname,
                'access_token'  => $access_token,
                'refresh_token' => $refresh_token,
                'token_type'    => 'Bearer',
                'expires_in'    => $this->jwt_helper->get_expiry_time(),
            ]);
            return;
        }

        // Account approval status checks         
        if ((int)$userInfo->is_approved === 0) {
            $this->output->set_status_header(406);
            echo json_encode([
                'status'  => 406,
                'message' => 'Account pending admin approval. You will be notified once approved.',
                'user_id' => $userInfo->id,
            ]);
            return;
        }

        // Account active checks 
        if ((int)$userInfo->active === 0) {
            $this->output->set_status_header(423, 'Locked');
            echo json_encode([
                'status'  => 423,
                'message' => 'Account has been deactivated. Please contact support.',
                'user_id' => $userInfo->id,
            ]);
            return;
        }        

        // Issue tokens exactly
        $this->db->where('id', $userInfo->id);
        $this->db->update('users', ['last_login' => time()]);
        $profile = $this->db->get_where('user_profiles', ['user_id' => $userInfo->id])->row_array();
        $zone_row = $this->db
            ->select('c.city_id, c.zone_id, z.zone AS zone_name')
            ->from('cities c')
            ->join('zones z', 'c.zone_id = z.zone_id', 'left')
            ->where('LOWER(c.city) =', strtolower($userInfo->city ?? ''))
            ->get()->row();        

        $data = [
            'status'       => 200,
            'message'      => 'Login successful',
            'is_new_user'  => $is_new_user,
            'provider'     => $provider,
            'access_token'  => $access_token,
            'refresh_token' => $refresh_token,
            'token_type'    => 'Bearer',
            'expires_in'    => $this->jwt_helper->get_expiry_time(),

            'user_id'    => $userInfo->id,
            'user_code'  => $userInfo->user_code ?? null,
            'email'      => $userInfo->email,
            'fullname'   => $userInfo->fullname,
            'first_name' => $userInfo->first_name,
            'last_name'  => $userInfo->last_name,
            'phone'      => $userInfo->phone,
            'user_role'  => $userInfo->user_role,
            'avatar'     => $userInfo->avatar ? site_url($userInfo->avatar) : null,

            'active'         => (bool) $userInfo->active,
            'email_verified' => (bool) $userInfo->email_verified,
            'is_approved'    => (bool) $userInfo->is_approved,

            'chapter_id'      => $userInfo->chapter_id      ?? null,
            'graduation_year' => $userInfo->graduation_year ?? null,
            'department'      => $userInfo->department      ?? null,
            'bio'             => $userInfo->bio              ?? null,

            'zone_id'   => isset($zone_row->zone_id) ? (int) $zone_row->zone_id : null,
            'zone_name' => $zone_row->zone_name ?? null,
            'city_id'   => isset($zone_row->city_id) ? (int) $zone_row->city_id : null,

            'profile' => [
                'linkedin'         => $profile['linkedin']         ?? null,
                'twitter'          => $profile['twitter']          ?? null,
                'facebook'         => $profile['facebook']         ?? null,
                'instagram'        => $profile['instagram']        ?? null,
                'field_visibility' => $profile['field_visibility'] ?? null,
                'website'          => $profile['website']          ?? null,
                'current_company'  => $profile['current_company']  ?? null,
                'current_position' => $profile['current_position'] ?? null,
                'city'             => $profile['city']             ?? null,
                'country'          => $profile['country']          ?? null,
                'skills'           => $profile['skills']           ?? null,
                'achievements'     => $profile['achievements']     ?? null,
                'year'             => $profile['year']             ?? null,
                'is_visible'       => isset($profile['is_visible']) ? (bool) $profile['is_visible'] : true,
            ],
        ];

        $this->output->set_status_header(200);
        echo json_encode($data);
    }

    
    public function social_signup()
    {
        header('Content-Type: application/json');           
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents('php://input'), true)
            : $this->input->post();

        if (!$this->checkAPI_token_from_header()) {
            $this->output->set_status_header(401);
            echo json_encode(['status' => 401, 'message' => 'API key is invalid!']);
            return;
        }

        $provider = strtolower(trim($object['provider'] ?? ''));

        if (!in_array($provider, ['google', 'facebook'])) {
            $this->output->set_status_header(400);
            echo json_encode(['status' => 400, 'message' => 'provider must be "google" or "facebook"']);
            return;
        }

        // ── 1. Verify with the provider, get back a trusted identity ──
        if ($provider === 'google') {
            $id_token = trim($object['id_token'] ?? '');
            if (!$id_token) {
                $this->output->set_status_header(400);
                echo json_encode(['status' => 400, 'message' => 'id_token is required for google']);
                return;
            }
            $identity = $this->_verifyGoogleToken($id_token);
        } else {
            $access_token = trim($object['access_token'] ?? '');
            if (!$access_token) {
                $this->output->set_status_header(400);
                echo json_encode(['status' => 400, 'message' => 'access_token is required for facebook']);
                return;
            }
            $identity = $this->_verifyFacebookToken($access_token);
        }

        if (!$identity['valid']) {
            $this->output->set_status_header(401);
            echo json_encode(['status' => 401, 'message' => $identity['error']]);
            return;
        }

        $provider_user_id = $identity['provider_user_id'];
        $email             = strtolower(trim($identity['email']));
        $name              = trim($identity['name'] ?? '');
        $avatar_url        = $identity['avatar_url'] ?? null;

        if (empty($email)) {
            $this->output->set_status_header(422);
            echo json_encode([
                'status'  => 422,
                'message' => 'Your ' . ucfirst($provider) . ' account has no verified email. Please use email/password registration instead.',
            ]);
            return;
        }

        // ── 2. Already linked?
        $link = $this->db->get_where('user_social_accounts', [
            'provider'         => $provider,
            'provider_user_id' => $provider_user_id,
        ])->row();

        $is_new_user = false;

        if ($link) {
            $userInfo = $this->base_model->getUserById($link->user_id);

            if (!$userInfo) {
                // Link exists but user record vanished — shouldn't happen, fail loudly
                $this->output->set_status_header(500);
                echo json_encode(['status' => 500, 'message' => 'Linked account is inconsistent. Please contact support.']);
                return;
            }else{
                $this->output->set_status_header(409);
                echo json_encode([
                    'status'  => 409,
                    'message' => 'An account with this email already exists. Please sign in instead.',
                ]);
                return;
            }

        } else {
            // ── 3. Not linked yet — find by email or create new ──
            $existingUser = $this->db->get_where('users', ['email' => $email])->row();

            if ($existingUser) {
                $this->output->set_status_header(409);
                echo json_encode([
                    'status'  => 409,
                    'message' => 'An account with this email already exists. Please sign in instead.',
                ]);
                return;
            } else {
                // Brand new user via social signup
                $new_user_id = $this->_createSocialUser($email, $name, $avatar_url);

                if (!$new_user_id) {
                    $this->output->set_status_header(500);
                    echo json_encode(['status' => 500, 'message' => 'Could not create account. Please try again.']);
                    return;
                }
                $this->_linkSocialAccount($new_user_id, $provider, $provider_user_id, $email);
                $userInfo    = $this->base_model->getUserById($new_user_id);
                $is_new_user = true;
            }
        }
            
        $this->output->set_status_header(201);
        echo json_encode([
            'status'      => 201,
            'message'     => 'Account created successfully. Please complete your profile.',
            'is_new_user' => true,
            'user_id'     => $userInfo->id,
            'email'       => $userInfo->email,
            'fullname'    => $userInfo->fullname,
        ]);
        return;
    }

    /*=======================================================
        LINK AN ADDITIONAL PROVIDER TO AN ALREADY-LOGGED-IN ACCOUNT
        POST /api/social_link
        Auth: X-API-Key + JWT (must already be logged in)
        Body: provider, id_token / access_token
        Use case: user logged in with email/password, now wants
        to also enable "Sign in with Google" for convenience.
    ========================================================
    */
    public function link()
    {
        header('Content-Type: application/json');

        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents('php://input'), true)
            : $this->input->post();

        $user = $this->authorize();

        if (!$user) {
            return;
        }         

        $provider = strtolower(trim($object['provider'] ?? ''));
        if (!in_array($provider, ['google', 'facebook'])) {
            $this->output->set_status_header(400);
            echo json_encode(['status' => 400, 'message' => 'provider must be "google" or "facebook"']);
            return;
        }

        if ($provider === 'google') {
            $id_token = trim($object['id_token'] ?? '');
            $identity = $id_token ? $this->_verifyGoogleToken($id_token) : ['valid' => false, 'error' => 'id_token is required'];
        } else {
            $access_token = trim($object['access_token'] ?? '');
            $identity = $access_token ? $this->_verifyFacebookToken($access_token) : ['valid' => false, 'error' => 'access_token is required'];
        }

        if (!$identity['valid']) {
            $this->output->set_status_header(401);
            echo json_encode(['status' => 401, 'message' => $identity['error']]);
            return;
        }

        // Is this provider identity already linked to a DIFFERENT user?
        $existingLink = $this->db->get_where('user_social_accounts', [
            'provider'         => $provider,
            'provider_user_id' => $identity['provider_user_id'],
        ])->row();

        if ($existingLink && (int)$existingLink->user_id !== (int)$user->user_id) {
            $this->output->set_status_header(409);
            echo json_encode(['status' => 409, 'message' => 'This ' . ucfirst($provider) . ' account is already linked to a different user.']);
            return;
        }

        if ($existingLink) {
            $this->output->set_status_header(200);
            echo json_encode(['status' => 200, 'message' => ucfirst($provider) . ' account is already linked to your profile.']);
            return;
        }

        $this->_linkSocialAccount((int)$user->user_id, $provider, $identity['provider_user_id'], strtolower(trim($identity['email'] ?? '')));

        $this->output->set_status_header(200);
        echo json_encode(['status' => 200, 'message' => ucfirst($provider) . ' account linked successfully']);
    }

    /*=======================================================
        UNLINK A PROVIDER
        POST /api/social_unlink
        Auth: X-API-Key + JWT
        Body: provider
        Refuses to unlink if it's the user's ONLY login method
        and they have no usable password set.
    ========================================================
    */
    public function unlink()
    {
        header('Content-Type: application/json');

        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents('php://input'), true)
            : $this->input->post();

        $user = $this->authorize();

        if (!$user) {
            return;
        }

        $provider = strtolower(trim($object['provider'] ?? ''));
        if (!in_array($provider, ['google', 'facebook'])) {
            $this->output->set_status_header(400);
            echo json_encode(['status' => 400, 'message' => 'provider must be "google" or "facebook"']);
            return;
        }

        $user_id = (int)$user->user_id;
        $user    = $this->db->get_where('users', ['id' => $user_id])->row();

        $linkedCount = $this->db->where('user_id', $user_id)->count_all_results('user_social_accounts');
        $hasPassword = $user && (int)($user->has_password ?? 1) === 1;

        if ($linkedCount <= 1 && !$hasPassword) {
            $this->output->set_status_header(400);
            echo json_encode([
                'status'  => 400,
                'message' => 'You must set a password or link another provider before removing your only sign-in method.',
            ]);
            return;
        }

        $this->db->where(['user_id' => $user_id, 'provider' => $provider])->delete('user_social_accounts');

        if ($this->db->affected_rows() === 0) {
            $this->output->set_status_header(404);
            echo json_encode(['status' => 404, 'message' => ucfirst($provider) . ' account was not linked.']);
            return;
        }

        $this->output->set_status_header(200);
        echo json_encode(['status' => 200, 'message' => ucfirst($provider) . ' account unlinked successfully']);
    }

    // ─────────────────────────────────────────────────────────────
    //  PROVIDER VERIFICATION (server-side, never trust client claims)
    // ─────────────────────────────────────────────────────────────

    /**
     * Verifies a Google ID token using Google's tokeninfo endpoint.
     * Suitable for low/medium volume server-side verification.
     * Confirms signature validity (Google checks this for us) AND
     * that the token was issued for OUR client id (aud check) —
     * skipping the aud check would let a token meant for a
     * different app be replayed against this API.
     */
    private function _verifyGoogleToken($id_token)
    {
        $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($id_token);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err  = curl_error($ch);
        curl_close($ch);

        if ($curl_err) {
            log_message('error', 'Google token verify cURL error: ' . $curl_err);
            return ['valid' => false, 'error' => 'Could not reach Google to verify token'];
        }

        if ($http_code !== 200) {
            log_message('error', 'Google token verify failed, http_code=' . $http_code . ' body=' . $response);
            return ['valid' => false, 'error' => 'Invalid or expired Google token'];
        }

        $payload = json_decode($response, true);

        if (!$payload || empty($payload['sub'])) {
            return ['valid' => false, 'error' => 'Invalid Google token response'];
        }

        // Critical: confirm token was issued for THIS app, not some other
        // Google client. Without this check, any valid Google ID token
        // from any app could be replayed here.
        if (empty($payload['aud']) || $payload['aud'] !== $this->google_client_id) {
            log_message('error', 'Google token aud mismatch. Expected ' . $this->google_client_id . ' got ' . ($payload['aud'] ?? 'none'));
            return ['valid' => false, 'error' => 'Token was not issued for this application'];
        }

        if (isset($payload['email_verified']) && $payload['email_verified'] !== 'true' && $payload['email_verified'] !== true) {
            return ['valid' => false, 'error' => 'Google email is not verified'];
        }

        return [
            'valid'             => true,
            'provider_user_id'  => $payload['sub'],
            'email'             => $payload['email'] ?? null,
            'name'              => $payload['name'] ?? trim(($payload['given_name'] ?? '') . ' ' . ($payload['family_name'] ?? '')),
            'avatar_url'        => $payload['picture'] ?? null,
        ];
    }

    /**
     * Verifies a Facebook access token via the Graph API debug_token
     * endpoint (confirms it's valid AND issued for our app id), then
     * fetches the verified profile fields.
     */
    private function _verifyFacebookToken($access_token)
    {
        $app_token = $this->facebook_app_id . '|' . $this->facebook_app_secret;

        $debug_url = 'https://graph.facebook.com/debug_token?input_token=' . urlencode($access_token)
            . '&access_token=' . urlencode($app_token);

        $ch = curl_init($debug_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err  = curl_error($ch);
        curl_close($ch);

        if ($curl_err) {
            log_message('error', 'Facebook debug_token cURL error: ' . $curl_err);
            return ['valid' => false, 'error' => 'Could not reach Facebook to verify token'];
        }

        $debug = json_decode($response, true);

        if ($http_code !== 200 || empty($debug['data']['is_valid'])) {
            log_message('error', 'Facebook token invalid: ' . $response);
            return ['valid' => false, 'error' => 'Invalid or expired Facebook token'];
        }

        // Confirm the token was issued for OUR app, not a different one
        if (empty($debug['data']['app_id']) || (string)$debug['data']['app_id'] !== (string)$this->facebook_app_id) {
            log_message('error', 'Facebook token app_id mismatch: ' . $response);
            return ['valid' => false, 'error' => 'Token was not issued for this application'];
        }

        // Now fetch verified profile fields using the user's own token
        $profile_url = 'https://graph.facebook.com/me?fields=id,name,email,picture.type(large)'
            . '&access_token=' . urlencode($access_token);

        $ch = curl_init($profile_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        $profile_response = curl_exec($ch);
        $profile_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($profile_http_code !== 200) {
            log_message('error', 'Facebook /me fetch failed: ' . $profile_response);
            return ['valid' => false, 'error' => 'Could not retrieve Facebook profile'];
        }

        $profile = json_decode($profile_response, true);

        if (empty($profile['id'])) {
            return ['valid' => false, 'error' => 'Invalid Facebook profile response'];
        }

        // Facebook only returns email if the user granted that permission
        // AND has a verified email on file. It can legitimately be absent.
        return [
            'valid'            => true,
            'provider_user_id' => $profile['id'],
            'email'            => $profile['email'] ?? null,
            'name'             => $profile['name'] ?? null,
            'avatar_url'       => $profile['picture']['data']['url'] ?? null,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    //  HELPERS
    // ─────────────────────────────────────────────────────────────

    private function _linkSocialAccount($user_id, $provider, $provider_user_id, $email)
    {
        $this->db->insert('user_social_accounts', [
            'user_id'          => $user_id,
            'provider'         => $provider,
            'provider_user_id' => $provider_user_id,
            'email'            => $email ?: null,
            'created_at'       => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Creates a brand new user from a verified social identity.
     * Mirrors Api::register() field defaults but:
     *   - email_verified = 1 (provider already verified the email)
     *   - is_approved     = 0 (admin still reviews, per your workflow)
     *   - active          = 0 (cannot log in until approved)
     *   - has_password    = 0 (random unusable password, ion_auth needs *a* hash)
     */
    private function _createSocialUser($email, $name, $avatar_url = null)
    {
        $name_parts = preg_split('/\s+/', trim($name), 2);
        $first_name = $name_parts[0] ?? '';
        $last_name  = $name_parts[1] ?? '';
        $fullname   = trim($name) ?: $email;

        // Unusable random password — this account can only authenticate
        // via the linked provider until the user explicitly sets a password.
        $random_password = bin2hex(random_bytes(32));

        $user_code = $this->_generateUserCode(date('Y'), $email);

        $additional_data = [
            'first_name'      => $first_name,
            'last_name'       => $last_name,
            'fullname'        => $fullname,
            'user_role'       => 'member',
            'graduation_year' => date('Y'),
            'department'      => '',
            'profile_status'  => 'No',
            'email_verified'  => 1,             
            'onboarding_completion'  => 0, 
            'verify_token'    => null,
            'is_approved'     => 0,  
            'has_password'    => 0,
            'user_code'       => $user_code,
        ];

        $group = ['2']; // default alumni group, same as Api::register()

        $user_id = $this->ion_auth->register($email, $random_password, $email, $additional_data, $group);

        if (!$user_id) {
            log_message('error', 'Social signup ion_auth register failed for ' . $email . ': ' . $this->ion_auth->errors());
            return false;
        }
        return $user_id;
    }    
  
}
?>