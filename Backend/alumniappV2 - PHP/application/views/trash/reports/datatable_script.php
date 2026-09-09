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
            sScrollX: true,
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


<?php echo $save_val;?>

</script>