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

$data = $p;

/* ------------------ SANITIZATION & VALIDATION ------------------ */
function sanitizeString($str, $maxLength = 255)
{
    if (!is_string($str)) return '';
    return htmlspecialchars(substr(trim($str), 0, $maxLength), ENT_QUOTES, 'UTF-8');
}

function sanitizeEmail($email)
{
    $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
}

function sanitizeInt($val)
{
    return filter_var($val, FILTER_VALIDATE_INT) !== false ? (int)$val : null;
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

$requiredFields = ['name', 'email', 'gender', 'district', 'dob', 'age', 'education', 'employmentStatus'];
$fieldError = validateRequiredFields($data, $requiredFields);
if ($fieldError) {
    http_response_code(400);
    echo json_encode(['success' => 0, 'message' => $fieldError]);
    exit;
}

$data['name'] = sanitizeString($data['name'], 100);
$data['email'] = sanitizeEmail($data['email']);
$data['gender'] = sanitizeString($data['gender'], 20);
$data['district'] = sanitizeInt($data['district']);
$data['age'] = sanitizeInt($data['age']);
$data['education'] = sanitizeString($data['education'], 100);
$data['employmentStatus'] = sanitizeString($data['employmentStatus'], 100);
$data['employmentTypeOtherSpecify'] = sanitizeString($data['employmentTypeOtherSpecify'], 100);

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

/* ------------------ S3 UPLOAD (USING YOUR LOGIC) ------------------ */
function uploadFileToS3($fileKey, $folder = 'ekee/uploads')
{

    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowedExt  = ['pdf', 'jpg', 'jpeg', 'png'];
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
        'region'  => 'ap-south-1',
        'debug'   => false,
        'http'    => ['debug' => false],
    ]);

    $bucket = 'sfdb-elcot-household';
    $key    = $folder . '/' . $fileName;

    try {
        $result = $s3->putObject([
            'Bucket'      => $bucket,
            'Key'         => $key,
            'SourceFile'  => $tmpPath,
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


/* ------------------ INSERT APPLICANT ------------------ */
$application_id = 'APP_' . uniqid() . '_' . bin2hex(random_bytes(4));

$sql_applicant = "
INSERT INTO public.applicant_userinfo
(name, gender, email, district, proof, dob, age, education,
 employment_status, is_active, created_by, application_id,
 por_filepath, employment_type, mobile_no, otheraspirations,
 respondent, icon_name, employmentTypeOtherSpecify)
VALUES
(:name,:gender,:email,:district,:proof,:dob,:age,:education,
 :employment_status,true,1,:application_id,
 :por,:employment_type,:mobile_no,:otheraspirations,
 :respondent,:icon_name,:employmentTypeOtherSpecify)
RETURNING id;
";

$stmt = $write_db->prepare($sql_applicant);
$stmt->execute([
    ':name' => $data['name'],
    ':gender' => $data['gender'],
    ':email' => $data['email'],
    ':district' => $data['district'],
    ':proof' => $porFile,
    ':dob' => $data['dob'],
    ':age' => $data['age'],
    ':education' => $data['education'],
    ':employment_status' => $data['employmentStatus'],
    ':application_id' => $application_id,
    ':por' => $porFile,
    ':employment_type' => $data['employmentType'],
    ':mobile_no' => $data['phone'],
    ':otheraspirations' => $data['other_aspirations'] ?? '',
    ':respondent' => $data['respondent_type'],
    ':icon_name' => $data['icon_name'],
    ':employmentTypeOtherSpecify' => $data['employmentTypeOtherSpecify']
]);

$applicant_id = $stmt->fetchColumn();

/* ------------------ DREAMS INSERTION (UNCHANGED) ------------------ */
function insertDreams($dreams, $application_id, $user_id, $db)
{
    if (!$dreams || !is_array($dreams)) return;

    $sql = "
        INSERT INTO public.user_dream_applications
        (application_id, user_id, category_id, priority, support_option)
        VALUES (:application_id,:user_id,:category_id,:priority,:support_option)
    ";

    $stmt = $db->prepare($sql);

    foreach ($dreams as $d) {
        $stmt->execute([
            ':application_id' => $application_id,
            ':user_id' => $user_id,
            ':category_id' => sanitizeInt($d['category_id']),
            ':priority' => sanitizeInt($d['priority'] ?? 0),
            ':support_option' => sanitizeString($d['support_id'] ?? '', 20),
        ]);
    }
}

if (isset($data['immediate_dreams'])) {
    insertDreams($data['immediate_dreams'], $application_id, $applicant_id, $write_db);
}
if (isset($data['five_year_dreams'])) {
    insertDreams($data['five_year_dreams'], $application_id, $applicant_id, $write_db);
}

/* ------------------ FINAL RESPONSE ------------------ */
echo json_encode([
    'success' => 1,
    'message' => 'Application submitted successfully',
    'application_id' => $application_id,
    'user_id' => (int)$applicant_id,
    'uploaded_files' => [
        'proof_of_residence' => $porFile
    ]
]);
