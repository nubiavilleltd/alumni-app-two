<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');

echo'
<section class="services-wrap">        
    <div class="container">
        <div class="ibox">
            <div class="ibox-title">
                <h5>Evaluation Data Details:</h5>
                <div class="ibox-tools">
                    <a href="'.site_url('Customers/sales_volume')
                        .'" data-toggle="tooltip" data-placement="bottom" title="" data-original-title="Return">
                        <i class="fa fa-undo"></i>
                    </a>                                                      
                </div>                
            </div>
            <div class="ibox-content">
                <fieldset class="form-horizontal">
                     <div class="col-md-4">
                         <div class="form-group"><label class="col-sm-5 control-label">Import Date</label>
                             <div class="col-sm-7"><input name="import_date" type="text" class="form-control white_bkgd" 
                             value="'.$import_date.'" readonly="readonly"></div>
                         </div>
                     </div>
                     <div class="col-md-4">
                         <div class="form-group"><label class="col-sm-5 control-label">Imported By:</label>
                             <div class="col-sm-7"><input name="imported_by" type="text" class="form-control white_bkgd" 
                            value="'.$imported_by.'" readonly="readonly"></div>
                         </div>
                     </div>
                     <div class="col-md-4">
                         <div class="form-group"><label class="col-sm-5 control-label">Period (Week):</label>
                             <div class="col-sm-7"><input name="period_week" type="text" class="form-control white_bkgd" 
                              value="'.$period_week.'" readonly="readonly"></div>
                         </div>
                     </div>                
                     <div class="col-md-4">
                         <div class="form-group"><label class="col-sm-5 control-label">Period(Month):</label>
                             <div class="col-sm-7"><input name="period_month" type="text" 
                             class="form-control white_bkgd" value="'.$fullmonth.'" readonly="readonly"></div>
                         </div>
                     </div>                 
                     <div class="col-md-4">
                         <div class="form-group"><label class="col-sm-5 control-label">Customer Type:</label>
                             <div class="col-sm-7"><input name="cust_type" type="text" 
                             class="form-control white_bkgd" value="'.$cust_type.'" readonly="readonly"></div>
                         </div>
                 </fieldset> 
            </div>
        </div>        
    </div>
    <div class="ibox">
        <div class="ibox-title"><h5>Volume Details By SKU</h5></div>
            <div class="ibox-content">
                <div class="table-responsive">
                     <table id="example" class="display table table-hover table-striped"  width="100%">
                         <thead>
                           <tr>
                            <th>BATID</th>
                            <th>URN</th>
                            <th>Customer</th>
                            <th>SKU Code</th>
                            <th>SKU Name</th>
                            <th>Sales</th>
                            <th>Target</th>
                            <th>'.($cust_type==="Rural Wholesaler"?"Redx":"Sellout").'</th>
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