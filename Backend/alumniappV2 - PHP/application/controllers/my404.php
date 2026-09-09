<?php 
class my404 extends MY_Controller 
{
    public function __construct() 
    {
        parent::__construct(); 
    } 

    public function index() 
    { 
        $this->output->set_status_header('404'); 
        $data['content'] = 'error_404'; // View name 
        $this->data['page_title'] = 'Sorry, Page not found';
        $this->render('myerrors/404error');
    } 
} 
?> 