<?php

/* BXMLIO v0.03 (PI ignore)
 * Basic XML Input/Output Library
 * Toby Hunt (tjh05r)
 *
 * Distribution (in whole or part) is not permitted without prior permission
 * from the author. No warranties made and no liability accepted. Use at your
 * own risk. This message must be distributed with all software utilising this
 * library or any part of it.
 *
 * Note: Only minimal error checking is provided, especially for output and
 * search operations. Corrupt tree data is unlikely to be dealt with
 * gracefully.
 *
 *
 * Functions
 * =========
 *
 * bxmlio_load
 * -----------
 *
 * bxmlio_load( <filename> ) => <root of tree>
 * 
 * Loads an XML file, parsing it into a data tree.
 *
 * <filename> may be any resource that may be accepted by fopen(). URLs are
 * supported for those transports supported by any currently registered
 * protocol handlers.
 *
 * Returns NULL if an error occurred. Error information may be retrieved using
 * bxmlio_lastError().
 *
 *
 * bxmlio_save
 * -----------
 *
 * bxmlio_save( <root of tree>, <filename> )
 *
 * Saves a data tree to a file in XML format.
 *
 * <filename> may be any resource that may be accepted by fopen(). URLs are
 * supported for those transports supported by any currently registered
 * protocol handlers.
 *
 * Returns TRUE on success, FALSE if an error occurred. Error information may
 * be retrieved using bxmlio_lastError().
 * 
 *
 * bxmlio_find
 * -----------
 *
 * bxmlio_find( <root of tree>, <path to element> ) => <element>
 *
 * Returns the element (subtree, CDATA or attribute value) corresponding to
 * the given path.
 *
 * Returns NULL if an error occurred, or the tag could not be found. Error
 * information may be retrieved using bxmlio_lastError().
 *
 *
 * bxmlio_count
 * ------------
 *
 * bxmlio_count( <root of tree>, <path to element> ) => <number of matches>
 *
 * Returns the number of elements (subtrees, CDATA or attribute values) which
 * match the given path.
 *
 * Returns zero if an error occurred, or no matching element could be found.
 * Error information may be retrieved using bxmlio_lastError().
 *
 *
 * bxmlio_lastError
 * ----------------
 *
 * bxmlio_lastError() => <error information>
 *
 * Returns information about the last error which occurred.
 *
 * Returns NULL if no error has occurred since the start of the program, or
 * last call to bxmlio_clearError(). The returned error information is of the
 * form:
 *
 * array(
 * 	"code"		=> <short code for the error>
 *	"message"	=> <textual description of the error>
 * )
 *
 *
 * bxmlio_clearError
 * -----------------
 *
 * bxmlio_clearError()
 *
 * Clears the last error information.
 *
 *
 * Data formats
 * ============
 *
 * Path to an element
 * ------------------
 *
 * A path is used to select any of the pieces of data from a data tree using
 * a syntax much like a vastly simplified of the URL syntax.
 *
 * A path is a string of zero or more dot-seperated identifiers, followed by an
 * optional attribute selector. An identifier is the name of a node, optionally
 * followed by an integer index enclosed in square brackets (much like an array
 * index). An attribute selector is a hash symbol (#), followed by an optional
 * attribute name.
 *
 * The empty path selects the root of the tree. A dot seperated list of node
 * names behaves much like an object-oriented language, allowing child nodes
 * to be selected. If there is more than one node matching the given name at
 * any stage, the first match in the tree will be used. To select nodes other
 * than the first occurrence, array index notation may be used, with "bar[0]"
 * referring to the first instance of the tag "bar", "bar[1]" referring to the
 * second, etc.
 *
 * Attributes may be selected from within a node by appending a hash symbol,
 * followed by the name of the attribute. If no name is specified, the CDATA
 * (textual content) of the given node is returned.
 *
 * Examples (using bxmlio_find()):
 *   "foo.bar"		Return node "bar" from within node "foo".
 *   "b[3]"		Return the fourth "b" tag in the tree.
 *   "fruit#type"	Return the value of the "type" attribute from the
 *   			"fruit" node.
 *   "essay#"		Return the CDATA (textual content) of the "essay" node.
 *   "a[2].b.c[1]#x"	Return the value of the "x" attribute from the second
 *   			"c" attribute in the "b" node, within the third "a"
 *   			node.
 *
 *
 * Data tree
 * ---------
 *
 * Each node in the tree is of the form:
 *
 * array(
 *	"name"		=> <node name>
 *	"attributes"	=> <array of "<name> => <value>" pairs>
 *	"children"	=> <array of integer indexed child nodes>
 *	"cdata"		=> <string containing unencoded CDATA>
 * )
 *
 */

$bxmlio_version = 0.03;

function bxmlio_lastError() {
	global $bxmlio__error;
	if (isset($bxmlio__error)) {
		switch ($bxmlio__error) {
		case "oddequals": $msg = "Syntax error in tag - unexpected '='"; break;
		case "oddslash": $msg = "Syntax error in tag - unexpected '/'"; break;
		case "piend": $msg = "Syntax error in processing instruction - missing '?'"; break;
		case "eof": $msg = "Unexpected end of file"; break;
		case "quotematch": $msg = "Mismatched quotes"; break;
		case "emptytag": $msg = "Syntax error in tag - missing tag name"; break;
		case "attrclose": $msg = "Syntax error in tag - attribute found in closing tag"; break;
		case "tagmatch": $msg = "Mismatched open/close tags"; break;
		case "open": $msg = "File open failed"; break;
		case "write": $msg = "File write failed"; break;
		case "notfound": $msg = "Element not found"; break;
		default: $msg = "Unknown error condition '" . $bxmlio__error . "'";
		}
		return array("code" => $bxmlio__error, "message" => $msg);
	} else {
		return NULL;
	}
}


function bxmlio_clearError() {
	global $bxmlio__error;
	$bxmlio__error = NULL;
}


function bxmlio__makeNode($array) {
	global $bxmlio__error;
	$state = 0;
	$attributes = array();
	for ($i = 1; $i < count($array);) {
		if ($array[$i] == '=') {
			$bxmlio__error = "oddequals";
			return NULL;
		} else if ($array[$i] == '/') {
			if ($i + 1 < count($array)) {
				$bxmlio__error = "oddslash";
				return NULL;
			} else {
				$i++;
			}
		} else {
			$name = html_entity_decode($array[$i]);
			$i++;
			if ($i + 1 < count($array) && $array[$i] == '=') {
				$value = $array[$i + 1];
				$i += 2;
			} else {
				$value = "";
			}
			$attributes[$name] = html_entity_decode($value);
		}
	}
	return array(
		"name" => $array[0],
		"attributes" => $attributes,
		"children" => array(),
		"cdata" => ""
	);
}


function bxmlio_load($filename) {
	global $bxmlio__error;
	if ($handle = fopen($filename, "rb")) {
		$stack = array();
		array_push($stack, bxmlio__makeNode(array("<root>")));
		$char = fgetc($handle);
		while ($char !== FALSE) {
			if ($char == '<') {
				$inSQuote = FALSE;
				$inDQuote = FALSE;
				$tagArray = array();
				$tagAcc = "";
				$char = fgetc($handle);
				while ($char !== FALSE && ($char != '>' || $inSQuote || $inDQuote)) {
					$quoteStateChange = FALSE;
					if ($char == '\'') {
						if (!$inDQuote) {
							$inSQuote = !$inSQuote;
							$quoteStateChange = TRUE;
						}
					} else if ($char == '"') {
						if (!$inSQuote) {
							$inDQuote = !$inDQuote;
							$quoteStateChange = TRUE;
						}
					}
					if ($char == ' ' || $char == '\t' || $char == '=' || $char == '/' || $char == '?') {
						if (!$inSQuote && !$inDQuote) {
							if ($tagAcc != "") {
								$tagArray[] = $tagAcc;
								$tagAcc = "";
							}
							if ($char == '=' || $char == '/' || $char=='?') $tagArray[] = $char;
						} else {
							$tagAcc .= $char;
						}
					} else {
						if (!$quoteStateChange) $tagAcc .= $char;
					}
					$char = fgetc($handle);
				}
				if ($char != '>') {
					$bxmlio__error = "eof";
					fclose($handle);
					return NULL;
				}
				if ($inSQuote || $inDQuote) {
					$bxmlio__error = "quotematch";
					fclose($handle);
					return NULL;
				}
				if ($tagAcc != "") $tagArray[] = $tagAcc;
				if (count($tagArray) < 1) {
					$bxmlio__error = "emptytag";
					fclose($handle);
					return NULL;
				}
				if ($tagArray[0] == '/') {
					$node = array_pop($stack);
					if (strcasecmp($tagArray[1], $node["name"]) != 0)  {
						$bxmlio__error = "tagmatch";
						fclose($handle);
						return NULL;
					}
					if (count($tagArray) != 2) {
						$bxmlio__error = "attrclose";
						fclose($handle);
						return NULL;
					}
					$node["cdata"] = html_entity_decode(trim($node["cdata"]));
					$stack[count($stack) - 1]["children"][] = $node;
				} else if ($tagArray[count($tagArray) - 1] == '/') {
					$node = bxmlio__makeNode($tagArray);
					if ($node === NULL) {
						fclose($handle);
						return NULL;
					}
					$node["cdata"] = html_entity_decode(trim($node["cdata"]));
					$stack[count($stack) - 1]["children"][] = $node;
				} else if ($tagArray[0] == '?') {
					if ($tagArray[count($tagArray) - 1] != '?') {
						$bxmlio__error = "piend";
						fclose($handle);
						return NULL;
					}
				} else {
					$node = bxmlio__makeNode($tagArray);
					if ($node === NULL) {
						fclose($handle);
						return NULL;
					}
					array_push($stack, $node);
				}
			} else {
				if ($char < ' ') $char = ' ';
				if ($char != ' ' || substr($stack[count($stack) - 1]["cdata"], -1) != ' ') {
					$stack[count($stack) - 1]["cdata"] .= $char;
				}
			}
			$char = fgetc($handle);
		}
		fclose($handle);
		if (count($stack) != 1) {
			$bxmlio__error = "tagmatch";
			return NULL;
		}
		return $stack[0]["children"][0];
	} else {
		$bxmlio__error = "open";
		return NULL;
	}
}


function bxmlio__outputNode($handle, $node) {
	global $bxmlio__error;
	if (fwrite($handle, "<" . $node["name"]) === FALSE) return FALSE;
	foreach ($node["attributes"] as $name => $value) {
		if (fwrite($handle, " " . $name . "=\"" . htmlentities($value) . "\"") === FALSE) return FALSE;
	}
	if (count($node["children"])) {
		if (fwrite($handle, ">") === FALSE) return FALSE;
		foreach ($node["children"] as $subnode) {
			if (bxmlio__outputNode($handle, $subnode) === FALSE) return FALSE;
		}
		if (fwrite($handle, htmlentities($node["cdata"]) . "</" . $node["name"] . ">") === FALSE) return FALSE;
	} else if (strlen($node["cdata"])) {
		if (fwrite($handle, ">" . htmlentities($node["cdata"]) . "</" . $node["name"] . ">") === FALSE) return FALSE;
	} else {
		if (fwrite($handle, "/>") === FALSE) return FALSE;
	}
	fwrite($handle,"\n");
	return TRUE;
}


function bxmlio_save($root, $filename, $mode) {
	global $bxmlio__error;
	if ($handle = fopen($filename, $mode)) {
		if (!bxmlio__outputNode($handle, $root)) {
			fclose($handle);
			$bxmlio__error = "write";
			return FALSE;
		}
		fclose($handle);
		return TRUE;
	} else {
		$bxmlio__error = "open";
		return FALSE;
	}
}


function bxmlio_find($root, $path) {
	global $bxmlio__error;
	if ($path == "") return $root;
	$pathsplit = explode("#", $path);
	if (strlen($pathsplit[0]) > 0) {
		$pathbits = explode(".", $pathsplit[0]);
		foreach ($pathbits as $id) {
			unset($tarray);
			if (preg_match("/^(.*)\\[(.*)\\]$/", $id, $idbits)) {
				$name = $idbits[1];
				$index = $idbits[2];
			} else {
				$name = $id;
				$index = 0;
			}
			foreach ($root["children"] as $node) {
				if (strcasecmp($node["name"], $name) == 0) $tarray[] = $node;
			}
			if ($index < 0 || $index >= count($tarray)) {
				$bxmlio__error = "notfound";
				return NULL;
			}
			$root = $tarray[$index];
		}
	}
	if (count($pathsplit) > 1) {
		if (strlen($pathsplit[1])) {
			foreach ($root["attributes"] as $name => $value) {
				if (strcasecmp($name, $pathsplit[1]) == 0) return $value;
			}
			return NULL;
		} else {
			return $root["cdata"];
		}
	}
	return $root;
}


function bxmlio_count($root, $path) {
	global $bxmlio__error;
	$pathsplit = explode("#", $path);
	if (strlen($pathsplit[0]) > 0) {
		$pathbits = explode(".", $pathsplit[0]);
		foreach ($pathbits as $id) {
			unset($tarray);
			if (preg_match("/^(.*)\\[(.*)\\]$/", $id, $idbits)) {
				$name = $idbits[1];
				$index = $idbits[2];
			} else {
				$name = $id;
				$index = 0;
				unset($idbits);
			}
			foreach ($root["children"] as $node) {
				if (strcasecmp($node["name"], $name) == 0) $tarray[] = $node;
			}
			if ($index < 0 || $index >= count($tarray)) {
				$bxmlio__error = "notfound";
				return 0;
			}
			$root = $tarray[$index];
		}
	} else {
		$tarray = array($root);
	}
	if (count($pathsplit) > 1) {
		if (strlen($pathsplit[1])) {
			foreach ($root["attributes"] as $name => $value) {
				if (strcasecmp($name, $pathsplit[1]) == 0) return 1;
			}
			return 0;
		} else {
			return strlen($root["cdata"]) > 0 ? 1 : 0;
		}
	} else {
		if (isset($idbits)) return count($tarray) > 0 ? 1 : 0;
		return count($tarray);
	}
}

?>
