<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');
echo'
<section class="services-wrap">        
    <div class="container">
        <div class="ibox">
            <div class="ibox-title">
                <h5>Urban and Rural Reward List:</h5>   
                    <div class="ibox-tools">
                        <a href="javascript:add_row()" data-toggle="tooltip" data-placement="bottom" 
                            title="" data-original-title="Add New Reward Item">
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
                                <th>SKUCode</th>
                                <th>SKUName</th>                              
                                <th>70%</th> 
                                <th>80%</th>
                                <th>90%</th> 
                                <th>100%</th> 
                                <th>Growth</th> 
                                <th>Sellout</th> 
                                <th>Redx</th> 
                                <th>Participate</th> 
                                <th>Quarterly</th> 
                                <th>Loyalty</th> 
                                <th>DD</th> 
                                <th>Market</th> 
                                <th>Category</th> 
                                <th>Band</th> 
                                <th></th>     
                                <th></th>
                           </tr>
                         </thead>
                         <tbody>
                       </table>
                 </div>
                <div class="divide30"></div>
                <h5>Import Reward List</h5><hr/>
                '.form_open_multipart('Setup/import/rewards',array('name'=>'update_form','class'=>'form-horizontal')).'       
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
                        <div class="form-group col-sm-12">
                            <input type="hidden" name="'.$this->security->get_csrf_token_name()
                                .'" value="'.$this->security->get_csrf_hash().'" />                    
                            <input type="hidden" value="" name="id"/> 
                        </div>   
                        <div class="form-group col-sm-6"><label class="col-sm-5 control-label">SKU_Code:</label>
                            <div class="col-sm-7">'.$sku_code.'</div>
                        </div>
                        <div class="form-group col-sm-6"><label class="col-sm-5 control-label">SKU_Name:</label>
                            <div class="col-sm-7">'.$sku_name.'</div>
                        </div>
                        <div class="form-group col-sm-6"><label class="col-sm-5 control-label">Market:</label>
                            <div class="col-sm-7">'.$market.'</div>
                        </div>
                        <div class="form-group col-sm-6"><label class="col-sm-5 control-label">Band:</label>
                            <div class="col-sm-7">'.$band.'</div>
                        </div>    
                        <div class="form-group col-sm-6"><label class="col-sm-5 control-label">Category:</label>
                            <div class="col-sm-7">'.$category.'</div>
                        </div>                          
                        <div class="form-group col-sm-6"><label class="col-sm-5 control-label">70%:</label>
                            <div class="col-sm-7"><input type="text" name="r70" 
                            class="form-control" placeholder="Enter Rebate at 70%"></div>
                        </div>
                        <div class="form-group col-sm-6"><label class="col-sm-5 control-label">80%:</label>
                            <div class="col-sm-7"><input type="text" name="r80" 
                            class="form-control" placeholder="Enter Rebate At 80%"></div>
                        </div>
                       <div class="form-group col-sm-6"><label class="col-sm-5 control-label">90%:</label>
                            <div class="col-sm-7"><input type="text" name="r90" 
                            class="form-control" placeholder="Enter Rebate at 90%"></div>
                        </div>
                        <div class="form-group col-sm-6"><label class="col-sm-5 control-label">100%:</label>
                            <div class="col-sm-7"><input type="text" name="r100" 
                            class="form-control" placeholder="Enter Rebate At 100%"></div>
                        </div>  
                       <div class="form-group col-sm-6"><label class="col-sm-5 control-label">Growth:</label>
                            <div class="col-sm-7"><input type="text" name="growth" 
                            class="form-control" placeholder="Enter Rebate Above 100%"></div>
                        </div>
                        <div class="form-group col-sm-6"><label class="col-sm-5 control-label">Sell Out:</label>
                            <div class="col-sm-7"><input type="text" name="sell_out" 
                            class="form-control" placeholder="Enter Sell Out Rebate"></div>
                        </div>
                        <div class="form-group col-sm-6"><label class="col-sm-5 control-label">Participation:</label>
                            <div class="col-sm-7"><input type="text" name="participate" 
                            class="form-control" placeholder="Enter Participation Rebate"></div>
                        </div>
                        <div class="form-group col-sm-6"><label class="col-sm-5 control-label">Redx:</label>
                            <div class="col-sm-7"><input type="text" name="redx" 
                            class="form-control" placeholder="Enter Redx"></div>
                        </div>                        
                        <div class="form-group col-sm-6"><label class="col-sm-5 control-label">Quarterly:</label>
                            <div class="col-sm-7"><input type="text" name="quarterly" 
                            class="form-control" placeholder="Enter Quarterly Rebate"></div>
                        </div>
                        <div class="form-group col-sm-6"><label class="col-sm-5 control-label">Loyalty:</label>
                            <div class="col-sm-7"><input type="text" name="loyalty" 
                            class="form-control" placeholder="Enter Loyalty Rebate"></div>
                        </div>
                        <div class="form-group col-sm-6"><label class="col-sm-5 control-label">DD:</label>
                            <div class="col-sm-7"><input type="text" name="loyalty_double" 
                            class="form-control" placeholder="Enter Double Double Rebate"></div>
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