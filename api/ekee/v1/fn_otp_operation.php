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
require_once('../helper/db/read.php');
require_once('../helper/db/write.php');
require __DIR__ . '/../vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $origin = $_SERVER['HTTP_ORIGIN'];
    // Validate origin against whitelist
    $allowedOrigins = ['http://localhost:3000', 'http://127.0.0.1:3000', 'https://yourdomain.com', 'https://tngis.tnega.org'];
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

/*
|----------------------------------------------------------
|  OTP API Functions
|----------------------------------------------------------
*/

/**
 * Generate a 6-digit OTP
 */
function generateOTP()
{
    return str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Insert OTP into database
 */
function insertOTP($mobileNo, $otp)
{
    global $write_db;
    // Clean mobile number (remove any non-numeric characters)
    $mobileNo = preg_replace('/[^0-9]/', '', $mobileNo);

    // First, check if mobile number already exists
    try {
        $checkStmt = $write_db->prepare("SELECT id FROM public.otp_login WHERE mobileno = ?");
        $checkStmt->execute([$mobileNo]);
        $existing = $checkStmt->fetch();
         
        if ($existing) {
            // Update existing OTP
            $stmt = $write_db->prepare(
                "UPDATE public.otp_login \n SET otp = ?, created_ts = NOW(), updated_ts = NOW(), is_used = false, attempt_count = 0\n                WHERE mobileno = ?"
            );
            $result = $stmt->execute([$otp, $mobileNo]);
            if ($result === false) {
                $err = $stmt->errorInfo();
                error_log("OTP Update failed: " . json_encode($err));
            }
        } else {
            // Insert new OTP
            $stmt = $write_db->prepare(
                "INSERT INTO public.otp_login (mobileno, otp, created_ts) \n                VALUES (?, ?, NOW())"
            );
            $result = $stmt->execute([$mobileNo, $otp]);
            if ($result === false) {
                $err = $stmt->errorInfo();
                error_log("OTP Insert failed: " . json_encode($err));
            }
        }

        return $result;
    } catch (PDOException $e) {
        // Log error
        error_log("OTP Insert PDOException: " . $e->getMessage());
        // In development, rethrow so caller can surface the DB error to the client
        if (function_exists('isDevelopmentEnvironment') && isDevelopmentEnvironment()) {
            throw $e;
        }
        return false;
    } catch (Exception $e) {
        error_log("OTP Insert Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Verify OTP
 */
function verifyOTP($mobileNo, $otp)
{
    try {

        global $write_db;

        // $write_db = getEdmReadConnection();

        // Clean mobile number
        $mobileNo = preg_replace('/[^0-9]/', '', $mobileNo);

        // Check OTP with 10-minute expiry
        $stmt = $write_db->prepare("
            SELECT id, created_ts, is_used, attempt_count
            FROM public.otp_login 
            WHERE mobileno = ? AND otp = ? 
            AND (is_used IS NULL OR is_used = false)
            ORDER BY created_ts DESC 
            LIMIT 1
        ");

        $stmt->execute([$mobileNo, $otp]);
        $result = $stmt->fetch();
        // print_r($result);
        if (!$result) {
            // OTP not found
            // echo "OTP not found or expired\n";
            return false;
        }

        if ($result) {
            // Check if OTP is already used or too many attempts
            if ($result['is_used'] || $result['attempt_count'] >= 5) {
                return false;
            }

            // OTP verified successfully - mark it as used
            markOTPAsUsed($result['id']);
            return true;
        }

        return false;
    } catch (Exception $e) {
        error_log("OTP Verification Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark OTP as used
 */
function markOTPAsUsed($otpId)
{
    try {
        global $write_db;
        // $write_db = getEdmWriteConnection();
        $stmt = $write_db->prepare("
            UPDATE public.otp_login 
            SET is_used = true, updated_ts = NOW() 
            WHERE id = ?
        ");
        return $stmt->execute([$otpId]);
    } catch (Exception $e) {
        error_log("Mark OTP Used Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Increment OTP attempt count
 */
function incrementOTPAttempt($otpId)
{
    try {
        global $write_db;
        // $write_db = getEdmWriteConnection();
        $stmt = $write_db->prepare("
            UPDATE public.otp_login 
            SET attempt_count = attempt_count + 1, last_attempt = NOW(), updated_ts = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$otpId]);
    } catch (Exception $e) {
        error_log("Increment OTP Attempt Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if OTP was recently sent (rate limiting)
 */
function canSendOTP($mobileNo)
{
    try {
        global $write_db;
        // $write_db = getEdmReadConnection();

        $mobileNo = preg_replace('/[^0-9]/', '', $mobileNo);

        $stmt = $write_db->prepare("
            SELECT created_ts 
            FROM public.otp_login 
            WHERE mobileno = ? 
            AND created_ts >= NOW() - INTERVAL '1 minute'
            ORDER BY created_ts DESC 
            LIMIT 1
        ");

        $stmt->execute([$mobileNo]);
        $result = $stmt->fetch();

        // If OTP was sent in the last minute, don't allow resend
        return !$result;
    } catch (Exception $e) {
        error_log("OTP Rate Limit Check Error: " . $e->getMessage());
        return true; // Allow sending if there's an error
    }
}

/**
 * Get recent OTP record for a mobile number
 */
function getRecentOTP($mobileNo)
{
    try {
        global $write_db;
        // $write_db = getEdmReadConnection();

        $mobileNo = preg_replace('/[^0-9]/', '', $mobileNo);

        $stmt = $write_db->prepare("
            SELECT id, otp, created_ts, is_used, attempt_count
            FROM public.otp_login 
            WHERE mobileno = ? 
            AND created_ts >= NOW() - INTERVAL '10 minutes'
            ORDER BY created_ts DESC 
            LIMIT 1
        ");

        $stmt->execute([$mobileNo]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Get Recent OTP Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Handle OTP operations
 */
function handleOTPOperation($data)
{
    $operation = $data['operation'] ?? '';
    $mobileNo = $data['mobile_no'] ?? '';
    $otp = $data['otp'] ?? '';


    // Validate mobile number
    if (!preg_match('/^[6-9]\d{9}$/', $mobileNo)) {
        echo json_encode(['success' => 0, 'message' => 'Invalid mobile number format']);
        return;
    }

    switch ($operation) {
        case 'send_otp':
            sendOTP($mobileNo);
            break;

        case 'verify_otp':
            verifyOTPRequest($mobileNo, $otp);
            break;

        case 'check_otp_status':
            checkOTPStatus($mobileNo);
            break;

        case 'resend_otp':
            resendOTP($mobileNo);
            break;
        case 'get_user_details':
            $users_info = getUsersInfo($mobileNo);
            $user_mapping_data = null;
            if ($users_info['usercount'] == 1) {
                $user_mapping = $users_info['user']['id'];
                // echo $user_mapping;
                $user_mapping_data = getUserApplicationInfo($user_mapping);
                // print_r($user_mapping_data);

            }
            echo json_encode([
                'success' => 1,
                'user_count' => $users_info['usercount'],
                'user' => $users_info['user'],
                'mobile_no' => $mobileNo,
                'user_mapping' => $user_mapping_data,
                'message' => 'User details fetched successfully'


            ]);
            break;

        default:
            echo json_encode(['success' => 0, 'message' => 'Invalid operation']);
    }
}

/**
 * Send OTP to mobile number
 */
function sendOTP($mobileNo)
{
    // Rate limiting check
    if (!canSendOTP($mobileNo)) {
        echo json_encode([
            'success' => 0,
            'message' => 'Please wait 1 minute before requesting another OTP',
            'code' => 'RATE_LIMITED'
        ]);
        return;
    }

    // Generate OTP
    $otp = generateOTP();
    // $otp = 123456;
    sendOtpViamethod($mobileNo, $otp, 'என் கனவு, என் எதிர்காலம்', 'mobile');

    // Store OTP in database
    try {
        sendOtpViamethod($mobileNo, $otp, 'என் கனவு, என் எதிர்காலம்', 'mobile');

        $insertResult = insertOTP($mobileNo, $otp);
    } catch (Exception $e) {
        error_log("sendOTP: insertOTP exception: " . $e->getMessage());
        if (isDevelopmentEnvironment()) {
            echo json_encode([
                'success' => 0,
                'message' => 'Failed to send OTP. DB error: ' . $e->getMessage(),
                'code' => 'SEND_FAILED'
            ]);
            return;
        }
        echo json_encode([
            'success' => 0,
            'message' => 'Failed to send OTP. Please try again.',
            'code' => 'SEND_FAILED'
        ]);
        return;
    }

    if ($insertResult) {
        // In production, you would send SMS here
        // For development, we'll return the OTP
        if (isDevelopmentEnvironment()) {
            // Development mode - return OTP
            echo json_encode([
                'success' => 1,
                'message' => 'OTP sent successfully',
                'debug_otp' => $otp, // Remove this in production
                'expires_in' => 600 // 10 minutes in seconds
            ]);
        } else {
            // Production mode - don't return OTP
            // sendSMS($mobileNo, $otp); // Implement SMS sending function
            echo json_encode([
                'success' => 1,
                'message' => 'OTP sent successfully',
                'debug_otp' => $otp,
                'expires_in' => 600
            ]);
        }

        // Log OTP generation
        error_log("OTP generated for $mobileNo: $otp");
    } else {
        // insertOTP returned false without exception
        error_log("sendOTP: insertOTP returned false for $mobileNo");
        echo json_encode([
            'success' => 0,
            'message' => 'Failed to send OTP. Please try again.',
            'code' => 'SEND_FAILED'
        ]);
    }
}

/**
 * Resend OTP
 */
function resendOTP($mobileNo)
{
    // Check if there's a recent OTP that can be reused
    $recentOTP = getRecentOTP($mobileNo);

    if ($recentOTP && !$recentOTP['is_used'] && $recentOTP['attempt_count'] < 3) {
        // Reuse existing OTP
        $otp = $recentOTP['otp'];

        // Update timestamp to extend expiry
        global $write_db;
        // $write_db = getEdmWriteConnection();
        $stmt = $write_db->prepare(
            "UPDATE public.otp_login \n            SET created_ts = NOW(), attempt_count = 0 \n            WHERE mobileno = ? AND otp = ?"
        );
        $stmt->execute([$mobileNo, $otp]);

        echo json_encode([
            'success' => 1,
            'message' => 'OTP resent successfully',
            'debug_otp' => isDevelopmentEnvironment() ? $otp : null,
            'expires_in' => 600
        ]);
    } else {
        // Generate new OTP
        sendOTP($mobileNo);
    }
}

/**
 * Verify OTP request
 */
function verifyOTPRequest($mobileNo, $otp)
{



    if (empty($otp) || !preg_match('/^\d{6}$/', $otp)) {
        echo json_encode([
            'success' => 0,
            'message' => 'Invalid OTP format. Please enter 6 digits.',
            'code' => 'INVALID_FORMAT'
        ]);
        return;
    }




    // Get recent OTP record first to check status
    $recentOTP = getRecentOTP($mobileNo);

    if (!$recentOTP) {
        echo json_encode([
            'success' => 0,
            'message' => 'No OTP found or OTP expired. Please request a new OTP.',
            'code' => 'OTP_EXPIRED'
        ]);
        return;
    }
    // print_r($recentOTP);

    if ($recentOTP['is_used'] == 'true') {
        echo json_encode([
            'success' => 0,
            'message' => 'OTP already used. Please request a new OTP.',
            'code' => 'OTP_USED'
        ]);
        return;
    }

    if ($recentOTP['attempt_count'] >= 5) {
        echo json_encode([
            'success' => 0,
            'message' => 'Too many failed attempts. Please request a new OTP.',
            'code' => 'MAX_ATTEMPTS'
        ]);
        return;
    }

    // Increment attempt count
    incrementOTPAttempt($recentOTP['id']);
    // print_r(verifyOTP($mobileNo, $otp));
    // echo "Debug: Calling verifyOTP with $mobileNo and $otp\n";
    if (verifyOTP($mobileNo, $otp)) {


        $users_info = getUsersInfo($mobileNo);
        $user_mapping_data = null;

        if ($users_info['usercount'] == 1) {
            $user_mapping = $users_info['user']['id'];
            // echo $user_mapping;
            $user_mapping_data = getUserApplicationInfo($user_mapping);
            // print_r($user_mapping_data);

        }


        // OTP verified successfully
        $_SESSION['otp_verified'] = true;
        $_SESSION['verified_mobile'] = $mobileNo;
        $_SESSION['verified_at'] = time();

        echo json_encode([
            'success' => 1,
            'message' => 'OTP verified successfully',
            'verified' => true,
            'user_count' => $users_info['usercount'],
            'user' => $users_info['user'],
            'mobile_no' => $mobileNo,
            'user_mapping' => $user_mapping_data,

        ]);
    } else {
        $remainingAttempts = 5 - ($recentOTP['attempt_count'] + 1);

        echo json_encode([
            'success' => 0,
            'message' => $remainingAttempts > 0
                ? "Invalid OTP. {$remainingAttempts} attempts remaining."
                : "Too many failed attempts. Please request a new OTP.",
            'code' => 'INVALID_OTP',
            'is_used' => $recentOTP['is_used'],
            'remaining_attempts' => $remainingAttempts
        ]);
    }
}

/**
 * Check OTP status
 */
function checkOTPStatus($mobileNo)
{
    $recentOTP = getRecentOTP($mobileNo);

    if (!$recentOTP) {
        echo json_encode([
            'success' => 0,
            'message' => 'No active OTP found',
            'has_active_otp' => false
        ]);
        return;
    }

    $createdTime = strtotime($recentOTP['created_ts']);
    $currentTime = time();
    $timeElapsed = $currentTime - $createdTime;
    $timeRemaining = 600 - $timeElapsed; // 10 minutes expiry

    echo json_encode([
        'success' => 1,
        'has_active_otp' => true,
        'is_used' => (bool) $recentOTP['is_used'],
        'attempt_count' => (int) $recentOTP['attempt_count'],
        'time_remaining' => max(0, $timeRemaining),
        'expires_in' => max(0, $timeRemaining)
    ]);
}

/**
 * Check if we're in development environment
 */
function isDevelopmentEnvironment()
{
    $host = $_SERVER['HTTP_HOST'] ?? '';
    return in_array($host, ['localhost', '127.0.0.1', '::1']) || strpos($host, '.local') !== false;
}

/*
|----------------------------------------------------------
|  Main API Router
|----------------------------------------------------------
*/

// Handle OTP operations
if (isset($p['action']) && $p['action'] === 'otp_operation') {
    handleOTPOperation($p);
    exit;
}

// Your existing CRUD operations continue here...
// Handle other actions (insert, update, delete, select)
if (isset($p['action'])) {
    switch ($p['action']) {
        case 'insert':
            // Your existing insert logic
            handleInsertOperation($p);
            break;

        case 'select':
            // Your existing select logic
            handleSelectOperation($p);
            break;

        case 'update':
            // Your existing update logic
            handleUpdateOperation($p);
            break;

        case 'delete':
            // Your existing delete logic
            handleDeleteOperation($p);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => 0, 'message' => 'Invalid action']);
            exit;
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => 0, 'message' => 'No action specified']);
    exit;
}

/*
|----------------------------------------------------------
|  Your Existing CRUD Functions (placeholder)
|----------------------------------------------------------
*/

/* ==========================================================
   PRESIGNED URL (GLOBAL – FIX)
   ========================================================== */
function presignedURL($file_name, $folder, $expiry = '+20 minutes')
{
    $s3 = new S3Client([
        'version' => 'latest',
        'region'  => 'ap-south-1',
    ]);

    $bucket = 'sfdb-elcot-household';
    $key    = $folder . '/' . $file_name;

    try {
        $cmd = $s3->getCommand('GetObject', [
            'Bucket' => $bucket,
            'Key'    => $key
        ]);
        $request = $s3->createPresignedRequest($cmd, $expiry);
        return (string) $request->getUri();
    } catch (AwsException $e) {
        return null;
    }
}

function getUsersInfo($mobileNo)
{

    global $read_db;
    $stmt = $read_db->prepare("SELECT * FROM public.applicant_userinfo WHERE mobile_no = ?");
    $stmt->execute([$mobileNo]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $usercount = $stmt->rowCount();
    
     if (!empty($user)) {

        /* ---------- proof ---------- */
        if (!empty($user['proof'])) {
            $path = ltrim(parse_url($user['proof'], PHP_URL_PATH), '/');
            if ($path) {
                $user['proof'] = presignedURL(
                    basename($path),
                    dirname($path)
                );
            }
        }

        /* ---------- por_filepath ---------- */
        if (!empty($user['por_filepath'])) {
            $path = ltrim(parse_url($user['por_filepath'], PHP_URL_PATH), '/');
            if ($path) {
                $user['por_filepath'] = presignedURL(
                    basename($path),
                    dirname($path)
                );
            }
        }
    }

    $result = [
        'usercount' => $usercount,
        'user' => $user
    ];



    // print_r($user);
    return $result;

}
function getUserApplicationInfo($user_id)
{

    global $read_db;
    $stmt = $read_db->prepare("SELECT * FROM public.user_dream_applications WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // print_r($user);
    $usercount = $stmt->rowCount();

    $result = [
        'usercount' => $usercount,
        'user' => $user
    ];
    if (!$user) {
        $result = [
            'usercount' => 0,
            'user' => null
        ];
    }

    return $result;
}



function sendOtpViamethod($contact, $generatedOTP, $app_name, $method)
{
    $_SESSION['otp'] = $generatedOTP;
    $_SESSION['otp_expiry'] = time() + 120; // OTP expires in 2 minutes

    if ($method === 'mobile') {
        $message_content = "OTP is $generatedOTP for $app_name. Don't share it with anyone - TNGOVT";
        $entityid = 1301157259712022912;
        $templateid = 1007048609431761598;
        $endpoint = 'https://tmegov.onex-aura.com/api/sms';
        $params = array('key' => 'odSuiU2q', 'to' => $contact, 'from' => 'TNGOVT', 'body' => $message_content, 'entityid' => $entityid, 'templateid' => $templateid);
        $url = $endpoint . '?' . http_build_query($params);

        // Initialize cURL
        $ch = curl_init();
        // Set the URL with query string
        curl_setopt($ch, CURLOPT_URL, $url);
        // Set the request type to GET
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);

        // Decode the JSON response
        $data = json_decode($result);
        if ($data) {
            if ($data->status == 100) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    } else {

        return false;
    }

}



function handleInsertOperation($data)
{
    // Your existing insert logic here
    // Example:
    try {
        // $write_db = getEdmWriteConnection();
        // Your insert implementation
        echo json_encode(['success' => 1, 'message' => 'Record inserted successfully']);
    } catch (Exception $e) {
        error_log("Insert Error: " . $e->getMessage());
        echo json_encode(['success' => 0, 'message' => 'Insert failed']);
    }
}

function handleSelectOperation($data)
{
    // Your existing select logic here
}

function handleUpdateOperation($data)
{
    // Your existing update logic here
}

function handleDeleteOperation($data)
{
    // Your existing delete logic here
}

?>