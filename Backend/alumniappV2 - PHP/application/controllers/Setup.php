<?php
defined('BASEPATH') or exit('No direct script access allowed');


class Setup extends User_Controller
{
    function __construct()
    {
        parent::__construct();
        $this->datetime         = date("Y-m-d H:i:s");
        $this->data['data_access'] = array("Add", "Edit", "Delete");
        // if ($this->current_user->user_role !== "Admin" || $this->uri->segment(3)=='pos_item') {
        //     redirect('requests/dashboard');
        // }
        // if ($this->current_user->user_role !== "Admin" && $this->uri->segment(3) !== 'pos_item') {
        //     redirect('setup/dashboard');
        // }
    }

    public function index()
    {
        $this->view_parameters();
    }
     public function dashboard()
    {
        // redirect('requests/request_dashboard');
        //$this->data['page_title'] = 'Automator Dashboard';                 
        $this->render('home/request_dashboard');
    }

    public function view_parameters()
    {
        $this->data['page_title'] = 'Setup System Parameters';
        $this->data['data_columns'] = '{ "data": "setup_id","visible":false },{ "data": "setup_name" },'
            . '{ "data": "setup_value" }';
        $this->data['data_section'] = "Setup";
        $this->data['data_table'] = "setup_parameters";
        $this->data['data_key'] = "setup_id";
        $this->data['data_editflds'] = '
            $(\'[name="id"]\').val(data.setup_id);
            $(\'[name="setup_name"]\').val(data.setup_name);
            $(\'[name="setup_value"]\').val(data.setup_value);';
        $this->data['mini'] = FALSE;
        $this->datatable_script($this->data['data_section'], 'asc', 1);
        $this->render('setup/setup_parameters');
    }

    public function view_items_table()
    {
        $this->data['page_title'] = 'Items List';
        $this->data['data_columns'] = '{ "data": "sno","visible":false },'
            . '{ "data": "item_type" },{ "data": "item_code" },{ "data": "item_desc" },'
            . '{ "data": "item_price", "render": function ( data, type, row ) '
            . '{if(data==\'0.00\'){return \'\'}else{return format_number(data)};} },{ "data": "location" }';
        $this->data['data_section'] = "Setup";
        $this->data['data_table'] = "items_table";
        $this->data['data_key'] = "sno";
        $this->data['data_editflds'] = '
            $(\'[name="sno"]\').val(data.sno);
            $(\'[name="item_type"]\').val(data.item_type);
             $(\'[name="item_code"]\').val(data.item_code);
            $(\'[name="item_desc"]\').val(data.item_desc);
            $(\'[name="item_price"]\').val(data.item_price);';
        $this->data['mini'] = FALSE;
        $this->datatable_script($this->data['data_section'], 'asc', 1);
        $this->render('setup/setup_items');
    }


    public function view_free_good_item()
    {
        $this->data['page_title'] = 'Setup Free Good Table';
        $this->data['data_columns'] = '{ "data": "sno","visible":false },'
            . '{ "data": "item_type" },{ "data": "item_code" },{ "data": "item_desc" },'
            . '{ "data": "item_price", "render": function ( data, type, row ) '
            . '{if(data==\'0.00\'){return \'\'}else{return format_number(data)};} },{ "data": "location" }';
        $this->data['data_section'] = "Setup";
        $this->data['data_table'] = "free_good_item";
        $this->data['data_key'] = "sno";
        $this->data['data_editflds'] = '
            $(\'[name="sno"]\').val(data.sno);
            $(\'[name="item_type"]\').val(data.item_type);
             $(\'[name="item_code"]\').val(data.item_code);
            $(\'[name="item_desc"]\').val(data.item_desc);
           
            $(\'[name="item_price"]\').val(data.item_price);';

        $this->data['mini'] = FALSE;
        $this->datatable_script($this->data['data_section'], 'asc', 1);
        $this->render('setup/setup_free_item');
    }
    public function view_dept()
    {
        $this->data['page_title'] = 'Setup Department Approvers';
        $this->data['data_columns'] = ''
            . '{ "data": "deptname" },{ "data": "hod" },{ "data": "deptcountry" }';
        $this->data['data_section'] = "Setup";
        $this->data['data_table'] = "dept";
        $this->data['data_key'] = "deptcode";
        $this->data['data_editflds'] = '
            $(\'[name="deptcode"]\').val(data.deptcode);
            $(\'[name="deptname"]\').val(data.deptname);
            $(\'[name="deptcountry"]\').val(data.deptcountry);            
            $(\'[name="hod"]\').val(data.hod);';
        $this->data['mini'] = FALSE;
        $sql = "SELECT users.email as value, users.fullname as 'name' "
            . "FROM users where active=1 order by users.fullname asc";
        $this->data['hod'] = $this->base_model->get_record_menu3($sql, 'hod', '', 'Select Manager');

        $this->datatable_script($this->data['data_section'], 'asc', 0);
        $this->render('setup/setup_dept');
    }

    public function view_users()
    {
        $this->data['page_title'] = 'Setup Users';
        $this->form_script_modal();
        $this->data['data_columns'] = '{ "data": "id","visible":false },{ "data": "email" },'
            . '{ "data": "fullname" },{ "data": "designation" },'
            . '{ "data": "dept" },{ "data": "manager" },'
            . '{ "data": "mgr_email" },{ "data": "userArea" },'
            . '{ "data": "user_role" },'
            . '{ "data": "active",
                        "render": function ( data, type, row ) {
                            if(data==1){
                                return "Active";
                            } else {
                                return "Inactive";
                            }
                        }
                    }';
        $this->data['data_section'] = "Setup";
        $this->data['data_table'] = "users";
        $this->data['data_key'] = "id";
        $this->data['data_editflds'] = '
            $(\'[name="id"]\').val(data.id);
            $(\'[name="email"]\').val(data.email);         
            $(\'[name="fullname"]\').val(data.fullname);               
            $(\'[name="designation"]\').val(data.designation);
            $(\'[name="dept"]\').val(data.dept);            
            $(\'[name="manager"]\').val(data.manager);
           
               
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
            
            var $select2 = $(\'#userArea\').selectize(); 
            var selectize2= $select2[0].selectize; 
            selectize2.setValue(data.userArea, false);
          
            var $select1 = $(\'#mgr_email\').selectize(); 
            var selectize1 = $select1[0].selectize; 
            selectize1.setValue(data.mgr_email, false);     
            
            var $select2 = $(\'#user_role\').selectize(); 
            var selectize2= $select2[0].selectize; 
            selectize2.setValue(data.user_role, false);
            ';
        $this->data['mini'] = TRUE;
        $this->datatable_script($this->data['data_section'], 'asc', 1);
        $sql = "SELECT users.email as value, users.fullname as 'name' "
            . "FROM users where active=1 order by users.fullname asc";
        $this->data['mgr_email'] = $this->base_model->get_record_menu($sql, 'mgr_email', '', 'Select Manager');

        $this->data['user_role'] = $this->base_model->get_parameters_menu(
            'User Roles',
            'user_role',
            '',
            'Enter User Role'
        );
        $this->data['company'] = $this->base_model->get_parameters_menu(
            'Markets',
            'company',
            'Pernod Ricard Nigeria',
            'Enter Market'
        );

        $sql_dept = "SELECT deptname as value, deptname as 'name' FROM dept order by deptname asc";
        $this->data['dept'] = $this->base_model->get_record_menu(
            $sql_dept,
            'dept',
            '',
            'Enter Departments'
        );

        $sql_dept = "SELECT deptcountry as value, deptcountry as 'name' FROM dept order by deptcountry asc";
        $this->data['userArea'] = $this->base_model->get_record_menu(
            $sql_dept,
            'userArea',
            '',
            'Enter Area'
        );


        $this->data['after_foot'] = $this->data['after_foot'] . '
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
        $location = "Nigeria";
        $this->data['page_title'] = 'Setup Workflow';
        $this->data['data_columns'] = '{ "data": "process_id","visible":false },'
            . '{ "data": "request_title" },{ "data": "request_type" },
            
            { "data": "active","render": function ( data, type, row ) {
                 if (data=="Yes") {
                return \'<span class="tb-status text-success"  >\'+data+\'</span>\';
           //        
            }   else{
                return \'<span class="tb-status text-danger"  >\'+data+\'</span>\';
            }} },
            
            
            '
            . '{ "data": "approvers" },
                { "data": "location","render": function ( data, type, row ) {
                    if (data) {
                       
                         return data;
                    //return";         
                }else{
                    return \'' . $location . '\';
                }} }';
        $this->data['data_section'] = "Setup";
        $this->data['data_table'] = "workflow";
        $this->data['data_key'] = "process_id";
        $this->data['data_access'] = ['no-edit'];
        $this->data['mini'] = FALSE;
        $this->datatable_script($this->data['data_section'], 'asc', 4, 1);
        $this->render('setup/setup_workflow_list');
    }

    public function add_workflow($action = null, $id = null)
    {
        $this->form_script();
        if ($action === 'edit') {
            $this->data['page_title'] = 'Edit Workflow';
            $this->data['form_action'] = 'edit';
            $this->data['main_data'] = is_null($id) ? array() :
                $this->base_model->get_single_record($id, 'process_id', 'workflow');
        } else {
            $this->data['page_title'] = 'Add Workflow';
            $this->data['form_action'] = 'add';
        }
        $this->form_validation->set_rules('request_title', 'Request Title', 'trim|required');
        $this->form_validation->set_rules('request_type', 'request type', 'trim|required');
        $this->form_validation->set_rules('request_form', 'request form', 'trim|required');
        $this->form_validation->set_rules('request_table', 'request table', 'trim|required');
       // $this->form_validation->set_rules('active', 'Active', 'trim|required');
        $this->form_validation->set_rules('wait_time', 'Wait Time', 'trim|required');
        $this->form_validation->set_rules('expiration_action', 'Action Type', 'trim|required');

        if ($this->form_validation->run() === true) {
            $id = $this->input->post('id');
            $upd_data = array(
                'request_title' => $this->input->post('request_title'),
                'request_type'  => $this->input->post('request_type'),
                'request_form'  => $this->input->post('request_form'),
                'request_table' => $this->input->post('request_table'),
                'location' => $this->input->post('location'),
                'active' => $action == "add" ?"Yes": $this->input->post('active'),
                'wait_time'     => $this->input->post('wait_time'),
                'expiration_action'     => $this->input->post('expiration_action'),
                'last_updated'   => date('Y-m-d H:i:s')
            );
            if ($action == "add") {
                $id = $this->base_model->insert($upd_data, 'workflow');
            }
            if ($action == "edit") {
                $this->base_model->update(
                    $upd_data,
                    $id,
                    'workflow',
                    array('process_id' => $id)
                );
            }
            redirect('setup/view_workflow');
        } else {
            $this->render('setup/setup_add_workflow');
        }
    }
    public function view_request_approver_details($id)
    {
        $this->form_script_modal();
        $process_id = $this->base_model->get_single_record($id, 'request_id', 'workflow_requests')->process_id;
        $request_type = $this->base_model->get_single_record($id, 'request_id', 'workflow_requests')->request_type;

        $sql1 = "SELECT workflow.request_title FROM workflow where workflow.request_type='$request_type' AND workflow.process_id ='$process_id' ";
        $this->data['newval']  = $this->base_model->run_qry($sql1);
        $next_appr =
            $this->data['page_title'] = 'Workflow Details - ' . $id;
        $this->data['data_columns'] = '{"data": "id","visible": false }, {  "data": "order_no" }, '
            . '{"data": "appr_name","visible": false  },{"data": "appr_status","visible": false  },{ "data": "approver_name" },{ "data": "appr_function" },
            
            { "data": "appr_name","render": function ( data, type, row ) {
                if (data==' . $this->base_model->get_single_record($id, 'request_id', 'workflow_requests')->next_appr . '  && ' . $this->base_model->get_single_record($id, 'request_id', 'workflow_requests')->order_no . '  == row[\'order_no\'] ) {
                return \'<span class="tb-status text-success"  >Yes</span>\';
           //        
            } else{
                return \'<span class="tb-status text-danger"  >No</span>\';
            }} },
            { "data": "appr_status","render": function ( data, type, row ) {
                if (data=="Approved"  ) {
                return \'<span class="tb-status text-success"  >Approved</span>\';
           //        
            } else{
                return \'<span class="tb-status text-danger"  >Pending</span>\';
            }} }
            ';
        $this->data['data_section'] = "Setup";
        $this->data['data_var'] = $id;
        $this->data['data_table'] = "request_approvers";
        $this->data['data_key'] = "id";

        $this->data['data_editflds'] = '
        if(data.appr_status==\'Approved\' ){
            
          

        }
            $(\'[name="id"]\').val(data.id);

            var $select1 = $(\'#approver_from\').selectize(); 
            var selectize1 = $select1[0].selectize; 
            selectize1.setValue(data.approver_from, false);

            var $select1 = $(\'#appr_name\').selectize(); 
            var selectize1 = $select1[0].selectize; 
            selectize1.setValue(data.appr_name, false);
            $(\'[name="appr_function"]\').val(data.appr_function);
            $(\'[name="appr_name2"]\').val(data.appr_name);
        ';

        $this->data['after_foot'] .= '
                <Script Language="Javascript">' . "
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

        $this->data['main_data'] = $this->base_model->get_single_record($id, 'request_id', 'workflow_requests');
        $sql = "SELECT users.id as value, users.fullname as 'name' "
            . "FROM users where active=1 order by users.fullname asc";

        $this->data['approver_name'] = $this->base_model->get_record_menu2($sql, 'appr_name', '', 'Select Approver');
        $this->data['mini'] = FALSE;
        $this->datatable_script($this->data['data_section'], 'asc', 1);
        $this->render('setup/setup_request_approver');
    }

    public function view_workflow_details($id)
    {
        $this->form_script_modal();
        $this->data['page_title'] = 'Workflow Details - ' . $id;
        $this->data['data_columns'] = '{"data": "approver_id","visible": false }, {  "data": "order_no" }, '
            . '{"data": "approver_name" },{ "data": "appr_function" },{ "data": "notifier" },{ "data": "approve_type" }';
        $this->data['data_section'] = "Setup";
        $this->data['data_var'] = $id;
        $this->data['data_table'] = "workflow_approvers";
        $this->data['data_key'] = "approver_id";

        $this->data['data_editflds'] = '
            $(\'[name="id"]\').val(data.approver_id);

            var $select1 = $(\'#approver_from\').selectize(); 
            var selectize1 = $select1[0].selectize; 
            selectize1.setValue(data.approver_from, false);

            var $select1 = $(\'#appr_name\').selectize(); 
            var selectize1 = $select1[0].selectize; 
            selectize1.setValue(data.appr_name, false);
             $(\'[name="approve_type"]\').val(data.approve_type);
            $(\'[name="appr_function"]\').val(data.appr_function);
            $(\'[name="notifier"]\').val(data.notifier);
        ';

        $this->data['after_foot'] .= '
                <Script Language="Javascript">' . "
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

        $this->data['main_data'] = $this->base_model->get_single_record($id, 'process_id', 'workflow');
        $sql = "SELECT users.id as value, users.fullname as 'name' "
            . "FROM users where active=1 order by users.fullname asc";
        $this->data['approver_name'] = $this->base_model->get_record_menu2($sql, 'appr_name', '', 'Select Approver');
        $this->data['mini'] = FALSE;
        $this->datatable_script($this->data['data_section'], 'asc', 1);
        $this->render('setup/setup_workflow_details');
    }



    public function import($data_type = "locations")
    {
        $config['upload_path'] = './uploads/imports/';
        $config['allowed_types'] =  'xls|xlsx|csv';
        $config['file_ext_tolower'] = TRUE;
        $config['file_ext_tolower'] = TRUE;
        $config['overwrite'] = 1;
        print_r($data_type);
        $redirectTo = 'setup/view_' . $data_type;
        $redirectTo1 = 'requests/pos_item_view';
        $this->load->library('upload');
        $this->upload->initialize($config);
        print_r($data = array('upload_data' => $this->upload->data()));
        var_dump("file enter");
        //return;
        if (!$this->upload->do_upload('userfile')) {
            $error = array('error' => $this->upload->display_errors());
            $this->session->set_flashdata('error', $error['error'] . ' or the wrong File was Selected please try again');
            // print_r($error);
            // exit;
            if ($data_type == 'pos_item') {
                redirect($redirectTo1);
            } else {
                redirect($redirectTo);
            }
        } else {
            $data = array('upload_data' => $this->upload->data());

            if (!empty($data)) {
                // $result= json_encode($data);
                // print_r($result);
                $fileStatus = TRUE;
                $filetype =  $data['upload_data']['file_type'];
                $fileNameNoExtension =  $data['upload_data']['file_name'];



                /// save image record
                $filename =   $fileNameNoExtension;

                var_dump($filename);
                //return;
            }
        }

        // return;
        $redirectTo = 'setup/view_' . $data_type;
        $redirectTo1 = 'requests/pos_item_view';


        $rowData = array();
        $import_data = array();
        $db_name = $data_type;
        $sucessRate = false;
        switch ($data_type) {
            case "users":
                $db_flds = array('email', 'first_name', 'last_name', 'fullname', 'designation', 'dept', 'manager', 'mgr_email', 'user_role');
                break;
                //  case "items_table":     
            case "items_table_not_in_use_for_now":
                $db_flds = array('item_type', 'item_code', 'item_desc', 'item_price');
                $update_rule = "item_type=values(item_type),item_code=values(item_code)," .
                    "item_desc=values(item_desc),item_price=values(item_price)";
                break;
        }

        if (is_null($filename)) {
            $this->session->set_flashdata('error', 'Error in File Import - import failed');
            redirect($redirectTo);
        }
        $this->load->library('Excel');
        $this->session->set_flashdata('success', 'Successful upload ' . $filename);
      // $this->session->set_flashdata('success', 'Successful upload: <a href="' . base_url('uploads/' . $filename) . '" target="_blank">' . $filename . '</a>');

        try {
            /// it will be your file name that you are posting with a form or 
            //can pass static name $_FILES["file"]["name"];
            $objPHPExcel = PHPExcel_IOFactory::load('./uploads/imports/' . $filename);
            //$objWorksheet = $objPHPExcel->setActiveSheetIndex(0);
            $objWorksheet = $objPHPExcel->getSheet(0);
        } catch (Exception $e) {
            $this->session->set_flashdata('error', 'Error in Excel library - import failed');
            redirect($redirectTo);
            exit;
        }

        $highestRow = $objWorksheet->getHighestDataRow();
        $highestColumn = $objWorksheet->getHighestDataColumn();
        $rowData = $objWorksheet->rangeToArray(
            'A2:' . $highestColumn . $highestRow,
            NULL,
            TRUE,
            FALSE
        );
        //var_dump($rowData);
        if ($data_type == 'users') {
            for ($row = 2; $row <= $highestRow; $row++) {
                $email = $rowData[$row - 2][0];
                $username = $this->get_id();
                $password = 'welcomenewuser';
                $group[0] = 2;
                $group[1] = 3;
                $group_ids = $group;
                $additional_data = array(
                    'first_name'    => $rowData[$row - 2][1],
                    'last_name'     => $rowData[$row - 2][2],
                    'fullname'      => $rowData[$row - 2][1] . ' ' . $rowData[$row - 2][2],
                    'designation'   => $rowData[$row - 2][3],
                    'dept'          => $rowData[$row - 2][4],
                    'manager'       => $rowData[$row - 2][5],
                    'mgr_email'     => $rowData[$row - 2][6],
                    'cust_name'     => $rowData[$row - 2][7],
                    'cust_code'     => $rowData[$row - 2][8],
                    'user_role'     => 'User',
                    'company'       => 'Pernod Ricard Nigeria',
                    'active'        => 1
                );
                if (!empty($email)) {
                    $user_id = $this->ion_auth->register($username, $password, $email, $additional_data, $group_ids);
                }
            }
        }
        if ($data_type == 'pos_item') {
            for ($row = 2; $row <= $highestRow; $row++) {
                $qtyVal = isset($rowData[$row - 2][6]) ? $rowData[$row - 2][6] : 0;
                $re_order_level = isset($rowData[$row - 2][5]) ? $rowData[$row - 2][5] : 0;
                $pos_desc = $rowData[$row - 2][4];
                $price = isset($rowData[$row - 2][7]) ? str_replace(",", "",$rowData[$row - 2][7]) : 0;
                $safety_lock = isset($rowData[$row - 2][7]) ? $rowData[$row - 2][8] : 0;
                $additional_data = array(
                    //  'poscode'    => $this->passcode(),
                    'poscode'    => $rowData[$row - 2][2],
                    'pos_type'     => "POS",
                    // 'price'      => $rowData[$row-2][4],

                    //'qty'   => $rowData[$row-2][2],
                    'qty'   => isset($rowData[$row - 2][6]) ? $rowData[$row - 2][6] : 0,
                    're_order_level'          => isset($rowData[$row - 2][5]) ? $rowData[$row - 2][5] : 0,
                    'status'       => $rowData[$row - 2][10],
                    'return_type'       => $rowData[$row - 2][9],
//'pos_desc'       => $rowData[$row - 2][4],
            'pos_desc' => preg_replace('/\s+/', '_', trim($rowData[$row - 2][4])),
                    'location'       => $rowData[$row - 2][11],
                    'safety_lock'       => $safety_lock,
                    'price'       => $price,
                    'date_added'           => $this->datetime


                );
                log_message("error", $rowData[$row - 2][2]);
                $poscodeql = $this->db->distinct()->select('pos_type', 'qty', 're_order_level', 'pos_desc', 'poscode', 'safety_lock', 'status')->from('pos_item')->where('poscode', $rowData[$row - 2][2])->get();
                if ($poscodeql->num_rows() > 0) {
                    try {
                        // $sql = 'UPDATE  pos_item   SET qty=' . '"' . $qtyVal  . '"' . ',re_order_level=' . '"' . $re_order_level . '"' . ',pos_desc=' . '"' . $rowData[$row - 2][4] . '"' . ',safety_lock=' . '"' . $safety_lock . '"' . ',status=' . '"' . $rowData[$row - 2][9] . '"' . ',return_type=' . '"' . $rowData[$row - 2][8] . '"' . ',location=' . '"' . $rowData[$row - 2][10] . '"' . '  WHERE poscode  = ' . '"' . $rowData[$row - 2][2] . '"' . ';';
                        $sql = 'UPDATE  pos_item   SET price=' . '"' . $price  . '"' . ', qty=' . '"' . $qtyVal  . '"' . ',re_order_level=' . '"' . $re_order_level . '"' . ',pos_desc=' . '"' . preg_replace('/\s+/', '_', trim($rowData[$row - 2][4])). '"' . ',safety_lock=' . '"' . $safety_lock . '"' . ',status=' . '"' . $rowData[$row - 2][10] . '"' . ',return_type=' . '"' . $rowData[$row - 2][9] . '"' . ',location=' . '"' . $rowData[$row - 2][11] . '"' . '  WHERE poscode  = ' . '"' . $rowData[$row - 2][2] . '"' . ';';

                        $this->db->query($sql);
                    } catch (Exception $e) {
                        // Handle the error message
                        $this->session->set_flashdata('error', 'Error initiating request - ' . $e->getMessage() . ' not found');
                        echo "Error Code: " . $e->getCode() . "<br />";
                        echo "Error Message: " . $e->getMessage() . "<br />";
                        echo "SQL Statement: " . $this->db->last_query() . "<br />";
                        redirect($redirectTo1);
                    }
                    // $sql = 'UPDATE  pos_item   SET qty=' . '"' . $qtyVal  . '"' . ',re_order_level=' . '"' . $re_order_level . '"' . ',pos_desc=' . '"' . $rowData[$row - 2][4] . '"' . ',safety_lock=' . '"' . $safety_lock . '"' . ',status=' . '"' . $rowData[$row - 2][8] . '"' . ',return_type=' . '"' . $rowData[$row - 2][9] . '"' . ',location=' . '"' . $rowData[$row - 2][10] . '"' . '  WHERE poscode  = ' . '"' . $rowData[$row - 2][2] . '"' . ';';
                    // $this->db->query($sql);
                    log_message("error", "update POS Item");
                } else {
                    $result =  $this->base_model->insert($additional_data, 'pos_item');
                    //$result = $this->base_model->add2('pos_item',$additional_data);  
                    log_message("error", "add new pos item");
                }
                // $this->db->insert_batch($db_name, $additional_data);


                // var_dump($additional_data);
            }


            // return;
        } elseif ($data_type == 'sales_product_price') {
            $counter = 0;
            $newCounter = 0;
            $length = count($rowData);
            for ($row = 2; $row <= $highestRow; $row++) {
                // $counter=$counter+1;
                $unit_price = isset($rowData[$row - 2][5]) ? $rowData[$row - 2][5] : 0;
                $price_value = (float) str_replace(',', '', $unit_price);
                $price_save = trim(number_format($price_value, 2, ".", ""));

                log_message("error", "here335");
                //if($sucessRate==true){
                $passcodeql = $this->db->distinct()->select('unit_price', 'sno')->from('sales_product_price')->where('product_code', $rowData[$row - 2][1])->where('cust_type', $rowData[$row - 2][3])->where('uom', $rowData[$row - 2][4])->get();

                //$passcodeql = $this->db->distinct()->select('unit_price','sno')->from('sales_product_price')->where('sno', $rowData[$row-2][0])->get();
                if ($passcodeql->num_rows() > 0) {
                    $sql = 'UPDATE  sales_product_price   SET unit_price=' . $price_save . ', prod_desc  = "' . $rowData[$row - 2][2] . '",
                uom  = "' . $rowData[$row - 2][4] . '",cust_type  = "' . $rowData[$row - 2][3] . '" WHERE product_code  = ' . '"' . $rowData[$row - 2][1] . '"' . ' AND cust_type  = ' . '"' . $rowData[$row - 2][3] . '"' . '  AND uom  = ' . '"' . $rowData[$row - 2][4] . '"' . ';';
                    $this->db->query($sql);
                    log_message("error", "update sales product");
                } else {
                    $additional_data_price = array(
                        'product_code'    => $rowData[$row - 2][1],
                        // 'sno'    =>  $rowData[$row-2][0] ,

                        'unit_price'   => $price_save,
                        'uom'   => $rowData[$row - 2][4],
                        'prod_desc'   => $rowData[$row - 2][2],
                        'cust_type'       => $rowData[$row - 2][3],



                    );
                    $this->base_model->insert($additional_data_price, 'sales_product_price');
                    // $ADDNEWITEM = $this->base_model->add2('sales_product_price',$additional_data);  
                    log_message("error", "update quantity and ADD NEW ITEM");
                }
                //}
                // var_dump($additional_data);
            }
            $counter = 0;
            $newCounter = 0;
            $length = count($rowData);
            for ($row = 2; $row <= $highestRow; $row++) {
                // $counter=$counter+1;
                $unit_price = isset($rowData[$row - 2][5]) ? $rowData[$row - 2][5] : 0;
                $price_value = (float) str_replace(',', '', $unit_price);
                $price_save = trim(number_format($price_value, 2, ".", ""));

                log_message("error", "here335");
                //if($sucessRate==true){
                $passcodeql = $this->db->distinct()->select('unit_price', 'sno')->from('sales_product_price')->where('product_code', $rowData[$row - 2][1])->where('cust_type', $rowData[$row - 2][3])->where('uom', $rowData[$row - 2][4])->get();

                //$passcodeql = $this->db->distinct()->select('unit_price','sno')->from('sales_product_price')->where('sno', $rowData[$row-2][0])->get();
                if ($passcodeql->num_rows() > 0) {
                    $sql = 'UPDATE  sales_product_price   SET unit_price=' . $price_save . ', prod_desc  = "' . $rowData[$row - 2][2] . '",
                uom  = "' . $rowData[$row - 2][4] . '",cust_type  = "' . $rowData[$row - 2][3] . '" WHERE product_code  = ' . '"' . $rowData[$row - 2][1] . '"' . ' AND cust_type  = ' . '"' . $rowData[$row - 2][3] . '"' . '  AND uom  = ' . '"' . $rowData[$row - 2][4] . '"' . ';';
                    $this->db->query($sql);
                    log_message("error", "update sales product");
                } else {
                    $additional_data_price = array(
                        'product_code'    => $rowData[$row - 2][1],
                        // 'sno'    =>  $rowData[$row-2][0] ,

                        'unit_price'   => $price_save,
                        'uom'   => $rowData[$row - 2][4],
                        'prod_desc'   => $rowData[$row - 2][2],
                        'cust_type'       => $rowData[$row - 2][3],



                    );
                    $this->base_model->insert($additional_data_price, 'sales_product_price');
                    // $ADDNEWITEM = $this->base_model->add2('sales_product_price',$additional_data);  
                    log_message("error", "update quantity and ADD NEW ITEM");
                }
                //}
                // var_dump($additional_data);
            }
        } elseif ($data_type == 'items_table') {
            for ($row = 2; $row <= $highestRow; $row++) {
                $unit_price = isset($rowData[$row - 2][4]) ? $rowData[$row - 2][4] : 0;
                $price_value = (float) str_replace(',', '', $unit_price);
                $price_save = trim(number_format($price_value, 2, ".", ""));
                $additional_data = array(
                    'item_type'    => $rowData[$row - 2][1],
                    // 'sno'    => $rowData[$row - 2][0],

                    'item_price'   => $price_save,
                    'item_code'   => $rowData[$row - 2][2],
                    'item_desc'   =>  $rowData[$row - 2][3],

                    'location'   =>  $rowData[$row - 2][5]


                );


                $sucessRate = true;
                log_message("error", "here33");
                if ($sucessRate == true) {

                    $passcodeql = $this->db->distinct()->select('item_price', 'sno')->from('items_table')->where('item_code', $rowData[$row - 2][2])->where('item_desc', $rowData[$row - 2][3])->get();
                    if ($passcodeql->num_rows() > 0) {
                        $sql = 'UPDATE  items_table   SET item_price=' . $price_save . ',location  = "' . $rowData[$row - 2][5] . '"  WHERE item_code  = ' . '"' . $rowData[$row - 2][2] . '"' . ' AND item_desc  = ' . '"' . $rowData[$row - 2][3] . '"' . ';';
                        $this->db->query($sql);
                        log_message("error", "update Item Table");
                    } else {

                        log_message("error", "update price and ADD NEW ITEM items_table");
                        $ADDNEWITEM = $this->base_model->insert($additional_data, 'items_table');
                        // $ADDNEWITEM = $this->base_model->add2('sales_product_price',$additional_data);  
                        log_message("error", "update price and ADD NEW ITEM items_table");
                    }
                }
                // var_dump($additional_data);
            }
        } elseif ($data_type == 'free_good_item') {
            for ($row = 2; $row <= $highestRow; $row++) {
                $unit_price = isset($rowData[$row - 2][4]) ? $rowData[$row - 2][4] : 0;
                $price_value = (float) str_replace(',', '', $unit_price);
                $price_save = trim(number_format($price_value, 2, ".", ""));
                $additional_data = array(
                    'item_type'    => $rowData[$row - 2][1],
                    // 'sno'    => $rowData[$row - 2][0],

                    'item_price'   => $price_save,
                    'item_code'   => $rowData[$row - 2][2],
                    'item_desc'   =>  $rowData[$row - 2][3],

                    'location'   =>  $rowData[$row - 2][5]


                );


                $sucessRate = true;
                log_message("error", "here33");
                if ($sucessRate == true) {

                    $passcodeql = $this->db->distinct()->select('item_price', 'sno')->from('free_good_item')->where('item_code', $rowData[$row - 2][2])->where('item_desc', $rowData[$row - 2][3])->get();
                    if ($passcodeql->num_rows() > 0) {
                        $sql = 'UPDATE  free_good_item   SET item_price=' . $price_save . ',location  = "' . $rowData[$row - 2][5] . '"  WHERE item_code  = ' . '"' . $rowData[$row - 2][2] . '"' . ' AND item_desc  = ' . '"' . $rowData[$row - 2][3] . '"' . ';';
                        $this->db->query($sql);
                        log_message("error", "update Item Table");
                    } else {

                        log_message("error", "update price and ADD FREE ITEM free_good_item");
                        $ADDNEWITEM = $this->base_model->insert($additional_data, 'free_good_item');
                        // $ADDNEWITEM = $this->base_model->add2('sales_product_price',$additional_data);  
                        log_message("error", "update price and ADD NEW ITEM items_table");
                    }
                }
                // var_dump($additional_data);
            }
        } else {
            $table_data = array();
            $cnt = 0;

            for ($row = 2; $row <= $highestRow; $row++) {
                $col = 0;
                $import_data = array();
                foreach ($db_flds as $flds_val) {
                    //$row_val=trim(str_replace(",","",$rowData[$row-2][$col]));
                    $row_val = trim($rowData[$row - 2][$col]);
                    log_message("error", $row_val);
                    // $import_data["$flds_val"]= empty($row_val)||($row_val==='-')?NULL:$row_val;
                    if (strpos($row_val, "'") !== false) {
                        $import_data["$flds_val"] = empty($row_val) || ($row_val === '-') ? "''" : $this->db->escape($row_val);
                    } else {
                        $import_data["$flds_val"] = empty($row_val) || ($row_val === '-') ? "''" : "'" . $row_val . "'";
                    }
                    $col++;
                }
                //
                $table_data[$cnt] = $import_data;
                $table_data[$cnt] = '(' . implode(',', $import_data) . ')';
                $cnt += 1;
                //var_dump('33',$row_val);                 
            }
            //var_dump('error',$table_data);
            //$this->db->insert_batch($db_name, $table_data);                     
            $str = " INSERT INTO $data_type (" . implode(',', $db_flds) . ") Values " . implode(",", $table_data) . " "
                . " ON DUPLICATE KEY UPDATE $update_rule";
            // var_dump($str);
            //return;
            log_message('error', "why here");
            log_message('error', $str);
            $this->db->query($str);
        }
        if ($data_type == 'pos_item') {
            $this->session->set_flashdata('success', 'Successful upload: <a href="' . base_url('uploads/' . $filename) . '" target="_blank">' . $filename . '</a>');

            redirect($redirectTo1);
        } else {
            redirect($redirectTo);
        }
    }
    //    public function import($data_type="locations")
    //     {
    //         $filename = $this->upload_files();

    //         $redirectTo = 'setup/view_'.$data_type;
    //         $rowData=array();
    //         $import_data=array();
    //         $db_name=$data_type;

    //         switch ($data_type) {
    //             case "users":
    //                 $db_flds= array('email','fullname','designation','dept','manager','mgr_email','userArea'); 
    //                 break;       
    //             case "items_table":
    //                 $db_flds= array('item_type','item_code','item_desc','item_price','location'); 
    //                 $update_rule="item_type=values(item_type),item_code=values(item_code),".
    //                     "item_desc=values(item_desc),item_price=values(item_price),location=values(location)";                 
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
    //                     'fullname'      => $rowData[$row-2][1],
    //                     'designation'   => $rowData[$row-2][2],
    //                     'dept'          => $rowData[$row-2][3],
    //                     'manager'       => $rowData[$row-2][4],
    //                     'mgr_email'     => $rowData[$row-2][5],  
    //                     'userArea'      => $rowData[$row-2][6],                                      
    //                     'user_role'     => 'User',
    //                     'company'       => 'Pernod Ricard West Africa',                                
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

    private function update_dept($action, $data_table, $data_key)
    {
        // $this->form_validation->set_rules('deptcode','Code','trim|required');
        $this->form_validation->set_rules('deptname', 'Name', 'trim|required');
        $this->form_validation->set_rules('hod', 'hod', 'trim|required');
        if ($this->form_validation->run() === true) {
            $id = $this->input->post('deptcode');
            $upd_data = array(
                //'deptcode'     => $this->input->post('deptcode'),
                'deptname'     => $this->input->post('deptname'),
                'deptcountry'     => $this->input->post('deptcountry'),
                'hod'     => $this->input->post('hod')
            );
            if ($action == "add") {
                $this->base_model->insert($upd_data, $data_table);
                $this->run_detail_log($data_table, $action, $where_conditions='', $upd_data  ,'deptcode',$id);

            }
            if ($action == "update") {
                $this->base_model->update(
                    $upd_data,
                    $id,
                    $data_table,
                    array($data_key => $id)
                );
                $this->run_detail_log($data_table, $action, $where_conditions='', $upd_data  ,'deptcode',$id);

            }
            echo json_encode(array("status" => TRUE));
        } else {
            header('Content-Type: application/json');
            echo json_encode(validation_errors());
        }
    }

    public function view_pos_batch()
    {
        $this->data['page_title'] = 'Setup POS Batch';
        $this->data['data_columns'] = '
         { "data": "request_date",
            "render": function ( data, type, row ) {                      
                return type === "sort" ? row[\'request_date\']:\'<a href="' . site_url('setup/view_page/') .  '/pos_batch_item\'+\'/\'+ row[\'batchcode\'] +\'">\'+data+\'</a>\';         
            }
        } ,
         { "data": "batchcode"},'
            . '{ "data": "procurementcode" },{ "data": "location" }';
        $this->data['data_section'] = "Setup";
        $this->data['data_table'] = "pos_batch_item";
        $this->data['data_key'] = "batchcode";
        $this->data['data_access'] = ['no-edit'];
        $this->data['mini'] = FALSE;
        $this->datatable_script($this->data['data_section'], 'asc', 0);
        $this->render('setup/setup_pos_batch_item');
    }
    // Add batch Detail
    public function add_pos_item($action = null, $id = null)
    {
        $this->form_script();
        if ($action === 'edit') {

            $this->data['page_title'] = 'Edit POS  Batch Details';
            $this->data['form_action'] = 'edit';
            $this->data['batchcode'] = $id;
            $this->datatable_script($this->data['data_section'], 'asc', 1);
            $this->data['main_data'] = is_null($id) ? array() :
                $this->base_model->get_single_record($id, 'batchcode', 'pos_batch_item');
        }
        if ($action === 'afterbatch') {

            $this->data['page_title'] = 'Edit POS  Batch Details';
            $this->data['form_action'] = 'afterbatch';
            $this->data['batchcode'] = $id;
            $this->datatable_script($this->data['data_section'], 'asc', 1);
            $this->data['main_data'] = is_null($id) ? array() :
                $this->base_model->get_single_record($id, 'batchcode', 'pos_batch_item');
        } else {
            $this->data['page_title'] = 'Add POS  Batch  Details';
            $this->data['form_action'] = 'add';
            $rand = substr(md5(microtime()), 0, 8);
            $this->data['rand'] = $rand;
            $this->data['data_section'] = "Setup";
            $this->data['data_table'] = "pos_order_item";
            $this->datatable_script($this->data['data_section'], 'desc', 1);
        }
        $this->form_validation->set_rules('procurementcode', 'Enter procurement code', 'trim');
        $this->form_validation->set_rules('request_date', 'Date ', 'trim|required');
        $this->form_validation->set_rules('location', 'location', 'trim|required');
        //$filename = $this->upload_files();


        if ($this->form_validation->run() === true) {

            //  $id=$this->input->post('id');
            $id = $this->input->post('batchcode');
            $upd_data = array(
                'batchcode' => $this->input->post('batchcode'),
                'procurementcode' => $this->input->post('procurementcode'),
                'request_date'  => $this->input->post('request_date'),
                'location'  => $this->input->post('location'),
                'date_added' => $this->datetime,


            );
            $upd_data2 = array(
                'batchcode' => $this->input->post('batchcode'),
                'procurementcode' => $this->input->post('procurementcode'),
                'request_date'  => $this->input->post('request_date'),
                'location'  => $this->input->post('location'),
                'date_updated' => $this->datetime,


            );
            $batchcode = $this->input->post('batchcode');

            if ($action == "add") {


                $this->importSingleFile($batchcode);
                $id = $this->base_model->insert($upd_data, 'pos_batch_item');
            }
            if ($action == "edit") {
                //  $id=$this->base_model->insert($upd_data,'pos_batch_item'); 
                $this->base_model->update(
                    $upd_data2,
                    $id,
                    'pos_batch_item',
                    array('batchcode' => $id)
                );
            }
            if ($action == "afterbatch") {
                $id = $this->base_model->insert($upd_data, 'pos_batch_item');
                //   $this->base_model->update($upd_data2,$id,
                //       'pos_batch_item',array('batchcode' => $id));
            }

            redirect('setup/view_pos_batch');
        } else {
            $this->render('setup/setup_add_pos_batch');
        }
    }

    // VIEW BATCH DETAILS
    public function view_pos_details($id)
    {
        $this->form_script_modal();
        $this->data['page_title'] = 'POS Batch Details - ' . $id;
        $this->data['data_columns'] = '{"data": "pos_id" },{"data": "batchcode" }, {  "data": "itemType" }, '
            . '{"data": "pos_desc" },{ "data": "qty" }';
        $this->data['data_section'] = "Setup";
        $this->data['data_var'] = $id;
        $this->data['data_table'] = "pos_order_item";
        $this->data['data_key'] = "pos_id";

        $this->data['data_editflds'] = '
                $(\'[name="id"]\').val(data.pos_id);
                $(\'[name="sno"]\').val(data.pos_id);
                $(\'[name="pos"]\').val(data.itemType);
                $(\'[name="pos_desc"]\').val(data.pos_desc);
                $(\'[name="batchcode"]\').val(data.batchcode);
                $(\'[name="qty"]\').val(data.qty);
                
               
            ';

        $this->data['main_data'] = $this->base_model->get_single_record($id, 'batchcode', 'pos_batch_item');
        $this->data['mini'] = FALSE;
        $this->datatable_script($this->data['data_section'], 'asc', 1);
        $this->render('setup/setup_pos_batch_detail');
    }

    public function importBatch($batchcode)
    {
        $filename = $this->upload_files();

        //  $redirectTo = 'setup/view_'.$data_type;
        $redirectTo = 'setup/add_pos_item';
        $redirectTo1 = 'setup/add_pos_item/afterbatch/' . $batchcode;
        $sucessRate = false;
        $add = '+';

        if (is_null($filename)) {
            $this->session->set_flashdata('error', 'Error in File Import - import failed');
            redirect($redirectTo);
        }
        $this->load->library('Excel');
        $this->session->set_flashdata('message', 'Successful upload' . $filename);
        try {
            /// it will be your file name that you are posting with a form or 
            //can pass static name $_FILES["file"]["name"];
            $objPHPExcel = PHPExcel_IOFactory::load('./uploads/imports/' . $filename);
            //$objWorksheet = $objPHPExcel->setActiveSheetIndex(0);
            $objWorksheet = $objPHPExcel->getSheet(0);
        } catch (Exception $e) {
            $this->session->set_flashdata('error', 'Error in Excel library - import failed');
            redirect($redirectTo);
            exit;
        }

        $highestRow = $objWorksheet->getHighestDataRow();
        $highestColumn = $objWorksheet->getHighestDataColumn();
        $rowData = $objWorksheet->rangeToArray(
            'A2:' . $highestColumn . $highestRow,
            NULL,
            TRUE,
            FALSE
        );
        //var_dump($rowData);

        for ($row = 2; $row <= $highestRow; $row++) {

            $additional_data = array(
                'poscode'    => $rowData[$row - 2][1],
                'itemType'     => $rowData[$row - 2][2],

                'qty'   => $rowData[$row - 2][4],

                'pos_desc'       => $rowData[$row - 2][3],
                'batchcode'       =>     $batchcode,
                'date_added'           => $this->datetime


            );
            $pos_new_data = array(
                //  'poscode'    => $this->passcode(),
                'poscode'    => $rowData[$row - 2][1],
                'pos_type'     => $rowData[$row - 2][2],
                // 'price'      => $rowData[$row-2][4],
                'qty'   => $rowData[$row - 2][4],
                're_order_level'          => $rowData[$row - 2][5],
                'pos_desc'       => $rowData[$row - 2][3],
                'date_added'           => $this->datetime


            );

            // $this->db->insert_batch($db_name, $additional_data);

            $result = $this->base_model->insert($additional_data, 'pos_order_item');
            $sucessRate = true;
            log_message("error", "here33");
            if ($sucessRate == true) {

                $passcodeql = $this->db->select('qty', 'pos_desc')->from('pos_item')->where('poscode', $rowData[$row - 2][1])->get();
                if ($passcodeql->num_rows() > 0) {
                    $sql = 'UPDATE  pos_item LEFT JOIN pos_order_item ON pos_item.pos_desc = pos_order_item.pos_desc
                        SET pos_item.qty= pos_item.qty +' . $rowData[$row - 2][4] . '  WHERE pos_item.pos_desc  = ' . '"' . $rowData[$row - 2][3] . '"' . ';';
                    $this->db->query($sql);
                    log_message("error", "update quantity");
                } else {

                    // $sql='UPDATE  pos_item LEFT JOIN pos_order_item ON pos_item.pos_desc = pos_order_item.pos_desc
                    // SET pos_item.qty= pos_item.qty +'.$rowData[$row-2][4].'  WHERE pos_item.pos_desc  = '.'"'.$rowData[$row-2][3].'"'.';';
                    // $this->db->query($sql); 
                    $sql = 'UPDATE  pos_item LEFT JOIN pos_order_item ON pos_item.pos_desc = pos_order_item.pos_desc
                        SET pos_item.qty= pos_item.qty +' . str_replace(',', '', $rowData[$row - 2][4]) . '  WHERE pos_item.pos_desc  = ' . '"' . $rowData[$row - 2][3] . '"' . ';';
                    $this->db->query($sql);;
                    $ADDNEWITEM = $this->base_model->insert($pos_new_data, 'pos_item');
                    log_message("error", "update quantity and ADD NEW ITEM");
                }
            }
            // var_dump($additional_data);
        }
        // echo "$('#modal_form2').modal('hide')";
        redirect($redirectTo1);
        return;
    }


    public function importSingleFile($batchcode)
    {
        //  $filename = $this->upload_files();
        $config['upload_path'] = './/uploads/imports/';
        $config['allowed_types'] =  'xls|xlsx|csv';
        $config['file_ext_tolower'] = TRUE;
        $config['file_ext_tolower'] = TRUE;
        $config['overwrite'] = 1;
        //var_dump print_r($data_type);
        $redirectTo = 'setup/add_pos_item';
        $redirectTo1 = 'requests/pos_item_view';
        $this->load->library('upload');
        $this->upload->initialize($config);
        print_r($data = array('upload_data' => $this->upload->data()));
        //return;
        if (!$this->upload->do_upload('userfile')) {
            $error = array('error' => $this->upload->display_errors());
            $this->session->set_flashdata('error', $error['error'] . ' or the wrong File was Selected please try again');
            // print_r($error);
            // exit;
            redirect($redirectTo);
        } else {
            $data = array('upload_data' => $this->upload->data());

            if (!empty($data)) {
                // $result= json_encode($data);
                // print_r($result);
                $fileStatus = TRUE;
                $filetype =  $data['upload_data']['file_type'];
                $fileNameNoExtension =  $data['upload_data']['file_name'];



                /// save image record
                $filename =   $fileNameNoExtension;

                var_dump($filename);
                //return;
            }
        }
        //  $redirectTo = 'setup/view_'.$data_type;
        $redirectTo = 'setup/add_pos_item';
        $redirectTo1 = 'setup/add_pos_item/afterbatch/' . $batchcode;
        $sucessRate = false;
        $add = '+';

        if (is_null($filename)) {
            $this->session->set_flashdata('error', 'Error in File Import - import failed');
            redirect($redirectTo);
        }
        $this->load->library('Excel');
        $this->session->set_flashdata('message', 'Successful upload' . $filename);
        try {
            /// it will be your file name that you are posting with a form or 
            //can pass static name $_FILES["file"]["name"];
            $objPHPExcel = PHPExcel_IOFactory::load('./uploads/imports/' . $filename);
            //$objWorksheet = $objPHPExcel->setActiveSheetIndex(0);
            $objWorksheet = $objPHPExcel->getSheet(0);
        } catch (Exception $e) {
            $this->session->set_flashdata('error', 'Error in Excel library - import failed');
            redirect($redirectTo);
            exit;
        }

        $highestRow = $objWorksheet->getHighestDataRow();
        $highestColumn = $objWorksheet->getHighestDataColumn();
        $rowData = $objWorksheet->rangeToArray(
            'A2:' . $highestColumn . $highestRow,
            NULL,
            TRUE,
            FALSE
        );
        //var_dump($rowData);

        for ($row = 2; $row <= $highestRow; $row++) {
            $qtyNumber = isset($rowData[$row - 2][2]) ? $rowData[$row - 2][2] : 0;
            $additional_data = array(
                'poscode'    => $rowData[$row - 2][0],
                'itemType'     => "POS",

                // 'qty'   => $rowData[$row-2][2],
                'qty'   => isset($rowData[$row - 2][2]) ? $rowData[$row - 2][2] : 0,
               // 'pos_desc'       => $rowData[$row - 2][1],
               'pos_desc' => preg_replace('/\s+/', '_', trim($rowData[$row - 2][1])),
                'batchcode'       =>     $batchcode,
                'date_added'           => $this->datetime


            );
            $pos_new_data = array(
                'poscode'    => $rowData[$row - 2][0],
                'pos_type'     => "POS",

                // 'qty'   => $rowData[$row-2][2],
                'qty'   => isset($rowData[$row - 2][2]) ? $rowData[$row - 2][2] : 0,
               // 'pos_desc'       => $rowData[$row - 2][1],
               'pos_desc' => preg_replace('/\s+/', '_', trim($rowData[$row - 2][1])),

                'date_added'           => $this->datetime

            );


            // $this->db->insert_batch($db_name, $additional_data);

            $result = $this->base_model->insert($additional_data, 'pos_order_item');
            $sucessRate = true;
            log_message("error", "here33");
            if ($sucessRate == true) {

                $passcodeql = $this->db->select('qty', 'pos_desc')->from('pos_item')->where('poscode', $rowData[$row - 2][0])->get();
                if ($passcodeql->num_rows() > 0) {
                    // $sql = 'UPDATE  pos_item LEFT JOIN pos_order_item ON pos_item.pos_desc = pos_order_item.pos_desc
                    //  SET pos_item.qty= pos_item.qty +' . $qtyNumber . '  WHERE pos_item.pos_desc  = ' . '"' . $rowData[$row - 2][1] . '"' . ';';
                    
                    $sql = 'UPDATE  pos_item LEFT JOIN pos_order_item ON pos_item.pos_desc = pos_order_item.pos_desc
                     SET pos_item.qty= pos_item.qty +' . $qtyNumber . '  WHERE pos_item.pos_desc  = ' . '"' .preg_replace('/\s+/', '_', trim($rowData[$row - 2][1])) . '"' . ';';
                    $this->db->query($sql);
                    log_message("error", "update quantity");
                } else {

                    // $sql = 'UPDATE  pos_item LEFT JOIN pos_order_item ON pos_item.pos_desc = pos_order_item.pos_desc
                    //  SET pos_item.qty= pos_item.qty +' . $qtyNumber . '  WHERE pos_item.pos_desc  = ' . '"' . $rowData[$row - 2][1] . '"' . ';';
                     $sql = 'UPDATE  pos_item LEFT JOIN pos_order_item ON pos_item.pos_desc = pos_order_item.pos_desc
                     SET pos_item.qty= pos_item.qty +' . $qtyNumber . '  WHERE pos_item.pos_desc  = ' . '"' . preg_replace('/\s+/', '_', trim($rowData[$row - 2][1])). '"' . ';';
                    $this->db->query($sql);

                    $ADDNEWITEM = $this->base_model->insert($pos_new_data, 'pos_item');
                    log_message("error", "update quantity and ADD NEW ITEM");
                }
            }
            // var_dump($additional_data);
        }
        // echo "$('#modal_form2').modal('hide')";
        //    redirect($redirectTo1);
        //     return;

    }

    private function update_items_table($action, $data_table, $data_key)
    {

        $this->form_validation->set_rules('item_type', 'Type', 'trim|required');
        $this->form_validation->set_rules('item_code', 'Code', 'trim|required');
        $this->form_validation->set_rules('item_desc', 'Desc', 'trim|required');
        $this->form_validation->set_rules('location', 'location', 'trim|required');
        if ($this->form_validation->run() === true) {
            $id = $this->input->post('sno');
            $upd_data = array(
                'item_type'     => $this->input->post('item_type'),
                'item_desc'     => $this->input->post('item_desc'),
                'item_code'     => $this->input->post('item_code'),
                'location'     => $this->input->post('location'),
                'item_price'     => $this->input->post('item_price')
            );
            if ($action == "add") {
                $this->base_model->insert($upd_data, $data_table);
                $this->run_detail_log('item_price', $action, $where_conditions='', $upd_data  ,'sno',$id);

            }
            if ($action == "update") {
                $this->base_model->update(
                    $upd_data,
                    $id,
                    $data_table,
                    array($data_key => $id)
                );
                $this->run_detail_log('item_price', $action, $where_conditions='', $upd_data  ,'sno',$id);

            }
            echo json_encode(array("status" => TRUE));
        } else {
            header('Content-Type: application/json');
            echo json_encode(validation_errors());
        }
    }
    private function update_free_good_item($action, $data_table, $data_key)
    {

        $this->form_validation->set_rules('item_type', 'Type', 'trim|required');
        $this->form_validation->set_rules('item_code', 'Code', 'trim|required');
        $this->form_validation->set_rules('item_desc', 'Desc', 'trim|required');
        $this->form_validation->set_rules('location', 'location', 'trim|required');
        if ($this->form_validation->run() === true) {
            $id = $this->input->post('sno');
            $upd_data = array(
                'item_type'     => $this->input->post('item_type'),
                'item_desc'     => $this->input->post('item_desc'),
                'item_code'     => $this->input->post('item_code'),
                'location'     => $this->input->post('location'),
                'item_price'     => $this->input->post('item_price')
            );
            if ($action == "add") {
                $this->base_model->insert($upd_data, $data_table);
                $this->run_detail_log($data_table, $action, $where_conditions='', $upd_data  ,'sno',$id);

            }
            if ($action == "update") {
                $this->base_model->update(
                    $upd_data,
                    $id,
                    $data_table,
                    array($data_key => $id)
                );
                $this->run_detail_log($data_table, $action, $where_conditions='', $upd_data  ,'sno',$id);

            }
            echo json_encode(array("status" => TRUE));
        } else {
            header('Content-Type: application/json');
            echo json_encode(validation_errors());
        }
    }

    private function update_parameters($action, $data_table, $data_key)
    {

        $this->form_validation->set_rules('setup_name', 'Parameters', 'trim|required');
        $this->form_validation->set_rules('setup_value', 'Setup Value', 'trim|required');
        if ($this->form_validation->run() === true) {
            $id = $this->input->post('id');
            $upd_data = array(
                'setup_name'     => $this->input->post('setup_name'),
                'setup_value'     => $this->input->post('setup_value')
            );
            if ($action == "add") {
                $this->base_model->insert($upd_data, $data_table);
                $this->run_detail_log($data_table, $action, $where_conditions='', $upd_data  ,'id',$id);

            }
            if ($action == "update") {
                $this->base_model->update(
                    $upd_data,
                    $id,
                    $data_table,
                    array($data_key => $id)
                );
                $this->run_detail_log($data_table, $action, $where_conditions='', $upd_data  ,'id',$id);

            }
            echo json_encode(array("status" => TRUE));
        } else {
            header('Content-Type: application/json');
            echo json_encode(validation_errors());
        }
    }

    private function update_users($action, $data_table, $data_key)
    {

        if ($action == "add") {
            $this->form_validation->set_rules('email', 'Email', 'trim|required|valid_email|is_unique[users.email]');
            $this->form_validation->set_message('is_unique', 'This %s has been registered. Please use another or login with it');
            $this->form_validation->set_rules('password', 'Password', 'trim|required|min_length[6]');
            $this->form_validation->set_rules('repass', 'Password confirmation', 'required|matches[password]');
        } else {
            $this->form_validation->set_rules('email', 'email', 'trim');
        }
        $this->form_validation->set_rules('designation', 'designation', 'trim');
        $this->form_validation->set_rules('user_role', 'user_role', 'trim|required');
        $this->form_validation->set_rules('user_status', 'user_status', 'trim|required');
		$this->form_validation->set_rules('dept', 'dept', 'trim|required');
        if ($this->form_validation->run() === true) {
            $user_status = $this->input->post('user_status') == "Active" ? 1 : 0;
            $additional_data = array(
                'fullname' => $this->input->post('fullname'),
                'designation'      => $this->input->post('designation'),
                'userArea'      => $this->input->post('userArea'),
				 'country'      => $this->input->post('userArea'),
                'cust_name'      => $this->input->post('cust_name'),
                'cust_code'      => $this->input->post('cust_code'),
                'dept'      => $this->input->post('dept'),
                'manager'      => $this->input->post('manager'),
                'mgr_email'      => $this->input->post('mgr_email'),
                'user_role'      => $this->input->post('user_role'),
                'company'      => 'Pernod Ricard West Africa',
                'active'      => $user_status
            );

            if ($action == "add") {
                $username = $this->get_id();
                $subject = "PRN Automator Notifier";
                $fullname = $this->input->post('first_name') . ' ' . $this->input->post('last_name');
                $email = $this->input->post('email');
                $password = $this->input->post('password');
                $group[0] = 2;
                $group[1] = 3;
                $group_ids = $group;
                $user_id = $this->ion_auth->register($username, $password, $email, $additional_data, $group_ids);
                sleep(3);
                // added email for new users 
                $sql = 'SELECT * FROM `users` WHERE `username`= "' . $username . '" and `email`= "' . $email . '"';
                $tempval = $this->base_model->run_qry($sql, "result");
                $userEmail = $tempval->email;
                if ($userEmail == $email && $tempval->username === $username) {
                    log_message('error', 'user detail');
                    $this->sendUserMail($email, $subject, $fullname, $password);
                    $this->session->set_flashdata('message', 'Email sent to ' . $userEmail);
                }
                $this->run_detail_log('users', $action, $where_conditions='', $additional_data  ,'id',$tempval->id);
            }
            if ($action == "update") {
                $id = $this->input->post('id');
                $key='id';
                $additional_data['email'] = $this->input->post('email');
                $this->base_model->update(
                    $additional_data,
                    $id,
                    $data_table,
                    array($data_key => $id)
                );
                sleep(3);
                $this->run_detail_log('users', $action, $where_conditions='', $additional_data,$data_key,$id);
            }
            echo json_encode(array("status" => TRUE));
        } else {
            header('Content-Type: application/json');
            echo json_encode(validation_errors());
        }
    }

    private function update_workflow_approvers($action, $data_table, $data_key)
    {
        $this->form_validation->set_rules('process_id', 'process_id', 'trim|required');
        $this->form_validation->set_rules('approver_from', 'approver_from', 'trim|required');
        $this->form_validation->set_rules('appr_name', 'appr_name', 'trim');
        $this->form_validation->set_rules('appr_function', 'appr_function', 'trim|required');
        $approve_type = $this->input->post('approve_type');
        if ($this->form_validation->run() === true) {
            $process_id = $this->input->post('process_id');
            $id = $this->input->post('id');
            $appr_name = $this->input->post('appr_name');
            $approver_from = $this->input->post('approver_from');
            if ($approver_from == "Preset1" || $approver_from == "Initiator" || $approver_from == "Preset3") {
                $appr_name = 0;
            }
            $upd_data = array(
                'process_id'     => $this->input->post('process_id'),
                'approver_from'   => $approver_from,
                'appr_name'       => empty($appr_name) ? 0 : $appr_name,
                'appr_function'   => $this->input->post('appr_function'),
                'approve_type'       => empty($approve_type) ? "" : $approve_type,

                'notifier'   => $this->input->post('notifier')
            );

            if ($action == "add") {
                $order_no = $this->base_model->rec_count($data_table, $process_id, 'process_id');
                $upd_data['order_no'] = empty($order_no) ? 1 : $order_no + 1;
                $this->base_model->insert($upd_data, $data_table);
                $this->run_detail_log($data_table, $action, $where_conditions='', $upd_data  ,'process_id',$id);

            }
            if ($action == "update") {
                $this->base_model->update($upd_data, $id, $data_table, array($data_key => $id));
                $this->run_detail_log($data_table, $action, $where_conditions='', $upd_data  ,'process_id',$id);

            }
            echo json_encode(array("status" => TRUE));
        } else {
            header('Content-Type: application/json');
            echo json_encode(validation_errors());
        }
    }
    private function update_request_approvers($action, $data_table, $data_key)
    {
        $this->form_validation->set_rules('process_id', 'process_id', 'trim|required');
        $this->form_validation->set_rules('approver_from', 'approver_from', 'trim|required');
        $this->form_validation->set_rules('appr_name', 'appr_name', 'trim');
        $this->form_validation->set_rules('appr_function', 'appr_function', 'trim|required');
        $approve_type = $this->input->post('approve_type');
        $current_next_appr = $this->input->post('appr_name2');
        $request_id = $this->input->post('request_id');
        if ($this->form_validation->run() === true) {
            $process_id = $this->input->post('process_id');
            $id = $this->input->post('id');
            $appr_name = $this->input->post('appr_name');
            $approver_from = $this->input->post('approver_from');
            if ($approver_from == "Preset1" || $approver_from == "Initiator" || $approver_from == "Preset3") {
                $appr_name = 0;
            }
            $upd_data = array(
                'process_id'     => $this->input->post('process_id'),
                //    'approver_from'   => $approver_from,                  
                'appr_name'       => empty($appr_name) ? 0 : $appr_name,
                'appr_function'   => $this->input->post('appr_function'),

                'request_id'   => $this->input->post('request_id')
            );
            $next_appr = $this->base_model->get_single_record($request_id, 'request_id', 'workflow_requests')->next_appr;
            if ($action == "add") {
                $order_no = $this->base_model->rec_count($data_table, $process_id, 'process_id');
                $upd_data['order_no'] = empty($order_no) ? 1 : $order_no + 1;
                $this->base_model->insert($upd_data, $data_table);
                $this->run_detail_log($data_table, $action, $where_conditions='', $upd_data  ,'process_id',$id);

            }
            if ($action == "update") {
                $this->base_model->update($upd_data, $id, $data_table, array($data_key => $id));
                $this->run_detail_log($data_table, $action, $where_conditions='', $upd_data  ,'process_id',$id);

                if ($next_appr == $current_next_appr) {
                    $requester_id = $this->base_model->get_single_record($request_id, 'request_id', 'workflow_requests')->requester_id;
                    $requester_acct = $this->ion_auth->user($requester_id)->row();
                    $requester_name = $requester_acct->fullname;

                    $requester_acct1 = $this->ion_auth->user($appr_name)->row();
                    $next_appr_name = $requester_acct1->fullname;
                    $headerMessage = ($this->base_model->get_single_record($request_id, 'request_id', 'workflow_requests')->request_type == "error_request") ? "  Request raised by " . $requester_name . " is currently awaiting your review" : " Request raised by " . $requester_name . " has been re-assigned to you and is awaiting your approval";
                    $subject = "A " . $this->get_title($request_id) . $headerMessage;
                    $action_type = $this->base_model->get_single_record($request_id, 'request_id', 'workflow_requests')->request_status;
                    $sql = 'UPDATE  workflow_requests   SET  next_appr= ' . $appr_name . ', next_approver=' . '"' . $next_appr_name . '"' . '    WHERE request_id =' . '"' . $request_id . '"' . ' ;';
                    $this->db->query($sql);
                    $this->sendMail($appr_name, $subject, $request_id, $action_type);

                }
            }
            echo json_encode(array("status" => TRUE));
        } else {
            header('Content-Type: application/json');
            echo json_encode(validation_errors());
        }
    }

    public function ajax_action($action, $data_table, $data_key = FALSE)
    {
        switch ($data_table) {
            case "setup_parameters":
                $this->update_parameters($action, $data_table, $data_key);
                break;
            case "users":
                $this->update_users($action, $data_table, $data_key);
                break;
            case "items_table":
                $this->update_items_table($action, $data_table, $data_key);
                break;
            case "free_good_item":
                $this->update_free_good_item($action, $data_table, $data_key);
                break;
            case "dept":
                $this->update_dept($action, $data_table, $data_key);
                break;
            case "workflow_approvers":
                $this->update_workflow_approvers($action, $data_table, $data_key);
                break;
            case "request_approvers":
                $this->update_request_approvers($action, $data_table, $data_key);
                break;
            case "customer_location":
                $this->update_customer_location($action, $data_table, $data_key);
                break;
            case "sales_product_price":
                $this->update_sales_product_price($action, $data_table, $data_key);
                break;
            case "pos_item":
                $this->update_pos_item($action, $data_table, $data_key);
                break;
            case "pos_order_item":
                $this->update_add_pos_order_itemn($action, $data_table, $data_key);
                break;
        }
    }


    public function ajax_reset($data_table, $data_key)
    {
        $rows = $this->input->post('rows');
        foreach ($rows as $row) {
            $identity = $this->ion_auth->user($row)->row()->email;
            $change = $this->ion_auth->reset_password($identity, 'password');
        }
        echo json_encode(array("status" => TRUE));
    }

    public function ajax_list($data_table, $id = NULL)
    {
        header('Content-Type: application/json');
        if (is_null($id)) {
            switch ($data_table) {
                case "workflow":
                    $temp_data = $this->base_model->run_qry("CALL LIST_WORKFLOW()", 'qry');
                    $total_data = $temp_data->num_rows();
                    $total_data = is_null($total_data) ? 0 : $total_data;
                    $main_data = $temp_data->result();
                    break;
                default:
                    $total_data = $this->base_model->rec_count($data_table, $id, $key);
                    $total_data = is_null($total_data) ? 0 : $total_data;
                    $main_data = $this->base_model->get_record($data_table, $id, $key);
                    break;
            }
        } else {
            switch ($data_table) {
                case "workflow":
                    $key = "process_id";
                    $total_data = $this->base_model->rec_count($data_table, $id, $key);
                    $total_data = is_null($total_data) ? 0 : $total_data;
                    $main_data = $this->base_model->get_record($data_table, $id, $key);
                    break;
                case "workflow_approvers":
                    $temp_data = $this->base_model->run_qry("CALL GET_WORKFLOW_APPROVERS('$id')", 'qry');
                    $total_data = $temp_data->num_rows();
                    $total_data = is_null($total_data) ? 0 : $total_data;

                    $main_data = $temp_data->result();
                    break;
                case "request_approvers":
                    $temp_data = $this->base_model->run_qry("CALL GET_REQUEST_APPROVERS('$id')", 'qry');
                    $total_data = $temp_data->num_rows();
                    $total_data = is_null($total_data) ? 0 : $total_data;

                    $main_data = $temp_data->result();
                    break;
                case "customer_location":
                    $temp_data = $this->base_model->run_qry("CALL GET_CUSTOMER_LOCATION('$id')", 'qry');
                    $total_data = $temp_data->num_rows();
                    $total_data = is_null($total_data) ? 0 : $total_data;

                    $main_data = $temp_data->result();
                    break;
                case "pos_order_item":
                    $temp_data = $this->base_model->run_qry("CALL GET_POS_BATCH_ITEM('$id')", 'qry');
                    $total_data = $temp_data->num_rows();
                    $total_data = is_null($total_data) ? 0 : $total_data;

                    $main_data = $temp_data->result();
                    break;
            }
            $main_data = $total_data == 0 ? array() : $main_data;
        }
        $json_data = array(
            "recordsTotal"    => intval($total_data),  // total number of records
            "recordsFiltered" => intval($total_data), // total number of records after searching, if there is no searching then totalFiltered = totalData
            "data"            => $main_data   // total data array
        );
        echo json_encode($json_data);
    }

    public function ajax_edit($data_table, $data_key, $id)
    {
        $data = $this->base_model->get_single_record($id, $data_key, $data_table);
        echo json_encode($data);
    }

    public function ajax_delete($data_table, $data_key)
    {
        $rows = $this->input->post('rows');
        $action = 'delete';
        foreach ($rows as $row) {
            $this->run_detail_log($data_table, $action, array($data_key => $row), $detail = '',$id='',$key='');
            $this->base_model->delete($data_table, array($data_key => $row));
            log_message("error", $data_table);
            if ($data_table == "workflow_approvers") {
                $process_id = trim(urldecode($this->input->post('process_id')));
                log_message("error", $process_id);


                $sql = "SET @order_no := 0;
                UPDATE workflow_approvers 
                SET order_no = (@order_no := @order_no + 1) 
                WHERE process_id = '$process_id'
                ORDER BY order_no;
                ";
                $this->base_model->run_qry("CALL ReassignOrderNo('" . $process_id . "')", 'qry');
            }
            if ($data_table == "request_approvers") {
                $process_id = trim(urldecode($this->input->post('process_id')));
                log_message("error", $process_id);


                $sql = "SET @order_no := 0;
                UPDATE workflow_approvers 
                SET order_no = (@order_no := @order_no + 1) 
                WHERE process_id = '$process_id'
                ORDER BY order_no;
                ";
                $this->base_model->run_qry("CALL ReassignApproverOrderNo('" . $process_id . "')", 'qry');
            }
        }
        echo json_encode(array("status" => TRUE));
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
        $this->data['data_columns'] = '{ "data": "sno","visible":false },'
            . '{ "data": "product_code" },{ "data": "prod_desc" },{ "data": "cust_type" },{ "data": "uom" },'
            . '{ "data": "unit_price", "render": function ( data, type, row ) '
            . '{if(data==\'0.00\'){return \'\'}else{return format_number(data)};} }';
        $this->data['data_section'] = "Setup";
        $this->data['data_table'] = "sales_product_price";
        $this->data['data_key'] = "sno";
        $this->data['data_editflds'] = '
            $(\'[name="sno"]\').val(data.sno);
            $(\'[name="product_code"]\').val(data.product_code);
             $(\'[name="prod_desc"]\').val(data.prod_desc);
             var $select1 = $(\'#uom\').selectize(); 
             var selectize1 = $select1[0].selectize; 
             selectize1.setValue(data.uom, false);
            
            $(\'[name="cust_type"]\').val(data.cust_type);

            $(\'[name="unit_price"]\').val(data.unit_price);';
        $this->data['mini'] = FALSE;
        $this->datatable_script($this->data['data_section'], 'asc', 1);

        $this->render('setup/setup_product_detail');
    }
    public function view_page($data_table, $id = '')
    {
        switch ($data_table) {
            case 'workflow':
                $this->view_workflow_details($id);
                break;
            case 'customer_master_data':
                $this->view_customer_details($id);
                break;
        }
    }
    // Add Customer Detail
    public function add_customer($action = null, $id = null)
    {
        $this->form_script();
        $this->form_script_modal();
        if ($action === 'edit') {
            $this->data['data_section'] = "Setup";
            $this->data['page_title'] = 'Edit Customer Details';
            $this->data['form_action'] = 'edit';
            $this->data['main_data'] = is_null($id) ? array() :
                $this->base_model->get_single_record($id, 'cust_code', 'customer_master_data');
        } else {
            $this->data['page_title'] = 'Add Customer Details';
            $this->data['form_action'] = 'add';
        }
        $this->form_validation->set_rules('cust_name1', 'Enter Name', 'trim|required');
        $this->form_validation->set_rules('cust_code', 'Customer Code', 'trim|required');
        $this->form_validation->set_rules('cust_type', 'Customer Type', 'trim|required');
        $this->form_validation->set_rules('credit_limit', 'Credit Limit', 'trim|required');
        $this->form_validation->set_rules('credit_type', 'Credit Type', 'trim|required');


        if ($this->form_validation->run() === true) {
            $id = $this->input->post('id');
            $upd_data = array(
                'cust_name' => $this->input->post('cust_name1'),
                'cust_code'  => $this->input->post('cust_code'),
                'cust_type'  => $this->input->post('cust_type'),
                'credit_limit' => $this->input->post('credit_limit'),
                'credit_type'     => $this->input->post('credit_type')

            );
            // $this->base_model->insert($upd_data,'customer_master_data');  
            // redirect('setup/cust_view');
            if ($action == "add") {
                $id = $this->base_model->insert($upd_data, 'customer_master_data');
                $this->run_detail_log('customer_master_data', $action, $where_conditions='', $upd_data  ,'process_id',$id);

            }
            if ($action == "edit") {
                $this->base_model->update(
                    $upd_data,
                    $id,
                    'customer_master_data',
                    array('cust_code' => $id)
                );
                $this->run_detail_log('customer_master_data', $action, $where_conditions='', $upd_data  ,'process_id',$id);

            }
            redirect('setup/view_customer');
        } else {
            $this->render('setup/setup_add_customer');
        }
    }
    public function view_customer()
    {
        $this->data['page_title'] = 'Setup Customer';
        $this->data['data_columns'] = '{ "data": "cust_name"},'
            . '{ "data": "cust_code" },{ "data": "cust_type" },{ "data": "credit_type" },'
            . '{ "data": "credit_limit" }';
        $this->data['data_section'] = "Setup";
        $this->data['data_table'] = "customer_master_data";
        $this->data['data_key'] = "cust_code";
        $this->data['data_access'] = ['no-edit'];
        $this->data['mini'] = FALSE;
        $this->datatable_script($this->data['data_section'], 'asc', 1);
        $this->render('setup/setup_customer_list');
    }
    public function view_customer_details($id)
    {
        $this->form_script_modal();
        $this->data['page_title'] = 'Customers Details - ' . $id;
        $this->data['data_columns'] = '{"data": "sno" }, {  "data": "cust_name" }, '
            . '{"data": "cust_code" },{ "data": "address" },{ "data": "store" },{ "data": "name_store" },{ "data": "email" }';
        $this->data['data_section'] = "Setup";
        $this->data['data_var'] = $id;
        $this->data['data_table'] = "customer_location";
        $this->data['data_key'] = "sno";
        // made changes here
        $this->data['data_editflds'] = '
            $(\'[name="id"]\').val(data.sno);
            $(\'[name="sno"]\').val(data.sno);
            $(\'[name="cust_name"]\').val(data.cust_name);
            $(\'[name="cust_code"]\').val(data.cust_code);
            $(\'[name="store"]\').val(data.store);
            $(\'[name="address"]\').val(data.address);
            $(\'[name="name_store"]\').val(data.name_store);
            $(\'[name="email"]\').val(data.email);
            $(\'#address\').hide();
        ';

        $this->data['main_data'] = $this->base_model->get_single_record($id, 'cust_code', 'customer_master_data');
        $this->data['mini'] = FALSE;
        $this->datatable_script($this->data['data_section'], 'asc', 1);
        $this->render('setup/setup_customer_detail');
    }
    private function update_customer_location($action, $data_table, $data_key)
    {
        //$this->form_validation->set_rules('id','id','trim|required');
        // $this->form_validation->set_rules('cust_name','cust_name','required');        
        // $this->form_validation->set_rules('cust_code','cust_code','trim');                
        $this->form_validation->set_rules('store', 'store', 'trim|required');
        // $this->form_validation->set_rules('name_store','name_store','trim|required');
        $this->form_validation->set_rules('email', 'email', 'trim|required');

        if ($this->form_validation->run() === true) {
            $process_id = $this->input->post('sno');
            $id = $this->input->post('sno');
            $appr_name = $this->input->post('appr_name');
            $approver_from = $this->input->post('approver_from');

            $upd_data = array(
                'cust_name'     => $this->input->post('cust_name'),
                'cust_code'   => $this->input->post('cust_code'),
                'store'       => $this->input->post('store'),
                'name_store'   => trim($this->input->post('cust_name') . " - " . $this->input->post('store')),
                'email'   =>  trim($this->input->post('email')),
                'address'   => $this->input->post('address')
            );
            $upd_data2 = array(
                'cust_name'     => $this->input->post('cust_name'),
                'cust_code'   => $this->input->post('cust_code'),
                'store'       => $this->input->post('store'),
                'name_store'   => trim($this->input->post('name_store')),
                'email'   =>  trim($this->input->post('email')),
                'address'   => $this->input->post('address')
            );
            if ($action == "add") {
                // $order_no=$this->base_model->rec_count($data_table,$process_id,'sno');
                // $upd_data['order_no']=empty($order_no)?1:$order_no+1;
                $this->base_model->insert($upd_data, $data_table);
                $this->run_detail_log($data_table, $action, $where_conditions='', $upd_data  ,'sno',$id);

            }
            if ($action == "update") {
                $this->base_model->update($upd_data2, $id, $data_table, array($data_key => $id));
                $this->run_detail_log($data_table, $action, $where_conditions='', $upd_data2  ,'sno',$id);

            }
            echo json_encode(array("status" => TRUE));
        } else {
            header('Content-Type: application/json');
            echo json_encode(validation_errors());
        }
    }
    private function update_sales_product_price($action, $data_table, $data_key)
    {
        //$this->form_validation->set_rules('id','id','trim|required');
        $this->form_validation->set_rules('product_code', 'product_code', 'required');
        $this->form_validation->set_rules('prod_desc', 'prod_desc', 'trim');
        $this->form_validation->set_rules('cust_type', 'cust_type', 'trim|required');
        $this->form_validation->set_rules('uom', 'uom', 'trim|required');
        $this->form_validation->set_rules('unit_price', 'unit_price', 'trim|required');

        if ($this->form_validation->run() === true) {
            $process_id = $this->input->post('sno');
            $id = $this->input->post('sno');
            $appr_name = $this->input->post('appr_name');
            $approver_from = $this->input->post('approver_from');

            $upd_data = array(
                'product_code'     => $this->input->post('product_code'),
                'prod_desc'   => $this->input->post('prod_desc'),
                'cust_type'       => $this->input->post('cust_type'),
                'uom'   => $this->input->post('uom'),
                'unit_price'   => $this->input->post('unit_price')
            );

            if ($action == "add") {
                // $order_no=$this->base_model->rec_count($data_table,$process_id,'process_id');
                // $upd_data['order_no']=empty($order_no)?1:$order_no+1;
                $this->base_model->insert($upd_data, $data_table);
                $this->run_detail_log($data_table, $action, $where_conditions='', $upd_data  ,'sno',$id);

            }
            if ($action == "update") {
                $this->base_model->update($upd_data, $id, $data_table, array($data_key => $id));
                $this->run_detail_log($data_table, $action, $where_conditions='', $upd_data  ,'sno',$id);

            }
            echo json_encode(array("status" => TRUE));
        } else {
            header('Content-Type: application/json');
            echo json_encode(validation_errors());
        }
    }
    // request for pos

    private function update_pos_item($action, $data_table, $data_key)
    {
        $this->load->library('upload');

        //$this->form_validation->set_rules('id','id','trim|required');
        $this->form_validation->set_rules('pos', 'pos', 'required');
        $this->form_validation->set_rules('pos_type', 'pos_type', 'trim');
        $this->form_validation->set_rules('pos_desc', 'pos_desc', 'trim|required');
        $this->form_validation->set_rules('qty', 'qty', 'trim|required');
        //$this->form_validation->set_rules('price','price','trim|required');

        if ($this->form_validation->run() === true) {
            $process_id = $this->input->post('pos_id');
            $id = $this->input->post('pos_id');
            $poscode = $this->input->post('pos');


            $config['upload_path'] = './uploads/pictures/POS/';
            $config['allowed_types'] = 'gif|jpg|png';
            $config['max_size'] = 10000;
            $config['max_width'] = 10000;
            $config['max_height'] = 10000;

            //  $this->load->library('upload');
            $this->upload->initialize($config);
            // print_r($data = array('upload_data' => $this->upload->data()));
            // return;
            if (!$this->upload->do_upload('importfile')) {
                $error = array('error' => $this->upload->display_errors());

                //  print_r($error);
                //exit;
            } else {
                $data = array('upload_data' => $this->upload->data());

                if (!empty($data)) {
                    // $result= json_encode($data);
                    // print_r($result);
                    $fileStatus = TRUE;
                    $filetype =  $data['upload_data']['file_type'];
                    $fileNameNoExtension =  $data['upload_data']['file_name'];



                    /// save image record
                    $picture_link =  site_url('') . "./uploads/pictures/POS/" . $fileNameNoExtension;
                    $post_file = array(
                        'poscode' => $this->input->post('pos'),
                        'file_type' => $filetype,
                        'filename' => $fileNameNoExtension,
                        'attachment_file' => site_url('') . "./uploads/pictures/POS/" . $fileNameNoExtension,
                        'dateadded' => date('Y-m-d H:i:s')
                    );
                }
            }
            $upd_data = array(
                'poscode'     => $this->input->post('pos'),
                'pos_type'   => $this->input->post('pos_type'),
                'price'   =>   $total_amount = str_replace(",", "", $this->input->post('price')),
//'pos_desc'       => $this->input->post('pos_desc'),
                'pos_desc' => preg_replace('/\s+/', '_', trim($this->input->post('pos_desc'))),
                'qty'   => $this->input->post('qty'),
                'safety_lock'   => $this->input->post('safety_lock'),
                're_order_level'   => $this->input->post('re_order_level'),
                'location'   => $this->input->post('location'),
                'status'   => $this->input->post('status'),
                'return_type'   => $this->input->post('return_type')
            );
            $posscode =   $this->input->post('pos');
            if ($action == "add") {
                // $order_no=$this->base_model->rec_count($data_table,$process_id,'process_id');
                // $upd_data['order_no']=empty($order_no)?1:$order_no+1;
                $poscodeql = $this->db->distinct()->select('picture_link', 'qty', 're_order_level', 'pos_desc', 'poscode')->from('pos_item')->where('poscode', $posscode)->get();
                if ($poscodeql->num_rows() > 0) {
                    $this->db->where('poscode', $poscode);
                    $this->db->update($data_table, $upd_data);

                }else{
                    $this->base_model->insert($upd_data, $data_table);
                }
                // $this->base_model->insert($upd_data, $data_table);
                $this->run_detail_log($data_table, $action, $where_conditions='', $upd_data  ,'pos_id',$id);

                if ($fileStatus) {
                    $this->db->insert('pos_attachment', $post_file);
                  //  $poscodeql = $this->db->distinct()->select('picture_link', 'qty', 're_order_level', 'pos_desc', 'poscode')->from('pos_item')->where('poscode', $posscode)->get();
                    if ($poscodeql->num_rows() > 0) {
                        $sql = 'UPDATE  pos_item   SET picture_link=' . '"' . $picture_link . '"' . '  WHERE poscode  = ' . '"' . $posscode . '"' . ';';
                        $this->db->query($sql);
                        log_message("error", "update POS image link for add");
                    }
                }
            }
            if ($action == "update") {
                log_message('error', $id);
                $this->base_model->update($upd_data, $id, $data_table, array($data_key => $id));
                $this->run_detail_log($data_table, $action, $where_conditions='', $upd_data  ,'pos_id',$id);

                if ($fileStatus) {
                    $this->db->insert('pos_attachment', $post_file);
                    $poscodeql = $this->db->distinct()->select('picture_link', 'qty', 're_order_level', 'pos_desc', 'poscode')->from('pos_item')->where('poscode', $posscode)->get();
                    if ($poscodeql->num_rows() > 0) {
                        $sql = 'UPDATE  pos_item   SET picture_link=' . '"' . $picture_link . '"' . '  WHERE poscode  = ' . '"' . $posscode . '"' . ';';
                        $this->db->query($sql);
                        log_message("error", "update POS image link for update");
                    }
                }
            }

            // $this->uploadposfiles2( $poscode,$file,$datainfo);
            // redirect('requests/uploadfiles/importfiles/'.$poscode.'/'.$id);
            // $this->uploadfiles('importfiles', $poscode,$id);
            echo json_encode(array("status" => TRUE));
        } else {
            header('Content-Type: application/json');
            echo json_encode(validation_errors());
        }
    }
    public function run_detail_log($table = '', $action, $where_conditions, $detail = '',$key='',$id='')
    {
        $email = $this->ion_auth->user($this->ion_auth->get_user_id())->row()->email;
        $fullname = $this->ion_auth->user($this->ion_auth->get_user_id())->row()->fullname;
        log_message('error',$action);
        log_message('error',$table);
        log_message('error',"where_conditions".var_export($where_conditions,true));
        if ($action == "delete") {
            $result = $this->base_model->select($table, $where_conditions);

            // Check if the result is not NULL
           if ($result !== NULL) {
                // Process the result array
                //  print_r($result);
                $detail = json_encode($result);
                $upd_data = array(
                    'user_id'     => $this->ion_auth->get_user_id(),
                    'user_name'   => $fullname,
                    'user_email'       => $email,
                    'action_type'       => $action,
                    'detail'       => $detail,
                    'date_of_action'       => $this->datetime,
                    'affected_table_name'   => $table
                );
                $this->base_model->insert($upd_data, 'detail_log');
           }
            return;
        }
        if ($action == "add") {
            // $last_record = $this->base_model->get_last_saved_record($table,$key,$id);

            // if ($last_record !== NULL) {
                // Process the last saved record
                //print_r($last_record);
                $detail = json_encode($detail);
                $upd_data = array(
                    'user_id'     => $this->ion_auth->get_user_id(),
                    'user_name'   => $fullname,
                    'user_email'       => $email,
                    'action_type'       => $action,
                    'detail'       => $detail,
                    'date_of_action'       => $this->datetime,
                    'affected_table_name'   => $table
                );
                $this->base_model->insert($upd_data, 'detail_log');
         
            return;
        }
       else{
            // $where_conditions1 = array($id => $key);
            // $where_conditions1 = array($id => $key);
            // log_message('error', 'where_conditions'.$where_conditions1.$id.$key);
            // $result = $this->base_model->get_last_saved_record($table,$key,$id);

            // // Check if the result is not NULL
            // if ($result !== NULL) {
                // Process the result array
                //  print_r($result);
                $detail = json_encode($detail);
                $upd_data = array(
                    'user_id'     => $this->ion_auth->get_user_id(),
                    'user_name'   => $fullname,
                    'user_email'       => $email,
                    'action_type'       => $action,
                    'detail'       => $detail,
                    'date_of_action'       => $this->datetime,
                    'affected_table_name'   => $table
                );
                $this->base_model->insert($upd_data, 'detail_log');
                return;
            }
           
        
    }
    // Add new Batch pos details
    private function update_add_pos_order_itemn($action, $data_table, $data_key)
    {
        //$this->form_validation->set_rules('id','id','trim|required');
        // $this->form_validation->set_rules('cust_name','cust_name','required');        
        $this->form_validation->set_rules('batchcode', 'batchcode', 'trim');
        $this->form_validation->set_rules('pos', 'POS Item Type', 'trim|required');
        $this->form_validation->set_rules('pos_desc', 'POS Description', 'trim|required');
        $this->form_validation->set_rules('qty', 'Quantity', 'trim|required');

        if ($this->form_validation->run() === true) {
            $process_id = $this->input->post('sno');
            $id = $this->input->post('sno');


            $upd_data = array(
                'batchcode'     => $this->ion_auth->get_user_id(),
                'poscode'   => $this->input->post('pos'),
                'qty'       => $this->input->post('qty'),
                'itemType'       => $this->input->post('pos'),
                'date_added'       => $this->datetime,
                'pos_desc'   => $this->input->post('pos_desc')
            );
            $upd_data2 = array(
                'batchcode'     => $this->input->post('batchcode'),
                'poscode'   => $this->input->post('pos'),
                'qty'       => $this->input->post('qty'),
                'itemType'       => $this->input->post('pos'),
                'date_updated'       => $this->datetime,
                'pos_desc'   => $this->input->post('pos_desc')
            );

            if ($action == "add") {
                // $order_no=$this->base_model->rec_count($data_table,$process_id,'sno');
                // $upd_data['order_no']=empty($order_no)?1:$order_no+1;
                $this->base_model->insert($upd_data, $data_table);
            }
            if ($action == "update") {
                $this->base_model->update($upd_data2, $id, $data_table, array($data_key => $id));
            }
            echo json_encode(array("status" => TRUE));
        } else {
            header('Content-Type: application/json');
            echo json_encode(validation_errors());
        }
    }
    public function add_pos_image()
    {
        $this->render('setup/setup_add_pos_item');
    }
    //upload images
    function do_upload_images()
    {
        $redirectTo = 'setup/add_pos_image';
        $redirectTo1 = 'requests/pos_item_view';
        // File upload configuration
        $config['upload_path'] = './uploads/pictures/POS/';
        $config['allowed_types'] = 'gif|jpg|png';
        $config['max_size'] = '1024';
        // $config['encrypt_name'] = TRUE;
        $config['file_ext_tolower'] = TRUE;
        $config['remove_spaces'] = TRUE;

        $this->load->library('upload', $config);
        $this->upload->initialize($config);

        $fileInfos = array();
        $errors = array();
        if (!empty($_FILES['photos']['name'])) {
            $photosCount = count($_FILES['photos']['name']);
            for ($i = 0; $i < $photosCount; $i++) {
                $filedetail = $_FILES['photo']['tmp_name'] = $_FILES['photos']['name'][$i];
                $file_name = "./uploads/pictures/POS/" . $_FILES['photo']['tmp_name'] = $_FILES['photos']['name'][$i];
                log_message("error", 'filename #' . $file_name . ' is named "' . $file_name . '"');
                // Create file upload info
                $_FILES['photo']['name'] = $_FILES['photos']['name'][$i];
                $_FILES['photo']['type'] = $_FILES['photos']['type'][$i];
                $_FILES['photo']['tmp_name'] = $_FILES['photos']['tmp_name'][$i];
                $_FILES['photo']['error'] = $_FILES['photos']['error'][$i];
                $_FILES['photo']['size'] = $_FILES['photos']['size'][$i];
                $imageDetail = substr($filedetail, 0, strrpos($filedetail, '.'));
                //$posscode = substr(strrchr($imageDetail, needle: '-'), 1);
                $posscode =trim($imageDetail); ;

                log_message("error", 'imageDetail #' . $imageDetail . ' is filedetail "' . $filedetail . '"');
                log_message("error", 'posscodebefore #' . $posscode . ' is filedetail "' . $filedetail . '"');
                // re -Upload file to server
                if (file_exists($file_name)) {
                    log_message("error", 'filename2 #' . $file_name . ' is named "' . $file_name . '"');
                    unlink($file_name);

                    if ($this->upload->do_upload('photo')) {
                        array_push($fileInfos, array(
                            'fileInfo' => $this->upload->data()
                        ));

                        foreach ($fileInfos as $fileInfo) {
                            // Initializing a variable
                            // with filename
                            $fileExtra = $fileInfo['fileInfo']['file_name'];
                            //$fileName = basename($file);
                            $fileNameNoExtension = preg_replace("/\.[^.]+$/", "", $fileExtra);

                            // Using substr 
                            $x = substr($fileExtra, 0, strrpos($fileExtra, '.'));

                            // Display the filename
                            //echo $x . "<BR>";
                            $picture_link =  site_url('') . "./uploads/pictures/POS/" . $fileInfo['fileInfo']['file_name'];
                            log_message("error", 'posscode #' . $posscode . ' is named "' . $picture_link . '"');
                            $post_file = array(
                                'poscode' => $posscode,
                                'file_type' => $fileInfo['fileInfo']['file_type'],
                                'filename' => $fileNameNoExtension,
                                'attachment_file' => site_url('') . "./uploads/pictures/POS/" . $fileInfo['fileInfo']['file_name'],
                                'dateadded' => date('Y-m-d H:i:s')
                            );
                            $this->db->insert('pos_attachment', $post_file);
                            $poscodeql = $this->db->distinct()->select('picture_link', 'qty', 're_order_level', 'pos_desc', 'poscode')->from('pos_item')->where('poscode', $posscode)->get();
                            if ($poscodeql->num_rows() > 0) {
                                $sql = 'UPDATE  pos_item   SET picture_link=' . '"' . $picture_link . '"' . '  WHERE poscode  = ' . '"' . $posscode . '"' . ';';
                                $this->db->query($sql);
                                log_message("error", "update POS image link2");
                            }
                        }
                    } else {
                        array_push($errors, array(
                            'error' => $this->upload->display_errors()
                        ));
                    }
                } else {
                    //                                ;
                    // Upload file to server
                    if ($this->upload->do_upload('photo')) {
                        array_push($fileInfos, array(
                            'fileInfo' => $this->upload->data()
                        ));

                        foreach ($fileInfos as $fileInfo) {
                            // Initializing a variable
                            // with filename
                            $file = $fileInfo['fileInfo']['file_name'];
                            //$fileName = basename($file);
                            $fileNameNoExtension = preg_replace("/\.[^.]+$/", "", $file);

                            // Using substr 
                            $x = substr($file, 0, strrpos($file, '.'));
                            // $posscode = substr(strrchr($x, '-'), 1);
                            // Display the filename
                            //echo $x . "<BR>";
                            $picture_link =  site_url('') . "./uploads/pictures/POS/" . $fileInfo['fileInfo']['file_name'];
                            log_message("error", 'posscode2 #' . $posscode2 . ' is picture_link2 "' . $picture_link . '"');
                            $post_file = array(
                                'poscode' => $posscode,
                                'file_type' => $fileInfo['fileInfo']['file_type'],
                                'filename' => $fileNameNoExtension,
                                'attachment_file' => site_url('') . "./uploads/pictures/POS/" . $fileInfo['fileInfo']['file_name'],
                                'dateadded' => date('Y-m-d H:i:s')
                            );
                            $this->db->insert('pos_attachment', $post_file);
                            $poscodeql = $this->db->distinct()->select('picture_link', 'qty', 're_order_level', 'pos_desc', 'poscode')->from('pos_item')->where('poscode', $posscode)->get();
                            if ($poscodeql->num_rows() > 0) {
                                $sql = 'UPDATE  pos_item   SET picture_link=' . '"' . $picture_link . '"' . '  WHERE poscode  = ' . '"' . $posscode . '"' . ';';
                                $this->db->query($sql);
                                log_message("error", "update POS image link");
                            }
                        }
                    } else {
                        array_push($errors, array(
                            'error' => $this->upload->display_errors()
                        ));
                    }
                }
            }
        }

        if (count($errors) != 0) {
            //  $data['errors'] = $errors;
            //  $this->load->view('demo/index', $data);
            $this->session->set_flashdata('error', 'Error in File Import - import failed');
            redirect($redirectTo);
        } else {
            $data['fileInfos'] = $fileInfos;
            $this->session->set_flashdata('success', 'Successful uploaded  image(s)');

            redirect($redirectTo1);
        }
    }
    public function ajax_reset_approvers($data_table)
    {
        $rows = $this->input->post('rows');
        log_message("error", 'rows');
        log_message("error", var_export($rows, true));
        foreach ($rows as $row) {
            log_message("error", $row['order'] . $row['id']);
            $req_data = array(

                'order_no' => $row['order'],

            );
            $this->db->update($data_table, $req_data, array('approver_id' => $row['id']));
        }
        echo json_encode(array("status" => TRUE));
    }
  public function getdept($pos_item)
    {
        log_message('error', "chika here".$pos_item);

        $newParameter =  urldecode($pos_item);
        log_message('error', $newParameter);
        $sqla = 'SELECT deptname as value, deptname as name,deptcountry as location  FROM dept
              
       where deptcountry="'.$newParameter.'" order by deptname ASC';
log_message('error', $sqla);
    $jsonData = $this->base_model->run_qry($sqla,'qry'); 
        // var_export($pos_descSql);
        
        $total_data=$jsonData->num_rows();
        $total_data=is_null($total_data)?0:$total_data;
        $main_data=$total_data==0?array():$jsonData->result();

           
            
            $json_data = array(
                // total number of records after searching, if there is no searching then totalFiltered = totalData
                "data"            => $main_data   // total data array
            );
            echo json_encode($json_data);
        
    }
}
