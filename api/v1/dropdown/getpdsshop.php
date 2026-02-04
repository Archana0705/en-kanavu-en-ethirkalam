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
$userid = $decryptData['user_id'] ?? null;
$village_id = $decryptData['village_id'] ?? null;


if (empty($userid)) {
    http_response_code(400);
    echo json_encode([
        "success" => 0,
        "message" => "Parameter 'userid' is required"
    ]);
    exit;
}
if (empty($village_id)) {
    http_response_code(400);
    echo json_encode([
        "success" => 0,
        "message" => "Parameter 'village id' is required"
    ]);
    exit;
}
try {

    $pdsshopSql = "SELECT * FROM public.fn_user_pds(:village, :userid)";
    $pdsshopQry = $read_db->prepare($pdsshopSql);

    $pdsshopQry->bindParam(':village', $village_id, PDO::PARAM_INT);
    $pdsshopQry->bindParam(':userid', $userid, PDO::PARAM_INT);

    if ($pdsshopQry->execute()) {

        $pdsshopList = $pdsshopQry->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode([
            "success" => 1,
            "message" => "PDS Shop List Loaded Successfully",
            "data" => encryptData($pdsshopList)
            // "rawdata" => $pdsshopList,
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
