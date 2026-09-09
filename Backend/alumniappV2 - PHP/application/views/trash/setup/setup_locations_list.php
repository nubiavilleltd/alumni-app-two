<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');
echo'
<section class="services-wrap">    
    <div class="container">
        <div class="ibox">
            <div class="ibox-title">
                <h5>All Location:- <small> List of locations registered on the system</small></h5>
                <div class="ibox-tools">
                    <a href="javascript:add_row()" data-toggle="tooltip" data-placement="bottom" 
                        title="" data-original-title="Add New Location">
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
                             <th>ID</th>
                             <th>Area</th>
                             <th>Region</th>
                             <th>Market</th>
                             <th>Inv Share</th>
                            <th></th>
                            <th></th>                             
                           </tr>
                         </thead>
                         <tfoot>
                          <tr>
                             <th>ID</th>
                             <th>Area</th>
                             <th>Region</th>
                             <th>Market</th>
                             <th>Inv Share</th>
                            <th></th>
                            <th></th>                             
                           </tr>                           
                         </tfoot>
                         <tbody>
                          </tbody>
                       </table>
                    </div>
                <div class="divide30"></div>
                <h5>Import Locations List</h5><hr/>
                '.form_open_multipart('Setup/import/locations',array('name'=>'update_form','class'=>'form-horizontal')).'       
                    <div class="col-md-9">
                        <div class="form-group">
                            <label class="col-sm-5 control-label">select Locations list to import:</label>
                            <div class="col-sm-5"><input type="file" name="userfile"/></div>
                            <div class="col-sm-2"></div>
                        </div>
                    </div>
                    <div class="col-md-3">
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
                        <div class="form-group"><label class="col-sm-4 control-label">Area:</label>
                            <div class="col-sm-8"><input name="area" type="text" class="form-control" 
                            placeholder="Enter Area"></div>
                        </div>            
                        <div class="form-group"><label class="col-sm-4 control-label">Region:</label>
                            <div class="col-sm-8"><input name="region" type="text" class="form-control" 
                                placeholder="Enter Region"></div>
                        </div>   
                        <div class="form-group"><label class="col-sm-4 control-label">Market:</label>
                            <div class="col-sm-8"> '.$market.'</div>
                        </div> 
                        <div class="form-group"><label class="col-sm-4 control-label">Inventory Share:</label>
                            <div class="col-sm-8"><input name="inv_share" type="text" class="form-control" 
                                placeholder="Enter Inventory Share"></div>
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