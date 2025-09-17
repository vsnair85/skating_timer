<?php
header('Content-Type: application/json; charset=utf-8');
require_once("api_config.php"); // must include your skatingApi class with saveRaceWithLaps()

$obj = new skatingApi();

// 1) Token check (same as your sample)
$token = isset($_REQUEST['token_s']) ? $_REQUEST['token_s'] : "";
if (empty($token)) {
    die(json_encode(["success" => false, "message" => "You must include a 'secure token' in your request."]));
}
if ($token !== "6hmaIVxHAhU52xcjLh24Tj6HrvCMpyS5xHc931Hj") {
    die(json_encode(["success" => false, "message" => "Token Authentication Failed"]));
}

// Common (race-level) fields
$racer       = isset($_POST['racer']) ? (int)$_POST['racer'] : 0;
$startedAt   = isset($_POST['started_at']) ? $_POST['started_at'] : "";
$endedAt     = isset($_POST['ended_at']) ? $_POST['ended_at'] : "";
$elapsedMs   = isset($_POST['elapsed_ms']) ? (int)$_POST['elapsed_ms'] : 0; // not stored, but accepted
$racerName   = isset($_POST['racer_name']) ? $_POST['racer_name'] : "";

// ---- Build laps ----
$laps = [];

// A) Bracketed keys -> PHP automatically parses to nested arrays in $_POST['laps']
if (isset($_POST['laps']) && is_array($_POST['laps'])) {
    foreach ($_POST['laps'] as $lap) {
        // tolerate missing optional formatted fields
        $laps[] = [
            "index"                  => isset($lap['index']) ? (int)$lap['index'] : null,
            "lap_ms"                 => isset($lap['lap_ms']) ? (int)$lap['lap_ms'] : null,
            "cumulative_ms"          => isset($lap['cumulative_ms']) ? (int)$lap['cumulative_ms'] : null,
            "lap_formatted"          => isset($lap['lap_formatted']) ? $lap['lap_formatted'] : null,
            "cumulative_formatted"   => isset($lap['cumulative_formatted']) ? $lap['cumulative_formatted'] : null,
        ];
    }
}
// B) Parallel arrays (index[], lap_ms[], cumulative_ms[], lap_formatted[], cumulative_formatted[])
elseif (
    isset($_POST['index']) && is_array($_POST['index']) &&
    isset($_POST['lap_ms']) && is_array($_POST['lap_ms']) &&
    isset($_POST['cumulative_ms']) && is_array($_POST['cumulative_ms'])
) {
    $count = count($_POST['index']);
    for ($i = 0; $i < $count; $i++) {
        $laps[] = [
            "index"                 => (int)($_POST['index'][$i] ?? 0),
            "lap_ms"                => (int)($_POST['lap_ms'][$i] ?? 0),
            "cumulative_ms"         => (int)($_POST['cumulative_ms'][$i] ?? 0),
            "lap_formatted"         => isset($_POST['lap_formatted'][$i]) ? $_POST['lap_formatted'][$i] : null,
            "cumulative_formatted"  => isset($_POST['cumulative_formatted'][$i]) ? $_POST['cumulative_formatted'][$i] : null,
        ];
    }
}
// C) Single field laps_json (optional fallback)
elseif (!empty($_POST['laps_json'])) {
    $decoded = json_decode($_POST['laps_json'], true);
    if (is_array($decoded)) {
        $laps = $decoded;
    }
}

// Build final payload the same shape your saveRaceWithLaps expects
$payload = [
    "racer"       => $racer,
    "started_at"  => $startedAt,
    "ended_at"    => $endedAt,
    "elapsed_ms"  => $elapsedMs,
    "racer_name"  => $racerName,
    "laps"        => $laps
];

// Validate & save
$result = $obj->saveRaceWithLaps($payload);

if (!empty($result['ok'])) {
    echo json_encode([
        "success" => true,
        "message" => "Race and laps saved successfully.",
        "race_id" => $result["race_id"],
        "inserted_laps" => $result["inserted_laps"]
    ]);
} else {
    echo json_encode([
        "success" => false,
        "error_code" => 2001,
        "message" => "Failed to save race.",
        "debug" => $result["error"] ?? "Unknown error" // remove in prod
    ]);
}
