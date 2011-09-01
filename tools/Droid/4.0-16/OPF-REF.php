<?php

function perform_scan($path,$tool_id) {
	$now = time();
	$report_file = "/tmp/droid4.0-16_$now.txt";
	$cmd = "java -jar droid.jar -l $path > $report_file";
	$ret = exec($cmd,$output);
	echo "SCAN COMPLETE, processing results. \n\n";
	process_results($path,$tool_id,$report_file);
}
	
function process_results($path,$tool_id,$report_file) {
	
	$handle = fopen($report_file,"r");
	$identifying = false;
	$result = false;
	$count = 1;
	while (($line = fgets($handle, 4096)) !== false) {
		if (trim($line) == "==================================") {
			if ($result) {
				echo "processing result : " . $count . "\n";
				process_result($path,$tool_id,$result);
				$count++;
			}
			$result = null;
			$result["filename"] = trim(fgets($handle, 4096));
			$identifying = true;
		} elseif ($identifying) {
			$result["reading"][] = trim($line);
		}
	}
	
	echo "processing result : " . $count . "\n";
	process_result($path,$tool_id,$result);
	$count++;
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

	$model = "tool_" . $tool_id . "_file_" . $file_id;

	clean_up($model,$tool_id,$file_id);

	$query = "insert into raw_results set raw_result=\"$raw_result\";";
	$res = mysql_query($query);
	$raw_result_id = mysql_insert_id();

	$query = "insert into results set file_id=$file_id, tool_id=$tool_id, datestamp=".time().", raw_result_id=$raw_result_id;";
	$res = mysql_query($query);
	$result_id = mysql_insert_id();

	process_droid_4_results($model,$result_id,$raw_result_id);

}

function clean_up($model,$tool_id,$file_id) {
	$query = "select id,raw_result_id from results where tool_id=$tool_id and file_id=$file_id;";
	$res = mysql_query($query);

	$array = mysql_fetch_array($res);

	$result_id = $array["id"];
	$query = 'delete from raw_results where id='.$result_id.';';				
	$res2 = mysql_query($query);
	$query = "delete from results where tool_id=$tool_id and file_id=$file_id;";				
	$res2 = mysql_query($query);
# DELETE ALL PROCESSED DATA ALREADY PRESENT 
	$query = 'delete from triples where model="'.$model.'"';
	$res2 = mysql_query($query);


}

function process_droid_4_results($model,$result_id,$raw_result_id) {

#FETCH RAW RESULT AND PROCESS IT
	$query = "select raw_result from raw_results where id=" . $raw_result_id .";";
	$res2 = mysql_query($query);

	$raw_result = mysql_fetch_array($res2);
	$raw_result_string = $raw_result["raw_result"];

	$lines = explode("\n",$raw_result_string);
	$marker = 0;
	$counter = 1;
	for ($i=0;$i<count($lines);$i++) {
		$counter = droid_4_line_processor($model,$result_id,$lines[$i],$counter);
	}


}

function droid_4_line_processor($model,$result_id,$line,$counter) {

# Handle Warnings from results
	if (substr($line,0,strlen("WARNING:")) == "WARNING:") {
		$object = substr($line,strlen("WARNING: "),strlen($line));
		$query = "insert into triples set model='".$model."', subject='results/" . $result_id . "', predicate='droid:hasWarning', object='".$object."';";
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

		$uri = get_pronom_uri($puid);
		if ($uri != "") {
			$triples["hasPronomURI"] = $uri;
		}	
	
		$mime = substr($line,strpos($line,"[MIME: ")+strlen("[MIME: "),strlen($line));
		$mime = substr($mime,0,strpos($mime,"]"));
		$triples["hasMime"] = $mime;

	}

	$guid = guid();
	$guid = str_replace("{","",$guid);
	$guid = str_replace("}","",$guid);

	$hit_id = "hit/$guid";
	$query = "insert into triples set model='".$model."', subject='results/" . $result_id . "', predicate='droid:hasHit', object='".$hit_id."';";
	$res = mysql_query($query) or die(mysql_error());
	foreach ($triples as $predicate => $object) {
		if ($object == "") {
			$object = "unspecified";
		}
		$query = "insert into triples set model='".$model."', subject='" . $hit_id . "', predicate='droid:".$predicate."', object='".$object."';";
		$res = mysql_query($query) or die(mysql_error());
	}
	$counter++;

	return $counter;

}

function get_pronom_uri($format) {
	global $format_mappings;
	if (@$format_mappings[$format] != "") {
		return $format_mappings[$format];
	}
	$url = "http://test.linkeddatapronom.nationalarchives.gov.uk/sparql/endpoint.php?query=";
	
	$predicate = "http://reference.data.gov.uk/technical-registry/";

	if (substr($format,0,1) == "x") {
		$predicate .= "XPUID";
	} else {
		$predicate .= "PUID";
		$format = substr($format,4,strlen($format));
		$url = "http://reference.data.gov.uk/id/file-format/" . $format;
		return $url; 
	}
	
	$query = 'SELECT ?s WHERE { ?s <'.$predicate.'> "'.$format.'" }';
	$url = $url . urlencode($query);
	
	$handle = fopen($url,"r");
	while ($line = fgets($handle,4096)) {
		$line = trim($line);
		if (substr($line,0,5) == "<uri>") {
			$uri = str_replace("<uri>","",$line);
			$uri = str_replace("</uri>","",$uri);
		}
	}
	fclose($handle);
	$format_mappings[$format] = $uri;
	return $uri;
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
