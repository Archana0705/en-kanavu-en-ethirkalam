<?php

require_once('../../../helper/header.php');
require_once('../../../helper/log_file.php');
require_once('../../../config/write_database.php');

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    echo json_encode([
        "success" => 3,
        "message" => "Method Not Allowed"
    ]);
    exit;
}

$name = $_POST['name'] ?? null;
$mobile_number = $_POST['mobile_number'] ?? null;
$district_code = $_POST['district_code'] ?? 0;
$taluk_code = $_POST['taluk_code'] ?? 0;

$roleData = $_POST['role_data'] ?? '[]';
$mappingData = $_POST['mapping_data'] ?? '[]';

if (empty($name) || empty($mobile_number)) {
    http_response_code(400);
    echo json_encode([
        "success" => 0,
        "message" => "Name and Mobile Number are required"
    ]);
    exit;
}

try {






    // Prepare JSON for user_detail
    $userDetail = json_encode([
        "name" => $name,
        "mobile_no" => $mobile_number,
        "district_id" => $district_code,
        "taluk_id" => $taluk_code,
        "username" => $mobile_number,
        "password" => password_hash("secure@123", PASSWORD_BCRYPT),
        "active" => true,
        "created_by" => $_POST['user_id'] ?? 1
    ]);

    $userRoleData = $roleData;      // already JSON from frontend
    $userMappingData = $mappingData;   // already JSON from frontend

    $status = false;


    // CALL PROCEDURE
    $stmt = $write_db->prepare("
        CALL sp_usercreation(:userdetail, :userroledata, :usermappingdata, :status)
    ");

    $stmt->bindParam(':userdetail', $userDetail, PDO::PARAM_STR);
    $stmt->bindParam(':userroledata', $userRoleData, PDO::PARAM_STR);
    $stmt->bindParam(':usermappingdata', $userMappingData, PDO::PARAM_STR);
    $stmt->bindParam(':status', $status, PDO::PARAM_BOOL | PDO::PARAM_INPUT_OUTPUT, 1);

    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $status = $result['status'] ?? $status;

    http_response_code(200);
    echo json_encode([
        "success" => $status ? 1 : 0,
        "message" => $status ? "User Created Successfully" : "User Creation Failed",
        "status" => $status
    ]);
    exit;

} catch (PDOException $e) {
    // Database-specific errors
    $errorCode = $e->getCode();
    $errorMessage = $e->getMessage();

    // Log detailed error
    error_log("PDOException [Code: $errorCode]: " . $errorMessage);
    error_log("File: " . $e->getFile() . ":" . $e->getLine());
    error_log("Trace: " . $e->getTraceAsString());

    // Check if this is a "success" exception from the stored procedure
    if (strpos($errorMessage, 'New user created successfully') !== false) {
        // Extract user_id from the success message
        $userId = null;
        if (preg_match('/user_id=(\d+)/', $errorMessage, $matches)) {
            $userId = $matches[1];
        }

        // This is actually a success case (unusual but based on your stored procedure)
        $responseData = [
            "success" => true,
            "code" => 200,
            "message" => "User Created Successfully",
            "data" => [
                "name" => $name,
                "mobile_number" => $mobile_number,
                "user_id" => $userId
            ]
        ];

        http_response_code(200);
        echo json_encode($responseData);
        exit;
    }

    // Map error messages to user-friendly responses
    $httpCode = 400;
    $errorType = "Database Error";
    $userMessage = $errorMessage; // Show the actual DB error message

    // Check for specific error conditions from stored procedure
    if (
        strpos($errorMessage, 'User with mobile_no') !== false &&
        strpos($errorMessage, 'already exists') !== false
    ) {
        $httpCode = 409;
        $errorType = "Duplicate Entry";
        $userMessage = "A user with this mobile number already exists.";
    } elseif (strpos($errorMessage, 'Password is mandatory') !== false) {
        $httpCode = 400;
        $errorType = "Validation Error";
        $userMessage = "Password is required.";
    } elseif (strpos($errorMessage, 'Username is mandatory') !== false) {
        $httpCode = 400;
        $errorType = "Validation Error";
        $userMessage = "Username is required.";
    } elseif (strpos($errorMessage, 'Mobile number is mandatory') !== false) {
        $httpCode = 400;
        $errorType = "Validation Error";
        $userMessage = "Mobile number is required.";
    } elseif (strpos($errorMessage, 'Invalid role_id:') !== false) {
        $httpCode = 400;
        $errorType = "Validation Error";
        // Extract role_id from error message
        if (preg_match('/Invalid role_id:\s*(\d+)/', $errorMessage, $matches)) {
            $userMessage = "Invalid role ID: " . $matches[1];
        } else {
            $userMessage = "Invalid role ID provided.";
        }
    } elseif (
        strpos($errorMessage, 'duplicate') !== false ||
        strpos($errorMessage, 'unique constraint') !== false
    ) {
        $httpCode = 409;
        $errorType = "Duplicate Entry";
        $userMessage = "A duplicate entry was detected.";
    } elseif (strpos($errorMessage, 'foreign key') !== false) {
        $httpCode = 400;
        $errorType = "Invalid Reference";
        $userMessage = "Invalid reference to another record.";
    }

    // Prepare error response
    $response = [
        "success" => false,
        "code" => $httpCode,
        "message" => $errorType,
        "error" => $userMessage
    ];

    // Add debug information only in development mode
    if (defined('APP_DEBUG') && APP_DEBUG) {
        $response["debug_info"] = [
            "database_error" => $errorMessage,
            "database_code" => $errorCode
        ];
    }

    http_response_code($httpCode);
    echo json_encode($response);

} catch (Throwable $e) {
    // General exceptions
    $errorMessage = $e->getMessage();
    error_log("General Exception: " . $errorMessage);
    error_log("File: " . $e->getFile() . ":" . $e->getLine());

    $httpCode = 500;
    $errorType = "Application Error";
    $userMessage = "An unexpected error occurred. Please try again.";

    $response = [
        "success" => false,
        "code" => $httpCode,
        "message" => $errorType,
        "error" => $userMessage
    ];

    // Add debug information only in development mode
    if (defined('APP_DEBUG') && APP_DEBUG) {
        $response["debug_info"] = [
            "exception" => $errorMessage,
            "file" => $e->getFile(),
            "line" => $e->getLine()
        ];
    }

    http_response_code(400);
    echo json_encode($response);

} finally {
    $write_db = null;
}

?>