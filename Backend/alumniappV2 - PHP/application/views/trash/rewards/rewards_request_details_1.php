<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');
$action='';
$script='
<script language="javascript">
 function actionIt(actionType) {
    document.getElementById("actionType").value = actionType;
    document.comment_form.submit()
}
</script>
';
if(empty($request_data) && ($list_data->initiated_by==$this->ion_auth->get_user_id())) {
    $action='initiator';
} elseif ($request_data->next_appr==$this->ion_auth->get_user_id()) {
    $action='approver';
} else {
    $action='viewer';
     $script='';
}

echo $script.'
    
<section class="services-wrap">        
    <div class="container">
        <div class="ibox">
            <div class="ibox-title">
                <h5>Reward Details</h5>
                <div class="ibox-tools">
                
                    <div class="btn-group">
                        <button aria-expanded="false" data-toggle="dropdown" 
                        class="btn btn-default border-theme dropdown-toggle" type="button">
                        <i class="fa fa-bars"></i>Action Menu<span class="caret"></span></button>
                        <ul role="menu" class="dropdown-menu">                        

                        '
                        .($action==='initiator'?
                            '
                            <li><a href="javascript:actionIt(\'submit\');" 
                                data-toggle="tooltip" data-placement="bottom" title="" 
                                data-original-title="Submit Request"><i class="fa fa-check-square-o">Submit</i>
                            </a></li><li class="divider"></li>                    
                            ':($action==='approver'?'    
                            <li><a href="javascript:actionIt(\'approve\');" 
                                data-toggle="tooltip" data-placement="bottom" title="" 
                                data-original-title="Approve Request"><i class="fa fa-check-square-o">Approve</i>
                            </a></li><li class="divider"></li>
                            <li><a href="javascript:actionIt(\'deny\');" 
                                data-toggle="tooltip" data-placement="bottom" title="" 
                                data-original-title="Deny Request"><i class="fa fa-times">Deny</i>
                            </a></li><li class="divider"></li>
                            <li><a href="javascript:actionIt(\'return\');" 
                                data-toggle="tooltip" data-placement="bottom" title="" 
                                data-original-title="Return Request"><i class="fa fa-external-link">Return</i>
                            </a></li><li class="divider"></li>  
                            ':'')).'
                            <li><a href="'.site_url('Rewards/view_winners_list').'" data-toggle="tooltip" 
                                data-placement="bottom" title="" data-original-title="Go back">
                                <i class="fa fa-undo"></i>Go back
                            </a></li>
                        </ul>
                    </div>                   
                </div>            
            </div>
            <div class="ibox-content">
                '.form_open('Request/action_request/'
                    .$process_data->process_id.'/'.$list_data->list_id,
                    array('name'=>'comment_form','class'=>'form-horizontal')).' 
                    <input type="hidden" value="_blank" name="actionType" id="actionType"/>
                    <div class="col-md-6">
                        <div class="form-group"><div class="col-sm-4 strong">Date Initiated:</div>
                            <div class="col-sm-8">'.date_create($list_data->initiated_date)->format('g:ia, d M, Y').'</div>
                        </div>
                    </div>                
                    <div class="col-md-2">
                        <div class="form-group"><div class="col-sm-4 strong">Period</div>
                            <div class="col-sm-8">'.$list_data->period.'</div>
                        </div>
                    </div>    
                    <div class="col-md-4">
                        <div class="form-group"><div class="col-sm-6 strong">Process Type:</div>
                            <div class="col-sm-6">'.$list_data->process_type.'</div>
                        </div>
                    </div>   
                    <div class="col-md-6">
                        <div class="form-group"><div class="col-sm-4 strong">Date Submitted:</div>
                            <div class="col-sm-8">'
                            .(empty($request_data->request_date)?'Not yet submitted':
                            date_create($request_data->request_date)->format('g:ia, d M, Y')).'</div>
                        </div>
                    </div>                                      
                    <div class="col-md-2">
                        <div class="form-group"><div class="col-sm-4 strong">Area:</div>
                            <div class="col-sm-8">'.$list_data->area.'</div>
                        </div>
                    </div>     
                    <div class="col-md-4">
                        <div class="form-group"><div class="col-sm-6 strong">Initiated By:</div>
                            <div class="col-sm-6">'.$initated_by->first_name.' '.$initated_by->last_name.'</div>
                        </div>
                    </div>                      
                    <div class="col-md-6">
                        <div class="form-group"><div class="col-sm-4 strong">Rebate Type:</div>
                            <div class="col-sm-8">'.$list_data->campaign.'</div>
                        </div>
                    </div>   
                    <div class="col-md-2">
                        <div class="form-group"><div class="col-sm-4 strong">Market:</div>
                            <div class="col-sm-8">'.$list_data->market.'</div>
                        </div>
                    </div>                      
                    <div class="col-md-4">
                        <div class="form-group"><div class="col-sm-6 strong">Next Approver:</div>
                            <div class="col-sm-6">'.$request_data->next_approver.'</div>
                        </div>
                    </div>
                    <div class="col-md-12">  
                        <div class="form-group"><div class="col-sm-2 strong">Enter Comments if any:</div>
                            <div class="col-sm-10">
                            <textarea rows="4" name="comment" id="comment" 
                            placeholder="Enter Comments" class="form-control tooltips top" 
                            data-original-title="Enter Comments if any"></textarea></div>
                        </div>
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
        </div>'. 

        (!empty($request_data)&&($user_grp==='Admin')?'
        <div class="ibox">
            <div class="ibox-title">
                <h5>Re-route:</h5>
            </div>
            <div class="ibox-content">
            
            '.form_open('Request/re_route/'.$list_data->list_id,
                array('name'=>'reroute_form','class'=>'form-horizontal')).' 
                <div class="col-md-4">
                    <div class="form-group"><div class="col-sm-6 strong">Current Approver</div>
                        <div class="col-sm-6">'.$request_data->next_approver.'</div>
                    </div>
                </div>  
                <div class="col-md-6">
                    <div class="form-group"><div class="col-sm-3 strong">Re-route to:</div>
                        <div class="col-sm-9">'.$approver_name.'</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary rounded btn-3d">Re-route</button>
                </div>
            </form>
            </div>
        </div
        ':''
        ).'

        <h4>Workflow Path:</h4><hr>
        <div class="bs-example bs-example-popover" data-example-id="static-popovers"> 
            <div class="popover left '.(empty($request_data)?'mainactive':'').'">
                <div class="arrow"></div> 
                <h3 class="popover-title '.(empty($request_data)?'mainheader':'').'">Initiator</h3>
                <div class="popover-content '.(empty($request_data)?'mainactive':'').'"> 
                    <strong>'.$initated_by->first_name.' '.$initated_by->last_name.'</strong>
                     <br/>Initiator 
                </div> 
            </div> ';
$cnt_appr=1;
if (empty($approvers_list)){
    echo'';
}else {
    foreach($approvers_list as $approver) {
        echo'
            <div class="popover '.($approvers_count==$cnt_appr?'':'left ')
                .($approver->appr_name==$request_data->next_appr?'mainactive ':'').'">
                <div class="arrow"></div>
                <h3 class="popover-title '.($approver->appr_name==$request_data->next_appr?'mainheader ':'').'">'
                .($approvers_count==$cnt_appr?'Final Approver':'Approver '.$cnt_appr).'</h3>
                <div class="popover-content '.($approver->appr_name==$request_data->next_appr?'mainactive ':'').'"> 
                    <strong>'.$approver->approver_name.'</strong><br/>'.$approver->appr_function.' 
                </div> 
            </div> 
            ';
        $cnt_appr+=1;
    }
}
        echo'
            <div class="clearfix"></div> 
        </div>
        

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
                <tbody>';
            if (empty($workflow_history)){
                echo'<tr><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
            }else {
                foreach($workflow_history as $history) {
                    echo'        
                    <tr>
                      <td>'.$history->appr_name.'</td>
                      <td>'.$history->appr_function.'</td>
                      <td>'.$history->appr_status.'</td>
                      <td>'.$history->appr_date.'</td>
                      <td>'.$history->appr_comment.'</td>
                      <td>'.$history->next_approver.'</td>
                    </tr>';
                }
            }
            echo'
                </tbody>
            </table>
        </div>
    </div>
</section>        
';
?>        