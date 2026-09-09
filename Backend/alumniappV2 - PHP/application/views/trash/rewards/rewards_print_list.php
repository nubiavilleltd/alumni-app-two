<?php defined('BASEPATH') OR exit('No direct script access allowed');
function convert_number_to_words($number) {
    
    $hyphen      = '-';
    $conjunction = ' and ';
    $separator   = ', ';
    $negative    = 'negative ';
    $decimal     = ' kobo ';
    $dictionary  = array(
        0                   => 'zero',
        1                   => 'one',
        2                   => 'two',
        3                   => 'three',
        4                   => 'four',
        5                   => 'five',
        6                   => 'six',
        7                   => 'seven',
        8                   => 'eight',
        9                   => 'nine',
        10                  => 'ten',
        11                  => 'eleven',
        12                  => 'twelve',
        13                  => 'thirteen',
        14                  => 'fourteen',
        15                  => 'fifteen',
        16                  => 'sixteen',
        17                  => 'seventeen',
        18                  => 'eighteen',
        19                  => 'nineteen',
        20                  => 'twenty',
        30                  => 'thirty',
        40                  => 'forty',
        50                  => 'fifty',
        60                  => 'sixty',
        70                  => 'seventy',
        80                  => 'eighty',
        90                  => 'ninety',
        100                 => 'hundred',
        1000                => 'thousand',
        1000000             => 'million',
        1000000000          => 'billion',
        1000000000000       => 'trillion',
        1000000000000000    => 'quadrillion',
        1000000000000000000 => 'quintillion'
    );
    
    if (!is_numeric($number)) {
        return false;
    }
    
    if (($number >= 0 && (int) $number < 0) || (int) $number < 0 - PHP_INT_MAX) {
        // overflow
        trigger_error(
            'convert_number_to_words only accepts numbers between -' . PHP_INT_MAX . ' and ' . PHP_INT_MAX,
            E_USER_WARNING
        );
        return false;
    }

    if ($number < 0) {
        return $negative . convert_number_to_words(abs($number));
    }
    
    $string = $fraction = null;
    
    if (strpos($number, '.') !== false) {
        list($number, $fraction) = explode('.', $number);
    }
    
    switch (true) {
        case $number < 21:
            $string = $dictionary[$number];
            break;
        case $number < 100:
            $tens   = ((int) ($number / 10)) * 10;
            $units  = $number % 10;
            $string = $dictionary[$tens];
            if ($units) {
                $string .= $hyphen . $dictionary[$units];
            }
            break;
        case $number < 1000:
            $hundreds  = $number / 100;
            $remainder = $number % 100;
            $string = $dictionary[$hundreds] . ' ' . $dictionary[100];
            if ($remainder) {
                $string .= $conjunction . convert_number_to_words($remainder);
            }
            break;
        default:
            $baseUnit = pow(1000, floor(log($number, 1000)));
            $numBaseUnits = (int) ($number / $baseUnit);
            $remainder = $number % $baseUnit;
            $string = convert_number_to_words($numBaseUnits) . ' ' . $dictionary[$baseUnit];
            if ($remainder) {
                $string .= $remainder < 100 ? $conjunction : $separator;
                $string .= convert_number_to_words($remainder);
            }
            break;
    }
    
    if (null !== $fraction && is_numeric($fraction)) {
        if($fraction==='00'){
            $string .= ' Naira ';   
        } else {
            $string .= ' Naira ';
            $words = array();
            foreach (str_split((string) $fraction) as $number) {
                $words[] = $dictionary[$number];
            }
            $string .= implode(' ', $words);
            $string .= ' '.$decimal;
        }
    } 
    
    return $string;
}

echo'
<style>
    @media print {
    .voucher {
    width: 100%;
        background: url('.site_url('assets/images/voucher2.png').') top left no-repeat!important;;
    }
}
</style>    
<section class="services-wrap">        
    <div class="container">
        <div class="ibox">
            <div class="ibox-title">
                <h5>Print Voucher</h5>
                <div class="ibox-tools">
                    <a href="'.site_url('Rewards/generate_pdf/'.$data_var).'" data-toggle="tooltip" 
                        data-placement="bottom" title="" data-original-title="Print">
                        <i class="fa fa-print"></i>
                    </a>
                    <a href="'.site_url('Rewards/view_winners_list').'" data-toggle="tooltip" 
                        data-placement="bottom" title="" data-original-title="Return">
                        <i class="fa fa-undo"></i>
                    </a>                      
                </div>    
                
            </div>
            <div class="ibox-content">               
 
                <div class="col-md-12">
';
    $cnt=0;
    foreach($main_data as $row)
    {
        $cnt+=1;
        echo'
<p>&nbsp;</p>
<div class="voucher" width=100%>
    <table width="755" style="background:url('.site_url('assets/images/voucher2.png').'); background-repeat: no-repeat">
        <tbody>
        <tr>
          <td height="77" width="14">&nbsp;</td>
          <td width="96">&nbsp;</td>
          <td colspan="3">&nbsp;</td>
          <td width="73">&nbsp;</td>
        </tr>
        <tr>
          <td height="32">&nbsp;</td>
          <td>&nbsp;</td>
          <td colspan="3"></td>
          <td>&nbsp;</td>
        </tr>
        <tr>
          <td height="24">&nbsp;</td>
          <td colspan="4"><strong><div align="center">'.$row->rebate_title.'</div></strong></td>
          <td>&nbsp;</td>
        </tr>        
        <tr>
          <td height="24">&nbsp;</td>
          <td><strong>Customer:</strong></td>
          <td width="361">'.$row->cust_name.'</td>
          <td width="77"><strong>Area:</strong></td>
          <td width="106">'.$row->location.'</td>
          <td>&nbsp;</td>
        </tr>        
        <tr>
          <td height="24">&nbsp;</td>
          <td><strong><nobr>URN:</nobr></strong></td>
          <td>'.$row->cust_code.'</td>
          <td><strong>Region:</strong></td>
          <td>'.$row->region.'</td>
          <td>&nbsp;</td>
        </tr>
        <tr>
          <td height="24">&nbsp;</td>
          <td><strong>Amount:</strong></td>
          <td rowspan="2" valign="top">'.ucfirst(convert_number_to_words(floor($row->total_amt))).' Naira Only </td>
          <td colspan="2" align="right">&nbsp;</td>
          <td>&nbsp;</td>
        </tr>
        <tr>
          <td height="24">&nbsp;</td>
          <td>&nbsp;</td>
          <td colspan="2"><strong>'.number_format(floor($row->total_amt),2).' NGN</strong></td>
          <td>&nbsp;</td>                    
        </tr>
        <tr>
          <td height="24">&nbsp;</td>
          <td><strong><nobr>Print Date:</nobr></strong></td>
          <td>'.date("M j, Y").'</td>
          <td><strong>Expiry:</strong></td>
          <td><nobr>'.date('M j, Y', mktime(date("H"), date("i"), date("s"), date("m"), date("d") + 45, date("Y"))).'</nobr></td>
          <td>&nbsp;</td>
        </tr>
        <tr>
          <td height="24">&nbsp;</td>
          <td colspan="2"><strong>CDM Approved Ok Date</strong>::'.date("M j, Y", strtotime($appr->appr_date)).'</td>
          <td><strong><nobr>CHQ No: </nobr></strong></td>
          <td> '.$row->gencode.'</td>
          <td><br></td>
        </tr>
        <tr>
          <td height="24"></td>
          <td colspan="2"></td>
          <td></td>
          <td></td>
          <td></td>
      </tr>
      </tbody>
    </table>
    </div> 
    <br/>
    ';
        if($cnt%3==0){
            echo '<p>&nbsp;<br/></p>';            
        }        
 }
echo'
                 </div>                
            </div>
        </div>        
    </div>
</section>        
';
?>        