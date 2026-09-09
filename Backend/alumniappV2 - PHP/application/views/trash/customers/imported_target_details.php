<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');

echo'
<section class="services-wrap">        
    <div class="container">
    <div class="ibox">
        <div class="ibox-title"><h5>Target Volume Details By SKU</h5>
            <div class="ibox-tools">
                <a href="'.site_url('Customers/sales_volume')
                    .'" data-toggle="tooltip" data-placement="bottom" title="" data-original-title="Return">
                    <i class="fa fa-undo"></i>
                </a>                                                      
            </div>  
        </div>
        <div class="ibox-content">
            <div class="table-responsive">
                <table id="example" class="display table table-hover table-striped"  width="100%">
                    <thead><tr>'.$tbl_header.'</tr></thead>
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