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

/* =============================================================== *\
|     Collection of misc functions                                  |
|     for common used functions or to clean up the main script      |
\* =============================================================== */

$v_inc["misc"] = "5";

function getgpc()
{
  global $vbulletin;
// get GPC
// yes, yes, I know, this is pretty messy and could be done better, but this way
//   I have all possible parameters in nice and handy variables without the need to care
//   if they are already read and cleaned....
$vbulletin->input->clean_array_gpc("r", array("do"        => TYPE_STR,
                                              "page"      => TYPE_INT,
											  "n"         => TYPE_INT,
											  "part"      => TYPE_STR,
											  "to"        => TYPE_STR,
											  "cc"        => TYPE_STR,
											  "bcc"       => TYPE_STR,
											  "subj"      => TYPE_STR,
											  "body"      => TYPE_STR,
											  "sendmail"  => TYPE_STR,
											  "uploadatt" => TYPE_STR,
											  "quote"     => TYPE_INT,
											  "attlist"   => TYPE_STR,
											  "cfg"       => TYPE_ARRAY,
											  "delete"    => TYPE_ARRAY,
											  "btnsave"   => TYPE_STR,
											  "btndelete" => TYPE_STR,
											  "delatt"    => TYPE_ARRAY,
											  "message"   => TYPE_STR,
											  "wysiwyg"   => TYPE_BOOL,
											  "name"      => TYPE_STR,
											  "email"     => TYPE_STR,
											  "id"        => TYPE_STR,
											  "btnmarkread" => TYPE_STR,
											  "btnmarkunread" => TYPE_STR,
											  "btnmarkallread" => TYPE_STR ));
}

function fetch_adrbook_entries()
{
  global $vbulletin, $db;
  
  // get all entries for current user and build addressbook listing
  $r = $db->query_read( "SELECT name, email FROM " . TABLE_PREFIX . "wmail_adrbook WHERE userid = '" . $vbulletin->userinfo["userid"] . "' ORDER BY name ASC;" );
  if ( mysql_num_rows( $r ) > 0 )
  {
    $entries = "";
	while ( $a = mysql_fetch_array( $r ) )
	{
	  $name = $a["name"];
	  $email = $a["email"];
      eval('$thisbit = "' . fetch_template('webmail_addressbook_entry') . '";');
	  $entries .= $thisbit;
	}
  } else {
    $name = $vbphrase["wmail_adrbook_adrbookempty"];
	$email = "";
    eval('$thisbit = "' . fetch_template('webmail_addressbook_entry') . '";');
	$entries = $thisbit;
  }
  
  return $entries;
}

function getmailboxitem( $i )
{
  global $pop3, $rowcolor, $readmarks, $wmailcfg, $stylevar, $mlist;
  
  $s = $pop3->top($i,0);
  $mimehandler = new mimepart( $s );
  $mfrom  = $mimehandler->get_pretty_header( "From" );
  $msubj  = $mimehandler->get_pretty_header( "Subject" );
  if ( strlen( $msubj ) > $wmailcfg["subjmaxchars"] ) { $msubj = substr( $msubj, 0, $wmailcfg["subjmaxchars"] ) . "..."; }
  $mmsgid = $mimehandler->get_pretty_header( "Message-ID" );
  if ( $readmarks[$mmsgid] == "UNREAD" ) { $readmark = true; } else { $readmark = false; }
  $tempdate = $mimehandler->get_pretty_header( "Date" );
  $mdate = "";
  if ( strlen( $tempdate ) > 1 )  // do some checking of the date hare to prevent strtodate() failing and to
  {                               // generate a human readable output on failure(s)
    @$mdate  = date( "d.m.y H:i", strtotime( $tempdate ) );
  } else {
    $mdate = "";
  }
  if ( strlen( $mdate ) < 4 ) { $mdate = "N/A"; }
  $msize  = number_format( $mlist[$i] / 1024, 1, ",",".") . "k";
  if ( substr( $mimehandler->get_pretty_header( "Content-Type" ), 0, 9) == "multipart" ) { $mismultipart = true; } else { $mismultipart = false; }
  eval('$thisbit = "' . fetch_template('webmail_mailboxitem') . '";');
  if ( $rowcolor == "alt1" ) { $rowcolor = "alt2"; } else { $rowcolor = "alt1"; }
  return $thisbit;
}

?>