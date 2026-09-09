<?php
defined('BASEPATH') OR exit('No direct script access allowed');
ini_set('max_execution_time', 600);
ini_set('memory_limit','128M');
class Rewards extends User_Controller
{
    function __construct()
    {
        parent::__construct();
    }

    public function index()
    {       
     $this->view_winners_list();
    }      
    
       public function area_pdf($id)
    {       
        $this->form_validation->set_rules('area','Area','trim|required');        
        if($this->form_validation->run() === true){    
            $area=$this->input->post('area');
            $this->approve_list($id,$area);
        }              
    }       
       
    public function approve_list($id,$area='')
    {       
        $request_details = $this->base_model->get_single_record($id, 'request_id','workflow_requests'); 
        if($request_details->request_status==='Approved'){
            $this->print_list($id,$area);
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
                $this->print_list($id,$area);
            } else {
                $this->session->set_flashdata('error','Error printing winners list - please check approval status');
                redirect('rewards/view_winners_list');         
            }
        }        
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
    
    private function print_list($id,$area='')
    {       
        $sql="SELECT winners_list.market from winners_list WHERE winners_list.list_id = '$id'";
        $list = $this->base_model->run_qry($sql);
        if ($list->market == "Nigeria") {
            $this->generate_pdf($id,$area);   
        } else {
            $this->wamreport($id,$area);          
        }
    }   
    
    public function generate_pdf($id,$area='')
    {       
        $request_details = $this->base_model->get_single_record($id, 'request_id','workflow_requests'); 
        if($request_details->request_status==='Approved'
            && $this->current_user->user_role==="Initiator"){
            $this->load->library('mpdf_main');
            $this->data['obj_pdf'] = $this->mpdf_main->load();     
            $this->data['page_title'] = 'Print Winners List'; 
            $this->data['main_data'] = $this->base_model->run_qry("CALL GET_VOUCHER_DETAILS('$id','$area')","result","multi");
            log_message("error","GET_VOUCHER_DETAILS('$id','$area')");
            $sql="select appr_date from workflow_history where request_id='$id' order by appr_date desc limit 1";
            log_message("error",$sql);
            $this->data['appr'] = $this->base_model->run_qry($sql); 
            $this->render('rewards/pdfreport','print');                  
        } else {
            $this->session->set_flashdata('error','Error printing winners list - Only initiators can approved winners list');
            redirect('rewards/view_winners_list');         
        }
    }   
        
    public function wamreport($id,$area='')
    {      
        $request_details = $this->base_model->get_single_record($id, 'request_id','workflow_requests'); 
        if($request_details->request_status==='Approved'
            && $this->current_user->user_role==="Initiator"){
            $this->data['main_class']=$this;
            $this->load->library('mpdf_main');
            $this->data['obj_pdf'] = $this->mpdf_main->load('WAM');
            $this->data['page_title'] = 'Print Winners List'; 

            $this->data['data_access']=['noval'];  
            $this->data['main_data'] = $this->base_model->run_qry("CALL GET_VOUCHER_DETAILS('$id','$area')","result","multi");
            $sql="select appr_date from workflow_history where request_id='$id' order by appr_date desc limit 1";
            $this->data['appr'] = $this->base_model->run_qry($sql,"result","multi");
            $sql="SELECT country.*,winners_list.campaign,winners_list.fullmonth,winners_list."
                . "quarter,winners_list.weekval,winners_list.period FROM country Inner Join winners_list "
                . "ON winners_list.market = country.country WHERE winners_list.list_id = '$id'";
            $this->data['country_data'] = $this->base_model->run_qry($sql);
            $this->render('rewards/wamreport','print'); 
        } else {
            $this->session->set_flashdata('error','Error printing winners list - Only initiators can approved winners list');
            redirect('rewards/view_winners_list');         
        }        
                 

    }     
    
    public function voucher_table($id,$pcent)
    {       
        $this->data['page_title'] = 'Print Winners List'; 
        $voucher_table = $this->base_model->run_qry("CALL GET_VOUCHER_BREAKDOWN_WAM('$id')");  
        $html='';
        $total_amt=0;
        $gwth_amt=0;
        $due_amt=0;
        foreach($voucher_table as $row2)
        {
            $html.= '<tr>
                  <td height="24">&nbsp;</td>
                  <td>'.$row2->sku_name.'</td>
                  <td align="right">'.$row2->target_vol.'</td>
                  <td align="right">'.$row2->sales_vol.'</td>
                  <td align="right">'.$row2->pay_rate.'</td>
                  <td align="right">'.$row2->amt.'</td>
                  <td align="right">'.($row2->amt*$pcent/100).'</td>                      
                  <td align="right">'.$row2->growth_rate.'</td>
                  <td align="right">'.$row2->growth_amt.'</td>
                </tr>';
            $total_amt+=$row2->amt;
            $gwth_amt+=$row2->growth_amt;
             $due_amt+=$row2->amt*$pcent/100;
        };
        $voucher_data=array("html" => $html,"total_amt" => $total_amt,"due_amt" => $due_amt,"gwth_amt" => $gwth_amt) ;
        return $voucher_data;
    }       

    public function new_list()
    {       
        if($this->current_user->user_role!=="Initiator"){
            $this->session->set_flashdata('error','Only Initiators can initiate a request');
            redirect('rewards/view_winners_list');
        }
        $this->data['data_access']=['noval'];  
        $this->data['page_title'] = 'New Winners List';
        $this->data['after_foot']=$this->data['after_foot']
        .'<script Language="Javascript">
        $("#market").change(function () {
            var market1= $("#market option:selected").text();
            var campaign1= $("#campaign option:selected").text();
            if ((market1 != "Nigeria" && campaign1.indexOf("Weekly") >= 0)) {
                $("#wamDiv").css(\'visibility\', \'visible\');
            } else {
                $("#wamDiv").css(\'visibility\', \'hidden\');
            }
        });
        $("#campaign").change(function () {
            var market1= $("#market option:selected").text();
            var campaign1= $("#campaign option:selected").text();
            if ((market1 != "Nigeria" && campaign1.indexOf("Weekly") >= 0)) {
                $("#wamDiv").css(\'visibility\', \'visible\');
            } else {
                $("#wamDiv").css(\'visibility\', \'hidden\');
            }
        });        
        $(\'input[type=radio][name=process_type]\').change(function() {
            if ((this.value == "Generated")) {
                $(".fileDiv").hide();
            } else {
                $(".fileDiv").show();
            }
        });        
        </script>';
        
        $this->form_script();
        $this->form_validation->set_rules('campaign','Campaign','trim|required');
        $this->form_validation->set_rules('period_week','Period - Week','trim');        
        $this->form_validation->set_rules('period','period','trim|required');
        $this->form_validation->set_rules('cust_type','cust_type','trim|required');
        $this->form_validation->set_rules('market','market','trim|required');   
        $this->form_validation->set_rules('process_type','Process Type','trim|required');        
        if($this->form_validation->run() === true){    
            $temp_key=$this->get_id();
            $date = date_parse($this->input->post('period'));
            $month=$date['month']."/".$date['year'];
            $week=$this->input->post('period_week');
            $market=$this->input->post('market');
            $quarter=(int)($date['month']/3);
            $rem=$date['month']%3;

            $quarter = ($rem==0?$quarter:$quarter+1);    
            
            $campaign=$this->input->post('campaign');
            $period=$date['month']."/".$date['year'];
            $fullmonth=$this->input->post('period');
            $cust_type=$this->input->post('cust_type');
            $market=$this->input->post('market');            
            $process_type=$this->input->post('process_type'); 
            $where_arr=array("campaign" => $campaign,"fullmonth" => $fullmonth, 
                    "cust_type" => $cust_type, "market" => $market, "process_type" => $process_type);
            
           $records = $this->base_model->get_linked_records("winners_list",
                array("winners_list_imported"=>"winners_list.list_id=winners_list_imported.list_id"),
                empty($week)?$where_arr:$where_arr+array("period_week" => $week),'','left','count'); 
           
//            if (!empty($records) and $records > 0){
//                $this->session->set_flashdata('error','Winners list has '
//                    . 'already been generated for '.$cust_type.' '.$campaign.' - '.$fullmonth.' for '.$market);
//                redirect('Rewards/new_list');
//            }

            $upd_data = array(
                'list_id'   => $temp_key,
                'campaign'   => $campaign,
                'period'   => $period,
                'fullmonth' => $fullmonth,
                'region'   => "All",
                'quarter'   => $quarter,
                'weekval'   => $week,
                'year'   => $date['year'],
                'cust_type'   => $cust_type,
                'market'   => $market,
                'process_type'     => $process_type,
                'initiated_by'   => $this->ion_auth->get_user_id(),
                'initiated_date'   => date('Y-m-d H:i:s'),
            ); 
            
            $get_rules=$this->base_model->run_qry("CALL SELECT_RULES('".$upd_data['campaign'].
                "','".$upd_data['cust_type']."')");
            
            //log_message("error"," get rules "."campaign=".$upd_data['campaign'].",cust_type=".$upd_data['cust_type']);
            
            if ($process_type==="Generated") {
                $this->db->trans_start();
                
                if ($market != "Nigeria") {
                    $this->base_model->insert($upd_data,'winners_list');
                    $get_result=$this->base_model->run_qry("CALL $get_rules->rule_code('".$temp_key
                            ."',".$get_rules->rule_id.",'".$upd_data['weekval']."','".$upd_data['period']."','".$upd_data['market']
                            ."')","run"); 
                
                log_message("error","$get_rules->rule_code('".$temp_key
                            ."',".$get_rules->rule_id.",'".$upd_data['weekval']."','".$upd_data['period']."','".$upd_data['market']
                            ."')"); 
                } else {
                    $this->base_model->insert($upd_data,'winners_list');
                    if (strpos($upd_data['campaign'], 'Quarterly') !== false) {
                        $get_quart_yr=$this->base_model->run_qry("CALL GET_QUART_YEAR('".$temp_key."')");
                        $get_result=$this->base_model->run_qry("CALL $get_rules->rule_code('".$temp_key
                            ."',".$get_rules->rule_id.",'".$upd_data['period']."','".$upd_data['market']
                            ."','".$upd_data['cust_type']."',".$get_quart_yr->year_val.",".$get_quart_yr->quart_val.")","run");
                    } else {
                        $get_result=$this->base_model->run_qry("CALL $get_rules->rule_code('".$temp_key
                            ."',".$get_rules->rule_id.",'".$upd_data['period']."','".$upd_data['market']
                            ."','".$upd_data['cust_type']."')","run");  
                        log_message('error',"CALL $get_rules->rule_code('".$temp_key
                            ."',".$get_rules->rule_id.",'".$upd_data['period']."','".$upd_data['market']
                            ."','".$upd_data['cust_type']."')");
                    }
                }
                $this->db->trans_complete();
            } else {
                $winfile = $this->upload_files('winfile'); 
                $evalfile=$this->input->post('evalfile');
                $evalfile = empty($fileval)? NULL : $this->upload_files('evalfile'); 
                if (is_null($winfile)) {
                    $this->session->set_flashdata('error','Error in File Import - import failed');
                    redirect('Rewards/new_list');
                } else {    
                    $this->db->trans_start();
                    $this->base_model->insert($upd_data,'winners_list');
                    if (!is_null($evalfile)) {
                        rename('./uploads/imports/'.$evalfile, './uploads/imports/'.$temp_key.'-'.$evalfile);
                        $filename='./uploads/imports/'.$temp_key.'-'.$evalfile;
                    } else {
                        $filename=NULL;
                    }
                    
                    $upd_data2 = array(
                        'list_id'   => $temp_key,
                        'period_week'   => $week,
                        'filename' => $filename
                    );                    
                    $this->base_model->insert($upd_data2,'winners_list_imported');
                    
                    if($this->import($temp_key,$winfile,$get_rules)) {
                        $this->session->set_flashdata('success','Import Successful');     
                        $this->db->trans_complete();
                    } else {
                        log_message("error","complete trans error log - ".implode(",",$this->db->error())); 
                        $this->db->trans_rollback();
                        redirect('customers/sales_volume');
                    } 
                }
            }
            redirect('Rewards/request_details/'.$temp_key);            
        } else { 
        $json_data = array(
            "Rural"=> $this->get_campaign_data('Rural Wholesaler'),
            "key"=> $this->get_campaign_data('Key Account'),
            "urban"=> $this->get_campaign_data('Urban Wholesaler'),
            "wam"=> $this->get_campaign_data('WAM Customers')
        );
        $jsontable = json_encode($json_data);             
        $this->data['after_foot']=$this->data['after_foot'].'
            
        <script type="text/javascript">

            $(document).ready(function () {
                var jsonData = '.$jsontable.';                
                $("#cust_type").change(function () {
                    var cust_type1= $("#cust_type option:selected").text();
                     $(\'#campaign\').html("");
                     $(\'#campaign\').append(\'<option value=""> -- Campaign Type -- </option>\');
                    if ((cust_type1== "Rural Wholesaler")) {
                        for (var i = 0; i < jsonData.Rural.length; i++) {
                            $(\'#campaign\').append(\'<option value="\' + jsonData.Rural[i].campaign + \'">\' + jsonData.Rural[i].campaign + \'</option>\');
                        }
                    } else if ((cust_type1== "Key Account")) {
                        for (var i = 0; i < jsonData.key.length; i++) {
                            $(\'#campaign\').append(\'<option value="\' + jsonData.key[i].campaign + \'">\' + jsonData.key[i].campaign + \'</option>\');
                        }

                    } else if ((cust_type1== "Urban Wholesaler")) {
                        for (var i = 0; i < jsonData.urban.length; i++) {
                            $(\'#campaign\').append(\'<option value="\' + jsonData.urban[i].campaign + \'">\' + jsonData.urban[i].campaign + \'</option>\');
                        }

                    } else {
                        for (var i = 0; i < jsonData.wam.length; i++) {
                            $(\'#campaign\').append(\'<option value="\' + jsonData.wam[i].campaign + \'">\' + jsonData.wam[i].campaign + \'</option>\');
                        }
                    }
                    $("#campaign").trigger("chosen:updated");
                    $("#campaign").trigger("liszt:updated");                        
                });

            });
         </script> 
         ';
            
            $this->data['campaign'] = $this->base_model->get_parameters_menu('Campaign Types',
                'campaign','','Campaign Type'); 
            $this->data['cust_type'] = $this->base_model->get_parameters_menu('Customer Type',
                'cust_type','','Customer Type');             
            $market = "Nigeria";
            $this->data['market'] = $this->base_model->get_parameters_menu('Markets','market',$market,'Market');                
            $this->data['current_user'] = $this->current_user->fullname;
            $sql="SELECT distinct region as name, region as value FROM locations WHERE market "
                . "=  '".$market."' order by region";
            $this->data['region'] = $this->base_model->get_record_menu($sql,'region','','Choose Region');                      
            $this->render('rewards/rewards_new_winners');
        }
    }

    private function get_campaign_data($id)
    {
        $temp_data=$this->base_model->run_qry("CALL GET_CAMPAIGN('$id')",'qry');
        $total_data=$temp_data->num_rows();
        $main_data=$temp_data->result(); 
        $main_data=is_null($total_data)?array():$main_data;
        
        $temp_data->next_result();
        $temp_data->free_result();
        return $main_data; 
    }    
    
    public function request_details($temp_key)
    {               
        $this->data['list_data']= $this->base_model->get_linked_records("winners_list",
                array("winners_list_imported"=>"winners_list.list_id=winners_list_imported.list_id"),
                array("winners_list.list_id" => $temp_key),'','left');                    
        $this->data['list_data']->list_id=$temp_key;
        $this->data['data_columns']='{  "data": "cust_code" }, {"data": "cust_name" },'
              .($this->data['list_data']->process_type==="Generated"?
                '{"data": "location" },{ "data": "cust_category" },'
                .($this->data['list_data']->cust_type==="WAM Customers"?'':'{"data": "kpi" },'):
                '{"data": "location" },{ "data": "cust_category" },')

        .($this->data['list_data']->cust_type==="WAM Customers"?''              
         :'{ "data": "prorated",
                "render": function ( data, type, row ) {
                    return format_number(data);
                  }},')      
            .'{ "data": "actual",
                "render": function ( data, type, row ) {
                    return format_number(data);
                  }},'                
            .'{ "data": "target",
                "render": function ( data, type, row ) {
                    return format_number(data);
                  }},'  
            .'{ "data": "percent",
                "render": function ( data, type, row ) {
                    return format_number(data);
                  }},'                  
            .'{ "data": "total_amt",
                "render": function ( data, type, row ) {
                    return format_number(data);
                  }},'.
                  
          ($this->data['list_data']->process_type==="Generated"
             ?'{  "data": "gencode" }':'{  "data": "gencode" },{  "data": "reason" }');
        $this->data['data_section']="Rewards";
        if ($this->data['list_data']->process_type==="Generated") {
            $this->data['data_access']=['no-edit'];  
            
        } else {
            $this->data['data_access']=['noval'];  
        }
        $this->data['data_table']="winners";              
        $this->data['data_key']="winners_id";
        $this->data['data_var']=$temp_key; 

        
        $this->data['request_data']= $this->base_model->get_single_record($temp_key,
                'request_id','workflow_requests');  

          
        $this->data['initated_by'] = 
            $this->ion_auth->user($this->data['list_data']->initiated_by)->row();        
        $this->data['process_data']=$this->base_model->get_single_record
            ($this->data['list_data']->market,'market','workflow');        

        $sql="SELECT users.id as value, users.fullname as 'name' "
                . "FROM users where active=1 and user_role='Approver' order by users.fullname asc";      
        $this->data['approver_name'] = $this->base_model->get_record_menu($sql,
            'approver_name','','Re-route to Approver');

        $workflow=$this->base_model->get_single_record($this->data['list_data']->market,'market','workflow');
        $this->data['approvers_count']=$this->base_model->rec_count('request_approvers',
            $temp_key,'request_id'); 

        $sql='SELECT users.fullname as approver_name,
            request_approvers.appr_name,request_approvers.appr_function, request_approvers.approver_from 
            FROM request_approvers left Join users ON users.id = request_approvers.appr_name
            where request_approvers.request_id=\''.$temp_key.'\'';

        $this->data['approvers_list']=$this->base_model->run_qry($sql,'result','multi');  
              
        $this->data['workflow_history']=$this->base_model->get_single_record(
            $this->data['list_data']->list_id,'request_id','workflow_history','multi');     
        $this->data['page_title'] = 'Request Details:- '.$this->data['list_data']->cust_type
             .' - '.$this->data['list_data']->campaign.' - '.$this->data['list_data']->fullmonth
             .(stripos(x.$this->data['list_data']->campaign, 'Week') !== false?' - Week '.$this->data['list_data']->weekval:'');
        $this->data['mini']=FALSE; 
        $this->datatable_script($this->data['data_section'],'desc');   
        $this->data['area']=$this->base_model->get_record_menu("CALL GET_AREA_LIST('$temp_key')",'area','area','Select Area');  
        $this->render('rewards/rewards_request_details');
    }    
    
    private function get_winners($data_key='',$id='')
    {
        $data_table="winners";
        $rule_details=$this->base_model->run_qry("CALL GET_RULE_DETAILS('$id')");
        $winners_list=$this->base_model->get_single_record($id,'list_id','winners_list');
        
        if($data_key==''){
            header('Content-Type: application/json'); 
            if ($winners_list->process_type==='Imported') {
                $temp_data=$this->base_model->run_qry("CALL GET_WINNERS_LIST_IMPORTED('$id')",'qry');
            } else {
                $temp_data=$this->base_model->run_qry("CALL $rule_details->display_code('$id')",'qry');
            }
            $total_data=$temp_data->num_rows();
            $total_data=is_null($total_data)?0:$total_data;
            $main_data=$temp_data->result(); 
            $main_data=$total_data==0?array():$main_data;

            $json_data = array(
                "recordsTotal"    => intval($total_data),  // total number of records
                "recordsFiltered" => intval($total_data), // total number of records after searching, if there is no searching then totalFiltered = totalData
                "data"            => $main_data   // total data array
                );
            echo json_encode($json_data, JSON_UNESCAPED_UNICODE); 
        } else {
            //$data = $this->base_model->get_single_record($id,$data_key,$data_table);        
            echo json_encode(NULL);
        }   
    }
   
    private function sales_breakdown($id)
    {
        $this->data['data_access']=['noval'];  
        $this->data['main_data'] = $this->base_model->run_qry("CALL GET_WINNER_DETAILS($id)"); 
        $this->data['country'] = $this->base_model->get_single_record($this->data['main_data']->market,'country','country'); 
        switch ($this->data['main_data']->cust_type){
            Case "WAM Customers":
                    $this->data['data_columns']=' { "data": "sku_code" },{ "data": "sku_name" },'
                    . '{"data": "total_target" ,"render": function ( data, type, row ) {
                            return format_number(data);
                        }},'
                    . '{"data": "total_sales" ,"render": function ( data, type, row ) {
                            return format_number(data);
                        }},'                  
                    . '{"data": "total_prorated" ,"render": function ( data, type, row ) {
                            return format_number(data);
                        }},'
                    . '{"data": "rebate_val" ,"render": function ( data, type, row ) {
                            return format_number(data);
                        }},'
                    . '{"data": "pcent_growth" ,"render": function ( data, type, row ) {
                            return format_number(data);
                        }},'                    
                    . '{"data": "growth" ,"render": function ( data, type, row ) {
                            return format_number(data);
                        }}'                
                    ;
                break;
            DEFAULT:
                $this->data['data_columns']=' { "data": "period_week" },'
                . '{ "data": "period_month" },{"data": "sku_code" },{"data": "sku_name" },'
                . '{"data": "actual_vol" ,"render": function ( data, type, row ) {
                        return format_number(data);
                    }},'
                . '{"data": "pro_vol" ,"render": function ( data, type, row ) {
                        return format_number(data);
                    }},'
                . '{"data": "pay_rate" ,"render": function ( data, type, row ) {
                        return format_number(data);
                    }},'
                . '{"data": "amt" ,"render": function ( data, type, row ) {
                        return format_number(data);
                    }}'                
                ; 
                break;            
        }        

        $this->data['data_section']="Rewards";
        $this->data['data_table']="sales_breakdown";   
        $this->data['data_key']="winners_id";
        $this->data['page_title'] = 'Reward Breakdown:- '.$this->data['main_data']->cust_name
             .' - '.$this->data['main_data']->title;        
        $this->data['data_var']=$id;
        $this->data['mini']=FALSE; 
        $this->datatable_script($this->data['data_section']);      
        $this->render('rewards/rewards_request_breakdown');        
    }
    
    private function get_sales_breakdown($data_key='',$id='')
    {
        $data_table="winners";

        if($data_key==''){
            header('Content-Type: application/json'); 
            $sql="SELECT winners_list.list_id FROM winners_list Inner Join winners 
                ON winners.list_id = winners_list.list_id WHERE winners.winners_id = $id";
            $win_type = $this->base_model->run_qry($sql);
            

            $rule_details=$this->base_model->run_qry("CALL GET_RULE_DETAILS('$win_type->list_id')");
                
            $temp_data=$this->base_model->run_qry("CALL $rule_details->breakdown_code('$id')",'qry');
            
            $total_data=$temp_data->num_rows();
            $total_data=is_null($total_data)?0:$total_data;

            $main_data=$temp_data->result(); 
            $main_data=$total_data==0?array():$main_data;
            
            $json_data = array(
                "recordsTotal"    => intval( $total_data ),  // total number of records
                "recordsFiltered" => intval( $total_data ), // total number of records after searching, if there is no searching then totalFiltered = totalData
                "data"            => $main_data   // total data array
                );
            echo json_encode($json_data); 
        } else {
            echo json_encode(NULL);
        }   
    }    
    
    public function view_winners_list()
    {       
        $this->data['page_title'] = 'All Winners List';  
        $this->data['data_columns']='{"data": "initiated_date2" },{"data": "fullmonth" },'
            . '{ "data": "initiator" },{ "data": "process_type" },{ "data": "campaign_val" },{ "data": "current_status" },'
            . '{"data": "cust_type" },{ "data": "market" },{  "data": "list_id" }';
        $this->data['data_section']="Rewards";
        $this->data['data_table']="winners_list";   
        $this->data['data_access']=['no-edit'];        
        $this->data['data_key']="list_id";    
       
        $this->data['mini']=TRUE; 
        $this->datatable_script($this->data['data_section']);       
        $this->render('rewards/rewards_view_winners_list');
    }
    
    private function get_winners_list($data_key='',$id='')
    {
        $data_table="winners_list";

        if($data_key==''){
            header('Content-Type: application/json');                     
            $sql="SELECT winners_list.*,DATE_FORMAT(winners_list.initiated_date, '%d %b, %Y') as initiated_date2,
                users.fullname as 'initiator',
                if(workflow_requests.request_status IS NULL or workflow_requests.request_status='','Not Yet Submitted',
                if(workflow_requests.request_status='Approved',concat(workflow_requests.request_status,' by initiator '),
                concat(workflow_requests.request_status,' ',workflow_requests.next_approver))) as current_status,
                workflow_requests.process_id , if(winners_list.campaign LIKE '%weekly%',
                concat(winners_list.campaign,' - Week ',winners_list.weekval),winners_list.campaign) as campaign_val
                FROM winners_list left Join users ON winners_list.initiated_by = users.id
                left Join workflow_requests ON workflow_requests.request_id =winners_list.list_id";
            $temp_data = $this->base_model->run_qry($sql,'qry');
            $total_data=$temp_data->num_rows();
            $total_data=is_null($total_data)?0:$total_data;
            $main_data=$temp_data->result();
            $main_data=$total_data==0?array():$main_data;
            
            $json_data = array(
                "recordsTotal"    => intval( $total_data ),  // total number of records
                "recordsFiltered" => intval( $total_data ), // total number of records after searching, if there is no searching then totalFiltered = totalData
                "data"            => $main_data   // total data array
                );
            echo json_encode($json_data); 
        } else {
            $data = $this->base_model->get_single_record($id,$data_key,$data_table);        
            echo json_encode($data);
        }   
    }    
    
    public function awaiting_approval()
    {       
        $this->data['page_title'] = 'Winners List Awaiting My Approval'; 
        $this->data['data_columns']='{"data": "initiated_date2" },{"data": "fullmonth" },'
            . '{ "data": "initiator" },{ "data": "process_type" },{ "data": "campaign_val" },{ "data": "current_status" },'
            . '{"data": "cust_type" },{ "data": "market" },{  "data": "list_id" }';
       
        $this->data['data_section']="Rewards";
        $this->data['data_table']="awaiting_approval";   
        $this->data['data_access']=['no-edit'];        
        $this->data['data_key']="list_id";    
        $this->data['mini']=FALSE;
        $this->datatable_script($this->data['data_section']);          
        $this->render('rewards/rewards_view_winners_list');
    }    
   
    private function get_awaiting_approval($data_key='',$id='')
    {
        $data_table="workflow_requests";
        $key=$this->ion_auth->get_user_id();
        if($data_key==''){
            header('Content-Type: application/json');          
            
            $sql="SELECT winners_list.*,DATE_FORMAT(winners_list.initiated_date, '%d %b, %Y') as initiated_date2,
                users.fullname as 'initiator',
                if(workflow_requests.request_status IS NULL or workflow_requests.request_status='','Not Yet Submitted',
                if(workflow_requests.request_status='Approved',concat(workflow_requests.request_status,' by initiator '),
                concat(workflow_requests.request_status,' ',workflow_requests.next_approver))) as current_status,
                workflow_requests.process_id , if(winners_list.campaign LIKE '%weekly%',
                concat(winners_list.campaign,' - Week ',winners_list.weekval),winners_list.campaign) as campaign_val
                FROM winners_list left Join users ON winners_list.initiated_by = users.id
                inner Join workflow_requests ON workflow_requests.request_id =winners_list.list_id 
                where next_appr=".$key;
                        
            $temp_data = $this->base_model->run_qry($sql,'qry');
            $total_data=$temp_data->num_rows();
            $total_data=is_null($total_data)?0:$total_data;
            $main_data = $temp_data->result();
            $main_data=$total_data==0?array():$main_data;
            
            $json_data = array(
                "recordsTotal"    => intval( $total_data ),  // total number of records
                "recordsFiltered" => intval( $total_data ), // total number of records after searching, if there is no searching then totalFiltered = totalData
                "data"            => $main_data   // total data array
                );
            echo json_encode($json_data); 
        } else {
            $data = $this->base_model->get_single_record($id,$data_key,$data_table);        
            echo json_encode($data);
        }   
    }    
    
    public function view_vouchers_list()
    {       
        $this->data['page_title'] = 'Approved Vouchers List'; 
        $this->data['data_columns']='{"data": "initiated_date2" },{"data": "initiator" },{"data": "fullmonth" },'
            . '{ "data": "campaign" },{ "data": "cust_type" },{ "data": "market" },'
            . '{  "data": "list_id" }';
        $this->data['data_section']="Rewards";
        $this->data['data_table']="approved_vouchers";   
        $this->data['data_access']=['no-edit'];        
        $this->data['data_key']="list_id";           
        $this->data['mini']=FALSE; 
        $this->datatable_script($this->data['data_section']);       
        $this->render('rewards/rewards_vouchers_list');
    }
    
    private function get_approved_vouchers($data_key='',$id='')
    {
        $data_table="winners_list";

        if($data_key==''){
            header('Content-Type: application/json');             
            $sql="SELECT winners_list.*,DATE_FORMAT(winners_list.initiated_date, '%d %b, %Y') as initiated_date2,
                users.fullname) as 'initiator',
                workflow_requests.process_id 
                FROM winners_list left Join users ON winners_list.initiated_by = users.id
                left Join workflow_requests ON workflow_requests.request_id =winners_list.list_id
                where workflow_requests.request_status='Approved'";
            $temp_data = $this->base_model->run_qry($sql,'qry');
            $total_data=$temp_data->num_rows();
            $total_data=is_null($total_data)?0:$total_data;
            $main_data=$temp_data->result();
            $main_data=$total_data==0?array():$main_data;
            
            $json_data = array(
                "recordsTotal"    => intval( $total_data ),  // total number of records
                "recordsFiltered" => intval( $total_data ), // total number of records after searching, if there is no searching then totalFiltered = totalData
                "data"            => $main_data   // total data array
                );
            echo json_encode($json_data); 
        } else {
            $data = $this->base_model->get_single_record($id,$data_key,$data_table);        
            echo json_encode($data);
        }   
    }      
    
    public function ajax_list($data_table,$id='')
    {       
        $func='get_'.$data_table;
        $this->$func('',$id);
    }    
    
    public function connector($id='')
    {
        $this->request_details($id);        
    }
    
    public function view_page($data_table,$id='')
    {       
        switch ($data_table){
            case 'winners':
                $this->sales_breakdown($id);
                break;
            case 'sales_total':
                $this->view_sales_details($id);
                break;    
            case 'winners_list':            
            case 'awaiting_approval':  
            case 'approved_vouchers':                
                $this->request_details($id);
                break;                       
        }     
    } 
       
    public function ajax_edit($data_table,$data_key='',$id='')
    {
        $func='get_'.$data_table;
        $this->$func($data_key,$id);
    }  
        
    public function delete_row($del_key){
        if($this->current_user->user_role!=="Initiator"){
            $this->session->set_flashdata('error','Only Initiators can delete winners list');
            redirect('rewards/view_winners_list');
        }        
        $get_result=$this->base_model->run_qry("CALL CANCEL_WIN_LIST('".$del_key."')","run");     
        redirect('rewards/view_winners_list');
    }    
    
   private function import($list_id,$filename,$rule_id)
    {
        $this->load->library('Excel');
        try {
            $objPHPExcel = PHPExcel_IOFactory::load('./uploads/imports/'.$filename);
            $objWorksheet = $objPHPExcel->getSheet(0); 
        }
        catch(Exception $e){
            $this->session->set_flashdata('error','Error in Excel library - import failed');
            exit;
            return FALSE;
        }
        $highestRow = $objWorksheet->getHighestDataRow(); 
        $highestColumn = $objWorksheet->getHighestDataColumn();
        $columncount = PHPExcel_Cell::columnIndexFromString($highestColumn);
        $colData = $objWorksheet->rangeToArray('A1:' . $highestColumn . '1',
         NULL, TRUE, FALSE);
        $rowData = $objWorksheet->rangeToArray('A1:' . $highestColumn . $highestRow,
         NULL, TRUE, FALSE);
        $win_table = array();
        $reward_table = array();
        $cnt=0;
          
        $win_col="list_id,rule_id,cust_code,band,actual,prorated,target,percent,total_amt,reason";

        for ($row = 1; $row <= $highestRow-1; $row++)
        {     
            $import_data = array();
            $cust_code=empty($rowData[$row][0])?'':$rowData[$row][0];
            $band=empty($rowData[$row][2])?'':$rowData[$row][2];      
            $target=empty($rowData[$row][3])?0:$rowData[$row][3];
            $actual=empty($rowData[$row][4])?'':$rowData[$row][4];                 
            $prorated=empty($rowData[$row][5])?'':$rowData[$row][5];                 
            $percent=empty($rowData[$row][6])?'':$rowData[$row][6];     
            $payout=empty($rowData[$row][7])?'':$rowData[$row][7];     
            $reason=empty($rowData[$row][8])?'':$rowData[$row][8];
//            log_message("error","datalog log ".'("list_id='.$list_id.'","rule_id='.$rule_id.'","cust_code='.$cust_code.
//            '","band='.$band.'","target='.$target.'","actual='.$actual.'","prorated='.$prorated.'","percent='.$percent.'","payout='.$payout.'")'); 
            $import_data = '("'.$list_id.'","'.$rule_id.'","'.$cust_code.'","'.$band.'","'.
            $target.'","'.$actual.'","'.$prorated.'","'.$percent.'","'.$payout.'","'.$reason.'")';
            $win_table[$cnt] = $import_data;    
  
            $cnt+=1;         
        }
        //$this->db->insert_batch($data_table, $table_data); 
        $str = " INSERT INTO winners_imported (".$win_col.") Values ".implode (",",$win_table)."";
        //log_message("error","query log ".$str); 
        $this->db->query($str);    
        return TRUE;
    }     
}      