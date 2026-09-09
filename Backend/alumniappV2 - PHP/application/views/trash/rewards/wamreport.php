<?php defined('BASEPATH') OR exit('No direct script access allowed');

if ($country_data->language=='English'){
    $this->load->view('rewards/english');
    
   $rebate_type="WAM Customers";
   $mon_rebate="Monthly Sell in Rebate";
   $week_rebate="Weekly Sell in Rebate";
   $quart_rebate="Quarterly Sell in Rebate";
   $Customer="Customer";
   $prod="Product name";
   $Currency="Currency";
   $Weekly="Weekly";
   $Target="Target";
   $Actual="Actual";
   $Quarterly="Quarterly";
   $Monthly="Monthly";
   $Expire="Expire";
   $Growth="Growth(Rate Rebate)";
   $Base="Base(Rate Rebate)";
   $Amount="Amount";
   $Print="Print Date";
   $amt_words="Amount in words";
   $cust_sign="Customer Sign & Date";
   $bat_sign="BAT Sign & Date";
   $dis_sign="Distributor Sign & Date";  
   $appr_date="Approval Date";
} else {
    $this->load->view('rewards/french');   
    $t = new ConvertNumberToText();
   $rebate_type="Clients WAM";
   $mon_rebate="Vente Mensuelle Avec Remise";
   $week_rebate="Vente semaine Avec Remise";
   $quart_rebate="Vente Trimestriel Avec Remise";
   $Customer="Nom Du Client";
   $prod="Nom Du Produit";   
   $Currency="Devise"; 
   $Weekly="Semaine";   
   $Target="Objectif"; 
   $Actual="Realisation";   
   $Quarterly="Trimestriel"; 
   $Monthly="Mensuelle";   
   $Expire="Expirer";  
   $Growth="Croissance(Taux Remise)";   
   $Base="Base(Taux Remise)";
   $Amount="Montant";  
   $Print="Date D’Impression";   
   $amt_words="Montant En Lettres";
   $cust_sign="Client Sign & Date";  
   $bat_sign="BAT Sign & Date";   
   $dis_sign="Distributer Sign & Date"; 
   $appr_date="Date d'approbation";   
}

$cnt=0;
$curr1  =$country_data->currency;
$curr2  = $country_data->currency;
$weekly=$country_data->weekly;
$monthly=$country_data->monthly;
$quaterly=$country_data->quarterly;
$html = '
<style>
body {
    font-size: 12px;
    line-height: 24px;
    font-family: verdana;
}

    .voucher {
        background: url('.site_url('assets/images/'.$country_data->voucher).') no-repeat!important;
    }
</style>
';
    $sellout=(strpos($country_data->campaign, 'Sell Out') !== false)?TRUE:FALSE;
    
    if (strpos($country_data->campaign, 'Weekly') !== false) {
       $rebate_title =$rebate_type.' - '.$week_rebate.' - '.$country_data->fullmonth.' - '.$Weekly.' - '.$country_data->weekval;
    } else if (strpos($country_data->campaign, 'Monthly') !== false) {
        $rebate_title =$rebate_type.' - '.$mon_rebate.' - '.$country_data->fullmonth;
    } else if (strpos($row->campaign, 'Quarterly') !== false) {
        $rebate_title =$rebate_type.' - '.$quart_rebate.' - '.$Quarterly.' - '.$country_data->quarter;
    } else {

    }


        
    foreach($main_data as $row)
    {
        $cnt+=1;
        $pcent_val=$sellout?100:$row->pcent;
        $vals=$main_class->voucher_table($row->winners_id,$pcent_val);
        $html.='<p>&nbsp;</p>
<div class="voucher" width=100%>
    <table width="780" >
        <tbody>
        <tr>
          <td height="70" width="5">&nbsp;</td>
          <td colspan="3">&nbsp;</td>
          <td width="12">&nbsp;</td>
        </tr>
        <tr>
          <td height="24">&nbsp;</td>
          <td colspan="3">&nbsp;</td>
          <td>&nbsp;</td>
        </tr>
        <tr>
          <td height="24">&nbsp;</td>
          <td colspan="3"><strong><center>'.$rebate_title.'</center></strong></td>
          <td>&nbsp;</td>
        </tr>         
        <tr>
          <td height="24">&nbsp;</td>
          <td colspan="2"><strong>'.$Customer.': </strong> '.$row->cust_name.'</td>
          <td><strong>Distributor: </strong>'.$row->distributor.'</td>
          <td>&nbsp;</td>
        </tr>      
        <tr>
          <td height="22">&nbsp;</td>
          <td><strong>URN: </strong>'.$row->cust_code.'</td>
          <td><strong>Area: </strong>'.$row->location.'</td>
          <td><strong>Region: </strong>'.$row->region.'</td>
          <td>&nbsp;</td>
        </tr>           
        <tr>
          <td colspan="6" height="254" valign="top">
          
            <table width="768">
              <tr>
                <td width="5" height="22"></td>
                <td><strong><nobr>'.$prod.'</nobr></strong></td>
                <td align="right"><strong><nobr>'.$Target.'</nobr></strong></td>
                <td align="right"><strong><nobr>'.$Actual.'</nobr></strong></td>
                <td colspan="2" align="right"><strong><nobr>'.$Base.'</nobr></strong></td>
                <td align="right"><strong><nobr>Due</nobr></strong></td>
                <td colspan="2" align="right"><strong><nobr>'.$Growth.'</nobr></strong></td>                    
             </tr>               
          '.$vals['html'].'
                <tr>
                  <td height="24"></td>
                  <td><strong>'.$Currency.':</strong> '.$curr1.'</td>
                  <td></td>
                  <td></td>
                  <td></td>
                  <td align="right"><strong>Due</strong></td>
                  <td align="right"><strong>'.number_format($vals['due_amt'],2).'</strong></td> 
                  <td align="right"><strong>Total</strong></td>
                  <td align="right"><strong>'.number_format($vals['gwth_amt'],2).'</strong></td>
                </tr>'.
                ($sellout?'':'
                <tr>
                  <td height="24">&nbsp;</td>
                  <td><strong>'.$Weekly.' ('.$weekly.'%): </strong> '.number_format(($vals['total_amt']*$weekly/100),2).'</td>
                  <td></td>                      
                  <td colspan="3"><strong>'.$Monthly.' ('.$monthly.'%): </strong> '.number_format(($vals['total_amt']*$monthly/100),2).'</td>
                  <td colspan="3" align="right"><strong>'.$Quarterly.'  ('.$quaterly.'%): </strong> '.number_format(($vals['total_amt']*$quaterly/100),2).'</td>
                </tr>').'
              </table>
                
          </td>
        </tr>
        <tr>
          <td height="52">&nbsp;</td>
          <td colspan="2"><strong>'.$amt_words.':</strong> '.ucfirst((($country_data->language=='English')?
           convert_number_to_words(floor($row->total_amt)):$t->Convert(floor($row->total_amt)))).' '.$curr2.' Only </td>
          <td><strong>'.$Amount.': '.number_format(floor($row->total_amt),2).' '.$curr1.'</strong></td>
          <td>&nbsp;</td>
        </tr>          
        <tr>
          <td height="24">&nbsp;</td>
          <td colspan="2"><strong>'.$Print.': </strong>'.date("M j, Y").'</td>
          <td><strong>'.$Expire.':</strong>'.date('M j, Y', mktime(date("H"), date("i"), date("s"), date("m"), date("d") + 45, date("Y"))).'</td>
          <td>&nbsp;</td>
        </tr>
        <tr>
          <td height="24">&nbsp;</td>
          <td colspan="2"><strong>'.$appr_date.'</strong>:'.date("M j, Y", strtotime($appr->appr_date)).'</td>
          <td><strong>CHQ No: </strong> '.$row->gencode.'</td>
          <td><br></td>
        </tr>
        <tr>
          <td height="50"></td>
          <td colspan="2"></td>
          <td></td>
          <td></td>
      </tr>
        <tr>
          <td height="24"></td>
          <td><strong>Customer Sign &amp; Date</strong></td>
          <td align="centre"><strong>BAT Sign &amp; Date</strong></td>
          <td align="right"><strong>Distributor Sign &amp; Date</strong></td>
          <td></td>
      </tr>      
        <tr>
          <td height="24"></td>
          <td colspan="2"></td>
          <td></td>
          <td></td>
      </tr> 
      </tbody>
    </table>
</div>    
    <br/>
';
 }
//echo $html;
      
        
        
//        $timer1='stats => '.memory_get_usage().' - '.$mem1.', '.microtime(TRUE).' - '.$time1.'<br>'.
//        'memory=> '.(memory_get_usage() - $mem1) / (1024 * 1024).'MB<br>'.
//        'seconds =>'.(microtime(TRUE) - $time1).'Secs';
// $test='<table><tr><td>'.$timer1. '</td></tr><tr><td>'.$timer. '</td></tr></table>';
        
$obj_pdf->writeHTML($html);
//$obj_pdf->writeHTML($test);
$obj_pdf->Output();
exit();