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
class Api extends CI_Controller
{
    private $cached_request_body = NULL;
     protected $srvlink = "https://alumni-app-three.vercel.app/";
    // public function __construct()
    // {
        
    //     parent::__construct();
	// 			  // CORS Headers - must be in constructor to apply to ALL methods
    //     header('Access-Control-Allow-Origin: *');
    //     header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    //     header('Access-Control-Allow-Headers: Content-Type, Authorization');

    //     // Handle preflight
    //     if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    //         http_response_code(200);
    //         exit();
    //     }
    //     $this->load->library('email');
    //     $this->load->library('ion_auth');
    //     $this->load->library('jwt_helper');
    //     // $this->load->library('MobileDetect');
    //     $this->load->model('api_model');
    //     $this->load->model('base_model');
    //     $this->load->helper(array("url", "form", 'notification_helper'));
    // }
public function __construct()
{
    // CORS headers must be set BEFORE parent::__construct() so they are
    // always sent even if CI boot (DB, libraries) throws an error.
    $allowedOrigins = [
        'https://alumni-app-three.vercel.app',
        'http://localhost:5173'
    ];

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, $allowedOrigins)) {
        header("Access-Control-Allow-Origin: $origin");
    }
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
    header('Access-Control-Max-Age: 86400');

    // Handle preflight immediately — never touch the DB for OPTIONS
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }

    parent::__construct();

    $this->load->library('email');
    $this->load->library('ion_auth');
    $this->load->library('jwt_helper');
    // $this->load->library('MobileDetect');
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
            $data['message'] = 'No account found with that email address';
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

            // Update last_login
            $this->db->where('id', $userInfo->id);
            $this->db->update('users', ['last_login' => time()]);

            // Fetch user_profile row
            $profile = $this->db->get_where('user_profiles', ['user_id' => $userInfo->id])->row_array();

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

        // --- API token ---
        if (!$this->checkAPI_token_from_header()) {
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode(['status' => 401, 'message' => 'API key is invalid']);
            return;
        }

        // --- JWT ---
        $jwtData = $this->checkJWT();
        if (!$jwtData) {
            $this->jwtErrorResponse();
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

            // Profile / social
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
        ]);
    }

    /*──────────────────────────────────────────────────────────────
    |  Generate a unique 6-digit numeric user code.
    |  Loops until it finds one not already in the users table,
    |  so it is safe even as user count grows toward 999 999.
    ──────────────────────────────────────────────────────────────*/
//    private function _generateUserCode()
// {
//     $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

//     do {
//         $code = '';
//         for ($i = 0; $i < 6; $i++) {
//             $code .= $characters[random_int(0, strlen($characters) - 1)];
//         }

//         $exists = $this->db
//             ->where('user_code', $code)
//             ->count_all_results('users');
//     } while ($exists > 0);

//     return $code;
// }

// ...existing code...
private function _generateUserCode($graduationYear = null, $email = null)
{
    // If year + email provided -> deterministic member id: MBR-YYYY-XXXXXX (hex)
    if (!empty($graduationYear) && !empty($email)) {
        $seed = strtolower(trim((string)$email));
        $makeHex = function ($s) {
            $h = 0;
            $len = strlen($s);
            for ($i = 0; $i < $len; $i++) {
                $h = ((($h << 5) - $h) + ord($s[$i])) & 0xFFFFFFFF;
            }
            return substr(str_pad(dechex(abs($h)), 6, '0', STR_PAD_LEFT), 0, 6);
        };

        $hex = $makeHex($seed);
        $code = "MBR-{$graduationYear}-{$hex}";

        // Ensure uniqueness; if collision, vary seed with a counter
        $counter = 1;
        while ($this->db->where('user_code', $code)->count_all_results('users') > 0) {
            $hex = $makeHex($seed . '|' . $counter);
            $code = "MBR-{$graduationYear}-{$hex}";
            $counter++;
            if ($counter > 1000) {
                // fallback to random if too many collisions
                break;
            }
        }

        // final check: if still exists, fall back to random generator below
        if ($this->db->where('user_code', $code)->count_all_results('users') == 0) {
            return $code;
        }
    }

    // Fallback: original random 6-char alphanumeric code (keeps backward compatibility)
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }

        $exists = $this->db
            ->where('user_code', $code)
            ->count_all_results('users');
    } while ($exists > 0);

    return $code;
}
// ...existing code...
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
	    // public function forgot_password()
	    // {
	    //     $body = file_get_contents("php://input");
	    //     $object = json_decode($body, true);

    //     $identity = strtolower(trim($object["identity"]));
    //     $data = array(
    //         'message' => 'Kindly provide your email and password',
    //     );
    //     if ($identity != '') {
    //         $identity = $this->ion_auth->where('email', $identity)->users()->row();
    //         if (empty($identity)) {
    //             $data['message'] = 'Email not found!';
    //         }
    //         $forgotten = $this->ion_auth->forgotten_password($identity->{$this->config->item('identity', 'ion_auth')});
    //         if ($forgotten) {
    //             //$this->session->set_flashdata('success', $this->ion_auth->messages());
    //             //$this->session->set_flashdata('message', 'Please check your email for password reset link');
    //             $data['message'] = 'Please check your email for password reset link';
    //         } else {
    //             $data['message'] = 'An error occured. Please try again';
    //         }
    //     }
    //     header('Content-type: application/json');
    //     echo json_encode($data);
    // }

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
        http_response_code(404); // Not Found
        echo json_encode([
            'status'  => false,
            'message' => 'Email does not exist'
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

    /*=======================================================
            END LOGIN API SECTION
    ========================================================
    */

    /*=======================================================
        POST API SECTION
    ========================================================
    */



    // Function to encode all elements in the array recursively




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
    private function checkAPI_token($token)
    {
        // normalize input
        $token = trim((string) $token);
        log_message('error', "Incoming API token: [" . $token . "]");

        $api_name = 'alumni_key';
        $this->db->where('api_name', $api_name);
        $query = $this->db->get('api_table');
        if ($query !== FALSE) {
            if ($query->num_rows() == 1) {
                $user = $query->row();
                $token_hash = $user->api_token;
                log_message('error', "API token from DB: " . var_export($token_hash, true));

                if (password_verify($token, $token_hash)) {
                    // echo 'API key is valid!';
                    return TRUE;
                } else {
                    log_message('error', 'API token mismatch');
                    //echo 'API key is invalid!';
                    return FALSE;
                }
            } else {
                $query->free_result();
                log_message('error', 'API lookup returned ' . $query->num_rows() . ' rows');
                return FALSE;
            }
        } else {
            log_message('error', 'Database query failed: ' . $this->db->last_query());
            return FALSE;
        }
    }

    private function checkAPI_token_from_header()
    {
        $key = '';

        if (isset($_SERVER['HTTP_X_API_KEY'])) {
            $key = $_SERVER['HTTP_X_API_KEY'];
        } elseif (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            foreach ($headers as $k => $v) {
                if (strtolower($k) === 'x-api-key') {
                    $key = $v;
                    break;
                }
            }
        }

        return $this->checkAPI_token(trim($key));
    }

	    // private function checkJWT()
	    // {
	    //     // 1. Try Authorization: Bearer header
	    //     $token = $this->jwt_helper->getFromHeader();
        //        $body  = file_get_contents('php://input');     
        //        log_message('error', "Request body: " . $body);    
        //                     log_message('error', "JWT from header: [" . $token . "]");
	    //     // 2. Fallback: accept jwt field in JSON body (useful for testing)
	    //     if (!$token) {
	    //         $body  = file_get_contents('php://input');
	    //         $input = json_decode($body, TRUE);
	    //         $input = is_array($input) ? $input : array();
	    //         $token = isset($input['jwt']) ? trim($input['jwt']) : NULL;
	    //     }

	    //     if (!$token) {
	    //         return NULL;
	    //     }

	    //     $result = $this->jwt_helper->validateAccessToken($token);
        //             log_message('error', "JWT validation result: " . var_export($result, true));
	    //     if (!$result['valid']) {
	    //         log_message('error', 'JWT checkJWT failed: ' . $result['error']);
	    //         return NULL;
	    //     }

	    //     return $result['data'];
	    // }
        
	    private function getRequestBody()
	    {
	        if ($this->cached_request_body === NULL) {
	            $this->cached_request_body = file_get_contents('php://input');
	        }
	        return $this->cached_request_body;
	    }

 private function checkJWT()
	    {
	        $token = $this->jwt_helper->getFromHeader();

	        if (!$token) {
	            return NULL;
	        }
	        log_message('error', 'JWT token found, validating...');

	        $result = $this->jwt_helper->validateAccessToken($token);

	        if (!$result['valid']) {
	            log_message('error', 'JWT checkJWT failed: ' . $result['error']);
	            return NULL;
	        }

	        return $result['data'];
	    }
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
	    public function getAPIKey()
	    {
	        $api_name = 'alumni_key';
	        $fetchAPIKey = $this->api_model->fetchAPIKey($api_name);
        log_message('error', "logging object fetchAPIKey");
        echo json_encode($fetchAPIKey);
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

    // public function uploadfiles($field, $user_id,$type){
    //     //log_message('error','fieldname '.$field.' request_id is'.$request_id);
    //     $fieldname=strtolower($field);
    //     $destination = realpath('./uploads/attachments');
    //     if ($user_id==NULL) {
    //         return false;
    //     }
    //     foreach ($_FILES[$fieldname]["error"] as $key => $error) {
    //         if ($error == UPLOAD_ERR_OK) {
    //             $firstname = preg_replace("|[\\\/()]|", "", str_replace(' ', '_',basename($_FILES[$fieldname]["name"][$key])));
    //             $filename = $user_id."_".$fieldname."_".$firstname;
    //             //log_message('error','File name is'.$filename);
    //             $tmp_name = $_FILES[$fieldname]["tmp_name"][$key];
    //             move_uploaded_file($tmp_name, $destination."/".$filename);
    //             $post_file = array(
    //                 'user_id' => $user_id, 
    //                 'file_type' => $type, 
    //                 'filename' => $firstname,
    //                 'attachment_file' => "attachments/".$filename,
    //                 'dateadded' => date('Y-m-d H:i:s')
    //             );      
    //             $this->db->insert('attachments', $post_file);        
    //         }
    //     }     
    //     return true;
    // }
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

    /*=======================================================
        REGISTER API SECTION
    ========================================================
    */
    //     public function register()
    //     {
    //         $body = file_get_contents("php://input");
    //         $object = json_decode($body, true);
    //         log_message('error', "logging object in register");
    //         log_message('error', var_export($object, true));
    //         log_message('error', var_export($body, true));
    //         $data = array(
    //             'status' => 404,
    //             'message' => 'Kindly provide your email and password',
    //             //  'userCode' => 0
    //         );


    //         $token = trim($object["token"]);
    // string: string: string: 

    //         $confirmToken = $this->checkAPI_token_from_header();
    //         // $custtype = trim($object["custtype"]);
    //          if ($confirmToken) {
    //             $email = trim($object["email"]);
    //             $first_name = trim($object["first_name"]);
    //             $last_name = trim($object["last_name"]);
    //             $fullname = $first_name . ' ' . $last_name;
    //             $emergency_contact = trim($object["emergency_contact"]);
    //             $address = trim($object["address"]);
    //             $move_in_date = trim($object["move_in_date"]);
    //            // $area = trim($object["area"]);
    //            // $designation = trim($object["designation"]);
    //             $password = trim($object["password"]);
    //             $additional_data = array(
    //                 'first_name' => $first_name,
    //                 'last_name' => $last_name,
    //                 'fullname' => $fullname,
    //                 'emergency_contact' => $emergency_contact,
    //                 'address' => $address,
    //                 'move_in_date' => $move_in_date,
    //                // 'area' => $area,
    //                 //'designation' => $designation,
    //                 'user_role' => 'User',
    //                 'profile_status' => 'No'
    //             );
    //             $group = array('2'); // Sets user to admin.
    //             if ($this->ion_auth->email_check($email)) {
    //                 $data['status'] = 409;
    //                 $data['message'] = 'Email already exist, Kindly use another email';
    //                 $this->output->set_status_header(409);
    //             } else {
    //                 $user_id = $this->ion_auth->register($email, $password, $email, $additional_data, $group);
    //                 if ($user_id) {
    //                    $proof_of_document = $this->uploadfiles('Proof of Document', $user_id);
    //                    if ($proof_of_document) {
    //                      if (!empty($getCustomer)) {
    //                         $data['proof_of_document'] = $getCustomer[0]['filename'];
    //                         $data['attachment_file']   = $getCustomer[0]['attachment_file'];
    //                     }
    //                    }

    //                     $data['user_id'] = $user_id;
    //                     $data['status'] = 200;
    //                     $data['message'] = 'Registration Successful';
    //                     $data['email'] = $email;
    //                     //$data['fullName'] = $userInfo->first_name ." ". $userInfo->last_name;
    //                     $data['fullname'] = $fullname;
    //                     $data['user_role'] = 'User';
    //                     $data['emergency_contact'] = $emergency_contact;
    //                     $data['move_in_date'] = $move_in_date;
    //                     //$data['area'] = $area;
    //                     $data['profile_status'] =  'No';

    //                     $data['status'] = TRUE;
    //                     //$data['designation'] = $designation;
    //                     //$this->trackUser();
    //                     $this->output->set_status_header(200);
    //                 } else {
    //                     $data['status'] = 400;
    //                     $data['message'] = 'Registration Failed, Please try again';
    //                     //$data['userCode'] = 0;
    //                     $this->output->set_status_header(400);
    //                 }
    //             }

    //          }else {

    //             $data['message'] = 'API key is invalid!';
    //             $this->output->set_status_header(404);
    //         }
    //         log_message('error', 'Before Sending out');
    //         log_message('error', var_export($data, true));
    //         header('Content-type: application/json');
    //         // var_dump($data);
    //         // die();
    //         // var_export($data);
    //         echo json_encode($data);
    //         //$this->output->set_status_header(404);

    //     }


//     public function get_chapters()
// {
//     $contentType = $this->input->server('CONTENT_TYPE');
//     $object = (strpos($contentType, 'application/json') !== false)
//         ? json_decode(file_get_contents("php://input"), true)
//         : $this->input->get();

//     $data = ['status' => 400, 'message' => 'Invalid request'];

//     $token = isset($object['token']) ? trim($object['token']) : '';
//     $confirmToken = $this->checkAPI_token_from_header();

//     if (!$confirmToken) {
//         $data['message'] = 'API key is invalid!';
//         $this->output->set_status_header(401);
//         header('Content-Type: application/json');
//         echo json_encode($data);
//         return;
//     }

//     $chapters = $this->db
//         ->where('is_enabled', 1)
//         ->order_by('chapter_name', 'ASC')
//         ->get('alumni_chapter')
//         ->result_array();

//     $data = [
//         'status'   => 200,
//         'message'  => 'Chapters retrieved successfully',
//         'chapters' => $chapters
//     ];
//     $this->output->set_status_header(200);
//     header('Content-Type: application/json');
//     echo json_encode($data);
// }
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
}public function register()
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
                    'user_id'    => $user_id,
                    'chapter_id' => $chapter_id ?: null,
                    'year'       => $year,
                    'city'       => $city ?: null,
                    'created_at' => date('Y-m-d H:i:s'),
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

    // public function vehicle_register()
    // {
    //     $contentType = $this->input->server('CONTENT_TYPE');
    //     if (strpos($contentType, 'application/json') !== false) {
    //         $body   = file_get_contents("php://input");
    //         $object = json_decode($body, true);
    //     } else {
    //         $object = $this->input->post();
    //     }

    //     log_message('error', "Vehicle API Input: " . var_export($object, true));

    //     $data = [
    //         'status'  => 400,
    //         'message' => 'Invalid request'
    //     ];

    //     $token = isset($object['token']) ? trim($object['token']) : '';
    //     $confirmToken = $this->checkAPI_token_from_header();

    //     if ($confirmToken) {
    //         $user_id             = trim($object["user_id"]);
    //         $brand               = trim($object["brand"]);
    //         $model               = trim($object["model"]);
    //         $registration_number = trim($object["registration_number"]);
    //         $colour              = isset($object["colour"]) ? trim($object["colour"]) : null;
    //         $registration_date   = isset($object["registration_date"]) ? trim($object["registration_date"]) : null;

    //         // --- validate user_id exists (prevent foreign key error) ---
    //         $userExists = $this->db->get_where('users', ['id' => $user_id])->row();
    //         if (!$userExists) {
    //             $data['status']  = 404;
    //             $data['message'] = 'User not found';
    //             $this->output->set_status_header(404);
    //             header('Content-Type: application/json');
    //             echo json_encode($data);
    //             return;
    //         }

    //         // --- handle uploads ---
    //         $vehicle_photo     = null;
    //         $proof_of_ownership = null;

    //         if (!empty($_FILES['vehicle_photo']['name'])) {
    //             $vehicle_photo = $this->uploadfiles('vehicle_photo', $user_id, 'vehicles');
    //         }

    //         // if (!empty($_FILES['proof_of_ownership']['name'])) {
    //         //     $proof_of_ownership = $this->uploadfiles('proof_of_ownership', $user_id, 'vehicles');
    //         // }

    //         $insertData = [
    //             'user_id'             => $user_id,
    //             'brand'               => $brand,
    //             'model'               => $model,
    //             'registration_number' => $registration_number,
    //             'colour'              => $colour,
    //             'vehicle_photo'       =>  site_url($vehicle_photo[0]->attachment_file),
    //             'registration_date'   => $registration_date,
    //             // 'proof_of_ownership'  => $proof_of_ownership
    //         ];

    //         // --- check if vehicle exists by registration_number ---
    //         $existingVehicle = $this->db->get_where('vehicles', ['registration_number' => $registration_number])->row();

    //         if ($existingVehicle) {
    //             // UPDATE instead of insert
    //             $this->db->where('registration_number', $registration_number);
    //             $this->db->update('vehicles', $insertData);
    //             $vehicle_id = $existingVehicle->vehicle_id;
    //             $message    = 'Vehicle Updated Successfully';
    //         } else {
    //             // INSERT new record
    //             $this->db->insert('vehicles', $insertData);
    //             $vehicle_id = $this->db->insert_id();
    //             $message    = 'Vehicle Registered Successfully';
    //         }

    //         if ($vehicle_id) {
    //             $data['status']  = 200;
    //             $data['message'] = $message;
    //             $data['vehicle'] = array_merge(['vehicle_id' => $vehicle_id], $insertData);
    //             $this->output->set_status_header(200);
    //         } else {
    //             $data['status']  = 400;
    //             $data['message'] = 'Vehicle Registration Failed';
    //             $this->output->set_status_header(400);
    //         }
    //     } else {
    //         $data['message'] = 'API key is invalid!';
    //         $this->output->set_status_header(404);
    //     }

    //     header('Content-Type: application/json');
    //     echo json_encode($data);
    // }

    //
    public function vehicle_register()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        if (strpos($contentType, 'application/json') !== false) {
            $body   = file_get_contents("php://input");
            $object = json_decode($body, true);
        } else {
            $object = $this->input->post();
        }

        log_message('error', "Vehicle API Input: " . var_export($object, true));

        $data = [
            'status'  => 400,
            'message' => 'Invalid request'
        ];

        // If JSON starts as an array of vehicles
        $vehicles = isset($object[0]) ? $object : [$object];

        // Check token from first vehicle only
        $token = isset($vehicles[0]['token']) ? trim($vehicles[0]['token']) : '';
        $confirmToken = $this->checkAPI_token_from_header();

        if (!$confirmToken) {
            $data['message'] = 'API key is invalid!';
            $this->output->set_status_header(404);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        $results = [];
        foreach ($vehicles as $item) {
            $user_id             = trim($item["user_id"]);
            $brand               = trim($item["brand"]);
            $model               = trim($item["model"]);
            $registration_number = trim($item["registration_number"]);
            $colour              = isset($item["colour"]) ? trim($item["colour"]) : null;
            $registration_date   = isset($item["registration_date"]) ? trim($item["registration_date"]) : null;

            // Validate user_id
            $userExists = $this->db->get_where('users', ['id' => $user_id])->row();
            if (!$userExists) {
                $results[] = [
                    'registration_number' => $registration_number,
                    'status'  => 404,
                    'message' => 'User not found'
                ];
                continue;
            }

            // Handle uploads (optional)
            $vehicle_photo = null;
            if (!empty($_FILES['vehicle_photo']['name'])) {
                $vehicle_photo = $this->uploadfiles('vehicle_photo', $user_id, 'vehicles');
            }

            $insertData = [
                'user_id'             => $user_id,
                'brand'               => $brand,
                'model'               => $model,
                'registration_number' => $registration_number,
                'colour'              => $colour,
                'vehicle_photo'       => isset($vehicle_photo[0]->attachment_file) ? site_url($vehicle_photo[0]->attachment_file) : null,
                'registration_date'   => $registration_date
            ];

            // Check existing vehicle
            $existingVehicle = $this->db->get_where('vehicles', ['registration_number' => $registration_number])->row();

            if ($existingVehicle) {
                $this->db->where('registration_number', $registration_number);
                $this->db->update('vehicles', $insertData);
                $vehicle_id = $existingVehicle->vehicle_id;
                $message = 'Vehicle Updated Successfully';
            } else {
                $this->db->insert('vehicles', $insertData);
                $vehicle_id = $this->db->insert_id();
                $message = 'Vehicle Registered Successfully';
            }

            $results[] = [
                'registration_number' => $registration_number,
                'status'  => 200,
                'message' => $message,
                'vehicle' => array_merge(['vehicle_id' => $vehicle_id], $insertData)
            ];
        }

        $data = [
            'status'  => 200,
            'message' => 'Processed ' . count($results) . ' vehicle(s)',
            'results' => $results
        ];

        $this->output->set_status_header(200);
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    public function estate_dues_register()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        if (strpos($contentType, 'application/json') !== false) {
            $body   = file_get_contents("php://input");
            $object = json_decode($body, true);
        } else {
            $object = $this->input->post();
        }

        log_message('error', "Estate Dues API Input: " . var_export($object, true));

        $data = [
            'status'  => 400,
            'message' => 'Invalid request'
        ];

        $token = isset($object['token']) ? trim($object['token']) : '';
        $confirmToken = $this->checkAPI_token_from_header();

        if ($confirmToken) {
            $user_id = trim($object["user_id"]);
            $notes   = isset($object["notes"]) ? trim($object["notes"]) : null;
            $amount  = isset($object["amount"]) ? trim($object["amount"]) : null;
            $bill_id  = isset($object["bill_id"]) ? trim($object["bill_id"]) : null;

            // --- validate user_id exists ---
            $userExists = $this->db->get_where('users', ['id' => $user_id])->row();
            if (!$userExists) {
                $data['status']  = 404;
                $data['message'] = 'User not found';
                $this->output->set_status_header(404);
                header('Content-Type: application/json');
                echo json_encode($data);
                return;
            }

            // --- handle uploads ---
            $receipt_image = null;
            if (!empty($_FILES['receipt_image']['name'])) {
                $uploaded = $this->uploadfiles('receipt_image', $user_id, 'estate_dues');
                log_message('error', var_export($uploaded, true));
                $receipt_image = site_url($uploaded[0]->attachment_file);
            }

            $insertData = [
                'user_id'       => $user_id,
                'notes'         => $notes,
                'amount'        => $amount,
                'receipt_image' => $receipt_image,
                'bill_id'       => $bill_id
            ];

            // --- check if estate_due_id was provided (for update) ---
            $estate_due_id = isset($object["estate_due_id"]) ? trim($object["estate_due_id"]) : null;
            if ($estate_due_id) {
                $existingDue = $this->db->get_where('estate_dues', ['estate_due_id' => $estate_due_id])->row();

                if ($existingDue) {
                    $this->db->where('estate_due_id', $estate_due_id);
                    $this->db->update('estate_dues', $insertData);
                    $message = 'Estate Due Updated Successfully';
                } else {
                    $this->db->insert('estate_dues', $insertData);
                    $estate_due_id = $this->db->insert_id();
                    $message = 'Estate Due Registered Successfully';
                }
            } else {
                $this->db->insert('estate_dues', $insertData);
                $estate_due_id = $this->db->insert_id();
                $message = 'Estate Due Registered Successfully';
            }

            if ($estate_due_id) {
                $data['status'] = 200;
                $data['message'] = $message;
                $data['estate_due'] = array_merge(['estate_due_id' => $estate_due_id], $insertData);
                $this->output->set_status_header(200);
            } else {
                $data['status']  = 400;
                $data['message'] = 'Estate Due Registration Failed';
                $this->output->set_status_header(400);
            }
        } else {
            $data['message'] = 'API key is invalid!';
            $this->output->set_status_header(404);
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }
    ///
    public function bill_register()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        if (strpos($contentType, 'application/json') !== false) {
            $body   = file_get_contents("php://input");
            $object = json_decode($body, true);
        } else {
            $object = $this->input->post();
        }

        log_message('error', "Bill API Input: " . var_export($object, true));

        $data = [
            'status'  => 400,
            'message' => 'Invalid request'
        ];

        $token = isset($object['token']) ? trim($object['token']) : '';
        $confirmToken = $this->checkAPI_token_from_header();

        if ($confirmToken) {
            $user_id          = trim($object["user_id"]);
            $bill_title       = trim($object["bill_title"]);
            $payment_deadline = trim($object["payment_deadline"]);
            $amount           = trim($object["amount"]);
            $frequency        = isset($object["frequency"]) ? trim($object["frequency"]) : null;
            // --- validate user_id exists ---
            $userExists = $this->db->get_where('users', ['id' => $user_id])->row();
            if (!$userExists) {
                $data['status']  = 404;
                $data['message'] = 'User not found';
                $this->output->set_status_header(404);
                header('Content-Type: application/json');
                echo json_encode($data);
                return;
            }

            // --- prepare insert/update data ---
            $insertData = [
                'user_id'          => $user_id,
                'bill_title'       => $bill_title,
                'payment_deadline' => $payment_deadline,
                'amount'           => $amount,
                'frequency'        => $frequency
            ];

            // --- check if a bill already exists for this user & bill_title ---
            $existingBill = $this->db->get_where('bills', [
                'user_id'    => $user_id,
                'bill_title' => $bill_title
            ])->row();

            if ($existingBill) {
                // UPDATE existing bill
                $this->db->where('bill_id', $existingBill->bill_id);
                $this->db->update('bills', $insertData);
                $bill_id = $existingBill->bill_id;
                $message = 'Bill Updated Successfully';
            } else {
                // INSERT new bill
                $this->db->insert('bills', $insertData);
                $bill_id = $this->db->insert_id();
                $message = 'Bill Registered Successfully';
            }

            if ($bill_id) {
                $data['status'] = 200;
                $data['message'] = $message;
                $data['bill'] = array_merge(['bill_id' => $bill_id], $insertData);
                $this->output->set_status_header(200);
            } else {
                $data['status'] = 400;
                $data['message'] = 'Bill Registration Failed';
                $this->output->set_status_header(400);
            }
        } else {
            $data['message'] = 'API key is invalid!';
            $this->output->set_status_header(404);
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }
    public function expense_register()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        if (strpos($contentType, 'application/json') !== false) {
            $body   = file_get_contents("php://input");
            $object = json_decode($body, true);
        } else {
            $object = $this->input->post();
        }

        log_message('error', "Expense API Input: " . var_export($object, true));

        $data = [
            'status'  => 400,
            'message' => 'Invalid request'
        ];

        $token = isset($object['token']) ? trim($object['token']) : '';
        $confirmToken = $this->checkAPI_token_from_header();

        if ($confirmToken) {
            $user_id       = trim($object["user_id"]);
            $expense_name  = trim($object["expense_name"]);
            $date          = trim($object["date"]);
            $amount        = trim($object["amount"]);
            $description   = isset($object["description"]) ? trim($object["description"]) : null;

            // --- validate user_id exists ---
            $userExists = $this->db->get_where('users', ['id' => $user_id])->row();
            if (!$userExists) {
                $data['status']  = 404;
                $data['message'] = 'User not found';
                $this->output->set_status_header(404);
                header('Content-Type: application/json');
                echo json_encode($data);
                return;
            }

            // --- prepare insert/update data ---
            $insertData = [
                'user_id'      => $user_id,
                'expense_name' => $expense_name,
                'date'         => $date,
                'description'  => $description,
                'amount'       => $amount
            ];

            // --- check if expense already exists for same user + expense_name + date ---
            $existingExpense = $this->db->get_where('expense_tracker', [
                'user_id'      => $user_id,
                'expense_name' => $expense_name,
                'date'         => $date
            ])->row();

            if ($existingExpense) {
                // UPDATE existing expense
                $this->db->where('expense_id', $existingExpense->expense_id);
                $this->db->update('expense_tracker', $insertData);
                $expense_id = $existingExpense->expense_id;
                $message    = 'Expense Updated Successfully';
            } else {
                // INSERT new expense
                $this->db->insert('expense_tracker', $insertData);
                $expense_id = $this->db->insert_id();
                $message    = 'Expense Registered Successfully';
            }

            if ($expense_id) {
                $data['status']  = 200;
                $data['message'] = $message;
                $data['expense'] = array_merge(['expense_id' => $expense_id], $insertData);
                $this->output->set_status_header(200);
            } else {
                $data['status']  = 400;
                $data['message'] = 'Expense Registration Failed';
                $this->output->set_status_header(400);
            }
        } else {
            $data['message'] = 'API key is invalid!';
            $this->output->set_status_header(404);
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }
    // public function incident_report_register()
    // {
    //     $contentType = $this->input->server('CONTENT_TYPE');
    //     if (strpos($contentType, 'application/json') !== false) {
    //         $body   = file_get_contents("php://input");
    //         $object = json_decode($body, true);
    //     } else {
    //         $object = $this->input->post();
    //     }

    //     log_message('error', "Incident Report API Input: " . var_export($object, true));

    //     $data = [
    //         'status'  => 400,
    //         'message' => 'Invalid request'
    //     ];

    //     $token = isset($object['token']) ? trim($object['token']) : '';
    //     $confirmToken = $this->checkAPI_token_from_header();

    //     if ($confirmToken) {
    //         $user_id       = trim($object["user_id"]);
    //         $incident_type = isset($object["incident_type"]) ? trim($object["incident_type"]) : null;
    //         $description   = isset($object["description"]) ? trim($object["description"]) : null;
    //         $location      = isset($object["location"]) ? trim($object["location"]) : null;

    //         // --- validate user_id exists ---
    //         $userExists = $this->db->get_where('users', ['id' => $user_id])->row();
    //         if (!$userExists) {
    //             $data['status']  = 404;
    //             $data['message'] = 'User not found';
    //             $this->output->set_status_header(404);
    //             header('Content-Type: application/json');
    //             echo json_encode($data);
    //             return;
    //         }

    //         // --- handle uploads (image) ---
    //         $incident_image = null;
    //         if (!empty($_FILES['image']['name'])) {
    //             $uploaded = $this->uploadfiles('image', $user_id, 'incident_reports');
    //             log_message('error', var_export($uploaded, true));
    //             $incident_image = site_url($uploaded[0]->attachment_file);
    //         }

    //         $insertData = [
    //             'user_id'       => $user_id,
    //             'incident_type' => $incident_type,
    //             'description'   => $description,
    //             'location'      => $location,
    //             'image'         => $incident_image
    //         ];

    //         // --- check if incident_id was provided (for update) ---
    //         $incident_id = isset($object["incident_id"]) ? trim($object["incident_id"]) : null;
    //         if ($incident_id) {
    //             $existingIncident = $this->db->get_where('incident_reports', ['incident_id' => $incident_id])->row();

    //             if ($existingIncident) {
    //                 $this->db->where('incident_id', $incident_id);
    //                 $this->db->update('incident_reports', $insertData);
    //                 $message = 'Incident Report Updated Successfully';
    //             } else {
    //                 $this->db->insert('incident_reports', $insertData);
    //                 $incident_id = $this->db->insert_id();
    //                 $message = 'Incident Report Registered Successfully';
    //             }
    //         } else {
    //             $this->db->insert('incident_reports', $insertData);
    //             $incident_id = $this->db->insert_id();
    //             $message = 'Incident Report Registered Successfully';
    //         }

    //         if ($incident_id) {
    //             $data['status']   = 200;
    //             $data['message']  = $message;
    //             $data['incident'] = array_merge(['incident_id' => $incident_id], $insertData);
    //             $this->output->set_status_header(200);
    //         } else {
    //             $data['status']  = 400;
    //             $data['message'] = 'Incident Report Registration Failed';
    //             $this->output->set_status_header(400);
    //         }
    //     } else {
    //         $data['message'] = 'API key is invalid!';
    //         $this->output->set_status_header(404);
    //     }

    //     header('Content-Type: application/json');
    //     echo json_encode($data);
    // }
    public function incident_report_register()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        if (strpos($contentType, 'application/json') !== false) {
            $body   = file_get_contents("php://input");
            $object = json_decode($body, true);
        } else {
            $object = $this->input->post();
        }

        log_message('error', "Incident Report API Input: " . var_export($object, true));

        $data = [
            'status'  => 400,
            'message' => 'Invalid request'
        ];

        $token = isset($object['token']) ? trim($object['token']) : '';
        $confirmToken = $this->checkAPI_token_from_header();

        if ($confirmToken) {
            $user_id           = trim($object["user_id"]);
            $incident_name     = isset($object["incident_name"]) ? trim($object["incident_name"]) : null;
            $incident_type     = isset($object["incident_type"]) ? trim($object["incident_type"]) : null;
            $incident_category = isset($object["incident_category"]) ? trim($object["incident_category"]) : null;
            $description       = isset($object["description"]) ? trim($object["description"]) : null;
            $location          = isset($object["location"]) ? trim($object["location"]) : null;

            // --- validate user_id exists ---
            $userExists = $this->db->get_where('users', ['id' => $user_id])->row();
            if (!$userExists) {
                $data['status']  = 404;
                $data['message'] = 'User not found';
                $this->output->set_status_header(404);
                header('Content-Type: application/json');
                echo json_encode($data);
                return;
            }

            // --- validation: incident_type Emergency must have incident_category ---
            if (strtolower($incident_type) === 'emergency' && empty($incident_category)) {
                $data['status']  = 422;
                $data['message'] = 'Incident category is required for Emergency type.';
                $this->output->set_status_header(422);
                header('Content-Type: application/json');
                echo json_encode($data);
                return;
            }

            // --- handle multiple image uploads ---
            $incident_images = [];
            if (!empty($_FILES['images']['name'][0])) {
                $files = $_FILES;
                $count = count($_FILES['images']['name']);
                for ($i = 0; $i < $count; $i++) {
                    $_FILES['image']['name']     = $files['images']['name'][$i];
                    $_FILES['image']['type']     = $files['images']['type'][$i];
                    $_FILES['image']['tmp_name'] = $files['images']['tmp_name'][$i];
                    $_FILES['image']['error']    = $files['images']['error'][$i];
                    $_FILES['image']['size']     = $files['images']['size'][$i];

                    $uploaded = $this->uploadfiles('image', $user_id, 'incident_reports');
                    if ($uploaded && isset($uploaded[0]->attachment_file)) {
                        $incident_images[] = site_url($uploaded[0]->attachment_file);
                    }
                }
            }

            $insertData = [
                'user_id'           => $user_id,
                'incident_type'     => $incident_type,
                'incident_name'     => $incident_name,
                'incident_category' => $incident_category,
                'description'       => $description,
                'location'          => $location,
                'status'            => 'Pending',
                'image'             => !empty($incident_images) ? json_encode($incident_images, JSON_UNESCAPED_SLASHES) : null
            ];

            // --- check if incident_id was provided (for update) ---
            $incident_id = isset($object["incident_id"]) ? trim($object["incident_id"]) : null;
            if ($incident_id) {
                $existingIncident = $this->db->get_where('incident_reports', ['incident_id' => $incident_id])->row();

                if ($existingIncident) {
                    $this->db->where('incident_id', $incident_id);
                    $this->db->update('incident_reports', $insertData);
                    if (strtolower($existingIncident->incident_category) === 'emergency') {
                        $message = 'Emergency Report Updated Successfully';
                    } else {
                        $message = 'Incident Report Updated Successfully';
                    }
                } else {
                    $this->db->insert('incident_reports', $insertData);
                    $incident_id = $this->db->insert_id();
                    if (strtolower($incident_category) === 'emergency') {
                        $message = 'Emergency Report Registered Successfully';
                    } else {
                        $message = 'Incident Report Registered Successfully';
                    }
                }
            } else {
                $this->db->insert('incident_reports', $insertData);
                $incident_id = $this->db->insert_id();
               // Determine message based on incident category
                if (strtolower($incident_category) === 'emergency') {
                    $message = 'Emergency Report Registered Successfully';
                } else {
                    $message = 'Incident Report Registered Successfully';
                }
            }

            if ($incident_id) {
                $data['status']   = 200;
                $data['message']  = $message;
                $data['incident'] = array_merge(['incident_id' => $incident_id], $insertData);
                $this->output->set_status_header(200);
            } else {
                $data['status']  = 400;
                $data['message'] = 'Incident Report Registration Failed';
                $this->output->set_status_header(400);
            }
        } else {
            $data['message'] = 'API key is invalid!';
            $this->output->set_status_header(404);
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }

    public function register_visitor()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        if (strpos($contentType, 'application/json') !== false) {
            $body   = file_get_contents("php://input");
            $object = json_decode($body, true);
        } else {
            $object = $this->input->post();
        }

        log_message('error', "Register Visitor API Input: " . var_export($object, true));

        $data = [
            'status'  => 400,
            'message' => 'Invalid request'
        ];

        $token = isset($object['token']) ? trim($object['token']) : '';
        $confirmToken = $this->checkAPI_token_from_header();

        if ($confirmToken) {
            $user_id   = trim($object["user_id"]);
            $full_name = trim($object["full_name"]);
            $date_time = trim($object["date_time"]);
            // $status    = trim($object["status"]);
            $mode      = isset($object["mode_of_arrival"]) ? trim($object["mode_of_arrival"]) : null;
            $plate     = isset($object["plate_number"]) ? trim($object["plate_number"]) : null;
            $note      = isset($object["note_for_security"]) ? trim($object["note_for_security"]) : null;
            $visitor_type     = isset($object["visitor_type"]) ? trim($object["visitor_type"]) : "visitor";
            //Generate access code
            
            $access_code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

            //Auto set check_date
            $check_date = date('Y-m-d H:i:s');

            // --- validate user_id exists ---
            $userExists = $this->db->get_where('users', ['id' => $user_id])->row();
            $saveVisitorCode=strtolower($visitor_type) === 'resident' ? $userExists->userAccessCode : $access_code;
            if (!$userExists) {
                $data['status']  = 404;
                $data['message'] = 'User not found';
                $this->output->set_status_header(404);
                header('Content-Type: application/json');
                echo json_encode($data);
                return;
            }

            $insertData = [
                'user_id'        => $user_id,
                'full_name'      => $full_name,
                'date_time'      => $date_time,
                'status'         => 'Submitted',
                // 'check_date'     => $check_date,
                'visitor_type'     => $visitor_type,
                'mode_of_arrival' => $mode,
                'plate_number'   => $plate,
                'note_for_security' => $note,
                // 'access_code'    => $access_code
                'access_code'    => $saveVisitorCode
            ];

            // --- check if visitor_id provided (update) ---
            $visitor_id = isset($object["visitor_id"]) ? trim($object["visitor_id"]) : null;
            if ($visitor_id) {
                $existing = $this->db->get_where('register_visitors', ['visitor_id' => $visitor_id])->row();
                if ($existing) {
                    $this->db->where('visitor_id', $visitor_id);
                    $this->db->update('register_visitors', $insertData);
                    $message = 'Visitor Updated Successfully';
                } else {
                    $this->db->insert('register_visitors', $insertData);
                    $visitor_id = $this->db->insert_id();
                    $message = 'Visitor Registered Successfully';
                }
            } else {
                $this->db->insert('register_visitors', $insertData);
                $visitor_id = $this->db->insert_id();
                $message = 'Visitor Registered Successfully';
            }

            if ($visitor_id) {
                $data['status']  = 200;
                $data['message'] = $message;
                $data['visitor'] = array_merge(['visitor_id' => $visitor_id], $insertData);
                $this->output->set_status_header(200);
            } else {
                $data['status']  = 400;
                $data['message'] = 'Visitor Registration Failed';
                $this->output->set_status_header(400);
            }
        } else {
            $data['message'] = 'API key is invalid!';
            $this->output->set_status_header(404);
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }
    public function service_request_register()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        if (strpos($contentType, 'application/json') !== false) {
            $body   = file_get_contents("php://input");
            $object = json_decode($body, true);
        } else {
            $object = $this->input->post();
        }

        log_message('error', "Service Request API Input: " . var_export($object, true));

        $data = [
            'status'  => 400,
            'message' => 'Invalid request'
        ];

        $token = isset($object['token']) ? trim($object['token']) : '';
        $confirmToken = $this->checkAPI_token_from_header();

        if ($confirmToken) {
            $user_id      = trim($object["user_id"]);
            $service_type = trim($object["service_type"]);
            $description  = isset($object["description"]) ? trim($object["description"]) : null;

            // --- validate user_id exists ---
            $userExists = $this->db->get_where('users', ['id' => $user_id])->row();
            if (!$userExists) {
                $data['status']  = 404;
                $data['message'] = 'User not found';
                $this->output->set_status_header(404);
                header('Content-Type: application/json');
                echo json_encode($data);
                return;
            }

            // --- handle uploads ---
            $issue_image = null;
            if (!empty($_FILES['issue_image']['name'])) {
                $uploaded = $this->uploadfiles('issue_image', $user_id, 'service_request');
                log_message('error', var_export($uploaded, true));
                if ($uploaded) {
                    $issue_image = site_url($uploaded[0]->attachment_file);
                }
            }

            $insertData = [
                'user_id'      => $user_id,
                'service_type' => $service_type,
                'description'  => $description,
                'issue_image'  => $issue_image
            ];

            // --- check if service_request_id provided (for update) ---
            $service_request_id = isset($object["service_request_id"]) ? trim($object["service_request_id"]) : null;
            if ($service_request_id) {
                $existing = $this->db->get_where('service_requests', ['service_request_id' => $service_request_id])->row();
                if ($existing) {
                    $this->db->where('service_request_id', $service_request_id);
                    $this->db->update('service_requests', $insertData);
                    $message = 'Service Request Updated Successfully';
                } else {
                    $this->db->insert('service_requests', $insertData);
                    $service_request_id = $this->db->insert_id();
                    $message = 'Service Request Registered Successfully';
                }
            } else {
                $this->db->insert('service_requests', $insertData);
                $service_request_id = $this->db->insert_id();
                $message = 'Service Request Registered Successfully';
            }

            if ($service_request_id) {
                $data['status']  = 200;
                $data['message'] = $message;
                $data['service_request'] = array_merge(['service_request_id' => $service_request_id], $insertData);
                $this->output->set_status_header(200);
            } else {
                $data['status']  = 400;
                $data['message'] = 'Service Request Registration Failed';
                $this->output->set_status_header(400);
            }
        } else {
            $data['message'] = 'API key is invalid!';
            $this->output->set_status_header(404);
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }
    public function service_request_action()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        if (strpos($contentType, 'application/json') !== false) {
            $body   = file_get_contents("php://input");
            $object = json_decode($body, true);
        } else {
            $object = $this->input->post();
        }

        log_message('error', "Service Request Action API Input: " . var_export($object, true));

        $data = ['status' => 400, 'message' => 'Invalid request'];

        $token = isset($object['token']) ? trim($object['token']) : '';
        $confirmToken = $this->checkAPI_token_from_header();

        if (!$confirmToken) {
            $data['message'] = 'API key is invalid!';
            $this->output->set_status_header(403);
            echo json_encode($data);
            return;
        }

        $service_request_id = isset($object['service_request_id']) ? trim($object['service_request_id']) : null;
        if (!$service_request_id) {
            $data['message'] = 'Service Request ID is required!';
            $this->output->set_status_header(400);
            echo json_encode($data);
            return;
        }

        $action = isset($object['action']) ? strtolower(trim($object['action'])) : '';

        if ($action === 'delete') {
            $existing = $this->db->get_where('service_requests', ['service_request_id' => $service_request_id])->row();
            if (!$existing) {
                $data['status'] = 404;
                $data['message'] = 'Service Request not found';
                $this->output->set_status_header(404);
            } else {
                $this->db->where('service_request_id', $service_request_id);
                $this->db->delete('service_requests');
                $data['status'] = 200;
                $data['message'] = 'Service Request deleted successfully';
                $this->output->set_status_header(200);
            }
        } elseif ($action === 'update') {
            $updateData = [];
            if (isset($object['service_type'])) $updateData['service_type'] = trim($object['service_type']);
            if (isset($object['description']))  $updateData['description'] = trim($object['description']);
            if (isset($object['status']))  $updateData['status'] = trim($object['status']);
            // Handle file upload if provided  
            if (!empty($_FILES['issue_image']['name'])) {
                $uploaded = $this->uploadfiles('issue_image', $object['user_id'], 'service_request');
                if ($uploaded) {
                    $updateData['issue_image'] = site_url($uploaded[0]->attachment_file);
                }
            }

            if (empty($updateData)) {
                $data['message'] = 'Nothing to update';
                $this->output->set_status_header(400);
            } else {
                $this->db->where('service_request_id', $service_request_id);
                $updated = $this->db->update('service_requests', $updateData);

                if ($updated) {
                    $data['status'] = 200;
                    $data['message'] = 'Service Request updated successfully';
                    $data['service_request'] = array_merge(['service_request_id' => $service_request_id], $updateData);
                    $this->output->set_status_header(200);
                } else {
                    $data['status'] = 400;
                    $data['message'] = 'Service Request update failed';
                    $this->output->set_status_header(400);
                }
            }
        } else {
            $data['message'] = 'Invalid action! Use "update" or "delete"';
            $this->output->set_status_header(400);
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }

    public function create_emergency_contact()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->post();

        log_message('error', "Create Emergency Contact Input: " . var_export($object, true));

        $data = [
            'status'  => 400,
            'message' => 'Invalid request'
        ];

        $token = isset($object['token']) ? trim($object['token']) : '';
        $confirmToken = $this->checkAPI_token_from_header();

        if ($confirmToken) {
            $role         = isset($object["role"]) ? trim($object["role"]) : null;
            $phone_number = isset($object["phone_number"]) ? trim($object["phone_number"]) : null;

            if (!$role || !$phone_number) {
                $data['message'] = 'Role and phone number are required!';
                $this->output->set_status_header(400);
            } else {
                $insertData = [
                    'role'         => $role,
                    'phone_number' => $phone_number
                ];

                $this->db->insert('emergency_contacts', $insertData);
                $insert_id = $this->db->insert_id();

                if ($insert_id) {
                    $data['status']  = 200;
                    $data['message'] = 'Emergency Contact Created Successfully';
                    $data['contact'] = array_merge(['id' => $insert_id], $insertData);
                    $this->output->set_status_header(200);
                } else {
                    $data['message'] = 'Failed to create emergency contact';
                    $this->output->set_status_header(400);
                }
            }
        } else {
            $data['message'] = 'Invalid API token';
            $this->output->set_status_header(401);
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }

    // ==========get API-================
    public function get_vehicle_register()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        if (strpos($contentType, 'application/json') !== false) {
            $body   = file_get_contents("php://input");
            $object = json_decode($body, true);
        } else {
            $object = $this->input->get(); // use GET params
        }

        log_message('error', "Get Vehicle API Input: " . var_export($object, true));

        $data = [
            'status'  => 400,
            'message' => 'Invalid request'
        ];

        $token      = isset($object['token']) ? trim($object['token']) : '';
        $user_id    = isset($object['user_id']) ? trim($object['user_id']) : '';
        $vehicle_id = isset($object['vehicle_id']) ? trim($object['vehicle_id']) : null;

        $confirmToken = $this->checkAPI_token_from_header();

        if ($confirmToken) {
            // --- validate user_id exists ---
            //$userExists = $this->db->get_where('users', ['id' => $user_id])->row();
            if ($user_id) {
                $userExists = $this->db->get_where('users', ['id' => $user_id])->row();
                $user_role = $userExists->user_role;
            }
            if ($user_id && !$userExists) {
                $data['status']  = 404;
                $data['message'] = 'User not found';
                $this->output->set_status_header(404);
                header('Content-Type: application/json');
                echo json_encode($data);
                return;
            }

            // $user_role = $userExists->user_role;
            if (stripos($user_role ?? '', 'resident') !== false) {
                if ($vehicle_id) {
                    // --- single vehicle fetch ---
                    $where = ['vehicle_id' => $vehicle_id];
                    //$vehicle = $this->db->get_where('vehicles', $where)->row_array();
                    $vehicle = $this->db
                        ->select('v.*, u.fullname AS user_name, u.email AS user_email')
                        ->from('vehicles v')
                        ->join('users u', 'u.id = v.user_id', 'left')
                        ->where('v.vehicle_id', $vehicle_id)
                        ->order_by('v.vehicle_id', 'DESC')
                        ->get()
                        ->result_array();
                }

                $vehicle = $this->db
                    ->select('v.*, u.fullname AS user_name, u.email AS user_email')
                    ->from('vehicles v')
                    ->join('users u', 'u.id = v.user_id', 'left')
                    ->where('v.user_id', $user_id)
                    ->order_by('v.vehicle_id', 'DESC')
                    ->get()
                    ->result_array();
                if ($vehicle) {
                    $data['status']  = 200;
                    $data['message'] = 'Vehicle retrieved successfully';
                    $data['vehicle'] = $vehicle;
                    $this->output->set_status_header(200);
                } else {
                    $data['status']  = 200;
                    $data['message'] = 'success';
                     $data['vehicle'] = [];
                    $this->output->set_status_header(200);
                }
            } else {
                if ($vehicle_id) {
                    // --- single vehicle fetch ---
                    $where = ['vehicle_id' => $vehicle_id];
                    //$vehicle = $this->db->get_where('vehicles', $where)->row_array();
                    $vehicles = $this->db
                        ->select('v.*, u.fullname AS user_name, u.email AS user_email')
                        ->from('vehicles v')
                        ->join('users u', 'u.id = v.user_id', 'left')
                        ->where('v.vehicle_id', $vehicle_id)
                        ->order_by('v.vehicle_id', 'DESC')
                        ->get()
                        ->result_array();
                } else {
                    // --- fetch all vehicles ---
                    // if ($user_role != 'User' || $user_role != 'resident') {
                    $vehicles = $this->db
                        ->select('vehicles.*, users.fullname AS user_name, users.email AS user_email')
                        ->from('vehicles')
                        ->join('users', 'users.id = vehicles.user_id', 'left')
                        ->order_by('vehicle_id', 'DESC')
                        ->get()
                        ->result_array();
                }
                // } else {
                //     // $vehicles = $this->db
                //     //     ->order_by('vehicle_id', 'DESC')
                //     //     ->get_where('vehicles', ['user_id' => $user_id])
                //     //     ->result_array();
                //     $vehicles = $this->db
                //         ->select('v.*, u.fullname AS user_name, u.email AS user_email')
                //         ->from('vehicles v')
                //         ->join('users u', 'u.id = v.user_id', 'left')
                //         ->where('v.user_id', $user_id)
                //         ->order_by('v.vehicle_id', 'DESC')
                //         ->get()
                //         ->result_array();
                // }


                if (!empty($vehicles)) {
                    $data['status']   = 200;
                    $data['message']  = 'Vehicles retrieved successfully';
                    $data['vehicles'] = $vehicles;
                    $this->output->set_status_header(200);
                } else {
                     $data['status']  = 200;
                    $data['message'] = 'success';
                     $data['vehicle'] = [];
                    $this->output->set_status_header(200);
                }
            }
        } else {
            $data['message'] = 'API key is invalid!';
            $this->output->set_status_header(401);
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }


    public function get_estate_dues()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        if (strpos($contentType, 'application/json') !== false) {
            $body   = file_get_contents("php://input");
            $object = json_decode($body, true);
        } else {
            $object = $this->input->get(); // use GET params
        }

        log_message('error', "Get Estate Dues API Input: " . var_export($object, true));

        $data = [
            'status'  => 400,
            'message' => 'Invalid request'
        ];

        $token         = isset($object['token']) ? trim($object['token']) : '';
        $user_id       = isset($object['user_id']) ? trim($object['user_id']) : '';
        $estate_due_id = isset($object['estate_due_id']) ? trim($object['estate_due_id']) : null;
        $bill_id       = isset($object['bill_id']) ? trim($object['bill_id']) : null;

        $confirmToken = $this->checkAPI_token_from_header();

        if ($confirmToken) {
            // --- validate user_id exists ---
            $userExists = $this->db->get_where('users', ['id' => $user_id])->row();
            if (!$userExists) {
                $data['status']  = 404;
                $data['message'] = 'User not found';
                $this->output->set_status_header(404);
                header('Content-Type: application/json');
                echo json_encode($data);
                return;
            }

            $user_role = $userExists->user_role;
            $user_name = $userExists->fullname;
            if (stripos($user_role, 'resident') !== false) {
                $where['user_id'] = $user_id; // restrict for user
                $estate_due = $this->db->get_where('estate_dues', $where)->result_array();
                if ($estate_due_id) {
                    // --- single due fetch ---
                    $where = ['estate_due_id' => $estate_due_id];
                    // if ($user_role == 'resident') {
                    $estate_due = $this->db->get_where('estate_dues', $where)->row_array();
                }



                if ($estate_due) {
                    $data['status']      = 200;
                    $data['message']     = 'Estate due retrieved successfully';
                    $data['estate_due']  = $estate_due;
                    $data['user_name']  = $user_name;
                    $this->output->set_status_header(200);
                } else {
                    $data['status']  = 200;
                    $data['message'] = 'success';
                    $data['estate_due'] = [];
                    $this->output->set_status_header(200);
                }
            } else {
                // --- fetch all dues ---
                // if ($user_role != 'resident') {
                //     $estate_dues = $this->db
                //         ->order_by('estate_due_id', 'DESC')
                //         ->get('estate_dues')
                //         ->result_array();
                // } else {
                //     $this->db->order_by('estate_due_id', 'DESC');
                //     $this->db->where('user_id', $user_id);

                //     if (!empty($bill_id)) {
                //         $this->db->where('bill_id', $bill_id);
                //     }

                //     $estate_dues = $this->db->get('estate_dues')->result_array();
                // }
                if (stripos($user_role, 'resident') === false) {
                    $estate_dues = $this->db
                        ->select('estate_dues.*, users.fullname AS user_name, users.email AS user_email') // select only needed fields
                        ->from('estate_dues')
                        //  ->join('bills', 'bills.bill_id = estate_dues.bill_id', 'left')
                        ->join('users', 'users.id = estate_dues.user_id', 'left')
                        ->order_by('estate_dues.estate_due_id', 'DESC')
                        ->get()
                        ->result_array();
                } else {
                    $this->db->select('estate_dues.*,  users.fullname AS user_name, users.email AS user_email');
                    $this->db->from('estate_dues');
                    $this->db->join('users', 'users.id = estate_dues.user_id', 'left');
                    $this->db->where('estate_dues.user_id', $user_id);

                    if (!empty($bill_id)) {
                        $this->db->where('estate_dues.bill_id', $bill_id);
                    }

                    $this->db->order_by('estate_dues.estate_due_id', 'DESC');
                    $estate_dues = $this->db->get()->result_array();
                }


                if (!empty($estate_dues)) {
                    $data['status']       = 200;
                    $data['message']      = 'Estate dues retrieved successfully';
                    $data['estate_dues']  = $estate_dues;
                    $data['user_name']  = $user_name;
                    $this->output->set_status_header(200);
                } else {
                    $data['status']  = 200;
                    $data['message'] = 'success';
                     $data['estate_dues']  = [];
                    $this->output->set_status_header(200);
                }
            }
        } else {
            $data['message'] = 'API key is invalid!';
            $this->output->set_status_header(401);
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }

    public function get_bills()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        if (strpos($contentType, 'application/json') !== false) {
            $body   = file_get_contents("php://input");
            $object = json_decode($body, true);
        } else {
            $object = $this->input->get(); // use GET params
        }

        log_message('error', "Get Bills API Input: " . var_export($object, true));

        $data = [
            'status'  => 400,
            'message' => 'Invalid request'
        ];

        $token   = isset($object['token']) ? trim($object['token']) : '';
        $user_id = isset($object['user_id']) ? trim($object['user_id']) : '';

        $confirmToken = $this->checkAPI_token_from_header();

        if ($confirmToken) {
            // --- validate user_id exists ---
            $userExists = $this->db->get_where('users', ['id' => $user_id])->row();
            if (!$userExists) {
                $data['status']  = 404;
                $data['message'] = 'User not found';
                $this->output->set_status_header(404);
                header('Content-Type: application/json');
                echo json_encode($data);
                return;
            }

            //Fix: directly use the object property
            $user_role = $userExists->user_role;

            // --- check if bill_id was passed ---
            $bill_id = isset($object['bill_id']) ? trim($object['bill_id']) : null;

            if ($bill_id) {
                // fetch a single bill
                $bill = $this->db->get_where('bills', [
                    'user_id' => $user_id,
                    'bill_id' => $bill_id
                ])->row_array();

                if ($bill) {
                    $data['status']  = 200;
                    $data['message'] = 'Bill retrieved successfully';
                    $data['bill']    = $bill;
                    $this->output->set_status_header(200);
                } else {
                    $data['status']  = 200;
                    $data['message'] = 'Bill not found';
                    $data['bill']    = [];
                    $this->output->set_status_header(200);
                }
            } else {
                //If role is NOT User → get all bills
                if ($user_role != 'resident') {
                    $bills = $this->db
                        ->order_by('bill_id', 'DESC')
                        ->get('bills')
                        ->result_array();
                } else {
                    //If role is User → only get their bills
                    $bills = $this->db
                        ->order_by('bill_id', 'DESC')
                        ->get_where('bills', ['status' => 'active'])
                        ->result_array();
                }

                if (!empty($bills)) {
                    $data['status']  = 200;
                    $data['message'] = 'Bills retrieved successfully';
                    $data['bills']   = $bills;
                    $this->output->set_status_header(200);
                } else {
                    $data['status']  = 200;
                    $data['message'] = 'No bills found';
                    $data['bills']   = [];
                }
            }
        } else {
            $data['message'] = 'API key is invalid!';
            $this->output->set_status_header(401);
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }
    public function get_expenses()
    {

        $contentType = $this->input->server('CONTENT_TYPE');
        if (strpos($contentType, 'application/json') !== false) {
            $body   = file_get_contents("php://input");
            $object = json_decode($body, true);
        } else {
            $object = $this->input->get(); // use GET params
        }
        log_message('error', "Get Expenses API Input: " . var_export($object, true));

        $data = [
            'status'  => 400,
            'message' => 'Invalid request'
        ];

        $token      = isset($object['token']) ? trim($object['token']) : '';
        $user_id    = isset($object['user_id']) ? trim($object['user_id']) : '';
        $expense_id = isset($object['expense_id']) ? trim($object['expense_id']) : null;
        $date       = isset($object['date']) ? trim($object['date']) : null; // optional filter

        $confirmToken = $this->checkAPI_token_from_header();

        if ($confirmToken) {
            // --- validate user_id exists ---
            $userExists = $this->db->get_where('users', ['id' => $user_id])->row();
            if (!$userExists) {
                $data['status']  = 404;
                $data['message'] = 'User not found';
                $this->output->set_status_header(404);
                header('Content-Type: application/json');
                echo json_encode($data);
                return;
            }

            $user_role = $userExists->user_role;

            if ($expense_id) {
                // --- single expense fetch ---
                $where = ['expense_id' => $expense_id];
                if ($user_role == 'resident') {
                    $where['user_id'] = $user_id; // restrict for user
                }

                $expense = $this->db->get_where('expense_tracker', $where)->row_array();

                if ($expense) {
                    $data['status']     = 200;
                    $data['message']    = 'Expense retrieved successfully';
                    $data['expense']    = $expense;
                    $this->output->set_status_header(200);
                } else {
                    $data['status']  = 200;
                    $data['message'] = 'Expense not found';
                    $data['expense'] = [];
                    $this->output->set_status_header(200);
                }
            } else {
                // --- fetch all expenses ---
                if ($user_role != 'User' || $user_role != 'resident') {
                    $this->db->order_by('expense_id', 'DESC');
                    if ($date) {
                        $this->db->where('date', $date);
                    }
                    $expenses = $this->db->get('expense_tracker')->result_array();
                } else {
                    $this->db->order_by('expense_id', 'DESC');
                    $where = ['user_id' => $user_id];
                    if ($date) {
                        $where['date'] = $date;
                    }
                    $expenses = $this->db->get_where('expense_tracker', $where)->result_array();
                }

                if (!empty($expenses)) {
                    $data['status']    = 200;
                    $data['message']   = 'Expenses retrieved successfully';
                    $data['expenses']  = $expenses;
                    $this->output->set_status_header(200);
                } else {
                    $data['status']  = 200;
                    $data['message'] = 'No expenses found';
                    $data['expenses'] = [];
                    $this->output->set_status_header(200);
                }
            }
        } else {
            $data['message'] = 'API key is invalid!';
            $this->output->set_status_header(401);
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }
    public function get_incident_reports()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        if (strpos($contentType, 'application/json') !== false) {
            $body   = file_get_contents("php://input");
            $object = json_decode($body, true);
        } else {
            $object = $this->input->get(); // use GET params
        }

        log_message('error', "Get Incident Reports API Input: " . var_export($object, true));

        $data = [
            'status'  => 400,
            'message' => 'Invalid request'
        ];

        $token       = isset($object['token']) ? trim($object['token']) : '';
        $user_id     = isset($object['user_id']) ? trim($object['user_id']) : '';
        $incident_id = isset($object['incident_id']) ? trim($object['incident_id']) : null;

        $confirmToken = $this->checkAPI_token_from_header();

        if ($confirmToken) {
            // --- validate user_id exists ---
            $userExists = $this->db->get_where('users', ['id' => $user_id])->row();
            if (!$userExists) {
                $data['status']  = 404;
                $data['message'] = 'User not found';
                $this->output->set_status_header(404);
                header('Content-Type: application/json');
                echo json_encode($data);
                return;
            }

            $user_role = $userExists->user_role;

            if ($incident_id) {
                // --- single incident fetch ---
                $this->db->select('ir.*, u.fullname, u.email')
                    ->from('incident_reports ir')
                    ->join('users u', 'ir.user_id = u.id', 'left')
                    ->where('ir.incident_id', $incident_id);

                if ($user_role == 'User') {
                    $this->db->where('ir.user_id', $user_id);
                }

                $incident = $this->db->get()->row_array();

                if ($incident) {
                    $data['status']    = 200;
                    $data['message']   = 'Incident report retrieved successfully';
                    $data['incident']  = $incident;
                    $this->output->set_status_header(200);
                } else {
                    $data['status']  = 200;
                    $data['message'] = 'Incident report not found';
                    $data['incident'] = [];
                    $this->output->set_status_header(200);
                }
            } else {
                // --- fetch all incidents ---
                $this->db->select('ir.*, u.fullname, u.email')
                    ->from('incident_reports ir')
                    ->join('users u', 'ir.user_id = u.id', 'left')
                    ->order_by('ir.incident_id', 'DESC');

                if ($user_role == 'User') {
                    $this->db->where('ir.user_id', $user_id);
                }

                $incidents = $this->db->get()->result_array();

                if (!empty($incidents)) {
                    $data['status']     = 200;
                    $data['message']    = 'Incident reports retrieved successfully';
                    $data['incidents']  = $incidents;
                    $this->output->set_status_header(200);
                } else {
                    $data['status']  = 200;
                    $data['message'] = 'No incident reports found';
                    $data['incidents'] = [];
                    $this->output->set_status_header(200);
                }
            }
        } else {
            $data['message'] = 'API key is invalid!';
            $this->output->set_status_header(401);
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }

    public function get_visitors()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        if (strpos($contentType, 'application/json') !== false) {
            $body   = file_get_contents("php://input");
            $object = json_decode($body, true);
        } else {
            $object = $this->input->get(); // use GET params
        }

        log_message('error', "Get Visitors API Input: " . var_export($object, true));

	        $data = [
	            'status'  => 400,
	            'message' => 'Invalid request'
	        ];

	        $token      = isset($object['token']) ? trim($object['token']) : '';
	        $visitor_id = isset($object['visitor_id']) ? trim($object['visitor_id']) : null;
	        $access_code = isset($object['access_code']) ? trim($object['access_code']) : null;
	        $visitor_role = isset($object['visitor_role']) ? trim($object['visitor_role']) : null;
	        if ($this->checkAPI_token_from_header()) {
	            $jwtData = $this->checkJWT();
	            if (!$jwtData) {
	                $this->jwtErrorResponse();
	                return;
	            }

	            $user_id   = (int)$jwtData->user_id;
	            $user_role = $jwtData->user_role;
            // if (!$userExists) {
            //     $data['status']  = 404;
            //     $data['message'] = 'User not found';
            //     $this->output->set_status_header(404);
            //     header('Content-Type: application/json');
            //     echo json_encode($data);
            //     return;
            // }


            
            if (stripos($user_role ?? '', 'resident') !== false) {
                $where['user_id'] = $user_id;
                //$visitor = $this->db->get_where('register_visitors', $where)->result_array();
                $this->db->select('rv.*, u.fullname, u.email,u.address')
                    ->from('register_visitors rv')
                    ->join('users u', 'rv.user_id = u.id', 'left')
                    ->where('rv.user_id', $user_id)
                    ->order_by('rv.visitor_id', 'DESC');

                $visitor = $this->db->get()->result_array();
                if ($visitor_id) {
                    // --- fetch single visitor ---
                    $where = ['visitor_id' => $visitor_id];
                    $visitor = $this->db->get_where('register_visitors', $where)->row_array();
                    // restrict for user
                }
                



                if ($visitor) {
                    $data['status']   = 200;
                    $data['message']  = 'Visitor retrieved successfully';
                    $data['visitor']  = $visitor;
                    $this->output->set_status_header(200);
                } else {
                    $data['status']  = 200;
                    $data['message'] = 'Success';
                     $data['visitor']  = [];
                    $this->output->set_status_header(200);
                }
            } else {

                //  if (stripos($user_role, 'resident') !== false) {
                // //if ($user_role == 'Manager' || $user_role != 'resident' || $user_role == 'Admin' || $user_role == 'Security' || $user_role != 'resident_co') {
                //     // --- Admin/Manager/Security: get all visitors with user info ---
                //     $this->db->select('rv.*, u.fullname, u.email')
                //         ->from('register_visitors rv')
                //         ->join('users u', 'rv.user_id = u.id', 'left')
                //         ->order_by('rv.visitor_id', 'DESC');

                //     $visitors = $this->db->get()->result_array();
                // } else {
                //     // --- Regular user: get only their visitors ---
                //     $this->db->select('rv.*, u.fullname, u.email')
                //         ->from('register_visitors rv')
                //         ->join('users u', 'rv.user_id = u.id', 'left')
                //         ->where('rv.user_id', $user_id)
                //         ->order_by('rv.visitor_id', 'DESC');

                //     $visitors = $this->db->get()->result_array();

                // }
                
                 
	                log_message('error', "Get Visitors access_code branch check: access_code=" . var_export($access_code, true) . ", visitor_role=" . var_export($visitor_role, true) . ", strtolower(visitor_role)=" . var_export(is_string($visitor_role) ? strtolower($visitor_role) : $visitor_role, true));
	                $qry_state = (is_string($visitor_role) && strtolower($visitor_role) === 'resident')
	                    ? 'rv.access_code = u.userAccessCode'
	                    : 'rv.user_id = u.id';
	                if ($access_code)  {
	                    // --- fetch single visitor ---
	                    // restrict for user: join on userAccessCode
	                    $this->db->select('rv.*, u.fullname, u.email, u.address')
                        ->from('register_visitors rv')
                        ->join('users u', $qry_state, 'left')
                        ->where('rv.access_code', $access_code)
                        ->order_by('rv.visitor_id', 'DESC')
                        ->limit(1);

                    $visitor = $this->db->get()->row_array();

                    // ensure later response logic (which checks `$visitors`) sees this result
                    $visitors = $visitor ? array($visitor) : array();
                    log_message('error', 'Get Visitors: resident access_code lookup returned visitor=' . var_export($visitor, true));
                }


                 else {
                    $this->db->select('rv.*, u.fullname, u.email,u.address')
                        ->from('register_visitors rv')
                        ->join('users u', 'rv.user_id = u.id', 'left')
                        ->order_by('rv.visitor_id', 'DESC');
                    $visitors = $this->db->get()->result_array();
                }



                if (!empty($visitors)) {
                    $data['status']    = 200;
                    $data['message']   = 'Visitors retrieved successfully';
                    $data['visitors']  = $visitors;
                    $this->output->set_status_header(200);
                } else {
                    $data['status']  = 200;
                    $data['message'] = 'Success';
                     $data['visitor']  = [];
                    $this->output->set_status_header(200);
                }
            }
        } else {
            $data['message'] = 'API key is invalid!';
            $this->output->set_status_header(401);
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }
    public function get_service_requests()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        if (strpos($contentType, 'application/json') !== false) {
            $body   = file_get_contents("php://input");
            $object = json_decode($body, true);
        } else {
            $object = $this->input->get(); // use GET params
        }

        log_message('error', "Get Service Requests API Input: " . var_export($object, true));

        $data = [
            'status'  => 400,
            'message' => 'Invalid request'
        ];

        $token               = isset($object['token']) ? trim($object['token']) : '';
        $user_id             = isset($object['user_id']) ? trim($object['user_id']) : '';
        $service_request_id  = isset($object['service_request_id']) ? trim($object['service_request_id']) : null;

        $confirmToken = $this->checkAPI_token_from_header();

        if ($confirmToken) {
            // --- validate user_id exists ---
            $userExists = $this->db->get_where('users', ['id' => $user_id])->row();
            if (!$userExists) {
                $data['status']  = 404;
                $data['message'] = 'User not found';
                $this->output->set_status_header(404);
                header('Content-Type: application/json');
                echo json_encode($data);
                return;
            }

            $user_role = $userExists->user_role;

            if ($service_request_id) {
                // --- fetch single service request ---
                $where = ['service_request_id' => $service_request_id];
                if ($user_role == 'User') {
                    $where['user_id'] = $user_id; // restrict to own requests
                }

                $service_request = $this->db->get_where('service_requests', $where)->row_array();

                if ($service_request) {
                    $data['status']           = 200;
                    $data['message']          = 'Service request retrieved successfully';
                    $data['service_request']  = $service_request;
                    $this->output->set_status_header(200);
                } else {
                    $data['status']  = 200;
                    $data['message'] = 'Success';
                     $data['service_request']  = [];
                    $this->output->set_status_header(200);
                }
            } else {
                // --- fetch all service requests ---
                // if ($user_role != 'User' ||$user_role != 'resident') {
                //     $service_requests = $this->db
                //         ->order_by('service_request_id', 'DESC')
                //         ->get('service_requests')
                //         ->result_array();
                // } else {
                //     $service_requests = $this->db
                //         ->order_by('service_request_id', 'DESC')
                //         ->get_where('service_requests', ['user_id' => $user_id])
                //         ->result_array();
                // }
                if ($user_role != 'User' || $user_role != 'resident') {
                    // Admin / Manager / Security — get all service requests with user info
                    $this->db->select('sr.*, u.fullname, u.email')
                        ->from('service_requests sr')
                        ->join('users u', 'sr.user_id = u.id', 'left')
                        ->order_by('sr.service_request_id', 'DESC');
                    $service_requests = $this->db->get()->result_array();
                } else {
                    // Regular user — only their own service requests
                    $this->db->select('sr.*, u.fullname, u.email')
                        ->from('service_requests sr')
                        ->join('users u', 'sr.user_id = u.id', 'left')
                        ->where('sr.user_id', $user_id)
                        ->order_by('sr.service_request_id', 'DESC');
                    $service_requests = $this->db->get()->result_array();
                }

                if (!empty($service_requests)) {
                    $data['status']            = 200;
                    $data['message']           = 'Service requests retrieved successfully';
                    $data['service_requests']  = $service_requests;
                    $this->output->set_status_header(200);
                } else {
                     $data['status']  = 200;
                    $data['message'] = 'Success';
                     $data['service_requests']  = [];
                    $this->output->set_status_header(200);
                }
            }
        } else {
            $data['message'] = 'API key is invalid!';
            $this->output->set_status_header(401);
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }
    // ==========delete /update API-================

    public function vehicle_register_action()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        if (strpos($contentType, 'application/json') !== false) {
            $body   = file_get_contents("php://input");
            $object = json_decode($body, true);
        } else {
            $object = $this->input->post(); // use POST params
        }

        log_message('error', "Vehicle Register Action API Input: " . var_export($object, true));

        $data = [
            'status'  => 400,
            'message' => 'Invalid request'
        ];

        $token        = isset($object['token']) ? trim($object['token']) : '';
        $vehicle_id   = isset($object['vehicle_id']) ? trim($object['vehicle_id']) : null;
        $functionType = isset($object['function_type']) ? strtolower(trim($object['function_type'])) : '';

        $confirmToken = $this->checkAPI_token_from_header();

        if (!$confirmToken) {
            $data['status']  = 401;
            $data['message'] = 'API key is invalid!';
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        if (!$vehicle_id) {
            $data['status']  = 422;
            $data['message'] = 'vehicle_id is required';
            $this->output->set_status_header(422);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        // ---------- DELETE ----------
        if ($functionType === 'delete') {
            $deleted = $this->db->delete('vehicles', ['vehicle_id' => $vehicle_id]);
            if ($deleted) {
                $data['status']  = 200;
                $data['message'] = 'Vehicle deleted successfully';
                $this->output->set_status_header(200);
            } else {
                $data['status']  = 404;
                $data['message'] = 'Vehicle not found or already deleted';
                $this->output->set_status_header(404);
            }
        }

        // ---------- UPDATE ----------
        elseif ($functionType === 'update') {
            $updateData = [];
            if (isset($object['plate_no'])) $updateData['plate_no'] = $object['plate_no'];
            if (isset($object['model'])) $updateData['model'] = $object['model'];
            if (isset($object['brand'])) $updateData['brand'] = $object['brand'];
            if (isset($object['colour'])) $updateData['colour'] = $object['colour'];
            if (isset($object['registration_number'])) $updateData['registration_number'] = $object['registration_number'];
            if (isset($object['registration_date'])) $updateData['registration_date'] = $object['registration_date'];

            if (!empty($updateData)) {
                $this->db->where('vehicle_id', $vehicle_id);
                $updated = $this->db->update('vehicles', $updateData);

                if ($updated) {
                    $data['status']  = 200;
                    $data['message'] = 'Vehicle updated successfully';
                    $this->output->set_status_header(200);
                } else {
                    $data['status']  = 404;
                    $data['message'] = 'Vehicle not found or update failed';
                    $this->output->set_status_header(404);
                }
            } else {
                $data['status']  = 422;
                $data['message'] = 'No update fields provided';
                $this->output->set_status_header(422);
            }
        } else {
            $data['status']  = 400;
            $data['message'] = 'Invalid function_type. Use update or delete.';
            $this->output->set_status_header(400);
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }
    public function estate_due_action()
    {
        // Accept JSON or form-data
        $contentType = $this->input->server('CONTENT_TYPE');
        if (strpos($contentType, 'application/json') !== false) {
            $body   = file_get_contents("php://input");
            $object = json_decode($body, true);
        } else {
            $object = $this->input->post();
        }

        log_message('error', "Estate Due Action API Input: " . var_export($object, true));

        $data = [
            'status'  => 400,
            'message' => 'Invalid request'
        ];

        $token         = isset($object['token']) ? trim($object['token']) : '';
        $estate_due_id = isset($object['estate_due_id']) ? trim($object['estate_due_id']) : null;
        $functionType  = isset($object['function_type']) ? strtolower(trim($object['function_type'])) : '';
        $status = isset($object['status']) ? trim($object['status']) : '';

        // token check
        $confirmToken = $this->checkAPI_token_from_header();
        if (!$confirmToken) {
            $data['status']  = 401;
            $data['message'] = 'API key is invalid!';
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        if (!$estate_due_id) {
            $data['status']  = 422;
            $data['message'] = 'estate_due_id is required';
            $this->output->set_status_header(422);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        // fetch existing record
        $existingDue = $this->db->get_where('estate_dues', ['estate_due_id' => $estate_due_id])->row();
        if (!$existingDue) {
             $data['status']  = 200;
                    $data['message'] = 'Success';
                     $data['estate_due']  = [];
                    $this->output->set_status_header(200);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        // helper to delete old file + attachment record
        $deleteOldFileAndAttachment = function ($fullUrl) {
            if (empty($fullUrl)) return;
            // get path portion (e.g. /uploads/attachments/xxx)
            $path = parse_url($fullUrl, PHP_URL_PATH);
            $rel  = ltrim($path, '/'); // uploads/attachments/...
            $full = FCPATH . $rel;
            if (file_exists($full)) {
                @unlink($full);
            }
            // remove record in attachments table if any
            $this->db->where('attachment_file', $rel)->delete('attachments');
        };

        // ---------- DELETE ----------
        if ($functionType === 'delete') {
            // delete file and attachments (if any)
            if (!empty($existingDue->receipt_image)) {
                $deleteOldFileAndAttachment($existingDue->receipt_image);
            }

            // delete estate_due record
            $deleted = $this->db->delete('estate_dues', ['estate_due_id' => $estate_due_id]);
            if ($deleted) {
                $data['status']  = 200;
                $data['message'] = 'Estate due deleted successfully';
                $this->output->set_status_header(200);
            } else {
                $data['status']  = 500;
                $data['message'] = 'Failed to delete estate due';
                $this->output->set_status_header(500);
            }

            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        // ---------- UPDATE ----------
        if ($functionType === 'update') {
            $updateData = [];

            // optional fields from request body
            if (isset($object['notes']))  $updateData['notes']  = trim($object['notes']);
            if (isset($object['amount'])) $updateData['amount'] = trim($object['amount']);
            if (isset($object['status'])) $updateData['status'] = trim($object['status']);
            if (isset($object['approval_comment'])) $updateData['approval_comment'] = trim($object['approval_comment']);
            // handle uploaded file (multipart/form-data)
            if (!empty($_FILES['receipt_image']['name'])) {
                // use your existing upload helper
                $uploaded = $this->uploadfiles('receipt_image', $existingDue->user_id, 'estate_dues');
                // uploadfiles() in your code inserts into attachments. We'll pull the latest attachment row
                $att = $this->db
                    ->select('attachment_file')
                    ->from('attachments')
                    ->where(['user_id' => $existingDue->user_id, 'file_type' => 'estate_dues'])
                    ->order_by('dateadded', 'DESC')
                    ->limit(1)
                    ->get()
                    ->row();

                if ($att && !empty($att->attachment_file)) {
                    $newRel = $att->attachment_file; // e.g. uploads/attachments/1_receipt_image_xxx.jpg
                    $newUrl = site_url($newRel);

                    // delete old file + attachment record
                    if (!empty($existingDue->receipt_image)) {
                        $deleteOldFileAndAttachment($existingDue->receipt_image);
                    }

                    // set new value
                    $updateData['receipt_image'] = $newUrl;
                }
            } else {
                // if no new file uploaded, keep existing receipt_image (do nothing)
                // If you want to explicitly remove the file, you could accept a flag like remove_receipt=1
                if (isset($object['remove_receipt']) && (int)$object['remove_receipt'] === 1) {
                    // delete old file & attachment, then set null
                    if (!empty($existingDue->receipt_image)) {
                        $deleteOldFileAndAttachment($existingDue->receipt_image);
                    }
                    $updateData['receipt_image'] = null;
                }
            }

            if (empty($updateData)) {
                $data['status']  = 422;
                $data['message'] = 'No update fields provided';
                $this->output->set_status_header(422);
                header('Content-Type: application/json');
                echo json_encode($data);
                return;
            }

            // perform update
            $this->db->where('estate_due_id', $estate_due_id);
            $ok = $this->db->update('estate_dues', $updateData);
            if ($ok) {
                // fetch updated record to return
                $updated = $this->db->get_where('estate_dues', ['estate_due_id' => $estate_due_id])->row_array();
                $data['status']      = 200;
                $data['message']     = 'Estate due updated successfully';
                $data['estate_due']  = $updated;
                $this->output->set_status_header(200);
            } else {
                $data['status']  = 500;
                $data['message'] = 'Update failed';
                $this->output->set_status_header(500);
            }

            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        // invalid function_type
        $data['status']  = 400;
        $data['message'] = 'Invalid function_type. Use update or delete.';
        $this->output->set_status_header(400);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
    public function bills_action()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        if (strpos($contentType, 'application/json') !== false) {
            $body   = file_get_contents("php://input");
            $object = json_decode($body, true);
        } else {
            $object = $this->input->post(); // use POST params
        }

        log_message('error', "Bills Action API Input: " . var_export($object, true));

        $data = [
            'status'  => 400,
            'message' => 'Invalid request'
        ];

        $token        = isset($object['token']) ? trim($object['token']) : '';
        $bill_id      = isset($object['bill_id']) ? trim($object['bill_id']) : null;
        $functionType = isset($object['function_type']) ? strtolower(trim($object['function_type'])) : '';

        $confirmToken = $this->checkAPI_token_from_header();

        if (!$confirmToken) {
            $data['status']  = 401;
            $data['message'] = 'API key is invalid!';
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        if (!$bill_id) {
            $data['status']  = 422;
            $data['message'] = 'bill_id is required';
            $this->output->set_status_header(422);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }
        log_message('error', "Bills Action API Function Type: " . $functionType);
        // ---------- DELETE ----------
        if ($functionType === 'delete') {

            log_message('error', "Bills Action API Function Type: " . $functionType);
            $deleted = $this->db->delete('bills', ['bill_id' => $bill_id]);
            if ($deleted) {
                $data['status']  = 200;
                $data['message'] = 'Bill deleted successfully';
                $this->output->set_status_header(200);
            } else {
                $data['status']  = 404;
                $data['message'] = 'Bill not found or already deleted';
                $this->output->set_status_header(404);
            }
        }

        // ---------- UPDATE ----------
        elseif ($functionType === 'update') {

            log_message('error', "Bills Action API Function Type2: " . $functionType);
            $updateData = [];
            if (isset($object['amount'])) $updateData['amount'] = $object['amount'];
            if (isset($object['bill_title'])) $updateData['bill_title'] = $object['bill_title'];
            if (isset($object['payment_deadline'])) $updateData['payment_deadline'] = $object['payment_deadline'];
            if (isset($object['frequency'])) $updateData['frequency'] = $object['frequency'];
            if (isset($object['status'])) $updateData['status'] = $object['status'];

            if (!empty($updateData)) {
                $this->db->where('bill_id', $bill_id);
                $updated = $this->db->update('bills', $updateData);

                if ($updated) {
                    $data['status']  = 200;
                    $data['message'] = 'Bill updated successfully';
                    $this->output->set_status_header(200);
                } else {
                    $data['status']  = 404;
                    $data['message'] = 'Bill not found or update failed';
                    $this->output->set_status_header(404);
                }
            } else {
                $data['status']  = 422;
                $data['message'] = 'No update fields provided';
                $this->output->set_status_header(422);
            }
        } else {

            log_message('error', "Bills Action API Function Type3: " . $functionType);
            $data['status']  = 400;
            $data['message'] = 'Invalid function_type. Use update or delete.';
            $this->output->set_status_header(400);
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }
    public function expenses_action()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        if (strpos($contentType, 'application/json') !== false) {
            $body   = file_get_contents("php://input");
            $object = json_decode($body, true);
        } else {
            $object = $this->input->post(); // use POST params
        }

        log_message('error', "Expenses Action API Input: " . var_export($object, true));

        $data = [
            'status'  => 400,
            'message' => 'Invalid request'
        ];

        $token        = isset($object['token']) ? trim($object['token']) : '';
        $expense_id   = isset($object['expense_id']) ? trim($object['expense_id']) : null;
        $functionType = isset($object['function_type']) ? strtolower(trim($object['function_type'])) : '';

        $confirmToken = $this->checkAPI_token_from_header();

        if (!$confirmToken) {
            $data['status']  = 401;
            $data['message'] = 'API key is invalid!';
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        if (!$expense_id) {
            $data['status']  = 422;
            $data['message'] = 'expense_id is required';
            $this->output->set_status_header(422);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        // ---------- DELETE ----------
        if ($functionType === 'delete') {
            $deleted = $this->db->delete('expense_tracker', ['expense_id' => $expense_id]);
            if ($deleted) {
                $data['status']  = 200;
                $data['message'] = 'Expense deleted successfully';
                $this->output->set_status_header(200);
            } else {
                $data['status']  = 404;
                $data['message'] = 'Expense not found or already deleted';
                $this->output->set_status_header(404);
            }
        }

        // ---------- UPDATE ----------
        elseif ($functionType === 'update') {
            $updateData = [];
            if (isset($object['expense_name'])) $updateData['expense_name'] = $object['expense_name'];
            if (isset($object['amount'])) $updateData['amount'] = $object['amount'];
            if (isset($object['date'])) $updateData['date'] = $object['date'];
            if (isset($object['description'])) $updateData['description'] = $object['description'];

            if (!empty($updateData)) {
                $this->db->where('expense_id', $expense_id);
                $updated = $this->db->update('expense_tracker', $updateData);

                if ($updated) {
                    $data['status']  = 200;
                    $data['message'] = 'Expense updated successfully';
                    $this->output->set_status_header(200);
                } else {
                    $data['status']  = 404;
                    $data['message'] = 'Expense not found or update failed';
                    $this->output->set_status_header(404);
                }
            } else {
                $data['status']  = 422;
                $data['message'] = 'No update fields provided';
                $this->output->set_status_header(422);
            }
        } else {
            $data['status']  = 400;
            $data['message'] = 'Invalid function_type. Use update or delete.';
            $this->output->set_status_header(400);
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }
    public function manage_incident_report()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        if (strpos($contentType, 'application/json') !== false) {
            $body   = file_get_contents("php://input");
            $object = json_decode($body, true);
        } else {
            $object = $this->input->post();
        }

        log_message('error', "Manage Incident Report API Input: " . var_export($object, true));

        $data = [
            'status'  => 400,
            'message' => 'Invalid request'
        ];

        $token         = isset($object['token']) ? trim($object['token']) : '';
        $user_id       = isset($object['user_id']) ? trim($object['user_id']) : '';
        $incident_id   = isset($object['incident_id']) ? trim($object['incident_id']) : '';
        $function_type = isset($object['function_type']) ? strtolower(trim($object['function_type'])) : '';

        $confirmToken = $this->checkAPI_token_from_header();

        if (!$confirmToken) {
            $data['message'] = 'API key is invalid!';
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        // --- Validate user ---
        $user = $this->db->get_where('users', ['id' => $user_id])->row();
        if (!$user) {
            $data['status']  = 404;
            $data['message'] = 'User not found';
            $this->output->set_status_header(404);
            echo json_encode($data);
            return;
        }

        $user_role = $user->user_role;

        // --- DELETE FUNCTION ---
        if ($function_type === 'delete') {
            if (in_array($user_role, ['Manager', 'Security'])) {
                $this->db->delete('incident_reports', ['incident_id' => $incident_id]);
                $data['status']  = 200;
                $data['message'] = 'Incident report deleted successfully';
            } else {
                $data['status']  = 403;
                $data['message'] = 'Permission denied to delete incident reports';
            }

            $this->output->set_status_header($data['status']);
            header('Content-Type: application/json');
            echo json_encode($data);
            return;
        }

        // --- UPDATE FUNCTION ---
        if ($function_type === 'update') {
            $updateData = [];

            $incident_type = isset($object['incident_type']) ? trim($object['incident_type']) : null;
            $incident_name = isset($object['incident_name']) ? trim($object['incident_name']) : null;
            $description   = isset($object['description']) ? trim($object['description']) : null;
            $location      = isset($object['location']) ? trim($object['location']) : null;
            $status      = isset($object['status']) ? trim($object['status']) : null;

            if ($incident_type) $updateData['incident_type'] = $incident_type;
            if ($incident_name) $updateData['incident_name'] = $incident_name;
            if ($description)   $updateData['description']   = $description;
            if ($location)      $updateData['location']      = $location;
            if ($status)        $updateData['status']        = $status;

            // --- Handle Emergency Category ---
            if (strtolower($incident_type) === 'emergency') {
                $incident_category = isset($object['incident_category']) ? trim($object['incident_category']) : null;
                $updateData['incident_category'] = $incident_category;
            }

            // === Handle Multiple Image Uploads ===
            $uploaded_files = [];
            if (!empty($_FILES['images']['name'][0])) {
                $filesCount = count($_FILES['images']['name']);
                $uploadPath = './uploads/attachments/';
                if (!is_dir($uploadPath)) mkdir($uploadPath, 0777, true);

                $this->load->library('upload');

                for ($i = 0; $i < $filesCount; $i++) {
                    $_FILES['file']['name']     = $_FILES['images']['name'][$i];
                    $_FILES['file']['type']     = $_FILES['images']['type'][$i];
                    $_FILES['file']['tmp_name'] = $_FILES['images']['tmp_name'][$i];
                    $_FILES['file']['error']    = $_FILES['images']['error'][$i];
                    $_FILES['file']['size']     = $_FILES['images']['size'][$i];

                    $config['upload_path']   = $uploadPath;
                    $config['allowed_types'] = 'jpg|jpeg|png|gif|webp';
                    $config['max_size']      = 4096;
                    $config['encrypt_name']  = TRUE;

                    $this->upload->initialize($config);

                    if ($this->upload->do_upload('file')) {
                        $fileData = $this->upload->data();
                        $uploaded_files[] = base_url('uploads/attachments/' . $fileData['file_name']);
                    }
                }
            }

            // === Merge with existing images if any ===
            if (!empty($uploaded_files)) {
                $existing = $this->db->select('image')->from('incident_reports')->where('incident_id', $incident_id)->get()->row();

                $existingImages = [];
                if ($existing && !empty($existing->image)) {
                    $decoded = json_decode(stripslashes($existing->image), true);
                    if (is_array($decoded)) $existingImages = $decoded;
                }

                $mergedImages = array_merge($existingImages, $uploaded_files);
                $updateData['image'] = json_encode($mergedImages, JSON_UNESCAPED_SLASHES);
            }

            // === Permission: Only Manager or Security can mark as Resolved ===
            if (in_array($user_role, ['Manager', 'Security'])) {
                // $updateData['status'] = 'Resolved';
                $updateData['status'] = $status;
            }
            $message_type=(strtolower($incident_type) === 'emergency')?'Emergency':'Incident';
            $updateData['date_updated'] = date('Y-m-d H:i:s');

            if (!empty($updateData)) {
                $this->db->where('incident_id', $incident_id);
                $this->db->update('incident_reports', $updateData);

                $data['status']  = 200;
                $data['message'] = $message_type . ' report updated successfully';
                $data['updated'] = $updateData;
                $data['message_type'] = $message_type;
            } else {
                $data['status']  = 400;
                $data['message'] = 'No data provided for update';
            }

            $this->output->set_status_header($data['status']);
        }

        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
    }

    public function manage_visitors()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        if (strpos($contentType, 'application/json') !== false) {
            $body   = file_get_contents("php://input");
            $object = json_decode($body, true);
        } else {
            $object = $this->input->post();
        }

        log_message('error', "Manage Visitors API Input: " . var_export($object, true));

        $data = [
            'status'  => 400,
            'message' => 'Invalid request'
        ];

        $token         = isset($object['token']) ? trim($object['token']) : '';
        $user_id       = isset($object['user_id']) ? trim($object['user_id']) : '';
        $visitor_id    = isset($object['visitor_id']) ? trim($object['visitor_id']) : null;
        $function_type = isset($object['function_type']) ? strtolower(trim($object['function_type'])) : '';
        $visitor_type     = isset($object["visitor_type"]) ? trim($object["visitor_type"]) : null;
        $visitor_role     = isset($object["visitor_role"]) ? trim($object["visitor_role"]) : null;
         $access_code     = isset($object["access_code"]) ? trim($object["access_code"]) : null;
         $full_name     = isset($object["full_name"]) ? trim($object["full_name"]) : null;
        $currentDateTime = date('Y-m-d H:i:s');
        $confirmToken = $this->checkAPI_token_from_header();
        $updateData = [];
        if ($confirmToken) {
            // --- validate user_id exists ---
            $user = $this->db->get_where('users', ['id' => $user_id])->row();
            if (!$user) {
                $data['status']  = 200;
                $data['message'] = 'User not found';
                $this->output->set_status_header(200);
                echo json_encode($data);
                return;
            }

            $user_role = strtolower($user->user_role);

            // ---------- DELETE ----------
            if ($function_type === 'delete') {
                if (strpos($user_role, 'manager') !== false || strpos($user_role, 'security') !== false) {
                    $this->db->delete('register_visitors', ['visitor_id' => $visitor_id]);
                    $data['status']  = 200;
                    $data['message'] = 'Visitor deleted successfully';
                } else {
                    $data['status']  = 403;
                    $data['message'] = 'Permission denied to delete visitor records';
                }
                $this->output->set_status_header($data['status']);
                echo json_encode($data);
                return;
            }

            // ---------- UPDATE ----------
            // ---------- UPDATE ----------
            if ($function_type === 'update') {
              
            if(strtolower($visitor_type) === 'checkedout' && strtolower($visitor_role) ==='resident'){

                        $insertData = [
                        'user_id'=> $user_id,
                         'full_name'=> $full_name,
                          'check_date' => $currentDateTime,
                        'date_time' => $currentDateTime,
                        'date_updated' => $currentDateTime,
                         'visitor_type'=> 'resident', 
                         'status'=> 'checkedout',
                         'access_code' => $access_code

                        ];

               $this->db->insert('register_visitors', $insertData);
               $data['status']  = 200;
                    $data['message'] = 'Resident updated successfully';
                    $data['updated'] = $insertData;
                    $this->output->set_status_header($data['status']);
                echo json_encode($data);
                return;
            }else{
               
                // Allow residents to update their own visitor records (check-in/check-out)
                if (strpos($user_role, 'manager') !== false || strpos($user_role, 'security') !== false || strpos($user_role, 'user') !== false) {
                    
                    // Accept both checkout_date and check_out_date field names
                    if (isset($object['checkout_date'])) {
                        $updateData['checkout_date'] = $object['checkout_date'];
                    }
                    if (isset($object['check_out_date'])) {
                        $updateData['checkout_date'] = $object['check_out_date'];
                    }
                    if (isset($object['check_date'])) {
                        $updateData['check_date'] = $object['check_date'];
                    }

                    if (isset($object['status'])) {
                        $updateData['status'] = $object['status'];
                    }

                    if (!empty($updateData)) {
                        $updateData['date_updated'] = date('Y-m-d H:i:s'); // auto-update timestamp
                        $this->db->where('visitor_id', $visitor_id);
                        $this->db->update('register_visitors', $updateData);

                        $data['status']  = 200;
                        $data['message'] = 'Visitor updated successfully';
                        $data['updated'] = $updateData;
                    } else {
                        $data['status']  = 422;
                        $data['message'] = 'No update fields provided. Required: checkout_date, check_out_date, check_date, or status';
                    }
                } else {
                    $data['status']  = 403;
                    $data['message'] = 'Permission denied to update visitor records';
                }
                $this->output->set_status_header($data['status']);
                echo json_encode($data);
                return;
            }
            // end of UPDATE

            }
            // ---------- GET ----------
            if ($visitor_id) {
                $where = ['visitor_id' => $visitor_id];
                if ($user_role == 'user') {
                    $where['user_id'] = $user_id;
                }

                $visitor = $this->db->get_where('register_visitors', $where)->row_array();
                if ($visitor) {
                    $data['status']   = 200;
                    $data['message']  = 'Visitor retrieved successfully';
                    $data['visitor']  = $visitor;
                    $this->output->set_status_header(200);
                } else {
                    $data['status']  = 200;
                    $data['message'] = 'success';
                    $data['visitor']  = [];
                    $this->output->set_status_header(200);
                }
            } else {
                // if ($user_role != 'user') {
                //     $visitors = $this->db->order_by('visitor_id', 'DESC')->get('register_visitors')->result_array();
                // } else {
                //     $visitors = $this->db->order_by('visitor_id', 'DESC')->get_where('register_visitors', ['user_id' => $user_id])->result_array();
                // }
                if (strtolower($user_role) != 'user') {
                    // Admins, Managers, etc. — see all visitors
                    $this->db->select('v.*, u.fullname, u.email')
                        ->from('register_visitors v')
                        ->join('users u', 'v.user_id = u.id', 'left')
                        ->order_by('v.visitor_id', 'DESC');
                    $visitors = $this->db->get()->result_array();
                } else {
                    // Regular user — only see their own visitor records
                    $this->db->select('v.*, u.fullname, u.email')
                        ->from('register_visitors v')
                        ->join('users u', 'v.user_id = u.id', 'left')
                        ->where('v.user_id', $user_id)
                        ->order_by('v.visitor_id', 'DESC');
                    $visitors = $this->db->get()->result_array();
                }

                if (!empty($visitors)) {
                    $data['status']    = 200;
                    $data['message']   = 'Visitors retrieved successfully';
                    $data['visitors']  = $visitors;
                    $this->output->set_status_header(200);
                } else {
                    $data['status']  = 200;
                    $data['message'] = 'success';
                    $data['visitors'] = [];
                }
            }
        } else {
            $data['message'] = 'API key is invalid!';
            $this->output->set_status_header(401);
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    
    }

    public function manage_emergency_contact()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->post();

        log_message('error', "Manage Emergency Contact Input: " . var_export($object, true));

        $data = [
            'status'  => 400,
            'message' => 'Invalid request'
        ];

        $token = isset($object['token']) ? trim($object['token']) : '';
        $confirmToken = $this->checkAPI_token_from_header();

        if ($confirmToken) {
            $function_type = isset($object["function_type"]) ? strtolower(trim($object["function_type"])) : '';
            $contact_id    = isset($object["id"]) ? intval($object["id"]) : 0;

            if (!$contact_id) {
                $data['message'] = 'Contact ID is required!';
                $this->output->set_status_header(400);
            } else {
                $contact = $this->db->get_where('emergency_contacts', ['id' => $contact_id, 'is_deleted' => 0])->row();

                if (!$contact) {
                    $data['message'] = 'Emergency contact not found';
                    $this->output->set_status_header(404);
                } else {
                    if ($function_type === 'update') {
                        $updateData = [];

                        if (isset($object["role"])) $updateData['role'] = trim($object["role"]);
                        if (isset($object["phone_number"])) $updateData['phone_number'] = trim($object["phone_number"]);

                        if (empty($updateData)) {
                            $data['message'] = 'Nothing to update';
                            $this->output->set_status_header(400);
                        } else {
                            $this->db->where('id', $contact_id);
                            $this->db->update('emergency_contacts', $updateData);

                            $data['status']  = 200;
                            $data['message'] = 'Emergency Contact Updated Successfully';
                            $data['updated_contact'] = array_merge(['id' => $contact_id], $updateData);
                            $this->output->set_status_header(200);
                        }
                    } elseif ($function_type === 'delete') {
                        $this->db->where('id', $contact_id);
                        $this->db->update('emergency_contacts', ['is_deleted' => 1]);

                        $data['status']  = 200;
                        $data['message'] = 'Emergency Contact Deleted Successfully';
                        $this->output->set_status_header(200);
                    } else {
                        $data['message'] = 'Invalid function type. Use "update" or "delete".';
                        $this->output->set_status_header(400);
                    }
                }
            }
        } else {
            $data['message'] = 'Invalid API token';
            $this->output->set_status_header(401);
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }
    public function get_emergency_contacts()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->post();

        log_message('error', "Get Emergency Contacts Input: " . var_export($object, true));

        $data = [
            'status'  => 400,
            'message' => 'Invalid request'
        ];

        $token = isset($object['token']) ? trim($object['token']) : '';
        $confirmToken = $this->checkAPI_token_from_header();

        if ($confirmToken) {
            $contact_id = isset($object['id']) ? intval($object['id']) : 0;

            if ($contact_id > 0) {
                // Get single contact
                $contact = $this->db
                    ->get_where('emergency_contacts', ['id' => $contact_id, 'is_deleted' => 0])
                    ->row();

                if ($contact) {
                    $data['status']  = 200;
                    $data['message'] = 'Emergency Contact Retrieved Successfully';
                    $data['contact'] = $contact;
                    $this->output->set_status_header(200);
                } else {
                     $data['status']  = 200;
                    $data['message'] = 'Success';
                     $data['contact']  = [];
                    $this->output->set_status_header(200);
                }
            } else {
                // Get all active contacts
                $contacts = $this->db
                    ->where('is_deleted', 0)
                    ->order_by('id', 'DESC')
                    ->get('emergency_contacts')
                    ->result();

                $data['status']   = 200;
                $data['message']  = 'Emergency Contacts Retrieved Successfully';
                $data['contacts'] = $contacts;
                $this->output->set_status_header(200);
            }
        } else {
            $data['message'] = 'Invalid API token';
            $this->output->set_status_header(401);
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }
    public function create_helpcentre_faq()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->post();

        log_message('error', "Create HelpCentre FAQ Input: " . var_export($object, true));

        $data = [
            'status'  => 400,
            'message' => 'Invalid request'
        ];

        $token = isset($object['token']) ? trim($object['token']) : '';
        $confirmToken = $this->checkAPI_token_from_header();

        if ($confirmToken) {
            $question = isset($object["question"]) ? trim($object["question"]) : null;
            $answer   = isset($object["answer"]) ? trim($object["answer"]) : null;

            if (!$question || !$answer) {
                $data['message'] = 'Question and Answer are required!';
                $this->output->set_status_header(400);
            } else {
                $insertData = [
                    'question' => $question,
                    'answer'   => $answer
                ];

                $this->db->insert('HelpCentreFAQ', $insertData);
                $insert_id = $this->db->insert_id();

                if ($insert_id) {
                    $data['status']  = 200;
                    $data['message'] = 'FAQ Created Successfully';
                    $data['faq']     = array_merge(['id' => $insert_id], $insertData);
                    $this->output->set_status_header(200);
                } else {
                    $data['message'] = 'Failed to create FAQ';
                    $this->output->set_status_header(400);
                }
            }
        } else {
            $data['message'] = 'Invalid API token';
            $this->output->set_status_header(401);
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }
    public function manage_helpcentre_faq()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->post();

        log_message('error', "Manage HelpCentre FAQ Input: " . var_export($object, true));

        $data = [
            'status'  => 400,
            'message' => 'Invalid request'
        ];

        $token = isset($object['token']) ? trim($object['token']) : '';
        $confirmToken = $this->checkAPI_token_from_header();

        if ($confirmToken) {
            $function_type = isset($object["function_type"]) ? strtolower(trim($object["function_type"])) : '';
            $faq_id        = isset($object["id"]) ? intval($object["id"]) : 0;

            if (!$faq_id) {
                $data['message'] = 'FAQ ID is required!';
                $this->output->set_status_header(400);
            } else {
                $faq = $this->db->get_where('HelpCentreFAQ', ['id' => $faq_id])->row();

                if (!$faq) {
                    $data['message'] = 'FAQ not found';
                    $this->output->set_status_header(404);
                } else {
                    if ($function_type === 'update') {
                        $updateData = [];

                        if (isset($object["question"])) $updateData['question'] = trim($object["question"]);
                        if (isset($object["answer"])) $updateData['answer'] = trim($object["answer"]);

                        if (empty($updateData)) {
                            $data['message'] = 'Nothing to update';
                            $this->output->set_status_header(400);
                        } else {
                            $this->db->where('id', $faq_id);
                            $this->db->update('HelpCentreFAQ', $updateData);

                            $data['status']  = 200;
                            $data['message'] = 'FAQ Updated Successfully';
                            $data['updated_faq'] = array_merge(['id' => $faq_id], $updateData);
                            $this->output->set_status_header(200);
                        }
                    } elseif ($function_type === 'delete') {
                        $this->db->where('id', $faq_id);
                        $this->db->delete('HelpCentreFAQ');

                        $data['status']  = 200;
                        $data['message'] = 'FAQ Deleted Successfully';
                        $this->output->set_status_header(200);
                    } else {
                        $data['message'] = 'Invalid function type. Use "update" or "delete".';
                        $this->output->set_status_header(400);
                    }
                }
            }
        } else {
            $data['message'] = 'Invalid API token';
            $this->output->set_status_header(401);
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }
    public function get_helpcentre_faq()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->post();

        log_message('error', "Get HelpCentre FAQ Input: " . var_export($object, true));

        $data = [
            'status'  => 400,
            'message' => 'Invalid request'
        ];

        $token = isset($object['token']) ? trim($object['token']) : '';
        $confirmToken = $this->checkAPI_token_from_header();

        if ($confirmToken) {
            $faq_id = isset($object['id']) ? intval($object['id']) : 0;

            if ($faq_id > 0) {
                // Get one FAQ
                $faq = $this->db->get_where('HelpCentreFAQ', ['id' => $faq_id])->row();

                if ($faq) {
                    $data['status']  = 200;
                    $data['message'] = 'FAQ Retrieved Successfully';
                    $data['faq']     = $faq;
                    $this->output->set_status_header(200);
                } else {
                    $data['status']  = 200;
                    $data['message'] = 'FAQ not found';
                    $data['faq']     = [];
                    $this->output->set_status_header(200);
                }
            } else {
                // Get all FAQs
                $faqs = $this->db
                    ->order_by('id', 'DESC')
                    ->get('HelpCentreFAQ')
                    ->result();

                $data['status']  = 200;
                $data['message'] = 'FAQs Retrieved Successfully';
                $data['faqs']    = $faqs;
                $this->output->set_status_header(200);
            }
        } else {
            $data['message'] = 'Invalid API token';
            $this->output->set_status_header(401);
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }
    // public function create_staff()
    // {
    //     $contentType = $this->input->server('CONTENT_TYPE');
    //     $object = (strpos($contentType, 'application/json') !== false)
    //         ? json_decode(file_get_contents("php://input"), true)
    //         : $this->input->post();

    //     log_message('error', "Create Staff Input: " . var_export($object, true));

    //     $data = [
    //         'status'  => 400,
    //         'message' => 'Invalid request'
    //     ];

    //     $token = isset($object['token']) ? trim($object['token']) : '';
    //     $confirmToken = $this->checkAPI_token_from_header();

    //     if ($confirmToken) {
    //         $staff_name         = isset($object["staff_name"]) ? trim($object["staff_name"]) : null;
    //         $position_role      = isset($object["position_role"]) ? trim($object["position_role"]) : null;
    //         $phone_number       = isset($object["phone_number"]) ? trim($object["phone_number"]) : null;
    //         $email              = isset($object["email"]) ? trim($object["email"]) : null;
    //         $gender             = isset($object["gender"]) ? trim($object["gender"]) : null;
    //         $date_of_employment = isset($object["date_of_employment"]) ? trim($object["date_of_employment"]) : null;

    //         if (!$staff_name || !$email) {
    //             $data['message'] = 'Staff name and email are required!';
    //             $this->output->set_status_header(400);
    //         } else {
    //             // Handle optional staff photo upload
    //             $staff_photo = null;
    //             if (!empty($_FILES['staff_photo']['name'])) {
    //                 $uploaded = $this->uploadfiles('staff_photo', 'staff', 'staff_photos');
    //                 log_message('error', var_export($uploaded, true));
    //                 if ($uploaded) {
    //                     $staff_photo = site_url($uploaded[0]->attachment_file);
    //                 }
    //             }

    //             $insertData = [
    //                 'staff_name'         => $staff_name,
    //                 'position_role'      => $position_role,
    //                 'phone_number'       => $phone_number,
    //                 'email'              => $email,
    //                 'gender'             => $gender,
    //                 'date_of_employment' => $date_of_employment,
    //                 'staff_photo'        => $staff_photo
    //             ];

    //             $this->db->insert('staff', $insertData);
    //             $insert_id = $this->db->insert_id();

    //             if ($insert_id) {
    //                 $data['status']  = 200;
    //                 $data['message'] = 'Staff Created Successfully';
    //                 $data['staff']   = array_merge(['id' => $insert_id], $insertData);
    //                 $this->output->set_status_header(200);
    //             } else {
    //                 $data['message'] = 'Failed to create staff';
    //                 $this->output->set_status_header(400);
    //             }
    //         }
    //     } else {
    //         $data['message'] = 'Invalid API token';
    //         $this->output->set_status_header(401);
    //     }

    //     header('Content-Type: application/json');
    //     echo json_encode($data);
    // }
    public function create_staff()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->post();

        log_message('error', "Create Staff Input: " . var_export($object, true));

        // Validate request
        if (!$object) {
            $this->output->set_status_header(400);
            echo json_encode(['status' => 400, 'message' => 'Invalid request']);
            return;
        }

        // Validate token
        $token = trim($object['token'] ?? '');
        if (!$this->checkAPI_token_from_header()) {
            $this->output->set_status_header(401);
            echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
            return;
        }

        //----------------------------------------------------
        // REQUIRED FIELDS
        //----------------------------------------------------
        $staff_name   = trim($object["staff_name"] ?? '');
        $position     = trim($object["position_role"] ?? '');
        $phone_number = trim($object["phone_number"] ?? '');
        $email        = trim($object["email"] ?? '');
        $gender       = trim($object["gender"] ?? '');
        $date_emp     = trim($object["date_of_employment"] ?? '');
        //$password     = trim($object["password"] ?? '');  // optional
        $staff_photo = null;
        if (!empty($_FILES['staff_photo']['name'])) {
            $uploaded = $this->uploadfiles('staff_photo', 'staff', 'staff_photos');
            if ($uploaded) {
                $staff_photo = site_url($uploaded[0]->attachment_file);
            }
        }

        if (empty($staff_name) || empty($email)) {
            $this->output->set_status_header(400);
            echo json_encode(['status' => 400, 'message' => 'Staff name and email are required!']);
            return;
        }

        //----------------------------------------------------
        // CREATE USER FIRST (Ion Auth)
        //----------------------------------------------------

        // auto-generate password if none provided
        if (empty($password)) {
            $password = 'password';
        }

        // Prevent duplicate user
        if ($this->ion_auth->email_check($email)) {
            $this->output->set_status_header(409);
            echo json_encode(['status' => 409, 'message' => 'Email already exists!']);
            return;
        }

        // Split name
        $nameParts  = explode(" ", $staff_name);
        $first_name = $nameParts[0];
        $last_name  = $nameParts[1] ?? '';
        $accessCode = $this->generateAccessCode($email);
        $additional_data = [
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'fullname'   => $staff_name,
            'user_role'  => $position,
            'move_in_date'  => $date_emp,
            'phone'      => $phone_number,
            'profile_status' => 'Active',
            'image'      => $staff_photo,
            'userAccessCode'      => $accessCode
        ];

        // staff group
        $group = ['2'];

        // Register user
        $user_id = $this->ion_auth->register2($email, $password, $email, $additional_data, $group);

        if (!$user_id) {
            $this->output->set_status_header(400);
            echo json_encode(['status' => 400, 'message' => 'Failed to create user']);
            return;
        }

        //----------------------------------------------------
        // ONLY AFTER USER IS CREATED → INSERT STAFF
        //----------------------------------------------------

        // upload photo (optional)


        $staffInsert = [
            'user_id'         => $user_id,
            'staff_name'         => $staff_name,
            'position_role'      => $position,
            'phone_number'       => $phone_number,
            'email'              => $email,
            'gender'             => $gender,
            'date_of_employment' => $date_emp,
            'staff_photo'        => $staff_photo,
        ];

        $this->db->insert('staff', $staffInsert);
        $staff_id = $this->db->insert_id();

        if (!$staff_id) {
            $this->output->set_status_header(500);
            echo json_encode([
                'status' => 500,
                'message' => 'User created but failed to create staff'
            ]);
            return;
        }
        $this->sendStaffMail($email, "Account Activation Request", $staff_name, $password, $user_id);
        //----------------------------------------------------
        // SUCCESS RESPONSE
        //----------------------------------------------------
        $this->output->set_status_header(200);

        echo json_encode([
            'status'  => 200,
            'message' => 'User & Staff created successfully',
            'user'    => [
                'user_id'   => $user_id,
                'email'     => $email,
                'fullname'  => $staff_name,
                'position'  => $position,
                'phone'     => $phone_number
            ],
            'staff'   => array_merge(['id' => $staff_id], $staffInsert)
        ]);
    }

    public function get_staff()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->post();

        log_message('error', "Get Staff Input: " . var_export($object, true));

        $data = [
            'status'  => 400,
            'message' => 'Invalid request'
        ];

        $token = isset($object['token']) ? trim($object['token']) : '';
        $confirmToken = $this->checkAPI_token_from_header();

        if ($confirmToken) {
            $staff_id = isset($object['id']) ? intval($object['id']) : 0;

            if ($staff_id > 0) {
                // Get single staff
                $staff = $this->db->get_where('staff', ['id' => $staff_id])->row();

                if ($staff) {
                    $data['status']  = 200;
                    $data['message'] = 'Staff Retrieved Successfully';
                    $data['staff']   = $staff;
                    $this->output->set_status_header(200);
                } else {
                    $data['status']  = 200;
                    $data['message'] = 'success';
                    $data['staff']     = [];
                    $this->output->set_status_header(200);
                }
            } else {
                // Get all staff
                $staff = $this->db
                    ->order_by('id', 'DESC')
                    ->get('staff')
                    ->result();

                $data['status']  = 200;
                $data['message'] = 'Staff Retrieved Successfully';
                $data['staff']   = $staff;
                $this->output->set_status_header(200);
            }
        } else {
            $data['message'] = 'Invalid API token';
            $this->output->set_status_header(401);
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }
    public function manage_staff()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        $object = (strpos($contentType, 'application/json') !== false)
            ? json_decode(file_get_contents("php://input"), true)
            : $this->input->post();

        log_message('error', "Manage Staff Input: " . var_export($object, true));

        $data = [
            'status'  => 400,
            'message' => 'Invalid request'
        ];

        $token = isset($object['token']) ? trim($object['token']) : '';
        $confirmToken = $this->checkAPI_token_from_header();

        if ($confirmToken) {
            $function_type = isset($object["function_type"]) ? strtolower(trim($object["function_type"])) : '';
            $staff_id      = isset($object["id"]) ? intval($object["id"]) : 0;

            if (!$staff_id) {
                $data['message'] = 'Staff ID is required!';
                $this->output->set_status_header(400);
            } else {
                $staff = $this->db->get_where('staff', ['id' => $staff_id])->row();

                if (!$staff) {
                    $data['message'] = 'Staff not found';
                    $this->output->set_status_header(404);
                } else {
                    if ($function_type === 'update') {
                        $updateData = [];

                        // ✅ Include staff_status in updatable fields
                        $fields = [
                            'staff_name',
                            'position_role',
                            'phone_number',
                            'email',
                            'gender',
                            'date_of_employment',
                            'staff_status'
                        ];

                        foreach ($fields as $field) {
                            if (isset($object[$field])) {
                                $updateData[$field] = trim($object[$field]);
                            }
                        }

                        // ✅ Handle optional new staff photo upload
                        if (!empty($_FILES['staff_photo']['name'])) {
                            $uploaded = $this->uploadfiles('staff_photo', 'staff', 'staff_photos');
                            if ($uploaded) {
                                $updateData['staff_photo'] = site_url($uploaded[0]->attachment_file);
                            }
                        }

                        if (empty($updateData)) {
                            $data['message'] = 'Nothing to update';
                            $this->output->set_status_header(400);
                        } else {
                            $updateData['updated_at'] = date('Y-m-d H:i:s');
                            $this->db->where('id', $staff_id);
                            $this->db->update('staff', $updateData);

                            $data['status']  = 200;
                            $data['message'] = 'Staff Updated Successfully';
                            $data['updated_staff'] = array_merge(['id' => $staff_id], $updateData);
                            $this->output->set_status_header(200);
                        }
                    } elseif ($function_type === 'delete') {
                        // First get the user_id from staff table
                        $staff = $this->db->select('user_id')
                            ->from('staff')
                            ->where('id', $staff_id)
                            ->get()
                            ->row();

                        if (!$staff) {
                            return [
                                "status"  => false,
                                "message" => "Staff record not found"
                            ];
                        }

                        $user_id = $staff->user_id;

                        // Deactivate the user before deleting staff
                        $this->deactivate_staff($user_id);

                        // Now delete staff record
                        $this->db->where('id', $staff_id);
                        $this->db->delete('staff');

                        $data['status']  = 200;
                        $data['message'] = 'Staff Deleted Successfully and user deactivated successfully';
                        $this->output->set_status_header(200);
                    } else {
                        $data['message'] = 'Invalid function type. Use "update" or "delete".';
                        $this->output->set_status_header(400);
                    }
                }
            }
        } else {
            $data['message'] = 'Invalid API token';
            $this->output->set_status_header(401);
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }

    public function create_shift()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        if (strpos($contentType, 'application/json') !== false) {
            $body   = file_get_contents("php://input");
            $object = json_decode($body, true);
        } else {
            $object = $this->input->post();
        }

        log_message('error', "Shift API Input: " . var_export($object, true));

        $data = [
            'status'  => 400,
            'message' => 'Invalid request'
        ];

        $token = isset($object['token']) ? trim($object['token']) : '';
        $confirmToken = $this->checkAPI_token_from_header();

        if ($confirmToken) {
            $shift_id    = isset($object['id']) ? trim($object['id']) : null;
            $staff_id    = isset($object['staff_id']) ? trim($object['staff_id']) : null;
            $shift_type  = isset($object['shift_type']) ? trim($object['shift_type']) : '';
            $staff_name  = isset($object['staff_name']) ? trim($object['staff_name']) : '';
            $shift_date  = isset($object['shift_date']) ? trim($object['shift_date']) : '';
            $start_time  = isset($object['start_time']) ? trim($object['start_time']) : '';
            $end_time    = isset($object['end_time']) ? trim($object['end_time']) : '';

            $insertData = [
                'staff_id'   => $staff_id,
                'shift_type' => $shift_type,
                'staff_name' => $staff_name,
                'shift_date' => $shift_date,
                'start_time' => $start_time,
                'end_time'   => $end_time,
            ];

            if ($shift_id) {
                // Update existing shift
                $existing = $this->db->get_where('Shifts', ['id' => $shift_id])->row();
                if ($existing) {
                    $this->db->where('id', $shift_id);
                    $this->db->update('Shifts', $insertData);
                    $message = 'Shift updated successfully';
                } else {
                    $this->db->insert('Shifts', $insertData);
                    $shift_id = $this->db->insert_id();
                    $message = 'Shift created successfully';
                }
            } else {
                // Create new shift
                $this->db->insert('Shifts', $insertData);
                $shift_id = $this->db->insert_id();
                $message = 'Shift created successfully';
            }

            if ($shift_id) {
                $data['status']  = 200;
                $data['message'] = $message;
                $data['shift']   = array_merge(['id' => $shift_id], $insertData);
                $this->output->set_status_header(200);
            } else {
                $data['status']  = 400;
                $data['message'] = 'Shift creation failed';
                $this->output->set_status_header(400);
            }
        } else {
            $data['message'] = 'Invalid API token';
            $this->output->set_status_header(403);
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }
    public function get_shifts()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        if (strpos($contentType, 'application/json') !== false) {
            $body   = file_get_contents("php://input");
            $object = json_decode($body, true);
        } else {
            $object = $this->input->post();
        }

        $token = isset($object['token']) ? trim($object['token']) : '';
        $confirmToken = $this->checkAPI_token_from_header();

        if (!$confirmToken) {
            $this->output->set_status_header(403);
            echo json_encode(['status' => 403, 'message' => 'Invalid API token']);
            return;
        }

        // support fetching by id
        $id         = isset($object['id']) ? intval($object['id']) : 0;
        $staff_id   = isset($object['staff_id']) ? trim($object['staff_id']) : null;
        $shift_date = isset($object['shift_date']) ? trim($object['shift_date']) : null;
        $shift_type = isset($object['shift_type']) ? trim($object['shift_type']) : null;

        if ($id > 0) {
            // get single shift by id
            $shift = $this->db->get_where('Shifts', ['id' => $id])->row();
            if ($shift) {
                $response = [
                    'status' => 200,
                    'message' => 'Shift retrieved successfully',
                    'shift' => $shift
                ];
                $this->output->set_status_header(200);
            } else {
                $response = [
                    'status' => 404,
                    'message' => 'Shift not found'
                ];
                $this->output->set_status_header(404);
            }
        } else {
            // list / filter shifts
            $this->db->from('Shifts');
            if ($staff_id) $this->db->where('staff_id', $staff_id);
            if ($shift_date) $this->db->where('shift_date', $shift_date);
            if ($shift_type) $this->db->where('shift_type', $shift_type);
            $this->db->order_by('shift_date', 'DESC');
            $result = $this->db->get()->result();

            if (!empty($result)) {
                $response = [
                    'status' => 200,
                    'message' => 'Shifts retrieved successfully',
                    'data' => $result
                ];
                $this->output->set_status_header(200);
            } else {
                $response = [
                    'status' => 404,
                    'message' => 'No shifts found'
                ];
                $this->output->set_status_header(404);
            }
        }

        header('Content-Type: application/json');
        echo json_encode($response);
    }


    public function manage_shift()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        if (strpos($contentType, 'application/json') !== false) {
            $body   = file_get_contents("php://input");
            $object = json_decode($body, true);
        } else {
            $object = $this->input->post();
        }
        log_message('error', "Manage Shift Input: " . var_export($object, true));
        $token = isset($object['token']) ? trim($object['token']) : '';
        $confirmToken = $this->checkAPI_token_from_header();

        if (!$confirmToken) {
            $this->output->set_status_header(403);
            echo json_encode(['status' => 403, 'message' => 'Invalid API token']);
            return;
        }

        $shift_id    = isset($object['id']) ? trim($object['id']) : null;
        $start_time    = isset($object['start_time']) ? trim($object['start_time']) : null;
         $end_time    = isset($object['end_time']) ? trim($object['end_time']) : null;
        $action_type = isset($object['action_type']) ? strtolower(trim($object['action_type'])) : '';

        if (!$shift_id) {
            echo json_encode(['status' => 400, 'message' => 'Shift ID required']);
            return;
        }

        if ($action_type === 'delete') {
            $this->db->where('id', $shift_id);
            $this->db->delete('Shifts');
            $response = ['status' => 200, 'message' => 'Shift deleted successfully'];
        } elseif ($action_type === 'update') {

            // build updateData dynamically only for provided fields
            $updateData = [];
            $updatableFields = [
                'shift_type',
                'shift_date',
                'shift_end_date',
                'check_in',
                'check_out',
                'start_time',
                'end_time',
                'shift_status',
                'shift_success', // ✅ newly added field
                'staff_id',
                'staff_name'
            ];

            foreach ($updatableFields as $f) {
                if (array_key_exists($f, $object)) {
                    $updateData[$f] = is_string($object[$f]) ? trim($object[$f]) : $object[$f];
                }
            }

            if (empty($updateData)) {
                $response = ['status' => 400, 'message' => 'Nothing to update'];
            } else {
                if ($updateData['check_in'] !== 'No' && isset($updateData['check_in'])) {
                    // $updateData['start_time'] = date('H:i:s');
                       //  $updateData['start_time'] =$start_time;

                    $updateData['shift_status'] = 'In Progress';
                }
                if ($updateData['check_out'] !== 'No' && isset($updateData['check_out'])) {
                    //$updateData['end_time'] = date('H:i:s');
                    //$updateData['end_time'] = $end_time;
                    $updateData['shift_status'] = 'Completed';
                }
                $updateData['updated_at'] = date('Y-m-d H:i:s');
                $this->db->where('id', $shift_id);
                $this->db->update('Shifts', $updateData);

                $response = [
                    'status' => 200,
                    'message' => 'Shift updated successfully',
                    'updated_shift' => array_merge(['id' => $shift_id], $updateData)
                ];
            }
        } else {
            $response = ['status' => 400, 'message' => 'Invalid action_type. Use update or delete'];
        }

        header('Content-Type: application/json');
        echo json_encode($response);
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
        $confirmToken = $this->checkAPI_token_from_header();
        if (!$confirmToken) {
            $this->output->set_status_header(403);
            echo json_encode(['status' => 403, 'message' => 'Invalid API token']);
            return;
        }

        $jwt_user = $this->checkJWT();
        if (!$jwt_user) {
            return $this->jwtErrorResponse();
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
        if (!empty($_FILES['image']['name'])) {
            $uploadPath = './uploads/announcements/';
            if (!is_dir($uploadPath)) mkdir($uploadPath, 0777, true);

            $config['upload_path']   = $uploadPath;
            $config['allowed_types'] = 'jpg|jpeg|png|gif|webp';
            $config['max_size']      = 4096;
            $config['encrypt_name']  = TRUE;

            $this->load->library('upload', $config);

            if ($this->upload->do_upload('image')) {
                $fileData = $this->upload->data();
                $imag = base_url('uploads/announcements/' . $fileData['file_name']);
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
        $confirmToken = $this->checkAPI_token_from_header();
        if (!$confirmToken) {
            $this->output->set_status_header(403);
            echo json_encode(['status' => 403, 'message' => 'Invalid API token']);
            return;
        }

        $jwt_user = $this->checkJWT();
        if (!$jwt_user) {
            return $this->jwtErrorResponse();
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
            if (!empty($_FILES['image']['name'])) {
                $uploadPath = './uploads/announcements/';
                if (!is_dir($uploadPath)) mkdir($uploadPath, 0777, true);

                $config['upload_path']   = $uploadPath;
                $config['allowed_types'] = 'jpg|jpeg|png|gif|webp';
                $config['max_size']      = 4096;
                $config['encrypt_name']  = TRUE;

                $this->load->library('upload', $config);

                if ($this->upload->do_upload('image')) {
                    $fileData = $this->upload->data();
                    $update_data['imag'] = base_url('uploads/announcements/' . $fileData['file_name']);
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
        $subject     = 'New Contact Form Message from ' . $full_name;
        $any_sent    = false;

        foreach ($admins as $admin) {
            $data = [
                'subject_title' => $subject,
                'subject_name'  => $admin->fullname,
                'msg_body'      => "
                    <p>You have received a new message via the Alumni Portal contact form.</p>
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
            $this->email->from('jacknelsonxxx@gmail.com', 'Alumni Portal');
            $this->email->reply_to($email, $full_name);
            $this->email->to($admin->email);
            $this->email->bcc('jacknelsonxxx@gmail.com');
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
        $confirmToken = $this->checkAPI_token_from_header();
        if (!$confirmToken) {
            $this->output->set_status_header(403);
            echo json_encode(['status' => 403, 'message' => 'Invalid API token']);
            return;
        }

        $jwt_user = $this->checkJWT();
        if (!$jwt_user) return $this->jwtErrorResponse();

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
        $confirmToken = $this->checkAPI_token_from_header();
        if (!$confirmToken) {
            $this->output->set_status_header(403);
            echo json_encode(['status' => 403, 'message' => 'Invalid API token']);
            return;
        }

        $jwt_user = $this->checkJWT();
        if (!$jwt_user) return $this->jwtErrorResponse();

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
        if (!$this->checkAPI_token_from_header()) {
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
            return;
        }

        // --- JWT ---
        $jwtData = $this->checkJWT();
        if (!$jwtData) {
            $this->jwtErrorResponse();
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


    // public function get_users()
    // {
    //     $object = json_decode(file_get_contents("php://input"), true);
    //     $token  = isset($object['token']) ? trim($object['token']) : '';
    //     $user_id = isset($object['user_id']) ? trim($object['user_id']) : null;

    //     $confirmToken = $this->checkAPI_token_from_header();

    //     if (!$confirmToken) {
    //         $this->output->set_status_header(401);
    //         echo json_encode(['status' => 401, 'message' => 'Invalid API Token']);
    //         return;
    //     }

    //     // Validate admin
    //     // $admin = $this->db->get_where('users', ['id' => $confirmToken->user_id])->row();
    //     // if (!$admin || strtolower($admin->user_role) != 'admin') {
    //     //     $this->output->set_status_header(403);
    //     //     echo json_encode(['status' => 403, 'message' => 'Access denied']);
    //     //     return;
    //     // }

    //     if ($user_id) {
    //         $user = $this->db->get_where('users', ['id' => $user_id])->row_array();
    //         if ($user) {
    //             echo json_encode(['status' => 200, 'user' => $user]);
    //         } else {
    //             echo json_encode(['status' => 404, 'message' => 'User not found']);
    //         }
    //     } else {
    //         $users = $this->db->order_by('id', 'DESC')->get('users')->result_array();
    //         echo json_encode(['status' => 200, 'users' => $users]);
    //     }
    // }

    // public function get_users()
    // {
    //     $object = json_decode(file_get_contents("php://input"), true);
    //     $token  = isset($object['token']) ? trim($object['token']) : '';
    //     $user_id = isset($object['user_id']) ? trim($object['user_id']) : null;
    //   //  $userAccessCode = isset($object['userAccessCode']) ? trim($object['userAccessCode']) : null;
    //     // New optional filters
    //     $user_role = isset($object['user_role']) ? trim($object['user_role']) : null;
    //     //$address   = isset($object['address']) ? trim($object['address']) : null;

    //     $confirmToken = $this->checkAPI_token_from_header();

	//         if (!$confirmToken) {
	//             $this->output->set_status_header(401);
	//             echo json_encode(['status' => 401, 'message' => 'Invalid API Token']);
	//             return;
	//         }

	//         $jwtData = $this->checkJWT();
	//         if (!$jwtData) {
	//             $this->jwtErrorResponse();
	//             return;
	//         }

	//         $jwt_user_id   = (int)$jwtData->user_id;
	//         $jwt_user_role = strtolower($jwtData->user_role);

	//         // Alumni can only fetch their own record
	//         if (stripos($jwt_user_role, 'alumni') !== FALSE) {
	//             $user = $this->db->get_where('users', array('id' => $jwt_user_id))->row_array();
	//             if ($user) {
	//                 $user['user_id'] = $jwt_user_id;
	//                 echo json_encode(array('status' => 200, 'user' => $user));
	//             } else {
	//                 echo json_encode(array('status' => 404, 'message' => 'User not found'));
	//             }
	//             return;
	//         }

	//         if ($user_id) {
	//             $user = $this->db->get_where('users', ['id' => $user_id])->row_array();
	//             if ($user) {
	//                 $user['user_id'] = $user_id;
    //             echo json_encode(['status' => 200, 'user' => $user]);
    //         } else {
    //             echo json_encode(['status' => 404, 'message' => 'User not found']);
    //         }
    //     }
    //     else {

    //         // Start query
    //         $this->db->from('users');

    //         // If user_role was passed
    //         if (!empty($user_role)) {
    //             $this->db->where('LOWER(user_role)', strtolower($user_role));
    //         }

    //         // // If address was passed
    //         // if (!empty($address)) {
    //         //     $this->db->like('address', $address);
    //         // }

    //         $this->db->order_by('id', 'DESC');
    //         $users = $this->db->get()->result_array();

    //         echo json_encode(['status' => 200, 'users' => $users]);
    //     }
    // }
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
         user_profiles.field_visibility
         	
    ');
    $this->db->from('users');
    $this->db->join('user_profiles', 'user_profiles.user_id = users.id', 'left');

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
        ];

        // Remove profile fields from main row
        unset(
            $row['linkedin'],
            $row['twitter'],
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
              $row['field_visibility']
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
    // public function update_user_account()
    // {
    //      $object = json_decode(file_get_contents("php://input"), true);
    //     $token   = isset($object['token']) ? trim($object['token']) : '';
    //     $user_id = isset($object['user_id']) ? trim($object['user_id']) : '';
    //     log_message('error', "Update User Account Input: " . var_export($token, true));
    // log_message('error', "Update User Account Input: " . var_export($object, true));
    //     // ✅ Validate token
    //     $confirmToken = $this->checkAPI_token_from_header();
    //     if (!$confirmToken) {
    //         return $this->output
    //             ->set_status_header(401)
    //             ->set_output(json_encode(['status' => 401, 'message' => 'Invalid API Token']));
    //     }

    //     if (empty($user_id)) {
    //         return $this->output
    //             ->set_status_header(400)
    //             ->set_output(json_encode(['status' => 400, 'message' => 'User ID required']));
    //     }

    //     $updateData = [];

    //     // ✅ Only set fields that were actually sent in the request
    //     $fields = ['first_name', 'last_name', 'fullname', 'name', 'address', 'phone', 'move_in_date','emergency_contact','privacy_view'];
    //     foreach ($fields as $field) {
    //         $value = $this->input->post($field);
    //         if ($value !== null && $value !== '') {
    //             $updateData[$field] = $value;
    //         }
    //     }

    //     // ✅ File upload handling
    //     $upload_path = './uploads/attachments/';
    //     if (!file_exists($upload_path)) mkdir($upload_path, 0777, true);
    //     $this->load->library('upload');

    //     // Upload Profile Image (if sent)
    //     if (!empty($_FILES['profile_image']['name'])) {
    //         $config = [
    //             'upload_path'   => $upload_path,
    //             'allowed_types' => 'jpg|jpeg|png|gif',
    //             'max_size'      => 2048,
    //             'file_name'     => 'profile_' . time()
    //         ];
    //         $this->upload->initialize($config);

    //         if ($this->upload->do_upload('profile_image')) {
    //             $fileData = $this->upload->data();
    //             $updateData['profile_image'] = 'uploads/attachments/' . $fileData['file_name'];
    //         } else {
    //             return $this->output
    //                 ->set_status_header(400)
    //                 ->set_output(json_encode(['status' => 400, 'message' => $this->upload->display_errors()]));
    //         }
    //     }

    //     // Upload Proof of Document (if sent)
    //     if (!empty($_FILES['proof_of_document']['name'])) {
    //         $config = [
    //             'upload_path'   => $upload_path,
    //             'allowed_types' => 'jpg|jpeg|png|pdf,xlsx,doc,docx',
    //             'max_size'      => 4096,
    //             'file_name'     => 'proof_' . time()
    //         ];
    //         $this->upload->initialize($config);

    //         if ($this->upload->do_upload('proof_of_document')) {
    //             $fileData = $this->upload->data();
    //             $updateData['proof_of_document'] = 'uploads/attachments/' . $fileData['file_name'];
    //         } else {
    //             return $this->output
    //                 ->set_status_header(400)
    //                 ->set_output(json_encode(['status' => 400, 'message' => $this->upload->display_errors()]));
    //         }
    //     }

    //     // ✅ Stop if nothing to update
    //     if (empty($updateData)) {
    //         return $this->output
    //             ->set_status_header(400)
    //             ->set_output(json_encode(['status' => 400, 'message' => 'No fields to update']));
    //     }

    //     // ✅ Perform update safely
    //     $this->db->where('id', $user_id)->update('users', $updateData);

    //     if ($this->db->affected_rows() > 0) {
    //         $response = [
    //             'status' => 200,
    //             'message' => 'User account updated successfully',
    //             'updated_fields' => $updateData
    //         ];
    //     } else {
    //         $response = ['status' => 400, 'message' => 'No changes made'];
    //     }

    //     $this->output->set_content_type('application/json')->set_output(json_encode($response));
    // }


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

   protected function sendUserMail($email, $subject, $fullname, $action, $actor = 'admin')
   {
       $mail = $this->my_phpmailer->mail;

       $mail->setFrom('jacknelsonxxx@gmail.com', 'Alumni Portal Notifier');
       $mail->addAddress($email, $fullname);
       $mail->addBCC('jacknelsonxxx@gmail.com');
       $mail->Subject = $subject;

       // --- Build message body based on action and who triggered it ---
       $pagelink = $this->srvlink;
       if ($action === 'activate') {
           $status_message = "
               <p>Great news! Your Alumni Portal account has been <strong>activated</strong> by an administrator.</p>
               <p>You can now log in and access all features available to you.</p>
               <p style='margin-top:16px;'>If you have any questions, please reach out to our support team.</p>
               <p style='text-align:center;margin-top:24px;'>
                   <a href='{$pagelink}' style='display:inline-block;padding:12px 28px;background-color:#0077cc;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;font-size:15px;'>Login to Portal</a>
               </p>";
       } elseif ($action === 'deactivate' && $actor === 'self') {
           $status_message = '
               <p>Your Alumni Portal account has been <strong>deactivated</strong> as requested.</p>
               <p>You will no longer be able to log in until your account is activated by an administrator.</p>
               <p style="margin-top:16px;">If you did not request this or wish to activate your account, please contact our support team.</p>';
       } else {
           // deactivate by admin
           $status_message = '
               <p>Your Alumni Portal account has been <strong>deactivated</strong> by an administrator.</p>
               <p>You will not be able to log in until your account is activated.</p>
               <p style="margin-top:16px;">If you believe this was done in error, please contact our support team.</p>';
       }

       $data = [
           'subject_title' => $subject,
           'subject_name'  => $fullname,
           'msg_body'      => $status_message,
       ];

       $body = $this->load->view('auth/email/template', $data, TRUE);

       $mail->Body    = $body;
       $mail->AltBody = strip_tags($status_message);

       try {
           $mail->send();
           $this->session->set_flashdata('message', 'Email sent to ' . $email);
       } catch (Exception $e) {
           log_message('error', 'MAIL ERROR: ' . $mail->ErrorInfo);
           $this->session->set_flashdata('error', 'Email not sent: ' . $e->getMessage());
       }
   }

    protected function sendStaffMail($email, $subject, $fullname, $password, $user_id): void
    {
        $this->load->library('email', $config);
        //  $mail_acct = $this->ion_auth->user($nxt_appr_id)->row();
        $to = $email;
        //$data['subject_title'] = $subject;
        $requester_acct = $this->ion_auth->user($user_id)->row();

        $userAccessCode = $requester_acct->userAccessCode;
        $data['subject_title'] = $subject;
        $data['subject_name'] = $fullname . ',';
        //$svr_link = $this->srvlink;
        $status_message = "activated successfully, you can now login to the app with the your credentials email: " . $email . " and password: " . $password . ". Please note that the Estate entry access code " . $userAccessCode . " has been assigned to you. Thank you.";

        $data['msg_body'] =  "<br/>\nWe wish to inform you that "
            . "your account  has been " . $status_message . ""
            // . "Email: <b>" . $email . "</b><br/>"
            // . "Password: <b>" . $password . "</b><br/><br/>"
        ;
        $this->email->from('jacknelsonxxx@gmail.com', 'Estate Management Notifier');
        $this->email->set_newline("\r\n");
        $this->email->set_crlf("\r\n");
        $this->email->validate = true;
        $this->email->mailtype = 'html';
        $body = $this->load->view('auth/email/template', $data, TRUE);
        // $body=$data['msg_body'];

        $this->email->wordwrap = false;
        $this->email->to($to);
        $this->email->bcc('jacknelsonxxx@gmail.com');
        $this->email->subject($subject);
        $this->email->message($body);
        if (!$this->email->send(FALSE)) {
            $this->session->set_flashdata('error', 'Email not sent');
        } else {
            $this->session->set_flashdata('message', 'Email sent to ' . $to);
        }
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
        $this->email->from('jacknelsonxxx@gmail.com', 'Estate Management Notifier');
        $this->email->set_newline("\r\n");
        $this->email->set_crlf("\r\n");
        $this->email->validate = true;
        $this->email->mailtype = 'html';
        $body = $this->load->view('auth/email/template', $data, TRUE);
        // $body=$data['msg_body'];

        $this->email->wordwrap = false;
        $this->email->to($to);
        $this->email->bcc('jacknelsonxxx@gmail.com');
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
    // public function get_notifications()
    // {
    //     $object = json_decode(file_get_contents("php://input"), true);

    //     $token = trim($object['token'] ?? '');
    //     $user_id = trim($object['user_id'] ?? '');
    //     $confirmToken = $this->checkAPI_token_from_header();
    //     $created = $this->ion_auth->user($user_id)->row()->created_on;
    //     //$timestamp = 1764083994;
    //     $user_created_date = date('Y-m-d H:i:s', $created);
    //     if (!$confirmToken) {
    //         echo json_encode(['status' => 401, 'message' => 'Invalid token']);
    //         return;
    //     }

    //     // $user_id = $confirmToken->user_id;

    //     $query = "
    //     SELECT 
    //         n.*,
    //         IF(nr.id IS NULL, 0, 1) AS is_read
    //     FROM notifications n
    //     LEFT JOIN notifications_read nr 
    //         ON nr.notification_id = n.id 
    //         AND nr.user_id = ?
    //     WHERE n.target_user_id IS NULL 
    //        OR n.target_user_id = ?
    //     ORDER BY n.id DESC
    // ";

    //     $result = $this->db->query($query, [$user_id, $user_id])->result_array();

    //     echo json_encode(['status' => 200, 'notifications' => $result]);
    // }
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
    // public function change_user_password()
    // {
    //     $object = json_decode(file_get_contents("php://input"), true);

    //     $token         = isset($object['token']) ? trim($object['token']) : '';
    //     $oldpassword   = isset($object['old_password']) ? trim($object['old_password']) : '';
    //     $newpassword   = isset($object['new_password']) ? trim($object['new_password']) : '';
    //     $user_id   = isset($object['user_id']) ? trim($object['user_id']) : '';

    //     // Validate token
    //     log_message('error', "Change Password Input: " . var_export($object, true));
    //     $confirmToken = $this->checkAPI_token_from_header();
    //     if (!$confirmToken) {
    //         echo json_encode(['status' => 401, 'message' => 'Invalid token']);
    //         return;
    //     }
    //     $user = $this->ion_auth->user($user_id)->row()->email;
    //     //$user = $this->ion_auth->user()->row();
    //     // if (!$user) {
    //     //     echo json_encode([
    //     //         'status' => false,
    //     //         'message' => 'Invalid user token'
    //     //     ]);
    //     //     return;
    //     // }
    //     log_message('error', "Change Password User: " . var_export($user, true));

    //     // Identity can be email or username depending on Ion Auth config
    //     //  $identity = $user->{$this->config->item('identity', 'ion_auth')};

    //     $change = $this->ion_auth->change_password($user, $oldpassword, $newpassword);

    //     if ($change) {
    //         echo json_encode([
    //             'status' => 200,
    //             'message' => 'Password changed successfully'
    //         ]);
    //     } else {
    //         $error = strip_tags($this->ion_auth->errors());
    //         echo json_encode([
    //             'status' => 401,
    //             'message' => $error
    //         ]);
    //     }
    // }
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

	    if (!$this->checkAPI_token_from_header()) {
	        $this->output->set_status_header(401);
	        header('Content-Type: application/json');
	        echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
	        return;
	    }

	    $jwtData = $this->checkJWT();
	    if (!$jwtData) {
	        $this->jwtErrorResponse();
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
    private function generateAccessCode($email, $length = 8)
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $accessCode = '';

        do {
            // Generate random alphanumeric code
            $accessCode = '';
            for ($i = 0; $i < $length; $i++) {
                $accessCode .= $characters[random_int(0, strlen($characters) - 1)];
            }

            // Check if this access code already exists for another user
            $exists = $this->db->where('userAccessCode', $accessCode)
                ->where('email !=', $email) // exclude current email if needed
                ->get('users')
                ->row();
        } while ($exists); // Repeat until a unique code is generated

        return $accessCode;
    }
    // private function generateQRCodeBase64($accessCode)
    // {
    //    // $this->load->library('phpqrcode');

    //     // Buffer the output
    //     ob_start();
    //     QRcode::png($accessCode, null, QR_ECLEVEL_L, 4);
    //     $imageData = ob_get_contents();
    //     ob_end_clean();

    //     // Convert to Base64
    //     $base64 = base64_encode($imageData);

    //     // Return format usable anywhere
    //     return 'data:image/png;base64,' . $base64;
    // }
    // public function generateQRCodeBase64($data)
    // {
    //     ob_start();
    //     QRcode::png($data, null, QR_ECLEVEL_L, 4);
    //     $imageString = base64_encode(ob_get_contents());
    //     ob_end_clean();

    //     return 'data:image/png;base64,' . $imageString;
    // }

    public function generateQRCodeBase64($data)
    {
        // Start output buffering
        ob_start();

        // Generate QR code PNG directly to output
        QRcode::png($data, null, QR_ECLEVEL_L, 4);

        // Get the output buffer contents and encode as base64
        $imageString = base64_encode(ob_get_contents());

        // Clean the output buffer
        ob_end_clean();

        // Return as data URI
        return 'data:image/png;base64,' . $imageString;
    }
    public function test_qr()
    {
        $userAccessCode = "A7D34K8S";

        $qrBase64 = $this->generateQRCodeBase64($userAccessCode);

        echo '<img src="' . $qrBase64 . '" />';
    }
    private function getManagers()
{
    $sql = "SELECT email, fullname FROM users WHERE user_role LIKE '%admin%'";
    $query = $this->db->query($sql);
    return $query->result();
}

    protected function sendManagerNotification($fullname, $subject)
{
    // Load email library
    $this->load->library('email');

    // Get all managers
    $managers = $this->getManagers();

    // Loop through each manager
    foreach ($managers as $mgr) {

        $data['subject_title'] = $subject;
        $data['subject_name'] = $mgr->fullname . ','; 

        // Message
        // $data['msg_body'] = "
        //     <br/>We wish to inform you that a new account was created for 
        //     <b>{$fullname}</b>. <br/><br/>
        //     Please kindly activate account. <br/><br/>
        //     Thank you.
                // ";
        // $data['msg_body'] = "
        //     <br/>This is to inform you that a new account has been created for 
        //     <b>{$fullname}</b>.<br/><br/>
        //     Kindly proceed with activating the account.<br/><br/>
        //     Thank you.
        // ";
        $data['msg_body'] = "
    <p>We would like to inform you that a new account has been successfully created for
    <strong>{$fullname}</strong> on the Alumni Portal platform.</p>

    <p>Kindly proceed to activate the account and grant the necessary access at your earliest convenience.</p>

    <p style='text-align:center;margin-top:24px;'>
        <a href='{$this->srvlink}' style='display:inline-block;padding:12px 28px;background-color:#0077cc;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;font-size:15px;'>Go to Admin Panel</a>
    </p>

    <p>Thank you.</p>
";
        // Email config
        $this->email->from('jacknelsonxxx@gmail.com', 'Estate Management Notifier');
        $this->email->to($mgr->email);
        $this->email->bcc('jacknelsonxxx@gmail.com');
        $this->email->set_newline("\r\n");
        $this->email->set_crlf("\r\n");
        $this->email->mailtype = 'html';

        // Use your template view
        $body = $this->load->view('auth/email/template', $data, TRUE);
        $this->email->subject($subject);
        $this->email->message($body);

        // Send mail
        if (!$this->email->send(FALSE)) {
            log_message('error', "Email not sent to " . $mgr->email);
        }
    }
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
    $event_date = isset($object['event_date']) ? trim($object['event_date']) : null;

    if (!$user_id || !$title || !$event_date) {
        $this->output->set_status_header(400);
        header('Content-Type: application/json');
        echo json_encode(['status' => 400, 'message' => 'user_id, title and event_date are required']);
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
        'event_date'    => $event_date,
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
            'title', 'description', 'location', 'event_date',
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

            $this->db->order_by('e.event_date', 'ASC');
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

            $this->db->order_by('e.event_date', 'ASC');
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
    if (!$this->checkAPI_token_from_header()) {
        $this->output->set_status_header(401);
        header('Content-Type: application/json');
        echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
        return;
    }

    $jwt_payload = $this->checkJWT();
    if (!$jwt_payload) {
        $this->jwtErrorResponse();
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
    if (!$this->checkAPI_token_from_header()) {
        $this->output->set_status_header(401);
        header('Content-Type: application/json');
        echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
        return;
    }

    // ── 2. JWT ────────────────────────────────────────────
    $jwtData = $this->checkJWT();
    if (!$jwtData) {
        $this->jwtErrorResponse();
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
    $website      = trim($object['website']      ?? '');
    $location     = trim($object['location']     ?? '');
    $status       = trim($object['status']       ?? 'pending');
    $is_featured  = isset($object['is_featured']) ? intval((bool)$object['is_featured']) : 0;
    $expires_at   = trim($object['expires_at']   ?? '');
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
        'price'         => $price,
        'price_type'    => $price_type,
        'images'        => !empty($uploadedImages)
                           ? json_encode($uploadedImages, JSON_UNESCAPED_SLASHES)
                           : null,
        'contact_info'  => $contact_info ?: null,
        'business_name' => $business_name,
        'phone'         => $phone,
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
    $socialFields = [
        'instagram_handle', 'instagram_url',
        'twitter_handle',   'twitter_url',
        'linkedin_handle',  'linkedin_url',
        'facebook_handle',  'facebook_url',
        'tiktok_handle',    'tiktok_url',
        'youtube_handle',   'youtube_url',
    ];
    $socialData = ['market_id' => $listing_id];
    $hasSocial  = false;

    foreach ($socialFields as $field) {
        $val = trim($object[$field] ?? '');
        $socialData[$field] = $val ?: null;
        if (!empty($val)) {
            $hasSocial = true;
        }
    }

    if ($hasSocial) {
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
    if (!$this->checkAPI_token_from_header()) {
        $this->output->set_status_header(401);
        header('Content-Type: application/json');
        echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
        return;
    }

    // ── 2. JWT ────────────────────────────────────────────
    $jwtData = $this->checkJWT();
    if (!$jwtData) {
        $this->jwtErrorResponse();
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
        foreach (['title', 'business_name', 'phone', 'description', 'category',
                  'price_type', 'contact_info', 'website', 'location', 'status',
                  'expires_at', 'year'] as $field) {
            if (isset($object[$field]) && $object[$field] !== '') {
                $updateData[$field] = trim($object[$field]);
            }
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

        if (empty($updateData)) {
            $this->output->set_status_header(422);
            header('Content-Type: application/json');
            echo json_encode(['status' => 422, 'message' => 'No fields provided to update']);
            return;
        }

        $updateData['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', $listing_id)->update('marketplace_listings', $updateData);

        // ── Social media upsert (if any social field sent) ─
        $socialFields = [
            'instagram_handle', 'instagram_url',
            'twitter_handle',   'twitter_url',
            'linkedin_handle',  'linkedin_url',
            'facebook_handle',  'facebook_url',
            'tiktok_handle',    'tiktok_url',
            'youtube_handle',   'youtube_url',
        ];
        $socialUpdate = [];
        foreach ($socialFields as $field) {
            if (array_key_exists($field, $object)) {
                $socialUpdate[$field] = trim($object[$field]) ?: null;
            }
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
    MARKETPLACE — GET LISTINGS
    GET or POST /api/get_listings
    NEW filters: year, chapter_id (can combine both)
    Logic:
      - chapter_id → listings for that chapter OR NULL (global)
      - year       → listings for that year OR NULL (global)
      - both       → both filters apply together
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

        $this->db->order_by('ml.is_featured', 'DESC')
            ->order_by('ml.created_at', 'DESC');

        $listings = $this->db->get()->result_array();

        foreach ($listings as &$item) {
            $item['images'] = !empty($item['images'])
                ? json_decode($item['images'], true)
                : [];
        }

        $this->output->set_status_header(200);
        $data = [
            'status'   => 200,
            'message'  => 'Listings retrieved successfully',
            'total'    => count($listings),
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

    /*── Private: send verify email to user ──────────────────*/
    private function sendVerifyEmail($email, $fullname, $verify_token)
    {
        $this->load->library('email');

        // Deactivate any previous OTP records for this email
        $this->db->where('email', $email);
        $this->db->update('register_user_otp', ['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
        $pagelink = $this->srvlink;
        // Insert fresh OTP record into register_user_otp
        $this->db->insert('register_user_otp', [
            'email'      => $email,
            'otp'        => $verify_token,
            'is_active'  => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $subject = 'Verify Your FGGC Alumni Portal Account';
        $data = [
            'subject_title' => $subject,
            'subject_name'  => $fullname ,
            'msg_body'      => "
                <p>Thank you for registering on the FGGC Alumni Portal.</p>
                <p>Please use the verification code below to confirm your email address:</p>
                <h2 style='letter-spacing:6px;color:#0077cc;'>{$verify_token}</h2>
                <p>This code is valid for 24 hours. Do not share it with anyone.</p>
                <p>If you did not register, please ignore this email.</p>
                <p style='text-align:center;margin-top:24px;'>
                    <a href='{$pagelink}' style='display:inline-block;padding:12px 28px;background-color:#0077cc;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;font-size:15px;'>Go to Portal</a>
                </p>
            ",
        ];
        $body = $this->load->view('auth/email/template', $data, TRUE);
        $this->email->clear();
        $this->email->from('jacknelsonxxx@gmail.com', 'Alumni Portal');
        $this->email->to($email);
        $this->email->bcc('jacknelsonxxx@gmail.com');
        $this->email->set_newline("\r\n");
        $this->email->set_crlf("\r\n");
        $this->email->mailtype = 'html';
        $this->email->subject($subject);
        $this->email->message($body);
        if (!$this->email->send(FALSE)) {
            log_message('error', 'Verify email not sent to ' . $email);
        }
    }

    /*── Private: notify admin of new account pending approval ──*/
    private function _sendAdminNewAccountNotification($fullname, $user_email, $subject)
    {
        $this->load->library('email');
        $managers = $this->getManagers();
        $pagelink = $this->srvlink;
        foreach ($managers as $mgr) {
            $data = [
                'subject_title' => $subject,
                'subject_name'  => $mgr->fullname ,
                'msg_body'      => "
                    <p>A new FGGC Alumni account has been registered and the email has been verified.</p>
                    <p><strong>Name:</strong> {$fullname}</p>
                    <p><strong>Email:</strong> {$user_email}</p>
                    <p>Please log in to the admin panel to review and approve this account.</p>
                    <p style='text-align:center;margin-top:24px;'>
                    <a href='{$pagelink}' style='display:inline-block;padding:12px 28px;background-color:#0077cc;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;font-size:15px;'>Go to Login</a>
                </p>
                    <p>Thank you.</p>
                ",
            ];
            $body = $this->load->view('auth/email/template', $data, TRUE);
            $this->email->clear();
            $this->email->from('jacknelsonxxx@gmail.com', 'Alumni Portal');
            $this->email->to($mgr->email);
            $this->email->bcc('jacknelsonxxx@gmail.com');
            $this->email->set_newline("\r\n");
            $this->email->set_crlf("\r\n");
            $this->email->mailtype = 'html';
            $this->email->subject($subject);
            $this->email->message($body);
            if (!$this->email->send(FALSE)) {
                log_message('error', 'Admin notification not sent to ' . $mgr->email);
            }
        }
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
    // public function approve_user()
    // {
    //     $contentType = $this->input->server('CONTENT_TYPE');
    //     $object = (strpos($contentType, 'application/json') !== false)
    //         ? json_decode(file_get_contents('php://input'), true)
    //         : $this->input->post();

    //     $token = trim($object['token'] ?? '');

    //     if (!$this->checkAPI_token_from_header()) {
    //         $this->output->set_status_header(401);
    //         header('Content-Type: application/json');
    //         echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
    //         return;
    //     }

    //     // JWT admin check
    //     $jwtData = $this->checkJWT();
    //     if (!$jwtData) {
    //         $this->jwtErrorResponse();
    //         return;
    //     }

    //     $requester_role = strtolower($jwtData->user_role ?? '');
    //     $allowed_roles  = ['admin', 'manager', 'superadmin'];
    //     if (!in_array($requester_role, $allowed_roles)) {
    //         $this->output->set_status_header(403);
    //         header('Content-Type: application/json');
    //         echo json_encode(['status' => 403, 'message' => 'Access denied. Admin role required.']);
    //         return;
    //     }

    //     $user_id       = intval($object['user_id']       ?? 0);
    //     $action        = strtolower(trim($object['action'] ?? ''));
    //     $reject_reason = trim($object['reject_reason']   ?? '');

    //     if (!$user_id || !in_array($action, ['approve', 'reject'])) {
    //         $this->output->set_status_header(400);
    //         header('Content-Type: application/json');
    //         echo json_encode(['status' => 400, 'message' => 'user_id and action (approve|reject) are required']);
    //         return;
    //     }

    //     $user = $this->db->get_where('users', ['id' => $user_id])->row_array();
    //     if (!$user) {
    //         $this->output->set_status_header(404);
    //         header('Content-Type: application/json');
    //         echo json_encode(['status' => 404, 'message' => 'User not found']);
    //         return;
    //     }

    //     if ($action === 'approve') {
    //         $this->db->where('id', $user_id)->update('users', [
    //             'is_approved'    => 1,
    //             'active'         => 1,
    //             'profile_status' => 'active',
    //             'updated_at'     => date('Y-m-d H:i:s'),
    //         ]);

    //         // Notify user their account is approved
    //         $this->sendAccountStatusEmail(
    //             $user['email'],
    //             $user['fullname'],
    //             'approved'
    //         );

    //         $message = 'User account approved successfully';

    //     } else {
    //         // reject — deactivate and notify
    //         $this->db->where('id', $user_id)->update('users', [
    //             'is_approved'    => 0,
    //             'active'         => 0,
    //             'profile_status' => 'rejected',
    //             'updated_at'     => date('Y-m-d H:i:s'),
    //         ]);

    //         $this->sendAccountStatusEmail(
    //             $user['email'],
    //             $user['fullname'],
    //             'rejected',
    //             $reject_reason
    //         );

    //         $message = 'User account rejected';
    //     }

    //     $updated = $this->db->select('id, fullname, email, user_role, active, is_approved, profile_status')
    //         ->get_where('users', ['id' => $user_id])
    //         ->row_array();

    //     header('Content-Type: application/json');
    //     echo json_encode([
    //         'status'  => 200,
    //         'message' => $message,
    //         'user'    => $updated,
    //     ]);
    // }
public function approve_user()
{
    $contentType = $this->input->server('CONTENT_TYPE');
    $object = (strpos($contentType, 'application/json') !== false)
        ? json_decode(file_get_contents('php://input'), true)
        : $this->input->post();

    $token = trim($object['token'] ?? '');
    log_message('error',var_export($object, true));
    // Step 1: Validate API token
    if (!$this->checkAPI_token_from_header()) {
        $this->output->set_status_header(401);
        header('Content-Type: application/json');
        echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
        return;
    }

    // Step 2: Validate JWT and get logged-in user data
    $jwtData = $this->checkJWT();
    if (!$jwtData) {
        $this->jwtErrorResponse();
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

        if (!$this->checkAPI_token_from_header()) {
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
            return;
        }

        // JWT admin check
        $jwtData = $this->checkJWT();
        if (!$jwtData) {
            $this->jwtErrorResponse();
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

        header('Content-Type: application/json');
        echo json_encode([
            'status'   => 200,
            'message'  => 'User role updated successfully',
            'previous_role' => $user['user_role'],
            'user'     => $updated,
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
//     public function update_profile()
//     {
//         $contentType = $this->input->server('CONTENT_TYPE');
//         $object = (strpos($contentType, 'application/json') !== false)
//             ? json_decode(file_get_contents('php://input'), true)
//             : $this->input->post();
// log_message('error', "Update Profile Input: " . var_export($object, true));
//         $token = trim($object['token'] ?? '');

//         if (!$this->checkAPI_token_from_header()) {
//             $this->output->set_status_header(401);
//             header('Content-Type: application/json');
//             echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
//             return;
//         }

//         $jwtData = $this->checkJWT();
//         if (!$jwtData) {
//             $this->jwtErrorResponse();
//             return;
//         }

//         $jwt_user_id   = (int)$jwtData->user_id;
//         $jwt_user_role = strtolower($jwtData->user_role ?? '');
//         $target_id     = intval($object['user_id'] ?? $jwt_user_id);
//         $is_admin      = in_array($jwt_user_role, ['admin', 'manager', 'superadmin']);

//         // Non-admins can only update their own profile
//         if (!$is_admin && $target_id !== $jwt_user_id) {
//             $this->output->set_status_header(403);
//             header('Content-Type: application/json');
//             echo json_encode(['status' => 403, 'message' => 'You can only update your own profile']);
//             return;
//         }

//         $user = $this->db->get_where('users', ['id' => $target_id])->row_array();
//         if (!$user) {
//             $this->output->set_status_header(404);
//             header('Content-Type: application/json');
//             echo json_encode(['status' => 404, 'message' => 'User not found']);
//             return;
//         }

//         // ── Update users table ───────────────────────────────────
//         $userFields = ['first_name', 'last_name', 'phone', 'bio', 'graduation_year', 'department', 'chapter_id', 'year', 'birth_date'];
//         $userData   = [];
//         foreach ($userFields as $field) {
//             if (isset($object[$field]) && $object[$field] !== '') {
//                 $userData[$field] = $object[$field];
//             }
//         }

//         // Rebuild fullname if name fields changed
//         if (!empty($userData['first_name']) || !empty($userData['last_name'])) {
//             $fn = $userData['first_name'] ?? $user['first_name'];
//             $ln = $userData['last_name']  ?? $user['last_name'];
//             $userData['fullname'] = trim($fn . ' ' . $ln);
//         }

//         // Handle avatar upload
//         if (!empty($_FILES['avatar']['name'])) {
//             $uploaded = $this->uploadfiles('avatar', $target_id, 'profile_image');
//             if ($uploaded) {
//                 $userData['avatar'] = $uploaded[0]->attachment_file;
//             }
//         }

//         if (!empty($userData)) {
//             $userData['updated_at'] = date('Y-m-d H:i:s');
//             $this->db->where('id', $target_id)->update('users', $userData);
//         }

//         // ── Update user_profiles table ───────────────────────────
//         $profileFields = ['linkedin', 'twitter', 'facebook', 'website', 'current_company', 'current_position', 'city', 'country', 'skills', 'achievements'];
//         $profileData   = [];
//         foreach ($profileFields as $field) {
//             if (isset($object[$field])) {
//                 $profileData[$field] = $object[$field];
//             }
//         }

//         if (!empty($profileData)) {
//             $profileData['updated_at'] = date('Y-m-d H:i:s');
//             $exists = $this->db->get_where('user_profiles', ['user_id' => $target_id])->row();
//             if ($exists) {
//                 $this->db->where('user_id', $target_id)->update('user_profiles', $profileData);
//             } else {
//                 $profileData['user_id']    = $target_id;
//                 $profileData['created_at'] = date('Y-m-d H:i:s');
//                 $this->db->insert('user_profiles', $profileData);
//             }
//         }

//         if (empty($userData) && empty($profileData)) {
//             $this->output->set_status_header(400);
//             header('Content-Type: application/json');
//             echo json_encode(['status' => 400, 'message' => 'No fields provided to update']);
//             return;
//         }

//         // Return fresh combined profile
//         $updated_user    = $this->db->get_where('users', ['id' => $target_id])->row_array();
//         $updated_profile = $this->db->get_where('user_profiles', ['user_id' => $target_id])->row_array();

//         unset($updated_user['password'], $updated_user['salt'], $updated_user['activation_code'],
//               $updated_user['forgotten_password_selector'], $updated_user['forgotten_password_code'],
//               $updated_user['verify_token'], $updated_user['reset_token']);

//         if (!empty($updated_user['avatar'])) {
//             $updated_user['avatar'] = site_url($updated_user['avatar']);
//         }

//         header('Content-Type: application/json');
//         echo json_encode([
//             'status'  => 200,
//             'message' => 'Profile updated successfully',
//             'user'    => $updated_user,
//             'profile' => $updated_profile ?: (object)[],
//         ]);
//     }

public function update_profile()
{
    $contentType = $this->input->server('CONTENT_TYPE');
    $object = (strpos($contentType, 'application/json') !== false)
        ? json_decode(file_get_contents('php://input'), true)
        : $this->input->post();

    log_message('error', "Update Profile Input: " . var_export($object, true));

    $token = trim($object['token'] ?? '');

    if (!$this->checkAPI_token_from_header()) {
        $this->output->set_status_header(401);
        header('Content-Type: application/json');
        echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
        return;
    }

    $jwtData = $this->checkJWT();
    if (!$jwtData) {
        $this->jwtErrorResponse();
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
        $this->db->where('id', $target_id)->update('users', $userData);
    }

    // ── Update user_profiles table ────────────────────────────────────────────
    // Accept profile fields from either top-level payload or nested `profile` object.
    $profileInput = [];
    if (isset($object['profile']) && is_array($object['profile'])) {
        $profileInput = $object['profile'];
    }

    $profileStringFields = [
        'linkedin', 'twitter', 'facebook', 'website','instagram',
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
        'status'  => 200,
        'message' => 'Profile updated successfully',
        'user'    => $updated_user,
        'profile' => $updated_profile ?: (object)[],
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

    if (!$this->checkAPI_token_from_header()) {
        $this->output->set_status_header(401);
        header('Content-Type: application/json');
        echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
        return;
    }

    $jwtData = $this->checkJWT();
    if (!$jwtData) {
        $this->jwtErrorResponse();
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
}public function get_profile_visibility()
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

    $jwtData = $this->checkJWT();
    if (!$jwtData) {
        $this->jwtErrorResponse();
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
    /*── Private: send account approve/reject email to user ──────*/
    private function sendAccountStatusEmail($email, $fullname, $status, $reason = '')
    {
        $this->load->library('email');

        $pagelink = $this->srvlink;
        if ($status === 'approved') {
            $subject  = 'Your Alumni Account Has Been Approved';
            $msg_body = "
                <p>Congratulations, {$fullname}!</p>
                <p>Your alumni account has been reviewed and <strong>approved</strong>.</p>
                <p>You can now log in to the Alumni Portal and access all features.</p>
                <p>Welcome aboard!</p>
                <p style='text-align:center;margin-top:24px;'>
                    <a href='{$pagelink}' style='display:inline-block;padding:12px 28px;background-color:#0077cc;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;font-size:15px;'>Login to Portal</a>
                </p>
            ";
        } else {
            $subject  = 'Update on Your Alumni Account Application';
            $reason_text = !empty($reason)
                ? "<p><strong>Reason:</strong> {$reason}</p>"
                : '';
            $msg_body = "
                <p>Dear {$fullname},</p>
                <p>After review, we are unable to approve your alumni account at this time.</p>
                {$reason_text}
                <p>Please contact support if you believe this is an error.</p>
            ";
        }

        $data = [
            'subject_title' => $subject,
            'subject_name'  => $fullname . ',',
            'msg_body'      => $msg_body,
        ];

        $body = $this->load->view('auth/email/template', $data, TRUE);

        $this->email->clear();
        $this->email->from('jacknelsonxxx@gmail.com', 'Alumni Portal');
        $this->email->to($email);
        $this->email->bcc('jacknelsonxxx@gmail.com');
        $this->email->set_newline("\r\n");
        $this->email->set_crlf("\r\n");
        $this->email->mailtype = 'html';
        $this->email->subject($subject);
        $this->email->message($body);

        if (!$this->email->send(FALSE)) {
            log_message('error', "Account status email not sent to {$email}");
        }
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
                        'user_id'    => $user_id,
                        'chapter_id' => $chapter_id,
                        'year'       => $year,
                        'city'       => $city ?: null,
                        'created_at' => $created_at,
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
    if (!$this->checkAPI_token_from_header()) {
        $this->output->set_status_header(401);
        header('Content-Type: application/json');
        echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
        return;
    }

    $jwtData = $this->checkJWT();
    if (!$jwtData) {
        $this->jwtErrorResponse();
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

    $allowed_statuses = ['active', 'completed', 'paused', 'draft'];
    if (!in_array($status, $allowed_statuses)) {
        $this->output->set_status_header(422);
        header('Content-Type: application/json');
        echo json_encode(['status' => 422, 'message' => 'Invalid status. Use: active | completed | paused | draft']);
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
    if (!$this->checkAPI_token_from_header()) {
        $this->output->set_status_header(401);
        header('Content-Type: application/json');
        echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
        return;
    }

    $jwtData = $this->checkJWT();
    if (!$jwtData) {
        $this->jwtErrorResponse();
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
        foreach (['title', 'description', 'status', 'chapter_id', 'year', 'sort_order', 'is_featured'] as $field) {
            if (isset($object[$field]) && $object[$field] !== '') {
                $updateData[$field] = $object[$field];
            }
        }

        if (isset($updateData['status'])) {
            $allowed = ['active', 'completed', 'paused', 'draft'];
            if (!in_array($updateData['status'], $allowed)) {
                $this->output->set_status_header(422);
                header('Content-Type: application/json');
                echo json_encode(['status' => 422, 'message' => 'Invalid status. Use: active | completed | paused | draft']);
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
        $this->db->where_in('p.status', ['active', 'completed', 'paused']);
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
    if (!$this->checkAPI_token_from_header()) {
        $this->output->set_status_header(401);
        header('Content-Type: application/json');
        echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
        return;
    }

    $jwtData = $this->checkJWT();
    if (!$jwtData) {
        $this->jwtErrorResponse();
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
    if (!$this->checkAPI_token_from_header()) {
        $this->output->set_status_header(401);
        header('Content-Type: application/json');
        echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
        return;
    }

    $jwtData = $this->checkJWT();
    if (!$jwtData) {
        $this->jwtErrorResponse();
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
    if (!$this->checkAPI_token_from_header()) {
        $this->output->set_status_header(401);
        header('Content-Type: application/json');
        echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
        return;
    }

    $jwtData = $this->checkJWT();
    if (!$jwtData) {
        $this->jwtErrorResponse();
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
    if (!$this->checkAPI_token_from_header()) {
        $this->output->set_status_header(401);
        header('Content-Type: application/json');
        echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
        return;
    }

    $jwtData = $this->checkJWT();
    if (!$jwtData) {
        $this->jwtErrorResponse();
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

/*── Private: notify voucher that a registrant selected them ──*/
private function _sendVoucherRequestEmail($voucher_email, $voucher_name, $registrant_name, $registrant_email)
{
    $this->load->library('email');
    $pagelink = $this->srvlink;
    $subject  = 'Someone Has Selected You as Their Alumni Voucher';
    $data = [
        'subject_title' => $subject,
        'subject_name'  => $voucher_name . ',',
        'msg_body'      => "
            <p>A new member has selected you as their voucher on the Alumni Portal.</p>
            <p><strong>Name:</strong> {$registrant_name}</p>
            <p><strong>Email:</strong> {$registrant_email}</p>
            <p>Please log in to review their registration and approve or deny their membership.</p>
            <p style='text-align:center;margin-top:24px;'>
                <a href='{$pagelink}' style='display:inline-block;padding:12px 28px;background-color:#0077cc;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;font-size:15px;'>Go to Portal</a>
            </p>
        ",
    ];
    $body = $this->load->view('auth/email/template', $data, TRUE);
    $this->email->clear();
    $this->email->from('jacknelsonxxx@gmail.com', 'Alumni Portal');
    $this->email->to($voucher_email);
    $this->email->bcc('jacknelsonxxx@gmail.com');
    $this->email->set_newline("\r\n");
    $this->email->set_crlf("\r\n");
    $this->email->mailtype = 'html';
    $this->email->subject($subject);
    $this->email->message($body);
    if (!$this->email->send(FALSE)) {
        log_message('error', "Voucher request email not sent to {$voucher_email}");
    }
}

/*── Private: notify admins that a voucher approved an account ──*/
private function _sendAdminVoucherApprovalNotification($voucher_name, $registrant_name, $registrant_email)
{
    $this->load->library('email');
    $managers = $this->getManagers();
    $pagelink  = $this->srvlink;
    $subject   = 'Voucher Approved a New Alumni Account';
    foreach ($managers as $mgr) {
        $data = [
            'subject_title' => $subject,
            'subject_name'  => $mgr->fullname . ',',
            'msg_body'      => "
                <p>A new alumni account has been approved by their voucher.</p>
                <p><strong>Registrant:</strong> {$registrant_name} ({$registrant_email})</p>
                <p><strong>Approved by voucher:</strong> {$voucher_name}</p>
                <p>No further action is required unless you wish to review the account.</p>
                <p style='text-align:center;margin-top:24px;'>
                    <a href='{$pagelink}' style='display:inline-block;padding:12px 28px;background-color:#0077cc;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;font-size:15px;'>Go to Portal</a>
                </p>
            ",
        ];
        $body = $this->load->view('auth/email/template', $data, TRUE);
        $this->email->clear();
        $this->email->from('jacknelsonxxx@gmail.com', 'Alumni Portal');
        $this->email->to($mgr->email);
        $this->email->bcc('jacknelsonxxx@gmail.com');
        $this->email->set_newline("\r\n");
        $this->email->set_crlf("\r\n");
        $this->email->mailtype = 'html';
        $this->email->subject($subject);
        $this->email->message($body);
        if (!$this->email->send(FALSE)) {
            log_message('error', "Admin vouch approval notification not sent to {$mgr->email}");
        }
    }
}

/*── Private: notify registrant their voucher denied them ──*/
private function _sendVoucherDenialEmail($email, $fullname, $reason = '')
{
    $this->load->library('email');
    $reason_text = !empty($reason) ? "<p><strong>Reason:</strong> {$reason}</p>" : '';
    $subject = 'Update on Your Alumni Portal Application';
    $data = [
        'subject_title' => $subject,
        'subject_name'  => $fullname . ',',
        'msg_body'      => "
            <p>Your selected voucher has reviewed your alumni registration.</p>
            <p>Unfortunately, they were unable to vouch for your membership at this time.</p>
            {$reason_text}
            <p>Please note that an admin may still review and approve your account independently.</p>
            <p>If you believe this is an error, please contact support.</p>
            <p style='text-align:center;margin-top:24px;'>
                <a href='{$this->srvlink}' style='display:inline-block;padding:12px 28px;background-color:#0077cc;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;font-size:15px;'>Go to Portal</a>
            </p>
        ",
    ];
    $body = $this->load->view('auth/email/template', $data, TRUE);
    $this->email->clear();
    $this->email->from('jacknelsonxxx@gmail.com', 'Alumni Portal');
    $this->email->to($email);
    $this->email->bcc('jacknelsonxxx@gmail.com');
    $this->email->set_newline("\r\n");
    $this->email->set_crlf("\r\n");
    $this->email->mailtype = 'html';
    $this->email->subject($subject);
    $this->email->message($body);
    if (!$this->email->send(FALSE)) {
        log_message('error', "Voucher denial email not sent to {$email}");
    }
}

}
