<?php
	error_reporting(E_ERROR | E_WARNING | E_PARSE);
	
	$i=1;
	$functions[$i]["function"] = "process_droid_4_results";
	$functions[$i]["description"] = "DROID 4.X results processor";
	$i++;
	
	ini_set('include_path','.:../:../lib/:../etc/');
        require_once('database_connector.php');
	
	$tool_query = "SELECT id,name,version from tools;";
	$res = mysql_query($tool_query);
	
	if (mysql_num_rows($res) < 1) {
		echo "\n";
		echo "No tools are known by the system.\n";
		echo "Please install some tools and then run the import_tool script to update the database\n\n";
		exit;
	}
	
	echo "\n";
	echo "Please select a tool which you wish to run post processing for:\n\n";

	$i=0;
	while ($row = mysql_fetch_array($res)) {
		echo " " . $row["id"] . " : " . $row["name"] . " (Version " . $row["version"] . ")\n";
		$i++;
	}
	echo "\n";
	echo "Selection [1-$i]: ";

	$handle = fopen ("php://stdin","r");
	$tool_id = trim(fgets($handle));

	$tool_query = "select name,version,relative_path,single_file_scan_command from tools where id=$tool_id;";
	$res = mysql_query($tool_query);
	if (mysql_num_rows($res) < 1) {
		echo "\n\nInput not valid!\n\n";
		exit;
	}

	$i=1;
	$functions[$i]["function"] = "process_droid_4_results";
	$functions[$i]["description"] = "DROID 4.X results processor";
	$i++;

	echo "\nPlease select a post processor:\n\n";
	for($i=1;$i<=count($functions);$i++) {
		echo $i . " : " . $functions[$i]["description"] . "\n";
	}
	echo "\nSelection [1-$i]: ";

	$handle = fopen ("php://stdin","r");
	$processor_id = trim(fgets($handle));
	
	if ($processor_id > $i) {
		echo "\n\nInput not valid!\n\n";
		exit;
	}
	
	$func = $functions[$processor_id]["function"];
	echo "\nProcessing...\n";
	$func($tool_id);
	echo "\nDone\n\n";

	function process_droid_4_results($tool_id) {
		
		$predicates[] = "hasHit";
		$predicates[] = "hasWarning";
		
		$query = "select id,raw_result_id from results where tool_id=$tool_id;";
		$res = mysql_query($query);

		while ($array = mysql_fetch_array($res)) {
				
			$result_id = $array["id"];				

			# DELETE ALL PROCESSED DATA ALREADY PRESENT 
			$query = 'select object from triples where subject="results/'.$result_id.'" and predicate="hasHit"';
			$res2 = mysql_query($query);

			while($array2 = @mysql_fetch_array($res2)) {
				$query = 'delete from triples where subject="'.$array2["object"].'";';
				$res3 = mysql_query($query) or die(mysql_error);
			}

			for($i=0;$i<count($predicates);$i++) {
				$query = 'delete from triples where subject="results/'.$result_id.'" and predicate="'.$predicates[$i].'"';
				$res3 = mysql_query($query) or die(mysql_error);
			}
			#TIDY COMPLETE

			#FETCH RAW RESULT AND PROCESS IT
	
			$query = "select raw_result from raw_results where id=" . $array["raw_result_id"].";";
			$res2 = mysql_query($query);
			
			$raw_result = mysql_fetch_array($res2);
			$raw_result_string = $raw_result["raw_result"];

			$lines = explode("\n",$raw_result_string);
			$marker = 0;
			$counter = 1;
			for ($i=0;$i<count($lines);$i++) {
				if (trim($lines[$i]) == "==================================") {
					$marker++;
				} elseif ($marker == 1) {
					$marker++;
				} elseif ($marker > 1) {
					$counter = droid_4_line_processor($result_id,$lines[$i],$counter);
				}
			}

		}		

	}

	function droid_4_line_processor($result_id,$line,$counter) {
		
		# Handle Warnings from results
		if (substr($line,0,strlen("WARNING:")) == "WARNING:") {
			$object = substr($line,strlen("WARNING: "),strlen($line));
			$query = "insert into triples set subject='results/" . $result_id . "', predicate='hasWarning', object='".$object."';";
			$res = mysql_query($query);
			return $counter;
		}
		
		# Remove anything in brackets () from the string
		while (strpos($line,"(") > 0) {
			$start = substr($line,0,strpos($line,"("));
			$end = substr($line,strpos($line,")")+2,strlen($line));
			$line = $start . $end;
		}
	
		# Work out the hit type, hit quality, file description, pronom puid and mime 
		# Example input: Tentative generic hit for MS-DOS Text File with line breaks  [PUID: x-fmt/130]  [MIME: text/plain]
		$bits = explode(" ",$line);

		$triples = null;

		$triples["hasQuality"] = $bits[0];
		$triples["hasType"] = $bits[1];
		
		$file_description = "";
		$break_flag = false;
		for ($i=4;$i<count($bits);$i++) {
		
			if (substr(trim($bits[$i]),0,1) == "[") {
				$break_flag = true;
			}
	
			if (!$break_flag) {
				$file_description .= $bits[$i] . " ";
			}
		}
		$triples["hasFileDescription"] = trim($file_description);
	
		$puid = substr($line,strpos($line,"[PUID: ")+strlen("[PUID: "),strlen($line));
		$puid = substr($puid,0,strpos($puid,"]"));
		$triples["hasPronomPUID"] = $puid;
		
		$mime = substr($line,strpos($line,"[MIME: ")+strlen("[MIME: "),strlen($line));
		$mime = substr($mime,0,strpos($mime,"]"));
		$triples["hasMime"] = $mime;

		$guid = guid();
		$guid = str_replace("{","",$guid);
		$guid = str_replace("}","",$guid);

		$hit_id = "hit/$guid";
		$query = "insert into triples set subject='results/" . $result_id . "', predicate='hasHit', object='".$hit_id."';";
		$res = mysql_query($query) or die(mysql_error());
		foreach ($triples as $predicate => $object) {
			if ($object == "") {
				$object = "unspecified";
			}
			$query = "insert into triples set subject='" . $hit_id . "', predicate='".$predicate."', object='".$object."';";
			$res = mysql_query($query) or die(mysql_error());
		}
		$counter++;

		return $counter;

	}

	function guid(){
		if (function_exists('com_create_guid')){
			return com_create_guid();
		}else{
			mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
			$charid = strtoupper(md5(uniqid(rand(), true)));
			$hyphen = chr(45);// "-"
			$uuid = chr(123)// "{"
				.substr($charid, 0, 8).$hyphen
				.substr($charid, 8, 4).$hyphen
				.substr($charid,12, 4).$hyphen
				.substr($charid,16, 4).$hyphen
				.substr($charid,20,12)
				.chr(125);// "}"
			return $uuid;
		}
	}


?>
