<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');
echo'
<section class="services-wrap">        
    <div class="container">                
        <div class="ibox">
            <div class="ibox-title">
                <h5>All Request List</h5>
                <div class="ibox-tools">
                    <a href="'.site_url('Rewards/new_list').'" data-toggle="tooltip" 
                          data-placement="bottom" title="" data-original-title="New Winners List">
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
                              <th>Request Date</th>
                              <th>Initiator</th>
                              <th>Status</th>
                              <th>Next Approver</th>                                                 
                            </tr>        
                         </thead>
                         <tfoot>
                          <tr>
                              <th>ID</th>
                              <th>Request Date</th>
                              <th>Initiator</th>
                              <th>Status</th>
                              <th>Next Approver</th>
                            </tr>                                 
                         </tfoot>
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