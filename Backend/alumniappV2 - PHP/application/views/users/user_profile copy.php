<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');
echo'
<section class="services-wrap">        
    <div class="container">
        <div class="ibox">
            <div class="ibox-title">
                <h5>User Profile:- <small> '.$user_rec->fullname.'</small></h5>       
                <div class="ibox-tools">
                    <div class="ibox-tools">
                        <a href="javascript:add_row()" data-toggle="tooltip" data-placement="bottom" 
                            title="" data-original-title="Change Password">
                            <i class="fa fa-unlock-alt"></i>
                        </a>   
                    </div>  
                </div>                
            </div>
            <div class="ibox-content">
                <form class="form-horizontal">
                    <div class="col-md-6">
                        <div class="form-group"><label class="col-sm-4 control-label">First Name:</label>
                            <div class="col-sm-8"><input name="first_name" type="text" class="form-control white_bkgd" readonly="readonly" 
                            value="'.$user_rec->first_name.'"></div>
                        </div>
                    </div>              
                    <div class="col-md-6">
                        <div class="form-group"><label class="col-sm-4 control-label">Last Name:</label>
                            <div class="col-sm-8"><input name="last_name" type="text" class="form-control white_bkgd" readonly="readonly"
                            value="'.$user_rec->last_name.'"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group"><label class="col-sm-4 control-label">Email:</label>
                            <div class="col-sm-8"><input type="text" name="email" 
                            class="form-control white_bkgd" readonly="readonly" value="'.$user_rec->email.'"></div>
                        </div>
                    </div>                                                
                    <div class="col-md-6">
                        <div class="form-group"><label class="col-sm-4 control-label">User Role</label>
                            <div class="col-sm-8"><input type="text" name="role" 
                            class="form-control white_bkgd" readonly="readonly" 
                            value="'.$user_rec->user_role.'"></div>  
                        </div>
                    </div>     
                    <div class="col-md-6">
                        <div class="form-group"><label class="col-sm-4 control-label">Designation:</label>
                            <div class="col-sm-8"><input type="text" name="designation" 
                            class="form-control white_bkgd" readonly="readonly" 
                            value="'.$user_rec->designation.'"></div>
                        </div>
                    </div>                        
                    <div class="col-md-6">
                        <div class="form-group"><label class="col-sm-4 control-label">Status</label>
                            <div class="col-sm-8"><input type="text" name="status" 
                            class="form-control white_bkgd" readonly="readonly" 
                            value="'.($user_rec->active==1?"Active":"Disabled").'"></div>                        
                        </div>
                    </div> 
                </form>
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
                    <div class="col-md-12">
                        <div class="form-group"><label class="col-sm-4 control-label">Old Password:</label>
                            <div class="col-sm-8"><input type="password" name="oldpassword" 
                            class="form-control" placeholder="Enter Old Password"></div>
                        </div>
                    </div>                                       
                    <div class="col-md-12">
                        <div class="form-group"><label class="col-sm-4 control-label">New Password:</label>
                            <div class="col-sm-8"><input type="password" name="newpassword" 
                            class="form-control" placeholder="Enter New Password"></div>
                        </div>
                    </div>  
                    <div class="col-md-12">
                        <div class="form-group"><label class="col-sm-4 control-label">Confirm New Password:</label>
                            <div class="col-sm-8"><input type="password" name="repass" 
                            class="form-control" placeholder="Confirm New Password"></div>
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