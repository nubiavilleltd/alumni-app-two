<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');

$editmode=TRUE;
$readonly='readonly="readonly"';
$action='';
$script='
<script language="javascript">
    function actionIt(actionType) {
        document.getElementById("actionType").value = actionType; 
        document.comment_form.submit();
    }
</script>
';
if(($wkfl_request->request_status=="New" 
    && ($wkfl_request->requester_id==$this->ion_auth->get_user_id()))||
    ($wkfl_request->request_status=="Returned to" 
        && ($wkfl_request->requester_id==$this->ion_auth->get_user_id())
    && $wkfl_request->next_appr==$this->ion_auth->get_user_id() ) )
{
    $action='initiator';
    $editmode=TRUE;
    $readonly='';
    $form_url=$update_request;      
} else {
    $action='viewer';
    $editmode=FALSE;
    $readonly='readonly="readonly"';
    $form_url='';
     $script='';
}

echo $script.'
<section class="services-wrap">        
    <div class="container">
        '.form_open_multipart($form_url.$data_var.(empty($req_details->card_id)?'/add':'/update'),
            array('name'=>'comment_form','class'=>'form-horizontal')).'        
            <div class="ibox">
                <input type="hidden" value="_blank" name="actionType" id="actionType"/>
                <input type="hidden" value="'.$wkfl_request->process_id.'" name="process_id" id="process_id"/>
                <input type="hidden" value="'.$req_details->card_id.'" name="id" id="id"/>   
                <input type="hidden" value="'.$req_details->acct_val.'" name="acct_val" id="acct_val"/>                    
                <div class="ibox-title">
                    <h5>'.$page_title.' :- '.$data_var.'</h5>
                    <div class="ibox-tools">'
                        .($action==='initiator'?'
                            <a href="javascript:actionIt(\'subform\');" 
                                data-toggle="tooltip" data-placement="bottom" title="" 
                                data-original-title="Save Detail"><i class="fa fa-check-square-o"></i>
                            </a>          
                            <a href="'.site_url('request/delete_row/request_card_details/'.$data_var).'"data-toggle="tooltip" data-placement="bottom" 
                               onclick="return confirm(\'Are you sure delete this request? This process is irreversible\');"
                                title="" data-original-title="Delete Detail (Irrevesible)">
                                <i class="fa fa-trash-o"></i>
                            </a>                    
                            ':'').'
                            <a href="'.site_url('requests/request_form/request_card/'.$data_var).'" data-toggle="tooltip" 
                                data-placement="bottom" title="" data-original-title="Return">
                                <i class="fa fa-undo"></i>
                            </a>                         
                    </div>
                </div> 
                <div class="ibox-content">  
                    <div class="col-sm-6">
                        <div class="form-group"><label class="col-sm-5 col-md-4  control-label">Account Type</label>
                            <div class="col-sm-7 col-md-8">'.($editmode?$acct_type:
                                '<input name="acct_type" type="text" class="form-control white_bkgd" 
                                value="'.$req_details->acct_type.'" readonly="readonly">').'</div>
                        </div>
                    </div> 
                    <div class="col-sm-6">
                        <div class="form-group"><label class="col-sm-5 col-md-4  control-label">Account Detail</label>
                            <div class="col-sm-7 col-md-8">'.($editmode?'<select class="chosen-select-no-results form-control" name="acct_desc" id="acct_desc">
                            <option value="'.$req_details->acct_no.' ">'.$req_details->acct_desc.'</option>
                            </select>':
                                '<input name="acct_desc" type="text" class="form-control white_bkgd" 
                                value="'.$req_details->acct_desc.'" readonly="readonly">').'</div>
                        </div>
                    </div>                     
                    <div class="col-sm-6">
                        <div class="form-group"><label class="col-sm-5 col-md-4  control-label">Account No:</label>
                            <div class="col-sm-7 col-md-8">
                                <input id="acct_no" name="acct_no" type="text" class="form-control white_bkgd" 
                                value="'.$req_details->acct_no.'" readonly="readonly"></div>    
                        </div>
                    </div>                
                    <div class="col-sm-6">
                        <div class="form-group"><label class="col-sm-5 col-md-4 control-label">Description:</label>
                            <div class="col-sm-7 col-md-8"><input name="description" type="text" class="form-control white_bkgd" 
                            value="'.$req_details->description.'" placeholder="Enter Short Description of expense"></div>
                        </div>
                    </div>   
                    <div class="col-sm-6">
                        <div class="form-group"><label class="col-sm-5 col-md-4 control-label">Amount Spent:</label>
                            <div class="col-sm-7 col-md-8"><input name="trans_amt" type="text" class="form-control white_bkgd" 
                            value="'.$req_details->trans_amt.'"></div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group"><label class="col-sm-5 col-md-4 control-label">SKU:</label>
                            <div class="col-sm-7 col-md-8"><input name="trans_sku" type="text" class="form-control white_bkgd" 
                            value="'.$req_details->trans_sku.'"></div>
                        </div>
                    </div>                    
                </div>
            </div>     
            <div class="ibox">
                <div class="ibox-title"><h6>Transaction Details</h6>            
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
        </form>        
    </div>
</section>    
';