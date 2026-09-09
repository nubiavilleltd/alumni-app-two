<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');

echo'
<section class="services-wrap">        
<div class="container">
    <div class="ibox">
        '.form_open('Setup/add_rule/'.$form_action,array('name'=>'setup_form','class'=>'form-horizontal')).'       
        <div class="ibox-title">
            <h5>'.$page_title.':</h5>
            <div class="ibox-tools">
                <a href="javascript:document.setup_form.submit();" data-toggle="tooltip"' 
                    .'data-placement="bottom" title="" data-original-title="Save Form">
                    <i class="fa fa-floppy-o"></i>
                </a>
                <a href="'.site_url('Setup/view_rules').'" data-toggle="tooltip"'
                    .'data-placement="bottom" title="" data-original-title="Return">
                    <i class="fa fa-undo"></i>
                </a>                        
            </div>
        </div>
        <div class="ibox-content">
            <div class="col-md-12">
                <input type="hidden" name="'.$this->security->get_csrf_token_name()
                    .'" value="'.$this->security->get_csrf_hash().'" />                    
                <input type="hidden" value="'.$main_data->rule_id.'" name="id"/> 
                <input type="hidden" value="Volume" name="Y_type"/> 
            </div>              
            <div class="col-md-6">
                <div class="form-group"><label class="col-sm-4 control-label">Campaign</label>
                    <div class="col-sm-8">'.$campaign.'
                    </div>
                </div>
            </div>   
            <div class="col-md-6">
                <div class="form-group"><label class="col-sm-4 control-label">Rule Title:</label>
                    <div class="col-sm-8"><input name="title" type="text" class="form-control" 
                    value="'.$main_data->title.'" placeholder="Enter Rule Title"></div>
                </div>
            </div>                 
            <div class="col-md-6">
                <div class="form-group"><label class="col-sm-4 control-label">Market</label>
                    <div class="col-sm-8">
                        <select name="market" class="chosen-select-no-results form-control">
                            <option value="'.$main_data->market.'">'.$main_data->market.'</option>
                            <option value="Nigeria">Nigeria</option>
                            <option value="WAM">WAM</option>
                        </select>                        
                    </div>                
                </div>                        
            </div>                           
            <div class="col-md-6">
                <div class="form-group"><label class="col-sm-4 control-label">Rule Status:</label>
                    <div class="col-sm-8 form-group">
                        <div class="col-sm-12">
                            <div class="radio radio-primary radio-inline">
                                <input type="radio" name="rule_status" id="Active" value="Active"
                                '.(empty($main_data->rule_status)||$main_data->rule_status==='Active'?
                                'checked="checked"':'').'>
                                <label for="enable">Active</label>
                            </div>
                            <div class="radio radio-danger radio-inline">
                                <input type="radio" name="rule_status" id="Inactive" value="Inactive"
                                '.($main_data->rule_status==='inactive'?'checked="checked"':'').'>
                                <label for="disable">Inactive</label>
                            </div>  
                        </div>                                                                               
                    </div>                    
                </div>
            </div>    
            <div class="col-md-6">
                <div class="form-group"><label class="col-sm-4 control-label">Customer Types</label>
                    <div class="col-sm-8">
                        '.$cust_types.'                      
                    </div>
                </div>                        
            </div>             
        </div>
        <div class="col-md-12 ibox-footer text-right">
            <button type="submit" class="btn btn-primary rounded btn-3d">Save</button>
        </div>
        </form>
    </div>        
</div>
</section>    
';
       