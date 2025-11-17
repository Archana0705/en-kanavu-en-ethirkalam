<?php

// Include your encryption functions here (as you already have)
function encrypt($data)
{

    $type = $_SERVER['HTTP_X_APP_TYPE'] ?? null;

    if ($type == 'te$t') {
        return $data;
    } else {

        $secretKey = 'xmcK|fbngp@!71L$'; // Secret key
        $binaryKey = hash('sha256', $secretKey, true); // Derive 256-bit binary key
        $iv = random_bytes(16); // Generate random IV (16 bytes)

        $cipherText = openssl_encrypt(json_encode($data), 'aes-256-cbc', $binaryKey, OPENSSL_RAW_DATA, $iv);
        if ($cipherText === false) {
            die("Encryption failed: " . openssl_error_string());
        }

        // Combine IV and ciphertext
        $combinedData = $iv . $cipherText;

        // Return Base64-encoded string
        return base64_encode($combinedData);
    }
}

function decryptData($encryptedData)
{

    $type = $_SERVER['HTTP_X_APP_TYPE'] ?? null;

    if ($type == 'te$t') {
        return $encryptedData;
    } else {


        $secretKey = 'xmcK|fbngp@!71L$'; // Secret key
        $binaryKey = hash('sha256', $secretKey, true); // Derive 256-bit binary key
        // Decode the Base64-encoded input
        $decodedData = base64_decode($encryptedData);
        if ($decodedData === false) {
            die("Base64 decode failed.");
        }

        // Extract IV (first 16 bytes)
        $iv = substr($decodedData, 0, 16);
        // Extract Ciphertext (remaining bytes)
        $cipherText = substr($decodedData, 16);

        // Debug outputs

        // Decrypt the data
        $decrypted = openssl_decrypt($cipherText, 'aes-256-cbc', $binaryKey, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            die("Decryption failed: " . openssl_error_string());
        }

        // Decode JSON data if applicable
        return json_decode($decrypted, true);
    }
}

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$action = $input['action'] ?? '';
$data = $input['data'] ?? '';

if ($action === 'encrypt') {
    echo json_encode([
        'success' => 1,
        'encrypted' => encrypt($data)
    ]);
} elseif ($action === 'decrypt') {
    echo json_encode([
        'success' => 1,
        'decrypted' => decryptData($data)
    ]);
} else {
    echo json_encode([
        'success' => 0,
        'message' => 'Invalid action'
    ]);
}
