<?php

function perform_scan($path,$tool_id) {
	$now = time();
	$report_file1 = "/tmp/droid5.0.3-43_$now.droid";
	$report_file2 = "/tmp/droid5.0.3-43_$now.csv";

	require_once('../java_path.php');
	$java_path = get_java_path_droid_5();
	
	$cmd = $java_path . 'java -jar droid-command-line-5.0.3.jar -s 43 2>/dev/null >/dev/null';
	$cmd .= ' && ' . $java_path . 'java -jar droid-command-line-5.0.3.jar -a "'.$path.'" -p '.$report_file1 . ' 2>/dev/null';	
	$cmd .= ' && ' . $java_path . "java -jar droid-command-line-5.0.3.jar -p $report_file1 -e $report_file2" . ' 2>/dev/null'; 
	$ret = exec($cmd,$output);

	unlink($report_file1);
	process_results($path,$tool_id,$report_file2);
	unlink($report_file2);
}
	
function process_results($path,$tool_id,$report_file) {
	
	$handle = fopen($report_file,"r");
	$identifying = false;
	$result = false;
	while (($line = fgets($handle, 4096)) !== false) {
		$parts = explode('","',$line);
		$file = substr($parts[0],6,strlen($parts[0]));
		if (is_file($file)) {
			process_result($path,$tool_id,$line);
		}
	}	
}

function process_result($path,$tool_id,$line) {

	$parts = explode(',',$line);

	for($i=0;$i<count($parts);$i++) {
		$thing = trim($parts[$i]);
		if (substr($thing,strlen($thing)-1,strlen($thing)) == '"') {
			$thing = substr($thing,0,strlen($thing)-1);
		}
		if (substr($thing,0,1) == '"') {
			$thing = substr($thing,1,strlen($thing));
		}
		$out[$i] = $thing;
	}
	$parts = $out;
	
	$result = "";
	$result["full_file_path"] = $parts[1];
	$result["filename"] = $parts[2];
	$triples["matchMethod"] = $parts[3];
	$triples["reading"] = $parts[4];
	$result["filesize"] = $parts[5];
	$result["ext"] = $parts[7];
	$result["last_modified"] = $parts[8];
	$triples["hasPronomPUID"] = $parts[9];
	$triples["hasMime"] = $parts[10];
	$triples["hasFileDescription"] = $parts[11];
	$triples["hasFileVersion"] = $parts[12];

	$filename = $result["filename"];

	$file = $result["full_file_path"];
	$rel_path = substr($file,strrpos($file,"govdocs_selected/"),strlen($file));
	
	if (is_dir($file) || $filename == "" || $filename == null) {
		continue;
	}

	$query = "select id from files where name=\"$filename\" and relative_full_path=\"$rel_path\";";
	$res = mysql_query($query) or die($query);
	if (@mysql_num_rows($res) < 1) {
		$query = "insert into files set name=\"$filename\",relative_full_path=\"$rel_path\";";
		$res = mysql_query($query) or die($query);
		$file_id = mysql_insert_id();
	} else {
		$row = mysql_fetch_array($res);
		$file_id = $row["id"];
	}

	$raw_result = trim($line);

	$query = "insert into raw_results set raw_result='".addslashes($raw_result)."';";
	$res = mysql_query($query) or die($query);
	$raw_result_id = mysql_insert_id();
	
	$model = "tool_" . $tool_id . "_file_" . $file_id;

#	if ($triples["hasPronomPUID"] != "") {
#		$uri = get_pronom_uri($triples["hasPronomPUID"]);
#	}
#	if ($uri != "") {
#		$triples["hasPronomURI"] = $uri;
#	}	
	
	$string = "";
	foreach ($triples as $predicate => $object) {
		if ($object != "") {
			$string .= $predicate . ":" . $object . ", ";
		}
	}
	$string = substr($string,0,strlen($string)-2);
	$key = md5($string);
	
	$query = "insert into results set id=\"$key\", file_id=$file_id, tool_id=$tool_id, datestamp=".time().", raw_result_id=$raw_result_id;";
	$res = mysql_query($query) or die($query);

	$result_id = "result/$key";
	#$query = "insert into triples set model='".$model."', subject='file/" . $file_id . "', predicate='opf_ref:hasResult', object='".$result_id."';";
	#$res = mysql_query($query) or die(mysql_error());
	foreach ($triples as $predicate => $object) {
		if ($object == "") {
			$object = "unspecified";
		} else {
			$query = "select * from triples where subject='" . $result_id . "' and predicate='droid:".$predicate."' and object='".$object."';";
			$res = mysql_query($query) or die($query);
			if (mysql_num_rows($res) < 1) {
				$query = "insert into triples set model='".$model."', subject='" . $result_id . "', predicate='droid:".$predicate."', object='".$object."';";
				$res = mysql_query($query) or die(mysql_error());
			}
		}
	}

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

?>
