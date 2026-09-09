<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');
echo'
<section class="services-wrap">        
    <div class="container">
        <div class="ibox">
            <div class="ibox-title">
                <h5>Setup System Parameters:-</h5>  
                <div class="ibox-tools">
                    <a href="javascript:add_row()" data-toggle="tooltip" data-placement="bottom" 
                        title="" data-original-title="Add New Parameter">
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
                                <th>REF</th>
                                <th>Parameters</th>
                                <th>Setup Value</th>
                                <th></th>
                                <th></th>
                           </tr>                          
                         </thead>
                         <tbody>
                         </tbody>
                       </table>
                 </div>              
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
                        <div class="form-group"><label class="col-sm-4 control-label">Setup Parameters:</label>
                            <div class="col-sm-8"><input name="setup_name" type="text" class="form-control" 
                            placeholder="Enter Setup Parameters"></div>
                        </div>            
                        <div class="form-group"><label class="col-sm-4 control-label">Setup Vaues:</label>
                            <div class="col-sm-8">
                                <textarea name="setup_value" cols="40" rows="10" class="form-control" 
                                    placeholder="Enter Setup Values"></textarea>
                            </div>
                        </div>                 
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