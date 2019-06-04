<?php

error_reporting( E_ALL & ~E_NOTICE );
@set_time_limit( 0 );

$phrasegroups = array( "wmailadmin", "style" );
$specialtemplates = array( "products" );

require_once( "./global.php" );
require_once( DIR . "/includes/adminfunctions_template.php" );
require_once( DIR . "/wmail/inc_accessconfig.php" );

$vbulletin->input->clean_array_gpc( "r", array( "do"  => TYPE_STR,
                                                "cfg" => TYPE_ARRAY ) );

print_cp_header($vbphrase["wmail_admin_header_global"]);

if ( $vbulletin->GPC["do"] == "save" )
{
  $wmglobalcfg = $vbulletin->GPC["cfg"];
  saveglobalconfig();
  print_cp_message( $vbphrase["wmail_admin_settingssaved"], "wmail_admin_global.php", 3 );
  print_cp_footer();
  die;
}

getglobalconfig();
$cfg = $wmglobalcfg;

print_form_header( "wmail_admin_global", "save", false, false, "wmailglobalsettings", "90%", "", false, "POST" );
print_table_start();
print_table_header( $vbphrase["wmail_admin_header_global"] );
print_description_row( $vbphrase["wmail_admin_descr_global"], 0, 2, "thead");
print_input_row( $vbphrase["wmail_admin_pop3host"], "cfg[inboundhost]", $cfg["inboundhost"] );
print_input_row( $vbphrase["wmail_admin_smtphost"], "cfg[outboundhost]", $cfg["outboundhost"] );
print_radio_row( $vbphrase["wmail_admin_smtpauth"], "cfg[outboundauth]", array( "LOGIN" => "LOGIN", "PLAIN" => "PLAIN", "NONE" => "NONE", "NOOVERRIDE" => "NO OVERRIDE" ), $cfg["outboundauth"] );
print_checkbox_row( $vbphrase["wmail_admin_useforumemail"], "cfg[useforumemail]", $cfg["useforumemail"] );
print_checkbox_row( $vbphrase["wmail_admin_useforumnick"], "cfg[useforumnick]", $cfg["useforumnick"] );
print_checkbox_row( $vbphrase["wmail_admin_useforumsig"], "cfg[useforumsig]", $cfg["useforumsig"] );
print_submit_row( "Save", 0 );
print_table_footer();

print_cp_footer();

?>