<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once(FCPATH . 'vendor/autoload.php');
include APPPATH . 'third_party/phpqrcode/qrlib.php';

/**
 * Api Controller — with JWT Authentication
 * ==========================================
 *
 * TWO auth layers:
 *
 *   1. checkAPI_token($token)
 *      — machine-to-machine API key stored in api_table
 *      — every request must include: "token": "your_api_key"
 *      — used to prove the request comes from your app
 *
 *   2. checkJWT()
 *      — per-user identity token
 *      — issued on login, sent as: Authorization: Bearer eyJ...
 *      — proves WHO is making the request
 *      — no database hit needed
 *
 * How they work together:
 *   - Login endpoint: needs token (machine key) + credentials
 *   - Protected endpoints: need token (machine key) + JWT (user identity)
 *   - Admin endpoints: need token (machine key) + JWT with role check
 */
class Api extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('email');
        $this->load->library('ion_auth');
        $this->load->library('jwt_helper');          // ← JWT library
        $this->load->model('api_model');
        $this->load->model('base_model');
        $this->load->helper(array('url', 'form', 'notification_helper'));
    }

    // =========================================================
    //  PRIVATE HELPERS
    // =========================================================

    /**
     * Validate machine API key (existing — unchanged)
     * Checks api_table in database
     */
    private function checkAPI_token($token)
    {
        $api_name = 'alumni_key';

        $this->db->where('api_name', $api_name);
        $query = $this->db->get('api_table');

        if ($query !== FALSE) {
            if ($query->num_rows() == 1) {
                $user       = $query->row();
                $token_hash = $user->api_token;

                if (password_verify($token, $token_hash)) {
                    return TRUE;
                } else {
                    return FALSE;
                }
            } else {
                $query->free_result();
                return FALSE;
            }
        } else {
            log_message('error', 'Database query failed: ' . $this->db->last_query());
            return FALSE;
        }
    }

    /**
     * Validate JWT from Authorization: Bearer header
     * Falls back to 'jwt' field in JSON body (useful for testing)
     *
     * Returns decoded user data object (stdClass) on success
     * Returns NULL on failure
     *
     * Usage:
     *   $jwtData = $this->checkJWT();
     *   if (!$jwtData) { // reject }
     *   echo $jwtData->user_id;
     *   echo $jwtData->user_role;
     */
    private function checkJWT()
    {
        // 1. Try Authorization: Bearer header
        $token = $this->jwt_helper->getFromHeader();

        // 2. Fallback: accept jwt field in JSON body
        if (!$token) {
            $body  = file_get_contents('php://input');
            $input = json_decode($body, TRUE);
            $token = isset($input['jwt']) ? trim($input['jwt']) : NULL;
        }

        if (!$token) {
            return NULL;
        }

        $result = $this->jwt_helper->validateAccessToken($token);

        if (!$result['valid']) {
            log_message('error', 'JWT checkJWT failed: ' . $result['error']);
            return NULL;
        }

        return $result['data'];
    }

    /**
     * Standardised JWT error response
     * Sends the right HTTP status and JSON body for JWT failures
     *
     * @param string $error  error code from jwt_helper->validate()
     */
    private function jwtErrorResponse($error = 'token_invalid')
    {
        if ($error === 'token_expired') {
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode(array(
                'status'  => 401,
                'message' => 'Token expired, please login again',
                'code'    => 'token_expired'
            ));
        } else {
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode(array(
                'status'  => 401,
                'message' => 'Unauthorized: invalid or missing token',
                'code'    => 'token_invalid'
            ));
        }
    }

    // =========================================================
    //  LOGIN — issues JWT on success
    // =========================================================

    /**
     * POST /api/login
     *
     * Request body:
     * {
     *   "identity": "john@email.com",
     *   "password": "secret123",
     *   "token":    "your_api_key"
     * }
     *
     * Response on success adds:
     * {
     *   "access_token":  "eyJ...",   ← store this, send in Authorization header
     *   "refresh_token": "eyJ...",   ← store this, use to refresh access_token
     *   "expires_in":    86400,      ← seconds until access_token expires
     *   ...existing user fields...
     * }
     */
    public function login()
    {
        $body   = file_get_contents('php://input');
        $object = json_decode($body, TRUE);

        log_message('error', 'login input: ' . var_export($object, TRUE));

        $data = array(
            'status'  => 404,
            'message' => 'Kindly provide your email and password',
        );

        if (!$object) {
            $this->output->set_status_header(400);
            echo json_encode($data);
            return;
        }

        $identity = strtolower(trim($object['identity']));
        $password = trim($object['password']);
        $token    = trim($object['token']);

        $confirmToken = $this->checkAPI_token($token);

        if ($confirmToken) {
            $remember = (bool) 1;

            if (!$this->ion_auth->email_check($identity)) {
                $data['status']  = 409;
                $data['message'] = 'Email does not exist, kindly use another email';
                $this->output->set_status_header(409);
                echo json_encode($data);
                return;
            }

            if ($identity != '' && $password != '') {
                if (filter_var($identity, FILTER_VALIDATE_EMAIL)) {

                    $sql   = 'SELECT * FROM users WHERE email = ?';
                    $query = $this->db->query($sql, array($identity));
                    $user  = $query->row();

                    $loginSuccess1 = $this->ion_auth->login2($identity, $password, $remember);

                    if (($loginSuccess1) && ($user->profile_status === 'No' && $user->active == 0)) {
                        $data['status']  = 405;
                        $data['message'] = 'Login Failed, account pending verification';
                        $data['user_id'] = $user->id;
                        $this->output->set_status_header(405);
                        header('Content-type: application/json');
                        echo json_encode($data);
                        return;
                    }

                    if (($loginSuccess1) && ($user->profile_status !== 'No' && $user->active == 0)) {
                        $data['status']  = 406;
                        $data['message'] = 'Login Failed, account pending approval';
                        $data['user_id'] = $user->id;
                        $this->output->set_status_header(406);
                        header('Content-type: application/json');
                        echo json_encode($data);
                        return;
                    }

                    if ($this->ion_auth->login($identity, $password, $remember)) {
                        $userInfo = $this->base_model->getUserId($identity);

                        if ($userInfo) {

                            // ─────────────────────────────────────────────────────
                            // GENERATE JWT TOKENS
                            // ─────────────────────────────────────────────────────
                            $jwtPayload = array(
                                'user_id'   => $userInfo->id,
                                'email'     => $userInfo->email,
                                'user_role' => $userInfo->user_role,
                                'fullname'  => $userInfo->fullname,
                            );

                            $access_token  = $this->jwt_helper->generateAccessToken($jwtPayload);
                            $refresh_token = $this->jwt_helper->generateRefreshToken($userInfo->id);

                            // Store refresh token in DB so we can invalidate it later
                            $this->_storeRefreshToken($userInfo->id, $refresh_token);
                            // ─────────────────────────────────────────────────────

                            $data['status']            = 200;
                            $data['message']           = 'Login Successful';

                            // JWT fields
                            $data['access_token']      = $access_token;
                            $data['refresh_token']     = $refresh_token;
                            $data['expires_in']        = 86400;           // seconds
                            $data['token_type']        = 'Bearer';

                            // Existing user fields (unchanged)
                            $data['email']             = $userInfo->email;
                            $data['fullName']          = $userInfo->fullname;
                            $data['first_name']        = $userInfo->first_name;
                            $data['last_name']         = $userInfo->last_name;
                            $data['user_role']         = $userInfo->user_role;
                            $data['user_id']           = $userInfo->id;
                            $data['address']           = $userInfo->address;
                            $data['phone']             = $userInfo->phone;
                            $data['profile_status']    = $userInfo->profile_status;
                            $data['privacy_view']      = $userInfo->privacy_view;
                            $data['push_notification'] = $userInfo->push_notification;
                            $data['status']            = ($userInfo->active == 1) ? TRUE : FALSE;
                            $data['emergency_contact'] = $userInfo->emergency_contact;
                            $data['image']             = $userInfo->image;
                            $data['proof_of_document'] = $userInfo->proof_of_document;
                            $data['userAccessCode']    = $userInfo->userAccessCode;

                            $this->trackUser();
                            $this->output->set_status_header(200);

                        } else {
                            $data['status']  = 200;
                            $data['message'] = 'Invalid email and password. Please try again';
                            $data['userCode'] = 0;
                            $this->output->set_status_header(200);
                        }
                    } else {
                        $data['status']  = 400;
                        $data['message'] = 'Login Failed, Invalid email or password';
                        $data['userCode'] = 0;
                        $this->output->set_status_header(400);
                    }
                } else {
                    $data['status']  = 405;
                    $data['message'] = 'Invalid email';
                    $data['userCode'] = 0;
                    $this->output->set_status_header(405);
                }
            }
        } else {
            $data['message'] = 'API key is invalid!';
            $this->output->set_status_header(404);
        }

        header('Content-type: application/json');
        echo json_encode($data);
    }

    // =========================================================
    //  REFRESH TOKEN — get a new access token without re-login
    // =========================================================

    /**
     * POST /api/refresh_token
     *
     * Request body:
     * {
     *   "token":         "your_api_key",
     *   "refresh_token": "eyJ..."
     * }
     *
     * Response:
     * {
     *   "status":       200,
     *   "access_token": "eyJ...",   ← new access token
     *   "expires_in":   86400
     * }
     *
     * Error (refresh expired or invalid):
     * {
     *   "status":  401,
     *   "message": "Refresh token expired, please login again",
     *   "code":    "refresh_expired"
     * }
     */
    public function refresh_token()
    {
        $body   = file_get_contents('php://input');
        $object = json_decode($body, TRUE);

        $data = array('status' => 400, 'message' => 'Invalid request');

        // 1. Validate machine API key
        $token = isset($object['token']) ? trim($object['token']) : '';
        if (!$this->checkAPI_token($token)) {
            $data['message'] = 'API key is invalid!';
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        // 2. Get refresh token from body
        $refresh_token = isset($object['refresh_token']) ? trim($object['refresh_token']) : '';
        if (empty($refresh_token)) {
            $data['message'] = 'refresh_token is required';
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        // 3. Validate refresh token
        $result = $this->jwt_helper->validateRefreshToken($refresh_token);

        if (!$result['valid']) {
            $code    = $result['error'];
            $message = ($code === 'token_expired')
                ? 'Refresh token expired, please login again'
                : 'Invalid refresh token, please login again';

            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode(array(
                'status'  => 401,
                'message' => $message,
                'code'    => $code === 'token_expired' ? 'refresh_expired' : 'refresh_invalid'
            ));
            return;
        }

        // 4. Get user_id from refresh token payload
        $decoded = $result['data'];
        $user_id = isset($decoded->user_id) ? (int)$decoded->user_id : 0;

        if (!$user_id) {
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode(array('status' => 401, 'message' => 'Invalid refresh token'));
            return;
        }

        // 5. Check refresh token exists in DB (wasn't revoked/logged out)
        $stored = $this->db->get_where('jwt_refresh_tokens', array(
            'user_id' => $user_id,
            'token'   => hash('sha256', $refresh_token),
            'revoked' => 0
        ))->row();

        if (!$stored) {
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode(array(
                'status'  => 401,
                'message' => 'Refresh token has been revoked, please login again',
                'code'    => 'refresh_revoked'
            ));
            return;
        }

        // 6. Fetch fresh user data
        $userInfo = $this->ion_auth->user($user_id)->row();
        if (!$userInfo || !$userInfo->active) {
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode(array('status' => 401, 'message' => 'User account is inactive'));
            return;
        }

        // 7. Issue new access token
        $jwtPayload = array(
            'user_id'   => $userInfo->id,
            'email'     => $userInfo->email,
            'user_role' => $userInfo->user_role,
            'fullname'  => $userInfo->fullname,
        );

        $new_access_token = $this->jwt_helper->generateAccessToken($jwtPayload);

        $this->output->set_status_header(200);
        header('Content-Type: application/json');
        echo json_encode(array(
            'status'       => 200,
            'message'      => 'Token refreshed successfully',
            'access_token' => $new_access_token,
            'expires_in'   => 86400,
            'token_type'   => 'Bearer'
        ));
    }

    // =========================================================
    //  LOGOUT — revoke refresh token
    // =========================================================

    /**
     * POST /api/logout
     *
     * Request body:
     * {
     *   "token":         "your_api_key",
     *   "refresh_token": "eyJ...",
     *   "user_id":       5
     * }
     *
     * This revokes the refresh token in the DB.
     * The access token expires naturally (24h) — nothing to do server-side.
     */
    public function logout()
    {
        $body   = file_get_contents('php://input');
        $object = json_decode($body, TRUE);

        $data = array('status' => 400, 'message' => 'Invalid request');

        $token = isset($object['token']) ? trim($object['token']) : '';
        if (!$this->checkAPI_token($token)) {
            $data['message'] = 'API key is invalid!';
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        $refresh_token = isset($object['refresh_token']) ? trim($object['refresh_token']) : '';
        $user_id       = isset($object['user_id'])       ? (int)$object['user_id']        : 0;

        if ($refresh_token) {
            // Revoke this specific refresh token
            $this->db->where('token', hash('sha256', $refresh_token))
                     ->update('jwt_refresh_tokens', array('revoked' => 1));
        }

        if ($user_id) {
            // Revoke ALL refresh tokens for this user (logout from all devices)
            // Uncomment if you want that behaviour:
            // $this->db->where('user_id', $user_id)->update('jwt_refresh_tokens', array('revoked' => 1));
        }

        // Optionally: ion_auth logout
        $this->ion_auth->logout();

        $this->output->set_status_header(200);
        header('Content-Type: application/json');
        echo json_encode(array('status' => 200, 'message' => 'Logged out successfully'));
    }

    // =========================================================
    //  EXAMPLE PROTECTED ENDPOINT — get_visitors (with JWT)
    // =========================================================

    /**
     * GET /api/get_visitors
     *
     * BEFORE (no JWT):
     *   - Sent: token (machine key) + user_id
     *   - Server: DB query to get user role
     *
     * AFTER (with JWT):
     *   - Sent: token (machine key) + Authorization: Bearer eyJ...
     *   - Server: decode JWT → get user_id + role with NO DB hit
     *
     * Request:
     *   Header: Authorization: Bearer <access_token>
     *   Body: {
     *     "token":      "your_api_key",
     *     "visitor_id": null,         (optional)
     *     "access_code": null         (optional)
     *   }
     */
    public function get_visitors()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents('php://input'), TRUE)
            : $this->input->get();

        $data = array('status' => 400, 'message' => 'Invalid request');

        // ── Step 1: Validate machine API key ──────────────────────
        $token = isset($object['token']) ? trim($object['token']) : '';
        if (!$this->checkAPI_token($token)) {
            $data['message'] = 'API key is invalid!';
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        // ── Step 2: Validate JWT — get user identity ──────────────
        $jwtData = $this->checkJWT();
        if (!$jwtData) {
            $this->jwtErrorResponse();
            return;
        }

        // ── Step 3: Use JWT data (no DB needed for user_id/role) ──
        $user_id    = (int)$jwtData->user_id;
        $user_role  = $jwtData->user_role;

        $visitor_id  = isset($object['visitor_id'])  ? trim($object['visitor_id'])  : NULL;
        $access_code = isset($object['access_code']) ? trim($object['access_code']) : NULL;
        $visitor_role = isset($object['visitor_role']) ? trim($object['visitor_role']) : NULL;

        log_message('error', "get_visitors JWT user_id=$user_id role=$user_role");

        if (stripos($user_role, 'resident') !== false) {
            $this->db->select('rv.*, u.fullname, u.email, u.address')
                ->from('register_visitors rv')
                ->join('users u', 'rv.user_id = u.id', 'left')
                ->where('rv.user_id', $user_id)
                ->order_by('rv.visitor_id', 'DESC');

            $visitor = $this->db->get()->result_array();

            if ($visitor_id) {
                $visitor = $this->db->get_where('register_visitors', array('visitor_id' => $visitor_id))->row_array();
            }

            if ($visitor) {
                $data = array('status' => 200, 'message' => 'Visitor retrieved successfully', 'visitor' => $visitor);
            } else {
                $data = array('status' => 200, 'message' => 'Success', 'visitor' => array());
            }
            $this->output->set_status_header(200);

        } else {
            $qry_state = (is_string($visitor_role) && strtolower($visitor_role) === 'resident')
                ? 'rv.access_code = u.userAccessCode'
                : 'rv.user_id = u.id';

            if ($access_code) {
                $this->db->select('rv.*, u.fullname, u.email, u.address')
                    ->from('register_visitors rv')
                    ->join('users u', $qry_state, 'left')
                    ->where('rv.access_code', $access_code)
                    ->order_by('rv.visitor_id', 'DESC')
                    ->limit(1);

                $visitor  = $this->db->get()->row_array();
                $visitors = $visitor ? array($visitor) : array();
            } else {
                $this->db->select('rv.*, u.fullname, u.email, u.address')
                    ->from('register_visitors rv')
                    ->join('users u', 'rv.user_id = u.id', 'left')
                    ->order_by('rv.visitor_id', 'DESC');
                $visitors = $this->db->get()->result_array();
            }

            if (!empty($visitors)) {
                $data = array('status' => 200, 'message' => 'Visitors retrieved successfully', 'visitors' => $visitors);
            } else {
                $data = array('status' => 200, 'message' => 'Success', 'visitor' => array());
            }
            $this->output->set_status_header(200);
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }

    // =========================================================
    //  EXAMPLE PROTECTED ENDPOINT — get_users (with JWT + role check)
    // =========================================================

    /**
     * POST /api/get_users
     *
     * JWT role check example — only Manager/Admin can get all users.
     * A resident can only get their own record.
     */
    public function get_users()
    {
        $object  = json_decode(file_get_contents('php://input'), TRUE);
        $token   = isset($object['token']) ? trim($object['token']) : '';
        $user_id = isset($object['user_id']) ? trim($object['user_id']) : NULL;

        // ── Machine key ────────────────────────────────────────────
        if (!$this->checkAPI_token($token)) {
            $this->output->set_status_header(401);
            echo json_encode(array('status' => 401, 'message' => 'Invalid API Token'));
            return;
        }

        // ── JWT ────────────────────────────────────────────────────
        $jwtData = $this->checkJWT();
        if (!$jwtData) {
            $this->jwtErrorResponse();
            return;
        }

        $jwt_user_id   = (int)$jwtData->user_id;
        $jwt_user_role = strtolower($jwtData->user_role);

        // ── Role-based access ──────────────────────────────────────
        // Residents can only fetch their own record
        if (stripos($jwt_user_role, 'resident') !== FALSE) {
            $user = $this->db->get_where('users', array('id' => $jwt_user_id))->row_array();
            if ($user) {
                $user['user_id'] = $jwt_user_id;
                echo json_encode(array('status' => 200, 'user' => $user));
            } else {
                echo json_encode(array('status' => 404, 'message' => 'User not found'));
            }
            return;
        }

        // Managers / Admins — full access
        $userAccessCode = isset($object['userAccessCode']) ? trim($object['userAccessCode']) : NULL;
        $user_role_filter = isset($object['user_role']) ? trim($object['user_role']) : NULL;
        $address          = isset($object['address'])   ? trim($object['address'])   : NULL;

        if ($user_id) {
            $user = $this->db->get_where('users', array('id' => $user_id))->row_array();
            if ($user) {
                $user['user_id'] = $user_id;
                echo json_encode(array('status' => 200, 'user' => $user));
            } else {
                echo json_encode(array('status' => 404, 'message' => 'User not found'));
            }
        } elseif ($userAccessCode) {
            $user = $this->db->get_where('users', array('userAccessCode' => $userAccessCode))->row_array();
            if ($user) {
                $user['user_id'] = $user['id'];
                echo json_encode(array('status' => 200, 'user' => $user));
            } else {
                echo json_encode(array('status' => 404, 'message' => 'User not found'));
            }
        } else {
            $this->db->from('users');
            if (!empty($user_role_filter)) {
                $this->db->where('LOWER(user_role)', strtolower($user_role_filter));
            }
            if (!empty($address)) {
                $this->db->like('address', $address);
            }
            $this->db->order_by('id', 'DESC');
            $users = $this->db->get()->result_array();
            echo json_encode(array('status' => 200, 'users' => $users));
        }
    }

    // =========================================================
    //  REFRESH TOKEN — DB HELPER (private)
    // =========================================================

    /**
     * Store hashed refresh token in jwt_refresh_tokens table
     * We store a SHA256 hash — never the raw token
     *
     * @param int    $user_id
     * @param string $refresh_token  raw token string
     */
    private function _storeRefreshToken($user_id, $refresh_token)
    {
        // Clean up old tokens for this user (keep last 5 devices)
        $count = $this->db->where('user_id', $user_id)
                          ->where('revoked', 0)
                          ->count_all_results('jwt_refresh_tokens');

        if ($count >= 5) {
            // Delete oldest tokens beyond the limit
            $oldest = $this->db->select('id')
                               ->where('user_id', $user_id)
                               ->order_by('created_at', 'ASC')
                               ->limit($count - 4)
                               ->get('jwt_refresh_tokens')
                               ->result_array();

            foreach ($oldest as $row) {
                $this->db->where('id', $row['id'])->delete('jwt_refresh_tokens');
            }
        }

        // Insert new token (hashed)
        $this->db->insert('jwt_refresh_tokens', array(
            'user_id'    => $user_id,
            'token'      => hash('sha256', $refresh_token),
            'revoked'    => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', time() + 604800)
        ));
    }

    // =========================================================
    //  ALL YOUR EXISTING METHODS BELOW (UNCHANGED)
    //  Just add checkJWT() at the top of any you want to protect
    // =========================================================

    /*
     * HOW TO PROTECT ANY EXISTING ENDPOINT:
     *
     * Add these lines right after checkAPI_token():
     *
     *   $jwtData = $this->checkJWT();
     *   if (!$jwtData) {
     *       $this->jwtErrorResponse();
     *       return;
     *   }
     *   $user_id   = (int)$jwtData->user_id;
     *   $user_role = $jwtData->user_role;
     *
     * Then remove the DB lookup for user_role (it's already in the token):
     *   // REMOVE: $userExists = $this->db->get_where('users', ['id' => $user_id])->row();
     *   // REMOVE: $user_role  = $userExists->user_role;
     */

    // ... paste all your existing methods here ...

    // =========================================================
    //  EXISTING HELPERS (kept exactly as-is)
    // =========================================================

    private function checkAPI_token_alias($token)
    {
        // alias kept for reference — actual method above
        return $this->checkAPI_token($token);
    }

    private function respond($status_code, $message, $extra_data = array())
    {
        $CI =& get_instance();

        if (!is_array($extra_data)) {
            $extra_data = array('extra_info' => $extra_data);
        }

        $ok = ($status_code >= 200 && $status_code < 300);

        $response = array_merge(
            array(
                'ok'      => $ok,
                'status'  => $status_code,
                'message' => $message
            ),
            $extra_data
        );

        $CI->output
            ->set_status_header($status_code)
            ->set_content_type('application/json')
            ->set_output(json_encode($response));
    }

    private function generateAccessCode($email, $length = 8)
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $accessCode = '';

        do {
            $accessCode = '';
            for ($i = 0; $i < $length; $i++) {
                $accessCode .= $characters[random_int(0, strlen($characters) - 1)];
            }

            $exists = $this->db->where('userAccessCode', $accessCode)
                ->where('email !=', $email)
                ->get('users')
                ->row();
        } while ($exists);

        return $accessCode;
    }

    public function trackUser()
    {
        $body   = file_get_contents('php://input');
        $object = json_decode($body, TRUE);

        $email = strtolower(trim($object['email']));
        $ip    = $this->input->ip_address();

        $location = 'Unknown';
        $geoData  = @file_get_contents("http://ip-api.com/json/{$ip}");
        if ($geoData) {
            $geoData = json_decode($geoData, TRUE);
            if ($geoData['status'] === 'success') {
                $location = "{$geoData['city']}, {$geoData['regionName']}, {$geoData['country']}";
            }
        }

        $userAgent   = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $os          = 'Unknown OS';
        $deviceModel = 'Unknown Model';

        if (stripos($userAgent, 'Android') !== FALSE) {
            $deviceModel = 'Android Device';
            $os          = 'Android';
        } elseif (stripos($userAgent, 'iPhone') !== FALSE) {
            $deviceModel = 'iPhone';
            $os          = 'iOS';
        } elseif (stripos($userAgent, 'iPad') !== FALSE) {
            $deviceModel = 'iPad';
            $os          = 'iOS for iPad';
        } elseif (stripos($userAgent, 'Windows') !== FALSE) {
            $deviceModel = 'Windows PC';
            $os          = 'Windows';
        } elseif (stripos($userAgent, 'Macintosh') !== FALSE) {
            $deviceModel = 'Macbook';
            $os          = 'Mac OS';
        } elseif (stripos($userAgent, 'Linux') !== FALSE) {
            $deviceModel = 'Linux Device';
            $os          = 'Linux';
        }

        if (!empty($email)) {
            $sql        = "SELECT * FROM users WHERE email = '$email'";
            $userdetail = $this->base_model->run_qry($sql);
        } else {
            $id         = $this->ion_auth->get_user_id();
            $userdetail = $this->ion_auth->user($id)->row();
        }

        $name  = $userdetail->fullname;
        $email = $userdetail->email;

        $this->api_model->saveUserDetails($name, $email, $ip, $location, $deviceModel, $os, $deviceModel);
    }
}
