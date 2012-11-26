<?php
	include('settings.php');
	
	error_reporting(E_ALL ^ E_NOTICE);
	
	MYSQL_CONNECT($server, $cuser, $ppassword) or die ( "<H3>Server unreachable</H3>");
	MYSQL_SELECT_DB($database) or die ( "<H3>Database non existent</H3>");

	// Take the path to the files. 

	$path = $_GET["path"];
	if ($path == "" || $path == null) {
		$path = $argv[1];
	}

	if ($path == "" || $path == null) {
		echo "NO PATH";
		exit();
	}

	$path = str_replace($root_path,"",$path);
	
	// GET ALL THE FILES AND NAMES FOR THE BOXES WE DRAW LATER
	$files = get_files($path);

	// GET ALL THE DIFFERENT RESULT IDS.
	$result_ids = get_result_ids($path);

	// GET RESULT POSITIONS
	$positions = get_result_positions($path,$result_ids);
#print_r($positions);
	
// GET TOOL INTERVALS IN EACH RESULT POSITION (HOPEFULLY THE CLEVER BIT!)
	$tool_intervals = get_tool_intervals($path);
#print_r($tool_intervals);
	
	$points = get_data($path,$positions,$tool_intervals);
#print_r($points);
	
	$data_string = format_data($points);

	$y_axis_string = get_y_axis_string($positions);

	echo get_header($path);
	echo $y_axis_string;
	echo $data_string;
	echo end_graph();

	echo '</head>';
	echo '<body>

<div id="container" style="min-width: 400px; height: 400px; margin: 0 auto"></div>
<div align="center">
<div id="analysis_0" class="analysis analysis_0">
	<div class="title">Analysis Box<br/>(click a point above to get info)</div>
	<div id="tool_info_0" class="tool_info">
		<div class="tool_name">Tool Name: <span id="tool_name_0"></span></div>
		<div class="Date">Valid From: <span id="tool_date_0"></span></div>
		<div class="Date">Last MD5 (debug): <span id="last_md5_0"></span></div>
		<div class="info" id="info_0"></div>
	</div>
	<div id="files_0" class="files"></div>
</div>
<div id="analysis_1" class="analysis analysis_1">
	<div class="title">Analysis Box<br/>(click a point above to get info)</div>
	<div id="tool_info_1" class="tool_info">
		<div class="tool_name">Tool Name: <span id="tool_name_1"></span></div>
		<div class="Date">Valid From: <span id="tool_date_1"></span></div>
		<div class="Date">Last MD5 (debug): <span id="last_md5_1"></span></div>
		<div class="info" id="info_1"></div>
	</div>
	<div id="files_1" class="files"></div>
</div>
</div>
<script type="text/javascript">';
	
	echo write_javascript($points);
	echo '</script>';
	echo '</body></html>';
	

function write_javascript($points) {
	$string .= "\n\tvar last_side = 0;\n";
	$string .= "\n\tfunction show_files(x,y,tool_name,color) { \n";
	$string .= "\t\t" . 'if (last_side > 1) { last_side = 0; }' . "\n";
//	$string .= "alert(x + y + tool_name);";
	foreach ($points as $tool_name => $data) {
		for ($i=0;$i<count($data);$i++) {
			$point = $data[$i];
			$string .= "\t\t" . ' if ( x == ' . $point["x"] . ' && y == "' . $point["y"] . '" && tool_name == "'.$tool_name.'") {' . "\n";
			$string .= "\t\t\t" . 'document.getElementById("files_" + last_side).innerHTML = "'.$point["files"].'";' . "\n";
			$string .= "\t\t\t" . 'document.getElementById("info_" + last_side).innerHTML = "'.$point["label"].'";' . "\n";
			$string .= "\t\t\t" . 'document.getElementById("tool_name_" + last_side).innerHTML = "<b>'.$tool_name.'</b>";' . "\n";
			$string .= "\t\t\t" . 'document.getElementById("tool_date_" + last_side).innerHTML = "<b>" + new Date('.$point["x"].').toString().substring(0,15) + "</b>";' . "\n";
			$string .= "\t\t\t" . 'document.getElementById("last_md5_" + last_side).innerHTML = "'.$point["result_id"].'";' . "\n";
			$string .= "\t\t\t" . 'document.getElementById("analysis_" + last_side).style.border = "3px solid " + color;' . "\n";
			$string .= "\t\t\t" . 'last_side++;' . "\n";
			$string .= "\t\t}\n";
		}
	}
	$string .= "}\n";
	return $string;
}

function format_data($points,$positions) {
	$colors = get_colors();
	$symbols = get_symbols();
	$string = "series: [\n";
	foreach ($points as $tool_name => $data) {
		if (strpos($tool_name,"-") !== false) {
			$reduced_tool_name = substr($tool_name,0,strrpos($tool_name,"-"));
			$show_in_legend = "false";
		} else {
			$show_in_legend = "true";
			$reduced_tool_name = $tool_name;
		}
		if (!$tcolor[$reduced_tool_name]) {
			$tcolor[$reduced_tool_name] = array_pop($colors);
		}
		$tool_color = $tcolor[$reduced_tool_name];
		if (!$tsymbol[$reduced_tool_name]) {
			$tsymbol[$reduced_tool_name] = array_pop($symbols);
		}
		$tool_color = $tcolor[$reduced_tool_name];
		$tool_symbol = $tsymbol[$reduced_tool_name];
		$string .= "\t{\n";
		$string .= "\t\tname: '$tool_name',\n";
		$string .= "\t\tshowInLegend: $show_in_legend,\n";
		$string .= "\t\tmarker: {\n\t\t\tsymbol: '$tool_symbol'\n\t\t},\n";
		$string .= "\t\tcursor: 'pointer',\n";
		$string .= "\t\tpoint: { events: { click: function () { show_files ( this.x, this.y, \"".$tool_name."\", \"".$tool_color."\" ); }}},\n";
		$string .= "\t\tcolor: '$tool_color',\n";
		$string .= "\t\tdata: [\n";
		for ($i=0;$i<count($data);$i++) {
			$point = $data[$i];
			$string .= "\t\t\t" . '{name: "' . $point["label"] . '", x: ' . $point["x"] . ', y: ' . $point["y"] . '},' . "\n";
		}
		$string .= "\t\t]\n";
		$string .= "\t},\n";
	}
	$string = trim($string);
	$string = substr($string,0,strlen($string)-1);
	$string .= "],\n"; 

	return $string;
}

function get_y_axis_string($positions) {

	$max = 0;
	foreach ($positions as $key => $value) {
		$value = $value + 1;
		if ($value > $max) {
			$max = $value;
		}
	}

$string .= "yAxis: {
                title: {
                    text: 'Identification Type'
                },
                labels: {
                    enabled: false
                },
                min: 0,
                max: $max,
                minorGridLineWidth: 0,
                gridLineWidth: 0,
                alternateGridColor: null,
                plotBands: [
		";

	$query = 'select object from owl where subject="droid:hasFileDescription" and predicate="rdfs:seeAlso";';
	$res = mysql_query($query);
	$predicates[] = "droid:hasFileDescription";
	while ($array = mysql_fetch_array($res)) {
		$predicates[] = $array["object"];
	}

	for($i=0;$i<$max;$i++) {
		$label = "";
		foreach($positions as $id => $value) {
			if ($value == $i) {
				$count = 0;
				while ($label == "" and $count < count($predicates)) {
					$query = 'select object from triples where subject="result/'.$id.'" and predicate="'.$predicates[$count].'"';
					$res = mysql_query($query);
					$row = mysql_fetch_row($res);
					if (mysql_num_rows($res) > 0) {
						$label = $row[0]; 
						if ($predicates[$count] == "fits:format") {
							$query = 'select object from triples where subject="result/'.$id.'" and predicate="fits:version"';
							$res = mysql_query($query);
							$row = mysql_fetch_row($res);
							$label .= " " . $row[0];
						}
					}
					$count++;
				}
			}
		}
		$j = $i+1;
		if ($i % 2) {
$string .="	{
                    from: $i,
                    to: $j,
                    color: 'rgba(68, 170, 213, 0.1)',
                    label: {
			verticalAlign: \"top\",
			y: 10,
                        text: '".$label."',
                        style: {
                            color: '#606060',
	   		    fontSize: '0.8em'
                        }
                    }
                },
";
		} else {
$string .= " { 
                    from: $i,
                    to: $j,
                    color: 'rgba(0, 0, 0, 0)',
                    label: {
			verticalAlign: \"top\",
			y: 10,
                        text: '".$label."',
                        style: {
                            color: '#606060',
                            fontSize: '0.8em'
                        }
                    }
                },
";
		}
	}
	$string = trim($string);
	$string = substr($string,0,strlen($string)-1);
	$string .= "]},\n";

	return $string;
}

function get_colors() {
	$colors = array("#4572a7","#3d96ae","#aa4643","#80699b","#89a54e");
	return $colors;
}
function get_symbols() {
	$symbols = array("circle","square","diamond","triangle","triangle-down","circle","square","diamond","triangle","triangle-down");
	return $symbols;
}

function get_data($path,$positions,$tool_intervals) {
	$query = 'select distinct results.id,results.tool_id,tools.name,tools.version,tools.date from results inner join files on files.id=results.file_id inner join tools on tools.id=results.tool_id where files.relative_full_path like "'.$path.'%" and files.name != "README" order by tools.version;';
	$res = mysql_query($query);
	while ($row = mysql_fetch_array($res)) {

		$result_id = $row["id"];
		$tool_id = $row["tool_id"];
		$tool_name = $row["name"];
		if (!$tcolor[$tool_name]) {
			$tcolor[$tool_name] = array_pop($colors);
		}
		
		$date = $row["date"];
		$year = intval(substr($date,6,4));
		$month = intval(substr($date,3,2));
		$day = intval(substr($date,0,2));

		$x = "Date.UTC($year,$month,$day)";
		$y = $positions[$row["id"]] + $tool_intervals[$row["name"]];
//echo "Position for " . $row["id"] . " = " . $positions[$row["id"]] . " ( + " . $tool_intervals[$row["name"]] . ")\n";
		
		$label = "<b>" . $row["name"] . " " . $row["version"] . "</b><br/>";
		$label .= get_result_tooltip($result_id);
		
		$query = 'select distinct files.id,files.name from files inner join results on files.id=results.file_id where results.id="'.$result_id.'" and tool_id="'.$tool_id.'" order by files.id asc;';
		$res2 = mysql_query($query) or die($query);
		$name = $row["name"];
		$base_name = $name;
		if ($count[$tool_id] < 1) {
			$count[$base_name]++;
		}
		
		$number_of_files = mysql_num_rows($res2);
	
		$files = "";
		$switch = "even";
		while ($array = mysql_fetch_array($res2)) {
			$files .= "<span class='file_".$switch."'>" . $array["name"] . "</span>";
			if ($switch == "even") { $switch = "odd"; } else { $switch = "even"; }
		}
		
		$done = false;
		if ($number_of_files != 10) {
			$located_array = false;
			foreach($points as $src_name => $values) {
				if (substr($src_name,0,strlen($name)) == $name) {
					$last_num = count($points[$src_name]) - 1;
					$last_x = $points[$src_name][$last_num]["x"];
					$last_y = $points[$src_name][$last_num]["y"];
					$last_files = $points[$src_name][$last_num]["files"];
#if (substr($src_name,0,5) == "Droid") {
#	echo "$last_x to $x, $last_y to $y = comparing $name to $src_name<br/>";
#}
					if ($last_x != $x and $last_y == $y && files_contains($last_files,$files)) {
#if (substr($src_name,0,5) == "Droid") {
#	echo "$last_x to $x, $last_y to $y = switch from $name to $src_name<br/>";
#}
						$located_array = true;
						$name = $src_name;
					} elseif ($last_x == $x and $last_y == $y) {
						$located_array = true;
						$files = combine_files($points[$src_name][$last_num]["files"],$files);
						$points[$src_name][$last_num]["files"] = $files;
						$done = true;
					}
				}
			}
			if (!$located_array) {
				if ($count[$basename] >  0) {
					$name = $row["name"] . "-" . $count[$basename];
				} else {
					$name = $row["name"];
				}
				$count[$basename]++;
			}
			// SO we have the date(x) and points. 
			// Take y and see if you can append it to an existing array based upon x
			// If x and y already exists, then we need to append the files.
			// If we can't append, make a new line. 
			// Re-sort at the end and fill in the gaps, thus connect lines that change together. 
		} 
#echo "selected $name<br/><br/>";
		if (!$done) {
			$pos = count($points[$name]);
			$points[$name][$pos]["x"] = $x;
			$points[$name][$pos]["y"] = $y;	
			$points[$name][$pos]["label"] = $label;	
			$points[$name][$pos]["files"] = $files;
			$points[$name][$pos]["result_id"] = $result_id;
		}
/*	
		if (substr($name,0,5) == "Droid") {
			$array = $points[$name];
			echo "\n" . $name . "\n";
			print_r($array);
			echo "\n\n=============\n\n";
		}
*/
	}

	foreach ($count as $basename => $number) {
		if ($number == 1) {
			continue;
		}
		
		$required_dates = array();
		$query = "select date from tools where name='$basename' order by version asc";
		$res = mysql_query($query);
		while ($row = mysql_fetch_row($res)) {
			$required_dates[] = $row[0];
		}
		$required_dates = sort_date_array($required_dates);

		for ($i=0;$i<$number;$i++) {
			$name = $basename;
			if ($i>0) {
				$name = $name . "-" . $i;	
			}
			if ($points[$name] == null) {
				continue;
			}
			$first = $points[$name][0]["x"];
			$last = $points[$name][count($points[$name])-1]["x"];
				
			if ($first != $required_dates[0]) {
				for ($j=0;$j<count($required_dates);$j++) {
					if ($required_dates[$j] == $first) {
						$pos = $j-1;
					}
				}
				$date_needed = $required_dates[$pos];
				$points = make_connection($points,$name,$date_needed,0,$tool_id,$count);	
			}
			$first = $points[$name][0]["x"];
			$active = false;
			$k=0;
			for ($j=0;$j<count($required_dates);$j++) {
				$required_date = $required_dates[$j];
				if ($required_date == $first) {
					$active = true;
					$k=0;
				}
				if ($required_date == $last) {
					$already_connected = already_connected($basename,$last,$points,$count);
					$pos = $j+1;
					if ($required_dates[$pos] != "") {
#echo "Last date for $name at $k = $current_date != $required_date\n<br/>";
						 $required_date = $required_dates[$pos];
						 $points = make_connection($points,$name,$required_date,$k,$tool_id,$count);
					}
					$active = false;
					$k=0;
				}
				if ($active) {
					$current_date = $points[$name][$k]["x"];
					if ($current_date != $required_date) {
# echo "Current date for $name at $k = $current_date != $required_date\n<br/>";
						$points = make_connection($points,$name,$required_date,$k,$tool_id,$count);
					}
					$k++;
				}
			}
			$active = false;
		}
		
	}

	return $points;
}

function already_connected($basename,$last,$points,$count) {
	foreach ($count as $basename => $number) {
		
	}
}

function sort_date_array($dates) {
	foreach ($dates as $num => $string) {
		$orig = $string;
		$string = str_replace("Date.UTC(","",$string);
		$string = str_replace(")","",$string);
		$parts = explode("/",$string);
		$year = $parts[2];
		$month = $parts[1];
		$day = $parts[0];
		$time = mktime(0,0,0,$month,$day,$year);
		$out[] = $time;
		$ret[$time] = $orig;
	}
	
	asort($out);
	
	for($i=0;$i<count($out);$i++) {
		$string = $ret[$out[$i]];
		$parts = explode("/",$string);
                $year = $parts[2];
                $month = $parts[1];
                $day = $parts[0];
		if (substr($month,0,1) == 0) {
			$month = substr($month,1,strlen($month));
		}
		if (substr($day,0,1) == 0) {
			$day = substr($day,1,strlen($day));
		}
		$string = $year . "," . $month . "," . $day;		
		$string = "Date.UTC("  . $string . ")";
		$back[] = $string;
	}

	return $back;
}

function make_connection($points,$src_name,$date_needed,$pos_got,$tool_id,$count) {
	if (count($points[$src_name])== $pos_got + 1) {
		$last = true;
	}
	$got_files = $points[$src_name][$pos_got]["files"];
	foreach ($count as $basename => $number) {
		for ($i=0;$i<$number;$i++) {
			if (substr($src_name,0,strlen($basename)) != $basename) {
				continue;
			}
			$name = $basename;
			if ($i>0) {
				$name = $name . "-" . $i;	
			}
			if ($name == $src_name) {
				continue;
			}
			for ($j=0;$j<count($points[$name]);$j++) {
				if ($points[$name][$j]["x"] == $date_needed) {
					$files = $points[$name][$j]["files"];
					if (files_contains_any($files,$got_files)) {
						if ($pos_got == 0 && !$last) {
# echo "Attempting unshift with $src_name and $name $j<br/>";
							array_unshift($points[$src_name],$points[$name][$j]);
							return $points;
						} elseif ($last) {
							array_push($points[$src_name],$points[$name][$j]);
							return $points;
						} else  {
# echo "Attempting splice with $src_name at $pos_got and $name $j<br/>\n";
							$input = $points[$src_name];
							$array = array();
							$array[] = $points[$name][$j];
# echo "SPLICING @ POS $pos_got\n";
# print_r($input);
							array_splice($input,$pos_got,0,$array);
# echo "TO \n";
# print_r($array);
# echo "========\n";
							$points[$src_name] = $input;
							return $points;
						}
					}
				}
			}
		}
	}
	return $points;
}

function files_contains_any($old_files,$new_files) {
	$out = array();
	$old_files = explode("</span>",$old_files);
	for($i=0;$i<count($old_files);$i++) {
		$string = $old_files[$i];
		if ($string != "") {
			$string .= '</span>';
			$out[] = strip_tags($string);
		}
	}
	$old_files = $out;

	$new_files = explode("</span>",$new_files);
	$out = array();
	for($i=0;$i<count($new_files);$i++) {
		$string = $new_files[$i];
		if ($string != "") {
			$string .= '</span>';
			$out[] = strip_tags($string);
		}
	}
	$new_files = $out;

	for($i=0;$i<count($new_files);$i++) {
		if (in_array($new_files[$i],$old_files)) {
			return true;
		}
	}
	return false;
}

function files_contains($old_files,$new_files) {
	$out = array();
	$old_files = explode("</span>",$old_files);
	for($i=0;$i<count($old_files);$i++) {
		$string = $old_files[$i];
		if ($string != "") {
			$string .= '</span>';
			$out[] = strip_tags($string);
		}
	}
	$old_files = $out;

	$new_files = explode("</span>",$new_files);
	$out = array();
	for($i=0;$i<count($new_files);$i++) {
		$string = $new_files[$i];
		if ($string != "") {
			$string .= '</span>';
			$out[] = strip_tags($string);
		}
	}
	$new_files = $out;

	for($i=0;$i<count($new_files);$i++) {
		if (!in_array($new_files[$i],$old_files)) {
			return false;
		}
	}
	return true;
}

function combine_files($old_files,$new_files) {
	$out = array();
	$old_files = explode("</span>",$old_files);
	for($i=0;$i<count($old_files);$i++) {
		$string = $old_files[$i];
		if ($string != "") {
			$string .= '</span>';
			$out[] = strip_tags($string);
		}
	}
	$old_files = $out;

	$new_files = explode("</span>",$new_files);
	$out = array();
	for($i=0;$i<count($new_files);$i++) {
		$string = $new_files[$i];
		if ($string != "") {
			$string .= '</span>';
			$out[] = strip_tags($string);
		}
	}
	$new_files = $out;

	$out = array_merge($old_files,$new_files);

	$out = array_unique($out);
	
	asort($out);
	
	$files = "";
	$switch = "even";
	for ($i=0;$i<count($out);$i++){
		$files .= "<span class='file_".$switch."'>" . $out[$i] . "</span>";
		if ($switch == "even") { $switch = "odd"; } else { $switch = "even"; }
	}
	
	return $files;
	
}


$result_data = array();
function get_result_tooltip($result_id) {
	global $result_data;

	if ($result_data[$result_id] != "") {
		return $result_data[$result_id];
	}

	$query = 'select predicate, object from triples where subject="result/'.$result_id.'";';
	$res = mysql_query($query);
	while ($array = mysql_fetch_array($res)) {
		$string .= "    " . $array["predicate"] . ': <b>' . htmlentities($array["object"]) . '</b><br/>';
	}
	
	$result_data[$result_id] = $string;
	
	return $string;

}

function get_tool_intervals($path) {
	$query = 'select tools.name from results inner join files on files.id=results.file_id inner join tools on tools.id=results.tool_id where files.relative_full_path like "'.$path.'%" and files.name != "README" group by tools.name';
	$res = mysql_query($query);
	$count = mysql_num_rows($res) + 1;
	$interval = 1 / $count;
	$current = $interval;
	while ($row = mysql_fetch_array($res)) {
		$intervals[$row["name"]] = round($current,2);
		$current = $current + $interval;
	}
	return $intervals;
}

function get_result_positions2($path,$result_ids) {
	foreach (array_keys($result_ids) as $result) {
		if ($done[$result]) {
			continue;
		}
		$matches = array();
		if (is_array($result_ids[$result])) {
			$matches = $result_ids[$result];
		}
		$query = 'select object from owl where predicate="owl:sameAs" and subject="result/' . $result . '";';
		$res = mysql_query($query);
		while ($array = mysql_fetch_array($res)) {
			$object = $array["object"];
			$object = str_replace("result/","",$object);
			if (!in_array($object,$matches)) {
				$result_ids[$result][] = $object;
				$done[$object] = 1;
				unset($result_ids[$object]);
			}
		}
#		$query = 'select subject from owl where predicate="owl:sameAs" and object="result/' . $result . '";';
#		$res = mysql_query($query);
#		while ($array = mysql_fetch_array($res)) {
#			$object = $array["subject"];
#			$object = str_replace("result/","",$object);
#			if (!in_array($object,$matches)) {
#				$result_ids[$result][] = $object;
#				$done[$object] = 1;
#				unset($result_ids[$object]);
#			}
#		}
	}

#print_r($result_ids);
#exit();

	$uniques = array();
	$skip = array();
	foreach($result_ids as $result => $matches) {
		if(!in_array($result,$skip)) {
			$count++;
			$uniques[$result] = count($matches)+1;
			for ($i=0;$i<count($matches);$i++) {
				$skip[] = $matches[$i];
			}
		}
	}
	arsort($uniques);
	
	$max = count($uniques) - 1;

	if ($max % 2) {
		$direction = "down";
		$start = $max / 2;
		$start = ceil($start);
	} else {
		$direction = "up";
		$start = $max / 2;
	}

	$current = $start;
	$diff = 1;
	foreach($uniques as $key => $count) {
		if ($count > 1) {
			$number = $count+1;
			$matches = $result_ids[$key];
			$position[$key] = $current;
			for($i=0;$i<count($matches);$i++) {
				$position[$matches[$i]] = $current;
			}
		} else {
			$position[$key] = $current;
		}
		if ($direction == "up") {
			$current = $current + $diff;
			$direction = "down";
		} else {
			$current = $current - $diff;
			$direction = "up";
		}
		$diff++;
	}

	return $position;
}

function get_result_positions($path,$result_ids) {
	global $root_path;	
	
	$check = $result_ids;
	foreach (array_keys($check) as $result) {
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
      <".$prefix."result/".$result."> owl:sameAs ?x 
}";
		$handle = fopen("/tmp/query.sparql","w");
		fwrite($handle,$file_string);
		fclose($handle);
		$dir = $root_path . "bin";
		chdir($dir);
		$cmd = 'sh pellet.sh query -q /tmp/query.sparql owl.rdf';
		$ret = array();
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
			$object = $results[$i];
			if (!in_array($object,$matches) && $object != $result) {
                                $result_ids[$result][] = $object;
				$done[$object] = 1;
				unset($result_ids[$object]);
                        }
		}

#		$query = 'select subject from owl where predicate="owl:sameAs" and object="result/' . $result . '";';
#		$res = mysql_query($query);
#		while ($array = mysql_fetch_array($res)) {
#			$object = $array["subject"];
#			$object = str_replace("result/","",$object);
#			if (!in_array($object,$matches)) {
#				$result_ids[$result][] = $object;
#			}
#		}
	}

//	print_r($result_ids);	
	$uniques = array();
	$skip = array();
	foreach($result_ids as $result => $matches) {
		if(!in_array($result,$skip)) {
			$count++;
			$uniques[$result] = count($matches)+1;
			for ($i=0;$i<count($matches);$i++) {
				$skip[] = $matches[$i];
			}
		}
	}
	arsort($uniques);
	
	$max = count($uniques) - 1;

	if ($max % 2) {
		$direction = "down";
		$start = $max / 2;
		$start = ceil($start);
	} else {
		$direction = "up";
		$start = $max / 2;
	}

	$current = $start;
	$diff = 1;
	foreach($uniques as $key => $count) {
		if ($count > 1) {
			$number = $count+1;
			$matches = $result_ids[$key];
			$position[$key] = $current;
			for($i=0;$i<count($matches);$i++) {
				$position[$matches[$i]] = $current;
			}
		} else {
			$position[$key] = $current;
		}
		if ($direction == "up") {
			$current = $current + $diff;
			$direction = "down";
		} else {
			$current = $current - $diff;
			$direction = "up";
		}
		$diff++;
	}

	return $position;
}

function get_result_ids($path) {

	$query = "select distinct results.id from results inner join files on results.file_id=files.id where files.relative_full_path like '$path%' and files.name != 'README';";
	
	$res = mysql_query($query);
	while ($array = mysql_fetch_array($res)) {
		$results[$array["id"]] = array();
	}
	
	return $results;

}


// GET ALL THE FILES
function get_files($path) {

	$query = "select id,name from files where files.relative_full_path like '$path%' and files.name != 'README';";
	$res = mysql_query($query);
	while ($array = mysql_fetch_array($res)) {
		$files[$array["id"]] = $array["name"];
	}
	$res = ""; $query = ""; $array = "";
	
	return $files;
}

function get_header($path) {
	global $root_path;

	$string .= '<!DOCTYPE html>
<html>
<head>
  <meta http-equiv="content-type" content="text/html; charset=UTF-8">
  <title>REF</title>
  
  <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>
  
  <link rel="stylesheet" type="text/css" href="/css/normalize.css">
  <link rel="stylesheet" type="text/css" href="/css/result-light.css">
  
  <style type="text/css">
.analysis_0 {
	float: left;
	margin-left: 2em;
}
.analysis_1 {
	float: right;
	margin-right: 1em;
}
.files {
	display: inline;
	float: right;
	width: 29%;
	margin: 0.3em;
	border: 2px solid #707070;
        border-radius: 15px;
	height: 210px;
	margin-top: 20px;
	padding-top: 10px;
	padding-bottom: 10px;
	overflow: auto;
}
.file_even {
	display: block;
	background: #ebf6fa;
}
.file_odd {
	display: block;
	background: #e7e7e7;
}
.tool_info {
	text-align: left;
	width: 63%;
	margin: 0.3em;
	padding: 0.5em;
	float: left;
}
.info {
	width: 100%;
	margin: 0.3em;
	height: 200px;
	overflow: auto;
	padding: 0.2em;
	border: 2px solid #707070;
	border-radius: 10px;
}
.analysis {
	border: 3px solid #707070;
	border-radius: 15px;
	height: 300px;
	width: 45%;
	display: block;
	padding: 0.5em;
	font-size: 0.8em;
	font-family: sans;
}
  </style>';


	$path = trim($path);
	if (substr($path,strlen($path)-1,strlen($path)) == "/") {
		$path = substr($path,0,strlen($path)-1);
	}
	$ext = substr($path,strrpos($path,"/")+1,strlen($path));
	$ext = substr($ext,0,strpos($ext,"_"));

	$file_path = $strip_path . $path . "/README";
	$handle = fopen($strip_path . $path . "/README","r");
	if ($handle) {
		while (!feof($handle)) {
			$line = fgets($handle);
			$parts = explode("=",$line,2);
			$key = trim($parts[0]);
			$value = trim($parts[1]);
			if ($key == "dc:Description" or $key == "fitools:Numbers_Metadata_Summary") {
				$type .= " - " . $value;
			}
		}
		fclose($handle);
	}
	$type = trim($type);

	$string .= file_get_contents($root_path . "bin/header.txt");
        $string .=
"	title: {
                text: 'File Identification for $ext Files'
            },
            subtitle: {
                text: '10 Files of supposed Type $type - identified using 65 versions of 5 tools'
            },";
	return $string;
}

function end_graph() {
	global $root_path;
	$string = file_get_contents($root_path . "bin/footer.txt");
	return $string;
}


	// Find all the different results for these files for each version of the tool. 
	// Produce an array of all results (single)
	// Produce the result range array with the majority of results being in the middle! e.g. if 3 results bulk will be between 1 and 2 (min of 3)
	// Produce the version_date_result array
	// Group the files together for those who's version_data_result arrays match. 

	// Plot the version_date_result arrays in the plot location given, separating them by how many results for each tool there are in each range. 

	

?>
