<?php 

require_once('../../../helper/header.php');
require_once('../../../helper/log_file.php');
require_once('../../../config/read_database.php');

$startTime = microtime(true);
$serviceName = 'pdsshop_summary_list';

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
;if (!$decryptData || !is_array($decryptData)) {
    $response = json_encode([
        "error" => "Invalid or corrupted data"
    ]);
    echo $response;
    
    // Log invalid data
    ApiLogger::writeLog(
        'error',
        'Invalid or corrupted data',
        400,
        $startTime,
        microtime(true),
        $_SERVER['REQUEST_METHOD'],
        $_SERVER['REQUEST_URI'],
        [],
        $serviceName,
        ['error_type' => 'invalid_data']
    );
    exit;
}
$userid      = $decryptData['user_id'] ?? null;
$district_id = $decryptData['district_id'] ?? 0;
$taluk_id    = $decryptData['taluk_id'] ?? 0;
$village_id  = $decryptData['village_id'] ?? 0;
$pdsshop_id  = $decryptData['pdsshop_id'] ?? 0;
// print_r($_POST);exit;

$start       = $decryptData['start'] ?? 0;
$length      = $decryptData['length'] ?? 50;
$searchValue = $decryptData['search_value'] ?? '';
$draw        = intval($decryptData['draw'] ?? 1);

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

    // MAIN data query
    $sql = "SELECT * FROM public.fn_get_pdsshop_summary(:districtid, :talukid, :villageid, :limits, :offset, :searchvalue)";
    $stmt = $read_db->prepare($sql);
    $stmt->bindParam(':districtid', $district_id, PDO::PARAM_INT);
    $stmt->bindParam(':talukid', $taluk_id, PDO::PARAM_INT);
    $stmt->bindParam(':villageid', $village_id, PDO::PARAM_INT);
    $stmt->bindParam(':limits', $length, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $start, PDO::PARAM_INT);
    $stmt->bindParam(':searchvalue', $searchValue, PDO::PARAM_STR);

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
                ],
                'pagination' => [
                    'start' => $start,
                    'length' => $length,
                    'search_value' => $searchValue
                ]
            ]
        );
        exit;
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // $count = $stmt->rowCount();
    // Extract total_count from first row (if function returns it)
    $recordsTotal = 0;
    if (isset($rows[0]['total_count'])) {
        $recordsTotal = intval($rows[0]['total_count']);
    }

    // If DB does not return total_count → run a count query
    if ($recordsTotal == 0) {
        $countSql = "SELECT COUNT(*) AS total FROM public.fn_get_pdsshop_summary(:districtid, :talukid, :villageid, NULL, NULL, :searchvalue)";
        $countStmt = $read_db->prepare($countSql);
        $countStmt->bindParam(':districtid', $district_id, PDO::PARAM_INT);
        $countStmt->bindParam(':talukid', $taluk_id, PDO::PARAM_INT);
        $countStmt->bindParam(':villageid', $village_id, PDO::PARAM_INT);
        $countStmt->bindParam(':searchvalue', $searchValue, PDO::PARAM_STR);

        if (!$countStmt->execute()) {
            $countErrorInfo = $countStmt->errorInfo();
            $response = json_encode([
                "error" => "Count query failed",
                "details" => $countErrorInfo[2] ?? 'Unknown error'
            ]);
            echo $response;
            
            // Log count query failure
            ApiLogger::writeLog(
                'error',
                'PDS shop summary count query failed',
                400,
                $startTime,
                microtime(true),
                $_SERVER['REQUEST_METHOD'],
                $_SERVER['REQUEST_URI'],
                [],
                $serviceName,
                [
                    'error_type' => 'count_query_failed',
                    'error_details' => $countErrorInfo,
                    'user_id' => $userid,
                    'sql_function' => 'fn_get_pdsshop_summary'
                ]
            );
            exit;
        }
        $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
        $recordsTotal = intval($countResult['total']);
    }

    echo json_encode([
        "draw"            => $draw,
        "recordsTotal"    => $recordsTotal,
        "recordsFiltered" => $recordsTotal,
        "data"            => encryptData($rows),
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
        'Database exception in PDS shop summary list',
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
