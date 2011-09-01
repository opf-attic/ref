<?php
	function strip_quotes($string) {
		$string = trim($string);
		if (substr($string,0,1) == '"') {
			$string = substr($string,1,strlen($string));
			$string = str_replace("\"","",$string);
		}
		return $string;
	}
	function remove_predicates($res) {
		require('inc/connect_3store3.inc.php');
		while ($subjects_row = mysql_fetch_row($res)) {
			$subject = $subjects_row[0];
			if (strpos($subject,"://") > 0) {
				$comment = get_comment($subject);
				$id = get_short_name_from_long($subject);
				if (strpos($id,"://")>0) {
					$id = $subject;
					$name = $id;
					$array[] = array( "id"=>($id) ,"value"=>($name), "info"=>($comment) );
				}
			}
		}
		return $array;
	}
	function only_predicates($res) {
		require('inc/connect_3store3.inc.php');
		foreach ($res as $num => $values) {
			$subject = $values['value'];
			if (strpos($subject,"://") > 0) {
			} else {
				$array[] = $values;
			}
		}
		return $array;
	}

	function fetch_namespace_data($res) {
		require('inc/connect_3store3.inc.php');
		$row = mysql_fetch_row($res);
		$key = $row[0];
		$query = 'select distinct subject from rdf_data where subject like "'.$key.'%";';
		$res = mysql_query($query);
		while ($row = mysql_fetch_row($res)) {
			$id = get_short_name_from_long($row[0]);
			if ($id != "" && substr($id,strlen($id)-1,strlen($id)) != ":") {
				$array[] = $id;
			}
		}
		$query = 'select distinct predicate from rdf_data where predicate like "'.$key.'%";';
		$res = mysql_query($query);
		while ($row = mysql_fetch_row($res)) {
			$id = get_short_name_from_long($row[0]);
			if ($id != "" && substr($id,strlen($id)-1,strlen($id)) != ":") {
				$array[] = $id;
			}
		}
		$array = array_unique($array);
	}

	function get_from_subjects($subjects_res) {
		require('inc/connect_3store3.inc.php');
		while ($subjects_row = mysql_fetch_row($subjects_res)) {
			$subject = $subjects_row[0];
			$comment = get_comment($subject);
			$id = get_short_name_from_long($subject);
			$name = $id;
			if ($id != "") {
				$array[] = array( "id"=>($id) ,"value"=>($name), "info"=>($comment) );
			}
		}
		return $array;
	}

	function get_long_name_from_short($subject) {
		require('inc/connect_p2_registry.inc.php');
		$prefix = substr($subject,0,strpos($subject,":"));
		$item = substr($subject,strpos($subject,":")+1,strlen($subject));
		$query = 'SELECT namespace from rdf_namespaces where short_name="'.$prefix.'";';
		$res = mysql_query($query);
		$row = mysql_fetch_row($res);
		if ($row[0] == "") {
			return $subject;
		} else {
			return ($row[0] .$item);
		}
	}
	function get_short_name_from_long($subject) {
		require('inc/connect_p2_registry.inc.php');
		if (strpos($subject,"#") > 0) {
			$prefix = substr($subject,0,strpos($subject,"#"));
			$item = substr($subject,strpos($subject,"#")+1,strlen($subject));
		} else {
			$prefix = substr($subject,0,strrpos($subject,"/"));
			$item = substr($subject,strrpos($subject,"/")+1,strlen($subject));
		}
		$query = 'SELECT short_name from rdf_namespaces where namespace like "'.$prefix.'%"';
		$res = mysql_query($query);
		$row = mysql_fetch_row($res);
		if ($row[0] == "") {
			return "$subject";
		} else {
			return ($row[0] . ":" .$item);
		}
	}
	function get_comment($subject) {
		require('inc/connect_3store3.inc.php');
		$query = 'SELECT object from rdf_data where subject="'.$subject.'" and predicate="http://www.w3.org/2000/01/rdf-schema#comment";';
		$res = mysql_query($query);
		$row = mysql_fetch_row($res);
		return strip_quotes($row[0]);
	}
?>
