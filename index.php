<?php require 'script/db.php'; ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Select Racer</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{ --sky:#52b6ff; --sky-2:#a8dbff; }
    body{
      background:linear-gradient(180deg,var(--sky) 0%,#fff 50%);
      min-height:100svh; font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
    }
    .card-mobile{ max-width:520px; margin:clamp(8px,4vw,24px) auto; border:none; border-radius:18px; box-shadow:0 10px 30px rgba(0,0,0,.08);}
    .brand{ font-weight:700; color:#fff; text-align:center; padding:22px 16px 8px;}
    .btn-sky{ background:var(--sky); color:#fff; border:none; border-radius:12px;}
    .btn-sky:active{ transform:scale(.98); }
    .form-select{ border-radius:12px; }
    @media (min-width:576px){ body{background:radial-gradient(60% 50% at 50% 0%,var(--sky) 0%,#fff 70%);} }
  </style>
</head>
<body>
  <div class="brand h3">Speed Skating Stopwatch</div>
  <div class="card card-mobile">
    <div class="card-body p-3 p-sm-4">
      <h5 class="mb-3 text-center">Select Racer</h5>
      <?php
        $racers = [];
        if ($rs = $mysqli->query("SELECT tr_id,tr_name,tr_number FROM tbl_racers ORDER BY tr_name")) {
          while ($row = $rs->fetch_assoc()) $racers[] = $row;
          $rs->free();
        }
      ?>
      <form action="stopwatch.php" method="get" class="needs-validation" novalidate>
        <div class="mb-3">
          <label class="form-label">Racer</label>
          <select class="form-select" name="racer_id" required>
            <option value="">Choose...</option>
            <?php foreach($racers as $r): ?>
              <option value="<?= (int)$r['tr_id'] ?>">
                <?= htmlspecialchars($r['tr_name']) ?><?= $r['tr_number'] ? ' — #'.htmlspecialchars($r['tr_number']) : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">Please select a racer.</div>
        </div>
        <div class="d-grid gap-2 mt-4">
          <button class="btn btn-sky btn-lg" type="submit">Next</button>
        </div>
      </form>

      <hr class="my-4">

      <div class="d-grid">
        <a class="btn btn-outline-primary btn-lg" href="add_racer.php">+ Add New Racer</a>
      </div>

      <?php if (isset($_GET['added']) && $_GET['added']==='1'): ?>
        <div class="alert alert-success mt-3 py-2">Racer added.</div>
      <?php endif; ?>
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
