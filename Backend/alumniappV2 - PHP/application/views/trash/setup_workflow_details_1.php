<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');

echo'
<section class="services-wrap">        
<div class="container">
    <div class="ibox">
        <div class="ibox-title">
            <h5>Edit Workflow Process</h5>
            <div class="ibox-tools">
                <a href="javascript:document.setup_form.submit();" data-toggle="tooltip" 
                data-placement="bottom" title="" data-original-title="Save changes">
                    <i class="fa fa-floppy-o"></i>
                </a>
                <a href="'.site_url('Setup/view_workflow').'" data-toggle="tooltip" 
                    data-placement="bottom" title="" data-original-title="Return">
                    <i class="fa fa-undo"></i>
                </a>                        
            </div>
        </div>
        <div class="ibox-content">
            '.form_open('Setup/add_workflow/edit/'.$main_data->process_id,
                    array('name'=>'setup_form','class'=>'form-horizontal')).'              
                <div class="col-md-12">
                    <input type="hidden" name="'.$this->security->get_csrf_token_name()
                        .'" value="'.$this->security->get_csrf_hash().'" />                    
                    <input type="hidden" value="'.$main_data->process_id.'" name="id"/> 
                </div> 
                
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Request Title:</label>
                        <div class="col-sm-8"><input name="request_title" type="text" class="form-control" 
                        value="'.$main_data->request_title.'" placeholder="Enter Title"></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Request Type:</label>
                        <div class="col-sm-8"><input name="request_type" type="text" class="form-control" 
                        value="'.$main_data->request_type.'" placeholder="Enter Type"></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Request Form:</label>
                        <div class="col-sm-8"><input name="request_form" type="text" class="form-control" 
                        value="'.$main_data->request_form.'" placeholder="Enter Form Name"></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Request Table:</label>
                        <div class="col-sm-8"><input name="request_table" type="text" class="form-control" 
                        value="'.$main_data->request_table.'" placeholder="Enter Table Name"></div>
                    </div>
                </div>            
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Expiration Action:</label>
                        <div class="col-sm-8">
                            <select name="expiration_action" class="chosen-select-no-results form-control">
                                <option value="'.$main_data->expiration_action.'">'.$main_data->expiration_action.'</option>
                                <option value="Notify Admin">Notify Admin</option>
                                <option value="Send Reminder">Send Reminder</option>                  
                                <option value="Do Nothing">Do Nothing</option>   
                            </select>                        
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Wait Time (days):</label>
                        <div class="col-sm-8"><input name="wait_time" type="text" class="form-control" 
                        value="'.$main_data->wait_time.'" placeholder="Wait Time"></div>
                    </div>
                </div>                         
                <div class="col-md-12 divide20"></div>                 
            </form>  
        </div>       
        
    </div>      
    
    <div class="ibox">
        <div class="ibox-title"><h5>Approvers List</h5>
            <div class="ibox-tools">
                <a href="javascript:add_row()" data-toggle="tooltip" data-placement="bottom" 
                    title="" data-original-title="Add New Approver"><i class="fa fa-plus"></i>
                </a>                   
                <a href="javascript:delete_row()" data-toggle="tooltip" data-placement="bottom" 
                title="" data-original-title="Delete Selected"><i class="fa fa-trash-o"></i>
                </a>                                    
            </div>
        </div>
            <div class="ibox-content">
                <div class="table-responsive">
                     <table id="example" class="display table table-hover table-striped"  width="100%">
                         <thead>
                           <tr>
                            <th>ID</th>
                            <th>Order</th>
                            <th>Name</th>
                            <th>Designation</th>
                            <th></th>
                            <th></th>
                           </tr>                           
                         </thead>
                         <tbody>
                         </tbody>
                       </table>
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
                        <input type="hidden" value="'.$data_var.'" name="process_id"/>                               
                        <div class="form-group showhide"><label class="col-sm-4 control-label">Preset Approver Name:</label>
                            <div class="col-sm-8">'.$approver_name.'</div>
                        </div>            
                        <div class="form-group"><label class="col-sm-4 control-label">Approver Function:</label>
                            <div class="col-sm-8">
                                <input type="text" name="appr_function" class="form-control" 
                                placeholder="Enter Designation"/>                           
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
</div>
</section>    
';
       