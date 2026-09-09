<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class base_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }
    public $primary_table = ''; // Set the name of the table for this model.
    public $primary_key = 'id';// Set the primary key of the table for this model. 

    
    public function get_name($user_id){
        $this->db->select('*');
        $query = $this->db->get_where('users', array('id' => $user_id));
        if ($query->num_rows() > 0)	{
            $row = $query->row();
            return $row->fullname;
        } else {
            $query->free_result();
            return NULL;
        }
    }     
    
    public function rec_count_nigeria($tablename='',$id='', $key='', $arrkey=FALSE )
    {
        $second_DB = $this->load->database('nigeria', TRUE);

        $second_DB->initialize();
        $keyval = $arrkey=== FALSE ? array($key => $id) : $arrkey;
        if($id=='' & $arrkey=== FALSE){
            $retVal= $second_DB->count_all($tablename);
            //log_message("error", "count all -".$this->db->last_query()); 
            return $retVal ;   
        }else {
            $query = $second_DB->get_where($tablename, $keyval);
            $retVal= $query->num_rows(); 
            //log_message("error", "count record -".$this->db->last_query()); 
            return $retVal ;   
            
        }
    }
    
    public function rec_count($tablename='',$id='', $key='', $arrkey=FALSE )
    {
        $keyval = $arrkey=== FALSE ? array($key => $id) : $arrkey;
        if($id=='' & $arrkey=== FALSE){
            $retVal= $this->db->count_all($tablename);
            //log_message("error", "count all -".$this->db->last_query()); 
            return $retVal ;   
        }else {
            $query = $this->db->get_where($tablename, $keyval);
            $retVal= $query->num_rows(); 
            //log_message("error", "count record -".$this->db->last_query()); 
            return $retVal ;   
            
        }
    }
    public function get_record($tablename='', $id='', $key='', $per_page=0, $offset=0 ,$orderby='')
    {
        $table=($tablename == ''?$this->primary_table:$tablename);
        $key=($key === ''?$this->primary_key:$key);
        $sql = "select $table.* from $table ".($id==""?"":" where $key = "
           .(gettype($id)=='string'?"'".$id."'":$id))." "
           .($orderby==''?" ":" order by $orderby")
           .($per_page==0?" ":" LIMIT $offset, $per_page");
       
        $query=$this->db->query($sql);
        if ($query->num_rows() > 0)	{
            $retVal= $query->result();
        } else {
            $retVal= NULL;
        }
        //$query=$this->db->last_query();
        $query->free_result();
        return $retVal ;       
    }
    public function get_single_record_nigeria($id, $key='',$tablename='', $multi='',$arrkey = FALSE)
    {
        $second_DB = $this->load->database('nigeria', TRUE);



        $second_DB->initialize();
        $table=($tablename === ''?$this->primary_table:$tablename);
        $key=($key === ''?$this->primary_key:$key);
        $keyval = $arrkey=== FALSE ? array($key => $id) : $arrkey;        
        $query = $second_DB->get_where($table, $keyval);
        switch ($multi) {
            case 'qry':
                $retVal=$query;
                break;
            case 'count':
                $retVal=$query->num_rows(); 
                break;
            Default:
                if ($query->num_rows() < 1) $retVal=NULL;
                if ($query->num_rows()  == 1 && $multi==''){ 
                    $retVal = $query->row();
                } else {
                    $retVal= $query->result();
                }          
        }
        $query->free_result();
        return $retVal ;        
    }
    public function get_single_record($id, $key='',$tablename='', $multi='',$arrkey = FALSE)
    {
        $table=($tablename === ''?$this->primary_table:$tablename);
        $key=($key === ''?$this->primary_key:$key);
        $keyval = $arrkey=== FALSE ? array($key => $id) : $arrkey;        
        $query = $this->db->get_where($table, $keyval);
        switch ($multi) {
            case 'qry':
                $retVal=$query;
                break;
            case 'count':
                $retVal=$query->num_rows(); 
                break;
            Default:
                if ($query->num_rows() < 1) $retVal=NULL;
                if ($query->num_rows()  == 1 && $multi==''){ 
                    $retVal = $query->row();
                } else {
                    $retVal= $query->result();
                }          
        }
        $query->free_result();
        return $retVal ;        
    }
    public function get_linked_records($pri_table,$join_arr,$where_key = '',$order_key = '', $join_type, $multi='')
    {
        $this->db->select('*');
        $this->db->from($pri_table);
         foreach($join_arr as $sec_table => $join_key) {
            $this->db->join($sec_table, $join_key,$join_type);
        }
        $where_key=== '' ?'' : $this->db->where($where_key);
        $order_key=== '' ?'' : $this->db->order_by($order_key);
        $per_page==0?"":$this->db->limit($offset, $per_page);
        $query = $this->db->get();         
        switch ($multi) {
            case 'qry':
                $retVal=$query;
                break;
            case 'count':
                $retVal=$query->num_rows(); 
                break;
            Default:
                if ($query->num_rows() < 1) $retVal=NULL;
                if ($query->num_rows()  == 1 && $multi==''){ 
                    $retVal = $query->row();
                } else {
                    $retVal= $query->result();
                }          
        }
//        log_message("error", "link record log -".$this->db->last_query());             
        $query->free_result();
        return $retVal ;       
    }    

    public function run_qry($sql, $action='result', $multi=''){
        $qry_res=$this->db->query($sql);
        //log_message("error", "run proc log - ".$this->db->last_query()); 
        
        switch ($action) {
            case "run":
                return TRUE;
                break;         
            case "result":
                if ($qry_res->num_rows() < 1) {$res= Null;}
                if ($qry_res->num_rows() == 1 && $multi=='') {$res= $qry_res->row();
                } else {$res= $qry_res->result();}
                $qry_res->next_result();
                $qry_res->free_result();
                return $res;
                break;      
            case "count":
                return $qry_res->num_rows();  
                break;          
            case "qry":
                return $qry_res;             
                break;
        }
    } 
    public function run_qry_nigeria($sql, $action='result', $multi=''){
        $second_DB = $this->load->database('nigeria', TRUE);
        $second_DB->initialize();
        if ($second_DB) {
            log_message("error", "Database connection found.");
            $qry_res = $second_DB->query($sql);
            // process $qry_res here
        } else {
            // handle the error if the database is not loaded correctly
            echo "Database connection failed.";
            log_message("error", "Database connection failed.");
        //log_message("error", "run proc log - ".$this->db->last_query()); 
        }
        switch ($action) {
            case "run":
                return TRUE;
                break;         
            case "result":
                if ($qry_res->num_rows() < 1) {$res= Null;}
                if ($qry_res->num_rows() == 1 && $multi=='') {$res= $qry_res->row();
                } else {$res= $qry_res->result();}
                $qry_res->next_result();
                $qry_res->free_result();
                return $res;
                break;      
            case "count":
                return $qry_res->num_rows();  
                break;          
            case "qry":
                return $qry_res;             
                break;
        }
    }   
    
    public function insert($dataRec = FALSE,$tablename=''){
        
        $table = $tablename=='' ? $this->primary_table : $tablename;
        if ($dataRec === FALSE)
	{
            $this->session->set_flashdata('Error','Error Inserting Entry');
            return NULL;
        } else {
            $this->db->insert($table, $dataRec);
            //log_message("error", "insert log -".$this->db->last_query());                        
            return $this->db->insert_id();
        }
    }
 
    public function update($dataRec = FALSE,$id = FALSE,$tablename='',$key=FALSE ){
        
        $table = $tablename=='' ? $this->primary_table : $tablename;
        $keyval = $key=== FALSE ? array($this->primary_key => $id) : $key;
        if ($dataRec === FALSE or $dataRec === FALSE) {
            $this->session->set_flashdata('Error','Error Updating Entry');
            return NULL;
        } else {
            $this->db->update($table, $dataRec, $keyval);   
           //log_message("error", "update log -".$this->db->last_query());                        
            return $this->db->affected_rows();
        }   
    }
    public function select($tablename = '', $where_conditions = array())
{
    // Perform a select query to retrieve data based on provided conditions
    $query = $this->db->get_where($tablename, $where_conditions);

    // Check if any rows are returned
    if ($query->num_rows() > 0) {
        // Return the result set as an array
        return $query->result_array();
    } else {
        // If no rows are found, set a flash message and return NULL
        $this->session->set_flashdata('Error', 'No matching entries found');
        return NULL;
    }
}

public function get_last_saved_record($table_name='',$column='',$parameter='')
{
   // $this->db->select('*');
    log_message("error", "update log -".$table_name);
    $sqla = 'SELECT  * FROM ' . $table_name . ' where '.$column .'="' . $parameter . '"';
                $query = $this->db->query($sqla);
                log_message("error", "update log -".$sqla);
   // $query = $this->db->get_where($table_name, array($column=> $parameter));
        //$this->db->get_where($table_name, array($column=> $parameter)); // Assuming 'created_at' is the timestamp column

       // $this->db->order_by('created_on', 'desc');   
    
//$this->db->get_where($table_name, array($column=> $parameter)); // Assuming 'created_at' is the timestamp column
  
    $this->db->limit(1);

    $query = $this->db->get();

    if ($query->num_rows() > 0) {
        return $query->row_array(); // Return the last saved record as an associative array
    } else {
        return NULL; // No records found
    }
}
    public function delete($tablename='',$key=FALSE)
    {
	if ($key === FALSE)
	{
            $this->session->set_flashdata('Error','Error Deleting Entry');
            return NULL;
        } else { 
            $this->db->delete($tablename, $key);
            //log_message("error", "update log -".$this->db->last_query());
        }
    }    

    public function get_parameters_menu($parameter='',$menu_name='',$menu_value='',$place_holder='', $options='class="selectval"',$multiple="No")
    {
        $this->db->select("setup_value");
        //var_dump($parameter);die;
        $query = $this->db->get_where('setup_parameters', array('setup_name' => $parameter));
        //<select class="chosen-select-no-results form-control">
        $strval="<select $options name='$menu_name' data-placeholder='$place_holder' id='$menu_name' ".($multiple=="Yes"?'multiple="multiple"':'')." data-search='on'>";
        $strval.=($multiple=="Yes"?"":"<option value=' '>-- $place_holder --</option>");
        if ($query->num_rows() > 0)	{
                $row = $query->row();
                $rowitems = explode("\n", str_replace("\r", "", $row->setup_value));
                foreach($rowitems as $rowitem){ 
                if ($multiple=="Yes"){
                    $array_val=explode(',',$menu_value);
                    $strval.="<option value='".$rowitem."'".(in_array($rowitem,$array_val )?" selected='selected'":"").">".$rowitem."</option>";
                } else {
                    $strval.="<option value='".$rowitem."'".($menu_value==$rowitem?" selected='selected'":"").">".$rowitem."</option>";
                }
            }
        } 
        $query->free_result();
        return $strval.="</select>";
    }
    public function get_record_menu_nigeria($queryStr='',$menu_name='',$menu_value='',$place_holder='', $options='class="selectval"', $multiple='No')
    {
        $second_DB = $this->load->database('nigeria', TRUE);

        $second_DB->initialize();
        $query = $second_DB->query($queryStr);
        $strval="<select $options name='$menu_name".($multiple=="Yes"?'[]':'')."' data-placeholder='$place_holder' id='$menu_name' ".($multiple=="Yes"?'multiple="multiple"':'')."  data-search='on'>";        
        $strval.=($multiple=="Yes"?"":"<option value=''>-- $place_holder --</option>");
        if ($query->num_rows() > 0)	{
            if ($multiple=="Yes") {
                $valuArr=explode(",",$menu_value);
                foreach ($query->result() as $row)
                {
                    $strval.="<option value='".$row->value."'".(in_array($row->value, $valuArr)?" selected='selected'":"").">".$row->name."</option>";
                }   

            } else {
                foreach ($query->result() as $row)
                {
                    $strval.="<option value='".$row->value."'".($menu_value==$row->value?" selected='selected'":"").">".$row->name."</option>";
                }   

            }
        } 
        $query->free_result();
        return $strval.="</select>";
    }
    public function get_record_menu($queryStr='',$menu_name='',$menu_value='',$place_holder='', $options='class="selectval"', $multiple='No')
    {
        $query = $this->db->query($queryStr);
        $strval="<select $options name='$menu_name".($multiple=="Yes"?'[]':'')."' data-placeholder='$place_holder' id='$menu_name' ".($multiple=="Yes"?'multiple="multiple"':'')."  data-search='on'>";        
        $strval.=($multiple=="Yes"?"":"<option value=''>-- $place_holder --</option>");
        if ($query->num_rows() > 0)	{
            if ($multiple=="Yes") {
                $valuArr=explode(",",$menu_value);
                foreach ($query->result() as $row)
                {
                    $strval.="<option value='".$row->value."'".(in_array($row->value, $valuArr)?" selected='selected'":"").">".$row->name."</option>";
                }   

            } else {
                foreach ($query->result() as $row)
                {
                    $strval.="<option value='".$row->value."'".($menu_value==$row->value?" selected='selected'":"").">".$row->name."</option>";
                }   

            }
        } 
        $query->free_result();
        return $strval.="</select>";
    }
	  public function get_record_menu3($queryStr='',$menu_name='',$menu_value='',$place_holder='', $options='class="chosen-select-no-results form-control"', $multiple='No')
    {
        $query = $this->db->query($queryStr);
        $strval="<select $options name='$menu_name".($multiple=="Yes"?'[]':'')."' data-placeholder='$place_holder' id='$menu_name' ".($multiple=="Yes"?'multiple="multiple"':'')."  data-search='on'>";        
        $strval.=($multiple=="Yes"?"":"<option value=''>-- $place_holder --</option>");
        if ($query->num_rows() > 0)	{
            if ($multiple=="Yes") {
                $valuArr=explode(",",$menu_value);
                foreach ($query->result() as $row)
                {
                    $strval.="<option value='".$row->value."'".(in_array($row->value, $valuArr)?" selected='selected'":"").">".$row->name."</option>";
                }   

            } else {
                foreach ($query->result() as $row)
                {
                    $strval.="<option value='".$row->value."'".($menu_value==$row->value?" selected='selected'":"").">".$row->name."</option>";
                }   

            }
        } 
        $query->free_result();
        return $strval.="</select>";
    }

    public function get_record_menu2($queryStr='',$menu_name='',$menu_value='',$place_holder='', $options='class="selectval"', $multiple='No')
    {
        $query = $this->db->query($queryStr);
        $strval="<select $options name='$menu_name".($multiple=="Yes"?'[]':'')."' data-placeholder='$place_holder' id='$menu_name' ".($multiple=="Yes"?'multiple="multiple"':'')."  data-search='on'>";        
        $strval.=($multiple=="Yes"?"":"<option value=''>-- $place_holder --</option>");
        if ($query->num_rows() > 0)	{
            if ($multiple=="Yes") {
                $valuArr=explode(",",$menu_value);
                foreach ($query->result() as $row)
                {
                    $strval.="<option value='".$row->value."'".(in_array($row->value, $valuArr)?" selected='selected'":"").">".$row->name."</option>";
                }   

            } else {
                foreach ($query->result() as $row)
                {
                    $strval.="<option value='".$row->value."'".($menu_value==$row->value?" selected='selected'":"").">".$row->name."</option>";
                }   

            }
        } 
        $query->free_result();
        return $strval.="</select>";
    }
    
    protected function longDate($tmpdate='')
    {
       return date('F d, Y h:mA', strtotime($tmpdate));
    }

    protected function shortDate($tmpdate='')
    {
       return date('M d, Y', strtotime($tmpdate));
    }   
    
    public function get_preset ($field){  
        $this->db->select("setup_value");
        $query = $this->db->get_where('setup_parameters', array('setup_name' => $field));

        if ($query->num_rows() > 0)	{
            $row = $query->row();
            return $row->setup_value;
        } else {
            $query->free_result();
            return null;
        }
    }      

    public function checkParameter ($table,$field,$param){
        $this->db->select('*');
        $query = $this->db->get_where($table, array($field => $param));
        if ($query->num_rows() > 0)	{
            $query->free_result();
            return TRUE;
        } else {
            $query->free_result();
            return FALSE;
        }
    } 
    public function checkPreset ($field,$param){                
       //$this->db->get('setup_parameters');
        $this->db->select('*');
        $this->db->like( array('setup_value' => $param));
        $query = $this->db->get_where('setup_parameters', array('setup_name' => $field));

        if ($query->num_rows() > 0)	{
            $query->free_result();
            return TRUE;
        } else {
            $query->free_result();
            return FALSE;
        }
    }      
}       
?>