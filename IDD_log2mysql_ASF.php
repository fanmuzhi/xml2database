<?php

	require_once dirname(__FILE__).'/'.'../html/php/lib/database.php';
	require_once dirname(__FILE__).'/'.'lib/xml_api.php';
//	require_once dirname(__FILE__).'/'.'../Macross/php/lib/database.php';
//	require_once dirname(__FILE__).'/'.'lib/xml_api.php';
	
	$error_message;
	$root_dir = '/home/rawdata/ASF/';
//	$stations	= glob($root_dir.'/'.'*',GLOB_ONLYDIR);
//	array_shift ( $stations );
	$stations = array(
						$root_dir.'TMT1',
						$root_dir.'TMT2',
						$root_dir.'TMT3',
						$root_dir.'TMT4',
						$root_dir.'TPT'
					);
	$Invalid_sn_folder = '/home/rawdata/ASF_Invalid';
	if (file_exists($root_dir))
	{
		try
		{
			$i=1;
			foreach ($stations as $one_station)
			{
				foreach (array_slice(array_reverse($dates	= glob($one_station.'/'.'*',GLOB_ONLYDIR)),0,3) as $date_dir)
				{
					echo $date_dir;
					$my_xml = new xmlAPI();
					$my_xml->recurDIR_xml($date_dir);
					foreach ($my_xml->xml_files_array as $one_log)
					{
		//				echo $one_log."\n";
						if($xml_obj = simplexml_load_file($one_log))
						{
							echo $i."	";
							if($my_xml->db_log_check($xml_obj))
							{
								//echo $xml_obj->Serial_Number."<---"." already exist!";
								echo " ";
		//						continue;
							}
							else
							{
								if(!$my_xml->serial_number_IsValid($xml_obj->Serial_Number))
								{
									echo "\n"."\n"."Invalid Serial Number---->!".$xml_obj->Serial_Number."<----"."	$i"."\n"."\n";
									if(copy($one_log, $Invalid_sn_folder.'/'.basename($one_log)));
									{
										unlink($one_log);
		//								continue;
									}
								}
								else
								{
									if($inserted_id = $my_xml->log_insert_and_return_id($xml_obj))
									{
										$my_xml->sub_table_insert($inserted_id,$xml_obj);
										echo "log --->".$xml_obj->Serial_Number."<---"." inserted!"."\n";
									}
									else
									{
										echo $one_log."xxxxx"."\n";
									}
								}
					
							}
	//					echo	"	".$i;
						$i++;
						}
						else
						{
							$error_message= "xml file ".$one_log." parsing fail !";
		//					continue;
						}
					}	
				}
			}
		}
		catch(Exception $ex)
		{
        	//json_encode($e);
			$error_message=$ex->getMessage();
    	}
	}
	else
	{
		$error_message="invalid dir";
	}


?>