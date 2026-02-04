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

// Get and sanitize input
$district_code = isset($_POST['district_code']) ? trim($_POST['district_code']) : null;

// Base query
$sql = "
    SELECT  
        pm.district_id, 
        pmd.district_name, 
        pm.taluk_id, 
        pmt.taluk_name, 
        pm.village_id, 
        pvm.village_name, 
        pm.shop_code, 
        COUNT(DISTINCT urm.user_id) AS user_count
    FROM pds_master pm 
    LEFT JOIN pds_master_district pmd ON pmd.district_id = pm.district_id
    LEFT JOIN pds_master_taluk pmt    ON pmt.taluk_id = pm.taluk_id
    LEFT JOIN pds_village_master pvm ON pvm.village_id = pm.village_id
    LEFT JOIN userdistrictmapping udm ON udm.pds_id = pm.id
    LEFT JOIN userrolemapping urm     ON urm.user_id = udm.user_id
        AND urm.role_id = 6
        AND urm.active = true
";

// Parameters array
$params = [];

// Add WHERE clause only if district_code is provided and not '0' or empty
if (!empty($district_code) && $district_code !== '0') {
    $sql .= " WHERE pm.district_id = :district_code";
    $params[':district_code'] = $district_code;
}

$sql .= "
    GROUP BY 
        pm.district_id, pmd.district_name, 
        pm.taluk_id, pmt.taluk_name, 
        pm.village_id, pvm.village_name, 
        pm.shop_code
    ORDER BY pm.district_id ASC
";

try {
    $stmt = $read_db->prepare($sql);
    $stmt->execute($params);  // Properly bind parameters

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
        "message" => "An error occurred while fetching data."
        // Do not return $e->getMessage() in production!
    ]);
    exit;
}
?>