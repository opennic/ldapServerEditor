<?php
session_start();
include("config.php");

$user = $_POST['user'];
$pass = $_POST['pass'];

if (($user) && ($pass)) {
  $dn = "uid=$user,o=users,".$LDAP['base'];
  $ldapbind = @ldap_bind($LDAP['conn'], $dn, $pass);

  if (! $ldapbind) {
    $_SESSION['err'] = "Login Failed";
    $_SESSION['user'] = $user;
      logger("Login failed from ".get_client_ip());
      unset($_SESSION['user']);
    header("Location: login.php");
  } else {
    $_SESSION['user'] = $user;
    $_SESSION['pass'] = $pass;
    $_SESSION['user_dn'] = strtolower($dn);
    unset($_SESSION['show_mine']);
    logger("Logged on from ".get_client_ip());
    header("Location: view.php");
  }
} else header("Location: login.php");
