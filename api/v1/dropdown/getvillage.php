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
$taluk_id = $decryptData['taluk_id'] ?? null;
if (empty($userid)) {
    http_response_code(400);
    echo json_encode([
        "success" => 0,
        "message" => "Parameter 'userid' is required"
    ]);
    exit;
}
if (empty($taluk_id)) {
    http_response_code(400);
    echo json_encode([
        "success" => 0,
        "message" => "Parameter 'taluk id' is required"
    ]);
    exit;
}
try {

    $villageSql = "SELECT * FROM public.fn_user_village(:taluk, :userid)";
    $villageQry = $read_db->prepare($villageSql);

    $villageQry->bindParam(':taluk', $taluk_id, PDO::PARAM_INT);
    $villageQry->bindParam(':userid', $userid, PDO::PARAM_INT);

    if ($villageQry->execute()) {

        $villageList = $villageQry->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode([
            "success" => 1,
            "message" => "Village List Loaded Successfully",
            "data" => encryptData($villageList)
            // "rawdata" => $villageList,

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
