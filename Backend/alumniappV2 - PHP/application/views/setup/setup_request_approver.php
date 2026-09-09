<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');
$locationval = $this->ion_auth->user($main_data->requester_id)->row()->country;
$location = ($locationval && $locationval == "Ghana") ? $locationval : "Nigeria";
$locationdetails = $location == "Ghana" ? "Nigeria" : "Ghana";
$location_check = ($main_data->location) ? $main_data->location : $location;
// made changs hee
echo'
<style>
#loading-spinner {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(255, 255, 255, 0.8);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 999;
}

.spinner {
    
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}



</style>
<!-- content @s -->
<div class="nk-content nk-content-fluid">
    <div class="container-xl wide-xl">
        <div class="nk-content-inner">
            <div class="nk-content-body">
                <div class="components-preview">
                    <div class="nk-block-head nk-block-head-sm">
                        <div class="nk-block-between">
                            <div class="nk-block-head-content">
                                <h4 class="nk-block-title">
                                '.$newval->request_title.' Request :-'.$main_data->request_id.'
                                </h4>
                            </div>
                            <!-- .nk-block-head-content -->
                            <div class="nk-block-head-content">
                                <div class="toggle-wrap nk-block-tools-toggle">
                                    <a href="#" class="btn btn-icon btn-trigger toggle-expand me-n1"
                                        data-target="pageMenu"><em class="icon ni ni-more-v"></em></a>
                                    <div class="toggle-expand-content" data-content="pageMenu">
                                       
                                    </div>
                                </div>
                            </div>
                            <!-- .nk-block-head-content -->
                        </div>
                    </div>
                    <!-- .nk-block-head -->
                    <div class="nk-block nk-block-lg">
                        <div class="card card-bordered card-preview">
                            <div class="card-inner">
                            '.form_open('Setup/add_workflow/edit/'.$main_data->process_id,
                            array('name'=>'setup_form','class'=>'form-horizontal')).'              
                            <div class="col-md-12">
                                    <div class="row g-3 align-center">
                                        <div class="col-lg-2">
                                                    <input type="hidden" name="'.$this->security->get_csrf_token_name()
                                    .'" value="'.$this->security->get_csrf_hash().'" />                    
                                <input type="hidden" value="'.$main_data->request_id.'" id="id" name="id"/> 
                                            <div class="form-group">
                                                <label class="form-label" for="site-name">Date Initiated:</label>
                                            </div>
                                        </div>
                                        <div class="col-lg-4">
                                            <div class="form-group">
                                                <div class="form-control-wrap">
                                                <input type="text" name="date_initiaited" class="form-control"
                                                value="' . date("M j, Y g:i A", strtotime($main_data->request_date)) . '" readonly="readonly"
                                                    id="date_initiaited" readonly />
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-2">
                                            <div class="form-group">
                                                <label class="form-label" for="site-name">Initiated By:</label>
                                            </div>
                                        </div>
                                        <div class="col-lg-4">
                                            <div class="form-group">
                                                <div class="form-control-wrap">
                                                <input type="text"  value="' . $this->ion_auth->user($main_data->requester_id)->row()->fullname . '"  readonly="readonly"
                                                class="form-control" name="initiated_by" id="initiated_by" />
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-2">
                                        <div class="form-group">
                                            <label class="form-label" for="site-name">Email:</label>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <div class="form-control-wrap">
                                            <input type="text" name="email" class="form-control" id="email"  value="' . $this->ion_auth->user($main_data->requester_id)->row()->email . '"
                                                       
                                            readonly/>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-2">
                                    <div class="form-group">
                                        <label class="form-label" for="site-name">Location:</label>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <div class="form-control-wrap">
                                            <input type="text" name="request_table" type="text" class="form-control" 
                                            value="'.$location_check.'" placeholder="Enter Type"">
                                        </div>
                                    </div>
                                </div>
                                   
                                <div class="col-lg-2">
                                <div class="form-group">
                                    <label class="form-label" for="site-name">Request Status:</label>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <div class="form-control-wrap">
                                        <input type="text" name="request_table" type="text" class="form-control" 
                                        value="'.$main_data->request_status.'" placeholder="Enter Type"">
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-2">
                            <div class="form-group">
                                <label class="form-label" for="site-name">Next Approver:</label>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="form-group">
                                <div class="form-control-wrap">
                                    <input type="text" name="request_table" type="text" class="form-control" 
                                    value="'.$main_data->next_approver.'" placeholder="Enter Type"">
                                </div>
                            </div>
                        </div>     
                      
                                       
                                    </div>
                                    </div>
                                </form>
                                <hr class="preview-hr" />
                                <div style="display: flex; justify-content: space-between; align-items: center">
                                    <div class="preview-title-lg overline-title">Approvers List</div>
                                    <div>
                                        <a href="'.site_url('requests/request_form/').''.$main_data->request_type.'/'.$main_data->request_id.'" target="_blank" data-toggle="tooltip" data-placement="bottom" title="" data-original-title="Add New Approver"
                                            class="btn btn-round btn-sm btn-primary">
                                            <em class="icon ni ni-eye">View </em>
                                        </a>                                       
                                        <a href="javascript:delete_row()"  data-toggle="tooltip" data-placement="bottom" title="" data-original-title="Delete Selected" class="btn btn-round btn-sm btn-danger">
                                            <em class="icon ni ni-trash"></em>
                                        </a>
                                    </div>
                                </div>

                                <hr class="preview-hr" />
                                <table id="example" class="display table table-hover table-striped"  width="100%">
                                    <thead>
                                        <tr>
                                    <th>ID</th>
                                    <th>Order</th>
                                    <th>Order</th>
                                    <th>Next Approver</th>
                                    <th>Name</th>
                                    <th>Designation</th>
                                   
                                     <th>Next Approver</th>
                                     <th>Approver Status</th>
                                     
                                    <th></th>
                                    <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        

                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <!-- .card-preview -->
                    </div>
                    <!-- nk-block -->
                </div>
                <!-- .components-preview -->
            </div>
        </div>
    </div>
</div>
<!-- Modal Form -->

<div class="modal fade" id="modal_form" tabindex="-1" role="dialog" 
aria-labelledby="myModalLabel" aria-hidden="true">
<div class="modal-dialog">
   <div class="modal-content">
       <!-- Modal Header -->
       <div class="modal-header">
        <h5 class="modal-title">Modal Title</h5>
        <a href="#" class="close" data-bs-dismiss="modal" aria-label="Close">
            <em class="icon ni ni-cross"></em>
        </a>
    </div>
       <!-- Modal Body -->
       <div class="modal-body">
           <form action="#" id="form" class="form-horizontal row">
               <input type="hidden" name="'.$this->security->get_csrf_token_name()
                   .'" value="'.$this->security->get_csrf_hash().'" />                    
               <input type="hidden" value="" name="id"/> 
               <input type="hidden" value="'.$data_var.'" id="request_id" name="request_id"/>   
               <input type="hidden" value="'.$main_data->process_id.'" name="process_id"/>  
               <input type="hidden" value="'.$main_data->order_no.'" id="order_no" name="order_no"/>                               
  
               <input type="hidden" value="'.$main_data->next_appr.'" id="next_appr" name="next_appr"/>                               
               <input type="hidden" id="appr_name2"  name="appr_name2" class="form-control" 
               placeholder="Enter Designation"/>   
               <div class="form-group"><label class="form-label">Specify Approver from:</label>
                   <div class="form-control-wrap">
                       <select class="selectval" name="approver_from"  id="approver_from">
                           <option value="">'.(empty($approver_from)?
                           '--Select Approver from --':$approver_from).'</option>
                           <option value="Initiator">Initiator</option>
                           <option value="Preset1">Line Manager</option>
                           <option value="Preset3">Line Manager\'s Manager</option>
                           <option value="Preset2">Departmental Director</option>
                           <option value="Approver">Preset Approver</option>
                       </select>                            
                   </div>
               </div>                                  
               <div class="form-group showhide"><label class="form-label">Preset Approver Name:</label>
                   <div class="form-control-wrap">'.$approver_name.'</div>
               </div>            
               <div class="form-group"><label class="form-label">Approver Function:</label>
                   <div class="form-control-wrap">
                       <input type="text" name="appr_function" class="form-control" 
                       placeholder="Enter Designation"/>     
                                         
                   </div>
               </div>    
                                       
           </form>
        

       </div>
       <!-- Modal Footer -->
       <div class="modal-footer">
           <button type="button" id="btnSave" onclick="save()" class="btn btn-primary btnSave">Save</button>
           <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
       </div>
   </div>
</div>
</div>


';
       