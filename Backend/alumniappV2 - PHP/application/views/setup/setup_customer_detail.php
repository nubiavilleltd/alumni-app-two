<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');
// made changes here
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
                                    Add Customer Process
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
                                                <a href="'.site_url('Setup/view_customer').'" class="btn btn-round btn-sm btn-primary">
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
                                <div class="card-inner">
                                '.form_open('Setup/add_customer/edit/'.$main_data->cust_code,array('name'=>'setup_form','class'=>'form-horizontal')).' 
                                    <input type="hidden" name="'.$this->security->get_csrf_token_name()
                                        .'" value="'.$this->security->get_csrf_hash().'" />                    
                                    <input type="hidden" value="'.$main_data->cust_code.'" name="id"/>
                                        <div class="row g-3 align-center">                                            
                                            <div class="col-lg-2">
                                                <div class="form-group">
                                                    <label class="form-label" for="site-name">Customer Name:</label>
                                                </div>
                                            </div>
                                            <div class="col-lg-4">
                                                <div class="form-group">
                                                    <div class="form-control-wrap">
                                                        <input type="text" value="'.$main_data->cust_name.'" class="form-control"
                                                            name="cust_name1" placeholder="Enter Name">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-2">
                                                <div class="form-group">
                                                    <label class="form-label" for="site-name">Customer Type:</label>
                                                </div>
                                            </div>
                                            <div class="col-lg-4">
                                                <div class="form-group">
                                                    <div class="form-control-wrap">
                                                        <select name="cust_type"
                                                            class="form-select js-select2">
                                                            <option value="'.$main_data->cust_type.'"> '.$main_data->cust_type.' </option>
                                                            <option value="B2B"> B2B </option>
                                                            <option value="E-Commerce"> E-Commerce </option>
                                                            <option value="EMPLOYEES"> EMPLOYEES </option>
                                                            <option value="KDA"> KDA </option>
                                                            <option value="MAINSTREAM"> MAINSTREAM </option>
                                                            <option value="MOD TRADE"> MOD TRADE</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-2">
                                                <div class="form-group">
                                                    <label class="form-label" for="site-name">Credit Limit:</label>
                                                </div>
                                            </div>
                                            <div class="col-lg-4">
                                                <div class="form-group">
                                                    <div class="form-control-wrap">
                                                        <input type="text" class="form-control"
                                                            name="credit_limit" value="'.$main_data->credit_limit.'" placeholder="Enter Type">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-2">
                                                <div class="form-group">
                                                    <label class="form-label" for="site-name">Credit Type:</label>
                                                </div>
                                            </div>
                                            <div class="col-lg-4">
                                                <div class="form-group">
                                                    <div class="form-control-wrap">
                                                        <select id="credit_type" name="credit_type"
                                                            class="form-select js-select2">
                                                            <option value="'.$main_data->credit_type.'"> '.$main_data->credit_type.' </option>
                                                            <option value="Cash">
                                                                Cash
                                                            </option>
                                                            <option value="Credit">
                                                                Credit
                                                            </option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>                                            
                                        </div>                                        
                                    </form>
                                </div>
                            </div>
                        </div>
                                                     
                        <div class="nk-block-head">
                            <div class="nk-block-between">
                                <div class="nk-block-head-content">
                                    <h4 class="title nk-block-title">
                                    Customer List </h4>
                                </div>
                                <!-- .nk-block-head-content -->
                                <div class="nk-block-head-content">
                                    <div class="toggle-wrap nk-block-tools-toggle">
                                        <a href="#" class="btn btn-icon btn-trigger toggle-expand me-n1"
                                            data-target="pageMenu"><em class="icon ni ni-more-v"></em></a>
                                        <div class="toggle-expand-content" data-content="pageMenu">                                                                                
                                            <ul class="nk-block-tools g-3">
                                                <li>
                                                    <a href="javascript:add_row()" data-toggle="tooltip" data-placement="bottom" 
                                                        title="" data-original-title="Add New Customer Location Detail" class="btn btn-round btn-sm btn-primary">
                                                        <em class="icon ni ni-plus"></em>
                                                    </a> 
                                                </li>
                                                <li>
                                                    <a href="javascript:delete_row()" class="btn btn-round btn-sm btn-danger" data-toggle="tooltip" data-placement="bottom" 
                                                        title="" data-original-title="Delete Selected">
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
                        <th>Sno</th>
                            <th>Customer Name</th>
                            <th>Customer Code</th>
                            <th>Store Address</th>
                            <th>Store</th>
                             <th>Store Location</th>
                            <th>Email</th>
                            <th></th>
                            <th></th>
                      </tr>
                    </thead>
                    <tbody></tbody>
                  </table> 
                </div>
            </div>
                    </div>
                    <!-- .components-preview -->
                </div>
            </div>
        </div>
    </div>   
    <!-- Modal Form   -->
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
                        <input type="hidden" value="" name="id"/> 
                        <input type="hidden" value="" name="sno"/>                               
                        <input type="hidden" name="cust_name" value="'.$main_data->cust_name.'" class="form-control" 
                                placeholder="Enter Name"/>                           
                            
                        
                                <input type="hidden"  value="'.$data_var.'" name="cust_code" class="form-control" 
                                placeholder="Enter Code"/>                           
                           
                        <div class="form-group"><label class="form-label">Store:</label>
                        <div class="form-control-wrap">
                            <input type="text" name="store" class="form-control" 
                            placeholder="Enter Store"/>                           
                        </div>
                    </div>   

                   
                    <div class="form-group name_store"><label class="form-label">Store Location  :</label>
                            <div class="form-control-wrap">
                                <input type="text" name="name_store" class="form-control" 
                                placeholder=""/>                           
                            </div>
                        </div>   
                                                        
                               
                        <div class="form-group"><label class="form-label">Email  :</label>
                            <div class="form-control-wrap">
                                <input type="text" name="email" class="form-control" 
                                placeholder="Send email to"/>                           
                            </div>
                        </div>   
                        
                        <div class="form-group address"><label class="form-label">Store Address  :</label>
                            <div class="form-control-wrap">
                                <textarea  name="address" class="form-control" 
                                placeholder="Enter Address">    </textarea>                       
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

</section>    

       
';
       