<?php

require_once('../helper/header.php');
require_once('../helper/db/edm_read.php');
require_once('../helper/db/edm_write.php');
require __DIR__ . '/../vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $origin = $_SERVER['HTTP_ORIGIN'];
    // Validate origin against whitelist
    $allowedOrigins = ['http://localhost:3000', 'http://127.0.0.1:3000', 'https://yourdomain.com'];
    if (in_array($origin, $allowedOrigins)) {
        header("Access-Control-Allow-Origin: " . $origin);
    }
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

// Validate request method
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


$s3 = new S3Client([
    'version' => 'latest',
    'region'  => 'ap-south-1',   // Mumbai region (change if needed)
    'credentials' => [
        'key'    => 'YOUR_AWS_ACCESS_KEY',
        'secret' => 'YOUR_AWS_SECRET_KEY',
    ],
]);
$bucket = 'your-bucket-name';



$data = $p; // decrypted json
// --------------------------
// 0. Validation & Sanitization Functions
// --------------------------
function sanitizeString($str, $maxLength = 255) {
    if (!is_string($str)) return '';
    $str = trim($str);
    $str = substr($str, 0, $maxLength);
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function sanitizeEmail($email) {
    $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $email;
    }
    return null;
}

function sanitizeInt($val) {
    return filter_var($val, FILTER_VALIDATE_INT) !== false ? (int)$val : null;
}

function validateDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function validateRequiredFields($data, $required = []) {
    foreach ($required as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
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
// $data['created_by'] = sanitizeString($data['created_by'], 100);

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

// Validate age - must be valid integer
if ($data['age'] === null || $data['age'] < 0 || $data['age'] > 150) {
    http_response_code(400);
    echo json_encode(['success' => 0, 'message' => 'Invalid age']);
    exit;
}

// Validate district and taluk - must be valid integers
if ($data['district'] === null || $data['district'] <= 0) {
    http_response_code(400);
    echo json_encode(['success' => 0, 'message' => 'Invalid district']);
    exit;
}

// if ($data['taluk'] === null || $data['taluk'] <= 0) {
//     http_response_code(400);
//     echo json_encode(['success' => 0, 'message' => 'Invalid taluk']);
//     exit;
// }

// Validate is_active - must be valid integer


// --------------------------
// 1. Handle file upload
// --------------------------
function uploadFile($fileKey, $uploadDir = "../uploads/") {
    // Allowed file extensions and MIME types
    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif'];
    $allowedMimeTypes = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif'
    ];
    $maxFileSize = 5 * 1024 * 1024; // 5MB

    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return null; // no file
    }

    // Validate file size
    if ($_FILES[$fileKey]['size'] > $maxFileSize) {
        throw new Exception("File size exceeds 5MB limit for $fileKey");
    }

    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception("Failed to create upload directory");
        }
    }

    $tmp = $_FILES[$fileKey]['tmp_name'];
    $originalFileName = basename($_FILES[$fileKey]['name']);
    
    // Get file extension
    $fileExt = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
    
    // Validate extension
    if (!in_array($fileExt, $allowedExtensions)) {
        throw new Exception("Invalid file extension for $fileKey. Allowed: " . implode(',', $allowedExtensions));
    }

    // Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $tmp);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedMimeTypes)) {
        throw new Exception("Invalid file MIME type for $fileKey. Detected: $mimeType");
    }

    // Generate safe filename (timestamp + random + extension)
    $fileName = bin2hex(random_bytes(8)) . "_" . time() . "." . $fileExt;
    $dest = $uploadDir . $fileName;

    // Move uploaded file to s3 destination
    // if(move_To_S3($fileName)){
    //     throw new Exception("Failed to move uploaded file for $fileKey");
    // }
    
    
    if (!move_uploaded_file($tmp, $dest)) {
        throw new Exception("Failed to move uploaded file for $fileKey");
    }

    return $fileName;
}

function move_To_S3($filename){
    global $s3, $bucket;
    $filePath = "../uploads/" . $filename;
    
    try {
        // Upload data.
        $result = $s3->putObject([
            'Bucket' => $bucket,
            'Key'    => 'uploads/' . $filename,
            'SourceFile' => $filePath,
            'ACL'    => 'public-read', // Make file publicly accessible
        ]);

        // Return the URL to the object.
        return $result['ObjectURL'];
    } catch (AwsException $e) {
        throw new Exception("S3 Upload Error: " . $e->getMessage());
    }
}

// Upload POR (Proof of Residence) & POA (Proof of Age)
$porFile = null;
// $poaFile = null;

try {
    if (isset($_FILES['poi'])) {
        $porFile = uploadFile("poi");
    }
    // if (isset($_FILES['proofAge'])) {
    //     $poaFile = uploadFile("proofAge");
    // }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => 0, 'message' => 'File upload error: ' . $e->getMessage()]);
    exit;
}

// --------------------------
// 2. Insert applicant info
// --------------------------
$application_id = 'APP_' . uniqid() . '_' . bin2hex(random_bytes(4));

$sql_applicant = "
    INSERT INTO public.applicant_userinfo
    (name, gender, email, district,  proof, dob, age, education,
     employment_status, is_active, created_by, application_id, por_filepath,employment_type,mobile_no,otheraspirations,respondent,icon_name,employmentTypeOtherSpecify)
    VALUES
    (:name, :gender, :email, :district, :proof, :dob, :age, 
     :education, :employment_status, :is_active, :created_by, :application_id, :por,:employment_type,:mobile_no,:otheraspirations,:respondent,:icon_name,:employmentTypeOtherSpecify)
    RETURNING id;
";

try {
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
        ':is_active' => true,
        ':created_by' => 1,
        ':application_id' => $application_id,
        // ':proof_of_age' => $poaFile,
        ':por' => $porFile,
        // ':poa' => $poaFile,
        ':employment_type' => $data['employmentType'],
        ':mobile_no' => $data['phone'],
        ':otheraspirations' => $data['other_aspirations'] ?? '',
        'respondent'=> $data['respondent_type'],
        'icon_name' => $data['icon_name'],
        'employmentTypeOtherSpecify' => $data['employmentTypeOtherSpecify']
    ]);

    $applicant_id = $stmt->fetchColumn();
    
    if (!$applicant_id) {
        throw new Exception("Failed to insert applicant information");
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => 0, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => 0, 'message' => $e->getMessage()]);
    exit;
}

// ------------------------------
// 3. Insert Dreams With Files
// ------------------------------
function insertDreams($dreamListJson, $application_id, $user_id, $db) {
    if (!$dreamListJson) return;
   
    $dreams =$dreamListJson ;
 
    if (!is_array($dreams)) {
        throw new Exception("Invalid dreams format");
    }

    // Validate dreams array
    if (count($dreams) > 100) {
        throw new Exception("Maximum 100 dreams allowed");
    }

    $sql = "
        INSERT INTO public.user_dream_applications
        (application_id, user_id, category_id, priority, support_option)
        VALUES
        (:application_id, :user_id, :category_id, :priority, :support_option)
    ";

    try {
        $stmt = $db->prepare($sql);
        // print_r($dreams);
        // die();

        foreach ($dreams as $d) {
            // Validate dream object
            if (!isset($d['key'])) {
                throw new Exception("Missing 'key' in dream object");
            }

            $categoryId = sanitizeInt($d['category_id']);
            // print_r($categoryId);
            if (!$categoryId || $categoryId <= 0) {
                throw new Exception("Invalid category_id in dream");
            }

            $priority = isset($d['priority']) ? sanitizeInt($d['priority']) : 0;
            if ($priority === null) $priority = 0;
            
            if ($priority < 0 || $priority > 10) {
                throw new Exception("Priority must be between 0 and 10");
            }

            // Support option must be max 20 characters
            $supportOption = null;
            if (isset($d['support_id'])) {
                $rawSupport = trim($d['support_id']);
                if (strlen($rawSupport) > 20) {
                    throw new Exception("support_option must not exceed 20 characters");
                }
                $supportOption = sanitizeString($rawSupport, 20);
            }

            $stmt->execute([
                ':application_id' => $application_id,
                ':user_id' => $user_id,
                ':category_id' => $categoryId,
                ':priority' => $priority,
                ':support_option' => $supportOption,
            ]);
        }
    } catch (PDOException $e) {
        throw new Exception("Database error inserting dreams: " . $e->getMessage());
    }
}

// Insert Immediate Dreams
try {
    if (isset($data['immediate_dreams'])) {
        insertDreams($data['immediate_dreams'], $application_id, $applicant_id, $write_db);
    }
    
    // Insert Five-Year Dreams
    if (isset($data['five_year_dreams'])) {
        insertDreams($data['five_year_dreams'], $application_id, $applicant_id, $write_db);
    }
} catch (Exception $e) {
    http_response_code(400);
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
    'user_id' => (int)$applicant_id,
    'uploaded_files' => [
        'proof_of_residence' => $porFile ? htmlspecialchars($porFile, ENT_QUOTES, 'UTF-8') : null
    ]
]);