<?php defined('BASEPATH') or exit('No direct script access allowed');
$this->load->view('home/splash2');
$record = ($form_action == "add") ? $rand : $batchcode;
$editmode=TRUE;
echo '
<style>

                    /* .btn2:hover {
                        -webkit-transform: scale(1.1);
                        -moz-transform: scale(1.1);
                        -o-transform: scale(1.1);
                    }
                    .btn2 {
                        -webkit-transform: scale(0.8);
                        -moz-transform: scale(0.8);
                        -o-transform: scale(0.8);
                        -webkit-transition-duration: 0.5s;
                        -moz-transition-duration: 0.5s;
                        -o-transition-duration: 0.5s;
                    } */
                    </style>   
<section class="services-wrap">        
       
    <div class="nk-content nk-content-fluid">
        <div class="container-xl wide-xl">
            <div class="nk-content-inner">
                <div class="nk-content-body">
                    <div class="components-preview">
                        <div class="nk-block-head nk-block-head-sm">
                            <div class="nk-block-between">
                                <div class="nk-block-head-content">
                                    <h4 class="nk-block-title">
                                        Add POS Batch Process
                                    </h4>
                                </div>
                                <!-- .nk-block-head-content -->
                                <div class="nk-block-head-content">
                                    <div class="toggle-wrap nk-block-tools-toggle">
                                        <a href="#" class="btn btn-icon btn-trigger toggle-expand me-n1"
                                            data-target="pageMenu"><em class="icon ni ni-more-v"></em></a>
                                        <div class="toggle-expand-content" data-content="pageMenu">
                                            <ul class="nk-block-tools g-3">                                               
                                                <li>
                                                    <a href="' . site_url('Setup/view_pos_batch') . '" class="btn btn-round btn-sm btn-primary">
                                                        Return
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <!-- .nk-block-head-content -->
                            </div>
                        </div>
                        <div class="nk-block nk-block-lg">
                            <div class="card card-bordered card-preview">
                                ' . form_open_multipart('Setup/add_pos_item/' . $form_action, array('name' => 'setup_form', 'class' => 'form-horizontal')) .'
                                <div class="card-inner row">                                    
                                    <input type="hidden" name="'.$this->security->get_csrf_token_name()
                                        .'" value="'.$this->security->get_csrf_hash().'" />                    
                                        <input type="hidden" value="' . $main_data->batchcode . '" name="id"/>
                                        <!-- <div class="form-validate row is-alter"> -->
                                            <div class="col-md-6">
                                                <div class="form-group"><label class="form-label">Batch Code:</label>
                                                    <div class="form-control-wrap"><input name="batchcode" type="text" class="form-control" 
                                                    value="' . $record . '" placeholder="Enter Code" readonly="readonly"></div>
                                                </div>
                                            </div>   
                                            <div class="col-md-6">
                                                <div class="form-group"><label class="form-label">Procurement Code:</label>
                                                    <div class="form-control-wrap"><input name="procurementcode" type="text" class="form-control" 
                                                    value="' . $main_data->procurementcode . '" placeholder="Enter Code"></div>
                                                </div>
                                            </div>
                                           
                                        <div class="col-sm-6">
                                                            <div class="form-group"><label class="form-label">Request Date</label>
                                                                <div class="form-control-wrap"><input name="request_date" id="request_date" type="text" 
                                                                class="form-control white_bkgd '.($editmode?'datepicker2':'').'" 
                                                                value="'.($main_data->request_date=='0000-00-00'?date(" jS \of F Y h:i ") :date("d M, Y h:i A")).'" ></div> 
                                                            </div>
                                                        </div>   
                                            
                                            <div class="col-md-6">
                                                <div class="form-group"><label class="form-label">Location:</label>
                                                    <div class="form-control-wrap"><input name="location" type="text" class="form-control" 
                                                    value="' . $main_data->location . '" placeholder="Enter Location"></div>
                                                </div>
                                            </div>                                            
                                        <!-- </div>                                                                                                             -->
                                        <hr class="preview-hr" />
                                        <div class="preview-title-lg overline-title">
                                            Add POS Order List
                                        </div>
                                        <hr class="preview-hr" />                  
                                    
                                        <div class="offset-lg-1 col-lg-2 mt-1">
                                            Select Items list
                                        </div>
                                        <div class="col-lg-8">
                                        <div class="form-control-wrap">
                                            <div class="input-group">
                                            <div class="form-file">
                                                <input
                                                type="file"
                                                name="userfile"
                                                class="form-file-input"
                                                id="inputGroupFile04"
                                                />
                                                <label
                                                class="form-file-label"
                                                for="inputGroupFile04"
                                                >Choose file</label
                                                >
                                            </div>                          
                                            </div>
                                        </div>
                                        </div>
                                        <div style="display: flex; align-items: end; justify-content: end;">
                                            <button type="submit" name="login" class="btn btn btn-primary rounded btn-3d">SAVE</button>
                                        </div>
                                    </div>                                                                   
                                </form>
                                            
                            </div>
                        </div>
                    </div>
                    <!-- .components-preview -->
                </div>
            </div>
        </div>
    </div>     

 <!-- Modal -->
 <div class="modal fade" id="modal_form" tabindex="-1" role="dialog" 
      aria-labelledby="myModalLabel" aria-hidden="true">
     <div class="modal-dialog">
         <div class="modal-content">
             <!-- Modal Header -->
             <div class="modal-header">
                 <button type="button" class="close" 
                    data-dismiss="modal">
                        <span aria-hidden="true">&times;</span>
                       <span class="sr-only">Close</span>
                 </button>
                 <h4 class="modal-title">Modal title</h4>
             </div>
             <!-- Modal Body -->
             <div class="modal-body">
                 <form action="#" id="form" class="form-horizontal">
                     <input type="hidden" name="'.$this->security->get_csrf_token_name()
                         .'" value="'.$this->security->get_csrf_hash().'" />                    
                     <input type="hidden" value="" name="id"/> 
                     <input type="hidden" value="" name="sno"/>                               
                         <input type="hidden"  value="'.$record.'" name="batchcode" class="form-control" 
                             placeholder="Enter Code"/>   
                                                     
                        
                     <div class="form-group"><label class="form-label">Item Type:</label>
                     <div class="form-control-wrap">
                         <input type="text" name="pos" class="form-control" 
                         placeholder="Enter Type"/>                           
                     </div>
                 </div>   

                   
                                                 
                            
                     <div class="form-group"><label class="form-label">POS Description  :</label>
                         <div class="form-control-wrap">
                             <input type="text" name="pos_desc" class="form-control" 
                             placeholder="POS Description"/>                           
                         </div>
                     </div>  
                     <div class="form-group"><label class="form-label">Quantity  :</label>
                         <div class="form-control-wrap">
                             <input type="text" name="qty" class="form-control" 
                             placeholder="Quantity"/>                           
                         </div>
                     </div>                        
                 </form>
             </div>
             <!-- Modal Footer -->
             <div class="modal-footer">
                 <button type="button" id="btnSave" onclick="save()" class="btn btn-primary">Save</button>
                 <button type="button" class="btn btn-danger" data-dismiss="modal">Cancel</button>
             </div>
         </div>
     </div>
 </div>
</section>    


';
echo "<script src='http://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js'>\n";
echo"
<?php ?>

$('#login').on('click', function() {
$('#login').text('importing...'); //change button text
$('#login').attr('disabled',true)
console.log('here');
});



</script>
<?php ?>";