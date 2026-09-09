<?php defined('BASEPATH') OR exit('No direct script access allowed');

if ($country_data->language=='English'){
    $this->load->view('rewards/english');
} else {
    $this->load->view('rewards/french');   
    $t = new ConvertNumberToText();
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
        $vals=$main_class->voucher_table($row->winners_id);
        $html.='<p>&nbsp;</p>
<div class="voucher" width=100%>
    <table width="760" >
        <tbody>
        <tr>
          <td height="70" width="20">&nbsp;</td>
          <td colspan="3">&nbsp;</td>
          <td width="12">&nbsp;</td>
        </tr>
        <tr>
          <td height="20">&nbsp;</td>
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
          <td colspan="2"><strong>Customer: </strong> '.$row->cust_name.'</td>
          <td><strong>Distributor: </strong>'.$row->distributor.'</td>
          <td>&nbsp;</td>
        </tr>      
        <tr>
          <td height="24">&nbsp;</td>
          <td><strong>URN: </strong>'.$row->cust_code.'</td>
          <td><strong>Area: </strong>'.$row->location.'</td>
          <td><strong>Region: </strong>'.$row->region.'</td>
          <td>&nbsp;</td>
        </tr>           
        <tr>
          <td colspan="6" height="252" valign="top">
          
            <table width="100%">
              <tr>
                <td width="20"></td>
                <td><strong>Product Name</strong></td>
                <td align="right"><strong>Target</strong></td>
                <td align="right"><strong>Actual</strong></td>
                <td colspan="2" align="right"><strong>Base Rate &amp; </strong><strong>Rebate </strong></td>
                <td colspan="2" align="right"><strong>Growth Rate &amp; </strong><strong>Rebate</strong></td>
                <td width="20"></td>
             </tr>               

          '.$vals['html'].'
                <tr>
                  <td></td>
                  <td><strong>Currency:</strong> '.$curr1.'</td>
                  <td></td>
                  <td></td>
                  <td align="right"><strong>Total</strong></td>
                  <td align="right"><strong>'.$vals['total_amt'].'</strong></td>
                  <td align="right"><strong>Total</strong></td>
                  <td align="right"><strong>0</strong></td>
                  <td align="right">&nbsp;</td>
                </tr>
                <tr>
                  <td>&nbsp;</td>
                  <td><strong>Weekly ('.$weekly.'%): </strong> '.($vals['total_amt']*$weekly/100).'</td>
                  <td colspan="3"><strong>Monthly ('.$monthly.'%): </strong> '.($vals['total_amt']*$monthly/100).'</td>
                  <td></td>
                  <td colspan="2" align="right"><strong>Quarterly ('.$quaterly.'%): </strong> '.($vals['total_amt']*$quaterly/100).'</td>
                  <td></td>
                </tr>                
              </table>
                  
          </td>
        </tr>
        <tr>
          <td height="52">&nbsp;</td>
          <td colspan="2"><strong>Amount In Words:</strong> '.ucfirst((($country_data->language=='English')?
           convert_number_to_words(floor($row->total_amt)):$t->Convert(floor($row->total_amt)))).' '.$curr2.' Only </td>
          <td><strong>Amount: '.number_format(floor($row->total_amt),2).' '.$curr1.'</strong></td>
          <td>&nbsp;</td>
        </tr>          
        <tr>
          <td height="24">&nbsp;</td>
          <td colspan="2"><strong>Print Date: </strong>'.date("M j, Y").'</td>
          <td><strong>Expiry:</strong>'.date('M j, Y', mktime(date("H"), date("i"), date("s"), date("m"), date("d") + 45, date("Y"))).'</td>
          <td>&nbsp;</td>
        </tr>
        <tr>
          <td height="24">&nbsp;</td>
          <td colspan="2"><strong>CDM Approved Ok Date</strong>:'.date("M j, Y", strtotime($appr->appr_date)).'</td>
          <td><strong>CHQ No: </strong> '.$row->gencode.'</td>
          <td><br></td>
        </tr>
        <tr>
          <td height="48"></td>
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
        if($cnt%3==0){
            $html .= '<br/>';            
        }            
//        if($cnt==20){     
//            break;
//        }   
 }
echo $html;