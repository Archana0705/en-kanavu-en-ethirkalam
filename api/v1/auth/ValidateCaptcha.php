<?php
$allowed_origins = [
    'https://staging.d14ab8b5xeqqw5.amplifyapp.com/',
    'https://uks.tn.gov.in'
];
require_once('../../../helper/header.php');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
}

// User: hhredisadmin
// Pass: iDEgyEhkeeK
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-App-Key, X-App-Name, X-Captcha-Key");
    http_response_code(204);
    exit;
}
header('Content-Type: application/json');
$rootDir = dirname(__DIR__, 3);
require $rootDir . '/vendor/autoload.php';

$redis = new Predis\Client([
    'scheme'   => 'tcp',
    'host'     => '172.16.136.99',
    'port'     => 6379,
    'timeout'  => 1.0,
    'username' => 'hhredisadmin',
    'password' => 'iDEgyEhkeeK',
]);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $user_input = $_POST['captcha'] ?? '';
    $captcha_key = $_POST['captcha_key'] ?? '';

    if (empty($user_input) || empty($captcha_key)) {
        http_response_code(400);
        echo json_encode(["success" => 0, "message" => "Missing captcha or key"]);
        exit;
    }

    $stored_value = $redis->get("captcha:$captcha_key");

    if (!$stored_value) {
        http_response_code(200);
        echo json_encode(["success" => 0, "message" => "Captcha expired or invalid"]);
        exit;
    }

    if (strcasecmp($stored_value, $user_input) === 0) {
        $redis->del("captcha:$captcha_key");
        echo json_encode(["success" => 1, "message" => "Captcha is valid"]);
    } else {
        echo json_encode(["success" => 0, "message" => "Captcha is invalid"]);
    }
} else {
    http_response_code(405);
    echo json_encode(["success" => 0, "message" => "Method not allowed"]);
}
