<?php
require '../script/db.php';
require '../script/auth.php';
date_default_timezone_set('Asia/Kolkata');

// Small helpers
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function fmtSeconds($secs)
{
    $secs = max(0, (int)$secs);
    $h = floor($secs / 3600);
    $m = floor(($secs % 3600) / 60);
    $s = $secs % 60;
    return sprintf('%02d:%02d:%02d', $h, $m, $s);
}

// Fetch racers for the dropdown
$racers = [];
$res = $mysqli->query("SELECT tr_id, tr_name, tr_number FROM tbl_racers ORDER BY tr_name");
while ($row = $res->fetch_assoc()) $racers[] = $row;
$res->free();

// Read selected racer
$racerId = isset($_GET['racer_id']) ? (int)$_GET['racer_id'] : 0;
$summary = null;
$perYear = [];
$datesByYearMonth = [];

if ($racerId > 0) {
    // 1) First attendance date
    $stmt = $mysqli->prepare("SELECT MIN(ta_chkd_in_at) AS first_in FROM tbl_attendance WHERE ta_racer_id = ?");
    $stmt->bind_param('i', $racerId);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $firstIn = $r && $r['first_in'] ? $r['first_in'] : null;

    // 2) Total practice time (seconds)
    $stmt = $mysqli->prepare("
  SELECT SUM(
    CASE
      WHEN ta_chkd_out_at IS NOT NULL THEN
        TIMESTAMPDIFF(SECOND, ta_chkd_in_at, ta_chkd_out_at)
      WHEN DATE(ta_chkd_in_at) = CURDATE() THEN
        TIMESTAMPDIFF(SECOND, ta_chkd_in_at, NOW())
      ELSE
        7200 -- assume 2 hours (in seconds)
    END
  ) AS total_secs
  FROM tbl_attendance
  WHERE ta_racer_id = ?
");
    $stmt->bind_param('i', $racerId);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $totalSecs = (int)($r['total_secs'] ?? 0);


    // 3) Total days attended per year (distinct dates)
    $stmt = $mysqli->prepare("
    SELECT YEAR(ta_chkd_in_at) AS y,
           COUNT(DISTINCT DATE(ta_chkd_in_at)) AS days_attended
    FROM tbl_attendance
    WHERE ta_racer_id = ?
    GROUP BY YEAR(ta_chkd_in_at)
    ORDER BY y ASC
  ");
    $stmt->bind_param('i', $racerId);
    $stmt->execute();
    $rs = $stmt->get_result();
    while ($row = $rs->fetch_assoc()) {
        $perYear[] = $row;
    }
    $stmt->close();

    // 4) Dates attended grouped by Year and Month (distinct dates)
    $stmt = $mysqli->prepare("
    SELECT DISTINCT
      DATE(ta_chkd_in_at) AS d,
      YEAR(ta_chkd_in_at) AS y,
      MONTH(ta_chkd_in_at) AS m
    FROM tbl_attendance
    WHERE ta_racer_id = ?
    ORDER BY d ASC
  ");
    $stmt->bind_param('i', $racerId);
    $stmt->execute();
    $rs = $stmt->get_result();

    // Group into [year][month] => [dates...]
    while ($row = $rs->fetch_assoc()) {
        $y = (int)$row['y'];
        $m = (int)$row['m'];
        $d = $row['d'];
        if (!isset($datesByYearMonth[$y])) $datesByYearMonth[$y] = [];
        if (!isset($datesByYearMonth[$y][$m])) $datesByYearMonth[$y][$m] = [];
        $datesByYearMonth[$y][$m][] = $d;
    }
    $stmt->close();

    // Also fetch the racer card
    $stmt = $mysqli->prepare("SELECT tr_name, tr_number, tr_image_path FROM tbl_racers WHERE tr_id = ?");
    $stmt->bind_param('i', $racerId);
    $stmt->execute();
    $racerRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $summary = [
        'first_in'   => $firstIn,
        'total_secs' => $totalSecs,
        'name'       => $racerRow['tr_name'] ?? '',
        'number'     => $racerRow['tr_number'] ?? '',
        'image'      => $racerRow['tr_image_path'] ?? '',
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Attendance Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
        body {
            font-family: system-ui, Arial, sans-serif;
            max-width: 1000px;
            margin: 24px auto;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }

        a.btn,
        button,
        select {
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid #ccc;
            background: #fff;
            cursor: pointer;
        }

        a.btn {
            text-decoration: none;
            display: inline-block;
        }

        .card {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 14px;
            margin-top: 16px;
        }

        .row {
            display: flex;
            gap: 16px;
            align-items: center;
        }

        img.avatar {
            width: 96px;
            height: 96px;
            object-fit: cover;
            border-radius: 8px;
            background: #eee;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #eee;
            padding: 8px 10px;
            text-align: left;
        }

        th {
            background: #fafafa;
        }

        .muted {
            color: #777;
        }

        .pill {
            background: #111;
            color: #fff;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 12px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 12px;
        }

        .month {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 10px;
        }

        .month h4 {
            margin: 0 0 8px 0;
            font-size: 16px;
        }
    </style>
</head>

<body>
    <header>
        <h2>Attendance Report</h2>
        <div>
            <a class="btn" href="index.php">← Back</a>
            <a class="btn" href="report.php">Reset</a>
        </div>
    </header>

    <form method="get" style="margin-top:16px">
        <label for="racer_id">Select Racer:</label>
        <select name="racer_id" id="racer_id">
            <option value="0">-- Choose --</option>
            <?php foreach ($racers as $r): ?>
                <option value="<?= (int)$r['tr_id'] ?>" <?= $racerId === (int)$r['tr_id'] ? 'selected' : '' ?>>
                    <?= h($r['tr_name']) ?> (<?= h($r['tr_number']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Show Report</button>
    </form>

    <?php if ($racerId > 0 && $summary): ?>
        <div class="card">
            <div class="row">
                <?php if (!empty($summary['image'])): ?>
                    <img class="avatar" src="<?= h($summary['image']) ?>" alt="Photo"
                        onerror="this.style.display='none'" />
                <?php endif; ?>
                <div>
                    <div><b>Name:</b> <?= h($summary['name']) ?></div>
                    <div><b>Number:</b> <span class="pill"><?= h($summary['number']) ?></span></div>
                    <div class="muted">
                        <b>First attendance:</b>
                        <?= $summary['first_in'] ? h(date('d M Y, h:i A', strtotime($summary['first_in']))) : '—' ?>
                    </div>
                    <div><b>Total practice time (all-time):</b> <?= fmtSeconds($summary['total_secs']) ?></div>
                </div>
            </div>
        </div>

        <div class="card">
            <h3>Days Attended (by Year)</h3>
            <?php if (!$perYear): ?>
                <div class="muted">No attendance yet.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Year</th>
                            <th>Total Days Attended</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($perYear as $row): ?>
                            <tr>
                                <td><?= (int)$row['y'] ?></td>
                                <td><?= (int)$row['days_attended'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Dates Attended (Year → Month)</h3>
            <?php if (!$datesByYearMonth): ?>
                <div class="muted">No dates to show.</div>
            <?php else: ?>
                <?php ksort($datesByYearMonth); ?>
                <?php foreach ($datesByYearMonth as $year => $months): ?>
                    <h4><?= (int)$year ?></h4>
                    <div class="grid">
                        <?php ksort($months); ?>
                        <?php foreach ($months as $m => $dates): ?>
                            <div class="month">
                                <h4><?= date('F', mktime(0, 0, 0, (int)$m, 1)); ?> (<?= count($dates) ?> day<?= count($dates) > 1 ? 's' : ''; ?>)</h4>
                                <div class="muted" style="line-height:1.8">
                                    <?php
                                    // Show as comma-separated, dd Mon format
                                    $pretty = array_map(function ($d) {
                                        return date('d M', strtotime($d));
                                    }, $dates);
                                    echo h(implode(', ', $pretty));
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php elseif ($racerId > 0): ?>
        <div class="card">
            <div class="muted">Racer not found or no data.</div>
        </div>
    <?php endif; ?>
</body>

</html>