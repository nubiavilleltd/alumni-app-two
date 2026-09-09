<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');

echo'
    <section class="services-wrap">
        <div class="container">
            <div class="row">
            
                <div class="col-md-12">
                    <img src="'.site_url('assets/images/pic1.jpg').'">
                        <br/><br/>
                </div>                          
                <div class="col-md-12">
                    <img src="'.site_url('assets/images/pic2.jpg').'" >
                    <br/><br/>
                </div>    
                <div class="col-md-12">
                    <img src="'.site_url('assets/images/pic3.jpg').'" >
                </div>                                               
            </div>
        </div> 
    </section><!--services with the showcase mockups--> 
';
?>        