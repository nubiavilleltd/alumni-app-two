<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');
echo'
<section class="services-wrap">        
    <div class="container">                
        <div class="ibox">
            <div class="ibox-title">
                <h5>Approved Vouchers List</h5>                 
            </div>
            <div class="ibox-content">            
                <div class="table-responsive">
                     <table id="example" class="display table table-hover table-striped"  width="100%">
                        <thead>
                          <tr>
                          <th>Date</th>
                            <th>Initiator</th>
                            <th>Period</th>
                            <th>Campaign</th>
                            <th>Customer Type</th>
                            <th>Market</th>
                            <th>ID</th>
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