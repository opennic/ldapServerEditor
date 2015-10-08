<?php
session_start();
if ((! $_SESSION['user']) || (! $_SESSION['pass'])) header("Location: /");

include("config.php");
/*
// Check if user is T1 op
$dn = "o=admins,".$LDAP['base'];
$filter = "tier1operator=".$_SESSION['user_dn'];
$attr = array("tier1operator");
$ldapbind = ldap_bind($LDAP['conn'], $LDAP['admin_dn'], $LDAP['admin_pass']);
$query = @ldap_search($LDAP['conn'], $dn, $filter, $attr);
$res = @ldap_get_entries($LDAP['conn'], $query);
if (! $res['count']) header("Location: /");
*/

if ($city = $_POST['city']) {
  $citysearch = $city;
  $city = str_replace(",", "+", $city);
  $city = str_replace(" ", "+", $city);
  $city = str_replace("++", "+", $city);

  $geocode_stats = file_get_contents("http://maps.googleapis.com/maps/api/geocode/json?address=$city&sensor=false");
  $output = json_decode($geocode_stats);

//echo "<pre>"; var_dump($geocode_stats); die;
//echo "<pre>"; var_dump($output); die;
//echo "<pre>"; var_dump($output->results[0]->address_components); die;

  foreach((array)$output->results as $val) {
    unset($tmp);
    $addr = $val->formatted_address;
    $tmp['address'] = $addr;

    foreach((array)$val->address_components as $arr) {
      switch($arr->types[0]) {
        case "locality":
          $LOC = $arr->long_name;
          $tmp['city'] = $LOC;
          break;
        case "administrative_area_level_1":
          $st = strtoupper($arr->short_name);
          $ST = $arr->long_name;
          $tmp['region']['long'] = $ST;
          $tmp['region']['short'] = $st;
          break;
        case "country":
          $cn = strtolower($arr->short_name);
          $CN = strtoupper($arr->short_name);
          $tmp['country']['long'] = $arr->long_name;
          $tmp['country']['short'] = $CN;
          break;
      }
    }
    if ($st == $loc) $st = "";
    $tmp['server_name'] = trim(strtolower($st).".$cn", ".");
    //$tmp['location'] = trim("$LOC, $ST", ", ") . " ($CN)";

    $tmp['lat'] = $val->geometry->location->lat;
    $tmp['lng'] = $val->geometry->location->lng;

    $search[$addr] = $tmp;
  }
  ksort($search);
  $_SESSION['city_search'] = $search;
  //echo "<pre>"; print_r($search); die;
}
?>
<html>
<head>
  <meta content="text/html; charset=UTF-8" http-equiv="content-type">
  <title>OpenNIC Server Registration</title>
  <link rel="stylesheet" href="style.css" type="text/css" media="all">
  <link rel='icon' type='image/png' href='network.png'>
  <style>
    body { font-family: Arial,Helvetica,sans-serif; }
  </style>
</head>

<body>
<div id="frame">
  <div id="new">
<?
/********** ASK USER TO ENTER A LOCATION **********/
if (! $city) {
?>
    <form action="new.php" method="post">
    Please enter the location your new server is in:<br>
    <br>
    <input type='text' name='city' value='' size=30 autofocus>
    <input type='submit' value='Locate'><br>
    <hr>
    <b>Examples:</b><br>
    <br>
    &nbsp;&nbsp;SomeTown<br>
    &nbsp;&nbsp;MyCity, NY<br>
    &nbsp;&nbsp;Anywhere, USA
    </form>

<?
/********** LIST LOCATION CHOICES **********/
} else {
?>
    <form action="new.php" method="post">
    Select from below, or enter a new location:<br>
    <br>
    <input type='text' name='city' value='' size=30 autofocus>
    <input type='submit' value='Locate'><br>
    </form>
    <hr>
    <b>Results from &quot;<?=$citysearch?>&quot;</b><br>
<?
foreach((array)$search as $addr => $val) {
  echo "    ";
  echo "<a href='edit.php?addr=" . urlencode($addr) . "'>";
  echo "<button>$addr</button>";
  echo "</a>";
  echo "<br>\n";
}
?>
<? } ?>

    <a href='<?=$_SESSION['lastpage']?>' style='text-decoration:none'>
      <button style='float:right'>Cancel</button>
    </a>
  </div>
</div>
</body>
</html>
