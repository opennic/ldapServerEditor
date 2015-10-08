<?php
error_reporting(E_ALL ^ E_NOTICE);

$STATUS = array(
	"APPROVAL" => array(
		"stat" => "needs approval",
		"button" => "Approve",
		"title" => "Awaiting admin approval",
		"css" => "app",
		"abbr" => "Ap",
	),
	"NEW" => array(
		"stat" => "new",
		"button" => "New",
		"title" => "New server",
		"css" => "new",
		"abbr" => "Nw",
	),
	"UPDATED" => array(
		"stat" => "updated",
		"button" => "Updated",
		"title" => "Changes have been made",
		"css" => "updt",
		"abbr" => "Upd",
	),
	"PASS" => array(
		"stat" => "normal",
		"button" => "Normal",
		"title" => "Passing",
		"css" => "norm",
		"abbr" => "Ok",
	),
	"FAIL" => array(
		"stat" => "temp outage",
		"button" => "Outage",
		"title" => "Failing some tests",
		"css" => "out",
		"abbr" => "Fa",
	),
	"OFFLINE" => array(
		"stat" => "offline",
		"button" => "Offline",
		"title" => "Offline, not responding",
		"css" => "off",
		"abbr" => "Ol",
	),
	"PENDING" => array(
		"stat" => "pending deletion",
		"button" => "Remove",
		"title" => "Pending removal",
		"css" => "rem",
		"abbr" => "Rm",
	),
	"DELETED" => array(
		"stat" => "deleted",
		"button" => "Deleted",
		"title" => "No longer listed",
		"css" => "del",
		"abbr" => "X",
	),
);



/********** FUNCTIONS **********/

function getNS($st, $cn) {
  global $LDAP;

  $server_name = trim("$st.$cn", ".");
//echo "$server_name\n";

  $dn = "o=servers,".$LDAP['base'];
  $filter = "dc=*.$server_name.dns.opennic.glue";
  $attr = array("dc");
  $ldapbind = ldap_bind($LDAP['conn'], $LDAP['admin_dn'], $LDAP['admin_pass']);
  $query = @ldap_search($LDAP['conn'], $dn, $filter, $attr);
  $res = @ldap_get_entries($LDAP['conn'], $query);
//echo "<pre>"; print_r($res); die;

//echo "<pre>";
  $lastNS = 0;
  foreach((array)$res as $arr) {
    if ($dc = $arr['dc'][0]) {
      preg_match("/^ns([0-9]{1,3})(\.$server_name)/i", $dc, $match);
      //echo "$dc: "; print_r($match);
      if (($match[2]) && ($match[1] > $lastNS)) $lastNS = $match[1];
    }
  }
  $lastNS++;
  $server_name = "ns$lastNS.$server_name";

  return "ns$lastNS";
}
