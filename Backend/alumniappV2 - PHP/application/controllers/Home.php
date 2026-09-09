<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Home extends MY_Controller
{
    function __construct()
    {
        parent::__construct();
        $this->load->helper(array('language', 'url'));
        $this->form_validation->set_error_delimiters($this->config->item('error_start_delimiter', 'ion_auth'), $this->config->item('error_end_delimiter', 'ion_auth'));
        $this->lang->load('auth');        
        $this->output->set_header('Last-Modified: ' . gmdate("D, d M Y H:i:s") . ' GMT');('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
        $this->output->set_header('Pragma: no-cache');
        $this->output->set_header("Expires: Mon, 26 Jul 1995 05:00:00 GMT");        
    }
 public function alert()
    {
        $this->load->view('home/alert');
    }
    public function index()
    {  
     $this->login();
    } 
	public function previouslink($previous_url){
		$mypreviousLink = $_SERVER['REQUEST_URI'];
        $parts = explode("/", $mypreviousLink);
        $index = array_search("previouslink", $parts);

        if ($index !== false && $index < count($parts) - 1) {            
            $valuesAfterPreviousLink = array_slice($parts, $index + 1);
            implode("/", $valuesAfterPreviousLink);
			log_message('error','here'.$valuesAfterPreviousLink);
            $this->session->set_userdata('previous_url', implode("/", $valuesAfterPreviousLink));
            redirect('Home/login');
        } else {     
            redirect('Home/login');       
        }
		log_message("error", "my prevouslink1". $mypreviousLink);
	}  
	public function viewLoginRequest($request_id='',$request_form=''){			
		if (is_null($this->ion_auth->get_user_id()) || !($this->ion_auth->logged_in())) {
            $this->form_validation->set_rules('identity', 'Identity', 'required');
        $this->form_validation->set_rules('password', 'Password', 'required');
        $this->form_validation->set_rules('remember','Remember me','integer'); 
		// $previousUrl = $this->input->server('HTTP_REFERER');		
       $this->session->set_userdata('previous_url', current_url());
		
		$this->data['request_id'] =$request_id;
		$this->data['request_form'] =$request_form;
		log_message("error","yes we are request_id2". $this->input->post('request_form'));  
		log_message("error","yes we are previous_url--". $previous_url);   
		//$this->login($this->request_id,$this->request_form);     
        if($this->form_validation->run()===TRUE)
        {
            $remember = (bool) $this->input->post('remember');
			log_message("error","yes we are request_form1".$this->input->post('request_form')); 
			$this->login($this->input->post('request_id'),$this->input->post('request_form'));
           
        } 
        $this->render('home/home_view2'); 
        } else{
			log_message("error","yes we are  log in");
			redirect('requests/request_form/'.$request_form.'/'.$request_id);
		}

	}
	public function login($request_id='',$request_form="")
    {      
		
        $this->form_validation->set_rules('identity', 'Identity', 'required');
        $this->form_validation->set_rules('password', 'Password', 'required');
        $this->form_validation->set_rules('remember','Remember me','integer');  
		log_message("error","yesrequest_id".$request_id); 
		log_message("error","yesrequest_idchika".$this->request_id);
		$previous_url = $this->session->userdata('previous_url');
		log_message("error","yes we are previous_url44". $previous_url); 
		$position = strpos($previous_url, '/');

// Extract the substring after the first '/'
		$substring = substr($previous_url, $position + 1);          
        if($this->form_validation->run()===TRUE)
        {
            $remember = (bool) $this->input->post('remember');
            if ($this->ion_auth->login($this->input->post('identity'), $this->input->post('password'), $remember))
            {
				log_message("error","yesrequest_idJohnson".$previous_url);
				if (strpos($substring, 'requests') !== false || strpos($substring, 'viewLoginRequest') !== false ||strpos($substring, 'setup') !== false) {  																		
					redirect($substring);
				}
				else{
					log_message("error","yes we are notblog in");
					//( $this->REQUEST_URI2);
                redirect('setup/dashboard');
                // redirect($previous_url);
				}//
            } else {
                $this->session->set_flashdata('message','Login unsuccessful! Please enter valid email and password');
                $this->session->set_flashdata('error',$this->ion_auth->errors());
				redirect( $this->REQUEST_URI2);
            } 
        } 
        $this->render('home/home_view'); 
    }    
    
    public function forgot_password()
    {        
        $this->data['page_title'] = 'Forgot Password';
        $this->form_validation->set_rules('identity', 'Identity', 'required');

        if($this->form_validation->run()===TRUE) {
            $identity = $this->ion_auth->where('email', strtolower($this->input->post('identity')))->users()->row();
            if(empty($identity)) {
                $this->ion_auth->set_message('Email not found!');
                $this->session->set_flashdata('error',$this->ion_auth->messages());
                redirect("home/forgot_password", 'refresh');
            }

            //run the forgotten password method to email an activation code to the user
            $forgotten = $this->ion_auth->forgotten_password($identity->{$this->config->item('identity', 'ion_auth')});

            if ($forgotten)
            {
              //  $this->session->set_flashdata('success',$this->ion_auth->messages());
                $this->session->set_flashdata('message','Please check your email for password reset message');
                redirect("home/login", 'refresh'); //we should display a confirmation page here instead of the login page
            }
            else
            {
                $this->session->set_flashdata('error',$this->ion_auth->errors());
                redirect("home/forgot_password", 'refresh');
            }                  
        }
        $this->render('home/forgot_password');
    }
    
    public function logout()
    {
      $this->ion_auth->logout();
      $this->session->set_flashdata('error',$this->ion_auth->errors());
      $this->session->set_flashdata('message','You have been successfulled logged out');
      redirect('Home/index');
    }  
    public function trust()
    {
        $this->render('home/trusted_site');
    }    
    

	// reset password - final step for forgotten password
	public function reset_password($code = NULL)
	{
		if (!$code)
		{
			show_404();
		}

		$user = $this->ion_auth->forgotten_password_check($code);

		if ($user)
		{
			// if the code is valid then display the password reset form

			$this->form_validation->set_rules('new', $this->lang->line('reset_password_validation_new_password_label'), 'required|min_length[' . $this->config->item('min_password_length', 'ion_auth') . ']|max_length[' . $this->config->item('max_password_length', 'ion_auth') . ']|matches[new_confirm]');
			$this->form_validation->set_rules('new_confirm', $this->lang->line('reset_password_validation_new_password_confirm_label'), 'required');

			if ($this->form_validation->run() == false)
			{
				// display the form

				// set the flash data error message if there is one
				$this->data['message'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('message');

				$this->data['min_password_length'] = $this->config->item('min_password_length', 'ion_auth');
				$this->data['new_password'] = array(
					'name' => 'new',
					'id'   => 'new',
					'type' => 'password',
					'pattern' => '^.{'.$this->data['min_password_length'].'}.*$',
				);
				$this->data['new_password_confirm'] = array(
					'name'    => 'new_confirm',
					'id'      => 'new_confirm',
					'type'    => 'password',
					'pattern' => '^.{'.$this->data['min_password_length'].'}.*$',
				);
				$this->data['user_id'] = array(
					'name'  => 'user_id',
					'id'    => 'user_id',
					'type'  => 'hidden',
					'value' => $user->id,
				);
				$this->data['csrf'] = $this->_get_csrf_nonce();
				$this->data['code'] = $code;

				// render
                                $this->render('home/reset_password');
				//$this->_render_page('home/reset_password', $this->data);
			}
			else
			{
				// do we have a valid request?
				if ($this->_valid_csrf_nonce() === FALSE || $user->id != $this->input->post('user_id'))
				{

					// something fishy might be up
					$this->ion_auth->clear_forgotten_password_code($code);

					show_error($this->lang->line('error_csrf'));

				}
				else
				{
					// finally change the password
					$identity = $user->{$this->config->item('identity', 'ion_auth')};

					$change = $this->ion_auth->reset_password($identity, $this->input->post('new'));

					if ($change)
					{
						// if the password was successfully changed
						$this->session->set_flashdata('message', $this->ion_auth->messages());
						redirect("home/alert", 'refresh');
					}
					else
					{
						$this->session->set_flashdata('message', $this->ion_auth->errors());
						redirect('home/reset_password/' . $code, 'refresh');
					}
				}
			}
		}
		else
		{
			// if the code is invalid then send them back to the forgot password page
			$this->session->set_flashdata('message', $this->ion_auth->errors());
			redirect("home/forgot_password", 'refresh');
		}
	}


	function _get_csrf_nonce()
	{
		$this->load->helper('string');
		$key   = random_string('alnum', 8);
		$value = random_string('alnum', 20);
		$this->session->set_flashdata('csrfkey', $key);
		$this->session->set_flashdata('csrfvalue', $value);

		return array($key => $value);
	}

	function _valid_csrf_nonce()
	{
		if ($this->input->post($this->session->flashdata('csrfkey')) !== FALSE &&
			$this->input->post($this->session->flashdata('csrfkey')) == $this->session->flashdata('csrfvalue'))
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}

	function _render_page($view, $data=null, $returnhtml=false)//I think this makes more sense
	{

		$this->viewdata = (empty($data)) ? $this->data: $data;

		$view_html = $this->load->view($view, $this->viewdata, $returnhtml);

		if ($returnhtml) return $view_html;//This will return html on 3rd argument being true
	}    
}