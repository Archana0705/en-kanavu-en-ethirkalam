<?php 
require_once('../../../helper/header.php');
require_once('../../../helper/log_file.php');
require_once('../../../config/read_database.php');

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

try {
    $id = 2;
    $lastUpdateSql = "SELECT * FROM public.fn_get_cronjob_endtime(:id)";
    $getTime = $read_db->prepare($lastUpdateSql);

    $getTime->bindParam(':id', $id);

    if ($getTime->execute()) {
        $showTime = $getTime->fetchAll(PDO::FETCH_ASSOC);
        http_response_code(200);
        echo json_encode([
            "success" => 1,
            "message" => "Last Updated Time is here",
            "data" => encryptData($showTime),
        ]);
        exit;
    } else {
        // Query execution failed
        $errorInfo = $getTime->errorInfo();
        $response = json_encode([
            "success" => 0,
            "message" => "Query execution failed",
            "error" => $errorInfo[2] ?? 'Unknown database error'
        ]);
        http_response_code(400);
        echo $response;
        
        // Log query execution failure
        ApiLogger::writeLog(
            'error',
            'Failed to execute get_cronjob_endtime query for report',
            400,
            $startTime,
            microtime(true),
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REQUEST_URI'],
            [],
            $serviceName,
            [
                'error_type' => 'query_execution_failed',
                'error_details' => $errorInfo,
                'cronjob_id' => $id,
                'sql_function' => 'fn_get_cronjob_endtime'
            ]
        );
        exit;
    }

} catch (PDOException $e) {
$response = json_encode([
        "success" => 0,
        "message" => "Database Error",
        "error" => $e->getMessage()
    ]);
    http_response_code(400);
    echo $response;
    
    // Log database exception
    ApiLogger::writeLog(
        'error',
        'Database exception in get_cronjob_endtime for report',
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
            'cronjob_id' => $id ?? 'unknown',
            'sql_function' => 'fn_get_cronjob_endtime'
        ]
    );
    exit;
}

?>
