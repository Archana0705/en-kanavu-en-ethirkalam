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
// print_r($decryptData);
if (empty($userid)) {
    http_response_code(400);
    echo json_encode([
        "success" => 0,
        "message" => "Parameter 'userid' is required"
    ]);
    exit;
}

try {

    $districtSql = "SELECT * FROM public.fn_user_district(:userid)";
    $districtQry = $read_db->prepare($districtSql);

    $districtQry->bindParam(':userid', $userid, PDO::PARAM_STR);

    if ($districtQry->execute()) {

        $districtList = $districtQry->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode([
            "success" => 1,
            "message" => "District List Loaded Successfully",
            "data" => encryptData($districtList)
            // "rawdata" => $districtList,
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
