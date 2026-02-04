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
$district_code = isset($decryptData['district_code']) ? trim($decryptData['district_code']) : null;

// Build query dynamically but safely
$sql = "
    SELECT 
        pmd.district_id, 
        pmd.district_name, 
        COALESCE(COUNT(urm.user_id), 0) AS user_count
    FROM pds_master_district pmd 
    LEFT JOIN userdistrictmapping udm ON udm.district_id = pmd.district_id
    LEFT JOIN userrolemapping urm ON urm.user_id = udm.user_id
        AND urm.role_id = 6
        AND urm.active = true
";

$params = [];

if (!empty($district_code) && $district_code != '0') {
    $sql .= " WHERE pmd.district_id = :district_code";
    $params[':district_code'] = $district_code;
}

$sql .= " GROUP BY pmd.district_id, pmd.district_name ORDER BY pmd.district_id ASC";

try {
    $stmt = $read_db->prepare($sql);
    $stmt->execute($params); // Pass parameters safely

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => 1,
        "data" => $data,
        "message" => "User list fetched successfully"
    ]);
    exit;

} catch (PDOException $e) {
    // Log the error securely (avoid exposing details in production)
    error_log("Database error in district user count query: " . $e->getMessage());

    echo json_encode([
        "success" => 0,
        "error" => "An error occurred while fetching data."
        // Do NOT expose $e->getMessage() in production
    ]);
    exit;
}
?>