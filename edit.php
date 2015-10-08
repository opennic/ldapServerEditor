<?php
/********************
- Don't allow user to approve their own new record
- Show map with server location
  - for aprovals, also show map with geoip location

********************/

session_start();
if ((! $_SESSION['user']) || (! $_SESSION['pass']))
  $READONLY = " readonly";
  //header("Location: login.php");
if (! $_SESSION['lastpage']) $_SESSION['lastpage'] = "view.php";

include("config.php");
$title = "OpenNIC Server Registration";
//echo "GET <pre>"; print_r($_GET); echo "</pre>";
//echo "SESSION <pre>"; print_r($_SESSION); echo "</pre>";


// Check if user is T1 op
$dn = "o=admins,".$LDAP['base'];
$filter = "tier1operator=".$_SESSION['user_dn'];
$attr = array("tier1operator");
$ldapbind = ldap_bind($LDAP['conn'], $LDAP['admin_dn'], $LDAP['admin_pass']);
$query = @ldap_search($LDAP['conn'], $dn, $filter, $attr);
$res = @ldap_get_entries($LDAP['conn'], $query);
if ($res['count']) $ADMIN = true;


// Get server info //
if ($SRV = $_GET['srv']) {
  $dn = "o=servers,".$LDAP['base'];
  $filter = "dc=$SRV";
  $attr = array("*", "+");

  if ($_SESSION['user']) {
    $ldapbind = ldap_bind($LDAP['conn'], $_SESSION['user_dn'], $_SESSION['pass']);
  } else {
    $ldapbind = ldap_bind($LDAP['conn'], $LDAP['admin_dn'], $LDAP['admin_pass']);
    $READONLY = " readonly";
  }
  $query = @ldap_search($LDAP['conn'], $dn, $filter, $attr);
  $res = @ldap_get_entries($LDAP['conn'], $query);
  unset($res['count']);
  //if (! count($res)) $READONLY = " readonly";
  if (! count($res)) header("Location: ".$_SESSION['lastpage']);
  $data = $res[0];
  $_SESSION['last_edit'] = $SRV;
//  echo "<pre>"; print_r($data); echo "</pre>";
} else if (! $_SESSION['user']) header("Location: view.php");
$_SESSION['lastsrv'] = $SRV;


// Check if new server city was passed
$NS = "ns??";
if ((! $SRV) && (! $READONLY)) {
  $search = $_SESSION['city_search'];
  $addr = $_GET['addr'];
  if ($results = ($search[$addr])) {
    // User chose one of the results

    $lat = $results['lat'];
      $LAT = DECtoDMS($lat);
      $LAT['dir'] = ($LAT['deg'] < 0) ? "S" : "N";
      $LAT['deg'] = abs($LAT['deg']);
    $lng = $results['lng'];
      $LNG = DECtoDMS($lng);
      $LNG['dir'] = ($LNG['deg'] < 0) ? "W" : "E";
      $LNG['deg'] = abs($LNG['deg']);

    $elev_stats = file_get_contents("http://maps.googleapis.com/maps/api/elevation/json?locations=$lat,$lng");
    $output = json_decode($elev_stats);
    $elev = $output->results[0]->elevation;
    $res = round($output->results[0]->resolution, 0);

    $LOC  = $LAT['deg']." ".$LAT['min']." ".$LAT['sec']." ".$LAT['dir']." ";
    $LOC .= $LNG['deg']." ".$LNG['min']." ".$LNG['sec']." ".$LNG['dir']." ";
    $LOC .= number_format($elev, 2, '.', '') . "m {$res}m 100m 10m";
    $data['locrecord'][0] = $LOC;
    //$results['locrecord'] = $LOC;

    $ST = strtolower($results['region']['short']);
    $CO = strtolower($results['country']['short']);

    $NS = getNS($ST, $CO);

//echo "<pre>"; print_r($results); echo "</pre>";
  }
}


// Get owner info
unset($data['manager']['count']);
foreach ((array)$data['manager'] as $key => $dn) {
  if ($dn == $_SESSION['user_dn']) $OWNER = true;
  if (! $dn) $dn = $_SESSION['user_dn'];
  $filter = "uid=*";
  $attr = array("uid", "cn", "mail");

  if ($_SESSION['user']) {
    $ldapbind = ldap_bind($LDAP['conn'], $_SESSION['user_dn'], $_SESSION['pass']);
  } else {
    $ldapbind = ldap_bind($LDAP['conn'], $LDAP['admin_dn'], $LDAP['admin_pass']);
  }
  $query = @ldap_search($LDAP['conn'], $dn, $filter, $attr);
  $res = @ldap_get_entries($LDAP['conn'], $query);
  unset($res['count']);
  $uid[$key] = $res[0]['uid'][0];
  $name[$key] = $res[0]['cn'][0];
  $mail[$key] = $res[0]['mail'][0];
}

if ((! $ADMIN) && (! $OWNER)) $READONLY = " readonly";
if ($READONLY) $DISABLED = " disabled";


function DECtoDMS($dec) {
  $vars = explode(".",$dec);
  $deg = $vars[0];
  $tempma = "0.".$vars[1];

  $tempma = $tempma * 3600;
  $min = floor($tempma / 60);
  $sec = number_format($tempma - ($min*60), 3, '.', '');

  return array("deg"=>$deg,"min"=>$min,"sec"=>$sec);
}
?>
<!DOCTYPE html>
<head>
  <meta content="text/html; charset=UTF-8" http-equiv="content-type">
  <title><?=$title?></title>
  <link rel="stylesheet" href="style.css" type="text/css" media="all">

  <script type="text/javascript">
<?/* if ((! $SRV) && (! $READONLY)) { ?>
   window.open('loc.php','Enter city','toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=350,height=220')
<? }*/ ?>
    function tier1() {
      //alert("tier1");
      document.getElementById('dc').innerHTML = ". opennic . glue";
      document.getElementById('dnscrypt').style.display = "none";
    }

    function tier2() {
      //alert("tier2");
      document.getElementById('dc').innerHTML = ". dns . opennic . glue";
      document.getElementById('dnscrypt').style.display = "block";
    }

    function getNS() {
      var st = document.getElementById('st').value;
      var co = document.getElementById('co').value;
      var data = httpGet("getNS.php?st="+st+"&co="+co);
      //alert(data);
      if (data) {
        document.getElementById('ns').value = data;
        document.getElementById('nsn').innerHTML = data;
      }
    }

    function httpGet(url) {
      var xmlHttp = null;
      xmlHttp = new XMLHttpRequest();
      xmlHttp.open( "GET", url, false );
      xmlHttp.send( null );
      return xmlHttp.responseText;
    }
  </script>
</head>

<body>
<? if (! $READONLY) { ?>
<form id="frm" name="frm" action="_edit.php" method="post">
<? } ?>

<div id="frame">
 <div id="edit">
<? $close = ($SRV) ? "Close" : "Cancel"; ?>
<? /*
  <a href='<?=$_SESSION['lastpage']."#$SRV"?>'><button type='button' style='float:left' autofocus><?=$close?></button></a>
*/ ?>
  <a href='<?=$_SESSION['lastpage']?>'><button type='button' style='float:left' autofocus><?=$close?></button></a>
<? if (! $READONLY) { ?>
  <input type='submit' value='Update' style='float:right'>
<? } ?>

  <b><?
if ($SRV)
 echo "$SRV<input type='hidden' id='dc' name='dc' value='$SRV'>";
else {
// echo "<input type='text' id='dc' name='dc' size='12' value='' autofocus> <label id='dc'>. dns . opennic . glue</label>";
  echo "<input type='hidden' id='ns' name='ns' value='$NS'><label id='nsn'>$NS</label> . ";
  echo "<input type='text' id='st' name='st' size=2 maxlength=3 value='$ST' autofocus title='Region/state (optional)' onblur='getNS()'> . ";
  echo "<input type='text' id='co' name='co' size=2 maxlength=2 value='$CO' title='Country (required)' onblur='getNS()'> . ";
  echo "<label id='dc'>dns . opennic . glue</label>\n";
}
?></b><br>
<?
if ($SRV)
  echo "  Created: " . gmdate("Y-M-d H:i", strtotime($data['createtimestamp'][0])) . " UTC<br>\n";

if ($READONLY) {
  foreach((array)$uid as $val)
    echo "  Owner: <b>$val</b><br>\n";
} else {
  $tmp = "<label>Owner(s):</label>"; $val = "";
  echo "<div class='owners'>";
  foreach((array)$mail as $key => $val) {
    echo "$tmp";
//    if ($ADMIN)
      echo "<input type='text' value='" . $uid[$key] . "' name='owner[$key]' size=12> "; // . $name[$key];
//      else echo $uid[$key];  //echo "$name[$key]";
    if ($ad = $mail[$key]) echo " <a href='mailto:$ad'>&lt;$ad&gt;</a><br>\n";
    $tmp = "<label></label>";
  }
  $key++;
  if ( (($ADMIN) || ($OWNER)) && ($val) )
    echo "$tmp<input type='text' value='' name='owner[$key]' size=12>";
  echo "</div>\n";
}

if ($ADMIN) {
  $status = strtoupper($data['zonestatus'][0]);
  switch ($status) {
    case "APPROVAL":
      if ($OWNER) $btn = array("APPROVAL", "PENDING");
      else $btn = array("APPROVAL", "NEW", "PENDING");
      break;
    case "NEW":
      $btn = array("NEW", "UPDATED", "PASS", "FAIL", "OFFLINE", "PENDING");
      break;
    case "UPDATED":
      $btn = array("UPDATED", "PASS", "FAIL", "OFFLINE", "PENDING");
      break;
    case "PASS":
      $btn = array("UPDATED", "PASS", "FAIL", "OFFLINE", "PENDING");
      break;
    case "FAIL":
      $btn = array("UPDATED", "PASS", "FAIL", "OFFLINE", "PENDING");
      break;
    case "OFFLINE":
      $btn = array("UPDATED", "PASS", "FAIL", "OFFLINE", "PENDING");
      break;
    case "PENDING":
      $btn = array("UPDATED", "PENDING", "DELETED");
      break;
    case "DELETED":
      $btn = array("UPDATED", "DELETED");
      break;
    default:
      // New record being created
  }

  echo "    <ul class='cbx'>\n";
  foreach((array)$btn as $id) {
    $chk = $STATUS[$id]['stat'];
    $txt = $STATUS[$id]['button'];
    $title = $STATUS[$id]['title'];
    echo "    <li><input type='radio' id='stat[$id]' name='status' value='$id'";
    if ($status==$id) echo "checked";
    echo "><label for='stat[$id]'>$txt</label></li>\n";
  }
  echo "    </ul>\n";
}
?>

  <hr>
<!--  <label><input type='checkbox' name='disabled' value='TRUE'<?if ($data['zonedisabled']=="TRUE") echo " checked=1"?>>Disabled</label><br> -->
<?
$role = $data['opennicserverrole'][0];
if (($SRV) && (! $ADMIN)) {
  // server role has already been set
  echo "  <b>".ucfirst($role)."</b>\n";
  echo "  <input type='hidden' name='role' value='$role'>\n";
} else {
  // <select option> //
  echo "  <b>Server role:</b> <select name='role'>\n";
  if ($ADMIN) echo "  <option value='tier1' onclick='tier1()'>Tier1</option>\n";
?>
  <option value='tier2' selected onclick='tier2()'>Tier2</option>
  </select>
<?
}
?>
  <br><br>
  <div style='width:300px; text-align:left; margin:0 auto'>
<?
$key = 0;
echo "<label class='record'>A</label> <input type='text' name='A[$key]' size=12 value='".$data['arecord'][0]."'$READONLY><br>\n";
/* THIS ALLOWS FOR MULTIPLE ENTRIES
foreach((array)$data['arecord'] as $key => $rec) {
  if (is_numeric($key))
    echo "<label class='record'>A</label> <input type='text' name='A[$key]' size=12 value='$rec'$READONLY><br>\n";
}
$key++;
if (! $READONLY)
  echo "<label class='record'>A</label> <input type='text' name='A[$key]' size=12 value=''><br>\n";
*/

$key = 0;
echo "<label class='record'>AAAA</label> <input type='text' name='AAAA[$key]' size=25 value='".$data['aaaarecord'][0]."'$READONLY><br>\n";
/* THIS ALLOWS FOR MULTIPLE ENTRIES
foreach((array)$data['aaaarecord'] as $key => $rec) {
  if (is_numeric($key))
    echo "<label class='record'>AAAA</label> <input type='text' name='AAAA[$key]' size=25 value='$rec'$READONLY><br>\n";
}
$key++;
if (! $READONLY)
  echo "<label class='record'>AAAA</label> <input type='text' name='AAAA[$key]' size=25 value=''><br>\n";
*/
?>
  </div>

  <p style='margin-left:56px; text-align:left'>Location:
  <input type='text' id='loc' name='loc' size=48 value='<?=$data['locrecord'][0]?>'<?=$READONLY?>>
<? if (! $READONLY) { ?>
  <img src='loc.png' align='top' style='cursor:pointer'
   onclick="window.open('loc.php','Enter city','toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=350,height=220')">
<? } ?>
  <br>
<? if (! $addr) $addr = $data['locrecordtxt'][0]; ?>
  <span style='display:inline-block;width:64px'></span>
  <input type='text' id='location' name='location' size=20 value='<?=$addr?>'<?=$READONLY?>>
  <small><i>(City, region, country)</i></small>
  </p>

<?
$port = "";
  unset($data['listenport']['count']);
  foreach((array)$data['listenport'] as $val) $port .= "$val, ";
  $port = trim($port, ", ");
$port6 = "";
  unset($data['listenport6']['count']);
  foreach((array)$data['listenport6'] as $val) $port6 .= "$val, ";
  $port6 = trim($port6, ", ");
?>
  <i><small>Other than the standard port 53, what ports does this server listen to?</small></i>
  <div style='text-align:left; margin:0 50px'>
    <div style='display:inline-block; float:right'>IPv6 ports&nbsp;<input type='text' name='listenPort6' value='<?=$port6?>' size=15 title='Enter each port number separated by a comma'<?=$READONLY?>>&nbsp;</div>
    IPv4 ports&nbsp;<input type='text' name='listenPort' value='<?=$port?>' size=15 title='Enter each port number separated by a comma'<?=$READONLY?>>
  </div>

  <br>
  <div style='text-align:left; margin:0 62px'>
  <label style='display:inline-block; float:right'><input type='checkbox' name='blacklist' value='TRUE'<? if ($data['useblacklisting'][0]=="TRUE") echo " checked=1"?>'<?=$DISABLED?>> Blacklisting</label>
  <label><input type='checkbox' name='whitelist' value='TRUE'<? if ($data['usewhitelisting'][0]=="TRUE") echo " checked=1"?>'<?=$DISABLED?>> Whitelisting</label>
  </div>

  <hr>
  <b>Logging</b><br>
  <div style='text-align:left; margin:0 62px'>
  <div style='display:inline-block; float:right'><label><input type='checkbox' name='logAnon' value='TRUE'<? if ($data['logginganonymous'][0]=="TRUE") echo " checked=1"?><?=$DISABLED?>>&nbsp;Anonymous</label></div>
  Retained for <input type='text' name='logHours' size=2 value='<?=$data['logginghoursstored'][0]?>'<?=$READONLY?>> hours<br>
  </div>
  Policy: <input type='text' name='logPolicy' size=50 value='<?=$data['loggingpolicy'][0]?>'<?=$READONLY?>><br>

<?
if ((! $SRV) || ($role == "tier2")) {
  $port = "";
    unset($data['listendnscryptport']['count']);
    foreach((array)$data['listendnscryptport'] as $val) $port .= "$val, ";
    $port = trim($port, ", ");
  $port6 = "";
    unset($data['listendnscryptport6']['count']);
    foreach((array)$data['listendnscryptport6'] as $val) $port6 .= "$val, ";
    $port6 = trim($port6, ", ");
?>
  <div id='dnscrypt'>
    <hr>
    <b>DNSCrypt</b><br>
    <div style='text-align:left; margin:0 10px'>
      <label style='display:inline-block; float:right; margin-right:14px;'><input type='checkbox' name='useDNSCrypt' value='TRUE'<? if ($data['usednscrypt'][0]=="TRUE") echo " checked=1"?><?=$DISABLED?>>&nbsp;Enabled</label>
      <label class='dnscrypt'>Server</label>&nbsp;<input type='text' name='DNSCryptServer' size=35 value='<?=$data['dnscryptserver'][0]?>'<?=$READONLY?>><br>
      <label class='dnscrypt'>DNSCrypt-Name</label>&nbsp;<input type='text' name='DNSCryptName' size=54 value='<?=$data['dnscryptname'][0]?>'<?=$READONLY?>><br>
      <label class='dnscrypt'>DNSCrypt-Key</label>&nbsp;<textarea name='DNSCryptKey' style='width:445px; height:28px; vertical-align:top;'<?=$READONLY?>><?=$data['dnscryptkey'][0]?></textarea><br>
      <div style='display:inline-block; float:right'><label class='dnscrypt'>IPv6 ports</label>&nbsp;<input type='text' name='listenDNSCryptPort6' value='<?=$port6?>' size=15 title='Enter each port number separated by a comma'<?=$READONLY?>>&nbsp;</div>
      <label class='dnscrypt'>IPv4 ports</label>&nbsp;<input type='text' name='listenDNSCryptPort' value='<?=$port?>' size=15 title='Enter each port number separated by a comma'<?=$READONLY?>>
    </div>
  </div>
<? } ?>

  <hr>

  <b>Description</b><br>
  <textarea name="desc" rows=3 cols=63<?=$READONLY?>><?=$data['description'][0]?></textarea><br>
<?
if ($role == "tier1") {
  $TLDS = "";
  unset($data['opennictlds']['count']);
  foreach((array)$data['opennictlds'] as $tld) $TLDS .= "$tld, ";
  $TLDS = trim($TLDS, ", ");
?>
  <br>
  <label>TLDs</label>: <input type='text' name='TLDs' size=40 value='<?=$TLDS?>' title='Enter each TLD separated by a comma'><br>
<?
/*
  $key = 0;
  foreach((array)$data['registrarurl'] as $key => $rec) {
    if (is_numeric($key))
      echo "<label>Registrar</label>: <input type='text' name='regURL[$key]' size=25 value='$rec'><br>\n";
  }
  $key++;
  echo "<label>Registrar</label>: <input type='text' name='regURL[$key]' size=25 value=''><br>\n";
*/
  echo "<label>Registrar</label>: <input type='text' name='regURL[0]' size=40 value='". $data['registrarurl'][0] . "'><br>\n";
}
?>
  </div>
</div>

<? if (! $READONLY) echo "</form>\n"; ?>
</body>
</html>
