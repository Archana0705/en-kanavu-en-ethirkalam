<?php 
require_once('../../../helper/header.php');
require_once('../../../helper/log_file.php');
require_once('../../../config/read_database.php');
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    echo json_encode([
        "message" => "Method Not Allowed",
        "success" => false
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
        "success" => false,
        "message" => "Missing required parameter"
    ]);
    exit;
}

try {
    $decryptData = decryptData($rawInput);
    // print_r($decryptData);exit;
    $userid      = !empty($decryptData['user_id']) ? (int)$decryptData['user_id'] : null;
    $district_id = !empty($decryptData['district_id']) ? (int)$decryptData['district_id'] : 0;
    $taluk_id    = !empty($decryptData['taluk_id']) ? (int)$decryptData['taluk_id'] : 0;
    $village_id  = !empty($decryptData['village_id']) ? (int)$decryptData['village_id'] : 0;
    $pdsshop_id  = !empty($decryptData['pdsshop_id']) ? (int)$decryptData['pdsshop_id'] : 0;
    $filter  = !empty($decryptData['status_filter']) ? (int)$decryptData['status_filter'] : 0;
    $surveyor = isset($decryptData['user_wise']) ? intval($decryptData['user_wise']) : 0;
    $start       = !empty($decryptData['start']) ? (int)$decryptData['start'] : 0;
    $length      = !empty($decryptData['length']) ? (int)$decryptData['length'] : null;
    $searchValue = !empty($decryptData['search_value']) ? trim($decryptData['search_value']) : '';
    $draw        = !empty($decryptData['draw']) ? (int)$decryptData['draw'] : 1;


    $fromdate = null;
    $todate = null;

    if ($filter === -1 || $filter === 0) {
        $fromdate = null;
        $todate = null;
    } else {
        $fromdate = !empty($decryptData['from_date']) ? $decryptData['from_date'] : date('Y-m-d');
        $todate = !empty($decryptData['to_date']) ? $decryptData['to_date'] : date('Y-m-d');
    }

    if (empty($userid)) {
        http_response_code(400);
        echo json_encode(["error" => "Bad Request","message" => "Required parameter is missing","success" => false,]);
        exit;
    }

    if (empty($pdsshop_id)) {
        http_response_code(400);
        echo json_encode(["error" => "Bad Request", "message" => "Required parameter is missing","success" => false,]);
        exit;
    }

    // Main query for data
    $sql = "SELECT * FROM public.fn_db_familymembersdetials_by_shopcode(:pdsid, :limits, :offset, :searchvalue, :p_status, :uw, :fd, :td)";
    // $sql = "SELECT * FROM public.fn_db_familymembersdetials_by_shopcode($pdsshop_id, $length, $start, '$searchValue', $filter, $surveyor, '$fromdate', '$todate')";
    // print_r($sql);exit;
    $stmt = $read_db->prepare($sql);
    $stmt->bindParam(':pdsid', $pdsshop_id, PDO::PARAM_INT);
    $stmt->bindParam(':limits', $length, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $start, PDO::PARAM_INT);
    $stmt->bindParam(':searchvalue', $searchValue, PDO::PARAM_STR);
    $stmt->bindParam(':p_status', $filter, PDO::PARAM_INT);
    $stmt->bindParam(':uw', $surveyor, PDO::PARAM_INT);
    $stmt->bindParam(':fd', $fromdate, PDO::PARAM_STR);
    $stmt->bindParam(':td', $todate, PDO::PARAM_STR);
    
    if (!$stmt->execute()) {
        $errorInfo = $stmt->errorInfo();
        http_response_code(400);
        echo json_encode([
            "message" => "Data fetch error",
            "success" => false,
        ]);
        exit;
    }
    
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $recordsTotal = 0;
    
    // Extract total count from first row if available (assuming the function returns it)
    if (!empty($rows) && isset($rows[0]['total_count'])) {
        $recordsTotal = intval($rows[0]['total_count']);
        // Remove the total_count from data rows if not needed
        foreach ($rows as &$row) {
            unset($row['total_count']);
        }
    }
    
    // If total count not found in result, fetch it separately
    if ($recordsTotal == 0) {
        try {
            // Note: This assumes your function supports a separate call for count
            // You might need a different function or modify parameters for count-only query
            $countSql = "SELECT COUNT(*) as total FROM public.fn_db_familymembersdetials_by_shopcode(:pdsid, NULL, NULL, :searchvalue, :p_status, :uw, :fd, :td)";
            $countStmt = $read_db->prepare($countSql);
            $countStmt->bindParam(':pdsid', $pdsshop_id, PDO::PARAM_INT);
            $countStmt->bindParam(':searchvalue', $searchValue, PDO::PARAM_STR);
            $countStmt->bindParam(':p_status', $filter, PDO::PARAM_INT);
            $countStmt->bindParam(':uw', $surveyor, PDO::PARAM_INT);
            $countStmt->bindParam(':fd', $fromdate, PDO::PARAM_STR);
            $countStmt->bindParam(':td', $todate, PDO::PARAM_STR);
            
            if ($countStmt->execute()) {
                $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
                $recordsTotal = intval($countResult['total'] ?? 0);
            }
        } catch (Exception $e) {
            // Log but don't fail - use row count as fallback
            error_log("Count query failed: " . $e->getMessage());
            $recordsTotal = count($rows);
        }
    }
    
    // Encrypt data if required (consider if this is really needed for all data)
    $encryptedData = encryptData($rows);
    
    // Return successful response
    echo json_encode([
        "draw"            => $draw,
        "recordsTotal"    => $recordsTotal,
        "recordsFiltered" => $recordsTotal,
        "data"            => $encryptedData,
        "success"         => true
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    error_log("API Error: " . $e->getMessage());
    
    echo json_encode([
        "error"   => "Internal Server Error",
        "message" => "An error occurred while processing your request"
    
    ]);
    exit;
}
?>