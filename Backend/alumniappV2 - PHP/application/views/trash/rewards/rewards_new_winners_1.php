<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');


echo'
<section class="services-wrap">        
<div class="container">
    <div class="ibox">
        '.form_open('Rewards/new_list',array('name'=>'setup_form','class'=>'form-horizontal')).'       
            <div class="ibox-title">
                <h5>New Winners List:</h5>
                <div class="ibox-tools">
                    <a href="javascript:document.setup_form.submit();" data-toggle="tooltip" 
                        data-placement="bottom" title="" data-original-title="Submit Form">
                        <i class="fa fa-floppy-o"></i>
                    </a>
                    <a href="'.site_url('Rewards/view_winners_list').'" data-toggle="tooltip" 
                        data-placement="bottom" title="" data-original-title="Return">
                        <i class="fa fa-undo"></i>
                    </a>                        
                </div>
            </div>
            <div class="ibox-content">
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Date Initiated:</label>
                        <div class="col-sm-8"><input name="date_initiated" type="text" class="form-control white_bkgd" 
                        value="'.date("F j, Y").'" readonly="readonly"></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Initiated By:</label>
                        <div class="col-sm-8"><input name="initiated_by" type="text" class="form-control white_bkgd" 
                        value="'.$current_user.'" readonly="readonly"></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4  control-label">Rebate Type</label>
                        <div class="col-sm-8">'.$campaign.'</div>
                    </div>
                </div>                  
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Period:</label>
                        <div class="col-sm-8"><input id="choosemnth" name="period" type="text" class="form-control" 
                        value="04-2016" placeholder="Period - mm-yyyy"></div>
                    </div>
                </div>  
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4  control-label">Customer Type</label>
                        <div class="col-sm-8">'.$cust_type.'</div>
                    </div>
                </div>      
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4  control-label">Market</label>
                        <div class="col-sm-8">'.$market.'</div>
                    </div>
                </div>                                          
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Process Type</label>
                        <div class="col-sm-8">
                            <div class="radio radio-primary radio-inline">
                                <input type="radio" name="process_type" id="import" value="Imported">
                                <label for="import">Import List</label>
                            </div>
                            <div class="radio radio-success radio-inline">
                                <input type="radio" name="process_type" id="generate" value="generated" checked="checked">
                                <label for="generate">Generate List</label>
                            </div>  
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Attach File <br/>(Import Only):</label>
                        <div class="col-sm-8"><input type="file" name="file" 
                        class="form-control" placeholder="Attach winners list"></div>
                    </div>
                </div>                
            </div>
            <div class="col-md-12 ibox-footer text-right">
                <button type="submit" class="btn btn-primary rounded btn-3d">Submit</button>
            </div>   
        </form>
    </div>        
</div>
</section>    
';