<?php 
require_once('../../../helper/header.php');
require_once('../../../helper/log_file.php');
require_once('../../../config/read_database.php');

$startTime = microtime(true);
$serviceName = 'download_dashboard_summary';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    $response = json_encode([
        "success" => 3,
        "message" => "Method Not Allowed"
    ]);
    http_response_code(405);
    echo $response;
    exit;
}

try {
    $rawInput = $_POST['data'] ?? null;
    
    if (empty($rawInput)) {
        http_response_code(400);
        echo json_encode([
            "error" => "Bad Request",
            "message" => "Missing required data parameter"
        ]);
        exit;
    }

    $decryptData = decryptData($rawInput);
    
    if (!$decryptData || !is_array($decryptData)) {
       $response = json_encode([
            "error" => "Bad Request", 
            "message" => "Invalid or corrupted data"
        ]);
        http_response_code(400);
        echo $response;
        exit;
    }

    // Required parameters
    $userid = $decryptData['user_id'] ?? null;
    
    if (empty($userid)) {
    $response = json_encode([
        "success" => 0,
        "message" => "Parameter 'userid' is required"
    ]);
    http_response_code(400);
    echo $response;
    exit;
    }

    // Optional parameters with validation
    $district_id = isset($decryptData['district_id']) ? intval($decryptData['district_id']) : 0;
    $taluk_id = isset($decryptData['taluk_id']) ? intval($decryptData['taluk_id']) : 0;
    $village_id = isset($decryptData['village_id']) ? intval($decryptData['village_id']) : 0;
    $pdsshop_id = isset($decryptData['pdsshop_id']) ? intval($decryptData['pdsshop_id']) : 0;
    $type = isset($decryptData['type_id']) ? intval($decryptData['type_id']) : null;
    $surveyor = isset($decryptData['user_wise']) ? intval($decryptData['user_wise']) : null;
    $fromdate = isset($decryptData['from_date']) && !empty($decryptData['from_date']) ? $decryptData['from_date'] : date('Y-m-d'); 
    $todate = isset($decryptData['to_date']) && !empty($decryptData['to_date']) ? $decryptData['to_date'] : date('Y-m-d');


    $start = 0;
    $length = 100000000; 
    $searchValue = ''; // No search for download
    $draw = 1;

    // MAIN data query
    $sql = "SELECT * FROM public.fn_get_dashboardsummary_download(:districtid, :talukid, :villageid, :pdsid, :limits, :offset, :searchvalue, :types, :uw, :fd, :td)";
    $stmt = $read_db->prepare($sql);
    $stmt->bindParam(':districtid', $district_id, PDO::PARAM_INT);
    $stmt->bindParam(':talukid', $taluk_id, PDO::PARAM_INT);
    $stmt->bindParam(':villageid', $village_id, PDO::PARAM_INT);
    $stmt->bindParam(':pdsid', $pdsshop_id, PDO::PARAM_INT);
    $stmt->bindParam(':limits', $length, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $start, PDO::PARAM_INT);
    $stmt->bindParam(':searchvalue', $searchValue, PDO::PARAM_STR);
    $stmt->bindParam(':types', $type, PDO::PARAM_INT);
    $stmt->bindParam(':uw', $surveyor, PDO::PARAM_INT);
    $stmt->bindParam(':fd', $fromdate, PDO::PARAM_STR);
    $stmt->bindParam(':td', $todate, PDO::PARAM_INT);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute database query');
    }
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalCount = count($results);
    $response = encryptData($results);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => 1,"message" => "Data Fetch Successfully", "data"=>$response], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    $response = json_encode([
        "error" => "Database Error",
        "message" => "A database error occurred while processing your request"
    ]);
    http_response_code(400);
    echo $response;
    exit;
} catch (Exception $e) {
    $response = json_encode([
        "error" => "Server Error", 
        "message" => "An error occurred while processing your request"
    ]);
    http_response_code(400);
    echo $response;
    exit;
}
?>