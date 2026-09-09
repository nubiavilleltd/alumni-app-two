<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('templates/_parts/pop_header_view'); 
echo '<div class="nine columns texts-wrap"><h3 class="title">'.$page_title.'</h3>';
$this->load->view('templates/_parts/flashdata'); 
echo $the_view_content.'</div>';
$this->load->view('templates/_parts/pop_footer_view');?>