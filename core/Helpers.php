<?php

/**
 * Helpers – Central Utility Class
 *
 * Provides standardised JSON responses, secure CORS handling,
 * robust input validation, error logging, and reusable utility functions.
 *
 * All public methods are static and safe to call from anywhere.
 *
 * @package  AliveChMS\Core
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

class Helpers
{
    /**
     * Send standardised JSON response and terminate execution
     *
     * @param string $message Response message
     * @param int    $code    HTTP status code (default 400)
     * @param string $type    Response type: 'success' or 'error' (default 'error')
     * @return never
     */
    public static function sendFeedback(string $message, int $code = 400, string $type = 'error'): never
    {
        http_response_code($code);
        echo json_encode([
            'status'    => $type,
            'message'   => $message,
            'code'      => $code,
            'timestamp' => date('c')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Add secure CORS headers from .env configuration
     *
     * Handles preflight (OPTIONS) requests automatically.
     *
     * @return void
     */
    public static function addCorsHeaders(): void
    {
        $origin   = $_ENV['ALLOWED_ORIGINS']   ?? '*';
        $methods  = $_ENV['ALLOWED_METHODS']  ?? 'GET,POST,PUT,DELETE,OPTIONS';
        $headers  = $_ENV['ALLOWED_HEADERS']  ?? 'Authorization,Content-Type,X-Requested-With';

        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Methods: $methods");
        header("Access-Control-Allow-Headers: $headers");
        header('Access-Control-Allow-Credentials: true');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    /**
     * Validate input data against a ruleset
     *
     * Supported rules:
     * - required
     * - email
     * - numeric
     * - date (YYYY-MM-DD)
     * - phone
     * - max:length
     * - in:value1,value2,...
     *
     * @param array $data  Input data (usually from JSON payload)
     * @param array $rules Associative array of field => rules string
     * @return void
     * @throws Exception On validation failure
     */
    public static function validateInput(array $data, array $rules): void
    {
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $value      = $data[$field] ?? null;
            $ruleList   = explode('|', $ruleString);

            foreach ($ruleList as $rule) {
                $param = null;
                if (str_contains($rule, ':')) {
                    [$rule, $param] = explode(':', $rule, 2);
                }

                switch ($rule) {
                    case 'required':
                        if ($value === null || $value === '') {
                            $errors[] = "$field is required";
                        }
                        break;

                    case 'email':
                        if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[] = "$field must be a valid email address";
                        }
                        break;

                    case 'numeric':
                        if ($value !== null && !is_numeric($value)) {
                            $errors[] = "$field must be numeric";
                        }
                        break;

                    case 'date':
                        if ($value && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                            $errors[] = "$field must be in YYYY-MM-DD format";
                        }
                        break;

                    case 'phone':
                        if ($value && !preg_match('/^\+?[0-9\s\-\(\)]{10,20}$/', $value)) {
                            $errors[] = "$field is not a valid phone number";
                        }
                        break;

                    case 'max':
                        if ($value && is_string($value) && strlen($value) > (int)$param) {
                            $errors[] = "$field must not exceed $param characters";
                        }
                        break;

                    case 'in':
                        $allowed = explode(',', $param ?? '');
                        if ($value && !in_array($value, $allowed, true)) {
                            $errors[] = "$field must be one of: " . implode(', ', $allowed);
                        }
                        break;
                }
            }
        }

        if ($errors !== []) {
            throw new Exception(implode('; ', $errors));
        }
    }

    /**
     * Log error with context to storage/logs/app.log
     *
     * @param string $message Error message
     * @return void
     */
    public static function logError(string $message): void
    {
        $logDir  = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile  = $logDir . '/app.log';
        $timestamp = date('Y-m-d H:i:s');
        $trace    = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller   = $trace[1]['function'] ?? 'unknown';

        error_log("[$timestamp] $caller: $message" . PHP_EOL, 3, $logFile);
    }

    /**
     * Calculate quotient and remainder (internal helper)
     *
     * @param int $divisor
     * @param int $dividend
     * @return array [quotient, remainder]
     */
    private static function getQuotientAndRemainder(int $divisor, int $dividend): array
    {
        $quotient  = (int)($divisor / $dividend);
        $remainder = $divisor % $dividend;
        return [$quotient, $remainder];
    }

    /**
     * Convert timestamp to human-readable "time ago" string
     *
     * Examples:
     * - "A few seconds ago"
     * - "3 minutes ago"
     * - "2 hours, 15 minutes ago"
     * - "1 week, 3 days ago"
     *
     * @param int $timestamp Unix timestamp
     * @return string Human-readable difference
     */
    public static function calcDateDifference(int $timestamp): string
    {
        $diffSeconds = time() - $timestamp;
        if ($diffSeconds < 0) {
            return 'in the future';
        }

        $minutes = (int)($diffSeconds / 60);
        $hours   = (int)($minutes / 60);
        $days    = (int)($hours / 24);

        if ($days > 0) {
            if ($days < 7) {
                return $days === 1 ? 'Yesterday' : "$days days ago";
            }

            [$weeks, $extraDays] = self::getQuotientAndRemainder($days, 7);

            $parts = [];
            if ($weeks === 1) {
                $parts[] = '1 week';
            } elseif ($weeks > 1) {
                $parts[] = "$weeks weeks";
            }
            if ($extraDays === 1) {
                $parts[] = '1 day';
            } elseif ($extraDays > 1) {
                $parts[] = "$extraDays days";
            }

            return implode(', ', $parts) . ' ago';
        }

        if ($hours > 0) {
            $remainingMinutes = $minutes % 60;
            $hourText = $hours === 1 ? '1 hour' : "$hours hours";
            if ($remainingMinutes === 0) {
                return "$hourText ago";
            }
            $minuteText = $remainingMinutes === 1 ? '1 minute' : "$remainingMinutes minutes";
            return "$hourText, $minuteText ago";
        }

        if ($minutes > 0) {
            return $minutes === 1 ? '1 minute ago' : "$minutes minutes ago";
        }

        return 'A few seconds ago';
    }
}