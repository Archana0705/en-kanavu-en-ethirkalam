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



try {

    $rolesSql = "SELECT  * FROM  fn_user_activeroles();";
    $rolesQry = $read_db->prepare($rolesSql);


    if ($rolesQry->execute()) {

        $roleList = $rolesQry->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode([
            "success" => 1,
            "message" => " List Loaded Successfully",
            "data" => encryptData($roleList)
            // "rawdata" => $roleList,
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