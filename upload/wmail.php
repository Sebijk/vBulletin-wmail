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
|     Main-Script - Version 1.00                                    |
\* =============================================================== */


error_reporting(E_ALL & ~E_NOTICE); 

define('THIS_SCRIPT', 'wmail');

$phrasegroups = array( "wmail",
                       "posting" ); 

$specialtemplates = array(); 

$globaltemplates = array( "GENERIC_SHELL",
                          "webmail_shell",
						  "webmail_error",
						  "webmail_homepage",
						  "webmail_addressbook_entry",
						  "webmail_homepage_mailbit",
						  "webmail_homepage_recent" ); 

$actiontemplates = array("getmail"  => array( "webmail_mailbox",
                                              "webmail_mailboxitem" ),
					     "read"     => array( "webmail_mailview",
						                      "webmail_attachmentbit",
											  "webmail_attachments" ),
						 "getatt"   => array( "webmail_erroronly" ),
						 "newmail"  => array( "webmail_newmail",
						                      "webmail_addattachments",
											  "webmail_addattachmentbit",
											  "editor_clientscript",
											  "editor_jsoptions_font",
											  "editor_jsoptions_size",
											  "editor_smilie",
											  "editor_smiliebox",
											  "editor_smiliebox_row",
											  "editor_smiliebox_straggler",
											  "editor_toolbar_on",
											  "newpost_disablesmiliesoption",
											  "webmail_simpleeditor",
											  "webmail_mailsent",
											  "webmail_redirectheader" ),
					     "settings" => array( "webmail_settings" ),
						 "adrbook"  => array( "webmail_addressbook_main" )
						);

$temp_do = $_REQUEST["do"];  // remeber the original "do" value, incase some thing goes wrong while vB processes it
                             // may happen on some installs....

require_once( "./global.php" ); 
require_once( "./wmail/inc_accessconfig.php" );
require_once( "./wmail/inc_misc.php" );

$headinclude = str_replace('clientscript', $vbulletin->options['bburl'] . '/clientscript', $headinclude);

// this can't work if noone is logged in.
// so, regardless of all settings, block out any unregistered/not logged in guests.
if (!$vbulletin->userinfo["userid"])
{
  print_no_permission();
}
// now check if the logged in user has permissions to use this....
if (!($permissions['wmailpermissions'] & $vbulletin->bf_ugp['wmailpermissions']['canusewebmail']))
{
	print_no_permission();
}

// initial style classes for naviagtion panel
$navclass["getmail"] = "alt2";
$navclass["newmail"] = "alt2";
$navclass["settings"] = "alt2";
$navclass["adrbook"] = "alt2";

// get mail config
// init some defaults
$wmailcfg["mailsperpage"] = 25;
$wmailcfg["sortorder"]    = false;
$wmailcfg["timeout"]      = 30;
$wmailcfg["subjmaxchars"] = 40;
$wmailcfg["pop3port"]     = 110;
$wmailcfg["smtpport"]     = 25;
// and get config
getconfig();

// dynamic global config values
$wmailcfg["temppath"]     = str_replace( "\\", "/", DIR . "/wmail/temp/" );  // str_replace() incase we are running windows....
$wmailversion             = "1.00";
$v_inc["inc"]             = "5";

// fetch GPC
getgpc();
// now save everything into more easy to use variables (shorter to type ;-)
// I know, this is pretty messy again, but so I have (almost) everything in nice and handy vars....
// I'ma lazy bitch, hate to type long var names :-D
$do          = $temp_do; // $vbulletin->GPC["do"];
$page        = $vbulletin->GPC["page"];
$n           = $vbulletin->GPC["n"];
$part        = $vbulletin->GPC["part"];
$mto         = $vbulletin->GPC["to"];
$mcc         = $vbulletin->GPC["cc"];
$mbcc        = $vbulletin->GPC["bcc"];
$mbody       = $vbulletin->GPC["body"];
$msubj       = $vbulletin->GPC["subj"];
$send        = $vbulletin->GPC["sendmail"];
$ulatt       = $vbulletin->GPC["uploadatt"];
$quote       = $vbulletin->GPC["quote"];
$attlist     = $vbulletin->GPC["attlist"];
$cfg         = $vbulletin->GPC["cfg"];
$delete      = $vbulletin->GPC["delete"];
$btnsave     = $vbulletin->GPC["btnsave"];
$btndel      = $vbulletin->GPC["btndelete"];
$delatt      = $vbulletin->GPC["delatt"];
$msgid       = $vbulletin->GPC["id"];

// alter $do if the "Mark all mails read" button was pressed
if ( strlen( $vbulletin->GPC["btnmarkallread"] ) > 1 ) { $do = "markallread"; }

// fetch mail text if WYSIWYG editor was used
if ($vbulletin->GPC["wysiwyg"])
{
  require_once( "./includes/functions_wysiwyg.php" );
  $mbody = convert_wysiwyg_html_to_bbcode($vbulletin->GPC['message'], false);
} else {
  if ( strlen( $vbulletin->GPC["message"] ) > 0 ) { $mbody = $vbulletin->GPC["message"]; }
}

// some cleanup
if ( $page < 1 ) { $page = 1; }

// ------------------------------------------------------
// start handling of whatever the user wants us to do :-)
// ------------------------------------------------------

($hook = vBulletinHook::fetch_hook('wmail_global_start')) ? eval($hook) : false;

// check if we have to process something before starting the showoff stuff
if ( $do == "procmail" )
{
  require_once( "./wmail/inc_pop3.php" );
  if ( strlen( $btndel ) > 1 )   // the user has selected some mails in the inbox view and wants them to be deleted
  {
    $pop3 = new pop3($inbound_host);
    $pop3->TIMEOUT = $wmailcfg["timeout"];
	$pop3->port = $wmailcfg["pop3port"];
    if ( $pop3->pop_login($inbound_username, $inbound_password) )
    {
      foreach( $delete as $delitem => $v )
	  {
        if ( strlen( $v ) > 1 )
	    {
		  $pop3->deletemsg( $delitem );
		  $pop3->noop();
		}
	  }
	}
	$pop3->quit();
  }
  if ( strlen( $vbulletin->GPC["btnmarkread"] ) > 1 )   // the user has requested to mark the selected mails as read
  {
    foreach( $delete as $delitem => $v )
    {
      if ( strlen( $v ) > 1 )
      {
        $db->query_write( "DELETE FROM " . TABLE_PREFIX . "wmail_readmarks WHERE userid='" . $vbulletin->userinfo["userid"] . "' AND msgid='" . $v . "'" );
      }
	}
  }
  if ( strlen( $vbulletin->GPC["btnmarkunread"] ) > 1 )   // the user has requested to mark the selected mails as NOT read
  {
    foreach( $delete as $delitem => $v )
    {
      if ( strlen( $v ) > 1 )
      {
		$db->query_write( "INSERT INTO " . TABLE_PREFIX . "wmail_readmarks (userid, msgid) VALUES ('" . $vbulletin->userinfo["userid"] . "', '" . $v . "')" );
	  }
	}
  }
  $do = "getmail";
}
if ( $do == "deletemail" )   // delete the given mail
{
  require_once( "./wmail/inc_pop3.php" );
  $pop3 = new pop3($inbound_host);
  $pop3->TIMEOUT = $wmailcfg["timeout"];
  $pop3->port = $wmailcfg["pop3port"];
  if ( $pop3->pop_login($inbound_username, $inbound_password) )
  {
    $pop3->deletemsg( $n );
  }
  $pop3->quit();
  $do = "getmail";
}
if ( $do == "markallread" )   // mark ALL mails as read
{
  $db->query_write( "DELETE FROM " . TABLE_PREFIX . "wmail_readmarks WHERE userid='" . $vbulletin->userinfo["userid"] . "'" );
  $do = "getmail";
}


// and here we go....
if ( $do == "getmail" )
{
  /* ============================================================ *\
  |    Get mailbox content                                         |
  \* ============================================================ */

  // get functions required here
  require_once( "./wmail/inc_pop3.php" );
  require_once( "./wmail/inc_mime.php" );
  
  $navclass["getmail"] = "alt1";
  
  // connet to POP3 server
  $pop3 = new pop3($inbound_host);
  $pop3->TIMEOUT = $wmailcfg["timeout"];
  $pop3->port = $wmailcfg["pop3port"];
  $mmailcount = $pop3->pop_login($inbound_username, $inbound_password);
  
  ($hook = vBulletinHook::fetch_hook('wmail_getmail_start')) ? eval($hook) : false;
  
  if ( $mmailcount )
  {
    if ( ( $mmailcount > $wmailcfg["lastmailcount"] ) AND ( ( $wmailcfg["lastmailcount"] > 0 ) OR ( $wmailcfg["lastmailcount"] == -1 ) ) )
	{
	  // get all msg-ids for newly arrived mail for read/unread markings
	  if ( $wmailcfg["lastmailcount"] == -1 ) { $wmailcfg["lastmailcount"] = 0; }
	  $sql = "";
	  for ( $i = $wmailcfg["lastmailcount"] + 1; $i <= $mmailcount; $i++ )
	  {
	    $s = $pop3->top($i,0);
        $mimehandler = new mimepart( $s );
        $mmsgid = $mimehandler->get_pretty_header( "Message-ID" );
		$db->query_write( "INSERT INTO " . TABLE_PREFIX . "wmail_readmarks (userid, msgid) VALUES ('" . $vbulletin->userinfo["userid"] . "', '" . $mmsgid . "')" );
	  }
	}
	$wmailcfg["lastmailcount"] = $mmailcount;
	saveuserconfig( $vbulletin->userinfo["userid"] );
    $listbits = "";
	
	// get all markings for unread mails
	$r = $db->query_read( "SELECT msgid FROM " . TABLE_PREFIX . "wmail_readmarks WHERE userid='" . $vbulletin->userinfo["userid"] . "'" );
	$readmarks = array();
	if ( mysql_num_rows( $r ) > 0 )
	{
	  while ( $a = mysql_fetch_array( $r ) )
	  {
	    $mmsgid = $a["msgid"];
	    $readmarks[$mmsgid] = "UNREAD";
	  }
	}
	
	// build the maillist
    $mlist = $pop3->pop_list();
    if ( $wmailcfg["sortorder"] )
	{
      $first = 1 + ( $wmailcfg["mailsperpage"] * ( $page - 1 ) );
	  $last  = $first + $wmailcfg["mailsperpage"];
	  if ( $first > $mmailcount ) { $first = $mmailcount; };
	  if ( $last > $mmailcount ) { $last = $mmailcount; };
	} else {
      $last  = $mmailcount - ( ( $page - 1 ) * $wmailcfg["mailsperpage"] );
	  $first = $last - $wmailcfg["mailsperpage"];
	  if ( $first < 1 ) { $first = 1; }
	  if ( $last  < 1 ) { $last = 1; }
	}
	$rowcolor = "alt1";
	if ( $wmailcfg["sortorder"] )
	{
	  for ( $i = $first; $i <= $last; $i++ )
	  {
        $listbits .= getmailboxitem( $i );
	  }
	} else {
	  for ( $i = $last; $i >= $first; $i-- )
	  {
	    $listbits .= getmailboxitem( $i );
	  }
	}
  } else {
    if ( $mmailcount === false )
    {
	  $wmailerror = $vbphrase["wmail_error_pop3connect"];
	} else {
	  $wmailerror = $vbphrase["wmail_error_inboxempty"];
	  $vbphrase["wmail_error"] = $vbphrase["wmail_notice"];
	  $wmailcfg["lastmailcount"] = -1;
	  saveuserconfig( $vbulletin->userinfo["userid"] );
	}
  }
  
  // build the mailbox display
  
  // Construct Page Navigation 
  $total = $mmailcount; 
  $url = "wmail.php?do=getmail";
  if ( $page < 1 ) { $page = 1; }
  $offset = ($page - 1) * $wmailcfg["mailsperpage"]; 
  $limit = "$offset, " . $wmailcfg["mailsperpage"]; 
  $pageNavigation = construct_page_nav( $page, $wmailcfg["mailsperpage"], $total, $url, "" );  
  
  eval('$wmailerrormsg = "' . fetch_template('webmail_error') . '";');
  eval('$wmailcontent = "' . fetch_template('webmail_mailbox') . '";');
  
  // navbits
  $navbits["wmail.php"] = $vbphrase["wmail_navbit_webmail"];
  $navbits[""] = $vbphrase["wmail_inbox"];
  
  ($hook = vBulletinHook::fetch_hook('wmail_getmail_complete')) ? eval($hook) : false;
} elseif ( $do == "read" ) {
  /* ============================================================ *\
  |    Get complete mail and print it on screen                    |
  \* ============================================================ */
  
  // get functions required here
  require_once( "./wmail/inc_pop3.php" );
  require_once( "./wmail/inc_mime.php" );
  require_once( "./includes/class_bbcode.php" );
  require_once( "./includes/functions_newpost.php" );
  $vbulletin->cdbbcode_parse =& new vB_BbCodeParser($vbulletin, fetch_tag_list());
  
  $navclass["getmail"] = "alt1";
  
  // connet to POP3 server
  $pop3 = new pop3($inbound_host);
  $pop3->TIMEOUT = $wmailcfg["timeout"];
  $pop3->port = $wmailcfg["pop3port"];
  $mmailcount = $pop3->pop_login($inbound_username, $inbound_password);
  $mlist = $pop3->pop_list();
  
  ($hook = vBulletinHook::fetch_hook('wmail_readmail_start')) ? eval($hook) : false;
  
  if ( $mmailcount )
  {
    // delete unread marking for this mail
	$db->query_write( "DELETE FROM " . TABLE_PREFIX . "wmail_readmarks WHERE userid='" . $vbulletin->userinfo["userid"] . "' AND msgid='" . $msgid . "'" );
	$s = $pop3->get_text( $n );
    $mimehandler = new mimepart( $s );
	$mfromaddr = $mimehandler->quick_from( $s );
    $mfrom  = $mimehandler->get_pretty_header( "From" );
	$mto    = $mimehandler->get_pretty_header( "To" );
    $msubj  = $mimehandler->get_pretty_header( "Subject" );
    $mmsgid = $mimehandler->get_pretty_header( "Message-ID" );
    $mdate  = date( "d.m.y H:i", strtotime( $mimehandler->get_pretty_header( "Date" ) ) );
    $msize  = number_format( $mlist[$n] / 1024, 1, ",",".") . "k";
	$mmultipart = substr($mimehandler->get_pretty_header( "Content-Type" ), 0, 9);
	if ( $mmultipart == "multipart" ) { $mismulti = true; } else { $mismulti = false; }
	$mbody = $vbulletin->cdbbcode_parse->parse(convert_url_to_bbcode($mimehandler->get_text_body( false, $mismulti )), 'nonforum');
	
	$showattachments = false;
	if ( $mismulti )
	{
	  $p = 1;
	  $attachbits = "";
	  while ( $mimehandler->extract_part( $p ) )
	  {
	    if ( substr( $mimehandler->mimeparts[$p]->headers->header_arr["content-disposition"]["content"], 0, 10) == "attachment" )
		{
	      $showattachments = true;
		  $mpart = $p;
		  $attname = $mimehandler->get_attachment_name( $p );
		  $attsize = number_format( strlen( $mimehandler->get_attachment_data( array( "mimepart" => $p ) ) ) / 1024, 1, ",",".") . "k";
		  eval('$thisattbit = "' . fetch_template('webmail_attachmentbit') . '";');
		  $attachbits .= $thisattbit;
		}
		$p++;
	  }
	  eval('$attachments = "' . fetch_template('webmail_attachments') . '";');
	  
	  ($hook = vBulletinHook::fetch_hook('wmail_readmail_attachments')) ? eval($hook) : false;
	  
	}
  } else {
    $wmailerror = $vbphrase["wmail_error_pop3connect"];
  }
  
  // build the mail display
  eval('$wmailerrormsg = "' . fetch_template('webmail_error') . '";');
  eval('$wmailcontent = "' . fetch_template('webmail_mailview') . '";');
  
  // navbits
  $navbits["wmail.php"] = $vbphrase["wmail_navbit_webmail"];
  $navbits["wmail.php?do=getmail"] = $vbphrase["wmail_inbox"];
  $navbits[""] = $vbphrase["wmail_readmail"];
  
  ($hook = vBulletinHook::fetch_hook('wmail_readmail_complete')) ? eval($hook) : false;
} elseif ( $do == "getatt" ) {
  /* ============================================================ *\
  |    Send an attachment to to client for downloading             |
  \* ============================================================ */
  
  // get functions required here
  require_once( "./wmail/inc_pop3.php" );
  require_once( "./wmail/inc_mime.php" );
  
  $navclass["getmail"] = "alt1";
  
  // connet to POP3 server
  $pop3 = new pop3($inbound_host);
  $pop3->TIMEOUT = $wmailcfg["timeout"];
  $pop3->port = $wmailcfg["pop3port"];
  $mmailcount = $pop3->pop_login($inbound_username, $inbound_password);
  $mlist = $pop3->pop_list();
  
  ($hook = vBulletinHook::fetch_hook('wmail_attachmentdownload')) ? eval($hook) : false;
  
  if ( $mmailcount )
  {
	$s = $pop3->get_text( $n );
    $mimehandler = new mimepart( $s );
	if ( $mimehandler->extract_part($part) )
	{
      $attdata   = $mimehandler->get_attachment_data( array( "mimepart" => $part ) );
	  $attname   = $mimehandler->get_attachment_name( $part );
	  $attfmtype = $mimehandler->mimeparts[$part]->headers->header_arr["content-type"]["content"];
	  $attmtype  = substr( $attfmtype, 0, strpos( $attfmtype, ";", 0));
	  
	  header ( "Content-Type: $attmtype");
      header ( "Content-Length: ".strlen($attdata));
      header ( "Content-Disposition: attachment; filename=$attname");
      echo $attdata;
	  exit;
	}
  }
  
  // if the script still lives at this point, we didn't send
  // the attachment! So -> send an errormessage instead:
  $wmailerror = $vbphrase["wmail_error_attdl"];
  eval('$wmailerrormsg = "' . fetch_template('webmail_error') . '";');
  eval('$wmailcontent = "' . fetch_template('webmail_erroronly') . '";');
  
  // navbits
  $navbits["wmail.php"] = $vbphrase["wmail_navbit_webmail"];
  $navbits[""] = $vbphrase["wmail_readmail"];
} elseif ( $do == "newmail" ) {
  /* ============================================================ *\
  |    Compose and send a new mail                                 |
  \* ============================================================ */
  
  // get functions required here
  require_once( "./wmail/inc_pop3.php" );
  require_once( "./wmail/inc_mime.php" );
  require_once( "./includes/functions_editor.php" );
  require_once( "./includes/class_bbcode.php" );
  $vbulletin->cdbbcode_parse =& new vB_BbCodeParser($vbulletin, fetch_tag_list());
	
  $navclass["newmail"] = "alt1";
  
  ($hook = vBulletinHook::fetch_hook('wmail_newmail_start')) ? eval($hook) : false;
  
  // navbit (more to come on the way down....)
  $navbits["wmail.php"] = $vbphrase["wmail_navbit_webmail"];
  
  if ( $quote > 0 )
  {
    // connet to POP3 server
    $pop3 = new pop3($inbound_host);
    $pop3->TIMEOUT = $wmailcfg["timeout"];
	$pop3->port = $wmailcfg["pop3port"];
    $mmailcount = $pop3->pop_login($inbound_username, $inbound_password);
    if ( $mmailcount )
    {
      $s = $pop3->get_text( $quote );
      $mimehandler = new mimepart( $s );
      $mmultipart = substr($mimehandler->get_pretty_header( "Content-Type" ), 0, 9);
      if ( $mmultipart == "multipart" ) { $mismulti = true; } else { $mismulti = false; }
      $mbody = "> " . str_replace( "\n", "\n> ", $mimehandler->get_text_body( false, $mismulti ) );
      $t = $mimehandler->get_pretty_header( "Subject" );
	  
	  // prepare some vars for the quoting header
	  $originalsubject = $t;
	  $oto = $mimehandler->quick_from( $s );
	  $originalsender = $mimehandler->get_pretty_header( "From" ) . " ($oto)";
	  $originaldate = $mimehandler->get_pretty_header( "Date" );
	  eval('$quoteheader = "' . fetch_template('webmail_quoteheader', 0, false) . '";');
	  $mbody = $quoteheader . $mbody;
	  
	  // navbit
	  $navbits["wmail.php?do=getmail"] = $vbphrase["wmail_inbox"];
      $navbits["wmail.php?do=read&amp;n=" . $quote] = $t;
	  
	  if ( $mto == "" )
	  {
	    $msubj = "FW: " . $t;
		$navbits[""] = $vbphrase["wmail_forward"];
	  } else {
	    $msubj = "Re: " . $t;
		$navbits[""] = $vbphrase["wmail_reply"];
	  }
    } else {
      $wmailerror = $vbphrase["wmail_error_quote"];
    }
  } else {
    $navbits[""] = $vbphrase["wmail_composemail"];
  }

  $attlistarray = unserialize( str_replace( "#", "\"", $attlist ) );
  if ( $attlistarray === false ) { $attlistarray = array(); }
  
  // check if the user uploaded an attachment and process it
  if ( strlen( $ulatt ) > 1 )     // the user did upload an attachment ("Upload" button was pressed)
  {
    $tempfile = md5( time() . $_FILES["newatt"]["tmp_name"] );  // generate a unique filename to store the file to for the moment
    if ( move_uploaded_file( $_FILES["newatt"]["tmp_name"], $wmailcfg["temppath"] . $tempfile) )
	{
	  $c = count( $attlistarray ) + 1;
	  $attlistarray[$c]["id"]   = $tempfile;
	  $attlistarray[$c]["name"] = basename( $_FILES["newatt"]["name"] );
	  $attlistarray[$c]["size"] = number_format( filesize( $wmailcfg["temppath"] . $tempfile ) / 1024, 1, ",",".") . "k";
	  $attlistarray[$c]["type"] = $_FILES["newatt"]["type"];
	}
  }
  ($hook = vBulletinHook::fetch_hook('wmail_newmail_attachment')) ? eval($hook) : false;
  // delete an attachment if the according button was pressed
  foreach( $delatt as $dattid => $v )
  {
    $a = array();
	$c = count( $attlistarray );
	for( $i = 1; $i <= $c; $i++ )
	{
	  if ( $i != $dattid )
	  {
	    $a[] = $attlistarray[$i];
	  } else {
	    @unlink( $wmailcfg["temppath"] . $attlistarray[$i]["id"] );
	  }
	}
	$attlistarray = $a;
  }
  $attlist = str_replace( "\"", "#", serialize( $attlistarray ) );  // Replace all quotemarks in the serialized string 'cause those would blow up the HTML form's <INPUT ... /> tag....
  
  // check if the user finally hit the "Send Mail" button and send it....
  ($hook = vBulletinHook::fetch_hook('wmail_newmail_sendmail')) ? eval($hook) : false;
  if ( strlen( $send ) > 1 )
  {
    // so, the user did hit the "Send Mail" button - so let's send it already....
	if ( strlen( $mto ) < 1 )
	{
	  // D'Oh! No recipient! We can't send a mail without a recipient!
	  $wmailerror = $vbphrase["wmail_error_missingto"];
	} else {
	  // everything OK? So, let's do it now....	
      $newmail = new mail;  // extension of the mimenpart class, defined in "inc_mime.php"
	
	  // init all headers of our new mail
	  $mbody .= "\r\n" . $wmailcfg["signature"];
	  $newmail->create($mto, $wmailcfg["fromaddr"], $msubj, $mbody);
	  $newmail->from( $wmailcfg["fromaddr"] );
	  $newmail->to( $mto );
	  $newmail->subject( $msubj );
	  if ( strlen( $mcc ) > 1 ) { $newmail->cc( $mcc ); }
	  if ( strlen( $mbcc ) > 1 ) { $newmail->bcc( $mbcc ); }
	
	  // attach all attachments
	  foreach ( $attlistarray as $att )
	  {
	    $fp = @fopen( $wmailcfg["temppath"] . $att["id"], "rb");
        if ($fp)
	    {
          $data = fread($fp, filesize( $wmailcfg["temppath"] . $att["id"] ));
          fclose($fp);
          @unlink( $wmailcfg["temppath"] . $att["id"] );
          $type = $att["type"] . ";\tname=\"" . $att["name"] . "\";";
          $disposition = "attachment; filename=\"" . $att["name"] . "\"";
		
		  $newmail->attach_data($data, $type, "base64", "", $disposition);
        }
	  }
	
	  $rawmail = $newmail->getraw();
	  
	  require_once( "./wmail/inc_smtp.php" ); // we need the SMTP backend class to be able to send mails through a "real" SMTP connection
	  $smtp = new smtp;                       // rather than PHP's mail() function
	  $smtp->smtp_server = $outbound_host;
	  $smtp->port = $wmailcfg["smtpport"];
      $smtp->from = $wmailcfg["smtpfrom"];
	  
	  $mto = str_replace( ";", ",", $mto );  // make all ; to , and strip all spaces - or should we better make "," to ";"?!?!? Any advise here?
	  $mto = str_replace( " ", "", $mto );   // sort of cleaning up the recipientslists to ensure the SMTP class doesn't get any hickups....
	  if ( substr( $mto, strlen( $mto ) - 1, 1 ) == "," ) { $mto = substr( $mto, 0, strlen( $mto ) - 1 ); } // strip a trailing "," if there's one
	  $a = explode( ",", $mto );
      $smtp->to = $a;
      
	  if ( strlen( $mcc ) > 1 )
	  {
	    str_replace( ";", ",", $mcc );
	    str_replace( " ", "", $mcc );
		if ( substr( $mcc, strlen( $mcc ) - 1, 1 ) == "," ) { $mcc = substr( $mcc, 0, strlen( $mcc ) - 1 ); }
	    $a = explode( ",", $mcc );
		$smtp->cc = $a;
      }
	  
	  if ( strlen( $mbcc ) > 1 )
	  {
        str_replace( ";", ",", $mbcc );
	    str_replace( " ", "", $mbcc );
		if ( substr( $mbcc, strlen( $mbcc ) - 1, 1 ) == "," ) { $mbcc = substr( $mbcc, 0, strlen( $mbcc ) - 1 ); }
	    $a = explode( ",", $mbcc );
		$smtp->bcc = $a;
      }
	  
      $smtp->subject = $msubj;
      $smtp->data = $rawmail;
	  
	  // connect to the SMTP server
	  $smtpconnection = $smtp->smtp_open();
	  if ( $smtpconnection )
	  {
	    // talk to the SMTP server
	    if ( $outbound_auth == "LOGIN" )
		{
		  $rhelo = $smtp->smtp_ehlo( $smtpconnection );
		} else {
		  $rhelo = $smtp->smtp_helo( $smtpconnection );
		}
		$rauth = $smtp->smtp_auth( $smtpconnection, $outbound_auth, $outbound_username, $outbound_password );
		$rfrom = $smtp->smtp_mail_from( $smtpconnection );
		$rto   = $smtp->smtp_rcpt_to( $smtpconnection );
		$rdata = $smtp->smtp_data( $smtpconnection );
		$rquit = $smtp->smtp_quit( $smtpconnection );
	  }
      // everything went well? yes -> prepare for "mail sent" message"; no -> print error and go back to mail compositing
	  if ( $rhelo AND $rauth AND $rfrom AND $rto AND $rdata AND $rquit )
	  {
        // prepare everything the "mail sent" message
		$sentto = $mto;
		if ( strlen( $mcc ) > 0 ) { $sentto .= "," . $mcc; }
		if ( strlen( $mbcc ) > 0 ) { $sentto .= "," . $mbcc; }
		$sentto = str_replace( " ", "", $sentto );
	    $sentto = str_replace( ",", "<br />", $sentto );
	    $sentto = str_replace( ";", "<br />", $sentto );
	    $mailsent = true;
	  } else {
	    // clean up the log for propper HTML output
		$log = str_replace( "\\", "<br />", $smtp->sessionlog );
		$log = str_replace( "\r\n", "<br />Rcvd: ", $log );
		$log = str_replace( "\n", "<br />Rcvd: ", $log );
		$log = str_replace( "<br />Rcvd: <br />", "<br />", $log );     // <-- why this you ask? To remove empty lines ;-)

		$wmailerror = $vbphrase["wmail_error_smtp"] . "<br /><div class=\"smallfont\" align=\"left\">" . $log . "</div>";
		$mailsent = false;
	  }
	}
  }
  
  if ( $mailsent )
  {
    if ( $wmailcfg["rediraftersend"] == "yes" )
	{
      $url = "wmail.php?do=newmail";
	  $js_url = $url;
	  eval('$headinclude .= "' . fetch_template('webmail_redirectheader') . '";');
	}
    eval('$wmailcontent = "' . fetch_template('webmail_mailsent') . '";');
  } else {
    // no "Send Mail" button or we got an error:
    // build the HTML list of all current attachments to be shown on the bottom of the "new mail" page
    $attachbits = "";
    foreach ( $attlistarray as $i => $att )
    {
      $attname = $att["name"];
      $attsize = $att["size"];
	  $attid   = $i;
	  eval('$attachbits .= "' . fetch_template('webmail_addattachmentbit') . '";');
    }

	// build the editor depending on user preference wich one to use
	if ( $wmailcfg["editor"] == "SIMPLE" )
	{
	  eval('$messagearea = "' . fetch_template('webmail_simpleeditor') . '";');
	} else {
	  $editorid = construct_edit_toolbar( $mbody );
	}

    // build the mail compositing page (again) for output to let the user start/continue his mail
	$entries = fetch_adrbook_entries();
	
    eval('$attachments = "' . fetch_template('webmail_addattachments') . '";');
	eval('$wmailerrormsg = "' . fetch_template('webmail_error') . '";');
    eval('$wmailcontent = "' . fetch_template('webmail_newmail') . '";');
  }
  
  ($hook = vBulletinHook::fetch_hook('wmail_newmail_complete')) ? eval($hook) : false;
} elseif ( $do == "settings" ) {
  /* ============================================================ *\
  |    Config page                                                 |
  \* ============================================================ */
  
  if ( strlen( $btnsave ) >= 1 )
  {
    $t1 = $wmailcfg["lastmailcount"];
    $wmailcfg = $cfg;
	$wmailcfg["lastmailcount"] = $t1;
	saveuserconfig( $vbulletin->userinfo["userid"] );
	getuserconfig( $vbulletin->userinfo["userid"] );
  }

  if ( $wmailcfg["useforumemail"] == "1" ) { $checkuserfemail = "checked"; }
  if ( $wmailcfg["useforumnick"] == "1" ) { $checkuserfnick = "checked"; }
  if ( $wmailcfg["useforumsig"] == "1" ) { $checkuserfsig = "checked"; }
  if ( $wmailcfg["loginascookie"] == "1" ) { $checkloginascookie = "checked"; }
  $navclass["settings"] = "alt1";
  eval('$wmailcontent = "' . fetch_template('webmail_settings') . '";');
  
  // navbits
  $navbits["wmail.php"] = $vbphrase["wmail_navbit_webmail"];
  $navbits[""] = $vbphrase["wmail_wmailsettings"];
  
  ($hook = vBulletinHook::fetch_hook('wmail_settings')) ? eval($hook) : false;
} elseif ( $do == "adrbook" ) {
  /* ============================================================ *\
  |    Addressbook                                                 |
  \* ============================================================ */
  
  $navclass["adrbook"] = "alt1";
  
  ($hook = vBulletinHook::fetch_hook('wmail_adrbook_start')) ? eval($hook) : false;
  
  if ( strlen( $btnsave ) > 1 )
  {
    // if the user requested to update or add an entry -> do so here
    $r = $db->query_read( "SELECT name, email FROM " . TABLE_PREFIX . "wmail_adrbook WHERE userid = '" . $vbulletin->userinfo["userid"] . "' AND name='" . $vbulletin->GPC["name"] . "'" );
    if ( mysql_num_rows( $r ) > 0 )
    {
	  // entry with given name already exists -> update email address
	  $r = $db->query_write( "UPDATE " . TABLE_PREFIX . "wmail_adrbook SET email='" . $vbulletin->GPC["email"] . "' WHERE userid = '" . $vbulletin->userinfo["userid"] . "' AND name='" . $vbulletin->GPC["name"] . "'" );
	} else {
	  // no entry with given name exists -> add it
	  $r = $db->query_write( "INSERT INTO " . TABLE_PREFIX . "wmail_adrbook (userid, name, email) VALUES ('" . $vbulletin->userinfo["userid"] . "', '" . $vbulletin->GPC["name"] . "', '" . $vbulletin->GPC["email"] . "')" );
	}
  }
  if ( strlen( $btndel ) > 1 )
  {
    // if the user requested to delete an entry -> do so here
    $r = $db->query_read( "DELETE FROM " . TABLE_PREFIX . "wmail_adrbook WHERE userid = '" . $vbulletin->userinfo["userid"] . "' AND name='" . $vbulletin->GPC["name"] . "'" );
  }
  
  $entries = fetch_adrbook_entries();
  
  // navbits
  $navbits["wmail.php"] = $vbphrase["wmail_navbit_webmail"];
  $navbits[""] = $vbphrase["wmail_adrbook"];
  
  ($hook = vBulletinHook::fetch_hook('wmail_adrbook_complete')) ? eval($hook) : false;
  
  eval('$wmailcontent = "' . fetch_template('webmail_addressbook_main') . '";');
  
  ($hook = vBulletinHook::fetch_hook('wmail_adrbook')) ? eval($hook) : false;
} else {
  /* ============================================================ *\
  |    Welcome panel                                               |
  \* ============================================================ */
  
  require_once( "./wmail/inc_pop3.php" );
  require_once( "./wmail/inc_mime.php" );
  
  // connet to POP3 server
  $pop3 = new pop3($inbound_host);
  $pop3->TIMEOUT = $wmailcfg["timeout"];
  $pop3->port = $wmailcfg["pop3port"];
  $mmailcount = $pop3->pop_login($inbound_username, $inbound_password);
  if ( $wmailcfg["lastmailcount"] == -1 ) { $wmailcfg["lastmailcount"] = 0; }
  if ( $mmailcount )
  {
    $inboxtotal = $mmailcount;
	$inboxnew = $mmailcount - $wmailcfg["lastmailcount"];
	$username = $vbulletin->userinfo["username"];
	
	$last = $mmailcount;
	if ( $inboxnew > 15 ) { $first = $last - 15; } else { $first = $last - $inboxnew; }
	$listbits = "";
	for ( $i = $last; $i >= $first; $i-- )
	{
	  $s = $pop3->top($i,0);
      $mimehandler = new mimepart( $s );
      $msubj  = $mimehandler->get_pretty_header( "Subject" );
      if ( strlen( $msubj ) > $wmailcfg["subjmaxchars"] ) { $msubj = substr( $msubj, 0, $wmailcfg["subjmaxchars"] ) . "..."; }
      $mmsgid = $mimehandler->get_pretty_header( "Message-ID" );
      eval('$thisbit = "' . fetch_template('webmail_homepage_mailbit') . '";');
      $listbits .= $thisbit;
	}
	$recentmails = $listbits;
	eval('$recentthings = "' . fetch_template('webmail_homepage_recent') . '";');
  } else {
    $inboxtotal = "???";
	$inboxnew = "???";
	$username = $vbulletin->userinfo["username"];
    if ( $mmailcount === false )
    {
	  $wmailerror = $vbphrase["wmail_error_pop3connect"];
	} else {
	  $inboxtotal = "0";
	  $inboxnew = "0";
	  $wmailerror = "";
	}
  }

  $navbits[""] = $vbphrase["wmail_navbit_webmail"];
  $entries = fetch_adrbook_entries();
  eval('$wmailerrormsg = "' . fetch_template('webmail_error') . '";');
  eval('$wmailcontent = "' . fetch_template('webmail_homepage') . '";');
  
  ($hook = vBulletinHook::fetch_hook('wmail_homepage')) ? eval($hook) : false;
}

$v_inc_match = true;
foreach ( $v_inc as $v )
{
  if ( $v != $v_inc["inc"] ) { $v_inc_match = false; }
}

// final output
$navbits = construct_navbits($navbits); 
eval('$navbar = "' . fetch_template('navbar') . '";'); 
eval('$HTML = "' . fetch_template('webmail_shell') . '";');

($hook = vBulletinHook::fetch_hook('wmail_global_complete')) ? eval($hook) : false;

eval('print_output("' . fetch_template('GENERIC_SHELL') . '");');

?>