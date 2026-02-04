<?php
// helper/log_file.php - Simple working version

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

/**
 * Simple API Logger
 */
class ApiLogger
{
    private static array $loggers = [];
    private static array $logPaths = [];
    private static string $baseLogDir;
    private static string $lastTitle = '';

    /**
     * Initialize base log directory
     */
    public static function init(?string $baseDir = null): void
    {
        if ($baseDir === null) {
            $baseDir = __DIR__ . '/../logs';
        }

        self::$baseLogDir = rtrim($baseDir, DIRECTORY_SEPARATOR);
        
        if (!is_dir(self::$baseLogDir)) {
            mkdir(self::$baseLogDir, 0755, true);
        }
    }

    /**
     * Write log message
     */
    public static function writeLog(
        string $level,
        string $message,
        int $httpCode,
        ?float $startTime = null,
        ?float $endTime = null,
        ?string $method = null,
        ?string $path = null,
        array $query = [],
        string $service = 'api',
        array $extra = []
    ): void {
        if (!isset(self::$baseLogDir)) {
            self::init();
        }

        // Calculate response time
        $responseTime = null;
        if ($startTime !== null && $endTime !== null) {
            $responseTime = round($endTime - $startTime, 4);
        }

        // Generate title
        $title = self::generateTitle($path);
        
        // Get logger
        $logger = self::getServiceLogger($service);
        
        // Add title separator
        self::addTitleSeparator($service, $title);
        
        // Build context
        $context = [
            'status' => $httpCode,
            'method' => $method ?? ($_SERVER['REQUEST_METHOD'] ?? ''),
            'endpoint' => $path ?? ($_SERVER['REQUEST_URI'] ?? ''),
            'service' => $service,
            'response_time' => $responseTime,
            'ip' => self::getClientIp(),
            'title' => $title,
        ];

        // Add request data
        if (!empty($_POST)) {
            $context['request_data'] = $_POST;
        }

        // Add query parameters
        if (!empty($query)) {
            $context['query_params'] = $query;
        } elseif (!empty($_GET)) {
            $context['query_params'] = $_GET;
        }

        // Add extra data
        if (!empty($extra)) {
            $context['extra'] = $extra;
        }

        // Log the message
        $normalizedLevel = strtolower($level);
        switch ($normalizedLevel) {
            case 'error':
                $logger->error($message, $context);
                break;
            case 'warning':
                $logger->warning($message, $context);
                break;
            case 'info':
            default:
                $logger->info($message, $context);
                break;
        }
    }

    /**
     * Get or create logger for a specific service
     */
    private static function getServiceLogger(string $service): Logger
    {
        if (isset(self::$loggers[$service])) {
            return self::$loggers[$service];
        }

        // Create service directory
        $serviceDir = self::$baseLogDir . '/' . $service;
        if (!is_dir($serviceDir)) {
            mkdir($serviceDir, 0755, true);
        }

        // Create log file with today's date
        $date = date('Y-m-d');
        $logFile = $serviceDir . '/' . $date . '.log';
        self::$logPaths[$service] = $logFile;

        // Create logger
        $logger = new Logger($service);
        
        // Create handler with custom formatter
        $handler = new StreamHandler($logFile, Logger::DEBUG, true, 0666);
        
        $formatter = new LineFormatter(
            "[%datetime%]\n%level_name%: %message%\n%context%\n%extra%\n\n",
            'Y-m-d H:i:s',
            false,
            true
        );
        
        $handler->setFormatter($formatter);
        $logger->pushHandler($handler);
        
        self::$loggers[$service] = $logger;
        
        return $logger;
    }

    /**
     * Add title separator
     */
    private static function addTitleSeparator(string $service, string $title): void
    {
        if (self::$lastTitle !== $title) {
            self::$lastTitle = $title;
            
            $logFile = self::$logPaths[$service] ?? null;
            if ($logFile) {
                $separator = "\n" . str_repeat('=', 80) . "\n";
                $separator .= str_pad("  {$title}  ", 80, ' ', STR_PAD_BOTH) . "\n";
                $separator .= str_repeat('=', 80) . "\n\n";
                
                file_put_contents($logFile, $separator, FILE_APPEND);
            }
        }
    }

    /**
     * Generate dynamic title from endpoint path
     */
    private static function generateTitle(?string $path): string
    {
        if (empty($path)) {
            return 'API Request';
        }

        $parts = explode('/', trim($path, '/'));
        $endpoint = end($parts);
        $endpoint = preg_replace('/\.php$/', '', $endpoint);
        
        // Convert camelCase to Title Case
        $words = preg_split('/(?=[A-Z])/', $endpoint, -1, PREG_SPLIT_NO_EMPTY);
        
        if (empty($words) || count($words) === 1) {
            $words = explode('_', $endpoint);
        }
        
        if (empty($words) || count($words) === 1) {
            $words = explode('-', $endpoint);
        }
        
        if (empty($words) || count($words) === 1) {
            $words = [ucfirst($endpoint)];
        }
        
        $title = implode(' ', array_map('ucfirst', $words));
        
        return $title ?: 'API Request';
    }

    /**
     * Get client IP address
     */
    private static function getClientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }
        
        return $ip;
    }

    /**
     * Get log file path for a service
     */
    public static function getLogPath(string $service = 'api'): string
    {
        return self::$logPaths[$service] ?? 'not-initialized';
    }
}

// Auto-initialize
ApiLogger::init();
?>