<?php

require_once('../../../helper/header.php');
require_once('../../../helper/log_file.php');
require_once('../../../config/write_database.php');

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    echo json_encode([
        "success" => 3,
        "message" => "Method Not Allowed"
    ]);
    exit;
}

$name           = $_POST['name'] ?? null;
$mobile_number  = $_POST['mobile_number'] ?? null;
$district_code  = $_POST['district_code'] ?? 0;
$taluk_code     = $_POST['taluk_code'] ?? 0;
$active         = $_POST['active'] ?? true;

$roleData       = $_POST['role_data'] ?? '[]';
$mappingData    = $_POST['mapping_data'] ?? '[]';

if (empty($name) || empty($mobile_number)) {
    http_response_code(400);
    echo json_encode([
        "success" => 0,
        "message" => "Name and Mobile Number are required"
    ]);
    exit;
}

try {

    // Prepare JSON for user_detail
    $userDetail = json_encode([
        "user_id"      => $_POST['user_id'],
        "name"         => $name,
        "mobile_no"    => $mobile_number,
        "district_id"  => $district_code,
        "taluk_id"     => $taluk_code,
        "username"     => $mobile_number,
        "password"     => password_hash("secure@123", PASSWORD_BCRYPT),
        "active"       => $active,
        "created_by"   => $_POST['user_id']
    ]);

    $userRoleData     = $roleData;      // already JSON from frontend
    $userMappingData  = $mappingData;   // already JSON from frontend

    $status = false;

    // CALL PROCEDURE
    $stmt = $write_db->prepare("
        CALL sp_userupdate(:userdetail, :userroledata, :usermappingdata, :status)
    ");

    $stmt->bindParam(':userdetail',      $userDetail,       PDO::PARAM_STR);
    $stmt->bindParam(':userroledata',    $userRoleData,     PDO::PARAM_STR);
    $stmt->bindParam(':usermappingdata', $userMappingData,  PDO::PARAM_STR);
    $stmt->bindParam(':status',          $status, PDO::PARAM_BOOL | PDO::PARAM_INPUT_OUTPUT, 1);

    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $status = $result['status'] ?? $status;

    http_response_code(200);
    echo json_encode([
        "success" => $status ? 1 : 0,
        "message" => $status ? "Success" : "Error",
        "status"  => $status
    ]);
    exit;

} catch (PDOException $e) {

    http_response_code(400);
    echo json_encode([
        "success" => 0,
        "message" => "Database Error",
        "error"   => $e->getMessage()
    ]);
    exit;

} finally {
    $write_db = null;
}

?>
