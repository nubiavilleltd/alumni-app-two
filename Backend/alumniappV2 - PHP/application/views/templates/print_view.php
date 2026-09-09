<?php defined('BASEPATH') OR exit('No direct script access allowed');
echo'
    <!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1"><title> '.$page_title.'</title>
    <link href="'.site_url('assets/bootstrap/css/bootstrap.min.css').'" rel="stylesheet">
    <link href="'.site_url('assets/css/style.css').'" rel="stylesheet" type="text/css" media="screen">
    <link href="'.site_url('assets/font-awesome/css/font-awesome.min.css').'" rel="stylesheet">
    <link href="'.site_url('assets/css/animate.css').'" rel="stylesheet" type="text/css" media="screen">
    <script src="'.site_url('assets/js/jquery.min.js').'"></script>
    <script src="'.site_url('assets/js/jquery-ui.min.js').'" type="text/javascript"></script>
    <script src="'.site_url('assets/bootstrap/js/bootstrap.min.js').'" type="text/javascript"></script>
    <script src="'.site_url('assets/js/jquery.easing.1.3.min.js').'" type="text/javascript"></script>
    </head><body>';

echo $the_view_content.
     '</body></html> ';
