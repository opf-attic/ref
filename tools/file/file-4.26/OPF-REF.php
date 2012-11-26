<?php

function perform_scan($path,$tool_id) {
	$now = time();
	
	$report_file1 = "/tmp/file1_$now.txt";
	$cmd = "./src/file -m magic/magic.mgc $path* > $report_file1";
	$ret = exec($cmd,$output);
	
	$report_file2 = "/tmp/file2_$now.txt";
	$cmd = "./src/file -m magic/magic.mgc -i -b $path* > $report_file2";
	$ret = exec($cmd,$output);
	
	$report_file = combine_files($report_file1,$report_file2);
	echo "SCAN COMPLETE, processing results. \n\n";
	process_results($path,$tool_id,$report_file);
}

function combine_files($file1,$file2) {
	if (!file_exists($file1) || !file_exists($file2)) {
		echo "NO FILE\n\n";
		exit();
	}
	$handle1 = fopen($file1,"r");
	$handle2 = fopen($file2,"r");
	$now = time();
	$report_file = "/tmp/file3_$now.txt";
	$handle3 = fopen($report_file,"w");
	while(!feof($handle1)) {
		$line = trim(fgets($handle1)) . ", " . trim(fgets($handle2)) . "\n";
		fwrite($handle3,$line);
	}
	fclose($handle1);
	fclose($handle2);
	fclose($handle3);
	unlink($file1);
	unlink($file2);
	return $report_file;
	
}
	
function process_results($path,$tool_id,$report_file) {
	
	$handle = fopen($report_file,"r");
	$count = 1;

	while (($line = fgets($handle, 4096)) !== false) {
		$line = trim($line);
		$mime = substr($line,strrpos($line,",")+1,strlen($line));
		$mime = trim($mime);
		$line = substr($line,0,strrpos($line,","));
		$parts = explode(": ",$line);
		$info = null;
		$info["hasMime"] = $mime;
		if (strpos($mime,";") > 0) {
			$partsx = explode(";",$mime);
			$info["hasMime"] = $partsx[0];
			$info["hasCharset"] = $partsx[1];
		}
		$partx2 = explode(" ",$mime);
		if (!$info["hasCharset"] && count($partx2) > 1) {
			$info["hasMime"] = $partx2[0];
			$info["hasCharset"] = $partx2[1];
		}
		for($i=0;$i<count($parts);$i++) {
			$value = "";
			$parts[$i] = trim($parts[$i]);
			$bits = explode(",",$parts[$i]);
			if ($i == 0) {
				$file_path = $bits[0];
				$key = "fileType";
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
		$rel_path = substr($file_path,strrpos($file_path,"govdocs_selected/"),strlen($file_path));
		if ($filename == "" || $filename == null || is_dir($rel_path)) {
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
		$query = "insert into raw_results set raw_result=\"$line\";";
		$res = mysql_query($query);
		$raw_result_id = mysql_insert_id();
	
#		$query = "insert into results set file_id=$file_id, tool_id=$tool_id, datestamp=".time().", raw_result_id=$raw_result_id;";
#		$res = mysql_query($query);
#		$result_id = mysql_insert_id();

		foreach ($info as $key => $value) {
			$key = strtolower($key);
			$key = str_replace(" ","_",$key);
			$key = str_replace("/","_",$key);
			$triples[$key] = htmlentities($value);
		}
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
			$query = "select * from triples where subject='" . $result_id . "' and predicate='file:".$predicate."' and object='".$object."';";
			$res = mysql_query($query);
			if (mysql_num_rows($res) < 1) {
				$query = "insert into triples set model='".$model."', subject='" . $result_id . "', predicate='file:".$predicate."', object='".$object."';";
				$res = mysql_query($query) or die(mysql_error());
			}
		}
	}
	unlink($report_file);
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
