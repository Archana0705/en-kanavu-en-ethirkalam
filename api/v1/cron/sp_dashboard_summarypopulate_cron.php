<?php
date_default_timezone_set('Asia/Kolkata');

// Security: Validate these headers match your expected values
$expectedAppName = 'householdsurvey';
$expectedAppKey = '@Sfdb!';

if (!isset($_SERVER['HTTP_X_APP_NAME']) || $_SERVER['HTTP_X_APP_NAME'] !== $expectedAppName ||
    !isset($_SERVER['HTTP_X_APP_KEY']) || $_SERVER['HTTP_X_APP_KEY'] !== $expectedAppKey) {
    http_response_code(403);
    die('Unauthorized access');
}

require_once('../../../helper/header.php');
require_once('../../../config/cron_database.php');

// Elastic Beanstalk Production Paths (Use ENVIRONMENT VARIABLES)
$baseDir = __DIR__;
$logBase = $_SERVER['DOCUMENT_ROOT'] . '/logs/cron/';
$lockBase = $_SERVER['DOCUMENT_ROOT'] . '/logs/cron_locks/';

// Or use Elastic Beanstalk environment variables for flexibility:
// $logBase = getenv('CRON_LOG_DIR') ?: '/var/app/current/logs/cron/';

$logFile = $logBase . 'dashboard_summarypopulate.log';
$lockFile = $lockBase . 'dashboard_summarypopulate.lock';

// Create local directories, not URLs
if (!is_dir($lockBase)) {
    if (!mkdir($lockBase, 0755, true)) {
        // Fallback to /tmp for lock files
        $lockBase = '/tmp/cron_locks/';
        $lockFile = $lockBase . 'dashboard_summarypopulate.lock';
        @mkdir($lockBase, 0755, true);
    }
}

if (!is_dir($logBase)) {
    if (!mkdir($logBase, 0755, true)) {
        // Fallback to /tmp for logs if necessary
        $logBase = '/tmp/cron_logs/';
        $logFile = $logBase . 'dashboard_summarypopulate.log';
        @mkdir($logBase, 0755, true);
    }
}

// Lock file mechanism (corrected)
$fp = @fopen($lockFile, 'c');
if (!$fp) {
    // Emergency fallback to temporary file
    $lockFile = '/tmp/dashboard_summarypopulate' . date('Y-m-d') . '.lock';
    $fp = @fopen($lockFile, 'c');
}

if ($fp) {
    if (!flock($fp, LOCK_EX | LOCK_NB)) {
        logMessage("Another instance is already running. Exiting.", $logFile);
        fclose($fp);
        exit(0); // Exit cleanly
    }
} else {
    logMessage("WARNING: Running without lock file due to permissions.", $logFile);
}

// Clean old logs (adjust path for production)
cleanOldLogs($logBase, 7);

logMessage("Cron job started at " . date('Y-m-d H:i:s'), $logFile);

try {
    // Validate database connection
    if (!isset($cron_db_connect) || !$cron_db_connect) {
        throw new Exception("Database connection not established");
    }
    
    // Test connection
    $cron_db_connect->query("SELECT 1");
    
    logMessage("Preparing to execute procedure: sp_dashboard_summarypopulate", $logFile);
    
    $benfi_count_sql = "CALL public.sp_dashboard_summarypopulate(null);";
    $benefi_count_stmt = $cron_db_connect->prepare($benfi_count_sql);
    
    if (!$benefi_count_stmt) {
        throw new Exception("Failed to prepare SQL statement: " . 
                          json_encode($cron_db_connect->errorInfo()));
    }
    
    logMessage("Procedure prepared. Executing now.", $logFile);
    
    $startTime = microtime(true);
    $result = $benefi_count_stmt->execute();
    $executionTime = round(microtime(true) - $startTime, 2);
    
    if ($result) {
        logMessage("Procedure executed successfully in {$executionTime} seconds.", $logFile);
    } else {
        throw new Exception("Procedure execution failed: " . 
                          json_encode($benefi_count_stmt->errorInfo()));
    }
    
} catch (Exception $e) {
    $errorMessage = "CRITICAL ERROR: " . $e->getMessage();
    logMessage($errorMessage, $logFile);
    
    // Also log to Elastic Beanstalk's system logs
    error_log($errorMessage);
    
    // You might want to send an alert here (email, SNS, etc.)
    // sendAlert($errorMessage);
    
} finally {
    // Clean up resources
    if (isset($benefi_count_stmt) && $benefi_count_stmt) {
        $benefi_count_stmt->closeCursor();
    }
    
    if (isset($cron_db_connect) && $cron_db_connect) {
        $cron_db_connect = null;
        logMessage("Database connection closed.", $logFile);
    }
    
    // Release lock
    if ($fp) {
        flock($fp, LOCK_UN);
        fclose($fp);
        // Optionally clean up old lock files
        // if (time() - filemtime($lockFile) > 3600) { @unlink($lockFile); }
    }
    
    logMessage("Cron job completed at " . date('Y-m-d H:i:s'), $logFile);
    logMessage("==============================================\n", $logFile);
}

function cleanOldLogs($logDir, $days = 7) {
    if (!is_dir($logDir)) return;
    
    $currentTime = time();
    $logFiles = glob($logDir . "*.log");
    
    foreach ($logFiles as $file) {
        if (is_file($file) && filemtime($file) && 
            ($currentTime - filemtime($file)) > ($days * 86400)) {
            @unlink($file);
        }
    }
}

function logMessage($message, $logFile) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message\n";
    
    // Write to local file
    $file = @fopen($logFile, 'a');
    if ($file) {
        fwrite($file, $logEntry);
        fclose($file);
    } else {
        // Fallback to error_log which goes to Elastic Beanstalk logs
        error_log("CRON: $message");
    }
    
    // Also echo for CLI execution visibility
    if (php_sapi_name() === 'cli') {
        echo $logEntry;
    }
}
?>