<?php
include('settings.php');

$sameAs["droid:hasPronomPUID"][] = "droid:puid";

error_reporting(E_ALL ^ E_NOTICE);
$server= "localhost";
$cuser= "root";
$ppassword= "";
$database= "opf_ref";

MYSQL_CONNECT($server, $cuser, $ppassword) or die ( "<H3>Server unreachable</H3>");
MYSQL_SELECT_DB($database) or die ( "<H3>Database non existent</H3>");

createTable();
#prepare4store();

require_once('inc/rdf_functions.inc.php');

$handle = fopen("owl.rdf","w");
fwrite($handle,rdf_headers());

$triples = null;
$triples["droid:hasFileDescription"] = array("droid:format_type","fits:format","fido:formatname","file:filetype","tika:hasMime");
addToDataBase($triples,"rdfs:seeAlso");
fwrite($handle,generateRDF($triples,"rdfs:seeAlso"));

$triples = null;
$triples["tika:hasMime"]= array("file:hasMime","droid:mime_type","fits:mimetype","droid:hasMime","file:hasmime");
addToDataBase($triples,"owl:sameAs");
fwrite($handle,generateRDF($triples,"owl:sameAs"));

$triples = null;
$triples["droid:hasPronomPUID"]= array("droid:puid","fido:puid","droid:hasPronomPUID");
addToDataBase($triples,"owl:sameAs");
fwrite($handle,generateRDF($triples,"owl:sameAs"));

findFormatMatches("droid:hasPronomPUID",$handle);
findFormatMatches("tika:hasMime",$handle);
	
fwrite($handle,rdf_footers());
fclose($handle);
processOwl();

#populate4store();


function createTable() {
	$query = "drop table if exists sameAs;";
	$res = mysql_query($query) or die("FAILED $query");	
	$query = "drop table if exists owl;";
	$res = mysql_query($query) or die("FAILED $query");	
$query = 'CREATE TABLE `owl` (
  `subject` varchar(255) DEFAULT NULL,
  `predicate` varchar(255) DEFAULT NULL,
  `object` varchar(255) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;';
	$res = mysql_query($query) or die("FAILED $query " . mysql_error());

}

function prepare4store() {
	$cmd = "killall 4s-backend";
	exec($cmd);
	$cmd = "4s-backend-destroy ref";
	exec($cmd);
	$cmd = "4s-backend-setup ref";	
	exec($cmd);
	$cmd = "4s-backend ref";
	exec($cmd);
}

function populate4store() {
	$cmd = "4s-import ref owl.rdf";
	exec($cmd);
}

function generateRDF($triples,$predicate) {
	$string = "";
	foreach ($triples as $subject => $array) {
		if (strpos($subject,":") !== false) {
			$subject = get_long_from_short($subject);
		}
		$string .= "\t\t" . '<rdf:Description rdf:about="'.$subject . '">' . "\n";
		for($i=0;$i<count($array);$i++) {
			$object = $array[$i];
			if (strpos($object,":") !== false) {
				$object = get_long_from_short($object);
			}
			$string .= "\t\t\t" . '<'. $predicate . ' rdf:resource="'. $object . '" />' . "\n";
		}
		$string .= "\t\t" . '</rdf:Description>' . "\n";
	}
	return $string;
}

function addToDataBase($triples,$predicate) {
	foreach ($triples as $subject => $array) {
		for($i=0;$i<count($array);$i++) {
			$query = 'INSERT into owl set subject="'.$subject.'", predicate="'.$predicate.'", object="'.$array[$i].'";';
			$res = mysql_query($query);	
			$query = 'INSERT into owl set subject="'.$array[$i].'", predicate="'.$predicate.'", object="'.$subject.'";';
			$res = mysql_query($query);	
		}
	}
}

function processOwl() {
	global $root_path;

	$query = 'select distinct subject from triples';
	$res = mysql_query($query) or die($query);
	while ($row = mysql_fetch_array($res)) {
		$element[$row["subject"]] = 1;
	}

	$done = array();
	foreach ($element as $result => $value) {
echo "Processing $result\n";
		if ($done[$result]) {
                        continue;
                }
                $done[$result] = 1;
                $matches = array();
                if (is_array($result_ids[$result])) {
                        $matches = $result_ids[$result];
                }
                $prefix = "http://data.openplanetsfoundation.org/ref/";
                $file_string = "PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
PREFIX owl: <http://www.w3.org/2002/07/owl#>

SELECT ?x
WHERE {
      <".$prefix.$result."> owl:sameAs ?x 
}";
                $handle = fopen("/tmp/query.sparql","w");
                fwrite($handle,$file_string);
                fclose($handle);
                $dir = $root_path . "bin";
                chdir($dir);
		$ret = array();
                $cmd = 'sh pellet.sh query -q /tmp/query.sparql owl.rdf';
                exec($cmd,$ret);
                unlink('/tmp/query.sparql');
                $results = array();

                $open = false;

		for ($i=0;$i<count($ret);$i++) {
                        $line = trim($ret[$i]);
                        if ($open == true && $line != "") {
                                $results[] = $line;
                        }
                        if (substr($line,0,4) == "====") {
                                $open = true;
                        }
                }

                for ($i=0;$i<count($results);$i++) {
                        $object = "result/" . $results[$i];
                        if (!in_array($object,$matches) && ($object != $result)) {
                                $result_ids[$result][] = $object;
                                $done[$object] = 1;
                                unset($result_ids[$object]);
                        }
                }
	}
	//print_r($result_ids);
	foreach ($result_ids as $subject => $array) {
		$predicate = "owl:sameAs";
		for($i=0;$i<count($array);$i++) {
			$query = 'INSERT into owl set subject="'.$subject.'", predicate="'.$predicate.'", object="'.$array[$i].'";';
			$res = mysql_query($query) or die($query);	
			$query = 'INSERT into owl set subject="'.$array[$i].'", predicate="'.$predicate.'", object="'.$subject.'";';
			$res = mysql_query($query) or die($query);	
		}
	}

}

function findFormatMatches($predicate,$handle) {
	$query = 'select object from owl where subject="'.$predicate.'" and predicate="owl:sameAs";';
	$res = mysql_query($query) or die($query);
		
	$query = 'select subject,object from triples where predicate="'.$predicate.'"';
	while ($array = mysql_fetch_array($res)) {
		$query .= ' or predicate="'.$array["object"].'"';
	}
	$query .= ' order by object asc;';
	$res = mysql_query($query) or die($query);
	$current_object = "";
	$current_subject = "";

	while ($array = mysql_fetch_array($res)) {
		$object = trim($array["object"]);
		if ($object == "unspecified") {
			continue;
		}
		if ($array["object"] != $current_object) {
			$current_object = $array["object"];
			$current_subject = $array["subject"];
		} else {
			$triples["http://data.openplanetsfoundation.org/ref/" . $current_subject][] = "http://data.openplanetsfoundation.org/ref/" . $array["subject"];
		}
	}	
	fwrite($handle,generateRDF($triples,"owl:sameAs"));
#	processOwl($triples,"owl:sameAs");

}


?>
