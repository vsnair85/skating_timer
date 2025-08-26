<?php
require 'db.php';

$name   = trim($_POST['name']   ?? '');
$number = trim($_POST['number'] ?? ''); // may be empty; we’ll fill it

if ($name === '') {
  header('Location: /add_racer.php'); exit;
}

// If number is empty or non-numeric, assign next available automatically
if ($number === '' || !preg_match('/^\d+$/', $number)) {
  $nextNo = 1;
  if ($rs = $mysqli->query("SELECT COALESCE(MAX(CAST(tr_number AS UNSIGNED)), 0) + 1 AS next_no FROM tbl_racers")) {
    if ($row = $rs->fetch_assoc()) $nextNo = (int)$row['next_no'];
    $rs->free();
  }
  $number = (string)$nextNo;
}

$stmt = $mysqli->prepare("INSERT INTO tbl_racers (tr_name, tr_number) VALUES (?, ?)");
$stmt->bind_param('ss', $name, $number);
$stmt->execute();
$stmt->close();

// Back to selection page with success flag
header('Location: /index.php?added=1');
