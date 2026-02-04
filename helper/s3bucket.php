<?php
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

 $possiblePaths = [
        __DIR__ . '/../vendor/autoload.php',      
        __DIR__ . '/../../vendor/autoload.php',   
        __DIR__ . '/vendor/autoload.php',         
    ];
// require_once __DIR__ . '/autoload.php';

$autoloadFile = null;
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $autoloadFile = $path;
            break;
        }
    }
    
    if (!$autoloadFile) {
        throw new Exception('autoload.php not found. Checked: ' . implode(', ', $possiblePaths));
    }
    
    require_once $autoloadFile;
// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get filename from request
$filename = $_GET['filename'] ?? '';

// Also accept POST requests
if (empty($filename) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    if (!empty($input)) {
        $data = json_decode($input, true);
        $filename = $data['filename'] ?? '';
    }
}

if (empty($filename)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Filename required']);
    exit;
}

try {
  

    $awsConfig = [
        'version' => 'latest',
        'region'  => 'ap-south-1', 
    ];
        // https://sfdb-elcot-household.s3.ap-south-1.amazonaws.com/
    $s3 = new S3Client($awsConfig);
    $cmd = $s3->getCommand('GetObject', [
        'Bucket' => 'sfdb-elcot-household',
        'Key'    => $filename
    ]);
    // print_r($s3);exit;
    $request = $s3->createPresignedRequest($cmd, '+1 hour');
    $signedUrl = (string) $request->getUri();
    
  
    
    echo json_encode([
        'success' => true,
        'url' => $signedUrl,
        'filename' => $filename,
        'expires_in' => 3600,
        'timestamp' => time()
    ]);
    
} catch (AwsException $e) {
    http_response_code(500);
    error_log("AWS Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'AWS Error: ' . $e->getAwsErrorMessage(),
        'code' => $e->getAwsErrorCode(),
        'message' => 'Check AWS credentials and permissions'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("General Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage(),
        'trace' => (ini_get('display_errors') ? $e->getTraceAsString() : 'Enable display_errors for details')
    ]);
}