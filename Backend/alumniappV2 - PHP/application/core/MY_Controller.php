<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Controller extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();			

        // CORS
        $allowedOrigins = [
			'https://alumni-app-two.vercel.app',
            'https://alumni-app-three.vercel.app',
            'http://localhost:5173',
            'http://127.0.0.1:5500',
            'http://localhost:5500',
            'http://localhost',
            'http://localhost:5500',
			
            'null'
        ];

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';        

        if (in_array($origin, $allowedOrigins, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
        } 

        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
        header('Access-Control-Max-Age: 86400');        

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        // Common libraries/helpers
        $this->load->database();
        $this->load->library('jwt_helper');
        $this->load->helper(['url']);
		
		if ($this->router->fetch_class() === 'product'
            && $this->router->fetch_method() === 'paystack_webhook') {
            return; // skip auth for webhook
        }
    }

    protected function getAPIKey()
    {
        $api_name = 'alumni_key';
        $fetchAPIKey = $this->api_model->fetchAPIKey($api_name);    
        echo json_encode($fetchAPIKey);
    }

    /**
     * Check JWT token
     */
    protected function checkJWT()
    {
        $token = $this->jwt_helper->getFromHeader();

        if (!$token) {
            return NULL;
        }

        $result = $this->jwt_helper->validateAccessToken($token);

        if (!$result['valid']) {
            log_message('error', 'JWT validation failed: ' . $result['error']);
            return NULL;
        }

        return $result['data'];
    }

    /**
     * Return JWT error response
     */
    protected function jwtErrorResponse($error = 'token_invalid')
    {
        $message = ($error === 'token_expired')
            ? 'Token expired, please login again'
            : 'Unauthorized: invalid or missing token';

        return $this->output
            ->set_status_header(401)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'status'  => false,
                'message' => $message,
                'code'    => $error
            ]));
    }

    /**
     * Check API key from request header
     */
    protected function checkAPI_token_from_header()
    {
        $key = '';

        if (isset($_SERVER['HTTP_X_API_KEY'])) {
            $key = $_SERVER['HTTP_X_API_KEY'];
        } elseif (function_exists('apache_request_headers')) {

            foreach (apache_request_headers() as $header => $value) {

                if (strtolower($header) === 'x-api-key') {
                    $key = $value;
                    break;
                }
            }
        }

        return $this->checkAPI_token(trim($key));
    }

    /**
     * Verify API key against database
     */
    protected function checkAPI_token($token)
    {
        $token = trim((string)$token);

        $query = $this->db
            ->where('api_name', 'alumni_key')
            ->get('api_table');

        if ($query->num_rows() !== 1) {
            return FALSE;
        }

        return password_verify(
            $token,
            $query->row()->api_token
        );
    }

    /**
     * Store Refresh Token
    */
    protected function _storeRefreshToken($user_id, $refresh_token)
    {
        $count = $this->db->where('user_id', $user_id)
            ->where('revoked', 0)
            ->count_all_results('jwt_refresh_tokens');

        if ($count >= 5) {
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

        $this->db->insert('jwt_refresh_tokens', array(
            'user_id'    => $user_id,
            'token'      => hash('sha256', $refresh_token),
            'revoked'    => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', time() + 604800)
        ));
    }

    /**
     * Authorize request (API Key + JWT)
     * Returns:
     *  - User object/array on success
     *  - FALSE on failure (response already sent)
     */
    protected function authorize()
    {
        if (!$this->checkAPI_token_from_header()) {

            $this->output
                ->set_status_header(403)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => false,
                    'message' => 'Invalid API token'
                ]));

            return FALSE;
        }

        $user = $this->checkJWT();

        if (!$user) {

            $this->jwtErrorResponse();

            return FALSE;
        }

        return $user;
    }

    protected function authorizeAdmin()
    {
        $user = $this->authorize();
        
        if (!$user || stripos($user->user_role, 'admin') === false) {
            $this->output
                ->set_status_header(403)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => false,
                    'message' => 'You are not authorized to perform this action.'
                ]));
            return null;
        }        
        return $user;
    }
    

    protected function authorizeStoreAdmins()
    {
        $user = $this->authorize();
        $admins = ["super admin", "admin", "finance admin", "storekeeper admin"]; 
        if (!$user || !in_array($user->user_role, $admins)) {
            $this->output
                ->set_status_header(403)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => false,
                    'message' => 'You are not authorized to perform this action.'
                ]));
            return null;
        }        
        return $user;
    }



    protected function _downloadAvatar($url, $user_id)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $imageData = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200 || empty($imageData)) {
            return null;
        }

        $destination = FCPATH . 'uploads/profiles';
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $filename = $user_id . '_' . time() . '_social_avatar.jpg';
        $full_path = $destination . '/' . $filename;

        if (file_put_contents($full_path, $imageData) === false) {
            return null;
        }

        return 'uploads/profiles/' . $filename;
    }

    protected function _generateUserCode($graduationYear = null, $email = null)
    {
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

            $counter = 1;
            while ($this->db->where('user_code', $code)->count_all_results('users') > 0) {
                $hex = $makeHex($seed . '|' . $counter);
                $code = "MBR-{$graduationYear}-{$hex}";
                $counter++;
                if ($counter > 1000) break;
            }

            if ($this->db->where('user_code', $code)->count_all_results('users') == 0) {
                return $code;
            }
        }

        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
            $exists = $this->db->where('user_code', $code)->count_all_results('users');
        } while ($exists > 0);

        return $code;
    }

    protected function getManagers()
    {
        $sql = "SELECT email, fullname FROM users WHERE user_role LIKE '%admin%'";
        $query = $this->db->query($sql);
        return $query->result();
    }

    /*── protected: send verify email to user ──────────────────*/
    protected function sendVerifyEmail($email, $fullname, $verify_token)
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
        $this->email->from('nubiavilleprojects@gmail.com', 'FGGC Alumni Portal');
        $this->email->to($email);
        $this->email->bcc('nubiavilleprojects@gmail.com');
        $this->email->set_newline("\r\n");
        $this->email->set_crlf("\r\n");
        $this->email->mailtype = 'html';
        $this->email->subject($subject);
        $this->email->message($body);
        if (!$this->email->send(FALSE)) {
            log_message('error', 'Verify email not sent to ' . $email);
        }
    }

    /*── protected: notify admin of new account pending approval ──*/
    protected function _sendAdminNewAccountNotification($fullname, $user_email, $subject)
    {
        $managers = $this->getManagers();
        $pagelink = 'https://alumni-app-three.vercel.app/auth/login';

        foreach ($managers as $mgr) {
            $data = [
                'subject_title' => $subject,
                'subject_name'  => $mgr->fullname,
                'msg_body'      => "
                    <p>A new FGGC Alumni account has been registered via social sign-in.</p>
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
            $this->email->from('nubiavilleprojects@gmail.com', 'FGGC Alumni Portal');
            $this->email->to($mgr->email);
            $this->email->bcc('nubiavilleprojects@gmail.com');
            $this->email->set_newline("\r\n");
            $this->email->set_crlf("\r\n");
            $this->email->mailtype = 'html';
            $this->email->subject($subject);
            $this->email->message($body);
            if (!$this->email->send(FALSE)) {
                log_message('error', 'Social signup admin notification not sent to ' . $mgr->email);
            }
        }
    }

    /*── protected: send account approve/reject email to user ──────*/
    protected function sendAccountStatusEmail($email, $fullname, $status, $reason = '')
    {
        $this->load->library('email');

        $pagelink = $this->srvlink;
        if ($status === 'approved') {
            $subject  = 'Your FGGC Alumni Account Has Been Approved';
            $msg_body = "
                <p>Congratulations, {$fullname}!</p>
                <p>Your FGGC Alumni account has been reviewed and <strong>approved</strong>.</p>
                <p>You can now log in to the FGGC Alumni Portal and access all features.</p>
                <p>Welcome aboard!</p>
                <p style='text-align:center;margin-top:24px;'>
                    <a href='{$pagelink}' style='display:inline-block;padding:12px 28px;background-color:#0077cc;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;font-size:15px;'>Login to Portal</a>
                </p>
            ";
        } else {
            $subject  = 'Update on Your FGGC Alumni Account Application';
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
            'subject_name'  => $fullname  ,
            'msg_body'      => $msg_body,
        ];

        $body = $this->load->view('auth/email/template', $data, TRUE);

        $this->email->clear();
       $this->email->from('nubiavilleprojects@gmail.com', 'FGGC Alumni Portal');
        $this->email->to($email);
        $this->email->bcc('nubiavilleprojects@gmail.com');
        $this->email->set_newline("\r\n");
        $this->email->set_crlf("\r\n");
        $this->email->mailtype = 'html';
        $this->email->subject($subject);
        $this->email->message($body);

        if (!$this->email->send(FALSE)) {
            log_message('error', "Account status email not sent to {$email}");
        }
    }

    /**
    * Send order delivery status email to customer
    */
    protected function sendOrderStatusEmail($email, $fullname, $orderNumber, $status, $deliveryType = '', $note = '', $riderDetails = '' ) {
        $this->load->library( 'email' );

        $orderNumber = htmlspecialchars( $orderNumber, ENT_QUOTES, 'UTF-8' );
        $fullname    = htmlspecialchars( $fullname, ENT_QUOTES, 'UTF-8' );
        $note        = htmlspecialchars( $note, ENT_QUOTES, 'UTF-8' );
        $riderDetails = htmlspecialchars( $riderDetails, ENT_QUOTES, 'UTF-8' );

        /*
        |--------------------------------------------------------------------------
        | Ready for Pickup
        |--------------------------------------------------------------------------
        */
        if ($status === 'pickup' ) {

            $subject = 'Your Order Is Ready for Pickup';

            $noteText = !empty( $note )
            ? "<p><strong>Note:</strong> {$note}</p>"
            : '';

            $msg_body = "
                <p>We're pleased to let you know that your order
                <strong>#{$orderNumber}</strong> is ready for pickup.</p>

                <p>Kindly collect your order from the designated pickup location
                during pickup hours. Please have your order number available
                for verification.</p>

                {$noteText}

                <p>Thank you for shopping with the FGGC Alumni Association Store.</p>

                <p>Regards,<br>
                FGGC Alumni Association</p>
            ";
        }

        /*
        |--------------------------------------------------------------------------
        | Out for Delivery
        |--------------------------------------------------------------------------
        */
        elseif ( $status === 'out_for_delivery' ) {
            $subject = 'Your Order Is Out for Delivery';

            $noteText = !empty( $note )
            ? "<p><strong>Note:</strong> {$note}</p>"
            : '';

            $riderText = !empty( $riderDetails )
            ? "
                    <p><strong>Rider Details:</strong><br>
                    {$riderDetails}</p>
                "
            : '';

            $msg_body = "        
                <p>We're pleased to let you know that your order
                <strong>#{$orderNumber}</strong> is now out for delivery.</p>

                {$riderText}

                {$noteText}

                <p>Please ensure you are available to receive your order.</p>

                <p>Thank you for shopping with the FGGC Alumni Association Store.</p>

                <p>Best regards,<br>
                FGGC Alumni Association</p>
            ";
        }

        /*
        |--------------------------------------------------------------------------
        | Completed
        |--------------------------------------------------------------------------
        */
        elseif ( $status === 'completed' ) {

            $subject = 'Your Order Has Been Completed';

            $noteText = !empty( $note )
            ? "<p><strong>Note:</strong> {$note}</p>"
            : '';

            $msg_body = "
                <p>We're pleased to let you know that your order
                <strong>#{$orderNumber}</strong> has been marked as
                <strong>Completed</strong>.</p>

                {$noteText}

                <p>Thank you for shopping with the FGGC Alumni Association Store.
                We hope you're satisfied with your purchase and appreciate your support.</p>

                <p>Best regards,<br>
                FGGC Alumni Association</p>
            ";
        } else {
            return;
        }

        $data = [
            'subject_title' => $subject,
            'subject_name'  => $fullname,
            'msg_body'      => $msg_body,
        ];
        $body = $this->load->view('auth/email/template', $data, TRUE);
            $this->email->clear();
            $this->email->from('nubiavilleprojects@gmail.com', 'FGGC Alumni Portal');
            $this->email->to($email);
            $this->email->bcc('nubiavilleprojects@gmail.com');
            $this->email->set_newline("\r\n");
            $this->email->set_crlf("\r\n");
            $this->email->mailtype = 'html';
            $this->email->subject($subject);
            $this->email->message($body);
      

        

        if ( !$this->email->send( FALSE ) ) {
            log_message('error', $this->email->print_debugger(['headers', 'subject', 'body']));
            log_message(
                'error',
                "Order status email not sent to {$email} for order #{$orderNumber}"
            );
        }
    }

    /*── protected: notify voucher that a registrant selected them ──*/
    protected function _sendVoucherRequestEmail($voucher_email, $voucher_name, $registrant_name, $registrant_email)
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
        $this->email->from('nubiavilleprojects@gmail.com', 'FGGC Alumni Portal');
        $this->email->to($voucher_email);
        $this->email->bcc('nubiavilleprojects@gmail.com');
        $this->email->set_newline("\r\n");
        $this->email->set_crlf("\r\n");
        $this->email->mailtype = 'html';
        $this->email->subject($subject);
        $this->email->message($body);
        if (!$this->email->send(FALSE)) {
            log_message('error', "Voucher request email not sent to {$voucher_email}");
        }
    }

    /*── protected: notify admins that a voucher approved an account ──*/
    protected function _sendAdminVoucherApprovalNotification($voucher_name, $registrant_name, $registrant_email)
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
        $this->email->from('nubiavilleprojects@gmail.com', 'FGGC Alumni Portal');
            $this->email->to($mgr->email);
            $this->email->bcc('nubiavilleprojects@gmail.com');
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

    /*── protected: notify registrant their voucher denied them ──*/
    protected function _sendVoucherDenialEmail($email, $fullname, $reason = '')
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
        $this->email->from('nubiavilleprojects@gmail.com', 'FGGC Alumni Portal');
        $this->email->to($email);
        $this->email->bcc('nubiavilleprojects@gmail.com');
        $this->email->set_newline("\r\n");
        $this->email->set_crlf("\r\n");
        $this->email->mailtype = 'html';
        $this->email->subject($subject);
        $this->email->message($body);
        if (!$this->email->send(FALSE)) {
            log_message('error', "Voucher denial email not sent to {$email}");
        }
    }

    protected function sendUserMail($email, $subject, $fullname, $action, $actor = 'admin')
    {
        $mail = $this->my_phpmailer->mail;

        $mail->setFrom('nubiavilleprojects@gmail.com', 'Alumni Portal Notifier');
        $mail->addAddress($email, $fullname);
        $mail->addBCC('nubiavilleprojects@gmail.com');
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
        } else {
            $this->session->set_flashdata('message', 'Email sent to ' . $to);
        }
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
            $this->email->from('nubiavilleprojects@gmail.com', 'Estate Management Notifier');
            $this->email->to($mgr->email);
            $this->email->bcc('nubiavilleprojects@gmail.com');
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

    protected function generateAccessCode($email, $length = 8)
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

    protected function generateQRCodeBase64($data)
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
}