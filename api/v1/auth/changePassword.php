<?php
require_once('../../../helper/header.php');
require_once('../../../helper/log_file.php');
require_once('../../../config/read_database.php');
require_once('../../../config/write_database.php');

$startTime = microtime(true);
$serviceName = 'change_password';

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

$username = $_POST['user'] ?? null;
$userid = $_POST['user_id'] ?? null;
$oldpassword = $_POST['oldPassword'] ?? null;
$newpassword = $_POST['newPassword'] ?? null;
// print_r($_POST);exit;
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

if (empty($oldpassword)) {
    $response = json_encode([
        "success" => 3,
        "message" => "Old Password is required"
    ]);
    http_response_code(400);
    echo $response;
    
    // Log missing old password
    ApiLogger::writeLog(
        'error',
        'Missing old password',
        400,
        $startTime,
        microtime(true),
        $_SERVER['REQUEST_METHOD'],
        $_SERVER['REQUEST_URI'],
        [],
        $serviceName,
        [
            'error_type' => 'missing_parameter',
            'parameter' => 'oldPassword',
            'username' => $username
        ]
    );
    exit;
}

if (empty($newpassword)) {
    $response = json_encode([
        "success" => 3,
        "message" => "New Password is required"
    ]);
    http_response_code(400);
    echo $response;
    
    // Log missing new password
    ApiLogger::writeLog(
        'error',
        'Missing new password',
        400,
        $startTime,
        microtime(true),
        $_SERVER['REQUEST_METHOD'],
        $_SERVER['REQUEST_URI'],
        [],
        $serviceName,
        [
            'error_type' => 'missing_parameter',
            'parameter' => 'newPassword',
            'username' => $username
        ]
    );
    exit;
}

// Validate password strength (optional but recommended)
if (strlen($newpassword) < 8) {
    $response = json_encode([
        "success" => 3,
        "message" => "New password must be at least 8 characters long"
    ]);
    http_response_code(400);
    echo $response;
    
    // Log weak password
    ApiLogger::writeLog(
        'error',
        'Weak new password provided',
        400,
        $startTime,
        microtime(true),
        $_SERVER['REQUEST_METHOD'],
        $_SERVER['REQUEST_URI'],
        [],
        $serviceName,
        [
            'error_type' => 'weak_password',
            'username' => $username,
            'password_length' => strlen($newpassword)
        ]
    );
    exit;
}

try {
    // Check if user exists and verify old password
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
        http_response_code(400);
        echo $response;
        
        // Log user not found
        ApiLogger::writeLog(
            'error',
            'User not found during password change',
            400,
            $startTime,
            microtime(true),
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REQUEST_URI'],
            [],
            $serviceName,
            [
                'error_type' => 'user_not_found',
                'username' => $username
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
    
    // Verify old password
    if (!password_verify($oldpassword, $db_password)) {
        $response = json_encode([
            "success" => 2,
            "message" => "Invalid old password",
            "data" => []
        ]);
        http_response_code(200); // Note: 200 for invalid credential (as per original code)
        echo $response;
        
        ApiLogger::writeLog(
            'warning',
            'Invalid old password provided',
            200,
            $startTime,
            microtime(true),
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REQUEST_URI'],
            [],
            $serviceName,
            [
                'error_type' => 'invalid_old_password',
                'username' => $username
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

    // Change password
    $hashed_pwd = password_hash($newpassword, PASSWORD_BCRYPT);
    $changePwdSql = $write_db->prepare("CALL public.sp_change_password(?, ?, null, null)");
    $changePwdSql->bindParam(1, $userid, PDO::PARAM_INT);
    $changePwdSql->bindParam(2, $hashed_pwd, PDO::PARAM_STR);
    
    if (!$changePwdSql->execute()) {
        $pwdErrorInfo = $changePwdSql->errorInfo();
        $response = json_encode([
            "success" => 0,
            "message" => "Password update failed",
            "error" => $pwdErrorInfo[2] ?? 'Unknown error'
        ]);
        http_response_code(400);
        echo $response;
        
        // Log password change failure
        ApiLogger::writeLog(
            'error',
            'Password change procedure failed',
            400,
            $startTime,
            microtime(true),
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REQUEST_URI'],
            [],
            $serviceName,
            [
                'error_type' => 'password_change_failed',
                'error_details' => $pwdErrorInfo,
                'username' => $username,
                'stored_procedure' => 'sp_change_password'
            ]
        );
        exit;
    }

    // Fetch updated user data
    $sql->execute();
    $updatedResult = $sql->fetch(PDO::FETCH_ASSOC);
    $userDataUpdated = [
        "userid" => $result['userid'] ?? '',
        "username" => $result['user_name'] ?? '',
        "rolename" => $result['role_name'] ?? '',
        "roleid" => $result['roleid'] ?? '',
    ];
    $response = json_encode([
        "success" => 1,
        "message" => "Password changed successfully",
        "data" => encryptData($userDataUpdated),
    ]);
    http_response_code(200);
    echo $response;

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
        'Database exception in password change',
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
            'username' => $username ?? 'unknown'
        ]
    );
    exit;
}
?>