<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');

echo'
<section class="services-wrap">        
<div class="container">
    <div class="ibox">
        <div class="ibox-title">
            <h5>Winner Details</h5>
            <div class="ibox-tools">
                <a href="'.site_url('Rewards/approve_list').'" data-toggle="tooltip" 
                    data-placement="bottom" title="" data-original-title="Return to Request details">
                    <i class="fa fa-undo"></i>
                </a>                          
            </div>                  
        </div>
        <div class="ibox-content">
            <fieldset class="form-horizontal">
                <div class="col-md-4">
                    <div class="form-group"><div class="col-sm-6 strong">BAT URN:</div>
                        <div class="col-sm-6">26</div>
                    </div>
                </div>            
                <div class="col-md-4">
                    <div class="form-group"><div class="col-sm-6 strong">Business Name:</div>
                        <div class="col-sm-6">Rack Fox Hotels</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group"><div class="col-sm-6 strong">Outlet Type:</div>
                        <div class="col-sm-6">Hotel</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group"><div class="col-sm-6 strong">Category:</div>
                        <div class="col-sm-6">Gold</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group"><div class="col-sm-6 strong">Cluster:</div>
                        <div class="col-sm-6">Surulere</div>
                    </div>
                </div>                        
                <div class="col-md-4">
                    <div class="form-group"><div class="col-sm-6 strong">Area:</div>
                        <div class="col-sm-6">Lagos</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group"><div class="col-sm-6 strong">Region:</div>
                        <div class="col-sm-6">South West</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group"><div class="col-sm-6 strong">Market:</div>
                        <div class="col-sm-6">Nigeria</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group"><div class="col-sm-6 strong">Target Volume:</div>
                        <div class="col-sm-6">15</div>
                    </div>
                </div>    
                <div class="col-md-4">
                    <div class="form-group"><div class="col-sm-6 strong">Total Volume:</div>
                        <div class="col-sm-6">18</div>
                    </div>
                </div>  
               <div class="col-md-4">
                    <div class="form-group"><div class="col-sm-6 strong">Total Sales Value:</div>
                        <div class="col-sm-6">1,2840</div>
                    </div>
                </div>    
                <div class="col-md-4">
                    <div class="form-group"><div class="col-sm-6 strong">Total Rebate:</div>
                        <div class="col-sm-6">2100</div>
                    </div>
                </div>                  
            </fieldset>
        </div>
    </div>  
            
    <div class="ibox">
        <div class="ibox-title"><h5>Rebate Points Details</h5></div>
        <div class="ibox-content">
            <div class="table-responsive">
                <table class="table table-hover table-striped table-bordered">
                    <thead>
                          <th>Rebate Name</th>
                          <th>Point Awarded</th>
                          <th>Comment</th>
                        </tr>
                    </thead>
                    <tbody>                             
                        <tr>
                          <td>100 Naira Per Unit</td>
                          <td>1800</td>
                          <td>Awarded 100 for 18 Units to get 1800 </td>
                        </tr>
                        <tr>
                          <td>100 Naira for Each Unit above Target</td>
                          <td>300</td>
                          <td>Awarded 100 for 3 units above target (18-15) to get 300</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</section>    
';
       