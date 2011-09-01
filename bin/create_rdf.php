<?php
	error_reporting(E_ERROR | E_WARNING | E_PARSE);

	$opf_ontology = "http://data.openplanetsfoundation.org/ref/ontology/#";
	$rdf_prefix = "http://data.openplanetsfoundation.org/ref/";

	ini_set('include_path','.:../:../lib/:../etc/');
	require_once('database_connector.php');
	require_once('rdf_functions.inc.php');

	$query = 'select id,name,relative_full_path from files;';
	
	$res = mysql_query($query);
	
	while ($array = mysql_fetch_array($res)) {
		$files_agg[] = $rdf_prefix . $array["relative_full_path"];
		
		$main[$array["relative_full_path"]] = '<rdf:Description rdf:about="' . $rdf_prefix . $array["relative_full_path"] . '"' . ">\n";

		process_file($array["id"],$array["relative_full_path"]);
		$query2 = "select id,datestamp,tool_id,raw_result_id from results where file_id=" . $array["id"] . ";";
		$res2 = mysql_query($query2);

		$main[$array["relative_full_path"]] .= '</rdf:Description>' . "\n";
		
	}
	process_results();
	process_tools();
		
	foreach($main as $path => $data) {
		$file = "../www/$path.rdf";
		$dir = "../www/" . substr($path,0,strrpos($path,"/"));
		if ($dirs[$dir] < 1) {
			exec('rm -fR ' . $dir);
			$dirs[$dir] = 1;
		}
		exec('mkdir -p ' . $dir);
		$handle = fopen($file,"w");
		fwrite($handle,rdf_headers());
		fwrite($handle,$data);
		fwrite($handle,rdf_footers());
		fclose($handle);
	}
	foreach($results as $path => $data) {
		$file = "../www/$path.rdf";
		$dir = "../www/" . substr($path,0,strrpos($path,"/"));
		if ($dirs[$dir] < 1) {
			exec('rm -fR ' . $dir);
			$dirs[$dir] = 1;
		}
		exec('mkdir -p ' . $dir);
		$handle = fopen($file,"w");
		fwrite($handle,rdf_headers());
		fwrite($handle,'<rdf:Description rdf:about="' . $rdf_prefix . $path . '">' . "\n");
		fwrite($handle,$data);
		fwrite($handle,'</rdf:Description>' . "\n");
		fwrite($handle,rdf_footers());
		fclose($handle);
	}

	process_agg($files_agg,"data");
	process_agg($tools_agg,"tools");
	process_agg($hits_agg,"hit");
	process_agg($results_agg,"results");

	function process_agg($agg,$path) {
		global $rdf_prefix;
		$file = "../www/$path/index.rdf";
		$handle = fopen($file,"w");

		fwrite($handle,rdf_headers());
		fwrite($handle,'<rdf:Description rdf:about="' . $rdf_prefix . $path . '/">' . "\n");

		for($i=0;$i<count($agg);$i++) {
			fwrite($handle,"\t\t" . '<ore:aggregates rdf:resource="' . $agg[$i] . '"/>' . "\n");
		}

		fwrite($handle,'</rdf:Description>' . "\n");
		fwrite($handle,rdf_footers());

		fclose($handle);
	}

#	print_r($main);
#	print_r($results);
	$extensions_done;

	function process_file($id,$path) {		
		global $main, $results, $rdf_prefix, $extensions_done;

		$extension = substr($path,strrpos($path,".")+1,strlen($path));
		$results["extension/" . $extension] .= "\t" . '<ore:aggregates rdf:resource="' . $rdf_prefix . $path .'"' . "/>\n";
		if ($extensions_done[$extension] < 1) {
			@$results["extension/index"] .= "\t" . '<ore:aggregates rdf:resource="' . $rdf_prefix . 'extension/' . $extension . '"' . "/>\n";
			$extensions_done[$extension] = 1;
		}	

		$query = "select id,datestamp,tool_id,raw_result_id from results where file_id=" . $id . ";";
		$res = mysql_query($query);

		while ($array = mysql_fetch_array($res)) {
			$main[$path] .= "\t" . '<opf-ref:has-result rdf:resource="' . $rdf_prefix . "results/" . $array["id"] . '"' . "/>\n";
			$results["results/" . $array["id"]] .= "\t" . '<opf-ref:uses-tool rdf:resource="' . $rdf_prefix . 'tools/' . $array["tool_id"] . '"' . "/>\n";
			$results["results/" . $array["id"]] .= "\t" . '<opf-ref:scantime>' . date(DATE_RFC822,$array["datestamp"]) . "</opf-ref:scantime>\n";
		}
	}

	function process_results() {
		global $results,$rdf_prefix,$results_agg,$hits_agg;

		$query = "select subject,predicate,object from triples;";
		$res = mysql_query($query);

		while ($array = mysql_fetch_array($res)) {
			$results_agg[] = $rdf_prefix . $array["subject"];
			if ($array["predicate"] == "droid:hasHit") {
				$hits_agg[] = $rdf_prefix . $array["object"];
				$results[$array["subject"]] .= "\t" . '<' . $array["predicate"] . ' rdf:resource="'.$rdf_prefix . $array["object"] . '"/>' . "\n";
			} elseif (substr($array["object"],0,4) == "http")  {
				$results[$array["subject"]] .= "\t" . '<' . $array["predicate"] . ' rdf:resource="' . $array["object"] . '"/>' . "\n";
			} else {
				$results[$array["subject"]] .= "\t" . '<' . $array["predicate"] . '>' . $array["object"] . '</' . $array["predicate"] . '>' . "\n";
			}
		} 
	}

	function process_tools() {
		global $results,$rdf_prefix,$tools_agg;
		$query = "select id, name, version from tools;"; 
		$res = mysql_query($query);
		while ($array = mysql_fetch_array($res)) {
			$path = "tools/" . $array['id'];
			$tools_agg[] = $rdf_prefix . $path;
			$results[$path] .= "\t" . '<rdfs:label>' . $array['name'] . '</rdfs:label>' . "\n";
			$results[$path] .= "\t" . '<opf-ref:version>' . $array['version'] . '</opf-ref:version>' . "\n";
		}
	}
	

?>
