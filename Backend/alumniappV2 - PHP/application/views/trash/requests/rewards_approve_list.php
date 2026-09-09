<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');

echo'
<section class="services-wrap">        
    <div class="container">
        <div class="ibox">
            <div class="ibox-title">
                <h5>Reward Details</h5>
                <div class="ibox-tools">
                  
                    <a href="" data-toggle="tooltip" data-placement="bottom" title="" data-original-title="Approve Request">
                        <i class="fa fa-check-circle-o"></i>
                    </a>  
                    <a href="" data-toggle="tooltip" data-placement="bottom" title="" data-original-title="Deny Request">
                        <i class="fa fa-times-circle-o"></i>
                    </a>   
                    <a href="'.site_url('Rewards/awaiting_approval').'" data-toggle="tooltip" data-placement="bottom" title="" data-original-title="Return">
                        <i class="fa fa-undo"></i>
                    </a>                    
                </div>            
            </div>
            <div class="ibox-content">
                <form class="form-horizontal">
                    <div class="col-md-4">
                        <div class="form-group"><div class="col-sm-6 strong">Request Date</div>
                            <div class="col-sm-6">14-May-16</div>
                        </div>
                    </div>            
                    <div class="col-md-4">
                        <div class="form-group"><div class="col-sm-6 strong">Requested By:</div>
                            <div class="col-sm-6">John Eze</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group"><div class="col-sm-6 strong">Request Type:</div>
                            <div class="col-sm-6">Generated List</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group"><div class="col-sm-6 strong">Period:</div>
                            <div class="col-sm-6">Apr-16</div>
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
                        <div class="form-group"><div class="col-sm-6 strong">Total Winners:</div>
                            <div class="col-sm-6">10</div>
                        </div>
                    </div>    
                    
                    <div class="col-md-4">
                        <div class="form-group"><div class="col-sm-6 strong">Request Status:</div>
                            <div class="col-sm-6">Awaiting Approval</div>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group"><div class="col-sm-2 strong">Enter Comments:</div>
                            <div class="col-sm-10"><textarea rows="4" name="comment" id="comment" placeholder="Enter reason approval or denial "
            class="form-control tooltips top" data-original-title="Enter reason approval or denial"></textarea></div>
                        </div>
                    </div>                                  
                </form>
            </div>
            
        </div>      ';

echo'

        <div class="ibox">
            <div class="ibox-title">
                <h5>Winners List:</h5>
            </div>
            <div class="ibox-content">
                <div class="table-responsive">
                     <table id="example" class="display table table-hover table-striped"  width="100%">
                        <thead>
                           <tr>
                             <th>URN</th>
                             <th>Business Name </th>
                             <th>Type</th>
                             <th>Category</th>
                             <th>Cluster</th>
                             <th>Area</th>
                             <th>Target</th>
                             <th>Volume</th>
                             <th>Rebate</th>
                           </tr>
                         </thead>
                         <tbody>
                           <tr>
                             <td>25</td>
                             <td>Woodland Bars</td>
                             <td>Café</td>
                             <td>Silver</td>
                             <td>Ikeja</td>
                             <td>Lagos</td>
                             <td>13</td>
                             <td>10</td>
                             <td>1,000</td>
                           </tr>
                           <tr>
                             <td>26</td>
                             <td><a href="'.site_url('Rewards/approve_detail/2').'">Rack Fox Hotels</a></td>
                             <td>Hotel</td>
                             <td>Gold</td>
                             <td>Surulere</td>
                             <td>Lagos</td>
                             <td>15</td>
                             <td>18</td>
                             <td>2,100</td>
                           </tr>
                           <tr>
                             <td>74</td>
                             <td>Morenike Shops</td>
                             <td>Retail</td>
                             <td>Bronze</td>
                             <td>Surulere</td>
                             <td>Lagos</td>
                             <td>20</td>
                             <td>8</td>
                             <td>800</td>
                           </tr>
                           <tr>
                             <td>80</td>
                             <td>Movers Supermarket</td>
                             <td>Retail</td>
                             <td>Bronze</td>
                             <td>Ikeja</td>
                             <td>Lagos</td>
                             <td>20</td>
                             <td>18</td>
                             <td>1,800</td>
                           </tr>
                           <tr>
                             <td>82</td>
                             <td>Aliu Salami Enterprise</td>
                             <td>Wholesale</td>
                             <td>Gold</td>
                             <td>Ikorodu</td>
                             <td>Lagos</td>
                             <td>50</td>
                             <td>58</td>
                             <td>6,600</td>
                           </tr>
                           <tr>
                             <td>84</td>
                             <td>Pearl Glory Ventures</td>
                             <td>Wholesale</td>
                             <td>Platinium</td>
                             <td>Agege</td>
                             <td>Lagos</td>
                             <td>55</td>
                             <td>80</td>
                             <td>10,500</td>
                           </tr>
                           <tr>
                             <td>92</td>
                             <td>Shehu Salami Investment Ltd</td>
                             <td>Key Account</td>
                             <td>Silver</td>
                             <td>Ikeja</td>
                             <td>Lagos</td>
                             <td>70</td>
                             <td>82</td>
                             <td>9,400</td>
                           </tr>
                           <tr>
                             <td>94</td>
                             <td>Manus Shops</td>
                             <td>Retail</td>
                             <td>Bronze</td>
                             <td>Ikeja</td>
                             <td>Lagos</td>
                             <td>20</td>
                             <td>21</td>
                             <td>2,200</td>
                           </tr>
                           <tr>
                             <td>95</td>
                             <td>Dong Berg Shop</td>
                             <td>Retail</td>
                             <td>Bronze</td>
                             <td>Agege</td>
                             <td>Lagos</td>
                             <td>20</td>
                             <td>8</td>
                             <td>800</td>
                           </tr>
                           <tr>
                             <td>140</td>
                             <td>Star Outlet Café</td>
                             <td>Café</td>
                             <td>Gold</td>
                             <td>Ikorodu</td>
                             <td>Lagos</td>
                             <td>15</td>
                             <td>15</td>
                             <td>1,500</td>
                           </tr>
                         </tbody>                                  
  
                       </table>
                 </div>
            </div>
        </div>    
        
        <h4>Workflow Path:</h4><hr>
        <div class="bs-example bs-example-popover" data-example-id="static-popovers"> 
            <div class="popover left">
                <div class="arrow"></div> 
                <h3 class="popover-title">Initiator</h3>
                <div class="popover-content"> 
                    <strong>John Eze</strong><br/>Channel Development Manager 
                </div> 
            </div> 
            <div class="popover left">
                <div class="arrow"></div>
                <h3 class="popover-title">Approver 1</h3>
                <div class="popover-content"> 
                    <strong>Betty Umar</strong><br/>Head Consumer Marketing 
                </div> 
            </div> 
            <div class="popover left"> 
                <div class="arrow"></div> 
                <h3 class="popover-title">Approver 2</h3> 
                <div class="popover-content"> 
                    <strong>Jane Obi</strong><br/>Marketing Finance 
                </div> 

            </div> 
            <div class="popover left mainactive"> 
                <div class="arrow"></div> 
                <h3 class="popover-title mainheader">Approver 3</h3> 
                <div class="popover-content mainactive"> 
                    <strong>Samuel Obe</strong><br/>Cluster Manager 
                </div> 
            </div> 
            <div class="popover"> 
                <h3 class="popover-title">Final Approver</h3> 
                <div class="popover-content"> 
                    <strong>John Eze</strong><br/>Channel Development Manager 
                </div> 
            </div>     
            <div class="clearfix"></div> 
        </div>

        
        <h4>Re-route:</h4><hr>
        <form class="form-horizontal">
            <div class="col-md-4">
                <div class="form-group"><div class="col-sm-6 strong">Curremt Approver</div>
                    <div class="col-sm-6">Samuel Obe</div>
                </div>
            </div>            
            <div class="col-md-6">
                <div class="form-group"><div class="col-sm-3 strong">Re-route to:</div>
                    <div class="col-sm-9">                        
                        <select class="chosen-select-no-results form-control">
                            <option>&lt;Select Approver&gt;</option>
                            <option>Betty Umar</option>
                            <option>Femi Adeyemi</option>
                            <option>Halima Abubakar</option>
                            <option>Hassan Usman</option>    
                            <option>Ify Okoye</option>
                            <option>Jane Obi</option>
                            <option>John Eze</option>
                            <option>Mercy John</option>  
                            <option>Yemi Adebiyi</option>                                
                        </select>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary rounded btn-3d">Re-route</button>
            </div>
        </form>
        <div class="divide40"></div>
        <div class="divide40"></div>
        <h4>Workflow Activity History:</h4><hr>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Designation</th>
                        <th>Action/Status</th>
                        <th>Date</th>
                        <th>Comment</th>
                        <th>Next Approver</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                      <td>John Eze</td>
                      <td>Channel Development Manager</td>
                      <td>Submit (initiator)</td>
                      <td>16-May-2016</td>
                      <td>Winners list submitted</td>
                      <td>Betty Umar (Head Consumer Marketing)</td>
                    </tr>
                    <tr>
                        <td>Betty Umar</td>
                        <td>Head Consumer Marketing</td>
                        <td>Approve</td>
                        <td>16-May-2016</td>
                        <td>Winners list vetted and cerified ok</td>
                        <td>Jane Obi (Marketing Finance)</td>
                    </tr>
                    <tr>
                        <td>Jane Obi</td>
                        <td>Marketing Finance</td>
                        <td>Approve</td>
                        <td>17-May-2016</td>
                        <td>Certified ok</td>
                        <td>Samuel Obe(Cluster Manager)</td>
                    </tr>
                    <tr>
                        <td>Samuel Obe</td>
                        <td>Cluster Manager</td>
                        <td>Return</td>
                      <td>17-May-2016</td>
                      <td>Issue with sales record of receipent (Shehu Salami Investment Ltd) - value seem incorrect</td>
                      <td>John Eze (Channel Development Manager)</td>
                    </tr>
                     <tr>
                        <td>John Eze</td>
                        <td>Channel Development Manager</td>
                       <td>Resubmit (initiator)</td>
                       <td>17-May-2016</td>
                       <td>Receipent (Shehu Salami Investment Ltd) sales record crossed checked and certified ok</td>
                       <td>Betty Umar (Head Consumer Marketing)</td>
                    </tr>
                     <tr>
                       <td>Betty Umar</td>
                       <td>Head Consumer Marketing</td>
                       <td>Approve</td>
                       <td>18-May-2016</td>
                       <td>Cerified ok</td>
                       <td>Jane Obi (Marketing Finance)</td>
                     </tr>
                     <tr>
                       <td>Jane Obi</td>
                       <td>Marketing Finance</td>
                       <td>Approve</td>
                       <td>19-May-2016</td>
                       <td>&nbsp;</td>
                       <td>Samuel Obe(Cluster Manager)</td>
                     </tr>
                     <tr>
                       <td>Samuel Obe</td>
                       <td>Cluster Manager</td>
                       <td>Awaiting Approval</td>
                       <td>&nbsp;</td>
                       <td>&nbsp;</td>
                       <td>&nbsp;</td>
                     </tr>
                     <tr>
                       <td>&nbsp;</td>
                       <td>&nbsp;</td>
                       <td>&nbsp;</td>
                       <td>&nbsp;</td>
                       <td>&nbsp;</td>
                       <td>&nbsp;</td>
                     </tr>
                </tbody>
            </table>
        </div>

    </div>
</section>        
';
?>        