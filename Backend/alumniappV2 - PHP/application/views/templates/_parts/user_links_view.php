<?php defined('BASEPATH') OR exit('No direct script access allowed'); 
$firstname = explode(" ", $fullname);
echo '
<div class="nk-app-root">
<!-- wrap @s -->
<div class="nk-wrap ">
    <!-- main header @s -->
    <div class="nk-header nk-header-fluid is-light">
        <div class="container-xl wide-xl">
            <div class="nk-header-wrap">
                <div class="nk-menu-trigger me-sm-2 d-lg-none">
                    <a href="#" class="nk-nav-toggle nk-quick-nav-icon" data-target="headerNav"><em class="icon ni ni-menu"></em></a>
                </div>
                <div class="nk-header-brand">
                    <a href="'.site_url('setup/dashboard').'" class="logo-link">
                    <img class="logo-dark logo-img " id-="logo-dark" src="'.site_url('assets/img/logo.png?ver=1.2').'" srcset="'.site_url('assets/img/logo.png?ver=1.2').'" alt="logo-dark">

                        <img class="logo-light logo-img" id="logo-light" src="'.site_url('assets/img/logo.png?ver=1.2').'" srcset='.site_url('assets/img/logo.png?ver=1.2').'" alt="logo">
                    </a>
                </div><!-- .nk-header-brand -->
                <div class="nk-header-menu" data-content="headerNav">
                <div class="nk-header-mobile">
                    <div class="nk-header-brand">
                        <a href="'.site_url('setup/dashboard').'" class="logo-link">
                        <img class="logo-dark logo-img " id-="logo-dark" src="'.site_url('assets/img/logo.png?ver=1.2').'" srcset="'.site_url('assets/img/logo.png?ver=1.2').'" alt="logo-dark">

                            <img class="logo-light logo-img" id="logo-light" src="'.site_url('assets/img/logo.png?ver=1.2').'" srcset="'.site_url('assets/img/logo.png?ver=1.2').'" alt="logo">
                        </a>
                    </div>
                    <div class="nk-menu-trigger me-n2">
                        <a href="#" class="nk-nav-toggle nk-quick-nav-icon" data-target="headerNav"><em class="icon ni ni-arrow-left"></em></a>
                    </div>
                </div>
                <ul class="nk-menu nk-menu-main ui-s2">
                <li class="nk-menu-item">
                    <a href="'.site_url('setup/dashboard').'" class="nk-menu-link">
                        <span class="nk-menu-text">Home</span>
                    </a>                                   
                </li><!-- .nk-menu-item -->
                
            
           
           
            '.($user_grp==='Admin'?'  
            <li class="nk-menu-item has-sub">
            <a href="#" class="nk-menu-link nk-menu-toggle">
                <span class="nk-menu-text">Setup</span>
            </a>
            <ul class="nk-menu-sub">
                <li class="nk-menu-item">
                    <a href="'.site_url('setup/view_parameters').'" class="nk-menu-link"><span class="nk-menu-text">System Parameters</span></a>
                </li> 
                <li class="nk-menu-item">
                    <a href="'.site_url('setup/view_users').'" class="nk-menu-link"><span class="nk-menu-text">User Access Settings</span></a>
                </li>   
              
                
            </ul> <!-- .nk-menu-sub -->
            </li><!-- .nk-menu-item -->
            ':'').'
        
    </ul><!-- .nk-menu -->
</div><!-- .nk-header-menu -->
<div class="nk-header-tools">
    <ul class="nk-quick-nav">                           
        <li class="dropdown user-dropdown order-sm-first">
            <a href="#" class="dropdown-toggle" data-bs-toggle="dropdown">
                <div class="user-toggle">
                    <div class="user-avatar sm">
                        <em class="icon ni ni-user-alt"></em>
                    </div>
                    <div class="user-info d-none d-xl-block">
                        <div class="user-status">'.$designation.'</div>
                        <div class="user-name dropdown-indicator">'.$fullname.'</div>
                    </div>
                </div>
            </a>
            <div class="dropdown-menu dropdown-menu-md dropdown-menu-end dropdown-menu-s1 is-light">
                <div class="dropdown-inner user-card-wrap bg-lighter d-none d-md-block">
                    <div class="user-card">
                        <div class="user-avatar">
                            <span>'.str_split($firstname[0])[0]. ''. str_split($firstname[1])[0].'</span>
                        </div>
                        <div class="user-info">
                            <span class="lead-text">'.$fullname.'</span>
                            <span class="sub-text">'.$email.'</span>
                        </div>                        
                    </div>
                </div>
              
                <div class="dropdown-inner">
                    <ul class="link-list">                    
                        <li><a href="'.site_url('Profile/view_profile').'"><em class="icon ni ni-user-alt"></em><span>View Profile</span></a></li>
                        <li><a href="'.site_url('requests/initiate_request/error_request').'"><em class="icon ni ni-report"></em><span>Report an Issue</span></a></li>
                        <li><a class="dark-switch" href="#"><em class="icon ni ni-moon"></em><span>Dark Mode</span></a></li>
                    </ul>
                </div>
                <div class="dropdown-inner">
                    <ul class="link-list">
                        <li><a href="'.site_url('Home/logout').'"><em class="icon ni ni-signout"></em><span>Sign out</span></a></li>
                    </ul>
                </div>
            </div>
        </li><!-- .dropdown -->
       
    </ul><!-- .nk-quick-nav -->
</div><!-- .nk-header-tools -->
</div><!-- .nk-header-wrap -->
</div><!-- .container-fliud -->
</div>
<!-- main header @e -->
'
?>