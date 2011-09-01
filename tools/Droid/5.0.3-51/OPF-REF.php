<?php

function perform_scan($path,$tool_id) {
	$now = time();
	$report_file1 = "/tmp/droid5.0.3-51_$now.droid";
	$report_file2 = "/tmp/droid5.0.3-51_$now.csv";
	
	$cmd = 'java -jar droid-command-line-5.0.3.jar -a "'.$path.'" -p '.$report_file1;
	echo $cmd;
	exit(0);
	$ret = exec($cmd,$output);
	
	$cmd = "java -jar droid-command-line-5.0.3.jar -p $report_file1 -e $report_file2";
	$ret = exec($cmd,$output);

	unlink($report_file1);
	exit;
	process_results($path,$tool_id,$report_file2);
	unlink($report_file2);
}
	
function process_results($path,$tool_id,$report_file) {
	
	$handle = fopen($report_file,"r");
	$identifying = false;
	$result = false;
	while (($line = fgets($handle, 4096)) !== false) {
		if (trim($line) == "==================================") {
			if ($result) {
				process_result($path,$tool_id,$result);
			}
			$result = null;
			$result["filename"] = trim(fgets($handle, 4096));
			$identifying = true;
		} elseif ($identifying) {
			$result["reading"][] = trim($line);
		}
	}
	
	process_result($path,$tool_id,$result);
}

function process_result($path,$tool_id,$result) {

	$file = $result["filename"];

	$filename = substr($file,strrpos($file,"/")+1,strlen($file));
	
	$rel_path = substr($file,strrpos($file,"data/"),strlen($file));

	$query = "select id from files where name=\"$filename\" and relative_full_path=\"$rel_path\";";
	$res = mysql_query($query) or die(mysql_error());
	if (@mysql_num_rows($res) < 1) {
		$query = "insert into files set name=\"$filename\",relative_full_path=\"$rel_path\";";
		$res = mysql_query($query);
		$file_id = mysql_insert_id();
	} else {
		$row = mysql_fetch_array($res);
		$file_id = $row["id"];
	}

	$raw_result = "";
	if (!(@$result["reading"])) {
		$raw_result = "No Result";
	} else {
		for ($i=0;$i<count($result["reading"]);$i++) {
			$line = trim($result["reading"][$i]);
			if ($line != "" and $line != null) {
				$raw_result .= $line . "\n";
			}
		}
	}
	
	$raw_result = trim($raw_result);

	$query = "insert into raw_results set raw_result=\"$raw_result\";";
	$res = mysql_query($query);
	$raw_result_id = mysql_insert_id();

	$query = "insert into results set file_id=$file_id, tool_id=$tool_id, datestamp=".time().", raw_result_id=$raw_result_id;";
	$res = mysql_query($query);
	$result_id = mysql_insert_id();

	process_droid_4_results($tool_id);

}

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
			$res3 = mysql_query($query) or die(mysql_error());
		}

		for($i=0;$i<count($predicates);$i++) {
			$query = 'delete from triples where subject="results/'.$result_id.'" and predicate="'.$predicates[$i].'"';
			$res3 = mysql_query($query) or die(mysql_error());
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
			$counter = droid_4_line_processor($result_id,$lines[$i],$counter);
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
	$triples = null;
	
	if ($line == "No Result") {
		$triples["hasMatch"] = "false";
	} else { 
		$triples["hasMatch"] = "true";

		$bits = explode(" ",$line);


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

	}

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
