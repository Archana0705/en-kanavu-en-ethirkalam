<?php 
require_once('../../../helper/header.php');
require_once('../../../helper/log_file.php');
require_once('../../../config/read_database.php');

$startTime = microtime(true);
$serviceName = 'dashboard'; 

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    $response = json_encode(["success" => 3,"message" => "Method Not Allowed"]);
    echo $response;
    exit;
}

$rawInput = $_POST['data'] ?? null;
$decryptData = decryptData($rawInput);

$userid      = !empty($decryptData['user_id']) ? (int)$decryptData['user_id'] : null;
$district_id = !empty($decryptData['district_id']) ? (int)$decryptData['district_id'] : 0;
$taluk_id    = !empty($decryptData['taluk_id']) ? (int)$decryptData['taluk_id'] : 0;
$village_id  = !empty($decryptData['village_id']) ? (int)$decryptData['village_id'] : 0;
$pdsshop_id  = !empty($decryptData['pdsshop_id']) ? (int)$decryptData['pdsshop_id'] : 0;
$type        = !empty($decryptData['type_id']) ? (int)$decryptData['type_id'] : null;

$surveyor = isset($decryptData['user_wise']) ? intval($decryptData['user_wise']) : null;
$fromdate = isset($decryptData['from_date']) && !empty($decryptData['from_date']) ? $decryptData['from_date'] : date('Y-m-d'); 
$todate = isset($decryptData['to_date']) && !empty($decryptData['to_date']) ? $decryptData['to_date'] : date('Y-m-d');

$start       = !empty($decryptData['start']) ? (int)$decryptData['start'] : 0;
$length      = !empty($decryptData['length']) ? (int)$decryptData['length'] : null;
$searchValue = $decryptData['search_value'] ?? '';
$draw        = !empty($decryptData['draw']) ? (int)$decryptData['draw'] : 1;

if (empty($userid)) {
   http_response_code(400);
    $response = json_encode(["success" => 0,"message" => "Required parameter is missing"]);
    echo $response;
    exit;
}
try {
    $sql = "SELECT * FROM public.fn_get_dashboardsummary_download(:userid, :districtid, :talukid, :villageid, :pdsid, :limits, :offset, :searchvalue, :types, :uw, :fd, :td)";
    $stmt = $read_db->prepare($sql);
    $stmt->bindParam(':userid', $userid, PDO::PARAM_INT);
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
    $stmt->bindParam(':td', $todate, PDO::PARAM_STR);
    $executionResult = $stmt->execute();
    
    if (!$executionResult) {
        $errorInfo = $stmt->errorInfo();
        $response = json_encode([
            "meassage" => "Data fetch error",
            "details" => $errorInfo[2] ?? 'Unknown error'
        ]);
        echo $response;
        exit;
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $recordsTotal = 0;
    if (!empty($rows) && isset($rows[0]['total_count'])) {
        $recordsTotal = intval($rows[0]['total_count']);
    }
    if ($recordsTotal == 0) {
        $countSql = "SELECT COUNT(*) AS total FROM public.fn_get_dashboardsummary_download(:userid, :districtid, :talukid, :villageid, :pdsid, NULL, NULL, :searchvalue, :types, :uw, :fd, :td)";
        $countStmt = $read_db->prepare($countSql);
        $countStmt->bindParam(':userid', $userid, PDO::PARAM_INT);
        $countStmt->bindParam(':districtid', $district_id, PDO::PARAM_INT);
        $countStmt->bindParam(':talukid', $taluk_id, PDO::PARAM_INT);
        $countStmt->bindParam(':villageid', $village_id, PDO::PARAM_INT);
        $countStmt->bindParam(':pdsid', $pdsshop_id, PDO::PARAM_INT);
        $countStmt->bindParam(':searchvalue', $searchValue, PDO::PARAM_STR);
        $countStmt->bindParam(':types', $type, PDO::PARAM_INT);
        $countStmt->bindParam(':uw', $surveyor, PDO::PARAM_INT);
        $countStmt->bindParam(':fd', $fromdate, PDO::PARAM_STR);
        $countStmt->bindParam(':td', $todate, PDO::PARAM_STR);

        $countStmt->execute();
        $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
        $recordsTotal = intval($countResult['total'] ?? 0);
    }

    echo json_encode([
        "draw"            => $draw,
        "recordsTotal"    => $recordsTotal,
        "recordsFiltered" => $recordsTotal,
        'success'         => true,
        "data"            => encryptData($rows),
    ]);
     exit;
} catch (PDOException $e) {
    http_response_code(400);
    $response = json_encode(["success" => 0,"message" => "Database Error",]); 
    echo $response;
    exit;
}

?>