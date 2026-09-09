<?php
if($data_table=="users"){
  echo  ''.$docreadydata.'';  
}
$order = ''; // Initialize the $order variable

// Check if $datatable_col2 is greater than 0
if ($datatable_col2 > 0) {
    // If $datatable_col2 is greater than 0, order by both $datatable_col and $datatable_col2
    $order = "order: [[$datatable_col, '$datatable_order'], [$datatable_col2, '$datatable_order']]";
} else {
    // If $datatable_col2 is not greater than 0, order only by $datatable_col
    $order = "order: [$datatable_col, '$datatable_order']";
}


 echo"

<style>
.viewbtn {
    text-transform: none !important;
    background: #337ab7 !important;
    color: white !important;
    border: 0 !important;
    border-radius: 0.4rem !important;
    padding: 0.21rem !important;
    box-shadow: 1px 2px 10px rgba(0,0,0,.5) !important;
} 
</style>

<script Language=\"Javascript\">
var table;
var data_key;
var data;
var fourthColumnData;
var newOrderNumbers = [];
var apprData;
var order_no;
var rows_selected = [];
var tablename = '".$data_table."';

$(document).ready(function() {

    if (!navigator.onLine) {
        console.warn('Offline mode: loading cached data');
        let cache = localStorage.getItem('datatable_' + tablename);
        let offlineData = cache ? JSON.parse(cache) : [];

        table = $('#example').DataTable({
            dom: '<\"top\">frt<\"bottom\"Bip><\"clear\">',
            buttons: [
                'copyHtml5',
                'excelHtml5',
                'csvHtml5',
                'print'
            ],
            ".$order.",
            scrollY: '350px',
            scrollX: true,
            scrollCollapse: true,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, \"All\"]],
            processing: true,
            data: offlineData,
            columns: [
                ".$data_columns.",
                { 
                    data: null,
                    orderable: true,
                    defaultContent: \"<button class='viewbtn'>View</button>\"
                }".($data_section=='Setup' ? ",
                {
                    targets: [1, 2, 3, 4, 5, 6],
                    orderable: true,
                    data: '".$data_key."',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            return '<input type=\"checkbox\" name=\"id[]\" class=\"editor-active\" value=\"' + $('<div/>').text(data).html() + '\">';
                        }
                        return data;
                    },
                    className: 'dt-body-center',
                }" : "")."
            ],
            language: {
                emptyTable: 'Offline mode — no cached data found'
            }
        });
    } else {
        table = $('#example').DataTable({
            dom: '<\"top\">frt<\"bottom\"Bip><\"clear\">',
            buttons: [
                'copyHtml5',
                'excelHtml5',
                'csvHtml5',
                'print'
            ],
            ".$order.",
            scrollY: '350px',
            scrollX: true,
            scrollCollapse: true,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, \"All\"]],
            processing: true,
            ajax: {
                url: '".site_url($data_section.'/ajax_list/'.$data_table.(is_null($data_var)?'':'/'.$data_var))."',
                dataSrc: function (json) {
                    localStorage.setItem('datatable_' + tablename, JSON.stringify(json.data));
                    return json.data;
                }
            },
            createdRow: function(row, data, dataIndex) {
                $(row).attr('id', 'row-' + dataIndex);
            },
            columns: [
                ".$data_columns.",
                { 
                    data: null,
                    orderable: true,
                    defaultContent: \"<button class='viewbtn'>View</button>\"
                }".($data_section=='Setup' ? ",
                {
                    targets: [1, 2, 3, 4, 5, 6],
                    orderable: true,
                    data: '".$data_key."',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            return '<input type=\"checkbox\" name=\"id[]\" class=\"editor-active\" value=\"' + $('<div/>').text(data).html() + '\">';
                        }
                        return data;
                    },
                    className: 'dt-body-center',
                }" : "")."
            ]
        });
    }


";

?>
// Enable drag and drop sorting
// Enable drag-and-drop row reordering

$(document).ready(function () {
  if (tablename == 'workflow_approvers' ||tablename == 'request_approvers') {
  $("tbody").sortable({
              // Add options if needed
              update: function (event, ui) {
                  // Update the order numbers
                   newOrderNumbers = [];
          $("#example tbody tr").each(function (index) {
              var orderNumber = index + 1;
              $(this).find('td:nth-child(1)').text(orderNumber);
              newOrderNumbers.push({ id: $(this).find('td:last-child input').val(), order: orderNumber });
          });
          console.log(newOrderNumbers)
              },
              stop: function (event, ui) {
                  // Rearrange the table rows based on the new sorting order
                  var rows = $("#example tbody tr").get();
                  rows.sort(function (a, b) {
                      var keyA = parseInt($(a).find('td:first').text(), 10);
                      var keyB = parseInt($(b).find('td:first').text(), 10);
                      if (keyA < keyB) return -1;
                      if (keyA > keyB) return 1;
                      return 0;
                  });
                  $.each(rows, function (index, row) {
//console.log(row)
                      $("#example").append(row);
                  });
              }
          });
          $("tbody").disableSelection();
        }

    });

         // Handle click on checkbox
        $('#example tbody').on('click', 'input[type="checkbox"]', function(e){
           var $row = $(this).closest('tr');

           // Get row data
            data = table.row($row).data();
             order_no= data['order_no'] ;
             apprData= data['appr_status']
             fourthColumnData = data['appr_name']; // Change '3' to the appropriate index of the fourth column
       console.log('Data of the fourth column: ' + fourthColumnData);
           // Get row ID
           
           var rowId = data["<?php echo $data_key;?>"];

           // Determine whether row ID is in the list of selected row IDs 
           var index = $.inArray(rowId, rows_selected);

           // If checkbox is checked and row ID is not in list of selected row IDs
           if(this.checked && index === -1){
              rows_selected.push(rowId);

           // Otherwise, if checkbox is not checked and row ID is in list of selected row IDs
           } else if (!this.checked && index !== -1){
              rows_selected.splice(index, 1);
              
           }

           if(this.checked){
              $row.addClass('selected');
           } else {
              $row.removeClass('selected');
           }

           // Prevent click event from propagating to parent
           e.stopPropagation();
        });

        // Handle click on table cells with checkboxes
//        $('#example').on('click', 'tbody td, thead th:last-child', function(e){
//           $(this).parent().find('input[type="checkbox"]').trigger('click');
//        });

        $('#example tbody').on('dblclick', 'tr', function () {
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
        
        $('#example tbody').on( 'click', 'button', function () {
            var data = table.row( $(this).parents('tr') ).data();
            
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
    } );    


    function format_number(x) {
        if(x){
           return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
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

    function add_row()
    {
        save_method = 'add';
        $('#form')[0].reset(); // reset form on modals
        $('.form-group').removeClass('has-error'); // clear error class
        $('.help-block').empty(); // clear error string
        $('#modal_form').modal('show'); // show bootstrap modal
        $('.modal-title').text('Add Record'); // Set Title to Bootstrap modal title
        $(".hide_this").show();
        
    }

    function edit_row(id)
    {
        save_method = 'update';
        $('#form')[0].reset(); // reset form on modals
        $('.form-group').removeClass('has-error'); // clear error class
        $('.help-block').empty(); // clear error string  
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

function save() {
    $('.btnSave').addClass('spinner');
    $('#btnSave').text('saving...');
    $('#btnSave').attr('disabled', true);

    let request_id = $('#request_id').val();

    var url;
    var alertoption;
    if (save_method == 'add') {
        alertoption = 'Added!';
        url = "<?php echo site_url($data_section.'/ajax_action/add/'.$data_table)?>";
    } else {
        alertoption = 'Updated!';
        url = "<?php echo site_url($data_section.'/ajax_action/update/'.$data_table.'/'.$data_key)?>";
    }

    var formData = new FormData($('#form')[0]);

    // ✅ If offline, store data locally
    if (!navigator.onLine) {
        let offlineData = localStorage.getItem('offlineQueue') ? JSON.parse(localStorage.getItem('offlineQueue')) : [];

        // Convert FormData to a plain object
        let dataObj = {};
        formData.forEach((value, key) => {
            dataObj[key] = value;
        });

        offlineData.push({
            url: url,
            method: save_method,
            data: dataObj,
            timestamp: new Date().toISOString()
        });

        localStorage.setItem('offlineQueue', JSON.stringify(offlineData));

        Swal.fire(
            'Offline!',
            'You are offline. Your data has been saved locally and will sync when online.',
            'warning'
        );
        $('#modal_form').modal('hide');
        $('#btnSave').text('Save');
        $('#btnSave').attr('disabled', false);
        $('.btnSave').removeClass('spinner');
        return;
    }

    // ✅ If online, send to server via AJAX
    $.ajax({
        url: url,
        type: "POST",
        data: formData,
        async: false,
        cache: false,
        contentType: false,
        processData: false,
        dataType: "JSON",

        success: function (data) {
            if (data.status) {
                $('#modal_form').modal('hide');
                Swal.fire(alertoption, 'Your item has been ' + alertoption, 'success')
                    .then(() => {
                        reload_table();
                        if (tablename == 'request_approvers') {
                            window.location.href = "<?php echo site_url($data_section.'/view_request_approver_details/')?>"+request_id;
                        }
                    });
            } else {
                alert('Error: ' + data);
            }

            $('#btnSave').text('Save');
            $('#btnSave').attr('disabled', false);
            $('.btnSave').removeClass('spinner');
        },
        error: function (jqXHR, textStatus, errorThrown) {
            alert('Error saving data: ' + textStatus);
            $('#btnSave').text('Save');
            $('#btnSave').attr('disabled', false);
            $('.btnSave').removeClass('spinner');
        }
    });
}

// ✅ Background Sync when back online
window.addEventListener('online', function () {
    let offlineData = localStorage.getItem('offlineQueue');
    if (!offlineData) return;

    offlineData = JSON.parse(offlineData);
    if (offlineData.length === 0) return;

    Swal.fire({
        title: 'Syncing Data...',
        text: 'Please wait while your offline data is being synced.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    let successCount = 0;
    let failedItems = [];

    const isReallyOnline = () => {
        return new Promise((resolve) => {
            // quick HEAD check to verify connectivity (instead of just navigator.onLine)
            fetch(window.location.origin, { method: 'HEAD', cache: 'no-store' })
                .then(() => resolve(true))
                .catch(() => resolve(false));
        });
    };

    isReallyOnline().then(confirmedOnline => {
        if (!confirmedOnline) {
            Swal.fire('Offline Detected', 'Network is unstable. Will retry syncing later.', 'error');
            return;
        }

        let syncPromises = offlineData.map(entry => {
            return new Promise(resolve => {
                const trySync = (attempt = 1) => {
                    $.ajax({
                        url: entry.url,
                        type: "POST",
                        data: entry.data,
                        timeout: 10000, // 10 seconds timeout
                        success: function (data) {
                            // ✅ Ensure server confirms successful save
                            if (data && data.status === true) {
                                console.log("✅ Confirmed server sync:", entry);
                                successCount++;
                                resolve(true);
                            } else {
                                console.warn("⚠️ Server did not confirm sync:", entry);
                                if (attempt < 3) {
                                    setTimeout(() => trySync(attempt + 1), 3000);
                                } else {
                                    failedItems.push(entry);
                                    resolve(false);
                                }
                            }
                        },
                        error: function (err) {
                            console.warn(`⚠️ Sync attempt ${attempt} failed for:`, entry.url, err.statusText);
                            if (attempt < 3) {
                                setTimeout(() => trySync(attempt + 1), 3000);
                            } else {
                                failedItems.push(entry);
                                resolve(false);
                            }
                        }
                    });
                };
                trySync();
            });
        });

        Promise.all(syncPromises).then(() => {
            if (failedItems.length > 0) {
                localStorage.setItem('offlineQueue', JSON.stringify(failedItems));
                Swal.fire(
                    'Partial Sync',
                    `${successCount} item(s) synced successfully. ${failedItems.length} will retry later.`,
                    'warning'
                );
            } else {
                localStorage.removeItem('offlineQueue');
                Swal.fire('Synced!', 'All offline data synced successfully.', 'success');
            }

            $('#modal_form').modal('hide');
        });
    });
});
// Show the spinner

    function showSpinner() {
    document.getElementById('loading-spinner').style.display = 'block';
}

// Hide the spinner
function hideSpinner() {
    document.getElementById('loading-spinner').style.display = 'none';
}
    function delete_row(list)
    {
        row_length =rows_selected.length;
       let process_id= $('#id').val()
       let next_appr= $('#next_appr').val()
       let next_appr2= $('#appr_name2').val()
       let order_no2= $('#order_no').val()
       let request_id= $('#request_id').val()
       order_no
      // alert(apprData)
      // alert(fourthColumnData)
      
       let data_type;

if (tablename == 'workflow_approvers' ||tablename == 'request_approvers') {
  
    data_type = {
        rows: rows_selected,
        process_id: process_id
    };
} else {
    data_type = {
        rows: rows_selected
    };
}
       if (row_length < 1) {
            alert('No record(s) selected');

        } if(tablename == 'request_approvers'&&(next_appr==fourthColumnData)&&(order_no2==order_no)){
                alert('You can not delete the current approver but you can select a new user');
                $('#example input[type="checkbox"]').prop('checked', false);
            }else if (tablename == 'request_approvers'&&(apprData=='Approved'&&apprData!==null )){
                alert('You can not delete this approver because He or She has approved');
                $('#example input[type="checkbox"]').prop('checked', false);

            }
             else {
            if(confirm('Are you sure delete these '+rows_selected.length+' record(s)?'))
            {
                $('#example input[type="checkbox"]').prop('checked', false);

                // ajax delete data to database
                $.ajax({
                    url : "<?php echo site_url($data_section.'/ajax_delete/'.$data_table.'/'.$data_key)?>",
                    type: "POST",
                    data :data_type,  
                    success: function(data)
                    {
                        //if success reload ajax table
                                <!-- toastr.success(row_length + ' record(s) deleted successfully', 'Deleted'); -->
                               
                                Swal.fire(
                                'Deleted !',
                                'Your item has been Deleted !',
                                'success'
                                )
                       

                        <!-- toastr.success(row_length + ' record(s) deleted successfully','Deleted'); -->
                        $('#modal_form').modal('hide');
                     
                       
                        reload_table();
                       
                        
                    },
                    error: function (jqXHR, textStatus, errorThrown)
                    {
                        alert('Error deleting data - '+errorThrown);
                    }
                });
            }
            $('#example input[type="checkbox"]').prop('checked', false);

        }
        rows_selected = [];      
        apprData=''  
    }

    function reset_row(list)
    {
        row_length =rows_selected.length;
        if (row_length < 1) {
            alert('No record(s) selected');
        } else {
            if(confirm('Are you sure reset these '+rows_selected.length+' record(s)?'))
            {
                // ajax delete data to database
                $.ajax({
                    url : "<?php echo base_url($data_section.'/ajax_reset/'.$data_table.'/'.$data_key)?>",
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
        rows_selected = [];
    } 
    function reset_approvers()
    {
       // ajax delete data to database
                $.ajax({
                    url : "<?php echo base_url($data_section.'/ajax_reset_approvers/'.$data_table)?>",
                    type: "POST",
                    data : {rows: newOrderNumbers},  
                    success: function(data)
                    {
                        //if success reload ajax table
                        Swal.fire({
                icon: 'success',
                title: 'Successful...',
                text: 'Approvers Updated',
                confirmButtonColor: '#3085d6',

                        })
                     
                        reload_table();
                    },
                    error: function (jqXHR, textStatus, errorThrown)
                    {
                        alert('Error deleting data - '+errorThrown);
                    }
                });

    

    } 
    $(document).on("change", "select[name='userArea']", function (event) {
        event.preventDefault();
        jQuery("[attribute='value']")
        
        
        $("#picture_link").val('');
      //  var pos_desc = $(this).val();
	var pos_desc = $("#userArea").val();
    var acct_code= $("#userArea option:selected").val();
            var $select2 = $('#dept').selectize();
            var selectize2 = $select2[0].selectize;
            $('#userArea').after('<span id="savingMsg">Populating...</span>');
      $('#userArea').prop('disabled', true); 
            
           
        $.ajax({
            url : "<?php echo site_url($data_section.'/getdept')?>/" + pos_desc,       
            //Ajax Load data from ajax     
            type: "GET",
            dataType: "JSON",
            success: function(data)
            {
                console.log(data);
                
                selectize2.clear();
            selectize2.clearOptions();
               
            console.log("here2");
           
            var selectedval= $("#userArea option:selected").val();
           
           
       
            for (var i = 0; i < data.data.length; i++) {

                console.log(data.data[i])
                $('#savingMsg').remove();
        $('#userArea').prop('disabled', false);

                if(selectedval== data.data[i].location) {
//console.log(data.data[i].value)
                    // $(\'#acct_desc\').append(\'<option value="\' + data.data[i].value +\'"> \'+ data.data[i].name + \'</option>\')
                    selectize2.addOption({value:data.data[i].value,text:data.data[i].name});

                }

            }
                
            },
            error: function (jqXHR, textStatus, errorThrown)
            {
            console.log( "<?php echo site_url($data_section.'/getdept/')?>/" + pos_desc);
                alert('Error get data from ajax');
                console.log(jqXHR);
                console.log(textStatus);
                console.log(errorThrown);
            }
        });
        
    });
</script>