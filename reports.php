<?php
require 'script/db.php';

// Get racers for dropdown
$racers = [];
if ($rs = $mysqli->query("SELECT tr_id, tr_name, tr_number FROM tbl_racers ORDER BY tr_name")) {
  while ($row = $rs->fetch_assoc()) $racers[] = $row;
  $rs->free();
}

// Selected racer
$racer_id = isset($_GET['racer_id']) ? (int)$_GET['racer_id'] : 0;
$selectedRacer = null;
if ($racer_id > 0) {
  $st = $mysqli->prepare("SELECT tr_id, tr_name, tr_number FROM tbl_racers WHERE tr_id=? LIMIT 1");
  $st->bind_param('i', $racer_id);
  $st->execute();
  $selectedRacer = $st->get_result()->fetch_assoc();
  $st->close();
}

// Fetch races for racer
$races = [];
if ($selectedRacer) {
  $st = $mysqli->prepare("
    SELECT trs_id, trs_started_at, trs_finished_at, trs_total_ms
    FROM tbl_races
    WHERE trs_racer_id=?
    ORDER BY COALESCE(trs_started_at, '1970-01-01 00:00:00') DESC, trs_id DESC
  ");
  $st->bind_param('i', $racer_id);
  $st->execute();
  $res = $st->get_result();
  while ($row = $res->fetch_assoc()) $races[] = $row;
  $st->close();
}

// Helper to format ms -> "seconds.milliseconds"
function fmt_secs_ms($ms) {
  $sec = floor($ms / 1000);
  $msec = (int)($ms % 1000);
  return $sec . '.' . str_pad((string)$msec, 3, '0', STR_PAD_LEFT);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Racer Reports</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{ --sky:#52b6ff; --sky-2:#e8f5ff; }
    body{ background:linear-gradient(180deg,var(--sky) 0%,#fff 50%); min-height:100svh; font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif; }
    .topbar{ background:var(--sky); color:#fff; padding:14px 16px; display:flex; align-items:center; justify-content:space-between; }
    .card-mobile{ max-width:720px; margin: clamp(8px,4vw,24px) auto; border:none; border-radius:18px; box-shadow:0 10px 30px rgba(0,0,0,.08); }
    .form-select{ border-radius:12px; }
    .badge-sky{ background: var(--sky); }
    .accordion-button:focus{ box-shadow:none; }
    .table> :not(caption)>*>*{ padding:.55rem .6rem; }
    .muted{ color:#6b7280; }
    .pill{ background:#eef6ff; color:#0d1b2a; border-radius:999px; padding:2px 10px; font-weight:600; }
    .btn-sky{ background:var(--sky); color:#fff; border:none; border-radius:12px; }
  </style>
</head>
<body>
  <div class="topbar">
    <div><a href="index.php" class="link-light text-decoration-none">&larr; Back</a></div>
    <div class="fw-bold">Racer Reports</div>
    <div style="width:42px"></div>
  </div>

  <div class="card card-mobile">
    <div class="card-body p-3 p-sm-4">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-12">
          <label class="form-label">Select Racer</label>
          <select name="racer_id" class="form-select" required>
            <option value="">Choose...</option>
            <?php foreach($racers as $r): ?>
              <option value="<?= (int)$r['tr_id'] ?>" <?= $r['tr_id']===$racer_id ? 'selected':'' ?>>
                <?= htmlspecialchars($r['tr_name']) ?><?= $r['tr_number'] ? ' — #'.htmlspecialchars($r['tr_number']) : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 d-grid mt-2">
          <button class="btn btn-sky btn-lg" type="submit">View Report</button>
        </div>
      </form>

      <?php if ($selectedRacer): ?>
        <hr class="my-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div class="h5 mb-0">
            <?= htmlspecialchars($selectedRacer['tr_name']) ?>
            <?php if ($selectedRacer['tr_number']): ?>
              <span class="pill">#<?= htmlspecialchars($selectedRacer['tr_number']) ?></span>
            <?php endif; ?>
          </div>
          <div class="muted"><?= count($races) ?> race(s) found</div>
        </div>

        <?php if (!count($races)): ?>
          <div class="alert alert-info mt-3 mb-0">No races recorded for this racer yet.</div>
        <?php else: ?>
          <div class="accordion mt-3" id="racesAcc">
            <?php foreach($races as $i => $race): ?>
              <?php
                $raceId = (int)$race['trs_id'];
                // Fetch laps for this race
                $laps = [];
                $stL = $mysqli->prepare("
                  SELECT tl_lap_no, tl_lap_ms, tl_cumulative_ms
                  FROM tbl_laps
                  WHERE tl_race_id=?
                  ORDER BY tl_lap_no ASC
                ");
                $stL->bind_param('i', $raceId);
                $stL->execute();
                $resL = $stL->get_result();
                while ($rowL = $resL->fetch_assoc()) $laps[] = $rowL;
                $stL->close();

                // Dates
                $dt = $race['trs_started_at'] ? new DateTime($race['trs_started_at']) : null;
                $dateText = $dt ? $dt->format('d M Y, h:i A') : '—';
                $totalMs = (int)$race['trs_total_ms'];
              ?>
              <div class="accordion-item">
                <h2 class="accordion-header" id="heading<?= $i ?>">
                  <button class="accordion-button <?= $i? 'collapsed':'' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $i ?>" aria-expanded="<?= $i? 'false':'true' ?>" aria-controls="collapse<?= $i ?>">
                    <div class="w-100 d-flex justify-content-between align-items-center">
                      <div>
                        <div><strong>Race #<?= $raceId ?></strong></div>
                        <div class="muted"><?= htmlspecialchars($dateText) ?></div>
                      </div>
                      <span class="badge bg-secondary">Total: <?= fmt_secs_ms($totalMs) ?> s</span>
                    </div>
                  </button>
                </h2>
                <div id="collapse<?= $i ?>" class="accordion-collapse collapse <?= $i? '':'show' ?>" aria-labelledby="heading<?= $i ?>" data-bs-parent="#racesAcc">
                  <div class="accordion-body">
                    <?php if (!count($laps)): ?>
                      <div class="alert alert-light border">No laps saved for this race.</div>
                    <?php else: ?>
                      <div class="table-responsive">
                        <table class="table align-middle">
                          <thead class="table-light">
                            <tr>
                              <th style="width:80px">Lap</th>
                              <th>Lap Time<br><small class="muted">(seconds.milliseconds)</small></th>
                              <th>Cumulative<br><small class="muted">(seconds.milliseconds)</small></th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach($laps as $L): ?>
                              <tr>
                                <td><strong><?= (int)$L['tl_lap_no'] ?></strong></td>
                                <td><?= fmt_secs_ms((int)$L['tl_lap_ms']) ?> s</td>
                                <td><?= fmt_secs_ms((int)$L['tl_cumulative_ms']) ?> s</td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
