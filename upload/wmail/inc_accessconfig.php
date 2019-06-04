<?php

$v_inc["accfg"] = "5";

function getglobalconfig()
{
  // globalize all that nice stuff we are setting here.... (or using)
  global $db, $inbound_host, $inbound_port, $inbound_username, $inbound_password, $outbound_host, $outbound_port, $outbound_username, $outbound_password, $wmailcfg, $wmglobalcfg;

  $r = $db->query_read( "SELECT data FROM " . TABLE_PREFIX . "wmail_settings WHERE userid=0" );
  
  // no matter what I do here, some ppl still get "Not a valid SQL resource" here....
  // suggestions anyone?
  if ( $r )
  {
    $rn = 0;  // checking, checking and double checking, just to prevent errors.... and yet they still come.... :-/
	$rn = @mysql_num_rows( $r );
    if ( $rn > 0 )
    {
      $d = mysql_fetch_array( $r );
      $wmglobalcfg = unserialize( $d["data"] );

      // check for user settings that should be overridden with admin values
      if ( strlen( $wmglobalcfg["inboundhost"] ) > 1 ) { $wmailcfg["inboundhost"] = $wmglobalcfg["inboundhost"]; }
      if ( strlen( $wmglobalcfg["inboundport"] ) > 1 ) { $wmailcfg["inboundport"] = $wmglobalcfg["inboundport"]; }
      if ( strlen( $wmglobalcfg["inbounduser"] ) > 1 ) { $wmailcfg["inbounduser"] = $wmglobalcfg["inbounduser"]; }
      if ( strlen( $wmglobalcfg["inboundpwd"] ) > 1 ) { $wmailcfg["inboundpwd"] = $wmglobalcfg["inboundpwd"]; }
      if ( strlen( $wmglobalcfg["outboundhost"] ) > 1 ) { $wmailcfg["outboundhost"] = $wmglobalcfg["outboundhost"]; }
      if ( strlen( $wmglobalcfg["outboundport"] ) > 1 ) { $wmailcfg["outboundport"] = $wmglobalcfg["outboundport"]; }
      if ( strlen( $wmglobalcfg["outbounduser"] ) > 1 ) { $wmailcfg["outbounduser"] = $wmglobalcfg["outbounduser"]; }
      if ( strlen( $wmglobalcfg["outboundpwd"] ) > 1 ) { $wmailcfg["outboundpwd"] = $wmglobalcfg["outboundpwd"]; }
      if ( strlen( $wmglobalcfg["outboundauth"] ) < 9 ) { $wmailcfg["outboundauth"] = $wmglobalcfg["outboundauth"]; }
      if ( $wmglobalcfg["useforumemail"] == "1" ) { $wmailcfg["useforumemail"] = "1"; }
      if ( $wmglobalcfg["useforumsig"] == "1" ) { $wmailcfg["useforumsig"] = "1"; }
      if ( $wmglobalcfg["useforumnick"] == "1" ) { $wmailcfg["useforumnick"] = "1"; }
    } else {
      $wmglobalcfg = array();
      $s = serialize( $wmglobalcfg );
      $db->query_write( "INSERT INTO " . TABLE_PREFIX . "wmail_settings (userid, data) VALUES (0, '$s');" );
	}
  }
}

function getuserconfig( $userid, $applyglobal = true )
{
  // globalize all that nice stuff we are setting here.... (or using)
  global $vbulletin, $db, $inbound_host, $inbound_port, $inbound_username, $inbound_password, $outbound_host, $outbound_port, $outbound_username, $outbound_password, $outbound_auth, $wmailcfg;

  $r = $db->query_read( "SELECT data FROM " . TABLE_PREFIX . "wmail_settings WHERE userid = $userid" );
  if ( $r )
  {
    $rn = 0;  // checking, checking and double checking, just to prevent errors.... and yet they still come.... :-/
	$rn = @mysql_num_rows( $r );
    if ( $rn > 0 )
    {
	  $d = mysql_fetch_array( $r );
      $wmailcfg = unserialize( $d["data"] );
    } else {
      // if the query didn't return any data, there's no config for this user yet -> init it by creating an empty one
      inituserconfig( $userid );
    }
  }
  
  // if requested, load global config that might overwrite some values before stripping out the server data
  if ( $applyglobal ) { getglobalconfig(); }

  if ( $wmailcfg["useforumemail"] == "1" ) { $wmailcfg["smtpfrom"] = $vbulletin->userinfo["email"]; }
  if ( $wmailcfg["useforumsig"] == "1" ) { $wmailcfg["signature"] = $vbulletin->userinfo["signature"]; }
  if ( $wmailcfg["useforumnick"] == "1" ) { $wmailcfg["realname"] = $vbulletin->userinfo["username"]; }
  
  if ( $wmailcfg["outboundauth"] == "NONE" ) { $wmailcfg["outboundauth"] = ""; }

  $wmailcfg["fromaddr"] = "\"" . $wmailcfg["realname"] . "\" <" . $wmailcfg["smtpfrom"] . ">";
  
  if ( ( $wmailcfg["pop3port"] == "" ) OR ( $wmailcfg["pop3port"] < 1 ) ) { $wmailcfg["pop3port"] = 110; }
  if ( ( $wmailcfg["smtpport"] == "" ) OR ( $wmailcfg["smtpport"] < 1 ) ) { $wmailcfg["smtpport"] = 25; }
  
  // decode login details
  $wmailcfg["inbounduser"] = base64_decode( $wmailcfg["inbounduser"] );
  $wmailcfg["inboundpwd"] = base64_decode( $wmailcfg["inboundpwd"] );
  $wmailcfg["outbounduser"] = base64_decode( $wmailcfg["outbounduser"] );
  $wmailcfg["outboundpwd"] = base64_decode( $wmailcfg["outboundpwd"] );
  
  // check if we need to load login details from the cookie
  if ( $wmailcfg["loginascookie"] == "1" )
  {
    $tmp = unserialize( $_COOKIE[COOKIE_PREFIX . "wmaildata"] );
	$wmailcfg["inbounduser"] = $tmp["inbounduser"];
	$wmailcfg["inboundpwd"] = $tmp["inboundpwd"];
	$wmailcfg["outbounduser"] = $tmp["outbounduser"];
	$wmailcfg["outboundpwd"] = $tmp["outboundpwd"];
  }

  $inbound_host = $wmailcfg["inboundhost"];
  $inbound_port = 110;
  $inbound_username = $wmailcfg["inbounduser"];
  $inbound_password = $wmailcfg["inboundpwd"];

  $outbound_host = $wmailcfg["outboundhost"];
  $outbound_port = 25;
  $outbound_username = $wmailcfg["outbounduser"];
  $outbound_password = $wmailcfg["outboundpwd"];
  $outbound_auth = $wmailcfg["outboundauth"];
}

// a simple wrapper to the getuserconfig call
// always loads the complete config for the current user with overridden admin values
function getconfig()
{
  global $vbulletin;
  getuserconfig( $vbulletin->userinfo["userid"], true );
}

function saveuserconfig( $userid )
{
  global $db, $wmailcfg;
  
  // check if login details should be stored in a cookie
  if ( $wmailcfg["loginascookie"] == "1" )
  {
	$tmp["inbounduser"] = $wmailcfg["inbounduser"];
	$tmp["inboundpwd"] = $wmailcfg["inboundpwd"];
	$tmp["outbounduser"] = $wmailcfg["outbounduser"];
	$tmp["outboundpwd"] = $wmailcfg["outboundpwd"];
	vbsetcookie( "wmaildata", serialize( $tmp ) );
	$wmailcfg["inbounduser"] = "*";
	$wmailcfg["inboundpwd"] = "*";
	$wmailcfg["outbounduser"] = "*";
	$wmailcfg["outboundpwd"] = "*";
  }
  
  // encode login details to prevent them beeing read in cleartext
  $wmailcfg["inbounduser"] = base64_encode( $wmailcfg["inbounduser"] );
  $wmailcfg["inboundpwd"] = base64_encode( $wmailcfg["inboundpwd"] );
  $wmailcfg["outbounduser"] = base64_encode( $wmailcfg["outbounduser"] );
  $wmailcfg["outboundpwd"] = base64_encode( $wmailcfg["outboundpwd"] );
  
  $s = serialize( $wmailcfg );

  $r = $db->query_write( "UPDATE " . TABLE_PREFIX . "wmail_settings SET data='$s' WHERE userid = $userid;" );
}

function inituserconfig( $userid )
{
  global $db, $wmailcfg;
  $s = serialize( $wmailcfg );
  $db->query_write( "INSERT INTO " . TABLE_PREFIX . "wmail_settings (userid, data) VALUES ($userid, '$s');" );
}

function saveglobalconfig()
{
  global $db, $wmglobalcfg;
  $s = serialize( $wmglobalcfg );

  $r = $db->query_write( "UPDATE " . TABLE_PREFIX . "wmail_settings SET data='$s' WHERE userid = 0;" );
}

?>