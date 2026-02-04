<?php 
require_once('../../../helper/header.php');
require_once('../../../helper/log_file.php');
require_once('../../../config/write_database.php');

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed", "message" => "Only POST requests are allowed"]);
    exit;
}

$rawInput = $_POST['data'] ?? null;

if (empty($rawInput)) {
    http_response_code(400);
    echo json_encode(["error" => "Bad Request", "message" => "Missing required parameter: data"]);
    exit;
}

try {
    $decryptData = decryptData($rawInput);
    
    $user_id = !empty($decryptData['user_id']) ? (int)$decryptData['user_id'] : null;
    $reject_reason = !empty($decryptData['reject_reason']) ? trim($decryptData['reject_reason']) : '';
    $ration_card_raw = !empty($decryptData['ration_card_numbers']) ? $decryptData['ration_card_numbers'] : null;
    
    if (empty($user_id)) {
        http_response_code(400);
        echo json_encode(["error" => "Bad Request", "message" => "User ID is required"]);
        exit;
    }
    
    if (empty($reject_reason)) {
        http_response_code(400);
        echo json_encode(["error" => "Bad Request", "message" => "Rejection reason is required"]);
        exit;
    }
    
    if (empty($ration_card_raw)) {
        http_response_code(400);
        echo json_encode(["error" => "Bad Request", "message" => "No ration card numbers provided"]);
        exit;
    }
    
    $ration_card_numbers = [];
    
    if (is_array($ration_card_raw)) {
        $ration_card_numbers = $ration_card_raw;
    } elseif (is_string($ration_card_raw)) {
        $decoded = json_decode($ration_card_raw, true);
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $ration_card_numbers = $decoded;
        } else {
            $ration_card_numbers = array_map('trim', explode(',', $ration_card_raw));
        }
    }
    
    $valid_ration_cards = [];
    foreach ($ration_card_numbers as $card_no) {
        $card_no = trim((string)$card_no);
        
        if (preg_match('/^[0-9]{1,19}$/', $card_no)) {
            $valid_ration_cards[] = $card_no;
        }
    }
    
    if (empty($valid_ration_cards)) {
        http_response_code(400);
        echo json_encode([
            "error" => "Bad Request",
            "message" => "No valid ration card numbers provided"
        ]);
        exit;
    }
    
    $pg_array = '{' . implode(',', array_map(function($card) {
        return $card; 
    }, $valid_ration_cards)) . '}';
    
    try {
        $sql = "CALL public.sp_reject_survey(:p_ufcno_list, :p_rejection_remarks, :p_rejected_by, null, null, null)";
        
        $stmt = $write_db->prepare($sql);
        $stmt->bindParam(':p_ufcno_list', $pg_array, PDO::PARAM_STR);
        $stmt->bindParam(':p_rejection_remarks', $reject_reason, PDO::PARAM_STR);
        $stmt->bindParam(':p_rejected_by', $user_id, PDO::PARAM_INT);
        $data = $stmt->execute();
        $dataExecute = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($data && $dataExecute[0]['o_success_count'] > 0) {
            $results = [
                'message' => 'Applications rejected successfully',
                'processed_count' => $dataExecute[0]['o_success_count']
            ];
            $encryptedData = encryptData($results);
            echo json_encode([
                "message" => $results['message'],
                "data" => $encryptedData,
                "success" => true
            ]);
            exit;
        } else {
            $results = [
                'message' => 'Applications rejection failed',
                'failure_count' => $dataExecute[0]['o_failure_count'],
                'list'=>$dataExecute[0]['o_failed_ufcno_list'],
            ];
            $encryptedData = encryptData($results);
             echo json_encode([
                "message" => $results['message'],
                "data" => $encryptedData,
                "success" => false
            ]);
            exit;
        }
    } catch (Exception $e) {
        throw new Exception('Stored procedure error: ' . $e->getMessage());
    }  
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "error" => "Internal Server Error",
        "message" => $e->getMessage(),
        "success" => false
    ]);
    exit;
}
?>