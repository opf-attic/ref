<?php

	ini_set('include_path','.:../:../lib/:../etc/');
	require_once('database_connector.php');

	if (@($argv[1] === null)) {
		echo "No path specified:\n\nUsage:\n\tphp import_tool.php /path/to/tool.info\n\n";
		exit;
	}	

	$file = $argv[1];

	if (!file_exists($file)) {
		echo "Could not find the specifiec tool info file\n";
		exit;
	}

	$handle = fopen($file,"r");
	if (!$handle) {
		echo "Could not open the specified input file (permissions?)\n";
		exit;
	}

	# Prepare the mysql query
	$check_query = "Select * from tools where ";
	$insert_query = "Insert into tools set ";

	while(!feof($handle)) {
		$line = fgets($handle,4096);
		if ($line != "") {
			$parts = explode(":",$line,2);
			$key = trim($parts[0]);
			$value = trim($parts[1]);

#realy should do some error checking here to see if the key is valid...

			$insert_query .= $key . '="'.$value.'",';
			if ($key == "name" || $key == "version" || $key == "signature_file") {
				$check_query .= $key . '="'.$value.'" and ';
			}
		}
	
	}
	fclose($handle);
	
	# Strip the last comma and add a semi-colon;
	$insert_query = substr($insert_query,0,strlen($insert_query)-1);
	$insert_query .= ";";
	
	$check_query = substr($check_query,0,strlen($check_query)-5);
	$check_query .= ";";

	$check_res = mysql_query($check_query);
	if (mysql_num_rows($check_res) > 0) {
		echo "A tool of that specification already exists in the database.\n";
		exit;
	}

	$res = mysql_query($insert_query);
	
	if ($res) {
		echo "Done\n";
	} else {
		echo "Tool insertion failed:\n" . mysql_error();
	}

?>
