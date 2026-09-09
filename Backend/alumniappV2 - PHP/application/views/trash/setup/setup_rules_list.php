<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');
echo'
<section class="services-wrap">        
    <div class="container">
        <div class="ibox">
            <div class="ibox-title">
                <h5>Reward Rules List:</h5>       
                    <div class="ibox-tools">
                        <a href="'.site_url('Setup/add_rule').'" data-toggle="tooltip" data-placement="bottom" 
                            title="" data-original-title="Add New Reward Rule">
                            <i class="fa fa-plus"></i>
                        </a>   
                        <a href="javascript:delete_row()" data-toggle="tooltip" data-placement="bottom" 
                            title="" data-original-title="Delete Selected">
                            <i class="fa fa-trash-o"></i>
                        </a> 
                    </div>                
            </div>
            <div class="ibox-content">
                <div class="table-responsive">
                    <table id="example" class="display table table-hover table-striped"  width="100%">
                         <thead>
                            <tr>
                                <th>Title</th>
                                <th>Campaign</th>
                                <th>Market</th>
                                <th>Status</th>
                                <th>REF</th>
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