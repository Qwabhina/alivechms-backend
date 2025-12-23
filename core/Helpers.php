<?php

/**
 * Helpers – Central Utility Class
 *
 * Enhanced validation, secure CORS, consistent responses, logging,
 * sanitization, security utilities, and reusable utility functions.
 *
 * - Complete validation rules
 * - Input sanitization
 * - Improved response helpers
 * - Security utilities
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
     * 
     * DEPRECATED: Use sendSuccess() or sendError() instead
     */
    public static function sendFeedback(string $message, int $code = 400, string $type = 'error'): void
    {
        http_response_code($code);
        echo json_encode([
            'status'  => $type,
            'message' => $message,
            'code'    => $code,
            'timestamp' => date('c')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send JSON success response
     * Consistent structure for all success responses
     * 
     * @param mixed  $data    Optional data payload
     * @param int    $code    HTTP status code (default 200)
     * @param string $message Optional success message
     * @return never
     */
    public static function sendSuccess($data = null, int $code = 200, string $message = ''): void
    {
        http_response_code($code);

        $response = [
            'status' => 'success',
            'timestamp' => date('c')
        ];

        if ($message !== null || $message !== '') {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send JSON error response
     * Consistent structure for all error responses
     * 
     * @param string $message Error message
     * @param int    $code    HTTP status code (default 400)
     * @param array  $errors  Optional array of validation errors
     * @return never
     */
    public static function sendError(string $message, int $code = 400, array $errors = []): void
    {
        http_response_code($code);

        $response = [
            'status' => 'error',
            'message' => $message,
            'code' => $code,
            'timestamp' => date('c')
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Add secure CORS headers from .env configuration
     * Handles preflight (OPTIONS) requests automatically.
     * 
     * @return void
     */
    public static function addCorsHeaders(): void
    {
        $allowedOrigin = $_ENV['ALLOWED_ORIGINS'] ?? '*';
        $allowedMethods = $_ENV['ALLOWED_METHODS'] ?? 'GET, POST, PUT, DELETE, OPTIONS';
        $allowedHeaders = $_ENV['ALLOWED_HEADERS'] ?? 'Authorization, Content-Type, X-Requested-With';

        header("Access-Control-Allow-Origin: $allowedOrigin");
        header("Access-Control-Allow-Methods: $allowedMethods");
        header("Access-Control-Allow-Headers: $allowedHeaders");
        header('Access-Control-Allow-Credentials: true');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    /**
     * Robust input validation with support for common rules
     * 
     * @param array $data  Input data to validate (e.g. $_POST)
     * @param array $rules Validation rules in format:
     *                    'field_name' => 'rule1|rule2:param|rule3'
     * @return void
     * @throws Exception on validation failure with detailed messages
     */
    public static function validateInput(array $data, array $rules): void
    {
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? null;
            $rulesList = explode('|', $ruleString);

            foreach ($rulesList as $rule) {
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

                    case 'nullable':
                        // Skip other validations if null and nullable
                        if ($value === null) {
                            break 2; // Break out of both switch and foreach
                        }
                        break;

                    case 'string':
                        if ($value !== null && !is_string($value)) {
                            $errors[] = "$field must be a string";
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

                    case 'integer':
                        if ($value !== null && !filter_var($value, FILTER_VALIDATE_INT)) {
                            $errors[] = "$field must be an integer";
                        }
                        break;

                    case 'float':
                        if ($value !== null && !filter_var($value, FILTER_VALIDATE_FLOAT)) {
                            $errors[] = "$field must be a decimal number";
                        }
                        break;

                    case 'boolean':
                        if ($value !== null && !in_array($value, [true, false, 0, 1, '0', '1'], true)) {
                            $errors[] = "$field must be a boolean";
                        }
                        break;

                    case 'date':
                        if ($value && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                            $errors[] = "$field must be in YYYY-MM-DD format";
                        }
                        // Additional check: valid date
                        if ($value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                            $parts = explode('-', $value);
                            if (!checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) {
                                $errors[] = "$field is not a valid date";
                            }
                        }
                        break;

                    case 'datetime':
                        if ($value && !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
                            $errors[] = "$field must be in YYYY-MM-DD HH:MM:SS format";
                        }
                        break;

                    case 'time':
                        if ($value && !preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
                            $errors[] = "$field must be in HH:MM:SS format";
                        }
                        break;

                    case 'phone':
                        // Ghana phone number validation (0XX XXXX XXXX or +233 XX XXXX XXXX)
                        if ($value && !preg_match('/^(\+?233|0)[2-5][0-9]{8}$/', preg_replace('/[\s\-\(\)]/', '', $value))) {
                            $errors[] = "$field is not a valid Ghana phone number";
                        }
                        break;

                    case 'url':
                        if ($value && !filter_var($value, FILTER_VALIDATE_URL)) {
                            $errors[] = "$field must be a valid URL";
                        }
                        break;

                    case 'alpha':
                        if ($value && !preg_match('/^[a-zA-Z]+$/', $value)) {
                            $errors[] = "$field must contain only letters";
                        }
                        break;

                    case 'alphanumeric':
                        if ($value && !preg_match('/^[a-zA-Z0-9]+$/', $value)) {
                            $errors[] = "$field must contain only letters and numbers";
                        }
                        break;

                    case 'alphanumeric_underscore':
                        if ($value && !preg_match('/^[a-zA-Z0-9_]+$/', $value)) {
                            $errors[] = "$field must contain only letters, numbers, and underscores";
                        }
                        break;

                    case 'alphanumeric_dash':
                        if ($value && !preg_match('/^[a-zA-Z0-9\-_]+$/', $value)) {
                            $errors[] = "$field must contain only letters, numbers, dashes, and underscores";
                        }
                        break;

                    case 'max':
                        if ($value && is_string($value) && strlen($value) > (int)$param) {
                            $errors[] = "$field must not exceed $param characters";
                        }
                        break;

                    case 'min':
                        if ($value && is_string($value) && strlen($value) < (int)$param) {
                            $errors[] = "$field must be at least $param characters";
                        }
                        break;

                    case 'min_value':
                        if ($value !== null && is_numeric($value) && (float)$value < (float)$param) {
                            $errors[] = "$field must be at least $param";
                        }
                        break;

                    case 'max_value':
                        if ($value !== null && is_numeric($value) && (float)$value > (float)$param) {
                            $errors[] = "$field must not exceed $param";
                        }
                        break;

                    case 'between':
                        if ($value && strpos($param, ',') !== false) {
                            [$min, $max] = explode(',', $param);
                            $length = strlen($value);
                            if ($length < (int)$min || $length > (int)$max) {
                                $errors[] = "$field must be between $min and $max characters";
                            }
                        }
                        break;

                    case 'in':
                        $allowed = explode(',', $param ?? '');
                        if ($value && !in_array($value, $allowed, true)) {
                            $errors[] = "$field must be one of: " . implode(', ', $allowed);
                        }
                        break;

                    case 'not_in':
                        $disallowed = explode(',', $param ?? '');
                        if ($value && in_array($value, $disallowed, true)) {
                            $errors[] = "$field cannot be one of: " . implode(', ', $disallowed);
                        }
                        break;

                    case 'regex':
                        if ($value && $param && !preg_match($param, $value)) {
                            $errors[] = "$field format is invalid";
                        }
                        break;

                    case 'confirmed':
                        // For password confirmation: 'password' => 'required|confirmed'
                        // Expects 'password_confirmation' field
                        $confirmField = $field . '_confirmation';
                        if (!isset($data[$confirmField]) || $value !== $data[$confirmField]) {
                            $errors[] = "$field confirmation does not match";
                        }
                        break;

                    case 'json':
                        if ($value) {
                            json_decode($value);
                            if (json_last_error() !== JSON_ERROR_NONE) {
                                $errors[] = "$field must be valid JSON";
                            }
                        }
                        break;

                    case 'array':
                        if ($value !== null && !is_array($value)) {
                            $errors[] = "$field must be an array";
                        }
                        break;

                    case 'unique':
                        // Format: unique:table,column
                        if ($param && strpos($param, ',') !== false) {
                            [$table, $column] = explode(',', $param);
                            $orm = new ORM();
                            $existing = $orm->getWhere($table, [$column => $value]);
                            if (!empty($existing)) {
                                $errors[] = "$field already exists";
                            }
                        }
                        break;

                    case 'exists':
                        // Format: exists:table,column
                        if ($param && strpos($param, ',') !== false) {
                            [$table, $column] = explode(',', $param);
                            $orm = new ORM();
                            $existing = $orm->getWhere($table, [$column => $value]);
                            if (empty($existing)) {
                                $errors[] = "$field does not exist";
                            }
                        }
                        break;

                    case 'file':
                        // File upload validation
                        if ($value && is_array($value)) {
                            if (!isset($value['error']) || $value['error'] !== UPLOAD_ERR_OK) {
                                $errors[] = "$field upload failed";
                            }
                        }
                        break;

                    case 'file_size':
                        // Max file size in KB: file_size:1024
                        if ($value && is_array($value) && isset($value['size'])) {
                            $maxSize = (int)$param * 1024; // Convert KB to bytes
                            if ($value['size'] > $maxSize) {
                                $errors[] = "$field must not exceed " . $param . "KB";
                            }
                        }
                        break;

                    case 'file_type':
                        // Allowed file types: file_type:jpg,png,pdf
                        if ($value && is_array($value) && isset($value['type'])) {
                            $allowed = explode(',', $param ?? '');
                            $ext = strtolower(pathinfo($value['name'], PATHINFO_EXTENSION));
                            if (!in_array($ext, $allowed, true)) {
                                $errors[] = "$field must be one of: " . implode(', ', $allowed);
                            }
                        }
                        break;
                }
            }
        }

        if (!empty($errors)) {
            throw new Exception(implode('; ', $errors));
        }
    }

    /**
     * Sanitize input data based on type
     * Prevent XSS and other injection attacks
     * 
     * @param mixed $data Data to sanitize
     * @param string $type Type of sanitization
     * @return mixed Sanitized data
     */
    public static function sanitize($data, string $type = 'string')
    {
        if (is_array($data)) {
            return array_map(function ($item) use ($type) {
                return self::sanitize($item, $type);
            }, $data);
        }

        if ($data === null) {
            return null;
        }

        switch ($type) {
            case 'string':
                return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');

            case 'raw_string':
                return trim($data);

            case 'email':
                return filter_var($data, FILTER_SANITIZE_EMAIL);

            case 'int':
            case 'integer':
                return filter_var($data, FILTER_SANITIZE_NUMBER_INT);

            case 'float':
            case 'decimal':
                return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

            case 'url':
                return filter_var($data, FILTER_SANITIZE_URL);

            case 'html':
                // For rich text content - allow safe HTML tags only
                $allowed = '<p><br><b><i><u><strong><em><ul><ol><li><a><h1><h2><h3><h4><h5><h6><blockquote>';
                return strip_tags($data, $allowed);

            case 'filename':
                // Remove dangerous characters from filenames
                return preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $data);

            case 'alphanum':
                return preg_replace('/[^a-zA-Z0-9]/', '', $data);

            case 'slug':
                $data = strtolower(trim($data));
                $data = preg_replace('/[^a-z0-9\-]/', '-', $data);
                return preg_replace('/-+/', '-', $data);

            default:
                return $data;
        }
    }

    /**
     * Log error messages with context
     * 
     * @param string $message Error message
     * @return void
     */
    public static function logError(string $message): void
    {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/app-' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $trace[1]['function'] ?? 'unknown';
        $file = basename($trace[0]['file'] ?? 'unknown');
        $line = $trace[0]['line'] ?? 0;

        $logMessage = "[$timestamp] [$caller] $message (in $file:$line)" . PHP_EOL;
        error_log($logMessage, 3, $logFile);

        // Also log to PHP error log in production
        if ($_ENV['APP_ENV'] === 'production') {
            error_log($logMessage);
        }
    }

    /**
     * Log informational messages
     * 
     * @param string $message Info message
     * @return void
     */
    public static function logInfo(string $message): void
    {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/info-' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        error_log("[$timestamp] INFO: $message" . PHP_EOL, 3, $logFile);
    }

    /**
     * Calculate quotient and remainder
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
     * Calculate date difference in human-readable format
     * 
     * @param int $time Unix timestamp to compare with current time
     * @return string Human-readable date difference
     */
    public static function calcDateDifference($time): string
    {
        $date_text = "";
        $calc_days = round(abs(time() - $time) / (60 * 60 * 24));
        $calc_mins = round(abs(time() - $time) / 60);

        if ($calc_days > 1) {
            if ($calc_days < 7) {
                $date_text = $calc_days . " days ago";
            } else {
                $weeks = floor($calc_days / 7);
                $days = $calc_days % 7;

                if ($weeks == 1 && $days == 0) {
                    $date_text = "1 week ago";
                } elseif ($weeks == 1 && $days == 1) {
                    $date_text = "1 week, 1 day ago";
                } elseif ($weeks == 1) {
                    $date_text = "1 week, $days days ago";
                } elseif ($days > 1) {
                    $date_text = "$weeks weeks, $days days ago";
                } elseif ($days == 1) {
                    $date_text = "$weeks weeks, 1 day ago";
                } else {
                    $date_text = "$weeks weeks ago";
                }
            }
        } elseif ($calc_days == 1) {
            $date_text = "Yesterday";
        } else {
            if ($calc_mins > 1) {
                if ($calc_mins < 60) {
                    $date_text = $calc_mins . " minutes ago";
                } elseif ($calc_mins == 60) {
                    $date_text = "An hour ago";
                } else {
                    $hours = floor($calc_mins / 60);
                    $mins = $calc_mins % 60;

                    if ($hours == 1 && $mins <= 1) {
                        $date_text = "About an hour ago";
                    } elseif ($hours == 1) {
                        $date_text = "1 hour, $mins minutes ago";
                    } else {
                        $date_text = "$hours hours" . ($mins > 0 ? ", $mins minutes" : "") . " ago";
                    }
                }
            } elseif ($calc_mins == 1) {
                $date_text = "1 minute ago";
            } else {
                $date_text = "Just now";
            }
        }

        return $date_text;
    }

    /**
     * Generate random string (for tokens, passwords, etc.)
     * 
     * @param int $length Length of the random string (default 32)
     * @return string Generated random string
     */
    public static function generateRandomString(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Check if request is AJAX (useful for different response formats)
     * 
     * @return bool True if AJAX request, false otherwise
     */
    public static function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Get client IP address (handles proxies and load balancers)
     * 
     * @return string Client IP address
     */
    public static function getClientIp(): string
    {
        $ipKeys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER)) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $ip;
                    }
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Clear session data securely
     * 
     * @return void
     */
    public static function clearSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
    }
}