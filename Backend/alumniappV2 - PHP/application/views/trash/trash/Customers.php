<?php
defined('BASEPATH') OR exit('No direct script access allowed');
ini_set('max_execution_time', 300); 
ini_set('memory_limit','128M');

class Customers extends User_Controller
{
    function __construct()
    {
        parent::__construct();
        $this->data['data_access']=array("Edit");
    }
    
    public function index()
    {       
        $this->customers_list();
    }  
  
    private function chk_sku($sku_name){
          $sql="SELECT sku_code from products where sku_name='".$sku_name."'";
          $res= $this->base_model->run_qry($sql);        
          return empty($res->sku_code)?$sku_name:$res->sku_code;
    }
    
    public function import_target($action=null)
    {     
        $this->data['data_access']=['noval'];  
       
        if($this->current_user->user_role!=="Initiator"){
            $this->session->set_flashdata('error','Only Initiators can import sales volume information');
            redirect('customers/sales_volume');
        }
        $this->form_script();
        $this->data['page_title'] = 'Generate Target Data'; 
        $this->data['form_action']='add';   
        $this->form_validation->set_rules('figures_type','figures Type','trim|required');
        if($this->form_validation->run() === true){
            $filename = $this->upload_files('targetfile');        
            if (is_null($filename) ) {
                $this->session->set_flashdata('error','Error in File Import - import failed');
                redirect('customers/import_target');
            }    
            $this->load->library('Excel');
             try {
                 $objPHPExcel = PHPExcel_IOFactory::load('./uploads/imports/'.$filename);
                 $objWorksheet = $objPHPExcel->getSheet(0); 
             }
             catch(Exception $e){
                 $this->session->set_flashdata('error','Error in Excel library - import failed');
                 exit;
             }
             $highestRow = $objWorksheet->getHighestDataRow(); 
             $highestColumn = $objWorksheet->getHighestDataColumn();
             $columncount = PHPExcel_Cell::columnIndexFromString($highestColumn);
             $colData = $objWorksheet->rangeToArray('A1:' . $highestColumn . '1',NULL, TRUE, FALSE);
             $rowData = $objWorksheet->rangeToArray('A1:' . $highestColumn . $highestRow,NULL, TRUE, FALSE);
             $table_data = array();
             $line_data = array();
             $col_array=array();
             $tbl_header="<th>cust_code</th><th>cust_name</th>";
             $columnsval='{"data": "cust_code"},{"data": "cust_name"},';
             for ($column = 4; $column <= $columncount-1; $column++) {               
                $tmpval=$this->chk_sku(empty($colData[0][$column])?'':$colData[0][$column]);
                $col_array[$column-4]=$tmpval;
                $tbl_header.="<th>".$tmpval."</th>";
                $columnsval.='{"data": "'.$tmpval.'" },';
            }             

            for ($row = 1; $row <= $highestRow-1; $row++)
            {    
                    $line_data["cust_code"] =empty($rowData[$row][0])?'NA':$rowData[$row][0];
                    $line_data["cust_name"] =empty($rowData[$row][1])?'NA':$rowData[$row][1];
                    $ratio =(empty($rowData[$row][2]) || empty($rowData[$row][3]))?0:$rowData[$row][2]/$rowData[$row][3];
                    
                    for ($column = 4; $column <= $columncount-1; $column++) {               
                        $sku_code=$col_array[$column-4];
                        $vol=empty($rowData[$row][$column])?0:$rowData[$row][$column];
                        $line_data[$sku_code] =  empty($ratio) || empty($vol)?0:$vol*$ratio;              

                    }
                    
                $table_data[]= $line_data; 
            }
            
            $this->data['tbl_header']=$tbl_header;
            $this->data['data_columns']=rtrim($columnsval,',');
            $this->main_script();
//            $json_data = array(
//                "demo"            =>  $table_data  // total data array
//            );
//            $json_data = array(
//             "recordsTotal"    => $highestRow,  // total number of records
//             "recordsFiltered" => $highestRow, // total number of records after searching, if there is no searching then totalFiltered = totalData
//             "data"            => $table_data   // total data array
//             );
               
            $this->data['after_foot'].='<script Language="Javascript">var dataSet='.json_encode($table_data).'</script>';
 //           $this->data['after_foot'].='<script Language="Javascript">'.json_encode($json_data).';</script>';            

            $this->data['after_foot']=$this->data['after_foot'].
                    $this->load->view("home/embed_datatable_script", $this->data, TRUE);  
            $this->render('customers/imported_target_details');
        
            
        } else {       
            $this->session->set_flashdata(validation_errors()); 
            $market= "Nigeria";                         
            $this->render('customers/import_target');
        }
    } 
        
    
    public function import_sales_volume($action=null,$id=null)
    {     
        $this->data['data_access']=['noval'];  
        $this->data['after_foot']=$this->data['after_foot']
        .'<script Language="Javascript">
        $("#cust_type").change(function () {
                 var cust_type1= $("#cust_type option:selected").text();
                if ((cust_type1== "WAM Customers")) {
                    $("#selloutFile").text("Attach Sellout File:");
                    $("#kpiDiv").hide();                 
                } else if ((cust_type1== "Rural Wholesaler")) {
                    $("#selloutFile").text("Attach Redx File:");
                    $("#kpiDiv").show();
                } else if ((cust_type1== "Key Account")) {
                    $("#selloutFile").text("Attach Focus Brand Target File:");
                    $("#kpiDiv").hide();
                } else {
                $("#selloutFile").text("Attach Sellout File:");
                $("#kpiDiv").show();
                }
        });
        </script>';
       
        if($this->current_user->user_role!=="Initiator"){
            $this->session->set_flashdata('error','Only Initiators can import sales volume information');
            redirect('customers/sales_volume');
        }
        $this->form_script();
        if($action==='edit'){
            $this->data['page_title'] = 'Edit Evaluation Data'; 
            $this->data['form_action']='edit';
            $this->data['main_data']=is_null($id)?array():
                $this->base_model->get_single_record($id,'sales_id','sales');
        } else {
            $this->data['page_title'] = 'Import Evaluation Data'; 
            $this->data['form_action']='add';            
        }        
        $this->form_validation->set_rules('imported_by','Imported By','trim|required');
        $this->form_validation->set_rules('cust_type','Customer Type','trim|required');
        $this->form_validation->set_rules('period_week','Period - Week','trim|required');
        $this->form_validation->set_rules('period_month','Period - Month','trim|required');
        $this->form_validation->set_rules('market','market','trim|required'); 
        $this->form_validation->set_rules('figures_type','figures type','trim');         
        if($this->form_validation->run() === true){
            $date = date_parse($this->input->post('period_month'));
            $fullmonth=$this->input->post('period_month');
            $month=$date['month']."/".$date['year'];
            $week=$this->input->post('period_week');
            $market=$this->input->post('market');
            $quarter=(int)($date['month']/3);
            $rem=$date['month']%3;
            $quarter = ($rem==0?$quarter:$quarter+1);   
            $cust_type = $this->input->post('cust_type');
            
            $where_arr=array("period_month" => $month,"cust_type" => $cust_type, "market" => $market);            
            $records= $this->base_model->get_single_record("","","sales","count",
                    $market=="Nigeria" & $cust_type!="Urban Wholesaler"?$where_arr:$where_arr+array("period_week" => $week));
           
            $sales_id=$records->sales_id;
            
            if($action==='edit'){
                $this->db->trans_start();
                $sales_id=$this->input->post('id');
            }else {
                if (!empty($records) and $records > 0){
                    $this->session->set_flashdata('error','Sales information has '
                        . 'already been uploaded for '. $cust_type.' '.$market.' '
                        .($market=="Nigeria"?'':' Week '.$week).' - '.$fullmonth);
                    redirect('customers/import_sales_volume');
                    //exit();
                }
                $insert_data = array(
                    'import_date'   => date('Y-m-d H:i:s'),
                    'imported_by'     => $this->input->post('imported_by'),
                    'cust_type'     => $this->input->post('cust_type'),
                    'period_week'   => $week,
                    'period_month'   => $month,
                    'fullmonth'   => $fullmonth,
                    'market'   => $market,
                    'period_quarter'   => $quarter,
                    'period_year'   => $date['year']
                ); 
                $this->db->trans_start();
                $sales_id = $this->base_model->insert($insert_data,'sales');
            }
            $salesfile = $this->upload_files('salesfile');
            $targetfile = $this->upload_files('targetfile');
            $cust_switch='';
            switch ($cust_type) {
                case "Key Account":
                    $selloutfile = $this->upload_files('selloutfile');
                    $kpifile = true; 
                    $cust_switch=$cust_type;
                    //log_message("error", "in key account operation"); 
                    break;                    
                case "WAM Customers":         
                    if(empty($_FILES['selloutfile']['tmp_name'])) {
                        $selloutfile = true;
                        $cust_switch="WAM-sellout";
                       // log_message("error", "in WAM-sellout operation"); 
                    } else {
                        $selloutfile = $this->upload_files('selloutfile');
                       // log_message("error", "in WAM+sellout operation"); 
                        $cust_switch="WAM+sellout";
                    }
                    $kpifile = true;  
                    break;
                default:   
                     if(empty($_FILES['selloutfile']['tmp_name']) && $cust_type="Urban Wholesaler") {
                        $cust_switch="week_urban";  
                        $kpifile = true;
                        $selloutfile = true;
                     }  else {          
                        $cust_switch=$cust_type;
                        $selloutfile = $this->upload_files('selloutfile');
                        $kpifile = $this->upload_files('kpifile');
                     }
            }            

            $min_target=70;
            $max_target=110;

            if (is_null($salesfile) || is_null($targetfile) || is_null($selloutfile) || is_null($kpifile)) {            
                $this->session->set_flashdata('error','Error in File ImportRow Now - import failed');
                $this->db->trans_rollback();
                redirect('customers/import_sales_volume');
            }    
            switch ($cust_switch) {
                case "Key Account":
                    if($this->import($sales_id,$salesfile,'sales_details')
                        && $this->import($sales_id,$targetfile,'target_details')
                        && $this->import($sales_id,$selloutfile,'focus_brand_details')) {
                        $this->session->set_flashdata('success','Import Successful');     
                        $this->base_model->run_qry("CALL GENERATE_KA_REWARD_TABLE($sales_id)",'run');
                        $this->db->trans_complete();
                        //log_message("error","complete trans KA1 error log - ".implode(",",$this->db->error())); 
                        redirect('customers/view_sales_details/'.$sales_id);
                    } else {
                        $this->db->trans_rollback();
                        log_message("error","complete trans KA2 error log - ".implode(",",$this->db->error())); 
                        redirect('customers/sales_volume');
                    }          
                    break;  
                case "WAM-sellout":                    
                    if($this->import($sales_id,$salesfile,'sales_details')
                        && $this->import($sales_id,$targetfile,'target_details')) {
                        $this->session->set_flashdata('success','Import Successful');     
                        $this->base_model->run_qry("CALL GENERATE_WAM_REWARD_TABLE($sales_id,$min_target,$max_target)",'run');
                        $this->db->trans_complete();
                        log_message("error","complete trans WAM-sellout1 error log - ".implode(",",$this->db->error())); 
                        redirect('customers/view_sales_details/'.$sales_id);

                    } else {
                        $this->db->trans_rollback();
                        log_message("error","complete trans WAM-sellout2 error log - ".implode(",",$this->db->error())); 
                        redirect('customers/sales_volume');
                    }          
                    break;
                case "WAM+sellout":                    
                    if($this->import($sales_id,$salesfile,'sales_details')
                        && $this->import($sales_id,$targetfile,'target_details')
                        && $this->import($sales_id,$selloutfile,'sellout_details')) {
                        $this->session->set_flashdata('success','Import Successful');     
                        $this->base_model->run_qry("CALL GENERATE_WAM_SOUT_REWARD_TABLE($sales_id,$min_target,$max_target)",'run');
                        $this->db->trans_complete();
                        log_message("error","complete trans WAM+sellout1 error log - ".implode(",",$this->db->error())); 
                        redirect('customers/view_sales_details/'.$sales_id);

                    } else {
                        $this->db->trans_rollback();
                        log_message("error","complete trans WAM+sellout2 error log - ".implode(",",$this->db->error())); 
                        redirect('customers/sales_volume');
                    }          
                    break;       
                case "week_urban":
                    if($this->import($sales_id,$salesfile,'sales_details')
                        && $this->import($sales_id,$targetfile,'target_details')) {
                        $this->session->set_flashdata('success','Import Successful');     
                        $this->base_model->run_qry("CALL GENERATE_REWARD_TABLE($sales_id,$min_target,$max_target)",'run');
                        $this->db->trans_complete();
                        //log_message("error","complete trans default1 error log - ".implode(",",$this->db->error())); 
                        redirect('customers/view_sales_details/'.$sales_id);

                    } else {
                        $this->db->trans_rollback();
                        log_message("error","complete trans default2 error log - ".implode(",",$this->db->error())); 
                        redirect('customers/sales_volume');
                    }
                    break;                    
                default:                    
                    if($this->import($sales_id,$salesfile,'sales_details')
                        && $this->import($sales_id,$targetfile,'target_details')
                        && $this->import($sales_id,$selloutfile,'sellout_details')
                        && $this->import($sales_id,$kpifile,'reward_kpi_details')) {
                        $this->session->set_flashdata('success','Import Successful');     
                        $this->base_model->run_qry("CALL GENERATE_REWARD_TABLE($sales_id,$min_target,$max_target)",'run');
                        $this->db->trans_complete();
                        //log_message("error","complete trans default1 error log - ".implode(",",$this->db->error())); 
                        redirect('customers/view_sales_details/'.$sales_id);

                    } else {
                        $this->db->trans_rollback();
                        log_message("error","complete trans default2 error log - ".implode(",",$this->db->error())); 
                        redirect('customers/sales_volume');
                    }
            }            
            
        } else {       
            $this->session->set_flashdata(validation_errors()); 
            $market= "Nigeria";
            $this->data['market'] = $this->base_model->get_parameters_menu('Markets',
                'market',$market,'Market');                
            $this->data['current_user'] = $this->current_user;
            $this->data['cust_type'] = $this->base_model->get_parameters_menu('Customer Type',
                'cust_type',$this->data['main_data']->cust_type,'Customer Type');                              
            $this->render('customers/import_sales');
        }
    } 
    

    private function import($sales_id,$filename,$data_table)
    {
        $this->load->library('Excel');
        try {
            $objPHPExcel = PHPExcel_IOFactory::load('./uploads/imports/'.$filename);
           //$objWorksheet = $objPHPExcel->setActiveSheetIndex(0);
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
        $table_data = array();
        $cnt=0;
        switch ($data_table){
            case "focus_brand_details":
                $columns="cust_code,cust_name,vol,sales_id";
                $update_rule="vol=values(vol)";  
                break;
            case "reward_kpi_details":
                $columns="cust_code,cust_name,warehouse_access,correct_display,tca_comms,correct_price,inventory,loyalty,sales_id";
                $update_rule="warehouse_access=values(warehouse_access),correct_display=values(correct_display),"
                    . "tca_comms=values(tca_comms),correct_price=values(correct_price),"
                    . "inventory=values(inventory),loyalty=values(loyalty)";
                break;
            DEFAULT:
                $columns="cust_code,cust_name,sku_code,vol,sales_id";
                $update_rule="vol=values(vol)";  
                break;                
        }
        
        for ($row = 1; $row <= $highestRow-1; $row++)
        {    
            switch ($data_table){
                case "focus_brand_details":
                    $import_data = array();
                    $cust_code=empty($rowData[$row][0])?'':$rowData[$row][0];
                    $cust_name=empty($rowData[$row][1])?'':$rowData[$row][1];                 
                    $vol=empty($rowData[$row][2])?0:$rowData[$row][2];
                    $import_data = '("'.$cust_code.'","'.$cust_name.'","'.$vol.'","'.$sales_id.'")';
                    $table_data[$cnt] = $import_data;                    
                    $cnt+=1;         
                    break;
                case "reward_kpi_details":
                    $import_data = array();
                    $cust_code=empty($rowData[$row][0])?'':$rowData[$row][0];
                    $cust_name=empty($rowData[$row][1])?'':$rowData[$row][1];                 
                    $warehouse_access=trim($rowData[$row][2])<>'Yes'?'':$rowData[$row][2];
                    $correct_display=trim($rowData[$row][3]) <>'Yes'?'':$rowData[$row][3];
                    $tca_comms=trim($rowData[$row][4])<>'Yes'?'':$rowData[$row][4];
                    $correct_price=trim($rowData[$row][5])<>'Yes'?'':$rowData[$row][5];
                    $loyalty=trim($rowData[$row][6])<>'No'?'':'Yes';
                    $inventory=empty($rowData[$row][7]) || is_nan($rowData[$row][7])?NULL:$rowData[$row][7];
                    $import_data = '("'.$cust_code.'","'.$cust_name.'","'.$warehouse_access.'","'.$correct_display
                        .'","'.$tca_comms.'","'.$correct_price.'","'.$inventory.'","'.$loyalty.'","'.$sales_id.'")';
                    $table_data[$cnt] = $import_data;
                    $cnt+=1;   
                    break;
                DEFAULT:
                    for ($column = 2; $column <= $columncount-1; $column++) {
                        $import_data = array();
                        $cust_code=empty($rowData[$row][0])?'':$rowData[$row][0];
                        $cust_name=empty($rowData[$row][1])?'':$rowData[$row][1];                    
                        $sku_code=empty($colData[0][$column])?'':$colData[0][$column];
                        $vol=empty($rowData[$row][$column])?0:$rowData[$row][$column];
                        //if ($vol<>0){
                            $import_data = '("'.$cust_code.'","'.$cust_name.'","'.$sku_code.'","'.abs($vol).'","'.$sales_id.'")';                            
                            $table_data[$cnt] = $import_data;
                            $cnt+=1;
                        //}
                    }
            }
        }
        //$this->db->insert_batch($data_table, $table_data); 
        $str = " INSERT INTO $data_table (".$columns.") Values ".implode (",",$table_data)."" 
            . " ON DUPLICATE KEY UPDATE $update_rule";
        $this->db->query($str);
        return TRUE;
    }     
    
    public function customers_list()
    {
        $this->data['page_title'] = 'Customers List';
        $this->form_script();
                
        $this->data['data_columns']='{ "data": "id" },{ "data": "cust_code" },'
            . '{ "data": "cust_name" },{ "data": "cust_type" },{"data": "cust_category" },{ "data": "location" },'
            . '{ "data": "market" },{ "data": "phone_no" },{ "data": "alt_phone" },{ "data": "cust_addr" },'
            . '{"data": "contact_person" },{ "data": "designation" },{"data": "distributor" },{"data": "email" },'
            .'{ "data": "cust_id","visible":false }';         
        $this->data['data_section']="Customers";
        $this->data['data_table']="customers";        
        $this->data['data_key']="cust_id";
        $this->data['data_access']=['no-edit'];      
        $this->data['mini']=TRUE;
        $this->datatable_script($this->data['data_section']); 
        
        $this->data['cust_category'] = $this->base_model->get_parameters_menu('Customer Category',
                'cust_category','','Customer Category'); 
        $this->data['cust_type'] = $this->base_model->get_parameters_menu('Customer Type',
                'cust_type','','Customer Type'); 
        $sql="SELECT locations.area as 'name',locations.area as 'value' FROM locations where market='Nigeria' order by area";
        $this->data['location'] = $this->base_model->get_record_menu($sql,'location','','Location');   
        $this->render('customers/customers_view_list');
    }   
    
    private function get_customers($data_key='',$id=''){
        $data_table="customers";
        if($data_key==''){
            header('Content-Type: application/json');          
            $total_data=$this->base_model->rec_count($data_table);
            $total_data=is_null($total_data)?0:$total_data;
            $main_data=$this->base_model->get_record($data_table);
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
    
    public function sales_volume()
    {            
        $this->data['page_title'] = 'Sales Vol List';   
        $this->data['data_columns']='{"data": "import_date" },'
            . '{ "data": "imported_by" },{"data": "period_week" },{ "data": "fullmonth" },'
            . '{ "data": "cust_type" },{ "data": "market" },{  "data": "sales_id","visible": false}';
        $this->data['data_section']="Customers";
        $this->data['data_table']="sales";   
        $this->data['data_access']=['no-edit'];        
        $this->data['data_key']="sales_id";

        $this->data['mini']=TRUE; 
        $this->datatable_script($this->data['data_section'],'desc');
        $this->render('customers/sales_vol_list');
    }    
    
    private function get_sales($data_key='',$id='')
    {
        $data_table="sales";

        if($data_key==''){
            header('Content-Type: application/json');          

            
            $temp_data=$this->base_model->run_qry("CALL GET_SALES()",'qry');

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
  
    public function view_sales_details($id)
    {              
        $this->data['page_title'] = 'Sales Vol Details - '.$id;  
        $main_data=$this->base_model->get_single_record($id,'sales_id','sales');        
        if ($main_data->cust_type==='Key Account') {
             $this->data['data_columns']='{"data": "cust_id" },'
                .'{"data": "cust_code" },{ "data": "cust_name" },{ "data": "cust_type" },'
                . '{ "data": "cust_category" },{ "data": "total_sales" , "render": function ( data, type, row ) {
                        return format_number(data);}},'
                . '{ "data": "total_target" , "render": function ( data, type, row ) {
                        return format_number(data);}},'
                . '{ "data": "percent_target" , "render": function ( data, type, row ) {
                        return format_number(data);}},'                
                . '{ "data": "total_growth" , "render": function ( data, type, row ) {
                        return format_number(data);}},'  
                . '{ "data": "pro_growth" , "render": function ( data, type, row ) {
                        return format_number(data);}},'  
                . '{ "data": "focus_vol" , "render": function ( data, type, row ) {
                        return format_number(data);}},'    
                . '{ "data": "focus_target" , "render": function ( data, type, row ) {
                        return format_number(data);}},'                     
                . '{ "data": "total_premium" , "render": function ( data, type, row ) {
                        return format_number(data);}},'             
                . '{ "data": "premium_percent" , "render": function ( data, type, row ) {
                        return format_number(data);}},
                   {  "data": "details_id","visible":false }'                
                ;                 
        } else {
            $this->data['data_columns']='{"data": "cust_id" },'
                .'{"data": "cust_code" },{ "data": "cust_name" },{ "data": "cust_type" },'
                . '{ "data": "cust_category" },{ "data": "total_sales" , "render": function ( data, type, row ) {
                        return format_number(data);}},'
                . '{ "data": "total_target" , "render": function ( data, type, row ) {
                        return format_number(data);}},'
                . '{ "data": "percent_target" , "render": function ( data, type, row ) {
                        return format_number(data);}},'                
                . '{ "data": "total_sellout" , "render": function ( data, type, row ) {
                        return format_number(data);}},{  "data": "details_id","visible":false }'                
                ;         
        }               
        $this->data['data_table']="sales_total";
        $this->data['data_section']="Customers";
        $this->data['data_key']="details_id";
        $this->data['data_var']=$id;
        $this->data['data_access']=['no-edit'];
        $this->data['sales_id']=$main_data->sales_id; 
        
        $this->data['import_date']=date("d M,Y", strtotime($main_data->import_date)); 
        $this->data['imported_by']=$main_data->imported_by; 
        $this->data['period_week']=$main_data->period_week; 
        $this->data['fullmonth']=$main_data->fullmonth; 
        $this->data['cust_type']=$main_data->cust_type; 
        $this->data['market']=$main_data->market; 
        $this->data['cust_type']=$main_data->cust_type; 
        
        $this->data['mini']=FALSE; 
        $this->datatable_script($this->data['data_section'],'desc');    
        
        $this->render('customers/sales_total_vol');
    }
    
    private function get_sales_total($data_key='',$id='')
    {
        $data_table="customers";
        $key="cust_id";
        if($data_key==''){
            header('Content-Type: application/json');   
            $temp_qry=$this->base_model->run_qry("CALL GET_REWARD_DETAILS($id)",'qry');
            
            $total_data=$temp_qry->num_rows();
            $total_data=is_null($total_data)?0:$total_data;
            
            $main_data=$temp_qry->result();
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
    
    public function view_missing_customers($id)
    {              
        $this->data['data_access']=['noval'];  
        $this->data['page_title'] = 'Missing Customers List - '.$id;  
        $this->data['data_columns']='{"data": "cust_code" },{ "data": "cust_name" },'
            . '{ "data": "total_sales" , "render": function ( data, type, row ) {
                    return format_number(data);}},'
            . '{ "data": "total_target" , "render": function ( data, type, row ) {
                    return format_number(data);}},'
            . '{ "data": "percent_target" , "render": function ( data, type, row ) {
                    return format_number(data);}},'                
            . '{ "data": "total_sellout" , "render": function ( data, type, row ) {
                    return format_number(data);}}'                
            ;
                   
        $this->data['data_section']="Customers";
        $this->data['data_table']="missing_customers";
        $this->data['data_key']="details_id";
        $this->data['data_var']=$id;

        $main_data=$this->base_model->get_single_record($id,'sales_id','sales');
        $this->data['sales_id']=$main_data->sales_id; 
        
        $this->data['import_date']=date("d M,Y", strtotime($main_data->import_date)); 
        $this->data['imported_by']=$main_data->imported_by; 
        $this->data['period_week']=$main_data->period_week; 
        $this->data['fullmonth']=$main_data->fullmonth; 
        $this->data['cust_type']=$main_data->cust_type; 
        $this->data['market']=$main_data->market; 
        $this->data['cust_type']=$main_data->cust_type; 
        
        $this->data['mini']=FALSE; 
        $this->datatable_script($this->data['data_section'],'asc');  
        
        $this->render('customers/missing_cust_list');
    }
    
    private function get_missing_customers($data_key='',$id='')
    {
        $data_table="reward_details";
        $key="cust_id";
        if($data_key==''){
            header('Content-Type: application/json');   
            $temp_qry=$this->base_model->run_qry("CALL GET_MISSING_CUSTOMERS($id)",'qry');
            
            $total_data=$temp_qry->num_rows();
            $total_data=is_null($total_data)?0:$total_data;
            
            $main_data=$temp_qry->result();
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
    
    public function view_sales_total_details($id)
    {
        $this->data['data_access']=['noval'];  
        $this->data['page_title'] = 'Customer Sales Details'.$id;
        $this->data['customer'] = $this->base_model->run_qry("CALL TOTAL_CUSTOMER_SALES_INFO('$id')");   
        switch ($this->data['customer']->cust_type){
            Case "Key Account":
                $this->data['data_columns']='{"data": "sku_code" },{"data": "sku_name" },'
                . '{"data": "actual_vol","render": function ( data, type, row ) {
                        return format_number(data);
                    }},
                    {"data": "pro_base","render": function ( data, type, row ) {
                        return format_number(data);
                    }},                    
                    {"data": "pro_growth","render": function ( data, type, row ) {
                        return format_number(data);
                    }}'; 
                break;
            Case "WAM Customers":
                $this->data['data_columns']='{"data": "sku_code" },{"data": "sku_name" },'
                . '{"data": "salesvol","render": function ( data, type, row ) {
                        return format_number(data);
                    }},                
                    {"data": "target","render": function ( data, type, row ) {
                        return format_number(data);
                    }},
                    {"data": "sellout","render": function ( data, type, row ) {
                        return format_number(data);
                    }}';  
                break;
            DEFAULT:
                $this->data['data_columns']='{"data": "sku_code" },{"data": "sku_name" },'
                . '{"data": "salesvol","render": function ( data, type, row ) {
                        return format_number(data);
                    }},
                    {"data": "prorated","render": function ( data, type, row ) {
                        return format_number(data);
                    }},                    
                    {"data": "target","render": function ( data, type, row ) {
                        return format_number(data);
                    }},
                    {"data": "sellout","render": function ( data, type, row ) {
                        return format_number(data);
                    }}';  
                break;            
        }
        $this->data['data_section']="Customers";
        $this->data['data_var']=$id;
        $this->data['data_table']="customer_monthly_sales";   
        $this->data['data_key']="sales_id"; 

        $this->data['mini']=FALSE; 
        $this->datatable_script($this->data['data_section']);  
        $this->render('customers/customer_monthly_sales_details');
    }    
    
    private function get_customer_monthly_sales($data_key='',$id='')
    {
        $data_table="sales";
        $key="sales_id";
        if($data_key==''){
            header('Content-Type: application/json');    
            $temp_qry=$this->base_model->run_qry("CALL GET_SALES_DETAILS('$id')",'qry');
            
            $total_data=$temp_qry->num_rows();
            $total_data=is_null($total_data)?0:$total_data;
            
            $main_data=$temp_qry->result();
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
    
   public function view_customers_details($id)
    {             
       $this->data['data_access']=['noval'];  
        $this->data['page_title'] = 'Customer Details'.$id;
        $this->data['data_columns']='{  "data": "sales_id",visible:false }, {"data": "import_date" },'
                . '{ "data": "period_week" },{"data": "fullmonth" },{"data": "sku_code" },'
                . '{"data": "sku_name" },'
                . '{"data": "salesvol","render": function ( data, type, row ) {
                        return format_number(data);
                    }},
                    {"data": "prorated","render": function ( data, type, row ) {
                        return format_number(data);
                    }},                    
                    {"data": "target","render": function ( data, type, row ) {
                        return format_number(data);
                    }},
                    {"data": "sellout","render": function ( data, type, row ) {
                        return format_number(data);
                    }}';                    

        $this->data['data_section']="Customers";
        $this->data['data_var']=$id;
        $this->data['data_table']="customer_sales";   
        $this->data['data_key']="sales_id"; 
        $main_data=$this->base_model->get_single_record($id,'cust_id','customers');
        $this->data['cust_code']=$main_data->cust_code; 
        $this->data['cust_name']=$main_data->cust_name; 
        $this->data['cust_addr']=$main_data->cust_addr; 
        $this->data['location']=$main_data->location; 
        $this->data['cust_type']=$main_data->cust_type; 
        $this->data['cust_category']=$main_data->cust_category; 
        $this->data['contact_person']=$main_data->contact_person; 
        $this->data['designation']=$main_data->designation;
        $this->data['phone_no']=$main_data->phone_no; 
        $this->data['alt_phone']=$main_data->alt_phone; 
        $this->data['email']=$main_data->email; 
        $this->data['contact_via']=$main_data->contact_via;       
        $this->data['mini']=TRUE; 
        $this->datatable_script($this->data['data_section'],'asc');    
        $this->render('customers/customer_sales_details');
    }    
    
    private function get_customer_sales($data_key='',$id='')
    {
        $data_table="sales";
        $key="sales_id";
        if($data_key==''){
            header('Content-Type: application/json');          
            $temp_qry=$this->base_model->run_qry("CALL GET_CUSTOMER_SALES_DETAILS('$id')",'qry');
            
            $total_data=$temp_qry->num_rows();
            $total_data=is_null($total_data)?0:$total_data;
            
            $main_data=$temp_qry->result();
            $main_data=$total_data==0?array():$main_data;
            
            $json_data = array(
                "recordsTotal"    => intval( $total_data ),  // total number of records
                "recordsFiltered" => intval( $total_data ), // total number of records after searching, if there is no searching then totalFiltered = totalData
                "data"            => $main_data   // total data array
                );
            echo json_encode($json_data); 
        } else {
            //$data = $this->base_model->get_single_record($id,$data_key,$data_table); 
            echo json_encode(NULL);
        }   
    }    
    
    public function ajax_list($data_table,$id='')
    {       
        $func='get_'.$data_table;
        $this->$func('',$id);
    }    
    
    public function view_page($data_table,$id='')
    {       
        $func='view_'.$data_table.'_details';
        $this->$func($id);      
    } 
       
    public function ajax_edit($data_table,$data_key='',$id='')
    {
        $func='get_'.$data_table;
        $this->$func($data_key,$id);
    }  
    
    public function ajax_delete($data_table,$data_key)
    {       
        $rows=$this->input->post('rows');
        foreach($rows as $row){
            $this->base_model->delete($data_table,array($data_key => $row));
        }
        echo json_encode(array("status" => TRUE)); 
    }    
    
    public function delete_row($del_key){
        if($this->current_user->user_role!=="Initiator"){
            $this->session->set_flashdata('error','Only Initiators can delete an imported sales volume information');
            redirect('customers/sales_volume');
        }        
        $get_result=$this->base_model->run_qry("CALL CANCEL_SALES(".$del_key.")","run");     
        redirect('customers/sales_volume');
    }
}
