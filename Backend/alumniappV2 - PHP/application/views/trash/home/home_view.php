<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');

echo'
    <section class="services-wrap">
        <div class="container">
            <div class="row">
                <div class="col-sm-5 hidden-sm wow animated fadeInLeft">
                <h3 class="heading">Welcome to Incentiva</h3>
                <!--bootstrap collapse-->
                <div class="panel-group" id="accordion">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h4 class="panel-title">
                                <a data-toggle="collapse" data-parent="#accordion" href="'.site_url('#collapseOne').'">
                                    <i class="fa fa-desktop"></i>What is Incentiva
                                </a>
                            </h4>
                        </div>
                        <div id="collapseOne" class="panel-collapse collapse in">
                            <div class="panel-body">
                              Incentiva is a comprehensive and highly automated web based incentive management system 
                              developed to help you capture partner mindshare, achieve sales and marketing objectives, 
                              maintain/improve sales velocity as well as create competitive advantage to expand 
                              distribution and grow market share.
                             
                            </div>
                        </div>
                    </div>
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h4 class="panel-title">
                                <a data-toggle="collapse" data-parent="#accordion" href="'.site_url('#collapseTwo').'">
                                    <i class="fa fa-crop"></i> How Incentiva Works</a>
                            </h4>
                        </div>
                        <div id="collapseTwo" class="panel-collapse collapse">
                            <div class="panel-body">
                               Incentiva is developed to target behaviours as its key focus. For all behaviours, 
                               a corresponding Program can be setup to target and promote that behaviour.
                               It is not about how much money you spend, but how you effectively allocate your spending 
                               to influence behaviours that make the difference. 

                            </div>
                        </div>
                    </div>
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h4 class="panel-title">
                                <a data-toggle="collapse" data-parent="#accordion" href="'.site_url('#collapseThree').'">
                                    <i class="fa fa-cogs"></i>Features List</a>
                            </h4>
                        </div>
                        <div id="collapseThree" class="panel-collapse collapse">
                            <div class="panel-body">
                                <ui>
                                    <li>Robust Loyalty Rules Engine</i>
                                    <li>High Performance Transaction Engine</i>
                                    <li>Workflow Engine with audit trail</i>
                                    <li>Email & SMS notification Engine</i>
                                    <li>BI Dashboard and Reporting Engine</i>
                                    <li>Vouchers and Rebate Printout</i>
                            </div>
                        </div>
                    </div>
                </div>
                <!--collapse end-->
                </div>
                <div class="col-sm-7 col-xs-12 service-3-meta">
                    <h3 class="wow animated heading fadeInDownfadeInRight">Login</h3>
                    <div class="services-box margin30 wow animated fadeInRight">
                        '.form_open('home/login',array('name'=>'loginform')).'  
                            <fieldset class="form-horizontal">
                                <div class="col-md-12">
                                    <div class="form-group"><label class="col-sm-3 control-label">Email:</label>
                                        <div class="col-sm-9"><input name="identity" type="text" class="form-control" 
                                        placeholder="Enter Email Address"></div>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group"><label class="col-sm-3 control-label">Password:</label>
                                        <div class="col-sm-9"><input name="password" type="password" class="form-control" 
                                        placeholder="Enter Password"></div>
                                    </div>
                                </div>                                
                                <div class="col-md-12">
                                    <div class="form-group"><label class="col-sm-3 control-label"></label>
                                        <div class="col-sm-9">
                                            <div class="col-md-6"><label>'.form_checkbox('remember','1',FALSE). 'Remember me</label></div>          
                                                <div class="col-md-6 text-right">
                                                    <a href="'.site_url('home/forgot_password').'">Forgot password?</a>
                                                </div>
                                        </div>
                                    </div>
                                </div>                                
                                <div class="col-md-12 text-right">
                                    <button type="submit" name="login" class="btn btn-primary rounded btn-3d">Login</button>
                                </div>  
                                <div>
                                    <h3>
                                        <i class="fa fa-spinner fa-spin"></i>
                                        <i class="fa fa-refresh fa-spin"></i>
                                    </h3>
                                    <strong>NOTE:</strong>
                                    <a href="'.site_url('/home/trust').'">
                                    If you cannot see the spinning icons, Click here to add this site to your trusted site.
                                    </a>
                                </div>
                            </fieldset>
                        '.form_close().'       
                    </div><!--service box-->                    
                </div>

            </div>
        </div> 
    </section><!--services with the showcase mockups--> 
';
?>        