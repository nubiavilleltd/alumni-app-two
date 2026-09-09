<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');

echo'
<section class="services-wrap">        
    <div class="container">
        <div class="ibox">
            <div class="ibox-title">
                <h5>Customer Sales Details:-<small>'.$customer->cust_name.' ('.$customer->cust_code.')</small></h5>
                <div class="ibox-tools">
                    <a href="'.site_url('Customers/view_page/sales/'.$customer->sales_id)
                        .'" data-toggle="tooltip" data-placement="bottom" title="" data-original-title="Return">
                        <i class="fa fa-undo"></i>
                    </a>                                                      
                </div>                
            </div>
            <div class="ibox-content">
                <fieldset class="form-horizontal">         
                    <div class="col-md-4">
                        <div class="form-group"><label class="col-sm-3 control-label">Type</label>
                            <div class="col-sm-9"><input name="cust_type" type="text" 
                            value="'.$customer->cust_type.'" class="form-control white_bkgd" readonly="readonly"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group"><label class="col-sm-3 control-label">Band</label>
                            <div class="col-sm-9"><input name="cust_category" type="text" 
                            value="'.$customer->cust_category.'" class="form-control white_bkgd" readonly="readonly"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group"><label class="col-sm-3 control-label">Location</label>
                            <div class="col-sm-9"><input name="location" type="text" 
                            value="'.$customer->location.'" class="form-control white_bkgd" readonly="readonly"></div>
                        </div>
                    </div>        
                    <div class="col-md-4">
                        <div class="form-group"><label class="col-sm-3 control-label">Sales:</label>
                            <div class="col-sm-9"><input type="text" name="sales" 
                            value="'.$customer->total_sales.'" class="form-control white_bkgd" readonly="readonly"></div>
                        </div>
                    </div>       
                    <div class="col-md-4">
                        <div class="form-group"><label class="col-sm-3 control-label">Target:</label>
                            <div class="col-sm-9"><input type="text" name="target" 
                            value="'.$customer->total_target.'" class="form-control white_bkgd" readonly="readonly"></div>
                        </div>
                    </div>      
                    <div class="col-md-4">
                        <div class="form-group"><label class="col-sm-3 control-label">Sellout:</label>
                            <div class="col-sm-9"><input type="text" name="sellout" 
                            value="' . $customer->total_sellout. '" class="form-control white_bkgd" readonly="readonly"></div>
                        </div>
                    </div>            
                    <div class="col-md-4">
                        <div class="form-group"><label class="col-sm-3 control-label">Percent:</label>
                            <div class="col-sm-9"><input type="text" name="percent" 
                            value="'.$customer->percent_target.'" class="form-control white_bkgd" readonly="readonly"></div>
                        </div>
                    </div>                      
                    <div class="col-md-4">
                        <div class="form-group"><label class="col-sm-3 control-label">Week:</label>
                            <div class="col-sm-9"><input type="text" name="Week" 
                            value="'.$customer->period_week.'" class="form-control white_bkgd" readonly="readonly"></div>
                        </div>
                    </div>  
                    <div class="col-md-4">
                        <div class="form-group"><label class="col-sm-3 control-label">Month:</label>
                            <div class="col-sm-9"><input type="text" name="month" 
                            value="'.$customer->fullmonth.'" class="form-control white_bkgd" readonly="readonly"></div>
                        </div>
                    </div>  
                    <div class="col-md-12">
                    '.($customer->cust_type==="Urban Wholesaler"?'
                        <div class="form-group"><label class="col-sm-1 control-label">KPIs:</label>
                            <div class="col-sm-11"><input type="text" name="kpi" 
                            value="Warehouse Access:'.($customer->warehouse_access=="Yes"?"Yes":"NA")
                                .', BAT Brands Display:'.($customer->correct_display=="Yes"?"Yes":"NA") 	
                                .', Compt. Comm. Display:'.($customer->tca_comms=="Yes"?"Yes":"NA")	
                                .', B&H Pricing:'.($customer->correct_price=="Yes"?"Yes":"NA")
                                .', PMI Listing:'.($customer->loyalty=="Yes"?"No":"NA")
                                .', Inv Share:'.$customer->inventory.'%"                               
                            class="form-control white_bkgd"></div>
                        </div>
                        ':'').'
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
                            '.($customer->cust_type==="Key Account"?'
                            <th>SKU Code</th>
                            <th>SKU Name</th>
                            <th>Actuals</th>
                            <th>Base</th>
                            <th>Growth</th>
                            ':'
                            <th>SKU Code</th>
                            <th>SKU Name</th>
                            <th>Actuals</th>
                            '.($customer->cust_type==="WAM Customers"?'':'
                            <th>Prorated</th>
                            ').'
                            <th>Target</th>
                            <th>Sellout</th>
                            ').'
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