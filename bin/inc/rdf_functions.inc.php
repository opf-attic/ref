<?php


$sparql_endpoint = "http://data.openplanetsfoundation.org:5052/sparql/";

function get_prefix() {
	$array = get_rdf_namespaces();
	foreach($array as $key => $value) {
		$string .= "prefix ".$key.": <".$value.">\n";
	}
	return $string;
}

function get_query_url() {
	return "http://data.openplanetsfoundation.org:5052/sparql/?result-format=sparql&query-lang=sparql&query=";
}

function xml_requested($data) {
	header('Content-type: text/xml');
	echo xml_headers();
	echo "  <report_format_detail>\n";
	echo "    <FileFormat>\n";
	echo output_xml($data,true);
	echo "    </FileFormat>\n";
	echo "  </report_format_detail>\n";
	echo xml_footers();
}

function xmlentities($data){
        $find=array("&#xD;","&","<",">","'",'"',);
        $replace=array(" ","&amp;","&lt;","&gt;","&apos;","&quot;");
        $replaced=str_replace($find, $replace, $data);
        for( $c=0; $c<strlen($replaced); $c++){
                $char=substr($replaced,$c,1);
                if ((ord($char)<32 || ord($char)>126) && (!(ord($char)>8 && ord($char)<14))) $replaced=substr_replace($replaced,'?',$c,1);
        }
        return $replaced;
}


function rdf_requested($data) {
	header('Content-type: text/xml');
	echo rdf_headers();
	
	echo output_rdf($data);

	foreach ($data as $key => $array) {
		for($i=0;$i<count($array);$i++) {
			$subdata = $array[$i];
			if ($subdata["z_type"] == "URI") {
				$node = $subdata["z"];
				//$data2 = "";
				//$data2 = sparql_subject($node);
				//if ($data2 != "") {
				//	echo output_rdf($data2);
				//}
			}
		}
	}	

	echo rdf_footers();
}

function html_requested($data,$excluded) {
	header('Content-type: text/html');
	echo html_headers();

	echo output_html($data,$excluded);

	echo html_footers();
}

function html_headers() {
	$handle = fopen("headers.html","r");
	while ($line = fgets($handle,4096)) {
		$string .= $line;
	}
	fclose($handle);
	return $string;
	$string .= '<html><head><title>P2-Registry HTML Output</title>';
	$string .= '<link rel="stylesheet" type="text/css" href="/css/base.css" media="screen, projection" />';
	$string .= "
<script type=\"text/javascript\">
function toggle( element ){
        var div1 = document.getElementById(element)
        if (div1.style.display == 'none') {
                div1.style.display = 'block'
        } else {
                div1.style.display = 'none'
        }
}
</script>";
	$string .= '</head><body>';
	return $string;
}

function html_footers() {
	$string = '<div align="center">data.openplanetsfoundation.org - &copy; University of Southampton, rights shared with Open Planets Foundation.<br/>
Powered by the <a href="http://p2-registry.ecs.soton.ac.uk">P2-Registry</a> and <a href="http://4store.org">4Store</a>. 
</div></body></html>';
	return $string;
}

function output_html($data,$excluded) {
	foreach ($data as $namespace => $foo) {
		$data = $data[$namespace];
		if ($uri == "") $uri = $namespace;
	}
	$subject = "http://" . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"];
	$subject = $uri;
	if (substr($subject,strrpos($subject,"."),strlen($subject)) == ".html") {
		$subject = substr($subject,0,strrpos($subject,"."));
	}
	if ($excluded != "") {
		$rdf_uri = $subject . ".rdf?excluded=" . $excluded;
	} else {
		$rdf_uri = $subject . ".rdf";
		if (substr($subject,-1) == "/") {
			$rdf_uri = $subject . "index.rdf";
		}
	}


	$availability_string = '<table><tr><td style="padding-top:0.6em;"><span style="color:#800000;">Alternative Formats: </span></td><td><a href="'.$rdf_uri.'" title="RDF Resource Description Framework"> <img border="0" src="http://www.w3.org/RDF/icons/rdf_w3c_button.32" alt="RDF Resource Description Framework Icon"/></a></td></tr></table>';
	foreach($data as $no => $values) {
		$key = $values["y"] . $values["z"];
		if ($keys[$key]["done"] != 1) {
			$keys[$key]["top"] .= "<tr>";
			if ($values["z_type"] == "URI" || substr($values["z"],0,4) == "http") {

				# HACK HACK
				if (substr($values["z"],0,strlen("http://reference.data.gov.uk/id/file-format")) == "http://reference.data.gov.uk/id/file-format") {
					$number = substr($values["z"],strrpos($values["z"],"/")+1,strlen($values["z"]));
					$fill_string = '<a href="http://test.linkeddatapronom.nationalarchives.gov.uk/doc/file-format/'.$number.'">' . $values["z"] . '</a>';
				} else {
					$fill_string = '<a href ="'.$values["z"].'">' . $values["z"] . '</a>';
				}

			} else {
				$fill_string = $values["z"];
			}
			$keys[$key]["top"] .= '<td width="30%"><h5>' . get_short_from_long($values["y"]) . '</h5></td><td width="70%"><h5>' . $fill_string . '</h5><td>';
			$keys[$key]["top"] .= '<td width="5%"><h5><i><a style="text-decoration:none; color:yellow; font-size:0.8em;" href="#" onClick=\'toggle("div_'.$no.'");\'>i</a></i></h5></td>';
			$keys[$key]["top"] .= "</tr>";

			if (substr($values["z"],0,strlen("http://data.openplanetsfoundation.org/ref/results/")) == "http://data.openplanetsfoundation.org/ref/results/") {
				$mapping_array = process_result($values["z"],$mapping_array);
			}
			
			$sub_subject = $values["g"];
			$data2 = sparql_subject($sub_subject);
			$data2 = $data2[$sub_subject];
			$keys[$key]["hidden"] .= '<div id="div_'.$no.'" style="display: none;">' . "\n";
			$keys[$key]["hidden"] .= '<table>' . "\n";
			if ($excluded != "") {
				$exclude_link = $excluded . ',' . $values["g"];
			} else {
				$exclude_link = $values["g"];
			}
			$keys[$key]["hidden"] .= '<tr><td width="45%"><h5>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Source of Triple</h5></td><td width="50%"><h5>' . $values["g"] . '</h5></td><td width="5%"><a style="text-decoration:none; color: yellow;" href="?excluded='.$exclude_link.'">exclude</a></td></tr>'."\n";
			error_reporting(0);
			foreach($data2 as $no2 => $values2) {
				if ($values2["z_type"] == "URI" || substr($values2["z"],0,4) == "http") {
					$fill_string = '<a href ="'.get_href($values2["z"]).'">' . $values2["z"] . '</a>';
				} else {
					$fill_string = $values2["z"];
				}
				$keys[$key]["hidden"] .= '<tr><td width="45%"><h6 style="font-size: 0.6em;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'.get_short_from_long($values2["y"]).'</h6></td><td width="50%"><h6 style="font-size: 0.6em;">' . $fill_string . "</h6></td><td></td></tr>\n";
			}
			error_reporting(1024);
			$keys[$key]["done"] = 1;
		} else {
			echo "gfdhgjkfdhjk";
			$sub_subject = $values["g"];
			$data2 = sparql_subject($sub_subject);
			$data2 = $data2[$sub_subject];
			if ($excluded != "") {
				$exclude_link = $excluded . ',' . $values["g"];
			} else {
				$exclude_link = $values["g"];
			}
			$keys[$key]["hidden"] .= '<tr><td style="border-top: 1px solid black;" width="45%"><h5>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Source of Triple</h5></td><td width="50%" style="border-top: 1px solid black;"><h5>' . $values["g"] . '</h5></td><td width="5%" style="border-top: 1px solid black;"><a style="text-decoration:none; color: yellow;" href="?excluded='.$exclude_link.'">exclude</a></td></tr>'."\n";
			foreach($data2 as $no2 => $values2) {
				if ($values2["z_type"] == "URI" || substr($values2["z"],0,4) == "http") {
					$fill_string = '<a href ="'.get_href($values2["z"]).'">' . $values2["z"] . '</a>';
				} else {
					$fill_string = $values2["z"];
				}
				$keys[$key]["hidden"] .= '<tr><td width="45%"><h6 style="font-size: 0.6em;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'.get_short_from_long($values2["y"]).'</h6></td><td width="50%"><h6 style="font-size: 0.6em;">' . $fill_string . "</h6></td><td></td></tr>\n";
			}
		}
	}
	$string = '<table>' . "\n";
	foreach ($keys as $key => $values) {
		$string .= $keys[$key]["top"];
		$string .= '<tr><td colspan="3">' . $keys[$key]["hidden"] .  "</table>\n" . '</td></tr>';
	}
	$string .= '</table>' . "\n";
	#print_r($mapping_array);
	if ($mapping_array != "") {
		$string .= '<table><tr><th>Files/Tools</th>';
		foreach ($mapping_array["tools"] as $tool) {
			 $string .= '<th>' . $tool . '</th>';
		}
		$string .= '</tr><tr><td>&nbsp;</td>';
		foreach ($mapping_array["tools"] as $tool) {
			$string .= '<td align="center">' . $mapping_array[$tool] . '</td>';
		}
		$string .= '</tr>';
		$string .= '</table>';
	}
	?>
	<br/>
	<h1><?php echo $subject; ?></h1>
	<div align="center">
<div style="width:900px" align="right">
<div style="width:300px;" class="">
        <div class="box-2 clear clearing margin-b-2em">
                <div class="b2-t">
                        <div class="b2-tr">&nbsp;</div>
                </div>
                <div class="b2-m" style="height: 55px;">
                        <div class="b2-p">
                                <?php echo $availability_string; ?>
                        </div>
                </div>
        </div>
</div>
</div>

<div style="width:900px;" class="journey-planner journey-planner-homepage clear">
        <div class="journey-planner-inner">
                <div class="box-1">
                        <div class="b1-t">
                                <div class="b1-tr">&nbsp;</div>
                        </div>
                        <div class="b1-m">
                                <div class="b1-p">
                                        <?php echo $string; ?>
                                </div>
                        </div>
                        <div class="b1-b">
                                <div class="b1-br">&nbsp;</div>
                        </div>
                </div>
        </div>
</div>
<br/>
<?php
        if ($excluded != "") {
                $string = '<table>';
                for ($i=0;$i<count($exclusions);$i++) {
                        $href = "";
                        for ($k=0;$k<count($exclusions);$k++) {
                                if ($exclusions[$k] != $exclusions[$i]) {
                                        if ($href != "") {
                                                $href .= "," . $exclusions[$k];
                                        } else {
                                                $href = "?excluded=" . $exclusions[$k];
                                        }
                                }
			}
                        if ($href == "") {
                                $href = $subject;
                        }
                        $string .= '<tr><td width="75%"><h2 style="font-size: 1em;">' . $exclusions[$i] . '</h2></td><td width="25%"><div align="right"><a style="text-decoration:none; color: purple;" href="'.$href.'">include</a></div></td></tr>';
                }
                $string .= '</table>';
?>
<div style="width:900px;" class="journey-planner journey-planner-homepage clear">
        <div class="journey-planner-inner">
                <div class="box-7">
                        <div class="b7-t">
                                <div class="b7-tr"><h2 style="font-size: 1.5em; color: white">Excluded Namespaces</h2></div>
                        </div>
                        <div class="b7-m">
                                <div class="b7-p" style="padding-left: 20px; padding-right: 20px; padding-top: 10px;">
                                        <?php echo $string; ?>
                                </div>
                        </div>
                        <div class="b7-b">
                                <div class="b7-br">&nbsp;</div>
                        </div>
                </div>
        </div>
</div>
<br/>
<?php
        }
}

function get_href($uri) {
	if (strpos($uri,"nationalarchives.gov.uk") > 0) {
		$href = str_replace("http://nationalarchives.gov.uk/","http://data.openplanetsfoundation.org/",$uri);
	} 
	if (strpos(substr($href,strlen($href)-5,strlen($href)),".") > 0) {
	} else {
		$href = $href . ".html";
	}
	return $href;
}

function output_rdf($data) {
	foreach ($data as $key => $array) {
		$string .= '<rdf:Description rdf:about="' . $key . '">' . "\n";
		for($i=0;$i<count($array);$i++) {
			$subdata = $array[$i];
			$prefix = $subdata["y"];
			if ($subdata["y_type"] == "URI") {
				$prefix = get_short_from_long($prefix);
			}
			if ($subdata["z_type"] == "URI") {
				$string .= "\t" . '<' . $prefix . ' rdf:resource="'.$subdata["z"] . '"/>' . "\n";
			} else {
				$string .= "\t" . '<' . $prefix . '>' . $subdata["z"] . '</' . $prefix . '>' . "\n";
			}
		}
		$string .= '</rdf:Description>' . "\n";
	}
	return $string;
}

function output_xml($data,$recurse) {
	foreach ($data as $key => $array) {
		for($i=0;$i<count($array);$i++) {
			$subdata = $array[$i];
			$suffix = $subdata["y"];
			if ($subdata["y_type"] == "URI") {
				$suffix = get_remainder_from_long($suffix);
			}
			if ($subdata["z_type"] == "URI" && $recurse == true) {
				$string .= "\t" . '<' . $suffix . '>' . "\n";
				$node = $subdata["z"];
				$data2 = "";
				$data2 = sparql_subject($node);
				echo "\n<br/>HERE";
				echo "\n<br/>END";
				if ($data2 != "") {
					$string .= output_xml($data2,false);
				}
				$string .= "\t" . '</' . $suffix . '>' . "\n";
			} else {
				$string .= "\t" . '<' . $suffix . '>' . $subdata["z"] . '</' . $suffix . '>' . "\n";
			}
		}
	}
	return $string;
}

function sparql_subject($subject) {
	$sparql_endpoint = "http://data.openplanetsfoundation.org:5052/sparql/";
	$query = 'SELECT distinct ?y ?z where { <'.$subject.'> ?y ?z }';
	$query = urlencode($query);
	
	$url = $sparql_endpoint . "?result-format=sparql&stylesheet=&query-lang=sparql&query=" . $query;

	$data = handleSPARQL3($url);
	$data = re_sort_yz($data,$subject);
	
	return $data;

}

function sparql_subject2($subject,$exclusions) {
	$sparql_endpoint = "http://data.openplanetsfoundation.org:5052/sparql/";
	
	$array = get_subject_predicate($subject);
	$subject = $array["subject"];
	$predicate = $array["predicate"];

	$query = get_prefix();        

	if ($predicate == "") {
        	$query .= 'SELECT distinct ?g ?y ?z where { GRAPH ?g { <'.$subject.'> ?y ?z ';
	} else {
        	$query .= 'SELECT distinct ?g ?z where { GRAPH ?g { <'.$subject.'> '.$predicate.' ?z ';
	}
	for ($i=0;$i<count($exclusions);$i++) {
		$query .= ' FILTER (?g != <'.$exclusions[$i].'>) ';
	}
	$query .= ' } }';

        $query = urlencode($query);

        $url = $sparql_endpoint . "?result-format=sparql&stylesheet=&query-lang=sparql&query=" . $query;
	$data = handleSPARQL3($url);
        $data = re_sort_yz($data,$subject);
	if ($predicate != "" && $data != "") {
		$data = re_insert_predicate($data,$predicate);
	}

        return $data;
}

function re_insert_predicate($data,$predicate) {
	foreach ($data as $key => $values) {
		for ($i=0;$i<count($values);$i++) {
			$data[$key][$i]["y_type"] = "URI";
			$data[$key][$i]["y"] = $predicate;
		}
	}
	return $data;
}

function sparql_graphs($subject,$exclusions) {
	$sparql_endpoint = "http://data.openplanetsfoundation.org:5052/sparql/";
	
	$array = get_subject_predicate($subject);
	$subject = $array["subject"];
	$predicate = $array["predicate"];
        
	$query = get_prefix();        
	
	if ($predicate == "") {
        	$query .= 'SELECT distinct ?z where { GRAPH ?z { <'.$subject.'> ?p ?o ';
	} else {
        	$query .= 'SELECT distinct ?z where { GRAPH ?z { <'.$subject.'> '.$predicate.' ?o ';
	}
	for ($i=0;$i<count($exclusions);$i++) {
		$query .= ' FILTER (?z != <'.$exclusions[$i].'>) ';
	}
	$query .= ' } }';
	
        $query = urlencode($query);

        $url = $sparql_endpoint . "?result-format=sparql&stylesheet=&query-lang=sparql&query=" . $query;
        
	$data = handleSPARQL3($url);
        $data = re_sort_yz($data,$subject);

        return $data;
}

function get_subject_predicate($subject) {
	$array["subject"] = $subject;
	$array["predicate"] = "";	
	$predicate = substr($subject,strrpos($subject,"/")+1,strlen($subject));
	if (strpos($predicate,":") > 0) {
		if (substr($predicate,0,7) == "http://") {
			$predicate = "<" . $predicate . ">";
		}
		$array["predicate"] = $predicate;
		$array["subject"] = substr($subject,0,strrpos($subject,"/"));
	}
	return $array;
}

function sparql_subject_graph($subject,$graph) {
	$sparql_endpoint = "http://data.openplanetsfoundation.org:5052/sparql/";
	
	$array = get_subject_predicate($subject);
	$subject = $array["subject"];
	$predicate = $array["predicate"];

	$query = get_prefix();        
	if ($predicate == "") {
		$query .= 'SELECT distinct ?g ?y ?z where { GRAPH ?g { <'.$subject.'> ?y ?z ';
	} else {
		$query .= 'SELECT distinct ?g ?z where { GRAPH ?g { <'.$subject.'> ' .$predicate. ' ?z ';
	}
	$query .= ' FILTER (?g = <'.$graph.'>) ';
	$query .= ' } }';
	$query = urlencode($query);

        $url = $sparql_endpoint . "?result-format=sparql&stylesheet=&query-lang=sparql&query=" . $query;
        
	$data = handleSPARQL3($url);
        $data = re_sort_yz($data,$subject);
	if ($predicate != "" && $data != "") {
		$data = re_insert_predicate($data,$predicate);
	}

        return $data;
}

function re_sort($data) {
	for ($i=0;$i<count($data);$i++) {
		$output = $data[$i];
		unset($output["x"]);
		unset($output["x_type"]);
		$data2[$data[$i]["x"]][] = $output;
	}
	return $data2;
}

function re_sort_xy($data,$predicate,$field) {
	$subject = "";
	for ($i=0;$i<count($data);$i++) {
		$output = $data[$i];
		$subject = $output["x"] . "#" . $field;
		unset($output["x"]);
		unset($output["x_type"]);
		$output["y"] = get_long_from_short($predicate);
		$output["y_type"] = "URI";
		$data2[$subject][] = $output;
	}
	return $data2;
}

function re_sort_yz($data,$subject) {
	for ($i=0;$i<count($data);$i++) {
		$output = $data[$i];
		unset($output["x"]);
		unset($output["x_type"]);
		$data2[$subject][] = $output;
	}
	return $data2;
}


function handleSPARQL3($file) {
        $url = $file;
        //print_r($url);
        //$root=bxmlio_load("inc/test.xml");
        $root=bxmlio_load($url);
        $resultsNode = bxmlio_find($root, "RESULTS");
        for ($i = 0; $i < bxmlio_count($resultsNode, "RESULT"); $i++) {
           $resultNode = bxmlio_find($resultsNode, "RESULT[" . $i . "]");
                for ($j = 0; $j < bxmlio_count($resultNode,"BINDING"); $j++) {
                        $nodetype = bxmlio_find($resultNode,"BINDING[" . $j . "]#NAME");
                        $dataNode = bxmlio_find($resultNode,"BINDING[" . $j . "]");
                        if (bxmlio_find($dataNode,"URI#") != "") {
                                $data[$i][$nodetype . "_type"] = "URI";
                                $data[$i][$nodetype] = bxmlio_find($dataNode,"URI#");
                        } elseif ((bxmlio_find($dataNode,"BNODE#") != "") && ($flag != "1")) {
                                //$propertyNode = getNode($dataNode,"BNODE");
                                $data[$i][$nodetype . "_type"] = "BNODE";
                                if ($bnodesDone[$data[$i]["labeluri"]] != "1") {
                                        $bnodesDone[$data[$i]["labeluri"]] = "1";
                                        $data[$i][$nodetype] = getBlankNodeData($data[$i]["labeluri"],$item);
                                }
                        } elseif ((bxmlio_find($dataNode,"BNODE#") != "") && ($flag == "1")) {
                                $data[$i][$nodetype . "_type"] = "BNODE";
                                $data[$i][$nodetype] = bxmlio_find($dataNode,"BNODE#");
                        } else {
                                //$propertyNode = getNode($dataNode,"LITERAL");
                                $data[$i][$nodetype . "_type"] = "LITERAL";
                                $data[$i][$nodetype] = str_replace("&","&amp;",bxmlio_find($dataNode,"LITERAL#"));
                        }
                }
        }
        return $data;
}

function output_requested_headers($extension) {
	if ($extension == "html") {
		header('Content-type: text/html');
		echo html_headers();
	} elseif ($extension == "xml") {
		header('Content-type: text/xml');
		echo xml_headers();
	} else {
		header('Content-type: text/xml');
		echo rdf_headers();
	}
}

function output_requested_footers($extension) {
	if ($extension == "html") {
		echo html_footers();
	} elseif ($extension == "xml") {
		echo xml_footers();
	} else {
		echo rdf_footers();
	}
}

function output_requested_body($extension,$data,$excluded) {
	if ($extension == "html") {
		echo output_html($data,$excluded);
	} elseif ($extension == "xml") {
		echo output_xml($data);
	} else {
		echo output_rdf($data);
	}
}

function rdf_headers() {
	$string = '<?xml version="1.0" encoding="UTF-8"?>' .  "\n";
	$string .= '<!DOCTYPE rdf:RDF [' . "\n";
	
	$namespaces = get_rdf_namespaces();
	foreach ($namespaces as $short => $long) {
		$string .= "\t" . '<!ENTITY ' . $short . ' "' . $long .'">' . "\n";
	}
	$string .= ']>' . "\n\n";
	$string .= '<rdf:RDF';
	foreach ($namespaces as $short => $long) {
		$string .= ' xmlns:' . $short . '="' . $long .'"';
	}
	$string .= '>' . "\n";
	
	return $string;
}

function xml_headers() {
	$string = '<?xml version="1.0" encoding="utf-8"?>' . "\n";
	$string .= '<PRONOM-Report ';
	$namespaces = get_xml_namespaces();
	foreach ($namespaces as $short => $long) {
		if ($short == "pronom") {
			$string .= ' xmlns="' . $long .'"';
		} else {
			$string .= ' xmlns:' . $short . '="' . $long .'"';
		}
	}
	$string .= ">\n";
	
	return $string;
}

function xml_footers() {
	$string = '</PRONOM-Report>';

	return $string;
}

function rdf_footers() {
	$string = '</rdf:RDF>';

	return $string;
}

function get_xml_namespaces() {
	$array["pronom"] = "http://pronom.nationalarchives.gov.uk/#";
	return $array;
}

function get_rdf_namespaces() {
	$array["rdf"] = "http://www.w3.org/1999/02/22-rdf-syntax-ns#";
	$array["rdfs"] = "http://www.w3.org/2000/01/rdf-schema#";
	$array["dbpedia"] = "http://dbpedia.org/property/";
	$array["skos"] = "http://www.w3.org/2004/02/skos/core#";
	$array["foaf"] = "http://xmlns.com/foaf/0.1/";
	$array["owl"] = "http://www.w3.org/2002/07/owl#";
	$array["ore"] = "http://www.openarchives.org/ore/terms/";
	$array["dc"] = "http://purl.org/dc/elements/1.1/";
	$array["dcterms"] = "http://purl.org/dc/terms/";
	$array["ror"] = "http://rorweb.com/0.1/";
	$array["iana-mime"] = "http://id.iana.org/assignments/media-types/ontology/#";
	$array["opf-ref"] = "http://data.openplanetsfoundation.org/ref/ontology/default/#";
	$array["fitools"] = "http://data.openplanetsfoundation.org/ref/ontology/fitools/#";
	$array["droid"] = "http://data.openplanetsfoundation.org/ref/ontology/droid/#";
        $array["file"] = "http://data.openplanetsfoundation.org/ref/ontology/file/#";
        $array["fido"] = "http://data.openplanetsfoundation.org/ref/ontology/fido/#";
        $array["fits"] = "http://data.openplanetsfoundation.org/ref/ontology/fits/#";
        $array["tika"] = "http://data.openplanetsfoundation.org/ref/ontology/tika/#";
	return $array;
}

function get_remainder_from_long($string) {
	$namespaces = get_xml_namespaces();
	foreach ($namespaces as $short => $long) {
		if (substr($string,0,strlen($long)) == $long) {
			return str_replace($long,"",$string);
		}
	}
	return $string;
}

function get_short_from_long($string) {
	$namespaces = get_rdf_namespaces();
	foreach ($namespaces as $short => $long) {
		if (substr($string,0,strlen($long)) == $long) {
			$ret = str_replace($long,$short.":",$string);
			return str_replace($long,$short.":",$string);
		}
	}
	return $string;
}

function get_long_from_short($string) {
        $namespaces = get_rdf_namespaces();
        foreach ($namespaces as $short => $long) {
                if (substr($string,0,strpos($string,":")) == $short) {
                        $ret = str_replace($short.":",$long,$string);
                        return $ret;
                }
        }
        return $string;
}

function get_format_id_from_pronom_id($string) {
	$query_url = get_query_url();
	
	$prefix = get_prefix();

	if (substr($string,0,7) == "http://") {
		$query = $prefix . ' select distinct ?pronom_id where { ?pronom_id pronom:FileFormatIdentifier <'.$string.'> . <'.$string.'> pronom:IdentifierType "PUID" }';
	} else {
		$query = $prefix . ' select distinct ?pronom_id where { ?pronom_id pronom:FileFormatIdentifier ?x . ?x pronom:Identifier "'.$string.'" . ?x pronom:IdentifierType "PUID" }';
	}
	$url_query = urlencode($query);
	$l_query_url = $query_url . $url_query;
	$root = @bxmlio_load($l_query_url);
	$results = @bxmlio_find($root,"results");
	for ($r=0;$r<@bxmlio_count($results,"result");$r++) {
		$result = @bxmlio_find($results,"result[".$r."]");
		for ($b=0;$b<@bxmlio_count($result,"binding");$b++) {
			$binding = @bxmlio_find($result,"binding[".$b."]");
			$binding_value = @bxmlio_find($binding,"#name");
			$pronom_ids[] = @bxmlio_find($binding,"uri#");
		}
	}
	if (count($pronom_ids) > 1) {
		return $pronom_ids[0];
	} else {
		return $pronom_ids[0];
	}
}

function get_software_id_from_pronom_id($string) {
	$query_url = get_query_url();
	
	$prefix = get_prefix();

	$query = $prefix . ' select distinct ?pronom_id where { ?pronom_id pronom:SoftwareIdentifier ?x . ?x pronom:Identifier "'.$string.'" . ?x pronom:IdentifierType "PUID" }';
	$url_query = urlencode($query);
	$l_query_url = $query_url . $url_query;
	$root = @bxmlio_load($l_query_url);
	$results = @bxmlio_find($root,"results");
	for ($r=0;$r<@bxmlio_count($results,"result");$r++) {
		$result = @bxmlio_find($results,"result[".$r."]");
		for ($b=0;$b<@bxmlio_count($result,"binding");$b++) {
			$binding = @bxmlio_find($result,"binding[".$b."]");
			$binding_value = @bxmlio_find($binding,"#name");
			$pronom_ids[] = @bxmlio_find($binding,"uri#");
		}
	}
	if (count($pronom_ids) > 1) {
		return $pronom_ids[0];
	} else {
		return $pronom_ids[0];
	}
}

function process_result($uri,$mapping_array) {
	
	$data = sparql_subject($uri);
	$data = $data[$uri];

	$tool = "";

	foreach($data as $no => $values) {
		$predicate = $values["y"];
		$object = $values["z"];

		if ($predicate == "http://data.openplanetsfoundation.org/ref/ontology/default/#uses-tool") {
			$tool = get_tool($object);
			$mapping_array["tools"][$tool] = $tool;
		}

		if ($predicate == "http://data.openplanetsfoundation.org/ref/ontology/droid/#hasHit") {
			$data2 = sparql_subject($object);
			$data2 = $data2[$object];
			foreach ($data2 as $no2 => $values2) {
				$predicate2 = $values2["y"];
				$object2 = $values2["z"];
				if ($predicate2 == "http://data.openplanetsfoundation.org/ref/ontology/droid/#hasPronomPUID" 
				//|| $predicate2 == "http://data.openplanetsfoundation.org/ref/ontology/droid/#hasPronomURI"
				|| $predicate2 == "http://data.openplanetsfoundation.org/ref/ontology/droid/#hasFileDescription"
				|| $predicate2 == "http://data.openplanetsfoundation.org/ref/ontology/droid/#format_type"
				|| $predicate2 == "http://data.openplanetsfoundation.org/ref/ontology/droid/#format_version"
				|| $predicate2 == "http://data.openplanetsfoundation.org/ref/ontology/droid/#puid"
				)
				{
					$current_tool .= $object2 . '<br/>';
				}
			}
		}
	
		if ($predicate == "http://data.openplanetsfoundation.org/ref/ontology/file/#file_type") {
			$current_tool .= $object . '<br/>';
		}
	
		if ($predicate == "http://data.openplanetsfoundation.org/ref/ontology/fido/#puid" 
				//|| $predicate == "http://data.openplanetsfoundation.org/ref/ontology/droid/#hasPronomURI"
				|| $predicate == "http://data.openplanetsfoundation.org/ref/ontology/fido/#signaturename")
		{
					$current_tool .= $object . '<br/>';
		}

	}
	
	$mapping_array[$tool] .= $current_tool;	

	#print_r("Setting " . $tool . " to " . $current_tool . "<br/>");

	return $mapping_array;
}	

function get_tool($uri) {
	$data = sparql_subject($uri);
	$data = $data[$uri];
	foreach($data as $no => $values) {
		$predicate = $values["y"];
		$object = $values["z"];
		$string .= $object . ' <br/>';
	}
	return $string;
}
?>
