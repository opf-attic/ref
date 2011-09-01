<?php

require_once('common_functions.inc.php');

function perform_scan($path,$tool_id) {
	$now = time();
	$report_file1 = "/tmp/droid6.0.1-50_$now.droid";
	$report_file2 = "/tmp/droid6.0.1-50_$now.csv";
	
	$cmd = 'java -jar droid-command-line-6.0.jar -a "'.$path.'" -p '.$report_file1;
	$ret = exec($cmd,$output);
	
	$cmd = "java -jar droid-command-line-6.0.jar -p $report_file1 -e $report_file2";
	$ret = exec($cmd,$output);

	unlink($report_file1);
	process_results($path,$tool_id,$report_file2);
	unlink($report_file2);
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
		$rel_path = substr($file_path,strrpos($file_path,"data/"),strlen($file_path));
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

	$query = "insert into results set file_id=$file_id, tool_id=$tool_id, datestamp=".time().", raw_result_id=$raw_result_id;";
	$res = mysql_query($query);
	$result_id = mysql_insert_id();	

	if (@$array["puid"] != "") {
		$uri = get_pronom_uri($array["puid"]);
		if ($uri != "") {
			$array["hasPronomURI"] = $uri;
		}	
	}
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

}

?>
