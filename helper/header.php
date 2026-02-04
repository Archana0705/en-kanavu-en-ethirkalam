<?php
// $allowed_origins = [
//     'https://staging.d14ab8b5xeqqw5.amplifyapp.com/',
//     'https://uks.tn.gov.in'
// ];

// $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// if (in_array($origin, $allowed_origins, true)) {
//     header("Access-Control-Allow-Origin: $origin");
//     header("Access-Control-Allow-Credentials: true");
//     header("Vary: Origin");
// }

// // Pre-flight (OPTIONS)
// if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
//     header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
//     header("Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-App-Key, X-App-Name, X-Captcha-Key");
//     header("Access-Control-Max-Age: 86400"); 
//     http_response_code(204);
//     exit;
// }
header('Content-Type: application/json; charset=UTF-8');
date_default_timezone_set('Asia/Calcutta');
$request_time = microtime(true);
require_once('autoloader.php');

try {
    $currentDirectory = __DIR__;
    $rootDirectory = findAndRequireAutoload($currentDirectory);
    $envFilePath = $rootDirectory . '/.env';

    // Check if the .env file exists
    if (file_exists($envFilePath)) {
        $dotenv = Dotenv\Dotenv::createImmutable($rootDirectory);
        $dotenv->load();
    } else {
        die("env file is not found");
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
// ---------------------------------------------------------------------
// 5. Environment & Debug Mode (Secure)
// ---------------------------------------------------------------------

$environment = $_ENV['ENV'] ?? 'production';
$debug       = filter_var($_ENV['DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);

// if ($debug && $environment === 'development') {
//     error_reporting(E_ALL);
//     ini_set('display_errors', '1');
//     ini_set('log_errors', '1');
// } else {
//     // Production: Hide errors, log to file
//     error_reporting(E_ALL);
//     ini_set('display_errors', '0');
//     ini_set('log_errors', '1');
//     ini_set('error_log', '/var/log/myapp/php-errors.log');
// }

// ---------------------------------------------------------------------
// 6. Include Auth Key Check (Optional)
// ---------------------------------------------------------------------
require_once __DIR__ . '/auth_key.php'; // Must define $valid_app_keys or similar

$_SERVER['REQUEST_ID'] = bin2hex(random_bytes(8));

// ---------------------------------------------------------------------
// Ready for your API routes!
// ---------------------------------------------------------------------

$permission = true;


function encryptData($data) {
    global $permission;

    if(!$permission){
        return $data;
    }
    $secretKey = 'bdfs|$uRve@!25Y$'; 
    $binaryKey = hash('sha256', $secretKey, true); 
    $iv = random_bytes(16);

    $cipherText = openssl_encrypt(json_encode($data), 'aes-256-cbc', $binaryKey, OPENSSL_RAW_DATA, $iv);
    if ($cipherText === false) {
        die("Encryption failed: " . openssl_error_string());
    }
    $combinedData = $iv . $cipherText;
    return base64_encode($combinedData);
    
}

function decryptData($encryptedData) {
    global $permission;
    if(!$permission){
        return $encryptedData;
    }
    $secretKey = 'bdfs|$uRve@!25Y$'; 
    $binaryKey = hash('sha256', $secretKey, true); 
    $decodedData = base64_decode($encryptedData);
    if ($decodedData === false) {
        die("Base64 decode failed.");
    }
    $iv = substr($decodedData, 0, 16); 
    $cipherText = substr($decodedData, 16);
    $decrypted = openssl_decrypt($cipherText, 'aes-256-cbc', $binaryKey, OPENSSL_RAW_DATA, $iv);
    if ($decrypted === false) {
        die("Decryption failed: " . openssl_error_string());
    }
    return json_decode($decrypted, true);
}

