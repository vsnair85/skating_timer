<?php
require '../script/db.php';
require '../script/auth.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) { header('Location: index.php'); exit; }

$stmt = $mysqli->prepare("SELECT tr_id, tr_name, tr_number, tr_image_path FROM tbl_racers WHERE tr_id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$racer = $res->fetch_assoc();
$stmt->close();

if (!$racer) { header('Location: index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Confirm Attendance</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<style>
  body { font-family: system-ui, Arial, sans-serif; max-width: 640px; margin: 32px auto; }
  .card { border:1px solid #ddd; border-radius:10px; padding:16px; display:flex; gap:16px; align-items:center; }
  img { width:120px; height:120px; object-fit:cover; border-radius:8px; background:#eee; }
  .actions { margin-top:16px; }
  button { padding:10px 14px; border:0; background:#111; color:#fff; border-radius:8px; cursor:pointer; }
  a { margin-left:8px; }
</style>
</head>
<body>
  <h3>Confirm Attendance</h3>
  <div class="card">
    <img src="<?php echo htmlspecialchars($racer['tr_image_path'] ?: ''); ?>" alt="Racer Photo"
         onerror="this.style.display='none'"/>
    <div>
      <div><b>Name:</b> <?php echo htmlspecialchars($racer['tr_name']); ?></div>
      <div><b>Number:</b> <?php echo htmlspecialchars($racer['tr_number']); ?></div>
    </div>
  </div>

  <form class="actions" method="post" action="mark_attendance.php">
    <input type="hidden" name="racer_id" value="<?php echo (int)$racer['tr_id']; ?>"/>
    <button type="submit">Mark attendance</button>
    <a href="index.php">Cancel</a>
  </form>
</body>
</html>
