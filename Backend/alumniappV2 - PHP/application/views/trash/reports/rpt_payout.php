<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');
echo'
<section class="services-wrap">        
<div class="container">                
        <div class="ibox">
            <div class="ibox-title">
                <h5>'.$page_title.':- <small id="rpt_type"> '.$rpt_title.'</small></h5>
                <div class="ibox-tools">
                   <a href="javascript:searchbox()" data-toggle="tooltip" data-placement="bottom" 
                            title="" data-original-title="Specify Report Parameters">
                            <i class="fa fa-file-text-o"></i>
                        </a>                                       
                </div>                  
            </div>
            <div class="ibox-content">
                <div id="rpt_div" class="table-responsive">
                     <table id="example" class="display table table-hover table-striped" width="100%">
                         <thead>                      
                            <tr>     
                              <th>URN</th><th>Customer</th><th>Month</th><th>Location</th><th>Target</th><th>Sales</th> 
                              <th>Prorated</th><th>Sellout</th><th>Percent</th><th>KPI</th><th>C_Band</th><th>P_Band</th>  
                              <th>M_Sellin</th><th>M_Sellout</th><th>M_Loyalty</th><th>M_Participation</th><th>Q_Sellin</th>  
                              <th>Q_Loyalty</th><th>Imported</th><th>NetPayout</th>                                
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
    


    <!-- Modal -->
    <form action="#" id="form" class="form-horizontal">
    <div class="modal fade" id="modal_form" tabindex="-1" role="dialog" 
         aria-labelledby="myModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <!-- Modal Header -->
                <div class="modal-header">
                    <button type="button" class="close" 
                       data-dismiss="modal">
                           <span aria-hidden="true">&times;</span>
                           <span class="sr-only">Close</span>
                    </button>
                    <h4 class="modal-title">Modal title</h4>
                </div>

                <!-- Modal Body -->
                <div class="modal-body">
                    <input type="hidden" name="'.$this->security->get_csrf_token_name()
                        .'" value="'.$this->security->get_csrf_hash().'" />                    
                    <input type="hidden" value="" name="id"/> 
                    <div class="col-md-12">
                        Please Use the checkboxes to specify report columns and 
                        use dropdown to select specific market, region, area, customer type or band.<br><br>
                    </div>
                    
                    <div class="col-md-6">                   
                        <div class="form-group"><label class="col-sm-5 control-label">Market:</label> 
                                <div class="col-sm-7">
                                    <select class="form-control" name="market" id="market">
                                   <option value=>-- Select Market  --</option>
                                   </select>                                                          
                                </div>
                        </div>    
                    </div>

                    <div class="col-md-6">                   
                        <div class="form-group"><label class="col-sm-5 control-label">Customer Type:</label> 
                                <div class="col-sm-7">
                                    <select class="form-control" name="cust_type" id="cust_type">
                                          <option value=>-- Select Customer Type  --</option>
                                   </select>                                                             
                                </div>
                        </div>    
                    </div>
                    
                    <div class="col-md-12">
                        Specify report period. Use Period drop down to select week, month, quarter or annual report 
                        and use period week, month quarter or year to specify specific period
                    </div>

                    <div class="col-md-6">                   
                        <div class="form-group"><label class="col-sm-5 control-label">Specify Period:</label> 
                                <div class="col-sm-7">
                                    <select class="form-control" name="periodval" id="periodval">
                                        <option value=>-- Select Period  --</option>
                                        <option value="Month">Month</option>
                                        <option value="Quarter">Quarter</option>
                                        <option value="Year">Year</option>
                                   </select>                                                     
                                </div>
                        </div>    
                    </div>
                    
                    <div class="col-md-6 monthDiv" style="display:none">
                        <div class="form-group">
                            <div class="col-sm-12"><input id="choosemnth2" name="period_mnth2" type="text" class="form-control" 
                            value="" placeholder="Select Month - mm-yyyy"></div>
                        </div>
                     </div> 
                     
                    <div class="col-md-6 qtrDiv" style="display:none">
                        <div class="form-group">
                            <div class="col-sm-6">
                                <select class="form-control" name="period_qtr" id="period_qtr">
                                   <option value=>-- Select Quarter  --</option>
                                   <option value="1">Quarter 1</option>
                                   <option value="2">Quarter 2</option>
                                   <option value="3">Quarter 3</option>
                                   <option value="4">Quarter 4</option>
                                   </select>                      
                            </div>
                            <div class="col-sm-6">
                              <select class="form-control" name="period_yr" id="period_yr">
                                   <option value=>-- Select Year  --</option>
                                   <option value="2016">2016</option>
                                   <option value="2017">2017</option>
                                   <option value="2018">2018</option>
                                   </select>    
                              </div>
                        </div>
                     </div> 
                    <div class="col-md-6 yrDiv" style="display:none">
                        <div class="form-group">
                            <div class="col-sm-12">
                              <select class="form-control" name="period_yr2" id="period_yr2">
                                   <option value=>-- Select Year  --</option>
                                   <option value="2016">2016</option>
                                   <option value="2017">2017</option>
                                   <option value="2018">2018</option>
                                   </select>    
                              </div>
                        </div>
                     </div> 
                     
                    &nbsp;<div class="col-md-12"></div>
                </div>
                <!-- Modal Footer -->
                <div class="modal-footer">
                    <button type="button" id="btnSave" onclick="save()" class="btn btn-primary">Run</button>
                    <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                </div>             
            </div>
        </div>
    </div> 
    </form>
    
</section>        
';
?>        