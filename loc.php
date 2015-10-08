<?php
if ($city = $_POST['city']) {
  include("config.php");

  $city = str_replace(",", "+", $city);
  $city = str_replace(" ", "+", $city);
  $city = str_replace("++", "+", $city);

  $geocode_stats = file_get_contents("http://maps.googleapis.com/maps/api/geocode/json?address=$city&sensor=false");
//echo "<pre>"; var_dump($geocode_stats); die;
  $output = json_decode($geocode_stats);
  $latLng = $output->results[0]->geometry->location;
  $addr = $output->results[0]->formatted_address;

//echo "<pre>"; var_dump($output->results[0]->address_components); die;

  foreach((array)$output->results[0]->address_components as $arr) {
    switch($arr->types[0]) {
      case "locality":
        $loc = $arr->long_name;
        $LOC = strtolower($arr->short_name);
        break;
      case "administrative_area_level_1":
        $st = $arr->long_name;
        $ST = strtolower($arr->short_name);
        break;
      case "country":
        $cn = strtolower($arr->short_name);
        $CN = strtoupper($arr->short_name);
        break;
    }
  }
  if ($ST == $LOC) $ST = "";
  //$server_name = trim("$ST.$cn.dns.opennic.glue", ".");
  $server_name = trim("$ST.$cn", ".");
  $location = trim("$st, $CN", ", ");
//echo "$server_name<br>"; die;

  $dn = "o=servers,".$LDAP['base'];
  $filter = "dc=*.$server_name.dns.opennic.glue";
  $attr = array("dc");
  $ldapbind = ldap_bind($LDAP['conn'], $LDAP['admin_dn'], $LDAP['admin_pass']);
  $query = @ldap_search($LDAP['conn'], $dn, $filter, $attr);
  $res = @ldap_get_entries($LDAP['conn'], $query);

//echo "<pre>";
  $lastNS = 0;
  foreach((array)$res as $arr) {
    if ($dc = $arr['dc'][0]) {
      preg_match('/^ns([0-9]{1,3})/i', $dc, $match);
      //print_r($match);
      if ($match[1] > $lastNS) $lastNS = $match[1];
    }
  }
  $lastNS++;
  $server_name = "ns$lastNS.$server_name";

//print_r($res); die;


  $lat = $latLng->lat;
    $LAT = DECtoDMS($lat);
    $LAT['dir'] = ($LAT['deg'] < 0) ? "S" : "N";
    $LAT['deg'] = abs($LAT['deg']);
  $lng = $latLng->lng;
    $LNG = DECtoDMS($lng);
    $LNG['dir'] = ($LNG['deg'] < 0) ? "W" : "E";
    $LNG['deg'] = abs($LNG['deg']);

  $elev_stats = file_get_contents("http://maps.googleapis.com/maps/api/elevation/json?locations=$lat,$lng");
  $output = json_decode($elev_stats);
  $elev = $output->results[0]->elevation;
  $res = round($output->results[0]->resolution, 0);

?>
<html>
<head>
<script>
window.opener.document.frm.loc.value="<?
  echo "{$LAT['deg']} {$LAT['min']} {$LAT['sec']} {$LAT['dir']} ";
  echo "{$LNG['deg']} {$LNG['min']} {$LNG['sec']} {$LNG['dir']} ";
  echo number_format($elev, 2, '.', '') . "m {$res}m 100m 10m";
?>";
window.opener.document.frm.st.value="<?=$ST?>";
window.opener.document.frm.co.value="<?=$cn?>";
window.opener.document.frm.location.value="<?=$addr?>";
window.close();
</script>
</head>
</html>
<?
}

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
<html>
<head>
<style>
body { font-family: Arial,Helvetica,sans-serif; }
</style>
</head>
<body>
<form action="loc.php" method="post">
Enter your city:
<input type='text' name='city' value='' autofocus><br>
<br>
<center><input type='submit' value='Locate'></center>
<hr>
<b>Examples:</b><br>
<br>
&nbsp;&nbsp;Anywhere<br>
&nbsp;&nbsp;Anywhere, NY<br>
&nbsp;&nbsp;Anywhere, USA
</form>
</body>
</html>
