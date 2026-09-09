<?php defined('BASEPATH') OR exit('No direct script access allowed'); 
echo '
</div>
</div>
<!-- footer @e -->
</div>
<!-- wrap @e -->
</div>
<div class="nk-footer nk-footer-fluid bg-lighter footer_bg"
            <<div class="container-xl wide-lg">
            <div class="nk-footer-wrap">
                <div class="footer-btm col-sm-6">
                Copyright:&copy;2025. Estate Management</div>        
                <div class="footer-btm col-sm-6">
                <a href="https://www.nubiaville.com/" class="pull-right">
                Powered by Nubiaville Ltd</a>
                </div>
            </div>
        </footer>
        <!--scripts and plugins -->  
       
         
 <script src="'.site_url('assets/js/jquery-3.7.0.min.js').'"></script>  
    
        <script src="'.site_url('/assets/js/bundle.js?ver=3.1.1').'"></script>  
 <script src="'.site_url('/assets/js/scripts.js').'"></script>   
        <script src="'.site_url('/assets/js/libs/datatable-btns.js').'"></script>
        <script src="'.site_url('assets/js/jquery-ui.min.js').'" type="text/javascript"></script> 
        <!-- <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-beta.1/dist/js/select2.min.js"></script> -->
 <script src="'.site_url('/assets/js/MonthPicker.min.js').'"></script> 
        <!--bootstrap js plugin-->
        <!--easing plugin for smooth scroll-->
        <script src="'.site_url('assets/js/jquery.easing.1.3.min.js').'" type="text/javascript"></script>
        <!--sticky header-->
        <script type="text/javascript" src="'.site_url('assets/js/jquery.sticky.js').'"></script>
        <script src="'.site_url('assets/js/bootstrap-hover-dropdown.min.js').'" type="text/javascript"></script>            
        <!--parallax background plugin-->
        <script src="'.site_url('assets/js/jquery.stellar.min.js').'" type="text/javascript"></script>
        <!--on scroll animation-->
        <script src="'.site_url('assets/js/wow.min.js').'" type="text/javascript"></script> 
        <!--popup js-->
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.14.0/Sortable.min.js"></script>
        <script src="https://cdn.datatables.net/rowreorder/1.4.1/js/dataTables.rowReorder.min.js"></script>
        <script src="https://mpryvkin.github.io/jquery-datatables-row-reordering/1.2.3/jquery.dataTables.rowReordering.js"></script>
  		<script src="https://cdn.datatables.net/rowreorder/1.4.1/js/dataTables.rowReorder.min.js"></script>
  		<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
                <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js"></script>
        <script src="'.site_url('assets/js/sweetalert.min.js').'" type="text/javascript"></script>    
        <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>             
        <script src="'.site_url('assets/js/jquery.magnific-popup.min.js').'" type="text/javascript"></script>         
        <!--customizable plugin edit according to your needs-->
        <script src="'.site_url('assets/js/custom.js').'" type="text/javascript"></script>
        <script src="'.site_url('/assets/js/dataTables.dateTime.min.js').'"></script>
        <script src="'.site_url('/assets/js/moment.min.js').'"></script>
        <script src="'.site_url('/assets/js/moment.min.js').'"></script>
        <script src="'.site_url('/assets/js/libs/editors/summernote.js?ver=3.1.1').'"></script>    
        <script src="'.site_url('/assets/js/editors.js?ver=3.1.1').'"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js"></script>
  <script>
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


		  <script type="text/javascript">
                $(document).ready(function(){
                     $(\'.slick-slider\').carousel({
                            interval: 2000 // Set the interval time in milliseconds here
                          });
                   });
        document.addEventListener("DOMContentLoaded", function () {
    flatpickr(".choosemnth2", {
mode: "single",
      dateFormat: "M Y",
      // You can add more configuration options here if needed



    plugins: [
        new monthSelectPlugin({
          shorthand: true, //defaults to false
          dateFormat: "M Y", //defaults to "F Y"
          altFormat: "F Y", //defaults to "F Y"
          theme: "light" // defaults to "light"
        })
    ]


    });
  });
   // $(".slider-wrap").carousel({ interval: 3000 }); 
    </script>
        
   
        '.$after_foot.'            
        </body></html> 
         ';