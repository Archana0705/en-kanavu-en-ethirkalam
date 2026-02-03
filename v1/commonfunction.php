<?php
require_once('../helper/header.php');
require_once('../helper/db/edm_read.php');
require_once('../vendor/autoload.php');
// require_once('allowed_function.json');

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

$logFilePath = '../logs/commonfunction/';
$service = 'v1/commonfunction';
$request_time = date("Y-m-d H:i:s.u");
$method = $_SERVER['REQUEST_METHOD'];
$endpoint = $_SERVER['PHP_SELF'];
define('DATE_FORMAT', 'Y-m-d H:i:s.u');

// ------------------------- CORS -------------------------
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-App-Key, X-App-Name");
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}


 function logMessage($level, $message, $logDirPath, $status_code, $response_status_code, $method, $endpoint, $service, $request_parameters, $request_headers, $request_time, $response_time)
    {
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

            if (!is_dir($logDirPath)) {
                mkdir($logDirPath, 0755, true);
            }

            $log = new Logger('api');
            $lineFormatter = new LineFormatter(
                "[%datetime%] %channel%.%level_name%: %message%\n%context%\n%extra%\n" .
                "=======================================================================================================\n",
                'Y-m-d H:i:s',
                true,
                true
            );

            $logFileName = rtrim($logDirPath, '/') . '/' . date('Y-m-d') . '.log';
            $streamHandler = new StreamHandler($logFileName, Logger::DEBUG);
            $streamHandler->setFormatter($lineFormatter);
            $log->pushHandler($streamHandler);

            switch (strtolower($level)) {
                case 'error':
                    $log->error($message, $logDetails);
                    break;
                case 'warning':
                    $log->warning($message, $logDetails);
                    break;
                default:
                    $log->info($message, $logDetails);
                    break;
            }
        } catch (Exception $e) {
            error_log("[" . date('Y-m-d H:i:s') . "] Logging error: " . $e->getMessage() . "\n");
        }
    }

try {



    // -------------------- Helpers: Sanitization --------------------
// XSS-safe output sanitizer
    function sanitizeOutput($data)
    {
        if (is_string($data)) {
            return htmlspecialchars($data, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8');
        }
        if (is_array($data)) {
            return array_map('sanitizeOutput', $data);
        }
        if (is_object($data)) {
            return (object) array_map('sanitizeOutput', (array) $data);
        }
        return $data;
    }
    // Universal input sanitizer — recursive and strict
    function sanitizeInput($input)
    {
        if (is_string($input)) {
            $input = trim($input);
            // remove HTML tags
            $input = strip_tags($input);
            // encode special chars (prevents XSS, control characters remain safe)
            $input = htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8');
            return $input;
        } elseif (is_array($input)) {
            $out = [];
            foreach ($input as $k => $v) {
                $out[$k] = sanitizeInput($v);
            }
            return $out;
        } elseif (is_object($input)) {
            return (object) sanitizeInput((array) $input);
        }
        return $input;
    }

    // Basic whitelist sanitizer for DB identifiers (functions, columns, etc.)
    function sanitizeIdentifier($value)
    {
        if (!is_string($value))
            return '';
        // Allow only letters, numbers and underscores and limit length to avoid abuse
        $clean = preg_replace('/[^a-zA-Z0-9_]/', '', $value);
        return substr($clean, 0, 128);
    }

    // Escape user-supplied LIKE pattern (so % and _ are treated literally unless intended)
    function escapeLike($str)
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $str);
    }

    // -------------------- Request size limit (1MB) --------------------
    $jsonData = file_get_contents("php://input");
    if ($jsonData !== false && strlen($jsonData) > 1024 * 1024) {
        http_response_code(413);
        echo json_encode(["success" => 0, "message" => "Payload too large"]);
        logMessage("error", "Payload too large", $logFilePath, 0, 413, $method, $endpoint, $service, $_REQUEST, $_SERVER, $request_time, date(DATE_FORMAT));
        exit;
    }

    // Accept raw JSON body or fallback to form POST
    $raw = json_decode($jsonData, true);
    $json_data = $raw ?? $_POST;

    // decryptData() is assumed available in your included helpers
    $data = null;
    if (!empty($json_data) && isset($json_data['data'])) {
        $data = decryptData($json_data['data']);
    } else {
        // If caller sent non-encrypted body directly
        $data = $json_data;
    }

    // Validate decrypted payload
    if ($data === null || !is_array($data)) {
        http_response_code(400);
        echo json_encode(["success" => 0, "message" => "Invalid or missing data"]);
        logMessage("error", "Invalid or missing data", $logFilePath, 0, 400, $method, $endpoint, $service, $_REQUEST, $_SERVER, $request_time, date(DATE_FORMAT));
        exit;
    }

    // -------------------- Sanitize entire payload --------------------
    $data = sanitizeInput($data);

    // Optional: load whitelist files (recommended for production)
// $filename = 'allowed_function.json';
// if (file_exists($filename)) {
//     $allowedFunctions = json_decode(file_get_contents($filename), true);
// }

    // -------------------- Pagination & search sanitization --------------------
    $limit = isset($data['limit']) && is_numeric($data['limit']) ? max(1, (int) $data['limit']) : null;
    $offset = $limit !== null ? (isset($data['offset']) && is_numeric($data['offset']) ? max(0, (int) $data['offset']) : 0) : null;

    $search = isset($data['search']) ? $data['search'] : null;
    $search_key = isset($data['search_key']) ? $data['search_key'] : null;

    $action = $data['action'] ?? null;
    if (empty($action)) {
        http_response_code(400);
        echo json_encode(["success" => 0, "message" => "Action is required"]);
        logMessage("error", "Action is required", $logFilePath, 0, 400, $method, $endpoint, $service, $_REQUEST, $_SERVER, $request_time, date(DATE_FORMAT));
        exit;
    }

    switch ($action) {
        case 'function_call':
            session_start();

           


            $functionName = $data['function_name'] ?? null;
            $params = $data['params'] ?? [];
            $columns = $data['columns'] ?? '*';

            if (!$functionName) {

                echo json_encode(["success" => 0, "message" => "Function name is required"]);
                logMessage("error", "Function name is required", $logFilePath, 0, 400, $method, $endpoint, $service, $_REQUEST, $_SERVER, $request_time, date(DATE_FORMAT));
                exit;
            }

            // Enforce optional whitelist (uncomment for production)
            // if (!empty($allowedFunctions) && !in_array($functionName, $allowedFunctions['functions'] ?? [], true)) {
            //     http_response_code(403);
            //     echo json_encode(["success" => 0, "message" => "Function not allowed"]);
            //     logMessage("error", "Forbidden function: $functionName", $logFilePath, 0, 403, $method, $endpoint, $service, $_REQUEST, $_SERVER, $request_time, date(DATE_FORMAT));
            //     exit;
            // }

            // Sanitize identifiers
            $functionName = sanitizeIdentifier($functionName);

            // Sanitize columns list
            if ($columns !== '*') {
                $cols = array_filter(array_map('trim', explode(',', $columns)));
                $safeCols = [];
                foreach ($cols as $col) {
                    $c = sanitizeIdentifier($col);
                    if ($c !== '')
                        $safeCols[] = $c;
                }
                $columns = !empty($safeCols) ? implode(', ', $safeCols) : '*';
            }

            // Sanitize params (keys and values)
            $cleanParams = [];
            foreach ($params as $key => $value) {
                $cleanKey = sanitizeIdentifier($key);
                if ($cleanKey !== '') {
                    // If array, encode as JSON string for passing to DB safely
                    $cleanParams[$cleanKey] = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
                }
            }
            $params = $cleanParams;

            // Build placeholders
            $placeholders = '';
            if (!empty($params)) {
                $placeholders = implode(', ', array_map(fn($k) => ":$k", array_keys($params)));
            }

            // Build base SQL (calling a set-returning function)
            $sql = "SELECT $columns FROM $functionName(" . $placeholders . ")";
            $countSql = "SELECT COUNT(*) as total FROM $functionName(" . $placeholders . ")";

            $whereConditions = [];
            $bindParams = $params; // copy

            // Safe search handling
            if ($search !== null && $search_key !== null && $search !== '') {
                $safe_search_key = sanitizeIdentifier($search_key);
                if ($safe_search_key !== '') {
                    // escape % and _ so client can't trivially wildcard abuse
                    $escaped = escapeLike($search);
                    $whereConditions[] = "$safe_search_key ILIKE :search"; // case-insensitive
                    $bindParams['search'] = "%" . $escaped . "%";
                }
            }

            if (!empty($whereConditions)) {
                $whereClause = " WHERE " . implode(' AND ', $whereConditions);
                $sql .= $whereClause;
                $countSql .= $whereClause;
            }

            try {
                // Prepare and execute count
                $countStmt = $read_db->prepare($countSql);

                foreach ($bindParams as $k => $v) {
                    // PDO bind values - treat ints as ints
                    if (is_int($v)) {
                        $countStmt->bindValue(":$k", $v, PDO::PARAM_INT);
                    } else {
                        $countStmt->bindValue(":$k", $v, PDO::PARAM_STR);
                    }
                }
                $countStmt->execute();
                $totalCountRow = $countStmt->fetch(PDO::FETCH_ASSOC);
                $totalCount = $totalCountRow ? (int) $totalCountRow['total'] : 0;

                // Add pagination if requested
                if ($limit !== null) {
                    $sql .= " LIMIT :limit OFFSET :offset";
                    $bindParams['limit'] = $limit;
                    $bindParams['offset'] = $offset;
                }

                // Main query
                $stmt = $read_db->prepare($sql);
                foreach ($bindParams as $k => $v) {
                    // Do not let PDO try to bind arrays
                    if (is_int($v)) {
                        $stmt->bindValue(":$k", $v, PDO::PARAM_INT);
                    } else {
                        $stmt->bindValue(":$k", $v, PDO::PARAM_STR);
                    }
                }
                $stmt->execute();
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Sanitize output before encrypting/returning
                // $result = sanitizeOutput($result);

                $response = ["success" => 1, "message" => "Data found", "data" => encrypt($result)];
                if ($limit !== null) {
                    $response["pagination"] = [
                        "total" => $totalCount,
                        "limit" => $limit,
                        "offset" => $offset,
                        "total_pages" => $limit > 0 ? (int) ceil($totalCount / $limit) : 0,
                        "current_page" => $limit > 0 ? (int) floor($offset / $limit) + 1 : 1
                    ];
                } else {
                    $response["pagination"] = ["total" => $totalCount];
                }

                if (empty($result)) {
                    $response["success"] = 0;
                    $response["message"] = "Data not found";
                    unset($response['data']);
                }

                echo json_encode($response);
                logMessage("success", $response['message'], $logFilePath, 1, 200, $method, $endpoint, $service, $_REQUEST, $_SERVER, $request_time, date(DATE_FORMAT));

            } catch (Exception $e) {
                http_response_code(500);
                // Don't leak internal SQL to client. Log full error instead.
                echo json_encode(["success" => 0, "message" => "Database error"]);
                logMessage("error", "Query failed: " . $e->getMessage(), $logFilePath, 0, 500, $method, $endpoint, $service, $_REQUEST, $_SERVER, $request_time, date(DATE_FORMAT));
            }
            break;

        case 'procedure_call':

            session_start();

            try {
                if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
                    throw new Exception("Unauthorized access. Please log in again.", 401);
                }

                

            } catch (Exception $e) {
                http_response_code($e->getCode() ?: 500);

                echo json_encode([
                    "success" => 0,
                    "message" => $e->getMessage(),
                    "error_code" => $e->getCode()
                ]);

                exit;

                // Optional: log the error
                // logMessage("error", $e->getMessage(), $logFilePath, 0, $e->getCode(), $method, $endpoint, $service, $_REQUEST, $_SERVER, $request_time, date(DATE_FORMAT));
            }
            $procedureName = $data['procedure_name'] ?? null;
            $params = $data['params'] ?? [];

            if (!$procedureName) {
                http_response_code(400);
                echo json_encode(["success" => 0, "message" => "Procedure name is required"]);
                logMessage("error", "Procedure name is required", $logFilePath, 0, 400, $method, $endpoint, $service, $_REQUEST, $_SERVER, $request_time, date(DATE_FORMAT));
                exit;
            }

            // Optional whitelist check (recommended)
            // if (!empty($allowedFunctions) && !in_array($procedureName, $allowedFunctions['procedures'] ?? [], true)) {
            //     http_response_code(403);
            //     echo json_encode(["success" => 0, "message" => "Procedure not allowed"]);
            //     logMessage("error", "Forbidden procedure: $procedureName", $logFilePath, 0, 403, $method, $endpoint, $service, $_REQUEST, $_SERVER, $request_time, date(DATE_FORMAT));
            //     exit;
            // }

            $procedureName = sanitizeIdentifier($procedureName);

            // Sanitize params
            $cleanParams = [];
            foreach ($params as $key => $value) {
                $cleanKey = sanitizeIdentifier($key);
                if ($cleanKey !== '') {
                    $cleanParams[$cleanKey] = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
                }
            }
            $params = $cleanParams;

            $placeholders = '';
            if (!empty($params)) {
                $placeholders = implode(', ', array_map(fn($k) => ":$k", array_keys($params)));
            }
            $sql = "CALL $procedureName(" . $placeholders . ")";

            try {
                $stmt = $read_db->prepare($sql);
                foreach ($params as $k => $v) {
                    if (is_int($v)) {
                        $stmt->bindValue(":$k", $v, PDO::PARAM_INT);
                    } else {
                        $stmt->bindValue(":$k", $v, PDO::PARAM_STR);
                    }
                }
                $stmt->execute();

                // Try to fetch results if any (some drivers return after CALL)
                $result = [];
                try {
                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $_e) {
                    // no fetchable result — ignore
                }

                $result = sanitizeOutput($result);

                $response = ["success" => 1, "message" => "Procedure executed successfully"];
                if (!empty($result)) {
                    $response["data"] = encrypt($result);
                }

                echo json_encode($response);
                logMessage("success", $response['message'], $logFilePath, 1, 200, $method, $endpoint, $service, $_REQUEST, $_SERVER, $request_time, date(DATE_FORMAT));

            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(["success" => 0, "message" => "Procedure error"]);
                logMessage("error", "Procedure failed: " . $e->getMessage(), $logFilePath, 0, 500, $method, $endpoint, $service, $_REQUEST, $_SERVER, $request_time, date(DATE_FORMAT));
            }
            break;

        case 'logout':
            try {
                // Example: if using JWT or token-based authentication
                // If you are storing active tokens in DB or Redis, invalidate here
                // Example: $read_db->prepare("UPDATE user_tokens SET valid = false WHERE token = :token")->execute(['token' => $token]);

                // Or simply destroy PHP session if using session-based auth
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                session_unset();
                session_destroy();

                $response = ["success" => 1, "message" => "Logged out successfully"];
                echo json_encode($response);
                logMessage("success", "User logged out successfully", $logFilePath, 1, 200, $method, $endpoint, $service, $_REQUEST, $_SERVER, $request_time, date(DATE_FORMAT));

            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(["success" => 0, "message" => "Logout failed"]);
                logMessage("error", "Logout failed: " . $e->getMessage(), $logFilePath, 0, 500, $method, $endpoint, $service, $_REQUEST, $_SERVER, $request_time, date(DATE_FORMAT));
            }
            break;


        case 'login':


            // session_start();
            // print_r($_SESSION);
            // die();

            try {
                $functionName = 'IM_USER_LOGIN_FN';
                $params = $data['params'] ?? [];

                if (empty($params['mobnumber'])) {
                    http_response_code(400);
                    echo json_encode(["success" => 0, "message" => "Mobile number is required"]);
                    logMessage("error", "Mobile number is required", $logFilePath, 0, 400, $method, $endpoint, $service, $_REQUEST, $_SERVER, $request_time, date(DATE_FORMAT));
                    exit;
                }

                // Sanitize
                $mobnumber = preg_replace('/[^0-9]/', '', $params['mobnumber']);

                // Prepare SQL (PostgreSQL function call)
                $sql = "SELECT * FROM $functionName(:mobnumber)";
                $stmt = $read_db->prepare($sql);
                $stmt->bindValue(':mobnumber', $mobnumber, PDO::PARAM_STR);
                $stmt->execute();
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($result)) {
                    http_response_code(404);
                    echo json_encode(["success" => 0, "message" => "Invalid mobile number or user not found"]);
                    logMessage("error", "User not found for $mobnumber", $logFilePath, 0, 404, $method, $endpoint, $service, $_REQUEST, $_SERVER, $request_time, date(DATE_FORMAT));
                    exit;
                }

                // Sanitize output before encrypting
                $result = sanitizeOutput($result);

                // Start PHP session
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }

                // Store user info in session (assuming only one row is returned)
                $user = $result[0];
                $_SESSION['mobnumber'] = $mobnumber;
                $_SESSION['login_type'] = $user['login_type'] ?? '';
                $_SESSION['district'] = $user['district'] ?? '';
                $_SESSION['institution_name'] = $user['name_of_the_institution'] ?? '';
                $_SESSION['system'] = $user['system'] ?? '';
                $_SESSION['name'] = $user['name'] ?? '';
                $_SESSION['institution'] = $user['institution'] ?? '';
                $_SESSION['designation'] = $user['designation'] ?? '';
                $_SESSION['logged_in'] = true;
                $_SESSION['login_time'] = date('Y-m-d H:i:s');

                // Encrypt result before sending
                $response = [
                    "success" => 1,
                    "message" => "Login successful",
                    "session_id" => session_id(),
                    "data" => encrypt($result)
                ];

                echo json_encode($response);
                logMessage("success", "Login successful for $mobnumber", $logFilePath, 1, 200, $method, $endpoint, $service, $_REQUEST, $_SERVER, $request_time, date(DATE_FORMAT));

            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(["success" => 0, "message" => "Login failed"]);
                logMessage("error", "Login failed: " . $e->getMessage(), $logFilePath, 0, 500, $method, $endpoint, $service, $_REQUEST, $_SERVER, $request_time, date(DATE_FORMAT));
            }
            break;



        default:
            http_response_code(400);
            echo json_encode(["success" => 0, "message" => "Invalid action"]);
            logMessage("error", "Invalid action", $logFilePath, 0, 400, $method, $endpoint, $service, $_REQUEST, $_SERVER, $request_time, date(DATE_FORMAT));
            break;
    }

    // -------------------- Logging helper --------------------
   
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => 0, 'message' => 'An error occurred: ' . $e->getMessage()]);
    
}

?>