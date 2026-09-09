<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');
echo'
<section class="services-wrap">        
    <div class="container">
        <div class="ibox">
            <div class="ibox-title">
                <h5>Workflow Setup</h5>  
                <div class="ibox-tools">
                <a href="'.site_url('Setup/add_workflow').'" data-toggle="tooltip" data-placement="bottom" title="" data-original-title="Add New Outlet">
                    <i class="fa fa-file-text-o"></i>
                </a>                          
                </div>                  
            </div>
            <div class="ibox-content">              
                <div class="table-responsive">
                    <table id="example" class="display table table-hover table-striped"  width="100%">
                         <thead>
                            <tr>
                                <th>ID</th>
                                <th>Market</th>
                                <th>Process Name</th>
                                <th>Wait Time</th>
                                <th>Expiration Action</th>
                                <th></th>
                                <th></th>
                            </tr>                    
                         </thead>
                         <tbody>                            
                         </tbody>
                       </table>
                 </div>
            </div>
        </div>  
    </div>
</section>        
';
?>        