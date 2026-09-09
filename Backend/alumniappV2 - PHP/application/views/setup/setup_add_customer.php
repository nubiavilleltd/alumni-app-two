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
                                        Customer Setup
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
                                                    <a href="'.site_url('Setup/view_customer').'" class="btn btn-round btn-sm btn-primary">
                                                        Return
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
                                    '.form_open('Setup/add_customer/'.$form_action,array('name'=>'setup_form','class'=>'form-horizontal')).'
                                    <input type="hidden" name="'.$this->security->get_csrf_token_name()
                                        .'" value="'.$this->security->get_csrf_hash().'" />                    
                                    <input type="hidden" value="'.$main_data->cust_code.'" name="id"/>
                                        <div class="row g-3 align-center">
                                            <div class="col-lg-2">
                                                <div class="form-group">
                                                    <label class="form-label" for="site-name">Customer Code:</label>
                                                </div>
                                            </div>
                                            <div class="col-lg-4">
                                                <div class="form-group">
                                                    <div class="form-control-wrap">
                                                        <input type="text" class="form-control" value="'.$main_data->cust_code.'"
                                                            name="cust_code" placeholder="Enter Code">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-2">
                                                <div class="form-group">
                                                    <label class="form-label" for="site-name">Customer Name:</label>
                                                </div>
                                            </div>
                                            <div class="col-lg-4">
                                                <div class="form-group">
                                                    <div class="form-control-wrap">
                                                        <input type="text" value="'.$main_data->cust_name.'" class="form-control"
                                                            name="cust_name1" placeholder="Enter Name">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-2">
                                                <div class="form-group">
                                                    <label class="form-label" for="site-name">Customer Type:</label>
                                                </div>
                                            </div>
                                            <div class="col-lg-4">
                                                <div class="form-group">
                                                    <div class="form-control-wrap">
                                                        <select name="cust_type"
                                                            class="form-select js-select2">
                                                            <option value="">Select an option</option>
                                                            <option value="B2B"> B2B </option>
                                                            <option value="E-Commerce"> E-Commerce </option>
                                                            <option value="EMPLOYEES"> EMPLOYEES </option>
                                                            <option value="KDA"> KDA </option>
                                                            <option value="MAINSTREAM"> MAINSTREAM </option>
                                                            <option value="MOD TRADE"> MOD TRADE</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-2">
                                                <div class="form-group">
                                                    <label class="form-label" for="site-name">Credit Limit:</label>
                                                </div>
                                            </div>
                                            <div class="col-lg-4">
                                                <div class="form-group">
                                                    <div class="form-control-wrap">
                                                        <input type="text" class="form-control"
                                                            name="credit_limit" value="'.$main_data->credit_limit.'" placeholder="Enter Limit">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-2">
                                                <div class="form-group">
                                                    <label class="form-label" for="site-name">Credit Type:</label>
                                                </div>
                                            </div>
                                            <div class="col-lg-4">
                                                <div class="form-group">
                                                    <div class="form-control-wrap">
                                                        <select id="credit_type" name="credit_type"
                                                            class="form-select js-select2">
                                                            <option value="">Select an option</option>
                                                            <option value="Cash">
                                                                Cash
                                                            </option>
                                                            <option value="Credit">
                                                                Credit
                                                            </option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div style="display: flex;align-items: end;justify-content: end;">
                                            <div class="form-group">
                                            <button type="submit" class="btn btn-lg btn-primary">Save</button>
                                            </div></div>
                                        </div>                                        
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- .components-preview -->
                </div>
            </div>
        </div>
    </div>     
</section>    
';
       