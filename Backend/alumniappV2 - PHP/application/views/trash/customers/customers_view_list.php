<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');
echo'
<section class="services-wrap">        
    <div class="container">
        <div class="ibox">
            <div class="ibox-title">
                <h5>All Customers:</h5>             
            </div>
            <div class="ibox-content">        
                <div class="table-responsive">
                    <table id="example" class="display table table-hover table-striped"  width="100%">
                         <thead>                      
                           <tr>
                             <th>ID</th>
                             <th>URN</th>
                             <th>Customer</th>
                             <th>Type</th>
                             <th>Category</th>
                             <th>Area</th>
                             <th>Market</th>
                             <th>Phone</th>
                             <th>Alt</th>
                             <th>Address</th>
                             <th>Contact</th>
                             <th>Designation</th>
                             <th>Distributor</th>
                             <th>Email</th>
                             <th>CustID</th>
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