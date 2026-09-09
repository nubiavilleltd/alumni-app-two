<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');

echo'
<section class="services-wrap">        
<div class="container">
    <div class="ibox">
        <div class="ibox-title">
            <h5>Add Customer Process</h5>
            <div class="ibox-tools">
                <a href="javascript:document.setup_form.submit();" data-toggle="tooltip" 
                data-placement="bottom" title="" data-original-title="Add New Customer">
                    <i class="fa fa-floppy-o"></i>
                </a>
                <a href="'.site_url('Setup/view_customer').'" data-toggle="tooltip" 
                    data-placement="bottom" title="" data-original-title="Return">
                    <i class="fa fa-undo"></i>
                </a>                        
            </div>
        </div>
        <div class="ibox-content">
             
                    '.form_open('Setup/add_customer/edit/'.$main_data->cust_code,
                    array('name'=>'setup_form','class'=>'form-horizontal')).'              
                <div class="col-md-12">
                    <input type="hidden" name="'.$this->security->get_csrf_token_name()
                        .'" value="'.$this->security->get_csrf_hash().'" />                    
                    <input type="hidden" value="'.$main_data->cust_code.'" name="id"/> 
                </div>            
               
                
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Customer Name:</label>
                        <div class="col-sm-8"><input name="cust_name1" type="text" class="form-control" 
                        value="'.$main_data->cust_name.'" placeholder="Enter Name"></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Customer Code:</label>
                        <div class="col-sm-8"><input name="cust_code" type="text" class="form-control" 
                        value="'.$main_data->cust_code.'" placeholder="Customer Code"></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Customer Type:</label>
                        <div class="col-sm-8"><input name="cust_type" type="text" class="form-control" 
                        value="'.$main_data->cust_type.' " placeholder="Enter Type"></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Credit Limit:</label>
                        <div class="col-sm-8"><input name="credit_limit" type="text" class="form-control" 
                        value="'.$main_data->credit_limit.' " placeholder="Enter Type"></div>
                    </div>
                </div>            
               
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Credit Type:</label>
                        <div class="col-sm-8"><input name="credit_type" type="text" class="form-control" 
                        value="'.$main_data->credit_type.' " placeholder="Credit Type"></div>
                    </div>
                </div>                         
                <div class="col-md-12 divide20"></div>                 
            </form>  
        </div>       
        
    </div>      
    
    <div class="ibox">
        <div class="ibox-title"><h5>Customer List</h5>
            <div class="ibox-tools">
                <a href="javascript:add_row()" data-toggle="tooltip" data-placement="bottom" 
                    title="" data-original-title="Add New Customer Location Detail"><i class="fa fa-plus"></i>
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
                            <th>Sno</th>
                            <th>Customer Name</th>
                            <th>Customer Code</th>
                            <th>Store</th>
                             <th>Store Name to</th>
                            <th>Email</th>
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
                        <input type="hidden" value="" name="sno"/>                               
                                                         
                                 
                        <div class="form-group d-none hideDiv"><label class="col-sm-4 control-label">Customer Name:</label>
                            <div class="col-sm-8">
                                <input type="hidden" name="cust_name" value="'.$main_data->cust_name.'" class="form-control" 
                                placeholder="Enter Name"/>                           
                            </div>
                        </div> 
                        <div class="form-group d-none"><label class="col-sm-4 control-labeld-none ">Customer Code:</label>
                            <div class="col-sm-8">
                                <input type="hidden"  value="'.$data_var.'" name="cust_code" class="form-control" 
                                placeholder="Enter Code"/>                           
                            </div>
                        </div>
                        <div class="form-group"><label class="col-sm-4 control-label">Store:</label>
                        <div class="col-sm-8">
                            <input type="text" name="store" class="form-control" 
                            placeholder="Enter Store"/>                           
                        </div>
                    </div>   

                      <div class="form-group d-none"><label class="col-sm-4 control-label">Store Name  :</label>
                            <div class="col-sm-8">
                                <input type="hidden" name="name_store" class="form-control" 
                                placeholder="Enter Store Name"/>                           
                            </div>
                        </div>     
                        <div class="form-group"><label class="col-sm-4 control-label">Email  :</label>
                            <div class="col-sm-8">
                                <input type="text" name="email" class="form-control" 
                                placeholder="Send email to"/>                           
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
       