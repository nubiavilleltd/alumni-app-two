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
                 Product List:- <small> List of Sales Product Price</small>
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
                        data-original-title="Add New Item"
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
                style="width:100%"
                data-export-title="Export"
              >
                <thead>
                  <tr class="nk-tb-item nk-tb-head">
                        <th>Sno</th>
                          <th>Product Code</th>
                          <th>Product Description</th>
                          <th>Customer Type</th>
                          <th>UOM</th>
                          <th>Price</th>
                          <th></th>
                          <th></th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
              <hr class="preview-hr" />
              <div class="preview-title-lg overline-title">
                Import Users List
              </div>
              <hr class="preview-hr" />
              '.form_open_multipart('Setup/import/sales_product_price',array('name'=>'update_form','class'=>'form-horizontal')).'
              <div class="row">
                <div class="offset-lg-1 col-lg-2 mt-1">
                  Select Items list to import:
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
                      <div class="input-group-append">
                        <button
                          type="submit"
                          name="login"
                          class="btn btn-outline-primary btn-dim"
                        >
                          Import
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              '.form_close().'
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

    <!-- Modal -->
    <form action="#" id="form" class="form-horizontal">
    <div class="modal fade" id="modal_form" tabindex="-1" role="dialog" 
         aria-labelledby="myModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <!-- Modal Header -->
                <div class="modal-header">
            <h5 class="modal-title">Modal title</h5>
            <a
              href="#"
              class="close"
              data-bs-dismiss="modal"
              aria-label="Close"
            >
              <em class="icon ni ni-cross"></em>
            </a>
          </div>

                <!-- Modal Body -->
                <div class="modal-body">
                    <input type="hidden" name="'.$this->security->get_csrf_token_name()
                        .'" value="'.$this->security->get_csrf_hash().'" />                    
                    <input type="hidden" value="" name="sno" id="sno"/> 
                    
                    <div class="col-md-12">
                        <div class="form-group"><label class="form-label">Product Code:</label>
                            <div class="form-control-wrap"><input name="product_code" type="text" class="form-control" 
                            placeholder="Enter Product Code"></div>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group"><label class="form-label">Product Description:</label>
                            <div class="form-control-wrap"><input type="text" name="prod_desc" 
                            class="form-control" placeholder="Enter Product Description"></div>
                        </div>
                    </div>   
                    <div class="col-md-12">
                        <div class="form-group"><label class="form-label">Customer Type:</label>
                            <div class="form-control-wrap"><input type="text" name="cust_type" 
                            class="form-control" placeholder="Enter Customer Type"></div>
                        </div>
                    </div>  
                    <div class="col-md-12">
                        <div class="form-group"><label class="form-label">UOM:</label>
                            
                            <div class="form-control-wrap">
                                <select class="selectval" name="uom"  id="uom">
                                    
                                    <option value="BT">BT</option>
									<option value="CS">CS</option>
                                        
                                </select>                            
                        </div>
                        </div>
                    </div>       
                    <div class="col-md-12">
                        <div class="form-group"><label class="form-label">Unit Price:</label>
                            <div class="form-control-wrap"><input type="text" name="unit_price" 
                            class="form-control" placeholder="Enter Unit Price"></div>
                        </div>
                    </div>                      
                    &nbsp;<div class="col-md-12"></div>
                </div>
                <!-- Modal Footer -->
                <div class="modal-footer">
                    <button type="button" id="btnSave" onclick="save()" class="btn btn-primary">Save</button>
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
                </div>             
            </div>
        </div>
    </div> 
    </form>
</section>        
';
?>