<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');

echo'
<section class="services-wrap">        
    <div class="container">
        <div class="ibox">
            <div class="ibox-title">
                <h5>Reward Details:-<small>'.$main_data->cust_name.'</small></h5>
                <div class="ibox-tools">
                    <a href="'.site_url('Rewards/request_details/'.$main_data->list_id).'" data-toggle="tooltip" data-placement="bottom" title="" data-original-title="Return">
                        <i class="fa fa-undo"></i>
                    </a>                    
                </div>            
            </div>
            <div class="ibox-content">
                <form class="form-horizontal">
                    <div class="col-md-3">
                        <div class="form-group"><div class="col-sm-4 strong">URN</div>
                            <div class="col-sm-8">'.$main_data->cust_code.'</div>
                        </div>
                    </div>            
                    <div class="col-md-4">
                        <div class="form-group"><div class="col-sm-3 strong">Location:</div>
                            <div class="col-sm-9">'.$main_data->location.'</div>
                        </div>
                    </div>      
                    <div class="col-md-2">
                        <div class="form-group"><div class="col-sm-5 strong">Sales:</div>
                            <div class="col-sm-7">'.number_format($main_data->total_sales,2).'</div>
                        </div>
                    </div>    
                    
                    <div class="col-md-3">'.
                    ($main_data->cust_type=="Key Account"?'
                        <div class="form-group"><div class="col-sm-7 strong">Growth:</div>
                            <div class="col-sm-5">'.number_format($main_data->total_growth,2).'</div>
                        </div>
                     ':'
                        '.($main_data->cust_type=="WAM Customers"?'
                        <div class="form-group"><div class="col-sm-7 strong">Base pay:</div>
                            <div class="col-sm-5">'.number_format(($main_data->base_pay), 2, '.', ',').'</div>
                        </div>':'
                        <div class="form-group"><div class="col-sm-7 strong">Prorated:</div>
                            <div class="col-sm-5">'.number_format($main_data->pro_sales,2).'</div>
                        </div> '
                        )).
                    '</div>                      
                    <div class="col-md-3">
                        <div class="form-group"><div class="col-sm-4 strong">Type:</div>
                            <div class="col-sm-8">'.$main_data->cust_type.'</div>
                        </div>
                    </div>                    
                    <div class="col-md-4">
                        <div class="form-group"><div class="col-sm-3 strong">Title:</div>
                            <div class="col-sm-9">'.$main_data->title.'</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group"><div class="col-sm-5 strong">Target:</div>
                            <div class="col-sm-7">'.number_format($main_data->total_target,2).'</div>
                        </div>
                    </div>        
                    <div class="col-md-3">'.
                    ($main_data->cust_type=="Key Account"?'
                        <div class="form-group"><div class="col-sm-7 strong"><nobr>Focus Brand:</nobr></div>
                            <div class="col-sm-5">'.number_format($main_data->focus_vol,2).'</div>
                        </div>
                     ':'
                        '.($main_data->cust_type=="WAM Customers"?'
                        <div class="form-group"><div class="col-sm-7 strong">Growth:</div>
                            <div class="col-sm-5">'.number_format(($main_data->total_growth),
                                2, '.', ',').'</div>
                        </div>':'
                        <div class="form-group"><div class="col-sm-7 strong">'.
                        ($main_data->cust_type=="Rural Wholesaler"?"Redx":"Sellout").':</div>
                            <div class="col-sm-5">'.number_format($main_data->total_sellout,2).'</div>
                        </div> '
                    )).'
                    </div>                         
                    <div class="col-md-3">
                        <div class="form-group"><div class="col-sm-7 strong">Cust. Band:</div>
                            <div class="col-sm-5">'.$main_data->cust_category.'</div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        '.($main_data->cust_type=="WAM Customers"?'
                        <div class="form-group"><div class="col-sm-3 strong"></div>
                            <div class="col-sm-9"></div>
                        </div>':'
                        <div class="form-group"><div class="col-sm-3 strong">KPI Band</div>
                            <div class="col-sm-9">'.$main_data->band.'</div>
                        </div>'
                        ).'                    

                    </div>   
                    <div class="col-md-2">
                        <div class="form-group"><div class="col-sm-5 strong">Percent:</div>
                            <div class="col-sm-7">'.number_format($main_data->percent_target, 2, '.', ',').'</div>
                        </div>
                    </div>    
                    <div class="col-md-3">
                        <div class="form-group"><div class="col-sm-7 strong">Rebate:</div>
                            <div class="col-sm-5">'.number_format($main_data->total_amt, 2, '.', ',').'</div>
                        </div> 
                    </div>                       
                </form>
            </div>
        </div>';
echo'
        <div class="ibox">
            <div class="ibox-title">
                <h5>Sales Details</h5>
            </div>
            <div class="ibox-content">
                <div class="table-responsive">
                     <table id="example" class="display table table-hover table-striped"  width="100%">
                        <thead>  
                           <tr>
    '.($main_data->cust_type=="WAM Customers"?'
                            <th>SKU Code</th>
                            <th>SKU Name</th>
                            <th>Target</th>
                            <th>Sales</th>
                            <th>Prorated</th>
                            <th>Base Rate</th>
                            <th>%Grwth</th>    
                            <th>Grwth Rate</th>  
        ':'
                            <th>Week</th>
                            <th>Month</th>
                            <th>SKU Code</th>
                            <th>SKU Name</th>
                            <th>Actual</th>
                            <th>Prorated</th>
                            <th>Rate</th>
                            <th>Amount</th>
                            ').'
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