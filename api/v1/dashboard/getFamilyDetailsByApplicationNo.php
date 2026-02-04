<?php 
require_once('../../../helper/header.php');
require_once('../../../helper/log_file.php');
require_once('../../../config/read_database.php');

// Check request method
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    echo json_encode([
        "error" => "Method Not Allowed",
        "message" => "Only POST requests are allowed"
    ]);
    exit;
}

// Get input data
$rawInput = $_POST['data'] ?? null;

// Check if data is provided
if (empty($rawInput)) {
    http_response_code(400);
    echo json_encode([
        "error" => "Bad Request",
        "message" => "Missing required parameter: data"
    ]);
    exit;
}

try {
    $decryptData = decryptData($rawInput);
    
    // Extract and validate data types correctly
    $userid = !empty($decryptData['user_id']) ? (int)$decryptData['user_id'] : null;
    $taluk_id = !empty($decryptData['taluk_id']) ? (int)$decryptData['taluk_id'] : 0;
    
    // These are STRINGS, not integers
    $application_no = !empty($decryptData['application_no']) ? trim($decryptData['application_no']) : '';
    $rationcard_no = !empty($decryptData['ration_card_no']) ? trim($decryptData['ration_card_no']) : '';
    $pds_no = !empty($decryptData['pds_shop_code']) ? trim($decryptData['pds_shop_code']) : '';
    
    // Validate required fields
    $errors = [];
    
    // if (empty($userid)) {
    //     $errors[] = "Parameter 'user_id' is required";
    // }
    
    if (empty($application_no)) {
        $errors[] = "Parameter 'application_no' is required";
    }
    
    if (empty($rationcard_no)) {
        $errors[] = "Parameter 'ration_card_no' is required";
    }
    
    if (empty($pds_no)) {
        $errors[] = "Parameter 'pds_shop_code' is required";
    }
    
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode([
            "error" => "Bad Request",
            "message" => implode(", ", $errors)
        ]);
        exit;
    }
    
    if (!preg_match('/^[A-Z0-9\s]+$/i', $application_no)) {
    http_response_code(400);
    echo json_encode([
        "error" => "Bad Request",
        "message" => "Application No. must contain only letters, numbers, and spaces"
    ]);
    exit;
}
    
    if (!preg_match('/^[A-Z0-9]+$/i', $pds_no)) {
        http_response_code(400);
        echo json_encode([
            "error" => "Bad Request",
            "message" => "PDS Shop Code must contain only letters and numbers"
        ]);
        exit;
    }
    
    if (!preg_match('/^\d+$/', $rationcard_no)) {
        http_response_code(400);
        echo json_encode([
            "error" => "Bad Request", 
            "message" => "Ration Card No. must contain only numbers"
        ]);
        exit;
    }
    
    // Execute the query with correct parameter types
    $sql = "SELECT * FROM public.fn_get_application_fulldetails_applicationno(:taluk, :pds_no, :rationCardNo, :application_no)";
    $stmt = $read_db->prepare($sql);
    
    // Bind parameters with correct types
    $stmt->bindParam(':taluk', $taluk_id, PDO::PARAM_INT);
    $stmt->bindParam(':pds_no', $pds_no, PDO::PARAM_STR); // Changed to STRING
    $stmt->bindParam(':rationCardNo', $rationcard_no, PDO::PARAM_STR); // Changed to STRING
    $stmt->bindParam(':application_no', $application_no, PDO::PARAM_STR); // Changed to STRING
    
    if (!$stmt->execute()) {
        $errorInfo = $stmt->errorInfo();
        http_response_code(400);
        echo json_encode([
            "error" => "Database Query Failed",
            "details" => $errorInfo[2] ?? 'Unknown database error'
        ]);
        exit;
    }
    
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if(empty($rows)){
        http_response_code(404);
        echo json_encode([
            "error" => "Not Found",
            "message" => "No family details found for the provided application number"
        ]);
        exit;
    }
    
    $encryptedData = encryptData($rows);
    
    echo json_encode([
        "message" => "Family details fetched successfully",
        "data" => $encryptedData,
        "success" => true
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    error_log("API Error: " . $e->getMessage());
    
    echo json_encode([
        "error" => "Internal Server Error",
        "message" => "An error occurred while processing your request"
        // For debugging only:
        // "details" => $e->getMessage()
    ]);
    exit;
}
?>