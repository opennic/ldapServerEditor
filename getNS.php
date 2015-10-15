<?
include("config.php");

$tier = $_GET['t'];
$st = $_GET['st'];
$co = $_GET['co'];

$NS = getNS($st, $co, $tier);
echo $NS;

