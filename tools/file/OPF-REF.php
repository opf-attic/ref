<?php

function perform_scan($path,$tool_id) {
	$now = time();
	$report_file = "/tmp/file_$now.txt";
	$cmd = "file $path* > $report_file";
	$ret = exec($cmd,$output);
	echo "SCAN COMPLETE, processing results. \n\n";
	process_results($path,$tool_id,$report_file);
}
	
function process_results($path,$tool_id,$report_file) {
	
	$handle = fopen($report_file,"r");
	$count = 1;

	while (($line = fgets($handle, 4096)) !== false) {
		$parts = explode(": ",$line);
		$info = null;
		for($i=0;$i<count($parts);$i++) {
			$value = "";
			$parts[$i] = trim($parts[$i]);
			$bits = explode(",",$parts[$i]);
			if ($i == 0) {
				$file_path = $bits[0];
				$key = "file_type";
			} 
			$size = count($bits);
			if ($i > 0 && $size > 1) {
				if ($i != count($parts)-1) {
					$size--;
				}
				for ($j=0;$j<$size;$j++) {
					$value .= trim($bits[$j]) . ", ";
				}
				$value = substr($value,0,-2);
				$info[$key] = $value;
				@($key = trim($bits[$size]));
			} elseif ($i > 0) {
				$value = trim($bits[0]);
				$info[$key] = $value;
			}
		}
		$count++;
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
		$query = "insert into raw_results set raw_result=\"$line\";";
		$res = mysql_query($query);
		$raw_result_id = mysql_insert_id();
	
		$query = "insert into results set file_id=$file_id, tool_id=$tool_id, datestamp=".time().", raw_result_id=$raw_result_id;";
		$res = mysql_query($query);
		$result_id = mysql_insert_id();

		foreach ($info as $key => $value) {
			$key = strtolower($key);
			$key = str_replace(" ","_",$key);
			$key = str_replace("/","_",$key);
			$query = "insert into triples set model='".$model."', subject='results/" . $result_id . "', predicate='file:".$key."', object='".htmlentities($value)."';";
			$res = mysql_query($query);
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

?>
