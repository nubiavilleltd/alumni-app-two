<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
class mpdf_main {
   
    public function __construct()
    {
        log_message('Debug', 'mPDF class is loaded.');
    }    
    function load($params=NULL)
    {
        include_once APPPATH.'/third_party/mpdf/mpdf.php';
        if ($params == 'WAM')
        {
            return new mPDF( '"en-GB-x"',array(208,180));  
                        
        } else {
            return new mPDF( '"en-GB-x","A4","8","Helvetica",10,10,10,10,6,3'); 
                                   
        }
        
    }    
}