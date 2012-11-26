<?php

	$count = 0;
	ini_set('include_path','../');
	require_once('cfg/database_connector.php');
	require_once('../www/inc/bxmlio.inc.php');

	if (@($argv[1] === null)) {
		echo "No path specified:\n\nUsage:\n\tphp run_scan.php [/directory/of/files/ or /path/to/file.foo]\n\n";
		exit;
	}	

	$path = $argv[1];
	$tool_id = $argv[2];
	
	if (!(file_exists($path))) {
		echo "No file or directory @ $path\n\n";
		exit;
	}

	$tool_query = "SELECT id,name,version from tools order by id ASC;";
	$res = mysql_query($tool_query);
	
	if (mysql_num_rows($res) < 1) {
		echo "\n";
		echo "No tools are known by the system.\n";
		echo "Please install some tools and then run the import_tool script to update the database\n\n";
		exit;
	}
	$i=0;	
	while ($row = mysql_fetch_array($res)) {
		$ids[] = $row["id"];
		$i++;
	}
	
	if ($tool_id != "") {
		if (strtolower($tool_id) == "a") {
			foreach($ids as $tool_id) {
				$cmd = "php run_scan.php $path $tool_id";
				system($cmd,$ret);
			}
		} else {
			run_tool($tool_id,$path);
		}		
		exit();
	}
	
	echo "\n";
	echo "Please select a tool with which to do this scan:\n\n";

	$tool_query = "SELECT id,name,version from tools order by id ASC;";
	$res = mysql_query($tool_query);
	$i=0;	
	echo " A : All Tools\n";
	while ($row = mysql_fetch_array($res)) {
		echo " " . $row["id"] . " : " . $row["name"] . " (Version " . $row["version"] . ")\n";
		$ids[] = $row["id"];
		$i++;
	}
	echo "\n";
	echo "Selection: ";

	$handle = fopen ("php://stdin","r");
	$tool_id = trim(fgets($handle));

	if (strtolower($tool_id) == "a") {
		foreach($ids as $tool_id) {
			$cmd = "php run_scan.php $path $tool_id";
			system($cmd,$ret);
		}
	} else {
		run_tool($tool_id,$path);
	}		

function run_tool($tool_id, $path) {
	$tool_query = "select name,version,relative_path from tools where id=$tool_id;";
	$res = mysql_query($tool_query);
	if (mysql_num_rows($res) < 1) {
		echo "\n\nInput not valid!\n\n";
		exit;
	}

	$tool = mysql_fetch_array($res);
	$tool_inc = $tool["relative_path"] . "OPF-REF.php";

	echo "Running " . $tool["name"] . " (" . $tool["version"] . ")\n";
	
	require($tool_inc);

	$base_path = getcwd();
	$home_path = $base_path;
	$base_path = substr($base_path,0,strlen($base_path)-4) . "/";
	$new_path = $base_path . $tool["relative_path"];
	chdir($new_path);

	perform_scan($path,$tool_id);
	
	chdir($home_path);
	
	echo "DONE\n\n";
}
?>
