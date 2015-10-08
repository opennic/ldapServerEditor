<?php
session_start();
//include("config.php");

unset($_SESSION['user']);
unset($_SESSION['pass']);
unset($_SESSION['user_dn']);
unset($_SESSION['show_mine']);

$_SESSION['err'] = "You have been logged off";
header("Location: view.php");
