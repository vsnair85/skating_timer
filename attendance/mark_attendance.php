<?php
require '../script/db.php';

// Expect POST racer_id
$racerId = isset($_POST['racer_id']) ? intval($_POST['racer_id']) : 0;
if ($racerId <= 0) {
  http_response_code(400);
  echo "Invalid racer id";
  exit;
}

$today = date('Y-m-d');

// 1) Look for today's row
$stmt = $mysqli->prepare("
  SELECT ta_id
  FROM tbl_attendance
  WHERE ta_racer_id = ? AND DATE(ta_chkd_in_at) = ?
  LIMIT 1
");
$stmt->bind_param('is', $racerId, $today);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
  // No row today → create new check-in
  $stmt = $mysqli->prepare("
    INSERT INTO tbl_attendance (ta_racer_id, ta_chkd_in_at, ta_created_at, ta_updated_at)
    VALUES (?, NOW(), NOW(), NOW())
  ");
  $stmt->bind_param('i', $racerId);
  $stmt->execute();
  $stmt->close();
} else {
  // Row exists today → always update checkout time
  $stmt = $mysqli->prepare("
    UPDATE tbl_attendance
    SET ta_chkd_out_at = NOW(), ta_updated_at = NOW()
    WHERE ta_id = ?
  ");
  $stmt->bind_param('i', $row['ta_id']);
  $stmt->execute();
  $stmt->close();
}

// Redirect back to index
header('Location: index.php?done=1');
exit;
