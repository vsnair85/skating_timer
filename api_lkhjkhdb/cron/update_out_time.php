<?php
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = ''; // change if needed
$DB_NAME = 'skating_timer';

$mysqli = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
  http_response_code(500);
  die('Database connection failed.');
}

// Set out time to in time + 2 hours, only when out is NULL and in is NOT NULL
$sql = "
  UPDATE tbl_attendance
  SET ta_chkd_out_at = DATE_ADD(ta_chkd_in_at, INTERVAL 2 HOUR)
  WHERE ta_chkd_out_at IS NULL
    AND ta_chkd_in_at IS NOT NULL
";

if (!$mysqli->query($sql)) {
    die("Update failed: " . $mysqli->error);
}

echo "Rows updated: " . $mysqli->affected_rows;
$mysqli->close();
