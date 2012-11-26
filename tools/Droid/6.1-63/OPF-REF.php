<?php

error_reporting(E_ALL ^ E_NOTICE);

function perform_scan($path,$tool_id) {
	$version = 63;
	$now = time();
	$report_file1 = "/tmp/droid6.1-$version_$now.droid";
	$report_file2 = "/tmp/droid6.1-$version_$now.csv";
	
	require_once('../java_path.php');
	$java_path = get_java_path_droid_6_0();
	$droid_profile_path = get_droid_profile_path_6_0();
	
	$container_file = get_container_file($droid_profile_path . "container_sigs");

	$handle = fopen($droid_profile_path . "droid.properties","r");
	if ($handle) {
		$string = "";
		while(!feof($handle)) {
			$line = trim(fgets($handle));
			if (substr($line,0,strlen("profile.defaultBinarySigFileVersion")) == "profile.defaultBinarySigFileVersion") {
				$line = "profile.defaultBinarySigFileVersion = DROID_SignatureFile_V$version";
			} 
			if (substr($line,0,strlen("profile.defaultContainerSigFileVersion")) == "profile.defaultContainerSigFileVersion") {
				$line = "profile.defaultContainerSigFileVersion = $container_file";
			}
			$string .= $line . "\n";
		}
		$handle = fopen($droid_profile_path . "droid.properties","w");
		fwrite($handle,$string);
		fclose($handle);
	}
	
	$cmd = $java_path . 'java -jar droid-command-line-6.1.jar -x';
        $ret = exec($cmd,$output);
	
	$cmd = $java_path . 'java -jar droid-command-line-6.1.jar -a "'.$path.'" -p '.$report_file1;
	$ret = exec($cmd,$output);
	
	$cmd = $java_path . "java -jar droid-command-line-6.1.jar -p $report_file1 -e $report_file2";
	$ret = exec($cmd,$output);

	unlink($report_file1);
	process_results($path,$tool_id,$report_file2);
	unlink($report_file2);
}

function get_container_file($container_path) {
	$handle = fopen("INFO","r");
	if (!$handle) {
		echo "error\n";
		return;
	}
	while(!feof($handle)) {
		$line = trim(fgets($handle));
		if (substr($line,0,4) == "date") {
			$date_string = substr($line,6,strlen($line));
		}
	}
	fclose($handle);
	$day = substr($date_string,0,2);
	$month = substr($date_string,3,2);
	$year = substr($date_string,-4);
	$compare = $year . $month . $day;

	$file = "";
	if ($handle = opendir($container_path)) {
		while (false !== ($entry = readdir($handle))) {
			if ($entry != "." && $entry != "..") {
				$date = substr($entry,0,strrpos($entry,".xml"));
				$date = substr($date,strrpos($date,"-")+1,strlen($date));
				echo "Comparing $date to $compare \n";
				if ($date <= $compare) {
					$file = $entry;
				}
			}
		}
		closedir($handle);
	}
	return substr($file,0,strrpos($file,".xml"));;
}

function process_results($path,$tool_id,$report_file) {
	
	$handle = fopen($report_file,"r");
	
	//"ID","PARENT_ID","URI","FILE_PATH","NAME","METHOD","STATUS","SIZE","TYPE","EXT","LAST_MODIFIED","EXTENSION_MISMATCH","MD5_HASH","FORMAT_COUNT","PUID","MIME_TYPE","FORMAT_NAME","FORMAT_VERSION"
	$count = 0;
	while (($line = fgets($handle, 4096)) !== false) {
		$line = str_replace('",,"','","","',$line);
		$line = trim($line);
		$line = substr($line,1,strlen($line)-2);
		$parts = explode('","',$line);
		for($i=0;$i<count($parts);$i++) {
			$parts[$i] = trim($parts[$i]);
		//	$parts[$i] = substr($parts[$i],1,strlen($parts[$i])-2);
		}
		if ($count == 0) {
			for($i=0;$i<count($parts);$i++) {
				$predicates[] = strtolower($parts[$i]);
			}
		} else {
			$info[$parts[3]][$count]["raw_result"] = $line;
			for($i=0;$i<14;$i++) {
				@$info[$parts[3]][$count][$predicates[$i]] = $parts[$i];
			}
			$format_count = $parts[13];
			for($i=14;$i<count($parts);$i++) {
				$guid = guid();
				$guid = str_replace("{","",$guid);
				$guid = str_replace("}","",$guid);
				$hit = "hit/" . $guid;
				@$info[$parts[3]][$count]["hit"][$hit]["puid"] = $parts[$i];
				$i++;
				@$info[$parts[3]][$count]["hit"][$hit]["mime_type"] = $parts[$i];
				$i++;
				@$info[$parts[3]][$count]["hit"][$hit]["format_type"] = $parts[$i];
				$i++;
				@$info[$parts[3]][$count]["hit"][$hit]["format_version"] = $parts[$i];
			}
		}
		$count++;
	}
	fclose($handle);

	foreach ($info as $file_path => $count) {
		$filename = substr($file_path,strrpos($file_path,"/")+1,strlen($file_path));
		$rel_path = substr($file_path,strrpos($file_path,"govdocs_selected/"),strlen($file_path));
		if (is_dir($file_path) || $filename == "" || $filename == null) {
			continue;
		}
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

		foreach ($count as $array) {
			process_result($model,$tool_id,$file_id,$array);
		}
	}

}	

function process_result($model,$tool_id,$file_id,$array) {

	$raw_result = $array["raw_result"];
	$query = "insert into raw_results set raw_result='$raw_result';" or die(mysql_error());
	$res = mysql_query($query);
	$raw_result_id = mysql_insert_id();

#	$query = "insert into results set file_id=$file_id, tool_id=$tool_id, datestamp=".time().", raw_result_id=$raw_result_id;";
#	$res = mysql_query($query);
#	$result_id = mysql_insert_id();	
	
	$hits = $array["hit"];
	foreach ($hits as $hit) {
#		if ($array["puid"] != "") {
#			$uri = get_pronom_uri($array["puid"]);
#		}
#		if ($uri != "") {
#			$array["hasPronomURI"] = $uri;
#		}
		foreach ($hit as $key => $value) {
			if ($value != "") {
				$triples[$key] = $value;
			}
		}
		$triples["method"] = $array["method"];
		$triples["ext"] = $array["ext"];
		$triples["extension_mismatch"] = $array["extension_mismatch"];
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
/*
	foreach($array as $predicate => $object) {
		if ($predicate != "raw_result" && $predicate != "hit") {	

			$query = "insert into triples set model='".$model."', subject='results/" . $result_id . "', predicate='droid:".$predicate."', object='".$object."';";
			$res = mysql_query($query) or die(mysql_error());
		}
	}
	foreach($array["hit"] as $key => $array2) {
		$predicate = "hasHit";
		$query = "insert into triples set model='".$model."', subject='results/" . $result_id . "', predicate='droid:".$predicate."', object='".$key."';";
		$res = mysql_query($query) or die(mysql_error());
		foreach ($array2 as $predicate => $object) {
			$query = "insert into triples set model='".$model."', subject='" . $key . "', predicate='droid:".$predicate."', object='".$object."';";
			$res = mysql_query($query) or die(mysql_error());
		}
	}
*/
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
