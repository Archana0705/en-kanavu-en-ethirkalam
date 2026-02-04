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
$district_id = $decryptData['district_id'] ?? null;
$taluk_id = $decryptData['taluk_id'] ?? 0;
$_id = $decryptData['village_id'] ?? 0;
if (empty($userid)) {
    http_response_code(400);
    echo json_encode([
        "success" => 0,
        "message" => "Parameter 'userid' is required"
    ]);
    exit;
}
if (empty($district_id)) {
    http_response_code(400);
    echo json_encode([
        "success" => 0,
        "message" => "Parameter 'district id' is required"
    ]);
    exit;
}
try {

    $Sql = "SELECT * FROM public.fn_masterdetails(:district_id, :taluk, :village_id)";
    $Qry = $read_db->prepare($Sql);
    $Qry->bindParam(':district_id', $district_id, PDO::PARAM_INT);
    $Qry->bindParam(':taluk', $taluk_id, PDO::PARAM_INT);
    $Qry->bindParam(':village_id', $_id, PDO::PARAM_INT);

    if ($Qry->execute()) {

        $List = $Qry->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode([
            "success" => 1,
            "message" => " List Loaded Successfully",
            "data" => encryptData($List)
            // "rawdata" => $List,

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
