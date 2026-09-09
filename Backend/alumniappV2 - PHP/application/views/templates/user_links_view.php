<?php defined('BASEPATH') OR exit('No direct script access allowed'); 
echo '
<div class="navbar-collapse collapse">
    <ul class="nav navbar-nav navbar-right">
        <li><a href="'.site_url('requests/dashboard').'">Home</a></li>           
        <li class="dropdown">
            <a href="'.site_url('#').'" class="dropdown-toggle js-activated" 
                data-toggle="dropdown" role="button" aria-haspopup="true">Requests
                <i class="fa fa-angle-down"></i></a>
            <ul class="dropdown-menu">
                <li><a href="'.site_url('requests/request_view').'">Active Requests</a></li>
                <li><a href="'.site_url('requests/archived_view').'">Archived Requests</a></li>
                <li><a href="'.site_url('requests/awaiting_approval').'">Awaiting Approval</a></li>
                <li><a href="'.site_url('requests/request_list').'">Initiate Requests</a></li> 
            </ul>            
        </li> 
        <li class="dropdown">
            <a href="'.site_url('#').'" class="dropdown-toggle js-activated" 
                data-toggle="dropdown" role="button" aria-haspopup="true">HOD View
                <i class="fa fa-angle-down"></i></a>
            <ul class="dropdown-menu">
                <li><a href="'.site_url('requests/functional_view').'">Active Requests</a></li>
                <li><a href="'.site_url('requests/functional_frees').'">Frees Report</a></li>
                <li><a href="'.site_url('requests/functional_expenses').'">Expenses Report</a></li>
                <li><a href="'.site_url('requests/functional_travel').'">Travel Report</a></li>
            </ul>            
        </li>         
        

		'.($user_grp==='POS' || $user_grp==='Admin'?' 
        <li class="dropdown">
            <a href="'.site_url('#').'" class="dropdown-toggle js-activated" 
                data-toggle="dropdown" role="button" aria-haspopup="true">POSM View
                <i class="fa fa-angle-down"></i></a>
            <ul class="dropdown-menu">
                <li><a href="'.site_url('requests/Company_frees').'">Frees Report</a></li>
                <li><a href="'.site_url('requests/Company_frees_details').'">Frees Details Report</a></li>
                <li><a href="'.site_url('requests/Company_posm').'">POSM Report</a></li>
                <li><a href="'.site_url('requests/Company_posm_details').'">POSM Details Report</a></li>
            </ul>            
        </li> 		

		':'').'	
                    

		'.($user_grp==='Finance' || $user_grp==='Admin'?' 
        <li class="dropdown">
            <a href="'.site_url('#').'" class="dropdown-toggle js-activated" 
                data-toggle="dropdown" role="button" aria-haspopup="true">Finance View
                <i class="fa fa-angle-down"></i></a>
            <ul class="dropdown-menu">
                <li><a href="'.site_url('requests/Company_frees').'">Frees Report</a></li>
                <li><a href="'.site_url('requests/Company_frees_details').'">Frees Details Report</a></li>
                <li><a href="'.site_url('requests/Company_expenses').'">Expenses Report</a></li>
                <li><a href="'.site_url('requests/Company_travel').'">Travel Report</a></li>
            </ul>            
        </li> 		
        <li class="dropdown">
            <a href="'.site_url('#').'" class="dropdown-toggle js-activated" 
                data-toggle="dropdown" role="button" aria-haspopup="true">Journals
                <i class="fa fa-angle-down"></i></a>
            <ul class="dropdown-menu">
                <li><a href="'.site_url('requests/tr_journal').'">Travel Request</a></li>
                <li><a href="'.site_url('requests/tra_exp_journal').'">Travel Expense Claims</a></li>                    
                <li><a href="'.site_url('requests/other_exp_journal').'">Other Expense Claims</a></li>
                <li><a href="'.site_url('requests/card_journal').'">Credit Card Expense</a></li>                    
                <li><a href="'.site_url('requests/frees_journal').'">Frees</a></li>           
                <li><a href="'.site_url('requests/payable_journal').'">Expense Claims Payout List</a></li>
            </ul>            
        </li>  
		':'').'			
        <li class="dropdown">
            <a href="'.site_url('#').'" class="dropdown-toggle js-activated" 
                data-toggle="dropdown" role="button" aria-haspopup="true">My Profile
                <i class="fa fa-angle-down"></i></a>
            <ul class="dropdown-menu">
                <li><a href="'.site_url('Profile/view_profile').'">View Profile</a></li>
                <li><a href="'.site_url('Home/logout').'">Logout</a></li>               
            </ul>
        </li> 

        '.($user_grp==='Admin'?'            
        <li class="dropdown">
            <a href="'.site_url('#').'" class="dropdown-toggle js-activated" 
                data-toggle="dropdown" role="button" aria-haspopup="true">Setup
                <i class="fa fa-angle-down"></i></a>
            <ul class="dropdown-menu">
                <li><a href="'.site_url('setup/view_parameters').'">System Parameters</a></li>
                <li><a href="'.site_url('setup/view_users').'">Users Access Settings</a></li>                    
                <li><a href="'.site_url('setup/view_items_table').'">Items Table</a></li>
                <li><a href="'.site_url('setup/view_dept').'">Department Setup</a></li>                    
                <li><a href="'.site_url('setup/view_workflow').'">Workflow Settings</a></li>  
                <li><a href="'.site_url('requests/all_requests').'">Admin All Request View</a></li>                    
            </ul>
        </li>
        ':'').'
    </ul>
</div><!--/.nav-collapse -->
      ';
        