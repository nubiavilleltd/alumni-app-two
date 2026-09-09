<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');
$this->load->view('requests/request_header');
if($wkfl_request->request_status=="New" && ($wkfl_request->requester_id==$this->ion_auth->get_user_id())) {
    $action='initiator';
    $editmode=TRUE;
    $readonly='';
} elseif($wkfl_request->request_status=="Returned to" && ($wkfl_request->requester_id==$this->ion_auth->get_user_id())
    && $wkfl_request->next_appr==$this->ion_auth->get_user_id()) {
    $action='resubmit';   
    $editmode=TRUE;
    $readonly='';     
} elseif ($wkfl_request->request_status=="Awaiting Approval" && $wkfl_request->next_appr==$this->ion_auth->get_user_id()) {
    $action='approver';
    $editmode=FALSE;
    $readonly='readonly="readonly"';
} else {
    $action='viewer';
    $editmode=FALSE;
    $readonly='readonly="readonly"';
}
echo'
                    <div class="col-sm-6">
                        <div class="form-group"><label class="col-sm-5 col-md-4  control-label">Department</label>
                            <div class="col-sm-7 col-md-8">'.($editmode?$dept:
                                '<input name="dept" type="text" class="form-control white_bkgd" 
                                value="'.$req_details->dept.'" readonly="readonly">').'</div>
                        </div>
                    </div> 
                    <div class="col-sm-6">
                        <div class="form-group"><label class="col-sm-5 col-md-4  control-label">Transaction Month</label>
                            <div class="col-sm-7 col-md-8">
                                <input id="choosemnth" name="post_month" type="text" class="form-control white_bkgd" 
                                value="'.($req_details->post_month=='0000-00-00'?'':date("M Y", strtotime($req_details->post_month))).'" '.$readonly.'></div>    
                        </div>
                    </div>  
                    <div class="col-sm-6">
                        <div class="form-group"><label class="col-sm-5 col-md-4  control-label">Currency</label>
                            <div class="col-sm-7 col-md-8">'.($editmode?$currency:
                                '<input name="currency" type="text" class="form-control white_bkgd" 
                                value="'.$req_details->currency.'" readonly="readonly">').'</div> 
                        </div>
                    </div>                                     
                    <div class="col-sm-6">
                        <div class="form-group"><label class="col-sm-5 col-md-4  control-label">Card Type</label>
                            <div class="col-sm-7 col-md-8">'.($editmode?$card_type:
                                '<input name="$card_type" type="text" class="form-control white_bkgd" 
                                value="'.$req_details->card_type.'" readonly="readonly">').'</div> 
                        </div>
                    </div>      
                    <div class="col-sm-12">
                        <div class="form-group"><label class="col-sm-2 col-md-2  control-label">Description</label>
                            <div class="col-sm-10 col-md-10"><textarea class="form-control" name="details" id="details"
                                rows="2" '.$readonly.'>'.$req_details->details.'</textarea></div> 
                        </div>
                    </div>                      
                    '.($action=='approver'||$action=='resubmit'?'
                    <div class="col-sm-12">
                        <div class="form-group"><label class="col-sm-2 col-md-2 control-label">Approver\'s Comments (if any):</label>
                            <div class="col-sm-10 col-md-10"><textarea class="form-control" name="comment" id="comment" placeholder="Enter Comments"
                                rows="2"></textarea></div>
                        </div>
                    </div>':'').'                      
                </div>
            </div>     
        </form>   
        <div class="ibox">
            <div class="ibox-title"><h6>Transaction Details</h6>
                <div class="ibox-tools">
                '.($editmode?'
                    <a href="javascript:actionIt(\'subform\');" data-toggle="tooltip" data-placement="bottom" 
                        title="" data-original-title="Add New Transaction">
                        <i class="fa fa-plus"></i>
                    </a>   
                    <a href="javascript:delete_row_claims()" data-toggle="tooltip" data-placement="bottom" 
                        title="" data-original-title="Delete Selected">
                        <i class="fa fa-trash-o"></i>
                    </a>         
                ':'').'
                </div>             
            </div>        
            <div class="ibox-content">
                <div class="table-responsive">
                     <table id="card_details" class="display table table-hover table-striped nowrap"  width="100%">
                         <thead>
                           <tr>
                            '.$card_details_cols.'
                           </tr>  
                         </thead>
                         <tbody>
                         </tbody>
                         <tfoot>
                            '.$card_details_foot.'
                         </tfoot>                         
                     </table>
                 </div>
            </div>     
        </div>        
        '; 
        $this->load->view('requests/request_workflow');
        echo '    
        <!-- Modal -->
        <div class="modal fade" id="card_details_modal" tabindex="-1" role="dialog" 
             aria-labelledby="myModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <!-- Modal Header -->
                    <div class="modal-header">
                        <button type="button" class="close" 
                           data-dismiss="modal">
                               <span aria-hidden="true">&times;</span>
                               <span class="sr-only">Close</span>
                        </button>
                        <h4 class="modal-title">Enter Transaction Details</h4>
                    </div>
                    <!-- Modal Body -->
                    <div class="modal-body">
                        <form action="#" id="card_details_form" class="form-horizontal">
                            <input type="hidden" name="'.$this->security->get_csrf_token_name()
                                .'" value="'.$this->security->get_csrf_hash().'" />                    
                            <input type="hidden" value="" name="id"/> 
                            <div class="form-group"><label class="col-sm-5 col-md-4 control-label">Account Type:</label>
                                <div class="col-sm-7 col-md-8">'.$acct_type.'</div>
                            </div> 
                            <div class="form-group"><label class="col-sm-5 col-md-4 control-label">Account Detail:</label>
                                <div class="col-sm-7 col-md-8"><select class="form-control" name="acct_desc" id="acct_type">
                                    <option value=" ">-- Account Desc --</option>
                                    </select></div>
                            </div>                        
                            <div class="form-group"><label class="col-sm-5 col-md-4 control-label">Account No:</label>
                                <div class="col-sm-7 col-md-8"><input name="acct_no" type="text" class="form-control" 
                                    placeholder="Enter Short Description of expense" id="acct_no"></div>                                
                            </div>                                       
                            <div class="form-group"><label class="ccol-sm-5 col-md-4 control-label">Description:</label>
                                <div class="col-sm-7 col-md-8"><input name="description" type="text" class="form-control" 
                                    placeholder="Enter Short Description of expense"></div>
                            </div>                     
                            <div class="form-group"><label class="col-sm-5 col-md-4 control-label">Amount Spent:</label>
                                <div class="col-sm-7 col-md-8"><input name="trans_amt" type="text" class="form-control"></div>
                            </div>     
                            <div class="form-group"><label class="col-sm-5 col-md-4 control-label">SKU:</label>
                                <div class="col-sm-7 col-md-8"><input name="trans_sku" type="text" class="form-control"></div>
                            </div>                          
                        </form>
                    </div>               
                    <!-- Modal Footer -->
                    <div class="modal-footer">
                        '.($editmode?'
                        <button type="button" id="btnSave" onclick="save_card_details()" class="btn btn-primary">Save</button>
                        ':'').'<button type="button" class="btn btn-danger" data-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>    
';