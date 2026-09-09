<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');
echo'
<section class="services-wrap">        
    <div class="container">
        <div class="ibox">
            <div class="ibox-title">
                <h5>All Customers:- <small> List of Customers setup in system</small></h5>    
                    <div class="ibox-tools">'.
//                        <a href="javascript:add_row()" data-toggle="tooltip" data-placement="bottom" 
//                            title="" data-original-title="Add New Customer">
//                            <i class="fa fa-plus"></i>
//                        </a>   
                        '<a href="javascript:delete_row()" data-toggle="tooltip" data-placement="bottom" 
                            title="" data-original-title="Delete Selected">
                            <i class="fa fa-trash-o"></i>
                        </a> 
                    </div>               
            </div>
                
            <div class="ibox-content">        
                <div class="table-responsive">
                    <table id="example" class="display table table-hover table-striped"  width="100%">
                         <thead>                      
                           <tr>
                             <th>ID</th>
                             <th>URN</th>
                             <th>Customer</th>
                             <th>Type</th>
                             <th>Category</th>
                             <th>Area</th>
                             <th>Market</th>
                             <th>Phone</th>
                             <th>Alt</th>
                             <th>Address</th>
                             <th>Contact</th>
                             <th>Designation</th>
                             <th>Distributor</th>
                             <th>Email</th>
                             <th>CustID</th>
                             <th></th>
                             <th></th>
                           </tr>                         
                         </thead>                        
                         <tbody>
                          </tbody>
                       </table>
                    </div>                                   
                 </div>
                <div class="divide30"></div>
                <h5>Import Customers List</h5><hr/>
                '.form_open_multipart('Setup/import/customers',array('name'=>'update_form','class'=>'form-horizontal')).'       
                    <div class="col-md-9">
                        <div class="form-group">
                            <label class="col-sm-4 control-label">Select customers list to import:</label>
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
                    <form action="#" id="form" class="form-horizontal">
                        <input type="hidden" name="'.$this->security->get_csrf_token_name()
                            .'" value="'.$this->security->get_csrf_hash().'" />                    
                        <input type="hidden" value="" name="id"/> 

                        <div class="col-md-6">
                            <div class="form-group"><label class="col-sm-3 control-label">URN:</label>
                                <div class="col-sm-9"><input name="cust_code" type="text" class="form-control" 
                                placeholder="Enter Customer URN"></div>
                            </div>
                        </div>              
                        <div class="col-md-6">
                            <div class="form-group"><label class="col-sm-3 control-label">Name:</label>
                                <div class="col-sm-9"><input name="cust_name" type="text" class="form-control" 
                                placeholder="Enter Customer Name"></div>
                            </div>
                        </div>                
                        <div class="col-md-6">
                            <div class="form-group"><label class="col-sm-3 control-label">Type</label>
                                <div class="col-sm-9">
                                    '.$cust_type.'
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group"><label class="col-sm-3 control-label">Category</label>
                                <div class="col-sm-9">
                                    '.$cust_category.'
                                </div>
                            </div>
                        </div>        
                        <div class="col-md-6">
                            <div class="form-group"><label class="col-sm-3 control-label">Adress:</label>
                                <div class="col-sm-9"><input name="cust_addr" type="text" class="form-control" 
                                placeholder="Enter Address"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group"><label class="col-sm-3 control-label">Location</label>
                                <div class="col-sm-9">'.$location.'</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group"><label class="col-sm-3 control-label">Market</label>
                                <div class="col-sm-9">'.$market.'</div>
                            </div>
                        </div>                        
                        <div class="col-md-6">
                            <div class="form-group"><label class="col-sm-3 control-label">Contact:</label>
                                <div class="col-sm-9"><input type="text" name="contact_person" 
                                class="form-control" placeholder="Enter Contact Person"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group"><label class="col-sm-3 control-label">Designation:</label>
                                <div class="col-sm-9"><input type="text" name="designation" 
                                class="form-control" placeholder="Enter Job Designation"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group"><label class="col-sm-3 control-label">Distributor:</label>
                                <div class="col-sm-9"><input type="text" name="distributor" 
                                class="form-control" placeholder="Enter Distributor"></div>
                            </div>
                        </div>                            
                        <div class="col-md-6">
                            <div class="form-group"><label class="col-sm-3 control-label">Phone:</label>
                                <div class="col-sm-9"><input type="text" name="phone_no"
                                class="form-control" data-mask="999-9999999999" 
                                placeholder="Enter Phone No - e.g. 234-8012345678"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group"><label class="col-sm-3 control-label">Alt Phone:</label>
                                <div class="col-sm-9"><input type="text" name="alt_phone"
                                class="form-control" data-mask="999-9999999999" 
                                placeholder="Enter Alt Phone No - e.g. 234-8012345678"></div>
                            </div>
                        </div>        
                        <div class="col-md-6">
                            <div class="form-group"><label class="col-sm-3 control-label">Email:</label>
                                <div class="col-sm-9"><input type="text" name="email" 
                                class="form-control" placeholder="Enter Email"></div>
                            </div>
                        </div>                         
                        <div class="col-md-6">
                            <div class="form-group"><label class="col-sm-4 control-label">Contact via:</label>
                                <div class="col-sm-8">
                                    <div class="checkbox checkbox-primary checkbox-inline">
                                        <input type="checkbox" name="contact_via" id="inlineCheckbox1" value="SMS">
                                        <label for="inlineCheckbox1">SMS</label>
                                    </div>
                                    <div class="checkbox checkbox-primary checkbox-inline">
                                        <input type="checkbox" name="contact_via" id="inlineCheckbox2" value="Email">
                                        <label for="inlineCheckbox2">Email</label>
                                    </div>                               
                                </div>
                            </div>
                        </div>  
                        <div class="form-group">
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
</section>        
';
?>        