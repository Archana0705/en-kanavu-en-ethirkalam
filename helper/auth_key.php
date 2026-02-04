<?php
// backend/helper/auth_key.php
declare(strict_types=1);

/**
 * Validates X-App-Key & X-App-Name from .env
 * Supports space-separated single values
 */
function validateAppAuth(): void
{
    // -----------------------------------------------------------------
    // 1. Load from .env and trim whitespace
    // -----------------------------------------------------------------
    $rawKey  = trim($_ENV['ALLOWED_APP_KEYS']  ?? '');
    $rawName = trim($_ENV['ALLOWED_APP_NAMES'] ?? '');

    // Split by any whitespace (space, tab, etc.) and filter empty
    $allowedKeys  = array_filter(preg_split('/\s+/', $rawKey));
    $allowedNames = array_filter(preg_split('/\s+/', $rawName));

    // Fast lookup maps
    $keyMap  = array_flip($allowedKeys);
    $nameMap = array_flip($allowedNames);

    // -----------------------------------------------------------------
    // 2. Get headers
    // -----------------------------------------------------------------
    $appKey  = $_SERVER['HTTP_X_APP_KEY']  ?? '';
    $appName = $_SERVER['HTTP_X_APP_NAME'] ?? '';

    // -----------------------------------------------------------------
    // 3. Validate Key
    // -----------------------------------------------------------------
    if ($appKey === '') {
        respondAndDie(404, 'APP Key Missing');
    }
    if (!isset($keyMap[$appKey])) {
        respondAndDie(400, 'Invalid App key');
    }

    // -----------------------------------------------------------------
    // 4. Validate Name
    // -----------------------------------------------------------------
    if ($appName === '') {
        respondAndDie(404, 'APP Name Missing');
    }
    if (!isset($nameMap[$appName])) {
        respondAndDie(400, 'Invalid App Name');
    }

    // Optional: Store for later
    $_SERVER['APP_KEY']  = $appKey;
    $_SERVER['APP_NAME'] = $appName;
}

function respondAndDie(int $code, string $message): never
{
    http_response_code($code);
    echo json_encode([
        'success' => 0,
        'message' => $message
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

// Auto-run
validateAppAuth();
