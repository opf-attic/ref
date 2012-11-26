<?php
	include('settings.php');

	error_reporting(E_ALL ^ E_NOTICE);

	MYSQL_CONNECT($server, $cuser, $ppassword) or die ( "<H3>Server unreachable</H3>");
	MYSQL_SELECT_DB($database) or die ( "<H3>Database non existent</H3>");

	$rdf_prefix = "http://data.openplanetsfoundation.org/";

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
	
	$path = str_replace($strip_path,"",$path);

	// GET ALL THE FILES AND NAMES FOR THE BOXES WE DRAW LATER
	get_files($path);
	asort($results);

	header("Content-type: text/xml");

	require_once($root_path . 'www/inc/rdf_functions.inc.php');
	
	echo rdf_headers();
	echo "\n";
	foreach($results as $path => $data) {
		echo('<rdf:Description rdf:about="' . $rdf_prefix . $path . '">' . "\n");
		echo($data);
		echo('</rdf:Description>' . "\n\n");
	}
	echo rdf_footers();
	#print_r($results);
	


// GET ALL THE FILES
function get_files($path) {
	global $rdf_prefix,$main;
	
	$query = "select id,name,relative_full_path from files where files.relative_full_path like '$path%' and files.name != 'README';";
	$res = mysql_query($query);
	while ($array = mysql_fetch_array($res)) {
		$files_agg[] = $rdf_prefix . $array["relative_full_path"];
		
#		$main[$array["relative_full_path"]] .= '<rdf:Description rdf:about="' . $rdf_prefix . $array["relative_full_path"] . '"' . ">\n";
		$string = '<rdf:type rdf:resource="http://data.openplanetsfoundation.org/schema/ref/type/file"/>';
		$main[$array["relative_full_path"]] .= "\t" . $string . "\n";

		process_file($array["id"],$array["relative_full_path"]);
		$query2 = "select id,datestamp,tool_id,raw_result_id from results where file_id=" . $array["id"] . ";";
		$res2 = mysql_query($query2);

#		$main[$array["relative_full_path"]] .= '</rdf:Description>' . "\n";
	}
	$res = ""; $query = ""; $array = "";
	
	return $main;
}

function process_file($id,$path) {		
	global $main, $results, $rdf_prefix,$global_done;

	$extension = substr($path,strrpos($path,".")+1,strlen($path));
	$string = '<rdf:type rdf:resource="http://data.openplanetsfoundation.org/schema/ref/type/extension_profile"/>';
	if (strpos($results["extension/" . $extension],$string) == false) {
		@$results["extension/" . $extension] .= "\t" . $string . "\n";
	}
	@$results["extension/" . $extension] .= "\t" . '<ore:aggregates rdf:resource="' . $rdf_prefix . $path .'"' . "/>\n";
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
			$main[$path] .= "\t" . '<opf-ref:hasResult rdf:resource="' . $rdf_prefix . "result/" . $array["id"] . '"' . "/>\n";
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


?>
