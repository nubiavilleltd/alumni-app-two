<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');

echo'
<section class="services-wrap">        
    <div class="container">
        <div class="ibox">
            <div class="ibox-title">
                <h5>Reward Details</h5>
                <div class="ibox-tools">
                    <a href="javascript:document.comment_form.submit();" 
                        data-toggle="tooltip" data-placement="bottom" title="" 
                        data-original-title="Approve Request"><i class="fa fa-check-square-o"></i>
                    </a>  
                    <a href="javascript:document.comment_form.submit();" 
                        data-toggle="tooltip" data-placement="bottom" title="" 
                        data-original-title="Deny Request"><i class="fa fa-times"></i>
                    </a>  
                    <a href="javascript:document.comment_form.submit();" 
                        data-toggle="tooltip" data-placement="bottom" title="" 
                        data-original-title="Return Request"><i class="fa fa-external-link"></i>
                    </a>                                        
                    <a href="'.site_url('Rewards/view_winners_list').'" data-toggle="tooltip" 
                        data-placement="bottom" title="" data-original-title="Return to view">
                        <i class="fa fa-undo"></i>
                    </a>                    
                </div>            
            </div>
            <div class="ibox-content">
                <form class="form-horizontal">
                    <div class="col-md-6">
                        <div class="form-group"><div class="col-sm-4 strong">Date Initiated:</div>
                            <div class="col-sm-8">'.date_create($list_data->initiated_date)->format('g:ia, d M, Y').'</div>
                        </div>
                    </div>                
                    <div class="col-md-2">
                        <div class="form-group"><div class="col-sm-4 strong">Period</div>
                            <div class="col-sm-8">'.$list_data->fullmonth.'</div>
                        </div>
                    </div>    
                    <div class="col-md-4">
                        <div class="form-group"><div class="col-sm-6 strong">Process Type:</div>
                            <div class="col-sm-6">'.$list_data->process_type.'</div>
                        </div>
                    </div>   
                    <div class="col-md-6">
                        <div class="form-group"><div class="col-sm-4 strong">Initiated By:</div>
                            <div class="col-sm-8">'.$initated_by->fullname.'</div>
                        </div>
                    </div>                  
                    <div class="col-md-2">
                        <div class="form-group"><div class="col-sm-4 strong">Area:</div>
                            <div class="col-sm-8">'.$list_data->area.'</div>
                        </div>
                    </div>                        
                    <div class="col-md-4">
                        <div class="form-group"><div class="col-sm-6 strong">Market:</div>
                            <div class="col-sm-6">'.$list_data->market.'</div>
                        </div>
                    </div> 
                    <div class="col-md-6">
                        <div class="form-group"><div class="col-sm-4 strong">Rebate Type:</div>
                            <div class="col-sm-8">'.$list_data->campaign.'</div>
                        </div>
                    </div>   
                    <div class="col-md-3"></div>                      
                    <div class="col-md-3"></div> 
                    <div class="col-md-12">
                        '.form_open(site_url('Request/action_request/'
                        .$process_data->process_id.'/'.$list_data->list_id),
                        array('name'=>'comment_form','class'=>'form-horizontal')).'   
                        <div class="form-group"><div class="col-sm-2 strong">Enter Comments if any:</div>
                            <div class="col-sm-10">
                            <textarea rows="4" name="comment" id="comment" 
                            placeholder="Enter Comments" class="form-control tooltips top" 
                            data-original-title="Enter Comments if any"></textarea></div>
                        </div>
                        <input type="hidden" value="submit" name="action_type"/>
                        </form>
                    </div>                                  
                </form>
            </div>
        </div>';

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
                             <th>Customer Name </th>
                             <th>Type</th>
                             <th>Category</th>
                             <th>Rebate Title</th>                                
                             <th>Target</th>
                             <th>Volume</th>                          
                             <th>Amount</th>
                           </tr>
                         </thead>
                         <tbody>
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
                <div class="form-group"><div class="col-sm-6 strong">Current Approver</div>
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