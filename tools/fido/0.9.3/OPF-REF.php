<?php

require_once('common_functions.inc.php');

function perform_scan($path,$tool_id) {
	$now = time();
	$report_file = "/tmp/fido_0.9.3_$now.txt";
	$cmd = "python fido/run.py $path > $report_file";
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
		
		$info["fido:scan_result"] = trim($parts[0]);
		if ($parts[0] == "OK") {
			$info["fido:puid"] = trim($parts[2]);
			$info["fido:formatname"] = trim($parts[3]);
			$info["fido:signaturename"] = trim($parts[4]);
			$uri = get_pronom_uri($info["fido:puid"]);
			if ($uri != "") {
				$info["droid:hasPronomURI"] = $uri;
			}	
		}
		$info["fido:filesize"] = trim($parts[5]);
		$file_path = trim($parts[6]);
		$file_path = substr($file_path,1,strlen($file_path));
		$file_path = substr($file_path,0,strlen($file_path)-1);	

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

		$query = "insert into raw_results set raw_result='$line';";
		$res = mysql_query($query);
		$raw_result_id = mysql_insert_id();
	
		$query = "insert into results set file_id=$file_id, tool_id=$tool_id, datestamp=".time().", raw_result_id=$raw_result_id;";
		$res = mysql_query($query);
		$result_id = mysql_insert_id();
		
		foreach ($info as $key => $value) {
			$query = "insert into triples set model='".$model."', subject='results/" . $result_id . "', predicate='".$key."', object='".htmlentities($value)."';";
			$res = mysql_query($query);
		}

	}
}

?>
