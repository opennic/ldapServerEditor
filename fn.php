<?php
error_reporting(E_ALL ^ E_NOTICE);

$STATUS = array(
	"APPROVAL" => array(
		"stat" => "needs approval",
		"button" => "Approve",
		"title" => "Awaiting admin approval",
		"css" => "app",
		"abbr" => "Aprv",
	),
	"NEW" => array(
		"stat" => "new",
		"button" => "New",
		"title" => "New server",
		"css" => "new",
		"abbr" => "New",
	),
	"UPDATED" => array(
		"stat" => "updated",
		"button" => "Updated",
		"title" => "Changes have been made",
		"css" => "updt",
		"abbr" => "Updt",
	),
	"UPDT" => array( 	// Not a status, only used for buttons
		"title" => "New or updated",
		"abbr" => "Chg",
	),
	"PASS" => array(
		"stat" => "normal",
		"button" => "Pass",
		"title" => "Passing",
		"css" => "norm",
		"abbr" => "Ok",
	),
	"FAIL" => array(
		"stat" => "temp outage",
		"button" => "Failed",
		"title" => "Failed testing",
		"css" => "fail",
		"abbr" => "Fail",
	),
	"DOWN" => array(
		"stat" => "down",
		"button" => "Down",
		"title" => "Server not responding",
		"css" => "dwn",
		"abbr" => "Down",
	),
	"OFFLINE" => array(
		"stat" => "offline",
		"button" => "Offline",
		"title" => "Extended failure",
		"css" => "off",
		"abbr" => "Offln",
	),
	"ERR" => array( 	// Not a status, only used for buttons
		"title" => "Errors detected",
		"abbr" => "Err",
	),
	"DISABLED" => array(
		"stat" => "disabled",
		"button" => "Disabled",
		"title" => "Temporarily shut down",
		"css" => "dis",
		"abbr" => "Dis",
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

function logger($txt) {
  global $logfile;

  $ts = date("M d H:i:s");
  $tld = ($TLD) ? "$TLD: " : "";
  $txt = trim($txt, "\n") . "\n";

  if ($txt) {
    $fh = fopen($logfile, "a");
    if (! $usr = $_SESSION['user']) $usr = $_SERVER['REMOTE_ADDR'];
    fwrite($fh, "$ts [$usr] $txt");
    fclose($fh);
  }
}

function get_client_ip() {
  $ipaddress = '';
  if ($_SERVER['HTTP_CLIENT_IP'])
    $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
  else if($_SERVER['HTTP_X_FORWARDED_FOR'])
    $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
  else if($_SERVER['HTTP_X_FORWARDED'])
    $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
  else if($_SERVER['HTTP_FORWARDED_FOR'])
    $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
  else if($_SERVER['HTTP_FORWARDED'])
    $ipaddress = $_SERVER['HTTP_FORWARDED'];
  else if($_SERVER['REMOTE_ADDR'])
    $ipaddress = $_SERVER['REMOTE_ADDR'];
  else
    $ipaddress = 'UNKNOWN';
  return $ipaddress;
}

function getNS($region, $country, $tier=2) {
  // Get the first available NS number for a given region/country
  global $LDAP;

  $dn = "o=servers,".$LDAP['base'];
  $server_name = trim("$region.$country", ".");
//echo "$server_name\n";

  if ($tier == 1)  $filter = "(opennicserverrole=tier1)";
  if ($tier == 2)
    $filter = "(&(dc=*.$server_name.dns.opennic.glue)(opennicserverrole=tier2))";

  $attr = array("dc");
  $ldapbind = ldap_bind($LDAP['conn'], $LDAP['admin_dn'], $LDAP['admin_pass']);
  $query = @ldap_search($LDAP['conn'], $dn, $filter, $attr);
  $res = @ldap_get_entries($LDAP['conn'], $query);
//echo "<pre>"; print_r($res); die;

//echo "<pre>";
  $lastNS = 0;
  foreach((array)$res as $arr) {
    if ($dc = $arr['dc'][0]) {
      if ($tier == 1)
        preg_match("/^ns([0-9]{1,3})(\.opennic.glue)/i", $dc, $match);
      if ($tier == 2)
        preg_match("/^ns([0-9]{1,3})(\.$server_name)/i", $dc, $match);
//      echo "$dc($tier): <pre>"; print_r($match); echo "</pre>";
      if (($match[2]) && ($match[1] > $lastNS)) $lastNS = $match[1];
    }
  }
  $lastNS++;
  $server_name = "ns$lastNS.$server_name";

  return "ns$lastNS";
}

function diffdate($tm1, $tm2=0) {
  if (! $tm2) $tm2 = time();
  if (! $tm1) return array();
  $diff = intval(abs($tm2 - $tm1) / 60); // difference in minutes
  $dt['time1'] = $tm1;
  $dt['time2'] = $tm2;
  $dt['diff'] = $diff;

  $dt['totalhours'] = intval($diff / 60);
  $dt['totaldays'] = intval($diff / 1440);
  $weeks = intval($diff / 10080);  $diff -= $weeks * 10080;
  $days = intval($diff / 1440); $diff -= $days * 1440;
  $hours = intval($diff / 60); $diff -= $hours * 60;

  $dt['weeks'] = $weeks;
  $dt['days'] = $days;
  $dt['hours'] = $hours;
  $dt['minutes'] = $diff;

  if ($weeks) $str .= "$weeks week".S($weeks).", ";
  if ($days) $str .= "$days day".S($days).", ";
  if (($hours) && (! $weeks)) $str .= "$hours hour".S($hours).", ";
  if (! $str) $str = "$diff minute".S($diff);
  $dt['string'] = trim($str, ", ");

  return $dt;
}

function S($num) {
  if ($num == 1) return "";
  else return "s";
}
