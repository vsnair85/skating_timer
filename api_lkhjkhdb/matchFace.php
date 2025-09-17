<?php
/**
 * match_face.php
 * Accepts: token_s, face_base64 (or face_image), optional user_id
 * Returns: best match if distance <= threshold; else success=false
 */
header('Content-Type: application/json; charset=utf-8');

require_once("api_config.php");

const API_TOKEN          = "6hmaIVxHAhU52xcjLh24Tj6HrvCMpyS5xHc931Hj";
const EMBED_SERVICE_URL  = "http://127.0.0.1:8001";
const MATCH_THRESHOLD    = 0.40; // tune after pilot

function call_embed_b64(string $b64): array {
    $ch = curl_init(EMBED_SERVICE_URL . "/embed");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => ['b64' => $b64],
        CURLOPT_TIMEOUT        => 30,
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) { $e = curl_error($ch); curl_close($ch); return ['ok'=>false,'error'=>$e]; }
    curl_close($ch);
    $json = json_decode($resp, true);
    return is_array($json) ? $json : ['ok'=>false,'error'=>'Bad embed response'];
}

function cosine_distance_from_b64_vs_bin(string $probe_b64, string $cand_bin, int $dim = 512): float {
    $a = unpack("f*", base64_decode($probe_b64, true));
    $b = unpack("f*", $cand_bin);
    if (!$a || !$b) return INF;
    $dot=$na=$nb=0.0;
    for($i=1;$i<=$dim;$i++){ $va=$a[$i]; $vb=$b[$i]; $dot+=$va*$vb; $na+=$va*$va; $nb+=$vb*$vb; }
    if($na==0.0||$nb==0.0) return INF;
    $sim = $dot / (sqrt($na)*sqrt($nb));
    return 1.0 - $sim;
}

try {
    // Token
    $token = $_REQUEST['token_s'] ?? '';
    if ($token !== API_TOKEN) throw new Exception('Token Authentication Failed');

    // Inputs
    $userIdOpt  = isset($_POST['user_id']) ? (int)$_POST['user_id'] : null;
    $faceB64    = $_POST['face_base64'] ?? '';

    if ($faceB64 === '' && !empty($_FILES['face_image']['name'])) {
        if ($_FILES['face_image']['error'] !== UPLOAD_ERR_OK) throw new Exception('File upload failed');
        $bin = file_get_contents($_FILES['face_image']['tmp_name']);
        if ($bin === false) throw new Exception('Failed to read uploaded file');
        $faceB64 = base64_encode($bin);
    }
    if ($faceB64 === '') throw new Exception('face_base64 required (or face_image)');

    // Get probe embedding
    $embedResp = call_embed_b64($faceB64);
    if (empty($embedResp['ok'])) {
        $reason = $embedResp['reason'] ?? $embedResp['error'] ?? 'EMBED_FAILED';
        throw new Exception("Embedding failed: {$reason}");
    }
    $probe_b64 = $embedResp['embedding_b64'];
    $dim       = (int)$embedResp['dim'];

    $api = new skatingApi();

    // Load candidates (scope by user if provided)
    if ($userIdOpt) {
        $rows = $api->getRacerEmbeddingsByUser($userIdOpt);
    } else {
        $rows = $api->getAllRacerEmbeddings();
    }

    $best = null; $bestDist = 999.0;
    foreach ($rows as $r) {
        if (empty($r['tr_embed'])) continue;
        $dist = cosine_distance_from_b64_vs_bin($probe_b64, $r['tr_embed'], (int)$r['tr_embed_dim']);
        if ($dist < $bestDist) { $bestDist = $dist; $best = $r; }
    }

    if ($best && $bestDist <= MATCH_THRESHOLD) {
        echo json_encode([
            'success'       => true,
            'tr_id'         => (int)$best['tr_id'],
            'tr_name'       => $best['tr_name'],
            'tr_image_path' => "https://timer.vishnusnair.com/api_lkhjkhdb".$best['tr_image_path'],
            'distance'      => round($bestDist, 4)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No match found']);
    }

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
