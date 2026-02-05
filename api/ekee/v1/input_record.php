<?php

require_once('../helper/header.php');
require_once('../helper/db/read.php');
require_once('../helper/db/write.php');
require __DIR__ . '/../vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

define('DATE_FORMAT', 'Y-m-d H:i:s.u');

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => 0, 'message' => 'Method not allowed']);
    exit;
}

if (empty($_POST['data'])) {
    http_response_code(400);
    echo json_encode(['success' => 0, 'message' => 'Missing encrypted payload']);
    exit;
}

$p = decryptData($_POST['data']);
if (!$p || !is_array($p)) {
    http_response_code(400);
    echo json_encode(['success' => 0, 'message' => 'Invalid or corrupted payload']);
    exit;
}

$data = $p; // decrypted json

// --------------------------
// 0. Validation & Sanitization Functions
// --------------------------
function sanitizeString($str, $maxLength = 255)
{
    if (!is_string($str))
        return '';
    $str = trim($str);
    $str = substr($str, 0, $maxLength);
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function sanitizeEmail($email)
{
    $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $email;
    }
    return null;
}

function sanitizeInt($val)
{
    return filter_var($val, FILTER_VALIDATE_INT) !== false ? (int) $val : null;
}

function validateDate($date)
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function validateRequiredFields($data, $required = [])
{
    foreach ($required as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            return "Missing required field: $field";
        }
    }
    return null;
}

// Validate required fields
$requiredFields = ['name', 'email', 'gender', 'district', 'dob', 'age', 'education', 'employmentStatus'];
$fieldError = validateRequiredFields($data, $requiredFields);
if ($fieldError) {
    http_response_code(400);
    echo json_encode(['success' => 0, 'message' => $fieldError]);
    exit;
}

// Sanitize and validate input data
$data['name'] = sanitizeString($data['name'], 100);
$data['email'] = sanitizeEmail($data['email']);
$data['gender'] = sanitizeString($data['gender'], 20);
$data['district'] = sanitizeInt($data['district']);
$data['age'] = sanitizeInt($data['age']);
$data['education'] = sanitizeString($data['education'], 100);
$data['employmentStatus'] = sanitizeString($data['employmentStatus'], 100);
$data['employmentTypeOtherSpecify'] = sanitizeString($data['employmentTypeOtherSpecify'], 100);
$data['employmentType'] = sanitizeString($data['employmentType'] ?? '', 100);
$data['phone'] = sanitizeString($data['phone'] ?? '', 20);
$data['other_aspirations'] = sanitizeString($data['other_aspirations'] ?? '', 500);
$data['respondent_type'] = $data['respondent_type'] ?? '';
$data['icon_name'] = $data['icon_name'] ?? '';

if (
    !$data['email'] ||
    !validateDate($data['dob']) ||
    $data['age'] < 0 || $data['age'] > 150 ||
    !$data['district']
) {
    http_response_code(400);
    echo json_encode(['success' => 0, 'message' => 'Invalid input']);
    exit;
}

// Validate email
if (!$data['email']) {
    http_response_code(400);
    echo json_encode(['success' => 0, 'message' => 'Invalid email format']);
    exit;
}

// Validate date of birth
if (!validateDate($data['dob'])) {
    http_response_code(400);
    echo json_encode(['success' => 0, 'message' => 'Invalid date format for dob']);
    exit;
}

// Validate age
if (
    !$data['email'] ||
    !validateDate($data['dob']) ||
    $data['age'] < 0 || $data['age'] > 150 ||
    !$data['district']
) {
    http_response_code(400);
    echo json_encode(['success' => 0, 'message' => 'Invalid input']);
    exit;
}

// Validate district
if ($data['district'] === null || $data['district'] <= 0) {
    http_response_code(400);
    echo json_encode(['success' => 0, 'message' => 'Invalid district']);
    exit;
}

// Validate phone (if provided)
if (isset($data['phone']) && $data['phone'] !== '') {
    // Basic phone validation - adjust as needed
    if (!preg_match('/^[0-9]{10}$/', $data['phone'])) {
        http_response_code(400);
        echo json_encode(['success' => 0, 'message' => 'Invalid phone number']);
        exit;
    }
}

/* ------------------ S3 UPLOAD (USING YOUR LOGIC) ------------------ */
function uploadFileToS3($fileKey, $folder = 'ekee/uploads')
{

    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
    $allowedMime = [
        'application/pdf',
        'application/x-pdf',
        'application/octet-stream',
        'image/jpeg',
        'image/png',
        'image/jpg'
    ];

    $tmpPath = $_FILES[$fileKey]['tmp_name'];
    $ext = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
    $mime = mime_content_type($tmpPath);

    if (!in_array($ext, $allowedExt) || !in_array($mime, $allowedMime)) {
        throw new Exception('Invalid file type');
    }

    $fileName = bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;

    /* ---- SAME LOGIC AS move_to_s3_debug() ---- */
    $s3 = new S3Client([
        'version' => 'latest',
        'region' => 'ap-south-1',
        'debug' => false,
        'http' => ['debug' => false],
    ]);

    $bucket = 'sfdb-elcot-household';
    $key = $folder . '/' . $fileName;

    try {
        $result = $s3->putObject([
            'Bucket' => $bucket,
            'Key' => $key,
            'SourceFile' => $tmpPath,
            'ContentType' => $mime
        ]);

        return (string) $result['ObjectURL'];
    } catch (AwsException $e) {
        throw new Exception('File upload failed');
    }
}

/* ------------------ HANDLE FILE ------------------ */
$porFile = null;

if (empty($_FILES['poi'])) {
    http_response_code(400);
    echo json_encode([
        'success' => 0,
        'message' => 'Proof of Identity file is required'
    ]);
    exit;
}

try {
    if (isset($_FILES['poi'])) {
        $porFile = uploadFileToS3('poi');

        if ($porFile === null) {
            throw new Exception('File upload failed');
        }
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => 0, 'message' => $e->getMessage()]);
    exit;
}

// --------------------------
// 2. Prepare dreams data for JSON
// --------------------------
function prepareDreamsArray($dreamList, $dreamType = 'immediate')
{
    if (!is_array($dreamList)) {
        return [];
    }

    $preparedDreams = [];
    foreach ($dreamList as $index => $dream) {
        if (!isset($dream['category_id']) || !isset($dream['key'])) {
            continue; // Skip invalid dreams
        }

        $preparedDreams[] = [
            'category_id' => (int) $dream['category_id'],
            'priority' => isset($dream['priority']) ? (int) $dream['priority'] : 1,
            'support_option' => $dream['support_id'] ?? 'Not Specified'
        ];
    }

    return $preparedDreams;
}

// Combine immediate and five-year dreams
$allDreams = [];

// Add immediate dreams
if (!empty($data['immediate_dreams']) && is_array($data['immediate_dreams'])) {
    $immediateDreams = prepareDreamsArray($data['immediate_dreams'], 'immediate');
    $allDreams = array_merge($allDreams, $immediateDreams);
}

// Add five-year dreams
if (!empty($data['five_year_dreams']) && is_array($data['five_year_dreams'])) {
    $fiveYearDreams = prepareDreamsArray($data['five_year_dreams'], 'five_year');
    $allDreams = array_merge($allDreams, $fiveYearDreams);
}

// Validate that we have at least one dream
if (empty($allDreams)) {
    http_response_code(400);
    echo json_encode(['success' => 0, 'message' => 'At least one dream application is required']);
    exit;
}

// Prepare dreams JSON for stored procedure
$dreamsJson = json_encode($allDreams);

// --------------------------
// 3. Call PostgreSQL Stored Procedure
// --------------------------
try {
    // Prepare the stored procedure call
    $sql = "CALL public.sp_insert_applicant_userinfo_json(
        :p_name, :p_gender, :p_email, :p_district, :p_proof, 
        :p_dob, :p_age, :p_education, :p_employment_status, 
        :p_por_filepath, :p_employment_type, :p_mobile_no, 
        :p_otheraspirations, :p_respondent, :p_icon_name, 
        :p_employmenttypeotherspecify, :p_data, 
        NULL, NULL, NULL
    )";



    $stmt = $write_db->prepare($sql);

    // Bind parameters
    $params = [
        ':p_name' => $data['name'],
        ':p_gender' => $data['gender'],
        ':p_email' => $data['email'],
        ':p_district' => $data['district'],
        ':p_proof' => $porFile, // Assuming proof type is Aadhaar, adjust as needed
        ':p_dob' => $data['dob'],
        ':p_age' => $data['age'],
        ':p_education' => $data['education'],
        ':p_employment_status' => $data['employmentStatus'],
        ':p_por_filepath' => $porFile,
        ':p_employment_type' => $data['employmentType'],
        ':p_mobile_no' => $data['phone'],
        ':p_otheraspirations' => $data['other_aspirations'],
        ':p_respondent' => $data['respondent_type'],
        ':p_icon_name' => $data['icon_name'],
        ':p_employmenttypeotherspecify' => $data['employmentTypeOtherSpecify'] ?? '',
        ':p_data' => $dreamsJson
    ];

    // Execute the stored procedure
    $stmt->execute($params);

    // Fetch the result
    $result = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$result || !$result['o_success']) {
        throw new Exception("Stored procedure call failed");
    }

    // Get the returned values
    $applicant_id = $result['o_id'];
    $application_id = $result['o_application_id'];
    $success = $result['o_success'];

    if (!$success) {
        throw new Exception("Application submission failed");
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => 0,
        'message' => 'Database error: ' . $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => 0, 'message' => $e->getMessage()]);
    exit;
}

// --------------------
// 4. Final Response
// --------------------
http_response_code(200);
echo json_encode([
    'success' => 1,
    'message' => 'Application submitted successfully',
    'application_id' => htmlspecialchars($application_id, ENT_QUOTES, 'UTF-8'),
    'user_id' => (int) $applicant_id,
    'uploaded_files' => [
        'proof_of_identity' => $porFile ? htmlspecialchars($porFile, ENT_QUOTES, 'UTF-8') : null
    ]
]);
