<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');
$location=($main_data->location)?$main_data->location:"Nigeria";
// made changs hee
echo'
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
                                Edit Workflow Process
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
                                                <a href="javascript:document.setup_form.submit();" class="btn btn-round btn-sm btn-primary">
                                                    Save
                                                </a>
                                            </li>
											
                                            <li>
                                                <a href="'.site_url('Setup/view_workflow').'" class="btn btn-round btn-sm btn-primary">
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
                                <input type="hidden" value="'.$main_data->process_id.'" id="id" name="id"/> 
                                            <div class="form-group">
                                                <label class="form-label" for="site-name">Request Title:</label>
                                            </div>
                                        </div>
                                        <div class="col-lg-4">
                                            <div class="form-group">
                                                <div class="form-control-wrap">
                                                    <input type="text" class="form-control" name="request_title" type="text"  
                                                    value="'.$main_data->request_title.'" placeholder="Enter Title">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-2">
                                            <div class="form-group">
                                                <label class="form-label" for="site-name">Request Type:</label>
                                            </div>
                                        </div>
                                        <div class="col-lg-4">
                                            <div class="form-group">
                                                <div class="form-control-wrap">
                                                    <input type="text" name="request_type" type="text" class="form-control" 
                                                    value="'.$main_data->request_type.'" placeholder="Enter Type"">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-2">
                                        <div class="form-group">
                                            <label class="form-label" for="site-name">Request Form:</label>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <div class="form-control-wrap">
                                                <input type="text" name="request_form" type="text" class="form-control" 
                                                value="'.$main_data->request_form.'" placeholder="Enter Type"">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-2">
                                    <div class="form-group">
                                        <label class="form-label" for="site-name">Request Table:</label>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <div class="form-control-wrap">
                                            <input type="text" name="request_table" type="text" class="form-control" 
                                            value="'.$main_data->request_table.'" placeholder="Enter Type"">
                                        </div>
                                    </div>
                                </div>
                                        <div class="col-lg-2">
                                            <div class="form-group">
                                                <label class="form-label" for="site-name">Expiration Action:</label>
                                            </div>
                                        </div>
                                        <div class="col-lg-4">
                                            <div class="form-group">
                                                <div class="form-control-wrap">
                                                    <select id="expiration_action" name="expiration_action"
                                                        class="form-select js-select2"  data-search="on">
                                                        <option value="'.$main_data->expiration_action.'">'.$main_data->expiration_action.'</option>
                                                    <option value="Notify Admin">Notify Admin</option>
                                                    <option value="Send Reminder">Send Reminder</option>                  
                                                    <option value="Do Nothing">Do Nothing</option>   
                                                    </select>
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
                                                    <select id="location" name="location"
                                                        class="form-select js-select2" data-search="on">
                                                        <option value="'.$location.'">'.$location.'</option>
                                                    <option value="Ghana">Ghana</option>
                                                     <option value="Nigeria">Nigeria</option> 
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-2">
                                            <div class="form-group">
                                                <label class="form-label" for="site-name">Active:</label>
                                            </div>
                                        </div>
                                        <div class="col-lg-4">
                                            <div class="form-group">
                                                <div class="form-control-wrap">
                                                    <select id="active" name="active"
                                                        class="form-select js-select2" data-search="on">
                                                        <option value="'.$main_data->active.'">'.$main_data->active.'</option>
                                                    <option value="Yes">Yes</option>
                                                     <option value="No">No</option> 
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-2">
                                            <div class="form-group">
                                                <label class="form-label" for="site-name">Wait Time (days):</label>
                                            </div>
                                        </div>
                                        <div class="col-lg-4">
                                            <div class="form-group">
                                                <div class="form-control-wrap">
                                                <input name="wait_time" type="text" class="form-control" 
                                                value="'.$main_data->wait_time.'" placeholder="Wait Time">
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
                                        <a href="javascript:add_row()" data-toggle="tooltip" data-placement="bottom" title="" data-original-title="Add New Approver"
                                            class="btn btn-round btn-sm btn-primary">
                                            <em class="icon ni ni-plus"></em>
                                        </a>                                       
                                  <!--    <a href="javascript:delete_row()"  data-toggle="tooltip" data-placement="bottom" title="" data-original-title="Delete Selected" class="btn btn-round btn-sm btn-primary">
                                            <em class="icon ni ni-trash"></em>
                                        </a>-->
                                    
                                    <a href="javascript:reset_approvers()" class="btn btn-round btn-sm btn-primary">
                                    Re-Order
                                </a>
                                    </div>
                                </div>

                                <hr class="preview-hr" />
                                <table id="example" class="display table table-hover table-striped"  width="100%">
                                    <thead>
                                        <tr>
                                    <th>ID</th>
                                    <th>Order</th>
                                    <th>Name</th>
                                    <th>Designation</th>
                                     <th>Send Notice to</th>
                                     <th>Approve Type</th>
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
               <input type="hidden" value="'.$data_var.'" name="process_id"/>                               
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
               <div class="form-group"><label class="form-label">Approver Type:</label>
               <div class="form-control-wrap">
                   <input type="text" name="approve_type" class="form-control" 
                   placeholder="Enter Type"/>                           
               </div>
           </div> 
             <div class="form-group"><label class="form-label">Send notice to :</label>
                   <div class="form-control-wrap">
                       <input type="text" name="notifier" class="form-control" 
                       placeholder="Send email to"/>                           
                   </div>
               </div>                           
           </form>
       </div>
       <!-- Modal Footer -->
       <div class="modal-footer">
           <button type="button" id="btnSave" onclick="save()" class="btn btn-primary">Save</button>
           <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
       </div>
   </div>
</div>
</div>


';
       