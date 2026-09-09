<?php defined('BASEPATH') OR exit('No direct script access allowed');
class MY_Controller extends CI_Controller
{
    protected $data = array();
    protected $current_user;    
    
    protected $request_id;
    protected $request_form;
    protected $user_id;
    protected $time1;
    protected $mem1;
    protected $REQUEST_URI;
    protected $REQUEST_URI2;
    protected $srvlink = "https://estatemanagement.nubiaville.com/";
    function __construct()
    { //$this->load->helper(array('session'));
        parent::__construct();  
        $this->REQUEST_URI=$_SERVER['REQUEST_URI'];
    $this->REQUEST_URI2=$_SERVER['HTTP_REFERER'];
    log_message("error", "yes we are  notlogcon1" . $_SERVER['HTTP_REFERER']);
    if (strpos($_SERVER['REQUEST_URI'], 'viewLoginRequest') !== false) {
      $path = parse_url($this->REQUEST_URI, PHP_URL_PATH);
      log_message("error", "mypath". $path);
      $segments = explode('/', $path);

      if (isset($segments[4])) {
        $this->request_id= $segments[4];
        $this->request_form= $segments[5];
        log_message("error", "yes we are  notlogcon1" . $this->request_id);
        
      }
    }  
        $this->load->library('ion_auth');
        $this->data['page_title'] = 'Welcome to Automator Portal';
        $this->data['before_head'] = '';
        $this->data['after_body'] = '';
        $this->data['after_foot'] = '';
        $this->data['log_user_out'] ="";
        $this->data['login_menu'] = $this->load->view('templates/_parts/logged_links_view', $this->data, TRUE);        
        $this->data['current_user_menu'] = $this->load->view('templates/_parts/public_links_view', NULL, TRUE);
        $this->data['current_user'] = "";
        $this->data['first_name'] = "";
        $this->data['log_user_out'] = anchor('Home/login','Click to login');
    }
    

    protected function displayTime($fldPrefix, $timeVal="")
    {
        if ($timeVal !="")
        {
            $timeArr = explode(":", $timeVal);
        }
        $form='
        <select class="form-control-sm col-xs-4" name="'.$fldPrefix.'_hr">
            <option value="">Select Hr</option>
            <option value="00"'.($timeArr[0] == "00" ?" selected=\'selected\'":"").'>00</option>
            <option value="01"'.($timeArr[0] == "01" ?" selected=\'selected\'":"").'>01</option>
            <option value="02"'.($timeArr[0] == "02" ?" selected=\'selected\'":"").'>02</option>
            <option value="03"'.($timeArr[0] == "03" ?" selected=\'selected\'":"").'>03</option>
            <option value="04"'.($timeArr[0] == "04" ?" selected=\'selected\'":"").'>04</option>
            <option value="05"'.($timeArr[0] == "05" ?" selected=\'selected\'":"").'>05</option>
            <option value="06"'.($timeArr[0] == "06" ?" selected=\'selected\'":"").'>06</option>
            <option value="07"'.($timeArr[0] == "07" ?" selected=\'selected\'":"").'>07</option>
            <option value="08"'.($timeArr[0] == "08" ?" selected=\'selected\'":"").'>08</option>
            <option value="09"'.($timeArr[0] == "09" ?" selected=\'selected\'":"").'>09</option>
            <option value="10"'.($timeArr[0] == "10" ?" selected=\'selected\'":"").'>10</option>
            <option value="11"'.($timeArr[0] == "11" ?" selected=\'selected\'":"").'>11</option>
            <option value="12"'.($timeArr[0] == "12" ?" selected=\'selected\'":"").'>12</option>
        </select>
        <select class="form-control-sm col-xs-4" name="'.$fldPrefix.'_min">
            <option value="">Select Min</option>
            <option value="00"'.($timeArr[1] == "00" ?" selected=\'selected\'":"").'>00</option>
            <option value="01"'.($timeArr[1] == "01" ?" selected=\'selected\'":"").'>01</option>
            <option value="02"'.($timeArr[1] == "02" ?" selected=\'selected\'":"").'>02</option>
            <option value="03"'.($timeArr[1] == "03" ?" selected=\'selected\'":"").'>03</option>
            <option value="04"'.($timeArr[1] == "04" ?" selected=\'selected\'":"").'>04</option>
            <option value="05"'.($timeArr[1] == "05" ?" selected=\'selected\'":"").'>05</option>
            <option value="06"'.($timeArr[1] == "06" ?" selected=\'selected\'":"").'>06</option>
            <option value="07"'.($timeArr[1] == "07" ?" selected=\'selected\'":"").'>07</option>
            <option value="08"'.($timeArr[1] == "08" ?" selected=\'selected\'":"").'>08</option>
            <option value="09"'.($timeArr[1] == "09" ?" selected=\'selected\'":"").'>09</option>
            <option value="10"'.($timeArr[1] == "10" ?" selected=\'selected\'":"").'>10</option>
            <option value="11"'.($timeArr[1] == "11" ?" selected=\'selected\'":"").'>11</option>
            <option value="12"'.($timeArr[1] == "12" ?" selected=\'selected\'":"").'>12</option>
            <option value="13"'.($timeArr[1] == "13" ?" selected=\'selected\'":"").'>13</option>
            <option value="14"'.($timeArr[1] == "14" ?" selected=\'selected\'":"").'>14</option>
            <option value="15"'.($timeArr[1] == "15" ?" selected=\'selected\'":"").'>15</option>
            <option value="16"'.($timeArr[1] == "16" ?" selected=\'selected\'":"").'>16</option>
            <option value="17"'.($timeArr[1] == "17" ?" selected=\'selected\'":"").'>17</option>
            <option value="18"'.($timeArr[1] == "18" ?" selected=\'selected\'":"").'>18</option>
            <option value="19"'.($timeArr[1] == "19" ?" selected=\'selected\'":"").'>19</option>
            <option value="20"'.($timeArr[1] == "20" ?" selected=\'selected\'":"").'>20</option>
            <option value="21"'.($timeArr[1] == "21" ?" selected=\'selected\'":"").'>21</option>
            <option value="22"'.($timeArr[1] == "22" ?" selected=\'selected\'":"").'>22</option>
            <option value="23"'.($timeArr[1] == "23" ?" selected=\'selected\'":"").'>23</option>
            <option value="24"'.($timeArr[1] == "24" ?" selected=\'selected\'":"").'>24</option>
            <option value="25"'.($timeArr[1] == "25" ?" selected=\'selected\'":"").'>25</option>
            <option value="26"'.($timeArr[1] == "26" ?" selected=\'selected\'":"").'>26</option>
            <option value="27"'.($timeArr[1] == "27" ?" selected=\'selected\'":"").'>27</option>
            <option value="28"'.($timeArr[1] == "28" ?" selected=\'selected\'":"").'>28</option>
            <option value="29"'.($timeArr[1] == "29" ?" selected=\'selected\'":"").'>29</option>
            <option value="30"'.($timeArr[1] == "30" ?" selected=\'selected\'":"").'>30</option>
            <option value="31"'.($timeArr[1] == "31" ?" selected=\'selected\'":"").'>31</option>
            <option value="32"'.($timeArr[1] == "32" ?" selected=\'selected\'":"").'>32</option>
            <option value="33"'.($timeArr[1] == "33" ?" selected=\'selected\'":"").'>33</option>
            <option value="34"'.($timeArr[1] == "34" ?" selected=\'selected\'":"").'>34</option>
            <option value="35"'.($timeArr[1] == "35" ?" selected=\'selected\'":"").'>35</option>
            <option value="36"'.($timeArr[1] == "36" ?" selected=\'selected\'":"").'>36</option>
            <option value="37"'.($timeArr[1] == "37" ?" selected=\'selected\'":"").'>37</option>
            <option value="38"'.($timeArr[1] == "38" ?" selected=\'selected\'":"").'>38</option>
            <option value="39"'.($timeArr[1] == "39" ?" selected=\'selected\'":"").'>39</option>
            <option value="40"'.($timeArr[1] == "40" ?" selected=\'selected\'":"").'>40</option>
            <option value="41"'.($timeArr[1] == "41" ?" selected=\'selected\'":"").'>41</option>
            <option value="42"'.($timeArr[1] == "42" ?" selected=\'selected\'":"").'>42</option>
            <option value="43"'.($timeArr[1] == "43" ?" selected=\'selected\'":"").'>43</option>
            <option value="44"'.($timeArr[1] == "44" ?" selected=\'selected\'":"").'>44</option>
            <option value="45"'.($timeArr[1] == "45" ?" selected=\'selected\'":"").'>45</option>
            <option value="46"'.($timeArr[1] == "46" ?" selected=\'selected\'":"").'>46</option>
            <option value="47"'.($timeArr[1] == "47" ?" selected=\'selected\'":"").'>47</option>
            <option value="48"'.($timeArr[1] == "48" ?" selected=\'selected\'":"").'>48</option>
            <option value="49"'.($timeArr[1] == "49" ?" selected=\'selected\'":"").'>49</option>
            <option value="50"'.($timeArr[1] == "50" ?" selected=\'selected\'":"").'>50</option>
            <option value="51"'.($timeArr[1] == "51" ?" selected=\'selected\'":"").'>51</option>
            <option value="52"'.($timeArr[1] == "52" ?" selected=\'selected\'":"").'>52</option>
            <option value="53"'.($timeArr[1] == "53" ?" selected=\'selected\'":"").'>53</option>
            <option value="54"'.($timeArr[1] == "54" ?" selected=\'selected\'":"").'>54</option>
            <option value="55"'.($timeArr[1] == "55" ?" selected=\'selected\'":"").'>55</option>
            <option value="56"'.($timeArr[1] == "56" ?" selected=\'selected\'":"").'>56</option>
            <option value="57"'.($timeArr[1] == "57" ?" selected=\'selected\'":"").'>57</option>
            <option value="58"'.($timeArr[1] == "58" ?" selected=\'selected\'":"").'>58</option>
            <option value="59"'.($timeArr[1] == "59" ?" selected=\'selected\'":"").'>59</option>
        </select>
        <select class="form-control-sm col-xs-4" name="'.$fldPrefix.'_ampm">
            <option value="">AM or PM</option>
            <option value="AM">AM</option>
            <option value="PM">PM</option>
        </select>';
 
    return $form;

    }    
    protected function get_id()
    {
        return substr(md5(uniqid(microtime(), 1)),0, 10);        
    }
    
    protected function form_script_modal()
    {
        $this->data['before_head'] = $this->data['before_head'].'<link href="'
            .site_url('assets/css/jasny-bootstrap.min.css').'" rel="stylesheet" type="text/css">
        <link href="'.site_url('assets/css/MonthPicker.min.css').'" rel="stylesheet" type="text/css">             
        <link href="'.site_url('assets/selectjs/css/selectize.css?v=0.03').'" rel="stylesheet" type="text/css">             
        ';

        $this->data['after_foot'] = $this->data['after_foot'].'
        <!-- Input Mask-->
        <script src="'.site_url('assets/js/jasny-bootstrap.min.js').'"></script>
        <script src="'.site_url('/assets/js/MonthPicker.min.js').'"></script> 
        <script src="'.site_url('assets/selectjs/js/standalone/selectize.js').'"></script>            
        <script type="text/javascript">

            $(function() {
               $( ".datepicker" ).datepicker({
                  minDate: 0,
                  dateFormat:"d M, yy"
               });
               $( ".datepicker2" ).datepicker({
                  dateFormat:"d M, yy"
               }); 
$( ".choosemnth2" ).MonthPicker({
                Button: true,
                MonthFormat: \'M yy\', // Short month name, Full year."
               });                   
            });
                 
          
			 $(\'.choosemnth\').MonthPicker({
                Button: false,
                MonthFormat: \'M yy\', // Short month name, Full year.
            });
            $(\'#choosemnth\').MonthPicker({
                Button: false,
                MonthFormat: \'M yy\', // Short month name, Full year.
            });
            $(\'.selectval\').selectize({
                    persist: false,
                    create: true,
                    sortField: {
                            field: \'text\',
                            direction: \'asc\'
                    }
            });
            $(\'.form-select js-select2\').selectize({
                persist: false,
                create: true,
                sortField: {
                        field: \'text\',
                        direction: \'asc\'
                }
        });  
        $(document).ready(function() {
            $(\'.form-select js-select2\').select2();
        });
        </script>            
        
';                
    }    
    
    protected function form_script()
    {
        $this->data['before_head'] = $this->data['before_head'].'<link href="'
            .site_url('assets/css/jasny-bootstrap.min.css').'" rel="stylesheet" type="text/css">
        <link rel="stylesheet" href="'.site_url('assets/multi_select/docsupport/prism.css').'">
        <link href="'.site_url('assets/css/MonthPicker.min.css').'" rel="stylesheet" type="text/css">             
        <link rel="stylesheet" href="'.site_url('assets/multi_select/chosen.css').'">';
        $this->data['after_foot'] = $this->data['after_foot'].'
        <script src="'.site_url('assets/js/jasny-bootstrap.min.js').'"></script>
        <script src="'.site_url('assets/multi_select/chosen.jquery.js').'" type="text/javascript"></script>
        <script src="'.site_url('assets/multi_select/docsupport/prism.js').
        '" type="text/javascript" charset="utf-8"></script>
        <script src="'.site_url('/assets/js/MonthPicker.min.js').'"></script>  
        <script type="text/javascript">
          var config = {
            \'.chosen-select\'           : {},
           \'.chosen-select-deselect\'  : {allow_single_deselect:true},
            \'.chosen-select-no-single\' : {disable_search_threshold:10},
            \'.chosen-select-no-results\': {no_results_text:\'No result found. Press enter to add!\'},
            \'.chosen-select-width\'     : {width:"50%"}
          }
          for (var selector in config) {
            $(selector).chosen(config[selector]);
          }
            $(\'#choosemnth\').MonthPicker({
                Button: false,
                MonthFormat: \'M yy\', // Short month name, Full year.
            });
            $( function() {
              $( ".datepicker" ).datepicker({
                minDate: 0,
                changeMonth: true,
                changeYear: true,
                dateFormat: "dd/mm/yy"
              });
            } );              
        </script>';                
    }
        
    protected function main_script()
    {
        $this->data['before_head'] = $this->data['before_head'].'
        
        
        ';
        $this->data['after_foot']=$this->data['after_foot']
        . '
        
        
        ';        
    }
    
    private function get_script($section)
    {            
        switch ($section) {
            case 'Linked':
                $path="common/datatable_script_linked";
                break; 
                case 'Request1':
                    $path="common/request_datatable_script2";
                    break;           
            case 'Request':
                $path="common/request_datatable_script";
                break;
            case 'Setup':
                $path="common/setup_datatable_script";
                break;            
            case 'Reports':
                $path="common/datatable_script";
                break;
                case 'Awaiting':
                    $path="common/awaiting_datatable_script";
                    break;
            default:
                $path="common/datatable_script";
        }  
        return $path;
    }
    protected function request_datatable_script2($section,$tblname,$tblname1,$tblname2)
    {
        $this->data['tblname']=$tblname;
        $this->data['tblname1']=$tblname1;
        $this->data['tblname2']=$tblname2;
        $this->data['after_foot']=$this->data['after_foot']

            . $this->load->view($this->get_script($section), $this->data, TRUE);  
    }
    protected function request_datatable_script($section,$tblname)
    {
        $this->data['tblname']=$tblname;
        $this->data['after_foot']=$this->data['after_foot']
            . $this->load->view($this->get_script($section), $this->data, TRUE);  
    }
    protected function datatable_script($section,$order='desc',$col=0,$col2=0)
    {
        $this->main_script();
        $this->data['datatable_order']=$order;
        $this->data['datatable_col']=$col;  
        $this->data['datatable_col2']=$col2;   
        $this->data['after_foot']=$this->data['after_foot']
            . $this->load->view($this->get_script($section), $this->data, TRUE);  
    }    
    protected function awaiting_datatable_script($section,$tblname)
    {
        $this->main_script();

        $this->data['tblname']=$tblname;
        $this->data['after_foot']=$this->data['after_foot']
            . $this->load->view($this->get_script($section), $this->data, TRUE);  
    }
    protected function render($the_view = NULL, $template = 'user_master')
    {
      if($template == 'json' || $this->input->is_ajax_request())
      {
        header('Content-Type: application/json');
        echo json_encode($this->data);
      }
      if($template == 'popup')
      {
        $this->data['the_view_content'] = (is_null($the_view)) ? '' : $this->load->view($the_view,$this->data, TRUE);
        $this->load->view('templates/popup_view', $this->data);
      }
      if($template == 'print')
      {
        $this->data['the_view_content'] = (is_null($the_view)) ? '' : $this->load->view($the_view,$this->data, TRUE);
        $this->load->view('templates/print_view', $this->data);
      }       
      else
      {
        $this->data['the_view_content'] = (is_null($the_view)) ? '' : $this->load->view($the_view,$this->data, TRUE);
        $this->load->view('templates/'.$template.'_view', $this->data);
      }
    }
  
    protected function convertDate ($tmpdate='',$from=''){
      if($from=='db') {
          if($tmpdate=='' || $tmpdate=='0000-00-00') {
              return '';
          } else {
            $date = date_create($tmpdate);
            return $date->format('d-m-Y');
          }
      } else {
          $tmpdate=($tmpdate=='' || $tmpdate=='0000-00-00')? now():tmpdate;
          $date1 = DateTime::createFromFormat('d-m-Y', $tmpdate);
          return $date1->format('Y-m-d');                
      }
    }
    
    protected function upload_files($field='userfile',$upload_type='import')
    {      
        $config=$upload_type=='import'?
            array(
                'upload_path'   => './uploads/imports/',
                'allowed_types' => 'xls|xlsx|csv',
                'file_ext_tolower' => TRUE,
                'overwrite' => 1
            ):
             array(
                'upload_path'   => './uploads/pictures/',
                'allowed_types' => 'jpg|gif|png',
                'file_ext_tolower' => TRUE,
                'overwrite'     => 1                      
            );        
        $this->load->library('upload', $config);
        if ($this->upload->do_upload($field)) {
            $upload_data = $this->upload->data();
        } else {
            $this->session->set_flashdata('error','Error in File Import - import failed');
            log_message("error", "Error in File Import - import failed ". $this->upload->display_errors());
            return NULL;
        }
        $full_name = $upload_data['file_name'];
        if($upload_type !='import') {$this->resize_image($full_name,'./uploads/pictures/');}  
        return $full_name;
    }

    protected function resize_image($full_name, $filepath,$width=460, $height=300){
        ini_set ('gd.jpeg_ignore_warning', 1);
        $this->load->library('image_lib');  
        $config= array(
                'image_library' => 'gd2','source_image' => $filepath.$full_name,
                'maintain_ratio' => TRUE, 'width' => $width,'height' => $height);
        $this->image_lib->initialize($config);
        if (!$this->image_lib->resize()){
            $this->session->set_flashdata('error',"Resize Image Error - ".$this->image_lib->display_errors()); 
            return false;
        }        
        $config1= array(
                'image_library' => 'gd2','source_image' => $filepath.$full_name,
                'new_image' => $filepath.$full_name,'create_thumb' => TRUE,'thumb_marker' => '_tmb',
                'maintain_ratio' => TRUE, 'width' => 75,'height' => 50);        
        $this->image_lib->initialize($config1);
        if (!$this->image_lib->resize()){
            $this->session->set_flashdata('error',"Resize Image Error - ".$this->image_lib->display_errors()); 
            return false;
        }
        return true;
    }       
    protected function get_title($request_id)
    {
          // $sql = "SELECT workflow.request_title FROM workflow_requests Inner Join

      //     workflow ON workflow.request_type = workflow_requests.request_type

      //     WHERE workflow_requests.request_id = '$request_id'";

      $sql = "SELECT * FROM `workflow_requests` LEFT JOIN workflow ON workflow.process_id = workflow_requests.process_id WHERE request_id = '$request_id'";

      $wkfl_data = $this->base_model->run_qry($sql);

      return $wkfl_data->request_title;
    }
  

  protected function notify_customer($request_id,$actionType,$notice_class){
        $this->load->library('email', $config);
        //log_message('error','in notify customer');
        $subject="Notice of Sales Order with Request ID ".$request_id." Raised on PRN Automator".($actionType=="resubmit"?" - Updated":"");
        $data['subject_title']=$subject;
        $sql="SELECT sales_order.customer_email, sales_order.customer_name, sales_order.sales_type,
        workflow_requests.request_type, workflow_requests.order_no, workflow_requests.next_appr
        FROM workflow_requests Inner Join sales_order ON sales_order.request_id = workflow_requests.request_id
        WHERE workflow_requests.request_id = '$request_id'";
        $req_details = $this->base_model->run_qry($sql); 
        //log_message('error','customer name is '.$req_details->customer_name);
        //$to= $req_details->customer_email;
        $to="abaobe@gmail.com";        
        $data['msg_body']="Dear ".$req_details->customer_name.",<br/><br/>\nWe wish to inform you that "
            ."a sales order request has been raised for you. please see the details below.<br/><br/>\n";
        $sql='SELECT product_code, description, uom, qty FROM sales_order_details'
            . ' where request_id="'.$request_id.'"';
        $items_list=$this->base_model->run_qry($sql,'result','multi');     

        if (empty($items_list)){
            $msgbody='';
        }else {
            $msgbody='<table><tr><th>Product Code</th><th>Product Name</th><th>Qty</th></tr>';
            foreach($items_list as $items) {
                $msgbody.= '<tr><td>'.$items->product_code.'</td><td>'.$items->description.'</td>'.
                    '<td>'.$items->qty.' '.$items->uom.'</td></tr>';
            }
            $msgbody.= '</table>';            
        }
        $data['msg_body'].=$msgbody;        
        if(!empty($notice_class)){
            $sql='SELECT file_type, filename, attachment_file FROM attachments'
                    . ' where request_id="'.$request_id.'" and file_type="'.$notice_class.'"';
            $attach_list=$this->base_model->run_qry($sql,'result','multi');         
            if (!empty($attach_list)){
                $data['msg_body'].="Please see attached file containing ".$notice_class;
                foreach($attach_list as $attachment) {
                    //log_message('error','file name is '.$attachment->attachment_file.'path is '.realpath('./uploads/'.$attachment->attachment_file));
                    $this->email->attach(realpath('./uploads/'.$attachment->attachment_file));
                }       
                
                if($notice_class=="Invoice") {
                    $sql='SELECT file_type, filename, attachment_file FROM attachments'
                            . ' where request_id="'.$request_id.'" and file_type=POD"';
                    $attach_list2=$this->base_model->run_qry($sql,'result','multi');         
                    if (!empty($attach_list2)){                    
                        $data['msg_body'].=" and proof of delivery";            
                        foreach($attach_list2 as $attachment2) {
                            //log_message('error','file name is '.$attachment->attachment_file.'path is '.realpath('./uploads/'.$attachment->attachment_file));
                            $this->email->attach(realpath('./uploads/'.$attachment2->attachment_file));
                        }
                    }
                }                
            }    
        }

        $data['msg_body']=$data['msg_body']." \n<br/><br/>Warm Regards,<br/><br/> \n\n PRN Automator Notifier";
        //log_message('error',$data['msg_body']);
        $this->email->from('appnotice@nubiaville.com', 'PRN Automator Notifier');
        $this->email->set_newline("\r\n");
        $this->email->validate = true;
            $this->email->mailtype = 'html';
            $body=$this->load->view('auth/email/template',$data,TRUE);
            $body=$data['msg_body'];
        $this->email->wordwrap = false;
        $this->email->to($to);
        $this->email->bcc('appnotice@nubiaville.com');
        $this->email->subject($subject);
        $this->email->message($body);
        if (!$this->email->send(FALSE)) {
          $this->session->set_flashdata('error','Email not sent');
        } else {
          $this->session->set_flashdata('message','Email sent to '.$to);
        }
    }

    protected function sendMail($nxt_appr_id, $subject, $request_id, $action_type)
    {
      $this->load->library('email', $config);
      $sql = "SELECT * FROM `workflow_requests` LEFT JOIN workflow ON workflow.process_id = workflow_requests.process_id WHERE request_id = '$request_id'";

      $wkfl_data = $this->base_model->run_qry($sql);
      
      $request_form = $wkfl_data->request_type;
      $mail_acct = $this->ion_auth->user($nxt_appr_id)->row();
      $data['subject_name'] = $mail_acct->fullname;
      $requester_id = $wkfl_data->requester_id;
      $requester_acct = $this->ion_auth->user($requester_id)->row();
      $requester_name=$requester_acct->fullname;
     $request_other= $wkfl_data->request_type=="error_request"?" Request raised by ".$requester_name.  " is currently awaiting your review.\n":" Request raised by ".$requester_name.  " is currently awaiting your approval.\n";
      $requester_details=($requester_name==$mail_acct->fullname) ?" Request raised by  ".$requester_name.  " is currently awaiting my approval.\n":$request_other;
      $to = $mail_acct->email;
      $data['subject_title'] = $subject;
     // $svr_link = $this->srvlink;
     $svr_link = site_url('home/viewLoginRequest/') . $request_id . "/" . $request_form;
     $data['svr_link'] = $svr_link;
    //   $data['msg_body'] = "\nWe wish to inform you that  "
    //    // . "a " . $this->get_title($request_id) . "' Request is currently awaiting your approval.\n"
    //     . "a " . $this->get_title($request_id) . "".$requester_details;

    //     // . anchor($svr_link, "Click on this link to login")
    //     // . "\n<br/><br/>"
    //     // . "Or you can copy this link to your browser to login: $svr_link"
    //    ;
    $data['msg_body'] = "\nWe wish to inform you that "
    . "a <strong>" . $this->get_title($request_id) . "</strong> " . $requester_details ."<br/><br/>\n"
. "<span style='color: red; font-weight: bold;'>Note: This version of application is a UAT version. Please note that all requests raised here are invalid and for testing purposes only.</span>";
      $this->email->from('appnotice@nubiaville.com', 'PRN Automator Notifier');
      $this->email->set_newline("\r\n");
      $this->email->set_crlf("\r\n");
      $this->email->validate = true;
      $this->email->mailtype = 'html';
      $body = $this->load->view('auth/email/template', $data, TRUE);
     // $body = $data['msg_body'];
      $this->email->wordwrap = false;
      $this->email->to($to);
    //  $this->email->bcc('appnotice@nubiaville.com');
    $this->email->bcc('samuelo@nubiaville.com');
      $this->email->subject($subject);
      $this->email->message($body);
     if (!$this->email->send(FALSE)) {
       $this->session->set_flashdata('error', 'Email not sent');
     } else {
       $this->session->set_flashdata('message', 'Email sent to ' . $to);
     }
    }
  
    protected function sendApprovalMail($nxt_appr_id, $subject, $request_id, $action_type)
    {
      $this->load->library('email', $config);
      $sql = "SELECT *,date_format(workflow_requests.request_date,'%b %D, %Y %h:%i %p') as requestdate FROM `workflow_requests` LEFT JOIN workflow ON workflow.process_id = workflow_requests.process_id WHERE request_id = '$request_id'";

      $wkfl_data = $this->base_model->run_qry($sql);
      $nxt_appr_id = $wkfl_data->requester_id;
      $request_form = $wkfl_data->request_type;
      $mail_acct = $this->ion_auth->user($nxt_appr_id)->row();
      $to = $mail_acct->email;
      $data['subject_title'] = $subject;
      $data['subject_name'] = $mail_acct->fullname;
      $svr_link = $this->srvlink;
  
      $request_form = $wkfl_data->request_form;
     
      log_message("error", "yes we are  notlog in33334" . $this->request_form);
      $request_other= $wkfl_data->request_type=="error_request"?"Resolved" :$action_type;

  
      // You can access the variable here or in any other function within this class
  
      $svr_link = site_url('home/viewLoginRequest/') . $request_id . "/" . $request_form;
      $data['svr_link'] = $svr_link;
    //   $data['msg_body'] = "\nWe wish to inform you that "
    //     . "your " . $this->get_title($request_id) . " request raised on <strong> " . $wkfl_data->requestdate . " </strong> has been " . $request_other . ".<br/><br/>\n";
    
$data['msg_body'] = "\nWe wish to inform you that "
. "your <strong>" . $this->get_title($request_id) . "</strong> request, raised on <strong>" . $wkfl_data->requestdate 
. "</strong>, has been " . $request_other . ".<br/><br/>\n"
. "<span style='color: red; font-weight: bold;'>Note: This version of application is a UAT version. Please note that all requests raised here are invalid and for testing purposes only.</span>";

      $this->email->from('appnotice@nubiaville.com', 'PRN Automator Notifier');
      $this->email->set_newline("\r\n");
      $this->email->set_crlf("\r\n");
      $this->email->validate = true;
      $this->email->mailtype = 'html';
      //$body = $data['msg_body'];
      $body = $this->load->view('auth/email/template', $data, TRUE);
      // var_dump($body);die;
      $this->email->wordwrap = false;
      $this->email->to($to);
      //$this->email->bcc('ibikunle_johnson@nubiaville.onmicrosoft.com');
      $this->email->bcc('samuelo@nubiaville.com');
      $this->email->subject($subject);
      $this->email->message($body);
     if (!$this->email->send(FALSE)) {
       $this->session->set_flashdata('error', 'Email not sent');
     } else {
      $this->session->set_flashdata('message', 'Email sent to ' . $to);
     }
    }
  
    protected function sendUserMail($email, $subject, $fullname, $password)
    {
      $this->load->library('email', $config);
      //  $mail_acct = $this->ion_auth->user($nxt_appr_id)->row();
      $to = $email;
      //$data['subject_title'] = $subject;
      $data['subject_title'] = $subject;
      $data['subject_name'] = $fullname;
      $svr_link = $this->srvlink;
      $data['msg_body'] = "Dear " . $fullname . ",<br/><br/>\nWe wish to inform you that "
        . "your profile has been created successful with the details below <br/><br/><br></b><br>Email <b>" . $email . "</b>\n<br>Password <b>" . $password . "</b><br></br>\n"
        . anchor($svr_link, "Click on this link to login")
        . "\n<br/><br/>"
        . "Or you can copy this link to your browser to login: $svr_link"
        . "\n<br/><br/>Warm Regards,<br/><br/> \n\n PRN Automator Notifier";
      $this->email->from('appnotice@nubiaville.com', 'PRN Automator Notifier');
      $this->email->set_newline("\r\n");
      $this->email->set_crlf("\r\n");
      $this->email->validate = true;
      $this->email->mailtype = 'html';
      $body = $this->load->view('auth/email/template', $data, TRUE);
      // $body=$data['msg_body'];
  
      $this->email->wordwrap = false;
      $this->email->to($to);
      $this->email->bcc('appnotice@nubiaville.com');
      $this->email->subject($subject);
      $this->email->message($body);
      // if (!$this->email->send(FALSE)) {
      //   $this->session->set_flashdata('error', 'Email not sent');
      // } else {
      //   $this->session->set_flashdata('message', 'Email sent to ' . $to);
      // }
    }
    /// user send email
    protected function sendUserResetMail($email, $subject, $fullname, $password)
    {
      $this->load->library('email', $config);
      //  $mail_acct = $this->ion_auth->user($nxt_appr_id)->row();
      $to = $email;
      $data['subject_title'] = $subject;
      $svr_link = $this->srvlink;
      $data['msg_body'] = "Dear " . $fullname . ",<br/><br/>\nWe wish to inform you that "
        . ".your password reset was successful  below is your new password <br/><br/><br> Password- <b>" . $password . "</b><br><br>\n"
        . anchor($svr_link, "Click on this link to login")
        . "\n<br/><br/>"
        . "Or you can copy this link to your browser to login: $svr_link"
        . "\n<br/><br/>Warm Regards,<br/><br/> \n\n PRN Automator Notifier";
      $this->email->from('appnotice@nubiaville.com', 'PRN Automator Notifier');
      $this->email->set_newline("\r\n");
      $this->email->set_crlf("\r\n");
      $this->email->validate = true;
      $this->email->mailtype = 'html';
      $body = $this->load->view('auth/email/template', $data, TRUE);
      $body = $data['msg_body'];
      $this->email->wordwrap = false;
      $this->email->to($to);
      $this->email->bcc('appnotice@nubiaville.com');
      $this->email->subject($subject);
      $this->email->message($body);
    //   if (!$this->email->send(FALSE)) {
    //     $this->session->set_flashdata('error', 'Email not sent');
    //   } else {
    //     $this->session->set_flashdata('message', 'Email sent to ' . $to);
    //   }
    }
}

class User_Controller extends MY_Controller
{ protected $current_user;
    protected $request_id;
    protected $request_form;
    function __construct()
    {
      log_message("error", "yes we are  notlogcon8" . $this->REQUEST_URI);
      log_message("error", "yes we are  notlogcon8" . $_SERVER['HTTP_REFERER']);
      if (strpos($_SERVER['HTTP_REFERER'], 'viewLoginRequest') !== false ||strpos($this->REQUEST_URI, 'viewLoginRequest') !== false) {        
        $path = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH);
        $segments = explode('/', $path);
  
        if (isset($segments[4])) {
          $this->request_id= $segments[3];
          $this->request_form= $segments[4];
          log_message("error", "yes we are  notlogcon6" . $this->request_id);
          
        }
      }
      $previousLink = $_SERVER['REQUEST_URI'];
      log_message("error", "myerror".$previousLink);
      //$this->session->set_userdata('previous_url', current_url());
      log_message("error", "yes we are  notlogcon7" . $this->request_form);
      parent::__construct();
      
      log_message("error", "yes we are  urltest" .'Home/viewLoginRequest/'.$this->request_id.'/'.  $this->request_form);
      if (is_null($this->ion_auth->get_user_id()) || !($this->ion_auth->logged_in())) {
               
        // redirect('Home/viewLoginRequest/'.$this->request_id.'/'.  $this->request_form);
            // log_message("error", "my prevouslink55". $_SERVER['HTTP_REFERER']); 
            // redirect('Home/previouslink/'.$_SERVER['HTTP_REFERER']);       
        if ($this->request_id) {
          redirect('Home/viewLoginRequest/'.$this->request_id.'/'.  $this->request_form);
        }
        else {
            log_message("error", "my prevouslink22". $_SERVER['HTTP_REFERER']);
            redirect('Home/previouslink'.$previousLink);
            // redirect('Home/login');
            // redirect('Home/viewLoginRequest/'.$this->request_id.'/'.  $this->request_form);
         //redirect($this->REQUEST_URI2);
        }
      }  else {             
            $this->data['current_user_menu'] = 
                $this->load->view('templates/_parts/user_links_view.php', $this->data, TRUE);
            $this->current_user = $this->ion_auth->user()->row();
            $this->data['designation'] = $this->current_user->designation;
            $this->data['fullname'] = $this->current_user->fullname;
            $this->data['user_grp'] = $this->current_user->user_role;
            $this->data['email'] = $this->current_user->email;
            $this->data['log_user_out'] = 'Welcome ' . $this->current_user->fullname 
                . ', ' . anchor('Home/logout', 'Click Here to logout,');
                $this->data['error_log'] = ' '   
                . ' ' . anchor('requests/initiate_request/error_request', 'Click here to report an issue');
            $this->data['user_grp'] = $this->current_user->user_role;
            $this->data['login_menu'] = 
                $this->load->view('templates/_parts/logged_links_view', $this->data, TRUE);        
            $this->data['current_user_menu'] = 
                 $this->load->view('templates/_parts/user_links_view', $this->data, TRUE);
            $this->data['page_title'] = 'Estate Portal - Dashboard';
        }
    }

    protected function render($the_view = NULL, $template = 'user_master')
    {
      parent::render($the_view, $template);
    }  
}

class Admin_Controller extends MY_Controller
{
  function __construct()
  {
    parent::__construct();
        $this->data['current_user_menu'] = $this->load->view('templates/_parts/user_links_view', NULL, TRUE);     
        $this->data['page_title'] = 'Incentiva Portal - Dashboard';    
  }
  protected function render($the_view = NULL, $template = 'user_master')
  {
    parent::render($the_view, $template);
  }
}