<?php
defined('BASEPATH') or exit('No direct script access allowed');
///include APPPATH . 'libraries/MobileDetect.php';
// if (!class_exists('Detection\MobileDetect')) {
//     require_once APPPATH . 'third_party/MobileDetect.php';
//     //require_once FCPATH . 'vendor/autoload.php';
// }
require_once(FCPATH . 'vendor/autoload.php');
//use Detection\MobileDetect;
include APPPATH . 'third_party/phpqrcode/qrlib.php';
class Api extends MY_Controller
{
    private $cached_request_body = NULL;
    protected $srvlink = "https://alumni-app-two.vercel.app/auth/login";
    /* How long a marketplace listing displays when the client sends no
       expires_at. Keep in step with the backfill in database_migrations.sql. */
    const LISTING_DISPLAY_DAYS = 30;

    public function __construct()
    {        
        parent::__construct();
        $this->load->library('email');
        $this->load->library('ion_auth');        
        $this->load->model('api_model');
        $this->load->model('base_model');
        $this->load->helper(array("url", "form", 'notification_helper'));
    }
    /*=======================================================
        GET API SECTION
    ========================================================
    */




    /*=======================================================
            END USERS API SECTION
    ========================================================
    */


    /*=======================================================
        LOGIN API SECTION
    ========================================================
    */
    public function login()
    {
        // Accept both raw JSON and form-data
        $contentType = $this->input->server('CONTENT_TYPE');
        if (strpos($contentType, 'application/json') !== false) {
            $object = json_decode(file_get_contents('php://input'), true);
        } else {
            $object = $this->input->post();
        }

        $data = [
            'status'  => 400,
            'message' => 'Kindly provide your email and password',
        ];

        if (!$object) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        $identity = strtolower(trim($object['identity'] ?? ''));
        $password  = trim($object['password']           ?? '');
        $token     = trim($object['token']              ?? '');

        if (!$this->checkAPI_token_from_header()) {
            $data['status']  = 401;
            $data['message'] = 'API key is invalid!';
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        if (empty($identity) || empty($password)) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        if (!filter_var($identity, FILTER_VALIDATE_EMAIL)) {
            $data['status']  = 422;
            $data['message'] = 'Invalid email format';
            $this->output->set_status_header(422);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        if (!$this->ion_auth->email_check($identity)) {
            $data['status']  = 404;
            $data['message'] = 'Invalid Credentials';
            $this->output->set_status_header(404);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        // Fetch user record
        $user = $this->db->get_where('users', ['email' => $identity])->row();

        // Check account status BEFORE ion_auth->login2(), because ion_auth
        // returns false for inactive accounts, masking the real reason.

        if ($user->email_verified == 0) {
            $data['status']  = 403;
            $data['message'] = 'Email not verified. Please check your inbox for the verification code.';
            $data['user_id'] = $user->id;
            $this->output->set_status_header(403);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }


        // Verify credentials without creating a session
        $loginSuccess1 = $this->ion_auth->login2($identity, $password, true);

        if (!$loginSuccess1) {
            $data['status']  = 400;
            $data['message'] = 'Invalid email or password';
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        // All checks passed — do the full login
        if ($this->ion_auth->login($identity, $password, true)) {

            $userInfo = $this->base_model->getUserId($identity);

            if (!$userInfo) {
                $data['status']  = 500;
                $data['message'] = 'Login error. Please try again.';
                $this->output->set_status_header(500);
                header('Content-Type: application/json');
                echo json_encode($data);
                return;
            }
                // Build JWT
            $jwtPayload = [
                'user_id'   => $userInfo->id,
                'email'     => $userInfo->email,
                'user_role' => $userInfo->user_role,
                'fullname'  => $userInfo->fullname,
            ];
            $access_token  = $this->jwt_helper->generateAccessToken($jwtPayload);
            $refresh_token = $this->jwt_helper->generateRefreshToken($userInfo->id);
            $this->_storeRefreshToken($userInfo->id, $refresh_token);

            if ($user->onboarding_completion == 0) {
                $data['status']  = 407;            
                $data['message'] = 'Account profile is incomplete. Please continue your onboarding process.';
                $data['user_id'] = $user->id;
                $data['email'] = $userInfo->email;
                $data['fullname'] = $user->fullname;  
                // Auth tokens                
                $data['access_token'] = $access_token;  
                $data['refresh_token'] = $refresh_token;  
                $data['token_type'] = 'Bearer';
                $data['expires_in'] = $this->jwt_helper->get_expiry_time();       
                $this->output->set_status_header(406);
                header('Content-Type: application/json');
                echo json_encode($data);
                return;
            }

            if ($user->is_approved == 0) {
                $data['status']  = 406;
                $data['message'] = 'Account pending admin approval. You will be notified once approved.';
                $data['user_id'] = $user->id;
                $this->output->set_status_header(406);
                header('Content-Type: application/json');
                echo json_encode($data);
                return;
            }

            if ((int)$user->active === 0) {
                $data['status']  = 423;
                $data['message'] = 'Account has been deactivated. Please contact support.';
                $data['user_id'] = $user->id;
                $this->output->set_status_header(423, 'Locked');
                header('Content-Type: application/json');
                echo json_encode($data);
                return;
            }

            // Update last_login
            $this->db->where('id', $userInfo->id);
            $this->db->update('users', ['last_login' => time()]);

            // Fetch user_profile row
            $profile = $this->db->get_where('user_profiles', ['user_id' => $userInfo->id])->row_array();

            // Fetch user roles (concatenated)
            $roles_query = $this->db
                ->select('GROUP_CONCAT(role_name SEPARATOR ";") AS system_role')
                ->from('roles')
                ->where('user_id', $userInfo->id)
                ->get()->row_array();
            $system_role = $roles_query['system_role'] ?? '';

            // Zone lookup via city match
            $zone_row = $this->db
                ->select('c.city_id, c.zone_id, z.zone AS zone_name')
                ->from('cities c')
                ->join('zones z', 'c.zone_id = z.zone_id', 'left')
                ->where('LOWER(c.city) =', strtolower($userInfo->city ?? ''))
                ->get()->row();

        

            $data = [
                'status'          => 200,
                'message'         => 'Login successful',

                // Auth tokens
                'access_token'    => $access_token,
                'refresh_token'   => $refresh_token,
                'token_type'      => 'Bearer',
               // 'expires_in'      => 86400,
                'expires_in'      => $this->jwt_helper->get_expiry_time(),

                // Core user fields
                'user_id'         => $userInfo->id,
                'user_code'       => $userInfo->user_code ?? null,
                'email'           => $userInfo->email,
                'fullname'        => $userInfo->fullname,
                'first_name'      => $userInfo->first_name,
                'last_name'       => $userInfo->last_name,
                'phone'           => $userInfo->phone,
                'user_role'       => $userInfo->user_role,
                'avatar'          => $userInfo->avatar ? site_url($userInfo->avatar) : null,

                // Account status
                'active'          => (bool) $userInfo->active,
                'email_verified'  => (bool) $userInfo->email_verified,
                'is_approved'     => (bool) $userInfo->is_approved,

                // Alumni-specific (existing)
                'chapter_id'      => $userInfo->chapter_id      ?? null,
                'graduation_year' => $userInfo->graduation_year  ?? null,
                'department'      => $userInfo->department       ?? null,
                'bio'             => $userInfo->bio              ?? null,

                // NEW alumni membership fields
                'name_in_school'      => $userInfo->name_in_school      ?? null,
                'alternative_phone'   => $userInfo->alternative_phone   ?? null,
                'birth_date'          => $userInfo->birth_date           ?? null,
                'house_color'         => $userInfo->house_color          ?? null,
                'is_coordinator'      => isset($userInfo->is_coordinator)      ? (bool) $userInfo->is_coordinator      : false,
                'residential_address' => $userInfo->residential_address  ?? null,
                'area'                => $userInfo->area                 ?? null,
                'city'                => $userInfo->city                 ?? null,
                'employment_status'   => $userInfo->employment_status    ?? null,
                'occupation'          => $userInfo->occupation           ?? null,
                'industry_sector'     => $userInfo->industry_sector      ?? null,
                'years_of_experience' => $userInfo->years_of_experience  ?? null,
                'is_volunteer'        => isset($userInfo->is_volunteer)        ? (bool) $userInfo->is_volunteer        : false,

                // Zone
                'zone_id'   => isset($zone_row->zone_id)   ? (int) $zone_row->zone_id   : null,
                'zone_name' => $zone_row->zone_name ?? null,
                'city_id'   => isset($zone_row->city_id)   ? (int) $zone_row->city_id   : null,

                // user_profiles data
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
                    'system_role'      => $system_role,
                ],
            ];

            //$this->trackUser();
            $this->output->set_status_header(200);

        } else {
            $data['status']  = 400;
            $data['message'] = 'Login failed. Please try again.';
            $this->output->set_status_header(400);
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }

    // ─────────────────────────────────────────────────────────────
    //  GET USER PROFILE
    //  Admins can fetch any user by passing user_id in the body.
    //  Regular users always get their own profile from the JWT.
    // ─────────────────────────────────────────────────────────────
    public function get_user_profile()
    {
        $object = json_decode(file_get_contents('php://input'), true);
        $token  = trim($object['token'] ?? '');

        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        $jwt_user_id   = (int)$jwtData->user_id;
        $jwt_user_role = strtolower($jwtData->user_role ?? '');
        $is_admin      = in_array($jwt_user_role, ['admin', 'manager', 'superadmin']);

        // --- Resolve target ---
        $requested_id = intval($object['user_id'] ?? 0);
        $target_id    = $requested_id > 0 ? $requested_id : $jwt_user_id;

        // Regular users cannot fetch another user's profile
        if (!$is_admin && $target_id !== $jwt_user_id) {
            $this->output->set_status_header(403);
            header('Content-Type: application/json');
            echo json_encode(['status' => 403, 'message' => 'You can only view your own profile']);
            return;
        }

        // --- Fetch user ---
        $userInfo = $this->base_model->getUserById($target_id);

        if (!$userInfo) {
            $this->output->set_status_header(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 404, 'message' => 'User not found']);
            return;
        }

        // --- Fetch user_profile row ---
        $profile = $this->db->get_where('user_profiles', ['user_id' => $target_id])->row_array();

        // --- Fetch user roles (concatenated) ---
        $roles_query = $this->db
            ->select('GROUP_CONCAT(role_name SEPARATOR ";") AS system_role')
            ->from('roles')
            ->where('user_id', $target_id)
            ->get()->row_array();
        $system_role = $roles_query['system_role'] ?? '';

        // Zone lookup via city match
        $zone_row = $this->db
            ->select('c.city_id, c.zone_id, z.zone AS zone_name')
            ->from('cities c')
            ->join('zones z', 'c.zone_id = z.zone_id', 'left')
            ->where('LOWER(c.city) =', strtolower($userInfo->city ?? ''))
            ->get()->row();

        $this->output->set_status_header(200);
        header('Content-Type: application/json');
        echo json_encode([
            'status'  => 200,
            'message' => 'Profile retrieved successfully',

            // Core user fields
            'user_id'         => $userInfo->id,
            'user_code'       => $userInfo->user_code        ?? null,
            'email'           => $userInfo->email,
            'fullname'        => $userInfo->fullname,
            'first_name'      => $userInfo->first_name,
            'last_name'       => $userInfo->last_name,
            'phone'           => $userInfo->phone,
            'user_role'       => $userInfo->user_role,
            'avatar'          => $userInfo->avatar ? site_url($userInfo->avatar) : null,

            // Account status
            'active'          => (bool) $userInfo->active,
            'email_verified'  => (bool) $userInfo->email_verified,
            'is_approved'     => (bool) $userInfo->is_approved,

            // Alumni-specific
            'chapter_id'          => $userInfo->chapter_id          ?? null,
            'graduation_year'     => $userInfo->graduation_year      ?? null,
            'department'          => $userInfo->department           ?? null,
            'bio'                 => $userInfo->bio                  ?? null,
            'name_in_school'      => $userInfo->name_in_school       ?? null,
            'alternative_phone'   => $userInfo->alternative_phone    ?? null,
            'birth_date'          => $userInfo->birth_date           ?? null,
            'house_color'         => $userInfo->house_color          ?? null,
            'is_coordinator'      => isset($userInfo->is_coordinator) ? (bool) $userInfo->is_coordinator : false,
            'residential_address' => $userInfo->residential_address  ?? null,
            'area'                => $userInfo->area                 ?? null,
            'city'                => $userInfo->city                 ?? null,
            'employment_status'   => $userInfo->employment_status    ?? null,
            'occupation'          => $userInfo->occupation           ?? null,
            'industry_sector'     => $userInfo->industry_sector      ?? null,
            'years_of_experience' => $userInfo->years_of_experience  ?? null,
            'is_volunteer'        => isset($userInfo->is_volunteer)   ? (bool) $userInfo->is_volunteer   : false,
            'nick_name'           => $userInfo->nick_name             ?? null,
            'state'               => $userInfo->state                 ?? null,

            // Zone
            'zone_id'   => isset($zone_row->zone_id)   ? (int) $zone_row->zone_id   : null,
            'zone_name' => $zone_row->zone_name ?? null,
            'city_id'   => isset($zone_row->city_id)   ? (int) $zone_row->city_id   : null,

            // Profile / social
            'profile' => [
                'linkedin'         => $profile['linkedin']         ?? null,
                'twitter'          => $profile['twitter']          ?? null,
                 'tiktok'          => $profile['tiktok']          ?? null,
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
                'system_role'      => $system_role,
            ],
        ]);
    }

    public function refresh_token()
    {
        $body   = file_get_contents('php://input');
        $object = json_decode($body, TRUE);
        $object = is_array($object) ? $object : array();

        $data = array('status' => 400, 'message' => 'Invalid request');

        $token = isset($object['token']) ? trim($object['token']) : '';
        if (!$this->checkAPI_token_from_header()) {
            $data['message'] = 'API key is invalid!';
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        $refresh_token = isset($object['refresh_token']) ? trim($object['refresh_token']) : '';
        if (empty($refresh_token)) {
            $data['message'] = 'refresh_token is required';
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

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

        $decoded = $result['data'];
        $user_id = isset($decoded->user_id) ? (int)$decoded->user_id : 0;

        if (!$user_id) {
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode(array('status' => 401, 'message' => 'Invalid refresh token'));
            return;
        }

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

        $userInfo = $this->ion_auth->user($user_id)->row();
        if (!$userInfo || !$userInfo->active) {
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode(array('status' => 401, 'message' => 'User account is inactive'));
            return;
        }

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
            //'expires_in'   => 86400,
                'expires_in'      => $this->jwt_helper->get_expiry_time(),
            'token_type'   => 'Bearer'
        ));
    }

    public function logout()
    {
        $body   = file_get_contents('php://input');
        $object = json_decode($body, TRUE);
        $object = is_array($object) ? $object : array();

        $data = array('status' => 400, 'message' => 'Invalid request');

        $token = isset($object['token']) ? trim($object['token']) : '';
        if (!$this->checkAPI_token_from_header()) {
            $data['message'] = 'API key is invalid!';
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        $refresh_token = isset($object['refresh_token']) ? trim($object['refresh_token']) : '';
        $user_id       = isset($object['user_id']) ? (int)$object['user_id'] : 0;

        if ($refresh_token) {
            $this->db->where('token', hash('sha256', $refresh_token))
                ->update('jwt_refresh_tokens', array('revoked' => 1));
        }

        if ($user_id) {
            // Revoke ALL refresh tokens for this user (logout from all devices)
            // Uncomment if you want that behaviour:
            // $this->db->where('user_id', $user_id)->update('jwt_refresh_tokens', array('revoked' => 1));
        }

        $this->ion_auth->logout();

        $this->output->set_status_header(200);
        header('Content-Type: application/json');
        echo json_encode(array('status' => 200, 'message' => 'Logged out successfully'));
    }

    public function forgot_password()
    {
        $body   = file_get_contents("php://input");
        $object = json_decode($body, true);

        $identity = isset($object['identity']) ? strtolower(trim($object['identity'])) : '';

        header('Content-Type: application/json');

        // 1. Validate input
        if ($identity === '') {
            http_response_code(400); // Bad Request
            echo json_encode([
                'status'  => false,
                'message' => 'Email address is required'
            ]);
            return;
        }

        // 2. Check if user exists
        $user = $this->ion_auth->where('email', $identity)->users()->row();

        if (empty($user)) {
            http_response_code(200); // Not Found
            echo json_encode([
                'status'  => true,
                'message' => "Email sent, if your account is found in our system, you'll receive a password reset email."
            ]);
            return;
        }

        // 3. Trigger forgotten password
        $forgotten = $this->ion_auth->forgotten_password(
            $user->{$this->config->item('identity', 'ion_auth')}
        );

        if ($forgotten) {
            http_response_code(200); // OK
            echo json_encode([
                'status'  => true,
                'message' => 'Please check your email for the password reset link'
            ]);
        } else {
            http_response_code(500); // Server Error
            echo json_encode([
                'status'  => false,
                'message' => 'An error occurred. Please try again later'
            ]);
        }
    }

    public function reset_password()
    {
        $body   = file_get_contents('php://input');
        $object = json_decode($body, true);

        $token    = trim($object['token'] ?? '');
        $code     = trim($object['code'] ?? '');
        $new_pass = trim($object['new_password'] ?? '');
        $confirm  = trim($object['new_password_confirm'] ?? '');

        $data = ['status' => 400, 'message' => ''];

        if (!$this->checkAPI_token_from_header()) {
            $data['message'] = 'API key is invalid';
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        if (!$code) {
            $data['message'] = 'Reset code is required';
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        $user = $this->ion_auth->forgotten_password_check($code);

        if (!$user) {
            $data['message'] = 'Reset link is invalid or has expired';
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        $min_length = (int)$this->config->item('min_password_length', 'ion_auth');

        if ($new_pass === '') {
            $data['message'] = 'New password is required';
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        if ($new_pass !== $confirm) {
            $data['message'] = 'Passwords do not match';
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        if (strlen($new_pass) < $min_length) {
            $data['message'] = 'Password must be at least ' . $min_length . ' characters';
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        $identity_field = $this->config->item('identity', 'ion_auth');
        $identity       = $user->$identity_field;

        $changed = $this->ion_auth->reset_password($identity, $new_pass);

        if ($changed) {
            $data['status']  = 200;
            $data['message'] = 'Password has been reset successfully';
            $this->output->set_status_header(200);
        } else {
            $data['status']  = 500;
            $data['message'] = $this->ion_auth->errors() ?: 'Failed to reset password';
            $this->output->set_status_header(500);
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }

    public function check_reset_password()
    {
        $body = file_get_contents("php://input");
        $object = json_decode($body, true);

        $email = strtolower(trim($object["email"]));
        //$password = (trim($object["password"])); 
        log_message('error', "Identity: " . var_export($object, true));

        $data = array(
            'message' => 'Kindly provide your email and password',
        );
        $token = trim($object["token"]);


        $confirmToken = $this->checkAPI_token_from_header();
        // $custtype = trim($object["custtype"]);
        if ($confirmToken) {
            if ($email != '') {
                $identity = $this->ion_auth->where('email', $email)->users()->row();
                if (empty($email)) {
                    $data['message'] = 'Email not found!';
                }

                $forgotten = $identity->resetKey;
                log_message('error', "forgotten: " . $forgotten);
                if ($forgotten) {
                    //$this->session->set_flashdata('success', $this->ion_auth->messages());
                    //$this->session->set_flashdata('message', 'Please check your email for password reset link');
                    $data['resetKey'] = $forgotten;
                    $data['profile_status'] =  $identity->profile_status;
                    $data['status'] = ($identity->active == 1) ? TRUE : FALSE;
                    $data['message'] = 'Please check your password has been resetted';
                } else {
                    $data['message'] = 'An error occured. Please try again';
                }
            }
        } else {

            $data['message'] = 'API key is invalid!';

            $this->output->set_status_header(404);
        }
        header('Content-type: application/json');
        echo json_encode($data);
    }

    private function getRequestBody()
    {
        if ($this->cached_request_body === NULL) {
            $this->cached_request_body = file_get_contents('php://input');
        }
        return $this->cached_request_body;
    }	

    // in your controller
    public function getAPIKey2()
    {
        $api_name = 'alumni_key';

        // rotate (generate+save) and get the new key
        $newKey = $this->api_model->rotateAPIKey($api_name);

        if ($newKey === false) {
            // handle error
            $resp = ['success' => false, 'message' => 'Could not rotate api key'];
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($resp));
            return;
        }

        // success - return new key
        $resp = ['success' => true, 'api_key' => $newKey];
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($resp));
    }

    public function trackUser()
    {
        $body = file_get_contents("php://input");
        $object = json_decode($body, true);

        $email = strtolower(trim($object["email"]));
        //$this->load->library('MobileDetect');
        // Load MobileDetect

        //$detect = new MobileDetect();

        // Get IP Address
        $ip = $this->input->ip_address();

        // Get Location Data
        $location = "Unknown";
        $geoData = @file_get_contents("http://ip-api.com/json/{$ip}");
        if ($geoData) {
            $geoData = json_decode($geoData, true);
            if ($geoData['status'] === 'success') {
                $location = "{$geoData['city']}, {$geoData['regionName']}, {$geoData['country']}";
            }
        }

        // Get User-Agent
        // $userAgent = $this->input->user_agent();
        $userAgent = $_SERVER['HTTP_USER_AGENT']
            ?? $_SERVER['HTTP_X_DEVICE_USER_AGENT']
            ?? $_SERVER['HTTP_X_ORIGINAL_USER_AGENT']
            ?? $_SERVER['HTTP_X_OPERAMINI_PHONE_UA']
            ?? $_SERVER['HTTP_X_SKYFIRE_PHONE']
            ?? $_SERVER['HTTP_X_BOLT_PHONE_UA']
            ?? $_SERVER['HTTP_DEVICE_STOCK_UA']
            ?? $_SERVER['HTTP_X_UCBROWSER_DEVICE_UA']
            ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
        log_message('error', 'HTTP_USER_AGENT3: ' . var_export($userAgent, true));
        // Get OS
        $os = "Unknown OS";



        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        $deviceModel = "Unknown Model";

        // Check for common device types
        if (stripos($userAgent, "Android") !== false) {
            $deviceModel = "Android Device";
            $os = "Android";
        } elseif (stripos($userAgent, "iPhone") !== false) {
            $deviceModel = "iPhone";
            $os = "iOS";
        } elseif (stripos($userAgent, "iPad") !== false) {
            $deviceModel = "iPad";
            $os = "iOS for iPad";
        } elseif (stripos($userAgent, "Windows Phone") !== false) {
            $deviceModel = "Windows Phone";
            $os = "Windows";
        } elseif (stripos($userAgent, "Macintosh") !== false) {
            $deviceModel = "Macbook";
            $os = "Mac OS";
        } elseif (stripos($userAgent, "Windows") !== false) {
            $deviceModel = "Windows PC";
            $os = "Windows";
        } elseif (stripos($userAgent, "Linux") !== false) {
            $deviceModel = "Linux Device";
            $os = "Linux";
        }

        if (!empty($email) || $email == '') {
            $sql = "SELECT * from users WHERE email = '$email' ";
            $userdetail = $this->base_model->run_qry($sql);
            $name = $userdetail->fullname;
            $email = $userdetail->email;
        } else {
            // Get Name and Email from form
            $id = $this->ion_auth->get_user_id();

            $userdetail = $this->ion_auth->user($id)->row();
            $name = $userdetail->fullname;
            $email = $userdetail->email;
        }

        // Save data to database
        $this->api_model->saveUserDetails($name, $email, $ip, $location, $deviceModel, $os, $deviceModel);
    }

    public function uploadfiles($field, $user_id, $type)
    {
        $folderMap = [
            'event_banner'      => 'events',
            'market'            => 'market',
            'market_images'     => 'market',
            'blog'              => 'blog',
            'blog_images'       => 'blog',
            'avatar'            => 'profiles',
            'profile'           => 'profiles',
            'profile_image'     => 'profiles',
            'proof_of_document' => 'documents',
            'project_banner'    => 'projects',    // ← NEW
            'leadership_photo'  => 'leadership',  // ← NEW
        ];
    
        $subfolder = 'attachments';
        foreach ($folderMap as $key => $folder) {
            if (stripos($type, $key) !== false || stripos($field, $key) !== false) {
                $subfolder = $folder;
                break;
            }
        }
    
        $destination = FCPATH . 'uploads/' . $subfolder;
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
    
        if (!isset($_FILES[$field])) {
            return false;
        }
    
        $files = $_FILES[$field];
    
        if (!is_array($files['name'])) {
            $files = [
                'name'     => [$files['name']],
                'type'     => [$files['type']],
                'tmp_name' => [$files['tmp_name']],
                'error'    => [$files['error']],
                'size'     => [$files['size']],
            ];
        }
    
        $uploadedFiles = [];
        $timestamp     = time();
    
        foreach ($files['error'] as $key => $error) {
            if ($error == UPLOAD_ERR_OK) {
                $originalName = basename($files['name'][$key]);
                $cleanName    = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
    
                if ($user_id) {
                    $filename = $user_id . '_' . $timestamp . '_' . $field . '_' . $cleanName;
                } else {
                    $filename = $timestamp . '_' . $field . '_' . $cleanName;
                }
    
                move_uploaded_file($files['tmp_name'][$key], $destination . '/' . $filename);
    
                $relative_path = 'uploads/' . $subfolder . '/' . $filename;
    
                $post_file = [
                    'user_id'         => $user_id ?: null,
                    'file_type'       => $type,
                    'filename'        => $cleanName,
                    'attachment_file' => $relative_path,
                    'dateadded'       => date('Y-m-d H:i:s'),
                ];
    
                $this->db->insert('attachments', $post_file);
                $uploadedFiles[] = (object) $post_file;
            }
        }
    
        return $uploadedFiles;
    }
    
    public function get_chapters()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->get();

        $data = ['status' => 400, 'message' => 'Invalid request'];

        $token = isset($object['token']) ? trim($object['token']) : '';
        if (!$this->checkAPI_token_from_header()) {
            $data['message'] = 'API key is invalid!';
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        $user_id = isset($object['user_id']) ? intval($object['user_id']) : null;

        // --- Get chapter for a specific user ---
        if ($user_id) {

            // Validate user exists
            $user = $this->db->get_where('users', ['id' => $user_id])->row();
            if (!$user) {
                $this->output->set_status_header(404);
                header('Content-Type: application/json');
                echo json_encode(['status' => 404, 'message' => 'User not found']);
                return;
            }

            // Join alumni_category + alumni_chapter
            $result = $this->db
                ->select('
                    ac.id           AS category_id,
                    ac.user_id,
                    ac.year,
                    ac.location,
                    ac.created_at   AS joined_at,
                    ch.id           AS chapter_id,
                    ch.chapter_name,
                    ch.is_enabled
                ')
                ->from('alumni_category ac')
                ->join('alumni_chapter ch', 'ch.id = ac.chapter_id', 'left')
                ->where('ac.user_id', $user_id)
                ->get()
                ->row_array();

            if (!$result) {
                $this->output->set_status_header(200);
                header('Content-Type: application/json');
                echo json_encode([
                    'status'  => 200,
                    'message' => 'User is not assigned to any chapter',
                    'chapter' => null,
                ]);
                return;
            }

            $this->output->set_status_header(200);
            header('Content-Type: application/json');
            echo json_encode([
                'status'  => 200,
                'message' => 'User chapter retrieved successfully',
                'user_id' => $user_id,
                'chapter' => $result,
            ]);
            return;
        }

        // --- No user_id: return all enabled chapters ---
        $chapters = $this->db
            ->where('is_enabled', 'true')
            ->order_by('chapter_name', 'ASC')
            ->get('alumni_chapter')
            ->result_array();

        $this->output->set_status_header(200);
        header('Content-Type: application/json');
        echo json_encode([
            'status'   => 200,
            'message'  => 'Chapters retrieved successfully',
            'total'    => count($chapters),
            'chapters' => $chapters,
        ]);
    }

    public function register()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        if (strpos($contentType, 'application/json') !== false) {
            $body   = file_get_contents('php://input');
            $object = json_decode($body, true);
        } else {
            $object = $this->input->post();
        }

        log_message('error', 'logging object in register');
        log_message('error', var_export($object, true));

        $data = [
            'status'  => 400,
            'message' => 'Kindly provide your email and password',
        ];

        if (!$object) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        $token        = trim($object['token'] ?? '');
        $confirmToken = $this->checkAPI_token_from_header();

        if (!$confirmToken) {
            $data['status']  = 401;
            $data['message'] = 'API key is invalid!';
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        // ── Required fields ──────────────────────────────────────
        $email    = trim($object['email']    ?? '');
        $password = trim($object['password'] ?? '');

        if (empty($email) || empty($password)) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        // ── Core identity fields ──────────────────────────────────
        $first_name      = trim($object['first_name']      ?? '');
        $last_name       = trim($object['last_name']       ?? '');
        $fullname        = trim($first_name . ' ' . $last_name);
        $user_role       = trim($object['user_role']       ?? 'member');
        $phone           = trim($object['phone']           ?? '');
        $chapter_id      = trim($object['chapter_id']      ?? '');
        $year            = trim($object['year']            ?? date('Y'));
        $graduation_year = trim($object['graduation_year'] ?? $year);
        $department      = trim($object['department']      ?? '');

        // ── NEW: Alumni membership form fields ────────────────────
        $name_in_school      = trim($object['name_in_school']      ?? '');
        $alternative_phone   = trim($object['alternative_phone']   ?? '');
        $birth_date          = trim($object['birth_date']          ?? ''); // YYYY-MM-DD
        $house_color         = trim($object['house_color']         ?? '');
        // Accepts: true, false, 1, 0, "1", "0", "true", "false"
        $is_coordinator      = (isset($object['is_coordinator']) && filter_var($object['is_coordinator'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true) ? true : false;
        $residential_address = trim($object['residential_address'] ?? '');
        $area                = trim($object['area']                ?? '');
        $city                = trim($object['city']                ?? '');
        $employment_status   = trim($object['employment_status']   ?? '');
        $occupation          = trim($object['occupation']          ?? '');
        $industry_sector     = trim($object['industry_sector']     ?? '');
        $years_of_experience = trim($object['years_of_experience'] ?? '');
        $is_volunteer        = (isset($object['is_volunteer']) && filter_var($object['is_volunteer'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true) ? true : false;
        $voucher_id          = intval($object['voucher_id'] ?? 0);
        $nick_name           = trim($object['nick_name'] ?? '');
        $state               = trim($object['state']     ?? '');

        // ── OTP for email verification ────────────────────────────
        $raw_code     = mt_rand(100000, 999999);
        $verify_token = $raw_code;

        // ── Unique 6-digit user code ──────────────────────────────
        $user_code = $this->_generateUserCode($graduation_year, $email);

        $additional_data = [
            // core
            'first_name'          => $first_name,
            'last_name'           => $last_name,
            'fullname'            => $fullname,
            'phone'               => $phone,
            'user_role'           => $user_role,
            'graduation_year'     => $graduation_year,
            'department'          => $department,
            'profile_status'      => 'No',
            'email_verified'      => 0,
            'verify_token'        => $verify_token,
            'is_approved'         => 0,
            'user_code'           => $user_code,

            // NEW alumni membership fields
            'name_in_school'      => $name_in_school,
            'alternative_phone'   => $alternative_phone,
            'birth_date'          => ($birth_date !== '') ? $birth_date : null,
            'house_color'         => $house_color,
            'is_coordinator'      => $is_coordinator,
            'residential_address' => $residential_address,
            'area'                => $area,
            'city'                => $city,
            'employment_status'   => $employment_status,
            'occupation'          => $occupation,
            'industry_sector'     => $industry_sector,
            'years_of_experience' => $years_of_experience,
            'is_volunteer'        => $is_volunteer,
            'nick_name'           => $nick_name,
            'state'               => $state,
        ];

        if (!empty($chapter_id)) {
            $additional_data['chapter_id'] = $chapter_id;
        }

        $group = ['2']; // default alumni group

        if ($this->ion_auth->email_check($email)) {
            $data['status']  = 409;
            $data['message'] = 'Email already exists, kindly use another email';
            $this->output->set_status_header(409);
        } else {
            $user_id = $this->ion_auth->register($email, $password, $email, $additional_data, $group);

            if ($user_id) {

                // 1. Insert into alumni_category
                $this->db->insert('alumni_category', [
                    'user_id'    => $user_id,
                    'chapter_id' => $chapter_id ?: null,
                    'year'       => $year,
                    'location'   => $city ?: '',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

                // 2. Insert into user_profiles (seed with city/country if provided)
                $this->db->insert('user_profiles', [
                    'user_id'          => $user_id,
                    'chapter_id'       => $chapter_id ?: null,
                    'year'             => $year,
                    'city'             => $city ?: null,
                    'is_visible'       => 0,
                    'field_visibility' => json_encode(['avatar'=>false,'phone'=>false,'alternative_phone'=>false,'birth_date'=>false,'residential_address'=>false,'area'=>false,'city'=>false,'employment_status'=>false,'occupation'=>false,'industry_sector'=>false,'years_of_experience'=>false,'is_volunteer'=>false]),
                    'created_at'       => date('Y-m-d H:i:s'),
                ]);

                // 3. Handle optional avatar upload
                $avatar = null;
                if (!empty($_FILES['avatar']['name'])) {
                    $uploaded = $this->uploadfiles('avatar', $user_id, 'profile_image');
                    if ($uploaded) {
                        $avatar = site_url($uploaded[0]->attachment_file);
                        $this->db->where('id', $user_id);
                        $this->db->update('users', ['avatar' => $uploaded[0]->attachment_file]);
                    }
                }

                // 4. Send email verification code
                $this->sendVerifyEmail($email, $fullname, $raw_code);

                // 5. If a voucher was selected, create a pending vouch record
                // NOTE: voucher is NOT emailed here — they are emailed after the registrant verifies their email
                if ($voucher_id > 0) {
                    $voucher_user = $this->db->get_where('users', ['id' => $voucher_id, 'voucher' => 'yes', 'active' => 1])->row();
                    if ($voucher_user) {
                        $this->db->insert('vouches', [
                            'register_id' => $user_id,
                            'voucher_id'  => $voucher_id,
                            'status'      => 'pending',
                            'created_at'  => date('Y-m-d H:i:s'),
                            'updated_at'  => date('Y-m-d H:i:s'),
                        ]);
                    }
                }

                $data = [
                    'status'  => 200,
                    'message' => 'Registration successful. Please check your email for your verification code.',

                    // core
                    'user_id'             => $user_id,
                    'user_code'           => $user_code,
                    'email'               => $email,
                    'fullname'            => $fullname,
                    'first_name'          => $first_name,
                    'last_name'           => $last_name,
                    'user_role'           => $user_role,
                    'phone'               => $phone,
                    'chapter_id'          => $chapter_id ?: null,
                    'year'                => $year,
                    'graduation_year'     => $graduation_year,
                    'department'          => $department,
                    'email_verified'      => 0,
                    'avatar'              => $avatar,

                    // NEW alumni membership fields
                    'name_in_school'      => $name_in_school,
                    'alternative_phone'   => $alternative_phone,
                    'birth_date'          => $birth_date ?: null,
                    'house_color'         => $house_color,
                    'is_coordinator'      => (bool) $is_coordinator,
                    'residential_address' => $residential_address,
                    'area'                => $area,
                    'city'                => $city,
                    'employment_status'   => $employment_status,
                    'occupation'          => $occupation,
                    'industry_sector'     => $industry_sector,
                    'years_of_experience' => $years_of_experience,
                    'is_volunteer'        => (bool) $is_volunteer,
                    'nick_name'           => $nick_name,
                    'state'               => $state,
                ];

                $this->output->set_status_header(200);
            } else {
                $data['status']  = 400;
                $data['message'] = 'Registration failed, please try again';
                $this->output->set_status_header(400);
            }
        }

        log_message('error', 'Before Sending out');
        log_message('error', var_export($data, true));

        header('Content-Type: application/json');
        echo json_encode($data);
    }

    private function update_document($user_id, $file, $table_name, $identity_column)
    {
        $upd_data = array(
            $identity_column => $file
        );
        $this->db->update($table_name, $upd_data, array('id' => $user_id));
    }

    public function create_market()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->post();

        log_message('error', "Create Market Input: " . var_export($object, true));

        $token = isset($object['token']) ? trim($object['token']) : '';
        $confirmToken = $this->checkAPI_token_from_header();

        if (!$confirmToken) {
            $this->output->set_status_header(403);
            echo json_encode(['status' => 403, 'message' => 'Invalid API token']);
            return;
        }

        $user_id       = isset($object['user_id']) ? trim($object['user_id']) : null;
        $business_name = isset($object['business_name']) ? trim($object['business_name']) : null;
        $location      = isset($object['location']) ? trim($object['location']) : null;
        $phone         = isset($object['phone']) ? trim($object['phone']) : null;
        $description   = isset($object['description']) ? trim($object['description']) : null;

        if (!$user_id || !$business_name || !$location) {
            $this->output->set_status_header(400);
            echo json_encode(['status' => 400, 'message' => 'Missing required fields']);
            return;
        }

        // Handle image upload if provided
        $image_url = null;
        if (!empty($_FILES['image_url']['name'])) {
            $uploaded = $this->uploadfiles('image_url', 'market', 'market_images');
            if ($uploaded) {
                $image_url = site_url($uploaded[0]->attachment_file);
            }
        }

        $insertData = [
            'user_id'       => $user_id,
            'business_name' => $business_name,
            'location'      => $location,
            'phone'         => $phone,
            'description'   => $description,
            'image_url'     => $image_url,
        ];

        $this->db->insert('market', $insertData);
        $market_id = $this->db->insert_id();

        if ($market_id) {
            $response = [
                'status' => 200,
                'message' => 'Marketplace Info Successfully Created',
                'market' => array_merge(['id' => $market_id], $insertData)
            ];
        } else {
            $response = ['status' => 500, 'message' => 'Market creation failed'];
        }

        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public function manage_market()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->post();

        $token = isset($object['token']) ? trim($object['token']) : '';
        $confirmToken = $this->checkAPI_token_from_header();

        if (!$confirmToken) {
            $this->output->set_status_header(403);
            echo json_encode(['status' => 403, 'message' => 'Invalid API token']);
            return;
        }

        $market_id     = isset($object['id']) ? intval($object['id']) : 0;
        $function_type = isset($object['function_type']) ? strtolower(trim($object['function_type'])) : '';

        if (!$market_id) {
            echo json_encode(['status' => 400, 'message' => 'Market ID is required']);
            return;
        }

        if ($function_type === 'delete') {
            $this->db->where('id', $market_id)->delete('market');
            $response = ['status' => 200, 'message' => 'Marketplace Info Successfully Deleted'];
        } elseif ($function_type === 'update') {
            $updateData = [];

            $fields = ['business_name', 'location', 'phone', 'description', 'user_id'];
            foreach ($fields as $field) {
                if (isset($object[$field]) && $object[$field] !== '') {
                    $updateData[$field] = trim($object[$field]);
                }
            }

            // Optional image update
            if (!empty($_FILES['image_url']['name'])) {
                $uploaded = $this->uploadfiles('image_url', 'market', 'market_images');
                if ($uploaded) {
                    $updateData['image_url'] = site_url($uploaded[0]->attachment_file);
                }
            }

            if (!empty($updateData)) {
                $this->db->where('id', $market_id)->update('market', $updateData);
                $response = [
                    'status' => 200,
                    'message' => 'Market updated successfully',
                    'updated_market' => array_merge(['id' => $market_id], $updateData)
                ];
            } else {
                $response = ['status' => 400, 'message' => 'Nothing to update'];
            }
        } else {
            $response = ['status' => 400, 'message' => 'Invalid function type. Use update or delete'];
        }

        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public function get_market()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->post();

        $token = isset($object['token']) ? trim($object['token']) : '';
        $confirmToken = $this->checkAPI_token_from_header();

        if (!$confirmToken) {
            $this->output->set_status_header(403);
            echo json_encode(['status' => 403, 'message' => 'Invalid API token']);
            return;
        }

        $id = isset($object['id']) ? intval($object['id']) : 0;
        $user_id = isset($object['user_id']) ? intval($object['user_id']) : 0;

        if ($id > 0) {
            $market = $this->db->get_where('market', ['id' => $id])->row();
            if ($market) {
                $response = [
                    'status' => 200,
                    'message' => 'Market retrieved successfully',
                    'market' => $market
                ];
            } else {
                $response = ['status' => 404, 'message' => 'Market not found'];
            }
        } else {
            // if ($user_id > 0) {
            //     $this->db->where('user_id', $user_id);
            // }
            $this->db->order_by('created_at', 'DESC');
            $markets = $this->db->get('market')->result();

            if (!empty($markets)) {
                $response = [
                    'status' => 200,
                    'message' => 'Markets retrieved successfully',
                    'data' => $markets
                ];
            } else {
                $response = ['status' => 404, 'message' => 'No markets found'];
            }
        }

        header('Content-Type: application/json');
        echo json_encode($response);
    }
    public function create_announcement()
    {        
        $jwt_user = $this->authorize();

        if (!$jwt_user) {
            return;
        } 

        $contentType = $this->input->server('CONTENT_TYPE');
        if (strpos($contentType, 'application/json') !== false) {
            $object = json_decode(file_get_contents("php://input"), true);
        } else {
            $object = $this->input->post();
        }

        $title      = isset($object['title'])      ? trim($object['title'])      : null;
        $content    = isset($object['content'])    ? trim($object['content'])    : null;
        $type       = isset($object['type'])       ? trim($object['type'])       : 'info';
        $chapter_id = isset($object['chapter_id']) ? intval($object['chapter_id']) : null;
        $year       = isset($object['year'])       ? trim($object['year'])       : null;
        $starts_at  = isset($object['starts_at'])  ? trim($object['starts_at'])  : null;
        $ends_at    = isset($object['ends_at'])    ? trim($object['ends_at'])    : null;

        if (!$title || !$content) {
            $this->output->set_status_header(400);
            echo json_encode(['status' => 400, 'message' => 'Missing required fields: title, content']);
            return;
        }

        $valid_types = ['info', 'warning', 'success', 'event'];
        if (!in_array($type, $valid_types)) {
            $this->output->set_status_header(400);
            echo json_encode(['status' => 400, 'message' => 'Invalid type. Must be: info, warning, success, event']);
            return;
        }

        // Single image upload
        $imag = '';
        $fileKey = !empty($_FILES['image']['name']) ? 'image'
                 : (!empty($_FILES['images']['name']) ? 'images' : null);

        if ($fileKey) {
            $uploadPath = FCPATH . 'uploads/announcements/';
            if (!is_dir($uploadPath)) mkdir($uploadPath, 0777, true);

            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ext     = strtolower(pathinfo(basename($_FILES[$fileKey]['name']), PATHINFO_EXTENSION));

            if (in_array($ext, $allowed) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                $newName = bin2hex(random_bytes(16)) . '.' . $ext;
                if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $uploadPath . $newName)) {
                    $imag = base_url('uploads/announcements/' . $newName);
                } else {
                    log_message('error', 'create_announcement: move_uploaded_file failed for ' . $uploadPath . $newName);
                }
            } else {
                log_message('error', 'create_announcement: upload rejected ext=' . $ext . ' error=' . $_FILES[$fileKey]['error']);
            }
        }

        $data = [
            'created_by' => $jwt_user->user_id,
            'title'      => $title,
            'content'    => $content,
            'imag'       => $imag,
            'type'       => $type,
            'year'       => $year,
            'starts_at'  => $starts_at  ?: null,
            'ends_at'    => $ends_at    ?: null,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if ($chapter_id !== null) {
            $data['chapter_id'] = $chapter_id;
        }

        $this->db->insert('announcements', $data);
        $insert_id = $this->db->insert_id();

        $response_data = array_merge(['id' => $insert_id], $data);
        $response_data['images'] = $response_data['imag'];
        unset($response_data['imag']);

        $this->output->set_status_header(200);
        header('Content-Type: application/json');
        echo json_encode([
            'status'  => 200,
            'message' => 'Announcement created successfully',
            'data'    => $response_data,
        ]);
    }

    public function manage_announcement()
    {
        $jwt_user = $this->authorize();

        if (!$jwt_user) {
            return;
        } 

        $contentType = $this->input->server('CONTENT_TYPE');
        if (strpos($contentType, 'application/json') !== false) {
            $object = json_decode(file_get_contents("php://input"), true);
        } else {
            $object = $this->input->post();
        }

        $function_type = isset($object['function_type']) ? strtolower(trim($object['function_type'])) : '';
        $id            = isset($object['id'])            ? intval($object['id'])                       : null;

        if (!$function_type || !$id) {
            $this->output->set_status_header(400);
            echo json_encode(['status' => 400, 'message' => 'Missing function_type or id']);
            return;
        }

        if ($function_type === 'update') {
            $update_data = [];

            if (!empty($object['title']))      $update_data['title']      = trim($object['title']);
            if (!empty($object['content']))    $update_data['content']    = trim($object['content']);
            if (!empty($object['type']))       $update_data['type']       = trim($object['type']);
            if (isset($object['chapter_id']))  $update_data['chapter_id'] = $object['chapter_id'] !== '' ? intval($object['chapter_id']) : null;
            if (isset($object['year']))        $update_data['year']       = $object['year'] !== '' ? trim($object['year']) : null;
            if (isset($object['starts_at']))   $update_data['starts_at']  = $object['starts_at'] !== '' ? trim($object['starts_at']) : null;
            if (isset($object['ends_at']))     $update_data['ends_at']    = $object['ends_at'] !== '' ? trim($object['ends_at']) : null;

            // Replace single image if provided
            $fileKey = !empty($_FILES['image']['name']) ? 'image'
                     : (!empty($_FILES['images']['name']) ? 'images' : null);

            if ($fileKey) {
                $uploadPath = FCPATH . 'uploads/announcements/';
                if (!is_dir($uploadPath)) mkdir($uploadPath, 0777, true);

                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $ext     = strtolower(pathinfo(basename($_FILES[$fileKey]['name']), PATHINFO_EXTENSION));

                if (in_array($ext, $allowed) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                    $newName = bin2hex(random_bytes(16)) . '.' . $ext;
                    if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $uploadPath . $newName)) {
                        $update_data['imag'] = base_url('uploads/announcements/' . $newName);
                    } else {
                        log_message('error', 'manage_announcement: move_uploaded_file failed for ' . $uploadPath . $newName);
                    }
                } else {
                    log_message('error', 'manage_announcement: upload rejected ext=' . $ext . ' error=' . $_FILES[$fileKey]['error']);
                }
            }

            if (empty($update_data)) {
                $this->output->set_status_header(400);
                echo json_encode(['status' => 400, 'message' => 'No fields provided to update']);
                return;
            }

            $update_data['updated_at'] = date('Y-m-d H:i:s');
            $this->db->where('id', $id)->update('announcements', $update_data);

            $response = ['status' => 200, 'message' => 'Announcement updated successfully'];
            $this->output->set_status_header(200);

        } elseif ($function_type === 'delete') {
            $exists = $this->db->where('id', $id)->count_all_results('announcements');
            if (!$exists) {
                $this->output->set_status_header(404);
                echo json_encode(['status' => 404, 'message' => 'Announcement not found']);
                return;
            }

            $this->db->where('id', $id)->delete('announcements');
            $response = ['status' => 200, 'message' => 'Announcement deleted successfully'];
            $this->output->set_status_header(200);

        } else {
            $response = ['status' => 400, 'message' => 'Invalid function_type. Use: update or delete'];
            $this->output->set_status_header(400);
        }

        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public function get_announcements()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->post();

        $confirmToken = $this->checkAPI_token_from_header();
        if (!$confirmToken) {
            $this->output->set_status_header(403);
            echo json_encode(['status' => 403, 'message' => 'Invalid API token']);
            return;
        }

        $id         = isset($object['id'])         ? intval($object['id'])         : null;
        $created_by = isset($object['created_by']) ? intval($object['created_by']) : null;
        $type       = isset($object['type'])       ? trim($object['type'])         : null;
        $chapter_id = isset($object['chapter_id']) ? intval($object['chapter_id']) : null;
        $year       = isset($object['year'])       ? intval($object['year'])       : null;

        $this->db->select('announcements.*, announcements.imag AS images, CONCAT(u.first_name, " ", u.last_name) AS created_by_name');
        $this->db->from('announcements');
        $this->db->join('users u', 'u.id = announcements.created_by', 'left');
        $this->db->order_by('announcements.created_at', 'DESC');

        if ($id)         $this->db->where('announcements.id', $id);
        if ($created_by) $this->db->where('announcements.created_by', $created_by);
        if ($type)       $this->db->where('announcements.type', $type);
        if ($chapter_id) $this->db->where('announcements.chapter_id', $chapter_id);
        if ($year)       $this->db->where('announcements.year', $year);

        $result = $this->db->get()->result_array();

        if ($result) {
            $response = [
                'status'  => 200,
                'message' => 'Announcements retrieved successfully',
                'data'    => $result
            ];
        } else {
            $response = [
                'status'  => 404,
                'message' => 'No announcements found',
                'data'    => []
            ];
        }

        header('Content-Type: application/json');
        echo json_encode($response);
    }

    /*=============================================================
    |  CONTACT US
    |  POST /api/contact_us
    |  Auth: API token only (no JWT — public form)
    |=============================================================*/
    public function contact_us()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents('php://input'), true)
            : $this->input->post();

        $confirmToken = $this->checkAPI_token_from_header();
        if (!$confirmToken) {
            $this->output->set_status_header(403);
            echo json_encode(['status' => 403, 'message' => 'Invalid API token']);
            return;
        }

        // Validate required fields
        $first_name = isset($object['firstName']) ? trim($object['firstName']) : '';
        $last_name  = isset($object['lastName'])  ? trim($object['lastName'])  : '';
        $email      = isset($object['email'])     ? trim($object['email'])     : '';
        $message    = isset($object['message'])   ? trim($object['message'])   : '';

        if (!$first_name || !$last_name || !$email || !$message) {
            $this->output->set_status_header(400);
            echo json_encode(['status' => 400, 'message' => 'All fields are required: firstName, lastName, email, message']);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->output->set_status_header(400);
            echo json_encode(['status' => 400, 'message' => 'Invalid email address']);
            return;
        }

        // Save to DB
        $inserted = $this->db->insert('contact_us', [
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'email'      => $email,
            'message'    => $message,
            'status'     => 'new',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$inserted) {
            $this->output->set_status_header(500);
            echo json_encode(['status' => 500, 'message' => 'Failed to save your message. Please try again.']);
            return;
        }

        // Send email to all admins
        $admins      = $this->getManagers();
        $full_name   = $first_name . ' ' . $last_name;
        $subject     = 'New FGGC Alumni Portal Contact Form Message from ' . $full_name;
        $any_sent    = false;

        foreach ($admins as $admin) {
            $data = [
                'subject_title' => $subject,
                'subject_name'  => $admin->fullname,
                'msg_body'      => "
                    <p>You have received a new message via the FGGC Alumni Portal contact form.</p>
                    <table style='width:100%;border-collapse:collapse;font-size:15px;color:#374151;'>
                        <tr><td style='padding:6px 0;font-weight:600;width:100px;'>Name:</td><td style='padding:6px 0;'>{$full_name}</td></tr>
                        <tr><td style='padding:6px 0;font-weight:600;'>Email:</td><td style='padding:6px 0;'><a href='mailto:{$email}' style='color:#0077cc;'>{$email}</a></td></tr>
                        <tr><td style='padding:6px 0;font-weight:600;vertical-align:top;'>Message:</td><td style='padding:6px 0;'>" . nl2br(htmlspecialchars($message)) . "</td></tr>
                    </table>
                    <p style='margin-top:20px;font-size:13px;color:#6b7280;'>Reply directly to this email to respond to {$first_name}.</p>
                    <p style='text-align:center;margin-top:24px;'>
                        <a href='{$this->srvlink}' style='display:inline-block;padding:12px 28px;background-color:#0077cc;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;font-size:15px;'>Go to Admin Panel</a>
                    </p>
                ",
            ];

            $body = $this->load->view('auth/email/template', $data, TRUE);

            $this->email->clear();
           $this->email->from('nubiavilleprojects@gmail.com', 'FGGC Alumni Portal');
            $this->email->reply_to($email, $full_name);
            $this->email->to($admin->email);
            $this->email->bcc('nubiavilleprojects@gmail.com');
            $this->email->set_newline("\r\n");
            $this->email->set_crlf("\r\n");
            $this->email->mailtype = 'html';
            $this->email->subject($subject);
            $this->email->message($body);

            if ($this->email->send(FALSE)) {
                $any_sent = true;
            } else {
                log_message('error', 'Contact-us email not sent to admin: ' . $admin->email);
            }
        }

        if (!$any_sent && !empty($admins)) {
            $this->output->set_status_header(500);
            echo json_encode(['status' => 500, 'message' => 'Message saved but email notification failed. We will get back to you soon.']);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode(['status' => 200, 'message' => 'Your message has been sent successfully. We will get back to you soon.']);
    }

    /*=============================================================
    |  CREATE VACANCY
    |  POST /api/create_vacancy
    |  Auth: X-API-Key + JWT
    |=============================================================*/
    public function create_vacancy()
    {
        $jwt_user = $this->authorize();

        if (!$jwt_user) {
            return;
        } 

        $contentType = $this->input->server('CONTENT_TYPE') ?? '';
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents('php://input'), true)
            : $this->input->post();

        $job_title   = isset($object['job_title'])   ? trim($object['job_title'])   : '';
        $company_name= isset($object['company_name'])? trim($object['company_name']): '';

        if (!$job_title || !$company_name) {
            $this->output->set_status_header(400);
            echo json_encode(['status' => 400, 'message' => 'job_title and company_name are required']);
            return;
        }

        $valid_job_types      = ['full_time','part_time','contract','internship','freelance'];
        $valid_workplace_types = ['remote','hybrid','on_site'];
        $valid_expertise      = ['entry_level','mid_level','senior_level','executive'];
        $valid_app_types      = ['email','link'];

        $job_type          = in_array($object['job_type']          ?? '', $valid_job_types)       ? $object['job_type']          : 'full_time';
        $workplace_type    = in_array($object['workplace_type']    ?? '', $valid_workplace_types)  ? $object['workplace_type']    : 'remote';
        $level_of_expertise= in_array($object['level_of_expertise']?? '', $valid_expertise)       ? $object['level_of_expertise']: 'entry_level';
        $application_type  = in_array($object['application_type'] ?? '', $valid_app_types)        ? $object['application_type']  : 'email';

        $application_email = isset($object['application_email']) ? trim($object['application_email']) : null;
        $application_link  = isset($object['application_link'])  ? trim($object['application_link'])  : null;

        if ($application_type === 'email' && (!$application_email || !filter_var($application_email, FILTER_VALIDATE_EMAIL))) {
            $this->output->set_status_header(400);
            echo json_encode(['status' => 400, 'message' => 'A valid application_email is required when application_type is email']);
            return;
        }
        if ($application_type === 'link' && !$application_link) {
            $this->output->set_status_header(400);
            echo json_encode(['status' => 400, 'message' => 'application_link is required when application_type is link']);
            return;
        }

        // Flyer upload
        $flyer = null;
        if (!empty($_FILES['flyer']['name'])) {
            $upload_dir = './uploads/vacancies/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $this->load->library('upload', [
                'upload_path'   => $upload_dir,
                'allowed_types' => 'jpg|jpeg|png|gif|webp',
                'max_size'      => 5120,
                'encrypt_name'  => true,
            ]);

            if (!$this->upload->do_upload('flyer')) {
                $this->output->set_status_header(400);
                echo json_encode(['status' => 400, 'message' => $this->upload->display_errors('', '')]);
                return;
            }
            $flyer = base_url('uploads/vacancies/' . $this->upload->data()['file_name']);
        }

        $data = [
            'user_id'            => $jwt_user->user_id,
            'chapter_id'         => isset($object['chapter_id']) ? intval($object['chapter_id']) : 1,
            'job_title'          => $job_title,
            'company_name'       => $company_name,
            'job_type'           => $job_type,
            'workplace_type'     => $workplace_type,
            'level_of_expertise' => $level_of_expertise,
            'location'           => isset($object['location'])           ? trim($object['location'])           : null,
            'salary'             => isset($object['salary'])             ? trim($object['salary'])             : null,
            'application_deadline'=> isset($object['application_deadline']) && $object['application_deadline'] ? $object['application_deadline'] : null,
            'keywords'           => isset($object['keywords'])           ? trim($object['keywords'])           : null,
            'about_role'         => isset($object['about_role'])         ? $object['about_role']               : null,
            'responsibilities'   => isset($object['responsibilities'])   ? $object['responsibilities']         : null,
            'requirements'       => isset($object['requirements'])       ? $object['requirements']             : null,
            'application_type'   => $application_type,
            'application_email'  => $application_email,
            'application_link'   => $application_link,
            'flyer'              => $flyer,
            'created_at'         => date('Y-m-d H:i:s'),
        ];

        $this->db->insert('job_vacancies', $data);
        $new_id = $this->db->insert_id();

        if (!$new_id) {
            $this->output->set_status_header(500);
            echo json_encode(['status' => 500, 'message' => 'Failed to create vacancy']);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode(['status' => 200, 'message' => 'Vacancy created successfully', 'id' => $new_id]);
    }

    /*=============================================================
    |  MANAGE VACANCY (UPDATE / DELETE)
    |  POST /api/manage_vacancy
    |  Auth: X-API-Key + JWT
    |=============================================================*/
    public function manage_vacancy()
    {
        $jwt_user = $this->authorize();

        if (!$jwt_user) {
            return;
        } 

        $contentType = $this->input->server('CONTENT_TYPE') ?? '';
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents('php://input'), true)
            : $this->input->post();

        $function_type = isset($object['function_type']) ? trim($object['function_type']) : '';
        $id            = isset($object['id'])            ? intval($object['id'])           : 0;

        if (!$id) {
            $this->output->set_status_header(400);
            echo json_encode(['status' => 400, 'message' => 'id is required']);
            return;
        }

        $vacancy = $this->db->where('id', $id)->get('job_vacancies')->row();
        if (!$vacancy) {
            $this->output->set_status_header(404);
            echo json_encode(['status' => 404, 'message' => 'Vacancy not found']);
            return;
        }

        if ($function_type === 'update') {
            $valid_job_types       = ['full_time','part_time','contract','internship','freelance'];
            $valid_workplace_types = ['remote','hybrid','on_site'];
            $valid_expertise       = ['entry_level','mid_level','senior_level','executive'];
            $valid_app_types       = ['email','link'];

            $update = [];

            if (!empty($object['job_title']))    $update['job_title']    = trim($object['job_title']);
            if (!empty($object['company_name'])) $update['company_name'] = trim($object['company_name']);
            if (isset($object['location']))      $update['location']     = trim($object['location']);
            if (isset($object['salary']))        $update['salary']       = trim($object['salary']);
            if (isset($object['keywords']))      $update['keywords']     = trim($object['keywords']);
            if (isset($object['about_role']))    $update['about_role']   = $object['about_role'];
            if (isset($object['responsibilities'])) $update['responsibilities'] = $object['responsibilities'];
            if (isset($object['requirements']))  $update['requirements'] = $object['requirements'];
            if (isset($object['application_deadline']) && $object['application_deadline'])
                $update['application_deadline'] = $object['application_deadline'];
            if (isset($object['chapter_id']))    $update['chapter_id']   = intval($object['chapter_id']);

            if (isset($object['job_type']) && in_array($object['job_type'], $valid_job_types))
                $update['job_type'] = $object['job_type'];
            if (isset($object['workplace_type']) && in_array($object['workplace_type'], $valid_workplace_types))
                $update['workplace_type'] = $object['workplace_type'];
            if (isset($object['level_of_expertise']) && in_array($object['level_of_expertise'], $valid_expertise))
                $update['level_of_expertise'] = $object['level_of_expertise'];

            if (isset($object['application_type']) && in_array($object['application_type'], $valid_app_types)) {
                $update['application_type'] = $object['application_type'];
                if ($object['application_type'] === 'email') {
                    $update['application_email'] = isset($object['application_email']) ? trim($object['application_email']) : null;
                    $update['application_link']  = null;
                } else {
                    $update['application_link']  = isset($object['application_link']) ? trim($object['application_link']) : null;
                    $update['application_email'] = null;
                }
            }

            // Replace flyer if new file uploaded
            if (!empty($_FILES['flyer']['name'])) {
                $upload_dir = './uploads/vacancies/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

                $this->load->library('upload', [
                    'upload_path'   => $upload_dir,
                    'allowed_types' => 'jpg|jpeg|png|gif|webp',
                    'max_size'      => 5120,
                    'encrypt_name'  => true,
                ]);

                if (!$this->upload->do_upload('flyer')) {
                    $this->output->set_status_header(400);
                    echo json_encode(['status' => 400, 'message' => $this->upload->display_errors('', '')]);
                    return;
                }
                $update['flyer'] = base_url('uploads/vacancies/' . $this->upload->data()['file_name']);
            }

            if (empty($update)) {
                $this->output->set_status_header(400);
                echo json_encode(['status' => 400, 'message' => 'No fields provided to update']);
                return;
            }

            $update['updated_at'] = date('Y-m-d H:i:s');
            $this->db->where('id', $id)->update('job_vacancies', $update);

            $response = ['status' => 200, 'message' => 'Vacancy updated successfully'];

        } elseif ($function_type === 'delete') {
            $this->db->where('id', $id)->delete('job_vacancies');
            $response = ['status' => 200, 'message' => 'Vacancy deleted successfully'];

        } else {
            $this->output->set_status_header(400);
            echo json_encode(['status' => 400, 'message' => 'Invalid function_type. Use: update or delete']);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode($response);
    }

    /*=============================================================
    |  GET VACANCIES
    |  POST /api/get_vacancies
    |  Auth: X-API-Key only (public read)
    |=============================================================*/
    public function get_vacancies()
    {
        $confirmToken = $this->checkAPI_token_from_header();
        if (!$confirmToken) {
            $this->output->set_status_header(403);
            echo json_encode(['status' => 403, 'message' => 'Invalid API token']);
            return;
        }

        $contentType = $this->input->server('CONTENT_TYPE') ?? '';
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents('php://input'), true)
            : $this->input->post();

        $id                = isset($object['id'])                 ? intval($object['id'])          : null;
        $user_id           = isset($object['user_id'])            ? intval($object['user_id'])     : null;
        $chapter_id        = isset($object['chapter_id'])         ? intval($object['chapter_id'])  : null;
        $job_type          = isset($object['job_type'])           ? trim($object['job_type'])      : null;
        $workplace_type    = isset($object['workplace_type'])     ? trim($object['workplace_type']): null;
        $level_of_expertise= isset($object['level_of_expertise']) ? trim($object['level_of_expertise']) : null;

        $this->db->select('jv.*, CONCAT(u.first_name, " ", u.last_name) AS posted_by');
        $this->db->from('job_vacancies jv');
        $this->db->join('users u', 'u.id = jv.user_id', 'left');
        $this->db->order_by('jv.created_at', 'DESC');

        if ($id)                $this->db->where('jv.id', $id);
        if ($user_id)           $this->db->where('jv.user_id', $user_id);
        if ($chapter_id)        $this->db->where('jv.chapter_id', $chapter_id);
        if ($job_type)          $this->db->where('jv.job_type', $job_type);
        if ($workplace_type)    $this->db->where('jv.workplace_type', $workplace_type);
        if ($level_of_expertise)$this->db->where('jv.level_of_expertise', $level_of_expertise);

        $result = $this->db->get()->result_array();

        if ($result) {
            $response = ['status' => 200, 'message' => 'Vacancies retrieved successfully', 'data' => $result];
        } else {
            $response = ['status' => 404, 'message' => 'No vacancies found', 'data' => []];
        }

        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public function create_role()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->post();

        $token = isset($object['token']) ? trim($object['token']) : '';
        $confirmToken = $this->checkAPI_token_from_header();

        if (!$confirmToken) {
            $this->output->set_status_header(403);
            echo json_encode(['status' => 403, 'message' => 'Invalid API token']);
            return;
        }

        $role_name   = isset($object['role_name']) ? trim($object['role_name']) : null;
        $description = isset($object['description']) ? trim($object['description']) : null;

        if (!$role_name) {
            $this->output->set_status_header(400);
            echo json_encode(['status' => 400, 'message' => 'Missing required field: role_name']);
            return;
        }

        // ✅ Check for duplicate role_name
        $existing = $this->db->get_where('roles', ['role_name' => $role_name])->row();
        if ($existing) {
            $this->output->set_status_header(409);
            echo json_encode(['status' => 409, 'message' => 'Role name already exists']);
            return;
        }

        $data = [
            'role_name'   => $role_name,
            'description' => $description,
            'created_at'  => date('Y-m-d H:i:s')
        ];

        $insert = $this->db->insert('roles', $data);

        if ($insert) {
            $data['id'] = $this->db->insert_id();
            $response = [
                'status'  => 200,
                'message' => 'Role created successfully',
                'data'    => $data
            ];
        } else {
            $response = [
                'status'  => 500,
                'message' => 'Failed to create role',
                'error'   => $this->db->error()['message']
            ];
        }

        $this->output->set_status_header($response['status']);
        header('Content-Type: application/json');
        echo json_encode($response, JSON_UNESCAPED_SLASHES);
    }

    public function manage_role()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->post();

        $token = isset($object['token']) ? trim($object['token']) : '';
        $confirmToken = $this->checkAPI_token_from_header();

        if (!$confirmToken) {
            $this->output->set_status_header(403);
            echo json_encode(['status' => 403, 'message' => 'Invalid API token']);
            return;
        }

        $function_type = isset($object['function_type']) ? strtolower(trim($object['function_type'])) : '';
        $id            = isset($object['id']) ? intval($object['id']) : null;

        if (!$function_type || !$id) {
            $this->output->set_status_header(400);
            echo json_encode(['status' => 400, 'message' => 'Missing function_type or id']);
            return;
        }

        if ($function_type === 'update') {
            $update_data = [];
            if (!empty($object['role_name'])) {
                // ✅ Prevent duplicate role_name (except itself)
                $exists = $this->db
                    ->where('role_name', $object['role_name'])
                    ->where('id !=', $id)
                    ->get('roles')
                    ->row();
                if ($exists) {
                    $this->output->set_status_header(409);
                    echo json_encode(['status' => 409, 'message' => 'Role name already exists']);
                    return;
                }
                $update_data['role_name'] = trim($object['role_name']);
            }

            if (!empty($object['description']))
                $update_data['description'] = trim($object['description']);

            $update_data['updated_at'] = date('Y-m-d H:i:s');

            $this->db->where('id', $id)->update('roles', $update_data);

            $response = ['status' => 200, 'message' => 'Role updated successfully'];
            $this->output->set_status_header(200);
        } elseif ($function_type === 'delete') {
            $this->db->where('id', $id)->delete('roles');
            $response = ['status' => 200, 'message' => 'Role deleted successfully'];
            $this->output->set_status_header(200);
        } else {
            $response = ['status' => 400, 'message' => 'Invalid function_type'];
            $this->output->set_status_header(400);
        }

        header('Content-Type: application/json');
        echo json_encode($response, JSON_UNESCAPED_SLASHES);
    }

    public function get_roles()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->post();

        $token = isset($object['token']) ? trim($object['token']) : '';
        $confirmToken = $this->checkAPI_token_from_header();

        if (!$confirmToken) {
            $this->output->set_status_header(403);
            echo json_encode(['status' => 403, 'message' => 'Invalid API token']);
            return;
        }

        $id = isset($object['id']) ? intval($object['id']) : null;

        if ($id) {
            $this->db->where('id', $id);
            $query = $this->db->get('roles');
            $result = $query->row_array();
        } else {
            $this->db->order_by('created_at', 'DESC');
            $query = $this->db->get('roles');
            $result = $query->result_array();
        }

        if ($result) {
            $response = [
                'status'  => 200,
                'message' => 'Roles retrieved successfully',
                'data'    => $result
            ];
        } else {
            $response = [
                'status'  => '404,get_privacy_policy',
                'message' => 'No roles found'
            ];
        }

        header('Content-Type: application/json');
        echo json_encode($response, JSON_UNESCAPED_SLASHES);
    }

    public function get_setup_parameters()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->post();

        $confirmToken = $this->checkAPI_token_from_header();

        if (!$confirmToken) {
            $this->output->set_status_header(403);
            echo json_encode(['status' => 403, 'message' => 'Invalid API token']);
            return;
        }

        $action_type = isset($object['action_type']) ? trim($object['action_type']) : null;

        if (!$action_type) {
            $response = [
                'status'  => 400,
                'message' => 'action_type parameter is required'
            ];
            header('Content-Type: application/json');
            echo json_encode($response, JSON_UNESCAPED_SLASHES);
            return;
        }

        $this->db->where('setup_name', $action_type);
        $query = $this->db->get('setup_parameters');
        $result = $query->row_array();

        if ($result) {
            $setup_values = array_filter(array_map('trim', explode(',', $result['setup_value'])));
            $response = [
                'status'  => 200,
                'message' => 'Setup parameters retrieved successfully',
                'data'    => [
                    'setup_id'    => $result['setup_id'],
                    'setup_name'  => $result['setup_name'],
                    'setup_value' => $result['setup_value'],
                    'values'      => $setup_values
                ]
            ];
        } else {
            $response = [
                'status'  => 404,
                'message' => 'No setup parameters found for action_type: ' . $action_type
            ];
        }

        header('Content-Type: application/json');
        echo json_encode($response, JSON_UNESCAPED_SLASHES);
    }

    public function create_privacy_policy()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->post();

        $token = isset($object['token']) ? trim($object['token']) : '';
        $confirmToken = $this->checkAPI_token_from_header();

        if (!$confirmToken) {
            $this->output->set_status_header(403);
            echo json_encode(['status' => 403, 'message' => 'Invalid API token']);
            return;
        }

        $section_title     = isset($object['section_title']) ? trim($object['section_title']) : null;
        $subsection_title  = isset($object['subsection_title']) ? trim($object['subsection_title']) : null;
        $content           = isset($object['content']) ? trim($object['content']) : null;
        $sort_order        = isset($object['sort_order']) ? intval($object['sort_order']) : 0;

        if (!$section_title || !$content) {
            $this->output->set_status_header(400);
            echo json_encode(['status' => 400, 'message' => 'Missing required fields']);
            return;
        }

        $data = [
            'section_title'    => $section_title,
            'subsection_title' => $subsection_title,
            'content'          => $content,
            'sort_order'       => $sort_order,
        ];

        $this->db->insert('privacy_policy', $data);

        $response = [
            'status'  => 201,
            'message' => 'Privacy Policy section created successfully',
            'data'    => $data
        ];
        $this->output->set_status_header(201);
        echo json_encode($response);
    }

    public function manage_privacy_policy()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->post();

        $token = isset($object['token']) ? trim($object['token']) : '';
        $confirmToken = $this->checkAPI_token_from_header();

        if (!$confirmToken) {
            $this->output->set_status_header(403);
            echo json_encode(['status' => 403, 'message' => 'Invalid API token']);
            return;
        }

        $function_type = isset($object['function_type']) ? strtolower(trim($object['function_type'])) : '';
        $id = isset($object['id']) ? intval($object['id']) : 0;

        if (!$function_type || !$id) {
            $this->output->set_status_header(400);
            echo json_encode(['status' => 400, 'message' => 'Missing function_type or id']);
            return;
        }

        if ($function_type === 'update') {
            $updateData = [];
            if (!empty($object['section_title']))     $updateData['section_title'] = $object['section_title'];
            if (!empty($object['subsection_title']))  $updateData['subsection_title'] = $object['subsection_title'];
            if (!empty($object['content']))           $updateData['content'] = $object['content'];
            if (isset($object['sort_order']))         $updateData['sort_order'] = intval($object['sort_order']);

            $updateData['updated_at'] = date('Y-m-d H:i:s');

            $this->db->where('id', $id)->update('privacy_policy', $updateData);
            $response = ['status' => 200, 'message' => 'Privacy Policy section updated successfully', 'data' => $updateData];
        } elseif ($function_type === 'delete') {
            $this->db->delete('privacy_policy', ['id' => $id]);
            $response = ['status' => 200, 'message' => 'Privacy Policy section deleted successfully'];
        } else {
            $response = ['status' => 400, 'message' => 'Invalid function_type'];
        }

        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public function get_privacy_policy()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->get();

        $token = isset($object['token']) ? trim($object['token']) : '';
        $confirmToken = $this->checkAPI_token_from_header();

        if (!$confirmToken) {
            $this->output->set_status_header(403);
            echo json_encode(['status' => 403, 'message' => 'Invalid API token']);
            return;
        }

        $id = isset($object['id']) ? intval($object['id']) : 0;

        if ($id) {
            $policy = $this->db->get_where('privacy_policy', ['id' => $id])->row_array();
            if ($policy) {
                $response = ['status' => 200, 'message' => 'Privacy Policy section retrieved successfully', 'data' => $policy];
            } else {
                $response = ['status' => 404, 'message' => 'Privacy Policy section not found'];
            }
        } else {
            $policies = $this->db->order_by('sort_order', 'ASC')->get('privacy_policy')->result_array();
            if (!empty($policies)) {
                $response = ['status' => 200, 'message' => 'All Privacy Policy sections retrieved successfully', 'data' => $policies];
            } else {
                $response = ['status' => 404, 'message' => 'No Privacy Policy sections found'];
            }
        }

        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public function manage_user_account()
    {
        $object   = json_decode(file_get_contents('php://input'), true);
        $token    = trim($object['token'] ?? '');
        $user_id  = intval($object['user_id'] ?? 0);
        $action   = strtolower(trim($object['action'] ?? ''));   // 'activate' | 'deactivate'
        $new_role = trim($object['user_role'] ?? '');

        // --- API token ---        
        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        } 

        $jwt_user_id   = (int)$jwtData->user_id;
        $jwt_user_role = strtolower($jwtData->user_role ?? '');
        $is_admin      = in_array($jwt_user_role, ['admin', 'manager', 'superadmin']);

        // --- Resolve target user ---
        $target_id = $user_id > 0 ? $user_id : $jwt_user_id;

        // --- Permission rules ---
        if (!$is_admin) {
            // Regular users may only deactivate their own account
            if ($target_id !== $jwt_user_id) {
                $this->output->set_status_header(403);
                header('Content-Type: application/json');
                echo json_encode(['status' => 403, 'message' => 'You can only manage your own account']);
                return;
            }
            if ($action === 'activate') {
                $this->output->set_status_header(403);
                header('Content-Type: application/json');
                echo json_encode(['status' => 403, 'message' => 'You cannot activate your own account']);
                return;
            }
            if ($new_role !== '') {
                $this->output->set_status_header(403);
                header('Content-Type: application/json');
                echo json_encode(['status' => 403, 'message' => 'You cannot change your own role']);
                return;
            }
        }

        // --- Validate user_id ---
        if ($target_id === 0) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'User ID is required']);
            return;
        }

        // --- Build update payload ---
        $updateData = [];

        if ($action === 'activate') {
            $updateData['active'] = 1;
        } elseif ($action === 'deactivate') {
            $updateData['active'] = 0;
        }

        if ($is_admin && $new_role !== '') {
            $updateData['user_role'] = $new_role;
        }

        if (empty($updateData)) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'No valid update parameters provided']);
            return;
        }

        // --- Apply update ---
        $this->db->where('id', $target_id)->update('users', $updateData);

        if ($this->db->affected_rows() > 0) {
            $target_user = $this->ion_auth->user($target_id)->row();

            // --- Send email only for activate / deactivate ---
            if ($action === 'activate' || $action === 'deactivate') {
                $actor   = $is_admin ? 'admin' : 'self';
                $subject = $action === 'activate' ? 'Your Account Has Been Activated' : 'Your Account Has Been Deactivated';
                $this->sendUserMail($target_user->email, $subject, $target_user->fullname, $action, $actor);
            }

            $updated_user = $this->db->select('id, fullname, email, phone, user_role, active, profile_status')
                ->get_where('users', ['id' => $target_id])
                ->row_array();

            $message = 'User updated successfully';
            if ($action === 'activate')   $message = 'User account activated';
            if ($action === 'deactivate') $message = 'User account deactivated';
            if ($new_role !== '')          $message .= ' and role updated to ' . $new_role;

            $this->output->set_status_header(200);
            header('Content-Type: application/json');
            echo json_encode(['status' => 200, 'message' => $message, 'user' => $updated_user]);
        } else {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'No changes made or user not found']);
        }
    }

    public function get_users_by_action()
    {
        $object = json_decode(file_get_contents("php://input"), true);

        $token       = isset($object['token']) ? trim($object['token']) : '';
        $action_type = isset($object['action_type']) ? trim($object['action_type']) : null;

        // =========================
        // ✅ TOKEN VALIDATION ONLY
        // =========================
        if (!$this->checkAPI_token_from_header()) {
            $this->output->set_status_header(401);
            echo json_encode(['status' => 401, 'message' => 'Invalid API Token']);
            return;
        }

        // =========================
        // ✅ BASE QUERY (JOIN)
        // =========================
        $this->db->select('
            users.*,
            user_profiles.linkedin,
            user_profiles.twitter,
            user_profiles.tiktok,
            user_profiles.facebook,
            user_profiles.website,
            user_profiles.current_company,
            user_profiles.current_position,
            user_profiles.city,
            user_profiles.country,
            user_profiles.skills,
            user_profiles.achievements,
            user_profiles.year,
            user_profiles.is_visible,
            user_profiles.instagram,
            user_profiles.tiktok,
            user_profiles.field_visibility,
            c.city_id AS zone_city_id,
            c.zone_id,
            z.zone    AS zone_name,
            GROUP_CONCAT(r.role_name SEPARATOR ";") AS system_role

        ');
        $this->db->from('users');
        $this->db->join('user_profiles', 'user_profiles.user_id = users.id', 'left');
        $this->db->join('cities c', 'LOWER(users.city) = LOWER(c.city)', 'left');
        $this->db->join('zones z',  'c.zone_id = z.zone_id', 'left');
        $this->db->join('roles r', 'r.user_id = users.id', 'left');

        // =========================
        // ✅ ACTION TYPE FILTER
        // =========================
        if ($action_type === "approved") {

            $this->db->where('users.email_verified', 1);
            $this->db->where('users.active', 1);
            $this->db->where('users.is_approved', 1);

        } elseif ($action_type === "pending Approval") {

            $this->db->where('users.is_approved', 0);
            $this->db->where('users.email_verified', 1);
            $this->db->where('users.active', 1);
        }else {
            // Default to all users if no valid action_type provided
                $this->db->where('users.email_verified', 1);
            
        }

        // =========================
        // ✅ GROUP BY (for role aggregation)
        // =========================
        $this->db->group_by('users.id');

        // =========================
        // ✅ ORDER
        // =========================
        $this->db->order_by('users.id', 'DESC');

        $results = $this->db->get()->result_array();

        // =========================
        // ✅ FORMAT RESPONSE
        // =========================
        $users = [];

        foreach ($results as $row) {

            $profile = [
                'linkedin'         => $row['linkedin'] ?? null,
                'twitter'          => $row['twitter'] ?? null,
                'facebook'         => $row['facebook'] ?? null,
                'tiktok'         => $row['tiktok'] ?? null,
                'website'          => $row['website'] ?? null,
                'instagram'          => $row['instagram'] ?? null,
                'field_visibility'          => $row['field_visibility'] ?? null,

                'current_company'  => $row['current_company'] ?? null,
                'current_position' => $row['current_position'] ?? null,
                'city'             => $row['city'] ?? null,
                'country'          => $row['country'] ?? null,
                'skills'           => $row['skills'] ?? null,
                'achievements'     => $row['achievements'] ?? null,
                'year'             => $row['year'] ?? null,
                'is_visible'       => isset($row['is_visible']) ? (bool)$row['is_visible'] : true,
                'system_role'      => $row['system_role'] ?? '',
            ];

            // Extract zone fields before unsetting
            $row['zone_id']   = isset($row['zone_id'])      ? (int) $row['zone_id']      : null;
            $row['zone_name'] = $row['zone_name']            ?? null;
            $row['city_id']   = isset($row['zone_city_id']) ? (int) $row['zone_city_id'] : null;

            // Remove profile fields from main row
            unset(
                $row['linkedin'],
                $row['twitter'],
                $row['tiktok'],
                $row['facebook'],
                $row['website'],
                $row['current_company'],
                $row['current_position'],
                $row['city'],
                $row['country'],
                $row['skills'],
                $row['achievements'],
                $row['year'],
                $row['is_visible'],
                $row['instagram'],
                $row['field_visibility'],
                $row['zone_city_id'],
                $row['system_role']
            );

            $row['profile'] = $profile;

            $users[] = $row;
        }

        // =========================
        // ✅ RESPONSE
        // =========================
        echo json_encode([
            'status' => 200,
            'count'  => count($users),
            'users'  => $users
        ]);
    }

    public function update_user_account()
    {
        //$object = json_decode(file_get_contents("php://input"), true);
        //  $contentType = $this->input->server('CONTENT_TYPE');
        //     $object = (strpos($contentType, 'application/json') !== false)
        //         ? json_decode(file_get_contents("php://input"), true)
        //         : $this->input->post();
        $raw = file_get_contents("php://input");
        $json = json_decode($raw, true);

        if (json_last_error() === JSON_ERROR_NONE && !empty($json)) {
            // If client sent JSON
            $object = $json;
        } else {
            // Otherwise, assume form-data (your Postman keys)
            $object = $this->input->post();
        }

        log_message('error', 'RAW INPUT: ' . $raw);
        log_message('error', 'CONTENT TYPE: ' . $this->input->server('CONTENT_TYPE'));

        $token   = isset($object['token']) ? trim($object['token']) : '';
        $user_id = isset($object['user_id']) ? trim($object['user_id']) : '';

        log_message('error', "Update User Account Input: " . var_export($token, true));
        log_message('error', "Update User Account Input Object: " . var_export($object, true));

        // ✅ Validate token  
        if (!$this->checkAPI_token_from_header()) {
            return $this->output
                ->set_status_header(401)
                ->set_output(json_encode(['status' => 401, 'message' => 'Invalid API Token']));
        }

        if (empty($user_id)) {
            return $this->output
                ->set_status_header(400)
                ->set_output(json_encode(['status' => 400, 'message' => 'User ID required']));
        }

        $updateData = [];
        $fields = ['first_name', 'last_name', 'fullname', 'name', 'address', 'phone', 'move_in_date', 'emergency_contact', 'privacy_view', 'image', 'proof_of_document'];

        foreach ($fields as $field) {
            if (isset($object[$field]) && $object[$field] !== '') {
                $updateData[$field] = $object[$field];
            }
        }

        // ✅ File uploads (still via $_FILES if multipart)  
        $upload_path = './uploads/attachments/';
        if (!file_exists($upload_path)) mkdir($upload_path, 0777, true);
        $this->load->library('upload');

        $fileFields = ['profile_image' => 'profile_', 'proof_of_document' => 'proof_', 'image' => 'profile_'];
        foreach ($fileFields as $fileKey => $prefix) {
            if (!empty($_FILES[$fileKey]['name'])) {
                $config = [
                    'upload_path'   => $upload_path,
                    'allowed_types' => ($fileKey === 'image') ? 'jpg|jpeg|png|gif' : 'jpg|jpeg|png|pdf|xlsx|doc|docx',
                    'max_size'      => ($fileKey === 'image') ? 5120 : 4096,  // 5MB for images, 4MB for documents
                    'file_name'     => $prefix . time()
                ];
                $this->upload->initialize($config);

                if ($this->upload->do_upload($fileKey)) {
                    $fileData = $this->upload->data();
                    $updateData[$fileKey] = 'uploads/attachments/' . $fileData['file_name'];
                } else {
                    // return $this->output  
                    //     ->set_status_header(400)  
                    //     ->set_output(json_encode(['status' => 400, 'message' => $this->upload->display_errors()]));  
                    // Remove <p></p> tags
                    $error = strip_tags($this->upload->display_errors());

                    // Show allowed file size
                    $allowedSize = ($fileKey === 'image') ? '5MB' : '4MB';
                    $error .= " Maximum allowed size: $allowedSize.";

                    return $this->output
                        ->set_status_header(400)
                        ->set_output(json_encode([
                            'status' => 400,
                            'message' => $error
                        ]));
                }
            }
        }

        if (empty($updateData)) {
            return $this->output
                ->set_status_header(400)
                ->set_output(json_encode(['status' => 400, 'message' => 'No fields to update']));
        }

        $this->db->where('id', $user_id)->update('users', $updateData);

        // $response = ($this->db->affected_rows() > 0)  
        //     ? ['status' => 200, 'message' => 'User account updated successfully', 'updated_fields' => $updateData]  
        //     : ['status' => 400, 'message' => 'No changes made'];  
        $response =  ['status' => 200, 'message' => 'User account updated successfully', 'updated_fields' => $updateData];
        $this->output->set_content_type('application/json')->set_output(json_encode($response));
    }
    
    public function sendUserOTP($email, $fullname)
    {
        $this->load->library('email', $config);
        //  $mail_acct = $this->ion_auth->user($nxt_appr_id)->row();
        $subject = "Account Verification OTP";
        $to = $email;
        $otp = mt_rand(100000, 999999);
        if ($otp) {

            $response = $this->api_model->create_otp_record($otp, $email);

            if (!$response) {
                // log_message( 'error', 'An error occured while creating otp record' );
                $status_code = 500;
                echo json_encode(['status' => $status_code, 'msg' => 'An error occured while creating otp record, Please try again or contact support.']);
                return;
            }
        }
        //$data['subject_title'] = $subject;
        $data['subject_title'] = $subject;
        $data['subject_name'] = $fullname . ',';
        //$svr_link = $this->srvlink;
        $status_message = ($status == 1) ? "activated  successfully , you can go to the app with the your details "
            : "deactivated successfully , please contact support for more details";
        //   $data['msg_body'] =  "<br/>\nWe wish to inform you that "
        //     . " An Verification email sent successfully, You will receive an otp -" . $otp . " in your mail. "

        //     // . "Email: <b>" . $email . "</b><br/>"
        //     // . "Password: <b>" . $password . "</b><br/><br/>"
        //     . "\n<br/><br/>Warm Regards,<br/><br/> \n\n Estate  Management Notifier";
        $data['msg_body'] = "
        <br/>\n
        <p>We wish to inform you that your verification email has been sent successfully.</p>
        <p>Thank you for registering. Please use the One-Time Password (OTP) below to verify your email address:</p>
        <h3 style='color: #2c3e50;'>$otp</h3>
        
       
        ";
        $this->email->from('nubiavilleprojects@gmail.com', 'Estate Management Notifier');
        $this->email->set_newline("\r\n");
        $this->email->set_crlf("\r\n");
        $this->email->validate = true;
        $this->email->mailtype = 'html';
        $body = $this->load->view('auth/email/template', $data, TRUE);
        // $body=$data['msg_body'];

        $this->email->wordwrap = false;
        $this->email->to($to);
        $this->email->bcc('nubiavilleprojects@gmail.com');
        $this->email->subject($subject);
        $this->email->message($body);
	        if (!$this->email->send(FALSE)) {
	            $this->session->set_flashdata('error', 'Email not sent');
	            $response = [
	                'status' => 500,
	                'msg'    => 'Email not sent'
	            ];
            //;
        } else {
            $this->session->set_flashdata('message', 'Email sent to ' . $to);
            $response = [
                'status' => 200,
                'msg'    => 'Email sent to ' . $to
            ];
            //return;
        }
        return $response;
    }

    public function verify_otp()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->get();

        $token = isset($object['token']) ? trim($object['token']) : '';
        $otp = isset($object['verify_otp']) ? trim($object['verify_otp']) : '';
        $user_id = isset($object['user_id']) ? trim($object['user_id']) : '';
        $confirmToken = $this->checkAPI_token_from_header();

        if (!$confirmToken) {
            $this->output->set_status_header(403);
            echo json_encode(['status' => 403, 'message' => 'Invalid API token']);
            return;
        }
        // Validate required parameters
        if (empty($otp) || empty($token)) {
            echo json_encode(['status' => 400, 'msg' => 'Missing required parameters: otp or token']);
            return;
        }

        // Fetch user by token
        $user = $this->db->get_where('users', ['id' => $user_id])->row_array();

        if (!$user) {
            echo json_encode(['status' => 404, 'msg' => 'Invalid token or user not found']);
            return;
        }

        $otp_info = [
            'otp'   => $otp,
            'email' => $user['email'], // still needed if your base_model uses email for validation
        ];

        // Verify OTP using base_model
        $is_otp_valid = $this->api_model->is_otp_valid($otp_info);

        if ($is_otp_valid['status'] === false) {
            echo json_encode(['status' => 500, 'msg' => $is_otp_valid['message']]);
        } else {
            // ✅ OTP valid — activate user
            $this->db->where('email', $user['email']);
            $this->db->update('users', ['profile_status' => 'active']); 
            $subject = "Access Request Notification";
            $this->sendManagerNotification($user['fullname'], $subject);
            echo json_encode(['status' => 200, 'msg' => 'OTP verified successfully. Profile activated.']);
        }
    }
    public function verify_user_access_code()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->get();

        $token = isset($object['token']) ? trim($object['token']) : '';
        $accesscode = isset($object['access_code']) ? trim($object['access_code']) : '';         
        $user_id = isset($object['user_id']) ? trim($object['user_id']) : '';
        $confirmToken = $this->checkAPI_token_from_header();

        if (!$confirmToken) {
            $this->output->set_status_header(403);
            echo json_encode(['status' => 403, 'message' => 'Invalid API token']);
            return;
        }if (!$accesscode) {
            $this->output->set_status_header(403);
            echo json_encode(['status' => 403, 'message' => 'Please provide access code']);
            return;
        }

        // Fetch user by token
        $user = $this->db->get_where('users', ['id' => $user_id])->row_array();

        if (!$user) {
            echo json_encode(['status' => 404, 'msg' => 'Invalid token or user not found']);
            return;
        }
        //$sendotp = $this->sendUserOTP($user['email'], $user['fullname']);
        if ($user['userAccessCode'] == $accesscode) {
            echo json_encode(['status' => 200, 'msg' => 'Access code verified successfully for user - '.$user['fullname'].', Access Type:'.$user['user_role']]);
            return;
        } else {


            echo json_encode(['status' => 500, 'msg' => 'Invalid access code']);
            return;
        }
    }

    public function resend_otp()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->get();

        $token = isset($object['token']) ? trim($object['token']) : '';

        $user_id = isset($object['user_id']) ? trim($object['user_id']) : '';
        $confirmToken = $this->checkAPI_token_from_header();

        if (!$confirmToken) {
            $this->output->set_status_header(403);
            echo json_encode(['status' => 403, 'message' => 'Invalid API token']);
            return;
        }

        // Fetch user by token
        $user = $this->db->get_where('users', ['id' => $user_id])->row_array();

        if (!$user) {
            echo json_encode(['status' => 404, 'msg' => 'Invalid token or user not found']);
            return;
        }
        $sendotp = $this->sendUserOTP($user['email'], $user['fullname']);
        if ($sendotp['status'] == 500) {
            echo json_encode(['status' => 500, 'msg' => $sendotp['msg']]);
            return;
        } else {


            echo json_encode(['status' => 200, 'msg' => $sendotp['msg']]);
            return;
        }
    }

    public function create_notification()
    {
        $object = json_decode(file_get_contents("php://input"), true);

        $token     = trim($object['token'] ?? '');
        $category  = trim($object['category'] ?? '');
        $title     = trim($object['title'] ?? '');
        $message   = trim($object['message'] ?? '');
        $target_id = $object['target_user_id'] ?? null;

        // Ensure admin
        $confirmToken = $this->checkAPI_token_from_header();
        if (!$confirmToken) {
            echo json_encode(['status' => 401, 'message' => 'Invalid token']);
            return;
        }

        // $admin = $this->db->get_where('users', ['id' => $confirmToken->user_id])->row();
        // if (!$admin || strtolower($admin->user_role) !== 'admin') {
        //     echo json_encode(['status' => 403, 'message' => 'Only admin allowed']);
        //     return;
        // }

        if (!$category || !$title || !$message) {
            echo json_encode(['status' => 400, 'message' => 'Missing fields']);
            return;
        }

        $this->db->insert('notifications', [
            'category' => $category,
            'title'    => $title,
            'message'  => $message,
            'target_user_id' => $target_id
        ]);

        $get_recipients = !empty($target_id) ? $this->api_model->get_recipients($target_id) : $this->api_model->get_recipients();
        $response = !empty($get_recipients) ? send_notification($title, $message, $get_recipients, $url = "") : "This user doesnt have a registered push token";
        //log_message('error', 'message' . json_encode($push_notification_response, true));
        // $get_recipients = $this->api_model->get_recipients();

        //$response = send_notification($title, $message, $get_recipients, $url = "");

        if (is_object($response)) {
            $response = (array) $response;
        }

        $data = array(
            'title' => $title,
            'body' => $message,
            'recipients' => $get_recipients,
            'response' => $response
        );
        echo json_encode(['status' => 200, 'message' => 'Notification created', 'data' => $data]);
    }
    
    public function get_notifications()
    {
        $object = json_decode(file_get_contents("php://input"), true);

        $token = trim($object['token'] ?? '');
        $user_id = trim($object['user_id'] ?? '');
        $confirmToken = $this->checkAPI_token_from_header();

        if (!$confirmToken) {
            echo json_encode(['status' => 401, 'message' => 'Invalid token']);
            return;
        }

        // Get user account creation timestamp from Ion Auth
        $created = $this->ion_auth->user($user_id)->row()->created_on;
        $user_created_date = date('Y-m-d H:i:s', $created); // Convert timestamp to MySQL DATETIME
        log_message('error', "User Created Date: " . var_export($user_created_date, true));
        // Query notifications only **after user account creation**
        $query = "
        SELECT 
            n.*,
            IF(nr.id IS NULL, 0, 1) AS is_read
        FROM notifications n
        LEFT JOIN notifications_read nr 
            ON nr.notification_id = n.id 
            AND nr.user_id = ?
        WHERE (n.target_user_id = ? or n.target_user_id IS NULL )
          AND n.created_at >= ?   -- Only notifications after account creation
        ORDER BY n.id DESC
        ";

        $result = $this->db->query($query, [$user_id, $user_id, $user_created_date])->result_array();

        echo json_encode(['status' => 200, 'notifications' => $result]);
    }

    public function mark_notification_read()
    {
        $object = json_decode(file_get_contents("php://input"), true);
        $token = trim($object['token'] ?? '');
        $id    = $object['notification_id'] ?? null;
        $user_id    = $object['user_id'] ?? null;


        $confirmToken = $this->checkAPI_token_from_header();
        if (!$confirmToken) {
            echo json_encode(['status' => 401, 'message' => 'Invalid token']);
            return;
        }

        // if (!$id) {
        //       $created = $this->ion_auth->user($user_id)->row()->created_on;
        // $user_created_date = date('Y-m-d H:i:s', $created); // Convert timestamp to MySQL DATETIME
        // log_message('error', "User Created Date: " . var_export($user_created_date, true));
        //     echo json_encode(['status' => 400, 'message' => 'Notification ID required']);
        //     return;
        // }
        if (!$id) {

            $created = $this->ion_auth->user($user_id)->row()->created_on;
            $user_created_date = date('Y-m-d H:i:s', $created);

            $query = "
                SELECT n.id
                FROM notifications n
                LEFT JOIN notifications_read nr 
                    ON nr.notification_id = n.id 
                    AND nr.user_id = ?
                WHERE (n.target_user_id = ? OR n.target_user_id IS NULL)
                AND n.created_at >= ?
                AND nr.id IS NULL
                ORDER BY n.id DESC
            ";

            $notifications = $this->db
                ->query($query, [$user_id, $user_id, $user_created_date])
                ->result_array();

            // Use your same insert query
            $sql = "INSERT IGNORE INTO notifications_read (notification_id, user_id) VALUES (?, ?)";

            foreach ($notifications as $row) {
                $this->db->query($sql, [$row['id'], $user_id]);
            }

            echo json_encode([
                'status' => 200,
                'message' => 'All unread notifications marked as read'
            ]);
            return;
        }


        //$user_id = $confirmToken->user_id;

        // Insert only if not already read
        // $this->db->insert_ignore('notifications_read', [
        //     'notification_id' => $id,
        //     'user_id' => $user_id
        // ]);
        $sql = "INSERT IGNORE INTO notifications_read (notification_id, user_id) VALUES (?, ?)";
        $this->db->query($sql, [$id, $user_id]);

        echo json_encode(['status' => 200, 'message' => 'Notification marked as read']);
    }

    
    public function change_user_password1()
    {
        $object = json_decode(file_get_contents("php://input"), true);

        $token       = trim($object['token'] ?? '');
        $oldpassword = trim($object['old_password'] ?? '');
        $newpassword = trim($object['new_password'] ?? '');
        $user_id     = trim($object['user_id'] ?? '');

        if (!$this->checkAPI_token_from_header()) {
            echo json_encode(['status' => false, 'message' => 'Invalid token']);
            return;
        }

        if (!$oldpassword || !$newpassword) {
            echo json_encode(['status' => false, 'message' => 'Passwords are required']);
            return;
        }

        $user = $this->ion_auth->user($user_id)->row();
        if (!$user) {
            echo json_encode(['status' => false, 'message' => 'User not found']);
            return;
        }

        if (!$user->active) {
            echo json_encode(['status' => false, 'message' => 'User account is inactive']);
            return;
        }

        $identity_field = $this->config->item('identity', 'ion_auth');
        $identity = $user->$identity_field;

        // Explicit old password check
        if (!$this->ion_auth->login($identity, $oldpassword, false)) {
            echo json_encode([
                'status' => false,
                'message' => 'Old password is not correct'
            ]);
            return;
        }

        // Change password
        if ($this->ion_auth->change_password($identity, $oldpassword, $newpassword)) {
            echo json_encode([
                'status' => 200,
                'message' => 'Password changed successfully'
            ]);
        } else {
            echo json_encode([
                'status' => false,
                'message' => strip_tags($this->ion_auth->errors())
            ]);
        }
    }

	public function change_user_password()
	{
	    $contentType = $this->input->server('CONTENT_TYPE');
	    $object = (strpos($contentType, 'application/json') !== false)
	        ? json_decode($this->getRequestBody(), true)
	        : $this->input->post();

	    $token            = trim($object['token'] ?? '');
	    $oldpassword      = trim($object['old_password'] ?? '');
	    $newpassword      = trim($object['new_password'] ?? '');
	    $confirm_password = trim($object['confirm_password'] ?? '');

	    $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

	    $jwt_user_id   = (int)$jwtData->user_id;
	    $jwt_user_role = strtolower($jwtData->user_role ?? '');
	    $target_id     = intval($object['user_id'] ?? $jwt_user_id);
	    $is_admin      = in_array($jwt_user_role, ['admin', 'manager', 'superadmin']);
	    if (!$is_admin && $target_id !== $jwt_user_id) {
	        $this->output->set_status_header(403);
	        header('Content-Type: application/json');
	        echo json_encode(['status' => 403, 'message' => 'You can only update your own password']);
	        return;
	    }

	    if ($oldpassword === '' || $newpassword === '' || $confirm_password === '') {
	        $this->output->set_status_header(400);
	        header('Content-Type: application/json');
	        echo json_encode(['status' => 400, 'message' => 'old_password, new_password and confirm_password are required']);
	        return;
	    }

	    if ($newpassword !== $confirm_password) {
	        $this->output->set_status_header(400);
	        header('Content-Type: application/json');
	        echo json_encode(['status' => 400, 'message' => 'new_password and confirm_password do not match']);
	        return;
	    }

	    if ($oldpassword === $newpassword) {
	        $this->output->set_status_header(400);
	        header('Content-Type: application/json');
	        echo json_encode(['status' => 400, 'message' => 'New password must be different from old password']);
	        return;
	    }

	    $min_password_length = (int)$this->config->item('min_password_length', 'ion_auth');
	    if ($min_password_length > 0 && strlen($newpassword) < $min_password_length) {
	        $this->output->set_status_header(400);
	        header('Content-Type: application/json');
	        echo json_encode([
	            'status' => 400,
	            'message' => 'New password must be at least ' . $min_password_length . ' characters'
	        ]);
	        return;
	    }

	    $user = $this->ion_auth->user($target_id)->row();
	    if (!$user) {
	        $this->output->set_status_header(404);
	        header('Content-Type: application/json');
	        echo json_encode(['status' => 404, 'message' => 'User not found']);
	        return;
	    }

	    $identity_field = $this->config->item('identity', 'ion_auth');
	    $identity       = $user->$identity_field;

	    if (!(int)$user->active) {
	        $this->output->set_status_header(423);
	        header('Content-Type: application/json');
	        echo json_encode(['status' => 423, 'message' => 'User account is inactive']);
	        return;
	    }

	    $changed = $this->ion_auth->change_password($identity, $oldpassword, $newpassword);

	    if ($changed) {
	        $this->output->set_status_header(200);
	        header('Content-Type: application/json');
             $updateData = [
	        'resetKey'   => 'No', // Set to "No" since admin reset is a deliberate action, not a user-initiated reset
	        'updated_at' => date('Y-m-d H:i:s'),
	    ];

	    $this->db->where('id', $target_id)->update('users', $updateData);
	        echo json_encode([
	            'status' => 200,
	            'message' => 'Password updated successfully'
	        ]);
	        return;
	    }

	    $error = strip_tags($this->ion_auth->errors());
	    $this->output->set_status_header(400);
	    header('Content-Type: application/json');
	    echo json_encode([
	        'status' => 400,
	        'message' => $error ?: 'Unable to update password. Check old_password and try again.'
	    ]);
	}

    public function deactivate_staff($user_id)
    {
        // convert to integer for safety
        $user_id = (int)$user_id;

        // Prepare update data
        $updateData = [
            'active' => 0
        ];

        $status = 0;
        $headerMessage = "your Account has been Deactivated.";

        // --- Update User Record ---
        $this->db->where('id', $user_id)->update('users', $updateData);

        // If nothing updated, return error
        if ($this->db->affected_rows() < 1) {
            return [
                "status"  => false,
                "message" => "User not found or already deactivated",
            ];
        }

        // Fetch updated user details
        $requester_acct = $this->ion_auth->user($user_id)->row();

        if (!$requester_acct) {
            return [
                "status"  => false,
                "message" => "User record not found",
            ];
        }

        $fullname = $requester_acct->fullname;
        $email    = $requester_acct->email;

        // Email subject
        $subject = "Account Update: Dear " . $fullname . " - " . $headerMessage;

        // Send email notification
        $this->sendUserMail($email, $subject, $fullname, $status);

        // Fetch updated row to return
        $updated_user = $this->db->select('id, fullname, email, phone, user_role, active, profile_status')
            ->get_where('users', ['id' => $user_id])
            ->row_array();
        $this->db->where('id', $user_id);
        $this->db->delete('users');
        return [
            "status"  => true,
            "message" => "User account deactivated",
            "data"    => $updated_user
        ];
    }

    private function respond($status_code, $message, $extra_data = [])
    {
        $CI = &get_instance();

        if (!is_array($extra_data)) {
            $extra_data = ['extra_info' => $extra_data];
        }

        $ok = ($status_code >= 200 && $status_code < 300);

        $response = array_merge(
            [
                'ok' => $ok,
                'status' => $status_code,
                'message' => $message
            ],
            $extra_data
        );

        $CI->output
            ->set_status_header($status_code)
            ->set_content_type('application/json')
            ->set_output(json_encode($response));
    }

    public function user_tokens()
    {
        // if ($this->input->method() !== 'post') {
        //     $this->output
        //         ->set_status_header(405)
        //         ->set_content_type('application/json')
        //         ->set_output(json_encode(['error' => 'Method Not Allowed']));
        //     return;
        // }

        $body = file_get_contents('php://input');
        $objects = json_decode($body, true);
        $token = trim($objects['token']);
        $user_token = trim($objects['user_token']);
        $user_id = trim($objects['user_id']);
        // $user_id = $this->ion_auth->user()->row()->id;
        // log_message('error', 'user_id: ' . $user_id);
        log_message('error', "tokens Input: " . var_export($objects, true));
        $confirmToken = $this->checkAPI_token_from_header();
        if (!$confirmToken) {
            echo json_encode(['status' => 401, 'message' => 'Invalid token']);
            return;
        }


        if (!$user_token || !$user_id) {
            log_message('error', 'Invalid Reuqest Payload');
            return $this->respond(400, 'Invalid Request Payload');
        }

        $data = [
            'token' => $user_token,
            'user_id' => (int) $user_id
        ];

        if (!empty($user_token)) {
            $save_token = $this->api_model->register_token($data);
            if ($save_token) {
                $status_code = 200;
                $response = "Token saved sucessfully or already exists";
            } else {
                $status_code = 400;
                $response = "An Error Occured while saving token";
            }
        } else {
            $status_code = 400;
            $response = "Invalid Token";
        }

        return $this->respond($status_code, $response, $data);
    }    

    public function test_qr()
    {
        $userAccessCode = "A7D34K8S";

        $qrBase64 = $this->generateQRCodeBase64($userAccessCode);

        echo '<img src="' . $qrBase64 . '" />';
    }

    /*
    |=============================================================
    |  ALUMNI PORTAL — API ENDPOINTS (UPDATED WITH year SUPPORT)
    |  File: application/controllers/Api.php  (append/replace these)
    |  Generated: 2026-03-07
    |
    |  CHANGES FROM PREVIOUS VERSION:
    |  - year VARCHAR(4) added to create/manage/get for:
    |      events, register_event, manage_event_rsvp,
    |      get_event_attendees, create_listing, manage_listing,
    |      get_listings
    |  - get_events  now filterable by year AND chapter
    |  - get_listings now filterable by year AND chapter
    |  - register_event auto-pulls year from parent event row
    |=============================================================
    */


    /*=======================================================
        EVENTS — CREATE
        POST /api/create_event
        New field: year (VARCHAR 4, e.g. "2026") — optional
    ========================================================
    */
    public function create_event()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->post();

        log_message('error', "Create Event Input: " . var_export($object, true));

        $token = isset($object['token']) ? trim($object['token']) : '';
        if (!$this->checkAPI_token_from_header()) {
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
            return;
        }

        $user_id    = isset($object['user_id'])    ? trim($object['user_id'])    : null;
        $title      = isset($object['title'])      ? trim($object['title'])      : null;
        // $event_date = isset($object['event_date']) ? trim($object['event_date']) : null;
        $start_date = isset($object['start_date']) ? trim($object['start_date']) : null;
        $end_date   = isset($object['end_date'])   ? trim($object['end_date'])   : null;

        if (!$user_id || !$title || !$start_date) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'user_id, title and start_date are required']);
            return;
        }

        $user = $this->db->get_where('users', ['id' => $user_id])->row();
        if (!$user) {
            $this->output->set_status_header(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 404, 'message' => 'User not found']);
            return;
        }

        $chapter_id    = isset($object['chapter_id'])    ? intval($object['chapter_id'])    : 1;
        $year          = isset($object['year'])          ? trim($object['year'])            : null;
        $description   = isset($object['description'])   ? trim($object['description'])     : null;
        $location      = isset($object['location'])      ? trim($object['location'])        : null;
        $start_time    = isset($object['start_time'])    ? trim($object['start_time'])      : null;
        $end_time      = isset($object['end_time'])      ? trim($object['end_time'])        : null;
        $color         = isset($object['color'])         ? trim($object['color'])           : '#0077cc';
        $status        = isset($object['status'])        ? trim($object['status'])          : 'upcoming';
        $visibility    = isset($object['visibility'])    ? trim($object['visibility'])      : 'members';
        $max_attendees = isset($object['max_attendees']) ? intval($object['max_attendees']) : 0;
        $is_approved   = isset($object['is_approved'])   ? intval($object['is_approved'])   : 0;

        if ($chapter_id) {
            $chapter = $this->db->get_where('alumni_chapter', ['id' => $chapter_id, 'is_enabled' => 1])->row();
            if (!$chapter) {
                $this->output->set_status_header(404);
                header('Content-Type: application/json');
                echo json_encode(['status' => 404, 'message' => 'Chapter not found or not enabled']);
                return;
            }
        }

        $event_banner = '';
        if (!empty($_FILES['event_banner']['name'])) {
            $uploaded = $this->uploadfiles('event_banner', $user_id, 'event_banners');
            if ($uploaded) {
                $event_banner = site_url($uploaded[0]->attachment_file);
            }
        }

        $insertData = [
            'title'         => $title,
            'description'   => $description,
            'location'      => $location,
            // 'event_date' => $event_date,
            'start_date'    => $start_date,
            'end_date'      => $end_date,
            'start_time'    => $start_time,
            'end_time'      => $end_time,
            'color'         => $color,
            'status'        => $status,
            'visibility'    => $visibility,
            'max_attendees' => $max_attendees,
            'is_approved'   => $is_approved,
            'chapter_id'    => $chapter_id,
            'year'          => $year,
            'created_by'    => $user_id,
            'event_banner'  => $event_banner,
            'created_at'    => date('Y-m-d H:i:s'),
        ];

        $this->db->insert('events', $insertData);
        $event_id = $this->db->insert_id();

        if ($event_id) {
            $this->output->set_status_header(200);
            $data = [
                'status'  => 200,
                'message' => 'Event created successfully',
                'event'   => array_merge(['id' => $event_id], $insertData),
            ];
        } else {
            $this->output->set_status_header(500);
            $data = ['status' => 500, 'message' => 'Event creation failed'];
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }


    /*=======================================================
        EVENTS — MANAGE (UPDATE / DELETE)
        POST /api/manage_event
        year is now an updatable field
    ========================================================
    */
    public function manage_event()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->post();

        log_message('error', "Manage Event Input: " . var_export($object, true));

        $token = isset($object['token']) ? trim($object['token']) : '';
        if (!$this->checkAPI_token_from_header()) {
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
            return;
        }

        $event_id      = isset($object['id'])            ? intval($object['id'])                      : 0;
        $function_type = isset($object['function_type']) ? strtolower(trim($object['function_type'])) : '';

        if (!$event_id || !$function_type) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'id and function_type are required']);
            return;
        }

        $event = $this->db->get_where('events', ['id' => $event_id])->row();
        if (!$event) {
            $this->output->set_status_header(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 404, 'message' => 'Event not found']);
            return;
        }

        if ($function_type === 'delete') {
            $this->db->delete('event_attendees', ['event_id' => $event_id]);
            $this->db->delete('events', ['id' => $event_id]);
            $this->output->set_status_header(200);
            $data = ['status' => 200, 'message' => 'Event deleted successfully'];

        } elseif ($function_type === 'update') {
            $updatableFields = [
                'title', 'description', 'location',
                // 'event_date',
                'start_date', 'end_date',
                'start_time', 'end_time', 'color', 'status',
                'visibility', 'max_attendees', 'is_approved',
                'chapter_id', 'year'
            ];

            $updateData = [];
            foreach ($updatableFields as $field) {
                if (isset($object[$field]) && $object[$field] !== '') {
                    $updateData[$field] = trim($object[$field]);
                }
            }

            if (!empty($_FILES['event_banner']['name'])) {
                $user_id  = isset($object['user_id']) ? trim($object['user_id']) : $event->created_by;
                $uploaded = $this->uploadfiles('event_banner', $user_id, 'event_banners');
                if ($uploaded) {
                    $updateData['event_banner'] = site_url($uploaded[0]->attachment_file);
                }
            }

            if (empty($updateData)) {
                $this->output->set_status_header(422);
                $data = ['status' => 422, 'message' => 'No fields to update'];
            } else {
                $updateData['updated_at'] = date('Y-m-d H:i:s');
                $this->db->where('id', $event_id)->update('events', $updateData);
                $this->output->set_status_header(200);
                $data = [
                    'status'  => 200,
                    'message' => 'Event updated successfully',
                    'event'   => array_merge(['id' => $event_id], $updateData),
                ];
            }
        } else {
            $this->output->set_status_header(400);
            $data = ['status' => 400, 'message' => 'Invalid function_type. Use update or delete'];
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }

    /*=======================================================
        EVENTS — GET
        GET or POST /api/get_events
        NEW filters: year, chapter_id (can combine both)
        Logic:
        - chapter_id passed → returns events for that chapter
            OR events with NULL chapter (global)
        - year passed → returns events for that year
            OR events with NULL year (global)
        - both passed → applies both filters together
    ========================================================
    */
    public function get_events()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->get();

        log_message('error', "Get Events Input: " . var_export($object, true));

        $token = isset($object['token']) ? trim($object['token']) : '';
        if (!$this->checkAPI_token_from_header()) {
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
            return;
        }

        $event_id   = isset($object['id'])         ? intval($object['id'])         : 0;
        $chapter_id = isset($object['chapter_id']) ? intval($object['chapter_id']) : null;
        $year       = isset($object['year'])       ? trim($object['year'])         : null;
        $user_id    = isset($object['user_id'])    ? intval($object['user_id'])    : null;
        $visibility = isset($object['visibility']) ? trim($object['visibility'])   : null;
        $status     = isset($object['status'])     ? trim($object['status'])       : null;

        if ($event_id > 0) {
            // Single event
            $this->db->select('e.*,u.id as user_id, u.fullname AS created_by_name, u.email AS created_by_email,
                            ac.chapter_name,
                            (SELECT COUNT(*) FROM event_attendees ea
                                WHERE ea.event_id = e.id AND ea.status = "going") AS attendee_count')
                ->from('events e')
                ->join('users u', 'u.id = e.created_by', 'left')
                ->join('alumni_chapter ac', 'ac.id = e.chapter_id', 'left')
                ->where('e.id', $event_id);

            $event = $this->db->get()->row_array();

            if (!$event) {
                $this->output->set_status_header(404);
                $data = ['status' => 404, 'message' => 'Event not found'];
            } else {
                if ($user_id) {
                    $rsvp = $this->db->get_where('event_attendees', [
                        'event_id' => $event_id,
                        'user_id'  => $user_id,
                    ])->row();
                    $event['my_rsvp'] = $rsvp ? $rsvp->status : null;
                }
                $this->output->set_status_header(200);
                $data = ['status' => 200, 'message' => 'Event retrieved successfully', 'event' => $event];
            }

        } else {
            // "My events" mode — only events the user has registered for
            if ($user_id) {
                $this->db->select('e.*, u.fullname AS created_by_name,
                                ac.chapter_name,
                                ea.status AS my_rsvp,
                                (SELECT COUNT(*) FROM event_attendees eac
                                    WHERE eac.event_id = e.id AND eac.status = "going") AS attendee_count')
                    ->from('events e')
                    ->join('event_attendees ea', 'ea.event_id = e.id AND ea.user_id = ' . $user_id, 'inner')
                    ->join('users u', 'u.id = e.created_by', 'left')
                    ->join('alumni_chapter ac', 'ac.id = e.chapter_id', 'left');

                if ($visibility) $this->db->where('e.visibility', $visibility);
                if ($status)     $this->db->where('e.status', $status);

                $this->db->order_by('e.start_date', 'ASC');
                $events = $this->db->get()->result_array();

            } else {
                // General list with filters
                $this->db->select('e.*, u.fullname AS created_by_name,
                                ac.chapter_name,
                                (SELECT COUNT(*) FROM event_attendees ea
                                    WHERE ea.event_id = e.id AND ea.status = "going") AS attendee_count')
                    ->from('events e')
                    ->join('users u', 'u.id = e.created_by', 'left')
                    ->join('alumni_chapter ac', 'ac.id = e.chapter_id', 'left');

                // chapter filter: chapter-specific OR global (NULL chapter_id)
                if ($chapter_id) {
                    $this->db->group_start()
                        ->where('e.chapter_id', $chapter_id)
                        ->or_where('e.chapter_id IS NULL')
                        ->group_end();
                }

                // year filter: year-specific OR global (NULL year)
                if ($year) {
                    $this->db->group_start()
                        ->where('e.year', $year)
                        ->or_where('e.year IS NULL')
                        ->group_end();
                }

                if ($visibility) $this->db->where('e.visibility', $visibility);
                if ($status)     $this->db->where('e.status', $status);

                $this->db->order_by('e.start_date', 'ASC');
                $events = $this->db->get()->result_array();
            }

            $this->output->set_status_header(200);
            $data = [
                'status'  => 200,
                'message' => 'Events retrieved successfully',
                'total'   => count($events),
                'events'  => $events,
            ];
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }

    /*=======================================================
        EVENTS — REGISTER / RSVP
        POST /api/register_event
        year is auto-pulled from the parent event record
        and stored on the event_attendees row
    ========================================================
    */
    public function register_event()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->post();

        log_message('error', "Register Event Input: " . var_export($object, true));

        $token = isset($object['token']) ? trim($object['token']) : '';        
        $jwt_payload = $this->authorize();

        if (!$jwt_payload) {
            return;
        }

        $jwt_user_id   = intval($jwt_payload->user_id);
        $jwt_user_role = $jwt_payload->user_role ?? '';
        $is_admin      = in_array($jwt_user_role, ['admin', 'manager', 'superadmin']);

        // Admins can register on behalf of another user; regular users are locked to their own ID
        $requested_user_id = isset($object['user_id']) ? intval($object['user_id']) : 0;

        if (!$is_admin && $requested_user_id && $requested_user_id !== $jwt_user_id) {
            $this->output->set_status_header(403);
            header('Content-Type: application/json');
            echo json_encode(['status' => 403, 'message' => 'You can only register for yourself']);
            return;
        }

        $user_id = ($is_admin && $requested_user_id) ? $requested_user_id : $jwt_user_id;

        $event_id        = isset($object['event_id'])        ? intval($object['event_id'])        : 0;
        $status          = isset($object['status'])          ? trim($object['status'])            : 'going';
        $additional_info = isset($object['additional_info']) ? trim($object['additional_info'])   : '';

        if (!$event_id) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'event_id is required']);
            return;
        }

        $event = $this->db->get_where('events', ['id' => $event_id])->row();
        if (!$event) {
            $this->output->set_status_header(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 404, 'message' => 'Event not found']);
            return;
        }

        // Max attendees cap
        if ($event->max_attendees > 0 && $status === 'going') {
            $currentCount = $this->db->where(['event_id' => $event_id, 'status' => 'going'])
                ->count_all_results('event_attendees');
            if ($currentCount >= $event->max_attendees) {
                $this->output->set_status_header(409);
                header('Content-Type: application/json');
                echo json_encode(['status' => 409, 'message' => 'Event is fully booked. No more spots available']);
                return;
            }
        }

        // Auto-pull year from the event record
        $event_year = !empty($event->year) ? $event->year : null;

        // $existing = $this->db->get_where('event_attendees', [
        //     'event_id' => $event_id,
        //     'user_id'  => $user_id,
        // ])->row();
        // $this->db->where('status NOT LIKE', '%cancel%', false);
        //     $existing = $this->db->get_where('event_attendees', [
        //         'event_id' => $event_id,
        //         'user_id'  => $user_id,
        //     ])->row();
        $this->db->not_like('status', 'cancel');
        $existing = $this->db->get_where('event_attendees', [
            'event_id' => $event_id,
            'user_id'  => $user_id,
        ])->row();

        if ($existing) {
            $updateData = ['status' => $status, 'year' => $event_year];
            if ($additional_info !== '') {
                $updateData['additional_info'] = $additional_info;
            }
            $this->db->where(['event_id' => $event_id, 'user_id' => $user_id])
                ->update('event_attendees', $updateData);

            $this->output->set_status_header(200);
            $data = [
                'status'  => 200,
                'message' => 'You have already registered for this event with status "' . $status . '"',
                'rsvp'    => [
                    'event_id'        => $event_id,
                    'user_id'         => $user_id,
                    'year'            => $event_year,
                    'status'          => $status,
                    'additional_info' => $additional_info ?: null,
                ],
            ];
        } else {
            $insertData = [
                'event_id'        => $event_id,
                'user_id'         => $user_id,
                'year'            => $event_year,
                'status'          => $status,
                'additional_info' => $additional_info ?: null,
                'registered_at'   => date('Y-m-d H:i:s'),
            ];

            $this->db->insert('event_attendees', $insertData);
            $rsvp_id = $this->db->insert_id();

            $this->output->set_status_header(200);
            $data = [
                'status'  => 200,
                'message' => 'Successfully registered for event',
                'rsvp'    => array_merge(['id' => $rsvp_id], $insertData),
            ];
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }


    /*=======================================================
        EVENTS — CANCEL / MANAGE RSVP
        POST /api/manage_event_rsvp
    ========================================================
    */
    public function manage_event_rsvp()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->post();

        log_message('error', "Manage Event RSVP Input: " . var_export($object, true));

        $token = isset($object['token']) ? trim($object['token']) : '';
        if (!$this->checkAPI_token_from_header()) {
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
            return;
        }

        $user_id       = isset($object['user_id'])       ? intval($object['user_id'])                 : 0;
        $event_id      = isset($object['event_id'])      ? intval($object['event_id'])                : 0;
        $function_type = isset($object['function_type']) ? strtolower(trim($object['function_type'])) : '';

        if (!$user_id || !$event_id || !$function_type) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'user_id, event_id and function_type are required']);
            return;
        }

        $rsvp = $this->db->get_where('event_attendees', [
            'event_id' => $event_id,
            'user_id'  => $user_id,
        ])->row();

        if (!$rsvp) {
            $this->output->set_status_header(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 404, 'message' => 'RSVP not found. User has not registered for this event']);
            return;
        }

        if ($function_type === 'cancel') {
        //   $this->db->delete('event_attendees', ['event_id' => $event_id, 'user_id' => $user_id]);
            $this->db->update('event_attendees', ['status' => 'not_going'], ['event_id' => $event_id, 'user_id' => $user_id]);
            $this->output->set_status_header(200);
            $data = ['status' => 200, 'message' => 'RSVP cancelled successfully'];

        } elseif ($function_type === 'update') {
            $new_status = isset($object['status']) ? trim($object['status']) : null;
            $allowed    = ['going', 'maybe', 'not_going'];

            if (!$new_status || !in_array($new_status, $allowed)) {
                $this->output->set_status_header(400);
                header('Content-Type: application/json');
                echo json_encode(['status' => 400, 'message' => 'status must be: going, maybe, or not_going']);
                return;
            }

            $updateFields = ['status' => $new_status];
            $new_additional_info = isset($object['additional_info']) ? trim($object['additional_info']) : null;
            if ($new_additional_info !== null) {
                $updateFields['additional_info'] = $new_additional_info;
            }

            $this->db->where(['event_id' => $event_id, 'user_id' => $user_id])
                ->update('event_attendees', $updateFields);

            $this->output->set_status_header(200);
            $data = [
                'status'  => 200,
                'message' => 'RSVP updated to "' . $new_status . '"',
                'rsvp'    => [
                    'event_id'        => $event_id,
                    'user_id'         => $user_id,
                    'year'            => $rsvp->year,
                    'status'          => $new_status,
                    'additional_info' => $new_additional_info ?? $rsvp->additional_info ?? null,
                ],
            ];
        } else {
            $this->output->set_status_header(400);
            $data = ['status' => 400, 'message' => 'Invalid function_type. Use cancel or update'];
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }

    /*=======================================================
        EVENTS — GET ATTENDEES
        GET or POST /api/get_event_attendees
        NEW filter: year
    ========================================================
    */
    public function get_event_attendees()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->get();

        log_message('error', "Get Event Attendees Input: " . var_export($object, true));

        $token = isset($object['token']) ? trim($object['token']) : '';
        if (!$this->checkAPI_token_from_header()) {
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
            return;
        }

        $event_id = isset($object['event_id']) ? intval($object['event_id']) : 0;
        $status   = isset($object['status'])   ? trim($object['status'])     : null;
        $year     = isset($object['year'])     ? trim($object['year'])       : null;

        if (!$event_id) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'event_id is required']);
            return;
        }

        $event = $this->db->get_where('events', ['id' => $event_id])->row();
        if (!$event) {
            $this->output->set_status_header(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 404, 'message' => 'Event not found']);
            return;
        }

        $this->db->select('ea.id, ea.status, ea.year, ea.additional_info, ea.registered_at,
                        u.id AS user_id, u.fullname, u.email, u.phone, u.avatar')
            ->from('event_attendees ea')
            ->join('users u', 'u.id = ea.user_id', 'left')
            ->where('ea.event_id', $event_id);

        if ($status) $this->db->where('ea.status', $status);
        if ($year)   $this->db->where('ea.year', $year);

        $this->db->order_by('ea.registered_at', 'ASC');
        $attendees = $this->db->get()->result_array();

        $going     = $this->db->where(['event_id' => $event_id, 'status' => 'going'])->count_all_results('event_attendees');
        $maybe     = $this->db->where(['event_id' => $event_id, 'status' => 'maybe'])->count_all_results('event_attendees');
        $not_going = $this->db->where(['event_id' => $event_id, 'status' => 'not_going'])->count_all_results('event_attendees');

        $this->output->set_status_header(200);
        $data = [
            'status'    => 200,
            'message'   => 'Attendees retrieved successfully',
            'event_id'  => $event_id,
            'year'      => $event->year,
            'summary'   => [
                'going'     => $going,
                'maybe'     => $maybe,
                'not_going' => $not_going,
                'total'     => $going + $maybe + $not_going,
            ],
            'attendees' => $attendees,
        ];

        header('Content-Type: application/json');
        echo json_encode($data);
    }

    /*=======================================================
        EVENT REGISTRATION FORMS
        -------------------------------------------------------
        POST /api/create_event_registration_form   (Admin)
        POST /api/manage_event_registration_form   (Admin)
        POST /api/get_event_registration_forms     (Token)
        POST /api/register_event_with_forms        (JWT)
        POST /api/get_event_registration_submissions       (Admin)
        POST /api/get_event_registration_submission_detail (Admin)
    ========================================================
    */

    public function create_event_registration_form()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->post();

        log_message('error', "Create Event Registration Form Input: " . var_export($object, true));

        $jwt_payload = $this->authorize();

        if (!$jwt_payload) {
            return;
        }

        $jwt_user_id   = intval($jwt_payload->user_id);
        $jwt_user_role = strtolower($jwt_payload->user_role ?? '');
        $is_admin      = in_array($jwt_user_role, ['admin', 'manager', 'superadmin']);

        if (!$is_admin) {
            $this->output->set_status_header(403);
            header('Content-Type: application/json');
            echo json_encode(['status' => 403, 'message' => 'Admin access required']);
            return;
        }

        $event_id    = isset($object['event_id'])    ? intval($object['event_id'])       : 0;
        $name        = isset($object['name'])        ? trim($object['name'])             : '';
        $description = isset($object['description']) ? trim($object['description'])      : null;
        $sort_order  = isset($object['sort_order'])  ? intval($object['sort_order'])     : 0;
        $questions   = isset($object['questions'])   && is_array($object['questions'])   ? $object['questions'] : [];

        if (!$event_id) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'event_id is required']);
            return;
        }

        if ($name === '') {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'Form name is required']);
            return;
        }

        $event = $this->db->get_where('events', ['id' => $event_id])->row();
        if (!$event) {
            $this->output->set_status_header(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 404, 'message' => 'Event not found']);
            return;
        }

        $this->db->insert('event_registration_forms', [
            'event_id'    => $event_id,
            'name'        => $name,
            'description' => $description ?: null,
            'sort_order'  => $sort_order,
            'version'     => 1,
            'is_active'   => 1,
            'created_by'  => $jwt_user_id,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        $form_id = $this->db->insert_id();

        $valid_types = ['short_answer', 'long_answer', 'multiple_choice', 'checkbox', 'dropdown'];
        $inserted    = 0;

        foreach ($questions as $i => $q) {
            $q_label = isset($q['label']) ? trim($q['label']) : '';
            $q_type  = isset($q['type'])  ? trim($q['type'])  : '';
            if ($q_label === '' || !in_array($q_type, $valid_types)) continue;

            $options = null;
            if (in_array($q_type, ['multiple_choice', 'checkbox', 'dropdown']) && !empty($q['options']) && is_array($q['options'])) {
                $options = json_encode(array_values($q['options']));
            }

            $this->db->insert('event_registration_form_questions', [
                'form_id'      => $form_id,
                'label'        => $q_label,
                'type'         => $q_type,
                'required'     => !empty($q['required']) ? 1 : 0,
                'placeholder'  => isset($q['placeholder']) ? trim($q['placeholder']) : null,
                'options_json' => $options,
                'sort_order'   => $i,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
            $inserted++;
        }

        $this->output->set_status_header(200);
        header('Content-Type: application/json');
        echo json_encode([
            'status'             => 200,
            'message'            => 'Form created successfully',
            'form_id'            => $form_id,
            'version'            => 1,
            'questions_inserted' => $inserted,
        ]);
    }


    public function manage_event_registration_form()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->post();

        log_message('error', "Manage Event Registration Form Input: " . var_export($object, true));

        $jwt_payload = $this->authorize();

        if (!$jwt_payload) {
            return;
        }

        $jwt_user_role = strtolower($jwt_payload->user_role ?? '');
        $is_admin      = in_array($jwt_user_role, ['admin', 'manager', 'superadmin']);

        if (!$is_admin) {
            $this->output->set_status_header(403);
            header('Content-Type: application/json');
            echo json_encode(['status' => 403, 'message' => 'Admin access required']);
            return;
        }

        $action  = isset($object['action'])  ? trim($object['action'])    : '';
        $form_id = isset($object['form_id']) ? intval($object['form_id']) : 0;

        if (!$form_id) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'form_id is required']);
            return;
        }

        $form = $this->db->get_where('event_registration_forms', ['id' => $form_id])->row();
        if (!$form) {
            $this->output->set_status_header(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 404, 'message' => 'Form not found']);
            return;
        }

        $valid_types = ['short_answer', 'long_answer', 'multiple_choice', 'checkbox', 'dropdown'];

        switch ($action) {

            case 'update_form':
                $updateData = [];
                if (isset($object['name']))        $updateData['name']        = trim($object['name']);
                if (isset($object['description'])) $updateData['description'] = trim($object['description']) ?: null;
                if (isset($object['sort_order']))  $updateData['sort_order']  = intval($object['sort_order']);
                if (isset($object['is_active']))   $updateData['is_active']   = $object['is_active'] ? 1 : 0;

                if (empty($updateData)) {
                    $this->output->set_status_header(400);
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 400, 'message' => 'No fields to update']);
                    return;
                }
                $this->db->where('id', $form_id)->update('event_registration_forms', $updateData);
                header('Content-Type: application/json');
                echo json_encode(['status' => 200, 'message' => 'Form updated']);
                break;

            case 'delete_form':
                $has_answers = $this->db->where('form_id', $form_id)->count_all_results('event_registration_answers');
                if ($has_answers > 0) {
                    $this->output->set_status_header(409);
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 409, 'message' => 'Cannot delete a form that has submissions. Set is_active to 0 to hide it instead.']);
                    return;
                }
                $this->db->where('form_id', $form_id)->delete('event_registration_form_questions');
                $this->db->where('id', $form_id)->delete('event_registration_forms');
                header('Content-Type: application/json');
                echo json_encode(['status' => 200, 'message' => 'Form deleted']);
                break;

            case 'add_question':
                $q_label = isset($object['label']) ? trim($object['label']) : '';
                $q_type  = isset($object['type'])  ? trim($object['type'])  : '';

                if ($q_label === '' || !in_array($q_type, $valid_types)) {
                    $this->output->set_status_header(400);
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 400, 'message' => 'label and a valid type are required']);
                    return;
                }

                $max = $this->db->select_max('sort_order')->where('form_id', $form_id)->get('event_registration_form_questions')->row();
                $next_order = ($max && $max->sort_order !== null) ? intval($max->sort_order) + 1 : 0;

                $options = null;
                if (in_array($q_type, ['multiple_choice', 'checkbox', 'dropdown']) && !empty($object['options']) && is_array($object['options'])) {
                    $options = json_encode(array_values($object['options']));
                }

                $this->db->insert('event_registration_form_questions', [
                    'form_id'      => $form_id,
                    'label'        => $q_label,
                    'type'         => $q_type,
                    'required'     => !empty($object['required']) ? 1 : 0,
                    'placeholder'  => isset($object['placeholder']) ? trim($object['placeholder']) : null,
                    'options_json' => $options,
                    'sort_order'   => $next_order,
                    'created_at'   => date('Y-m-d H:i:s'),
                ]);
                header('Content-Type: application/json');
                echo json_encode(['status' => 200, 'message' => 'Question added', 'question_id' => $this->db->insert_id()]);
                break;

            case 'update_question':
                $q_id = isset($object['question_id']) ? intval($object['question_id']) : 0;
                if (!$q_id) {
                    $this->output->set_status_header(400);
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 400, 'message' => 'question_id is required']);
                    return;
                }

                $qUpdate = [];
                if (isset($object['label']))                                             $qUpdate['label']       = trim($object['label']);
                if (isset($object['type']) && in_array($object['type'], $valid_types))   $qUpdate['type']        = $object['type'];
                if (isset($object['required']))                                           $qUpdate['required']    = $object['required'] ? 1 : 0;
                if (isset($object['placeholder']))                                        $qUpdate['placeholder'] = trim($object['placeholder']) ?: null;
                if (isset($object['sort_order']))                                         $qUpdate['sort_order']  = intval($object['sort_order']);
                if (isset($object['options']) && is_array($object['options']))            $qUpdate['options_json']= json_encode(array_values($object['options']));

                if (empty($qUpdate)) {
                    $this->output->set_status_header(400);
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 400, 'message' => 'No fields to update']);
                    return;
                }
                $this->db->where(['id' => $q_id, 'form_id' => $form_id])->update('event_registration_form_questions', $qUpdate);
                header('Content-Type: application/json');
                echo json_encode(['status' => 200, 'message' => 'Question updated']);
                break;

            case 'delete_question':
                $q_id = isset($object['question_id']) ? intval($object['question_id']) : 0;
                if (!$q_id) {
                    $this->output->set_status_header(400);
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 400, 'message' => 'question_id is required']);
                    return;
                }
                $this->db->where(['id' => $q_id, 'form_id' => $form_id])->delete('event_registration_form_questions');
                header('Content-Type: application/json');
                echo json_encode(['status' => 200, 'message' => 'Question deleted']);
                break;

            case 'reorder_questions':
                $order = isset($object['order']) && is_array($object['order']) ? $object['order'] : [];
                if (empty($order)) {
                    $this->output->set_status_header(400);
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 400, 'message' => 'order array of question_ids is required']);
                    return;
                }
                foreach ($order as $position => $q_id) {
                    $this->db->where(['id' => intval($q_id), 'form_id' => $form_id])
                        ->update('event_registration_form_questions', ['sort_order' => $position]);
                }
                header('Content-Type: application/json');
                echo json_encode(['status' => 200, 'message' => 'Questions reordered']);
                break;

            default:
                $this->output->set_status_header(400);
                header('Content-Type: application/json');
                echo json_encode(['status' => 400, 'message' => 'Invalid action. Valid: update_form, delete_form, add_question, update_question, delete_question, reorder_questions']);
        }
    }

    public function get_event_registration_forms()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->post();

        log_message('error', "Get Event Registration Forms Input: " . var_export($object, true));

        if (!$this->checkAPI_token_from_header()) {
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
            return;
        }

        $event_id = isset($object['event_id']) ? intval($object['event_id']) : 0;

        if (!$event_id) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'event_id is required']);
            return;
        }

        $event = $this->db->get_where('events', ['id' => $event_id])->row();
        if (!$event) {
            $this->output->set_status_header(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 404, 'message' => 'Event not found']);
            return;
        }

        $forms_raw = $this->db
            ->where(['event_id' => $event_id, 'is_active' => 1])
            ->order_by('sort_order', 'ASC')
            ->get('event_registration_forms')
            ->result_array();

        $forms = [];
        foreach ($forms_raw as $f) {
            $questions_raw = $this->db
                ->where('form_id', intval($f['id']))
                ->order_by('sort_order', 'ASC')
                ->get('event_registration_form_questions')
                ->result_array();

            $questions = [];
            foreach ($questions_raw as $q) {
                $questions[] = [
                    'question_id' => intval($q['id']),
                    'label'       => $q['label'],
                    'type'        => $q['type'],
                    'required'    => (bool) $q['required'],
                    'placeholder' => $q['placeholder'],
                    'options'     => !empty($q['options_json']) ? json_decode($q['options_json'], true) : null,
                    'sort_order'  => intval($q['sort_order']),
                ];
            }

            $forms[] = [
                'form_id'     => intval($f['id']),
                'name'        => $f['name'],
                'description' => $f['description'],
                'sort_order'  => intval($f['sort_order']),
                'version'     => intval($f['version']),
                'questions'   => $questions,
            ];
        }

        $this->output->set_status_header(200);
        header('Content-Type: application/json');
        echo json_encode([
            'status'   => 200,
            'event_id' => $event_id,
            'forms'    => $forms,
        ]);
    }


    public function register_event_with_forms()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->post();

        log_message('error', "Register Event With Forms Input: " . var_export($object, true));

        $jwt_payload = $this->authorize();

        if (!$jwt_payload) {
            return;
        }

        $jwt_user_id   = intval($jwt_payload->user_id);
        $jwt_user_role = strtolower($jwt_payload->user_role ?? '');
        $is_admin      = in_array($jwt_user_role, ['admin', 'manager', 'superadmin']);

        $requested_user_id = isset($object['user_id']) ? intval($object['user_id']) : 0;
        if (!$is_admin && $requested_user_id && $requested_user_id !== $jwt_user_id) {
            $this->output->set_status_header(403);
            header('Content-Type: application/json');
            echo json_encode(['status' => 403, 'message' => 'You can only register for yourself']);
            return;
        }

        $user_id = ($is_admin && $requested_user_id) ? $requested_user_id : $jwt_user_id;

        $event_id        = isset($object['event_id'])        ? intval($object['event_id'])       : 0;
        $rsvp_status     = isset($object['rsvp_status'])     ? trim($object['rsvp_status'])      : 'going';
        $additional_info = isset($object['additional_info']) ? trim($object['additional_info'])  : '';
        $form_answers    = isset($object['form_answers'])    && is_array($object['form_answers']) ? $object['form_answers'] : [];

        if (!$event_id) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'event_id is required']);
            return;
        }

        $event = $this->db->get_where('events', ['id' => $event_id])->row();
        if (!$event) {
            $this->output->set_status_header(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 404, 'message' => 'Event not found']);
            return;
        }

        if ($event->max_attendees > 0 && $rsvp_status === 'going') {
            $currentCount = $this->db->where(['event_id' => $event_id, 'status' => 'going'])->count_all_results('event_attendees');
            if ($currentCount >= $event->max_attendees) {
                $this->output->set_status_header(409);
                header('Content-Type: application/json');
                echo json_encode(['status' => 409, 'message' => 'Event is fully booked. No more spots available']);
                return;
            }
        }

        $event_year = !empty($event->year) ? $event->year : null;

        // Upsert event_attendees (same table, same logic as register_event)
        $this->db->not_like('status', 'cancel');
        $existing = $this->db->get_where('event_attendees', ['event_id' => $event_id, 'user_id' => $user_id])->row();

        if ($existing) {
            $updateData = ['status' => $rsvp_status, 'year' => $event_year];
            if ($additional_info !== '') $updateData['additional_info'] = $additional_info;
            $this->db->where(['event_id' => $event_id, 'user_id' => $user_id])->update('event_attendees', $updateData);
            $attendee_id = intval($existing->id);
            $is_update   = true;
        } else {
            $this->db->insert('event_attendees', [
                'event_id'        => $event_id,
                'user_id'         => $user_id,
                'year'            => $event_year,
                'status'          => $rsvp_status,
                'additional_info' => $additional_info ?: null,
                'registered_at'   => date('Y-m-d H:i:s'),
            ]);
            $attendee_id = $this->db->insert_id();
            $is_update   = false;
        }

        // On re-submission wipe old answers so they are replaced cleanly
        if ($is_update && !empty($form_answers)) {
            $this->db->where('attendee_id', $attendee_id)->delete('event_registration_answers');
        }

        $answers_saved = 0;
        $valid_types   = ['short_answer', 'long_answer', 'multiple_choice', 'checkbox', 'dropdown'];

        foreach ($form_answers as $fa) {
            $fa_form_id = isset($fa['form_id']) ? intval($fa['form_id']) : 0;
            $fa_answers = isset($fa['answers']) && is_array($fa['answers']) ? $fa['answers'] : [];
            if (!$fa_form_id) continue;

            $form = $this->db->get_where('event_registration_forms', ['id' => $fa_form_id, 'event_id' => $event_id, 'is_active' => 1])->row();
            if (!$form) continue;

            foreach ($fa_answers as $ans) {
                $q_id = isset($ans['question_id']) ? intval($ans['question_id']) : 0;
                if (!$q_id) continue;

                $question = $this->db->get_where('event_registration_form_questions', ['id' => $q_id, 'form_id' => $fa_form_id])->row();
                if (!$question) continue;

                $answer_val  = isset($ans['answer']) ? $ans['answer'] : null;
                $answer_text = null;
                $answer_json = null;

                if (in_array($question->type, ['multiple_choice', 'checkbox', 'dropdown'])) {
                    $answer_json = is_array($answer_val) ? json_encode($answer_val) : json_encode([$answer_val]);
                } else {
                    $answer_text = is_string($answer_val) ? trim($answer_val) : null;
                }

                $this->db->insert('event_registration_answers', [
                    'attendee_id'             => $attendee_id,
                    'form_id'                 => $fa_form_id,
                    'form_version'            => intval($form->version),
                    'question_id'             => $q_id,
                    'question_label_snapshot' => $question->label,
                    'question_type'           => $question->type,
                    'question_order'          => intval($question->sort_order),
                    'required_snapshot'       => intval($question->required),
                    'answer_text'             => $answer_text,
                    'answer_json'             => $answer_json,
                    'created_at'              => date('Y-m-d H:i:s'),
                ]);
                $answers_saved++;
            }
        }

        $this->output->set_status_header(200);
        header('Content-Type: application/json');
        echo json_encode([
            'status'        => 200,
            'message'       => $is_update ? 'Registration updated' : 'Registration submitted',
            'attendee_id'   => $attendee_id,
            'answers_saved' => $answers_saved,
        ]);
    }


    public function get_event_registration_submissions()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->post();

        log_message('error', "Get Event Registration Submissions Input: " . var_export($object, true));

        $jwt_payload = $this->authorize();

        if (!$jwt_payload) {
            return;
        }

        $jwt_user_role = strtolower($jwt_payload->user_role ?? '');
        $is_admin      = in_array($jwt_user_role, ['admin', 'manager', 'superadmin']);

        if (!$is_admin) {
            $this->output->set_status_header(403);
            header('Content-Type: application/json');
            echo json_encode(['status' => 403, 'message' => 'Admin access required']);
            return;
        }

        $event_id    = isset($object['event_id'])    ? intval($object['event_id'])                      : 0;
        $rsvp_status = isset($object['rsvp_status']) ? trim($object['rsvp_status'])                     : null;
        $page        = isset($object['page'])        ? max(1, intval($object['page']))                  : 1;
        $per_page    = isset($object['per_page'])    ? min(100, max(1, intval($object['per_page'])))    : 20;

        if (!$event_id) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'event_id is required']);
            return;
        }

        $event = $this->db->get_where('events', ['id' => $event_id])->row();
        if (!$event) {
            $this->output->set_status_header(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 404, 'message' => 'Event not found']);
            return;
        }

        // Total count
        $this->db->from('event_attendees ea')->where('ea.event_id', $event_id);
        if ($rsvp_status) $this->db->where('ea.status', $rsvp_status);
        $total = $this->db->count_all_results();

        // Paginated list
        $this->db->select('ea.id AS attendee_id, ea.user_id, ea.status AS rsvp_status, ea.additional_info, ea.registered_at,
                        u.fullname, u.email, u.phone, u.avatar')
            ->from('event_attendees ea')
            ->join('users u', 'u.id = ea.user_id', 'left')
            ->where('ea.event_id', $event_id);
        if ($rsvp_status) $this->db->where('ea.status', $rsvp_status);

        $rows = $this->db->order_by('ea.registered_at', 'DESC')
            ->limit($per_page, ($page - 1) * $per_page)
            ->get()->result_array();

        foreach ($rows as &$r) {
            $r['attendee_id']     = intval($r['attendee_id']);
            $r['user_id']         = intval($r['user_id']);
            $answer_count         = $this->db->where('attendee_id', $r['attendee_id'])->count_all_results('event_registration_answers');
            $r['has_form_answers'] = $answer_count > 0;
            $r['answer_count']    = $answer_count;
        }
        unset($r);

        $this->output->set_status_header(200);
        header('Content-Type: application/json');
        echo json_encode([
            'status'        => 200,
            'event_id'      => $event_id,
            'total'         => $total,
            'page'          => $page,
            'per_page'      => $per_page,
            'registrations' => $rows,
        ]);
    }

    public function get_event_registration_submission_detail()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->post();

        log_message('error', "Get Event Registration Submission Detail Input: " . var_export($object, true));

        $jwt_payload = $this->authorize();

        if (!$jwt_payload) {
            return;
        }

        $jwt_user_role = strtolower($jwt_payload->user_role ?? '');
        $is_admin      = in_array($jwt_user_role, ['admin', 'manager', 'superadmin']);

        if (!$is_admin) {
            $this->output->set_status_header(403);
            header('Content-Type: application/json');
            echo json_encode(['status' => 403, 'message' => 'Admin access required']);
            return;
        }

        $attendee_id = isset($object['attendee_id']) ? intval($object['attendee_id']) : 0;

        if (!$attendee_id) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'attendee_id is required']);
            return;
        }

        $attendee = $this->db
            ->select('ea.id AS attendee_id, ea.event_id, ea.user_id, ea.status AS rsvp_status, ea.additional_info, ea.registered_at,
                    u.fullname, u.email, u.phone, u.avatar')
            ->from('event_attendees ea')
            ->join('users u', 'u.id = ea.user_id', 'left')
            ->where('ea.id', $attendee_id)
            ->get()->row_array();

        if (!$attendee) {
            $this->output->set_status_header(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 404, 'message' => 'Registration not found']);
            return;
        }

        $answers_raw = $this->db
            ->where('attendee_id', $attendee_id)
            ->order_by('form_id', 'ASC')
            ->order_by('question_order', 'ASC')
            ->get('event_registration_answers')
            ->result_array();

        $forms_map = [];
        foreach ($answers_raw as $a) {
            $fid = intval($a['form_id']);
            if (!isset($forms_map[$fid])) {
                $form_meta = $this->db->get_where('event_registration_forms', ['id' => $fid])->row();
                $forms_map[$fid] = [
                    'form_id'      => $fid,
                    'form_name'    => $form_meta ? $form_meta->name : 'Unknown Form',
                    'form_version' => intval($a['form_version']),
                    'answers'      => [],
                ];
            }

            $answer_value = ($a['answer_json'] !== null)
                ? json_decode($a['answer_json'], true)
                : $a['answer_text'];

            $forms_map[$fid]['answers'][] = [
                'question_id' => intval($a['question_id']),
                'label'       => $a['question_label_snapshot'],
                'type'        => $a['question_type'],
                'required'    => (bool) $a['required_snapshot'],
                'sort_order'  => intval($a['question_order']),
                'answer'      => $answer_value,
            ];
        }

        $attendee['attendee_id'] = intval($attendee['attendee_id']);
        $attendee['user_id']     = intval($attendee['user_id']);
        $attendee['event_id']    = intval($attendee['event_id']);
        $attendee['forms']       = array_values($forms_map);

        $this->output->set_status_header(200);
        header('Content-Type: application/json');
        echo json_encode([
            'status'       => 200,
            'registration' => $attendee,
        ]);
    }

    /*=======================================================
        MARKETPLACE — NORMALISE SOCIAL MEDIA INPUT
        Accepts either the marketplace_social_media column names
        (instagram_url, twitter_handle, …) or the short client
        aliases (social_instagram, social_x, …). A short alias may
        carry a full URL or a bare handle — whichever half is
        missing is derived from the one that was sent.

        instagram_hashtag stands alone (no url/handle pair) and is
        normalised to #-prefixed tokens.

        Returns [column => value|null] for the platforms present in
        $object only, so it is safe for both insert and update.
    ========================================================
    */
    private function normalize_social_input($object)
    {
        $platforms = [
            'instagram' => ['aliases' => ['social_instagram'],           'url' => 'https://instagram.com/%s',        'at' => true],
            'twitter'   => ['aliases' => ['social_x', 'social_twitter'], 'url' => 'https://x.com/%s',                'at' => true],
            'linkedin'  => ['aliases' => ['social_linkedin'],            'url' => 'https://linkedin.com/company/%s', 'at' => false],
            'facebook'  => ['aliases' => ['social_facebook'],            'url' => 'https://facebook.com/%s',         'at' => false],
            'tiktok'    => ['aliases' => ['social_tiktok'],              'url' => 'https://tiktok.com/@%s',          'at' => true],
            'youtube'   => ['aliases' => ['social_youtube'],             'url' => 'https://youtube.com/@%s',         'at' => true],
        ];

        $social = [];

        foreach ($platforms as $name => $cfg) {
            $handleKey = $name . '_handle';
            $urlKey    = $name . '_url';

            $sent   = false;
            $handle = '';
            $url    = '';

            if (array_key_exists($handleKey, $object)) {
                $handle = trim((string)$object[$handleKey]);
                $sent   = true;
            }
            if (array_key_exists($urlKey, $object)) {
                $url  = trim((string)$object[$urlKey]);
                $sent = true;
            }

            // Short alias — a URL or a bare handle, we work out which
            foreach ($cfg['aliases'] as $alias) {
                if (!array_key_exists($alias, $object)) {
                    continue;
                }
                $sent = true;
                $val  = trim((string)$object[$alias]);
                if ($val === '') {
                    continue;
                }
                if (preg_match('~^(https?://|www\.)~i', $val) || strpos($val, '/') !== false) {
                    if ($url === '')    $url    = $val;
                } else {
                    if ($handle === '') $handle = $val;
                }
            }

            if (!$sent) {
                continue;
            }

            // Fill in whichever half is missing
            if ($url !== '' && $handle === '') {
                $segment = ltrim(basename(trim((string)parse_url($url, PHP_URL_PATH), '/')), '@');
                if ($segment !== '') {
                    $handle = $cfg['at'] ? '@' . $segment : $segment;
                }
            } elseif ($handle !== '' && $url === '') {
                $url = sprintf($cfg['url'], ltrim($handle, '@'));
            }

            $social[$handleKey] = $handle ?: null;
            $social[$urlKey]    = $url    ?: null;
        }

        // Standalone columns — no handle/url pair. Kept out of the loop above so
        // that sending only a hashtag never blanks the Instagram handle or URL.
        $standalone = [
            'instagram_hashtag' => ['social_instagram_hashtag', 'social_hashtag', 'hashtag'],
        ];

        foreach ($standalone as $column => $aliases) {
            foreach (array_merge([$column], $aliases) as $key) {
                if (!array_key_exists($key, $object)) {
                    continue;
                }
                $val = trim((string)$object[$key]);
                $social[$column] = $val !== '' ? $this->normalize_hashtag($val) : null;
                break;
            }
        }

        return $social;
    }

    /*=======================================================
        MARKETPLACE — NORMALISE A HASHTAG STRING
        "mybusiness" → "#mybusiness", "#a, b" → "#a #b".
        Space/comma separated tokens each get a single leading #.
    ========================================================
    */
    private function normalize_hashtag($value)
    {
        $tokens = preg_split('/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
        if (empty($tokens)) {
            return null;
        }

        $tags = [];
        foreach ($tokens as $token) {
            $token = ltrim($token, '#');
            if ($token !== '') {
                $tags[] = '#' . $token;
            }
        }

        return !empty($tags) ? implode(' ', $tags) : null;
    }

    /*=======================================================
        MARKETPLACE — CREATE LISTING
        POST /api/create_listing
        New field: year (VARCHAR 4)
    ========================================================
    */
    public function create_listing()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents('php://input'), true)
            : $this->input->post();

        log_message('error', 'Create Listing Input: ' . var_export($object, true));

        // ── 1. API token ──────────────────────────────────────
        $token = trim($object['token'] ?? '');        
        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }
        // ── 3. Required fields ────────────────────────────────
        $title         = trim($object['title']         ?? '');
        $business_name = trim($object['business_name'] ?? '');
        $phone         = trim($object['phone']         ?? '');

        if (empty($title) || empty($business_name) || empty($phone)) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'title, business_name and phone are required']);
            return;
        }

        $user_id = (int)$jwtData->user_id;

        // ── 4. Optional scalar fields ─────────────────────────
        $description  = trim($object['description']  ?? '');
        $category     = trim($object['category']     ?? 'other');
        $price        = isset($object['price'])       ? floatval($object['price'])    : 0.00;
        $price_type   = trim($object['price_type']   ?? 'fixed');
        $contact_info = trim($object['contact_info'] ?? '');
        $whatsapp     = trim($object['whatsapp']     ?? '');
        $website      = trim($object['website']      ?? '');
        $location     = trim($object['location']     ?? '');
        $message_prompt     = trim($object['message_prompt']     ?? '');
        $status       = trim($object['status']       ?? 'pending');
        $is_featured  = isset($object['is_featured']) ? intval((bool)$object['is_featured']) : 0;
        // Every listing gets a display window — without one it would sit on the
        // grid forever, since get_listings only retires listings by expires_at
        $expires_at   = trim($object['expires_at']   ?? '');
        if ($expires_at === '') {
            $expires_at = date('Y-m-d', strtotime('+' . self::LISTING_DISPLAY_DAYS . ' days'));
        }
        $chapter_id   = isset($object['chapter_id']) && $object['chapter_id'] !== ''
                        ? intval($object['chapter_id']) : 1;
        $year         = trim($object['year'] ?? '');

        // Validate enums
        $allowed_categories  = ['jobs', 'housing', 'items', 'services', 'tutoring', 'other'];
        $allowed_price_types = ['fixed', 'negotiable', 'free'];
        $allowed_statuses    = ['active', 'sold', 'expired', 'pending'];

        if (!in_array($category, $allowed_categories)) {
            $category = 'other';
        }
        if (!in_array($price_type, $allowed_price_types)) {
            $price_type = 'fixed';
        }
        if (!in_array($status, $allowed_statuses)) {
            $this->output->set_status_header(422);
            header('Content-Type: application/json');
            echo json_encode(['status' => 422, 'message' => 'Invalid status. Use: active | sold | expired | pending']);
            return;
        }

        // Validate chapter if provided
        if ($chapter_id) {
            $chapter = $this->db->get_where('alumni_chapter', ['id' => $chapter_id, 'is_enabled' => 1])->row();
            if (!$chapter) {
                $this->output->set_status_header(404);
                header('Content-Type: application/json');
                echo json_encode(['status' => 404, 'message' => 'Chapter not found or not enabled']);
                return;
            }
        }

        // ── 5. Upload images[] via uploadfiles() ──────────────
        $uploadedImages = [];
        if (!empty($_FILES['images']['name'][0])) {
            $uploaded = $this->uploadfiles('images', $user_id, 'market_images');
            if ($uploaded) {
                foreach ($uploaded as $file) {
                    $uploadedImages[] = site_url($file->attachment_file);
                }
            }
        }

        // ── 6. Insert listing ─────────────────────────────────
        $insertData = [
            'user_id'       => $user_id,
            'chapter_id'    => $chapter_id,
            'year'          => $year ?: null,
            'title'         => $title,
            'description'   => $description ?: null,
            'category'      => $category,
            'message_prompt'      => $message_prompt,
            'price'         => $price,
            'price_type'    => $price_type,
            'images'        => !empty($uploadedImages)
                            ? json_encode($uploadedImages, JSON_UNESCAPED_SLASHES)
                            : null,
            'contact_info'  => $contact_info ?: null,
            'business_name' => $business_name,
            'phone'         => $phone,
            'whatsapp'      => $whatsapp ?: null,
            'website'       => $website ?: null,
            'location'      => $location ?: null,
            'status'        => $status,
            'is_featured'   => $is_featured,
            'expires_at'    => $expires_at ?: null,
            'created_at'    => date('Y-m-d H:i:s'),
        ];

        $this->db->insert('marketplace_listings', $insertData);
        $listing_id = $this->db->insert_id();

        if (!$listing_id) {
            $this->output->set_status_header(500);
            header('Content-Type: application/json');
            echo json_encode(['status' => 500, 'message' => 'Listing creation failed. Please try again.']);
            return;
        }

        // ── 7. Social media (optional) ────────────────────────
        $socialData = $this->normalize_social_input($object);
        $hasSocial  = !empty(array_filter($socialData));

        if ($hasSocial) {
            $socialData['market_id'] = $listing_id;
            $this->db->insert('marketplace_social_media', $socialData);
        }

        // ── 8. Build response ─────────────────────────────────
        $insertData['id']     = $listing_id;
        $insertData['images'] = $uploadedImages; // decoded array

        $this->output->set_status_header(200);
        header('Content-Type: application/json');
        echo json_encode([
            'status'      => 200,
            'message'     => 'Listing created successfully',
            'image_count' => count($uploadedImages),
            'listing'     => $insertData,
            'social'      => $hasSocial ? $socialData : null,
        ]);
    }

    /*=======================================================
        MARKETPLACE — MANAGE LISTING (UPDATE / DELETE)
        POST /api/manage_listing
        year is now an updatable field
    ========================================================
    */
    public function manage_listing()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents('php://input'), true)
            : $this->input->post();

        log_message('error', 'Manage Listing Input: ' . var_export($object, true));

        // ── 1. API token ──────────────────────────────────────
        $token = trim($object['token'] ?? '');
        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        // ── 3. Validate id + function_type ───────────────────
        $listing_id    = intval($object['id']            ?? 0);
        $function_type = strtolower(trim($object['function_type'] ?? ''));

        if (!$listing_id || !$function_type) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'id and function_type are required']);
            return;
        }

        // ── 4. Fetch existing listing ─────────────────────────
        $listing = $this->db->get_where('marketplace_listings', ['id' => $listing_id])->row();
        if (!$listing) {
            $this->output->set_status_header(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 404, 'message' => 'Listing not found']);
            return;
        }

        // Owner or admin can modify
        $requester_role = strtolower($jwtData->user_role ?? '');
        $is_admin = in_array($requester_role, ['admin', 'manager', 'superadmin']);
        if (!$is_admin && (int)$listing->user_id !== (int)$jwtData->user_id) {
            $this->output->set_status_header(403);
            header('Content-Type: application/json');
            echo json_encode(['status' => 403, 'message' => 'Access denied. You can only edit your own listings.']);
            return;
        }

        // ── DELETE ────────────────────────────────────────────
        if ($function_type === 'delete') {
            $this->db->where('id', $listing_id)->delete('marketplace_listings');
            $this->output->set_status_header(200);
            header('Content-Type: application/json');
            echo json_encode(['status' => 200, 'message' => 'Listing deleted successfully']);
            return;
        }

        // ── UPDATE ────────────────────────────────────────────
        if ($function_type === 'update') {
            $updateData = [];

            // Text / scalar fields
            // Required fields — a blank value is ignored, never written
            foreach (['title', 'business_name', 'phone', 'category',
                    'price_type', 'status'] as $field) {
                if (isset($object[$field]) && $object[$field] !== '') {
                    $updateData[$field] = trim($object[$field]);
                }
            }

            // Optional fields — sending "" clears them, so a seller can
            // remove a WhatsApp number or website they no longer use
            $clearableFields = [
                'description'    => null,
                'contact_info'   => null,
                'whatsapp'       => null,
                'location'       => null,
                'year'           => null,
                'expires_at'     => null,
                'website'        => '',   // column is NOT NULL
                'message_prompt' => '',
            ];
            foreach ($clearableFields as $field => $emptyValue) {
                if (!array_key_exists($field, $object)) {
                    continue;
                }
                $val = trim((string)$object[$field]);
                $updateData[$field] = $val !== '' ? $val : $emptyValue;
            }

            // Numeric fields
            if (isset($object['price']) && $object['price'] !== '') {
                $updateData['price'] = floatval($object['price']);
            }
            if (isset($object['is_featured'])) {
                $updateData['is_featured'] = intval((bool)$object['is_featured']);
            }
            if (isset($object['chapter_id']) && $object['chapter_id'] !== '') {
                $updateData['chapter_id'] = intval($object['chapter_id']);
            }

            // Validate enums if present
            if (isset($updateData['category'])) {
                if (!in_array($updateData['category'], ['jobs', 'housing', 'items', 'services', 'tutoring', 'other'])) {
                    unset($updateData['category']);
                }
            }
            if (isset($updateData['price_type'])) {
                if (!in_array($updateData['price_type'], ['fixed', 'negotiable', 'free'])) {
                    unset($updateData['price_type']);
                }
            }
            if (isset($updateData['status'])) {
                if (!in_array($updateData['status'], ['active', 'sold', 'expired', 'pending'])) {
                    $this->output->set_status_header(422);
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 422, 'message' => 'Invalid status. Use: active | sold | expired | pending']);
                    return;
                }
            }

            // ── Image management ──────────────────────────────
            $image_action = strtolower(trim($object['image_action'] ?? 'add'));
            log_message('error', "Image Action: $image_action");
            $user_id      = (int)$jwtData->user_id;

            // Decode existing images from DB
            $existingImages = [];
            if (!empty($listing->images)) {
                $decoded = json_decode($listing->images, true);
                if (is_array($decoded)) {
                    $existingImages = $decoded;
                }
            }

            // Step A: Remove specific images if requested
            $toRemove = [];
            if (!empty($object['remove_images'])) {
                $raw = $object['remove_images'];
                log_message('error', "Raw remove_images input: " . var_export($raw, true));
                $toRemove = is_string($raw)
                    ? (json_decode($raw, true) ?: [])
                    : (is_array($raw) ? $raw : []);
            }
            if (!empty($toRemove)) {
                $existingImages = array_values(
                    array_filter($existingImages, fn($url) => !in_array($url, $toRemove))
                );
            }

            // Step B: Upload new images via uploadfiles()
            $newUploads = [];
            if (!empty($_FILES['images']['name'][0])) {
                $uploaded = $this->uploadfiles('images', $user_id, 'market_images');
                if ($uploaded) {
                    foreach ($uploaded as $file) {
                        $newUploads[] = site_url($file->attachment_file);
                    }
                }
            }

            // Step C: Merge or replace
            if (!empty($newUploads)) {
                $finalImages = ($image_action === 'replace')
                    ? $newUploads
                    : array_merge($existingImages, $newUploads);
                $updateData['images'] = json_encode($finalImages, JSON_UNESCAPED_SLASHES);
            } elseif (!empty($toRemove)) {
                $updateData['images'] = !empty($existingImages)
                    ? json_encode($existingImages, JSON_UNESCAPED_SLASHES)
                    : null;
            } elseif ($image_action === 'replace') {
                $updateData['images'] = null;
            }

            // ── Social media upsert (if any social field sent) ─
            $socialUpdate = $this->normalize_social_input($object);

            if (empty($updateData) && empty($socialUpdate)) {
                $this->output->set_status_header(422);
                header('Content-Type: application/json');
                echo json_encode(['status' => 422, 'message' => 'No fields provided to update']);
                return;
            }

            if (!empty($updateData)) {
                $updateData['updated_at'] = date('Y-m-d H:i:s');
                $this->db->where('id', $listing_id)->update('marketplace_listings', $updateData);
            }

            if (!empty($socialUpdate)) {
                $existingSocial = $this->db->get_where('marketplace_social_media', ['market_id' => $listing_id])->row();
                if ($existingSocial) {
                    $this->db->where('market_id', $listing_id)
                            ->update('marketplace_social_media', $socialUpdate);
                } else {
                    $socialUpdate['market_id'] = $listing_id;
                    $this->db->insert('marketplace_social_media', $socialUpdate);
                }
            }

            // Read socials back so the client can re-render the card from the response
            $socialRow = $this->db->get_where('marketplace_social_media', ['market_id' => $listing_id])->row_array();

            // Decode images for response
            $responseImages = [];
            if (isset($updateData['images'])) {
                $responseImages = !empty($updateData['images'])
                    ? (json_decode($updateData['images'], true) ?: [])
                    : [];
            } else {
                $responseImages = $existingImages;
            }

            $this->output->set_status_header(200);
            header('Content-Type: application/json');
            echo json_encode([
                'status'      => 200,
                'message'     => 'Listing updated successfully',
                'image_count' => count($responseImages),
                'listing'     => array_merge(
                    ['id' => $listing_id],
                    $updateData,
                    ['images' => $responseImages, 'social_media' => $socialRow ?: null]
                ),
                'social'      => $socialRow ?: null,
            ]);
            return;
        }

        $this->output->set_status_header(400);
        header('Content-Type: application/json');
        echo json_encode(['status' => 400, 'message' => 'Invalid function_type. Use: update | delete']);
    }

    /*=======================================================
        MARKETPLACE — GET LISTINGS
        GET or POST /api/get_listings
        NEW filters: year, chapter_id (can combine both)
        Logic:
        - chapter_id → listings for that chapter OR NULL (global)
        - year       → listings for that year OR NULL (global)
        - both       → both filters apply together

        Display window:
        - listings past their expires_at are hidden; expires_at NULL
          never expires
        - include_expired=1 shows them anyway (seller's own manage
          screen); implied when status=expired is asked for explicitly

        Ordering:
        - shuffled by default, a fresh random order on every pull, so
          every business gets a turn on top
        - shuffle=0 gives newest first (created_at DESC) instead
        - featured listings stay above the rest either way; the sort
          happens within each tier, so a handful of is_featured rows
          will look static at the top of the grid
        - NOTE: a fresh random order per request means a listing can
          repeat or be skipped across pages, so revisit shuffle if
          get_listings ever grows LIMIT/OFFSET pagination
    ========================================================
    */
    public function get_listings()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->get();

        log_message('error', "Get Listings Input: " . var_export($object, true));

        $token = isset($object['token']) ? trim($object['token']) : '';
        if (!$this->checkAPI_token_from_header()) {
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
            return;
        }

        $listing_id = isset($object['id'])         ? intval($object['id'])         : 0;
        $chapter_id = isset($object['chapter_id']) ? intval($object['chapter_id']) : null;
        $year       = isset($object['year'])       ? trim($object['year'])         : null;
        $user_id    = isset($object['user_id'])    ? intval($object['user_id'])    : null;
        $category   = isset($object['category'])   ? trim($object['category'])     : null;
        $status     = isset($object['status'])     ? trim($object['status'])       : null;
        $search     = isset($object['search'])     ? trim($object['search'])       : null;

        // Display window + ordering
        $include_expired = !empty($object['include_expired']) || $status === 'expired';
        $shuffle         = !isset($object['shuffle']) || (string)$object['shuffle'] !== '0';
        $today           = date('Y-m-d');

        if ($listing_id > 0) {
            // Single listing with social media
            $this->db->select('ml.*, u.fullname AS seller_name, u.email AS seller_email,
                            u.phone AS seller_phone, ac.chapter_name')
                ->from('marketplace_listings ml')
                ->join('users u', 'u.id = ml.user_id', 'left')
                ->join('alumni_chapter ac', 'ac.id = ml.chapter_id', 'left')
                ->where('ml.id', $listing_id);

            $listing = $this->db->get()->row_array();

            if (!$listing) {
                $this->output->set_status_header(404);
                $data = ['status' => 404, 'message' => 'Listing not found'];
            } else {
                $listing['images'] = !empty($listing['images'])
                    ? json_decode($listing['images'], true)
                    : [];

                // Increment view count
                $this->db->where('id', $listing_id)
                    ->set('views', 'views + 1', FALSE)
                    ->update('marketplace_listings');

                $social = $this->db->get_where('marketplace_social_media', ['market_id' => $listing_id])->row_array();
                $listing['social_media'] = $social ?: null;

                // Fetch-by-id still resolves an expired listing (direct links,
                // the owner's own preview) — the client decides how to treat it
                $listing['is_expired'] = !empty($listing['expires_at']) && $listing['expires_at'] < $today;

                $this->output->set_status_header(200);
                $data = ['status' => 200, 'message' => 'Listing retrieved successfully', 'listing' => $listing];
            }
        } else {
            // List with filters
            $this->db->select('ml.*, u.fullname AS seller_name, u.email AS seller_email,
                            ac.chapter_name')
                ->from('marketplace_listings ml')
                ->join('users u', 'u.id = ml.user_id', 'left')
                ->join('alumni_chapter ac', 'ac.id = ml.chapter_id', 'left');

            // chapter filter: chapter-specific OR global (NULL)
            if ($chapter_id) {
                $this->db->group_start()
                    ->where('ml.chapter_id', $chapter_id)
                    ->or_where('ml.chapter_id IS NULL')
                    ->group_end();
            }

            // year filter: year-specific OR global (NULL)
            if ($year) {
                $this->db->group_start()
                    ->where('ml.year', $year)
                    ->or_where('ml.year IS NULL')
                    ->group_end();
            }

            if ($user_id)  $this->db->where('ml.user_id', $user_id);
            if ($category) $this->db->where('ml.category', $category);
            if ($status)   $this->db->where('ml.status', $status);
            if ($search)   $this->db->like('ml.title', $search);

            // Display window: drop anything past its expiry date
            if (!$include_expired) {
                $this->db->group_start()
                    ->where('ml.expires_at IS NULL')
                    ->or_where('ml.expires_at >=', $today)
                    ->group_end();
            }

            // Featured stays on top; the sort happens inside each tier.
            // Unseeded RAND() by default; shuffle=0 swaps in newest-first.
            $this->db->order_by('ml.is_featured', 'DESC');
            if ($shuffle) {
                $this->db->order_by('', 'RANDOM');      // → ORDER BY RAND()
            } else {
                $this->db->order_by('ml.created_at', 'DESC');
            }

            $listings = $this->db->get()->result_array();

            // Socials for the whole page in one query, so cards can render them
            $socialMap = [];
            $listingIds = array_column($listings, 'id');
            if (!empty($listingIds)) {
                $socialRows = $this->db->where_in('market_id', $listingIds)
                    ->get('marketplace_social_media')
                    ->result_array();
                foreach ($socialRows as $row) {
                    $socialMap[$row['market_id']] = $row;
                }
            }

            foreach ($listings as &$item) {
                $item['images'] = !empty($item['images'])
                    ? json_decode($item['images'], true)
                    : [];
                $item['social_media'] = $socialMap[$item['id']] ?? null;
                $item['is_expired']   = !empty($item['expires_at']) && $item['expires_at'] < $today;
            }
            unset($item);

            $this->output->set_status_header(200);
            $data = [
                'status'   => 200,
                'message'  => 'Listings retrieved successfully',
                'total'    => count($listings),
                'rotation' => [
                    'shuffled' => $shuffle,
                    'mode'     => $shuffle ? 'random_per_request' : 'newest_first',
                ],
                'listings' => $listings,
            ];
        }

        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
    }


    /*=======================================================
        VERIFY EMAIL
        POST /api/verify_email
        Body: token, user_id, verify_code
    ========================================================*/
    public function verify_email()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents('php://input'), true)
            : $this->input->post();

        $token       = trim($object['token']       ?? '');
        $user_id     = trim($object['user_id']     ?? '');
        $verify_code = trim($object['verify_code'] ?? '');

        if (!$this->checkAPI_token_from_header()) {
            $this->output->set_status_header(401);
            echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
            return;
        }

        if (empty($user_id) || empty($verify_code)) {
            $this->output->set_status_header(400);
            echo json_encode(['status' => 400, 'message' => 'user_id and verify_code are required']);
            return;
        }

        $user = $this->db->get_where('users', ['id' => $user_id])->row_array();

        if (!$user) {
            echo json_encode(['status' => 404, 'message' => 'User not found']);
            return;
        }

        if ($user['email_verified'] == 1) {
            echo json_encode(['status' => 200, 'message' => 'Email already verified']);
            return;
        }

        // Look up active OTP in register_user_otp table
        $otp_row = $this->db->get_where('register_user_otp', [
            'email'     => $user['email'],
            'otp'       => $verify_code,
            'is_active' => 1,
        ])->row_array();

        if (!$otp_row) {
            header('Content-Type: application/json');
              $this->output->set_status_header(400);
            echo json_encode(['status' => 400, 'message' => 'Invalid verification code']);
            return;
        }

        // Check 24-hour expiry using created_at from register_user_otp
        $created_at = strtotime($otp_row['created_at']);
        if (time() > ($created_at + (24 * 60 * 60))) {
            header('Content-Type: application/json');
            echo json_encode([
                'status'  => 410,
                'message' => 'Verification code has expired. Please request a new one via /api/resend_verify_email',
            ]);
            return;
        }

        // Deactivate the used OTP
        $this->db->where('id', $otp_row['id']);
        $this->db->update('register_user_otp', ['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')]);

        // Mark email as verified in users table
        $this->db->where('id', $user_id);
        $this->db->update('users', [
            'email_verified' => 1,
            'verify_token'   => null,
            'active'         => 1,
        ]);

        // Notify admin that new account needs approval
        $this->_sendAdminNewAccountNotification($user['fullname'], $user['email'], 'New FGGC Alumni Account is Pending Approval');

        // If this registrant has a voucher assigned, now email the voucher to review
        $vouch = $this->db->get_where('vouches', ['register_id' => $user_id, 'status' => 'pending'])->row();
        if ($vouch) {
            $voucher_user = $this->db->get_where('users', ['id' => $vouch->voucher_id])->row();
            if ($voucher_user) {
                $this->_sendVoucherRequestEmail($voucher_user->email, $voucher_user->fullname, $user['fullname'], $user['email']);
            }
        }

        header('Content-Type: application/json');
        echo json_encode([
            'status'  => 200,
            'message' => 'Email verified successfully. Your account is pending admin approval.',
        ]);
    }

    /*=======================================================
        RESEND EMAIL VERIFY CODE
        POST /api/resend_verify_email
        Body: token, user_id

        - Checks if already verified
        - Generates fresh 6-digit code with new 24hr expiry
        - Stores as "CODE|EXPIRY_TIMESTAMP" in verify_token
        - Resends verification email
    ========================================================*/
    public function resend_verify_email()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents('php://input'), true)
            : $this->input->post();

        $token   = trim($object['token']   ?? '');
        $user_id = trim($object['user_id'] ?? '');

        if (!$this->checkAPI_token_from_header()) {
            $this->output->set_status_header(401);
            echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
            return;
        }

        if (empty($user_id)) {
            $this->output->set_status_header(400);
            echo json_encode(['status' => 400, 'message' => 'user_id is required']);
            return;
        }

        $user = $this->db->get_where('users', ['id' => $user_id])->row_array();

        if (!$user) {
            echo json_encode(['status' => 404, 'message' => 'User not found']);
            return;
        }

        if ($user['email_verified'] == 1) {
            echo json_encode(['status' => 200, 'message' => 'Email is already verified']);
            return;
        }

        // Generate fresh 6-digit OTP
        $new_code = mt_rand(100000, 999999);

        // Store new code in users.verify_token too (backup reference)
        $this->db->where('id', $user_id);
        $this->db->update('users', ['verify_token' => $new_code]);

        // sendVerifyEmail handles deactivating old OTPs + inserting new row
        // in register_user_otp + sending the email
        $this->sendVerifyEmail($user['email'], $user['fullname'], $new_code);

        header('Content-Type: application/json');
        echo json_encode([
            'status'  => 200,
            'message' => 'A new verification code has been sent to ' . $user['email'] . '. It expires in 24 hours.',
        ]);
    }    


    /*=============================================================
    |  ADMIN — APPROVE / REJECT USER
    |  POST /api/approve_user
    |  JWT required — admin only
    |
    |  Body:
    |    token        = api_token
    |    admin_id     = JWT user_id (must be admin/manager)
    |    user_id      = target user
    |    action       = approve | reject
    |    reject_reason= (optional, used when action=reject)
    |=============================================================*/
    
    public function approve_user()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents('php://input'), true)
            : $this->input->post();

        $token = trim($object['token'] ?? '');
        log_message('error',var_export($object, true));
        // Step 1: Validate API token
        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        // Step 3: Check if the logged-in user is admin/manager
        $requester_role = strtolower($jwtData->user_role ?? '');
        if (!in_array($requester_role, ['admin', 'manager', 'superadmin'])) {
            $this->output->set_status_header(403);
            header('Content-Type: application/json');
            echo json_encode(['status' => 403, 'message' => 'Access denied. Admin role required.']);
            return;
        }

        // Step 4: Validate inputs
        $user_id       = intval($object['user_id']      ?? 0);
        $action        = strtolower(trim($object['action'] ?? ''));
        $reject_reason = trim($object['reject_reason']  ?? '');

        if (!$user_id || !in_array($action, ['approve', 'reject'])) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'user_id and action (approve|reject) are required']);
            return;
        }

        // Step 5: Find the target user
        $user = $this->db->get_where('users', ['id' => $user_id])->row_array();
        if (!$user) {
            $this->output->set_status_header(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 404, 'message' => 'User not found']);
            return;
        }

        // Step 6: Approve or Reject
        if ($action === 'approve') {
            $this->db->where('id', $user_id)->update('users', [
                'is_approved'    => 1,
                'active'         => 1,
                'profile_status' => 'active',
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);
            $this->sendAccountStatusEmail($user['email'], $user['fullname'], 'approved');
            $message = 'User account approved successfully';

        } else {
            $this->db->where('id', $user_id)->update('users', [
                'is_approved'    => 0,
                'active'         => 0,
                'profile_status' => 'rejected',
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);
            $this->sendAccountStatusEmail($user['email'], $user['fullname'], 'rejected', $reject_reason);
            $message = 'User account rejected';
        }

        // Step 7: Return updated user
        $updated = $this->db->select('id, fullname, email, user_role, active, is_approved, profile_status')
            ->get_where('users', ['id' => $user_id])
            ->row_array();

        header('Content-Type: application/json');
        echo json_encode([
            'status'  => 200,
            'message' => $message,
            'user'    => $updated,
        ]);
    }

    /*=============================================================
    |  ADMIN — UPDATE USER ROLE
    |  POST /api/update_user_role
    |  JWT required — admin only
    |
    |  Body:
    |    token     = api_token
    |    user_id   = target user
    |    user_role = new role (alumni | admin | manager | etc.)
    |=============================================================*/
    public function update_user_role()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents('php://input'), true)
            : $this->input->post();

        $token = trim($object['token'] ?? '');

        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        $requester_role = strtolower($jwtData->user_role ?? '');
        $allowed_roles  = ['admin', 'manager', 'superadmin'];
        if (!in_array($requester_role, $allowed_roles)) {
            $this->output->set_status_header(403);
            header('Content-Type: application/json');
            echo json_encode(['status' => 403, 'message' => 'Access denied. Admin role required.']);
            return;
        }

        $user_id  = intval($object['user_id']   ?? 0);
        $new_role = trim($object['user_role']   ?? '');
        $chapter_id = trim($object['chapter_id'] ?? '');

        if (!$user_id || empty($new_role)) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'user_id and user_role are required']);
            return;
        }

        $user = $this->db->get_where('users', ['id' => $user_id])->row_array();
        if (!$user) {
            $this->output->set_status_header(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 404, 'message' => 'User not found']);
            return;
        }

        $updateData = [
            'user_role'  => $new_role,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if (!empty($chapter_id)) {
            $updateData['chapter_id'] = $chapter_id;
        }

        $this->db->where('id', $user_id)->update('users', $updateData);

        $updated = $this->db->select('id, fullname, email, user_role, chapter_id, active, is_approved')
            ->get_where('users', ['id' => $user_id])
            ->row_array();

        // Fetch user system roles
        $roles_query = $this->db
            ->select('GROUP_CONCAT(role_name SEPARATOR ";") AS system_role')
            ->from('roles')
            ->where('user_id', $user_id)
            ->get()->row_array();
        $system_role = $roles_query['system_role'] ?? '';

        header('Content-Type: application/json');
        echo json_encode([
            'status'   => 200,
            'message'  => 'User role updated successfully',
            'previous_role' => $user['user_role'],
            'user'     => $updated,
            'system_role' => $system_role,
        ]);
    }

    /*=============================================================
    |  ADMIN — MANAGE USER SYSTEM ROLES
    |  POST /api/manage_user_roles
    |  JWT required — admin only
    |
    |  Body:
    |    user_id = target user
    |    action = "add" | "remove" | "list"
    |    role_name = role to add/remove (required for add/remove)
    |=============================================================*/
    public function manage_user_roles()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents('php://input'), true)
            : $this->input->post();

        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        $requester_role = strtolower($jwtData->user_role ?? '');
        if (!in_array($requester_role, ['admin', 'manager', 'superadmin'])) {
            $this->output->set_status_header(403);
            header('Content-Type: application/json');
            echo json_encode(['status' => 403, 'message' => 'Access denied. Admin role required.']);
            return;
        }

        $user_id = intval($object['user_id'] ?? 0);
        $action = strtolower(trim($object['action'] ?? ''));
        $role_name = trim($object['role_name'] ?? '');

        if (!$user_id || !in_array($action, ['add', 'remove', 'list'])) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'user_id and action (add|remove|list) are required']);
            return;
        }

        $user = $this->db->get_where('users', ['id' => $user_id])->row_array();
        if (!$user) {
            $this->output->set_status_header(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 404, 'message' => 'User not found']);
            return;
        }

        if ($action === 'add') {
            if (empty($role_name)) {
                $this->output->set_status_header(400);
                header('Content-Type: application/json');
                echo json_encode(['status' => 400, 'message' => 'role_name is required for add action']);
                return;
            }

            $exists = $this->db->where('user_id', $user_id)
                ->where('role_name', $role_name)
                ->count_all_results('roles');

            if ($exists > 0) {
                $this->output->set_status_header(409);
                header('Content-Type: application/json');
                echo json_encode(['status' => 409, 'message' => 'User already has this role']);
                return;
            }

            $this->db->insert('roles', [
                'user_id' => $user_id,
                'role_name' => $role_name,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $message = "Role '{$role_name}' added successfully";

        } elseif ($action === 'remove') {
            if (empty($role_name)) {
                $this->output->set_status_header(400);
                header('Content-Type: application/json');
                echo json_encode(['status' => 400, 'message' => 'role_name is required for remove action']);
                return;
            }

            $this->db->where('user_id', $user_id)
                ->where('role_name', $role_name)
                ->delete('roles');
            $message = "Role '{$role_name}' removed successfully";

        } else {
            $roles = $this->db->where('user_id', $user_id)
                ->get('roles')
                ->result_array();

            $role_names = array_column($roles, 'role_name');
            $system_role = !empty($role_names) ? implode(';', $role_names) : '';

            header('Content-Type: application/json');
            echo json_encode([
                'status' => 200,
                'message' => 'User roles retrieved successfully',
                'user_id' => $user_id,
                'system_role' => $system_role,
                'roles' => $role_names
            ]);
            return;
        }

        $roles_query = $this->db
            ->select('GROUP_CONCAT(role_name SEPARATOR ";") AS system_role')
            ->from('roles')
            ->where('user_id', $user_id)
            ->get()->row_array();
        $system_role = $roles_query['system_role'] ?? '';

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 200,
            'message' => $message,
            'user_id' => $user_id,
            'system_role' => $system_role,
        ]);
    }

    /*=============================================================
    |  USER — UPDATE PROFILE
    |  POST /api/update_profile
    |  JWT required — own profile only (admin can update any)
    |
    |  Updates BOTH users table (core fields) and
    |  user_profiles table (social/professional fields)
    |
    |  Body (all optional except token + user_id):
    |    token, user_id
    |    -- users table --
    |    first_name, last_name, phone, bio, graduation_year,
    |    department, chapter_id, year
    |    avatar (file upload)
    |    -- user_profiles table --
    |    linkedin, twitter, facebook, website,
    |    current_company, current_position,
    |    city, country, skills, achievements
    |=============================================================*/

    public function update_profile()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents('php://input'), true)
            : $this->input->post();

        log_message('error', "Update Profile Input: " . var_export($object, true));

        $token = trim($object['token'] ?? '');

        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        $jwt_user_id   = (int)$jwtData->user_id;
        $jwt_user_role = strtolower($jwtData->user_role ?? '');
        $target_id     = intval($object['user_id'] ?? $jwt_user_id);
        $is_admin      = in_array($jwt_user_role, ['admin', 'manager', 'superadmin']);

        if (!$is_admin && $target_id !== $jwt_user_id) {
            $this->output->set_status_header(403);
            header('Content-Type: application/json');
            echo json_encode(['status' => 403, 'message' => 'You can only update your own profile']);
            return;
        }

        $user = $this->db->get_where('users', ['id' => $target_id])->row_array();
        if (!$user) {
            $this->output->set_status_header(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 404, 'message' => 'User not found']);
            return;
        }

        // ── Update users table ────────────────────────────────────────────────────
        // Use array_key_exists so only fields explicitly sent get updated.
        // A field sent as "" will update (clear) the value — intentional.
        // A field NOT present in the payload is completely ignored.
        $userStringFields = [
            // core
            'first_name', 'last_name', 'phone', 'bio',
            'graduation_year', 'department', 'chapter_id', 'year', 'birth_date',
            // alumni membership
            'name_in_school', 'alternative_phone', 'house_color',
            'residential_address', 'area', 'city',
            'employment_status', 'occupation', 'industry_sector', 'years_of_experience',
            'nick_name', 'state',
        ];

        $userData = [];

        foreach ($userStringFields as $field) {
            if (array_key_exists($field, $object)) {
                $val = $object[$field];
                $userData[$field] = is_string($val) ? trim($val) : $val;
            }
        }

        // Boolean fields — only update when key is present
        foreach (['is_coordinator', 'is_volunteer'] as $boolField) {
            if (array_key_exists($boolField, $object)) {
                $userData[$boolField] = filter_var(
                    $object[$boolField],
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                ) ? 1 : 0;
            }
        }

        // Rebuild fullname only if a name part was sent
        if (array_key_exists('first_name', $userData) || array_key_exists('last_name', $userData)) {
            $fn = $userData['first_name'] ?? $user['first_name'];
            $ln = $userData['last_name']  ?? $user['last_name'];
            $userData['fullname'] = trim($fn . ' ' . $ln);
        }

        // Avatar upload — only touches avatar column, nothing else
        if (!empty($_FILES['avatar']['name'])) {
            $uploaded = $this->uploadfiles('avatar', $target_id, 'profile_image');
            if ($uploaded) {
                $userData['avatar'] = $uploaded[0]->attachment_file;
            }
        }

        if (!empty($userData)) {
            $userData['updated_at'] = date('Y-m-d H:i:s');
            $userData['onboarding_completion'] = 1;
            $userData['active'] = 1;
            $this->db->where('id', $target_id)->update('users', $userData);
        }

        // ── Update user_profiles table ────────────────────────────────────────────
        // Accept profile fields from either top-level payload or nested `profile` object.
        $profileInput = [];
        if (isset($object['profile']) && is_array($object['profile'])) {
            $profileInput = $object['profile'];
        }

        $profileStringFields = [
            'linkedin', 'twitter', 'facebook', 'website','instagram','tiktok',
            'current_company', 'current_position',
            'city', 'country', 'skills', 'achievements',
        ];

        $profileData = [];
        foreach ($profileStringFields as $field) {
            if (array_key_exists($field, $profileInput)) {
                $val = $profileInput[$field];
                $profileData[$field] = is_string($val) ? trim($val) : $val;
            } elseif (array_key_exists($field, $object)) {
                // Backward compatibility for clients sending flat fields.
                $val = $object[$field];
                $profileData[$field] = is_string($val) ? trim($val) : $val;
            }
        }

        if (!empty($profileData)) {
            $profileData['updated_at'] = date('Y-m-d H:i:s');
            $exists = $this->db->get_where('user_profiles', ['user_id' => $target_id])->row();
            if ($exists) {
                $this->db->where('user_id', $target_id)->update('user_profiles', $profileData);
                if ($this->db->affected_rows() === 0) {
                    log_message('error', 'update_profile: user_profiles update matched no rows for user_id=' . $target_id . ' data=' . json_encode($profileData));
                }
            } else {
                $profileData['user_id']    = $target_id;
                $profileData['created_at'] = date('Y-m-d H:i:s');
                $this->db->insert('user_profiles', $profileData);
            }
        }

        if (empty($userData) && empty($profileData)) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'No fields provided to update']);
            return;
        }

        // Return fresh combined profile
        $updated_user    = $this->db->get_where('users', ['id' => $target_id])->row_array();
        $updated_profile = $this->db->get_where('user_profiles', ['user_id' => $target_id])->row_array();

        // Zone lookup via city match
        $zone_row = $this->db
            ->select('c.city_id, c.zone_id, z.zone AS zone_name')
            ->from('cities c')
            ->join('zones z', 'c.zone_id = z.zone_id', 'left')
            ->where('LOWER(c.city) =', strtolower($updated_user['city'] ?? ''))
            ->get()->row();

        foreach ([
            'password', 'salt', 'activation_code',
            'forgotten_password_selector', 'forgotten_password_code',
            'verify_token', 'reset_token',
        ] as $s) {
            unset($updated_user[$s]);
        }

        if (!empty($updated_user['avatar'])) {
            $updated_user['avatar'] = site_url($updated_user['avatar']);
        }

        header('Content-Type: application/json');
        echo json_encode([
            'status'   => 200,
            'message'  => 'Profile updated successfully',
            'user'     => $updated_user,
            'profile'  => $updated_profile ?: (object)[],
            'zone_id'   => isset($zone_row->zone_id)   ? (int) $zone_row->zone_id   : null,
            'zone_name' => $zone_row->zone_name ?? null,
            'city_id'   => isset($zone_row->city_id)   ? (int) $zone_row->city_id   : null,
        ]);
    }
    /*=============================================================
    |  USER — UPDATE PROFILE VISIBILITY
    |  POST /api/update_profile_visibility
    |  JWT required — own profile only
    |
    |  Controls whether the alumni profile is visible
    |  to other members in the directory.
    |
    |  Body:
    |    token      = api_token
    |    user_id    = your user_id
    |    is_visible = 1 (visible) | 0 (hidden)
    |=============================================================*/
    public function update_profile_visibility()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents('php://input'), true)
            : $this->input->post();

        $token = trim($object['token'] ?? '');

        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        $jwt_user_id   = (int)$jwtData->user_id;
        $jwt_user_role = strtolower($jwtData->user_role ?? '');
        $target_id     = intval($object['user_id'] ?? $jwt_user_id);
        $is_admin      = in_array($jwt_user_role, ['admin', 'manager', 'superadmin']);

        if (!$is_admin && $target_id !== $jwt_user_id) {
            $this->output->set_status_header(403);
            header('Content-Type: application/json');
            echo json_encode(['status' => 403, 'message' => 'You can only update your own profile visibility']);
            return;
        }

        // All fields that can be toggled private/public
        $controllableFields = [
            'avatar',               // profile picture
            'phone',
            'alternative_phone',
            'birth_date',
            'residential_address',
            'area',
            'city',
            'employment_status',
            'occupation',
            'industry_sector',
            'years_of_experience',
            'is_volunteer',
            'socials',
            'facebook',
            'tiktok',
        ];

        $updateData = ['updated_at' => date('Y-m-d H:i:s')];

        // Global profile visibility (whole profile in directory)
        if (array_key_exists('is_visible', $object)) {
            $updateData['is_visible'] = $this->parseBool($object['is_visible']) ? 1 : 0;
        }

        // Fetch existing field_visibility from DB
        $profile     = $this->db->get_where('user_profiles', ['user_id' => $target_id])->row();
        $existing_fv = [];

        if ($profile && !empty($profile->field_visibility)) {
            $decoded = json_decode($profile->field_visibility, true);
            if (is_array($decoded)) {
                $existing_fv = $decoded;
            }
        }

        // Merge only fields sent in the request
        // Accepts: true, false, "true", "false", 1, 0, "1", "0"
        $changed = false;
        foreach ($controllableFields as $field) {
            $visKey = $field . '_visible'; // e.g. avatar_visible, birth_date_visible
            if (array_key_exists($visKey, $object)) {
                $existing_fv[$field] = $this->parseBool($object[$visKey]);
                $changed = true;
            }
        }

        if ($changed) {
            $updateData['field_visibility'] = json_encode($existing_fv, JSON_UNESCAPED_SLASHES);
        }

        // Nothing to update
        if (count($updateData) === 1) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode([
                'status'  => 400,
                'message' => 'No visibility fields provided. Send keys like avatar_visible, phone_visible, birth_date_visible etc. Values: true or false',
            ]);
            return;
        }

        // Upsert
        if ($profile) {
            $this->db->where('user_id', $target_id)->update('user_profiles', $updateData);
        } else {
            $updateData['user_id']    = $target_id;
            $updateData['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert('user_profiles', $updateData);
        }

        // Return full visibility state
        $updated = $this->db->get_where('user_profiles', ['user_id' => $target_id])->row_array();
        $fv      = !empty($updated['field_visibility'])
            ? (json_decode($updated['field_visibility'], true) ?: [])
            : [];

        $visibilityMap = [];
        foreach ($controllableFields as $field) {
            // Default to true (public) if never set
        // $visibilityMap[$field] = isset($fv[$field]) ? (bool)$fv[$field] : true;
        // NEW - returns "public"/"private"
            $visibilityMap[$field] = (isset($fv[$field]) ? (bool)$fv[$field] : true) ? 'public' : 'private';
        }

        header('Content-Type: application/json');
        echo json_encode([
            'status'           => 200,
            'message'          => 'Visibility settings updated successfully',
            'user_id'          => $target_id,
            'is_visible'       => isset($updated['is_visible']) ? (bool)$updated['is_visible'] : true,
            'field_visibility' => $visibilityMap,
        ]);
    }

    public function get_profile_visibility()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents('php://input'), true)
            : $this->input->get();

        $token = trim($object['token'] ?? '');

       $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        $jwt_user_id = (int)$jwtData->user_id;
        $target_id   = intval($object['user_id'] ?? $jwt_user_id);

        $controllableFields = [
            'avatar',
            'phone',
            'alternative_phone',
            'birth_date',
            'residential_address',
            'area',
            'city',
            'employment_status',
            'occupation',
            'industry_sector',
            'years_of_experience',
            'is_volunteer',
            'socials',
            'facebook',
            'tiktok',
        ];

        $profile = $this->db->get_where('user_profiles', ['user_id' => $target_id])->row_array();

        $fv = [];
        if (!empty($profile['field_visibility'])) {
            $decoded = json_decode($profile['field_visibility'], true);
            if (is_array($decoded)) {
                $fv = $decoded;
            }
        }

        // Build map with defaults
        $visibilityMap = [];
        foreach ($controllableFields as $field) {
        // $visibilityMap[$field] = isset($fv[$field]) ? (bool)$fv[$field] : true;
        // NEW - returns "public"/"private"
            $visibilityMap[$field] = (isset($fv[$field]) ? (bool)$fv[$field] : true) ? 'public' : 'private';
        }

        header('Content-Type: application/json');
        echo json_encode([
            'status'           => 200,
            'message'          => 'Profile visibility retrieved successfully',
            'user_id'          => $target_id,
            'is_visible'       => isset($profile['is_visible']) ? (bool)$profile['is_visible'] : true,
            'field_visibility' => $visibilityMap,
        ]);
    }

    private function parseBool($val)
    {
        if (is_bool($val)) return $val;
        if (is_int($val))  return $val !== 0;
        $str = strtolower(trim((string)$val));
        return in_array($str, ['true', '1', 'yes', 'on'], true);
    }

    
    /*=======================================================
        ALUMNI STATS CARDS
        GET or POST /api/get_alumni_stats
        Returns the 4 summary cards for the directory header
    ========================================================*/
    public function get_alumni_stats()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->get();

        $token = isset($object['token']) ? trim($object['token']) : '';
        if (!$this->checkAPI_token_from_header()) {
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
            return;
        }

        $total_alumni = $this->db
            ->where('is_approved', 1)
            ->where('active', 1)
            ->where('user_role', 'alumni')
            ->count_all_results('users');

        $total_years = $this->db
            ->select('COUNT(DISTINCT year) AS cnt')
            ->where('year IS NOT NULL')
            ->get('alumni_category')
            ->row()->cnt;

        $total_chapters = $this->db
            ->where('is_enabled', 1)
            ->count_all_results('alumni_chapter');

        $total_departments = $this->db
            ->select('COUNT(DISTINCT department) AS cnt')
            ->where('department IS NOT NULL')
            ->where('department !=', '')
            ->where('is_approved', 1)
            ->get('users')
            ->row()->cnt;

        $this->output->set_status_header(200);
        header('Content-Type: application/json');
        echo json_encode([
            'status'  => 200,
            'message' => 'Alumni stats retrieved successfully',
            'stats'   => [
                'total_alumni'      => (int) $total_alumni,
                'total_years'       => (int) $total_years,
                'total_chapters'    => (int) $total_chapters,
                'total_departments' => (int) $total_departments,
            ],
        ]);
    }


    public function import_alumni()
    {
        // ── 1. Token check ────────────────────────────────────────
        $contentType = $this->input->server('CONTENT_TYPE') ?? '';
        $isJson      = strpos($contentType, 'application/json') !== false;

        if ($isJson) {
            $body   = file_get_contents('php://input');
            $object = json_decode($body, true) ?: [];
        } else {
            $object = $this->input->post();
        }

        $token = trim($object['token'] ?? '');
        if (!$this->checkAPI_token_from_header()) {
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
            return;
        }

        $chapter_id       = intval($object['chapter_id'] ?? 1);
        $default_password = trim($object['default_password'] ?? 'Alumni@2026');

        // ── 2. Load rows ──────────────────────────────────────────
        $rows = [];

        if (!$isJson && !empty($_FILES['file']['name'])) {
            $ext      = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            $tmp_path = $_FILES['file']['tmp_name'];

            if ($ext === 'csv') {
                $rows = $this->_parseCsv($tmp_path);
            } elseif (in_array($ext, ['xlsx', 'xls'])) {
                $rows = $this->_parseXlsx($tmp_path);
            } else {
                $this->output->set_status_header(422);
                header('Content-Type: application/json');
                echo json_encode(['status' => 422, 'message' => 'Unsupported file type. Use .xlsx or .csv']);
                return;
            }

        } elseif ($isJson && !empty($object['records']) && is_array($object['records'])) {
            $rows = $object['records'];

        } else {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'Provide a .xlsx/.csv file upload (field: file) or a JSON records array']);
            return;
        }

        if (empty($rows)) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'No data rows found in the provided source']);
            return;
        }

        // ── 3. Process rows ───────────────────────────────────────
        $results  = [];
        $imported = 0;
        $updated  = 0;
        $failed   = 0;

        foreach ($rows as $index => $row) {

            $row_num = $index + 2; // row 1 = header

            // Normalise keys: lowercase + trim
            $r = [];
            foreach ($row as $k => $v) {
                $r[strtolower(trim((string)$k))] = is_string($v) ? trim($v) : $v;
            }

            // ── Validate email ────────────────────────────────────
            $email = strtolower(trim($r['email'] ?? $r['email address'] ?? ''));
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $results[] = ['row' => $row_num, 'email' => $email ?: 'N/A', 'status' => 'failed', 'reason' => 'Invalid or missing email'];
                $failed++;
                continue;
            }

            // ── Map all fields ────────────────────────────────────
            $surname        = trim($r['surname'] ?? '');
            $other_names    = trim($r['other names'] ?? $r['other_names'] ?? '');
            $first_name     = explode(' ', $other_names)[0] ?? '';
            $last_name      = $surname;
            $fullname       = trim($other_names . ' ' . $surname);
            $name_in_school = trim($r['name in school (first name + surname)'] ?? $r['name_in_school'] ?? $fullname);
            $phone          = trim($r['whatsapp mobile phone number (e.g. 080xxxxxxxx)'] ?? $r['phone'] ?? '');
            $alt_phone      = trim($r['alternative phone number'] ?? $r['alternative_phone'] ?? '') ?: null;

            // birth_date
            $birth_date_raw = trim($r['birth date'] ?? $r['birth_date'] ?? '');
            $birth_date     = null;
            if (!empty($birth_date_raw)) {
                try {
                    $birth_date = (new DateTime($birth_date_raw))->format('Y-m-d');
                } catch (Exception $e) {
                    $birth_date = null;
                }
            }

            // graduation year
            $grad_year_raw   = $r['year of graduation from fggc owerri (e.g. 1994)']
                            ?? $r['graduation_year']
                            ?? $r['year of graduation']
                            ?? '';
            $graduation_year = intval($grad_year_raw) ?: null;
            $year            = $graduation_year ? (string)$graduation_year : date('Y');

            $house_color    = trim($r['house color'] ?? $r['house_color'] ?? '');
            $is_coordinator = $this->_toBool($r['are  you the coordinator for your class?'] ?? $r['is_coordinator'] ?? 'no') ?true :false;
            $address        = trim($r['residential address (please include the closest bus stop/ landmark)'] ?? $r['residential_address'] ?? '');
            $area           = trim($r['area'] ?? '');

            // city — handle multiline Excel header
            $city = '';
            foreach ($r as $rk => $rv) {
                if (strpos($rk, 'city') !== false) { $city = trim((string)$rv); break; }
            }
            if (empty($city)) $city = trim($r['city'] ?? '');

            $emp_status  = trim($r['current employment status'] ?? $r['employment_status'] ?? '');
            $occupation  = trim($r['occupation(s)/ profession(s). fill as appropriate.'] ?? $r['occupation'] ?? '');
            $industry    = trim($r['industry sector (select as many as are applicable)'] ?? $r['industry_sector'] ?? '');
            $yrs_exp     = trim($r['years of professional experience'] ?? $r['years_of_experience'] ?? '') ?: null;
            $is_volunteer = $this->_toBool($r['would you be interested in volunteering for any project/ initiative?'] ?? $r['is_volunteer'] ?? 'no') ?true :false;

            // ── Timestamp from sheet → created_at ─────────────────
            $ts_raw    = $r['timestamp'] ?? $r['created_at'] ?? null;
            $created_at = date('Y-m-d H:i:s'); // fallback to now
            if (!empty($ts_raw)) {
                try {
                    $created_at = (new DateTime((string)$ts_raw))->format('Y-m-d H:i:s');
                } catch (Exception $e) {
                    $created_at = date('Y-m-d H:i:s');
                }
            }

            // ── Shared update payload (used for both insert & update)
            $profile_data = [
                'first_name'          => $first_name,
                'last_name'           => $last_name,
                'fullname'            => $fullname,
                'phone'               => $phone,
                'alternative_phone'   => $alt_phone,
                'name_in_school'      => $name_in_school,
                'birth_date'          => $birth_date,
                'house_color'         => $house_color,
                'is_coordinator'      => $is_coordinator,
                'graduation_year'     => $graduation_year,
                'year'                => $year,
                'residential_address' => $address,
                'area'                => $area,
                'city'                => $city,
                'employment_status'   => $emp_status,
                'occupation'          => $occupation,
                'industry_sector'     => $industry,
                'years_of_experience' => $yrs_exp,
                'is_volunteer'        => $is_volunteer,
                'created_at'          => $created_at, // from sheet Timestamp
            ];

            // ══════════════════════════════════════════════════════
            // BRANCH A — Email already exists → UPDATE
            // ══════════════════════════════════════════════════════
            if ($this->ion_auth->email_check($email)) {

                $existing = $this->db->get_where('users', ['email' => $email])->row();
                $user_id  = $existing->id;

                // Preserve existing user_code & userAccessCode; only set if missing
                $update = array_merge($profile_data, [
                    'is_approved'    => 1,
                    'email_verified' => 1,
                    'active'         => 1,
                    'profile_status' => 'active',
                ]);

                // Only set user_code if the existing row has none
                if (empty($existing->user_code)) {
                    $update['user_code'] = $this->_generateUserCode($graduation_year,$email);
                }
                if (empty($existing->userAccessCode)) {
                    $update['userAccessCode'] = $this->generateAccessCode($email);
                }
                $update['user_code'] = $this->_generateUserCode($graduation_year,$email);

                $this->db->where('id', $user_id)->update('users', $update);

                // Upsert alumni_category
                $cat = $this->db->get_where('alumni_category', ['user_id' => $user_id])->row();
                if ($cat) {
                    $this->db->where('user_id', $user_id)->update('alumni_category', [
                        'chapter_id' => $chapter_id,
                        'year'       => $year,
                        'location'   => $city,
                    ]);
                } else {
                    $this->db->insert('alumni_category', [
                        'user_id'    => $user_id,
                        'chapter_id' => $chapter_id,
                        'year'       => $year,
                        'location'   => $city,
                        'created_at' => $created_at,
                    ]);
                }

                // Upsert user_profiles
                $prof = $this->db->get_where('user_profiles', ['user_id' => $user_id])->row();
                if ($prof) {
                    $this->db->where('user_id', $user_id)->update('user_profiles', [
                        'chapter_id' => $chapter_id,
                        'year'       => $year,
                        'city'       => $city ?: null,
                    ]);
                } else {
                    $this->db->insert('user_profiles', [
                        'user_id'    => $user_id,
                        'chapter_id' => $chapter_id,
                        'year'       => $year,
                        'city'       => $city ?: null,
                        'created_at' => $created_at,
                    ]);
                }

                $results[] = [
                    'row'         => $row_num,
                    'email'       => $email,
                    'status'      => 'updated',
                    'user_id'     => $user_id,
                    'user_code'   => $existing->user_code ?? ($update['user_code'] ?? null),
                    'access_code' => $existing->userAccessCode ?? ($update['userAccessCode'] ?? null),
                    'fullname'    => $fullname,
                    'created_at'  => $created_at,
                ];
                $updated++;

            // ══════════════════════════════════════════════════════
            // BRANCH B — New email → INSERT via ion_auth
            // ══════════════════════════════════════════════════════
            } else {

                $user_code   = $this->_generateUserCode($graduation_year,$email);
                $access_code = $this->generateAccessCode($email);

                $additional_data = array_merge($profile_data, [
                    'user_role'      => 'alumni',
                    'is_approved'    => 1,
                    'email_verified' => 1,
                    'active'         => 1,
                    'profile_status' => 'active',
                    'user_code'      => $user_code,
                    'userAccessCode' => $access_code,
                    'verify_token'   => null,
                    'department'     => '',
                ]);

                $group   = ['2']; // alumni group
                $user_id = $this->ion_auth->register($email, $default_password, $email, $additional_data, $group);

                if ($user_id) {
                    // Patch created_at — ion_auth sets it to NOW(), overwrite with sheet value
                    $this->db->where('id', $user_id)->update('users', [
                        'created_at' => $created_at,
                    ]);

                    // Seed alumni_category
                    $this->db->insert('alumni_category', [
                        'user_id'    => $user_id,
                        'chapter_id' => $chapter_id,
                        'year'       => $year,
                        'location'   => $city,
                        'created_at' => $created_at,
                    ]);

                    // Seed user_profiles
                    $this->db->insert('user_profiles', [
                        'user_id'          => $user_id,
                        'chapter_id'       => $chapter_id,
                        'year'             => $year,
                        'city'             => $city ?: null,
                        'is_visible'       => 0,
                        'field_visibility' => json_encode(['avatar'=>false,'phone'=>false,'alternative_phone'=>false,'birth_date'=>false,'residential_address'=>false,'area'=>false,'city'=>false,'employment_status'=>false,'occupation'=>false,'industry_sector'=>false,'years_of_experience'=>false,'is_volunteer'=>false]),
                        'created_at'       => $created_at,
                    ]);

                    $results[] = [
                        'row'         => $row_num,
                        'email'       => $email,
                        'status'      => 'imported',
                        'user_id'     => $user_id,
                        'user_code'   => $user_code,
                        'access_code' => $access_code,
                        'fullname'    => $fullname,
                        'created_at'  => $created_at,
                    ];
                    $imported++;

                } else {
                    $results[] = [
                        'row'    => $row_num,
                        'email'  => $email,
                        'status' => 'failed',
                        'reason' => 'ion_auth register failed',
                    ];
                    $failed++;
                }
            }
        }

        // ── 4. Response ───────────────────────────────────────────
        $this->output->set_status_header(200);
        header('Content-Type: application/json');
        echo json_encode([
            'status'  => 200,
            'message' => "Import complete: {$imported} imported, {$updated} updated, {$failed} failed",
            'summary' => [
                'total'    => count($rows),
                'imported' => $imported,
                'updated'  => $updated,
                'failed'   => $failed,
            ],
            'results' => $results,
        ], JSON_PRETTY_PRINT);
    }


    /*──────────────────────────────────────────────────────────────
    |  Parse CSV file → array of associative rows
    ──────────────────────────────────────────────────────────────*/
    private function _parseCsv($path)
    {
        $rows    = [];
        $headers = [];
        $line    = 0;

        if (($handle = fopen($path, 'r')) === false) return $rows;

        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($line === 0) {
                $headers = array_map('trim', $data);
            } else {
                if (count($data) === count($headers)) {
                    $rows[] = array_combine($headers, $data);
                }
            }
            $line++;
        }

        fclose($handle);
        return $rows;
    }


    /*──────────────────────────────────────────────────────────────
    |  Parse XLSX file → array of associative rows
    |  Requires: composer require phpoffice/phpspreadsheet
    ──────────────────────────────────────────────────────────────*/
    private function _parseXlsx($path)
    {
        $autoloadPath = FCPATH . 'vendor/autoload.php';
        if (!file_exists($autoloadPath)) {
            log_message('error', 'import_alumni: vendor/autoload.php not found');
            return [];
        }

        require_once $autoloadPath;

        if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            log_message('error', 'import_alumni: PhpSpreadsheet not installed');
            return [];
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $sheet       = $spreadsheet->getActiveSheet();
            $data        = $sheet->toArray(null, true, true, false);
        } catch (Exception $e) {
            log_message('error', 'import_alumni XLSX error: ' . $e->getMessage());
            return [];
        }

        if (empty($data)) return [];

        $headers = array_map('trim', $data[0]);
        $rows    = [];

        for ($i = 1; $i < count($data); $i++) {
            $row = $data[$i];
            if (empty(array_filter($row, fn($v) => !is_null($v) && $v !== ''))) continue;
            if (count($row) === count($headers)) {
                $rows[] = array_combine($headers, $row);
            }
        }

        return $rows;
    }


    /*──────────────────────────────────────────────────────────────
    |  Convert Yes / No / true / false / 1 / 0 → boolean
    ──────────────────────────────────────────────────────────────*/
    private function _toBool($val)
    {
        if (is_bool($val)) return $val;
        return in_array(strtolower(trim((string)$val)), ['yes', '1', 'true', 'on'], true);
    }
    /*
    |=============================================================
    |  ALUMNI PORTAL — PROJECTS API (UPDATED)
    |  Multi-image support for create_project & manage_project
    |
    |  Changes from v1:
    |    - image column → images (TEXT, stores JSON array)
    |    - Upload field: images[]  (multiple files allowed)
    |    - manage_project supports:
    |        add_images   → merges new uploads with existing
    |        remove_images → removes specific URLs from the array
    |        replace_images → clears all existing, sets new uploads
    |
    |  ALTER TABLE required (run once):
    |    ALTER TABLE `projects`
    |      CHANGE `image` `images` TEXT DEFAULT NULL
    |      COMMENT 'JSON array of image URLs';
    |=============================================================
    */


    /*=======================================================
        PROJECTS — CREATE
        POST /api/create_project
        Auth: API token + JWT (admin / manager / superadmin only)

        Body: form-data (for images) OR raw JSON (no images)
        token, title, description, amount_raised,
        target_amount (opt), status (opt),
        chapter_id (opt), year (opt),
        sort_order (opt), is_featured (opt)

        File field : images[]   (multiple files, optional)
                    e.g. images[0], images[1], images[2] ...
    ========================================================*/
    public function create_project()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents('php://input'), true)
            : $this->input->post();

        log_message('error', 'Create Project Input: ' . var_export($object, true));

        $token = trim($object['token'] ?? '');
        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        $requester_role = strtolower($jwtData->user_role ?? '');
        if (!in_array($requester_role, ['admin', 'manager', 'superadmin'])) {
            $this->output->set_status_header(403);
            header('Content-Type: application/json');
            echo json_encode(['status' => 403, 'message' => 'Access denied. Admin role required.']);
            return;
        }

        $title = trim($object['title'] ?? '');
        if (empty($title)) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'title is required']);
            return;
        }

        $created_by    = (int)$jwtData->user_id;
        $description   = trim($object['description']   ?? '');
        $amount_raised = isset($object['amount_raised']) ? floatval($object['amount_raised']) : 0.00;
        $target_amount = isset($object['target_amount']) && $object['target_amount'] !== ''
                        ? floatval($object['target_amount']) : null;
        $status        = trim($object['status']      ?? 'active');
        $chapter_id    = isset($object['chapter_id']) && $object['chapter_id'] !== ''
                        ? intval($object['chapter_id']) : 1;
        $year          = trim($object['year']        ?? '');
        $sort_order    = isset($object['sort_order'])  ? intval($object['sort_order'])       : 0;
        $is_featured   = isset($object['is_featured']) ? intval((bool)$object['is_featured']) : 0;
        $start_date    = trim($object['start_date']   ?? '');
        $end_date      = trim($object['end_date']     ?? '');
        $conducted_by  = trim($object['conducted_by'] ?? '');
        $location      = trim($object['location']     ?? '');

        $allowed_statuses = ['active', 'completed', 'paused', 'draft', 'ongoing'];
        if (!in_array($status, $allowed_statuses)) {
            $this->output->set_status_header(422);
            header('Content-Type: application/json');
            echo json_encode(['status' => 422, 'message' => 'Invalid status. Use: active | completed | paused | draft | ongoing']);
            return;
        }

        if ($chapter_id) {
            $chapter = $this->db->get_where('alumni_chapter', ['id' => $chapter_id, 'is_enabled' => 1])->row();
            if (!$chapter) {
                $this->output->set_status_header(404);
                header('Content-Type: application/json');
                echo json_encode(['status' => 404, 'message' => 'Chapter not found or not enabled']);
                return;
            }
        }

        // ── Upload images[] via existing uploadfiles() ────────
        $uploadedImages = [];
        if (!empty($_FILES['images']['name'][0])) {
            $uploaded = $this->uploadfiles('images', $created_by, 'project_banner');
            if ($uploaded) {
                foreach ($uploaded as $file) {
                    $uploadedImages[] = site_url($file->attachment_file);
                }
            }
        }

        $insertData = [
            'title'         => $title,
            'description'   => $description,
            'images'        => !empty($uploadedImages)
                            ? json_encode($uploadedImages, JSON_UNESCAPED_SLASHES)
                            : null,
            'amount_raised' => $amount_raised,
            'target_amount' => $target_amount,
            'status'        => $status,
            'chapter_id'    => $chapter_id,
            'year'          => $year ?: null,
            'start_date'    => $start_date   ?: null,
            'end_date'      => $end_date     ?: null,
            'conducted_by'  => $conducted_by ?: null,
            'location'      => $location     ?: null,
            'sort_order'    => $sort_order,
            'is_featured'   => $is_featured,
            'is_deleted'    => 0,
            'created_by'    => $created_by,
            'created_at'    => date('Y-m-d H:i:s'),
        ];

        $this->db->insert('projects', $insertData);
        $project_id = $this->db->insert_id();

        if (!$project_id) {
            $this->output->set_status_header(500);
            header('Content-Type: application/json');
            echo json_encode(['status' => 500, 'message' => 'Project creation failed. Please try again.']);
            return;
        }

        $insertData['id']     = $project_id;
        $insertData['images'] = $uploadedImages;

        $this->output->set_status_header(200);
        header('Content-Type: application/json');
        echo json_encode([
            'status'      => 200,
            'message'     => 'Project created successfully',
            'image_count' => count($uploadedImages),
            'project'     => $insertData,
        ]);
    }

    /*=======================================================
        PROJECTS — MANAGE (UPDATE / DELETE)
        POST /api/manage_project
        Auth: API token + JWT (admin / manager / superadmin only)

        Body:
        token, id, function_type (update | delete)

        For update, supply any of:
        title, description, amount_raised, target_amount,
        status, chapter_id, year, sort_order, is_featured

        Image actions (all optional — use one at a time):
        images[]        → ADD new images to existing list
        image_action    → "replace" clears ALL existing images
                            before adding new uploads
                            "add" (default) merges with existing
        remove_images[] → JSON array of full URLs to remove
                            e.g. ["https://.../img1.jpg","https://.../img2.jpg"]
                            Pass as a JSON string in form-data:
                            remove_images: '["url1","url2"]'
    ========================================================*/
    public function manage_project()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents('php://input'), true)
            : $this->input->post();

        log_message('error', 'Manage Project Input: ' . var_export($object, true));

        $token = trim($object['token'] ?? '');
        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        $requester_role = strtolower($jwtData->user_role ?? '');
        if (!in_array($requester_role, ['admin', 'manager', 'superadmin'])) {
            $this->output->set_status_header(403);
            header('Content-Type: application/json');
            echo json_encode(['status' => 403, 'message' => 'Access denied. Admin role required.']);
            return;
        }

        $project_id    = intval($object['id']            ?? 0);
        $function_type = strtolower(trim($object['function_type'] ?? ''));

        if (!$project_id || !$function_type) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'id and function_type are required']);
            return;
        }

        $project = $this->db->get_where('projects', ['id' => $project_id, 'is_deleted' => 0])->row();
        if (!$project) {
            $this->output->set_status_header(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 404, 'message' => 'Project not found']);
            return;
        }

        // ── DELETE ────────────────────────────────────────────
        if ($function_type === 'delete') {
            $this->db->where('id', $project_id)->update('projects', [
                'is_deleted' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->output->set_status_header(200);
            header('Content-Type: application/json');
            echo json_encode(['status' => 200, 'message' => 'Project deleted successfully']);
            return;
        }

        // ── UPDATE ────────────────────────────────────────────
        if ($function_type === 'update') {
            $updateData = [];

            // Scalar / text fields
            foreach (['title', 'description', 'status', 'chapter_id', 'year', 'start_date', 'end_date', 'conducted_by', 'location', 'sort_order', 'is_featured'] as $field) {
                if (isset($object[$field]) && $object[$field] !== '') {
                    $updateData[$field] = $object[$field];
                }
            }

            if (isset($updateData['status'])) {
                $allowed = ['active', 'completed', 'paused', 'draft', 'ongoing'];
                if (!in_array($updateData['status'], $allowed)) {
                    $this->output->set_status_header(422);
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 422, 'message' => 'Invalid status. Use: active | completed | paused | draft | ongoing']);
                    return;
                }
            }

            if (isset($object['amount_raised']) && $object['amount_raised'] !== '') {
                $updateData['amount_raised'] = floatval($object['amount_raised']);
            }
            if (array_key_exists('target_amount', $object)) {
                $updateData['target_amount'] = ($object['target_amount'] !== '' && $object['target_amount'] !== null)
                    ? floatval($object['target_amount']) : null;
            }

            // ── Image management ──────────────────────────────
            $image_action = strtolower(trim($object['image_action'] ?? 'add')); // add | replace
            $admin_id     = (int)$jwtData->user_id;

            // Decode existing images stored in DB
            $existingImages = [];
            if (!empty($project->images)) {
                $decoded = json_decode($project->images, true);
                if (is_array($decoded)) {
                    $existingImages = $decoded;
                }
            }

            // Step A — remove specific images by URL if requested
            $toRemove = [];
            if (!empty($object['remove_images'])) {
                $raw      = $object['remove_images'];
                $toRemove = is_string($raw)
                    ? (json_decode($raw, true) ?: [])
                    : (is_array($raw) ? $raw : []);
            }
            if (!empty($toRemove)) {
                $existingImages = array_values(
                    array_filter($existingImages, fn($url) => !in_array($url, $toRemove))
                );
            }

            // Step B — upload new images[] via uploadfiles()
            $newUploads = [];
            if (!empty($_FILES['images']['name'][0])) {
                $uploaded = $this->uploadfiles('images', $admin_id, 'project_banner');
                if ($uploaded) {
                    foreach ($uploaded as $file) {
                        $newUploads[] = site_url($file->attachment_file);
                    }
                }
            }

            // Step C — merge or replace
            if (!empty($newUploads)) {
                $finalImages          = ($image_action === 'replace')
                    ? $newUploads
                    : array_merge($existingImages, $newUploads);
                $updateData['images'] = json_encode($finalImages, JSON_UNESCAPED_SLASHES);
            } elseif (!empty($toRemove)) {
                $updateData['images'] = !empty($existingImages)
                    ? json_encode($existingImages, JSON_UNESCAPED_SLASHES)
                    : null;
            } elseif ($image_action === 'replace') {
                // replace with no uploads = clear all images
                $updateData['images'] = null;
            }

            if (empty($updateData)) {
                $this->output->set_status_header(422);
                header('Content-Type: application/json');
                echo json_encode(['status' => 422, 'message' => 'No fields provided to update']);
                return;
            }

            $updateData['updated_at'] = date('Y-m-d H:i:s');
            $this->db->where('id', $project_id)->update('projects', $updateData);

            // Decode images for the response
            $responseImages = [];
            if (isset($updateData['images'])) {
                $responseImages = !empty($updateData['images'])
                    ? (json_decode($updateData['images'], true) ?: [])
                    : [];
            } else {
                $responseImages = $existingImages;
            }

            $this->output->set_status_header(200);
            header('Content-Type: application/json');
            echo json_encode([
                'status'      => 200,
                'message'     => 'Project updated successfully',
                'image_count' => count($responseImages),
                'project'     => array_merge(
                    ['id' => $project_id],
                    $updateData,
                    ['images' => $responseImages]
                ),
            ]);
            return;
        }

        $this->output->set_status_header(400);
        header('Content-Type: application/json');
        echo json_encode(['status' => 400, 'message' => 'Invalid function_type. Use: update | delete']);
    }

    /*=======================================================
        PROJECTS — GET
        POST /api/get_projects
        Auth: API token only (public — no JWT required)

        Optional filters:
        id, status, chapter_id, year, is_featured,
        limit (default 10, max 100), offset (default 0)
    ========================================================*/
    public function get_projects()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents('php://input'), true)
            : $this->input->get();

        log_message('error', 'Get Projects Input: ' . var_export($object, true));

        $token = trim($object['token'] ?? '');
        if (!$this->checkAPI_token_from_header()) {
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
            return;
        }

        $id          = isset($object['id'])          ? intval($object['id'])              : 0;
        $chapter_id  = isset($object['chapter_id'])  ? intval($object['chapter_id'])      : null;
        $year        = isset($object['year'])        ? trim($object['year'])              : null;
        $status      = isset($object['status'])      ? trim($object['status'])            : null;
        $is_featured = isset($object['is_featured']) ? intval($object['is_featured'])     : null;
        $limit       = isset($object['limit'])       ? min(intval($object['limit']), 100) : 10;
        $offset      = isset($object['offset'])      ? intval($object['offset'])          : 0;

        // ── Single project ────────────────────────────────────
        if ($id > 0) {
            $project = $this->db
                ->select('p.*, u.fullname AS created_by_name, ac.chapter_name')
                ->from('projects p')
                ->join('users u',           'u.id = p.created_by',   'left')
                ->join('alumni_chapter ac',  'ac.id = p.chapter_id', 'left')
                ->where('p.id', $id)
                ->where('p.is_deleted', 0)
                ->get()->row_array();

            if (!$project) {
                $this->output->set_status_header(404);
                header('Content-Type: application/json');
                echo json_encode(['status' => 404, 'message' => 'Project not found']);
                return;
            }

            $raw                 = $project['images'] ?? null;
            $project['images']   = (!empty($raw) && is_array($dec = json_decode($raw, true))) ? $dec : [];

            $this->output->set_status_header(200);
            header('Content-Type: application/json');
            echo json_encode([
                'status'  => 200,
                'message' => 'Project retrieved successfully',
                'project' => $project,
            ]);
            return;
        }

        // ── List with filters ─────────────────────────────────
        $this->db
            ->select('p.*, u.fullname AS created_by_name, ac.chapter_name')
            ->from('projects p')
            ->join('users u',           'u.id = p.created_by',   'left')
            ->join('alumni_chapter ac',  'ac.id = p.chapter_id', 'left')
            ->where('p.is_deleted', 0);

        if ($status) {
            $this->db->where('p.status', $status);
        } else {
            // Exclude drafts from public listing
            $this->db->where_in('p.status', ['active', 'completed', 'paused','ongoing']);
        }

        if ($chapter_id) {
            $this->db->group_start()
                ->where('p.chapter_id', $chapter_id)
                ->or_where('p.chapter_id IS NULL')
                ->group_end();
        }

        if ($year) {
            $this->db->group_start()
                ->where('p.year', $year)
                ->or_where('p.year IS NULL')
                ->group_end();
        }

        if ($is_featured !== null) {
            $this->db->where('p.is_featured', $is_featured);
        }

        $this->db
            ->order_by('p.is_featured', 'DESC')
            ->order_by('p.sort_order',  'ASC')
            ->order_by('p.created_at',  'DESC')
            ->limit($limit, $offset);

        $projects = $this->db->get()->result_array();

        foreach ($projects as &$proj) {
            $raw            = $proj['images'] ?? null;
            $proj['images'] = (!empty($raw) && is_array($dec = json_decode($raw, true))) ? $dec : [];
        }
        unset($proj);

        $this->output->set_status_header(200);
        header('Content-Type: application/json');
        echo json_encode([
            'status'   => 200,
            'message'  => 'Projects retrieved successfully',
            'total'    => count($projects),
            'limit'    => $limit,
            'offset'   => $offset,
            'projects' => $projects,
        ]);
    }

    /*──────────────────────────────────────────────────────────
    |  Private: upload images[] for a project
    |  Returns array of full base_url() image URLs
    |  Follows the same pattern as create_listing / manage_listing
    ──────────────────────────────────────────────────────────*/
    private function uploadProjectImages($user_id)
    {
        $uploadedImages = [];

        if (empty($_FILES['images']['name'][0])) {
            return $uploadedImages;
        }

        $uploadPath = './uploads/projects/';
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        $this->load->library('upload');
        $filesCount = count($_FILES['images']['name']);

        for ($i = 0; $i < $filesCount; $i++) {

            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
                log_message('error', "Project image[$i] upload error: " . $_FILES['images']['error'][$i]);
                continue;
            }

            // Re-map to the standard $_FILES['file'] key for CI Upload library
            $_FILES['file'] = [
                'name'     => $_FILES['images']['name'][$i],
                'type'     => $_FILES['images']['type'][$i],
                'tmp_name' => $_FILES['images']['tmp_name'][$i],
                'error'    => $_FILES['images']['error'][$i],
                'size'     => $_FILES['images']['size'][$i],
            ];

            $config = [
                'upload_path'   => $uploadPath,
                'allowed_types' => 'jpg|jpeg|png|gif|webp',
                'max_size'      =>10240,     // 10 MB per image
                'encrypt_name'  => TRUE,
            ];
            $this->upload->initialize($config);

            if ($this->upload->do_upload('file')) {
                $fileData         = $this->upload->data();
                $uploadedImages[] = base_url('uploads/projects/' . $fileData['file_name']);
            } else {
                log_message('error', "Project image[$i] CI upload error: " . $this->upload->display_errors());
            }
        }

        return $uploadedImages;
    }

    /*──────────────────────────────────────────────────────────
    |  Private: safely decode a JSON images string from DB
    |  Always returns a plain PHP array (never null/false)
    ──────────────────────────────────────────────────────────*/
    private function decodeProjectImages($raw)
    {
        if (empty($raw)) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }


    /*
    |=============================================================
    |  ALUMNI PORTAL — LEADERSHIP API ENDPOINTS
    |  Add these methods inside the Api class in Api.php
    |
    |  Endpoints:
    |    POST /api/create_leader         — Admin/Manager only (JWT)
    |    POST /api/manage_leader         — Admin/Manager only (JWT)
    |    POST /api/get_leadership        — Public (token only)
    |
    |  Table: leadership  (links to existing users table)
    |
    |  Key design:
    |    - Leaders must already exist as users in the users table
    |    - Name + email come from users table automatically
    |    - leadership_photo overrides users.avatar if set;
    |      falls back to users.avatar if NULL
    |    - is_featured = 1 → the "From the President" hero block
    |    - sort_order controls display sequence
    |=============================================================
    */


    /*=======================================================
        LEADERSHIP — CREATE LEADER
        POST /api/create_leader
        Auth: API token + JWT (admin / manager / superadmin only)

        Body (form-data if uploading photo, else raw JSON):
        token, user_id (existing user), position_title,
        message (opt), chapter_id (opt), year (opt),
        sort_order (opt), is_featured (opt), is_active (opt)
        File field: leadership_photo  (optional)
    ========================================================*/
    public function create_leader()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents('php://input'), true)
            : $this->input->post();

        log_message('error', 'Create Leader Input: ' . var_export($object, true));

        $token = trim($object['token'] ?? '');
        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        $requester_role = strtolower($jwtData->user_role ?? '');
        if (!in_array($requester_role, ['admin', 'manager', 'superadmin'])) {
            $this->output->set_status_header(403);
            header('Content-Type: application/json');
            echo json_encode(['status' => 403, 'message' => 'Access denied. Admin role required.']);
            return;
        }

        $user_id        = intval($object['user_id']      ?? 0);
        $position_title = trim($object['position_title'] ?? '');

        if (!$user_id || empty($position_title)) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'user_id and position_title are required']);
            return;
        }

        $targetUser = $this->db->get_where('users', ['id' => $user_id])->row();
        if (!$targetUser) {
            $this->output->set_status_header(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 404, 'message' => 'User not found. user_id must be an existing user.']);
            return;
        }

        $message     = trim($object['message']     ?? '');
        $chapter_id  = isset($object['chapter_id']) && $object['chapter_id'] !== ''
                    ? intval($object['chapter_id']) : 1;
        $year        = trim($object['year']        ?? '');
        $sort_order  = isset($object['sort_order'])  ? intval($object['sort_order'])           : 0;
        $is_featured = isset($object['is_featured']) ? intval((bool)$object['is_featured'])    : 0;
        $is_active   = isset($object['is_active'])   ? intval((bool)$object['is_active'])      : 1;
        $created_by  = (int)$jwtData->user_id;

        if ($chapter_id) {
            $chapter = $this->db->get_where('alumni_chapter', ['id' => $chapter_id, 'is_enabled' => 1])->row();
            if (!$chapter) {
                $this->output->set_status_header(404);
                header('Content-Type: application/json');
                echo json_encode(['status' => 404, 'message' => 'Chapter not found or not enabled']);
                return;
            }
        }

        // Duplicate check: same user + year combination
        $dupWhere = ['user_id' => $user_id, 'is_deleted' => 0];
        if (!empty($year)) {
            $dupWhere['year'] = $year;
        } else {
            $this->db->where('year IS NULL');
        }
        $dupCheck = $this->db->get_where('leadership', $dupWhere)->row();
        if ($dupCheck) {
            $this->output->set_status_header(409);
            header('Content-Type: application/json');
            echo json_encode([
                'status'      => 409,
                'message'     => 'This user already has a leadership position for this year. Use manage_leader to update.',
                'existing_id' => $dupCheck->id,
            ]);
            return;
        }

        // Only one person can be is_featured=1 at a time
        if ($is_featured) {
            $clearWhere = ['is_featured' => 1, 'is_deleted' => 0];
            if (!empty($year)) {
                $clearWhere['year'] = $year;
            }
            $this->db->where($clearWhere)->update('leadership', ['is_featured' => 0]);
        }

        // ── Upload leadership photo via uploadfiles() ─────────
        $photo_path = null;
        if (!empty($_FILES['leadership_photo']['name'])) {
            $uploaded = $this->uploadfiles('leadership_photo', $user_id, 'leadership_photo');
            if ($uploaded && !empty($uploaded[0]->attachment_file)) {
                $photo_path = $uploaded[0]->attachment_file;
            }
        }

        $insertData = [
            'user_id'          => $user_id,
            'position_title'   => $position_title,
            'message'          => $message ?: null,
            'leadership_photo' => $photo_path,
            'chapter_id'       => $chapter_id,
            'year'             => $year ?: null,
            'sort_order'       => $sort_order,
            'is_featured'      => $is_featured,
            'is_active'        => $is_active,
            'is_deleted'       => 0,
            'created_by'       => $created_by,
            'created_at'       => date('Y-m-d H:i:s'),
        ];

        $this->db->insert('leadership', $insertData);
        $leader_id = $this->db->insert_id();

        if (!$leader_id) {
            $this->output->set_status_header(500);
            header('Content-Type: application/json');
            echo json_encode(['status' => 500, 'message' => 'Failed to create leader. Please try again.']);
            return;
        }

        $insertData['id']       = $leader_id;
        $insertData['photo']    = $this->resolveLeaderPhoto($photo_path, $targetUser->avatar ?? null);
        $insertData['fullname'] = $targetUser->fullname;
        $insertData['email']    = $targetUser->email;

        $this->output->set_status_header(200);
        header('Content-Type: application/json');
        echo json_encode([
            'status'  => 200,
            'message' => 'Leader created successfully',
            'leader'  => $insertData,
        ]);
    }


    /*=======================================================
        LEADERSHIP — MANAGE (UPDATE / DELETE / REORDER)
        POST /api/manage_leader
        Auth: API token + JWT (admin / manager / superadmin only)

        Body:
        token, id, function_type (update | delete | reorder)

        For update, supply any of:
        user_id, position_title, message, chapter_id,
        year, sort_order, is_featured, is_active
        File field: leadership_photo (optional, replaces existing)

        For reorder:
        order = [ {"id": 1, "sort_order": 1}, {"id": 2, "sort_order": 2}, ... ]
        (Pass array of {id, sort_order} objects — no single "id" needed)
    ========================================================*/
    public function manage_leader()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents('php://input'), true)
            : $this->input->post();

        log_message('error', 'Manage Leader Input: ' . var_export($object, true));

        $token = trim($object['token'] ?? '');
        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        $requester_role = strtolower($jwtData->user_role ?? '');
        if (!in_array($requester_role, ['admin', 'manager', 'superadmin'])) {
            $this->output->set_status_header(403);
            header('Content-Type: application/json');
            echo json_encode(['status' => 403, 'message' => 'Access denied. Admin role required.']);
            return;
        }

        $function_type = strtolower(trim($object['function_type'] ?? ''));

        if (empty($function_type)) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'function_type is required: update | delete | reorder']);
            return;
        }

        // ── REORDER ───────────────────────────────────────────
        if ($function_type === 'reorder') {
            $order = $object['order'] ?? [];

            if (empty($order) || !is_array($order)) {
                $this->output->set_status_header(400);
                header('Content-Type: application/json');
                echo json_encode([
                    'status'  => 400,
                    'message' => '"order" array is required. Format: [{"id":1,"sort_order":1}, ...]',
                ]);
                return;
            }

            $updated = 0;
            foreach ($order as $item) {
                $lid  = intval($item['id']         ?? 0);
                $sord = intval($item['sort_order'] ?? 0);
                if ($lid > 0) {
                    $this->db->where('id', $lid)->update('leadership', [
                        'sort_order' => $sord,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    $updated++;
                }
            }

            $this->output->set_status_header(200);
            header('Content-Type: application/json');
            echo json_encode([
                'status'  => 200,
                'message' => "Reorder successful. {$updated} leader(s) updated.",
                'updated' => $updated,
            ]);
            return;
        }

        // ── UPDATE + DELETE need a single id ──────────────────
        $leader_id = intval($object['id'] ?? 0);
        if (!$leader_id) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'id is required for update and delete']);
            return;
        }

        $leader = $this->db->get_where('leadership', ['id' => $leader_id, 'is_deleted' => 0])->row();
        if (!$leader) {
            $this->output->set_status_header(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 404, 'message' => 'Leader not found']);
            return;
        }

        // ── DELETE ────────────────────────────────────────────
        if ($function_type === 'delete') {
            $this->db->where('id', $leader_id)->update('leadership', [
                'is_deleted' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->output->set_status_header(200);
            header('Content-Type: application/json');
            echo json_encode(['status' => 200, 'message' => 'Leader removed successfully']);
            return;
        }

        // ── UPDATE ────────────────────────────────────────────
        if ($function_type === 'update') {
            $updateData = [];

            // Validate new user_id if being changed
            if (isset($object['user_id']) && $object['user_id'] !== '') {
                $newUserId = intval($object['user_id']);
                $newUser   = $this->db->get_where('users', ['id' => $newUserId])->row();
                if (!$newUser) {
                    $this->output->set_status_header(404);
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 404, 'message' => 'New user_id not found in users table']);
                    return;
                }
                $updateData['user_id'] = $newUserId;
            }

            foreach (['position_title', 'message', 'year'] as $field) {
                if (isset($object[$field])) {
                    $updateData[$field] = trim($object[$field]) ?: null;
                }
            }

            foreach (['sort_order', 'is_active', 'chapter_id'] as $field) {
                if (isset($object[$field]) && $object[$field] !== '') {
                    $updateData[$field] = intval($object[$field]);
                }
            }

            // Clear other featured leaders before setting this one
            if (isset($object['is_featured'])) {
                $newFeatured = intval((bool)$object['is_featured']);
                if ($newFeatured === 1) {
                    $year_val = $updateData['year'] ?? $leader->year;
                    $q        = $this->db->where('is_deleted', 0)->where('id !=', $leader_id);
                    if (!empty($year_val)) {
                        $q->where('year', $year_val);
                    }
                    $q->update('leadership', ['is_featured' => 0]);
                }
                $updateData['is_featured'] = $newFeatured;
            }

            // ── Upload new photo via uploadfiles() ────────────
            if (!empty($_FILES['leadership_photo']['name'])) {
                $uid      = intval($updateData['user_id'] ?? $leader->user_id);
                $uploaded = $this->uploadfiles('leadership_photo', $uid, 'leadership_photo');
                if ($uploaded && !empty($uploaded[0]->attachment_file)) {
                    $updateData['leadership_photo'] = $uploaded[0]->attachment_file;
                }
            }

            // Pass remove_photo=1 to clear the override photo
            if (isset($object['remove_photo']) && intval($object['remove_photo']) === 1) {
                $updateData['leadership_photo'] = null;
            }

            if (empty($updateData)) {
                $this->output->set_status_header(422);
                header('Content-Type: application/json');
                echo json_encode(['status' => 422, 'message' => 'No fields provided to update']);
                return;
            }

            $updateData['updated_at'] = date('Y-m-d H:i:s');
            $this->db->where('id', $leader_id)->update('leadership', $updateData);

            $updated = $this->db
                ->select('l.*, u.fullname, u.email, u.phone, u.graduation_year,
                        u.department, u.bio, u.avatar AS user_avatar, ac.chapter_name')
                ->from('leadership l')
                ->join('users u',           'u.id = l.user_id',      'left')
                ->join('alumni_chapter ac',  'ac.id = l.chapter_id', 'left')
                ->where('l.id', $leader_id)
                ->get()->row_array();

            if ($updated) {
                $updated['photo'] = $this->resolveLeaderPhoto(
                    $updated['leadership_photo'] ?? null,
                    $updated['user_avatar']      ?? null
                );
            }

            $this->output->set_status_header(200);
            header('Content-Type: application/json');
            echo json_encode([
                'status'  => 200,
                'message' => 'Leader updated successfully',
                'leader'  => $updated,
            ]);
            return;
        }

        $this->output->set_status_header(400);
        header('Content-Type: application/json');
        echo json_encode(['status' => 400, 'message' => 'Invalid function_type. Use: update | delete | reorder']);
    }


    /*=======================================================
        LEADERSHIP — GET
        POST /api/get_leadership
        Auth: API token only (public — no JWT required)

        Optional filters:
        id          — single leader by leadership.id
        user_id     — single leader by user ID
        chapter_id  — scope to chapter (also returns NULL chapter)
        year        — scope to year   (also returns NULL year)
        is_featured — 1 to get featured/president block only
        is_active   — 1 (default) or 0
    ========================================================*/
    public function get_leadership()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents('php://input'), true)
            : $this->input->get();

        log_message('error', 'Get Leadership Input: ' . var_export($object, true));

        $token = trim($object['token'] ?? '');
        if (!$this->checkAPI_token_from_header()) {
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
            return;
        }

        $id          = isset($object['id'])          ? intval($object['id'])          : 0;
        $user_id     = isset($object['user_id'])     ? intval($object['user_id'])     : 0;
        $chapter_id  = isset($object['chapter_id'])  ? intval($object['chapter_id'])  : 0;
        $year        = isset($object['year'])        ? trim($object['year'])          : null;
        $is_featured = isset($object['is_featured']) ? intval($object['is_featured']) : null;
        $is_active   = isset($object['is_active'])   ? intval($object['is_active'])   : 1;

        $baseSelect = '
            l.id,
            l.user_id,
            l.position_title,
            l.message,
            l.leadership_photo,
            u.avatar         AS user_avatar,
            u.fullname,
            u.email,
            u.phone,
            u.graduation_year,
            u.department,
            u.bio,
            l.chapter_id,
            ac.chapter_name,
            l.year,
            l.sort_order,
            l.is_featured,
            l.is_active,
            l.created_at,
            l.updated_at
        ';

        // ── Single by leadership id ───────────────────────────
        if ($id > 0) {
            $row = $this->db
                ->select($baseSelect)
                ->from('leadership l')
                ->join('users u',           'u.id = l.user_id',      'left')
                ->join('alumni_chapter ac',  'ac.id = l.chapter_id', 'left')
                ->where('l.id', $id)
                ->where('l.is_deleted', 0)
                ->get()->row_array();

            if (!$row) {
                $this->output->set_status_header(404);
                header('Content-Type: application/json');
                echo json_encode(['status' => 404, 'message' => 'Leader not found']);
                return;
            }

            $row['photo'] = $this->resolveLeaderPhoto(
                $row['leadership_photo'] ?? null,
                $row['user_avatar']      ?? null
            );

            $this->output->set_status_header(200);
            header('Content-Type: application/json');
            echo json_encode([
                'status'  => 200,
                'message' => 'Leader retrieved successfully',
                'leader'  => $row,
            ]);
            return;
        }

        // ── Single by user_id ─────────────────────────────────
        if ($user_id > 0) {
            $row = $this->db
                ->select($baseSelect)
                ->from('leadership l')
                ->join('users u',           'u.id = l.user_id',      'left')
                ->join('alumni_chapter ac',  'ac.id = l.chapter_id', 'left')
                ->where('l.user_id', $user_id)
                ->where('l.is_deleted', 0)
                ->where('l.is_active', 1)
                ->order_by('l.sort_order', 'ASC')
                ->get()->row_array();

            if (!$row) {
                $this->output->set_status_header(404);
                header('Content-Type: application/json');
                echo json_encode(['status' => 404, 'message' => 'No leadership record found for this user']);
                return;
            }

            $row['photo'] = $this->resolveLeaderPhoto(
                $row['leadership_photo'] ?? null,
                $row['user_avatar']      ?? null
            );

            $this->output->set_status_header(200);
            header('Content-Type: application/json');
            echo json_encode([
                'status'  => 200,
                'message' => 'Leader retrieved successfully',
                'leader'  => $row,
            ]);
            return;
        }

        // ── List with optional filters ────────────────────────
        $this->db
            ->select($baseSelect)
            ->from('leadership l')
            ->join('users u',           'u.id = l.user_id',      'left')
            ->join('alumni_chapter ac',  'ac.id = l.chapter_id', 'left')
            ->where('l.is_deleted', 0)
            ->where('l.is_active', $is_active);

        if ($chapter_id > 0) {
            $this->db->group_start()
                ->where('l.chapter_id', $chapter_id)
                ->or_where('l.chapter_id IS NULL')
                ->group_end();
        }

        if (!empty($year)) {
            $this->db->group_start()
                ->where('l.year', $year)
                ->or_where('l.year IS NULL')
                ->group_end();
        }

        if ($is_featured !== null) {
            $this->db->where('l.is_featured', $is_featured);
        }

        $this->db
            ->order_by('l.sort_order', 'ASC')
            ->order_by('l.created_at', 'ASC');

        $rows = $this->db->get()->result_array();

        foreach ($rows as &$row) {
            $row['photo'] = $this->resolveLeaderPhoto(
                $row['leadership_photo'] ?? null,
                $row['user_avatar']      ?? null
            );
        }
        unset($row);

        $featured = array_values(array_filter($rows, fn($r) => intval($r['is_featured']) === 1));
        $team     = array_values(array_filter($rows, fn($r) => intval($r['is_featured']) === 0));

        $this->output->set_status_header(200);
        header('Content-Type: application/json');
        echo json_encode([
            'status'   => 200,
            'message'  => 'Leadership retrieved successfully',
            'total'    => count($rows),
            'featured' => $featured,
            'team'     => $team,
            'all'      => $rows,
        ]);
    }

    /*──────────────────────────────────────────────────────────
    |  Private helper: resolve final photo URL
    |  Priority: leadership_photo → user avatar → null
    ──────────────────────────────────────────────────────────*/
    private function resolveLeaderPhoto($leadership_photo = null, $user_avatar = null)
    {
        if (!empty($leadership_photo)) {
            return site_url($leadership_photo);
        }
        if (!empty($user_avatar) && $user_avatar !== 'default.png') {
            return site_url($user_avatar);
        }
        return null;
    }

    /*=============================================================
    |  GET VOUCHERS
    |  GET /api/get_vouchers
    |  API token only — called during registration form load
    |  Returns active users who are available as vouchers
    |=============================================================*/
    public function get_vouchers()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents('php://input'), true)
            : $this->input->get();

        $token = trim($object['token'] ?? '');
        if (!$this->checkAPI_token_from_header()) {
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
            return;
        }

        $vouchers = $this->db
            ->select('id as voucher_id, fullname, email, graduation_year, chapter_id,user_role,avatar, department, phone')
            ->where('voucher', 'yes')
            ->where('active', 1)
            ->get('users')
            ->result_array();

        header('Content-Type: application/json');
        echo json_encode(['status' => 200, 'vouchers' => $vouchers]);
    }

    /*=============================================================
    |  VOUCHER — PENDING REGISTRATIONS
    |  GET /api/voucher_pending
    |  JWT required — only for users where voucher = 'yes'
    |  Returns all registrations pending this voucher's review
    |=============================================================*/
    public function voucher_pending()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents('php://input'), true)
            : $this->input->get();

        $token = trim($object['token'] ?? '');
        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        // Confirm the logged-in user is actually a voucher
        $me = $this->db->get_where('users', ['id' => $jwtData->user_id, 'voucher' => 'yes', 'active' => 1])->row();
        // if (!$me) {
        //     $this->output->set_status_header(403);
        //     header('Content-Type: application/json');
        //     echo json_encode(['status' => 403, 'message' => 'Access denied. Voucher role required.']);
        //     return;
        // }

        $pending = $this->db
            ->select('v.id AS vouch_id, v.status, v.created_at,
                    u.id AS user_id, u.fullname, u.email, u.graduation_year, u.department, u.phone')
            ->from('vouches v')
            ->join('users u', 'u.id = v.register_id')
            ->where('v.voucher_id', $me->id)
            ->where('v.status', 'pending')
            ->get()
            ->result_array();

        header('Content-Type: application/json');
        echo json_encode(['status' => 200, 'pending' => $pending]);
    }

    /*=============================================================
    |  VOUCHER — APPROVE OR DENY A REGISTRATION
    |  POST /api/vouch_action
    |  JWT required — only for users where voucher = 'yes'
    |
    |  Body:
    |    token    = api_token
    |    vouch_id = id in vouches table
    |    action   = approve | deny
    |    reason   = (optional, used when action=deny)
    |=============================================================*/
    public function vouch_action()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents('php://input'), true)
            : $this->input->post();

        $token = trim($object['token'] ?? '');
        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        // Confirm the logged-in user is a voucher
        $me = $this->db->get_where('users', ['id' => $jwtData->user_id, 'voucher' => 'yes', 'active' => 1])->row();
        if (!$me) {
            $this->output->set_status_header(403);
            header('Content-Type: application/json');
            echo json_encode(['status' => 403, 'message' => 'Access denied. Voucher role required.']);
            return;
        }

        $vouch_id = intval($object['vouch_id'] ?? 0);
        $action   = strtolower(trim($object['action'] ?? ''));
        $reason   = trim($object['reason'] ?? '');

        if (!$vouch_id || !in_array($action, ['approve', 'deny','reject'])) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'vouch_id and action (approve|deny) are required']);
            return;
        }

        // Fetch the vouch record — must belong to this voucher
        $vouch = $this->db->get_where('vouches', ['id' => $vouch_id, 'voucher_id' => $me->id])->row();
        if (!$vouch) {
            $this->output->set_status_header(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 404, 'message' => 'Vouch record not found']);
            return;
        }

        if ($vouch->status !== 'pending') {
            $this->output->set_status_header(409);
            header('Content-Type: application/json');
            echo json_encode(['status' => 409, 'message' => 'This vouch has already been actioned']);
            return;
        }

        $registrant = $this->db->get_where('users', ['id' => $vouch->register_id])->row_array();
        if (!$registrant) {
            $this->output->set_status_header(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 404, 'message' => 'Registrant not found']);
            return;
        }

        $now = date('Y-m-d H:i:s');

        if ($action === 'approve') {
            // Update vouch record
            $this->db->where('id', $vouch_id)->update('vouches', [
                'status'     => 'approved',
                'reason'     => $reason,
                'updated_at' => $now,
            ]);

            // Activate the registrant's account
            $this->db->where('id', $vouch->register_id)->update('users', [
                'is_approved'    => 1,
                'active'         => 1,
                'profile_status' => 'active',
                'updated_at'     => $now,
            ]);

            // Email registrant
            $this->sendAccountStatusEmail($registrant['email'], $registrant['fullname'], 'approved');

            // Email admins/managers
            $this->_sendAdminVoucherApprovalNotification($me->fullname, $registrant['fullname'], $registrant['email']);

            $message = 'Account approved by voucher';

        } else {
            // Deny — only update vouch table, leave users table untouched (admin can still approve)
            $this->db->where('id', $vouch_id)->update('vouches', [
                'status'     => 'denied',
                'reason'     => $reason,
                'updated_at' => $now,
            ]);

            // Email registrant about the denial
            $this->_sendVoucherDenialEmail($registrant['email'], $registrant['fullname'], $reason);

            $message = 'Vouch denied. Admin can still approve the account.';
        }

        header('Content-Type: application/json');
        echo json_encode(['status' => 200, 'message' => $message]);
    }

    // ══════════════════════════════════════════════════════════════
    // ZONES & CITIES
    // ══════════════════════════════════════════════════════════════

    public function get_zones()
    {
        if (!$this->checkAPI_token_from_header()) {
            $this->output->set_status_header(403);
            header('Content-Type: application/json');
            echo json_encode(['status' => 403, 'message' => 'Invalid API token']);
            return;
        }

        $this->db->select('z.zone_id, z.zone, z.chapter_id, z.coordinator_user_id,
            u.first_name, u.last_name, u.phone, u.email, u.avatar');
        $this->db->from('zones z');
        $this->db->join('users u', 'u.id = z.coordinator_user_id', 'left');
        $this->db->order_by('z.zone_id', 'ASC');
        $zones = $this->db->get()->result_array();

        $result = [];
        foreach ($zones as $z) {
            $coordinator = null;
            if (!empty($z['coordinator_user_id'])) {
                $coordinator = [
                    'user_id'    => (int) $z['coordinator_user_id'],
                    'name'       => trim($z['first_name'] . ' ' . $z['last_name']),
                    'first_name' => $z['first_name'],
                    'last_name'  => $z['last_name'],
                    'phone'      => $z['phone'],
                    'email'      => $z['email'],
                    'avatar'     => $z['avatar'] ? site_url($z['avatar']) : null,
                ];
            }
            $cities = $this->db
                ->select('city_id, city')
                ->where('zone_id', (int) $z['zone_id'])
                ->order_by('city', 'ASC')
                ->get('cities')
                ->result_array();

            foreach ($cities as &$c) {
                $c['city_id'] = (int) $c['city_id'];
            }
            unset($c);

            $result[] = [
                'zone_id'     => (int) $z['zone_id'],
                'zone'        => $z['zone'],
                'chapter_id'  => (int) $z['chapter_id'],
                'coordinator' => $coordinator,
                'cities'      => $cities,
            ];
        }

        header('Content-Type: application/json');
        echo json_encode(['status' => 200, 'message' => 'Zones retrieved successfully', 'data' => $result]);
    }

    public function get_cities()
    {
        if (!$this->checkAPI_token_from_header()) {
            $this->output->set_status_header(403);
            header('Content-Type: application/json');
            echo json_encode(['status' => 403, 'message' => 'Invalid API token']);
            return;
        }

        $this->db->select('c.city_id, c.city, c.chapter_id, z.zone_id, z.zone');
        $this->db->from('cities c');
        $this->db->join('zones z', 'z.zone_id = c.zone_id', 'left');
        $this->db->order_by('z.zone_id', 'ASC');
        $this->db->order_by('c.city', 'ASC');
        $cities = $this->db->get()->result_array();

        foreach ($cities as &$c) {
            $c['city_id']    = (int) $c['city_id'];
            $c['zone_id']    = (int) $c['zone_id'];
            $c['chapter_id'] = (int) $c['chapter_id'];
        }
        unset($c);

        header('Content-Type: application/json');
        echo json_encode(['status' => 200, 'message' => 'Cities retrieved successfully', 'data' => $cities]);
    }

    public function get_users_by_zone()
    {
        if (!$this->checkAPI_token_from_header()) {
            $this->output->set_status_header(403);
            header('Content-Type: application/json');
            echo json_encode(['status' => 403, 'message' => 'Invalid API token']);
            return;
        }       

        $zone_id   = (int) ($this->input->get('zone_id') ?: 0);
        $zone_name = trim($this->input->get('zone') ?: '');

        if (!$zone_id && !$zone_name) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'zone_id or zone name is required']);
            return;
        }

        $this->db->select('z.zone_id, z.zone, z.coordinator_user_id,
            u.first_name, u.last_name, u.phone, u.email, u.avatar');
        $this->db->from('zones z');
        $this->db->join('users u', 'u.id = z.coordinator_user_id', 'left');
        if ($zone_id) {
            $this->db->where('z.zone_id', $zone_id);
        } else {
            $this->db->where('LOWER(z.zone)', strtolower($zone_name));
        }
        $zone = $this->db->get()->row_array();

        if (!$zone) {
            $this->output->set_status_header(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 404, 'message' => 'Zone not found']);
            return;
        }

        $coordinator = null;
        if (!empty($zone['coordinator_user_id'])) {
            $coordinator = [
                'user_id'    => (int) $zone['coordinator_user_id'],
                'name'       => trim($zone['first_name'] . ' ' . $zone['last_name']),
                'first_name' => $zone['first_name'],
                'last_name'  => $zone['last_name'],
                'phone'      => $zone['phone'],
                'email'      => $zone['email'],
                'avatar'     => $zone['avatar'] ? site_url($zone['avatar']) : null,
            ];
        }

        $this->db->select('u.id, u.user_code, u.first_name, u.last_name, u.fullname,
            u.email, u.phone, u.avatar, u.city, u.area, u.state, u.residential_address,
            u.chapter_id, u.graduation_year, u.department, u.birth_date, u.house_color,
            u.is_coordinator, u.employment_status, u.occupation, u.industry_sector,
            u.years_of_experience, u.is_volunteer, u.nick_name, u.user_role, u.active');
        $this->db->from('users u');
        $this->db->join('cities c', 'LOWER(u.city) = LOWER(c.city)', 'inner');
        $this->db->where('c.zone_id', $zone_id);
        $this->db->where('u.active', 1);
        $rows = $this->db->get()->result_array();

        $users = [];
        foreach ($rows as $u) {
            $users[] = [
                'user_id'             => (int) $u['id'],
                'user_code'           => $u['user_code'],
                'first_name'          => $u['first_name'],
                'last_name'           => $u['last_name'],
                'fullname'            => $u['fullname'],
                'email'               => $u['email'],
                'phone'               => $u['phone'],
                'avatar'              => $u['avatar'] ? site_url($u['avatar']) : null,
                'city'                => $u['city'],
                'area'                => $u['area'],
                'state'               => $u['state'],
                'residential_address' => $u['residential_address'],
                'chapter_id'          => $u['chapter_id'],
                'graduation_year'     => $u['graduation_year'],
                'department'          => $u['department'],
                'birth_date'          => $u['birth_date'],
                'house_color'         => $u['house_color'],
                'is_coordinator'      => (bool) $u['is_coordinator'],
                'employment_status'   => $u['employment_status'],
                'occupation'          => $u['occupation'],
                'industry_sector'     => $u['industry_sector'],
                'years_of_experience' => $u['years_of_experience'],
                'is_volunteer'        => (bool) $u['is_volunteer'],
                'nick_name'           => $u['nick_name'],
                'user_role'           => $u['user_role'],
                'active'              => (bool) $u['active'],
            ];
        }

        header('Content-Type: application/json');
        echo json_encode([
            'status'  => 200,
            'message' => 'Users retrieved successfully',
            'zone'    => [
                'zone_id'     => (int) $zone['zone_id'],
                'zone'        => $zone['zone'],
                'coordinator' => $coordinator,
            ],
            'total' => count($users),
            'users' => $users,
        ]);
    }

    public function get_my_zone()
    {        
        $jwt_user = $this->authorize();

        if (!$jwt_user) {
            return;
        }

        $user = $this->db->where('id', (int) $jwt_user->user_id)->get('users')->row();
        if (!$user) {
            $this->output->set_status_header(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 404, 'message' => 'User not found']);
            return;
        }

        $not_available = [
            'status'  => 200,
            'message' => 'Zone not yet available',
            'city'    => $user->city ?? null,
            'zone'    => 'Not Yet Available',
        ];

        if (empty($user->city)) {
            header('Content-Type: application/json');
            echo json_encode($not_available);
            return;
        }

        $city_row = $this->db
            ->where('LOWER(city)', strtolower(trim($user->city)))
            ->get('cities')->row();

        if (!$city_row) {
            header('Content-Type: application/json');
            echo json_encode($not_available);
            return;
        }

        $this->db->select('z.zone_id, z.zone, z.coordinator_user_id,
            u.first_name, u.last_name, u.phone, u.email, u.avatar');
        $this->db->from('zones z');
        $this->db->join('users u', 'u.id = z.coordinator_user_id', 'left');
        $this->db->where('z.zone_id', $city_row->zone_id);
        $zone = $this->db->get()->row_array();

        if (!$zone) {
            header('Content-Type: application/json');
            echo json_encode($not_available);
            return;
        }

        $coordinator = null;
        if (!empty($zone['coordinator_user_id'])) {
            $coordinator = [
                'user_id'    => (int) $zone['coordinator_user_id'],
                'name'       => trim($zone['first_name'] . ' ' . $zone['last_name']),
                'first_name' => $zone['first_name'],
                'last_name'  => $zone['last_name'],
                'phone'      => $zone['phone'],
                'email'      => $zone['email'],
                'avatar'     => $zone['avatar'] ? site_url($zone['avatar']) : null,
            ];
        }

        header('Content-Type: application/json');
        echo json_encode([
            'status'  => 200,
            'message' => 'Zone retrieved successfully',
            'city'    => $user->city,
            'zone'    => [
                'zone_id'     => (int) $zone['zone_id'],
                'zone'        => $zone['zone'],
                'coordinator' => $coordinator,
            ],
        ]);
    }

    public function manage_zone()
    {
        $jwt_user = $this->authorize();

        if (!$jwt_user) {
            return;
        }

        if (strtolower($jwt_user->user_role) !== 'admin') {
            $this->output->set_status_header(403);
            header('Content-Type: application/json');
            echo json_encode(['status' => 403, 'message' => 'Admin access required']);
            return;
        }

        $contentType = $this->input->server('CONTENT_TYPE');
        $object = strpos($contentType, 'application/json') !== false
            ? json_decode(file_get_contents('php://input'), true)
            : $this->input->post();

        $action = trim($object['action'] ?? '');

        if ($action === 'create') {
            $zone = trim($object['zone'] ?? '');
            if (!$zone) {
                $this->output->set_status_header(400);
                header('Content-Type: application/json');
                echo json_encode(['status' => 400, 'message' => 'zone name is required']);
                return;
            }
            if ($this->db->where('zone', $zone)->count_all_results('zones')) {
                $this->output->set_status_header(400);
                header('Content-Type: application/json');
                echo json_encode(['status' => 400, 'message' => 'Zone already exists']);
                return;
            }
            $data = [
                'zone'       => $zone,
                'chapter_id' => (int) ($object['chapter_id'] ?? 1),
                'created_at' => date('Y-m-d H:i:s'),
            ];
            if (!empty($object['coordinator_user_id'])) {
                $data['coordinator_user_id'] = (int) $object['coordinator_user_id'];
            }
            $this->db->insert('zones', $data);
            $zone_id = $this->db->insert_id();
            $this->output->set_status_header(200);
            header('Content-Type: application/json');
            echo json_encode(['status' => 200, 'message' => 'Zone created successfully', 'zone_id' => $zone_id]);

        } elseif ($action === 'update') {
            $zone_id = (int) ($object['zone_id'] ?? 0);
            if (!$zone_id) {
                $this->output->set_status_header(400);
                header('Content-Type: application/json');
                echo json_encode(['status' => 400, 'message' => 'zone_id is required']);
                return;
            }
            $update = ['updated_at' => date('Y-m-d H:i:s')];
            if (isset($object['zone']))                $update['zone']                 = trim($object['zone']);
            if (isset($object['coordinator_user_id'])) $update['coordinator_user_id']  = $object['coordinator_user_id'] !== '' ? (int) $object['coordinator_user_id'] : null;
            if (isset($object['chapter_id']))          $update['chapter_id']           = (int) $object['chapter_id'];
            $this->db->where('zone_id', $zone_id)->update('zones', $update);
            $this->output->set_status_header(200);
            header('Content-Type: application/json');
            echo json_encode(['status' => 200, 'message' => 'Zone updated successfully']);

        } elseif ($action === 'delete') {
            $zone_id = (int) ($object['zone_id'] ?? 0);
            if (!$zone_id) {
                $this->output->set_status_header(400);
                header('Content-Type: application/json');
                echo json_encode(['status' => 400, 'message' => 'zone_id is required']);
                return;
            }
            $this->db->where('zone_id', $zone_id)->delete('zones');
            $this->output->set_status_header(200);
            header('Content-Type: application/json');
            echo json_encode(['status' => 200, 'message' => 'Zone deleted successfully']);

        } else {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'Invalid action. Use: create, update, delete']);
        }
    }

    public function manage_city()
    {
        $jwt_user = $this->authorize();

        if (!$jwt_user) {
            return;
        }

        if (strtolower($jwt_user->user_role) !== 'admin') {
            $this->output->set_status_header(403);
            header('Content-Type: application/json');
            echo json_encode(['status' => 403, 'message' => 'Admin access required']);
            return;
        }

        $contentType = $this->input->server('CONTENT_TYPE');
        $object = strpos($contentType, 'application/json') !== false
            ? json_decode(file_get_contents('php://input'), true)
            : $this->input->post();

        $action = trim($object['action'] ?? '');

        if ($action === 'create') {
            $city    = trim($object['city'] ?? '');
            $zone_id = (int) ($object['zone_id'] ?? 0);
            if (!$city || !$zone_id) {
                $this->output->set_status_header(400);
                header('Content-Type: application/json');
                echo json_encode(['status' => 400, 'message' => 'city and zone_id are required']);
                return;
            }
            if ($this->db->where('LOWER(city)', strtolower($city))->count_all_results('cities')) {
                $this->output->set_status_header(400);
                header('Content-Type: application/json');
                echo json_encode(['status' => 400, 'message' => 'City already exists']);
                return;
            }
            $this->db->insert('cities', [
                'city'       => $city,
                'zone_id'    => $zone_id,
                'chapter_id' => (int) ($object['chapter_id'] ?? 1),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $city_id = $this->db->insert_id();
            $this->output->set_status_header(200);
            header('Content-Type: application/json');
            echo json_encode(['status' => 200, 'message' => 'City created successfully', 'city_id' => $city_id]);

        } elseif ($action === 'update') {
            $city_id = (int) ($object['city_id'] ?? 0);
            if (!$city_id) {
                $this->output->set_status_header(400);
                header('Content-Type: application/json');
                echo json_encode(['status' => 400, 'message' => 'city_id is required']);
                return;
            }
            $update = [];
            if (isset($object['city']))       $update['city']       = trim($object['city']);
            if (isset($object['zone_id']))    $update['zone_id']    = (int) $object['zone_id'];
            if (isset($object['chapter_id'])) $update['chapter_id'] = (int) $object['chapter_id'];
            if (empty($update)) {
                $this->output->set_status_header(400);
                header('Content-Type: application/json');
                echo json_encode(['status' => 400, 'message' => 'No fields provided to update']);
                return;
            }
            $this->db->where('city_id', $city_id)->update('cities', $update);
            $this->output->set_status_header(200);
            header('Content-Type: application/json');
            echo json_encode(['status' => 200, 'message' => 'City updated successfully']);

        } elseif ($action === 'delete') {
            $city_id = (int) ($object['city_id'] ?? 0);
            if (!$city_id) {
                $this->output->set_status_header(400);
                header('Content-Type: application/json');
                echo json_encode(['status' => 400, 'message' => 'city_id is required']);
                return;
            }
            $this->db->where('city_id', $city_id)->delete('cities');
            $this->output->set_status_header(200);
            header('Content-Type: application/json');
            echo json_encode(['status' => 200, 'message' => 'City deleted successfully']);

        } else {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'Invalid action. Use: create, update, delete']);
        }
    }

    public function upload_zones_cities()
    {
        if (!$this->checkAPI_token_from_header()) {
            $this->output->set_status_header(403);
            header('Content-Type: application/json');
            echo json_encode(['status' => 403, 'message' => 'Invalid API token']);
            return;
        }        

        if (empty($_FILES['file']['name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'No file uploaded or upload error']);
            return;
        }

        $ext  = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        $rows = [];

        if ($ext === 'csv') {
            if (($handle = fopen($_FILES['file']['tmp_name'], 'r')) !== false) {
                $header = null;
                while (($row = fgetcsv($handle)) !== false) {
                    if (!$header) {
                        $header = array_map('trim', array_map('strtolower', $row));
                        continue;
                    }
                    if (count($row) >= count($header)) {
                        $rows[] = array_combine($header, array_slice($row, 0, count($header)));
                    }
                }
                fclose($handle);
            }
        } elseif (in_array($ext, ['xlsx', 'xls'])) {
            require_once APPPATH . 'third_party/PHPExcel.php';
            try {
                $objPHPExcel = PHPExcel_IOFactory::load($_FILES['file']['tmp_name']);
                $sheet       = $objPHPExcel->getActiveSheet();
                $header      = null;
                foreach ($sheet->getRowIterator() as $row) {
                    $cellIterator = $row->getCellIterator();
                    $cellIterator->setIterateOnlyExistingCells(false);
                    $cells = [];
                    foreach ($cellIterator as $cell) {
                        $cells[] = trim((string) $cell->getValue());
                    }
                    if (empty(array_filter($cells))) continue;
                    if (!$header) {
                        $header = array_map('strtolower', array_map('trim', $cells));
                        continue;
                    }
                    if (count($cells) >= count($header)) {
                        $rows[] = array_combine($header, array_slice($cells, 0, count($header)));
                    }
                }
            } catch (Exception $e) {
                $this->output->set_status_header(500);
                header('Content-Type: application/json');
                echo json_encode(['status' => 500, 'message' => 'Failed to parse file: ' . $e->getMessage()]);
                return;
            }
        } else {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'Unsupported file type. Use CSV, XLS or XLSX']);
            return;
        }

        if (empty($rows)) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'File is empty or has no valid rows']);
            return;
        }

        // Upsert zones first, build zone_name => zone_id map
        $zone_map    = [];
        $zones_added = 0;
        foreach ($rows as $row) {
            $zone_name = trim($row['zone'] ?? '');
            if (!$zone_name || isset($zone_map[$zone_name])) continue;
            $existing = $this->db->where('zone', $zone_name)->get('zones')->row();
            if ($existing) {
                $zone_map[$zone_name] = (int) $existing->zone_id;
            } else {
                $this->db->insert('zones', [
                    'zone'       => $zone_name,
                    'chapter_id' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $zone_map[$zone_name] = (int) $this->db->insert_id();
                $zones_added++;
            }
        }

        // Upsert cities
        $cities_added   = 0;
        $cities_updated = 0;
        foreach ($rows as $row) {
            $city_name = trim($row['city'] ?? '');
            $zone_name = trim($row['zone'] ?? '');
            if (!$city_name || !$zone_name || !isset($zone_map[$zone_name])) continue;
            $zone_id  = $zone_map[$zone_name];
            $existing = $this->db->where('LOWER(city)', strtolower($city_name))->get('cities')->row();
            if ($existing) {
                $this->db->where('city_id', $existing->city_id)->update('cities', ['zone_id' => $zone_id]);
                $cities_updated++;
            } else {
                $this->db->insert('cities', [
                    'city'       => $city_name,
                    'zone_id'    => $zone_id,
                    'chapter_id' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $cities_added++;
            }
        }

        header('Content-Type: application/json');
        echo json_encode([
            'status'  => 200,
            'message' => 'Upload processed successfully',
            'summary' => [
                'zones_added'    => $zones_added,
                'cities_added'   => $cities_added,
                'cities_updated' => $cities_updated,
                'total_rows'     => count($rows),
            ],
        ]);
    }

    /*=======================================================
        BIRTHDAY API SECTION
    ========================================================
    */

    /**
     * GET /api/get_birthdays
     *
     * Checks every active user's birth_date and returns those celebrating.
     *
     * Query params:
     *  scope   today (default) | week | month | upcoming
     *  days    only for scope=upcoming (default 30, max 365)
     *  month   only for scope=month   (1-12, default = current month)
     *  limit   max records to return (default 50, max 200)
     *  include_self  1 = include the logged in user (default 1)
     *
     * Notes:
     *  - A user is announced unless user_profiles.field_visibility has
     *    birth_date set to false. A missing key counts as public, matching
     *    get_profile_visibility(). Registration writes false by default, so
     *    new users opt in rather than out.
     *  - user_profiles.is_visible is deliberately NOT consulted - hiding a
     *    profile from the directory does not hide a birthday.
     *  - A 29 Feb birthday is celebrated on 28 Feb in non leap years.
     */
    public function get_birthdays()
    {
        $jwt_user = $this->authorize();

        if (!$jwt_user) {
            return;
        }

        $scope        = strtolower(trim($this->input->get('scope') ?: 'today'));
        $limit        = (int) ($this->input->get('limit') ?: 50);
        $include_self = $this->input->get('include_self');
        $include_self = ($include_self === null || $include_self === '') ? true : $this->parseBool($include_self);

        if (!in_array($scope, ['today', 'week', 'month', 'upcoming'], true)) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode([
                'status'  => 400,
                'message' => 'Invalid scope. Use today, week, month or upcoming',
            ]);
            return;
        }

        if ($limit < 1)   $limit = 50;
        if ($limit > 200) $limit = 200;

        $today = new DateTime('today');

        // Window (in days from today) we are interested in
        $window = 0;
        if ($scope === 'week') {
            $window = 6; // today + next 6 days
        } elseif ($scope === 'upcoming') {
            $window = (int) ($this->input->get('days') ?: 30);
            if ($window < 1)   $window = 30;
            if ($window > 365) $window = 365;
            $window -= 1; // "next 30 days" = today .. today+29
        }

        $month = 0;
        if ($scope === 'month') {
            $month = (int) ($this->input->get('month') ?: $today->format('n'));
            if ($month < 1 || $month > 12) {
                $this->output->set_status_header(400);
                header('Content-Type: application/json');
                echo json_encode(['status' => 400, 'message' => 'Invalid month. Use 1 - 12']);
                return;
            }
        }

        $this->db->select('u.id, u.user_code, u.first_name, u.last_name, u.fullname,
            u.name_in_school, u.nick_name, u.avatar, u.birth_date, u.graduation_year,
            u.year, u.department, u.house_color, u.city, u.email, u.phone,
            p.field_visibility');
        $this->db->from('users u');
        $this->db->join('user_profiles p', 'p.user_id = u.id', 'left');
        $this->db->where('u.active', 1);
        $this->db->where('u.birth_date IS NOT NULL');
        $this->db->where('u.birth_date !=', '0000-00-00');

        if ($scope === 'today') {
            $this->db->where('MONTH(u.birth_date)', (int) $today->format('n'));
            $this->db->where('DAY(u.birth_date)', (int) $today->format('j'));
        } elseif ($scope === 'month') {
            $this->db->where('MONTH(u.birth_date)', $month);
        } else {
            // Only pull the months the window actually spans
            $months = [];
            for ($i = 0; $i <= $window; $i++) {
                $d = (clone $today)->modify("+{$i} day");
                $months[(int) $d->format('n')] = (int) $d->format('n');
            }
            if (count($months) < 12) {
                $this->db->where_in('MONTH(u.birth_date)', array_values($months), false);
            }
        }

        $rows = $this->db->get()->result_array();

        // 29 Feb birthdays celebrate on 28 Feb when the current year is not a leap year
        $leap_shift = (
            $scope === 'today'
            && $today->format('n-j') === '2-28'
            && !(bool) $today->format('L')
        );

        if ($leap_shift) {
            $this->db->select('u.id, u.user_code, u.first_name, u.last_name, u.fullname,
                u.name_in_school, u.nick_name, u.avatar, u.birth_date, u.graduation_year,
                u.year, u.department, u.house_color, u.city, u.email, u.phone,
                p.field_visibility');
            $this->db->from('users u');
            $this->db->join('user_profiles p', 'p.user_id = u.id', 'left');
            $this->db->where('u.active', 1);
            $this->db->where('MONTH(u.birth_date)', 2);
            $this->db->where('DAY(u.birth_date)', 29);
            $rows = array_merge($rows, $this->db->get()->result_array());
        }

        $celebrants = [];

        foreach ($rows as $u) {

            // Announce a birthday only when birth_date is public.
            // is_visible is deliberately not consulted here.
            $fv = [];
            if (!empty($u['field_visibility'])) {
                $decoded = json_decode($u['field_visibility'], true);
                if (is_array($decoded)) {
                    $fv = $decoded;
                }
            }

            // A missing key means public, matching get_profile_visibility().
            // Only an explicit false opts the user out.
            if (isset($fv['birth_date']) && !$this->parseBool($fv['birth_date'])) {
                continue;
            }

            if (!$include_self && (int) $u['id'] === (int) $jwt_user->user_id) {
                continue;
            }

            $dob = date_create($u['birth_date']);
            if (!$dob) {
                continue;
            }

            $days_until = $this->_days_until_birthday($dob, $today);

            if ($scope === 'today' && $days_until !== 0) {
                continue;
            }
            if (($scope === 'week' || $scope === 'upcoming') && $days_until > $window) {
                continue;
            }

            $class_of = $u['year'] ?: $u['graduation_year'];

            $celebrants[] = [
                'user_id'        => (int) $u['id'],
                'user_code'      => $u['user_code'],
                'first_name'     => $u['first_name'],
                'last_name'      => $u['last_name'],
                'fullname'       => $u['fullname'] ?: trim($u['first_name'] . ' ' . $u['last_name']),
                'name_in_school' => $u['name_in_school'],
                'nick_name'      => $u['nick_name'],
                'avatar'         => $u['avatar'] ? site_url($u['avatar']) : null,
                'class_of'       => $class_of ? (string) $class_of : null,
                'class_label'    => $class_of ? "Class '" . substr((string) $class_of, -2) : null,
                'department'     => $u['department'],
                'house_color'    => $u['house_color'],
                'city'           => $u['city'],
                'birth_day'      => (int) $dob->format('j'),
                'birth_month'    => (int) $dob->format('n'),
                'birth_date'     => $dob->format('Y-m-d'),
                'age'            => $this->_birthday_age($dob, $today),
                'date'           => (clone $today)->modify("+{$days_until} day")->format('Y-m-d'),
                'days_until'     => $days_until,
                'is_today'       => ($days_until === 0),
                'is_self'        => ((int) $u['id'] === (int) $jwt_user->user_id),
                'message'        => ($days_until === 0)
                    ? "It's their birthday today!"
                    : 'Birthday in ' . $days_until . ' day' . ($days_until === 1 ? '' : 's'),
            ];
        }

        // Soonest first, then alphabetically
        usort($celebrants, function ($a, $b) {
            if ($a['days_until'] === $b['days_until']) {
                return strcasecmp((string) $a['fullname'], (string) $b['fullname']);
            }
            return $a['days_until'] - $b['days_until'];
        });

        $total = count($celebrants);
        if ($total > $limit) {
            $celebrants = array_slice($celebrants, 0, $limit);
        }

        header('Content-Type: application/json');
        echo json_encode([
            'status'    => 200,
            'message'   => $total
                ? 'Birthdays retrieved successfully'
                : 'No birthdays found for this period',
            'scope'     => $scope,
            'date'      => $today->format('Y-m-d'),
            'total'     => $total,
            'returned'  => count($celebrants),
            'birthdays' => $celebrants,
        ]);
    }

    /**
     * Whole days from $today until the next occurrence of $dob's day & month.
     * Returns 0 when the birthday is today.
     */
    private function _days_until_birthday(DateTime $dob, DateTime $today)
    {
        $month = (int) $dob->format('n');
        $day   = (int) $dob->format('j');

        for ($i = 0; $i < 2; $i++) {

            $year = (int) $today->format('Y') + $i;
            $d    = $day;

            // 29 Feb in a non leap year -> celebrate on 28 Feb
            if ($month === 2 && $day === 29 && !checkdate(2, 29, $year)) {
                $d = 28;
            }

            $next = date_create_from_format('Y-n-j H:i:s', $year . '-' . $month . '-' . $d . ' 00:00:00');

            if ($next && $next >= $today) {
                return (int) $today->diff($next)->days;
            }
        }

        return 0;
    }

    /**
     * Age the user turns on that birthday (null when the birth year is unknown).
     */
    private function _birthday_age(DateTime $dob, DateTime $today)
    {
        $birth_year = (int) $dob->format('Y');

        if ($birth_year < 1900) {
            return null;
        }

        $age = (int) $today->format('Y') - $birth_year;

        // Birthday already passed this year -> next one makes them a year older
        if ($this->_days_until_birthday($dob, $today) > 0
            && $dob->format('md') < $today->format('md')) {
            $age += 1;
        }

        return $age > 0 ? $age : null;
    }

}
