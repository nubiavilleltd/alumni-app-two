<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Requests extends User_Controller
{
    function __construct()
    {
        parent::__construct();
        $this->data['data_access']=array("Add","Edit","Delete");
    }
    
    public function index()
    {     
        redirect('requests/dashboard');
    }   
    
    public function dashboard()
    {       
        redirect('requests/request_view');
        //$this->data['page_title'] = 'Automator Dashboard';                 
        //$this->render('requests/dashboard_view');
    }    
    
    public function ajax_action($action,$data_table,$request_id,$data_key=FALSE)
    {
        //log_message('error',var_export($_POST, true));
        //log_message('error',$action.','.$data_table.','.$request_id.','.$data_key);
        switch ($data_table) {            
            case "travel_cost": 
                $this->update_cost($action,$data_table,$request_id,$data_key);
                break;            
            case "travel_itinerary":   
                $this->update_itinerary($action,$data_table,$request_id,$data_key);
                break;    
            case "claims_details":   
                $this->update_claim_details($action,$data_table,$request_id,$data_key);
                break;            
        }
    }    
    public function ajax_list($data_table,$id='')
    {        
        header('Content-Type: application/json');  
        switch ($data_table){
            case "users":
                $sqla = 'SELECT * FROM users';
                $temp_data=$this->base_model->run_qry($sqla,'qry');
                $total_data=$temp_data->num_rows();
                $total_data=is_null($total_data)?0:$total_data;
                $main_data=$total_data==0?array():$temp_data->result();    
                break;   
            case "journals":            
                $temp_data=$this->base_model->run_qry("CALL GET_JOURNAL_DETAILS()",'qry');
                $total_data=$temp_data->num_rows();
                $total_data=is_null($total_data)?0:$total_data;
                $main_data=$total_data==0?array():$temp_data->result();    
                break;    
            case "travel_cost":            
                $sqla = 'SELECT travel_cost.*, FORMAT(exp_amt,2) as expamt FROM travel_cost where request_id="'.$id.'"';
                $temp_data=$this->base_model->run_qry($sqla,'qry');
                $total_data=$temp_data->num_rows();
                $total_data=is_null($total_data)?0:$total_data;
                $main_data=$total_data==0?array():$temp_data->result();    
                break;   
            case "claims_details":        
                $sqla = 'SELECT claims_details.*, FORMAT(claim_amt,2) as claimamt FROM claims_details '
                        . ' where request_id="'.$id.'"';
                $temp_data=$this->base_model->run_qry($sqla,'qry');
                $total_data=$temp_data->num_rows();
                $total_data=is_null($total_data)?0:$total_data;
                $main_data=$total_data==0?array():$temp_data->result();    
                break;        
            case "card_details":        
                $sqla = 'SELECT card_details.*, FORMAT(trans_amt,2) as transamt FROM card_details '
                        . ' where request_id="'.$id.'"';
                $temp_data=$this->base_model->run_qry($sqla,'qry');
                $total_data=$temp_data->num_rows();
                $total_data=is_null($total_data)?0:$total_data;
                $main_data=$total_data==0?array():$temp_data->result();    
                break;                
            
            case "workflow_requests":    
                $sqla = '            
                SELECT
                workflow_requests.request_id, workflow_requests.request_date,
                workflow_requests.request_type, workflow.request_title,
                date_format(workflow_requests.request_date,"%b %D, %Y %h:%i %p") as requestdate,
                users.fullname, workflow_requests.request_status, workflow_requests.next_approver
                FROM workflow_requests
                Inner Join workflow ON workflow.process_id = workflow_requests.process_id
                Inner Join users ON users.id = workflow_requests.requester_id';

                $temp_data=$this->base_model->run_qry($sqla,'qry');
                $total_data=$temp_data->num_rows();
                $total_data=is_null($total_data)?0:$total_data;
                $main_data=$total_data==0?array():$temp_data->result(); 
                break; 
            default:              
                $total_data=$this->base_model->rec_count($data_table);
                $total_data=is_null($total_data)?0:$total_data;
                $main_data=$this->base_model->get_record($data_table);
                $main_data=$total_data==0?array():$main_data;
            break;
        }
        $json_data = array(
            "recordsTotal"    => intval( $total_data ),  // total number of records
            "recordsFiltered" => intval( $total_data ), // total number of records after searching, if there is no searching then totalFiltered = totalData
            "data"            => $main_data   // total data array
            );
        echo json_encode($json_data); 
    }    
    
    public function ajax_edit($data_table,$data_key,$id)
    {
        $urlval=urldecode($id);
        switch ($data_table){
            case "travel_itinerary":
                $sqla = 'SELECT travel_itinerary.*, date_format(departdate,"%d/%m/%Y") as depart_date,'
                    . 'date_format(departdate,"%d/%m/%Y") as arrive_date, date_format(departdate,"%h") as depart_hr,'
                    . ' date_format(departdate,"%i") as depart_min, date_format(departdate,"%p") as depart_ampm,'
                    . ' date_format(arrivedate,"%h") as arrive_hr, date_format(arrivedate,"%i") as arrive_min, '
                    . 'date_format(arrivedate,"%p") as arrive_ampm  FROM travel_itinerary where it_id='.$id;
                $data=$this->base_model->run_qry($sqla); 
                break;   
            case "travel_cost":
                $sqla = 'SELECT travel_cost.*, FORMAT(exp_amt,2) as expamt FROM travel_cost where cost_id='.$id;
                $data=$this->base_model->run_qry($sqla); 
                break;             
            
            default:              
                $data = $this->base_model->get_single_record($urlval,$data_key,$data_table); 
            break;     
        }
        echo json_encode($data);
    }  
    
    public function ajax_delete($data_table,$data_key)
    {       
        $rows=$this->input->post('rows');
        foreach($rows as $row){
            $this->base_model->delete($data_table,array($data_key => $row));
            if($data_table=='workflow_approvers'){
                $this->base_model->delete('workflow_alt_approvers',array($data_key => $row));
            }
        }
        echo json_encode(array("status" => TRUE)); 
    } 

    public function journal_view()
    {       
        $this->data['page_title'] = 'View All Journal Entries';   
        $this->data['cols'] = "<th>Posting Date</th><th>Document Type</th><th>Document No.</th>"
            . "<th>Account Type</th><th>Account No.</th><th>Description</th><th>Amount</th>"
            . "<th>Department</th><th>SKU</th>";
        $this->data['data_columns']='{"data": "post_date"},{"data": "doctype"},{"data": "docno"},'
            . '{"data": "acct_type"},{"data": "acct_no"},{ "data": "description" },'
            . '{ "data": "amount" },{"data": "dept" },{"data": "sku" }';     
        $this->data['data_table']="journals";  
        $this->data['data_key']="request_id"; 
        $this->data['data_section']="requests";
        $this->data['mini']=FALSE;   
        $this->datatable_script('Linked','asc',1);
        $this->render('requests/journal_view');
    } 
    
    public function request_view()
    {       
        $this->data['page_title'] = 'View All Requests';   
        
        $this->data['cols'] = "<th>ID</th><th></th><th>Request Date</th><th>Request Type</th>"
                . "<th>Requested By</th><th>Status</th><th>Next Approver</th>";
        $this->data['data_columns']='{"data": "request_date","visible": false  },'
                . '{"data": "request_id","visible": false  },{ "data": "requestdate" },'
                . '{ "data": "request_title" },{"data": "fullname" },{"data": "request_status" },'
                . '{ "data": "next_approver" }';
        
        $this->data['columnDefs'] = ', 
            "columnDefs": [
                    {
                        "render": function ( data, type, row ) {
                            return \'<a href="' . site_url('requests/request_form/') . '/\'+ row[\'request_type\'] +\'/\'+ row[\'request_id\'] +\'">\'+data+\'</a>\';         
                        },
                        "targets": "_all"
                    }
                 ]';        
        $this->data['data_table']="workflow_requests";  
        $this->data['data_key']="request_id"; 
        $this->data['data_section']="requests";
        $this->data['mini']=FALSE;   
        $this->datatable_script('Linked','asc',1);
        $this->render('requests/request_view');
    }  
    public function request_list()
    {       
        $this->data['page_title'] = 'List All Requests';                 
        $this->render('requests/request_list');
    }    
    public function awaiting_approval()
    {       
        $this->data['page_title'] = 'Requests Awaiting My Approval';                 
        $this->render('requests/awaiting_approval');
    }    

    public function initiate_request($request_type){ 
        $sql="SELECT workflow.* from workflow WHERE request_type = '$request_type'";
        $wkfl_list = $this->base_model->run_qry($sql);
        $request_id=$this->get_id();
        $workflow_data = array(
            'request_id'    => $request_id,
            'process_id'    => $wkfl_list->process_id,
            'request_type'    => $wkfl_list->request_type,
            'request_date'  => date('Y-m-d H:i:s'),
            'requester_id'  => $this->ion_auth->get_user_id(),            
            'request_status'=> 'New','order_no'=> 0,'next_appr'=> 0,'next_approver' => ''
        );
        $request_data = array(
            'request_id'    => $request_id,
            'request_date'  => date('Y-m-d H:i:s'),
            'iniitiated_by'  => $this->ion_auth->get_user_id(),
            'date_updated'  => date('Y-m-d H:i:s')
        );
        $this->db->trans_start();
        $this->base_model->insert($workflow_data,'workflow_requests');
        $this->base_model->insert($request_data, $wkfl_list->request_table); 
        $this->db->trans_complete();
        redirect('requests/request_form/'.$request_type.'/'.$request_id); 
    }
    public function request_form($request_type, $request_id)
    {
        $this->form_script_modal();
        $this->main_script();        
        $this->data['datatable_order']='desc';
        $this->data['datatable_col']=0; 
        $this->data['data_var'] = $request_id;
        $this->data['data_section']="requests";           
        $sql="SELECT workflow.* from workflow WHERE request_type = '$request_type'";
        $wkfl_list = $this->base_model->run_qry($sql);
        $this->data['wkfl_list']=$wkfl_list;
        $this->data['page_title'] = $wkfl_list->request_title.' Form';         
        $sql="SELECT * from  workflow_requests WHERE request_id = '$request_id'";
        $wkfl_request = $this->base_model->run_qry($sql); 
        $this->data['wkfl_request'] = $wkfl_request;
        $this->data['initated_by'] = $this->ion_auth->user($wkfl_request->requester_id)->row();        
         $sql="SELECT users.id as value, concat(users.first_name,' ',users.last_name) as 'name' "
                . "FROM users where active=1 and user_role='Approver' order by users.first_name asc";      

         $this->data['dept'] = $this->base_model->get_parameters_menu('Departments',
                'dept',$this->data['initated_by']->dept,'Departments');          
        $this->data['approver_name'] = $this->base_model->get_record_menu($sql,
            'approver_name','','Re-route to Approver');
        $sql='SELECT users.fullname as approver_name,
            request_approvers.appr_name,request_approvers.appr_function
            FROM request_approvers left Join users ON users.id = request_approvers.appr_name
            where request_approvers.request_id=\''.$request_id.'\'';
        $this->data['approvers_list']=$this->base_model->run_qry($sql,'result','multi');  
        $this->data['workflow_history']=$this->base_model->get_single_record(
            $request_id,'request_id','workflow_history','multi');
        $this->data['approvers_count']=$this->base_model->rec_count('request_approvers',
            $request_id,'request_id');    
        $tblname=$wkfl_list->request_table;
        $this->$request_type($request_id,$tblname);
    }
  
    private function request_travel($request_id,$tblname)
    {        
        $sql="SELECT * from  ".$tblname." WHERE request_id = '$request_id'";
        $req_details = $this->base_model->run_qry($sql);    
        $this->data['req_details'] =$req_details;
        $this->data['update_request']='requests/update_travel/';
        $this->data['currency'] = $this->base_model->get_parameters_menu('Currency',
                'currency',$req_details->currency,'Currency');  
       
        $this->data['traveltype'] = $this->base_model->get_parameters_menu('Travel Type',
                'traveltype',$req_details->travel_type,'Travel Type');
        $this->data['travelpurpose'] = $this->base_model->get_parameters_menu('Travel Purpose',
                'travelpurpose',$req_details->travel_purpose,'Travel Purpose');   
        $this->data['cost_cols'] = "<th>ID</th><th>Expected Expense</th><th>Amount</th><th></th><th></th>";
        $this->data['cost_foot'] = "<td></td><td></td><td id ='totalamt'></td>"
                . "<td></td><td></td>";
        $this->data['cost_data_columns']='{"data": "cost_id","visible": false  },'
                . '{ "data": "expense" },{"data": "expamt" }';
        $this->data['cost_data_table']="travel_cost";  
        $this->data['cost_data_key']="cost_id";            
        $this->data['cost_data_editflds']='       
            $(\'[name="id"]\').val(data.cost_id);
            $(\'[name="expense"]\').val(data.expense);
            $(\'[name="exp_amt"]\').val(data.expamt);';     
        
        $this->data['cost_colcalc']=",
            \"footerCallback\": function ( row, data, start, end, display ) {
                var api = this.api(), data;	 
                // Remove the formatting to get integer data for summation
                var intVal = function ( i ) {
                        return typeof i === 'string' ? i.replace(/[\$,]/g, '')*1 : typeof i === 'number' ?	i : 0;
                };
                // total_salary over all pages
                total_salary = api.column( 2 ).data().reduce( function (a, b) {
                        return intVal(a) + intVal(b);
                },0 );
                // Update footer
                total_salary = parseFloat(total_salary);
                total_salary = total_salary.toFixed(2);
                total_salary = total_salary.toString().replace(/\B(?=(\d{3})+(?!\d))/g, \",\");
                console.log(total_salary);
                $( api.column( 2 ).footer() ).html(total_salary);
                $('#cash_advance').val(total_salary);				
            },	            
        ";    
        $this->request_datatable_script('Request','cost');
        $this->data['mini']=FALSE;        
        $this->render('requests/request_travel');
    } 
    public function update_travel($process_id,$request_id)
    {       
        $this->form_validation->set_rules('dept','department','trim|required');
        $this->form_validation->set_rules('traveltype','travel type','trim|required');         
        $this->form_validation->set_rules('travelpurpose','travel purpose','trim|required');  
        $this->form_validation->set_rules('currency','currency','trim|required');  
        $this->form_validation->set_rules('details','details','trim|required');
        
        $depart_date = DateTime::createFromFormat('d/m/Y',$this->input->post('depart_date'))->format('Y-m-d');
        $arrive_date = DateTime::createFromFormat('d/m/Y',$this->input->post('arrive_date'))->format('Y-m-d');    
        if($this->form_validation->run() === true){ 
            $tempval = $this->base_model->run_qry("CALL GET_TOTAL_AMT('exp_amt','travel_cost','$request_id')","result");
            $cash_advance=$tempval->total_amt;
            $upd_data = array(
                'dept' => $this->input->post('dept'),
                'travel_type' => $this->input->post('traveltype'),
                'travel_purpose' => $this->input->post('travelpurpose'),
                'currency' => $this->input->post('currency'),
                'cash_advance' => $cash_advance,
                'route'     => $this->input->post('route'),
                'depart_date'   => $depart_date,
                'arrive_date'   => $arrive_date,                
                'details' => $this->input->post('details')
            );        
            $this->db->update('travel_request', $upd_data,array('request_id' => $request_id));  
            $actionType=$this->input->post('actionType');
            $comment=rawurlencode($this->input->post('comment'));
            if($actionType=="submit" || $actionType=="resubmit"){
                //log_message('error',"update_travel error log 2a");
                redirect('approval/action_request/'.$process_id.'/'.$request_id.'/'.$actionType.'/'.$comment);
            } else {
                //log_message('error',"update_travel error log 2b");
                redirect('requests/request_form/request_travel/'.$request_id);
            }
        } else {
            $this->session->set_flashdata('error','Error saving request details');
            redirect('requests/request_form/request_travel/'.$request_id); 
        }                   
    }    
        
    private function update_cost($action,$data_table,$request_id,$data_key)
    {
        $this->form_validation->set_rules('expense','expense','trim|required');
        $this->form_validation->set_rules('exp_amt','expense amount','trim|required');         
        if($this->form_validation->run() === true){    
            $id=$this->input->post('id');  
            $upd_data = array(
                'request_id' => $request_id,
                'expense' => $this->input->post('expense'),
                'exp_amt' => str_replace(',','',$this->input->post('exp_amt')),
                'date_added' => date('Y-m-d H:i:s'),
                'date_updated' => date('Y-m-d H:i:s')
            );     
            if ($action=="add"){$this->base_model->insert($upd_data,$data_table); }
            if ($action=="update"){$this->base_model->update($upd_data,$id,
                    $data_table,array($data_key => $id)); }
            echo json_encode(array("status" => TRUE));            
        } else {
           header('Content-Type: application/json');
           echo json_encode(validation_errors());
        }                   
    }
     
    private function request_claims($request_id,$tblname)
    {
        $sql="SELECT * from  ".$tblname." WHERE request_id = '$request_id'";
        $req_details = $this->base_model->run_qry($sql);    
        $this->data['req_details'] =$req_details;
        $this->data['update_request']='requests/update_claims/';  
        $this->data['currency'] = $this->base_model->get_parameters_menu('Currency',
                'currency',$req_details->currency,'Currency');                
        $this->data['claim_type'] = $this->base_model->get_parameters_menu('Claim Type',
                'claim_type','','Claim Type');  
        $this->data['claims_cols'] = "<th>ID</th><th>Type</th><th>Description</th>"
                . "<th>Amount</th><th></th><th></th>";
        $this->data['claims_data_columns']='{"data": "claim_id","visible": false  },'
                . '{ "data": "claim_type" },{ "data": "description" },{ "data": "claimamt" }';
        $this->data['claims_data_table']="claims_details";
        $this->data['claims_foot'] = "<td></td><td></td><td></td>"
                . "<td  id ='totalamt'></td><td></td><td></td>";        
        $this->data['claims_colcalc']=",
            \"footerCallback\": function ( row, data, start, end, display ) {
                var api = this.api(), data;	 
                // Remove the formatting to get integer data for summation
                var intVal = function ( i ) {
                        return typeof i === 'string' ? i.replace(/[\$,]/g, '')*1 : typeof i === 'number' ?	i : 0;
                };
                // total_salary over all pages
                total_salary = api.column( 3 ).data().reduce( function (a, b) {
                        return intVal(a) + intVal(b);
                },0 );
                // Update footer
                total_salary = parseFloat(total_salary);
                total_salary = total_salary.toFixed(2);
                total_salary = total_salary.toString().replace(/\B(?=(\d{3})+(?!\d))/g, \",\");
                console.log(total_salary);
                $( api.column( 3 ).footer() ).html(total_salary);
                $('#total_expense').val(total_salary);				
            },	            
        ";            
        $this->data['claims_data_key']="claim_id";   
        $this->data['claims_data_editflds']='
            $(\'[name="id"]\').val(data.claim_id);
            $(\'[name="claim_type"]\').val(data.claim_type);
            $(\'[name="claim_amt"]\').val(data.claim_amt);
            $(\'[name="description"]\').val(data.description);';        
        $this->request_datatable_script('Request','claims');
        $this->data['mini']=FALSE;        
        $this->render('requests/request_claims');
    } 
    public function update_claims($process_id,$request_id)
    {       
        $this->form_validation->set_rules('dept','department','trim|required');
        $this->form_validation->set_rules('currency','currency','trim|required');      
        $this->form_validation->set_rules('details','Transaction Desc','trim|required');           
        if($this->form_validation->run() === true){    

            $date = date_parse($this->input->post('claim_period'));
            $claim_period=$date['year'].'-'.$date['month']."-01";
            $claim_date = DateTime::createFromFormat('d/m/Y',$this->input->post('claim_date'))->format('Y-m-d');
            $upd_data = array(
                'dept' => $this->input->post('dept'),
                'currency' => $this->input->post('currency'),
                'claim_date' => $claim_date,
                'claim_period' => $claim_period,
                'initial_advance' => $this->input->post('initial_advance'),
                'details' => $this->input->post('details'),
                'total_expense' => $this->input->post('total_expense'),
                'due_company' => $this->input->post('due_company'),                
                'from_company' => $this->input->post('from_company')
            );        
            $this->db->update('claims_request', $upd_data,array('request_id' => $request_id));
            $actionType=$this->input->post('actionType');
            $comment=rawurlencode($this->input->post('comment'));
            if($actionType=="submit" || $actionType=="resubmit"){
                redirect('approval/action_request/'.$process_id.'/'.$request_id.'/'.$actionType.'/'.$comment);
            } else {
                redirect('requests/request_form/request_claims/'.$request_id);
            }
        } else {
            $this->session->set_flashdata('error','Error saving request details');
            redirect('requests/request_form/request_claims/'.$request_id); 
        }                   
    }
    private function update_claim_details($action,$data_table,$request_id,$data_key)
    {
        $this->form_validation->set_rules('claim_type','claim_type','trim|required');
        $this->form_validation->set_rules('claim_amt','Claim amount','trim|required');         
        if($this->form_validation->run() === true){    
            $id=$this->input->post('id');  
            $upd_data = array(
                'request_id' => $request_id,
                'description' => $this->input->post('description'),
                'claim_type' => $this->input->post('claim_type'),
                'claim_amt' => str_replace(',','',$this->input->post('claim_amt')),
                'date_added' => date('Y-m-d H:i:s'),
                'date_updated' => date('Y-m-d H:i:s')
            );     
            if ($action=="add"){$this->base_model->insert($upd_data,$data_table); }
            if ($action=="update"){$this->base_model->update($upd_data,$id,
                    $data_table,array($data_key => $id)); }
            echo json_encode(array("status" => TRUE));            
        } else {
           header('Content-Type: application/json');
           echo json_encode(validation_errors());
        }                   
    }
    
    
    private function show_card_details(){
        $this->data['data_access']=['no-edit']; 
        $this->data['card_details_data_key'] = 'card_id';     
        $this->data['card_details_editurl']='requests/add_card_details';
        $this->data['card_details_cols'] = "<th>ID</th><th>Code</th><th>Description</th>"
                . "<th>Expense Details</th><th>Amount</th><th>SKU</th><th></th><th></th>";
        $this->data['card_details_data_columns']='{"data": "card_id","visible": false  },'
                . '{ "data": "acct_no" },{ "data": "acct_desc" },'
                . '{ "data": "description" },{ "data": "transamt" },{ "data": "trans_sku" }';
        $this->data['card_details_data_table']="card_details";
        $this->data['card_details_foot'] = "<td></td><td></td>"
                . "<td></td><td></td><td></td><td></td><td></td><td></td>";        
        $this->data['card_details_colcalc']=",
            \"footerCallback\": function ( row, data, start, end, display ) {
                var api = this.api(), data;	 
                // Remove the formatting to get integer data for summation
                var intVal = function ( i ) {
                        return typeof i === 'string' ? i.replace(/[\$,]/g, '')*1 : typeof i === 'number' ?	i : 0;
                };
                // total_salary over all pages
                total_salary = api.column( 4 ).data().reduce( function (a, b) {
                        return intVal(a) + intVal(b);
                },0 );
                // Update footer
                total_salary = parseFloat(total_salary);
                total_salary = total_salary.toFixed(2);
                total_salary = total_salary.toString().replace(/\B(?=(\d{3})+(?!\d))/g, \",\");
                console.log(total_salary);
                $( api.column( 4 ).footer() ).html(total_salary);
                $('#total_expense').val(total_salary);				
            },	            
        ";                   
        $this->request_datatable_script('Request','card_details');
        $this->data['mini']=FALSE;        
    }
    private function request_card($request_id,$tblname)
    {
        $sql="SELECT * from  ".$tblname." WHERE request_id = '$request_id'";
        $req_details = $this->base_model->run_qry($sql);    
        $this->data['req_details'] =$req_details;
        $this->data['update_request']='requests/update_card/';  
        $this->data['currency'] = $this->base_model->get_parameters_menu('Currency',
                'currency',$req_details->currency,'Currency');             
        $this->data['card_type'] = $this->base_model->get_parameters_menu('Card Type',
                'card_type','CitiCard','Card Type');   
        $this->show_card_details();
        $this->render('requests/request_card');
    } 
       
    public function update_card($process_id,$request_id)
    {       
        $this->form_validation->set_rules('dept','department','trim|required');
        $this->form_validation->set_rules('currency','currency','trim|required');      
        if($this->form_validation->run() === true){    
            $date = date_parse($this->input->post('post_month'));
            $post_month=$date['year'].'-'.$date['month']."-01";
            $upd_data = array(
                'dept' => $this->input->post('dept'),
                'currency' => $this->input->post('currency'),
                'post_month' => $post_month,
                'details' => $this->input->post('details'),
                'doctype' => 'Finance Charge Memo',
                'card_type' => $this->input->post('card_type'),                
                'total_expense' => $this->input->post('total_expense')
            );        
            $this->db->update('card_request', $upd_data,array('request_id' => $request_id));  
            $actionType=$this->input->post('actionType');
            $comment=rawurlencode($this->input->post('comment'));
            if($actionType=="submit" || $actionType=="resubmit"){
                redirect('approval/action_request/'.$process_id.'/'.$request_id.'/'.$actionType.'/'.$comment);
            } else if($actionType=="subform"){
                redirect('requests/add_card_details/'.$request_id);
            } else {
                redirect('requests/request_form/request_card/'.$request_id);
            }
        } else {
            $this->session->set_flashdata('error','Error saving request details');
            redirect('requests/request_form/request_card/'.$request_id); 
        }                   
    }
         
    public function add_card_details($request_id,$card_id='')
    {
        $this->form_script();
        $this->main_script();        
        $this->data['datatable_order']='desc';
        $this->data['datatable_col']=0; 
        $this->data['data_var'] = $request_id;
        $this->data['data_section']="requests"; 
        if(empty($card_id)){
            $this->data['page_title'] ='Add Card Details';
        } else{
            $this->data['page_title'] ='Edit Card Details';
        }
        $sql="SELECT * from  workflow_requests WHERE request_id = '$request_id'";
        $wkfl_request = $this->base_model->run_qry($sql); 
        $this->data['wkfl_request'] = $wkfl_request;
        $this->data['initated_by'] = $this->ion_auth->user($wkfl_request->requester_id)->row();            
        $sql="SELECT * from  card_details WHERE card_id = '$card_id'";
        $req_details = $this->base_model->run_qry($sql);    
        $this->data['req_details'] =$req_details;
        $this->data['update_request']='requests/update_card_details/';  
        $sql="SELECT item_desc as name, item_code as value from  items_table WHERE item_type = 'GLCode' order by item_desc";
        $this->data['acct_desc'] = $this->base_model->get_record_menu($sql,
            'acct_desc',$req_details->acct_no,'Select Expense Type');
        $sql="SELECT item_desc as name, item_code as value from  items_table WHERE item_type = 'SKU' order by item_desc";
        $this->data['sku'] = $this->base_model->get_record_menu($sql,
            'trans_sku',$req_details->trans_sku,'Select SKU');    
        
        $this->displayjson();          
         $this->show_card_details();
       
        $this->render('requests/request_card_details');
        
    } 
    
    public function update_card_details($request_id,$action)
    {
        $this->form_validation->set_rules('acct_no','Acct No','trim|required');         
        $this->form_validation->set_rules('acct_desc','acct_desc','trim|required'); 
        $this->form_validation->set_rules('acct_val','acct_val','trim|required'); 
        $this->form_validation->set_rules('trans_amt','Transaction amount','trim|required');        
        if($this->form_validation->run() === true){    
            $id=$this->input->post('id');  
            $upd_data = array(
                'request_id' => $request_id,
                'acct_no' => $this->input->post('acct_no'),
                'acct_desc' => $this->input->post('acct_val'),
                'description' => $this->input->post('description'),
                'trans_amt' => str_replace(',','',$this->input->post('trans_amt')),                
                'trans_sku' => $this->input->post('trans_sku'),
                'date_added' => date('Y-m-d H:i:s'),
                'date_updated' => date('Y-m-d H:i:s')              
            );     
            if ($action=="add"){$this->base_model->insert($upd_data,'card_details'); }
            if ($action=="update"){$this->base_model->update($upd_data,$id,
                    'card_details',array('card_id' => $id)); }
                    
            $actionType=$this->input->post('actionType');
            if($actionType=="subform"){
                redirect('requests/add_card_details/'.$request_id);
            } else {
                redirect('requests/request_form/request_card/'.$request_id);
            }
        } else {
            $this->session->set_flashdata('error','Error saving request details');
            redirect('requests/request_form/request_card/'.$request_id);          
        }                
    }        
    
    private function get_gl_data($type)
    {
        
	$sql="SELECT glcode,glname FROM gltable where gltype='$type' order by glname";
        $temp_data=$this->base_model->run_qry($sql,'qry');
        $total_data=$temp_data->num_rows();
        $main_data=$temp_data->result(); 
        $main_data=is_null($total_data)?array():$main_data;
        $temp_data->next_result();
        $temp_data->free_result();
        return $main_data; 
    }

    private function displayjson(){ 

        $this->data['docreadydata']=$this->data['docreadydata'].'
            $("#acct_desc").change(function () {
                var acct_desc1= $("#acct_desc option:selected").val();
                $(\'#acct_no\').val(acct_desc1);
                var acct_desc1= $("#acct_desc option:selected").text();
                $(\'#acct_val\').val(acct_desc1);                
                 console.log("in acct_desc change");
            });

         ';
    }
}
