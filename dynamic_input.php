<?php
/*
|----------------------------------------------------------
|  fn_report_reportdata  –  REST endpoint (Extended CRUD)
|----------------------------------------------------------
|  POST  /v1/fn_report_reportdata
|  Body: { data: "<encrypted JSON>" , file?: <file> }
|
|  Decrypts → validates → checks 'action' (insert/update/delete/select)
|  and calls respective stored procedures:
|
|   - public.get_applied_students_dynamic(...)   → SELECT
|   - public.insert_applied_students_dynamic(...) → INSERT
|   - public.update_applied_students_dynamic(...) → UPDATE
|   - public.delete_applied_students_dynamic(...) → DELETE
|
|  File upload (optional):
|   - Stores in ../uploads/
|   - Adds p_file_path and p_file_name to params
|----------------------------------------------------------
*/

require_once('../helper/header.php');
require_once('../helper/db/dipr_read.php');
require_once('../helper/db/dipr_write.php');

header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
}
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
session_start();

define('DATE_FORMAT', 'Y-m-d H:i:s.u');

$logPath = '../../logs/fn_report_reportdata/';
$service = 'v1/fn_report_reportdata';
$method = $_SERVER['REQUEST_METHOD'];
$endpoint = $_SERVER['PHP_SELF'];
$reqTime = date(DATE_FORMAT);

try {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => 0, 'message' => 'Method Not Allowed']);
        exit;
    }

    if (empty($_POST['data'])) {
        throw new Exception('Missing encrypted payload');
    }

    $p = decryptData($_POST['data']);
    // $p = $_POST['data'];
    // $p = json_decode($_POST['data'], true);


    if (!$p || !is_array($p)) {
        throw new Exception('Invalid or corrupted payload');
    }



    $action = strtolower(trim($p['action'] ?? 'select'));
    if (!in_array($action, ['select', 'insert', 'update', 'delete'])) {
        throw new Exception('Invalid action type. Allowed: select, insert, update, delete');
    }

    /* ── Handle File Upload ─────────────────────────────── */

    // print_r($_FILES);
    if (!empty($_FILES['file']) && isset($_FILES['file']['error']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {

        $fileData= json_decode($_POST['fileData'],true);

        // ✅ Use absolute path for uploads
        $uploadDir =$fileData['upload_dir'];  // or adjust path as needed

        // ✅ Ensure directory exists and is writable
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
                throw new Exception("Failed to create upload directory: $uploadDir");
            }
        }

        // ✅ Create unique filename
        $filename = $fileData['filename'];
        $targetFile = $uploadDir ."/". $fileData['filename'];

        // ✅ Move uploaded file safely
        if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
            $p['p_file_path'] = '/uploads/' . $filename;
            $p['p_file_name'] = $filename;

           
        } else {
            throw new Exception('File upload failed: unable to move file.');
        }

    } else if (!empty($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_OK) {

        // ❌ Catch PHP upload errors
        throw new Exception('File upload error: ' . $_FILES['file']['error']);
    }

    //  exit();
    /* ── Prepare helper functions ───────────────────────── */
    $pgArray = fn(array $arr): string =>
        '{' . implode(',', array_map(fn($s) => '"' . str_replace('"', '\"', trim($s)) . '"', $arr)) . '}';

    $toJsonb = function ($val): ?string {
        if (empty($val))
            return null;
        if (is_array($val) && array_values($val) === $val)
            throw new Exception('filter_conditions & sort_columns must be JSON objects');
        return json_encode($val, JSON_UNESCAPED_UNICODE);
    };

    /* ── Choose stored procedure ────────────────────────── */
    switch ($action) {
        case 'insert':
            $proc = 'public.dynamicreport_insert';
            $db = $dipr_write_db;
            break;
        case 'update':
            $proc = 'public.dynamicreport_update';
            $db = $dipr_write_db;
            break;
        case 'delete':
            $proc = 'public.dynamicreport_delete';
            $db = $dipr_write_db;
            break;
        default:
            $proc = 'public.dynamicreport';
            $db = $dipr_read_db;
    }

    /* ── Build parameters ───────────────────────────────── */
    $params = [
        ':_table_name' => trim($p['_table_name'] ?? ''),
        ':selected_columns' => !empty($p['selected_columns']) ? $pgArray($p['selected_columns']) : null,
        ':filter_conditions' => $toJsonb($p['filter_conditions'] ?? null),
        ':group_by_columns' => !empty($p['group_by_columns']) ? $pgArray($p['group_by_columns']) : null,
        ':sort_columns' => $toJsonb($p['sort_columns'] ?? null),
        ':limit_rows' => isset($p['limit_rows']) ? (int) $p['limit_rows'] : null,
        ':offset_rows' => isset($p['offset_rows']) ? (int) $p['offset_rows'] : null,
        ':count_columns' => !empty($p['count_columns']) ? $pgArray($p['count_columns']) : null,
        ':sum_columns' => !empty($p['sum_columns']) ? $pgArray($p['sum_columns']) : null,
        ':distinct_count_columns' => !empty($p['distinct_count_columns']) ? $pgArray($p['distinct_count_columns']) : null,
        ':p_file_path' => $p['p_file_path'] ?? null,
        ':p_file_name' => $p['p_file_name'] ?? null
    ];

    /* ── Build SQL ──────────────────────────────────────── */
    $sql = "SELECT * FROM {$proc}(
        :_table_name,
        :selected_columns,
        :filter_conditions,
        :group_by_columns,
        :sort_columns,
        :limit_rows,
        :offset_rows,
        :count_columns,
        :sum_columns,
        :distinct_count_columns,
        :p_file_path,
        :p_file_name
    )";

    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue(
            $k,
            $v,
            is_null($v) ? PDO::PARAM_NULL :
            (in_array($k, [':limit_rows', ':offset_rows']) ? PDO::PARAM_INT : PDO::PARAM_STR)
        );
    }

    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $respTime = date(DATE_FORMAT);

    if ($action === 'select') {
        $result_data = json_decode($result['dynamicreport'], true);
        $rows = $result_data['data'] ?? [];
        $count = (int) ($result_data['total_count'] ?? 0);

        echo json_encode([
            'success' => $rows ? 1 : 0,
            'message' => $rows ? 'Report data and count fetched' : 'No data found',
            'data' => $rows ? $rows : [],
            'total_count' => $count
        ]);
    } else {
        $key = array_key_first($result);
        $proc_result = json_decode($result[$key] ?? '{}', true);
        echo json_encode([
            'success' => 1,
            'message' => ucfirst($action) . ' operation successful',
            'data' => $proc_result
        ]);
    }

    // logMessage('info', "$action executed successfully", $logPath, 1, 200, $method, $endpoint, $service, $_REQUEST, $_SERVER, $reqTime, $respTime);

} catch (Throwable $e) {
    http_response_code(400);
    $respTime = date(DATE_FORMAT);
    // logMessage('error', $e->getMessage(), $logPath, 0, 400, $method, $endpoint, $service, $_REQUEST, $_SERVER, $reqTime, $respTime);

    echo json_encode([
        'success' => 0,
        'message' => $e->getMessage(),
        'data' => [],
        'total_count' => 0
    ]);
} finally {
    $dipr_read_db = $dipr_write_db = null;
}
?>