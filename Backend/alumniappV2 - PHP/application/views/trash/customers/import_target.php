<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');

echo'
<section class="services-wrap">        
<div class="container">
    '.form_open_multipart('Customers/import_target/'.$form_action,array('name'=>'register_form','class'=>'form-horizontal')).'   
        <div class="ibox">
            <div class="ibox-title">
                <h5>'.$page_title.':</h5>
                <div class="ibox-tools">
                    <a href="javascript:document.register_form.submit();" data-toggle="tooltip" 
                    data-placement="bottom" title="" data-original-title="Submit Form">
                        <i class="fa fa-floppy-o"></i>
                    </a>
                    <a href="'.site_url('customers/sales_volume').'" data-toggle="tooltip" 
                        data-placement="bottom" title="" data-original-title="Return">
                        <i class="fa fa-undo"></i>
                    </a>                        
                </div>
            </div>
            <div class="ibox-content">         
                                    
                <div class="col-md-12">
                    <div class="form-group"><label id="targetfile1" class="col-sm-4 control-label">
                        Attach Target File:</label><div class="col-sm-8">
                        <input type="file" name="targetfile" id="targetfile" class="form-control"></div>
                    </div>
                </div>            
            </div>
            <div class="ibox-footer col-md-12 text-right">
            <input type="hidden" name="figures_type" id="actual" value="Actual">
                <button type="submit" class="btn btn-primary rounded btn-3d">Submit</button>
            </div>
        </div>    
    '.form_close().'     
</div>
</section>    
';
?> 
