<?php defined('BASEPATH') OR exit('No direct script access allowed');

echo'
<!DOCTYPE html>
<html lang="zxx" class="js">
 
<head>
    <meta charset="utf-8">
    <meta name="author" content="Softnio">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="A powerful and conceptual apps base dashboard template that especially build for developers and programmers.">    
    <link rel="shortcut icon" href="./images/favicon.png">
    <title>Login | DashLite Admin Template</title>    
    <link rel="stylesheet" href="./assets/css/dashlite.css?ver=3.1.3">
    <link id="skin-default" rel="stylesheet" href="./assets/css/theme.css?ver=3.1.1">
</head>
 
<body class="nk-body bg-white npc-general pg-auth">
    <div class="nk-app-root">
        <div class="nk-main ">            
            <div class="nk-wrap nk-wrap-nosidebar">
                <div class="nk-content" style="background:#2F3E9E">
                    <div class="nk-block nk-block-middle nk-auth-body  wide-xs">
                        <div class="card card-bordered">
                            <div class="card-inner card-inner-lg">
                                <div class="brand-logo pb-4 text-center">
                                    <a href="html/index.html" class="logo-link">
                                        <img class="logo-light logo-img logo-img-lg" src="'.site_url('assets/img/logo.png?ver=1.2').'" srcset="'.site_url('assets/img/logo.png?ver=1.2').'" alt="logo">
                                        <img class="logo-dark logo-img logo-img-lg" src="'.site_url('assets/img/logo.png?ver=1.2').'" alt="logo-dark">
                                    </a>
                                </div>
                                <div class="nk-block-head">
                                    <div class="nk-block-head-content">
                                        <h4 class="nk-block-title">Sign in to your admin account</h4>                                        
                                    </div>
                                </div>
';
$this->load->view('templates/_parts/flashdata');
echo '
                                '.form_open('home/login',array('name'=>'loginform')).' 
                                    <div class="form-group">
                                        <div class="form-label-group">
                                            <label class="form-label" for="identity">Email</label>
                                        </div>
                                        <div class="form-control-wrap">
                                            <input type="text" class="form-control form-control-lg" id="identity" name="identity" placeholder="Enter your email address">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <div class="form-label-group">
                                            <label class="form-label" for="password">Password</label>
                                            <a class="link link-primary link-sm" href="'.site_url('home/forgot_password').'">Forgot Password?</a>
                                        </div>
                                        <div class="form-control-wrap">
                                            <a href="#" class="form-icon form-icon-right passcode-switch lg" data-target="password">
                                                <em class="passcode-icon icon-show icon ni ni-eye"></em>
                                                <em class="passcode-icon icon-hide icon ni ni-eye-off"></em>
                                            </a>
                                            <input type="password" class="form-control form-control-lg" id="password" name="password" placeholder="Enter your password">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <button class="btn btn-lg btn-primary btn-block">Sign in</button>
                       
                                    </div>
                                '.form_close().'
              <div style="margin-top: 16px;">
                <button class="btn btn-lg btn-primary btn-block" onclick="installApp()">📱 Install App</button>
              </div>
                             
                            </div>
                        </div>
                    </div>
                </div>
                <!-- wrap @e -->
            </div>
            <!-- content @e -->
        </div>
        <!-- main @e -->
    </div>
    <!-- app-root @e -->
    <!-- JavaScript -->
    <script src="./assets/js/bundle.js?ver=3.1.1"></script>
    <script src="./assets/js/scripts.js?ver=3.1.1"></script>
    <script>
  let deferredPrompt;

  // Listen for the install prompt event
  window.addEventListener("beforeinstallprompt", (e) => {
    e.preventDefault(); // Prevent automatic prompt
    deferredPrompt = e;

    // Show your custom install button
    const installBtn = document.getElementById("installBtn");
    if (installBtn) installBtn.style.display = "inline-block";
  });

  // Handle click on install button
  async function installApp() {
    if (deferredPrompt) {
      deferredPrompt.prompt();
      const choiceResult = await deferredPrompt.userChoice;

      if (choiceResult.outcome === "accepted") {
        console.log("✅ User accepted the install prompt");
      } else {
        console.log("❌ User dismissed the install prompt");
      }

      deferredPrompt = null; // clear
    }
  }

  // Check if app is already installed
  window.addEventListener("appinstalled", () => {
    console.log("🎉 App successfully installed");
    const installBtn = document.getElementById("installBtn");
    if (installBtn) installBtn.style.display = "none";
  });
</script>

</html>
';
?>        