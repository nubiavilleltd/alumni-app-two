<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');
echo'
<section class="services-wrap">        
    <div class="container">
        <div class="ibox">
            <div class="ibox-title">
                <h5>Product List:- <small> List of Sales Product Price</small></h5>       
                <div class="ibox-tools">
                    <div class="ibox-tools">
                        <a href="javascript:add_row()" data-toggle="tooltip" data-placement="bottom" 
                            title="" data-original-title="Add New Item">
                            <i class="fa fa-plus"></i>
                        </a>   
                        <a href="javascript:delete_row()" data-toggle="tooltip" data-placement="bottom" 
                            title="" data-original-title="Delete Selected">
                            <i class="fa fa-trash-o"></i>
                        </a> 
                    </div>  
                </div>                
            </div>
            <div class="ibox-content">
                <div class="table-responsive">
                    <table id="example" class="display table table-hover table-striped"  width="100%">
                         <thead>
                            <tr>
                            
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
                         <tbody>
                         </tbody>
                    </table>
                </div>

                <div class="divide30"></div>
                <h5>Import Users List</h5><hr/>
                '.form_open_multipart('Setup/import/sales_product_price',array('name'=>'update_form','class'=>'form-horizontal')).'       
                    <div class="col-md-9">
                        <div class="form-group">
                            <label class="col-sm-4 control-label">Select Items list to import:</label>
                            <div class="col-sm-5"><input type="file" name="userfile"/></div>
                            <div class="col-sm-3"></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" name="login" class="btn btn-primary rounded btn-3d">Import</button>
                    </div>
                '.form_close().' 
                
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
                    <button type="button" class="close" 
                       data-dismiss="modal">
                           <span aria-hidden="true">&times;</span>
                           <span class="sr-only">Close</span>
                    </button>
                    <h4 class="modal-title">Modal title</h4>
                </div>

                <!-- Modal Body -->
                <div class="modal-body">
                    <input type="hidden" name="'.$this->security->get_csrf_token_name()
                        .'" value="'.$this->security->get_csrf_hash().'" />                    
                    <input type="hidden" value="" name="sno" id="sno"/> 
                    
                    <div class="col-md-12">
                        <div class="form-group"><label class="col-sm-4 control-label">Product Code:</label>
                            <div class="col-sm-8"><input name="product_code" type="text" class="form-control" 
                            placeholder="Enter Product Code"></div>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group"><label class="col-sm-4 control-label">Product Description:</label>
                            <div class="col-sm-8"><input type="text" name="prod_desc" 
                            class="form-control" placeholder="Enter Product Description"></div>
                        </div>
                    </div>   
                    <div class="col-md-12">
                        <div class="form-group"><label class="col-sm-4 control-label">Customer Type:</label>
                            <div class="col-sm-8"><input type="text" name="cust_type" 
                            class="form-control" placeholder="Enter Customer Type"></div>
                        </div>
                    </div>  
                    <div class="col-md-12">
                        <div class="form-group"><label class="col-sm-4 control-label">UOM:</label>
                            
                            <div class="col-sm-8">
                                <select class="selectval" name="uom"  id="uom">
                                    
                                    <option value="BT">BT</option>
									<option value="CS">CS</option>
                                        
                                </select>                            
                        </div>
                        </div>
                    </div>       
                    <div class="col-md-12">
                        <div class="form-group"><label class="col-sm-4 control-label">Unit Price:</label>
                            <div class="col-sm-8"><input type="text" name="unit_price" 
                            class="form-control" placeholder="Enter Unit Price"></div>
                        </div>
                    </div>                      
                    &nbsp;<div class="col-md-12"></div>
                </div>
                <!-- Modal Footer -->
                <div class="modal-footer">
                    <button type="button" id="btnSave" onclick="save()" class="btn btn-primary">Save</button>
                    <button type="button" class="btn btn-danger" data-dismiss="modal">Cancel</button>
                </div>             
            </div>
        </div>
    </div> 
    </form>
</section>        
';
?>        