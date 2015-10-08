<?php
session_start();
if ((! $_SESSION['user']) || (! $_SESSION['pass']))
  header("Location: view.php");

unset($_SESSION['city_search']);
include("config.php");

//echo "<pre>"; print_r($_POST); echo "</pre>"; die;
$dc = trim($_POST['dc']);
$role = strtolower($_POST['role']);


// Validate new NS
if ($NS = $_POST['ns']) {
  $ST = substr(preg_replace('/[^a-z]/i', '', $_POST['st']), 0, 3);
  $CO = substr(preg_replace('/[^a-z]/i', '', $_POST['co']), 0, 2);
  $NS = getNS($ST, $CO);
  //if ($NS != $tmp)  die("Error in NS number (should be $tmp)!");
  $tmp = trim("$ST.$CO", ".");
  $dc = "$NS.$tmp.";
  if ($role == "tier2") $dc .= "dns.";
  $dc .= "opennic.glue";
}
if (! $dc) header("Location: view.php");

//echo "$dc<pre>"; print_r($_POST); echo "</pre>"; die;


// Check if record exists //
$dn = "o=servers,".$LDAP['base'];
$filter = "dc=$dc";
$attr = array("*");

$ldapbind = ldap_bind($LDAP['conn'], $LDAP['admin_dn'], $LDAP['admin_pass']);
$query = @ldap_search($LDAP['conn'], $dn, $filter, $attr);
$oldrec = @ldap_get_entries($LDAP['conn'], $query);
if ($oldrec[0]['dc'][0] == $dc) $EXISTS=true; else $EXISTS=false;
//echo "<pre>"; print_r($oldrec); echo "</pre>"; die;


if (! $EXISTS) {
  // Create new record //
  $rec['objectclass'][] = "opennicDomain";
  $rec['objectclass'][] = "opennicServer";
  $rec['manager'] = $_SESSION['user_dn'];
  $rec['opennicserverrole'] = $role;
  $rec['dc'] = $dc;
    if ($role == "tier1")
      if (substr($dc,-13) != ".opennic.glue") $dc .= ".opennic.glue";
    if ($role == "tier2")
      if (substr($dc,-17) != ".dns.opennic.glue") $dc .= ".dns.opennic.glue";
  $rec['associateddomain'] = $dc;
  $rec['zonestatus'] = "APPROVAL";

} else {
  // Update some entries //
  $owner = $_POST['owner'];
  $display = $_POST['display'];
  $rec['displayname'] = array();
  foreach((array)$owner as $key => $mng)
    if ($mng) {
      $rec['manager'][] = "uid=$mng,o=users,dc=opennic,dc=glue";
      if ($alt = $display[$key]) $rec['displayname'][] = "$mng=$alt";
    }
  if ($status = strtoupper($_POST['status'])) $rec['zonestatus'] = $status;
}

//$tmp = strtoupper($_POST['disabled']);
//  if ($tmp != "TRUE") $tmp = "FALSE";
//  $rec['zoneDisabled'] = $tmp;

// A records
$rec['arecord'] = $_POST['A'];
foreach((array)$rec['arecord'] as $key => $val) {
  $ip = trim($val);
  if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
    $rec['arecord'][$key] = $ip;
    if (! $rec['arecord'][$key]) unset($rec['arecord'][$key]);
  }
}
$rec['arecord'] = array_values($rec['arecord']);

// AAAA records
$rec['aaaarecord'] = $_POST['AAAA'];
foreach((array)$rec['aaaarecord'] as $key => $val) {
  $ip = trim($val);
  if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
    $rec['aaaarecord'][$key] = $ip;
    if (! $rec['aaaarecord'][$key]) unset($rec['aaaarecord'][$key]);
  }
}
$rec['aaaarecord'] = array_values($rec['aaaarecord']);

// IPv4 Ports
$tmp = explode(",", trim($_POST['listenPort']));
foreach((array)$tmp as $key => $val) {
  $tmp[$key] = intval(trim($val, ", "));
  if (! $tmp[$key]) unset($tmp[$key]);
  if (! filter_var($portnum, FILTER_VALIDATE_INT, array("min_range" => 0, "max_range" => 65535)) === false) unset($tmp[$key]);
}
sort($tmp); $rec['listenport'] = $tmp;

// IPv6 Ports
$tmp = explode(",", trim($_POST['listenPort6']));
foreach((array)$tmp as $key => $val) {
  $tmp[$key] = intval(trim($val, ", "));
  if (! $tmp[$key]) unset($tmp[$key]);
  if (! filter_var($portnum, FILTER_VALIDATE_INT, array("min_range" => 0, "max_range" => 65535)) === false) unset($tmp[$key]);
}
sort($tmp); $rec['listenport6'] = $tmp;

// LOC record
$rec['locrecord'] = preg_replace('/[^ewnsm0-9\. ]/i', '', trim($_POST['loc']));
$rec['locrecordtxt'] = preg_replace('/[^a-z0-9\.,:;\(\) ]/i', '', trim($_POST['location']));
$tmp = strtoupper($_POST['whitelist']);
  if ($tmp != "TRUE") $tmp = "FALSE";
  $rec['usewhitelisting'] = $tmp;
$tmp = strtoupper($_POST['blacklist']);
  if ($tmp != "TRUE") $tmp = "FALSE";
  $rec['useblacklisting'] = $tmp;
$rec['description'] = trim($_POST['desc']);

// TLDs
$tmp = explode(",", trim($_POST['TLDs']));
  foreach((array)$tmp as $key => $val) {
    $tmp[$key] = trim($val, ", ");
    if (! $tmp[$key]) unset($tmp[$key]);
  }
  sort($tmp);
  $rec['opennictlds'] = $tmp;
$rec['registrarurl'] = trim($_POST['regURL'][0]);
//

// logging
$rec['logginghoursstored'] = intval($_POST['logHours']);
$tmp = strtoupper($_POST['logAnon']);
  if ($tmp != "TRUE") $tmp = "FALSE";
  $rec['logginganonymous'] = $tmp;
$rec['loggingpolicy'] = trim($_POST['logPolicy']);
//

// DNSCrypt
$tmp = strtoupper($_POST['useDNSCrypt']);
  if ($tmp != "TRUE") $tmp = "FALSE";
  $rec['usednscrypt'] = $tmp;
$rec['dnscryptserver'] = $_POST['DNSCryptServer'];
$rec['dnscryptname'] = $_POST['DNSCryptName'];
$dnscryptkey = preg_replace('/[^a-f0-9:]/i', '', $_POST['DNSCryptKey']);
  preg_match('/^([a-f0-9]{4}(\:)?){15}[a-f0-9]{4}$/i', $dnscryptkey, $match);
  if (! $match) $dnscryptkey = "";
  $rec['dnscryptkey'] = $dnscryptkey;
//  if ($match) $rec['dnscryptkey'] = $dnscryptkey;
//  else $del[] = "dnscryptkey";

// DNSCrypt IPv4 ports
$tmp = explode(",", trim($_POST['listenDNSCryptPort']));
foreach((array)$tmp as $key => $val) {
  $tmp[$key] = intval(trim($val, ", "));
  if (! $tmp[$key]) unset($tmp[$key]);
  if (! filter_var($portnum, FILTER_VALIDATE_INT, array("min_range" => 0, "max_range" => 65535)) === false) unset($tmp[$key]);
}
sort($tmp);  $rec['listendnscryptport'] = $tmp;

// DNSCrypt IPv6 ports
$tmp = explode(",", trim($_POST['listenDNSCryptPort6']));
foreach((array)$tmp as $key => $val) {
  $tmp[$key] = intval(trim($val, ", "));
  if (! $tmp[$key]) unset($tmp[$key]);
  if (! filter_var($portnum, FILTER_VALIDATE_INT, array("min_range" => 0, "max_range" => 65535)) === false) unset($tmp[$key]);
}
sort($tmp);  $rec['listendnscryptport6'] = $tmp;
//

//$rec = array_filter($rec, "nullFilter");

//echo "<pre>New "; print_r($rec); echo "</pre>";
//echo "<pre>";

if (! $dc) header("Location: /");
$dn = "dc=$dc,o=servers," . $LDAP['base'];
if ($EXISTS) {
  // modify record //
  logger("Saved record for $dc");
  $ldapbind = ldap_bind($LDAP['conn'], $LDAP['admin_dn'], $LDAP['admin_pass']);
  //$ldapbind = ldap_bind($LDAP['conn'], $_SESSION['user_dn'], $_SESSION['pass']);
  if ($rec['zonestatus'] != $oldrec[0]['zonestatus'][0])
    $rec['zonestatussince'] = gmdate("YmdHis") . "Z";
  if (($rec['zonestatus'] == $oldrec[0]['zonestatus'][0]) && ($rec['zonestatus'] == "PASS")) {
    $rec['zonestatus'] = "UPDATED";
    $rec['zonestatussince'] = gmdate("YmdHis") . "Z";
  }
//echo "<pre>OLD: "; print_r($rec); echo "</pre>";
/*
  if ($rec['zonestatus'] == $oldrec[0]['zonestatus'][0]) {
    if (($rec['zonestatus'] == "PASS")
     || ($rec['zonestatus'] == "FAIL")
     || ($rec['zonestatus'] == "DOWN")
     || ($rec['zonestatus'] == "OFFLINE"))
      $rec['zonestatus'] = "UPDATED";
  }
*/
  foreach($rec as $key => $val) {
    unset($old);
    $old[$key] = $oldrec[0][$key];
    if (isset($old[$key]['count'])) unset($old[$key]['count']);
    unset($tmp); $tmp[$key] = $rec[$key];
//echo "---------- $key<br>";
//echo "<pre>OLD: "; print_r($old); echo "</pre>";
//echo "<pre>NEW: "; print_r($tmp); echo "</pre>";
    if (count($old[$key])) @ldap_mod_del($LDAP['conn'], $dn, $old);
    if (($tmp[$key]) && ($tmp[$key] != "FALSE")) {
      ldap_modify($LDAP['conn'], $dn, $tmp);
      unset($oldrec[0][$key]['count']);

      // Logging
      if (($key != "zonestatus") && ($tmp[$key] != "UPDATED")) {
//echo "$key<pre>"; print_r($tmp); echo "</pre>";
        if (($tmp[$key] != $oldrec[0][$key]) && ($tmp[$key] != $oldrec[0][$key][0])) {
          $log = $tmp[$key];
          if (is_array($log)) {
            //$log = implode(", ", $tmp[$key]);
            logger(": Modified $key:");
            foreach((array)$log as $val) logger(": - $val");
          } else logger(": Modified $key: \"$log\"");
        }
      }

    } else if ($oldrec[0][$key]) logger(": Deleted $key");
    if (ldap_errno($LDAP['conn'])) {
      $err = ldap_error($LDAP['conn']);
      //echo "[$key] $err<br>\n";
      logger(": LDAP error updating $key: $err");
    }
  }
  //ldap_modify($LDAP['conn'], $dn, $rec);

} else {
  // add new record //
  logger("Created record for $dc");
  $ldapbind = ldap_bind($LDAP['conn'], $LDAP['admin_dn'], $LDAP['admin_pass']);
  ldap_add($LDAP['conn'], $dn, $rec);
//echo "<pre>"; print_r($rec); echo "</pre>";
}


header("Location: edit.php?srv=$dc");


function nullFilter($var) {
  if (is_array($var)) {
    if (! count($var)) return false;

  } else {
    if ($var == "0") return true;
    if ($var == "") return false;
  }
  return true;
}
