<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');
$action='';
$script='
<script language="javascript">
 function actionIt(actionType) {
    document.getElementById("actionType").value = actionType; 
    
    if(document.getElementById("comment").value == \'\'){

        swal({
            title: "Comments box is empty!",
            text: "Are you sure you want to submit without comments?",
            type: "warning",
            showCancelButton: true,
            confirmButtonColor: \'#d33\',
            confirmButtonText: \'Cancel Submit!\',
            cancelButtonText: \'Submit Form!\',           
            closeOnConfirm: true,
        },
        function (isConfirm) {
            if (!isConfirm) {
                document.comment_form.submit();
            }
        });

    } else {
        document.comment_form.submit();
    }
}
</script>
';
if(empty($request_data) && ($list_data->initiated_by==$this->ion_auth->get_user_id())) {
    $action='initiator';
} elseif(!empty($request_data) && ($list_data->initiated_by==$this->ion_auth->get_user_id())
        && $request_data->next_appr==$this->ion_auth->get_user_id()) {
    $action=$request_data->order_no==0?'resubmit':'approver';    
} elseif ($request_data->next_appr==$this->ion_auth->get_user_id()) {
    $action='approver';    
} else {
    $action='viewer';
     $script='';
}

if (($request_data->next_appr==$this->ion_auth->get_user_id()
        && count($approvers_list)==$request_data->order_no)||
    ($request_data->request_status==='Approved' 
        && $request_data->requester_id==$this->ion_auth->get_user_id())) { 
    
    $action='printer';
}
    
echo $script.'
    
<section class="services-wrap">        
    <div class="container">
        <div class="ibox">
            <div class="ibox-title">
                <h5>'.$page_title.'</h5>
                <div class="ibox-tools">'
                    .($action==='initiator'?'
                    <a href="javascript:actionIt(\'submit\');" 
                        data-toggle="tooltip" data-placement="bottom" title="" 
                        data-original-title="Submit Request"><i class="fa fa-check-square-o"></i>
                    </a>          
                    <a href="'.site_url('rewards/delete_row/'.$data_var).'"data-toggle="tooltip" data-placement="bottom" 
                       onclick="return confirm(\'Are you sure delete this winners list? This process is irreversible\');"
                        title="" data-original-title="Delete Winners List (Irrevesible)">
                        <i class="fa fa-trash-o"></i>
                    </a>                    
                    ':($action==='resubmit'?'
                    <a href="javascript:actionIt(\'resubmit\');" 
                        data-toggle="tooltip" data-placement="bottom" title="" 
                        data-original-title="Submit Request"><i class="fa fa-check-square-o"></i>
                    </a>   
                    <a href="'.site_url('rewards/delete_row/'.$data_var).'"data-toggle="tooltip" data-placement="bottom" 
                       onclick="return confirm(\'Are you sure delete this winners list? This process is irreversible\');"
                        title="" data-original-title="Delete Winners List (Irrevesible)">
                        <i class="fa fa-trash-o"></i>
                    </a>                        
                    ':($action==='approver'?'    
                    <a href="javascript:actionIt(\'approve\');" 
                        data-toggle="tooltip" data-placement="bottom" title="" 
                        data-original-title="Approve Request"><i class="fa fa-check-square-o"></i>
                    </a>  '.
//                    <a href="javascript:actionIt(\'deny\');" 
//                        data-toggle="tooltip" data-placement="bottom" title="" 
//                        data-original-title="Deny Request"><i class="fa fa-ban"></i>
//                    </a>  
                    '<a href="javascript:actionIt(\'return\');" 
                        data-toggle="tooltip" data-placement="bottom" title="" 
                        data-original-title="Deny Request"><i class="fa fa-external-link"></i>
                    </a>   
                ':($action==='printer'?'     
                    <a href="'.site_url('rewards/approve_list/'.$data_var).'" target="_blank" 
                        data-toggle="tooltip" data-placement="bottom" title="" 
                        data-original-title="Generate Vouchers"><i class="fa fa-print"></i>
                    </a>                       
                    ':'')))).'
                    <a href="'.site_url('Rewards/view_winners_list').'" data-toggle="tooltip" 
                        data-placement="bottom" title="" data-original-title="Return">
                        <i class="fa fa-undo"></i>
                    </a>                    
                </div>            
            </div>
            <div class="ibox-content">
                '.form_open('Request/action_request/'
                    .$process_data->process_id.'/'.$list_data->list_id,
                    array('name'=>'comment_form','class'=>'form-horizontal')).' 
                    <input type="hidden" value="_blank" name="actionType" id="actionType"/>
                    <div class="col-md-5">
                        <div class="form-group"><div class="col-sm-5 strong">Date Initiated:</div>
                            <div class="col-sm-7">'.date_create($list_data->initiated_date)->format('g:ia, d M, Y').'</div>
                        </div>
                    </div>                
                    <div class="col-md-3">
                        <div class="form-group"><div class="col-sm-4 strong">Period</div>
                            <div class="col-sm-8">'.$list_data->fullmonth.'</div>
                        </div>
                    </div>    
                    <div class="col-md-4">
                        <div class="form-group"><div class="col-sm-5 strong">Customer:</div>
                            <div class="col-sm-7">'.$list_data->cust_type.'</div>
                        </div>
                    </div>   
                    <div class="col-md-5">
                        <div class="form-group"><div class="col-sm-5 strong">Date Submitted:</div>
                            <div class="col-sm-7">'
                            .(empty($request_data->request_date)?'Not yet submitted':
                            date_create($request_data->request_date)->format('g:ia, d M, Y')).'</div>
                        </div>
                    </div>                                      
                    <div class="col-md-3">
                        <div class="form-group"><div class="col-sm-4 strong">Market:</div>
                            <div class="col-sm-8">'.$list_data->market.'</div>
                        </div>
                    </div>     
                    <div class="col-md-4">
                        <div class="form-group"><div class="col-sm-5 strong">Initiator:</div>
                            <div class="col-sm-7">'.$initated_by->fullname.'</div>
                        </div>
                    </div>                      
                    <div class="col-md-5">
                        <div class="form-group"><div class="col-sm-5 strong">Rebate Type:</div>
                            <div class="col-sm-7">'.$list_data->campaign.'</div>
                        </div>
                    </div>   
                    <div class="col-md-3">
                        <div class="form-group"><div class="col-sm-4 strong">Process:</div>
                            <div class="col-sm-8">'.$list_data->process_type.'</div>
                        </div>
                    </div>                      
                    <div class="col-md-4">
                        <div class="form-group"><div class="col-sm-6 strong">Period-Week:</div>
                            <div class="col-sm-6">Week '.$list_data->period_week.'</div>
                        </div>
                    </div>'.($list_data->process_type=="Imported"?'
                    <div class="col-md-6">
                        <div class="form-group"><div class="col-sm-4 strong">Status:</div>
                            <div class="col-sm-8">'.(empty($request_data->request_status)?'Not yet submitted':
                            $request_data->request_status.' '.$request_data->next_approver).'</div>
                        </div>
                    </div>   
                    <div class="col-md-6">'.(empty($list_data->filename)?'No evaluation data uploaded':
                                    '<a href="'.site_url($list_data->filename).'">Click to view evaluation data</a>').'
                    </div>                                         
                    ':'').'
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
                '.($action==='printer'?'
                    '.form_open('rewards/area_pdf/'.$data_var,array('name'=>'pdf_form','class'=>'form-horizontal','target'=>'_blank')).'
                    <div class="ibox-tools">
                        <div class="col-md-6 pull-right">
                            <div class="form-group">                                
                                <div class="col-md-5">Generate PDF By Area</div>
                                <div class="col-md-5 text-left">'.$area.'</div>
                                <div class="col-md-2">
                                    <a href="javascript:document.pdf_form.submit();" data-toggle="tooltip" 
                                    data-placement="bottom" title="" data-original-title="Submit Form">
                                        <i class="fa fa-check-square"></i>
                                    </a>    
                                </div>
                            </div>
                        </div>                                         
                    </div>
                    '.form_close().'
                ':'').'         
            </div>
            <div class="ibox-content">
                <div class="table-responsive">
                     <table id="example" class="display table table-hover table-striped"  width="100%">
                        <thead>
                           <tr>
                            '.($list_data->process_type=="Imported"?                           
                            ' <th>URN</th>
                             <th>Customer</th>
                             <th>Location</th>                             
                             <th>Band</th>                      
                            '.($list_data->cust_type=="WAM Customers"?'':'
                             <th>Prorated</th>').'
                             <th>Actual</th> 
                             <th>Target</th>                                 
                             <th>Percent</th>
                             <th>Payout</th>
                             <th>Chq No</th>
                             <th>Reason</th>
                            ':'
                             <th>URN</th>
                             <th>Customer</th>
                             <th>Location</th>                             
                             <th>Band</th>                                          
                            '.($list_data->cust_type=="WAM Customers"?'':'
                             <th>KPI</th><th>Prorated</th>').'
                             <th>Actual</th> 
                             <th>Target</th>                                    
                             <th>Percent</th>
                             <th>Payout</th>
                             <th>Chq No</th>
                            ').'
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
                <div class="col-md-6">
                    <div class="form-group"><div class="col-sm-5 strong">Current Approver</div>
                        <div class="col-sm-7">'.$request_data->next_approver.'</div>
                    </div>
                </div>  
                <div class="col-md-6">
                    <div class="form-group"><div class="col-sm-5 strong">Re-route to:</div>
                        <div class="col-sm-7">'.$approver_name.'</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group"><div class="col-sm-5 strong">Reason:</div>
                        <div class="col-sm-7"><input type="text" value="" name="comment" id="comment"/></div>
                    </div>
                </div>                
                <div class="col-md-6">
                    <button type="submit" class="btn btn-primary rounded btn-3d">Re-route</button>
                </div>
            
            </form>
            </div>
        </div>

        ':''
        );
        if ($action!=='initiator'){
        echo'
        <h4>Workflow Path:</h4><hr>
        <div class="bs-example bs-example-popover" data-example-id="static-popovers"> 
            <div class="popover left '.(empty($request_data)||$request_data->order_no==0?'mainactive':'').'">
                <div class="arrow"></div> 
                <h3 class="popover-title '.(empty($request_data)||$request_data->order_no==0?'mainheader':'').'">Initiator</h3>
                <div class="popover-content '.(empty($request_data)||$request_data->order_no==0?'mainactive':'').'"> 
                    <strong>'.$initated_by->fullname.'</strong>
                     <br/>Initiator 
                </div> 
            </div> ';
            $cnt_appr=1;
            if (empty($approvers_list)){
                echo'';
            }else {
                foreach($approvers_list as $approver) {
                   $active=$approver->appr_name==$request_data->next_appr && $request_data->order_no!=0?TRUE:FALSE;
                    echo'
                        <div class="popover '.($approvers_count==$cnt_appr?'':'left ')
                            .($active?'mainactive ':'').'"><div class="arrow"></div><h3 class="popover-title '.
                            ($active?'mainheader ':'').'">'.($approvers_count==$cnt_appr?'Final Approver':
                            'Approver '.$cnt_appr).'</h3><div class="popover-content '.($active?'mainactive ':'').'"> 
                            <strong>'.(empty($approver->approver_name) && $approver->approver_from=='Initiator'?
                            $initated_by->fullname:
                            $approver->approver_name).'</strong><br/>'.$approver->appr_function.' 
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
            </div>';
        }
        echo'
    </div>
</section>        
';
?>        