<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Api_model extends CI_Model {

 /*=======================================================
        GET API SECTION
    ========================================================
*/
public function fetchAPIKey( $api_name)
    {
        // $sql = "SELECT * FROM api_table WHERE  "
        // . "api_name='".$api_name."'  limit 1"; 
        //     log_message('error', var_export($sql, true));
        // $query = $this->db->query($sql);
        $sql = "SELECT * FROM api_table WHERE api_name = ? LIMIT 1";
    
        log_message('error', "Executing SQL: " . $sql . " with param: " . $api_name);
    
        $query = $this->db->query($sql, [$api_name]);
    
        $result = [];
        
        foreach($query->result() as $row){
            // $i = null;
            // $linkcount = null;
            $temp["api_key"] = $row->api_key;
          ///  $temp["value"] = $row->value;
            array_push($result, $temp);           
        }
        log_message('error', var_export($result, true));
        return $result;
    }
// in your model (Api_model)
public function rotateAPIKey(string $api_name)
{
    // generate a 64-char hex key (32 bytes = 256 bits)
    try {
        $newKey = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        // random_bytes failed (very unlikely) — fallback
        $newKey = bin2hex(openssl_random_pseudo_bytes(32));
    }

    // start transaction (optional but safer)
    $this->db->trans_start();

    // update the row
    $this->db->where('api_name', $api_name);
    $this->db->update('api_table', ['api_key' => $newKey]);

    $this->db->trans_complete();

    if ($this->db->trans_status() === FALSE) {
        // update failed
        log_message('error', "Failed to rotate api_key for {$api_name}");
        return false;
    }

    // optionally return the updated row or just the key
    return $newKey;
}

	public function fetchAllCategories()
    {
        $sql = "SELECT * FROM categories";
        // "SELECT e.eventId, e.eventName, e.eventDesc, e.venue, e.eventLocation, e.eventStartDate, e.eventStartTime, e.eventEndDate, e.eventEndTime, e.eventFlier, e.eventStatus, f.thumbnail, f.fileUrl, f.fileExt, l.* FROM events e LEFT JOIN eventfiles f ON e.eventId = f.eventId LEFT JOIN eventlinks l ON e.eventId = l.id where e.eventStatus = 'Upcoming' ORDER BY e.createDate DESC ";
        $query = $this->db->query($sql);
        $result = [];
        foreach($query->result() as $row){
            // $i = null;
            // $linkcount = null;
            // $temp["categorycode"] = $row->categorycode;
            $temp["category"] = $row->category;
            $temp["brand"] = $row->brand;
            $temp["sku_list"] = $row->sku_list;                                  
            array_push($result, $temp);           
        }
        return $result;
    }

    public function fetchAllLocation()
    {
        $sql="SELECT country as 'name',country as 'value' FROM "
        . "country order by country";
        $query = $this->db->query($sql);
        $result = [];
        foreach($query->result() as $row){
            // $i = null;
            // $linkcount = null;
            $temp["name"] = $row->name;
            $temp["value"] = $row->value;
            array_push($result, $temp);           
        }
        return $result;
    }
    public function fetchgetCustomer( $market)
    {
        $sql = 'SELECT
                concat(customers.cust_name,"-(",customers.cust_code,")") as name,
                customers.cust_code as value FROM customers WHERE
                customers.market = "'.$market.'" 
                order by customers.cust_name 
            '; 
            log_message('error', var_export($sql, true));
        // $query = $this->db->query($sql);
        // $result = [];
        
        // foreach($query->result() as $row){
        //     // $i = null;
        //     // $linkcount = null;
        //     $temp["name"] = $row->name;
        //     $temp["value"] = $row->value;
        //     array_push($result, $temp);           
        // }
        // log_message('error', var_export($result, true));
        // return $result;
        $temp_data = $this->base_model->run_qry($sql,'qry');
        $total_data=$temp_data->num_rows();
        $total_data=is_null($total_data)?0:$total_data;

        $main_data=$temp_data->result(); 
        $main_data=$total_data==0?array():$main_data;
       //  log_message('error',$sql);
       log_message('error', var_export($main_data, true));
        return $main_data;
    }
    public function fetchgetCustomerDetail( $custcode)
    {
        $sql = "SELECT * FROM customers WHERE  "
        . "cust_code='".$custcode."'  limit 1"; 
            log_message('error', var_export($sql, true));
        $query = $this->db->query($sql);
        $result = [];
        
        foreach($query->result() as $row){
            // $i = null;
            // $linkcount = null;
            $temp["band"] = $row->cust_category;
          ///  $temp["value"] = $row->value;
            array_push($result, $temp);           
        }
        log_message('error', var_export($result, true));
        return $result;
    }

    public function fetchgetCustomertype($custcode)
    {
        $sql = 'SELECT distinct customers.cust_type as name,
        customers.cust_type as value
             FROM customers WHERE
            customers.cust_code = "'.$custcode.'" 
        ';   
        $query = $this->db->query($sql);
        $result = [];
        foreach($query->result() as $row){
            // $i = null;
            // $linkcount = null;
            $temp["cust_type"] = $row->name;
            array_push($result, $temp);           
        }
        return $result;
    }
   
    public function fetchgetMonth()
    {
        $sql="SELECT distinct(fullmonth) as name, (fullmonth) as month, fullmonth as value,  STR_TO_DATE(CONCAT('01 ', fullmonth), '%d %b %Y') AS sorter FROM importdata  WHERE   1=1  order by name desc";
        // $query = $this->db->query($sql);
        // $result = [];
        // foreach($query->result() as $row){
        //     // $i = null;
        //     // $linkcount = null;
        //     $temp["month"] = $row->name;
        //     array_push($result, $temp);           
        // }
        // return $result;
        
        
    
        log_message('error',"sql5".$sql);

  //}
  
        $temp_data = $this->base_model->run_qry($sql,'qry');
        $total_data=$temp_data->num_rows();
        $total_data=is_null($total_data)?0:$total_data;

        $main_data=$temp_data->result(); 
        $main_data=$total_data==0?array():$main_data;
       //  log_message('error',$sql);
       
        return $main_data;
    }
   
   
	
	 public function fetchAllOutlet($data)
    {
        $sql = "SELECT * FROM outlets WHERE usercode = '{$data}'";

        $query = $this->db->query($sql);
        $result = [];
        foreach($query->result() as $row){            
            $temp["name"] = $row->name;
            $temp["outletCode"] = $row->outletcode;
			$temp["address"] = $row->address;
			$temp["state"] = $row->state;
			$temp["region"] = $row->region;
			$temp["status"] = $row->status;
			$temp["city"] = $row->city;
			$temp["channel"] = $row->channel;
			$temp["subChannel"] = $row->sub_channel;
			$temp["managerName"] = $row->manager_name;
			$temp["managerPhoneNumber"] = $row->manager_phone_number;
			$temp["supplierName"] = $row->supplier_name;			
			$temp["userCode"] = $row->usercode;	
			$temp["lastvisit"] = $row->lastvisit;	

            array_push($result, $temp);           
        }
        return $result;
    }

    public function fetchAllVisit($data){
        $sql = "SELECT * FROM trade_visits WHERE usercode = '{$data}'";

        $query = $this->db->query($sql);
        $result = [];
        foreach($query->result() as $row){    
            $temp["visitCode"] = $row->visitcode;        
            $temp["outletName"] = $row->outletname;
            $temp["outletCode"] = $row->outletcode;
			$temp["category"] = $row->category;
			$temp["brand"] = $row->brand;
			$temp["sku"] = $row->sku;
			$temp["availability"] = $row->availability;
			$temp["listing"] = $row->listing;
			$temp["quantity"] = $row->quantity;
			$temp["price"] = $row->price;
			$temp["priceChange"] = $row->pricechange;
			$temp["newPrice"] = $row->new_price;
			$temp["duration"] = $row->duration;											
			$temp["userCode"] = $row->usercode;	
			$temp["lastvisit"] = $row->lastvisit;	
            array_push($result, $temp);           
        }
        return $result;
    }

    public function fetchAllSchedule($data){
        $sql = "SELECT * FROM schedule_visit WHERE usercode = '{$data}'";

        $query = $this->db->query($sql);
        $result = [];
        foreach($query->result() as $row){                  
            $temp["outletName"] = $row->outletname;
            $temp["outletCode"] = $row->outletcode;
            $temp["outletaddress"] = $row->outletaddress;
			$temp["date"] = $row->date;
			$temp["day"] = $row->day;
			$temp["userCode"] = $row->usercode;
            array_push($result, $temp);           
        }
        return $result;
    }

 /*=======================================================
        END GET API SECTION
========================================================
*/

 /*=======================================================
        POST API SECTION
========================================================
*/
public function tradeVisit($data)
{
    $this->db->trans_begin();
   
    $this->db->insert('trade_visits', $data);

    $this->db->trans_complete();

    if ($this->db->trans_status() === FALSE) {
        $this->db->trans_rollback();
        return FALSE;
    }
    else {
        $this->db->trans_commit();
        return TRUE;
    }
}
public function updatelastvisit($data)
{
    $this->db->trans_begin();

    // Update the last visit
    $sql = "UPDATE outlets SET lastvisit = '{$data['lastvisit']}' WHERE outletcode = '{$data['outletcode']}'";
    $query = $this->db->query($sql);

    // Check if the query was successful
    if (!$query) {
        $this->db->trans_rollback();
        return false;
    }

    // Insert the trade visit data
    $tradeVisitSuccess = $this->api_model->tradeVisit($data);

    if (!$tradeVisitSuccess) {
        $this->db->trans_rollback();
        return false;
    }

    $this->db->trans_commit();

    return true;
}

// function updatelastvisit($data){    
//     $sql = "UPDATE outlets SET lastvisit='{$data['lastvisit']}' WHERE outletcode='{$data['outletcode']}'";
//     $query = $this->db->query($sql); 
//     return $query;  
// }


public function fetchDocumentTypes($file_type, $user_id)
{
    $sql = 'SELECT file_type, filename, attachment_file 
            FROM attachments 
            WHERE user_id="' . $user_id . '" 
              AND file_type="' . $file_type . '"';

    log_message('error', var_export($sql, true));

    $temp_data = $this->base_model->run_qry($sql, 'qry');
    $total_data = $temp_data->num_rows();
    $total_data = is_null($total_data) ? 0 : $total_data;

    $main_data = $temp_data->result();
    $main_data = $total_data == 0 ? array() : $main_data;

    $result = [];
    if (!empty($main_data)) {
        foreach ($main_data as $doc) {
            $result[] = [
                "file_type"       => $doc->file_type,
                "filename"        => $doc->filename,
                "attachment_file" => site_url('uploads/' . $doc->attachment_file),
            ];
        }
    }

    log_message('error', var_export($result, true));
    return $result;
}


	
	public function getSKUs($market) {
        $sql = "SELECT DISTINCT sku_name FROM products WHERE country = '{$market}'";
        $query = $this->db->query($sql);
        $result = [];
        foreach($query->result() as $row){
            array_push($result, $row->sku_name);           
        }
        return $result;
    }
	public function saveUserDetails($name, $email, $ip, $location, $deviceType, $os, $deviceModel) {
        $data = [
            'name' => $name,
            'email' => $email,
            'ip_address' => $ip,
            'location' => $location,
            'device_type' => $deviceType,
            'operating_system' => $os,
            'device_model' => $deviceModel,
            'created_at' => date('Y-m-d H:i:s') // Full month format
        ];
        $this->db->insert('users_log', $data);
    }
/*=======================================================
   END POST API SECTION
========================================================
*/
// public function create_otp_record($otp, $email)
//     {
//         try {
//             $data['otp'] = (int) $otp;     
//             $data['email'] = $email;
//             $data['is_active'] = 1;

//             $this->db->insert('register_user_otp', $data);

//             if ($this->db->affected_rows() <= 0) {
//                 log_message('error', 'OTP CREATION FAILED, failed to create otp record: ' . json_encode($this->db->error()));
//                 return false;
//             }

//             return true;
//         } catch (Exception $e) {
//             log_message('error', "An error occured while creating otp record: " . $e->getMessage());
//             return false;
//         }
//     }
// public function create_otp_record($otp, $email)
// {
//     try {
//         $this->db->trans_start(); // Start transaction

//         // 1 Deactivate previous OTPs for this email
//         $this->db->where('email', $email);
//         $this->db->update('register_user_otp', ['is_active' => 0]);

//         // 2️ Prepare new OTP record
//         $data = [
//             'otp'        => (int) $otp,
//             'email'      => $email,
//             'is_active'  => 1,
//             'created_at' => date('Y-m-d H:i:s')
//            // 'expires_at' => date('Y-m-d H:i:s', strtotime('+15 minutes'))
//         ];

//         // 3️ Insert new OTP
//         $this->db->insert('register_user_otp', $data);

//         $this->db->trans_complete(); // Complete transaction

//         // 4️ Check if transaction succeeded
//         if ($this->db->trans_status() === false || $this->db->affected_rows() <= 0) {
//             log_message('error', 'OTP CREATION FAILED: ' . json_encode($this->db->error()));
//             return false;
//         }

//         return true;

//     } catch (Exception $e) {
//         log_message('error', 'An error occurred while creating OTP record: ' . $e->getMessage());
//         return false;
//     }
// }
public function create_otp_record($otp, $email)
{
    try {
        $this->db->trans_start();

        // 1️⃣ Check if email already exists
        $exists = $this->db
            ->select('id')
            ->from('register_user_otp')
            ->where('email', $email)
            ->limit(1)
            ->get()
            ->num_rows();

        // 2️⃣ If exists, deactivate previous OTPs
        if ($exists > 0) {
            $this->db
                ->where('email', $email)
                ->update('register_user_otp', ['is_active' => 0]);
        }

        // 3️⃣ Insert new OTP
        $data = [
            'otp'        => (int) $otp,
            'email'      => $email,
            'is_active'  => 1,
            'created_at' => date('Y-m-d H:i:s'),
            // 'expires_at' => date('Y-m-d H:i:s', strtotime('+15 minutes'))
        ];

        $this->db->insert('register_user_otp', $data);

        $this->db->trans_complete();

        // 4️⃣ Check transaction status
        if ($this->db->trans_status() === false) {
            log_message('error', 'OTP CREATION FAILED: ' . json_encode($this->db->error()));
            return false;
        }

        return true;

    } catch (Exception $e) {
        log_message('error', 'OTP ERROR: ' . $e->getMessage());
        return false;
    }
}

    public function is_otp_valid($data)
    {
        try {
            $query = $this->db->get_where('register_user_otp', [
                'email' => (int) $data['email'],
                'otp' => (int) $data['otp']
            ]);

            if ($this->db->error()['code'] !== 0) {
                log_message('error', "Database error while fetching OTP record: " . json_encode($this->db->error()));
                return ['status' => false, 'error' => 'EXCEPTION', 'message' => 'Database error occurred'];
            }

            if ($query->num_rows() === 0) {
                log_message('error', 'INVALID_OTP_CODE – OTP not found for email');
                return ['status' => false, 'error' => 'INVALID_OTP_CODE', 'message' => 'Invalid or EXPIRED OTP code'];
            }

            $otp_record = $query->row_array();

            if ($otp_record['is_active'] == 0) {
                log_message('info', 'OTP code has already expired');
                return ['status' => false, 'error' => 'OTP_CODE_EXPIRED', 'message' => 'This OTP code has expired'];
            }

            if ($otp_record['email'] !== $data['email']) {
                log_message('info', 'EMAIL_MISMATCH: OTP was not issued for this email');
                return [
                    'status' => false,
                    'error' => 'EMAIL_MISMATCH',
                    'message' => 'Invalid or EXPIRED OTP code'
                ];
            }

            $this->db->set('is_active', 0)->where('id', $otp_record['id'])->update('register_user_otp');

            if ($this->db->error()['code'] !== 0) {
                log_message('error', "Database error while deactivating OTP: " . json_encode($this->db->error()));
                return ['status' => false, 'error' => 'EXCEPTION', 'message' => 'Failed to deactivate OTP'];
            }

            return ['status' => true, 'message' => 'OTP is valid and now deactivated'];
        } catch (Exception $e) {
            log_message('error', 'Unexpected error while verifying OTP: ' . $e->getMessage());
            return ['status' => false, 'error' => 'EXCEPTION', 'message' => 'Something went wrong'];
        }
    }
    public function register_token($data)
	{
		$sql = "INSERT IGNORE INTO `push_tokens` (`user_id`, `token`) VALUES (?,?)";
		$run_qry = $this->db->query($sql, array($data['user_id'], $data['token']));

		if (!$run_qry) {
			return false;
		}

		return true;
	}
public function get_recipients($id = null)
	{
		$this->db->select('token');

		if ($id !== null) {
			$this->db->where('user_id', $id);
		}

		$query = $this->db->get('push_tokens');

		log_message("error", 'Push Tokens: ' . var_export($query->result_array(), true));

		// Return the result as an array of tokens
		return array_column($query->result_array(), 'token');
	}

}