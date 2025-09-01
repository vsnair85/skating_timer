<?php
require 'db.php';
session_start();
$sessionId = session_id();

// Check if the user is logged in
if (!isset($_SESSION['userid'])) {
    header("Location: ../login.php");
    exit();
}
?>