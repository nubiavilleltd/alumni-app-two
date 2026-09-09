<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Reports extends User_Controller
{
    protected $domval;
    function __construct()
    {
        $this->domval=' dom: \'lrftBip\', buttons: [\'copyHtml5\',\'excelHtml5\',\'csvHtml5\',\'print\'], sScrollX: true, scrollY: \'400px\', pageLength: 25,';
        parent::__construct();
    }

    public function index()
    {       
     $this->target_perf();
    }   

    public function dashboard()
    {   
        $this->data['page_title'] = 'Incentiva Dashboard';  
        $this->form_script();
        $this->form_validation->set_rules('period_month','period','trim');
        $this->form_validation->set_rules('market','market','trim');          
        if($this->form_validation->run() === true){    
            $period=$this->input->post('period_month');
            $date = date_parse($this->input->post('period_month'));
            $month=$date['month']."/".$date['year'];            
            $market=$this->input->post('market');   
           
        } else {
            $market= $this->current_user->company;
            $market=empty($market)?"Nigeria":$market;
            $sql="SELECT fullmonth, period_month, STR_TO_DATE(fullmonth,'%b %Y') as date1 FROM "
                    . "sales where market='".$market."' order by date1 desc limit 1";
            $list = $this->base_model->run_qry($sql);                
            $period=$list->fullmonth;
            $month=$list->period_month;  
        }
            $sql="SELECT currency from country where country='".$market."' ";
            $list = $this->base_model->run_qry($sql); 
        $this->data['currency']=$list->currency;
        $this->data['market_name']=$market;        
        $this->data['market'] = $this->base_model->get_parameters_menu('Markets','market',$market,'Market');                
        $this->data['current_mnth'] = $this->base_model->run_qry("CALL RPT_GET_CURRENT_MNTH('$period','$month','$market')");
        $this->data['regional_data'] = $this->base_model->run_qry("CALL RPT_REGIONAL_DATA('$period','$month','$market')",'result','multi');
        $this->data['top_10'] = $this->base_model->run_qry("CALL RPT_TOP_10_CUSTOMERS('$period','$month','$market')",'result','multi');        
        
        $this->render('home/dashboard_view');
    }  
    

    private function get_param_values($param) {
       $query = $this->db->get_where('setup_parameters', array('setup_name' => $param));
        if ($query->num_rows() > 0)	{
            $row = $query->row();
            
            $rowitems = str_replace("\r\n", ",", $row->setup_value);
        } else {
            $rowitems = NULL;
        }  
        return $rowitems;
    }
    
    private function get_arr_values($sql,$sub_field)
    {
        $query=$this->base_model->run_qry($sql,'qry');
        
        if ($query->num_rows() > 0){
            $region=$query->result_array();

            $temp_data='';
            foreach ($region as $key => $values)
            {
                $temp_data .= $values[$sub_field].',';
            }                            
            $temp_data = substr($temp_data, 0, -1);
        } else {
            $temp_data = NULL;
        }
        
        $query->next_result();
        $query->free_result();
        
        return $temp_data;
    }     

    public function get_data()
    {       
        $country_data=array();
        $country_data['countries']="";
        $sql_country="SELECT country FROM country order by country";
        $temp_data=$this->base_model->run_qry($sql_country,'result','multi');
        $country_list='';
        foreach($temp_data as $temp)
        {
            $country_list .=$temp->country.',';
            $sql_region="SELECT distinct region FROM locations where market ='".$temp->country."' order by region";
            $country_data[$temp->country]['region']=$this->get_arr_values($sql_region,'region');
            $region_data=$this->base_model->run_qry($sql_region,'result','multi');
            foreach($region_data as $region)
            {     
                $sql_area="SELECT locations.area FROM locations where market ='".$temp->country
                        ."' and region  ='".$region->region."' order by area";
                $country_data[$temp->country][$region->region]['area']=$this->get_arr_values($sql_area,'area');
                
            }
         
            $sql_type=" SELECT DISTINCT cust_type FROM customers where market ='".$temp->country."' order by cust_type";
            $country_data[$temp->country]['cust_type']=$this->get_arr_values($sql_type,'cust_type');
            $cust_type_data=$this->base_model->run_qry($sql_type,'result','multi');
            foreach($cust_type_data as $cust_type)
            {     
                $sql_band=" SELECT DISTINCT cust_category as band FROM customers "
                        . "where market ='".$temp->country."' and cust_type='".$cust_type->cust_type."' order by cust_category";
                $country_data[$temp->country][$cust_type->cust_type]['band']=$this->get_arr_values($sql_band,'band');
                
            }

        }

        $country_data['countries']=substr($country_list, 0, -1);
        $country_data['Rural']=$this->get_arr_values("CALL GET_CAMPAIGN('Rural Wholesaler')",'campaign');                        
        $country_data['Key_acct']=$this->get_arr_values("CALL GET_CAMPAIGN('Key Account')",'campaign');   
        $country_data['Urban']=$this->get_arr_values("CALL GET_CAMPAIGN('Urban Wholesaler')",'campaign');   
        $country_data['WAM']=$this->get_arr_values("CALL GET_CAMPAIGN('WAM Customers')",'campaign');   
        $country_data['All']=$this->get_arr_values("CALL GET_CAMPAIGN('All')",'campaign');   
        
                
       $json_data = array($country_data);
        header('Content-Type: application/json');   
       echo "var jsonData = ".json_encode($country_data, JSON_UNESCAPED_UNICODE); 
       //echo json_encode($country_data, JSON_UNESCAPED_UNICODE); 
    } 
    
    public function target_perf()
    {       
        $this->form_script_modal();
        $this->data['page_title'] = 'Target and Actual Performance Report';  
        $this->data['data_columns']='{"data": "market" },{"data": "region" },'
            . '{ "data": "area" },{ "data": "cust_type" },{ "data": "band" },{ "data": "sales" },'
            . '{"data": "target" },{ "data": "percent"}';
        $this->data['data_section']="Reports";
        $this->data['data_table']="target_perf";   
        $this->data['data_access']=['no-edit'];        
        $this->data['data_key']="list_id";    

        $this->data['mini']=FALSE; 
        $this->mf_target_perf();        
        $this->datatable_script($this->data['data_section'],'asc');  
        $this->render('reports/rpt_target_perf');
    } 
    
    
    public function get_target_perf($data_param='', $where='')
    {
        header('Content-Type: application/json'); 
        if($data_param !=""){
            parse_str(urldecode($data_param), $output); 
            $where_val=urldecode($where);       
            $selectedval=$output['selectedval'];
            $temp_data=$this->base_model->run_qry("CALL RPT_TGT_PERF('$selectedval','$where_val')",'qry');
        } else {
            $selectedval='"market,region,area,cust_type,band"';
            $sql="SELECT fullmonth,  STR_TO_DATE(fullmonth,'%b %Y') as date1 FROM sales where "
                    . "market='Nigeria' group by fullmonth order by date1 desc limit 1";
            $list = $this->base_model->run_qry($sql);      
            $fullmonth=$list->fullmonth;                  
            $where_val='sales.fullmonth = "'.$fullmonth.'"';
            $temp_data=$this->base_model->run_qry("CALL RPT_TGT_PERF($selectedval,'$where_val')",'qry');
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
        //echo "window.gridData=".json_encode($main_data, JSON_UNESCAPED_UNICODE); 
    }
    

    private function mf_target_perf(){

            $sql="SELECT fullmonth, STR_TO_DATE(fullmonth,'%b %Y') as date1 FROM sales where "
                    . "market='Nigeria' group by fullmonth order by date1 desc limit 1";
            $list = $this->base_model->run_qry($sql);      

            $this->data['rpt_title']=$period=$list->fullmonth;      

           $this->data['before_head'] .='<script src="'.site_url('Reports/get_data').'"></script>';
           $this->data['after_foot']=$this->data['after_foot'].'

            <script type="text/javascript">
               $(\'#choosemnth2\').MonthPicker({
                    Button: false,
                    MonthFormat: \'M yy\', // Short month name, Full year.
                });

                $(document).ready(function () {
                        $(\'#market\').html("");
                        $(\'#market\').append(\'<option value=""> -- Select All - </option>\');
                        if( jsonData.countries.length > 0){
                            vals = jsonData.countries.split(",");
                            $.each(vals, function(index, value) {
                                    $(\'#market\').append(\'<option value="\' + value + \'">\' + value + \'</option>\')
                            });      
                        }

                        $("#cust_type").change(function () {
                            var cust_val= $("#cust_type option:selected").val();
                            var mkt_val= $("#market option:selected").val();

                            $(\'#band\').html("");
                            $(\'#band\').append(\'<option value=""> -- Select All -- </option>\');
                            if(mkt_val.length > 0 && cust_val.length > 0){                         
                                tempval1=eval("jsonData[\""+mkt_val+"\"][\""+cust_val+"\"].band");                     
                                vals2 = tempval1.split(",");
                                $.each(vals2, function(index, value) {
                                        $(\'#band\').append(\'<option value="\' + value + \'">\' + value + \'</option>\')
                                }); 
                            }
                        });

                        $("#region").change(function () {
                            var reg_val= $("#region option:selected").val();
                            var mkt_val= $("#market option:selected").val();
                            $(\'#area\').html("");
                            $(\'#area\').append(\'<option value=""> -- Select All -- </option>\');
                            if(mkt_val.length > 0 && reg_val.length > 0){  
                                tempval2=eval("jsonData[\""+mkt_val+"\"][\""+reg_val+"\"].area");                    
                                vals2 = tempval2.split(",");                         
                                $.each(vals2, function(index, value) {
                                        $(\'#area\').append(\'<option value="\' + value + \'">\' + value + \'</option>\')
                                });   
                             }
                        });

                        $("#market").change(function () {
                            var mkt_val= $("#market option:selected").val();
                            $(\'#band\').html("");
                            $(\'#band\').append(\'<option value=""> -- Select All - </option>\');                              
                            $(\'#region\').html("");
                            $(\'#region\').append(\'<option value=""> -- Select All -- </option>\');
                            $(\'#cust_type\').html("");
                            $(\'#cust_type\').append(\'<option value=""> -- Select All - </option>\');
                            $(\'#area\').html("");
                            $(\'#area\').append(\'<option value=""> -- Select All -- </option>\');  
                            if(mkt_val.length > 0){               
                                tempval3=eval("jsonData[\""+mkt_val+"\"].region");                  
                                vals1 = tempval3.split(",");    
                                $.each(vals1, function(index, value) {
                                        $(\'#region\').append(\'<option value="\' + value + \'">\' + value + \'</option>\')
                                });  

                                tempval4=eval("jsonData[\""+mkt_val+"\"].cust_type");                
                                vals2 = tempval4.split(",");    
                                $.each(vals2, function(index, value) {
                                       $(\'#cust_type\').append(\'<option value="\' + value + \'">\' + value + \'</option>\')
                                });  
                            }                    
                        });    

                        $("#regionchk").change(function() {
                            if ($(this).is(":checked")) {
                                $("#region").prop("disabled", false);
                            } else {
                                $("#region").prop("disabled", true);
                            }
                        });

                        $("#areachk").change(function() {
                            if ($(this).is(":checked")) {
                                $("#area").prop("disabled", false);
                            } else {
                                $("#area").prop("disabled", true);
                            }
                        });
                        $("#cust_typechk").change(function() {
                            if ($(this).is(":checked")) {
                                $("#cust_type").prop("disabled", false);
                            } else {
                                $("#cust_type").prop("disabled", true);
                            }
                        });

                        $("#bandchk").change(function() {
                            if ($(this).is(":checked")) {
                                $("#band").prop("disabled", false);
                            } else {
                                $("#band").prop("disabled", true);
                            }
                        });

                });

                $("#periodval").change(function () {
                    var selperiod= $("#periodval option:selected").val();
                    if ((selperiod == "Week")) {
                        $(".weekDiv").show();
                        $(".monthDiv").hide();
                        $(".qtrDiv").hide();
                        $(".yrDiv").hide();
                    } 
                    if ((selperiod == "Month")) {
                        $(".weekDiv").hide();
                        $(".monthDiv").show();
                        $(".qtrDiv").hide();
                        $(".yrDiv").hide();
                    }     
                    if ((selperiod == "Quarter")) {
                        $(".weekDiv").hide();
                        $(".monthDiv").hide();
                        $(".qtrDiv").show();
                        $(".yrDiv").hide(); 
                    }     
                    if ((selperiod == "Year")) {
                        $(".weekDiv").hide();
                        $(".monthDiv").hide();
                        $(".qtrDiv").hide();
                        $(".yrDiv").show();
                    }         
                    if ((selperiod == "")) {
                        $(".weekDiv").hide();
                        $(".monthDiv").hide();
                        $(".qtrDiv").hide();
                        $(".yrDiv").hide();
                    }                   
                });    
             </script> 
             ';      

           $this->data['save_val']='
            function save()
            {
                var url;
                url = "'. site_url('Reports/ajax_list/target_perf').'";
                var mkt_val= $("#market option:selected").val();
                var region_val= $("#region option:selected").val();
                var area_val= $("#area option:selected").val();
                var cust_type= $("#cust_type option:selected").val();     
                var band= $("#band option:selected").val();  
                var periodval= $("#periodval option:selected").val();  
                var chkArray = [];
                var tableHeaders = "";
                var columnsval = "";
                var cnt=0;
                $("input:checkbox[name=selectedval]:checked").each(function() {
                        chkArray.push($(this).val());
                        tableHeaders += "<th>" + $(this).val() + "</th>";
                         columnsval+=\'{"data": "\' + $(this).val() + \'" },\';
                        cnt+=1;
                }); 
                /* we join the array separated by the comma */
                var selectedval;
                num1=cnt;
                num2=cnt+1;
                num3=cnt+2;
                selectedval = chkArray.join(",") ;
                tableHeaders += "<th>Sales</th><th>Target</th><th>Percent</th>";
                columnsval+=\'{ "data": "sales" },{"data": "target" },{ "data": "percent"}\';
                var str_val="";
                var where_val="";
                str_val="selectedval="+selectedval;  
                if(mkt_val.length > 0){
                    where_val+=\'sales.market ="\'+mkt_val+\'" and \';
                }
                if(region_val.length > 0){
                    where_val+=\'locations.region ="\'+region_val+\'" and \';
                }
                if(area_val.length > 0){
                    where_val+=\'locations.area ="\'+area_val+\'" and \';
                }        
                if(cust_type.length > 0){
                    where_val+=\'sales.cust_type ="\'+cust_type+\'" and \';
                }        
                if(band.length > 0){
                    where_val+=\'customers.cust_category ="\'+band+\'" and \';
                }           

                var rpt_type="";
                if(periodval.length <= 0){
                    where_val+="1=1";
                }  else {       
                    var period_yr2= $("#period_yr2 option:selected").val(); 

                    if(periodval== "Week"){

                            var period_week= $("#period_week option:selected").val(); 
                            var period_month= $("#choosemnth").val(); 
                            if(period_week.length >0 & period_month.length > 0){
                                where_val+=\'sales.period_week=\'+period_week + \' and sales.fullmonth="\'+period_month+\'"\';
                                rpt_type="Week Report - Week "+period_week +" - " +period_month;
                            }
                    }   
                    if(periodval== "Month"){
                            var period_month= $("#choosemnth2").val(); 
                            if(period_month.length > 0){
                                where_val+=\'sales.fullmonth="\'+period_month+\'"\';
                                rpt_type="Month Report - Month - " +period_month;
                            }
                    }    
                    if(periodval== "Quarter"){
                            var period_qtr= $("#period_qtr option:selected").val(); 
                            var period_yr= $("#period_yr option:selected").val(); 
                            if(period_qtr.length >0 & period_yr.length > 0){
                                where_val+=\'sales.period_quarter=\'+period_qtr + \' and sales.period_year="\'+period_yr+\'"\';
                                rpt_type="Quarter Report - Quarter "+period_qtr +" - " +period_yr;
                            }
                    }        
                  if(periodval== "Year"){
                            var period_yr= $("#period_yr2 option:selected").val(); 
                            if(period_yr.length > 0){
                                where_val+=\'sales.period_year="\'+period_yr+\'"\';
                                rpt_type="Year Report - " +period_yr;
                            }
                    }             
                }

                str_val=encodeURIComponent(str_val).replace(/\'/g, "%27");
                where_val=encodeURIComponent(where_val).replace(/\'/g, "%27");
                where_val=where_val.replace(/"/g, "%22");
                $("#rpt_type").text(rpt_type);
                $("#rpt_div").empty();
                $("#rpt_div").append(\'<table id="example" class="display table table-hover table-striped nowrap"  width="100%"><thead><tr>\' + tableHeaders + \'</tr></thead><tfoot></tfoot><tbody></tbody></table>\');
                $(\'#modal_form\').modal(\'hide\');
                
                var itemval="dom: \lrftBip\', buttons: [\'copyHtml5\',\'excelHtml5\',\'csvHtml5\',\'print\'], "+
                "scrollY: \'400px\',sScrollX: true,  scrollCollapse: true,pageLength: 25";
                
                var dataObject = eval(\'[{"COLUMNS":[\'+columnsval+\']}]\');
                $(\'#example\').DataTable( {'.$this->domval.' columns: dataObject[0].COLUMNS}).ajax.url(url+"/"+str_val+"/"+where_val).load();
            } ';     
    }        
       
    public function participation_win()
    {       
        $this->form_script_modal();
        $this->data['page_title'] = 'Program Participation & Win Report';  
        $this->data['data_columns']='{"data": "market" },{ "data": "band" },{ "data": "campaign" },'
                . '{ "data": "fullmonth" },{ "data": "customers" },{ "data": "participants" },{ "data": "winners" },'
            . '{ "data": "part_rate" },{ "data": "win_rate" },{ "data": "rebate" },{"data": "sales" },{ "data": "target" },{"data": "percent" }';
        
            //Customers,Participants,Winners,Part Rate,,Win Rate
            $selectedval='"Market,Region,Area"';
            
        $this->data['data_section']="Reports";
        $this->data['data_table']="participation_win";   
        $this->data['data_access']=['no-edit'];        
        $this->data['data_key']="list_id";    
        $this->data['mini']=TRUE; 
        $this->mf_participation_win();
        $this->datatable_script($this->data['data_section'],'asc');  
        $this->render('reports/rpt_participation_win');
    } 

    
    public function get_participation_win($data_param='', $where='')
    {
        header('Content-Type: application/json'); 
        if($data_param !=""){
            parse_str(urldecode($data_param), $output);    
            $selectval=$output['selectedval'];            
            $market=$output['market'];
            $region_val=$output['region_val'];
            $area_val=$output['area_val'];
            $cust_type=$output['cust_type'];
            $band=$output['band'];
            $processtype=$output['processtype'];
            $campaign=$output['campaign'];
            $selectarr = explode(',',$selectval);
            $perioditem=$output['perioditem'];
            $periodval=$output['periodval'];     
              
            
            $selectedval=" sales_table.fullmonth ";
            $grpval=" sales_table.fullmonth ";
            $select_sales=" sales.fullmonth ";
            $select_win=" winners_list.fullmonth ";
            $select_sales_param=" fullmonth ";
            $select_win_param=" fullmonth ";
            $sales_win_join=" sales_table.fullmonth=win_table.fullmonth ";
            $sales_winners_join=" win_table.fullmonth=winners_table.fullmonth ";
            $sales_part_join=" sales_table.fullmonth=participation_table.fullmonth ";
            $sales_cust_join=" sales_table.fullmonth=customers_table.fullmonth ";
            $where_sales=' sales.market="'.$market.'" ';
            $where_win=' winners_list.market="'.$market.'" ';
            
            if(in_array("region", $selectarr)){
                   $selectedval.=',sales_table.region ';
                   $grpval.=',sales_table.region ';
                   $select_sales.=', customers.region';
                   $select_win.=', customers.region';
                   $select_sales_param.=',region ';
                   $select_win_param.=',region ';
                   $sales_win_join.=' and sales_table.region=win_table.region ';
                   $sales_winners_join.=' and win_table.region=winners_table.region ';
                   $sales_part_join.='  and sales_table.region=participation_table.region ';
                   $sales_cust_join.='  and sales_table.region=customers_table.region ';
                   if($region_val!=null){
                        $where_sales.=' and customers.region ="'.$region_val.'" ';
                        $where_win.=' and customers.region ="'.$region_val.'" ';                      
                   }
            }
            
            if(in_array("area", $selectarr)){
                   $selectedval.=',sales_table.location as area ';
                   $grpval.=',sales_table.location ';
                   $select_sales.=', customers.location';
                   $select_win.=', customers.location';
                   $select_sales_param.=',location ';
                   $select_win_param.=',location ';
                   $sales_win_join.=' and sales_table.location=win_table.location ';
                   $sales_winners_join.=' and win_table.location=winners_table.location ';
                   $sales_part_join.=' and sales_table.location=participation_table.location ';
                   $sales_cust_join.=' and sales_table.location=customers_table.location ';
                   if($area_val!=null){
                        $where_sales.=' and customers.location ="'.$region_val.'" ';
                        $where_win.=' and customers.location ="'.$area_val.'" ';                      
                   }
            }           
            if(in_array("cust_type", $selectarr)){
                   $selectedval.=',sales_table.cust_type ';
                   $grpval.=',sales_table.cust_type ';
                   $select_sales.=', customers.cust_type';
                   $select_win.=', customers.cust_type';
                   $select_sales_param.=',cust_type ';
                   $select_win_param.=',cust_type ';
                   $sales_win_join.=' and sales_table.cust_type=win_table.cust_type ';
                   $sales_winners_join.=' and win_table.cust_type=winners_table.cust_type ';
                   $sales_part_join.=' and sales_table.cust_type=participation_table.cust_type ';
                   $sales_cust_join.=' and sales_table.cust_type=customers_table.cust_type ';
                   if($cust_type!=null){
                        $where_sales.=' and customers.cust_type ="'.$cust_type.'" ';
                        $where_win.=' and customers.cust_type ="'.$cust_type.'" ';                      
                   }
            }           
            if(in_array("band", $selectarr)){
                   $selectedval.=',sales_table.cust_category  as band ';
                   $grpval.=',sales_table.cust_category ';
                   $select_sales.=', customers.cust_category';
                   $select_win.=', customers.cust_category';
                   $select_sales_param.=',cust_category ';
                   $select_win_param.=',cust_category ';
                   $sales_win_join.=' and sales_table.cust_category=win_table.cust_category ';
                   $sales_winners_join.=' and win_table.cust_category=winners_table.cust_category ';
                   $sales_part_join.=' and sales_table.cust_category=participation_table.cust_category ';
                   $sales_cust_join.=' and sales_table.cust_category=customers_table.cust_category ';
                   if($band!=null){
                        $where_sales.=' and customers.cust_category ="'.$band.'" ';
                        $where_win.=' and customers.cust_category ="'.$band.'" ';                      
                   }
            }    
                     
            if(in_array("processtype", $selectarr)){
                   $selectedval.=',win_table.processtype ';
                   $grpval.=',win_table.processtype ';
                   $select_win.=', winners_list.processtype';
                   $select_win_param.=',processtype ';
                   if($processtype!=null){
                        $where_win.=' and winners_list.processtype ="'.$processtype.'" ';                      
                   }
            }            
            if(in_array("campaign", $selectarr)){
                   $selectedval.=',win_table.campaign ';
                   $grpval.=',win_table.campaign ';
                   $select_win.=', winners_list.campaign';
                   $select_win_param.=',campaign ';
                   if($campaign |=null){
                        $where_win.=' and winners_list.processtype ="'.$campaign.'" ';                      
                   }
            }   
            
            switch ($perioditem){
                case "Month":
                    $where_sales.=' and sales.fullmonth = "'.$periodval.'"';
                    $where_win.=' and winners_list.fullmonth = "'.$periodval.'"';
                    break;
                
                case "Quarter":
                    $arr=explode("-",$periodval);
                    $where_sales.=' and sales.period_quarter = "'.$arr[0].'" and sales.period_year = "'.$arr[1].'"';
                    $where_win.=' and winners_list.quarter = "'.$arr[0].'" and winners_list.year = "'.$arr[1].'"';
                    break;  
                
                case "Year":
                    $where_sales.=' and sales.period_year = "'.$periodval.'"';
                    $where_win.=' and winners_list.year = "'.$periodval.'"';
                    break; 
                
            }     
            
                        
            log_message("error", "selectedval is ".$selectval." market is ".$market." region_val is ".$region_val.
                    " area_val is ".$area_val." cust_type is ".$cust_type." band is ".$band." processtype is ".$processtype.
                    " campaign is ".$campaign." perioditem is ".$perioditem." periodval is ".$periodval);     

            $temp_data=$this->base_model->run_qry("CALL RPT_PART_WIN("
                    . "'$selectedval','$grpval','$select_sales','$where_sales','$select_win','$where_win',"
                    . "'$select_win_param','$select_sales_param','$sales_win_join','$sales_winners_join',"
                    . "'$sales_part_join','$sales_cust_join')",'qry');                 
            
        } else {
           
            $sql="SELECT fullmonth,  STR_TO_DATE(fullmonth,'%b %Y') as date1 FROM sales where "
                    . "market='Nigeria' group by fullmonth order by date1 desc limit 1";
            $list = $this->base_model->run_qry($sql);      
            $fullmonth=$list->fullmonth;                  
            $where_sales='sales.fullmonth = "'.$fullmonth.'"';
            $where_win='winners_list.fullmonth = "'.$fullmonth.'"';
            $selectedval='"sales_table.cust_category as band,winners_table.campaign,sales_table.fullmonth"';
            $grpval='"sales_table.cust_category,winners_table.campaign,sales_table.fullmonth"';
            $select_sales='"sales.cust_type,customers.cust_category,sales.fullmonth"';
            $select_win='"winners_list.cust_type,customers.cust_category,winners_list.campaign,winners_list.fullmonth"';
            $select_sales_param='"cust_type,cust_category,fullmonth"';
            $select_win_param='"cust_type,cust_category,campaign,fullmonth"';            
            $sales_win_join='"sales_table.market=win_table.market and sales_table.cust_type=win_table.cust_type and
                sales_table.cust_category=win_table.cust_category and sales_table.fullmonth=win_table.fullmonth "'; 
            $sales_winners_join='"win_table.cust_type=winners_table.cust_type 
                and win_table.cust_category=winners_table.cust_category 
                and win_table.campaign=winners_table.campaign and win_table.fullmonth=winners_table.fullmonth   "';             
            $sales_part_join='"sales_table.cust_type=participation_table.cust_type 
                and sales_table.cust_category=participation_table.cust_category and sales_table.fullmonth=participation_table.fullmonth  "';
            $sales_cust_join='"sales_table.market=customers_table.market and
                sales_table.cust_type=customers_table.cust_type and  sales_table.cust_category=customers_table.cust_category and
                sales_table.fullmonth=customers_table.fullmonth  "';            
            $temp_data=$this->base_model->run_qry("CALL RPT_PART_WIN("
                    . "$selectedval,$grpval,$select_sales,'$where_sales',$select_win,'$where_win',"
                    . "$select_win_param,$select_sales_param,$sales_win_join,$sales_winners_join,"
                    . "$sales_part_join,$sales_cust_join)",'qry');                   
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
        //echo "window.gridData=".json_encode($main_data, JSON_UNESCAPED_UNICODE); 
    }
    
   private function mf_participation_win(){

            $sql="SELECT fullmonth, STR_TO_DATE(fullmonth,'%b %Y') as date1 FROM sales where "
                    . "market='Nigeria' group by fullmonth order by date1 desc limit 1";
            $list = $this->base_model->run_qry($sql);      

            $this->data['rpt_title']=$period=$list->fullmonth;      

           $this->data['before_head'] .='<script src="'.site_url('Reports/get_data').'"></script>';
           $this->data['after_foot']=$this->data['after_foot'].'

            <script type="text/javascript">
               $(\'#choosemnth2\').MonthPicker({
                    Button: false,
                    MonthFormat: \'M yy\', // Short month name, Full year.
                });

                $(document).ready(function () {
                        $(\'#market\').html("");
                        $(\'#market\').append(\'<option value=""> -- Select All - </option>\');
                        if( jsonData.countries.length > 0){
                            vals = jsonData.countries.split(",");
                            $.each(vals, function(index, value) {
                                    $(\'#market\').append(\'<option value="\' + value + \'">\' + value + \'</option>\')
                            });      
                        }

                        $("#cust_type").change(function () {
                            var cust_val= $("#cust_type option:selected").val();
                            var mkt_val= $("#market option:selected").val();

                            $(\'#band\').html("");
                            $(\'#band\').append(\'<option value=""> -- Select All -- </option>\');

                            $(\'#campaign\').html("");
                            $(\'#campaign\').append(\'<option value=""> -- Select All -- </option>\'); 
                            
                            if(mkt_val.length > 0 && cust_val.length > 0){                         
                                tempval1=eval("jsonData[\""+mkt_val+"\"][\""+cust_val+"\"].band");       
                                vals2 = tempval1.split(",");
                                $.each(vals2, function(index, value) {
                                        $(\'#band\').append(\'<option value="\' + value + \'">\' + value + \'</option>\')
                                }); 

                                if (cust_val.search("Rural") !=-1) {
                                    tempval1=eval("jsonData[\"Rural\"]");  
                                } else  if (cust_val.search("Urban") !=-1) {
                                    tempval1=eval("jsonData[\"Urban\"]");   
                                } else  if (cust_val.search("Key") !=-1) {
                                    tempval1=eval("jsonData[\"Key_acct\"]");    
                                } else  if (cust_val.search("WAM") !=-1) {
                                    tempval1=eval("jsonData[\"WAM\"]");     
                                } else  {
                                    tempval1=eval("jsonData[\"All\"]");          
                                } 
                                vals1 = tempval1.split(",");    
                                $.each(vals1, function(index, value) {
                                        $(\'#campaign\').append(\'<option value="\' + value + \'">\' + value + \'</option>\')
                                }); 
                            }
                        });

                        $("#region").change(function () {
                            var reg_val= $("#region option:selected").val();
                            var mkt_val= $("#market option:selected").val();
                            $(\'#area\').html("");
                            $(\'#area\').append(\'<option value=""> -- Select All -- </option>\');
                            if(mkt_val.length > 0 && reg_val.length > 0){  
                                tempval2=eval("jsonData[\""+mkt_val+"\"][\""+reg_val+"\"].area");                 
                                vals2 = tempval2.split(",");                         
                                $.each(vals2, function(index, value) {
                                        $(\'#area\').append(\'<option value="\' + value + \'">\' + value + \'</option>\')
                                });   
                             }
                        });

                        $("#market").change(function () {
                            var mkt_val= $("#market option:selected").val();
                            $(\'#band\').html("");
                            $(\'#band\').append(\'<option value=""> -- Select All - </option>\');                              
                            $(\'#region\').html("");
                            $(\'#region\').append(\'<option value=""> -- Select All -- </option>\');
                            $(\'#cust_type\').html("");
                            $(\'#cust_type\').append(\'<option value=""> -- Select All - </option>\');
                            $(\'#area\').html("");
                            $(\'#area\').append(\'<option value=""> -- Select All -- </option>\');  

                            $(\'#campaign\').html("");
                            $(\'#campaign\').append(\'<option value=""> -- Select All -- </option>\'); 
                            
                            tempval1=eval("jsonData[\"All\"]");                
                            vals1 = tempval1.split(",");    
                            $.each(vals1, function(index, value) {
                                    $(\'#campaign\').append(\'<option value="\' + value + \'">\' + value + \'</option>\')
                            });  
                                
                            if(mkt_val.length > 0){               
                                tempval3=eval("jsonData[\""+mkt_val+"\"].region");                
                                vals1 = tempval3.split(",");    
                                $.each(vals1, function(index, value) {
                                        $(\'#region\').append(\'<option value="\' + value + \'">\' + value + \'</option>\')
                                });  

                                tempval4=eval("jsonData[\""+mkt_val+"\"].cust_type");                
                                vals2 = tempval4.split(",");    
                                $.each(vals2, function(index, value) {
                                       $(\'#cust_type\').append(\'<option value="\' + value + \'">\' + value + \'</option>\')
                                });  
                            }                    
                        });    

                        $("#regionchk").change(function() {
                            if ($(this).is(":checked")) {
                                $("#region").prop("disabled", false);
                            } else {
                                $("#region").prop("disabled", true);
                            }
                        });

                        $("#areachk").change(function() {
                            if ($(this).is(":checked")) {
                                $("#area").prop("disabled", false);
                            } else {
                                $("#area").prop("disabled", true);
                            }
                        });
                        $("#cust_typechk").change(function() {
                            if ($(this).is(":checked")) {
                                $("#cust_type").prop("disabled", false);
                            } else {
                                $("#cust_type").prop("disabled", true);
                            }
                        });

                        $("#bandchk").change(function() {
                            if ($(this).is(":checked")) {
                                $("#band").prop("disabled", false);
                            } else {
                                $("#band").prop("disabled", true);
                            }
                        });
                        
                        $("#processtypechk").change(function() {
                            if ($(this).is(":checked")) {
                                $("#processtype").prop("disabled", false);
                            } else {
                                $("#processtype").prop("disabled", true);
                            }
                        });
                        
                        $("#campaignchk").change(function() {
                            if ($(this).is(":checked")) {
                                $("#campaign").prop("disabled", false);
                            } else {
                                $("#campaign").prop("disabled", true);
                            }
                        });
                        

                });

                $("#periodval").change(function () {
                    var selperiod= $("#periodval option:selected").val();
                    if ((selperiod == "Week")) {
                        $(".weekDiv").show();
                        $(".monthDiv").hide();
                        $(".qtrDiv").hide();
                        $(".yrDiv").hide();
                    } 
                    if ((selperiod == "Month")) {
                        $(".weekDiv").hide();
                        $(".monthDiv").show();
                        $(".qtrDiv").hide();
                        $(".yrDiv").hide();
                    }     
                    if ((selperiod == "Quarter")) {
                        $(".weekDiv").hide();
                        $(".monthDiv").hide();
                        $(".qtrDiv").show();
                        $(".yrDiv").hide(); 
                    }     
                    if ((selperiod == "Year")) {
                        $(".weekDiv").hide();
                        $(".monthDiv").hide();
                        $(".qtrDiv").hide();
                        $(".yrDiv").show();
                    }         
                    if ((selperiod == "")) {
                        $(".weekDiv").hide();
                        $(".monthDiv").hide();
                        $(".qtrDiv").hide();
                        $(".yrDiv").hide();
                    }                   
                });    
             </script> 
             ';      

           $this->data['save_val']='
            function save()
            {
                var url;
                url = "'. site_url('Reports/ajax_list/participation_win').'";
                var mkt_val= $("#market option:selected").val();
                var region_val= $("#region option:selected").val();
                var area_val= $("#area option:selected").val();
                var cust_type= $("#cust_type option:selected").val();     
                var band= $("#band option:selected").val();  
                var processtype= $("#processtype option:selected").val();  
                var campaign= $("#campaign option:selected").val();  
                var periodval= $("#periodval option:selected").val();  
                var chkArray = [];
                var tableHeaders = "";
                var columnsval = "";
                var cnt=0;
                $("input:checkbox[name=selectedval]:checked").each(function() {
                        chkArray.push($(this).val());
                        tableHeaders += "<th>" + $(this).val() + "</th>";
                         columnsval+=\'{"data": "\' + $(this).val() + \'" },\';
                        cnt+=1;
                }); 
                /* we join the array separated by the comma */
                var selectedval;
                num1=cnt;
                num2=cnt+1;
                num3=cnt+2;
                selectedval = chkArray.join(",") ;
                tableHeaders += "<th>Month</th><th>Customers</th><th>Participants</th><th>Winners</th><th>Part Rate</th>";
                tableHeaders += "<th>Win Rate</th><th>Rebate</th><th>Sales</th><th>Target</th><th>Percent</th>";
                columnsval+=\'{ "data": "fullmonth" },{ "data": "customers" },{ "data": "participants" },{ "data": "winners" },\';
                columnsval+=\'{ "data": "part_rate" },{ "data": "win_rate" },{ "data": "rebate" },{"data": "sales" },{ "data": "target" },{"data": "percent" }\';
                
                var str_val="selectedval="+selectedval.replace(" ","+");
                var where_val="";
                if(mkt_val.length > 0){
                    str_val+="&market="+mkt_val.replace(" ","+");
                }
                if(region_val.length > 0){
                    str_val+="&region_val="+region_val.replace(" ","+");               
                }        
                if(area_val.length > 0){
                    str_val+="&area_val="+area_val.replace(" ","+");               
                }           
                if(cust_type.length > 0){
                    str_val+="&cust_type="+cust_type.replace(" ","+");               
                }     
                if(band.length > 0){
                    str_val+="&band="+band.replace(" ","+");               
                }     
                if(processtype.length > 0){
                    str_val+="&processtype="+processtype.replace(" ","+");               
                }     
                if(campaign.length > 0){
                    str_val+="&campaign="+campaign.replace(" ","+");               
                }                     
                
                var rpt_type="";
                rpt_type=cust_type+" Report for "+mkt_val+" ";

                if(periodval.length > 0){
                    var period_yr2= $("#period_yr2 option:selected").val(); 
                    if(periodval== "Month"){
                            var period_month= $("#choosemnth2").val(); 
                            if(period_month.length > 0){
                                str_val+="&perioditem=Month&periodval="+period_month;
                                rpt_type+="- Month - " +period_month;
                            }
                    }    
                    if(periodval== "Quarter"){
                            var period_qtr= $("#period_qtr option:selected").val(); 
                            var period_yr= $("#period_yr option:selected").val(); 
                            if(period_qtr.length >0 & period_yr.length > 0){  
                                str_val+="&perioditem=Quarter&periodval="+period_qtr + "-"+period_yr;                                
                                rpt_type="- Quarter "+period_qtr +" - " +period_yr;
                            }
                    }        
                  if(periodval== "Year"){
                            var period_yr= $("#period_yr2 option:selected").val(); 
                            if(period_yr.length > 0){
                                str_val+="&perioditem=Year&periodval="+period_yr;     
                                rpt_type="Year - " +period_yr;
                            }
                    }             
                }
                str_val=encodeURIComponent(str_val).replace(/\'/g, "%27");
                str_val=str_val.replace(/"/g, "%22");
                console.log(url+"/"+str_val);
                
                $("#rpt_type").text(rpt_type);
                $("#rpt_div").empty();
                $("#rpt_div").append(\'<table id="example" class="display table table-hover table-striped nowrap"  width="100%"><thead><tr>\' + tableHeaders + \'</tr></thead><tfoot></tfoot><tbody></tbody></table>\');
                $(\'#modal_form\').modal(\'hide\');
                                
                var itemval="dom: \lrftBip\', buttons: [\'copyHtml5\',\'excelHtml5\',\'csvHtml5\',\'print\'], "+
                "scrollY: \'400px\',sScrollX: true,  scrollCollapse: true,pageLength: 25";
                
                alert(tableHeaders);
                alert(columnsval);
                
                var dataObject = eval(\'[{"COLUMNS":[\'+columnsval+\']}]\');
                $(\'#example\').DataTable( {'.$this->domval.' columns: dataObject[0].COLUMNS}).ajax.url(url+"/"+str_val).load();
                    

            } ';     
    }            
    
    public function national_rpt()
    {       
        $this->datatable_script();
        $this->data['page_title'] = 'reports List'; 
        $this->render('reports/rpt_national');
    } 
           
       
    public function rpt_payout()
    {       
        $this->form_script_modal();
          $this->domval=' dom: \'lrftBip\', buttons: [\'copyHtml5\',\'excelHtml5\',\'csvHtml5\',\'print\'],scrollY: \'400px\', sScrollX: true, pageLength: 25,';
        $this->data['page_title'] = 'Payout Report';  
        $this->data['data_columns']='{"data": "cust_code" },{ "data": "cust_name" },{ "data": "fullmonth" },'
                . '{ "data": "location" },{ "data": "total_target" },{ "data": "total_sales" },{ "data": "pro_sales" },'
            . '{ "data": "total_sellout" },{ "data": "percent_target" },{ "data": "kpi_level" },{"data": "mband" },'
                . '{ "data": "pband" },{"data": "sellin" },{"data": "sellout" },{"data": "loyalty" },'
                . '{"data": "participation" },{"data": "q_sellin" },'
                . '{"data": "q_DD" },{"data": "imported" },{"data": "netpayout" }';

        $this->data['data_section']="Reports";
        $this->data['data_table']="rpt_payout";   
        $this->data['data_access']=['no-edit'];        
        $this->data['data_key']="list_id";    
        $this->mf_rpt_payout();
        $this->data['mini']=TRUE;         
        $this->datatable_script($this->data['data_section'],'asc');  
        $this->render('reports/rpt_payout');
    } 

    
    public function get_rpt_payout($data_param='', $where='')
    {
        header('Content-Type: application/json'); 
        if($data_param !=""){
            parse_str(urldecode($data_param), $output); 
            $market=$output['market'];
            $cust_type=$output['cust_type'];
            $perioditem=$output['perioditem'];
            $periodval=$output['periodval'];     

            switch ($perioditem){
                case "Month":
                    $sales_str='sales.fullmonth = "'.$periodval.'"';
                    $win_str='winners_list.fullmonth = "'.$periodval.'"';
                    break;
                
                case "Quarter":
                    $arr=explode("-",$periodval);
                    $sales_str='sales.period_quarter = "'.$arr[0].'" and sales.period_year = "'.$arr[1].'"';
                    $win_str='winners_list.quarter = "'.$arr[0].'" and winners_list.year = "'.$arr[1].'"';
                    break;  
                
                case "Year":
                    $sales_str='sales.period_year = "'.$periodval.'"';
                    $win_str=' winners_list.year = "'.$periodval.'"';
                    break; 
                
                DEFAULT:
                    $sales_str='sales.fullmonth = "Aug 2017"';
                    $win_str='winners_list.fullmonth = "Aug 2017"';
            }

            $where_sales='sales.market="'.$market.'" and sales.cust_type="'.$cust_type.'" and '.$sales_str;
         
            $where_win='winners_list.market="'.$market.'" and winners_list.cust_type="'.$cust_type.'" and '.$win_str;
            
            $temp_data=$this->base_model->run_qry("CALL RPT_PAYOUT('$cust_type','$where_sales','$where_win')",'qry');
            
        } else {
           
            $sql="SELECT fullmonth,  STR_TO_DATE(fullmonth,'%b %Y') as date1 FROM sales where "
                    . "market='Nigeria' group by fullmonth order by date1 desc limit 1";
            $list = $this->base_model->run_qry($sql);      
            $fullmonth=$list->fullmonth;          
                                 
            $where_sales='sales.market="Nigeria" and sales.cust_type="Urban Wholesaler"
		and sales.fullmonth = "'.$fullmonth.'"';
         
            $where_win='winners_list.market="Nigeria" and winners_list.cust_type="Urban Wholesaler"
		and winners_list.fullmonth = "'.$fullmonth.'"';
     
            $temp_data=$this->base_model->run_qry("CALL RPT_PAYOUT('Urban Wholesaler','$where_sales','$where_win')",'qry');                   
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
        //echo "window.gridData=".json_encode($main_data, JSON_UNESCAPED_UNICODE); 
    }
    
   private function mf_rpt_payout(){

            $sql="SELECT fullmonth, STR_TO_DATE(fullmonth,'%b %Y') as date1 FROM sales where "
                    . "market='Nigeria' group by fullmonth order by date1 desc limit 1";
            $list = $this->base_model->run_qry($sql);      

            $this->data['rpt_title']="Urban Wholesaler Report For Month ".$period=$list->fullmonth;      

           $this->data['before_head'] .='<script src="'.site_url('Reports/get_data').'"></script>';
           $this->data['after_foot']=$this->data['after_foot'].'

            <script type="text/javascript">
               $(\'#choosemnth2\').MonthPicker({
                    Button: false,
                    MonthFormat: \'M yy\', // Short month name, Full year.
                });

                $(document).ready(function () {
                        $(\'#market\').html("");
                        $(\'#market\').append(\'<option value=""> -- Select Market - </option>\');
                        if( jsonData.countries.length > 0){
                            vals = jsonData.countries.split(",");
                            $.each(vals, function(index, value) {
                                    $(\'#market\').append(\'<option value="\' + value + \'">\' + value + \'</option>\')
                            });      
                        }

                        $("#market").change(function () {
                            var mkt_val= $("#market option:selected").val();
                            $(\'#cust_type\').html("");
                            $(\'#cust_type\').append(\'<option value=""> -- Select Customer Type - </option>\');

                            if(mkt_val.length > 0){               
                                tempval4=eval("jsonData[\""+mkt_val+"\"].cust_type");                
                                vals2 = tempval4.split(",");    
                                $.each(vals2, function(index, value) {
                                       $(\'#cust_type\').append(\'<option value="\' + value + \'">\' + value + \'</option>\')
                                });  
                            }                    
                        });    
                });

                $("#periodval").change(function () {
                    var selperiod= $("#periodval option:selected").val();

                    if ((selperiod == "Month")) {
                        $(".monthDiv").show();
                        $(".qtrDiv").hide();
                        $(".yrDiv").hide();
                    }     
                    if ((selperiod == "Quarter")) {
                        $(".monthDiv").hide();
                        $(".qtrDiv").show();
                        $(".yrDiv").hide(); 
                    }     
                    if ((selperiod == "Year")) {
                        $(".monthDiv").hide();
                        $(".qtrDiv").hide();
                        $(".yrDiv").show();
                    }         
                    if ((selperiod == "")) {
                        $(".monthDiv").hide();
                        $(".qtrDiv").hide();
                        $(".yrDiv").hide();
                    }                   
                });    
             </script> 
             ';      

           $this->data['save_val']='
            function save()
            {
                var url;
                url = "'. site_url('Reports/ajax_list/rpt_payout').'";
                var mkt_val= $("#market option:selected").val();
                var cust_type= $("#cust_type option:selected").val();     
                var periodval= $("#periodval option:selected").val();  
                var chkArray = [];
                var tableHeaders = "";
                var columnsval = "";
                
                switch (cust_type) {
                case "Urban Wholesaler":
                    tableHeaders = "<th>URN</th><th>Customer</th><th>Month</th><th>Location</th>"+
                        "<th>Target</th><th>Sales</th><th>Prorated</th><th>Sellout</th><th>Percent</th>"+
                        "<th>KPI</th><th>C_Band</th><th>P_Band</th><th>M_Sellin</th><th>M_Sellout</th>"+
                        "<th>M_Loyalty</th><th>M_Participation</th><th>Q_Sellin</th>  "+
                        "<th>Q_Loyalty</th><th>Imported</th><th>NetPayout</th>";

                    columnsval=\'{"data": "cust_code" },{ "data": "cust_name" },{ "data": "fullmonth" },{ "data": "location" },\'+
                        \'{ "data": "total_target" },{ "data": "total_sales" },{ "data": "pro_sales" },{ "data": "total_sellout" },\'+
                       \' { "data": "percent_target" },{ "data": "kpi_level" },{"data": "mband" },{ "data": "pband" },\'+
                        \'{"data": "sellin" },{"data": "sellout" },{"data": "loyalty" },{"data": "participation" },\'+
                        \'{"data": "q_sellin" },{"data": "q_DD" },{"data": "imported" },{"data": "netpayout" }\';  
                        break;
                        
                case "Rural Wholesaler":
                    tableHeaders = "<th>URN</th><th>Customer</th><th>Month</th><th>Location</th>"+
                        "<th>Target</th><th>Sales</th><th>Prorated</th><th>Sellout</th><th>Percent</th>"+
                        "<th>KPI</th><th>Band</th><th>M_Sellin</th><th>Re_dx</th>"+
                        "<th>Q_Sellin</th><th>Imported</th><th>NetPayout</th>";

                    columnsval=\'{"data": "cust_code" },{ "data": "cust_name" },{ "data": "fullmonth" },{ "data": "location" },\'+
                        \'{ "data": "total_target" },{ "data": "total_sales" },{ "data": "pro_sales" },{ "data": "total_sellout" },\'+
                        \'{ "data": "percent_target" },{ "data": "kpi_level" },{"data": "mband" },\'+
                        \'{"data": "sellin" },{"data": "re_dx" },{"data": "q_sellin" },{"data": "imported" },{"data": "netpayout" }\';  
                        break;

                case "WAM Customers":
                    tableHeaders = "<th>URN</th><th>Customer</th><th>Month</th><th>Location</th>"+
                        "<th>Target</th><th>Sales</th><th>Prorated</th><th>Sellout</th><th>Percent</th>"+
                        "<th>Band</th><th>Week1</th><th>Week2</th><th>Week3</th><th>Week4</th><th>Week5</th>"+
                        "<th>Monthly</th><th>Quarterly</th><th>Imported</th><th>NetPayout</th>";

                    columnsval=\'{"data": "cust_code" },{ "data": "cust_name" },{ "data": "fullmonth" },{ "data": "location" },\'+
                        \'{ "data": "total_target" },{ "data": "total_sales" },{ "data": "pro_sales" },{ "data": "total_sellout" },\'+
                        \'{ "data": "percent_target" },{"data": "mband" },{ "data": "week1" },{ "data": "week2" },{ "data": "week3" },\'+
                        \'{ "data": "week4" },{ "data": "week5" },\'+
                        \'{"data": "monthly" },{"data": "quarterly" },{"data": "imported" },{"data": "netpayout" }\';  

                }   
                    
                var str_val="";
                var where_val="";
                if(mkt_val.length > 0){
                    str_val+="market="+mkt_val.replace(" ","+");
                }
                if(cust_type.length > 0){
                    str_val+="&cust_type="+cust_type.replace(" ","+");               
                }        
                
                var rpt_type="";
                rpt_type=cust_type+" Report for "+mkt_val+" ";

                if(periodval.length > 0){
                    var period_yr2= $("#period_yr2 option:selected").val(); 
                    if(periodval== "Month"){
                            var period_month= $("#choosemnth2").val(); 
                            if(period_month.length > 0){
                                str_val+="&perioditem=Month&periodval="+period_month;
                                rpt_type+="- Month - " +period_month;
                            }
                    }    
                    if(periodval== "Quarter"){
                            var period_qtr= $("#period_qtr option:selected").val(); 
                            var period_yr= $("#period_yr option:selected").val(); 
                            if(period_qtr.length >0 & period_yr.length > 0){  
                                str_val+="&perioditem=Quarter&periodval="+period_qtr + "-"+period_yr;                                
                                rpt_type="- Quarter "+period_qtr +" - " +period_yr;
                            }
                    }        
                  if(periodval== "Year"){
                            var period_yr= $("#period_yr2 option:selected").val(); 
                            if(period_yr.length > 0){
                                str_val+="&perioditem=Year&periodval="+period_yr;     
                                rpt_type="Year - " +period_yr;
                            }
                    }             
                }
                console.log(url+"/"+str_val);
                str_val=encodeURIComponent(str_val).replace(/\'/g, "%27");
                str_val=str_val.replace(/"/g, "%22");
                console.log(url+"/"+str_val);
                $("#rpt_type").text(rpt_type);
                $("#rpt_div").empty();
                $("#rpt_div").append(\'<table id="example" class="display table table-hover table-striped nowrap"  width="100%"><thead><tr>\' + tableHeaders + \'</tr></thead><tfoot></tfoot><tbody></tbody></table>\');
                $(\'#modal_form\').modal(\'hide\');
                
                var itemval="dom: \lrftBip\', buttons: [\'copyHtml5\',\'excelHtml5\',\'csvHtml5\',\'print\'], "+
                "scrollY: \'400px\',sScrollX: true,  scrollCollapse: true,pageLength: 25";
            
                var dataObject = eval(\'[{"COLUMNS":[\'+columnsval+\']}]\');
                $(\'#example\').DataTable( {'.$this->domval.' columns: dataObject[0].COLUMNS}).ajax.url(url+"/"+str_val).load();

            } ';     
    }            
    
    
    public function ajax_list($data_table,$id='',$where='')
    {       
        $func='get_'.$data_table;
        $this->$func($id,$where);
    }    

  
}
