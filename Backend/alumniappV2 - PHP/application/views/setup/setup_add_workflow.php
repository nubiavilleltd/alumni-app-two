<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');
$location=($main_data->location)?$main_data->location:"Nigeria";
echo'
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
                Add Workflow Process
              </h4>
            </div>
            <!-- .nk-block-head-content -->
            <div class="nk-block-head-content">
              <div class="toggle-wrap nk-block-tools-toggle">
                <a
                  href="#"
                  class="btn btn-icon btn-trigger toggle-expand me-n1"
                  data-target="pageMenu"
                  ><em class="icon ni ni-more-v"></em
                ></a>
                <div class="toggle-expand-content" data-content="pageMenu">
                  <ul class="nk-block-tools g-3">
                    <li>
                      <a
                        href="javascript:document.setup_form.submit();"
                        data-toggle="tooltip" 
                        data-placement="bottom" title="" data-original-title="Save Form"
                        class="btn btn-round btn-sm btn-primary"
                      >
                        Add
                      </a>
                    </li>    
                    <li>
                    <a
                        href="'.site_url('Setup/view_workflow').'"
                        data-toggle="tooltip" 
                    data-placement="bottom" title="" data-original-title="Return"
                        class="btn btn-round btn-sm btn-primary"
                      >
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
            '.form_open('Setup/add_workflow/'.$form_action,array('name'=>'setup_form','class'=>'form-horizontal')).'
              <div class="card-inner">
                <div class="form-validate row is-alter">
                  <div class="col-md-12">
                      <input type="hidden" name="'.$this->security->get_csrf_token_name()
                          .'" value="'.$this->security->get_csrf_hash().'" />                    
                      <input type="hidden" value="'.$main_data->process_id.'" name="id"/> 
                  </div>    
                  <div class="col-md-6">
                      <div class="form-group"><label class="form-label">Request Title:</label>
                          <div class="form-control-wrap"><input name="request_title" type="text" class="form-control" 
                          value="'.$main_data->request_title.'" placeholder="Enter Title"></div>
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group"><label class="form-label">Request Type:</label>
                          <div class="form-control-wrap"><input name="request_type" type="text" class="form-control" 
                          value="'.$main_data->request_type.'" placeholder="Enter Type"></div>
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group"><label class="form-label">Request Form:</label>
                          <div class="form-control-wrap"><input name="request_form" type="text" class="form-control" 
                          value="'.$main_data->request_form.'" placeholder="Enter Form Name"></div>
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group"><label class="form-label">Request Table:</label>
                          <div class="form-control-wrap"><input name="request_table" type="text" class="form-control" 
                          value="'.$main_data->request_table.'" placeholder="Enter Table Name"></div>
                      </div>
                  </div>            
                  <div class="col-md-6">
                      <div class="form-group"><label class="form-label">Expiration Action:</label>
                          <div class="form-control-wrap">
                              <select name="expiration_action" class="form-select js-select2">
                                  <option value="'.$main_data->expiration_action.'">'.$main_data->expiration_action.'</option>
                                  <option value="Notify Admin">Notify Admin</option>
                                  <option value="Send Reminder">Send Reminder</option>                  
                                  <option value="Do Nothing">Do Nothing</option>   
                              </select>                        
                          </div>
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group"><label class="form-label">Wait Time (days):</label>
                          <div class="form-control-wrap"><input name="wait_time" type="text" class="form-control" 
                          value="'.$main_data->wait_time.'" placeholder="Wait Time"></div>
                      </div>
                  </div>                                       
                    <div class="col-md-6">
                      <div class="form-group"><label class="form-label">Location:</label>
                          <div class="form-control-wrap">
                              <select name="location" class="form-select js-select2">
                                  <option value="'.$location.'">'.$location.'</option>
                                  <option value="Ghana">Ghana</option>
                                  <option value="Nigeria">Nigeria</option>                  
                                
                              </select>                        
                          </div>
                      </div>
                  </div>
                </div>
                <div class="form-group" style="display: flex; align-items: right; justify-content: right;">
                  <button type="submit" class="btn btn-primary rounded btn-3d">Save</button>
                </div>        
              </div>
            </form>
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
</section>        
';
?>  