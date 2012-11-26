<?php
	$here = getcwd();
	if ($handle = opendir('.')) {
	    while (false !== ($entry = readdir($handle))) {
        	if ($entry != "." && $entry != ".." && is_dir($entry)) {
		    #copy_files($entry);
	            make_file($entry);
		    chdir($here);
        	}
	    }
	    closedir($handle);
	}

function copy_files($entry) {
	echo "\n" . $entry . "\n";
	$cmd = "cp OPF-REF.php $entry/OPF-REF.php";
	exec($cmd);
}

function make_file($entry) {
	echo "\n\n\n" . $entry . "\n\n\n";
	chdir($entry);
	$cmd = "./configure";
	exec($cmd);
	$cmd = "make";
	exec($cmd);
}

?>
