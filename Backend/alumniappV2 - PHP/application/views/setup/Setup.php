<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Setup extends User_Controller
{
    function __construct()
    {
        parent::__construct();
        $this->data['data_access']=array("Add","Edit","Delete");
        if($this->current_user->user_role!=="Admin"){
            redirect('requests/dashboard');
        }
    }

    public function index()
    {
        $this->view_parameters();
    }

    public function view_parameters()
    {   
        $this->data['page_title'] = 'Setup System Parameters';
        $this->data['data_columns']='{ "data": "setup_id","visible":false },{ "data": "setup_name" },'
                . '{ "data": "setup_value" }';
        $this->data['data_section']="Setup";
        $this->data['data_table']="setup_parameters";        
        $this->data['data_key']="setup_id";     
        $this->data['data_editflds']='
            $(\'[name="id"]\').val(data.setup_id);
            $(\'[name="setup_name"]\').val(data.setup_name);
            $(\'[name="setup_value"]\').val(data.setup_value);';
        $this->data['mini']=FALSE;
        $this->datatable_script($this->data['data_section'],'asc',1);
        $this->render('setup/setup_parameters');       
    }
    
    public function view_items_table()
    {   
        $this->data['page_title'] = 'Setup Items Table';
        $this->data['data_columns']='{ "data": "sno","visible":false },'
        . '{ "data": "item_type" },{ "data": "item_code" },{ "data": "item_desc" },'
        . '{ "data": "item_price", "render": function ( data, type, row ) '
           . '{if(data==\'0.00\'){return \'\'}else{return format_number(data)};} }';
        $this->data['data_section']="Setup";
        $this->data['data_table']="items_table";        
        $this->data['data_key']="sno";     
        $this->data['data_editflds']='
            $(\'[name="sno"]\').val(data.sno);
            $(\'[name="item_type"]\').val(data.item_type);
             $(\'[name="item_code"]\').val(data.item_code);
            $(\'[name="item_desc"]\').val(data.item_desc);
            $(\'[name="item_price"]\').val(data.item_price);';
        $this->data['mini']=FALSE;
        $this->datatable_script($this->data['data_section'],'asc',1);
        $this->render('setup/setup_items');       
    }    

    public function view_dept()
    {   
        $this->data['page_title'] = 'Setup Department Approvers';
        $this->data['data_columns']='{ "data": "deptcode" },'
                . '{ "data": "deptname" },{ "data": "hod" }';
        $this->data['data_section']="Setup";
        $this->data['data_table']="dept";        
        $this->data['data_key']="deptcode";     
        $this->data['data_editflds']='
            $(\'[name="deptcode"]\').val(data.deptcode);
            $(\'[name="deptname"]\').val(data.deptname);
            $(\'[name="hod"]\').val(data.hod);';
        $this->data['mini']=FALSE;
        $sql="SELECT users.email as value, users.fullname as 'name' "
                . "FROM users where active=1 order by users.first_name asc";        
        $this->data['hod'] = $this->base_model->get_record_menu($sql,'hod','','Select Manager');
                
        $this->datatable_script($this->data['data_section'],'asc',0);
        $this->render('setup/setup_dept');       
    }    
    
    
    public function view_sales_product_price()
    {               
         $this->view_product_detail();
                               
        
    } 
       // product detail
    public function view_product_detail()
    {    
        $this->form_script_modal();
        $this->data['page_title'] = 'Sales Product Price';
        $this->data['data_columns']='{ "data": "sno","visible":false },'
        . '{ "data": "product_code" },{ "data": "prod_desc" },{ "data": "cust_type" },{ "data": "uom" },'
        . '{ "data": "unit_price", "render": function ( data, type, row ) '
           . '{if(data==\'0.00\'){return \'\'}else{return format_number(data)};} }';
        $this->data['data_section']="Setup";
        $this->data['data_table']="sales_product_price";        
        $this->data['data_key']="sno";     
        $this->data['data_editflds']='
            $(\'[name="sno"]\').val(data.sno);
            $(\'[name="product_code"]\').val(data.product_code);
             $(\'[name="prod_desc"]\').val(data.prod_desc);
             var $select1 = $(\'#uom\').selectize(); 
             var selectize1 = $select1[0].selectize; 
             selectize1.setValue(data.uom, false);
            
            $(\'[name="cust_type"]\').val(data.cust_type);

            $(\'[name="unit_price"]\').val(data.unit_price);';
        $this->data['mini']=FALSE;
        $this->datatable_script($this->data['data_section'],'asc',1);
          
        $this->render('setup/setup_product_detail');
    }
    //add the update customer location
    private function update_customer_location($action,$data_table,$data_key)
    {          
        //$this->form_validation->set_rules('id','id','trim|required');
        // $this->form_validation->set_rules('cust_name','cust_name','required');        
        // $this->form_validation->set_rules('cust_code','cust_code','trim');                
        $this->form_validation->set_rules('store','store','trim|required');
        // $this->form_validation->set_rules('name_store','name_store','trim|required');
        $this->form_validation->set_rules('email','email','trim|required');

        if($this->form_validation->run() === true){
            $process_id=$this->input->post('sno');
            $id=$this->input->post('sno'); 
            $appr_name=$this->input->post('appr_name');
            $approver_from=$this->input->post('approver_from');
            
            $upd_data = array(
                'cust_name'     => $this->input->post('cust_name'),
                'cust_code'   => $this->input->post('cust_code'),                  
                'store'       => $this->input->post('store'),
                'name_store'   => $this->input->post('cust_name')." - ".$this->input->post('store'),
                'email'   => $this->input->post('email')                
            );        
          
            if ($action=="add"){
                // $order_no=$this->base_model->rec_count($data_table,$process_id,'sno');
                // $upd_data['order_no']=empty($order_no)?1:$order_no+1;
                $this->base_model->insert($upd_data,$data_table);
            }
            if ($action=="update"){
                $this->base_model->update($upd_data,$id,$data_table,array($data_key => $id)); 
            }
            echo json_encode(array("status" => TRUE));            
        } else {
           header('Content-Type: application/json');
           echo json_encode(validation_errors());
        }                                                   
    }
    private function update_sales_product_price($action,$data_table,$data_key)
    {          
        //$this->form_validation->set_rules('id','id','trim|required');
        $this->form_validation->set_rules('product_code','product_code','required');        
        $this->form_validation->set_rules('prod_desc','prod_desc','trim');                
        $this->form_validation->set_rules('cust_type','cust_type','trim|required');
        $this->form_validation->set_rules('uom','uom','trim|required');
        $this->form_validation->set_rules('unit_price','unit_price','trim|required');

        if($this->form_validation->run() === true){
            $process_id=$this->input->post('sno');
            $id=$this->input->post('sno'); 
            $appr_name=$this->input->post('appr_name');
            $approver_from=$this->input->post('approver_from');
            
            $upd_data = array(
                'product_code'     => $this->input->post('product_code'),
                'prod_desc'   => $this->input->post('prod_desc'),                  
                'cust_type'       => $this->input->post('cust_type'),
                'uom'   => $this->input->post('uom'),
                'unit_price'   => $this->input->post('unit_price')                
            );        
          
            if ($action=="add"){
                // $order_no=$this->base_model->rec_count($data_table,$process_id,'process_id');
                // $upd_data['order_no']=empty($order_no)?1:$order_no+1;
                $this->base_model->insert($upd_data,$data_table);
            }
            if ($action=="update"){
                $this->base_model->update($upd_data,$id,$data_table,array($data_key => $id)); 
            }
            echo json_encode(array("status" => TRUE));            
        } else {
           header('Content-Type: application/json');
           echo json_encode(validation_errors());
        }                                                   
    }

    public function view_users()
    {
        $this->data['page_title'] = 'Setup Users';
        $this->form_script_modal();
        $this->data['data_columns']='{ "data": "id","visible":false },'
                . '{ "data": "fullname" },{ "data": "email" },'
                . '{ "data": "designation" },{ "data": "dept" },'
                . '{ "data": "cust_name" },{ "data": "cust_code" },'                  
                . '{ "data": "manager" },{ "data": "user_role" },'              
                . '{ "data": "active",
                        "render": function ( data, type, row ) {
                            if(data==1){
                                return "Active";
                            } else {
                                return "Inactive";
                            }
                        }
                    }';  
        $this->data['data_section']="Setup";
        $this->data['data_table']="users";        
        $this->data['data_key']="id";
        $this->data['data_editflds']='
            $(\'[name="id"]\').val(data.id);
            $(\'[name="email"]\').val(data.email);
            $(\'[name="first_name"]\').val(data.first_name);
            $(\'[name="last_name"]\').val(data.last_name);          
            $(\'[name="designation"]\').val(data.designation);
            $(\'[name="manager"]\').val(data.manager);
            $(\'[name="cust_name"]\').val(data.cust_name);
            $(\'[name="cust_code"]\').val(data.cust_code);            
               
            if(data.active==1){
                $(\'input:radio[id=Active]\').prop(\'checked\', true);
               // $(\'[name="user_status"]\').val(["Active"]);
            }else {
                $(\'input:radio[id=Inactive]\').prop(\'checked\', true);
               // $(\'[name="user_status"]\').val(["Inactive"]);
            }
            
            var $select2 = $(\'#dept\').selectize(); 
            var selectize2= $select2[0].selectize; 
            selectize2.setValue(data.dept, false);  
          
            var $select1 = $(\'#mgr_email\').selectize(); 
            var selectize1 = $select1[0].selectize; 
            selectize1.setValue(data.mgr_email, false);     
            
            var $select2 = $(\'#user_role\').selectize(); 
            var selectize2= $select2[0].selectize; 
            selectize2.setValue(data.user_role, false);
            ';                    
        $this->data['mini']=TRUE;
        $this->datatable_script($this->data['data_section'],'asc',1);
        $sql="SELECT users.email as value, users.fullname as 'name' "
                . "FROM users where active=1 order by users.first_name asc";        
        $this->data['mgr_email'] = $this->base_model->get_record_menu($sql,'mgr_email','','Select Manager');
        
        $this->data['user_role'] = $this->base_model->get_parameters_menu('User Roles',
                'user_role','','Enter User Role'); 
        $this->data['company'] = $this->base_model->get_parameters_menu('Markets',
                'company','Pernod Ricard Nigeria','Enter Market');
        
         $sql_dept="SELECT deptname as value, deptname as 'name' FROM dept order by deptname asc";      
         $this->data['dept'] = $this->base_model->get_record_menu($sql_dept,
                'dept','','Enter Departments');   
         
        $this->data['after_foot']=$this->data['after_foot'].'
        <script type="text/javascript">            
            $("#mgr_email").change(function () {
                var mgr_data= $("#mgr_email option:selected").text();
                $(\'#manager\').val(mgr_data);                
            });
        </script>
         ';        
        $this->render('setup/setup_users_list');
    }
  
    public function view_workflow()
    {
        $this->data['page_title'] = 'Setup Workflow';
        $this->data['data_columns']='{ "data": "process_id","visible":false },'
            .'{ "data": "request_title" },{ "data": "request_type" },'
                . '{ "data": "approvers" }';
        $this->data['data_section']="Setup";
        $this->data['data_table']="workflow";        
        $this->data['data_key']="process_id";
        $this->data['data_access']=['no-edit'];                  
        $this->data['mini']=FALSE;
        $this->datatable_script($this->data['data_section'],'asc',1);
        $this->render('setup/setup_workflow_list');
    }
    public function add_workflow($action=null,$id=null)
    {
        $this->form_script();
        if($action==='edit'){
            $this->data['page_title'] = 'Edit Workflow'; 
            $this->data['form_action']='edit';
            $this->data['main_data']=is_null($id)?array():
                $this->base_model->get_single_record($id,'process_id','workflow');
        } else {
            $this->data['page_title'] = 'Add Workflow'; 
            $this->data['form_action']='add';            
        }
        $this->form_validation->set_rules('request_title','Request Title','trim|required');
        $this->form_validation->set_rules('request_type','request type','trim|required');
        $this->form_validation->set_rules('request_form','request form','trim|required');
        $this->form_validation->set_rules('request_table','request table','trim|required');
        $this->form_validation->set_rules('wait_time','Wait Time','trim|required');  
        $this->form_validation->set_rules('expiration_action','Action Type','trim|required');
        
        if($this->form_validation->run() === true){ 
            $id=$this->input->post('id');
            $upd_data = array(
                'request_title' => $this->input->post('request_title'),
                'request_type'  => $this->input->post('request_type'),
                'request_form'  => $this->input->post('request_form'),
                'request_table' => $this->input->post('request_table'),
                'wait_time'     => $this->input->post('wait_time'),                
                'expiration_action'     => $this->input->post('expiration_action'),
                'last_updated'   => date('Y-m-d H:i:s')
            );
            if ($action=="add"){$id=$this->base_model->insert($upd_data,'workflow'); }
            if ($action=="edit"){$this->base_model->update($upd_data,$id,
                    'workflow',array('process_id' => $id)); }
            redirect('setup/view_workflow');
        } else {                  
            $this->render('setup/setup_add_workflow');
        } 
    }
    
    
    
      // Add Customer Detail
    public function add_customer($action=null,$id=null){
        $this->form_script();
        $this->form_script_modal();
        if($action==='edit'){
            $this->data['data_section']="Setup";
            $this->data['page_title'] = 'Edit Customer Details'; 
            $this->data['form_action']='edit';
            $this->data['main_data']=is_null($id)?array():
                $this->base_model->get_single_record($id,'cust_code','customer_master_data');
        } else {
            $this->data['page_title'] = 'Add Customer Details'; 
            $this->data['form_action']='add';            
        }
        $this->form_validation->set_rules('cust_name1','Enter Name','trim|required');
        $this->form_validation->set_rules('cust_code','Customer Code','trim|required');
        $this->form_validation->set_rules('cust_type','Customer Type','trim|required');
        $this->form_validation->set_rules('credit_limit','Credit Limit','trim|required');
        $this->form_validation->set_rules('credit_type','Credit Type','trim|required');  
        
        
        if($this->form_validation->run() === true){ 
            $id=$this->input->post('id');
            $upd_data = array(
                'cust_name' => $this->input->post('cust_name1'),
                'cust_code'  => $this->input->post('cust_code'),
                'cust_type'  => $this->input->post('cust_type'),
                'credit_limit' => $this->input->post('credit_limit'),
                'credit_type'     => $this->input->post('credit_type')               
                
            );
            // $this->base_model->insert($upd_data,'customer_master_data');  
            // redirect('setup/cust_view');
            if ($action=="add"){$id=$this->base_model->insert($upd_data,'customer_master_data'); }
            if ($action=="edit"){$this->base_model->update($upd_data,$id,
                    'customer_master_data',array('cust_code' => $id)); }
            redirect('setup/view_customer');
        } else {                  
            $this->render('setup/setup_add_customer');
        } 
    }
    public function view_customer()
    {
        $this->data['page_title'] = 'Setup Customer';
        $this->data['data_columns']='{ "data": "cust_name"},'
            .'{ "data": "cust_code" },{ "data": "cust_type" },{ "data": "credit_type" },'
                . '{ "data": "credit_limit" }';
        $this->data['data_section']="Setup";
        $this->data['data_table']="customer_master_data";        
        $this->data['data_key']="cust_code";
        $this->data['data_access']=['no-edit'];                  
        $this->data['mini']=FALSE;
        $this->datatable_script($this->data['data_section'],'asc',1);
        $this->render('setup/setup_customer_list');
    }
    public function view_customer_details($id)
    {    
        $this->form_script_modal();
        $this->data['page_title'] = 'Customers Details - '.$id;  
        $this->data['data_columns']='{"data": "sno" }, {  "data": "cust_name" }, '
            . '{"data": "cust_code" },{ "data": "store" },{ "data": "name_store" },{ "data": "email" }';
        $this->data['data_section']="Setup";
        $this->data['data_var']=$id;
        $this->data['data_table']="customer_location";   
        $this->data['data_key']="sno"; 

        $this->data['data_editflds']='
            $(\'[name="id"]\').val(data.sno);
            $(\'[name="sno"]\').val(data.sno);
            $(\'[name="cust_name"]\').val(data.cust_name);
            $(\'[name="cust_code"]\').val(data.cust_code);
            $(\'[name="store"]\').val(data.store);
            
            $(\'[name="name_store"]\').val(data.name_store);
            $(\'[name="email"]\').val(data.email);
        ';
        
        $this->data['main_data']=$this->base_model->get_single_record($id,'cust_code','customer_master_data');
        $this->data['mini']=FALSE;
        $this->datatable_script($this->data['data_section'],'asc',1); 
        $this->render('setup/setup_customer_detail');
    }
    
    public function view_workflow_details($id)
    {    
        $this->form_script_modal();
        $this->data['page_title'] = 'Workflow Details - '.$id;  
        $this->data['data_columns']='{"data": "approver_id","visible": false }, {  "data": "order_no" }, '
            . '{"data": "approver_name" },{ "data": "appr_function" },{ "data": "notifier" }';
        $this->data['data_section']="Setup";
        $this->data['data_var']=$id;
        $this->data['data_table']="workflow_approvers";   
        $this->data['data_key']="approver_id"; 

        $this->data['data_editflds']='
            $(\'[name="id"]\').val(data.approver_id);

            var $select1 = $(\'#approver_from\').selectize(); 
            var selectize1 = $select1[0].selectize; 
            selectize1.setValue(data.approver_from, false);

            var $select1 = $(\'#appr_name\').selectize(); 
            var selectize1 = $select1[0].selectize; 
            selectize1.setValue(data.appr_name, false);
            
            $(\'[name="appr_function"]\').val(data.appr_function);
            $(\'[name="notifier"]\').val(data.notifier);
        ';
        
         $this->data['after_foot'].='
                <Script Language="Javascript">'."
                    $('#approver_from').on('change', function() {
                      if ( this.value == 'Approver')
                      {
                        $('.showhide').show();
                      }
                      else
                      {
                        $('.showhide').hide();
                        $('#appr_name').val('');;
                      }
                    }); 
                    $('window').on('load', function() {
                      if ( this.value == 'Approver')
                      {
                        $('.showhide').show();
                      }
                      else
                      {
                        $('.showhide').hide();
                        $('#appr_name').val('');;
                      }
                    });                     
                </script>";

        $this->data['main_data']=$this->base_model->get_single_record($id,'process_id','workflow');
        $sql="SELECT users.id as value, users.fullname as 'name' "
                . "FROM users where active=1  order by users.first_name asc";        
        $this->data['approver_name'] = $this->base_model->get_record_menu($sql,'appr_name','','Select Approver');
        $this->data['mini']=FALSE;
        $this->datatable_script($this->data['data_section'],'asc',1); 
        $this->render('setup/setup_workflow_details');
    }
    // ? changes made to the import function
//    public function import($data_type="locations")
//     {
//         $filename = $this->upload_files();
        
//         $redirectTo = 'setup/view_'.$data_type;
//         $rowData=array();
//         $import_data=array();
//         $db_name=$data_type;
        
//         switch ($data_type) {
//             case "users":
//                 $db_flds= array('email','first_name','last_name','fullname','designation','dept','manager','mgr_email','user_role'); 
//                 break;       
//             case "items_table":
//                 $db_flds= array('item_type','item_code','item_desc','item_price'); 
//                 $update_rule="item_type=values(item_type),item_code=values(item_code),".
//                     "item_desc=values(item_desc),item_price=values(item_price)";                 
//                 break;              
//         }        
        
//         if (is_null($filename)) {
//             $this->session->set_flashdata('error','Error in File Import - import failed');
//             redirect($redirectTo);
//         }
//         $this->load->library('Excel');
//         $this->session->set_flashdata('message','Successful upload'.$filename);
//         try {
//               /// it will be your file name that you are posting with a form or 
//               //can pass static name $_FILES["file"]["name"];
//             $objPHPExcel = PHPExcel_IOFactory::load('./uploads/imports/'.$filename);
//            //$objWorksheet = $objPHPExcel->setActiveSheetIndex(0);
//             $objWorksheet = $objPHPExcel->getSheet(0); 
//         }
//         catch(Exception $e){
//             $this->session->set_flashdata('error','Error in Excel library - import failed');
//             redirect($redirectTo);
//             exit;
//         }
        
//         $highestRow = $objWorksheet->getHighestDataRow(); 
//         $highestColumn = $objWorksheet->getHighestDataColumn();
//         $rowData = $objWorksheet->rangeToArray('A2:' . $highestColumn . $highestRow,
//          NULL, TRUE, FALSE);

//         if($data_type=='users'){
//             for ($row = 2; $row <= $highestRow; $row++)
//             {           
//                 $email=$rowData[$row-2][0];
//                 $username = $this->get_id();                  
//                 $password = 'welcomenewuser';
//                 $group[0]=2;
//                 $group[1]=3;
//                 $group_ids=$group;               
//                 $additional_data = array(
//                     'first_name'    => $rowData[$row-2][1],
//                     'last_name'     => $rowData[$row-2][2],
//                     'fullname'      => $rowData[$row-2][1].' '.$rowData[$row-2][2],
//                     'designation'   => $rowData[$row-2][3],
//                     'dept'          => $rowData[$row-2][4],
//                     'manager'       => $rowData[$row-2][5],
//                     'mgr_email'     => $rowData[$row-2][6],
//                     'cust_name'     => $rowData[$row-2][7],
//                     'cust_code'     => $rowData[$row-2][8],                    
//                     'user_role'     => 'User',
//                     'company'       => 'Pernod Ricard Nigeria',                                
//                     'active'        => 1
//                 );       
//                 if(!empty($email)){
//                     $user_id = $this->ion_auth->register($username, $password, $email, $additional_data, $group_ids);
//                 }
//             }
//         } else {
//             $table_data = array();
//             $cnt=0;
           
//             for ($row = 2; $row <= $highestRow; $row++)
//             {            
//                 $col=0;
//                 $import_data=array();
//                 foreach ($db_flds as $flds_val)
//                 {
//                     //$row_val=trim(str_replace(",","",$rowData[$row-2][$col]));
//                     $row_val=trim($rowData[$row-2][$col]);
//                     // $import_data["$flds_val"]= empty($row_val)||($row_val==='-')?NULL:$row_val;
//                      if(strpos( $row_val, "'" ) !== false ){
//                          $import_data["$flds_val"]= empty($row_val)||($row_val==='-')?"''":$this->db->escape($row_val);
//                      }  else {
//                          $import_data["$flds_val"]= empty($row_val)||($row_val==='-')?"''":"'".$row_val."'";                           
//                      }
//                     $col++;                     
//                 }
//                 //$table_data[$cnt] = $import_data;
//                 $table_data[$cnt] = '('.implode(',',$import_data).')';
//                 $cnt+=1;                   
//             }
//             //$this->db->insert_batch($db_name, $table_data);                     
//             $str = " INSERT INTO $data_type (".implode(',',$db_flds).") Values ".implode (",",$table_data)." " 
//             . " ON DUPLICATE KEY UPDATE $update_rule";
//             log_message('error',$str);
//             $this->db->query($str);            
//         }    
//         redirect($redirectTo);
//     }

public function import($data_type="locations")
    {
        $filename = $this->upload_files();
        
        $redirectTo = 'setup/view_'.$data_type;
        $redirectTo1 = 'requests/pos_item_view';
        
   
        $rowData=array();
        $import_data=array();
        $db_name=$data_type;
        $sucessRate=false;
        switch ($data_type) {
            case "users":
                $db_flds= array('email','first_name','last_name','fullname','designation','dept','manager','mgr_email','user_role'); 
                break;  
              //  case "items_table":     
            case "items_table_not_in_use_for_now":
                $db_flds= array('item_type','item_code','item_desc','item_price'); 
                $update_rule="item_type=values(item_type),item_code=values(item_code),".
                    "item_desc=values(item_desc),item_price=values(item_price)";                 
                break;  
                         
        }        
        
        if (is_null($filename)) {
            $this->session->set_flashdata('error','Error in File Import - import failed');
            redirect($redirectTo);
        }
        $this->load->library('Excel');
        $this->session->set_flashdata('message','Successful upload'.$filename);
        try {
              /// it will be your file name that you are posting with a form or 
              //can pass static name $_FILES["file"]["name"];
            $objPHPExcel = PHPExcel_IOFactory::load('./uploads/imports/'.$filename);
           //$objWorksheet = $objPHPExcel->setActiveSheetIndex(0);
            $objWorksheet = $objPHPExcel->getSheet(0); 
            
        }
        catch(Exception $e){
            $this->session->set_flashdata('error','Error in Excel library - import failed');
            redirect($redirectTo);
            exit;
        }
        
        $highestRow = $objWorksheet->getHighestDataRow(); 
        $highestColumn = $objWorksheet->getHighestDataColumn();
        $rowData = $objWorksheet->rangeToArray('A2:' . $highestColumn . $highestRow,
         NULL, TRUE, FALSE);
         //var_dump($rowData);
        if($data_type=='users'){
            for ($row = 2; $row <= $highestRow; $row++)
            {           
                $email=$rowData[$row-2][0];
                $username = $this->get_id();                  
                $password = 'welcomenewuser';
                $group[0]=2;
                $group[1]=3;
                $group_ids=$group;               
                $additional_data = array(
                    'first_name'    => $rowData[$row-2][1],
                    'last_name'     => $rowData[$row-2][2],
                    'fullname'      => $rowData[$row-2][1].' '.$rowData[$row-2][2],
                    'designation'   => $rowData[$row-2][3],
                    'dept'          => $rowData[$row-2][4],
                    'manager'       => $rowData[$row-2][5],
                    'mgr_email'     => $rowData[$row-2][6],
                    'cust_name'     => $rowData[$row-2][7],
                    'cust_code'     => $rowData[$row-2][8],                    
                    'user_role'     => 'User',
                    'company'       => 'Pernod Ricard Nigeria',                                
                    'active'        => 1
                );       
                if(!empty($email)){
                    $user_id = $this->ion_auth->register($username, $password, $email, $additional_data, $group_ids);
                }
            }
        }if($data_type=='pos_item'){
            for ($row = 2; $row <= $highestRow; $row++)
            {           
                             
                $additional_data = array(
                  //  'poscode'    => $this->passcode(),
                  'poscode'    => $rowData[$row-2][0],
                    'pos_type'     => "POS",
                   // 'price'      => $rowData[$row-2][4],
                  
                //'qty'   => $rowData[$row-2][2],
                    'qty'   => isset($rowData[$row-2][2]) ? $rowData[$row-2][2] : 0,
                    're_order_level'          => $rowData[$row-2][3],
                    'pos_desc'       => $rowData[$row-2][1],
                    'date_added'           => $this->datetime 
                                        
                    
                );  
                   
               // $this->db->insert_batch($db_name, $additional_data);
               
               $result = $this->base_model->add2('pos_item',$additional_data);  
               log_message("error","here33");
              // var_dump($additional_data);
            }
            
           // return;
        }elseif($data_type=='sales_product_price'){
			 $counter=0; 
			$newCounter=0;
			$length = count($rowData);
            for ($row = 2; $row <= $highestRow; $row++)
            {           
                // $counter=$counter+1;
				$unit_price=isset($rowData[$row-2][5]) ? $rowData[$row-2][5] : 0;           
                $price_value =(float) str_replace(',', '', $unit_price);
                $price_save=trim(number_format($price_value, 2, ".", ""));
				if($row == $length){
					$counter +=$rowData[$row-2][0]+2;
   				 echo 'this is last in the loop.';
					log_message("error",'this is last in the loop.');
					log_message("error",$counter);
					log_message("error",$rowData[$row-2][0]);

  					}
                $additional_data = array(
                   'product_code'    => $rowData[$row-2][1],
                   'sno'    => $rowData[$row-2][0],
                   
                   'unit_price'   => $price_save,
                    'uom'   => $rowData[$row-2][4],
                   'prod_desc'   => $rowData[$row-2][2],
                    'cust_type'       => $rowData[$row-2][3],
                   
                    
                     
                 ); 
				
				
                                
             
               $sucessRate=true;
               log_message("error","here33");
               if($sucessRate==true){

                $passcodeql = $this->db->distinct()->select('unit_price','sno')->from('sales_product_price')->where('sno', $rowData[$row-2][0])->get();
                if( $passcodeql->num_rows() > 0 ) {
                    $sql='UPDATE  sales_product_price   SET unit_price='.$price_save.'  WHERE sno  = '.'"'.$rowData[$row-2][0].'"'.';';
                    $this->db->query($sql);  
                    log_message("error","update sales product");
                }else{
				$checkSnoCode = $this->db->distinct()->select('unit_price','sno')->from('sales_product_price')->where('sno', $counter)->get();
					 if( $checkSnoCode->num_rows() > 0 ) {
						 $newCounter=$rowData[$row-2][0] +1;
					log_message("error",$newCounter);
                    log_message("error","update quantity and ADD NEW ITEM sales produc");
                    log_message("error",$additional_data);
						 
					 $additional_data_price = array(
                   'product_code'    => $rowData[$row-2][1],
                   'sno'    => isset($rowData[$row-2][0]) ? $rowData[$row-2][0] : $newCounter,
                   
                   'unit_price'   => $price_save,
                    'uom'   => $rowData[$row-2][4],
                   'prod_desc'   => $rowData[$row-2][2],
                    'cust_type'       => $rowData[$row-2][3],
                   
                    
                     
                 ); 
                    $ADDNEWITEM = $this->base_model->insert($additional_data_price,'sales_product_price'); 
                   //  $ADDNEWITEM = $this->base_model->add2('sales_product_price',$additional_data);  
                    log_message("error","update quantity and ADD NEW ITEM");
				 }else{
						 log_message("error",$counter);
						  $additional_data_price = array(
                   'product_code'    => $rowData[$row-2][1],
                   'sno'    => isset($rowData[$row-2][0]) ? $rowData[$row-2][0] : $counter,
                   
                   'unit_price'   => $price_save,
                    'uom'   => $rowData[$row-2][4],
                   'prod_desc'   => $rowData[$row-2][2],
                    'cust_type'       => $rowData[$row-2][3],
                   
                    
                     
                 ); 
                    $ADDNEWITEM = $this->base_model->insert($additional_data_price,'sales_product_price'); 
					 }
					
               }
            }
              // var_dump($additional_data);
            }
        }elseif($data_type=='items_table'){
            for ($row = 2; $row <= $highestRow; $row++)
            {           
                  $unit_price=isset($rowData[$row-2][4]) ? $rowData[$row-2][4] : 0;           
                $price_value =(float) str_replace(',', '', $unit_price);
                $price_save=trim(number_format($price_value, 2, ".", ""));
                $additional_data = array(
                   'item_type'    => $rowData[$row-2][1],
                   'sno'    => $rowData[$row-2][0],
                   
                   'item_price'   =>$price_save,
                    'item_code'   => $rowData[$row-2][2],
                   'item_desc'   =>  $rowData[$row-2][3]
                    
                   
                    
                     
                 ); 
                                
             
               $sucessRate=true;
               log_message("error","here33");
               if($sucessRate==true){

                $passcodeql = $this->db->distinct()->select('item_price','sno')->from('items_table')->where('sno', $rowData[$row-2][0])->get();
                if( $passcodeql->num_rows() > 0 ) {
                    $sql='UPDATE  items_table   SET item_price='.$price_save.'  WHERE sno  = '.'"'.$rowData[$row-2][0].'"'.';';
                    $this->db->query($sql);  
                    log_message("error","update Item Table");
                }else{

                    log_message("error","update price and ADD NEW ITEM items_table");
                    $ADDNEWITEM = $this->base_model->insert($additional_data,'items_table'); 
                    // $ADDNEWITEM = $this->base_model->add2('sales_product_price',$additional_data);  
                    log_message("error","update price and ADD NEW ITEM items_table");
               }
            }
              // var_dump($additional_data);
            }
        }
         else {
            $table_data = array();
            $cnt=0;
           
            for ($row = 2; $row <= $highestRow; $row++)
            {            
                $col=0;
                $import_data=array();
                foreach ($db_flds as $flds_val)
                {
                    //$row_val=trim(str_replace(",","",$rowData[$row-2][$col]));
                    $row_val=trim($rowData[$row-2][$col]);
                    log_message("error",$row_val);
                    // $import_data["$flds_val"]= empty($row_val)||($row_val==='-')?NULL:$row_val;
                     if(strpos( $row_val, "'" ) !== false ){
                         $import_data["$flds_val"]= empty($row_val)||($row_val==='-')?"''":$this->db->escape($row_val);
                     }  else {
                         $import_data["$flds_val"]= empty($row_val)||($row_val==='-')?"''":"'".$row_val."'";                           
                     }
                    $col++;                     
                }
                //
                $table_data[$cnt] = $import_data;
                $table_data[$cnt] = '('.implode(',',$import_data).')';
                $cnt+=1;  
                //var_dump('33',$row_val);                 
            }
        //var_dump('error',$table_data);
            //$this->db->insert_batch($db_name, $table_data);                     
            $str = " INSERT INTO $data_type (".implode(',',$db_flds).") Values ".implode (",",$table_data)." " 
            . " ON DUPLICATE KEY UPDATE $update_rule";
           // var_dump($str);
           //return;
           log_message('error',"why here");
            log_message('error',$str);
            $this->db->query($str);            
        }  
        if($data_type=='pos_item'){ 
            redirect($redirectTo1);
         }
        else{ redirect($redirectTo);}

       
    }
    
    private function update_dept($action,$data_table,$data_key)
    {
        $this->form_validation->set_rules('deptcode','Code','trim|required');
        $this->form_validation->set_rules('deptname','Name','trim|required'); 
        $this->form_validation->set_rules('hod','hod','trim|required'); 
        if($this->form_validation->run() === true){    
            $id=$this->input->post('deptcode');        
            $upd_data = array(
                'deptcode'     => $this->input->post('deptcode'),
                'deptname'     => $this->input->post('deptname'),
                'hod'     => $this->input->post('hod')
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
    
    
    private function update_items_table($action,$data_table,$data_key)
    {
            
        $this->form_validation->set_rules('item_type','Type','trim|required');
        $this->form_validation->set_rules('item_code','Code','trim|required'); 
        $this->form_validation->set_rules('item_desc','Desc','trim|required'); 
        if($this->form_validation->run() === true){    
            $id=$this->input->post('sno');        
            $upd_data = array(
                'item_type'     => $this->input->post('item_type'),
                'item_desc'     => $this->input->post('item_desc'),
                'item_code'     => $this->input->post('item_code'),
                'item_price'     => $this->input->post('item_price')
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

    private function update_parameters($action,$data_table,$data_key)
    {
            
        $this->form_validation->set_rules('setup_name','Parameters','trim|required');
        $this->form_validation->set_rules('setup_value','Setup Value','trim|required'); 
        if($this->form_validation->run() === true){    
            $id=$this->input->post('id');        
            $upd_data = array(
                'setup_name'     => $this->input->post('setup_name'),
                'setup_value'     => $this->input->post('setup_value')
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
    
    private function update_users($action,$data_table,$data_key)
    {
        $this->form_validation->set_rules('first_name','First name','trim|required');
        $this->form_validation->set_rules('last_name','Last name','trim|required');   
        if ($action=="add") {       
            $this->form_validation->set_rules('email','Email','trim|required|valid_email|is_unique[users.email]');
            $this->form_validation->set_message('is_unique', 'This %s has been registered. Please use another or login with it');
            $this->form_validation->set_rules('password','Password','trim|required|min_length[6]');
            $this->form_validation->set_rules('repass','Password confirmation','required|matches[password]');
        } else {
            $this->form_validation->set_rules('email','email','trim');
        }      
        $this->form_validation->set_rules('designation','designation','trim');
        $this->form_validation->set_rules('user_role','user_role','trim|required');                 
        $this->form_validation->set_rules('user_status','user_status','trim|required');         
        if($this->form_validation->run() === true){       
            $user_status = $this->input->post('user_status')=="Active"?1:0;             
            $additional_data = array(
                'first_name' => $this->input->post('first_name'),
                'last_name'  => $this->input->post('last_name'),
                'fullname' => $this->input->post('first_name').' '.$this->input->post('last_name'),
                'designation'      => $this->input->post('designation'),
                'cust_name'      => $this->input->post('cust_name'),
                'cust_code'      => $this->input->post('cust_code'),
                'dept'      => $this->input->post('dept'),
                'manager'      => $this->input->post('manager'),
                'mgr_email'      => $this->input->post('mgr_email'),                
                'user_role'      => $this->input->post('user_role'),
                'company'      => 'Pernod Ricard Nigeria',                                
                'active'      => $user_status
            );       
            
            if ($action=="add"){
                $username = $this->get_id();
                $email = $this->input->post('email');                  
                $password = $this->input->post('password');
                $group[0]=2;
                $group[1]=3;
                $group_ids=$group;               
                $user_id = $this->ion_auth->register($username, $password, $email, $additional_data, $group_ids);
            }
            if ($action=="update"){
                $id=$this->input->post('id'); 
                $additional_data['email']=$this->input->post('email');                 
                $this->base_model->update($additional_data,$id,
                    $data_table,array($data_key => $id)); 
            }
            echo json_encode(array("status" => TRUE));            
        } else {
           header('Content-Type: application/json');
           echo json_encode(validation_errors());
        }               
    }     
    
    private function update_workflow_approvers($action,$data_table,$data_key)
    {          
        $this->form_validation->set_rules('process_id','process_id','trim|required');
        $this->form_validation->set_rules('approver_from','approver_from','trim|required');        
        $this->form_validation->set_rules('appr_name','appr_name','trim');                
        $this->form_validation->set_rules('appr_function','appr_function','trim|required');
        if($this->form_validation->run() === true){
            $process_id=$this->input->post('process_id');
            $id=$this->input->post('id'); 
            $appr_name=$this->input->post('appr_name');
            $approver_from=$this->input->post('approver_from');
            if($approver_from=="Preset1" || $approver_from=="Initiator"){
                $appr_name=0;
            }
            $upd_data = array(
                'process_id'     => $this->input->post('process_id'),
                'approver_from'   => $approver_from,                  
                'appr_name'       => empty($appr_name)?0:$appr_name,
                'appr_function'   => $this->input->post('appr_function'),
                'notifier'   => $this->input->post('notifier')                
            );        
          
            if ($action=="add"){
                $order_no=$this->base_model->rec_count($data_table,$process_id,'process_id');
                $upd_data['order_no']=empty($order_no)?1:$order_no+1;
                $this->base_model->insert($upd_data,$data_table);
            }
            if ($action=="update"){
                $this->base_model->update($upd_data,$id,$data_table,array($data_key => $id)); 
            }
            echo json_encode(array("status" => TRUE));            
        } else {
           header('Content-Type: application/json');
           echo json_encode(validation_errors());
        }                                                   
    }
    
    public function ajax_action($action,$data_table,$data_key=FALSE)
    {
        switch ($data_table) {
            case "setup_parameters":
                $this->update_parameters($action,$data_table,$data_key);
                break;  
            case "users":
                $this->update_users($action,$data_table,$data_key);
                break;   
            case "items_table":
                $this->update_items_table($action,$data_table,$data_key);
                break;    
            case "dept":
                $this->update_dept($action,$data_table,$data_key);
                break;             
            case "workflow_approvers":
                $this->update_workflow_approvers($action,$data_table,$data_key);
                break;      
            
        case "customer_location":
            $this->update_customer_location($action,$data_table,$data_key);
            break;   
            case "sales_product_price":
                $this->update_sales_product_price($action,$data_table,$data_key);
                break; 
           case "pos_item":
              $this->update_pos_item($action,$data_table,$data_key);
             break;  
           case "pos_order_item":
                $this->update_add_pos_order_itemn($action,$data_table,$data_key);
               break; 
        }
    }
    
	
    public function ajax_reset($data_table,$data_key)
    {             
        $rows=$this->input->post('rows');
        foreach($rows as $row){
            $identity = $this->ion_auth->user($row)->row()->email;
            $change = $this->ion_auth->reset_password($identity, 'password');
        }
        echo json_encode(array("status" => TRUE)); 
    } 
	
    public function ajax_list($data_table,$id=NULL)
    {        
        header('Content-Type: application/json');  
        if(is_null($id)) {
            switch ($data_table){
                case "workflow":
                    $temp_data=$this->base_model->run_qry("CALL LIST_WORKFLOW()",'qry');
                    $total_data=$temp_data->num_rows();
                    $total_data=is_null($total_data)?0:$total_data;
                    $main_data=$temp_data->result();    
                    break;
                DEFAULT:
                    $total_data=$this->base_model->rec_count($data_table,$id,$key);
                    $total_data=is_null($total_data)?0:$total_data;
                    $main_data=$this->base_model->get_record($data_table,$id,$key);
                    break;                
            }            

        } else {
            switch ($data_table){
                case "workflow":
                    $key="process_id";
                    $total_data=$this->base_model->rec_count($data_table,$id,$key);
                    $total_data=is_null($total_data)?0:$total_data;
                    $main_data=$this->base_model->get_record($data_table,$id,$key);
                    break;
                case "workflow_approvers":
                    $temp_data=$this->base_model->run_qry("CALL GET_WORKFLOW_APPROVERS('$id')",'qry');
                    $total_data=$temp_data->num_rows();
                    $total_data=is_null($total_data)?0:$total_data;

                    $main_data=$temp_data->result();    
                    break;
                    case "customer_location":
                        $temp_data=$this->base_model->run_qry("CALL GET_CUSTOMER_LOCATION('$id')",'qry');
                        $total_data=$temp_data->num_rows();
                        $total_data=is_null($total_data)?0:$total_data;
    
                        $main_data=$temp_data->result();    
                        break;
            }            
            $main_data=$total_data==0?array():$main_data;            
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
        $data = $this->base_model->get_single_record($id,$data_key,$data_table);        
        echo json_encode($data);
    }  
    
    public function ajax_delete($data_table,$data_key)
    {       
        $rows=$this->input->post('rows');
        foreach($rows as $row){
            $this->base_model->delete($data_table,array($data_key => $row));
        }
        echo json_encode(array("status" => TRUE)); 
    }    
    
    public function view_page($data_table,$id='')
    {               
        switch ($data_table){  
            case 'workflow':
                $this->view_workflow_details($id);
                break;   
                case 'customer_master_data':
                    $this->view_customer_details($id);
                    break;                
        }        
    }     
}
