<?
session_start();

if (! $cc = strtoupper(key($_GET))) $cc = "all";
$_SESSION['ccg'] = $cc;
