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

                     '.form_open('home/reset_password/' . $code, ['name' => 'loginform']) .'  
                        <fieldset class="form-horizontal">

                             <div class="form-group">
                                <label class="col-sm-6 control-label">New Password:</label>
                                <div class="col-sm-12" style="position:relative;">
                                    <input name="new" id="new" type="password" 
                                               class="form-control" 
                                               placeholder="Enter New Password"
                                               style="border-radius: 8px; height: 40px;">
                                    <span class="toggle-password" onclick="togglePasswordVisibility(\'new\')" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); cursor:pointer; font-size:18px; user-select:none;">👁️</span>
                                </div>
                            </div>
                              <div class="form-group">
                                <label class="col-sm-6 control-label">Confirm Password:</label>
                                <div class="col-sm-12" style="position:relative;">
                                    <input name="new_confirm" id="new_confirm" type="password" 
                                               class="form-control" 
                                               placeholder="Enter Confirm Password"
                                               style="border-radius: 8px; height: 40px;">
                                    <span class="toggle-password" onclick="togglePasswordVisibility(\'new_confirm\')" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); cursor:pointer; font-size:18px; user-select:none;">👁️</span>
                                </div>
                            </div>
                            <div class="text-right" style="margin-top:8px;">
                                <button type="submit" name="login" 
                                        class="btn btn-primary" 
                                        style="width:100%; border-radius: 8px; height: 40px; border: none;">
                                    Reset
                                </button>
                            </div>

                        </fieldset>
                      '.form_input($user_id) . form_hidden($csrf) .'
                        '.form_close().'     

                    </div><!-- services-box -->

                    <script>
                    function togglePasswordVisibility(fieldId) {
                        var field = document.getElementById(fieldId);
                        var icon = event.target;
                        if (field.type === "password") {
                            field.type = "text";
                            icon.textContent = "👁️";
                            
                        } else {
                            field.type = "password";
                            icon.textContent = "🙈";
                        }
                    }
                    </script>

                </div><!-- service-3-meta -->

            </div><!-- col-sm-5 -->
        </div><!-- row -->
    </div><!-- container -->
</section>

';
?>        