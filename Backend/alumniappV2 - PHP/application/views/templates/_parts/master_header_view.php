<?php defined('BASEPATH') OR exit('No direct script access allowed'); 
echo '
<!DOCTYPE html>
<html lang="en">
    <head>
	<!-- iOS PWA support -->
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Estate Manager">
<link rel="apple-touch-icon" href="/assets/img/icons/icon-512.png">
<link rel="apple-touch-startup-image" href="/assets/img/icons/icon-512.png">

    <link rel="manifest" href="'.site_url('manifest.json').'">
     <script>
if ("serviceWorker" in navigator) {
  window.addEventListener("load", function() {
    navigator.serviceWorker.register("/service-worker.js")
      .then(reg => console.log("Service Worker registered ✅", reg.scope))
      .catch(err => console.log("Service Worker failed ❌", err));
  });
}
</script>
<script>
window.addEventListener("offline", () => {
  console.log("🚫 Network offline — redirecting...");
  window.location.href = "/pwa/offline";
});

// Save last visited online page
if (navigator.onLine) {
  localStorage.setItem("last-online-url", window.location.href);
}

</script>

        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title> '.$page_title.'</title>
        <!-- Bootstrap -->
        <!-- font awesome for icons -->
        <link href="'.site_url('assets/font-awesome/css/font-awesome.min.css').'" rel="stylesheet">
        <!-- animated css  -->
     <!--sweet alerts-->
		        <link href="'.site_url('assets/css/MonthPicker.min.css').'" rel="stylesheet" type="text/css">             
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

        <link href="'.site_url('assets/css/sweetalert.css').'" rel="stylesheet"> 
           
        <link href="'.site_url('assets/css/animate.css').'" rel="stylesheet" type="text/css" media="screen">
        <link href="'.site_url('assets/css/jquery-ui.min.css').'" rel="stylesheet" type="text/css" media="screen">
        <link rel="stylesheet" href="'.site_url('/assets/css/editors/summernote.css?ver=3.1.1').'">
       <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-beta.1/dist/css/select2.min.css" rel="stylesheet" />
        <!--popups css-->
        <link href="'.site_url('assets/css/magnific-popup.css').'" rel="stylesheet" type="text/css">  
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/style.css">                  
        <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
        <!-- WARNING: Respond.js doesn\'t work if you view the page via file:// -->
 
        <!--[if lt IE 9]>
          <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
          <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
                  <!--must need plugin jquery-->
                  <script src="'.site_url('assets/js/jquery-3.7.0.min.js').'"></script> 
                  <script src="'.site_url('assets/js/jquery-ui.min.js').'" type="text/javascript"></script> 

        <![endif]-->
     
 '.$before_head.'
     <!-- custom css (blue color by default) -->
     <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/rowreorder/1.4.1/css/rowReorder.dataTables.min.css"/>       

     <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css"/>       
      <link href="'.site_url('assets/css/xtras.css').'" rel="stylesheet" type="text/css" media="screen">     
      <link href="'.site_url('assets/css/theme.css').'" rel="stylesheet" type="text/css" media="screen">     
      <link href="'.site_url('assets/css/dashlite.css').'" rel="stylesheet" type="text/css" media="screen">     
    </head>
    <body class="nk-body npc-invest bg-lighter ">
    <script>    const theme = localStorage.getItem(\'darkMode\') || \'light\';    document.documentElement.dataset.theme = theme;  </script>
    '.$current_user_menu.'
    '.$after_body.'
 ';