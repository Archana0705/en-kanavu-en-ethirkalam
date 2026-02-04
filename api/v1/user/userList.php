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

$request = $_POST;

// Validate case param
if (($request['case'] ?? '') !== 'getData') {
    echo json_encode([
        "success" => 0,
        "message" => "Invalid Request"
    ]);
    exit;
}

try {
    $db = $read_db;

    $draw = intval($request['draw'] ?? 1);
    $start = intval($request['start'] ?? 0);
    $length = intval($request['length'] ?? 10);
    $search = trim($request['search']['value'] ?? '');
    $orderColIndex = $request['order'][0]['column'] ?? 0;
    $orderDir = $request['order'][0]['dir'] ?? 'asc';

    $districtid = $_POST['district_id'] ?? 0;
    $userid = $_POST['user_id'] ?? 0;
    $userActive = $_POST['userActive'] ?? true;
    $userRole = $_POST['userRole'] ?? true;
    $userMapping = $_POST['userMapping'] ?? true;

    $columns = [
        'userid',
        'username',
        'mobile_no',
        'rolename',
        'district_name',
        'taluk_name',
        'village_name',
        'shop_code',
        'active',
        'rolemappingstatus',
        'districtmappingstatus'
    ];

    $orderColumn = $columns[$orderColIndex] ?? 'userid';

    // Base query should only contain WHERE conditions
    $baseQuery = "WHERE 1=1";
    $params = [
        ':districtid' => $districtid,
        ':userActive' => $userActive,
        ':userRole' => $userRole,
        ':userMapping' => $userMapping
    ];

    if ($search !== '') {
        $baseQuery .= "
            AND (
                username ILIKE :search OR 
                mobile_no::text ILIKE :search OR 
                rolename ILIKE :search OR 
                district_name ILIKE :search OR 
                taluk_name ILIKE :search OR 
                village_name ILIKE :search OR 
                shop_code ILIKE :search
            )
        ";
        $params[':search'] = "%{$search}%";
    }

    // ***********************
    // Total count (all records without filters)
    // ***********************
    $stmtTotal = $db->prepare("
        SELECT COUNT(*) 
        FROM fn_user_userdetails(:districtid, :userActive, :userRole, :userMapping)
    ");
    $stmtTotal->bindParam(':districtid', $districtid, PDO::PARAM_INT);
    $stmtTotal->bindParam(':userActive', $userActive, PDO::PARAM_BOOL);
    $stmtTotal->bindParam(':userRole', $userRole, PDO::PARAM_BOOL);
    $stmtTotal->bindParam(':userMapping', $userMapping, PDO::PARAM_BOOL);
    $stmtTotal->execute();
    $recordsTotal = $stmtTotal->fetchColumn();

    // ***********************
    // Filtered count (with search filter)
    // ***********************
    $filteredQuery = "
        SELECT COUNT(*) 
        FROM fn_user_userdetails(:districtid, :userActive, :userRole, :userMapping)
        $baseQuery
    ";
    
    $stmtFiltered = $db->prepare($filteredQuery);
    
    foreach ($params as $key => $value) {
        if ($key === ':districtid' || $key === ':userActive' || $key === ':userRole' || $key === ':userMapping') {
            $paramType = strpos($key, ':districtid') !== false ? PDO::PARAM_INT : PDO::PARAM_BOOL;
            $stmtFiltered->bindValue($key, $value, $paramType);
        } else {
            $stmtFiltered->bindValue($key, $value);
        }
    }
    
    $stmtFiltered->execute();
    $recordsFiltered = $stmtFiltered->fetchColumn();

    // ***********************
    // Paginated data
    // ***********************
    $query = "
        SELECT 
            userid, username, mobile_no, rolename, rolemappingid, usermappingid, role_id,
            district_id, taluk_id, village_id, pdsid,
            district_name, taluk_name, village_name, shop_code, active,
            rolemappingstatus, districtmappingstatus
        FROM fn_user_userdetails(:districtid, :userActive, :userRole, :userMapping)
        $baseQuery
        ORDER BY $orderColumn $orderDir
        LIMIT :length OFFSET :start
    ";

    $stmt = $db->prepare($query);

    foreach ($params as $key => $value) {
        if ($key === ':districtid' || $key === ':userActive' || $key === ':userRole' || $key === ':userMapping') {
            $paramType = strpos($key, ':districtid') !== false ? PDO::PARAM_INT : PDO::PARAM_BOOL;
            $stmt->bindValue($key, $value, $paramType);
        } else {
            $stmt->bindValue($key, $value);
        }
    }

    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // if (!empty($userid)) {
    //     $rows = array_filter($rows, function ($row) use ($userid) {
    //         return $row['userid'] != $userid;
    //     });
    //     $rows = array_values($rows);
    // }

    $data = [];
    $index = $start + 1;

    foreach ($rows as $row) {
        $row['index'] = $index++;
        $data[] = $row;
    }

    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => intval($recordsTotal),
        'recordsFiltered' => intval($recordsFiltered),
        'data' => $data
    ]);
    exit;

} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
    exit;
}
   

?>