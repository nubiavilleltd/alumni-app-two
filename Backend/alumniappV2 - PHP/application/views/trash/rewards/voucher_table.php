<?php defined('BASEPATH') OR exit('No direct script access allowed');
   $rebate_type="WAM Customers";
   $cus_type="Clients WAM";
   $mon_rebate="Monthly Sell in Rebate";
   $mon_rebate="Vente Mensuelle Avec Remise";
   $week_rebate="Weekly Sell in Rebate";
   $week_rebate="Vente semaine Avec Remise";
   $quart_rebate="Quarterly Sell in Rebate";
   $quart_rebate="Vente Trimestriel Avec Remise";
   $Customer="Customer";
   $Customer="Nom Du Client";
   $prod="Product name";
   $prod="Nom Du Produit";   
   $Currency="Currency";
   $Currency="Devise"; 
   $Weekly="Weekly";
   $Weekly="Hebdomadaire";   
   $Monthly="Monthly";
   $Monthly="Mensuelle";   
   $Target="Target";
   $Target="Objectif"; 
   $Actual="Actual";
   $Actual="Realisation";   
   $Quarterly="Quarterly";
   $Quarterly="Trimestriel"; 
   $Monthly="Monthly";
   $Monthly="Mensue";   
   $Expire="Expire";
   $Expire="Expirer";  
   $Growth="Growth(Rate Rebate)";
   $Monthly="Croissance(Taux Remise)";   
   $Base="Base(Rate Rebate)";
   $Base="Base(Taux Remise)";
   $Amount="Amount";
   $Amount="Montant";  
   $Print="Print Date";
   $Print="Date D’Impression";   
   $amt_words="Amount in words";
   $amt_words="Montant En Lettres";

   $cust_sign="Customer Sign & Date";
   $cust_sign="Client Sign & Date";  
   $bat_sign="BAT Sign & Date";
   $bat_sign="BAT Sign & Date";   
   $dis_sign="Distributor Sign & Date";
   $dis_sign="Distributer Sign & Date";
   
$html2=' <table width="100%">
              <tr>
                <td width="20"></td>
                <td><strong>Currency:</strong> GHS</td>
                <td></td>
                <td></td>
                <td></td>
                <td colspan="2" align="center"><strong>Base</strong></td>
                <td colspan="2" align="center"><strong>Growth</strong></td>
                <td align="center" width="50">&nbsp;</td>
              </tr>
              <tr>
                <td>&nbsp;</td>
                <td><strong>Product Name</strong></td>
                <td align="right"><strong>Target</strong></td>
                <td align="right"><strong>Actual</strong></td>
                <td align="right"><strong>Rate</strong></td>
                <td align="right"><strong>Rebate</strong></td>
                <td align="right"><strong>Rate</strong></td>
                <td  align="right"><strong>Rebate</strong></td>
                <td>&nbsp;</td>
              </tr>';
            foreach($main_data as $row2)
            {
                $html2.='                      
                    <tr>
                      <td>&nbsp;</td>
                      <td>'.$row2->sku_code.' - '.$row2->sku_name.'</td>
                      <td align="right">'.$row2->target_vol.'</td>
                      <td align="right">'.$row2->sales_vol.'</td>
                      <td align="right">'.$row2->pay_rate.'</td>
                      <td align="right">'.$row2->amt.'</td>
                      <td align="right">'.$row2->growth_rate.'</td>
                      <td align="right">'.$row2->growth_amt.'</td>
                      <td align="right">&nbsp;</td>
                    </tr>';
            }
                $html2.='
                <tr>
                  <td></td>
                  <td></td>
                  <td></td>
                  <td></td>
                  <td  align="right"><strong>Total</strong></td>
                  <td align="right"><strong>184</strong></td>
                  <td  align="right"><strong>Total</strong></td>
                  <td align="right"><strong>0</strong></td>
                  <td align="right">&nbsp;</td>
                </tr>
                <tr>
                  <td>&nbsp;</td>
                  <td><strong>Weekly: </strong> 128.8</td>
                  <td></td>
                  <td align="right"><strong>Monthly: </strong></td>
                  <td>36.8</td>
                  <td></td>
                  <td align="right"><strong>Quarterly: </strong></td>
                  <td>18.4</td>
                  <td align="right">&nbsp;</td>
                </tr>
              </table>';

echo $html2;