<?php
require '../script/db.php';
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors','0'); ini_set('log_errors','1');

function fail($msg,$code=400){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['embedding']) || !is_array($input['embedding'])) {
  fail('Invalid embedding');
}
$probe = $input['embedding'];

function euclidean($a,$b){
  $sum=0.0; $n=min(count($a),count($b));
  for($i=0;$i<$n;$i++){ $d=floatval($a[$i])-floatval($b[$i]); $sum+=$d*$d; }
  return sqrt($sum);
}

$res = $mysqli->query("SELECT tr_id, tr_name, tr_number, tr_embedding, tr_image_path FROM tbl_racers");
if(!$res) fail('DB error');
$best=null; $bestDist=999;

while($row=$res->fetch_assoc()){
  $emb=json_decode($row['tr_embedding'], true);
  if(!is_array($emb)) continue;
  $dist=euclidean($probe,$emb);
  if($dist<$bestDist){
    $bestDist=$dist; $best=$row;
  }
}
$res->free();

$THRESHOLD = 0.55;

if($best && $bestDist <= $THRESHOLD){
  echo json_encode([
    'ok'=>true,
    'match'=>true,
    'distance'=>$bestDist,
    'racer'=>[
      'id'=>(int)$best['tr_id'],
      'name'=>$best['tr_name'],
      'number'=>$best['tr_number'],
      'image'=>$best['tr_image_path']
    ]
  ]);
} else {
  echo json_encode(['ok'=>true,'match'=>false]);
}
