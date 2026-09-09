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
                        <div class="form-group"><label class="col-sm-5 col-md-4  control-label">Currency</label>
                            <div class="col-sm-7 col-md-8">'.($editmode?$currency:
                                '<input name="currency" type="text" class="form-control white_bkgd" 
                                value="'.$req_details->currency.'" readonly="readonly">').'</div> 
                        </div>
                    </div>      
                    <div class="col-sm-6">
                        <div class="form-group"><label class="col-sm-5 col-md-4  control-label">Transaction Month</label>
                            <div class="col-sm-7 col-md-8">
                                <input id="choosemnth" name="claim_period" type="text" class="form-control white_bkgd" 
                                value="'.($req_details->claim_period=='0000-00-00'?'':date("M Y", strtotime($req_details->claim_period))).'" '.$readonly.'></div>
                        </div>
                    </div>      
                    <div class="col-sm-6">
                        <div class="form-group"><label class="col-sm-5 col-md-4  control-label">Description</label>
                            <div class="col-sm-7 col-md-8"><textarea class="form-control" name="details" id="details"
                                rows="2" '.$readonly.'>'.$req_details->details.'</textarea></div> 
                        </div>
                    </div>                      
                    <div class="col-sm-6">
                        <div class="form-group"><label class="col-sm-5 col-md-4 control-label">Initial Advance:</label>
                            <div class="col-sm-7 col-md-8"><input name="initial_advance" type="text" class="form-control white_bkgd" 
                            value="'.$req_details->initial_advance.'" readonly="readonly"></div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group"><label class="col-sm-5 col-md-4 control-label">Total Expense:</label>
                            <div class="col-sm-7 col-md-8"><input name="total_expense" id="total_expense" type="text" class="form-control white_bkgd" 
                            value="0" readonly="readonly"></div>
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
            <div class="ibox">
                <div class="ibox-title"><h6>Cost Specification</h6>
                    <div class="ibox-tools">
                    '.($editmode?'
                        <a href="javascript:add_row_claims()" data-toggle="tooltip" data-placement="bottom" 
                            title="" data-original-title="Add New Claim">
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
                         <table id="claims" class="display table table-hover table-striped nowrap"  width="100%">
                             <thead>
                               <tr>
                                '.$claims_cols.'
                               </tr>  
                             </thead>
                             <tbody>
                             </tbody>
                             <tfoot>
                                '.$claims_foot.'
                             </tfoot>                         
                         </table>
                     </div>
                </div>     
            </div>
            <div class="ibox">
                <div class="ibox-content">
                    <div class="col-sm-6">
                        <div class="form-group"><label class="col-sm-6 col-md-5 control-label">Due to Company:</label>
                            <div class="col-sm-7 col-md-7"><input name="due_company" type="text" class="form-control white_bkgd" 
                            value="'.$req_details->due_company.'" readonly="readonly"></div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group"><label class="col-sm-6 col-md-5 control-label">Due From Company:</label>
                            <div class="col-sm-7 col-md-7"><input name="from_company" type="text" class="form-control white_bkgd" 
                            value="'.$req_details->from_company.'" readonly="readonly"></div>
                        </div>
                    </div>          
                </div>     
            </div>            
        </form>        
        '; 
        $this->load->view('requests/request_workflow');
        echo '    
        <!-- Modal -->
        <div class="modal fade" id="claims_modal" tabindex="-1" role="dialog" 
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
                        <h4 class="modal-title">Enter Expense Claim Details</h4>
                    </div>
                    
                    <!-- Modal Body -->
                    <div class="modal-body">
                        <form action="#" id="claims_form" class="form-horizontal">
                            <input type="hidden" name="'.$this->security->get_csrf_token_name()
                                .'" value="'.$this->security->get_csrf_hash().'" />                    
                            <input type="hidden" value="" name="id"/>                               
                            <div class="col-md-12">
                                <div class="form-group"><label class="col-sm-5 col-md-4 control-label">Claim Type:</label>
                                    <div class="col-sm-7 col-md-8">'.$claim_type.'</div>
                                </div>
                            </div>                            
                            <div class="col-md-12">
                                <div class="form-group"><label class="ccol-sm-5 col-md-4 control-label">Description:</label>
                                    <div class="col-sm-7 col-md-8"><input name="description" type="text" class="form-control" 
                                        placeholder="Enter Short Description of claim type"></div>
                                </div> 
                            </div>               
                            <div class="col-md-12">
                                <div class="form-group"><label class="col-sm-5 col-md-4 control-label">Amount Spent:</label>
                                    <div class="col-sm-7 col-md-8"><input name="claim_amt" type="text" class="form-control"></div>
                                </div>       
                            </div>                   
                            <div class="col-md-12">
                                <div class="form-group"><label class="col-sm-5 col-md-4 control-label">Attach Files:</label>
                                    <div class="col-sm-7 col-md-8"><input name="atach_files" type="file[]" class="form-control"></div>
                                </div>       
                            </div>                       
                        </form>
                    </div>
                    <!-- Modal Footer -->
                    <div class="modal-footer">
                        '.($editmode?'
                        <button type="button" id="btnSave" onclick="save_claims()" class="btn btn-primary">Save</button>
                        ':'').'<button type="button" class="btn btn-danger" data-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </div>
        </div>            
    </div>
</section>    
';  
        