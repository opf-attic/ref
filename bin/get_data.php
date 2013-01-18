<?php
	include('settings.php');

	error_reporting(E_ALL ^ E_NOTICE);

	MYSQL_CONNECT($server, $cuser, $ppassword) or die ( "<H3>Server unreachable</H3>");
	MYSQL_SELECT_DB($database) or die ( "<H3>Database non existent</H3>");

	$rdf_prefix = "http://data.openplanetsfoundation.org/ref/";
	$govdocs_prefix = "http://digitalcorpora.org/corp/nps/files/govdocs1/";
	// Take the path to the files. 

	$path = $_GET["path"];
if ($path == "" || $path == null) {
	$path = $argv[1];
}
if ($path == "" || $path == null) {
	echo "NO PATH";
	exit();
}

	if ($path == "") {
		header("HTTP/1.0 400 Bad Request");
		echo ("No Path defined");
	}
	
	require_once($root_path . 'bin/inc/rdf_functions.inc.php');
	
	$path = str_replace($strip_path,"",$path);
	if (substr($path,-1) == "/") {
		$subpath = substr($path,0,-1);
		$suffix = substr($subpath,strrpos($subpath,"/")+1,strlen($subpath));
	}

	// GET ALL THE FILES AND NAMES FOR THE BOXES WE DRAW LATER
	$ground_truth = get_groundtruth($path,$suffix);
	$files = get_files($path);
	asort($results);
	$owl_results = process_owl();
#print_r($results);
#exit();

	header("Content-type: text/xml");

	echo rdf_headers();
	echo "\n";
	foreach($ground_truth as $path => $data) {
		$groundtruth_uri = $path;
	}
	echo describe_self($suffix,$groundtruth_uri);
	foreach($ground_truth as $path => $data) {
		$groundtruth_uri = $path;
		echo('<rdf:Description rdf:about="' . $path . '">' . "\n");
		echo($data);
		echo('</rdf:Description>' . "\n\n");
	}
	foreach($results as $path => $data) {
		if (substr($path,0,7) != "http://") {
			echo('<rdf:Description rdf:about="' . $rdf_prefix . $path . '">' . "\n");
		} else {
			echo('<rdf:Description rdf:about="' . $path . '">' . "\n");
		}
		echo($data);
		echo('</rdf:Description>' . "\n\n");
	}
	foreach($owl_results as $path => $data) {
		echo('<rdf:Description rdf:about="' . $path . '">' . "\n");
		echo($data);
		echo('</rdf:Description>' . "\n\n");
	}
	echo rdf_footers();
	#print_r($results);
	
function get_groundtruth($path,$suffix) {
	global $rdf_prefix;
	$subject = $rdf_prefix . "apparent_groundtruth/" . $suffix;

	$path = "../" . $path . '/README';
	if (!file_exists($path)) {
		return "";	
	} 
	$handle = fopen($path,"r");
	while (!feof($handle)) {
		$line = trim(fgets($handle));
		$parts = explode("=",$line);
		$predicate = trim($parts[0]);
		$object = trim($parts[1]);
		if ($predicate != "" and $object != "") {
			if ($predicate == "dc:Description") {
				$predicate = "dc:description";
			}
			$string .= "\t" . "<" . $predicate . ">" . $object . '</' . $predicate . '>' . "\n";
		}
	}
	
	$groundtruth[$subject] = $string;
	return $groundtruth;
}


function describe_self($suffix,$groundtruth_uri) {
	$string = '<rdf:Description rdf:about="">' . "\n";
	$string .= "\t" . '<dc:title>Results Evaluation Framework - Single Resultset ('.$suffix.')</dc:title>' . "\n";
	$string .= "\t" . '<dc:type>Dataset</dc:type>' . "\n";
	$string .= "\t" . '<dc:description>This document provides all the data generated during the Open Planets Foundation (OPF) REF scanning process. This scanner takes in up to 10 files (when available) all of a single supposed ground truth (shown in this document) and scans then with multiple versions of multiple file identification tools to either verify or deny this ground truth in each case. The aim is not to see if the ground truth is correct, but to examine the results of a large magnitude of tools. This allows tool developers to view behavioral changes between versions as a unit test. It also allows collection managers to observe changes in file type identifications that may be of direct impact to their collection. So it is not about being correct, it is about being consistant. The graphs that have been created from these results can be found at http://data.openplanetsfoundation.org/ref/'.$suffix.'</dc:description>' . "\n";
	$string .= "\t" . '<dc:source rdf:resource="http://data.openplanetsfoundation.org/ref/' . $suffix . '"/>' . "\n";
	$string .= "\t" . '<dc:rights>The Results Evaluation Framework Data. Copyright The Open Planets Foundation / Univeristy of Southampton / Dr David Tarrant. Licensed under a Creative Commons Attribution 3.0 Unported License</dc:rights>' . "\n";
	$string .= "\t" . '<dcterms:license rdf:resource="http://creativecommons.org/licenses/by/3.0/deed.en_GB"/>' . "\n";
	$string .= "\t" . '<dcterms:rightsHolder>The Open Planets Foundation</dcterms:rightsHolder>' . "\n";
	$string .= "\t" . '<dcterms:rightsHolder>University of Southampton</dcterms:rightsHolder>' . "\n";
	$string .= "\t" . '<dcterms:rightsHolder>Dr David Tarrant</dcterms:rightsHolder>' . "\n";
	if ($groundtruth_uri != "") {
		$string .= "\t" . '<opf-ref:apparent_groundtruth rdf:resource="'.$groundtruth_uri.'"/>' . "\n";
	}
	$string .= '</rdf:Description>' . "\n\n";
	return $string;	
}

// GET ALL THE FILES
function get_files($path) {
	global $rdf_prefix,$govdocs_prefix,$results;
	
	$query = "select id,name,relative_full_path from files where files.relative_full_path like '$path%' and files.name != 'README';";
	$res = mysql_query($query);
	while ($array = mysql_fetch_array($res)) {
		$file_path = $array["relative_full_path"];
		$file_name = substr($file_path,strrpos($file_path,"/")+1,strlen($file_path));
		$file_uri = $govdocs_prefix . substr($file_name,0,3) . "/" . $file_name;
		$files_agg[] = $file_uri;
		#$files_agg[] = $rdf_prefix . $array["relative_full_path"];
		
#		$main[$array["relative_full_path"]] .= '<rdf:Description rdf:about="' . $rdf_prefix . $array["relative_full_path"] . '"' . ">\n";
		$string = '<rdf:type rdf:resource="http://data.openplanetsfoundation.org/schema/ref/type/file"/>';
		$results[$file_uri] .= "\t" . $string . "\n";

		process_file($array["id"],$array["relative_full_path"],$file_uri);
		$query2 = "select id,datestamp,tool_id,raw_result_id from results where file_id=" . $array["id"] . ";";
		$res2 = mysql_query($query2);

#		$main[$array["relative_full_path"]] .= '</rdf:Description>' . "\n";
	}
	$res = ""; $query = ""; $array = "";
	
	return $results;
}

function process_file($id,$path,$file_uri) {		
	global $results, $rdf_prefix,$global_done;

	$extension = substr($path,strrpos($path,".")+1,strlen($path));
	$string = '<rdf:type rdf:resource="http://data.openplanetsfoundation.org/schema/ref/type/extension_profile"/>';
	if (strpos($results["extension/" . $extension],$string) == false) {
		@$results["extension/" . $extension] .= "\t" . $string . "\n";
	}
	@$results["extension/" . $extension] .= "\t" . '<ore:aggregates rdf:resource="' . $file_uri .'"' . "/>\n";
#	if ($extensions_done[$extension] < 1) {
#		@$results["extension/index"] .= "\t" . '<ore:aggregates rdf:resource="' . $rdf_prefix . 'extension/' . $extension . '"' . "/>\n";
#		$extensions_done[$extension] = 1;
#	}	

	$query = "select results.id,datestamp,tool_id,raw_result_id,tools.name,tools.version from results inner join tools on results.tool_id=tools.id where file_id=" . $id . ";";
	$res = mysql_query($query) or die($query);

	$done = array();
	while ($array = mysql_fetch_array($res)) {
		$string = "";
		if (!$done[$array["id"]]) {
			$done[$array["id"]] = true;
			$results[$file_uri] .= "\t" . '<opf-ref:hasResult rdf:resource="' . $rdf_prefix . "result/" . $array["id"] . '"' . "/>\n";
			if (!$global_done[$array["id"]]) {
				$global_done[$array["id"]] = true;
				$results["result/" . $array["id"]] = process_result2("result/" . $array["id"],$results["result/" . $array["id"]]);
			}
		}
		$string = "";
		if (!$global_done[$array["id"] . "-" . $array["tool_id"]]) {
			$global_done[$array["id"] . "-" . $array["tool_id"]] = true;
			$string = '<opf-ref:producedBy rdf:resource="' . $rdf_prefix . 'tools/' . $array["name"] . '/' . $array["version"] . '"' . "/>";
			$results["result/" . $array["id"]] .= "\t" . $string . "\n";	
			if (!$global_done[$array["tool_id"]]) {
				$global_done[$array["tool_id"]] = true;
				$results['tools/' . $array["name"] . '/' . $array["version"]] = process_tool($array["tool_id"]);
			}
		}
	}
}

function process_result2($key,$string) {
	global $rdf_prefix;
	
	$processed_results[$key] = 1;
	$string = "\t" . '<rdf:type rdf:resource="http://data.openplanetsfoundation.org/schema/ref/result"/>'. "\n";

	$query = "select subject,predicate,object from triples where subject='$key';";
	$res = mysql_query($query);

	while ($array = mysql_fetch_array($res)) {
		if (substr($array["object"],0,4) == "http")  {
			$string .= "\t" . '<' . $array["predicate"] . ' rdf:resource="' . $array["object"] . '"/>' . "\n";
		} else {
			$string .= "\t" . '<' . $array["predicate"] . '>' . $array["object"] . '</' . $array["predicate"] . '>' . "\n";
		}
	} 
	return $string;
}
	
function process_tool($id) {
	$string = "";
	$query = "select id, name, version, date from tools where id=$id;"; 
	$res = mysql_query($query);
	while ($array = mysql_fetch_array($res)) {
		$path = "tools/" . $array['id'];
		$string .= "\t" . '<rdfs:label>' . $array['name'] . '</rdfs:label>' . "\n";
		$string .= "\t" . '<opf-ref:version>' . $array['version'] . '</opf-ref:version>' . "\n";
		$string .= "\t" . '<opf-ref:releaseDate>' . $array['date'] . '</opf-ref:releaseDate>' . "\n";
		$string .= "\t" . '<rdf:type rdf:resource="http://data.openplanetsfoundation.org/schema/ref/tool"/>' . "\n";
	}
	return $string;
}

function process_owl() {
	global $rdf_prefix;
	$string = "";
	$query = "select subject, predicate, object from owl order by subject ASC;";
	$res = mysql_query($query);
	$done = array();
	while ($array = mysql_fetch_array($res)) {
		$subject = $array["subject"];
		if (strpos($subject,":") !== false) {
			$subject = get_long_from_short($subject);
		} else {
			$subject = $rdf_prefix . $subject;
		}
		$object = $array["object"];
		if (strpos($object,":") !== false) {
			$object = get_long_from_short($object);
		} else {
			$object = $rdf_prefix . $object;
		}
		if (!$done[$subject]) {
			$string = "";
			$done[$subject] = 1;
			$sameAs = pellet_sameAs($subject);
			for ($i=0;$i<count($sameAs);$i++) {
				if ($sameAs[$i] != "" && $subject != $sameAs[$i]) {
					$string .= "\t" . '<owl:sameAs rdf:resource="'.$sameAs[$i].'"/>' . "\n"; 
				}
			}	
			$results[$subject] = $string;
		}
		if ($array["predicate"] != "owl:sameAs") {
			$results[$subject] .= "\t" . '<' . $array["predicate"] . ' rdf:resource="'.$object.'"/>' . "\n";
		}
	}
	return $results;
}

function pellet_sameAs($subject) {
	$prefix = "http://data.openplanetsfoundation.org/ref/";
	$file_string = "PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
		PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
		PREFIX owl: <http://www.w3.org/2002/07/owl#>

		SELECT ?x
		WHERE {
			<".$subject."> owl:sameAs ?x 
		}";
	$handle = fopen("/tmp/query.sparql","w");
	fwrite($handle,$file_string);
	fclose($handle);
	$dir = $root_path . "bin";
	@chdir($dir);
	$ret = array();
	$cmd = 'sh pellet.sh query -o JSON -q /tmp/query.sparql owl.rdf';
	exec($cmd,$ret);
	unlink('/tmp/query.sparql');
	$results = "";

	$open = false;

	$json = "";
	for ($i=0;$i<count($ret);$i++) {
		$json .= $ret[$i];
	}
	$stuff = json_decode($json,true);
	$sub = $stuff["results"]["bindings"];
	for ($i=0;$i<count($sub);$i++) {
		$results[] = $sub[$i]["x"]["value"];
	}
	return $results;
 
}
?>
