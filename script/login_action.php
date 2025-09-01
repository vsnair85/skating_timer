<?php
// $username = $_REQUEST['user'];
// echo $username;
session_start();
include 'db.php';
$mobile = isset($_REQUEST['mobile'])?$_REQUEST['mobile']:"";
$password = isset($_REQUEST['password'])?$_REQUEST['password']:"";
 if (empty($mobile)) {
  	echo "Please enter Mobile number";
  }
  $login = mysqli_query($mysqli,"SELECT  * FROM tbl_users WHERE tu_mobile='$mobile'");
  while($login_data = mysqli_fetch_array($login))
  {
      $f_password=$login_data['tu_pass'];
      $f_userid=$login_data['tu_id'];
  }
     
  	if ((password_verify($password, $f_password))) {
  	  $_SESSION['mobile'] = $mobile;
      $_SESSION['userid'] = $f_userid;
  	  header('location: ../index.php');
  	}
      else {
      echo "Login Unsuccessful : Wrong Username or Password.";
      header("Refresh:2; url=../login.php");
    }
?>