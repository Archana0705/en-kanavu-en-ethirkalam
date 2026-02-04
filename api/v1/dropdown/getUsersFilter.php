<?php 
require_once('../../../helper/header.php');
require_once('../../../helper/log_file.php');
require_once('../../../config/read_database.php');
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    echo json_encode([
        "error" => "Method Not Allowed",
        "message" => "Only POST requests are allowed"
    ]);
    exit;
}
$rawInput = $_POST['data'] ?? null;

if (empty($rawInput)) {
    http_response_code(400);
    echo json_encode([
        "success"=>false,
        "error" => "Bad Request",
        "message" => "Missing required parameter: data"
    ]);
    exit;
}

try {
    $decryptData = decryptData($rawInput);
    $userid      = !empty($decryptData['user_id']) ? (int)$decryptData['user_id'] : null;
    $district_id = !empty($decryptData['district_id']) ? (int)$decryptData['district_id'] : 0;
    $taluk_id    = !empty($decryptData['taluk_id']) ? (int)$decryptData['taluk_id'] : 0;
    $village_id  = !empty($decryptData['village_id']) ? (int)$decryptData['village_id'] : 0;
    $pdsshop_id  = !empty($decryptData['pdsshop_id']) ? (int)$decryptData['pdsshop_id'] : 0;
   
    if (empty($userid)) {
        http_response_code(400);
        echo json_encode([
            "success"=>false,
            "error" => "Bad Request",
            "message" => "Required parameter is missing."
        ]);
        exit;
    }

    $sql = "SELECT * FROM public.fn_getuserslist(:districtid, :talukid, :villageid, :pdsid)";
    $stmt = $read_db->prepare($sql);
    $stmt->bindParam(':districtid', $district_id, PDO::PARAM_INT);
    $stmt->bindParam(':talukid', $taluk_id, PDO::PARAM_INT);
    $stmt->bindParam(':villageid', $village_id, PDO::PARAM_INT);
    $stmt->bindParam(':pdsid', $pdsshop_id, PDO::PARAM_INT);
    
    if (!$stmt->execute()) {
        $errorInfo = $stmt->errorInfo();
        http_response_code(400);
        echo json_encode([
            "message" => "Failed to fetch data",
            "success"=>false,
        ]);
        exit;
    }
    
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $encryptedData = encryptData($rows);
  
    echo json_encode([
        "data"   => $encryptedData,
        "success"=> true,
        "message"=>'Data fetched successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);    
    echo json_encode([
        "success"=>false,
        "error"   => "Internal Server Error",
        "message" => "An error occurred while processing your request"
       
    ]);
    exit;
}
?>