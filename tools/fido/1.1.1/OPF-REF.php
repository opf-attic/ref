<?php

function perform_scan($path,$tool_id) {
	$now = time();
	$report_file = "/tmp/fido_1.1.1_$now.txt";
	$cmd = "python fido/fido.py $path > $report_file";
	$ret = exec($cmd,$output);
	echo "SCAN COMPLETE, processing results. \n\n";
	process_results($path,$tool_id,$report_file);
}

function process_results($path,$tool_id,$report_file) {
	
	$handle = fopen($report_file,"r");
	$count = 1;

	while (($line = fgets($handle, 4096)) !== false) {
		$parts = explode(",",$line);
		$info = null;
		
		$info["fido:scan_result"] = remove_quotes(trim($parts[0]));
		if ($parts[0] == "OK") {
			$info["fido:puid"] = remove_quotes(trim($parts[2]));
			$info["fido:formatname"] = remove_quotes(trim($parts[3]));
			$info["fido:signaturename"] = remove_quotes(trim($parts[4]));
#			$uri = get_pronom_uri($info["fido:puid"]);
#			if ($uri != "") {
#				$info["droid:hasPronomURI"] = $uri;
#			}	
		}
		#$info["fido:filesize"] = trim($parts[5]);
		$file_path = trim($parts[6]);
		$file_path = substr($file_path,1,strlen($file_path));
		$file_path = substr($file_path,0,strlen($file_path)-1);	

		$filename = substr($file_path,strrpos($file_path,"/")+1,strlen($file_path));
		$rel_path = substr($file_path,strrpos($file_path,"govdocs_selected/"),strlen($file_path));
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
		$model = "tool_" . $tool_id . "_file_" . $file_id;
		clean_up($model,$tool_id,$file_id);

		$query = "insert into raw_results set raw_result='$line';";
		$res = mysql_query($query);
		$raw_result_id = mysql_insert_id();
/*	
		$query = "insert into results set file_id=$file_id, tool_id=$tool_id, datestamp=".time().", raw_result_id=$raw_result_id;";
		$res = mysql_query($query);
		$result_id = mysql_insert_id();
		
		foreach ($info as $key => $value) {
			$query = "insert into triples set model='".$model."', subject='results/" . $result_id . "', predicate='".$key."', object='".htmlentities($value)."';";
			$res = mysql_query($query);
		}
*/
		$triples = $info;
		$string = "";
		foreach ($triples as $predicate => $object) {
			$string .= $predicate . ":" . $object . ", ";
		}
		$string = substr($string,0,strlen($string)-2);
		$key = md5($string);

		$query = "insert into results set id=\"$key\", file_id=$file_id, tool_id=$tool_id, datestamp=".time().", raw_result_id=$raw_result_id;";
		$res = mysql_query($query);

		$result_id = "result/$key";
#$query = "insert into triples set model='".$model."', subject='file/" . $file_id . "', predicate='opf_ref:hasResult', object='".$result_id."';";
#$res = mysql_query($query) or die(mysql_error());
		foreach ($triples as $predicate => $object) {
			if ($object == "") {
				$object = "unspecified";
			}
			$query = "select * from triples where subject='" . $result_id . "' and predicate='".$predicate."' and object='".$object."';";
			$res = mysql_query($query);
			if (mysql_num_rows($res) < 1) {
				$query = "insert into triples set model='".$model."', subject='" . $result_id . "', predicate='".$predicate."', object='".$object."';";
				$res = mysql_query($query) or die(mysql_error());
			}
		}

	}
}

function remove_quotes($string) {
	if (substr($string,0,1) == '"' and substr($string,strlen($string)-1,strlen($string)) == '"') {
		$string = substr($string,1,strlen($string)-2);
#		$string = substr($string,strlen($string)-1,strlen($string));
	}
	return $string;
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
