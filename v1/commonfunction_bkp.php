<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once('../helper/header.php');
require_once('../helper/db/edm_read.php');

// require_once('../allowed_functions.php');

$logFilePath = '../../logs/get_universities_details/';
$app_name_log = 'SSP get_universities_details';
$service = 'v1/get_universities_details';
$resquest_time = date("Y-m-d H:i:s.u");
$method = $_SERVER['REQUEST_METHOD'];
$endpoint = $_SERVER['PHP_SELF'];

header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");





$jsonData = file_get_contents("php://input");
$json_data = json_decode($jsonData, true) ?? $_POST;

$data = decryptData($json_data['data']);

$filename = 'allowed_functions.json';


if (empty($data['action'])) {
    http_response_code(400);
    echo json_encode(["success" => 0, "message" => "Action and table name are required"]);
    exit;
}



// $allowedFunctions = json_decode(file_get_contents($filename), true);


$action = $data['action'];
$primaryKey = $data['primary_key'] ?? 'id'; // Default primary key is 'id'

switch ($action) {


    case 'function_call':
        $functionName = $data['function_name'] ?? null;
        $params = $data['params'] ?? [];

        $columns = $data['columns'] ?? '*';

        if (!$functionName) {
            http_response_code(400);
            echo json_encode(["success" => 0, "message" => "Function name is required"]);
            exit;
        }

        // if (!in_array($functionName, $allowedFunctions['functions'] ?? [])) {
        //     http_response_code(403);
        //     echo json_encode(["success" => 0, "message" => "Function not allowed"]);
        //     exit;
        // }

        

        $placeholders = implode(', ', array_map(fn($k) => ':' . $k, array_keys($params)));


        $sql = "SELECT $columns FROM $functionName($placeholders)";


        try {
            $stmt = $read_db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($result)) {
                http_response_code(404);
                echo json_encode(["success" => 0, "message" => "Data not found"]);
                exit;
            }
            echo json_encode(["success" => 1, "data" => encrypt($result)]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => 0, "message" => "Error: " . $e->getMessage()]);
        }
        break;



    case 'procedure_call':
    $procedureName = $data['procedure_name'] ?? null;
    $params = $data['params'] ?? [];

    if (!$procedureName) {
        http_response_code(400);
        echo json_encode(["success" => 0, "message" => "Procedure name is required"]);
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
                exit;
            }

    
        http_response_code(200);
        echo json_encode(["success" => 1, "message" => "Procedure executed successfully"]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => 0, "message" => "Error: " . $e->getMessage()]);
    }
    break;


    default:
        http_response_code(400);
        echo json_encode(["success" => 0, "message" => "Invalid action"]);
}
