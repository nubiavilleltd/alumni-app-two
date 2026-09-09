<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');
echo'
<section class="services-wrap">        
    <div class="container">
        <div class="ibox">
            <div class="ibox-title">
                <h5>Users List:- <small> List of Users</small></h5>       
                <div class="ibox-tools">
                    <div class="ibox-tools">
                        <a href="javascript:add_row()" data-toggle="tooltip" data-placement="bottom" 
                            title="" data-original-title="Add New User">
                            <i class="fa fa-plus"></i>
                        </a>   
                        <a href="javascript:delete_row()" data-toggle="tooltip" data-placement="bottom" 
                            title="" data-original-title="Delete Selected">
                            <i class="fa fa-trash-o"></i>
                        </a> 
                    </div>  
                </div>                
            </div>
            <div class="ibox-content">
                <div class="table-responsive">
                    <table id="example" class="display table table-hover table-striped"  width="100%">
                         <thead>
                            <tr>
                              <th>REF</th>
                              <th>Name</th>
                              <th>Designation</th>
                              <th>Email</th>
                              <th>Phone</th>
                              <th>Role</th>
                              <th>Market</th>
                              <th>Status</th>
                              <th></th>
                              <th></th>
                            </tr>                          
                         </thead>
                         <tbody>
                         </tbody>
                    </table>
                </div>

                <div class="divide30"></div>
                <h5>Import Users List</h5><hr/>
                '.form_open_multipart('Setup/import/users',array('name'=>'update_form','class'=>'form-horizontal')).'       
                    <div class="col-md-9">
                        <div class="form-group">
                            <label class="col-sm-4 control-label">Select users list to import:</label>
                            <div class="col-sm-5"><input type="file" name="userfile"/></div>
                            <div class="col-sm-3"></div>
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
    <form action="#" id="form" class="form-horizontal">
    <div class="modal fade" id="modal_form" tabindex="-1" role="dialog" 
         aria-labelledby="myModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
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
                    <input type="hidden" name="'.$this->security->get_csrf_token_name()
                        .'" value="'.$this->security->get_csrf_hash().'" />                    
                    <input type="hidden" value="" name="id"/> 
                    <div class="col-md-6">
                        <div class="form-group"><label class="col-sm-4 control-label">First Name:</label>
                            <div class="col-sm-8"><input name="first_name" type="text" class="form-control" 
                            placeholder="Enter First Name"></div>
                        </div>
                    </div>              
                    <div class="col-md-6">
                        <div class="form-group"><label class="col-sm-4 control-label">Last Name:</label>
                            <div class="col-sm-8"><input name="last_name" type="text" class="form-control" 
                            placeholder="Enter Last Name"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group"><label class="col-sm-4 control-label">Email:</label>
                            <div class="col-sm-8"><input type="text" name="email" 
                            class="form-control" placeholder="Enter Email"></div>
                        </div>
                    </div>    
                    <div class="col-md-6 hide_this">
                        <div class="form-group"><label class="col-sm-4 control-label">Confirm:</label>
                            <div class="col-sm-8"><input type="text" name="email2" 
                            class="form-control" placeholder="Confirm Email"></div>
                        </div>
                    </div>                          
                    <div class="col-md-6 hide_this">
                        <div class="form-group"><label class="col-sm-4 control-label">Password:</label>
                            <div class="col-sm-8"><input type="password" name="password" 
                            class="form-control" placeholder="Enter Password"></div>
                        </div>
                    </div>  
                    <div class="col-md-6 hide_this">
                        <div class="form-group"><label class="col-sm-4 control-label">Confirm:</label>
                            <div class="col-sm-8"><input type="password" name="repass" 
                            class="form-control" placeholder="Re-enter Password"></div>
                        </div>
                    </div>    
                    <div class="col-md-6">
                        <div class="form-group"><label class="col-sm-4 control-label">Phone:</label>
                            <div class="col-sm-8"><input type="text" name="phone"
                            class="form-control" data-mask="999-9999999999" 
                            placeholder="Enter Phone No - e.g. 234-8012345678"></div>
                        </div>
                    </div>                          
                    <div class="col-md-6">
                        <div class="form-group"><label class="col-sm-4 control-label">User Role</label>
                            <div class="col-sm-8">'.$user_role.'</div>
                        </div>
                    </div>     
                    <div class="col-md-6">
                        <div class="form-group"><label class="col-sm-4 control-label">Designation:</label>
                            <div class="col-sm-8"><input type="text" name="designation" 
                            class="form-control" placeholder="Enter Job Designation"></div>
                        </div>
                    </div>                        
                    <div class="col-md-6">
                        <div class="form-group"><label class="col-sm-4 control-label">End Market</label>
                            <div class="col-sm-8">'.$company.'</div>
                        </div>
                    </div> 
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="col-sm-4 control-label">Status:</label>
                            <div class="col-sm-8">
                                <div class="radio radio-primary radio-inline">
                                    <input type="radio" name="user_status" id="enable" value="Enable">
                                    <label for="enable">Active</label>
                                </div>
                                <div class="radio radio-danger radio-inline">
                                    <input type="radio" name="user_status" id="disable" value="Disable">
                                    <label for="disable">Disabled</label>
                                </div>  
                            </div>                              
                        </div>
                    </div>
                    &nbsp;<div class="col-md-12"></div>
                </div>
                <!-- Modal Footer -->
                <div class="modal-footer">
                    <button type="button" id="btnSave" onclick="save()" class="btn btn-primary">Save</button>
                    <button type="button" class="btn btn-danger" data-dismiss="modal">Cancel</button>
                </div>             
            </div>
        </div>
    </div> 
    </form>
</section>        
';
?>        