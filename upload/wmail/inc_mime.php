<?php

/* =============================================================== *\
|     WMail - vBulletin Web-Mail Client for POP3/SMTP mailboxes     |
+-------------------------------------------------------------------+
|     by ---==> HELLCAT <==--- (Michael Hengstmann)                 |
|     hellcat@hellcat.net                                           |
|                                                                   |
|     This software is puplished under the                          |
|     GNU General Puplic License - see GPL.TXT for details          |
\* =============================================================== */

// Special note on INC_MIME.PHP:
// This file is based on work of
//   Jean-Pierre Bergamin
//
// wich was also published under the GPL

$v_inc["mime"] = "5";

class headers {
	
	var $header_arr = array();
	
	function get($name) {
		$element = $this->header_arr[strtolower($name)];
		return $element["content"];
	}
	
	function put($name, $content) {
		$element["name"] = $name;
		$element["content"] = $content;
		$this->header_arr[strtolower($name)] = $element;
	}
	
	function get_header_str(&$part) {
		$pos = strpos($part, "\r\n\r\n");
		return substr($part, 0, $pos);
	}	
	
	function extract($part) {
		// Clear previous values
		$this->header_arr = array();
		@set_time_limit(60);
		$header_str = $this->get_header_str($part)."\r\n";
		$line = strtok($header_str, "\n");
		while(1) {
			// Remove trailing whitespaces (the \r)
			$line = rtrim($line);

			if (preg_match("/^\s/", $line)) {
				// The new line starts with a whitespace. This indicates
				// That the headerpart is "folded" (multiline) and that
				// the current line belongs to the same header entity
				$header .= " $line";
				$line = strtok("\n");
				continue;
			}
			else if (!empty($header)) {
				// The $header now contains a header which we split up into its parts now
				if (preg_match("/^(.*?): (.*)$/", $header, $matches)) {
					$this->put($matches[1], $matches[2]);
				}
			}

			if (!$line) {
				// This was the last line
				break;
			}
					
			// A new header entity begins
			$header = $line;

			$line = strtok("\n");
		}
	}

	
	function generate_str($all_headers = true) {
		$header_str = "";
		foreach($this->header_arr as $element_name => $element) {
			// Don't include these two header fileds, because the PHP mail
			// function will add them itself
			if ($all_headers || ($element_name != "subject" && $element_name != "to")) {
				$header_str .= $element["name"].": ".$element["content"]."\r\n";
			}
		}
		
		return str_replace("\t", "\r\n\t", $header_str);
	}	
	
	// Strip Header comments from the string
	// Comments are in brackets outside of quotes
	function strip_comment($string) {
		$len = strlen($string);
		$ret_str = "";
		$quoted = false;
		$in_brackets = 0;
		for($i = 0; $i < $len; $i++) {
			$char = $string[$i];

			if (!$quoted && $in_brackets && $char == ')') {
				$in_brackets--;
				continue;
			}
			if (!$quoted && $char == '(') {
				$in_brackets++;
				continue;
			}
			if (!$quoted && $in_brackets) {
				continue;
			}
			if ($char == '"') {
				$quoted = !$quoted;
			}
			// We are not in brackets. Copy the character to the new string
			$str_ret .= $char;
		}
		return $str_ret;
	}	
	
	function split_header($string, $split_char) {
		$quoted = false; // true if in a quoted part
		$in_brackets = 0; // Reference counter of open brackets
		$split_arr = array();
		$old_pos = 0; // The last position of the $split_char
		$len = strlen($string);
		// Run throught the string
		for($i = 0; $i < $len; $i++) {
			$char = $string[$i];

			// $quoted == true after the first occurence of "
			// $quoted == false after the 2nd occurence of "
			// Quotes inside brackets are ignored
			if (!$in_brackets && $char == '"') {
				$quoted = !$quoted;
				continue;
			}
			// Brackets inside a quote are ignored
			else if (!$quoted && $char == '(') {
				$in_brackets++; // Increase the open brackets reference counter
				continue;
			}
			// Check for closing bracket if one was opened previously
			// Brackets inside a quote are ignored	
			else if (!$quoted && $in_brackets && $char == ')') {
				$in_brackets--; // Decrease the open brackets reference counter
				continue;
			}
			
			if (!$in_brackets && !$quoted && $char == $split_char) {
				// We found a string. Add it to the array
				$s = trim(substr($string, $old_pos, $i - $old_pos));
				$split_arr[] = $s;
				$old_pos = $i + 1;
			}
			
		}
		
		// Add the last part of the string to the array
		$s = trim(substr($string, $old_pos, $len - $old_pos));
		if (!empty($s)) {
			$split_arr[] = $s;
		}
		
		return $split_arr;
	}	
	
	function get_splitted($name) {
		$header = stripslashes($this->get($name));

		if (!$header) {
			return false;
		}
		$parameter = $this->split_header($header, ";");
		//$this->print_array($parameter);
		$header_arr[$name] = $parameter[0];
		reset($parameter);
		next($parameter);
		while(list($key, $p) = each($parameter)) {
			$split = $this->split_header($p, "=");
			$header_arr[$split[0]] = $this->un_quote($split[1]);
		}
				
		return $header_arr;
	}	
}

/* ====================================================================== */

class mimepart {

	var $body;
	var $mimeparts = array();
	var $offset;
	var $part_count;
	var $boundary;
	var $headers;

	function mimepart($part = 0) {
		$this->headers = new headers;
		if ($part) {
			$this->from_text($part);
		}
	}

	function from_text(&$part) {
		//$this->headers = $this->extract_headers($part);
		$this->headers->extract($part);
		$this->body = $this->extract_whole_body($part);	
	}

	function extract_whole_body(&$mail) {
		$divider = "\r\n\r\n";
		$pos = strpos($mail, $divider);
		return substr($mail, $pos + strlen($divider));		
	}

	function quick_from(&$mail) {
		if (preg_match("/^From: (.*)$/Um", $mail, $matches)) {
				//if (preg_match("/^(.*?): (.*)$/", $header, $matches)) {			
			if ($matches) {
				$addr = $this->get_address($matches[1]);
				return $addr["address"];
			}
			else {
				return "";
			}
		}
	}
	
	// If $get_html == true and a text/html part is found
	// the return value is array(htmlpart, true)
	// If it's text/plain the return value is just the text
	function get_text_body($get_html = false, $was_multipart = false) {
		$c_type = $this->get_header("Content-Type");
		$type = $c_type["CONTENT-TYPE"];
		// // dbg("Entering get_text_body $get_html");
		if (strtoupper(substr($type, 0, strlen("MULTIPART/"))) == "MULTIPART/") {
			$i = 1;
			$body = "";
			$text_body = "";
			while($this->extract_part($i)) {
				// // dbg("Extracting: $i");
				$body = $this->mimeparts[$i]->get_text_body($get_html, true);
				if (is_array($body) && $body[1] == true) {
					return $body;
				}
				if (!$get_html && !empty($body)) {
					// We got a text/plain body 
					return $body;
				}
				
				$c_type = $this->mimeparts[$i]->get_header("Content-Type");
				$type = $c_type["CONTENT-TYPE"];
				if (strtoupper($type) == "TEXT/PLAIN") {
					// dbg("Found text/plain");
					if (!$get_html) {
						return $body;
					}

					// If we find a text/plain in a multipart mail
					// We probably have a html mail
					$found_text_in_multipart = true;
					// We keep this text-body in case we don't find a html part
					$text_body = $body;
				}
				else if ($found_text_in_multipart && $get_html && strtoupper($type) == "TEXT/HTML") {
					// dbg("Found text/html!!!");					
					// We already found a text-part in a multipart
					// If this is text/html we got the HTML mail
					return array($this->mimeparts[$i]->decode($this->mimeparts[$i]->body), true);
				}
				
				
				$i++;
			}
			
			if (!empty($text_body)) {
				return $text_body;
			}
			if (!empty($body)) {
				return $body;	
			}
			
			return "\r\n   This mail does not contain a plaintext part\r\n\r\n   Please open the attachments to see the content";			

		}
		else if (!empty($type) && strtoupper($type) == "TEXT/HTML" && $get_html) {
			return array($this->decode($this->body), true);
		}
		
		else if (!empty($type) && strtoupper($type) != "TEXT/PLAIN") {
			return "";
		}
		else {
			return $this->decode($this->body);		
		}
		
	}
	
	function charset_decode($string) {
		return preg_replace("/=\?(.*?)\?([QB])\?(.*?)\?=/sexi", '$this->decode("\\3", "\\2")', $string);
	}
	
	function qp_decode($string) {
		// Remove all soft linebreaks wich are an = followed by a CRLF pair
		// All whitespaces at the end of the line are ignored
		$string = preg_replace("/(=\s*\r\n)/m", "", $string);
		// Replace the =XY encoded parts with the correct character
		$string = preg_replace("/(=([0-9A-F]){1,1}([0-9A-F]){1,1})/e", "chr(hexdec('\\2'.'\\3'))", $string);
		//return str_replace("_", " ", $string);
		return $string;
	}
	
	function decode($data = "", $encoding = "") {
		if (empty($data)) {
			$data = $this->body;
		}
		if (empty($encoding)) {
			$ctf = $this->get_header("Content-Transfer-Encoding");
			if (!$ctf) {
				return $data;
			}
			if (strcasecmp($ctf["CONTENT-TRANSFER-ENCODING"], "quoted-printable") == 0) {
				$encoding = "Q";
			}
			else if (strcasecmp($ctf["CONTENT-TRANSFER-ENCODING"], "base64") == 0) {
				$encoding = "B";
			}	
		}
	
		if ($encoding == "Q") {
			return $this->qp_decode($data);
		}
		else if ($encoding == "B") {
			return base64_decode($data);
		}
		else {
			return $data;
		}
	}	
	
	// Strip Header comments from the string
	// Comments are in brackets outside of quotes
	function strip_comment($string, &$comment) {
		$len = strlen($string);
		$ret_str = "";
		$quoted = false;
		$in_brackets = 0;
		for($i = 0; $i < $len; $i++) {
			$char = $string[$i];

			if (!$quoted && $in_brackets && $char == ')') {
				// A comment ends
				$in_brackets--;
				continue;
			}
			if (!$quoted && $char == '(') {
				// A comment begins
				$in_brackets++;
				continue;
			}
			if (!$quoted && $in_brackets) {
				// Add the char in brackets to the comment string
				$comment .= $char;
				continue;
			}
			if ($char == '"') {
				$quoted = !$quoted;
			}
			// We are not in brackets. Copy the character to the new string
			$str_ret .= $char;
		}
		return $str_ret;
	}
	
	function get_address($address) {
		// Strip out header comments
		$address = $this->strip_comment($address, $comment);
		// Try to fetch the name and the mail-address
		preg_match("/([\"\'])?(.*?)([\"\'])?\s*<(.*?)>/", $address, $matches);
		//$this->print_array($matches);
		
		$ret["name"] = $matches[2];
		if (!empty($matches[4])) {
			$ret["address"] = $matches[4];
		}
		else if (!empty($comment)) {
			// If there was a comment, we use that as the name
			$ret["name"] = $comment;
			$ret["address"] = $address;
		}
		else {
			$ret["address"] = $address;		
		}
		
		return $ret;
	}
	
	function get_pretty_header($name) {
		$header = $this->get_header($name);
		// Decode the charset
		$cont = $this->charset_decode($header[strtoupper($name)]);

		if ($name != "Subject") {
			// Strip out header comments
			$cont = $this->strip_comment($cont, $comment);
		}
		
		// The To, Cc and Bcc fields can contain comma separated entries
		if ($name == "To" || $name == "Cc" || $name == "Bcc") {
			$cont = explode(", ", $cont);
			foreach($cont as $entry) {
				// Get the parts that are either quoted or in angle brackets
				preg_match("/(\"(.*?)\"\s*)*<(.*?)>/", $entry, $matches);
		
				// We prefer the quoted representation
				if (!empty($matches[2])) {
					$ret[] = $matches[2];
				}
				// Then the string in brackets
				else if (!empty($matches[3])) {
					$ret[] = $matches[3];
				}
				else {
					$ret[] = $entry;
				}
				
			}
			
			return implode(", ", $ret);
		}
		else {
			// Get the parts that are either quoted or in angle brackets
			preg_match("/(\"(.*?)\"\s*)*<(.*?)>/", $cont, $matches);
	
			// We prefer the quoted representation
			if (!empty($matches[2])) {
				return $matches[2];
			}
			// Then the string in brackets
			if (!empty($matches[3])) {
				return $matches[3];
			}
			// If nothing did match, we just return the original header content
	
			return $header[strtoupper($name)];
		}
	}
	
	function get_header($name) {
		$header = stripslashes($this->headers->get($name));

		if (!$header) {
			return false;
		}
		$parameter = $this->split_header($header, ";");
		//$this->print_array($parameter);
		$header_arr[strtoupper($name)] = $parameter[0];
		reset($parameter);
		next($parameter);
		while(list($key, $p) = each($parameter)) {
			$split = $this->split_header($p, "=");
			$header_arr[strtoupper($split[0])] = $this->un_quote($split[1]);
		}
				
		return $header_arr;
	}

	// Extract part number $num
	function extract_part($num) {
		// Did we extract this part already?

		if (isset($this->mimeparts[$num]) && !empty($this->mimeparts[$num])) {
			// Yes, we extracted it
			return true;
		}

		// Get the boundary
		$c_type = $this->get_header("Content-Type");

		$type = $c_type["CONTENT-TYPE"];

		$is_multipart = (strtoupper(substr($type, 0, strlen("MULTIPART/"))) == "MULTIPART/");

		if (empty($c_type["BOUNDARY"])) {
			return false;
		}

		$boundary = "--".$c_type["BOUNDARY"]."\r\n";

		if (!isset($this->part_count) || empty($this->part_count)) {
			$this->part_count = substr_count($this->body, $boundary);
		}

		if ($num > $this->part_count) {
			return false;
		}

		$endboundary = "\r\n--".$c_type["BOUNDARY"]."--";
		$pos = 0;
		// We get all the parts from 1 to $num

		for($i = 1; $i <= $num; $i++) {
			
			// Check if we already extracted this part
			if (isset($this->mimeparts[$i]) && !empty($this->mimeparts[$i])) {
				// Yes, we extracted it, so keep on going
				continue;
			}
			
			if ($i > 1) {
				// The startposition is the offset of the previous part
				$pos = $this->mimeparts[$i - 1]->offset;
			}
			else {
				// This is the first part. Search for the first boundary
				$pos = strpos($this->body, $boundary);

				if ($pos === false) {
					// No boundary was found

					return false;
				}
				
				$pos += strlen($boundary);
			}
			
			// Search the boundary that closes this part
			$pos2 = strpos($this->body, $boundary, $pos);

			if ($pos2 === false) {
				// The 2nd boundary was not found. Check for end boundary
				$pos2 = strpos($this->body, $endboundary, $pos);
				if ($pos2 === false) {
					// The endboundary is also not present. The mail is corrupt.
					// We assume that the end of the mail is the end of the part
					$pos2 = strlen($this->body);
				}
				$end = true;
			}
			$part_str = substr($this->body, $pos, $pos2 - $pos);
	
			$part = new mimepart($part_str);
			// This is the offset in the body where the part ends
			$part->offset = $pos2 + strlen($boundary);

			$this->mimeparts[$i] = $part;
			$j = 1;
			while($this->mimeparts[$i]->extract_part($j)) {
				$j++;
			}
			if ($end)
				break;
		}
	
		return true;
	}

	function get_cid_part($cid, $mimepart = 0) {
		if ($mimepart == 0) {
			$mimepart = $this;
		}

		$i = 1;
		while($mimepart->extract_part($i)) {
			if (preg_match("/".preg_quote($cid)."/", $mimepart->mimeparts[$i]->headers->get("CONTENT-ID")) || preg_match("/cid: ".$cid."/i", $mimepart->mimeparts[$i]->headers->get("CONTENT-LOCATION"))) {
				return $mimepart->mimeparts[$i];
			}

			if (sizeof($mimepart->mimeparts[$i]->mimeparts) > 0) {
				$part = $this->get_cid_part($cid, $mimepart->mimeparts[$i]);
				if (!empty($part)) {
					return $part;
				}
			}
						
			$i++; 
		}
	}
	function get_cid_body($cid) {
		$part = $this->get_cid_part($cid);
		if ($part === false) {
			return false;
		}		
		return $part->body;
	}
	
	function get_cid_type($cid, $mimepart = 0) {
		$part = $this->get_cid_part($cid);
		if ($part === false) {
			return false;
		}
		return $part->headers->get("Content-Type");
	}

	function get_part($num, $part) {
		if ($num == 0) {
			return $this;
		}
		
		if ($part->extract_part($num) === false) {
			return false;
		}
		
		return $part->mimeparts[$num];
	}

	function get_attachment($att_path) {
		$part = $this;
		
		foreach($att_path as $key => $val) {
			$part = $part->get_part($val, $part);
			if ($part === false) {
				return false;
			}
		}

		return $part;
	}
	
	function get_attachment_data($att_path) {
		$part = $this->get_attachment($att_path);
		if ($part === false) {
			return false;
		}
		return $part->decode($part->body);
	}
	
	// Call this function only after a successfull extract_part($num)
	function get_attachment_name($num) {
		/*if (!$this->extract_part($num)) {
			return false;
		}*/
		if ($num == 0) {
			$c_type = $this->get_header("Content-Type");			
		}
		else {
			$c_type = $this->mimeparts[$num]->get_header("Content-Type");
		}
		
		$att_name = $c_type[strtoupper("name")];
		
		return $att_name;
	}

	// Call this function only after a successfull extract_part($num)
	function get_attachment_type($num) {
		/*if (!$this->extract_part($num)) {
			return false;
		}*/
		if ($num == 0) {
			$c_type = $this->get_header("Content-Type");			
		}
		else {
			$c_type = $this->mimeparts[$num]->get_header("Content-Type");
		}
		
		
		return $c_type["CONTENT-TYPE"];
	}

	function extract_headers(&$part) {
		$header_arr = array();
		$header_str = $this->get_header_str($part)."\r\n";
		$line = strtok($header_str, "\n");
		while(1) {
			// Remove trailing whitespaces (the \r)
			$line = rtrim($line);

			if (preg_match("/^\s/", $line)) {
				// The new line starts with a whitespace. This indicates
				// That the headerpart is "folded" (multiline) and that
				// the current line belongs to the same header entity
				$header .= " $line";
				$line = strtok("\n");
				continue;
			}
			else if (!empty($header)) {
				// The $header now contains a header which we split up into its parts now
				if (preg_match("/^(.*?): (.*)$/", $header, $matches)) {
					$header_arr[$matches[1]] = $matches[2];
				}
			}

			if (!$line) {
				// This was the last line
				break;
			}
					
			// A new header entity begins
			$header = $line;

			$line = strtok("\n");
		}

		return $header_arr;
	}
	
	function un_quote($string) {
		preg_match("/\"(.*?)\"/", trim($string), $matches);
		return $matches[1];
	}
	
	function split_header ($string, $split_char) {
		$quoted = false; // true if in a quoted part
		$in_brackets = 0; // Reference counter of open brackets
		$split_arr = array();
		$old_pos = 0; // The last position of the $split_char
		$len = strlen($string);
		// Run throught the string
		for($i = 0; $i < $len; $i++) {
			$char = $string[$i];

			// $quoted == true after the first occurence of "
			// $quoted == false after the 2nd occurence of "
			// Quotes inside brackets are ignored
			if (!$in_brackets && $char == '"') {
				$quoted = !$quoted;
				continue;
			}
			// Brackets inside a quote are ignored
			else if (!$quoted && $char == '(') {
				$in_brackets++; // Increase the open brackets reference counter
				continue;
			}
			// Check for closing bracket if one was opened previously
			// Brackets inside a quote are ignored	
			else if (!$quoted && $in_brackets && $char == ')') {
				$in_brackets--; // Decrease the open brackets reference counter
				continue;
			}
			
			if (!$in_brackets && !$quoted && $char == $split_char) {
				// We found a string. Add it to the array
				$s = trim(substr($string, $old_pos, $i - $old_pos));
				$split_arr[] = $s;
				$old_pos = $i + 1;
			}
			
		}
		
		// Add the last part of the string to the array
		$s = trim(substr($string, $old_pos, $len - $old_pos));
		if (!empty($s)) {
			$split_arr[] = $s;
		}
		
		return $split_arr;
	}
	
	function print_array($arr) {
		foreach($arr as $key => $val) {
			echo("$key --> $val<br>\r\n");
		}
	}
	
	function generate_header_str($all_headers = true) {
		$header_str = "";
		foreach($this->headers->header_arr as $element) {
			// Don't include these two header fields, because the PHP mail
			// function will add them itself
			if ($all_headers || ($element["name"] != "Subject" && $element["name"] != "To")) {
				$header_str .= "$element[name]: $element[content]\r\n";
			}
		}
		
		return str_replace("\t", "\r\n\t", $header_str);
	}
	
	function generate_parts() {
		$body = "";
		foreach($this->mimeparts as $key => $part) {
			$body .= "\r\n--".$this->boundary."\r\n";
			$body .= $this->mimeparts[$key]->generate_body(true, true)."\r\n";
		}
		
		// Add the end boundary
		$body .= "\r\n--".$this->boundary."--";
		
		return $body;
	}	
	
	function generate_body($with_headers = true, $all_headers = true) {
		if ($with_headers) {
			$text = $this->generate_header_str($all_headers)."\r\n";
		}
		$text .= $this->body;
		if (count($this->mimeparts) > 0) {
			$text .= $this->generate_parts();
		}

		return $text;
	}
	
	function create_boundary() {
		srand((double)microtime()*1000000);
		$this->boundary = '-NextPart---'.chr(rand(65, 91)).'---'.md5(uniqid(rand()).'---');
		return $this->boundary;
	}
	
	
	function attach_body() {
		// Create a random boundary

		// Add the content type if it's not already set
		$type = $this->headers->get("Content-Type");
		if (empty($type)) {
			$boundary = $this->create_boundary();
			$this->headers->put("Content-Type", "multipart/mixed;\tboundary=\"$boundary\"");
		}
		$this->headers->put("MIME-Version", "1.0");
		// Create a mimepart out of the body
		$msg = "Content-Type: text/plain;\tcharset=\"us-ascii\"\r\n";
		$msg .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
		$msg .= $this->body;
		$this->body = "This is a multi-part message in MIME format.\r\n";
		
		$body_part = new mimepart($msg);
		
		// Push the body part onto the beginning of the array
		$this->mimeparts[1] = $body_part;
	}
}


/* ====================================================================== */


class mail extends mimepart {

	var $mail_text;
	
	function mail() {
		$this->headers = new headers;
	}
	
	function create($to, $from, $subject, $body) {
		$this->to($to);
		$this->from($from);
		$this->subject($subject);
		//$this->headers["Content-Type"] = "text/plain;\tcharset=\"us-ascii\"";
		$this->body = $body;
	}

	function to($to = false) {
		if ($to === false) {
			return $this->get_pretty_header("To");
		}
		$this->headers->put("To", $to);
	}

	function cc($cc = false) {
		if ($cc === false) {
			$cc = $this->headers->get("Cc");
			return $this->get_pretty_header("Cc");
		}
		
		$this->headers->put("Cc", $cc);
	}	
	
	function bcc($bcc = false) {
		if ($bcc === false) {
			$bcc = $this->headers->get("Bcc");
			return $this->get_pretty_header("Bcc");
		}
		
		$this->headers->put("Bcc", $bcc);
	}	
		
	function from($from = false) {
		if ($from === false) {
			$from = $this->headers->get("From");
			return $this->get_pretty_header("From");
		}
		
		$this->headers->put("From", $from);
	}	
	
	function subject($subject = false) {
		if ($subject === false) {
			$subject = $this->headers->get("Subject");
			return $this->get_pretty_header("Subject");
		}
		if (empty($subject)) {
			$subject = "(No subject)";
		}
		$this->headers->put("Subject", $subject);
	}
	
	function add_header($header) {
		// TODO
		trim($header);
		if (empty($header)) {
			return;
		}
		$header .= "\r\n\r\n";
		$header_arr = $this->extract_headers($header);

		//$this->headers = array_merge($this->headers, $header_arr);
	}
	
	function attach_file($path, $filename, $type = "application/octet-stream", $encoding = "base64", $description = "", $disposition = "") {
		if (empty($encoding)) {
			$encoding = "base64";
		}
		if (substr(phpversion(), 0, 5) >= "4.0.3") {
	        $tmp_path = "tmp/".basename($path);
	        $move = move_uploaded_file($path, $tmp_path);
	        if (!$move) {
	                return false;
	        }
	        $path = $tmp_path;		
        }
		
		$fp = @fopen($path, "rb");
		if (!$fp) {
			return false;
		}
		$data = fread($fp, filesize($path));
		fclose($fp);
		@unlink($fp);
		$type .= ";\tname=\"$filename\";";
		$disposition .= "attachment; filename=\"$filename\"";
	
		return $this->attach_data($data, $type, $encoding, $description, $disposition);
	}
	
	function attach_data(&$data, $type = "application/octet-stream", $encoding = "base64", $description = "", $disposition = "") {
		if (stristr($type, "text/plain") && !stristr($type, "charset=")) {
			// append the default charset
			$type .= ';\tcharset="us-ascii"';
		}

		if (strcasecmp($encoding, "base64") == 0) {
			$data = chunk_split(base64_encode($data));
		}
		else if (strcasecmp($encoding, "quoted-printable") == 0) {
			// ???? I don't want to program a quoted-printable encoder...
		}
		
		$part = "Content-Type: ".$type."\r\n";
		if (!empty($encoding)) {
			$part .= "Content-Transfer-Encoding: ".$encoding."\r\n";
		}
		if (!empty($description)) {
			$part .= "Content-Description: ".$description."\r\n";
		}
		
		if (!empty($disposition)) {
			$part .= "Content-Disposition: ".$disposition."\r\n";			
		}
		
		$part .= "\r\n";
		$part .= $data;
		
		$new_part = new mimepart($part);
		
		if (count($this->mimeparts) == 0) {
			// This is the first MIME-Part
			// It means that we have to make a new MIME part out of the mail-body
			$this->attach_body();
		}
		
		// Add the new part to the current parts
		$this->mimeparts[] = $new_part;
	}
	
	function getraw() {
	    global $wmailversion;
		$this->headers->put("X-Mailer", "WMail - vB Web Mailer v" . $wmailversion);
		$body = $this->generate_body(true, true);
		
		return $body;
	}
}

?>