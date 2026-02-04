<?php 
require_once('../../../helper/header.php');
require_once('../../../helper/log_file.php');
require_once('../../../config/read_database.php');

$startTime = microtime(true);
$serviceName = 'download_pdsshop_summary';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    $response = json_encode([
        "error" => "Method Not Allowed"
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

$rawInput = $_POST['data'] ?? null;
if (empty($rawInput)) {
    $response = json_encode([
        "error" => "Missing data parameter"
    ]);
    echo $response;
    
    // Log missing data parameter
    ApiLogger::writeLog(
        'error',
        'Missing data parameter',
        400,
        $startTime,
        microtime(true),
        $_SERVER['REQUEST_METHOD'],
        $_SERVER['REQUEST_URI'],
        [],
        $serviceName,
        ['error_type' => 'missing_data_parameter']
    );
    exit;
}
$decryptData = decryptData($rawInput);
$userid      = $decryptData['user_id'] ?? null;
$district_id = $decryptData['district_id'] ?? 0;
$taluk_id    = $decryptData['taluk_id'] ?? 0;
$village_id  = $decryptData['village_id'] ?? 0;
$pdsshop_id  = $decryptData['pdsshop_id'] ?? 0;

if (empty($userid)) {
    $response = json_encode([
        "error" => "Parameter 'userid' is required"
    ]);
    echo $response;
    
    // Log missing userid parameter
    ApiLogger::writeLog(
        'error',
        'Missing required parameter: userid',
        400,
        $startTime,
        microtime(true),
        $_SERVER['REQUEST_METHOD'],
        $_SERVER['REQUEST_URI'],
        [],
        $serviceName,
        [
            'error_type' => 'missing_parameter',
            'parameter' => 'userid',
            'request_data' => $_POST
        ]
    );
    exit;
}

try {
    // For download, you might want all records without pagination
    $sql = "SELECT * FROM public.fn_get_pdsshop_summary(:districtid, :talukid, :villageid, NULL, NULL, '')";
    $stmt = $read_db->prepare($sql);
    $stmt->bindParam(':districtid', $district_id, PDO::PARAM_INT);
    $stmt->bindParam(':talukid', $taluk_id, PDO::PARAM_INT);
    $stmt->bindParam(':villageid', $village_id, PDO::PARAM_INT);
    
    if (!$stmt->execute()) {
        $errorInfo = $stmt->errorInfo();
        $response = json_encode([
            "error" => "Database query failed",
            "details" => $errorInfo[2] ?? 'Unknown database error'
        ]);
        echo $response;
        
        // Log query execution failure
        ApiLogger::writeLog(
            'error',
            'PDS shop summary query execution failed',
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
                'user_id' => $userid,
                'sql_function' => 'fn_get_pdsshop_summary',
                'filters' => [
                    'district_id' => $district_id,
                    'taluk_id' => $taluk_id,
                    'village_id' => $village_id
                ]
            ]
        );
        exit;
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!$rows || count($rows) === 0) {
        echo json_encode([
            "error" => "No data found",
            "data" => []
        ]);
        exit;
    }

    // For download, just return the raw data without pagination metadata
    echo json_encode([
        "success" => true,
        "data" => encryptData($rows),
        "count" => count($rows)
    ]);

} catch (PDOException $e) {
   $response = json_encode([
        "error" => "Database Error",
        "details" => $e->getMessage()
    ]);
    http_response_code(400);
    echo $response;
    
    // Log database exception
    ApiLogger::writeLog(
        'error',
        'Database exception in PDS shop summary download',
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
            'user_id' => $userid,
            'sql_function' => 'fn_get_pdsshop_summary'
        ]
    );
    exit;
}
?>