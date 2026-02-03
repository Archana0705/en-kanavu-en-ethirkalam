<?php


require_once('../helper/header.php');
require_once('../helper/db/edm_read.php');

header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

$jsonData = file_get_contents("php://input");
$json_data = json_decode($jsonData, true) ?? $_POST;

$data = decryptData($json_data['data']);

if (empty($data['action']) || empty($data['table'])) {
    http_response_code(400);
    echo json_encode(["success" => 0, "message" => "Action and table name are required"]);
    exit;
}



$action = $data['action'];
$table = $data['table'];
$primaryKey = $data['primary_key'] ?? 'id'; // Default primary key is 'id'


switch ($action) {
    case 'fetch':
        $filters = $data['filters'] ?? []; // Optional filters for WHERE clause
        $columns = $data['columns'] ?? '*'; // Optional columns to select

        $sql = "SELECT $columns FROM $table";

        if (!empty($filters)) {
            $whereClauses = [];
            foreach ($filters as $key => $value) {
                $whereClauses[] = "$key = :$key";
            }
            $sql .= " WHERE " . implode(" AND ", $whereClauses);
        }

        try {
            $stmt = $read_db->prepare($sql);
            foreach ($filters as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // print_R($result);
            $result = array_map(function ($row) {
                return array_combine(
                    array_map('trim', array_keys($row)),
                    array_values($row)
                );
            }, $result);
            // print_r($result);

            // Correct JSON response:

            echo json_encode(["success" => 1, "data" => encrypt($result)]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => 0, "message" => "Internal server error" . $e->getMessage()]);
        }
        break;

    case 'insert':
        $targetDir = "uploads/{$table}/";

        // Ensure the directory exists
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        // Handle file upload if present
        if (!empty($_FILES["file"])) {
            $fileName = basename($_FILES["file"]["name"]);
            $targetFilePath = $targetDir . $fileName;
            $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

            // Allowed file formats
            $allowedFormats = ['mp4', 'avi', 'mov', 'mkv', 'jpg', 'jpeg', 'png', 'pdf'];
            $maxFileSize = 50 * 1024 * 1024; // 50 MB

            // Validate file size
            if ($_FILES["file"]["size"] > $maxFileSize) {
                echo json_encode(["success" => 0, "message" => "Error: File size exceeds the maximum limit of 50MB."]);
                exit;
            }

            // Validate MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $_FILES["file"]["tmp_name"]);
            finfo_close($finfo);

            if (!in_array($fileType, $allowedFormats)) {
                echo json_encode(["success" => 0, "message" => "Invalid file format. Only MP4, AVI, MOV, MKV, JPG, JPEG, PNG, and PDF are allowed."]);
                exit;
            }

            // Move the uploaded file to the target directory
            if (!move_uploaded_file($_FILES["file"]["tmp_name"], $targetFilePath)) {
                echo json_encode(["success" => 0, "message" => "Error uploading file."]);
                exit;
            }

            // Add the file path to the data array
            $data['file_attachment_name'] = $targetFilePath;
            $data['mime_type'] = $mimeType;
        }

        // Prepare the INSERT query
        $columns = implode(", ", array_keys($data));
        $placeholders = ":" . implode(", :", array_keys($data));
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";

        try {
            $stmt = $read_db->prepare($sql);
            foreach ($data as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            if ($stmt->execute()) {
                http_response_code(201);
                echo json_encode(["success" => 1, "message" => "Data inserted successfully"]);
            } else {
                http_response_code(500);
                echo json_encode(["success" => 0, "message" => "Database execution failed"]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => 0, "message" => "Internal server error"]);
        }
        break;

  case 'update':
    if (empty($data[$primaryKey])) {
        http_response_code(400);
        echo json_encode(["success" => 0, "message" => "Primary key value is required"]);
        exit;
    }

    $setClauses = [];
    foreach ($data as $key => $value) {
        if (!in_array($key, ['action', 'table', 'primary_key']) && $key != $primaryKey) {
            $setClauses[] = "$key = :$key";
        }
    }

    $sql = "UPDATE $table SET " . implode(", ", $setClauses) . " WHERE $primaryKey = :pk_value";


    try {
        $stmt = $read_db->prepare($sql);

        // Bind all SET values
        foreach ($data as $key => $value) {
            if (!in_array($key, ['action', 'table', 'primary_key']) && $key != $primaryKey) {
                $stmt->bindValue(":$key", $value);
            }
        }
        
        // Bind primary key separately
        $stmt->bindValue(":pk_value", $data[$primaryKey]);

        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode(["success" => 1, "message" => "Data updated successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["success" => 0, "message" => "Database execution failed"]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => 0, "message" => "Internal server error", "error" => $e->getMessage()]);
    }
    break;

    case 'delete':
        if (empty($data[$primaryKey])) {
            http_response_code(400);
            echo json_encode(["success" => 0, "message" => "Primary key is required"]);
            exit;
        }

        $sql = "DELETE FROM $table WHERE $primaryKey = :$primaryKey";

        try {
            $stmt = $read_db->prepare($sql);
            $stmt->bindValue(":$primaryKey", $data[$primaryKey]);
            if ($stmt->execute()) {
                http_response_code(200);
                echo json_encode(["success" => 1, "message" => "Data deleted successfully"]);
            } else {
                http_response_code(500);
                echo json_encode(["success" => 0, "message" => "Database execution failed"]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => 0, "message" => "Internal server error"]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(["success" => 0, "message" => "Invalid action"]);
}
