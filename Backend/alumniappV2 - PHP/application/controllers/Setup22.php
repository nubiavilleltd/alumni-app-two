<?php
defined('BASEPATH') or exit('No direct script access allowed');


class Setup extends User_Controller
{
    function __construct()
    {
        parent::__construct();
        $this->data['data_access'] = array("Add", "Edit", "Delete");
      //  if ($this->current_user->user_role !== "Admin" && $this->current_user->user_role !== "Developer") {
        //    redirect('setup/view_users');
       // }
    }

    public function index()
    {
        $this->view_locations();
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
        $this->datatable_script('Setup2', 'asc', 1);
        //  $this->datatable_script($this->data['data_section'],'asc',1);
        $this->render('setup/setup_parameters');
    }

    public function view_locations()
    {
        $this->form_script_modal();
        $this->data['page_title'] = 'Setup Locations';
        $this->data['data_columns'] = '{ "data": "location_id" ,"visible":false},{ "data": "area" },'
            . '{ "data": "region" },{ "data": "market" },{ "data": "inv_share" }';
        $this->data['data_section'] = "Setup";
        $this->data['data_table'] = "locations";
        $this->data['data_key'] = "location_id";
        $this->data['data_editflds'] = '
            $(\'[name="id"]\').val(data.location_id);
            $(\'[name="area"]\').val(data.area);
            $(\'[name="region"]\').val(data.region);
            $(\'[name="market"]\').val(data.market);
            $(\'[name="inv_share"]\').val(data.inv_share);';
        $this->data['mini'] = FALSE;
        $this->datatable_script($this->data['data_section'], 'asc', 1);
        $this->data['market'] = $this->base_model->get_parameters_menu(
            'Markets',
            'market',
            '',
            'Enter Market'
        );
        $this->render('setup/setup_locations_list');
    }

    public function view_products()
    {
        $this->data['page_title'] = 'Setup Products';
        $this->form_script_modal();
        $this->data['data_columns'] = '{ "data": "sku_code" },{ "data": "sku_name" },'
            . '{ "data": "category" },{ "data": "brand" },{ "data": "country" },'
            . '{ "data": "unit_price",
                    "render": function ( data, type, row ) {
                        return format_number(data);
                      }
                }';

        $this->data['data_section'] = "Setup";
        $this->data['data_table'] = "products";
        $this->data['data_key'] = "sku_code";
        $this->data['data_editflds'] = '
            $(\'[name="id"]\').val(data.sku_code);
            $(\'[name="sku_code"]\').val(data.sku_code);
            $(\'[name="sku_name"]\').val(data.sku_name);
            $(\'[name="category"]\').val(data.category);
            $(\'[name="brand"]\').val(data.brand);
            $(\'[name="country"]\').val(data.country);
            $(\'[name="unit_price"]\').val(data.unit_price);';
        $this->data['mini'] = FALSE;
        $this->datatable_script($this->data['data_section'], 'asc', 1);
        $this->data['category'] = $this->base_model->get_parameters_menu(
            'Product Category',
            'category',
            '',
            'Enter Product Category'
        );
        $sql = "SELECT country as 'name',country as 'value' FROM "
            . "country order by country";
        $this->data['country'] = $this->base_model->get_record_menu($sql, 'country', '', 'Enter Market');
        $this->render('setup/setup_products_list');
    }

    public function view_customers()
    {
        $this->data['page_title'] = 'Setup Customers';
        $this->form_script_modal();

        $this->data['data_columns'] = '{ "data": "id" },{ "data": "cust_code" },'
            . '{ "data": "cust_name" },{ "data": "cust_type" },{"data": "cust_category" },{ "data": "location" },{ "data": "region" },'
            . '{ "data": "market" },{ "data": "phone_no" },{ "data": "alt_phone" },{ "data": "cust_addr" },'
            . '{"data": "contact_person" },{ "data": "designation" },{"data": "distributor" },{"data": "email" },'
            . '{ "data": "cust_id","visible":false }';
        $this->data['data_section'] = "Setup";
        $this->data['data_table'] = "customers";
        $this->data['data_key'] = "cust_id";
        $this->data['data_editflds'] = '
            $(\'[name="id"]\').val(data.cust_id);
            $(\'[name="cust_code"]\').val(data.cust_code);
            $(\'[name="cust_name"]\').val(data.cust_name);
            $(\'[name="cust_type"]\').val(data.cust_type);
            $(\'[name="cust_category"]\').val(data.cust_category);            
            $(\'[name="cust_addr"]\').val(data.cust_addr);
            $(\'[name="location"]\').val(data.location);
		  $(\'[name="region"]\').val(data.region);
            $(\'[name="distributor"]\').val(data.distributor);
            $(\'[name="market"]\').val(data.market);
            $(\'[name="phone_no"]\').val(data.phone_no);
            $(\'[name="alt_phone"]\').val(data.alt_phone);            
            $(\'[name="email"]\').val(data.email);            
            $(\'[name="contact_person"]\').val(data.contact_person);
            $(\'[name="designation"]\').val(data.designation);
            $(\'[name="contact_via"]\').val([data.contact_via]);';

        $this->data['mini'] = TRUE;

        $this->datatable_script($this->data['data_section'], 'asc', 2);

        $this->data['cust_category'] = $this->base_model->get_parameters_menu(
            'Customer Category',
            'cust_category',
            '',
            'Enter Customer Category'
        );
        $this->data['cust_type'] = $this->base_model->get_parameters_menu(
            'Customer Type',
            'cust_type',
            '',
            'Enter Customer Type'
        );
        $sql = "SELECT locations.area as 'name',locations.area as 'value' FROM "
            . "locations order by area";
        $this->data['location'] = $this->base_model->get_record_menu($sql, 'location', '', 'Enter Location');
        $sql = "SELECT country as 'name',country as 'value' FROM "
            . "country order by country";
        $this->data['market'] = $this->base_model->get_record_menu($sql, 'market', '', 'Enter Market');
        $this->render('setup/setup_customers_list');
    }

    public function view_distributor_rewards()
    {
        $this->data['page_title'] = 'Setup Distributor Rewards List';
        $this->form_script_modal();


        $this->data['data_columns'] = '{ "data": "distributor_id" ,"visible":false},{ "data": "distributor" },'

            . '{ "data": "r100","render": function ( data, type, row ) {return format_number(data);}},'
            . '{ "data": "DSS","render": function ( data, type, row ) {return format_number(data);}},'
            
            . '{ "data": "Base_Rate_RKS","render": function ( data, type, row ) {return format_number(data);}},'
            . '{ "data": "Base_Rate_B&HSF","render": function ( data, type, row ) {return format_number(data);}},'
            . '{ "data": "EOY","render": function ( data, type, row ) {return format_number(data);}},'
            . '{ "data": "consistency","render": function ( data, type, row ) {return format_number(data);}}';
        $this->data['data_section'] = "Setup";
        $this->data['data_table'] = "reward_distributor";
        $this->data['data_key'] = "distributor_id";
        $this->data['data_editflds'] = '
            $(\'[name="id"]\').val(data.type_id);
            $(\'[name="distributor"]\').val(data.distributor);
            $(\'[name="r100"]\').val(data.r100);
            $(\'[name="DSS"]\').val(data.DSS);
            $(\'[name="QTR"]\').val(data.QTR);
            $(\'[name="Base_Rate_RKS"]\').val(data.Base_Rate_RKS);                  
            $(\'[name="Base_Rate_B&HSF"]\').val(data.Base_Rate_B&HSF);
			$(\'[name="EOY"]\').val(data.EOY);
            $(\'[name="consistency"]\').val(data.consistency);
              
            ';
        $this->data['mini'] = TRUE;
        $this->datatable_script($this->data['data_section'], 'asc', 0);
        $this->data['distributor'] = $this->base_model->get_parameters_menu(
            'distributor',
            'distributor',
            '',
            'Enter distributor'
        );



        $this->render('setup/setup_distributor_list');
    }
    public function view_rewards()
    {
        $this->data['page_title'] = 'Setup Rewards List';
        $this->form_script_modal();
        $this->data['data_columns'] = '{ "data": "type_id","visible":false },{ "data": "sku_code" },{ "data": "sku_name" },'
            . '{ "data": "r70","render": function ( data, type, row ) {return format_number(data);}},'
            . '{ "data": "r80","render": function ( data, type, row ) {return format_number(data);}},'
            . '{ "data": "r90","render": function ( data, type, row ) {return format_number(data);}},'
            . '{ "data": "r100","render": function ( data, type, row ) {return format_number(data);}},'
            . '{ "data": "growth","render": function ( data, type, row ) {return format_number(data);}},'
            . '{ "data": "sell_out","render": function ( data, type, row ) {return format_number(data);}},'
            . '{ "data": "redx","render": function ( data, type, row ) {return format_number(data);}},'
            . '{ "data": "focus_brand","render": function ( data, type, row ) {return format_number(data);}},'
            . '{ "data": "hockey_stick","render": function ( data, type, row ) {return format_number(data);}},'
            . '{ "data": "participate","render": function ( data, type, row ) {return format_number(data);}},'
            . '{ "data": "quarterly","render": function ( data, type, row ) {return format_number(data);}},'
            . '{ "data": "prem_tact","render": function ( data, type, row ) {return format_number(data);}},'
            . '{ "data": "cash_purchase","render": function ( data, type, row ) {return format_number(data);}},'

            . '{ "data": "early_credit","render": function ( data, type, row ) {return format_number(data);}},'
            . '{ "data": "market" },{ "data": "category" },{ "data": "band" }';
        $this->data['data_section'] = "Setup";
        $this->data['data_table'] = "rewards";
        $this->data['data_key'] = "type_id";
        $this->data['data_editflds'] = '
            $(\'[name="id"]\').val(data.type_id);
            $(\'[name="sku_code"]\').val(data.sku_code);
            $(\'[name="sku_name"]\').val(data.sku_name);
            $(\'[name="price"]\').val(data.price);
            $(\'[name="r70"]\').val(data.r70);
            $(\'[name="r80"]\').val(data.r80);
            $(\'[name="r90"]\').val(data.r90);
            $(\'[name="r100"]\').val(data.r100);
            $(\'[name="growth"]\').val(data.growth);         
            $(\'[name="sell_out"]\').val(data.sell_out);
            $(\'[name="redx"]\').val(data.redx);
            $(\'[name="focus_brand"]\').val(data.focus_brand);
            $(\'[name="hockey_stick"]\').val(data.hockey_stick);
            $(\'[name="participate"]\').val(data.participate);
            $(\'[name="quarterly"]\').val(data.quarterly);
            $(\'[name="premium_drive"]\').val(data.prem_tact);
            $(\'[name="credit_management"]\').val(data.early_credit);
			$(\'[name="cash_purchase"]\').val(data.cash_purchase);
            $(\'[name="market"]\').val(data.market);
            $(\'[name="category"]\').val(data.category);
            $(\'[name="band"]\').val(data.band);              
            ';
        $this->data['mini'] = TRUE;
        $this->datatable_script($this->data['data_section'], 'asc', 0);
        $this->data['band'] = $this->base_model->get_parameters_menu(
            'Customer Category',
            'band',
            '',
            'Enter Customer Category'
        );
        $this->data['category'] = $this->base_model->get_parameters_menu(
            'Customer Type',
            'category',
            '',
            'Enter Customer Type'
        );
        $this->data['market'] = $this->base_model->get_parameters_menu(
            'Markets',
            'market',
            '',
            'Enter Market'
        );
        $sql = "SELECT sku_name as name, sku_name as value FROM products order by sku_name";
        $this->data['sku_name'] = $this->base_model->get_record_menu($sql, 'sku_name', '', 'Choose SKU Name', '', '');
        $sql = "SELECT sku_code as name, sku_code as value FROM products order by sku_code";
        $this->data['sku_code'] = $this->base_model->get_record_menu($sql, 'sku_code', '', 'Choose SKU Code', '', '');
        $this->render('setup/setup_rewards_list');
    }
    public function view_key_acct_rewards()
    {
        $this->data['page_title'] = 'Setup Key Acct Rewards List';
        $this->form_script_modal();
        $this->data['data_columns'] = '{ "data": "type_id" ,"visible":false},{ "data": "sku_code" },{ "data": "sku_name" },'

            . '{ "data": "base","render": function ( data, type, row ) {return format_number(data);}},'
            . '{ "data": "growth","render": function ( data, type, row ) {return format_number(data);}},'
            . '{ "data": "premium","render": function ( data, type, row ) {return format_number(data);}},'
            . '{ "data": "focus_brand","render": function ( data, type, row ) {return format_number(data);}},'
            . '{ "data": "unit_price","render": function ( data, type, row ) {return format_number(data);}}';
        $this->data['data_section'] = "Setup";
        $this->data['data_table'] = "key_acct_rewards";
        $this->data['data_key'] = "type_id";
        $this->data['data_editflds'] = '
            $(\'[name="id"]\').val(data.type_id);
            $(\'[name="sku_code"]\').val(data.sku_code);
            $(\'[name="sku_name"]\').val(data.sku_name);
            $(\'[name="price"]\').val(data.price);
            $(\'[name="base"]\').val(data.base);
            $(\'[name="growth"]\').val(data.growth);         
            $(\'[name="premium"]\').val(data.premium);
            $(\'[name="focus_brand"]\').val(data.focus_brand);
            $(\'[name="market"]\').val(data.market);           
            ';
        $this->data['mini'] = TRUE;
        $this->datatable_script($this->data['data_section'], 'asc', 0);
        $this->data['market'] = $this->base_model->get_parameters_menu(
            'Markets',
            'market',
            '',
            'Enter Market'
        );
        $sql = "SELECT sku_name as name, sku_name as value FROM products order by sku_name";
        $this->data['sku_name'] = $this->base_model->get_record_menu($sql, 'sku_name', '', 'Choose SKU Name', '', '');
        $sql = "SELECT sku_code as name, sku_code as value FROM products order by sku_code";
        $this->data['sku_code'] = $this->base_model->get_record_menu($sql, 'sku_code', '', 'Choose SKU Code', '', '');
        $this->render('setup/setup_key_acct_rewards_list');
    }

    public function view_wam_rewards()
    {
        $this->data['page_title'] = 'Setup WAM Rewards List';
        $this->form_script_modal();


        $this->data['data_columns'] = '{ "data": "type_id" ,"visible":false},{ "data": "sku_code" },{ "data": "sku_name" },'
            . '{ "data": "band" },{ "data": "market" },{ "data": "region" },{ "data": "category" },'
            . '{ "data": "weekly","render": function ( data, type, row ) {return format_number(data);}},'
            . '{ "data": "r90","render": function ( data, type, row ) {return format_number(data);}},'
            . '{ "data": "r100","render": function ( data, type, row ) {return format_number(data);}},'
            . '{ "data": "growth","render": function ( data, type, row ) {return format_number(data);}},'
            . '{ "data": "sell_out","render": function ( data, type, row ) {return format_number(data);}}';
        $this->data['data_section'] = "Setup";
        $this->data['data_table'] = "rewards_wam";
        $this->data['data_key'] = "type_id";
        $this->data['data_editflds'] = '
            $(\'[name="id"]\').val(data.type_id);
            $(\'[name="sku_code"]\').val(data.sku_code);
            $(\'[name="sku_name"]\').val(data.sku_name);
            $(\'[name="band"]\').val(data.band);
            $(\'[name="market"]\').val(data.market);
            $(\'[name="region"]\').val(data.region);                  
            $(\'[name="category"]\').val(data.category);
			$(\'[name="weekly"]\').val(data.weekly);
            $(\'[name="r90"]\').val(data.r90);
            $(\'[name="r100"]\').val(data.r100);
            $(\'[name="growth"]\').val(data.growth);         
            $(\'[name="sellout"]\').val(data.sell_out);    
            ';
        $this->data['mini'] = TRUE;
        $this->datatable_script($this->data['data_section'], 'asc', 0);
        $this->data['market'] = $this->base_model->get_parameters_menu(
            'Markets',
            'market',
            '',
            'Enter Market'
        );
        $this->data['region'] = $this->base_model->get_parameters_menu(
            'Region',
            'region',
            '',
            'Enter Region'
        );
        $this->data['band'] = $this->base_model->get_parameters_menu(
            'Customer Category',
            'band',
            '',
            'Enter Band'
        );
        $this->data['category'] = $this->base_model->get_parameters_menu(
            'Customer Type',
            'category',
            '',
            'Enter Customer Type'
        );
        $sql = "SELECT sku_name as name, sku_name as value FROM products order by sku_name";
        $this->data['sku_name'] = $this->base_model->get_record_menu($sql, 'sku_name', '', 'Choose SKU Name', '', '');
        $sql = "SELECT sku_code as name, sku_code as value FROM products order by sku_code";
        $this->data['sku_code'] = $this->base_model->get_record_menu($sql, 'sku_code', '', 'Choose SKU Code', '', '');
        $this->render('setup/setup_wam_rewards_list');
    }
    public function view_users()
    {
        $this->data['page_title'] = 'Setup Users';
        $this->form_script_modal();
        $this->data['data_columns'] = '{ "data": "id" , "visible":false},{ "data": "fullname" },{ "data": "email" },{ "data": "designation" },{ "data": "company" },{ "data": "region" },{ "data": "area" },'
            . ' { "data": "user_role" },'
            . '{ "data": "active",
                        "render": function ( data, type, row ) {
                            return format_status(data);
                        }
                    }';

        $this->data['data_section'] = "Setup";
        $this->data['data_table'] = "users";
        $this->data['data_key'] = "id";
        $this->data['data_editflds'] = '
            $(\'[name="id"]\').val(data.id);
            $(\'[name="email"]\').val(data.email);
            $(\'[name="fullname"]\').val(data.fullname);
            $(\'[name="last_name"]\').val(data.last_name);
            $(\'[name="phone"]\').val(data.phone);            
            $(\'[name="designation"]\').val(data.designation);
            $(\'[name="user_role"]\').val(data.user_role);
            $(\'[name="company"]\').val(data.company);
             $(\'[name="region"]\').val(data.region);
             $(\'[name="area"]\').val(data.area);
            if(data.active==1){
                $(\'input:radio[id=Active]\').prop(\'checked\', true);
               // $(\'[name="user_status"]\').val(["Active"]);
            }else {
                $(\'input:radio[id=Inactive]\').prop(\'checked\', true);
               // $(\'[name="user_status"]\').val(["Inactive"]);
            }

         
            ';

        $this->data['mini'] = TRUE;
        $this->datatable_script($this->data['data_section'], 'asc', 1);

        $this->data['user_role'] = $this->base_model->get_parameters_menu(
            'User Roles',
            'user_role',
            '',
            'Enter User Role'
        );
        $this->data['company'] = $this->base_model->get_parameters_menu(
            'Markets',
            'company',
            '',
            'Enter Market'
        );
        $this->data['region'] = $this->base_model->get_parameters_menu(
            'Region',
            'region',
            '',
            'Enter Region'
        );
        $this->render('setup/setup_users_list');
    }

    public function view_rules()
    {
        $this->data['page_title'] = 'Setup Rules';
        $this->data['data_columns'] = '{ "data": "title" },{ "data": "campaign" },'
            . '{ "data": "market" },{ "data": "rule_status" },{ "data": "rule_id", "visible":false}';
        $this->data['data_section'] = "Setup";
        $this->data['data_table'] = "reward_rules";
        $this->data['data_key'] = "rule_id";
        $this->data['data_access'] = ['no-edit'];
        $this->data['mini'] = FALSE;
        $this->datatable_script($this->data['data_section'], 'asc', 1);
        $this->render('setup/setup_rules_list');
    }

    public function add_rule($action = null, $id = null)
    {
        $this->form_script();
        if ($action === 'edit') {
            $this->data['page_title'] = 'Edit Reward Rule';
            $this->data['form_action'] = 'edit';
            $this->data['main_data'] = is_null($id) ? array() :
                $this->base_model->get_single_record($id, 'rule_id', 'reward_rules');
        } else {
            $this->data['page_title'] = 'Add Reward Rule';
            $this->data['form_action'] = 'add';
        }
        $this->form_validation->set_rules('title', 'Title', 'trim|required');
        $this->form_validation->set_rules('campaign', 'Campaign', 'trim|required');
        $this->form_validation->set_rules('cust_types[]', 'cust_types', 'trim|required');
        $this->form_validation->set_rules('market', 'Market', 'trim');
        $this->form_validation->set_rules('rule_status', 'rule_status', 'trim|required');

        if ($this->form_validation->run() === true) {
            $id = $this->input->post('id');
            $upd_data = array(
                'title'     => $this->input->post('title'),
                'campaign'   => $this->input->post('campaign'),
                'market'   => $this->input->post('market'),
                'cust_types'   => implode(',', $this->input->post('cust_types[]')),
                'rule_status'   => $this->input->post('rule_status'),
                'last_updated'   => date('Y-m-d H:i:s')
            );
            if ($action == "add") {
                $id = $this->base_model->insert($upd_data, 'reward_rules');
            }
            if ($action == "edit") {
                $this->base_model->update(
                    $upd_data,
                    $id,
                    'reward_rules',
                    array('rule_id' => $id)
                );
            }
            redirect('setup/view_rules');
        } else {
            $this->data['campaign'] = $this->base_model->get_parameters_menu(
                'Campaign Types',
                'campaign',
                $this->data['main_data']->campaign,
                'Campaign Type'
            );
            $this->data['cust_types'] = $this->base_model->get_parameters_menu(
                'Customer Type',
                'cust_types[]',
                $this->data['main_data']->cust_types,
                'Customer Types',
                '',
                'Yes'
            );
            $this->data['market'] = $this->base_model->get_parameters_menu(
                'Markets',
                'market',
                $this->data['main_data']->market,
                'Market'
            );
            $this->data['currency'] = $this->base_model->get_parameters_menu(
                'Currency',
                'currency',
                $this->data['main_data']->currency,
                'Currency'
            );
            $this->render('setup/setup_add_rule');
        }
    }

    public function view_workflow()
    {
        $this->data['page_title'] = 'Setup Workflow';
        $this->data['data_columns'] = '{ "data": "process_id","visible":false },'
            . '{ "data": "request_title" },{ "data": "request_type" },'
            . '{ "data": "approvers" }';
        $this->data['data_section'] = "Setup";
        $this->data['data_table'] = "workflow";
        $this->data['data_key'] = "process_id";
        $this->data['data_access'] = ['no-edit'];
        $this->data['mini'] = FALSE;
        $this->datatable_script($this->data['data_section'], 'asc', 1);
        $this->render('setup/setup_workflow_list');
    }


    public function setup_credit()
    {
        $this->data['page_title'] = 'Setup Regional Credits';
        $this->data['data_columns'] = '{ "data": "sno","visible":false },'
            . '{ "data": "region" },{ "data": "contribution" },{ "data": "creditvol" },'
            . '{ "data": "creditalloc" },{ "data": "outcredit" },{ "data": "balcreditalloc" },'
            . '{ "data": "custype" },{ "data": "periodval" }';
        $this->data['data_section'] = "Setup";
        $this->data['data_table'] = "setup_credit";
        $this->data['data_key'] = "sno";

        $this->data['data_obj'] = "Setup Credits";
        $this->data['data_headers'] = '<th>SNo</th><th>Region</th><th>Value Contribution</th>'
            . '<th>Exp. Credit Vol (cases)</th><th>Credit Allocation</th>'
            . '<th>Outstanding Credit</th><th>Month\'s Credit Allocation</th>'
            . '<th>Customer Type</th><th>Period</th><th></th><th></th>';
        $this->data['data_title'] = '<h5>Setup Regional Credits</h5> ';
        $this->data['mini'] = FALSE;

        $this->datatable_script('Setup2', 'asc', 0);
        $this->render('setup/setup_details');
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
        $this->form_validation->set_rules('process_name', 'Process Name', 'trim|required');
        $this->form_validation->set_rules('market', 'Market', 'trim|required');
        $this->form_validation->set_rules('wait_time', 'Wait Time', 'trim|required');
        $this->form_validation->set_rules('expiration_action', 'Action Type', 'trim|required');


        if ($this->form_validation->run() === true) {
            $id = $this->input->post('id');
            $upd_data = array(
                'process_name'     => $this->input->post('process_name'),
                'market'   => $this->input->post('market'),
                'wait_time'   => $this->input->post('wait_time'),
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
            $this->data['market'] = $this->base_model->get_parameters_menu(
                'Markets',
                'market',
                $this->data['main_data']->market,
                'Market'
            );
            $this->render('setup/setup_add_workflow');
        }
    }

    public function view_workflow_details($id)
    {
        $this->data['page_title'] = 'Workflow Details - ' . $id;
        $this->data['data_columns'] = '{"data": "approver_id","visible": false }, {  "data": "order_no" }, '
            . '{"data": "approver_from" },{"data": "approver_name" },{ "data": "appr_function" }';

        $this->data['data_section'] = "Setup";
        $this->data['data_var'] = $id;
        $this->data['data_table'] = "workflow_approvers";
        $this->data['data_key'] = "approver_id";

        $this->data['data_editflds'] = '
            $(\'[name="id"]\').val(data.approver_id);
            $(\'[name="approver_from"]\').val(data.approver_from);
            $(\'[name="appr_name"]\').val(data.appr_name);
            $(\'[name="appr_function"]\').val(data.appr_function);
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
        $sql = "SELECT users.id as value, concat(users.first_name,' ',users.last_name) as 'name' "
            . "FROM users where active=1 and user_role='Approver' order by users.first_name asc";
        $this->data['market'] = $this->base_model->get_parameters_menu(
            'Markets',
            'market',
            $this->data['main_data']->market,
            'Enter Market'
        );
        $this->data['approver_name'] = $this->base_model->get_record_menu($sql, 'appr_name', '', 'Select Approver');
        $this->data['alt_approver'] = $this->base_model->get_record_menu($sql, 'alt_approvers', '', 'Select alt_approvers', '', 'Yes');
        $this->data['mini'] = FALSE;
        $this->datatable_script('Setup2', 'asc', 1);
        //  $this->datatable_script($this->data['data_section'],'asc',1); 
        $this->render('setup/setup_workflow_details');
    }

    public function import($data_type = "locations")
    {
        $filename = $this->upload_files();
        $redirectTo = 'setup/view_' . $data_type;
        $rowData = array();
        $import_data = array();
        $db_name = $data_type;

        switch ($data_type) {
            case "locations":
                $db_flds = array('area', 'region', 'market', 'inv_share');
                $update_rule = "region=values(region),market=values(market),inv_share=values(inv_share)";
                break;
            case "products":
                $db_flds = array('sku_code', 'sku_name', 'category', 'brand', 'country', 'unit_price');
                $update_rule = "sku_name=values(sku_name),category=values(category),brand=values(brand)," .
                    "country=values(country),unit_price=values(unit_price)";
                break;
            case "customers":
                $db_flds = array(
                    'id',
                    'cust_code',
                    'cust_name',
                    'cust_type',
                    'cust_category',
                    'location',
                    'region',
                    'market',
                    'phone_no',
                    'alt_phone',
                    'cust_addr',
                    'contact_person',
                    'designation',
                    'distributor',
                    'email'
                );
                $update_rule = "cust_code=values(cust_code),cust_name=values(cust_name),cust_type=values(cust_type)," .
                    "cust_type=values(cust_type),cust_category=values(cust_category),location=values(location),region=values(region)," .
                    "market=values(market),phone_no=values(phone_no),alt_phone=values(alt_phone),cust_addr=values(cust_addr)," .
                    "contact_person=values(contact_person),designation=values(designation),distributor=values(distributor)," .
                    "email=values(email)";
                break;
            case "users":
                $db_flds = array('fullname', 'email',  'designation', 'company','region','area', 'user_role','active');
                break;
            case "rewards":
                $db_flds = array(
                    'type_id',
                    'sku_code',
                    'sku_name',
                    'r70',
                    'r80',
                    'r90',
                    'r100',
                    'growth',
                    'sell_out',
                    'redx',
                    'focus_brand',
                    'hockey_stick',
                    'participate',
                    'quarterly',
                    'prem_tact',
                    'cash_purchase',
                    'early_credit',
                    'market',
                    'category',
                    'band'
                );
                $update_rule = "sku_code=values(sku_code),sku_name=values(sku_name),r70=values(r70),r80=values(r80),r90=values(r90),r100=values(r100)," .
                    "growth=values(growth),sell_out=values(sell_out),redx=values(redx),participate=values(participate)," .
                    "focus_brand=values(focus_brand),hockey_stick=values(hockey_stick),quarterly=values(quarterly),prem_tact=values(prem_tact),cash_purchase=values(cash_purchase),early_credit=values(early_credit)," .
                    "market=values(market),category=values(category),band=values(band)";
                break;

            case "key_acct_rewards":
                $db_flds = array('type_id', 'sku_code', 'sku_name', 'base', 'growth', 'premium', 'focus_brand', 'market');
                $update_rule = "sku_name=values(sku_name),base=values(base),growth=values(growth)," .
                    "premium=values(premium),focus_brand=values(focus_brand),market=values(market)";
                break;
            case "rewards_wam":
                $redirectTo = 'setup/view_wam_rewards';
                $db_flds = array('type_id', 'sku_code', 'sku_name', 'band', 'market', 'region', 'category', 'weekly', 'r90', 'r100', 'growth', 'sell_out');
                $update_rule = "sku_code=values(sku_code),sku_name=values(sku_name),band=values(band),market=values(market),region=values(region)," .
                    "category=values(category),weekly=values(weekly),r90=values(r90),r100=values(r100),growth=values(growth),sell_out=values(sell_out)";
                break;
            case "reward_distributor":
                $redirectTo = 'setup/view_distributor_rewards';
                $db_flds = array('distributor_id', 'distributor', 'r100', 'DSS', 'Base_Rate_RKS', '`Base_Rate_B&HSF`', 'EOY', 'consistency');
                $update_rule = "distributor=values(distributor),r100=values(r100), DSS=values(DSS),  Base_Rate_RKS=values(Base_Rate_RKS), " .
                    "`Base_Rate_B&HSF`=values(`Base_Rate_B&HSF`), EOY=values(EOY), consistency=values(consistency)";
                break;
        }


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

        if ($data_type == 'users') {
            // Initialize field names outside the loop
            $db_flds = array('fullname', 'email', 'designation', 'company', 'region','area', 'user_role','active');
            
            for ($row = 2; $row <= $highestRow; $row++) {
                $username = $this->get_id();
                $password = 'password';
                $group_ids = array(2, 3); // Assuming groups 2 and 3
                
                $import_data = array(); // Initialize import data array
                
                // Iterate over each field and populate the $import_data array
                foreach ($db_flds as $col => $flds_val) {
                    if ($col == 1) {
                        // If this is the email column, set the $email variable
                        $email = isset($rowData[$row - 2][$col]) ? $rowData[$row - 2][$col] : '';
                    }
                    // Assign the data to the respective field in $import_data
                    $import_data[$flds_val] = isset($rowData[$row - 2][$col]) ? $rowData[$row - 2][$col] : '';
                }
                
                // Set the 'active' field
              
        
                // Debugging - Log the import data to see the structure
                log_message('error', var_export($import_data, true));
                log_message('error','EMAIL'. var_export($email, true));

                $email2 = strtolower(trim($email));
                // Register the user
                $identity = $this->ion_auth->where('email', $email)->users()->row();
               // log_message('error','EMAIL2'. var_export($identity, true));
               $activeStatus = $import_data['active']==='Active'?1:0;
               $profile_status = 'Yes';
               $import_data["active"] = $activeStatus;
               $import_data["profile_status"] = $profile_status;
			if ($identity) {
                log_message('error','EMAIL2'. var_export($email, true));
                $this->db->update('users', $import_data, array('email' => $email));				
			}
                else{
                    
                $user_id = $this->ion_auth->register($username, $password, $email, $import_data, $group_ids);
                }
            }
        }
        else {
            $table_data = array();
            $cnt = 0;

            for ($row = 2; $row <= $highestRow; $row++) {
                $col = 0;
                $import_data = array();
                foreach ($db_flds as $flds_val) {
                    $row_val = trim($rowData[$row - 2][$col]);

                    // Remove commas from the value
                    $row_val = str_replace(',', '', $row_val);

                    // Escape single quotes for database insertion
                    $row_val = $this->db->escape($row_val);

                    // If the value is empty or '-', assign NULL, else enclose it with single quotes
                    $import_data[$flds_val] = empty($row_val) || $row_val === '-' ? 'NULL' : $row_val;

                    $col++;
                }

                // Build the array of values for the row
                $row_values = array_values($import_data);

                // Combine the values into a string, with each value enclosed in single quotes
                $table_data[$cnt] = '(' . implode(',', $row_values) . ')';
                $cnt++;
            }

            // Construct the SQL query
            $str = " INSERT INTO $data_type (" . implode(',', $db_flds) . ") VALUES " . implode(",", $table_data) . " " . " ON DUPLICATE KEY UPDATE $update_rule";

            // Execute the query
            $this->db->query($str);

            //$this->db->insert_batch($db_name, $table_data);               
            //      // Remove commas from values in $table_data array
            //        if($data_type=='rewards_wam') {

            //         $cleaned_table_data = array_map(function ($value) {
            //             // If the value contains commas, remove them
            //             return strpos($value, ',') !== false ? "'" . str_replace(',', '', $value) . "'" : $value;
            //         }, $table_data);

            //         $str = "INSERT INTO $data_type (" . implode(',', $db_flds) . ") VALUES (" . implode(",", $cleaned_table_data) . ") "
            //             . " ON DUPLICATE KEY UPDATE $update_rule";
            //         $this->db->query($str);

            //        }else{      
            //    $str = " INSERT INTO $data_type (".implode(',',$db_flds).") Values ".implode (",",$table_data)." " 
            //    . " ON DUPLICATE KEY UPDATE $update_rule";
            //        }
            // Remove commas from values in $table_data array
            // Remove commas from values in $table_data array



            // $this->db->query($str);            
        }

        if ($data_type == 'customers') {
            $this->update_cust_id();
        }
        redirect($redirectTo);
    }

    private function update_cust_id()
    {
        $this->base_model->run_qry("CALL UPDATE_CUSTOMER_ID()", 'run');
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
            }
            if ($action == "update") {
                $this->base_model->update(
                    $upd_data,
                    $id,
                    $data_table,
                    array($data_key => $id)
                );
            }
            echo json_encode(array("status" => TRUE));
        } else {
            header('Content-Type: application/json');
            echo json_encode(validation_errors());
        }
    }

    private function update_locations($action, $data_table, $data_key)
    {

        $this->form_validation->set_rules('area', 'Area', 'trim|required');
        $this->form_validation->set_rules('region', 'Region', 'trim|required');
        $this->form_validation->set_rules('market', 'Market', 'trim|required');
        $this->form_validation->set_rules('inv_share', 'Inventory Share', 'trim|required');
        if ($this->form_validation->run() === true) {
            $id = $this->input->post('id');
            $upd_data = array(
                'area'     => $this->input->post('area'),
                'region'   => $this->input->post('region'),
                'market'   => $this->input->post('market'),
                'inv_share'   => $this->input->post('inv_share')
            );
            if ($action == "add") {
                $this->base_model->insert($upd_data, $data_table);
            }
            if ($action == "update") {
                $this->base_model->update(
                    $upd_data,
                    $id,
                    $data_table,
                    array($data_key => $id)
                );
            }
            echo json_encode(array("status" => TRUE));
        } else {
            header('Content-Type: application/json');
            echo json_encode(validation_errors());
        }
    }

    private function update_products($action, $data_table, $data_key)
    {

        $this->form_validation->set_rules('sku_code', 'sku_code', 'trim|required');
        $this->form_validation->set_rules('sku_name', 'sku_name', 'trim|required');
        $this->form_validation->set_rules('category', 'category', 'trim');
        $this->form_validation->set_rules('brand', 'brand', 'trim');
        $this->form_validation->set_rules('country', 'country', 'trim');
        $this->form_validation->set_rules('unit_price', 'unit_price', 'trim');
        if ($this->form_validation->run() === true) {
            $id = $this->input->post('id');
            $temp = $this->input->post('unit_price');
            $temp = empty($temp) ? NULL : $temp;
            $upd_data = array(
                'sku_code'     => $this->input->post('sku_code'),
                'sku_name'   => $this->input->post('sku_name'),
                'category'   => $this->input->post('category'),
                'brand'   => $this->input->post('brand'),
                'country'   => $this->input->post('country'),
                'unit_price'   => $temp
            );
            if ($action == "add") {
                $this->base_model->insert($upd_data, $data_table);
            }
            if ($action == "update") {
                $this->base_model->update(
                    $upd_data,
                    $id,
                    $data_table,
                    array($data_key => $id)
                );
            }
            echo json_encode(array("status" => TRUE));
        } else {
            header('Content-Type: application/json');
            echo json_encode(validation_errors());
        }
    }

    private function update_customers($action, $data_table, $data_key)
    {
        $this->form_validation->set_error_delimiters('<div class="errorMsg">', '</div>');
        $this->form_validation->set_rules('cust_name', 'cust_name', 'trim|required');
        $this->form_validation->set_rules('cust_type', 'cust_type', 'trim');
        $this->form_validation->set_rules('cust_category', 'cust_category', 'trim');
        $this->form_validation->set_rules('cust_addr', 'cust_addr', 'trim');
        $this->form_validation->set_rules('distributor', 'distributor', 'trim');
        $this->form_validation->set_rules('location', 'location', 'trim|required');
        $this->form_validation->set_rules('region', 'region', 'trim|required');
        $this->form_validation->set_rules('market', 'market', 'trim|required');
        $this->form_validation->set_rules('phone_no', 'phone_no', 'trim');
        $this->form_validation->set_rules('alt_phone', 'alt_phone', 'trim');
        $this->form_validation->set_rules('contact_person', 'contact_person', 'trim');
        $this->form_validation->set_rules('designation', 'designation', 'trim');
        $this->form_validation->set_rules('email', 'email', 'trim');
        $this->form_validation->set_rules('contact_via', 'contact_via', 'trim');


        if ($this->form_validation->run() === true) {
            $id = $this->input->post('id');
            $upd_data = array(
                'cust_code'   => $this->input->post('cust_code'),
                'cust_name'   => $this->input->post('cust_name'),
                'cust_type'   => $this->input->post('cust_type'),
                'cust_category' => $this->input->post('cust_category'),
                'cust_addr'   => $this->input->post('cust_addr'),
                'location'   => $this->input->post('location'),
                'region'   => $this->input->post('region'),
                'market'   => $this->input->post('market'),
                'distributor'   => $this->input->post('distributor'),
                'phone_no'   => $this->input->post('phone_no'),
                'alt_phone'   => $this->input->post('alt_phone'),
                'email' => $this->input->post('email'),
                'contact_person' => $this->input->post('contact_person'),
                'designation'   => $this->input->post('designation'),
                'contact_via'   => $this->input->post('contact_via')
            );
            if ($action == "add") {
                $this->base_model->insert($upd_data, $data_table);
            }
            if ($action == "update") {
                $this->base_model->update(
                    $upd_data,
                    $id,
                    $data_table,
                    array($data_key => $id)
                );
            }
            echo json_encode(array("status" => TRUE));
        } else {
            header('Content-Type: application/json');
            echo json_encode(validation_errors());
        }
    }

    private function update_wam_rewards($action, $data_table, $data_key)
    {
        $this->form_validation->set_error_delimiters('<div class="errorMsg">', '</div>');
        $this->form_validation->set_rules('sku_code', 'sku_code', 'trim|required');
        $this->form_validation->set_rules('sku_name', 'sku_name', 'trim');
        $this->form_validation->set_rules('band', 'band', 'trim');
        $this->form_validation->set_rules('market', 'market', 'trim');
        $this->form_validation->set_rules('category', 'category', 'trim');
        $this->form_validation->set_rules('weekly', 'weekly', 'trim|required');
        $this->form_validation->set_rules('region', 'region', 'trim|required');
        $this->form_validation->set_rules('market', 'market', 'trim|required');
        $this->form_validation->set_rules('r90', 'r90', 'trim');
        $this->form_validation->set_rules('r100', 'r100', 'trim');
        $this->form_validation->set_rules('growth', 'growth', 'trim');
        $this->form_validation->set_rules('sell_out', 'sell_out');



        if ($this->form_validation->run() === true) {
            $id = $this->input->post('id');
            $upd_data = array(
                'sku_code'   => $this->input->post('sku_code'),
                'sku_name'   => $this->input->post('sku_name'),
                'band'   => $this->input->post('band'),
                'market' => $this->input->post('market'),
                'category'   => $this->input->post('category'),
                'weekly'   => $this->input->post('weekly'),
                'region'   => $this->input->post('region'),
                'r90'   => $this->input->post('r90'),
                'r100'   => $this->input->post('r100'),
                'growth'   => $this->input->post('growth'),
                'sell_out'   => $this->input->post('sellout')

            );
            if ($action == "add") {
                $this->base_model->insert($upd_data, $data_table);
            }
            if ($action == "update") {
                $this->base_model->update(
                    $upd_data,
                    $id,
                    $data_table,
                    array($data_key => $id)
                );
            }
            echo json_encode(array("status" => TRUE));
        } else {
            header('Content-Type: application/json');
            echo json_encode(validation_errors());
        }
    }
    private function update_key_acct_rewards($action, $data_table, $data_key)
    {
        $this->form_validation->set_rules('sku_name', 'sku_name', 'trim');
        $this->form_validation->set_rules('sku_code', 'sku_code', 'trim');
        $this->form_validation->set_rules('base', 'base', 'trim');
        $this->form_validation->set_rules('growth', 'growth', 'trim');
        $this->form_validation->set_rules('premium', 'premium', 'trim');
        $this->form_validation->set_rules('focus_brand', 'focus_brand', 'trim');
        $this->form_validation->set_rules('market', 'market', 'trim');
        if ($this->form_validation->run() === true) {
            $id = $this->input->post('id');
            $base   = $this->input->post('base');
            $base   = empty($base) ? NULL : $base;
            $growth   = $this->input->post('growth');
            $growth   = empty($growth) ? NULL : $growth;
            $premium   = $this->input->post('premium');
            $premium   = empty($premium) ? NULL : $premium;
            $focus_brand   = $this->input->post('focus_brand');
            $focus_brand   = empty($focus_brand) ? NULL : $focus_brand;

            $upd_data = array(
                'sku_name'   => $this->input->post('sku_name'),
                'sku_code'   => $this->input->post('sku_code'),
                'base'   => $base,
                'growth' => $growth,
                'premium' => $premium,
                'focus_brand'   => $focus_brand,
                'market'   => $market
            );
            if ($action == "add") {
                $this->base_model->insert($upd_data, $data_table);
            }
            if ($action == "update") {
                $this->base_model->update(
                    $upd_data,
                    $id,
                    $data_table,
                    array($data_key => $id)
                );
            }
            echo json_encode(array("status" => TRUE));
        } else {
            header('Content-Type: application/json');
            echo json_encode(validation_errors());
        }
    }

    private function update_rewards($action, $data_table, $data_key)
    {
        $this->form_validation->set_rules('band', 'band', 'trim');
        $this->form_validation->set_rules('sku_name', 'sku_name', 'trim');
        $this->form_validation->set_rules('sku_code', 'sku_code', 'trim');
        $this->form_validation->set_rules('r70', 'r70', 'trim');
        $this->form_validation->set_rules('r80', 'r80', 'trim');
        $this->form_validation->set_rules('r90', 'r90', 'trim');
        $this->form_validation->set_rules('r100', 'r100', 'trim');
        $this->form_validation->set_rules('growth', 'growth', 'trim');
        $this->form_validation->set_rules('sell_out', 'sell_out', 'trim');
        $this->form_validation->set_rules('redx', 'redx', 'trim');
        $this->form_validation->set_rules('focus_brand', 'focus brand', 'trim');
        $this->form_validation->set_rules('hockey_stick', 'hockey stick', 'trim');
        $this->form_validation->set_rules('participate', 'participate', 'trim');
        $this->form_validation->set_rules('quarterly', 'quarterly', 'trim');
        $this->form_validation->set_rules('premium_drive', 'premium drive', 'trim');
        $this->form_validation->set_rules('credit_management', 'credit management', 'trim');
        $this->form_validation->set_rules('cash_purchase', 'cash_purchase', 'trim');
        $this->form_validation->set_rules('market', 'market', 'trim');
        $this->form_validation->set_rules('category', 'category', 'trim');
        if ($this->form_validation->run() === true) {
            $id = $this->input->post('id');
            $r70   = $this->input->post('r70');
            $r70   = empty($r70) ? NULL : $r70;
            $r80   = $this->input->post('r80');
            $r80   = empty($r80) ? NULL : $r80;
            $r90   = $this->input->post('r90');
            $r90   = empty($r90) ? NULL : $r90;
            $r100   = $this->input->post('r100');
            $r100   = empty($r100) ? NULL : $r100;
            $growth   = $this->input->post('growth');
            $growth   = empty($growth) ? NULL : $growth;
            $sell_out   = $this->input->post('sell_out');
            $sell_out   = empty($sell_out) ? NULL : $sell_out;
            $redx   = $this->input->post('redx');
            $redx   = empty($redx) ? NULL : $redx;
            $participate   = $this->input->post('participate');
            $participate   = empty($participate) ? NULL : $participate;
            $focus_brand   = $this->input->post('focus_brand');
            $focus_brand   = empty($focus_brand) ? NULL : $focus_brand;
            $hockey_stick   = $this->input->post('hockey_stick');
            $hockey_stick   = empty($hockey_stick) ? NULL : $hockey_stick;
            $quarterly   = $this->input->post('quarterly');
            $quarterly   = empty($quarterly) ? NULL : $quarterly;
            $loyalty   = $this->input->post('loyalty');
            $loyalty   = empty($loyalty) ? NULL : $loyalty;
            $quarterly   = $this->input->post('quarterly');
            $quarterly   = empty($quarterly) ? NULL : $quarterly;
            $loyalty_double   = $this->input->post('loyalty_double');
            $credit_management   = $this->input->post('credit_management');
            $credit_management   = empty($credit_management) ? NULL : $credit_management;
            $premium_drive   = $this->input->post('premium_drive');
            $premium_drive   = empty($premium_drive) ? NULL : $premium_drive;
            $cash_purchase   = $this->input->post('cash_purchase');
            $cash_purchase   = empty($cash_purchase) ? NULL : $cash_purchase;
            $loyalty_double   = empty($loyalty_double) ? NULL : $loyalty_double;
            $hfyr   = $this->input->post('hfyr');
            $hfyr   = empty($hfyr) ? NULL : $hfyr;
            $upd_data = array(
                'band'     => $this->input->post('band'),
                'sku_name'   => $this->input->post('sku_name'),
                'sku_code'   => $this->input->post('sku_code'),
                'r70'   => $r70,
                'r80'   => $r80,
                'r90'   => $r90,
                'r100'   => $r100,
                'growth' => $growth,
                'sell_out' => $sell_out,
                'redx' => $redx,
                'focus_brand'   => $focus_brand,
                'hockey_stick'   => $hockey_stick,
                'participate'   => $participate,
                'quarterly'   => $quarterly,
                // 'loyalty'     => $loyalty,
                // 'loyalty_double'   => $loyalty_double,
                // 'hfyr'   => $hfyr,
                'early_credit'     => $credit_management,
                'prem_tact'   => $premium_drive,
                'cash_purchase'   => $cash_purchase,
                'market'   => $this->input->post('market'),
                'category' => $this->input->post('category')
            );
            if ($action == "add") {
                $this->base_model->insert($upd_data, $data_table);
            }
            if ($action == "update") {
                $this->base_model->update(
                    $upd_data,
                    $id,
                    $data_table,
                    array($data_key => $id)
                );
            }
            echo json_encode(array("status" => TRUE));
        } else {
            header('Content-Type: application/json');
            echo json_encode(validation_errors());
        }
    }

    private function update_users($action, $data_table, $data_key)
    {

        // $this->form_validation->set_rules('first_name', 'First name', 'trim|required');
        // $this->form_validation->set_rules('last_name', 'Last name', 'trim|required');
        if ($action == "add") {
            $this->form_validation->set_rules('email', 'Email', 'trim|required|valid_email|is_unique[users.email]');
            $this->form_validation->set_message('is_unique', 'This %s has been registered. Please use another or login with it');
            $this->form_validation->set_rules('password', 'Password', 'trim|required|min_length[6]');
$this->form_validation->set_rules('email2', 'email confirmation', 'required|matches[email]');
$profile_status="No";
        } else {
            $this->form_validation->set_rules('email', 'email', 'trim');
            $profile_status="Yes";
        }
        $this->form_validation->set_rules('phone', 'phone', 'trim');
        $this->form_validation->set_rules('designation', 'designation', 'trim');
        $this->form_validation->set_rules('user_role', 'user_role', 'trim|required');
        $this->form_validation->set_rules('company', 'Market', 'trim|required');
        $this->form_validation->set_rules('user_status', 'user_status', 'trim|required');
        if ($this->form_validation->run() === true) {
            $user_status = $this->input->post('user_status') == "Active" ? 1 : 0;
            log_message('error', "   user_status ". $this->input->post('user_status') );
            $additional_data = array(
                'first_name' => $this->input->post('first_name'),
             //   'fullname' => $this->input->post('first_name') . ' ' . $this->input->post('last_name'),
                'fullname' => $this->input->post('fullname') ,

                'last_name'  => $this->input->post('last_name'),
                'designation'      => $this->input->post('designation'),
                'phone'      => $this->input->post('phone'),
                'user_role'      => $this->input->post('user_role'),
                'company'      => $this->input->post('company'),
                'region'      => $this->input->post('region'),
                'area'      => $this->input->post('area'),
                'active'      => $user_status,
                'profile_status'      => $profile_status,
            );

            if ($action == "add") {
                $username = $this->get_id();
                $email = $this->input->post('email');
                $password = $this->input->post('password');
                $group[0] = 2;
                $group[1] = 3;
                $group_ids = $group;
                $user_id = $this->ion_auth->register($username, $password, $email, $additional_data, $group_ids);
            }
            if ($action == "update") {
                $id = $this->input->post('id');
                $additional_data['email'] = $this->input->post('email');
                $this->base_model->update(
                    $additional_data,
                    $id,
                    $data_table,
                    array($data_key => $id)
                );
            }
            echo json_encode(array("status" => TRUE));
        } else {
            header('Content-Type: application/json');
            echo json_encode(validation_errors());
        }
    }


    private function update_rule_condition($action, $data_table, $data_key)
    {
        $this->form_validation->set_rules('rule_id', 'rule_id', 'trim|required');
        $this->form_validation->set_rules('cond_type', 'cond_type', 'trim|required');
        $this->form_validation->set_rules('operation', 'operation', 'trim|required');
        $this->form_validation->set_rules('cond_value', 'cond_value', 'trim|required');
        if ($this->form_validation->run() === true) {
            $id = $this->input->post('id');
            $upd_data = array(
                'rule_id'     => $this->input->post('rule_id'),
                'cond_type'   => $this->input->post('cond_type'),
                'operation'   => $this->input->post('operation'),
                'cond_value'   => $this->input->post('cond_value')
            );

            if ($action == "add") {
                $this->base_model->insert($upd_data, $data_table);
            }
            if ($action == "update") {
                $this->base_model->update(
                    $upd_data,
                    $id,
                    $data_table,
                    array($data_key => $id)
                );
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
        $this->form_validation->set_rules('appr_name', 'appr_name', 'trim');
        $this->form_validation->set_rules('approver_from', 'approver_from', 'trim|required');
        $this->form_validation->set_rules('appr_function', 'appr_function', 'trim|required');
        if ($this->form_validation->run() === true) {
            $process_id = $this->input->post('process_id');
            $id = $this->input->post('id');
            $appr_name = $this->input->post('appr_name');
            $approver_from = $this->input->post('approver_from');
            if ($approver_from == "Preset1" || $approver_from == "Initiator") {

                $appr_name = 0;
            }
            log_message('error', '$appr_name');
            log_message('error', $appr_name);
            // $appr_name=$this->input->post('appr_name');
            $upd_data = array(
                'process_id'     => $this->input->post('process_id'),
                'approver_from'   => $this->input->post('approver_from'),
                'appr_name'       => empty($appr_name) ? 0 : $appr_name,
                'appr_function'   => $this->input->post('appr_function')
            );

            if ($action == "add") {
                $order_no = $this->base_model->rec_count($data_table, $process_id, 'process_id');
                $upd_data['order_no'] = empty($order_no) ? 1 : $order_no + 1;
                $this->base_model->insert($upd_data, $data_table);
            }
            if ($action == "update") {
                $this->base_model->update($upd_data, $id, $data_table, array($data_key => $id));
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

            case "locations":
                $this->update_locations($action, $data_table, $data_key);
                break;

            case "products":
                $this->update_products($action, $data_table, $data_key);
                break;

            case "customers":
                $this->update_customers($action, $data_table, $data_key);
                break;

            case "rewards":
                $this->update_rewards($action, $data_table, $data_key);
                break;
            case "key_acct_rewards":
                $this->update_key_acct_rewards($action, $data_table, $data_key);
                break;
            case "rewards_wam":
                $this->update_wam_rewards($action, $data_table, $data_key);
                break;

            case "targets":
                $this->update_targets($action, $data_table, $data_key);
                break;

            case "users":
                $this->update_users($action, $data_table, $data_key);
                break;

            case "rule_condition":
                $this->update_rule_condition($action, $data_table, $data_key);
                break;
            case "workflow_approvers":
                $this->update_workflow_approvers($action, $data_table, $data_key);
                break;
        }
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
                case "key_acct_rewards":
                    $temp_data = $this->base_model->run_qry("CALL GET_REWARD_KEY_ACCT()", 'qry');
                    $total_data = $temp_data->num_rows();
                    $total_data = is_null($total_data) ? 0 : $total_data;
                    $main_data = $total_data == 0 ? array() : $temp_data->result();
                    break;
                case "rewards":
                    $temp_data = $this->base_model->run_qry("CALL GET_REWARD_URBAN()", 'qry');
                    $total_data = $temp_data->num_rows();
                    $total_data = is_null($total_data) ? 0 : $total_data;
                    $main_data = $total_data == 0 ? array() : $temp_data->result();
                    break;
                case "users":
                    $total_data = $this->base_model->rec_count($data_table);
                    $total_data = is_null($total_data) ? 0 : $total_data;
                    $main_data = $this->base_model->get_record($data_table);
                    $main_data = $total_data == 0 ? array() : $main_data;
                    break;
                case "setup_credit":
                    $sqla = 'SELECT * from  credit_data_import';
                    $temp_data = $this->base_model->run_qry($sqla, 'qry');
                    $total_data = $temp_data->num_rows();
                    $total_data = is_null($total_data) ? 0 : $total_data;
                    $main_data = $total_data == 0 ? array() : $temp_data->result();
                    break;
                default:
                    $total_data = $this->base_model->rec_count($data_table);
                    $total_data = is_null($total_data) ? 0 : $total_data;
                    $main_data = $this->base_model->get_record($data_table);
                    $main_data = $total_data == 0 ? array() : $main_data;
                    break;
            }
        } else {
            switch ($data_table) {
                case "key_acct_rewards":
                    $temp_data = $this->base_model->run_qry("CALL GET_KEY_ACCT_REWARD()", 'qry');
                    $total_data = $temp_data->num_rows();
                    $total_data = is_null($total_data) ? 0 : $total_data;
                    $main_data = $temp_data->result();
                    break;

                case "workflow":
                    $key = "process_id";
                    $total_data = $this->base_model->rec_count($data_table, $id, $key);
                    $total_data = is_null($total_data) ? 0 : $total_data;
                    $main_data = $this->base_model->get_record($data_table, $id, $key);
                    break;
                case "reward_rules":
                    $key = "rule_id";
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
        foreach ($rows as $row) {
            $this->base_model->delete($data_table, array($data_key => $row));
            if ($data_table == 'workflow_approvers') {
                $this->base_model->delete('workflow_alt_approvers', array($data_key => $row));
            }
        }
        echo json_encode(array("status" => TRUE));
    }

    public function view_page($data_table, $id = '')
    {
        switch ($data_table) {
            case 'reward_rules':
                $this->add_rule('edit', $id);
                break;
            case 'workflow':
                $this->view_workflow_details($id);
                break;
        }
    }
    public function ajax_reset($data_table, $data_key)
    {
        $rows = $this->input->post('rows');
        foreach ($rows as $row) {
            $identity = $this->ion_auth->user($row)->row()->email;
            $change = $this->ion_auth->reset_password_Admin($identity, 'password');
        //     $sql = "UPDATE users SET resetKey = 'Yes'WHERE email = ? ";
        // $this->db->query($sql, array($identity));
        }
        echo json_encode(array("status" => TRUE));
    }
}
