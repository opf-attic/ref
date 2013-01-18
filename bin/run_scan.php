<?php
	error_reporting( E_ALL ^ E_NOTICE );

	$count = 0;
	ini_set('include_path','../');
	require_once('cfg/database_connector.php');
	require_once('inc/bxmlio.inc.php');
	require_once( 'inc/Thread.php' );

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
		$ids[$row["id"]] = $row["name"];
		$i++;
	}
	
	if ($tool_id != "") {
		if (strtolower($tool_id) == "a") {
			foreach($ids as $tool_id => $name) {
				if (strtolower($tool_id) != "a") {
					$cmd = "php run_scan.php $path $tool_id";
					$jobs[$name][] = $cmd; 
				}
			}
			process_jobs($jobs);
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
	}
	echo "\n";
	echo "Selection: ";

	$tool_id = "";
	$handle = fopen ("php://stdin","r");
	$tool_id = trim(fgets($handle));
	
	if (strtolower($tool_id) == "a") {
		foreach($ids as $tool_id => $name) {
			if (strtolower($tool_id) != "a") {
				$cmd = "php run_scan.php $path $tool_id";
				$jobs[$name][] = $cmd; 
			}
		}
		process_jobs($jobs);
	} else {
		run_tool($tool_id,$path);
	}		

function process_jobs($jobs) {
	if( ! Thread::available() ) {
		foreach ($jobs as $name => $array) {
			for($i=0;$i<count($array);$i++) {
				system($array[$i],$ret);
			}
		}
	} else {
		use_threads($jobs);
	}
}

function paralel( $_cmd , $_thread_name ) {
        if ($_cmd == "init" || $_cmd == "" ) {
                return;
        }
        system($_cmd,$ret);
}

function use_threads($jobs) {
	$total_count = 0;
	foreach($jobs as $name => $array) {
		${"t$name"} = new Thread( 'paralel' );	
		${"t$name"}->start('init');
		$total_count += count($array);
	}
	while (count($completed) < count($jobs)) {
		$handle = fopen("threads","w");
		fwrite($handle,time() . "\n");
		foreach($jobs as $name => $array) {
			if ($completed[$name]) {
				fwrite($handle,"t$name : Done\n");
				continue;
			}
			if (!${"t$name"}->isAlive()) {
				fwrite($handle,"t$name : Available\n");
				$job = array_shift($array);
				if ($job == "") {
					$completed[$name] = true;
				} else {
					${"t$name"}->start($job, $name);
					$jobs[$name] = $array;
				}
			} else {
				fwrite($handle,"t$name : Busy\n");
			}
		}
		fclose($handle);
		sleep(2);
	}

	$still_active = true;

	while ($still_active) {
		$still_active = false;
		foreach($jobs as $name => $array) {
			if (${"t$name"}->isAlive()) {
				$still_active = true;
			}
		}
	}
	
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
	
	echo "DONE - " . $tool["name"] . " (" . $tool["version"] . ")\n";
}
?>
