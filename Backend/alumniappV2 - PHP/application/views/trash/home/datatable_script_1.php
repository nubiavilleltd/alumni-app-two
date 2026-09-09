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
            "columns": [
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


    <?php if(!in_array("no-edit", $data_access)){?>
    function edit_row(id)
    {
        save_method = 'update';    
        //Ajax Load data from ajax

        $.ajax({
            url : "<?php echo site_url($data_section.'/ajax_edit/'.$data_table.'/'.$data_key)?>/" + id,
            type: "GET",
            dataType: "JSON",
            success: function(data)
            {
                <?php echo $data_editflds;?>
                $('#modal_form').modal('show'); // show bootstrap modal when complete loaded
                $('.modal-title').text('Edit Record'); // Set title to Bootstrap modal title
                $(".hide_this").hide();

            },
            error: function (jqXHR, textStatus, errorThrown)
            {
                alert('Error get data from ajax');
            }            
        });
        
    }
    <?php }?>

    function save()
    {
        $('#btnSave').text('saving...'); //change button text
        $('#btnSave').attr('disabled',true); //set button disable 
        var url;
        if(save_method == 'add') {
            url = "<?php echo site_url($data_section.'/ajax_action/add/'.$data_table)?>";
        } else {
            url = "<?php echo site_url($data_section.'/ajax_action/update/'.$data_table.'/'.$data_key)?>";
        }
        // ajax adding data to database
        $.ajax({
            url : url,
            type: "POST",
            data: $('#form').serialize(),       
            dataType: "JSON",
            success: function(data)
            {
                if(data.status) //if success close modal and reload ajax table
                {
                    $('#modal_form').modal('hide');
                    reload_table();
                }
                else
                {
                    str = data.replace(/(<p>|<\/p>)/gm, "");
                    alert(str);
                }
                $('#btnSave').text('save'); //change button text
                $('#btnSave').attr('disabled',false); //set button enable 
            },
            error: function (jqXHR, textStatus, errorThrown)
            {
                alert('Error adding / update data, please try again - '+jqXHR.responseText);
                $('#btnSave').text('save'); //change button text
                $('#btnSave').attr('disabled',false); //set button enable    
                console.log(jqXHR);
                console.log(textStatus);
                console.log(errorThrown);
                
            }
        });
    }

    function delete_row(list)
    {
        row_length =rows_selected.length;
        if (row_length < 1) {
            alert('No records selected');
        } else {
            if(confirm('Are you sure delete these '+rows_selected.length+' records?'))
            {
                // ajax delete data to database
                $.ajax({
                    url : "<?php echo site_url($data_section.'/ajax_delete/'.$data_table.'/'.$data_key)?>",
                    type: "POST",
                    data : {rows: rows_selected},  
                    success: function(data)
                    {
                        //if success reload ajax table
                        $('#modal_form').modal('hide');
                        reload_table();
                    },
                    error: function (jqXHR, textStatus, errorThrown)
                    {
                        alert('Error deleting data - '+errorThrown);
                    }
                });

            }
        }
    }

</script>