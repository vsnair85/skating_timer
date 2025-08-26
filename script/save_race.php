<?php
require 'db.php';
header('Content-Type: application/json');

try {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  if (!$data) throw new Exception('Invalid JSON');

  $racer_id = (int)($data['racer_id'] ?? 0);
  $total_ms = (int)($data['total_ms'] ?? 0);
  $started_iso  = $data['started_at_iso']  ?? null;
  $finished_iso = $data['finished_at_iso'] ?? null;
  $laps = $data['laps'] ?? [];

  if ($racer_id <= 0 || $total_ms <= 0) throw new Exception('Bad input');

  // Optional: verify racer exists (simple SELECT)
  $exists = 0;
  if ($st = $mysqli->prepare("SELECT 1 FROM tbl_racers WHERE tr_id=? LIMIT 1")) {
    $st->bind_param('i', $racer_id);
    $st->execute();
    $st->bind_result($exists);
    $st->fetch();
    $st->close();
  }
  if (!$exists) throw new Exception('Racer not found');

  // ISO -> 'Y-m-d H:i:s.v' (trim to milliseconds)
  function iso_to_mysql_ms($iso) {
    if (!$iso) return null;
    $dt = new DateTime($iso);
    // MySQL DATETIME(3) style string
    $ms = (int)floor(((int)$dt->format('u'))/1000); // micro -> milli
    return $dt->format('Y-m-d H:i:s') . '.' . str_pad((string)$ms, 3, '0', STR_PAD_LEFT);
  }

  $started_at  = $started_iso  ? iso_to_mysql_ms($started_iso)  : null;
  $finished_at = $finished_iso ? iso_to_mysql_ms($finished_iso) : null;

  // Insert race
  $race_id = 0;
  $st1 = $mysqli->prepare("INSERT INTO tbl_races (trs_racer_id, trs_started_at, trs_finished_at, trs_total_ms) VALUES (?, ?, ?, ?)");
  $st1->bind_param('issi', $racer_id, $started_at, $finished_at, $total_ms);
  if (!$st1->execute()) throw new Exception('Insert race failed');
  $race_id = $mysqli->insert_id;
  $st1->close();

  // Insert laps (if any)
  if (is_array($laps) && count($laps) > 0) {
    $st2 = $mysqli->prepare("INSERT INTO tbl_laps (tl_race_id, tl_racer_id, tl_lap_no, tl_lap_ms, tl_cumulative_ms) VALUES (?, ?, ?, ?, ?)");
    foreach ($laps as $lap) {
      $lap_no = (int)($lap['no'] ?? 0);
      $lap_ms = (int)($lap['lapMs'] ?? 0);
      $cum_ms = (int)($lap['cumulative'] ?? 0);
      if ($lap_no > 0 && $lap_ms >= 0 && $cum_ms >= 0) {
        $st2->bind_param('iiiii', $race_id, $racer_id, $lap_no, $lap_ms, $cum_ms);
        $st2->execute(); // ignore per-lap errors for simplicity
      }
    }
    $st2->close();
  }

  echo json_encode(['ok' => true, 'race_id' => $race_id]);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Please contact the administrator.']);
}
