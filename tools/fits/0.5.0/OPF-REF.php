<?php

function perform_scan($path,$tool_id) {
	if (substr($path,strlen($path)-1,strlen($path)) != "/") {
		$path = $path . "/";
	}
	if (is_dir($path)) {
		if ($handle = opendir($path)) {
			while (false !== ($entry = readdir($handle))) {
				if ($entry != "." && $entry != ".." && is_file($path . $entry)) {
					scan_file($path . $entry,$tool_id);
				}
			}
			closedir($handle);
		}
	} else {
		scan_file($path,$tool_id);
	}
}

function scan_file($path,$tool_id) {
	$now = time();
	$report_file = "/tmp/fits_$now.txt";
	$cmd = "./fits.sh -i $path -o $report_file";
	$ret = exec($cmd,$output);
	process_result($path,$tool_id,$report_file);
	unlink($report_file);
}

function process_result($path,$tool_id,$report_file) {
	
	$root = bxmlio_load($report_file);
	
	$identification = bxmlio_find($root,"identification");

	$identity = bxmlio_find($identification,"identity");
	
	$attributes = $identity["attributes"];
	foreach ($attributes as $key => $value) {
		if ($key == "toolname") continue;
		if ($key == "toolversion") continue;
		$triples[$key] = $value;
	}
	
	$children = $identity["children"];
	for($i=0;$i<count($children);$i++) {
		$child = $children[$i];
		$name = $child["name"];
		$value = $child["cdata"];
		$attributes = $child["attributes"];
	
		if ($name == "tool") continue;
	
		$triples[$name] = $value;
		
		foreach ($attributes as $key => $value) {
			if ($key == "toolname") continue;
			if ($key == "toolversion") continue;
			$triples[$key] = $value;
		}
	
	}
	
	$filename = substr($path,strrpos($path,"/")+1,strlen($path));
	$rel_path = substr($path,strrpos($path,"govdocs_selected/"),strlen($path));
	
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
	
	$string = "";
	foreach ($triples as $predicate => $object) {
		$string .= $predicate . ":" . $object . ", ";
	}
	$string = substr($string,0,strlen($string)-2);
	$key = md5($string);

	$query = "insert into results set id=\"$key\", file_id=$file_id, tool_id=$tool_id, datestamp=".time().", raw_result_id=$raw_result_id;";
	$res = mysql_query($query);
	$result_id = "result/$key";

	foreach ($triples as $predicate => $object) {
		$predicate = "fits:" . $predicate;
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
