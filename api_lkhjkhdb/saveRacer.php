<?php
/**
 * save_racer_face.php
 * Accepts: token_s, user_id, racer_name, face_base64, face_image (file)
 * Saves to tbl_racers: tr_name, tr_parentid, tr_base_code, tr_image_path, tr_embed, tr_embed_dim
 */
header('Content-Type: application/json; charset=utf-8');

require_once("api_config.php"); // provides skatingApi + mysqli $this->db inside it

// ---- local config for the embed microservice ----
const API_TOKEN          = "6hmaIVxHAhU52xcjLh24Tj6HrvCMpyS5xHc931Hj";
const EMBED_SERVICE_URL  = "http://127.0.0.1:8001";
const MIN_DET_SCORE      = 0.35; // detector confidence gate

function call_embed_with_file(string $filePath): array {
    $ch = curl_init(EMBED_SERVICE_URL . "/embed");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => [
            'file'      => new CURLFile($filePath),
            'min_score' => (string)MIN_DET_SCORE
        ],
        CURLOPT_TIMEOUT        => 30,
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) { $e = curl_error($ch); curl_close($ch); return ['ok'=>false,'error'=>$e]; }
    curl_close($ch);
    $json = json_decode($resp, true);
    return is_array($json) ? $json : ['ok'=>false,'error'=>'Bad embed response'];
}

function call_embed_with_b64(string $b64): array {
    $ch = curl_init(EMBED_SERVICE_URL . "/embed");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => [
            'b64'       => $b64,
            'min_score' => (string)MIN_DET_SCORE
        ],
        CURLOPT_TIMEOUT        => 30,
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) { $e = curl_error($ch); curl_close($ch); return ['ok'=>false,'error'=>$e]; }
    curl_close($ch);
    $json = json_decode($resp, true);
    return is_array($json) ? $json : ['ok'=>false,'error'=>'Bad embed response'];
}

try {
    // 1) Token
    $token = $_REQUEST['token_s'] ?? '';
    if ($token === '') throw new Exception("You must include a 'secure token' in your request.");
    if ($token !== API_TOKEN) throw new Exception("Token Authentication Failed");

    // 2) Required inputs
    $userId     = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $racerName  = trim($_POST['racer_name'] ?? '');
    $faceBase64 = $_POST['face_base64'] ?? '';

    if ($userId <= 0)       throw new Exception("Missing/invalid user_id");
    if ($racerName === '')  throw new Exception("Missing racer_name");
    if ($faceBase64 === '' && empty($_FILES['face_image']['name'])) {
        throw new Exception("Provide face_base64 or face_image");
    }

    $api = new skatingApi();

    // (Optional) Block duplicates for same user + name
    // if ($api->racerFaceExists($userId, $racerName)) {
    //     http_response_code(409);
    //     echo json_encode([
    //         'success' => false,
    //         'code'    => 'RACER_EXISTS',
    //         'message' => 'Face already registered for this racer under this user.'
    //     ]);
    //     exit;
    // }

    // 3) Handle the image file (keep your original behavior)
    if (empty($_FILES['face_image']['name'])) {
        throw new Exception("Missing face_image file");
    }
    if ($_FILES['face_image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("File upload failed with code: " . (int)$_FILES['face_image']['error']);
    }

    $tmp  = $_FILES['face_image']['tmp_name'];

    // MIME check (fallback if fileinfo missing)
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $tmp);
        finfo_close($finfo);
    } else {
        $mime = mime_content_type($tmp);
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) throw new Exception("Unsupported image type: " . $mime);
    if (filesize($tmp) > 6 * 1024 * 1024) throw new Exception("Image too large. Max 6 MB");

    $baseDir   = 'uploads/racers';
    $subDir    = date('Y') . '/' . date('m');
    $targetDir = $baseDir . '/' . $subDir;
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true)) {
        throw new Exception("Failed to create target directory");
    }

    $ext        = $allowed[$mime];
    $fileName   = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $targetPath = $targetDir . '/' . $fileName;

    if (!move_uploaded_file($tmp, $targetPath)) {
        throw new Exception("Failed to move uploaded file");
    }

    // Relative path for DB
    $relativePath = 'uploads/racers/' . $subDir . '/' . $fileName;

    // 4) Get embedding from the running InsightFace service
    // Prefer the just-saved file for best fidelity. If it fails, try base64.
    $embedResp = call_embed_with_file($targetPath);
    if (empty($embedResp['ok']) && $faceBase64 !== '') {
        $embedResp = call_embed_with_b64($faceBase64);
    }
    if (empty($embedResp['ok'])) {
        $reason = $embedResp['reason'] ?? $embedResp['error'] ?? 'EMBED_FAILED';
        throw new Exception("Embedding failed: {$reason}");
    }

    $emb_b64 = $embedResp['embedding_b64']; // base64 of float32[dim]
    $emb_bin = base64_decode($emb_b64, true);
    if ($emb_bin === false) throw new Exception("Invalid embedding_b64");
    $emb_dim = (int)($embedResp['dim'] ?? 512);

    // 5) Save to DB (same signature + embedding fields)
    $result = $api->saveRacerFaceRow([
        'user_id'     => $userId,
        'racer_name'  => $racerName,
        'face_base64' => $faceBase64,   // what the app sent
        'image_path'  => $relativePath, // stored file path
        'embed_bin'   => $emb_bin,      // NEW
        'embed_dim'   => $emb_dim       // NEW
    ]);

    if (!empty($result['ok'])) {
        echo json_encode([
            'success'     => true,
            'message'     => 'Racer face saved',
            'racer_id'    => $result['racer_id'] ?? null,
            'image_path'  => $relativePath
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'DB save failed',
            'debug'   => $result['error'] ?? 'Unknown error'
        ]);
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
