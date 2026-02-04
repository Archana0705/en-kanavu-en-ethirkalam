<?php 

require_once('../../../helper/header.php');
require_once('../../../helper/log_file.php');
require_once('../../../config/read_database.php');

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    echo json_encode([
        "success" => 3,
        "message" => "Method Not Allowed"
    ]);
    exit;
}
$rawInput = $_POST['data'] ?? null;
$decryptData = decryptData($rawInput);
$userid = $decryptData['user_id'] ?? 0;
$district_id = $decryptData['district_id'] ?? 0;
if (empty($userid)) {
    http_response_code(400);
    echo json_encode([
        "success" => 0,
        "message" => "Parameter 'userid' is required"
    ]);
    exit;
}

try {

    $talukSql = "SELECT * FROM public.fn_user_taluk(:districtid, :userid)";
    $talukQry = $read_db->prepare($talukSql);

    $talukQry->bindParam(':districtid', $district_id, PDO::PARAM_STR);
    $talukQry->bindParam(':userid', $userid, PDO::PARAM_STR);

    if ($talukQry->execute()) {

        $talukList = $talukQry->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode([
            "success" => 1,
            "message" => "Taluk List Loaded Successfully",
            "data" => encryptData($talukList)
            // "rawdata" => $talukList,
        ]);
        exit;
    }

} catch (PDOException $e) {

    http_response_code(400);
    echo json_encode([
        "success" => 0,
        "message" => "Database Error",
        "error" => $e->getMessage()
    ]);
    exit;
}

?>
