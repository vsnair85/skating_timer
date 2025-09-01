<?php 
require 'script/db.php';
require 'script/auth.php'; 
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Add Racer</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{ --sky:#52b6ff; }
    body{ background:linear-gradient(180deg,var(--sky) 0%,#fff 50%); min-height:100svh; font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;}
    .card-mobile{ max-width:520px; margin:clamp(8px,4vw,24px) auto; border:none; border-radius:18px; box-shadow:0 10px 30px rgba(0,0,0,.08);}
    .topbar{ background:var(--sky); color:#fff; padding:14px 16px; display:flex; align-items:center; justify-content:space-between; }
    .btn-sky{ background:var(--sky); color:#fff; border:none; border-radius:12px; }
    .btn-sky:active{ transform:scale(.98); }
    .form-control{ border-radius:12px; }
  </style>
</head>
<body>
  <div class="topbar">
    <div><a href="index.php" class="link-light text-decoration-none">&larr; Back</a></div>
    <div class="fw-bold">Add New Racer</div>
    <div style="width:42px"></div>
  </div>

  <div class="card card-mobile">
    <div class="card-body p-3 p-sm-4">
      <?php
        // Find next racer number (tolerates varchar by casting to UNSIGNED)
        $nextNo = 1;
        if ($rs = $mysqli->query("SELECT COALESCE(MAX(CAST(tr_number AS UNSIGNED)), 0) + 1 AS next_no FROM tbl_racers")) {
          if ($row = $rs->fetch_assoc()) $nextNo = (int)$row['next_no'];
          $rs->free();
        }
      ?>
      <form action="script/save_racer.php" method="post" class="needs-validation" novalidate>
        <div class="mb-3">
          <label class="form-label">Racer Name <span class="text-danger">*</span></label>
          <input type="text" class="form-control" name="name" placeholder="Racer name" required>
          <div class="invalid-feedback">Name is required.</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Racer Number</label>
          <input type="text" class="form-control" name="number" value="<?= htmlspecialchars((string)$nextNo) ?>">
          <div class="form-text">Auto-filled with the next available number. You can change it if needed.</div>
        </div>
        <div class="d-grid gap-2 mt-3">
          <button class="btn btn-sky btn-lg" type="submit">Save Racer</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    (() => {
      const forms = document.querySelectorAll('.needs-validation');
      Array.from(forms).forEach(form => {
        form.addEventListener('submit', e => {
          if (!form.checkValidity()) { e.preventDefault(); e.stopPropagation(); }
          form.classList.add('was-validated');
        }, false);
      });
    })();
  </script>
</body>
</html>
