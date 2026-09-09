<?php
echo"
    
<script Language=\"Javascript\">
var table;
var data_key;
var rows_selected = [];

    $(document).ready(function() {
        table = $('#example').DataTable( {
            dom: 'lrftBip',
            buttons: [
                'copyHtml5',
                'excelHtml5',
                'csvHtml5',
                'print'
            ],        
            order: [".$datatable_col.", '".$datatable_order."'],
            scrollY: '400px',
            ".($mini?"sScrollX: '100%', "
        . 'scrollX: true,':"")."
        scrollCollapse: true,
            pageLength: 25,
".'
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]] ,
            processing: true,
            ajax: \''.site_url($data_section.'/ajax_list/'.$data_table.'/'.$data_var).'\',
            columns: [
                '.$data_columns.'
            ]  
             
                        
        } );
';
?>

        $('#example tbody').on('click', 'tr', function () {
            var data = table.row( this ).data(); 
            <?php if(in_array("no-edit", $data_access)){
               echo 'window.location.href = "'.site_url($data_section."/view_page/".
                $data_table."/").'/"+data[\''.$data_key.'\'];';
            
            } else {?>
            
            if(isNaN(data['<?php echo $data_key;?>'])){
                window.location.href = "javascript:edit_row('"+data['<?php echo $data_key;?>']+"')";
            } else {
                window.location.href = "javascript:edit_row("+data['<?php echo $data_key;?>']+")";
            }         
            
            <?php }?>
        } ); 
        
        $('#example tbody').on('dblclick', 'tr', function () {
            var data = table.row( this ).data(); 
            <?php if(in_array("no-edit", $data_access)){
               echo 'window.open("'.site_url($data_section."/view_page/".
                $data_table."/").'/"+data[\''.$data_key.'\']);';
            
            } else {?>
            
            if(isNaN(data['<?php echo $data_key;?>'])){
                window.open("javascript:edit_row('"+data['<?php echo $data_key;?>']+"')");
            } else {
                window.open("javascript:edit_row("+data['<?php echo $data_key;?>']+")");
            }         
            
            <?php }?>
        } );        
        
    } );    

    function format_number(x) {
        if(x){
           return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
           //return Number(parseFloat(x).toFixed(2)).toLocaleString('en');
        } else {
            return '-';
        }
    }
    
    function format_status(x) {
        if(x){
            return "Active";
        } else {
            return "Disabled";
        }
    }    

    function format_date(x) {
        var date = new Date(x);
        var month = date.getMonth() + 1;
        return (month.length > 1 ? month : "0" + month) + "/" + date.getDate() + "/" + date.getFullYear();
    }

    function reload_table()
    {
        table.ajax.reload(); //reload datatable ajax 
    }



    var save_method; //for save method string

    function searchbox()
    {
        save_method = 'Run';
        $('#form')[0].reset(); // reset form on modals
        $('.form-group').removeClass('has-error'); // clear error class
        $('.help-block').empty(); // clear error string
        $('#modal_form').modal('show'); // show bootstrap modal
        $('.modal-title').text('Report Parameters'); // Set Title to Bootstrap modal title
        $(".hide_this").show();
        
    }


    function save()
    {
        var url;
        url = "<?php echo site_url($data_section.'/ajax_list/'.$data_table)?>";
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
                 columnsval+='{"data": "' + $(this).val() + '" },';
                cnt+=1;
        }); 
        /* we join the array separated by the comma */
        var selectedval;
        num1=cnt;
        num2=cnt+1;
        num3=cnt+2;
        selectedval = chkArray.join(',') ;
        tableHeaders += "<th>Sales</th><th>Target</th><th>Percent</th>";
        columnsval+='{ "data": "sales" },{"data": "target" },{ "data": "percent"}';
        var str_val="";
        var where_val="";
        str_val="selectedval="+selectedval;  
        if(mkt_val.length > 0){
            str_val+="&market="+mkt_val.replace(' ','+');
            where_val+='market ="'+mkt_val+'" and ';
        }
        if(region_val.length > 0){
            str_val+="&region="+region_val.replace(' ','+');
            where_val+='region ="'+region_val+'" and ';
        }
        if(area_val.length > 0){
            str_val+="&area="+area_val.replace(' ','+');
            where_val+='area ="'+area_val+'" and ';
        }        
        if(cust_type.length > 0){
            str_val+="&cust_type="+cust_type.replace(' ','+');
            where_val+='cust_type ="'+cust_type_val+'" and ';
        }        
        if(band.length > 0){
            str_val+="&band="+band.replace(' ','+');
            where_val+='band ="'+band_val+'" and ';
        }           
        
        if(periodval.length <= 0){
            where_val+="1=1";
        }  else {       
            var period_yr2= $("#period_yr2 option:selected").val(); 

            if(periodval== "Week"){
                    var period_week= $("#period_week option:selected").val(); 
                    var period_month= $("#choosemnth").val(); 
                    if(period_week.length >0 & period_month.length > 0){
                        where_val+'period_week='+period_week + ' and fullmonth="'+period_month+'"';
                    }
            }   
            if(periodval== "Month"){
                    var period_month= $("#choosemnth2").val(); 
                    if(period_month.length > 0){
                        where_val+='fullmonth="'+period_month+'"';
                    }
            }    
            if(periodval== "Quarter"){
                    var period_qtr= $("#period_qtr option:selected").val(); 
                    var period_yr= $("#period_yr option:selected").val(); 
                    if(period_qtr.length >0 & period_yr.length > 0){
                        where_val+='period_quarter='+period_qtr + ' and period_year="'+period_yr+'"';
                    }
            }        
          if(periodval== "Year"){
                    var period_yr= $("#period_yr2 option:selected").val(); 
                    if(period_yr.length > 0){
                        where_val+='period_year="'+period_yr+'"';
                    }
            }             
        }
         url+"/"+str_val+"/"+where_val
       console.log("headers are"+ where_val);
       str_val=encodeURIComponent(str_val).replace(/'/g, "%27");
       where_val=encodeURIComponent(where_val).replace(/'/g, "%27");
       where_val=encodeURIComponent(where_val).replace(/"/g, "%22");
       console.log(url+"/"+str_val+"/"+where_val);

       $("#rpt_div").empty();
        $("#rpt_div").append('<table id="example" class="display table table-hover table-striped"  width="100%"><thead><tr>' + tableHeaders + '</tr></thead><tfoot></tfoot><tbody></tbody></table>');
        $('#modal_form').modal('hide');
        var dataObject = eval('[{"COLUMNS":['+columnsval+']}]');
        $('#example').DataTable( { columns: dataObject[0].COLUMNS}).ajax.url(url+"/"+str_val+"/"+where_val).load();
        
    }    

</script>