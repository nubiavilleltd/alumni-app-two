<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');

echo'
<section class="services-wrap">        
    <div class="container">
        <div class="ibox">
            <div class="ibox-title">
                <h5>View Evaluation Data:</h5>
                <div class="ibox-tools">
                    <a href="'.site_url('Customers/import_sales_volume').'" data-toggle="tooltip" 
                          data-placement="bottom" title="" data-original-title="Import Evaluation Data">
                          <i class="fa fa-file-text-o"></i>
                    </a>                                          
                </div>                 
            </div>
            <div class="ibox-content">
                <div class="table-responsive">
                     <table id="example" class="display table table-hover table-striped"  width="100%">
                         <thead>                      
                           <tr>
                              <th>Import Date</th>
                              <th>Imported By</th>        
                              <th>Week</th>
                              <th>Month</th>
                              <th>Customer Type</th>
                              <th>Market</th>
                              <th>REF</th>
                           </tr>                          
                         </thead>
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