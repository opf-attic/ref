<?php

for ($i=63;$i<80;$i++) {
	make_droid_61_version($i);
}

function make_droid_61_version($number) {
	if (!file_exists("/home/davetaz/.droid6/signature_files/DROID_SignatureFile_V$number.xml")) {
		return;
	}
	$cwd = getcwd();
	$newdir = "6.1-$number";
	$cmd = "rm -fR $newdir";
	exec($cmd);
	$cmd = "cp -r 6.1-62 $newdir";
	exec($cmd);
	$contents = file_get_contents($newdir . "/OPF-REF.php");
	$contents = str_replace('$version = 62','$version = ' . $number,$contents);
	$handle = fopen($newdir . "/OPF-REF.php","w");
	fwrite($handle,$contents);
	fclose($handle);
	$handle = fopen("/home/davetaz/.droid6/signature_files/DROID_SignatureFile_V$number.xml","r");	
	$str = fgets($handle);
	$date = substr($str,strpos($str,'DateCreated="')+13,19);
	while (!is_numeric(substr($date,0,1)) || $date == "") {
		$str = fgets($handle);
		$date = substr($str,strpos($str,'DateCreated="')+13,19);
	}
	make_info_file($newdir,$date);
}


function make_droid_6_version($number) {
	if (!file_exists("/home/davetaz/.droid6/signature_files/DROID_SignatureFile_V$number.xml")) {
		return;
	}
	$cwd = getcwd();
	$newdir = "6.0.1-$number";
	$cmd = "rm -fR $newdir";
	exec($cmd);
	$cmd = "cp -r 6.0.1-46 $newdir";
	exec($cmd);
	$contents = file_get_contents($newdir . "/OPF-REF.php");
	$contents = str_replace('$version = 46','$version = ' . $number,$contents);
	$handle = fopen($newdir . "/OPF-REF.php","w");
	fwrite($handle,$contents);
	fclose($handle);
	$handle = fopen("/home/davetaz/.droid6/signature_files/DROID_SignatureFile_V$number.xml","r");	
	$str = fgets($handle);
	$date = substr($str,strpos($str,'DateCreated="')+13,19);
	while (!is_numeric(substr($date,0,1)) || $date == "") {
		$str = fgets($handle);
		$date = substr($str,strpos($str,'DateCreated="')+13,19);
	}
	make_info_file($newdir,$date);
}

function make_droid_5_version($number) {
	if (!file_exists("/home/davetaz/.droid/signature_files/DROID_SignatureFile_V$number.xml")) {
		return;
	}
	$cwd = getcwd();
	$newdir = "5.0.3-$number";
	$cmd = "rm -fR $newdir";
	exec($cmd);
	$cmd = "cp -r 5.0.3-32 $newdir";
	exec($cmd);
	$contents = file_get_contents($newdir . "/OPF-REF.php");
	$contents = str_replace("java -jar droid-command-line-5.0.3.jar -s 32","java -jar droid-command-line-5.0.3.jar -s $number",$contents);
	$contents = str_replace("droid5.0.3-32","droid5.0.3-$number",$contents);
	$handle = fopen($newdir . "/OPF-REF.php","w");
	fwrite($handle,$contents);
	fclose($handle);
	$handle = fopen("/home/davetaz/.droid/signature_files/DROID_SignatureFile_V$number.xml","r");	
	$str = fgets($handle);
	$date = substr($str,strpos($str,'DateCreated="')+13,19);
	if ($date == "") {
		$str = fgets($handle);
#	$str = html_entity_decode(iconv('UTF-16','UTF-8',$str),ENT_QUOTES,'UTF-8');
		$date = substr($str,strpos($str,'DateCreated="')+13,19);
	}
	make_info_file($newdir,$date);
}

function update_droid_4_version($number) {
	$cmd = "cp -r 4.0-16/OPF-REF.php 4.0-$number/OPF-REF.php";
	exec($cmd);
}
function make_droid_4_version($number) {
	$cwd = getcwd();
	$newdir = "4.0-$number";
	$cmd = "rm -fR $newdir";
	exec($cmd);
	$cmd = "cp -r 4.0-16 4.0-$number";
	exec($cmd);
	$cmd = "wget http://www.nationalarchives.gov.uk/documents/DROID_SignatureFile_V$number.xml --output-document $newdir/DROID_SignatureFile.xml";
	update_droid_4_config($newdir,$number);
	echo $cmd;
	exec($cmd);
	$handle = fopen("$newdir/DROID_SignatureFile.xml","r");	
	$str = fgets($handle);
	$date = substr($str,strpos($str,'DateCreated="')+13,19);
	if ($date == "") {
		$str = fgets($handle);
#	$str = html_entity_decode(iconv('UTF-16','UTF-8',$str),ENT_QUOTES,'UTF-8');
		$date = substr($str,strpos($str,'DateCreated="')+13,19);
	}
	make_info_file("4.0-$number",$date);
	chdir($cwd);
}

function update_droid_4_config($newdir,$number) {
	$contents = file_get_contents($newdir . "/DROID_config.xml");
	$contents = str_replace("<SigFileVersion>16</SigFileVersion>","<SigFileVersion>$number</SigFileVersion>",$contents);
	$contents = str_replace("<SigFile>/home/davetaz/ref/tools/Droid/4.0-16/DROID_SignatureFile.xml</SigFile>","<SigFile>/home/davetaz/ref/tools/Droid/$newdir/DROID_SignatureFile.xml</SigFile>",$contents);
	$handle = fopen($newdir . "/DROID_config.xml","w");
	fwrite($handle,$contents);
	fclose($handle);
}

function make_info_file($newdir,$date) {
	$year = substr($date,0,4);
	$month = substr($date,5,2);
	$day = substr($date,8,2);
	$date = $day . "/" . $month . "/" . $year;
	$handle = fopen($newdir ."/INFO","w");
	fwrite($handle,"name: Droid\n");
	fwrite($handle,"version: " . $newdir . "\n");
	fwrite($handle,"relative_path: tools/Droid/" . $newdir . "/\n");
	fwrite($handle,"date: " . $date);
	fclose($handle);
}

?>
