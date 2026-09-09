<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class RateAgentApi extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->library('email');
        $this->load->library('ion_auth');
        $this->load->helper('file');
    }

    public function index(){
		print("Johnson");
		return;
	}

    public function send_contact_email() {
        // Get JSON input
        $body = file_get_contents("php://input");
        $data = json_decode($body, true);

        // Validate required fields
        if (!isset($data['agent']) || !isset($data['contact'])) {
            $output['status'] = 400;
            $output['message'] = 'Missing required fields: agent and contact';
            echo json_encode($output);
            return;
        }

        $agent = $data['agent'];
        $contact = $data['contact'];
        $site_name = 'Rate Agent';

        // Validate agent email
        $recipient_email = isset($agent['email']) ? $agent['email'] : null;
        if (empty($recipient_email)) {
            $output['status'] = 400;
            $output['message'] = 'Agent email is required';
            echo json_encode($output);
            return;
        }

        // Validate contact email for reply-to
        $contact_email = isset($contact['email']) ? $contact['email'] : null;
        $contact_name = isset($contact['name']) ? $contact['name'] : 'Unknown';

        try {
            $emailTemplate = read_file(APPPATH . 'views/rateagent/agent_contact_email.html');
            
            if (!$emailTemplate) {
                throw new Exception('Email template not found');
            }

            // Replace template variables
            $emailTemplate = str_replace('{{agent_name}}', isset($agent['name']) ? $agent['name'] : '', $emailTemplate);
            $emailTemplate = str_replace('{{contact_subject}}', isset($contact['subject']) ? $contact['subject'] : '', $emailTemplate);
            $emailTemplate = str_replace('{{contact_name}}', $contact_name, $emailTemplate);
            $emailTemplate = str_replace('{{contact_email}}', $contact_email, $emailTemplate);
            $emailTemplate = str_replace('{{contact_phone}}', isset($contact['phone']) ? $contact['phone'] : '', $emailTemplate);
            $emailTemplate = str_replace('{{contact_message}}', isset($contact['message']) ? $contact['message'] : '', $emailTemplate);
            $emailTemplate = str_replace('{{site_name}}', $site_name, $emailTemplate);

            // Configure email
            $subject = "New message from {$contact_name} via {$site_name}";
            
            $this->email->clear();
            $this->email->from('appnotice@nubiaville.com', $site_name);
            // $this->email->from(
            //     $this->config->item('admin_email', 'ion_auth'), 
            //     $site_name
            // );
            $this->email->to($recipient_email);
            
            // Set reply-to if contact email is provided
            if (!empty($contact_email)) {
                $this->email->reply_to($contact_email, $contact_name);
            }
            
            $this->email->set_newline("\r\n");
            $this->email->set_crlf("\r\n");
            $this->email->subject($subject);
            $this->email->set_mailtype("html");
            $this->email->message($emailTemplate);

            // Send email
            if ($this->email->send()) {
                $output['status'] = 200;
                $output['message'] = 'Email sent successfully';
            } else {
                $output['status'] = 500;
                $output['message'] = 'Failed to send email';
                $output['error'] = $this->email->print_debugger();
            }

        } catch (Exception $e) {
            $output['status'] = 500;
            $output['message'] = 'Error: ' . $e->getMessage();
        }

        echo json_encode($output);
    }

    public function send_meeting_email() {
        // Get JSON input
        $body = file_get_contents("php://input");
        $data = json_decode($body, true);

        // Validate required fields
        if (!isset($data['agent']) || !isset($data['schedule'])) {
            $output['status'] = 400;
            $output['message'] = 'Missing required fields: agent and schedule';
            echo json_encode($output);
            return;
        }

        $agent = $data['agent'];
        $schedule = $data['schedule'];
        $site_name = 'Rate Agent';

        // Validate agent email
        $recipient_email = isset($agent['email']) ? $agent['email'] : null;
        if (empty($recipient_email)) {
            $output['status'] = 400;
            $output['message'] = 'Agent email is required';
            echo json_encode($output);
            return;
        }

        // Validate contact email for reply-to
        $agent_name = $agent['broker_name'];
        $schedule_name = $schedule['name'];
        $schedule_email = $schedule['email'];
        $schedule_phone = $schedule['phone'];
        $meeting_date = $schedule['meeting_date'];
        $meeting_time = $schedule['meeting_time'];
        $meeting_type = $schedule['meeting_type']; //cap first case
        $meeting_location = $schedule['meeting_location'];
        $property_type = isset($schedule['property_type']) ? $schedule['property_type'] : null;
        $property_budget = isset($schedule['property_budget']) ? $schedule['property_budget'] : null;
        $property_location = isset($schedule['property_location']) ? $schedule['property_location'] : null;

        try {
            $emailTemplate = read_file(APPPATH . 'views/rateagent/meeting_schedule.html');
            
            if (!$emailTemplate) {
                throw new Exception('Email template not found');
            }

            // Handle conditional property details
            if (!empty($property_type) && !empty($property_budget) && !empty($property_location)) {
                $property_details = "
                    <div style='margin: 15px 0; padding: 15px; background-color: #f9f9f9; border-left: 3px solid #007bff;'>
                        <p><strong>Property Type:</strong> {$property_type}</p>
                        <p><strong>Budget:</strong> {$property_budget}</p>
                        <p><strong>Preferred Location:</strong> {$property_location}</p>
                    </div>
                ";
            } else {
                $property_details = '';
            }
            // Replace template variables            
            $emailTemplate = str_replace('{{agent_name}}', $agent_name, $emailTemplate);
            $emailTemplate = str_replace('{{schedule_name}}', $schedule_name, $emailTemplate);
            $emailTemplate = str_replace('{{schedule_email}}', $schedule_email, $emailTemplate);
            $emailTemplate = str_replace('{{schedule_phone}}', $schedule_phone, $emailTemplate);
            $emailTemplate = str_replace('{{meeting_date}}', $meeting_date, $emailTemplate);
            $emailTemplate = str_replace('{{meeting_time}}', $meeting_time, $emailTemplate);
            $emailTemplate = str_replace('{{meeting_type}}', $meeting_type, $emailTemplate);
            $emailTemplate = str_replace('{{meeting_location}}', $meeting_location, $emailTemplate);
            $emailTemplate = str_replace('{{property_details}}', $property_details, $emailTemplate);

            // Configure email
            $subject = "New Meeting Scheduled with {$agent_name}";
            
            $this->email->clear();
            $this->email->from('appnotice@nubiaville.com', $site_name);
            
            $this->email->to($recipient_email);
                        
            
            $this->email->set_newline("\r\n");
            $this->email->set_crlf("\r\n");
            $this->email->subject($subject);
            $this->email->set_mailtype("html");
            $this->email->message($emailTemplate);

            // Send email
            if ($this->email->send()) {
                $output['status'] = 200;
                $output['message'] = 'Email sent successfully';
            } else {
                $output['status'] = 500;
                $output['message'] = 'Failed to send email';
                $output['error'] = $this->email->print_debugger();
            }

        } catch (Exception $e) {
            $output['status'] = 500;
            $output['message'] = 'Error: ' . $e->getMessage();
        }

        echo json_encode($output);
    }

    public function send_password_reset_email() {
        // Get JSON input
        $body = file_get_contents("php://input");
        $data = json_decode($body, true);

        // Validate required fields
        if (!isset($data['email']) || !isset($data['reset_url'])) {
            $output['status'] = 400;
            $output['message'] = 'Missing required fields: Reset url and Email';
            echo json_encode($output);
            return;
        }

        $first_name = $data['first_name'];
        $recipient_email = $data['email'];
        $reset_url = $data['reset_url'];
        $site_name = 'Rate Agent';             

        try {
            $emailTemplate = read_file(APPPATH . 'views/rateagent/password_reset.html');
            
            if (!$emailTemplate) {
                throw new Exception('Email template not found');
            }
            
            // Replace template variables            
            $emailTemplate = str_replace('{{reset_url}}', $reset_url, $emailTemplate);
            $emailTemplate = str_replace('{{first_name}}', $first_name, $emailTemplate);
            $subject = "Reset Your Password - RateAgent.io";
            
            $this->email->clear();
            $this->email->from('appnotice@nubiaville.com', $site_name);
            
            $this->email->to($recipient_email);                        
            
            $this->email->set_newline("\r\n");
            $this->email->set_crlf("\r\n");
            $this->email->subject($subject);
            $this->email->set_mailtype("html");
            $this->email->message($emailTemplate);

            // Send email
            if ($this->email->send()) {
                $output['status'] = 200;
                $output['message'] = 'Email sent successfully';
            } else {
                $output['status'] = 500;
                $output['message'] = 'Failed to send email';
                $output['error'] = $this->email->print_debugger();
            }

        } catch (Exception $e) {
            $output['status'] = 500;
            $output['message'] = 'Error: ' . $e->getMessage();
        }

        echo json_encode($output);
    }
    
    public function send_verification_email() {
        // Get JSON input
        $body = file_get_contents("php://input");
        $data = json_decode($body, true);

        // Validate required fields
        if (!isset($data['email']) || !isset($data['verify_url'])) {
            $output['status'] = 400;
            $output['message'] = 'Missing required fields: Verify url and Email';
            echo json_encode($output);
            return;
        }

        $first_name = $data['first_name'];
        $recipient_email = $data['email'];
        $verify_url = $data['verify_url'];
        $site_name = 'Rate Agent';             

        try {
            $emailTemplate = read_file(APPPATH . 'views/rateagent/email_verification.html');
            
            if (!$emailTemplate) {
                throw new Exception('Email template not found');
            }
            
            // Replace template variables            
            $emailTemplate = str_replace('{{verify_url}}', $verify_url, $emailTemplate);
            $emailTemplate = str_replace('{{first_name}}', $first_name, $emailTemplate);            
            $subject = "Verify Your Email Address - RateAgent.io";
            
            $this->email->clear();
            $this->email->from('appnotice@nubiaville.com', $site_name);
            
            $this->email->to($recipient_email);                        
            
            $this->email->set_newline("\r\n");
            $this->email->set_crlf("\r\n");
            $this->email->subject($subject);
            $this->email->set_mailtype("html");
            $this->email->message($emailTemplate);

            // Send email
            if ($this->email->send()) {
                $output['status'] = 200;
                $output['message'] = 'Email sent successfully';
            } else {
                $output['status'] = 500;
                $output['message'] = 'Failed to send email';
                $output['error'] = $this->email->print_debugger();
            }

        } catch (Exception $e) {
            $output['status'] = 500;
            $output['message'] = 'Error: ' . $e->getMessage();
        }

        echo json_encode($output);
    }

    public function send_general_contact_email() {
        // Get JSON input
        $body = file_get_contents('php://input');
        $data = json_decode($body, true);

        // Validate required fields
        if (!isset($data['contact'])) {
            $output['status'] = 400;
            $output['message'] = 'Missing required field: contact';
            echo json_encode($output);
            return;
        }

        $contact = $data['contact'];
        $site_name = 'Rate Agent';
        $admin_email = $data['admin_email']; // Get from payload
        
        // Validate contact email
        $contact_email = isset($contact['email']) ? $contact['email'] : null;
        if (empty($contact_email)) {
            $output['status'] = 400;
            $output['message'] = 'Contact email is required';
            echo json_encode($output);
            return;
        }

        // Get contact details
        $contact_name = isset($contact['name']) ? $contact['name'] : 'Unknown';
        $contact_subject = isset($contact['subject']) ? $contact['subject'] : 'General Inquiry';
        $contact_message = isset($contact['message']) ? $contact['message'] : '';
        $created_at = isset($contact['created_at']) ? $contact['created_at'] : '';
        $attachments = isset($data['attachments']) ? $data['attachments'] : [];
        $has_attachments = count($attachments) > 0;
        $attachment_count = count($attachments);

        try {
            // ============================================
            // 1. SEND EMAIL TO ADMIN
            // ============================================
            $adminTemplate = read_file(APPPATH . 'views/rateagent/contact_admin.html');

            if (!$adminTemplate) {
                throw new Exception('Admin email template not found');
            }

            // Replace template variables for admin
            $adminTemplate = str_replace('{{contact_name}}', $contact_name, $adminTemplate);
            $adminTemplate = str_replace('{{contact_email}}', $contact_email, $adminTemplate);
            $adminTemplate = str_replace('{{subject_display}}', $contact_subject, $adminTemplate);
            $adminTemplate = str_replace('{{contact_message}}', nl2br($contact_message), $adminTemplate);
            $adminTemplate = str_replace('{{created_at}}', $created_at, $adminTemplate);

            // Handle attachments display
            if ($has_attachments) {
                $attachment_html = "
                <div class='field'>
                    <div class='field-label'>Attachments:</div>
                    <div class='field-value'>{$attachment_count} file(s) attached</div>
                </div>";
            } else {
                $attachment_html = '';
            }
            $adminTemplate = str_replace('{{attachment_section}}', $attachment_html, $adminTemplate);

            // Configure admin email
            $admin_subject = "New Contact Form Submission - {$contact_subject}";

            $this->email->clear();
            $this->email->from('appnotice@nubiaville.com', $site_name);
            $this->email->to($admin_email);
            $this->email->reply_to($contact_email, $contact_name);
            $this->email->subject($admin_subject);
            $this->email->set_mailtype('html');
            $this->email->set_newline("\r\n");
            $this->email->set_crlf("\r\n");   
            $this->email->message($adminTemplate);

            // Process base64 encoded attachments
            $temp_files = [];
            foreach ($attachments as $attachment) {
                if (isset($attachment['content']) && isset($attachment['filename'])) {
                    $file_content = base64_decode($attachment['content']);

                    if ($file_content !== false) {
                        $temp_path = sys_get_temp_dir() . '/' . uniqid() . '_' . $attachment['filename'];
                        file_put_contents($temp_path, $file_content);

                        $mime_type = isset($attachment['mime_type']) ? $attachment['mime_type'] : 'application/octet-stream';
                        $this->email->attach($temp_path, 'attachment', $attachment['filename'], $mime_type);

                        $temp_files[] = $temp_path;
                    }
                }
            }

            // Send admin email
            $admin_sent = $this->email->send();
            if (!$admin_sent) {
                $admin_error = $this->email->print_debugger();
            }

            // Clean up temporary files
            foreach ($temp_files as $temp_file) {
                if (file_exists($temp_file)) {
                    unlink($temp_file);
                }
            }

            // ============================================
            // 2. SEND CONFIRMATION EMAIL TO USER
            // ============================================
            $userTemplate = read_file(APPPATH . 'views/rateagent/contact_confirmation.html');

            if (!$userTemplate) {
                throw new Exception('User confirmation template not found');
            }

            // Replace template variables for user
            $userTemplate = str_replace('{{contact_name}}', $contact_name, $userTemplate);
            $userTemplate = str_replace('{{subject_display}}', $contact_subject, $userTemplate);
            $userTemplate = str_replace('{{contact_message}}', nl2br($contact_message), $userTemplate);

            // Configure user confirmation email
            $user_subject = "We've received your message - RateAgent.io";

            $this->email->clear();
            $this->email->from('appnotice@nubiaville.com', $site_name);
            $this->email->to($contact_email);
            $this->email->subject($user_subject);
            $this->email->set_mailtype('html');
            $this->email->set_newline("\r\n");
            $this->email->set_crlf("\r\n");   
            $this->email->message($userTemplate);

            // Send user confirmation email
            $user_sent = $this->email->send();
            if (!$user_sent) {
                $user_error = $this->email->print_debugger();
            }

            // ============================================
            // RETURN RESPONSE
            // ============================================
            if ($admin_sent && $user_sent) {
                $output['status'] = 200;
                $output['message'] = 'Emails sent successfully';
            } elseif ($admin_sent && !$user_sent) {
                $output['status'] = 206;
                $output['message'] = 'Admin email sent, but confirmation email failed';
                $output['error'] = isset($user_error) ? $user_error : 'Unknown error';
            } elseif (!$admin_sent && $user_sent) {
                $output['status'] = 206;
                $output['message'] = 'Confirmation email sent, but admin notification failed';
                $output['error'] = isset($admin_error) ? $admin_error : 'Unknown error';
            } else {
                $output['status'] = 500;
                $output['message'] = 'Failed to send both emails';
                $output['errors'] = [
                    'admin' => isset($admin_error) ? $admin_error : 'Unknown error',
                    'user' => isset($user_error) ? $user_error : 'Unknown error'
                ];
            }

        } catch (Exception $e) {
            $output['status'] = 500;
            $output['message'] = 'Error: ' . $e->getMessage();
        }

        echo json_encode($output);
    }
}