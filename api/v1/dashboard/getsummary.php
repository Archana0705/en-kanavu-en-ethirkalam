<?php 
require_once('../../../helper/log_file.php');
require_once('../../../helper/header.php');
require_once('../../../config/read_database.php');
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    $response = json_encode(["success" => 3,"message" => "Method Not Allowed"]);
    echo $response;
    exit;
}
$rawInput = $_POST['data'] ?? null;
$decryptData = decryptData($rawInput);
$userid = $decryptData['user_id'] ?? null;
$district_id = $decryptData['district_id'] ?? null;
$village_id = $decryptData['village_id'] ?? null;
$taluk_id = $decryptData['taluk_id'] ?? null;
$pdsshop_id = $decryptData['pdsshop_id'] ?? null;

$surveyor = isset($decryptData['user_wise']) ? intval($decryptData['user_wise']) : null;
$fromdate = isset($decryptData['from_date']) && !empty($decryptData['from_date']) ? $decryptData['from_date'] : date('Y-m-d'); 
$todate = isset($decryptData['to_date']) && !empty($decryptData['to_date']) ? $decryptData['to_date'] : date('Y-m-d');


if (empty($userid)) {
    http_response_code(400);
    $response = json_encode(["success" => 0,"message" => "Required parameter is missing"]);
    echo $response;
    exit;
}

try {
    $summarySql = "SELECT * FROM public.fn_dashboard_counts_revised(:userid, :districtid, :talukid, :villageid, :pdsid, :uw, :fd, :td)";
    $countQry = $read_db->prepare($summarySql);

    $countQry->bindParam(':userid', $userid, PDO::PARAM_INT);
    $countQry->bindParam(':districtid', $district_id, PDO::PARAM_INT);
    $countQry->bindParam(':talukid', $taluk_id, PDO::PARAM_INT);
    $countQry->bindParam(':villageid', $village_id, PDO::PARAM_INT);
    $countQry->bindParam(':pdsid', $pdsshop_id, PDO::PARAM_INT);
    $countQry->bindParam(':uw', $surveyor, PDO::PARAM_INT);
    $countQry->bindParam(':fd', $fromdate, PDO::PARAM_STR);
    $countQry->bindParam(':td', $todate, PDO::PARAM_STR);

    if ($countQry->execute()) {
        $summary = $countQry->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($summary)) {
            http_response_code(200);
            $response = json_encode(["success" => 1,"message" => "Data Loaded Successfully","data" => encryptData($summary)]);
            echo $response;
            exit;
        } else {
            http_response_code(200);
            $response = json_encode(["success" => 0,"message" => "No data found","data" => []]);
            echo $response;
        }
        exit;
    } else {
        http_response_code(400);
        $response = json_encode(["success" => 0,"message" => "Data fetch failed"]);
        echo $response;
        exit;
    }

} catch (PDOException $e) {
    http_response_code(400); 
    $response = json_encode(["success" => 0,"message" => "Database Error",]); 
    echo $response;
    exit;
}
?>