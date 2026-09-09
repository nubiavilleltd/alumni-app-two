<?php
echo '

<!DOCTYPE html>
<!--[if lt IE 7 ]><html class="ie ie6"> <![endif]-->
<!--[if IE 7 ]><html class="ie ie7"> <![endif]-->
<!--[if IE 8 ]><html class="ie ie8"> <![endif]-->
<!--[if (gte IE 9)|!(IE)]><!--><html > <!--<![endif]--><head >

	<!-- Basic Page Needs
    ================================================== -->
	<meta charset="utf-8">
	<title> ERROR</title>
	<meta name="robots" content="index, follow" />
 
    <!--[if lt IE 9]>
		<script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
	<![endif]-->   
	<!-- Mobile Specific Metas
    ================================================== -->
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<BASE HREF="http://www.titanicedge.com/">

    <link rel="stylesheet" href="assets/stylesheets/skeleton.css" type="text/css" media="all" />
    <link rel="stylesheet" href="assets/stylesheets/base.css" type="text/css" media="all" />
    <link rel="stylesheet" href="assets/stylesheets/layout.css?v1.1" type="text/css" media="all" />
    <link rel="stylesheet" href="assets/stylesheets/shortcodes.css?ver=1.1" type="text/css" media="all" />
    <link rel="stylesheet" href="assets/stylesheets/custom-style.css?ver=1.0" type="text/css" media="all" />
    <link rel="stylesheet" href="assets/stylesheets/form.css" type="text/css" media="all" />


</head>
	<body>
	<!-- Wrap (boxed or wide)-->
	<div id="wrap" class="wrap boxed"> 
        <div class="band top_border" > </div>

        <!-- Top Band - If you dont want to use Sticky menu please remove sticky class below -->
        <div class="band main sticky" >
            <!-- HEADER -->
            <div class="container header">
                <div class="three columns">
                    <h1 class="logo" ><a href=""><img src="assets/photos/logo.png" alt="Interio" /></a></h1>
                </div>
            	<div class="thirteen columns navbar" >
   
              <nav class="main">
  <ul id="menu-main-menu" class="sf-menu">
      <li><a href="user/dashboard">Dashboard</a></li>
      <li><a href="home/job_board">Job board</a>
          <ul class="sub-menu">			
          <li><a href="home/search_job"><span class="icon-sweden sz-xs" ></span>Search Jobs</a></li>	
      </ul> </li> 
      <li><a href="home/view_tests">Test Centre</a>
      <ul class="sub-menu">			
          <li><a href="home/view_tests"><span class="icon-sweden sz-xs" ></span>View Tests</a></li>	
          <li><a href="user/online_test/taken_test"><span class="icon-sweden sz-xs" ></span>Taken Test</a></li>
      </ul> </li>                       
      <li><a href="home/Courses">Online Courses</a></li>
  </ul>
</nav> 
<!-- End Navigation Menu -->   
       		</div><!--end thirteen -->       
            </div><!--end container -->   
            <!-- End HEADER -->   
        </div><!-- End of Main Band-->
        <!-- Slogan and Title -->
        <div class="band title" >
            
            <!-- Sub_Header -->
            <div class="container sub_header">
            	<div class="sixteen columns">
                    <div class="slogan">
                         Creating Awareness, Marketing Value
                    </div>
                    <div class="right_sub_text">
                        '.$log_user_out.'                        
                    </div>
                </div>
            </div><!-- End Sub_Header -->
            <!-- Page Title   -->  
            <div class="container page_title">
                <div class="sixteen columns center">
                   
                </div>              
            </div>
             </div><!-- end band-->     
            <!-- End Top Band -->  
            
            <!-- CONTENT -->   
            <div class="band content" >
                <div class="container">                   
                    <div class="sixteen columns texts-wrap center">   
                    
                    <h3 class="title">We Are Sorry!</h3>
                        <div class="col-md-12" > 

                                <h4>Your last operation just generated an error.</h4>
                                <p>Please be rest assured that this error has been properly logged and we are already
                                doing everything possible to ensure this does not happen again. </p>
                                <p>You can use <a href="home/index">click here to continue</a> or use any of the links above.<p>
                                <br />
                                We apologise for the inconvience.
                        </div>
                    </div><!-- container -->
                </div>
             </div><!-- end band-->    
             <!-- End CONTENT -->
            <!-- FOOTER -->
			<footer class="band footer" >
            <div class="container">            	
                <div class="widget widget_text six columns">
                <div class="textwidget">
                   <h4 class="title">Who We Are</h4>
                    <p>We give you The Titanic Edge in your career by providing you with all the information you 
                    need to get ahead and stay ahead.</p>
                </div>
			</div>
            <div class="widget widget_text six columns">
                <div class="textwidget"><h4 class="title">What We Offer</h4> <p>  
                        We have loads of currently available positions, practice tests as well online training courses 
                        to help you get the job of your dreams.</p>
            </div></div><div class="widget widget_interio_contact four columns">
            <h4 class="title">Contact Us</h4>
                <div class="address">
                    <div><a class="tooltips top" href="mailto:" target="_blank" title="Email">
                    <span class="icon-mail sz-xs" ></span>info_unit@titanicedge.com</a> </div>
                    <div><a class="tooltips top" href="#"  target="_blank"  title="Facebook">
                    <span class="icon-facebook-circled sz-xs" ></span>www.facebook.com/titanicedge</a> </div>
                    <div><a class="tooltips top" href="#"  target="_blank"  title="Twitter">
                    <span class="icon-twitter-circled sz-xs" ></span>www.twitter.com$#titanicedge</a> </div>

                </div>
            </div>	                
            </div><!-- container -->
         </footer><!-- end footer-->    
         <footer class="band bottom" >
            <div class="container">
                <div class="sixteen columns">
                        <div class="seven columns alpha">
                            <div class="copyright">
								Copyright &copy; 2015 TitanicEdge
                            </div>
                        </div>
                        <div class="two columns">
                            <div class="gototop"><a href="#top" class="tooltips top" title="Go to Top">&#xe851;</a></div>
                        </div>
                        <div class="seven columns omega">
                            <div class="copyright">

                            </div>  
                        </div>
                </div>
            </div><!-- container -->
         </footer><!-- end footer-->   
     </div><!-- end Wrap -->
<!-- End Document -->
</body>
</html>
        ';