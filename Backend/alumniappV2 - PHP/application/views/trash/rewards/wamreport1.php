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
} else {
    $this->load->view('rewards/french');   
    $t = new ConvertNumberToText();
   $cus_type="Clients WAM";
   $mon_rebate="Vente Mensvelle Avec Remise";
   $week_rebate="Vente semaine Avec Remise";
   $quart_rebate="Vente Trimestriel Avec Remise";
   $Customer="Nom Du Client";
   $prod="Nom Du Produit";   
   $Currency="Devise"; 
   $Weekly="Hebdomadaire";   
   $Target="Objectif"; 
   $Actual="Realisation";   
   $Quarterly="Trimestriel"; 
   $Monthly="Mensue";   
   $Expire="Expirer";  
   $Monthly="Croissance(Taux Remise)";   
   $Base="Base(Taux Remise)";
   $Amount="Montant";  
   $Print="Date D’Impression";   
   $amt_words="Montant En Lettres";
   $cust_sign="Client Sign & Date";  
   $bat_sign="BAT Sign & Date";   
   $dis_sign="Distributer Sign & Date";    
}
$cnt=0;
$curr1  =$country_data->currency;
$curr2  = $country_data->currency;
$weekly=$country_data->weekly;
$monthly=$country_data->monthly;
$quaterly=$country_data->quaterly;
$html = '
<style>
    .voucher {
        background: url('.site_url('assets/images/'.$country_data->voucher).') no-repeat!important;
    }
</style>
';
    foreach($main_data as $row)
    {
        $cnt+=1;
        $vals=$main_class->voucher_table($row->winners_id,$row->pcent);
        $html.='<p>&nbsp;</p>'.$cnt.'
<div class="voucher" width=100%>
    <table width="760" >
        <tbody>
        <tr>
          <td height="70" width="20">&nbsp;</td>
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
          <td colspan="3"><strong><center>'.$row->rebate_title.'</center></strong></td>
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
          
            <table width="758">
              <tr>
                <td width="20" height="22"></td>
                <td><strong><nobr>'.$prod.'</nobr></strong></td>
                <td align="right"><strong><nobr>'.$Target.'</nobr></strong></td>
                <td align="right"><strong><nobr>'.$Actual.'</nobr></strong></td>
                <td colspan="2" align="right"><strong><nobr>'.$Base.'</nobr></strong></td>
                <td align="right"><strong><nobr>Base Due</nobr></strong></td>
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
                  <td align="right"><strong>'.$vals['due_amt'].'</strong></td> 
                  <td align="right"><strong>Total</strong></td>
                  <td align="right"><strong>0</strong></td>
                </tr>
                <tr>
                  <td height="24">&nbsp;</td>
                  <td><strong>'.$Weekly.' ('.$weekly.'%): </strong> '.($vals['total_amt']*$weekly/100).'</td>
                  <td colspan="3"><strong>'.$Monthly.' ('.$monthly.'%): </strong> '.($vals['total_amt']*$monthly/100).'</td>
                  <td></td>
                  <td></td>
                  <td colspan="2" align="right"><strong>'.$Quarterly.'  ('.$quaterly.'%): </strong> '.($vals['total_amt']*$quaterly/100).'</td>
                </tr>                
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
          <td colspan="2"><strong>CDM Approved Ok Date</strong>:'.date("M j, Y", strtotime($appr->appr_date)).'</td>
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
echo $html;

//$obj_pdf->writeHTML($html);
//$obj_pdf->Output();
//exit();