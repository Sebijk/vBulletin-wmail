<?php

error_reporting( E_ALL & ~E_NOTICE );
@set_time_limit( 0 );

$phrasegroups = array( "wmailadmin", "style" );
$specialtemplates = array( "products" );

require_once( "./global.php" );
require_once( DIR . "/includes/adminfunctions_template.php" );

print_cp_header( $vbphrase["wmail_admin_header_repair"] );

if ( $_REQUEST["do"] == "repair" )
{
  print_dots_start( $vbphrase["wmail_admin_header_repair"] );
  echo "<div class=\"smallfont\"><strong>Setting up tables....</strong></div><br /><br />";
  $vbulletin->db->reporterror = false;
  $vbulletin->db->query_write( "CREATE TABLE " . TABLE_PREFIX . "wmail_settings ( userid int(11) NOT NULL default '0', data text NOT NULL, PRIMARY KEY  (userid) );" );
  echo "<div class=\"smallfont\">Table '" . TABLE_PREFIX . "wmail_settings' created....</div><br />";
  $vbulletin->db->query_write( "CREATE TABLE " . TABLE_PREFIX . "wmail_adrbook (entryid INT NOT NULL AUTO_INCREMENT, userid INT NOT NULL, name CHAR( 128 ) NOT NULL, email CHAR( 128 ) NOT NULL, PRIMARY KEY ( entryid ) );" );
  echo "<div class=\"smallfont\">Table '" . TABLE_PREFIX . "wmail_adrbook' created....</div><br />";
  $vbulletin->db->query_write( "CREATE TABLE " . TABLE_PREFIX . "wmail_readmarks (userid int(11) NOT NULL default '0', msgid char(128) NOT NULL default '');" );
  echo "<div class=\"smallfont\">Table '" . TABLE_PREFIX . "wmail_readmarks' created....</div><br />";
  $vbulletin->db->query_write( "ALTER TABLE " . TABLE_PREFIX . "usergroup ADD wmailpermissions INT( 10 ) UNSIGNED NOT NULL;" );
  echo "<div class=\"smallfont\">Table '" . TABLE_PREFIX . "usergroup' altered (column 'wmailpermissions' added)....</div><br />";
  $vbulletin->db->reporterror = true;
  echo "<br /><div class=\"smallfont\"><strong>Table setup complete....</strong></div><br /><br />";
  print_dots_stop();
  print_cp_message( $vbphrase["wmail_admin_repair1done"], "index.php?do=buildbitfields", 7 );
} else {
  print_form_header( "wmail_admin_repair", "repair", false, false, "wmailrepair", "70%", "", false, "POST" );
  print_table_start( true, "70%" );
  print_table_header( $vbphrase["wmail_admin_header_global"] );
  print_description_row($vbphrase["wmail_admin_repairdescr"], false, 2, '', 'center' );
  print_submit_row( "-- GO --", 0 );
  print_table_footer();
}

print_cp_footer();

?>