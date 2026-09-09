<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'third_party/PHPMailer/src/Exception.php';
require_once APPPATH . 'third_party/PHPMailer/src/PHPMailer.php';
require_once APPPATH . 'third_party/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class My_phpmailer {

    public $mail;

    public function __construct()
    {
        $this->mail = new PHPMailer(true);

        // Titanicedge SMTP Settings
        $this->mail->isSMTP();
        $this->mail->SMTPAuth   = true;
        // $this->mail->Host       = "smtp.ionos.com"; // replace with your SMTP host
        // $this->mail->Username   = 'appnotice@nubiaville.com'; // SMTP username
        // $this->mail->Password   ='XqhGJp96mH&LYD'; // SMTP password
         $this->mail->Host       = "smtp.gmail.com"; // replace with your SMTP host
        $this->mail->Username   = 'nubiavilleprojects@gmail.comm'; // SMTP username
        $this->mail->Password   ="ksircmeyiuyaqxxi";
        $this->mail->SMTPSecure = 'ssl'; // or 'ssl'
        $this->mail->Port       = 465;   // 587 for tls, 465 for ssl

        $this->mail->isHTML(true);
        $this->mail->CharSet = 'UTF-8';
    }
}
