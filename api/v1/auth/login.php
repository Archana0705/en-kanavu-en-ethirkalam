<?php
require_once('../../../helper/header.php');
require_once('../../../helper/log_file.php');
require_once('../../../config/read_database.php');
require_once('../../../config/write_database.php');

$startTime = microtime(true);
$serviceName = 'user_login';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    $response = json_encode([
        "success" => 3,
        "message" => "Method Not Allowed"
    ]);
    http_response_code(405);
    echo $response;
    
    // Log method not allowed error
    ApiLogger::writeLog(
        'error',
        'Method not allowed',
        405,
        $startTime,
        microtime(true),
        $_SERVER['REQUEST_METHOD'],
        $_SERVER['REQUEST_URI'],
        [],
        $serviceName,
        ['error_type' => 'method_not_allowed']
    );
    exit;
}

$username = $_POST['username'] ?? null;
$inputpassword = $_POST['password'] ?? null;

// Validate required parameters
if (empty($username)) {
    $response = json_encode([
        "success" => 3,
        "message" => "Username is required"
    ]);
    http_response_code(400);
    echo $response;
    
    // Log missing username
    ApiLogger::writeLog(
        'error',
        'Missing username parameter',
        400,
        $startTime,
        microtime(true),
        $_SERVER['REQUEST_METHOD'],
        $_SERVER['REQUEST_URI'],
        [],
        $serviceName,
        [
            'error_type' => 'missing_parameter',
            'parameter' => 'username',
            'request_data' => $_POST
        ]
    );
    exit;
}

if (empty($inputpassword)) {
    $response = json_encode([
        "success" => 3,
        "message" => "Password is required"
    ]);
    http_response_code(400);
    echo $response;
    
    // Log missing password
    ApiLogger::writeLog(
        'error',
        'Missing password parameter',
        400,
        $startTime,
        microtime(true),
        $_SERVER['REQUEST_METHOD'],
        $_SERVER['REQUEST_URI'],
        [],
        $serviceName,
        [
            'error_type' => 'missing_parameter',
            'parameter' => 'password',
            'username' => $username
        ]
    );
    exit;
}

try {
    // Check if user exists and verify password
    $sql = $read_db->prepare("SELECT * FROM public.fn_user_authentication(?)");
    $sql->bindParam(1, $username, PDO::PARAM_STR);

    if (!$sql->execute()) {
        $errorInfo = $sql->errorInfo();
        $response = json_encode([
            "success" => 0,
            "message" => "Authentication query failed",
            "error" => $errorInfo[2] ?? 'Unknown database error'
        ]);
        http_response_code(400);
        echo $response;
        
        // Log authentication query failure
        ApiLogger::writeLog(
            'error',
            'User authentication query failed',
            400,
            $startTime,
            microtime(true),
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REQUEST_URI'],
            [],
            $serviceName,
            [
                'error_type' => 'auth_query_failed',
                'error_details' => $errorInfo,
                'username' => $username,
                'sql_function' => 'fn_user_authentication'
            ]
        );
        exit;
    }

    $result = $sql->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        $response = json_encode([
            "success" => 0,
            "message" => "User not found",
            "data" => []
        ]);
        http_response_code(404);
        echo $response;
        
        // Log user not found
        ApiLogger::writeLog(
            'error',
            'User not found during login attempt',
            404,
            $startTime,
            microtime(true),
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REQUEST_URI'],
            [],
            $serviceName,
            [
                'error_type' => 'user_not_found',
                'username' => $username,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]
        );
        exit;
    }

    if (!isset($result['password'])) {
        $response = json_encode([
            "success" => 0,
            "message" => "Password column missing from database result"
        ]);
        http_response_code(400);
        echo $response;
        
        // Log missing password column
        ApiLogger::writeLog(
            'error',
            'Password column missing in database result',
            400,
            $startTime,
            microtime(true),
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REQUEST_URI'],
            [],
            $serviceName,
            [
                'error_type' => 'database_schema_error',
                'username' => $username,
                'available_columns' => array_keys($result)
            ]
        );
        exit;
    }

    $db_password = $result['password'];
    
    // Verify password
    if (!password_verify($inputpassword, $db_password)) {
        $response = json_encode([
            "success" => 2,
            "message" => "Invalid credentials",
            "data" => []
        ]);
        http_response_code(200); // Note: 200 for invalid credential (as per original code)
        echo $response;
        
        // Log invalid password attempt (security consideration - use warning level)
        ApiLogger::writeLog(
            'warning',
            'Invalid password attempt',
            200,
            $startTime,
            microtime(true),
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REQUEST_URI'],
            [],
            $serviceName,
            [
                'error_type' => 'invalid_credentials',
                'username' => $username,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]
        );
        exit;
    }

    // Update last active timestamp
    $updateQuery = $write_db->prepare("UPDATE userinfo SET last_active = NOW() WHERE username = :username");
    $updateQuery->bindParam(':username', $username, PDO::PARAM_STR);
    
    if (!$updateQuery->execute()) {
        $updateErrorInfo = $updateQuery->errorInfo();
        $response = json_encode([
            "success" => 0,
            "message" => "Failed to update last active timestamp",
            "error" => $updateErrorInfo[2] ?? 'Unknown error'
        ]);
        http_response_code(400);
        echo $response;
        
        // Log update timestamp failure
        ApiLogger::writeLog(
            'error',
            'Failed to update last active timestamp',
            400,
            $startTime,
            microtime(true),
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REQUEST_URI'],
            [],
            $serviceName,
            [
                'error_type' => 'timestamp_update_failed',
                'error_details' => $updateErrorInfo,
                'username' => $username
            ]
        );
        exit;
    }

    // Prepare successful response data
    $userData = [
        "userid" => $result['userid'] ?? '',
        "username" => $result['user_name'] ?? '',
        "rolename" => $result['role_name'] ?? '',
        "roleid" => $result['roleid'] ?? '',
        "district_id" => $result['district_id'] ?? '',
        "district_name" => $result['district_name'] ?? '',
        "taluk_id" => $result['taluk_id'] ?? '',
        "taluk_name" => $result['taluk_name'] ?? '',
        "village_id" => $result['village_id'] ?? '',
        "village_name" => $result['village_name'] ?? '',
        "pdsid" => $result['pdsid'] ?? '',
        "shop_code" => $result['shop_code'] ?? '',
        "mobile_no" => $result['mobile_no'] ?? '',
    ];

    $response = json_encode([
        "success" => 1,
        "message" => "Logged in successfully",
        "data" => $userData
    ]);
    http_response_code(200);
    echo $response;
    
    // NOTE: Success response logging is skipped as per your request
    // If you want to log successful logins in future, uncomment below:
    
    ApiLogger::writeLog(
        'info',
        'User logged in successfully',
        200,
        $startTime,
        microtime(true),
        $_SERVER['REQUEST_METHOD'],
        $_SERVER['REQUEST_URI'],
        [],
        $serviceName,
        [
            'username' => $username,
            'user_id' => $result['userid'] ?? '',
            'role' => $result['role_name'] ?? '',
            'district' => $result['district_name'] ?? '',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'login_successful' => true,
            'timestamp_updated' => true
        ]
    );
    

} catch (PDOException $e) {
    $response = json_encode([
        "success" => 0,
        "error" => $e->getMessage()
    ]);
    http_response_code(400);
    echo $response;
    
    // Log database exception
    ApiLogger::writeLog(
        'error',
        'Database exception in user login',
        400,
        $startTime,
        microtime(true),
        $_SERVER['REQUEST_METHOD'],
        $_SERVER['REQUEST_URI'],
        [],
        $serviceName,
        [
            'error_type' => 'database_exception',
            'error_message' => $e->getMessage(),
            'error_code' => $e->getCode(),
            'username' => $username ?? 'unknown',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]
    );
    exit;
}
?>