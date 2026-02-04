<?php
ob_start(); // Prevent early output issues
$allowed_origins = [
    'https://staging.d14ab8b5xeqqw5.amplifyapp.com/',
    'https://uks.tn.gov.in',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Expose-Headers: X-Captcha-Key");
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-App-Key, X-App-Name, X-Captcha-Key");
    http_response_code(204);
    exit;
}
$rootDir = dirname(__DIR__, 3);
require $rootDir . '/vendor/autoload.php';

// ----- CONFIGURATION -----
$captcha_font = __DIR__ . '/monofonto.ttf'; 
$redis_host = '172.16.136.99';
$redis_port = 6379;
$captcha_length = 6;
$captcha_width = 130;
$captcha_height = 40;
$captcha_lifetime_seconds = 300; // 5 mins

$possible_captcha_letters = 'bcdfghjkmnpqrstvwxyz23456789';

$random_captcha_dots = 15;
$random_captcha_lines = 5;

$gradient_start = [43, 37, 216];    
$gradient_end = [39, 5, 96];        
$font_color_hex = "0x00e7ff";       

// Padding configuration
$padding = 3; // 3px padding as requested
$border_radius = 8; // Smooth radius

if (!file_exists($captcha_font)) {
    error_log("CAPTCHA font missing: $captcha_font");
    http_response_code(400);
    echo json_encode(['error' => 'CAPTCHA font missing']);
    exit;
}

// ----- REDIS CONNECTION -----
try {
    $redis = new Predis\Client([
        'scheme' => 'tcp',
        'host'   => $redis_host,
        'port'   => $redis_port,
        'timeout'  => 1.0,
        'username' => 'hhredisadmin',
        'password' => 'iDEgyEhkeeK',
    ]);
    $redis->ping();
} catch (Exception $e) {
    error_log("Redis connection failed: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

// ----- GENERATE CAPTCHA CODE -----
$captcha_code = '';
$count = 0;
while ($count < $captcha_length) { 
    $captcha_code .= substr($possible_captcha_letters, mt_rand(0, strlen($possible_captcha_letters)-1), 1);
    $count++;
}

// Generate unique CAPTCHA key
$captcha_key = uniqid('captcha_', true);

// ----- STORE CAPTCHA IN REDIS FIRST -----
try {
    $redis->setex("captcha:$captcha_key", $captcha_lifetime_seconds, $captcha_code);
} catch (Exception $e) {
    error_log("Failed to store CAPTCHA in Redis: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'Failed to store CAPTCHA']);   
    exit;
}

// ----- CREATE CAPTCHA IMAGE -----
$captcha_font_size = $captcha_height * 0.64;
$captcha_image = @imagecreate($captcha_width, $captcha_height);

if (!$captcha_image) {
    error_log('Image creation failed');
    // Clean up Redis entry if image creation fails
    try {
        $redis->del("captcha:$captcha_key");
    } catch (Exception $e) {
        error_log("Failed to clean up Redis: " . $e->getMessage());
    }
    http_response_code(400);
    echo json_encode(['error' => 'Image creation failed']);
    exit;
}

// Calculate content area (inside padding)
$content_x1 = $padding;
$content_y1 = $padding;
$content_x2 = $captcha_width - $padding - 1;
$content_y2 = $captcha_height - $padding - 1;
$content_width = $content_x2 - $content_x1;
$content_height = $content_y2 - $content_y1;

$white = imagecolorallocate($captcha_image, 151, 157, 166);
imagefill($captcha_image, 0, 0, $white);

for ($i = 0; $i < $captcha_height; $i++) {
    // Reverse ratio for bottom-to-top gradient
    $ratio = 1 - ($i / $captcha_height);
    $r = (int)(10 + (180 - 10) * $ratio);   // 10 → 180
    $g = (int)(60 + (220 - 60) * $ratio);   // 60 → 220
    $b = (int)(120 + (255 - 120) * $ratio); // 120 → 255

    $line_color = imagecolorallocate($captcha_image, $r, $g, $b);
    imageline($captcha_image, 0, $i, $captcha_width, $i, $line_color);
}



// Convert font color
$array_text_color = hextorgb($font_color_hex);
// $captcha_text_color = imagecolorallocate($captcha_image, $array_text_color['red'], $array_text_color['green'], $array_text_color['blue']);
$captcha_text_color = imagecolorallocate($captcha_image, 51, 32, 140);

// Border color - light blue to complement the gradient
// $border_color = imagecolorallocate($captcha_image, 0, 231, 255); // #00e7ff
$border_color = imagecolorallocate($captcha_image, 255, 255, 255); // white

// Draw smooth rounded rectangle
drawSmoothRoundedRectangle(
    $captcha_image,
    $content_x1,
    $content_y1,
    $content_x2,
    $content_y2,
    $border_radius,
    $border_color
);

// Generate subtle background pattern
for ($count = 0; $count < $random_captcha_dots; $count++) {
    $dot_color = imagecolorallocatealpha($captcha_image, 
        23, 63, 155, 
        mt_rand(80, 100) // Very transparent
    );
    $dot_size = mt_rand(1, 2);
    imagefilledellipse($captcha_image, 
        mt_rand($content_x1 + 5, $content_x2 - 5),
        mt_rand($content_y1 + 5, $content_y2 - 5),
        $dot_size, 
        $dot_size, 
        $dot_color
    );
}
// ----- PERFECT TEXT CENTERING - SIMPLIFIED -----
$text_box = imagettfbbox($captcha_font_size, 0, $captcha_font, $captcha_code);
if ($text_box === false) {
    error_log('Text bounding box calculation failed');
    imagedestroy($captcha_image);
    try {
        $redis->del("captcha:$captcha_key");
    } catch (Exception $e) {
        error_log("Failed to clean up Redis: " . $e->getMessage());
    }
    http_response_code(400);
    echo json_encode(['error' => 'Text rendering failed']);
    exit;
}

$text_width = abs($text_box[4] - $text_box[6]); // Right - Left
$text_height = abs($text_box[3] - $text_box[5]); // Bottom - Top

$x = (int)(($captcha_width - $text_width) / 2);
$y = (int)(($captcha_height + $text_height) / 2);
$x = max(5, min($x, $captcha_width - $text_width - 5));
$y = max($text_height + 5, min($y, $captcha_height - 5));

error_log("Text position - X: $x, Y: $y, Width: $text_width, Height: $text_height");
$shadow_color = imagecolorallocatealpha($captcha_image, 0, 0, 0, 100);
$textResult = imagettftext($captcha_image, $captcha_font_size, 0, $x, $y, $captcha_text_color, $captcha_font, $captcha_code);

if ($textResult === false) {
    error_log('Text rendering failed - imagettftext returned false');
    // Try alternative positioning
    $x = 10;
    $y = 30;
    $textResult = imagettftext($captcha_image, $captcha_font_size, 0, $x, $y, $captcha_text_color, $captcha_font, $captcha_code);
    
    if ($textResult === false) {
        error_log('Alternative text rendering also failed');
        imagedestroy($captcha_image);
        try {
            $redis->del("captcha:$captcha_key");
        } catch (Exception $e) {
            error_log("Failed to clean up Redis: " . $e->getMessage());
        }
        http_response_code(400);
        echo json_encode(['error' => 'Text rendering failed completely']);
        exit;
    }
}

// ----- OUTPUT IMAGE AS BASE64 -----
ob_start();
$imageResult = imagejpeg($captcha_image, null, 100);
if ($imageResult === false) {
    error_log('JPEG image creation failed');
    ob_end_clean();
    imagedestroy($captcha_image);
    try {
        $redis->del("captcha:$captcha_key");
    } catch (Exception $e) {
        error_log("Failed to clean up Redis: " . $e->getMessage());
    }
    http_response_code(400);
    echo json_encode(['error' => 'Image output failed']);
    exit;
}
$imageData = ob_get_clean();

// DEBUG: Check if we have image data
if (empty($imageData)) {
    error_log('Image data is empty after ob_get_clean()');
    http_response_code(400);
    echo json_encode(['error' => 'Generated image data is empty']);
    exit;
}

$base64Encoded = base64_encode($imageData);
$dataUri = 'data:image/jpeg;base64,' . $base64Encoded;

// DEBUG: Log success
error_log("CAPTCHA generated successfully - Code: $captcha_code, Key: $captcha_key");

// ----- ENCRYPT CAPTCHA CODE -----
$secretKey = 'OFDNISIBINLIDRGINSILVHNIGIOCHSIOHBCHOEVPJWHOINHTC';
$encryptedCode = encrypt($captcha_code, $secretKey);

// ----- SET HEADERS AND OUTPUT JSON -----
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Captcha-Key: ' . $captcha_key);

// Output JSON with Data URI and CAPTCHA key
echo json_encode([
    'datauri' => $dataUri,
    'code' => $encryptedCode,
    'captcha_key' => $captcha_key,
    'debug_code' => $captcha_code // Remove this in production
]);

// Free up memory
imagedestroy($captcha_image);

// ----- HELPER FUNCTIONS -----
function hextorgb($hexstring) {
    // Remove '0x' prefix if present
    $hexstring = str_replace('0x', '', $hexstring);
    $integar = hexdec($hexstring);
    return [
        "red" => 0xFF & ($integar >> 0x10), 
        "green" => 0xFF & ($integar >> 0x8), 
        "blue" => 0xFF & $integar
    ];
}

function encrypt($plaintext, $key) {
    // Define the cipher method
    $cipherMethod = 'AES-256-CBC';

    // Generate an initialization vector (IV) for encryption
    $ivLength = openssl_cipher_iv_length($cipherMethod);
    $iv = openssl_random_pseudo_bytes($ivLength);

    // Encrypt the plaintext
    $ciphertext = openssl_encrypt($plaintext, $cipherMethod, $key, 0, $iv);

    // Encode the IV and ciphertext together in Base64 format
    return base64_encode($iv . $ciphertext);
}

function drawSmoothRoundedRectangle($img, $x1, $y1, $x2, $y2, $radius, $color) {
    // Draw the main rectangle (excluding corners)
    imagefilledrectangle($img, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
    imagefilledrectangle($img, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
    
    // Draw the four corners
    imagefilledarc($img, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, 180, 270, $color, IMG_ARC_PIE);
    imagefilledarc($img, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, 270, 360, $color, IMG_ARC_PIE);
    imagefilledarc($img, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, 90, 180, $color, IMG_ARC_PIE);
    imagefilledarc($img, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, 0, 90, $color, IMG_ARC_PIE);
}

ob_end_flush();
?>