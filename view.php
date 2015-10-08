<?php
session_start();
$_SESSION['lastpage'] = "view.php";

/********** PARSE URL OPTIONS **********/

// Set which servers to show
if ($show = strtolower($_GET['show'])) {
  if ($show == "all") $show = "";
  $_SESSION['show'] = $show;
  header("Location: view.php");
}
$show = $_SESSION['show'];

// Select sort order
if ($sort = $_GET['sort']) {
  $_SESSION['sort'] = strtolower(substr($sort,0,4));
  header("Location: view.php");
}
$sort = $_SESSION['sort']; if (! $sort) $sort = "host";

// Select tier to show
if ($tier = intval($_GET['tier'])) {
  $_SESSION['tier'] = $tier;
  header("Location: view.php");
}
$tier = $_SESSION['tier']; if ($tier != 1) $tier = 2;

// Select country group to show
if ($ccg = strtoupper($_GET['ccg'])) {
  $_SESSION['ccg'] = $ccg;
  header("Location: view.php");
}
$ccg = $_SESSION['ccg']; if (! $ccg) $ccg = "ALL";


/********** LOAD DATA TO DISPLAY PAGE **********/

include("config.php");
$pagetitle = "OpenNIC Public Servers";
include("CC.php");
if (($ccg != "ALL") && (! $ccTLD[$ccg])) $ccg = "ALL";


// Check if user is T1 op
$dn = "o=admins,".$LDAP['base'];
$filter = "tier1operator=".$_SESSION['user_dn'];
$attr = array("tier1operator");
$ldapbind = ldap_bind($LDAP['conn'], $LDAP['admin_dn'], $LDAP['admin_pass']);
$query = @ldap_search($LDAP['conn'], $dn, $filter, $attr);
$res = @ldap_get_entries($LDAP['conn'], $query);
if ($res['count']) $ADMIN = true;


// Get list of all servers //
$dn = "o=servers,".$LDAP['base'];
$filter = "opennicserverrole=tier$tier";
if ($show == "mine") $filter = "(&($filter)(manager=".$_SESSION['user_dn']."))";
  else if ($show) $filter = "(&($filter)(zonestatus=$show))";
if ((! $ADMIN) && (! $show)) $filter = "(&($filter)(!(zonestatus=deleted)))";
$attr = array("*");

$ldapbind = ldap_bind($LDAP['conn'], $LDAP['admin_dn'], $LDAP['admin_pass']);
$query = @ldap_search($LDAP['conn'], $dn, $filter, $attr);
@ldap_sort($LDAP['conn'], $query, "dc");
$servers = @ldap_get_entries($LDAP['conn'], $query);
unset($servers['count']);
uasort($servers, "sort$sort");

//echo "<pre>"; print_r($_SESSION); echo "</pre>"; die;
//echo "<pre>"; print_r($servers); echo "</pre>"; die;

$count = 0;
foreach ((array)$servers as $id => $srv) {
  $stat = strtoupper($srv['zonestatus'][0]);
  if ($stat != "DELETED")  $count++;
  $stats[$stat]++;
  if ($srv['manager'][0] == $_SESSION['user_dn']) $stats['MINE']++;

  preg_match('#(.*)\.([a-z]{1,3})(\.dns?)\.opennic\.glue#i', $srv['dc'][0], $match);
  $cc = strtoupper($match[2]);
  if ($ccTLD[$cc]) $CC[$cc][] = $srv; //['dc'][0];
}
//echo "<pre>"; print_r($CC); echo "</pre>"; die;
//echo "<pre>"; print_r($stats); echo "</pre>";



function DMStoDEC($deg,$min,$sec) {
  // Converts degrees/minutes/seconds to decimal format
  return $deg+((($min*60)+($sec))/3600);
}

function DECtoDMS($dec) {
  // Converts decimal format to degrees/minutes/seconds
  $vars = explode(".",$dec);
  $deg = $vars[0];
  $tempma = "0.".$vars[1];

  $tempma = $tempma * 3600;
  $min = floor($tempma / 60);
  $sec = $tempma - ($min*60);

  return array("deg"=>$deg,"min"=>$min,"sec"=>$sec);
}

function button($val) {
  if (is_array($val)) $val = $val[0];
  $val = strtoupper($val);
  $bool = 0;
  if ($val > 0) $bool = 1;
  if ($val == "TRUE") $bool = 1;
  return $bool;
}

function sorthost($a, $b) {
  $pcsA = explode(".", $a['dc'][0]);
  unset($a0, $a1, $a2, $a3);
  $a2 = trim($pcsA[0], "ns");
  $a0 = $pcsA[1];
  if ($pcsA[0] == "ipv6") { $a0="";  $a2=trim($pcsA[1],"ns"); $a3=$pcsA[0]; }
  if (count($pcsA) > 2) { $a0=$pcsA[2]; $a1=$pcsA[1]; }
  if ($a0 == "dns") { $a0=$a1; $a1=$a2; $a2=""; }

  $pcsB = explode(".", $b['dc'][0]);
  unset($b0, $b1, $b2, $b3);
  $b2 = trim($pcsB[0], "ns");
  $b0 = $pcsB[1];
  if ($pcsB[0] == "ipv6") { $b0=""; $b2=trim($pcsB[1],"ns"); $b3=$pcsB[0]; }
  if (count($pcsB) > 2) { $b0=$pcsB[2]; $b1=$pcsB[1]; }
  if ($b0 == "dns") { $b0=$b1; $b1=$b2; $b2=""; }

//  echo "[$a0][$a1][$a2] -- [$b0][$b1][$b2]<br>";

  if ($a0 < $b0) return (-1);
  if ($a0 > $b0) return (1);

  if ($a1 < $b1) return (-1);
  if ($a1 > $b1) return (1);

  if ($a2 < $b2) return (-1);
  if ($a2 > $b2) return (1);

  if ($a3 < $b3) return (-1);
  if ($a3 > $b3) return (1);

  return (0);
}

function sortownr($a, $b) {
  $a0 = $a['manager'];
  $b0 = $b['manager'];

  if ($a0 < $b0) return (-1);
  if ($a0 > $b0) return (1);
  return (0);
}

function sortipv4($a, $b) {
  $a0 = $a['arecord'];
  $b0 = $b['arecord'];

  if ((! $a0) && (! $b0)) return sorthost($a, $b);
  if (! $a0) return 1;
  if (! $b0) return -1;

  if ($a0 < $b0) return (-1);
  if ($a0 > $b0) return (1);
  return (0);
}

function sortipv6($a, $b) {
  $a0 = $a['aaaarecord'];
  $b0 = $b['aaaarecord'];

  if ((! $a0) && (! $b0)) return sorthost($a, $b);
  if (! $a0) return 1;
  if (! $b0) return -1;

  if ($a0 < $b0) return (-1);
  if ($a0 > $b0) return (1);
  return (0);
}

function sortstat($a, $b) {
  $a0 = $a['zonestatus'];
  $b0 = $b['zonestatus'];

  if ($a0 < $b0) return (-1);
  if ($a0 > $b0) return (1);
  return (0);
}

function sortby($tab, $col) {
  // $tab = name of tab on page
  // $col = code for tab column

  $sort = $_SESSION['sort'];
    if (! $sort) $sort = "host";
  $a0 = ""; $a1 = "";
  $class = "sort";
  if ($col != $sort) {
    $a0 = "<a href='?sort=$col'>";
    $a1 = "</a>";
    $class = "sel";
  }
  echo "<span class='$class'>$a0$tab$a1</span>\n";
}

?>
<!DOCTYPE html>
<head>
  <meta content="text/html; charset=UTF-8" http-equiv="content-type">
  <title><?=$pagetitle?></title>
  <link rel="stylesheet" href="style.css" type="text/css" media="all">
  <script type="text/javascript">
    function ccg(cc) {
      var obj = document.querySelectorAll("div[name^='ccg[']")
      var dsp = "";
      for (var i=0; i<obj.length; i++) {
        var grp = obj[i].getAttribute("name");
        dsp = ((cc == "all") || ("ccg["+cc+"]" == grp)) ? "table-row-group" : "none";
        obj[i].style.display = dsp;
      }
      httpGet("setcc.php?"+cc);
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

<body onload="document.getElementById('errmsg').style.opacity='0'">
<div id="frame">
 <div id="view">
<? if ($_SESSION['user']) { ?>
  <!-- ADMIN BUTTONS -->
  <a href='new.php' style='position:absolute; left:8px; top:0'><button type='button'>Add new server</button></a>
  <a href='logoff.php' style='position:absolute; right:8px; top:0'><button type='button'>Sign off</button></a>
<? } else { ?>
  <a href='login.php' style='position:absolute; right:8px; top:0'><button type='button'>Log in</button></a>
<? } ?>

  <!-- TIER BUTTONS -->
  <ul id='tier' class='cbx' style='position:absolute; right:100px; top:0'>
    <a href='?tier=1'><li onclick="window.location='?tier=1'">
      <input type='radio' id='tier1' name='tier'<?if ($tier==1) echo " checked"?>>
      <label for='tier1'>Tier1</label></li></a>
    <a href='?tier=2'><li onclick="window.location='?tier=2'">
      <input type='radio' id='tier2' name='tier'<?if ($tier==2) echo " checked"?>>
      <label for='tier2'>Tier2</label></li></a>
  </ul>

  <!-- FILTER BUTTONS -->
<?  /********** SHOW TITLE AND STATUS BUTTONS **********/
echo "  <h3 style='margin:0; padding-right:85px;'>";
if ($show) {
  echo "<a href='?show=all' class='stats'>";
  echo "<div class='stats mine' title='Click to show all servers'>";
  echo "&nbsp;show ALL servers&nbsp;</div></a> ";
} else if ($_SESSION['user']) {
  echo "<a href='?show=mine' class='stats'>";
  echo "<div class='stats' title='Click to show only your servers'>";
  echo "Mine: ".$stats['MINE']."</div></a> ";
}

foreach((array)$STATUS as $id => $val) {
  if ($stats[$id] > 0) {
    if (! $show) echo "<a href='?show=$id' class='stats'>";
    echo "<div class='stats'";
    echo " title='".$STATUS[$id]['title']."'>";
    echo $STATUS[$id]['abbr'].": ".$stats[$id];
    echo "</div>";
    if (! $show) echo "</a>";
  }
}
echo " <div class='stats'>Total: $count</div>&nbsp;&nbsp;";
echo "$pagetitle</h3>\n";


/********** SHOW ERROR MESSAGE **********/
$err = $_SESSION['err'];
if (! $err) $err = "&nbsp;";
unset($_SESSION['err']);
echo "  <div id='errmsg' class='err'>$err</div>\n";


/********** SHOW COUNTRY BUTTONS **********/
if (($tier != 1) && (! $show)) {
  $ht = 33 * (1 + intval((count($CC)+1.5) / 27.5));
?>

  <!-- COUNTRY CODE BUTTONS -->
  <div id='ccg' style='height:<?=$ht?>px;'>
  <ul id='cc' class='cbx'>
    <li style='width:44px' onclick="ccg('all')">
    <input type='radio' id='cc[all]' name='cc'<?if($ccg=="ALL") echo " checked"?>>
    <label for='cc[all]'>&lt;ALL&gt;</label></li>
<?
  foreach((array)$CC as $cc => $val) {
    echo "    <li onclick=\"ccg('$cc')\" title=\"" . $ccTLD[$cc] . "\">";
    echo "<input type='radio' id='cc[$cc]' name='cc'";
    if ($ccg == $cc) echo " checked";
    echo "><label for='cc[$cc]'>$cc</label></li>\n";
  }
?>
  </ul>
  </div>
<? } ?>

  <!-- DISPLAY TABLE OF SERVERS -->
  <div id='srvlist'>
    <p class='th'>
      <button></button>
      <button></button>
      <button></button>
      <? sortby("Hostname <label>(Click for details)</label>", "host"); ?>
      <? sortby("IPv4", "ipv4"); ?>
      <? sortby("IPv6", "ipv6"); ?>
      <? sortby("Owner(s)", "ownr"); ?>
      <? sortby("Status", "stat"); ?>
    </p>
<? /********** SHOW SERVERS, GROUPED BY COUNTRY **********/
foreach ((array)$CC as $grp => $grpcc) {
  $dis = "table-row-group";
  if (($tier == 2) && ($ccg != "ALL") && ($ccg != $grp) && (! $show)) $dis = "none";
  echo "    <div name='ccg[$grp]' style='display:$dis;'>\n";
  foreach ((array)$grpcc as $srv) {
    $dc = $srv['dc'][0];
    $short = preg_replace('#(?:\.dns)\.opennic\.glue#', '', $dc);
    $desc = $srv['description'][0];

    unset($owner, $srv['manager']['count']);
    foreach((array)$srv['manager'] as $mng) {
      preg_match('#uid=([a-z0-9]*)#i', $mng, $match);
      if ($match[1]) $owner[] = $match[1];
    }

    $location = $srv['locrecordtxt'][0];
/*
    $LOC = explode(" ", $srv['locrecord'][0]);
    $tmp = $LOC[0]; if (strtoupper($LOC[4]) == "S") $tmp = -$tmp;
    $lat = DMStoDEC($tmp, $LOC[1], $LOC[2]);
    $tmp = $LOC[4]; if (strtoupper($LOC[7]) == "E") $tmp = -$tmp;
    $lon = DMStoDEC($tmp, $LOC[5], $LOC[6]);
*/
    $logpol = $srv['loggingpolicy'][0];
      $logpol = preg_replace('/"/', '&quot;', $logpol);
    $status = strtoupper($srv['zonestatus'][0]);
      if (! $status) $status = "PASS";

    unset($srv['arecord']['count']);
    unset($ip4, $ip6);
    if ($ADMIN)  foreach((array)$srv['arecord'] as $key => $val)
      $srv['arecord'][$key] = "<a href='http://report.opennicproject.org/t2log/t2.php?ip_addr=$val' target='_blank'>$val</a>";
    if (count($srv['arecord'])) $ip4 = implode("<br>", $srv['arecord']);
    if (! $ip4) $ip4 = "&nbsp;";
    unset($srv['aaaarecord']['count']);
    if ($ADMIN)  foreach((array)$srv['aaaarecord'] as $key => $val) {
      $wbr = preg_replace('/:/', ':<wbr>', $val);
      $srv['aaaarecord'][$key] = "<a href='http://report.opennicproject.org/t2log/t2.php?ip_addr=$val' target='_blank'>$wbr</a>";
    }
    if (count($srv['aaaarecord'])) $ip6 = implode("<br>", $srv['aaaarecord']);
    if (! $ip6) $ip6 = "&nbsp;";

    // Get extra info for admins
    if ($ADMIN) {
      $dn = "o=users,".$LDAP['base'];
      $attr = array("mail");
      unset($mail);
      foreach((array)$owner as $mng) {
        $filter = "uid=$mng";
        $query = @ldap_search($LDAP['conn'], $dn, $filter, $attr);
        $res = @ldap_get_entries($LDAP['conn'], $query);
        $mail[] = ($res['count']) ? $res[0]['mail'][0] : "";
      }
    }

    $class = $STATUS[$status]['css'] . " ";
    foreach((array)$srv['manager'] as $mng)
      if ($_SESSION['user_dn'] == $mng) { $class .= "mine "; break; }
    if ($class = trim($class, " ")) $class = " class='$class'";
    echo "   <p$class><span>";
    echo "<button disabled class=btn" . button($srv['logginganonymous']) . " title=\"$logpol\">Log Anon</button>";
    echo "<button disabled class=btn" . button($srv['usewhitelisting']) . ">Whitelist</button>";
    echo "<button disabled class=btn" . button($srv['usednscrypt']) . ">DNSCrypt</button>";
    echo "</span>";

    $tmp = $desc;
    if ($location) $tmp = trim("($location) $tmp");
    $tmp = preg_replace('/"/', '&quot;', $tmp);
    echo "<span class='host' title=\"$tmp\"><a id='$dc' href='edit.php?srv=$dc'>$dc</a></span>";
    echo "<span class='mono ipv4'>$ip4</span>";
    echo "<span class='mono ipv6'>$ip6</span>";

    //echo "<span>$lat</span>";
    //echo "<span>$lon</span>";

    echo "<span class='ownr'>";
    foreach((array)$owner as $key => $val) {
      if (($ADMIN) && ($m = $mail[$key])) {
        echo "<a class='mail' href='mailto:$m'>$val</a><br>";
      } else echo "$val<br>";
    }
    echo "</span>";

    echo "<span class='stat'";
    if ($title = $STATUS[$status]['title']) echo " title='$title'";
    echo ">" . $STATUS[$status]['stat'] . "</span>";
    echo "</p>\n";
  }
  echo "    </div>\n";
}
?>
  </div>
  <br>
  <div><i>Click server hostname to view full details</i></div>
 </div>
</div>

<!-- ADDITION INFORMATION -->
<div class='instr'>
Some features on this page require javascript.
Servers which are offline or fail testing for more than 48 hours
will be automatically moved to a &quot;pending deletion&quot; status.
If you resolve the issue and wish to have your server re-listed, please
ask a Tier-1 operator to update the status.
</div>

</body>
</html>
