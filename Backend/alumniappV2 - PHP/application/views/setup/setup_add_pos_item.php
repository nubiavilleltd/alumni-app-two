<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');
echo'


<section class="services-wrap">
    <div class="nk-content nk-content-fluid">
        <div class="container-xl wide-xl">
            <div class="nk-content-inner">
                <div class="nk-content-body">
                    <div class="components-preview">
                        <div class="nk-block-head nk-block-head-sm">
                            <div class="nk-block-between">
                                <div class="nk-block-head-content">
                                    <h4 class="nk-block-title">
                                    POS Image:- <small> Image POS Import-</small><P><small>
                                        
                                        <span class="nk-block-title">
                                            This feature allows you to upload multiple POS images by renaming each image with the POS Code, e.g., <code class="text-success">AB100112.jpg</code>.
                                        </span>
                                        </small></P>
                                    </h4>
                                </div>
                                <!-- .nk-block-head-content -->
                                <div class="nk-block-head-content">
                                    <div class="toggle-wrap nk-block-tools-toggle">
                                        <a href="#" class="btn btn-icon btn-trigger toggle-expand me-n1"
                                            data-target="pageMenu"><em class="icon ni ni-more-v"></em></a>
                                        <div class="toggle-expand-content" data-content="pageMenu">
                                            <ul class="nk-block-tools g-3">
                                                
                                                <li>
                                                <a href="' . site_url('requests/pos_item_view') . '" data-toggle="tooltip" 
                                                data-placement="bottom" title="" data-original-title="Return">
                                               
                                                <button type="button" class="btn2 rounded-pill btn btn-danger rounded">
                                                <span class="glyphicon glyphicon-repeat" aria-hidden="true"></span>
                                                <span><strong>Return</strong></span>  
                                            </button>
                                            </a>  
                                                </li>
    
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <!-- .nk-block-head-content -->
                            </div>
                        </div>
                        <div class="nk-block nk-block-lg">
                            <div class="card card-bordered card-preview">
                                <div class="card-inner">
                                    
                                    <hr class="preview-hr" />
                                    <div class="preview-title-lg overline-title">Import POS Image List</div>
                                    <hr class="preview-hr" />
                                    '.form_open_multipart('Setup/do_upload_images',array('name'=>'update_form','class'=>'form-horizontal')).'  
                                    <div class="row">
                                        <div class="offset-lg-1 col-lg-2 mt-1">
                                       
                                        </div>
                                        <div class=" col-lg-8">
                                            <div class="form-control-wrap">
                                                <div class="input-group">
                                                    <div class="form-file">
                                                    <input type="file" name="photos[]" multiple="multiple"  class="form-file-input" id="inputGroupFile04"/>
                                                        <label class="form-file-label" for="inputGroupFile04">Choose
                                                            file</label>
                                                    </div>
                                                    <div class="input-group-append">
                                                        <button class="btn btn-outline-primary btn-dim" name="login">Import</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    '.form_close().' 
                                </div>
                            </div>
                            <!-- .card-preview -->
                        </div>
                        <!-- nk-block -->
                    </div>
                    <!-- .components-preview -->
                </div>
            </div>
        </div>
    </div>
   
    
</section>
';
?>        