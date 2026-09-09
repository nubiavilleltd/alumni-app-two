<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');

echo'
<section class="services-wrap">        
<div class="container">
    '.form_open_multipart('Customers/import_sales_volume/'.$form_action,array('name'=>'register_form','class'=>'form-horizontal')).'   
        <div class="ibox">
            <div class="ibox-title">
                <h5>'.$page_title.':</h5>
                <div class="ibox-tools">
                    <a href="javascript:document.register_form.submit();" data-toggle="tooltip" 
                    data-placement="bottom" title="" data-original-title="Submit Form">
                        <i class="fa fa-floppy-o"></i>
                    </a>
                    <a href="'.site_url('customers/sales_volume').'" data-toggle="tooltip" 
                        data-placement="bottom" title="" data-original-title="Return">
                        <i class="fa fa-undo"></i>
                    </a>                        
                </div>
            </div>
            <div class="ibox-content">
            <div class="col-md-12">                  
                <input type="hidden" value="'.$main_data->sales_id.'" name="id"/> 
            </div>              
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Import Date:</label>
                        <div class="col-sm-8"><input name="import_date" type="text" class="form-control white_bkgd" 
                        value="'.date("F j, Y").'" readonly="readonly"></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Imported By:</label>
                        <div class="col-sm-8"><input name="imported_by" type="text" class="form-control white_bkgd" 
                        value="'.$current_user->first_name.' '.$current_user->last_name.'" readonly="readonly"></div>
                    </div>
                </div>    
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Period (Week):</label>
                        <div class="col-sm-8">
                            <select class="chosen-select-no-results form-control" name="period_week" 
                                data-placeholder="Select Week" id="period_week" >
                               <option value=>-- Select Week  --</option>
                               <option value="1"'.($main_data->period_week==1?' selected="selected"':'').'>Week 1</option>
                               <option value="2"'.($main_data->period_week==2?' selected="selected"':'').'>Week 2</option>
                               <option value="3"'.($main_data->period_week==3?' selected="selected"':'').'>Week 3</option>
                               <option value="4"'.($main_data->period_week==4?' selected="selected"':'').'>Week 4</option>
                               <option value="5"'.($main_data->period_week==5?' selected="selected"':'').'>Week 5</option>
                               </select>                      
                        </div>
                    </div>
                </div>                 
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Period(Month):</label>
                        <div class="col-sm-8"><input id="choosemnth" name="period_month" type="text" 
                        class="form-control"  value="'.$main_data->fullmonth.'" placeholder="Period (mm-yyyy)"></div>
                    </div>
                </div>                    
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4  control-label">Market</label>
                        <div class="col-sm-8">'.$market.'</div>
                    </div>
                </div>                       
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4  control-label">Customer Type</label>
                        <div class="col-sm-8">'.$cust_type.'</div>
                    </div>
                </div>                                                
                <div class="col-md-6">
                    <div class="form-group"><label id="salesFile" class="col-sm-4 control-label">
                        Attach sales File:</label><div class="col-sm-8">
                        <input type="file" name="salesfile" id="salesfile" class="form-control"></div>
                    </div>
                </div>    
                <div class="col-md-6">
                    <div class="form-group"><label id="targetFile" class="col-sm-4 control-label">
                        Attach Target File:</label><div class="col-sm-8">
                        <input type="file" name="targetfile" id="targetfile" class="form-control"></div>
                    </div>
                </div>  
                <div class="col-md-6">
                    <div class="form-group"><label id="selloutFile" class="col-sm-4 control-label">
                        Attach Sellout File:</label><div class="col-sm-8">
                        <input type="file" name="selloutfile" id="selloutfile" class="form-control"></div>
                    </div>
                </div>    
                <div class="col-md-6" id="kpiDiv">
                    <div class="form-group"><label id="kpiFile" class="col-sm-4 control-label">
                        Attach KPI File:</label><div class="col-sm-8">
                        <input type="file" name="kpifile" id="kpifile" class="form-control"></div>
                    </div>
                </div>        
            </div>
            <div class="ibox-footer col-md-12 text-right">
            <input type="hidden" name="figures_type" id="actual" value="Actual">
                <button type="submit" class="btn btn-primary rounded btn-3d">Submit</button>
            </div>
        </div>    
    '.form_close().'     
</div>
</section>    
';
?> 
