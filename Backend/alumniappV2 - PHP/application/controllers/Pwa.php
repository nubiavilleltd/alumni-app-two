<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pwa extends CI_Controller {

    public function offline()
    {
        $this->load->view('home/offline');
    }
}
