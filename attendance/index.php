<?php require '../script/auth.php';  ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Face Attendance</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<style>
  body { font-family: system-ui, Arial, sans-serif; max-width: 640px; margin: 64px auto; text-align:center; }
  button { padding: 14px 18px; border:0; background:#111; color:#fff; border-radius:10px; cursor:pointer; font-size:16px; }
  .msg { margin-top:16px; color:#555; }
</style>
</head>
<body>
  <h2>Skating Timer — Face Attendance</h2>
  <p class="msg">Click to start detection and mark attendance.</p>
  <a href="detect.php"><button>Click to Detect</button></a>
  <a href="report.php"><button>Attendance Report</button></a> <br>
  <a href="../index.php"><button>Back</button></a>
</body>
</html>
