<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');
echo'
<section class="services-wrap">        
    <div class="container">                
        <div class="ibox">
            <div class="ibox-title">
                <h5>'.$page_title.'</h5>
                <div class="ibox-tools">
                    <a href="'.site_url('Rewards/new_list').'" data-toggle="tooltip" 
                          data-placement="bottom" title="" data-original-title="New Winners List">
                          <i class="fa fa-file-text-o"></i>
                    </a>  
                    <a href="'.site_url('Rewards/view_winners_list').'" data-toggle="tooltip" 
                          data-placement="bottom" title="" data-original-title="View Winners List">
                          <i class="fa fa-list"></i>
                    </a> 
                    <a href="'.site_url('Rewards/awaiting_approval').'" data-toggle="tooltip" 
                          data-placement="bottom" title="" data-original-title="View Awaiting Approval">
                          <i class="fa fa-list-alt"></i>
                    </a>                      
                </div>                  
            </div>
            <div class="ibox-content">
                <div class="table-responsive">
                     <table id="example" class="display table table-hover table-striped"  width="100%">
                         <thead>                      
                            <tr>     
                              <th><nobr>Request Date</nobr></th>
                              <th>Period</th>
                              <th>Initiator</th>
                              <th>Process</th>
                              <th>Campaign</th>
                              <th>Status</th> 
                              <th>Type</th> 
                              <th>Market</th>    
                              <th>ReqId</th>
                            </tr>        
                         </thead>
                         <tfoot>
                               
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