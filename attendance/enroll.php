<?php
require '../script/db.php';

// Support both form-post and JSON
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
  $input = json_decode(file_get_contents('php://input'), true);
  $name      = trim($input['name'] ?? '');
  $number    = trim($input['number'] ?? ($input['mobile'] ?? ''));
  $embedding = $input['embedding'] ?? null;
  $base64img = $input['image_base64'] ?? null;
} else {
  $name      = trim($_POST['name'] ?? '');
  $number    = trim($_POST['number'] ?? ($_POST['mobile'] ?? ''));
  $embedding = isset($_POST['embedding']) ? json_decode($_POST['embedding'], true) : null;
  $base64img = $_POST['image_base64'] ?? null;
}

if ($name === '' || $number === '' || !is_array($embedding)) {
  if (stripos($contentType, 'application/json') !== false) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Missing name/number/embedding']);
  } else {
    header('Location: enroll_form.php?error=1');
  }
  exit;
}

// Save image if provided
$imagePath = null;
if ($base64img) {
  if (!is_dir(__DIR__ . '/faces')) mkdir(__DIR__ . '/faces', 0775, true);
  $raw = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64img));
  $fname = 'faces/' . preg_replace('/\D+/','',$number) . '_' . time() . '.jpg';
  file_put_contents(__DIR__ . '/' . $fname, $raw);
  $imagePath = $fname;
}

$stmt = $mysqli->prepare("
  INSERT INTO tbl_racers (tr_name, tr_number, tr_embedding, tr_image_path, tr_created_at, tr_updated_at)
  VALUES (?, ?, ?, ?, NOW(), NOW())
");
$embJson = json_encode(array_values($embedding));
$stmt->bind_param('ssss', $name, $number, $embJson, $imagePath);

if (!$stmt->execute()) {
  if (stripos($contentType, 'application/json') !== false) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(409);
    echo json_encode(['ok'=>false,'error'=>'Insert failed: '.$stmt->error]);
  } else {
    header('Location: enroll_form.php?error=2');
  }
  $stmt->close(); exit;
}
$stmt->close();

// Go back to home
if (stripos($contentType, 'application/json') !== false) {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok'=>true]);
} else {
  header('Location: index.php?saved=1');
}
