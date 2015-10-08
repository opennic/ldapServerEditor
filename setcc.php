<?
session_start();
$cookie_expire = 604800;

if (! $cc = strtoupper(key($_GET))) $cc = "all";

if ((!$cc) || ($cc=="ALL")) unset($_SESSION['ccg'], $_COOKIE['ccg']);
else {
  $_SESSION['ccg'] = $cc;
  setcookie("ccg", $cc, time() + $cookie_expire);
}
