<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');
$firstname = explode(" ", $user_rec->fullname);
$status=($user_rec->active==1?"Active":"Inactive");
echo'
<div class="nk-content nk-content-fluid">
    <div class="container-xl wide-xl">
        <div class="nk-content-inner">
            <div class="nk-content-body">
                <div class="nk-block">
                    <div class="card card-bordered">
                        <div class="card-aside-wrap">
                            <div class="card-inner card-inner-lg">
                                <div class="nk-block-head nk-block-head-lg">
                                    <div class="nk-block-between">
                                        <div class="nk-block-head-content">
                                            <h4 class="nk-block-title">Personal Information</h4>

                                        </div>
                                        <div class="nk-block-head-content align-self-start d-lg-none">
                                            <a href="#" class="toggle btn btn-icon btn-trigger mt-n1"
                                                data-target="userAside"><em class="icon ni ni-menu-alt-r"></em></a>
                                        </div>
                                    </div>
                                </div><!-- .nk-block-head -->
                                <div class="nk-block">
                                    <div class="nk-data data-list">
                                        <div class="data-head">
                                            <h6 class="overline-title">Basics</h6>
                                        </div>
                                        <div class="data-item" data-bs-toggle="modal" data-bs-target="#profile-edit">
                                            <div class="data-col">
                                                <span class="data-label">Full Name</span>
                                                <span class="data-value">'.$user_rec->fullname.'</span>
                                            </div>
                                            <div class="data-col data-col-end"><span class="data-more disable">
                                                    <em class="icon ni ni-user-circle"></em></span></div>
                                        </div><!-- data-item -->


                                        <div class="data-item">
                                            <div class="data-col">
                                                <span class="data-label">Email</span>
                                                <span class="data-value">'.$user_rec->email.'</span>
                                            </div>
                                            <div class="data-col data-col-end"><span class="data-more disable"><em
                                                        class="icon ni ni-lock-alt"></em></span></div>
                                        </div><!-- data-item -->

                                        <div class="data-item">
                                            <div class="data-col">
                                                <span class="data-label">Designation</span>
                                                <span class="data-value">'.$user_rec->designation.'</span>
                                            </div>
                                            <div class="data-col data-col-end"><span class="data-more disable"><em
                                                        class="icon ni ni-lock-alt"></em></span></div>
                                        </div><!-- data-item -->
                                        <div class="data-item">
                                            <div class="data-col">
                                                <span class="data-label">Status</span>
                                                <span class="data-value">'.$status.'</span>
                                            </div>
                                            <div class="data-col data-col-end"><span class="data-more disable"><em
                                                        class="icon ni ni-lock-alt"></em></span></div>
                                        </div><!-- data-item -->
                                        <div class="data-item">
                                            <div class="data-col">
                                                <span class="data-label">Location</span>
                                                <span class="data-value">'.$user_rec->userArea.'</span>
                                            </div>
                                            <div class="data-col data-col-end"><span class="data-more disable"><em
                                                        class="icon ni ni-lock-alt"></em></span></div>
                                        </div><!-- data-item -->



                                    </div><!-- data-list -->
                                </div><!-- .nk-block -->
                            </div>
                            <div class="card-aside card-aside-left user-aside toggle-slide toggle-slide-left toggle-break-lg"
                                data-toggle-body="true" data-content="userAside" data-toggle-screen="lg"
                                data-toggle-overlay="true">
                                <div class="card-inner-group" data-simplebar>
                                    <div class="card-inner">
                                        <div class="user-card">
                                            <div class="user-avatar bg-primary">
                                                <span>'.str_split($firstname[0])[0]. ''. str_split($firstname[1])[0].'</span>
                                            </div>
                                            <div class="user-info">
                                                <span class="lead-text">'.$user_rec->fullname.'</span>
                                                <span class="sub-text">'.$user_rec->email.'</span>
                                            </div>

                                        </div><!-- .user-card -->
                                    </div><!-- .card-inner -->

                                    <div class="card-inner p-0">
                                        <ul class="link-list-menu">

                                            <li><a class="active" href="'.site_url('profile/view_profile').'"><em
                                                        class="icon ni ni-user-fill-c"></em><span>Personal
                                                        Infomation</span></a></li>
                                            <li><a href="javascript:add_row()" data-bs-toggle="modal" data-bs-target="#modal_form"><em
                                                        class="icon ni ni-edit-fill"></em><span>Update
                                                        Password</span></a>
                                            </li>
                                        </ul>
                                    </div><!-- .card-inner -->
                                </div><!-- .card-inner-group -->
                            </div><!-- card-aside -->
                        </div><!-- .card-aside-wrap -->
                    </div><!-- .card -->
                </div><!-- .nk-block -->
            </div>
        </div>
    </div>
</div>
<!-- Modal Form -->
<div class="modal fade" tabindex="-1" id="modal_form">
    <div class="modal-dialog modal-lg modal-dialog-top" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change Password</h5>
                <a href="#" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <em class="icon ni ni-cross"></em>
                </a>
            </div>
            <div class="modal-body">
                <form action="#" id="form" class="form-validate row is-alter">
                    <div class="form-group col-md-4">
                    <input type="hidden" name="'.$this->security->get_csrf_token_name()
                    .'" value="'.$this->security->get_csrf_hash().'" />                    
                <input type="hidden" value="" name="id"/> 
                        <label class="form-label" for="email-address">Old Password</label>
                        <div class="form-control-wrap">
                        <input type="password" name="oldpassword"
                        class="form-control" placeholder="Enter New Password">
                        </div>
                    </div>
                    <div class="form-group col-md-4">
                        <label class="form-label" for="description">New Password</label>
                        <div class="form-control-wrap">
                        <input type="password" name="newpassword" 
                        class="form-control" placeholder="Enter New Password">
                        </div>
                    </div>
                    <div class="form-group col-md-4">
                        <label class="form-label">Confirm New Password</label>
                        <div class="form-control-wrap">
                        <input type="password" name="repass" 
                        class="form-control" placeholder="Confirm New Password">
                        </div>
                    </div>
                    <div class="form-group">
                        <button type="button" id="btnSave" onclick="save()" class="btn btn-lg btn-primary">Save</button>
                        <button type="button"  data-dismiss="modal" class="btn btn-lg btn-danger">Cancel</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
        
';
?>        