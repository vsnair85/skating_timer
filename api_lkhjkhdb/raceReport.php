<?php
require_once("api_config.php");

$obj = new skatingApi();

$token            = isset($_REQUEST['token_s']) ? $_REQUEST['token_s'] : "";
$racerid            = isset($_REQUEST['racerid']) ? $_REQUEST['racerid'] : "";


if (empty($token)) {
    $response["success"] = "false";
    $response["message"] = "You must include a 'secure token' in your request.";
    die(json_encode($response));
}

if ($token !== "6hmaIVxHAhU52xcjLh24Tj6HrvCMpyS5xHc931Hj") {
    $response["success"] = "false";
    $response["message"] = "Token Authentication Failed";
    die(json_encode($response));
}

if (empty($racerid)) {
    $response["success"] = "false";
    $response["message"] = "You must include a 'Racer ID' in your request.";
    die(json_encode($response));
}


$userData = $obj->raceReport($racerid);

if ($userData !== false) {
    $response = [
        "success" => true,
        "message" => "List Of Racers Fetched Successfully.",
        "userdata" => $userData   // <-- this will now be a list of racer objects
    ];
} else {
    $response = [
        "success" => false,
        "error_code" => 1001,
        "message" => "No List Present."
    ];
}

die(json_encode($response));

