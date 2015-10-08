<?php
session_start();
include("config.php");
$title = "OpenNIC Server Registration";

?>
<!DOCTYPE html>
<head>
  <meta content="text/html; charset=UTF-8" http-equiv="content-type">
  <title><?=$title?></title>
  <link rel="stylesheet" href="style.css" type="text/css" media="all">
  <link rel='icon' type='image/png' href='network.png'>
</head>

<body>
<form id="frm" action="_login.php" method="post">

<div id="frame">
 <div id="login">
<? if ($err = $_SESSION['err']) { ?>
  <div class="err"><?=$err?></div>
<?
     unset($_SESSION['err']);
   }
?>
  <h3>Please enter your login credentials</h3>
  <label>Username</label>
  <input type="text" name="user" autofocus />
  <br>
  <label>Password</label>
  <input type="password" name="pass" />
  <br>
  <br>
  <a href='view.php'><button type='button'>Cancel</button></a>
  <input type="submit" value="Login">

  <div id='newacct'>
  <a target="_blank" href="https://www.opennicproject.org/members/">Click here if you need a new account</a>
  </div>
 </div>
</div>

</form>
</body>
</html>
