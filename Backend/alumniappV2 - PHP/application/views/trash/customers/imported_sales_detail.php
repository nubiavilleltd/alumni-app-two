<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');

echo'
<section class="services-wrap">        
<div class="container">
    <div class="ibox">
        <div class="ibox-title">
            <h5>Sales Details:</h5>
            <div class="ibox-tools">
                <a href="'.site_url('customers/customers_list').'" data-toggle="tooltip" 
                    data-placement="bottom" title="" data-original-title="Return">
                    <i class="fa fa-undo"></i>
                </a>                        
            </div>
        </div>
        <div class="ibox-content">
            <fieldset class="form-horizontal">
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Date Registered:</label>
                        <div class="col-sm-8"><input name="date_registered" type="text" class="form-control" 
                       value="'.date("F j, Y").'" readonly="readonly"></div>
                    </div>
                </div>               
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Outlet UNID:</label>
                        <div class="col-sm-8"><input name="business_name" type="text" class="form-control" 
                        value="<generated when saved>" readonly></div>
                    </div>
                </div>              
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Business Name:</label>
                        <div class="col-sm-8"><input name="business_name" type="text" class="form-control" 
                        placeholder="Enter Business Name"></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Adress:</label>
                        <div class="col-sm-8"><input name="address"  type="text" class="form-control" 
                        placeholder="Enter Address"></div>
                    </div>
                </div>                  
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Outlet Type</label>
                        <div class="col-sm-8">
                            <select class="form-control">
                                <option>&lt;Select Outlet type&gt;</option>
                                <option>Café</option>
                                <option>Hotel</option>
                                <option>Key Account</option>
                                <option>Retail</option>    
                                <option>Wholesale</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Outlet Category</label>
                        <div class="col-sm-8">
                            <select class="form-control">
                                <option>&lt;Select Outlet Category&gt;</option>
                                <option>Platinium</option>
                                <option>Gold</option>
                                <option>Key AccountSilver</option>
                                <option>Bronze</option>    
                            </select>
                        </div>
                    </div>
                </div>          
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4  control-label">End Market</label>
                        <div class="col-sm-8"><input type="text" name="region"
                        class="form-control" placeholder="Enter End Market"></div>
                    </div>
                </div>                 
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4  control-label">Region</label>
                        <div class="col-sm-8"><input type="text" name="region"
                        class="form-control" placeholder="Enter Region"></div>
                    </div>
                </div> 
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4  control-label">Area</label>
                        <div class="col-sm-8"><input type="text" name="area"
                        class="form-control" placeholder="Enter Area"></div>
                    </div>
                </div>           
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Cluster</label>
                        <div class="col-sm-8"><input name="cluster"  type="text" class="form-control" 
                        placeholder="Enter Cluster"></div>
                    </div>
                </div>       
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Contact Person:</label>
                        <div class="col-sm-8"><input type="text" name="contact_person" 
                        class="form-control" placeholder="Enter Contact Person"></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Designation:</label>
                        <div class="col-sm-8"><input type="text" name="designation" 
                        class="form-control" placeholder="Enter Job Designation in Outlet"></div>
                    </div>
                </div>                
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Phone:</label>
                        <div class="col-sm-8"><input type="text" name="phone"
                        class="form-control" data-mask="999-9999999999" 
                        placeholder="Enter Phone No - e.g. 234-8012345678"></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Email:</label>
                        <div class="col-sm-8"><input type="text" name="email" 
                        class="form-control" placeholder="Enter Email"></div>
                    </div>
                </div>    
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Contact Outlet By:</label>
                        <div class="col-sm-8">
                            <div class="checkbox checkbox-primary checkbox-inline">
                                <input type="checkbox" name="contact_outlet" id="inlineCheckbox1" value="SMS">
                                <label for="inlineCheckbox1">SMS</label>
                            </div>
                            <div class="checkbox checkbox-primary checkbox-inline">
                                <input type="checkbox" name="contact_outlet" id="inlineCheckbox2" value="Email">
                                <label for="inlineCheckbox2">Email</label>
                            </div>                               
                        </div>
                    </div>
                </div>                       
            </fieldset>
            <div class="ibox-footer text-right">
            <button type="submit" class="btn btn-primary rounded btn-3d">Save</button>
            </div>
        </div>
    </div>        
</div>
</section>    
';
       