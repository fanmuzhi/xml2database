<?php

class xmlAPI
{
	private $myDB;
	private $PDO_resource;
//	public	$xml_obj = array();
	public	$xml_files_array = array();
//	private $root_dir = '';
	private $serial_number_pattern ='/^[01]([0][0-9]|[1][0-9])([0][0-9][0-9])[0][0][0][1-9]([0][1-9]|[1][0-5])([0-4][0-9]|[5][0-3])[0-6][a-zA-Z0-9][a-zA-Z0-9][a-zA-Z0-9][1-8]$/';
	
	public	$error_message;


	//
	// Constructor
	//
	public function __construct()
	{
		try
		{
			$this->myDB=new MyDatabase();
			$this->myDB->connect_database();
			echo "db_connected"."\n";
//			$this->root_dir = $dir;
		}
		catch(Exception $ex)
		{
			$this->error_message=$ex->getMessage();
		}
	}

	//
	// Destructor
	//
	public function __destruct()
	{
		try
		{
			$this->myDB->disconnect_database();
//			$this->root_dir = '';
//			$this->xml_obj = null;
			unset($this->xml_files_array);
		}
		catch(Exception $ex)
		{
			$this->error_message=$ex->getMessage();
		}
	}


	
//	public function generate_xml_obj($xml_file)
//	{
//		if($xml_file)
//		{
//			$this->xml_obj = simplexml_load_file($xml_file);
////			print_r($this->xml_obj);
//			return true;
//		}
//		else
//		{
//			return false;
//		}
//	}
	


	
	/**
	 * $path is the root dir and
	 * this function can list all the *.xml files 
	 * include the ones exists in child folder.
	 */
	public function recurDIR_xml($path)
	{
		try
		{
			if($path)
			{
				$first_xml_files = array_merge(glob($path.'/'.'*.xml'),glob($path.'/'.'*.XML'));	
				foreach ($first_xml_files as $f)
				{
					array_push($this->xml_files_array,$f);
				}
				foreach ($child_dir= glob($path.'/'.'*',GLOB_ONLYDIR) as $dir)
				{
	//				$this->xml_files_array= array_merge($this->xml_files_array,$this->recurDIR_xml($dir));
					$this->recurDIR_xml($dir);
				}
				return true;
			}
			else
			{
				$this->error_message= "cannot recursive load xml files";
				return false;
			}
		}
		catch(Exception $ex)
		{
			$this->error_message=$ex->getMessage();
		}
		
	}




	/**
	 * check if log already exist in database
	 * return bool t/f if exist/notexist
	 */	
	public function db_log_check($xml_obj)
	{
		try
		{
			$serial_number =$xml_obj->Serial_Number ;
			$part_type = substr($serial_number,0,8);
			$test_time = substr($xml_obj->Test_Time,0,19);
			
			$sql_statement="SELECT `Id` FROM dut WHERE `PartType` = '$part_type' AND `SerialNumber` = '$serial_number' AND `TestTime` = '$test_time'";
			$result = $this->myDB->execute_reader($sql_statement);
			
			if($result==-1)
			{
				return FALSE;
			}
			else
			{
				return TRUE;
			}
		}
		catch(Exception $ex)
		{
			$this->error_message=$ex->getMessage();
		}
	}
	
	
	
	/**
	 * validat serial_number
	 * return bool t/f if valid/not_valid
	 */	
	public function serial_number_IsValid($serial_number)
	{
		try
		{
			if(preg_match($this->serial_number_pattern, $serial_number))
			{
					return true;
			}
			else
			{
				return false;
			}
		}
		catch(Exception $ex)
		{
			$this->error_message=$ex->getMessage();
		}
	}
	
	
	
	/**
	 * insert data into dut and return inserted id
	 * return $id
	 */	
	public function log_insert_and_return_id($xml_obj)
	{
		try
		{
			if($xml_obj)
			{
				if(isset($xml_obj->Serial_Number))
				{
					$serial_number	=	$xml_obj->Serial_Number;
					$part_type 		=	substr($serial_number,0,8);
				}
				else 
				{
					$serial_number	=	"null";
					$part_type		=	"null";
				}
				
				if(isset($xml_obj->Test_Station))
				{
					$test_station	=	substr($xml_obj->Test_Station,0,3);
				}
				else $test_station	= 	"null";
				
				if(isset($xml_obj->Error_Code))
				{
					$error_code 	=	$xml_obj->Error_Code;
				}
				else $error_code	=	"null";
				
				if(isset($xml_obj->Test_Time))
				{
					$test_time		=	substr($xml_obj->Test_Time,0,19);
				}
				else $test_time		= 	"null";
				
				if(isset($xml_obj->IDD_Value))
				{
					$idd_value		=	$xml_obj->IDD_Value;
				}
				else $idd_value		= 	"null";
				
				if(isset($xml_obj->Firmware_Revision))
				{
					$fw_version		=	$xml_obj->Firmware_Revision;
				}
				else $fw_version	=	"null";
				
				$sql0="insert into `trackpad`.`dut` (`SerialNumber`, `TestStation`, `PartType`, `ErrorCode`,`IDDValue`,`FirmwareVersion`, `TestTime`,`TestStatus`) " .
													 "values ('$serial_number', '$test_station', '$part_type', $error_code, $idd_value, $fw_version, '$test_time', ";
				
				$exist_active = $this->exist_active($serial_number, $part_type, $test_station);
				if($exist_active)
				{
					foreach($exist_active as $id_time)
					{
						$exist_id = $id_time['Id'];
						$exist_time = $id_time['TestTime'];
						if($this->file_time_newer($exist_time,$test_time))
						{
							$this->status_update($exist_id);
							$sql_status = "0";
						}
						else
						{
							$sql_status = "1";
						}
					}
				}
				else
				{
					$sql_status = "0";
				}

				$sql_statement= $sql0.$sql_status.")";
				
				$result = $this->myDB->execute_none_query($sql_statement);
				$sql_id = "select Id from dut ORDER BY Id DESC LIMIT 1";
//				$getID = $this->myDB->db->lastInsertId();//$getID is the last inserted item id num
				$getID = $this->myDB->execute_scalar($sql_id);
//				if($getID)
//				{
					return $getID;
//				}
//				else
//				{
//					return false;
//				}
			}
			else
			{
				return false;
			}
		}
		catch(Exception $ex)
		{
			$this->error_message=$ex->getMessage();
		}
	}
	
//	private function test_status_update($serial_number, $part_type, $test_station)
//	{
//		try
//		{
//			$sql0 = "SELECT `dut`.`TestTime` FROM dut WHERE `PartType` = '$part_type' AND `TestStation` = '$test_station' AND SerialNumber = '$serial_number' ORDER BY `TestTime` DESC";
//			$logs = $this->myDB->execute_reader($sql0);
////			print_r($logs);
//			if(count($logs) >= 1 )
//			{
//				$latest_time = $logs[0]['TestTime'];
//				$update_active = "UPDATE dut SET `TestStatus` = 0 WHERE `PartType` = '$part_type' AND `TestStation` = '$test_station' AND SerialNumber = '$serial_number' AND `TestTime` = '$latest_time'";
//				$update_inactive = "UPDATE dut SET `TestStatus` = 1 WHERE `PartType` = '$part_type' AND `TestStation` = '$test_station' AND SerialNumber = '$serial_number' AND `TestTime` < '$latest_time'";
//				$this->myDB->execute_none_query($update_active);
//				$this->myDB->execute_none_query($update_inactive);
//				return true;
////				echo "done";
//			}
//			else
//			{
//				return false;
//			}
//		}
//		catch(Exception $ex)
//		{
//			$this->error_message=$ex->getMessage();
//		}
//	}

	private function exist_active($serial_number, $part_type, $test_station)
	{
		try
		{
			$sql0 = "SELECT Id,TestTime FROM dut WHERE `PartType` = '$part_type' AND `TestStation` = '$test_station' AND SerialNumber = '$serial_number' AND TestStatus = 0";
			$actice_id_time = $this->myDB->execute_reader($sql0);
			if($actice_id_time != -1)
			{
				return $actice_id_time;
			}
			else
			{
				return false;
			}
		}
		catch(Exception $ex)
		{
			$this->error_message=$ex->getMessage();
		}
	}
	
	private function file_time_newer($existed_time,$xml_test_time)
	{
		try
		{
//			$sql = "SELECT 'TestTime' FROM dut Where `Id` = `$id`";
//			$existed_time = $this->myDB->execute_scalar($sql);
			if($existed_time<$xml_test_time)
			{
				return true;
			}	
			else
			{
				return false;
			}
		}
		catch(Exception $ex)
		{
			$this->error_message=$ex->getMessage();
		}
	}
	
	
	private function status_update($id)
	{
		try
		{
				$sql = "UPDATE dut SET `TestStatus` = 1 WHERE `dut`.`Id` = '$id'";
				$this->myDB->execute_none_query($sql);
				return true;
		
		}
		catch(Exception $ex)
		{
			$this->error_message=$ex->getMessage();
		}
	}
	
	
	
	/**
	 * check if log already exist in database
	 * @param $id is the id of dut to be inserted
	 * into child table as DUTID
	 * return bool t/f if exist/not
	 */	
	public function sub_table_insert($id,$xml_obj)
	{
		try
		{	
//			$dut_id = $id;
			if(isset($xml_obj->IDAC_Value))
			{
				$combined_value = '';
				foreach($xml_obj->IDAC_Value->children()	as	$key=>$val)
				{
					$combined_value	= $combined_value."(".$id.",".(int)substr($key,1).",".$val."),";
				}
				$str_id_index_value	=	substr($combined_value,0,-1);
				$sql_statement="insert into `trackpad`.`idacvalue`(`DUTID`, `ValueIndex`, `IDACValue`) " .
													  	  "values".$str_id_index_value;
				$this->myDB->execute_none_query($sql_statement);	
			}
			else if(isset($xml_obj->Global_IDAC_Value))
			{
				$combined_value = '';
				foreach($xml_obj->Global_IDAC_Value->children()	as	$key=>$val)
				{
					$combined_value	= $combined_value."(".$id.",".(int)substr($key,1).",".$val."),";
				}
				$str_id_index_value	=	substr($combined_value,0,-1);
				$sql_statement="insert into `trackpad`.`idacvalue`(`DUTID`, `ValueIndex`, `IDACValue`) " .
													  	  "values".$str_id_index_value;
				$this->myDB->execute_none_query($sql_statement);	
			}
			if(isset($xml_obj->Raw_Count_Averages))
			{
				$combined_value = '';
				foreach($xml_obj->Raw_Count_Averages->children() as $key=>$val)
				{
					$combined_value	= $combined_value."(".$id.",".(int)substr($key,1).",".$val."),";
				}
				$str_id_index_value	=	substr($combined_value,0,-1);
				$sql_statement="insert into `trackpad`.`rawcountaverage` (`DUTID`, `ValueIndex`, `RawCountAverage`) " .
						"		   					  			  values".$str_id_index_value;
				$this->myDB->execute_none_query($sql_statement);	
			}
			
			if(isset($xml_obj->Raw_Count_Noise))
			{
				$combined_value	= '';
				foreach($xml_obj->Raw_Count_Noise->children() as $key=>$val)
				{
					$combined_value	= $combined_value."(".$id.",".(int)substr($key,1).",".$val."),";
				}
				$str_id_index_value	=	substr($combined_value,0,-1);
				$sql_statement="insert into `trackpad`.`rawcountnoise` (`DUTID`, `ValueIndex`, `RawcountNoise`) " .
						"		   					  			values".$str_id_index_value;
				$this->myDB->execute_none_query($sql_statement);
			}
			
			if(isset($xml_obj->IDD_Sleep1_Value) and isset($xml_obj->IDD_Deep_Sleep_Value))
			{
				$idd_sleep_1 	= $xml_obj->IDD_Sleep1_Value;
				$idd_deep_sleep = $xml_obj->IDD_Deep_Sleep_Value;
				$sql_statement="insert into `trackpad`.`iddstandby` (`DUTID`, `IDDSleep1`, `IDDDeepSleep`)" .
															"values ('$id', '$idd_sleep_1', '$idd_deep_sleep') ";
				$this->myDB->execute_none_query($sql_statement);	
			}
		}
		catch(Exception $ex)
		{
			$this->error_message=$ex->getMessage();
		}
	}
}

?>