<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| Hooks
| -------------------------------------------------------------------------
| This file lets you define "hooks" to extend CI without hacking the core
| files.  Please see the user guide for info:
|
|	http://codeigniter.com/user_guide/general/hooks.html
|
*/

/*
| Rewrites image URLs stored against a previous domain to the host serving the
| current request. See application/hooks/Legacy_host_rewrite.php.
*/
$hook['pre_controller'][] = array(
	'class'    => 'Legacy_host_rewrite',
	'function' => 'start_buffer',
	'filename' => 'Legacy_host_rewrite.php',
	'filepath' => 'hooks'
);
