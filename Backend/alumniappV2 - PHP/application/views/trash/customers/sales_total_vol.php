<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');

echo'
<section class="services-wrap">        
    <div class="container">
        <div class="ibox">
            <div class="ibox-title">
                <h5>Evaluation Data Details:</h5>
                <div class="ibox-tools">                  
                    <a href="'.site_url('customers/sales_volume')
                        .'" data-toggle="tooltip" data-placement="bottom" title="" data-original-title="Return">
                        <i class="fa fa-undo"></i>
                    </a>   
                    <a href="'.site_url('customers/view_missing_customers/'.$sales_id)
                        .'" data-toggle="tooltip" data-placement="bottom" title="" 
                            data-original-title="Customers not in database">
                        <i class="fa fa-outdent"></i>
                    </a> 
                    <a href="'.site_url('customers/import_sales_volume/edit/'.$sales_id).'"data-toggle="tooltip" data-placement="bottom" 
                        title="" data-original-title="Edit Evaluation Data">
                        <i class="fa fa-edit"></i>
                    </a>                       
                    <a href="'.site_url('customers/delete_row/'.$sales_id).'"data-toggle="tooltip" data-placement="bottom" 
                       onclick="return confirm(\'WARNING! THIS ACTION CAN AFFECT AN APPROVED WINNERS LIST. Are you sure delete this record? This process is irreversible\');"
                        title="" data-original-title="Delete Evaluation Data (Irrevesible)">
                        <i class="fa fa-trash-o"></i>
                    </a>                     
                </div>                
            </div>
            <div class="ibox-content">
                <fieldset class="form-horizontal">
                     <div class="col-md-4">
                         <div class="form-group"><label class="col-sm-5 control-label">Import Date:</label>
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
                         <div class="form-group"><label class="col-sm-5 control-label">Market:</label>
                             <div class="col-sm-7"><input name="market" type="text" class="form-control white_bkgd" 
                             value="'.$market.'" readonly="readonly"></div>
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
                             <div class="col-sm-7"><input name="cust_type" type="text" class="form-control white_bkgd" 
                              value="'.$cust_type.'" readonly="readonly"></div>
                         </div>
                     </div>                     
                 </fieldset> 
            </div>
        </div>        
    </div>
    <div class="ibox">
        <div class="ibox-title"><h5>Customer Details</h5></div>
            <div class="ibox-content">
                <div class="table-responsive">
                     <table id="example" class="display table table-hover table-striped"  width="100%">
                         <thead>
                           <tr>
                            '.($cust_type==="Key Account"?"
                            <th>BATID</th>
                            <th>URN</th>
                            <th>Customer</th>
                            <th>Type</th>
                            <th>Band</th>
                            <th>Sales</th>
                            <th>Target</th>
                            <th>Percent</th>
                            <th>Growth</th>
                            <th>Pro Growth</th>
                            <th>Focus Vol</th>
                            <th>Focus Target</th>
                            <th>Premium</th>
                            <th>Prem %</th>
                            <th>REF</th>
                            ":"
                            <th>BATID</th>
                            <th>URN</th>
                            <th>Customer</th>
                            <th>Type</th>
                            <th>Band</th>
                            <th>Sales</th>
                            <th>Target</th>
                            <th>Percent</th>
                            <th>".($cust_type==="Rural Wholesaler"?"Redx":"Sellout")."</th>
                            <th>REF</th>
                            Sellout").'
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