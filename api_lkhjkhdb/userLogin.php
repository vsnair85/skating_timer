<?php
require_once("api_config.php");

$obj = new skatingApi();

$token            = isset($_REQUEST['token_s']) ? $_REQUEST['token_s'] : "";
$password         = isset($_REQUEST['password']) ? $_REQUEST['password'] : "";
$phone             = isset($_REQUEST['phone']) ? $_REQUEST['phone'] : "";


$password = trim($password); // Remove spaces from the beginning and end
$password = str_replace(' ', '', $password); // Remove all spaces within the string

$phone = trim($phone); // Remove spaces from the beginning and end
$phone = str_replace(' ', '', $phone); // Remove all spaces within the string

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

if (empty($password)) {
    $response["success"] = "false";
    $response["message"] = "You must include a 'password' var in your request.";
    die(json_encode($response));
}
if (empty($phone)) {
    $response["success"] = "false";
    $response["message"] = "You must include a 'phone' var in your request.";
    die(json_encode($response));
}


$userData = $obj->loginUser($phone, $password);

if ($userData !== false) {
    if (is_array($userData) && isset($userData['error_code'])) { // If an error is returned
        $response = [
            "success" => false,
            "error_code" => $userData['error_code'],
            "message" => $userData['message']
        ];
    } else {
        $response = [
            "success" => true,
            "message" => "Logged in successfully.",
            "userdata" => [
                "user_id" => $userData->tu_id,
                "fullname" => $userData->tu_fullname,
                "email" => $userData->tu_email,
                "mobile_number" => $userData->tu_mobile,
                "role" => $userData->tu_role,
            ]
        ];
    }
} else {
    $response = [
        "success" => false,
        "error_code" => 1001,
        "message" => "Invalid username or password."
    ];
}

die(json_encode($response));

