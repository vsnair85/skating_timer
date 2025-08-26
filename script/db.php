<?php
// script/db.php
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = ''; // change if needed
$DB_NAME = 'skating_timer';

$mysqli = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
  http_response_code(500);
  die('Database connection failed.');
}
$mysqli->set_charset('utf8mb4');
