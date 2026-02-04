<?php

require_once('../../../helper/header.php');
require_once('../../../helper/log_file.php');
require_once('../../../config/read_database.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "success" => 0,
        "message" => "Method Not Allowed"
    ]);
    exit;
}

$decryptData = decryptData($_POST['data']);

// Get and sanitize input
$district_code = isset($decryptData['district_code']) ? trim($decryptData['district_code']) : 0;
$function_name = isset($decryptData['function_name']) ? trim($decryptData['function_name']) : '';

if (empty($function_name)) {
    echo json_encode([
        "success" => 0,
        "message" => "Function name is required"
    ]);
    exit;
}

// Base query
$sql = "SELECT * from $function_name(:district_code)";

try {
    $stmt = $read_db->prepare($sql);
    $stmt->bindParam(':district_code', $district_code, PDO::PARAM_INT);
    $stmt->execute();  // Properly bind parameters

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => 1,
        "data" => $data,
        "message" => "Data fetched successfully"
    ]);
    exit;

} catch (PDOException $e) {
    // Log error internally (never expose in production)
    error_log("Database error in shop user count query: " . $e->getMessage());

    echo json_encode([
        "success" => 0,
        "message" => "An error occurred while fetching data.",
        "error" => $e->getMessage()
        // Do not return $e->getMessage() in production!
    ]);
    exit;
}
?>