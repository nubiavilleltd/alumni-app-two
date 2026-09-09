<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');

echo'

    
<section class="services-wrap" style="min-height:100vh; display:flex; align-items:center;">
    <div class="container">
        <div class="row">
            <div class="col-sm-5 hidden-sm wow animated fadeInLeft" 
                 style="margin:0 auto; float:none; text-align:center;">

                <h3 class="heading">Welcome to Alumni Portal</h3>

                <!-- Card -->
                <div class="col-md-12 col-xs-12 service-3-meta" 
                     style="text-align:center;">

                    <div class="services-box margin30 wow animated fadeInRight"
                         style="
                            display:inline-block; 
                            text-align:left; 
                            background:#ffffff;
                            padding:25px;
                            border-radius:12px;
                            box-shadow:0 4px 15px rgba(0,0,0,0.15);
                            width:100%;
                            max-width:450px;
                         ">

                        <h3 class="wow animated heading fadeInDownfadeInRight" 
                            style="text-align:center; margin-bottom:20px;">
                            Reset Password
                        </h3>

                      '.form_open('home/forgot_password', ['name'=>'loginform']) .'  
                        <fieldset class="form-horizontal">

                            <div class="form-group">
                                <label class="col-sm-6 control-label">Email:</label>
                                <div class="col-sm-12">
                                    <input name="identity" type="text" 
                                           class="form-control" 
                                           placeholder="Enter Email Address">
                                </div>
                            </div>

                            <div class="text-right" style="margin-top:15px;">
                                <button type="submit" name="login" 
                                        class="btn btn-primary rounded btn-3d" 
                                        style="width:100%;">
                                    Reset
                                </button>
                            </div>

                        </fieldset>
                        '.form_close().'    

                    </div><!-- services-box -->

                </div><!-- service-3-meta -->

            </div><!-- col-sm-5 -->
        </div><!-- row -->
    </div><!-- container -->
</section>

';
?>        