<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once('../helper/header.php');
require_once('../helper/db/edm_read.php');
require_once('../vendor/autoload.php');
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

// require_once('../allowed_functions.php');

$logFilePath = '../logs/commonfunction/';
$app_name_log = 'commonfunction';
$service = 'v1/commonfunction';
$request_time = date("Y-m-d H:i:s.u");
$method = $_SERVER['REQUEST_METHOD'];
$endpoint = $_SERVER['PHP_SELF'];
define('DATE_FORMAT', 'Y-m-d H:i:s.u');
// header("Access-Control-Allow-Origin: *");
// header("Access-Control-Allow-Methods: POST");
// header("Content-Type: application/json");


if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}



$jsonData = file_get_contents("php://input");
$json_data = json_decode($jsonData, true) ?? $_POST;

$data = decryptData($json_data['data']);

$filename = 'allowed_functions.json';

if (empty($data['action'])) {
    http_response_code(400);
    echo json_encode(["success" => 0, "message" => "Action and table name are required"]);
    logMessage("error", "Action and table name are required", $logFilePath, 0, 400, $method, $endpoint, $service, $_REQUEST, $_SERVER, $request_time, date(DATE_FORMAT));
    exit;
}

$action = $data['action'] ?? null;
$limit = isset($data['limit']) && $data['limit'] !== null ? max(1, (int)$data['limit']) : null; // Null if not provided
$offset = $limit !== null ? (isset($data['offset']) ? max(0, (int)$data['offset']) : 0) : null; // Only relevant if limit is set
$search = $data['search'] ?? null;
$search_key = $data['search_key'] ?? null;

// $allowedFunctions = json_decode(file_get_contents($filename), true);

switch ($action) {
    case 'function_call':
        $functionName = $data['function_name'] ?? null;
        $params = $data['params'] ?? [];
        $columns = $data['columns'] ?? '*';

        // Validate required parameters
        if (!$functionName) {
            http_response_code(400);
            echo json_encode(["success" => 0, "message" => "Function name is required"]);
            logMessage("error", "Function name is required", $logFilePath, 0, 400, $method, $endpoint, $service, $_REQUEST, $_SERVER, $request_time, date(DATE_FORMAT));
            exit;
        }

        // Optional: Uncomment to restrict allowed functions
        // if (!in_array($functionName, $allowedFunctions['functions'] ?? [])) {
        //     http_response_code(403);
        //     echo json_encode(["success" => 0, "message" => "Function not allowed"]);
        //     exit;
        // }

        // Sanitize function name to prevent SQL injection
        $functionName = preg_replace('/[^a-zA-Z0-9_]/', '', $functionName);

        // Build placeholders for function parameters
        $placeholders = implode(', ', array_map(fn($k) => ':' . $k, array_keys($params)));

        // Build SQL query
        $sql = "SELECT $columns FROM $functionName($placeholders)";
        $whereConditions = [];
        $bindParams = $params;

        // Add search condition if provided
        if ($search !== null && $search_key !== null) {
            // Sanitize search key to prevent SQL injection
            $safe_search_key = preg_replace('/[^a-zA-Z0-9_]/', '', $search_key);
            $whereConditions[] = "$safe_search_key LIKE :search";
            $bindParams['search'] = "%" . $search . "%";
        }

        // Combine conditions
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(' AND ', $whereConditions);
        }

        try {
            // Initialize response
            $response = ["success" => 1];

            // Handle count query for total records
            $countSql = "SELECT COUNT(*) as total FROM $functionName($placeholders)";
            if (!empty($whereConditions)) {
                $countSql .= " WHERE " . implode(' AND ', $whereConditions);
            }
            $countStmt = $read_db->prepare($countSql);
            foreach ($bindParams as $key => $value) {
                $countStmt->bindValue(":$key", $value);
            }
            $countStmt->execute();
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Add pagination only if limit is set
            if ($limit !== null) {
                $sql .= " LIMIT :limit OFFSET :offset";
                $bindParams['limit'] = $limit;
                $bindParams['offset'] = $offset;
            }

            // Execute main query
            $stmt = $read_db->prepare($sql);
            foreach ($bindParams as $key => $value) {
                $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue(":$key", $value, $paramType);
            }
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Build response
            $response["data"] = encrypt($result);
            if ($limit !== null) {
                $response["pagination"] = [
                    "total" => (int)$totalCount,
                    "limit" => $limit,
                    "offset" => $offset,
                    "total_pages" => ceil($totalCount / $limit),
                    "current_page" => floor($offset / $limit) + 1
                ];
            } else {
                $response["pagination"] = [
                    "total" => (int)$totalCount
                ];
            }

            if (empty($result)) {
                $response["success"] = 0;
                $response["message"] = "Data not found";
                http_response_code(200); // 200 for empty results
            }

            $response["message"] = "Data found";
            echo json_encode($response);
            logMessage("success", $response['message'], $logFilePath, 0, 200, $method, $endpoint, $service, $_REQUEST, $_SERVER, $request_time, date(DATE_FORMAT));
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => 0, "message" => "Error: " . $e->getMessage()]);
            logMessage("error", "Error: " . $e->getMessage(), $logFilePath, 0, 500, $method, $endpoint, $service, $_REQUEST, $_SERVER, $request_time, date(DATE_FORMAT));
        }
        break;

    case 'procedure_call':
    $procedureName = $data['procedure_name'] ?? null;
    $params = $data['params'] ?? [];

    if (!$procedureName) {
        http_response_code(400);
        echo json_encode(["success" => 0, "message" => "Procedure name is required"]);
        logMessage("error", "Procedure name is required", $logFilePath, 0, 400, $method, $endpoint, $service, $_REQUEST, $_SERVER, $request_time, date(DATE_FORMAT));
        exit;
    }

    $placeholders = implode(', ', array_map(fn($k) => ':' . $k, array_keys($params)));
    $sql = "CALL $procedureName($placeholders)";

    try {
        $stmt = $read_db->prepare($sql);
       foreach ($params as $key => $value) {
    if (is_array($value)) {
        // Convert PHP array to JSON string for PostgreSQL JSON input
        $value = json_encode($value, JSON_UNESCAPED_UNICODE);
    }
    $stmt->bindValue(":$key", $value);
}

        $stmt->execute();
         $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($result)) {
                http_response_code(404);
                echo json_encode(["success" => 0, "message" => "Data not found"]);
                logMessage("error",  "Data not found", $logFilePath, 0, 404, $method, $endpoint, $service, $_REQUEST, $_SERVER, $request_time, date(DATE_FORMAT));
                exit;
            }

    
        http_response_code(200);
        echo json_encode(["success" => 1, "message" => "Procedure executed successfully"]);
        logMessage("success", "Procedure executed successfully", $logFilePath, 0, 400, $method, $endpoint, $service, $_REQUEST, $_SERVER, $request_time, date(DATE_FORMAT));
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => 0, "message" => "Error: " . $e->getMessage()]);
        logMessage("error", "Error: " . $e->getMessage(), $logFilePath, 0, 500, $method, $endpoint, $service, $_REQUEST, $_SERVER, $request_time, date(DATE_FORMAT));
    }
    break;


    default:
        http_response_code(400);
        echo json_encode(["success" => 0, "message" => "Invalid action"]);
        logMessage("error", "Invalid action", $logFilePath, 0, 400, $method, $endpoint, $service, $_REQUEST, $_SERVER, $request_time, date(DATE_FORMAT));
}
function logMessage($level, $message, $logDirPath, $status_code, $response_status_code, $method, $endpoint, $service, $request_parameters, $request_headers, $request_time, $response_time) {
    try {
        $logDetails = [
            'status' => $status_code,
            'response_code' => $response_status_code,
            'method' => $method,
            'endpoint' => $endpoint,
            'service' => $service,
            'request_parameters' => $request_parameters,
            'request_headers' => $request_headers,
            'request_time' => $request_time,
            'response_time' => $response_time
        ];

        // Ensure log directory exists
        if (!is_dir($logDirPath)) {
            mkdir($logDirPath, 0755, true);
        }

        // Create logger
        $log = new Logger('api');

        // Create formatter
        $lineFormatter = new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message%\n%context%\n%extra%\n" .
            "=======================================================================================================\n",
            'Y-m-d H:i:s',
            true,
            true
        );

        // Log file path
        $logFileName = rtrim($logDirPath, '/') . '/' . date('Y-m-d') . '.log';

        // Create stream handler
        $streamHandler = new StreamHandler($logFileName, Logger::DEBUG);
        $streamHandler->setFormatter($lineFormatter);
        $log->pushHandler($streamHandler);

        // Log by level
        switch (strtolower($level)) {
            case 'error':
                $log->error($message, $logDetails);
                break;
            case 'warning':
                $log->warning($message, $logDetails);
                break;
            case 'info':
            default:
                $log->info($message, $logDetails);
                break;
        }
    } catch (Exception $e) {
        error_log("[" . date('Y-m-d H:i:s') . "] Logging error: " . $e->getMessage() . "\n");
    }
}