<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');
echo'
<section class="services-wrap">        
    <div class="container">
        <div class="ibox">
            <div class="ibox-title">
                <h5>Key Acct Rewards List:</h5>   
                    <div class="ibox-tools">
                        <a href="javascript:add_row()" data-toggle="tooltip" data-placement="bottom" 
                            title="" data-original-title="Add New Key Acct Reward Item">
                            <i class="fa fa-plus"></i>
                        </a>   
                        <a href="javascript:delete_row()" data-toggle="tooltip" data-placement="bottom" 
                            title="" data-original-title="Delete Selected">
                            <i class="fa fa-trash-o"></i>
                        </a> 
                    </div>                  
            </div>
                
            <div class="ibox-content">        
                <div class="table-responsive">
                    <table id="example" class="display table table-hover table-striped"  width="100%">
                         <thead>
                           <tr>                     
                                <th>S/No</th>
                                <th>SKU Code</th>
                                <th>SKU Name</th>
                                <th>Base</th> 
                                <th>Growth</th> 
                                <th>Premium</th> 
                                <th>Focus Brand</th>
                                <th>Price Sold</th>
                                <th></th>     
                                <th></th>
                           </tr>
                         </thead>
                         <tbody>
                       </table>
                 </div>
                <div class="divide30"></div>
                <h5>Import Reward List</h5><hr/>
                '.form_open_multipart('Setup/import/key_acct_rewards',array('name'=>'update_form','class'=>'form-horizontal')).'       
                    <div class="col-md-8">
                        <div class="form-group">
                            <label class="col-sm-4 control-label">Select Reward List to import:</label>
                            <div class="col-sm-4"><input type="file" name="userfile"/></div>
                            <div class="col-sm-4"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" name="login" class="btn btn-primary rounded btn-3d">Import</button>
                    </div>
                '.form_close().'                
            </div>
        </div>   
    </div>
    

    <!-- Modal -->
    <div class="modal fade" id="modal_form" tabindex="-1" role="dialog" 
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
                    <h4 class="modal-title">Modal title</h4>
                </div>
                <!-- Modal Body -->
                <div class="modal-body">
                    <form action="#" id="form" class="form-horizontal">
                        <input type="hidden" name="'.$this->security->get_csrf_token_name()
                            .'" value="'.$this->security->get_csrf_hash().'" />                    
                        <input type="hidden" value="" name="id"/> 
                        
                        <div class="form-group col-sm-6"><label class="col-sm-5 control-label"><nobr>SKU Code:</nobr></label>
                            <div class="col-sm-7">'.$sku_code.'</div>
                        </div>
                        <div class="form-group col-sm-6"><label class="col-sm-5 control-label"><nobr>SKU Name:</nobr></label>
                            <div class="col-sm-7">'.$sku_name.'</div>
                        </div>           
                        <div class="form-group col-sm-6"><label class="col-sm-5 control-label">Base:</label>
                            <div class="col-sm-7"><input type="text" name="base" 
                            class="form-control" placeholder="Enter Base Rebate"></div>
                        </div>  
                       <div class="form-group col-sm-6"><label class="col-sm-5 control-label">Growth:</label>
                            <div class="col-sm-7"><input type="text" name="growth" 
                            class="form-control" placeholder="Enter Growth Rebate"></div>
                        </div>
                        <div class="form-group col-sm-6"><label class="col-sm-5 control-label">Premium:</label>
                            <div class="col-sm-7"><input type="text" name="premium" 
                            class="form-control" placeholder="Enter Premium Rebate"></div>
                        </div>
                        <div class="form-group col-sm-6"><label class="col-sm-5 control-label"><nobr>Focus Brand:</nobr></label>
                            <div class="col-sm-7"><input type="text" name="focus_brand" 
                            class="form-control" placeholder="Enter Focus Brand Rebate"></div>
                        </div>
                        <div class="form-group"></div>     
                        
                    </form>              
                </div>
                <!-- Modal Footer -->
                <div class="modal-footer">
                    <button type="button" id="btnSave" onclick="save()" class="btn btn-primary">Save</button>
                    <button type="button" class="btn btn-danger" data-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div> 
</section>      


';
?>        