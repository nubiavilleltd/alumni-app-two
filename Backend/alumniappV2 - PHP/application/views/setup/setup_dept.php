<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');
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
              Items List:- <small> List of System Items</small>
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
                        href="javascript:add_row()"
                        data-original-title="Add New Request"
                        data-toggle="tooltip"
                        data-placement="bottom"                            
                        class="btn btn-round btn-sm btn-primary"
                      >
                        <em class="icon ni ni-plus"></em>
                      </a>
                    </li>    
                    <li>
                    <a
                        href="javascript:delete_row()"
                        data-toggle="tooltip"
                        data-placement="bottom"
                        data-original-title="Delete Selected"
                        class="btn btn-round btn-sm btn-primary"
                      >
                        <em class="icon ni ni-trash"></em>
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
              <table
                id="example"
                class="nk-tb-list nk-tb-ulist nowrap table cell-border"
                style="width: 100%"
                data-export-title="Export"
              >
                <thead>
                  <tr class="nk-tb-item nk-tb-head">
                  
                  <th>Name</th>
                  <th>HOD</th>
				<th>Location</th>
                  <th></th>
                  <th></th>
                  </tr>
                </thead>
                <tbody></tbody>
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
                    <form action="#" id="form" class="form-validate row is-alter">
                        <input type="hidden" name="'.$this->security->get_csrf_token_name()
                            .'" value="'.$this->security->get_csrf_hash().'" /> 
                    <input type="hidden" value="" name="sno"/>
                    
                    <div class="col-md-12">
                        <div class="form-group"><label class="form-label">Location:</label>
                            <div class="form-control-wrap">
							<select name="expiration_action" class="chosen-select-no-results form-control">
                            
                            <option value="Nigeria">Nigeria</option>
                            <option value="Ghana">Ghana</option>                  
                             
                        </select> 
							
							</div>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group"><label class="form-label">Dept Name:</label>
                            <div class="form-control-wrap"><input type="text" name="deptname" 
                            class="form-control" placeholder="Enter Dept Name"></div>
                        </div>
                    </div>  
                    <div class="col-md-12">
                        <div class="form-group"><label class="form-label">HOD:</label>
                            <div class="form-control-wrap">'.$hod.'</div>                            
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
</section>        
';
?>