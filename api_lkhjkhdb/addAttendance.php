<?php
require_once("api_config.php");

$obj = new skatingApi();

$token   = isset($_REQUEST['token_s']) ? $_REQUEST['token_s'] : "";
$racerid = isset($_REQUEST['racerid']) ? $_REQUEST['racerid'] : "";
$intime  = isset($_REQUEST['intime']) ? $_REQUEST['intime'] : "";

if (empty($token)) {
    die(json_encode(["success" => false, "message" => "You must include a 'secure token' in your request."]));
}

if ($token !== "6hmaIVxHAhU52xcjLh24Tj6HrvCMpyS5xHc931Hj") {
    die(json_encode(["success" => false, "message" => "Token Authentication Failed"]));
}

if (empty($racerid)) {
    die(json_encode(["success" => false, "message" => "You must include a 'Racer ID' in your request."]));
}

if (empty($intime)) {
    die(json_encode(["success" => false, "message" => "You must include a 'In Time' in your request."]));
}

$isAdded = $obj->addAttendance($racerid, $intime);

if ($isAdded === true) {
    $response = [
        "success" => true,
        "message" => "Attendance added Successfully."
    ];
} elseif ($isAdded === 'DUPLICATE') {
    $response = [
        "success" => false,
        "error_code" => 1002,
        "message" => "Attendance already added within the last 5 hours."
    ];
} elseif ($isAdded === 'ERROR_FORMAT') {
    $response = [
        "success" => false,
        "error_code" => 1003,
        "message" => "Invalid 'In Time' format. Expected 'Y-m-d H:i:s'."
    ];
} else {
    $response = [
        "success" => false,
        "error_code" => 1001,
        "message" => "Something went wrong."
    ];
}

die(json_encode($response));

