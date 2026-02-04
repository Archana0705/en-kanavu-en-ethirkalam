<?php
require_once('../../../helper/header.php');
require_once('../../../helper/log_file.php');
require_once('../../../config/read_database.php');

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    echo json_encode([
        "success" => 3,
        "message" => "Method Not Allowed"
    ]);
    exit;
}

 try {
    $rawInput = $_POST['data'] ?? null;
    $decryptData = decryptData($rawInput);
    $userid = $decryptData['user_id'] ?? null;

    if(empty($userid)){
        http_response_code(400);
        echo json_encode([
            "success" => 0,
            "message" => "User ID is required",
        ]);
        exit;
    }
    $statusParams = ["Pending", "Completed", "Closed", "Rejected"];
    $statusValues = [-1, 1, 2, 3];
    
    // Create combined array
    $combinedStatus = [];
    foreach($statusParams as $index => $param) {
        $combinedStatus[] = [
            "id" => $statusValues[$index],
            "status" => ucfirst($param)
        ];
    }

    http_response_code(200);
    echo json_encode([
        "success" => 1,
        "message" => "Dropdown list loaded successfully",
        "data" => encryptData($combinedStatus),
        "params_count" => count($combinedStatus)
    ]);
    exit;

} catch (PDOException $e) {
    http_response_code(400);
    echo json_encode([
        "success" => 0,
        "message" => "Database Error",
    ]);
    exit;
}
?>
