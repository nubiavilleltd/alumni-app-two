<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Request extends User_Controller
{
    function __construct()
    {
        parent::__construct();
    }
    
    public function index()
    {     
        redirect('Rewards/winners_list');
    }   
    

    public function approve_list($id)
    {       
        $request_details = $this->base_model->get_single_record($id, 'request_id','workflow_requests'); 
        if($request_details->request_status==='Approved'){
            $this->print_list($id);
        } else {
            if ($this->ion_auth->get_user_id()== $request_details->next_appr
                    && $this->ion_auth->get_user_id()== $request_details->requester_id
                    && $request_details->request_status==='Awaiting Approval'){
                $req_data = array(        
                    'request_status'=> 'Approved',
                    'next_appr'  => NULL,            
                    'next_approver' => NULL,
                    'order_no'     => NULL
                );
                $this->base_model->update($req_data,$request_id,'workflow_requests',array('request_id' => $id));  
                $this->update_history($id, 'Initiator','Voucher Generation (Initiator)',
                     'Winners list Approved - Vouchers Generated',NULL,NULL);   
                $this->print_list($id);
            } else {
                $this->session->set_flashdata('error','Error printing winners list - please check approval status');
                redirect('rewards/view_winners_list');         
            }
        }        
    }

    private function print_list($id)
    {       
        $sql="SELECT winners_list.market from winners_list WHERE winners_list.list_id = '$id'";
        $list = $this->base_model->run_qry($sql);
        if ($list->market == "Nigeria") {
            redirect('Rewards/generate_pdf/'.$id);   
        } else {
            redirect('Rewards/wamreport/'.$id);               
        }
    }   

    public function invalid_process($request_id){ 
        echo "Request with Id $request_id has generate an error . Please ensure the follwoing<br/><br/>
            1. That the request Id is correct, <br/><br/>
            2. That a functional workflow path has been defined for the Market under 
            which the request was raise.<br/><br/>
            ";
        log_message('error',$request_id." is the requestr id for this bad request<br><br><br><br>");
        redirect('Rewards/winners_list');
    }
    
    
    public function re_route($request_id){ 
        $this->form_validation->set_rules('comment','comments','trim');  
        
        if($this->form_validation->run() === true){    
            $comment=$this->input->post('comment');
            $approver_name=$this->input->post('approver_name');
            $this->base_model->run_qry("CALL REROUTE_REQUEST(".$approver_name.
                ",'".$request_id."','".$this->base_model->get_name($approver_name)."')",'run');            
            $this->update_history($request_id, 'Re-router','Re-routing Request',$comment,$approver_name, 'Re-routed'); 

            $subject = "A Winners list is currently awaiting your approval";
            $this->sendMail($approver_name,$subject,$request_id,'re_route');     

            redirect('Rewards/view_winners_list');
        }
    }
    
    public function action_request($process_id, $request_id){ 
        $this->form_validation->set_rules('comment','comments','trim');  
        $this->form_validation->set_rules('actionType','actionType','trim|required');  
        
        if($this->form_validation->run() === true){    
            $comment=$this->input->post('comment');
            $action_type=$this->input->post('actionType');
            
            $this->validated_request($process_id,$request_id,$comment,$action_type);
            redirect('Rewards/view_winners_list');
        }
    }
    
    private function validated_request($process_id,$request_id,$comment,$action_type){
        $request_details = $this->base_model->get_single_record($request_id, 'request_id','workflow_requests');        
        switch ($action_type){
            case "submit":
                $this->base_model->run_qry("CALL LOAD_APPROVERS(".$this->ion_auth->get_user_id().
                        ",".$process_id.",'".$request_id."')",'run');
                $appr_details=$this->get_appr_by_order($request_id);
                !empty($request_details) || empty($appr_details->appr_name)?$this->invalid_process($request_id):
                    $this->submit_request($appr_details, $process_id, $request_id,$comment);
            break;
            case "resubmit":                
            case "approve":
            case "deny":
            case "return":
                $appr_details = $this->base_model->get_single_record($request_id,'request_id',
                    'request_approvers','', array('request_id'=>$request_id,
                    'appr_name'=>$this->ion_auth->get_user_id()));  
                empty($appr_details->appr_name) && empty($request_details)?
                    $this->invalid_process($request_id):
                    $this->process_request($action_type,$appr_details, $process_id,$request_id,$comment);     
                break;
            default:
                $this->invalid_process($request_id);
                break;
        }        
    }
    
    private function get_appr_by_order($request_id,$order_no=1){  
        $appr_details = $this->base_model->get_single_record($request_id,
            'request_id','request_approvers','',
            array('request_id'=>$request_id,'order_no'=>$order_no)); 
        return $appr_details;
    }
    
    private function submit_request($next_appr, $process_id, $request_id,$comment){
        $req_data = array(
            'request_id'    => $request_id,
            'process_id'    => $process_id,
            'request_date'  => date('Y-m-d H:i:s'),
            'requester_id'  => $this->ion_auth->get_user_id(),            
            'request_status'=> 'Awaiting Approval',
            'order_no'       => 1,
            'next_appr'  => $next_appr->appr_name,            
            'next_approver' => $this->base_model->get_name($next_appr->appr_name)
        );
        $this->base_model->insert($req_data,'workflow_requests'); 
        $this->update_history($request_id, 'Initiator','Submitted (Initiator)',
                (empty($comment)?'Winners list submitted - Awaiting Approval':$comment),
            $next_appr->appr_name,$next_appr->appr_function); 
        
           $subject = "A Winners list is currently awaiting your approval";
            $this->sendMail($next_appr->appr_name,$subject,$request_id,'submit');         
    }

    private function process_request($action_type, $appr_details, $process_id, $request_id,$comment){
        $request_details = $this->base_model->get_single_record($request_id, 'request_id','workflow_requests');        
        switch ($action_type) {
            case 'resubmit':
                $appr_status='Awaiting Approval';
                $action_status='Re-submitted (Initiator)';
                $appr_function='Initiator';
                $order_no=1;
                $next_appr=$this->get_appr_by_order($request_id,$order_no);
                $next_appr_id=$next_appr->appr_name;
                $next_appr_function=$next_appr->appr_function;
                $nxt_appr_id=$next_appr_id;
                $subject = "A Winners list is currently awaiting your approval";                
                break;            
            case 'approve':
                $order_no=$appr_details->order_no + 1;
                $total_data=$this->base_model->rec_count('request_approvers',$request_id,'request_id');
                //log_message("error", $total_data.' is the record count '.$order_no.' is the order number');
                $appr_status=$order_no>$total_data?'Approved':'Awaiting Approval';
                $appr_function=$appr_details->appr_function;
                $action_status=$order_no>$total_data?'Approval complete':'Approved';
                $order_no=$appr_details->order_no + 1;
                $next_appr=$this->get_appr_by_order($request_id,$order_no);
                $next_appr_id=$next_appr->appr_name;
                $next_appr_function=$next_appr->appr_function;
                
                $nxt_appr_id=$next_appr_id;
                $subject = "A Winners list is currently awaiting your approval";                      
                break;
            case 'deny':
                $appr_status='Denied';
                $appr_function=$appr_details->appr_function;
                $action_status='Denied';
                $order_no=NULL;
                $next_appr_id=NULL;
                $next_appr_function=NULL;  
                
                $nxt_appr_id=$request_details->requester_id;
                $subject = "Your Winners list has been denied";      
                break;
            case 'return':
                $appr_status='Returned to';
                $appr_function=$appr_details->appr_function;
                $action_status='Returned';
                $order_no=0;
                $next_appr_id=$request_details->requester_id;
                $nxt_appr_id=$request_details->requester_id;
                $subject = "Your Winners list has been returned";                
                $next_appr_function='Initiator';              
                break;    
        }     
    
        $req_data = array(        
            'request_status'=> $appr_status,
            'next_appr'  => $next_appr_id,            
            'next_approver' => empty($next_appr_id)?'':$this->base_model->get_name($next_appr_id),
            'order_no'     => $order_no
        );
        $this->base_model->update($req_data,$request_id,'workflow_requests',array('request_id' => $request_id));  
        $this->update_history($request_id, $appr_function, 
            $action_status,$comment,$next_appr_id, $next_appr_function);    
        
        $this->sendMail($nxt_appr_id,$subject,$request_id,$action_type);         
    }   
       
    private function update_history($request_id,$function,$appr_status,$appr_comment, $next_appr_id, $appr_function){
        $req_data = array(
            'request_id'    => $request_id,
            'appr_id'       => $this->ion_auth->get_user_id(),
            'appr_name'     => $this->base_model->get_name($this->ion_auth->get_user_id()),
            'appr_function' => $function,
            'appr_status'   => $appr_status,
            'appr_comment'  => $appr_comment, 
            'appr_date'     => date('Y-m-d H:i:s'),            
            'next_appr_id'  => $next_appr_id,          
            'next_approver' => (empty($next_appr_id)?'':
                $this->base_model->get_name($next_appr_id).' ('.$appr_function.')')
        );
        $this->base_model->insert($req_data,'workflow_history');           
    }    
        
    protected function sendMail($nxt_appr_id,$subject,$request_id,$action_type){
        $this->load->library('email', $config);
        $list_details = $this->base_model->get_single_record($request_id, 'list_id','winners_list');
        $mail_acct = $this->ion_auth->user($nxt_appr_id)->row();
        $to= $mail_acct->email;
        $data['subject_title'] =$subject;
        $svr_link="http://10.16.9.6:8022/batincentiva/";        
        $data['msg_body']="Dear ".$mail_acct->first_name.",<br/><br/>\nWe wish to inform you that "
            .$list_details->cust_type." ".$list_details->campaign." \n List for ".$list_details->fullmonth
            ." is currently awaiting your approval.<br/><br/>\n"
            .anchor($svr_link,"Click on this link to login")
            . "\n<br/><br/>"
                . "Or you can copy this link to your browser to login: $svr_link"
                . "\n<br/><br/>Warm Regards,<br/><br/> \n\n BAT Incentiva Notifier";

        $this->email->from('lagossupport@bat.com', 'BAT Incentiva Notifier');
        $this->email->set_newline("\r\n");
        $this->email->validate = true;
            $this->email->mailtype = 'html';
            $body=$this->load->view('auth/email/template',$data,TRUE);
            $body=$data['msg_body'];
        $this->email->wordwrap = false;
        $this->email->to($to);
        $this->email->subject($subject);
        $this->email->message($body);
        if (!$this->email->send(FALSE)) {
          $this->session->set_flashdata('email','Email not sent');
        } else {
          $this->session->set_flashdata('email','Email sent to '.$to);
        }
    }
}