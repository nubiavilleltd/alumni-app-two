<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');

echo'
<section class="services-wrap">        
    <div class="container">
        <div class="ibox">
            <div class="ibox-title">
                <h5>Customer Evaluation Details:</h5>
                <div class="ibox-tools">
                    <a href="'.site_url('Customers/Customers_list')
                        .'" data-toggle="tooltip" data-placement="bottom" title="" data-original-title="Return">
                        <i class="fa fa-undo"></i>
                    </a>                                                      
                </div>                
            </div>
            <div class="ibox-content">
                <fieldset class="form-horizontal">
                    <div class="col-md-4">
                        <div class="form-group"><label class="col-sm-4 control-label">URN:</label>
                            <div class="col-sm-8"><input name="cust_code" type="text" 
                            value="'.$cust_code.'" class="form-control white_bkgd" readonly="readonly"></div>
                        </div>
                    </div>              
                    <div class="col-md-4">
                        <div class="form-group"><label class="col-sm-4 control-label">Name:</label>
                            <div class="col-sm-8"><input name="cust_name" type="text" 
                            value="'.$cust_name.'" class="form-control white_bkgd" readonly="readonly"></div>
                        </div>
                    </div>                
                    <div class="col-md-4">
                        <div class="form-group"><label class="col-sm-4 control-label">Type</label>
                            <div class="col-sm-8"><input name="cust_type" type="text" 
                            value="'.$cust_type.'" class="form-control white_bkgd" readonly="readonly"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group"><label class="col-sm-4 control-label">Category</label>
                            <div class="col-sm-8"><input name="cust_category" type="text" 
                            value="'.$cust_category.'" class="form-control white_bkgd" readonly="readonly"></div>
                        </div>
                    </div>        
                    <div class="col-md-4">
                        <div class="form-group"><label class="col-sm-4 control-label">Adress:</label>
                            <div class="col-sm-8"><input name="cust_addr" type="text" 
                            value="'.$cust_addr.'" class="form-control white_bkgd" readonly="readonly"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group"><label class="col-sm-4 control-label">Location</label>
                            <div class="col-sm-8"><input name="location" type="text" 
                            value="'.$location.'" class="form-control white_bkgd" readonly="readonly"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group"><label class="col-sm-4 control-label">Contact:</label>
                            <div class="col-sm-8"><input type="text" name="contact_person" 
                            value="'.$contact_person.'" class="form-control white_bkgd" readonly="readonly"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group"><label class="col-sm-4 control-label">Designation:</label>
                            <div class="col-sm-8"><input type="text" name="designation" 
                            value="'.$designation.'" class="form-control white_bkgd" readonly="readonly"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group"><label class="col-sm-4 control-label">Email:</label>
                            <div class="col-sm-8"><input type="text" name="email" 
                            value="'.$email.'" class="form-control white_bkgd" readonly="readonly"></div>
                        </div>
                    </div>                            
                    <div class="col-md-4">
                        <div class="form-group"><label class="col-sm-4 control-label">Phone:</label>
                            <div class="col-sm-8"><input type="text" name="phone_no" 
                            value="'.$phone_no.'" class="form-control white_bkgd" readonly="readonly"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group"><label class="col-sm-4 control-label">Alt Phone:</label>
                            <div class="col-sm-8"><input type="text" name="alt_phone" 
                            value="'.$alt_phone.'" class="form-control white_bkgd" readonly="readonly"></div>
                        </div>
                    </div>                        
                    <div class="col-md-4">
                        <div class="form-group"><label class="col-sm-4 control-label">Contact via:</label>
                            <div class="col-sm-8"><input type="text" name="contact_via" 
                            value="'.$contact_via.'" class="form-control white_bkgd" readonly="readonly"></div>
                        </div>
                    </div> 
                 </fieldset> 
            </div>
        </div>        
        <div class="ibox">
            <div class="ibox-title"><h5>Sales Details</h5></div>
            <div class="ibox-content">
                <div class="table-responsive">
                     <table id="example" class="display table table-hover table-striped"  width="100%">
                         <thead>
                           <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Week</th>
                            <th>Month</th>
                            <th>SKU Code</th>
                            <th>SKU Name</th>
                            <th>Actual</th>
                            <th>Prorated</th>
                            <th>Target</th>
                            <th>Sellout</th>
                           </tr>  
                         </thead>
                         <tfoot>
                          
                         </tfoot>
                         <tbody>
                         </tbody>
                     </table>
                 </div>
            </div>     
        </div>
    </div>
</section>        
';
?>        