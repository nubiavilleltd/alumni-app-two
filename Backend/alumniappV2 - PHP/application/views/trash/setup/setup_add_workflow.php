<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');

echo'
<section class="services-wrap">        
<div class="container">
    <div class="ibox">
        '.form_open('Setup/add_workflow/'.$form_action,array('name'=>'setup_form','class'=>'form-horizontal')).'      
        <div class="ibox-title">
            <h5>Add Workflow Process</h5>
            <div class="ibox-tools">
                <a href="javascript:document.setup_form.submit();" data-toggle="tooltip" 
                data-placement="bottom" title="" data-original-title="Save Form">
                    <i class="fa fa-floppy-o"></i>
                </a>
                <a href="'.site_url('Setup/view_workflow').'" data-toggle="tooltip" 
                    data-placement="bottom" title="" data-original-title="Return">
                    <i class="fa fa-undo"></i>
                </a>                        
            </div>
        </div>
        <div class="ibox-content">
            <div class="col-md-12">
                <input type="hidden" name="'.$this->security->get_csrf_token_name()
                    .'" value="'.$this->security->get_csrf_hash().'" />                    
                <input type="hidden" value="'.$main_data->process_id.'" name="id"/> 
            </div>         
            <div class="col-md-6">
                <div class="form-group"><label class="col-sm-4 control-label">End Market:</label>
                    <div class="col-sm-8">'.$market.'</div>
                </div>
             </div>             
            <div class="col-md-6">
                <div class="form-group"><label class="col-sm-4 control-label">Process Name:</label>
                    <div class="col-sm-8"><input name="process_name" type="text" 
                    class="form-control white_bkgd" readonly="readonly"
                    value="Winners List Approval" placeholder="Process Name" ></div>
                </div>
            </div>                           
            <div class="col-md-6">
                <div class="form-group"><label class="col-sm-4 control-label">Expiration Action:</label>
                    <div class="col-sm-8">
                        <select name="expiration_action" class="chosen-select-no-results form-control">
                            <option value="'.$main_data->expiration_action.'">'.$main_data->expiration_action.'</option>
                            <option value="Reroute to Alternate Approver">Reroute to Alternate Approver</option>
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
        </div>
        <div class="col-md-12 ibox-footer text-right">
            <button type="submit" class="btn btn-primary rounded btn-3d">Save</button>
        </div>
        </form>        
    </div>        
</div>
</section>    
';
       