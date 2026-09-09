<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');
     echo' <script src="'.site_url('assets/chartsjs/Chart.bundle.js').'"></script>';
echo'
    <section class="services-wrap"> 
        <div class="container">           
            <div class="ibox">
                <div class="ibox-title">
                    <h5>Targets, Actuals and Rebate For - '.$current_mnth->full_month.' - '. $market_name .' </h5>  
                    '.form_open('Reports/Dashboard/'.$form_action,array('name'=>'dash_form','class'=>'form-horizontal')).'
                    <div class="ibox-tools">
                        <div class="col-xs-5 col-md-5 pull-right">
                            <div class="form-group">                                
                                <div class="col-xs-5 col-md-6"><input id="choosemnth" name="period_month" type="text" 
                                class="form-control"  value="'.$main_data->fullmonth.'" placeholder="Period (mm-yyyy)"></div>
                                <div class="col-xs-5 col-md-5 text-left">'.$market.'</div>
                                <div class="col-xs-2 col-md-1">
                                    <a href="javascript:document.dash_form.submit();" data-toggle="tooltip" 
                                    data-placement="bottom" title="" data-original-title="Submit Form">
                                        <i class="fa fa-check-square"></i>
                                    </a>    
                                </div>
                            </div>
                        </div>                                         
                    </div>
                    '.form_close().'
                </div>
                <div class="ibox-content">
                    <div class="col-sm-3">
                        <div class="ibox float-e-margins">
                            <div class="ibox-title">
                                <h6>Total Outlets <br/> </h6>
                                <div class="ibox-tools">
                                    <a href="#" data-toggle="tooltip" 
                                    data-placement="bottom" title="">
                                    <i class="fa fa-users"></i></a>
                                </div>               
                            </div>
                            <div class="ibox-content">
                                <h5 class="no-margins">'.number_format($current_mnth->total_cust,2).'</h5>
                            </div>
                            <div class="divide0"></div>                            
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="ibox float-e-margins">
                            <div class="ibox-title">
                                <h6>Total Target <br/>(Cases)</h6>
                                <div class="ibox-tools">
                                    <a href="#" data-toggle="tooltip" 
                                    data-placement="bottom" title="">
                                    <i class="fa fa-arrows"></i></a>
                                </div>                                   
                            </div>
                            <div class="ibox-content">
                                <h5 class="no-margins">'.number_format($current_mnth->total_target,2).'</h5>
                            </div>
                            <div class="divide0"></div>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="ibox float-e-margins">
                            <div class="ibox-title">
                                <h6>Total Actual <br/>(Cases)</h6>
                                <div class="ibox-tools">
                                    <a href="#" data-toggle="tooltip" 
                                    data-placement="bottom" title="">
                                    <i class="fa fa-shopping-cart"></i></a>
                                </div>                                   
                            </div>
                            <div class="ibox-content">
                                <h5 class="no-margins">'.number_format($current_mnth->total_sales,2).'</h5>
                              <div class="stat-percent font-bold text-navy">'.number_format(($current_mnth->total_sales/$current_mnth->total_target*100),2).'%</div>
                            </div>
                            <div class="divide0"></div>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="ibox float-e-margins">
                            <div class="ibox-title">
                                <h6>Total Rebate <br/>('.$currency.')</h6>
                                <div class="ibox-tools">
                                    <a href="#" data-toggle="tooltip" 
                                    data-placement="bottom" title="">
                                    <i class="fa fa-cubes"></i></a>
                                </div>      
                            </div>
                            <div class="ibox-content">
                                <h5 class="no-margins">'.number_format($current_mnth->total_rebate,2).'</h5>
                            </div>
                            <div class="divide0"></div>
                        </div>
                    </div>                
                    <div class="col-md-7">
                        <canvas id="canvas"></canvas>
                    </div>
                    <div class="col-md-5 table-responsive">
                        <table id="example" class="display table table-hover table-striped"  width="100%">
                             <thead>                      
                               <tr>
                                  <th>Region</th>
                                  <th>Target</th>
                                  <th>Sales</th>                              
                                  <th>Rebate</th>
                               </tr>                          
                             </thead>
                             <tbody>
                                ';
                                $total_target=0;
                                $total_sales=0;
                                $total_rebate=0;
                                $region_list ='"';
                                $target_list = '';
                                $sales_list = '';
                                $reward_list = '';
                                foreach($regional_data as $datarow){
                                    $region_list .=$datarow->region.'","';
                                    $target_list .= ($datarow->target*1000).',';
                                    $sales_list .= ($datarow->sales*1000).',';
                                    $rebate_list .= $datarow->rebate.',';
                                    $total_target+=$datarow->target; 
                                    $total_sales+=$datarow->sales;
                                    $total_rebate+=$datarow->rebate;
                                    echo'
                                    <tr>
                                      <td>'.$datarow->region.'</td>
                                      <td>'.number_format($datarow->target,2).' </td>
                                      <td>'.number_format($datarow->sales,2).' </td>
                                      <td>'.number_format($datarow->rebate,2).' </td>
                                    </tr>';
                                }
                                echo'
                                <tr>
                                  <td><strong>Total</strong> </td>
                                  <td><strong>'.number_format($total_target,2).' </strong></td>
                                  <td><strong>'.number_format($total_sales,2).'</strong> </td>
                                  <td><strong>'.number_format($total_rebate,2).'</strong> </td>
                                </tr>                     
                             </tbody>
                        </table>
                    </div>
                </div>    
            </div>    
            <div class="col-md-12 table-responsive">
                <div class="divide60"></div>
                <h5>Top 10 Highest Paid Customers</h5>
                <table id="example" class="display table table-hover table-striped"  width="100%">
                     <thead>  
                       <tr>
                          <th>Customers</th>
                          <th>Type</th>
                          <th>Category</th>                              
                          <th>Area</th>
                          <th>Region</th>
                          <th>Target</th>
                          <th>Sales</th>                              
                          <th>Reward</th>                          
                       </tr>                          
                     </thead>
                     <tbody>                     
                     ';
                        foreach($top_10 as $data_top_10){
                            echo'
                            <tr>
                        <tr>
                          <td>'.$data_top_10->cust_name.'</td>
                          <td>'.$data_top_10->cust_type.'</td>
                          <td>'.$data_top_10->cust_category.'</td>
                          <td>'.$data_top_10->location.'</td>
                          <td>'.$data_top_10->region.'</td>
                          <td>'.number_format($data_top_10->sales,2).'</td>
                          <td>'.number_format($data_top_10->target,2).'</td>
                          <td>'.number_format($data_top_10->rebate,2).'</td>
                        </tr>';
                        }
                       echo'              
                     </tbody>
                </table>
            </div>
        </div>
    </section> 
';
?>


                                    
<script>
        var barChartData = {
            labels: [<?php echo substr($region_list, 0, -2);?>],
            datasets: [{
                type: 'bar',
                label: 'Target (cases)',
                backgroundColor: "rgba(151,187,205,0.5)",
                data: [<?php echo substr($target_list, 0, -1);?>],
                borderColor: 'white',
                borderWidth: 2
            }, {
                type: 'bar',
                label: 'Actuals (Cases)',
                backgroundColor: "rgba(19,13,112,0.9)",
                data: [<?php echo substr($sales_list, 0, -1);?>],
                borderColor: 'white',
                borderWidth: 2
            }, {
                type: 'line',
                label: 'Rebate Value',
                backgroundColor: "rgba(243,156,18,0.5)",
                data: [<?php echo substr($rebate_list, 0, -1);?>]
            }, ]

        };
        window.onload = function() {
            var ctx = document.getElementById("canvas").getContext("2d");
            window.myBar = new Chart(ctx, {
                type: 'bar',
                data: barChartData,
                options: {
                    scaleBeginAtZero: true,                    
                    responsive: true,
                    barValueSpacing: 1000,
                    barDatasetSpacing: 2,
                    title: {
                        display: false,
                        text: ''
                    }
                }
            });
        };

    </script>