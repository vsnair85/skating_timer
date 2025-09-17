<?php
error_reporting(E_ALL);

class skatingApi
{
	var $db;

	function __construct()
	{
		$this->db = mysqli_connect("localhost", "root", "", "skating_timer");
	}

	function loginUser($phone, $password)
	{
		// Use a prepared statement to prevent SQL injection
		$sql = "SELECT * FROM `tbl_users` WHERE `tu_mobile`= ?";
		$stmt = mysqli_prepare($this->db, $sql);
		mysqli_stmt_bind_param($stmt, "s", $phone);
		mysqli_stmt_execute($stmt);
		$result = mysqli_stmt_get_result($stmt);

		if (mysqli_num_rows($result) > 0) {
			$row = mysqli_fetch_assoc($result);

			if ($row) {
				// Check if user profile is activated
				if ($row['tu_status'] != '1') {
					return ["error_code" => 1212, "message" => "The Account is not active now. Please Contact Admin."];
				}

				// Verify password
				if (password_verify($password, $row['tu_pass'])) {
					return (object) $row;
				}
			}
		}

		return false;
	}

	function listRacers()
	{
		// Use a prepared statement to prevent SQL injection
		$sql = "SELECT tr_id,tr_name FROM `tbl_racers`";
		$stmt = mysqli_prepare($this->db, $sql);
		mysqli_stmt_execute($stmt);
		$result = mysqli_stmt_get_result($stmt);

		if (mysqli_num_rows($result) > 0) {
			$racers = [];
			while ($row = mysqli_fetch_assoc($result)) {
				$racers[] = (object) $row;
			}
			return $racers;
		}

		return false;
	}

	function addAttendance($racerid, $intime)
	{
		// Normalize/validate $intime to 'Y-m-d H:i:s'
		$ts = strtotime($intime);
		if ($ts === false) {
			return 'ERROR_FORMAT'; // bad datetime
		}
		$intimeNorm = date('Y-m-d H:i:s', $ts);

		// 1) Check if an attendance exists within the last 5 hours from the PASSED $intime
		// Use a BETWEEN to cap the window [intime-5h, intime]
		$checkSql = "
        SELECT 1
        FROM tbl_attendance
        WHERE ta_racer_id = ?
          AND ta_chkd_in_at BETWEEN DATE_SUB(?, INTERVAL 5 HOUR) AND ?
        LIMIT 1
    ";
		$checkStmt = mysqli_prepare($this->db, $checkSql);
		mysqli_stmt_bind_param($checkStmt, "iss", $racerid, $intimeNorm, $intimeNorm);
		mysqli_stmt_execute($checkStmt);
		mysqli_stmt_store_result($checkStmt);

		if (mysqli_stmt_num_rows($checkStmt) > 0) {
			mysqli_stmt_close($checkStmt);
			return 'DUPLICATE';
		}
		mysqli_stmt_close($checkStmt);

		// 2) Insert
		$sql = "INSERT INTO tbl_attendance (ta_racer_id, ta_chkd_in_at) VALUES (?, ?)";
		$stmt = mysqli_prepare($this->db, $sql);
		mysqli_stmt_bind_param($stmt, "is", $racerid, $intimeNorm);

		if (mysqli_stmt_execute($stmt)) {
			mysqli_stmt_close($stmt);
			return true;              // inserted
		} else {
			mysqli_stmt_close($stmt);
			return false;             // DB error
		}
	}


	function saveRaceWithLaps(array $payload)
	{
		// Basic validation
		if (
			!isset($payload['racer'], $payload['started_at'], $payload['ended_at'], $payload['laps']) ||
			!is_array($payload['laps']) || count($payload['laps']) === 0
		) {
			return ["ok" => false, "error" => "INVALID_PAYLOAD"];
		}

		$racerId   = (int)$payload['racer'];
		$startedAt = $payload['started_at']; // "YYYY-MM-DD HH:MM:SS"
		$endedAt   = $payload['ended_at'];   // "YYYY-MM-DD HH:MM:SS"

		// trs_total_ms = cumulative_formatted of the LAST lap (as requested)
		$lastLap = $payload['laps'][count($payload['laps']) - 1];
		$totalMsFormatted = isset($lastLap['cumulative_ms']) ? $lastLap['cumulative_ms'] : null;

		if ($totalMsFormatted === null) {
			return ["ok" => false, "error" => "MISSING_LAST_LAP_TOTAL"];
		}

		mysqli_begin_transaction($this->db);
		try {
			// Insert into tbl_races
			$sqlRace = "INSERT INTO tbl_races (trs_racer_id, trs_started_at, trs_finished_at, trs_total_ms)
                    VALUES (?, ?, ?, ?)";
			$stmtRace = mysqli_prepare($this->db, $sqlRace);
			if (!$stmtRace) {
				throw new Exception("PREPARE_RACE_FAILED");
			}
			mysqli_stmt_bind_param($stmtRace, "isss", $racerId, $startedAt, $endedAt, $totalMsFormatted);
			if (!mysqli_stmt_execute($stmtRace)) {
				throw new Exception("EXEC_RACE_FAILED");
			}
			$raceId = mysqli_insert_id($this->db);
			mysqli_stmt_close($stmtRace);

			// Prepare lap insert
			$sqlLap = "INSERT INTO tbl_laps (tl_race_id, tl_racer_id, tl_lap_no, tl_lap_ms, tl_cumulative_ms)
                   VALUES (?, ?, ?, ?, ?)";
			$stmtLap = mysqli_prepare($this->db, $sqlLap);
			if (!$stmtLap) {
				throw new Exception("PREPARE_LAP_FAILED");
			}

			// Insert each lap
			foreach ($payload['laps'] as $lap) {
				// Required: index, lap_ms, cumulative_ms
				if (!isset($lap['index'], $lap['lap_ms'], $lap['cumulative_ms'])) {
					throw new Exception("INVALID_LAP_ROW");
				}
				$lapNo         = (int)$lap['index'];
				$lapMs         = (int)$lap['lap_ms'];
				$cumulativeMs  = (int)$lap['cumulative_ms'];

				mysqli_stmt_bind_param($stmtLap, "iiiii", $raceId, $racerId, $lapNo, $lapMs, $cumulativeMs);
				if (!mysqli_stmt_execute($stmtLap)) {
					throw new Exception("EXEC_LAP_FAILED");
				}
			}
			mysqli_stmt_close($stmtLap);

			mysqli_commit($this->db);

			return [
				"ok" => true,
				"race_id" => $raceId,
				"inserted_laps" => count($payload['laps'])
			];
		} catch (Exception $e) {
			mysqli_rollback($this->db);
			return ["ok" => false, "error" => $e->getMessage()];
		}
	}

	function raceReport($racerid)
	{
		// Use INT binding if trs_racer_id is INT
		$sql = "SELECT tr.*, tl.tl_id, tl.tl_lap_no, tl.tl_lap_ms, tl.tl_cumulative_ms FROM tbl_races AS tr LEFT JOIN tbl_laps AS tl ON tr.trs_id = tl.tl_race_id WHERE tr.trs_racer_id = ? ORDER BY tr.trs_id ASC";

		$stmt = mysqli_prepare($this->db, $sql);
		// change "i" to "s" if your racerid is actually varchar
		mysqli_stmt_bind_param($stmt, "i", $racerid);
		mysqli_stmt_execute($stmt);
		$result = mysqli_stmt_get_result($stmt);

		if (!$result || mysqli_num_rows($result) === 0) {
			return false;
		}

		$races = []; // keyed by trs_id

		while ($row = mysqli_fetch_assoc($result)) {
			$raceId = (int)$row['trs_id'];

			if (!isset($races[$raceId])) {
				// Build the race shell once
				$races[$raceId] = [
					'trs_id'        => $raceId,
					'trs_racer_id'  => isset($row['trs_racer_id']) ? (int)$row['trs_racer_id'] : null,
					// include whatever columns you have in tbl_races:
					'racer_name'    => $row['trs_racer_name'] ?? null,
					'started_at'    => $row['trs_started_at'] ?? null,
					'ended_at'      => $row['trs_finished_at'] ?? null,
					'elapsed_ms'    => isset($row['trs_total_ms']) ? (int)$row['trs_total_ms'] : null,
					'laps'          => [],
				];
			}

			// If there is a lap on this row, push it into laps[]
			if (!empty($row['tl_id'])) {
				$races[$raceId]['laps'][] = [
					'id'                     => (int)$row['tl_id'],
					'index'                  => isset($row['tl_lap_no']) ? (int)$row['tl_lap_no'] : null,
					'lap_ms'                 => isset($row['tl_lap_ms']) ? (int)$row['tl_lap_ms'] : null,
					'cumulative_ms'          => isset($row['tl_cumulative_ms']) ? (int)$row['tl_cumulative_ms'] : null,
				];
			}
		}

		// Reindex to a plain array
		return array_values($races);
	}

	public function racerFaceExists(int $userId, string $racerName): ?array
	{
		$sql = "SELECT tr_id, tr_image_path
            FROM tbl_racers
            WHERE tr_parentid = ? AND tr_name = ?
            LIMIT 1";
		$stmt = mysqli_prepare($this->db, $sql);
		if (!$stmt) return null;

		mysqli_stmt_bind_param($stmt, "is", $userId, $racerName);
		mysqli_stmt_execute($stmt);
		$res = mysqli_stmt_get_result($stmt);
		$row = $res ? mysqli_fetch_assoc($res) : null;
		mysqli_stmt_close($stmt);

		return $row ?: null;
	}


	public function saveRacerFaceRow(array $p)
	{
		// Expect: user_id, racer_name, face_base64, image_path, embed_bin, embed_dim
		if (empty($p['user_id']) || empty($p['racer_name']) || empty($p['face_base64']) || empty($p['image_path']) || empty($p['embed_bin'])) {
			return ['ok' => false, 'error' => 'INVALID_PAYLOAD'];
		}

		$userId     = (int)$p['user_id'];
		$racerName  = $p['racer_name'];
		$faceBase64 = $p['face_base64'];
		$imagePath  = $p['image_path'];
		$embedBin   = $p['embed_bin']; // binary string (float32 x dim)
		$embedDim   = (int)($p['embed_dim'] ?? 512);

		// Insert row including embedding
		$sql = "INSERT INTO tbl_racers (tr_name, tr_parentid, tr_base_code, tr_image_path, tr_embed, tr_embed_dim)
            VALUES (?, ?, ?, ?, ?, ?)";
		$stmt = mysqli_prepare($this->db, $sql);
		if (!$stmt) {
			return ['ok' => false, 'error' => 'PREPARE_FAILED: ' . mysqli_error($this->db)];
		}

		// types: s i s s b i — but mysqli doesn't support 'b' here; we bind as string and use send_long_data
		mysqli_stmt_bind_param($stmt, "sisssi", $racerName, $userId, $faceBase64, $imagePath, $embedBin, $embedDim);
		// Make sure the blob is sent properly (index 5th param = zero-based? No, send_long_data uses param index starting at 0)
		// Our params: 0:racerName 1:userId 2:base64 3:imagePath 4:embedBin 5:embedDim
		mysqli_stmt_send_long_data($stmt, 4, $embedBin);

		if (!mysqli_stmt_execute($stmt)) {
			$err = mysqli_error($this->db);
			mysqli_stmt_close($stmt);
			return ['ok' => false, 'error' => 'EXEC_FAILED: ' . $err];
		}

		$id = mysqli_insert_id($this->db);
		mysqli_stmt_close($stmt);

		return ['ok' => true, 'racer_id' => $id];
	}

	public function getRacerEmbeddingsByUser(int $userId): array
	{
		$sql = "SELECT tr_id, tr_name, tr_image_path, tr_embed, tr_embed_dim
            FROM tbl_racers
            WHERE tr_parentid = ? AND tr_embed IS NOT NULL";
		$stmt = mysqli_prepare($this->db, $sql);
		if (!$stmt) return [];
		mysqli_stmt_bind_param($stmt, "i", $userId);
		mysqli_stmt_execute($stmt);
		$res = mysqli_stmt_get_result($stmt);
		$rows = [];
		if ($res) {
			while ($row = mysqli_fetch_assoc($res)) {
				$rows[] = $row;
			}
		}
		mysqli_stmt_close($stmt);
		return $rows;
	}

	public function getAllRacerEmbeddings(): array
	{
		$sql  = "SELECT tr_id, tr_name, tr_image_path, tr_embed, tr_embed_dim
             FROM tbl_racers
             WHERE tr_embed IS NOT NULL";
		$res = mysqli_query($this->db, $sql);
		$rows = [];
		if ($res) {
			while ($row = mysqli_fetch_assoc($res)) {
				$rows[] = $row;
			}
		}
		return $rows;
	}
}
