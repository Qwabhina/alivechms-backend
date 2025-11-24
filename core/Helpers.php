<?php

/**
 * Helpers – Utility Class
 * Enhanced validation, secure CORS, consistent responses and logging
 * @package AliveChMS\Core
 * @version 1.0.0
 * @author  Benjamin Ebo Yankson
 * @since   2025-11-19
 */

declare(strict_types=1);

class Helpers
{
    /**
     * Send consistent JSON feedback and terminate script
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
     * Secure CORS – change origin in production
     */
    public static function addCorsHeaders(): void
    {
        $allowedOrigin = $_ENV['ALLOWED_ORIGINS'] ?? '*'; // e.g., https://app.alivechms.org
        $allowedMethods = $_ENV['ALLOWED_METHODS'] ?? 'GET, POST';
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
                    case 'email':
                        if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[] = "$field must be a valid email";
                        }
                        break;
                    case 'numeric':
                        if ($value !== null && !is_numeric($value)) {
                            $errors[] = "$field must be numeric";
                        }
                        break;
                    case 'date':
                        if ($value && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                            $errors[] = "$field must be YYYY-MM-DD";
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

        if (!empty($errors)) {
            throw new Exception(implode('; ', $errors));
        }
    }

    /**
     * Secure error logging – never expose path in response
     */
    public static function logError(string $message): void
    {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . '/app.log';
        $timestamp = date('Y-m-d H:i:s');
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $caller = $trace[1]['function'] ?? 'unknown';
        error_log("[$timestamp] $caller: $message" . PHP_EOL, 3, $logFile);
    }

    /**
     * Calculates the quotient and remainder of two numbers.
     * This is used to determine how many times one number can fit into another and what is left over.
     * @param int $divisor The number to be divided.
     * @param int $dividend The number to divide by.
     * @return array An array containing the quotient and remainder.
     */
    private static function getQuotientAndRemainder($divisor, $dividend)
    {
        $quotient = (int)($divisor / $dividend);
        $remainder = $divisor % $dividend;
        return array($quotient, $remainder);
    }
    /**
     * Calculates the difference between the current time and a given timestamp.
     * Returns a human-readable string indicating how long ago the timestamp was.
     * @param int $time The timestamp to compare against the current time.
     * @return string A string indicating the time difference (e.g., "2 days ago" or "1 week, 2 days ago").
     */
    public static function calcDateDifference($time)
    {
        $date_text = "";

        $calc_days = round(abs(time() - $time) / (60 * 60 * 24));
        $calc_mins = round(abs(time() - $time) / 60);

        if ($calc_days > 1) {
            if ($calc_days < 7) {
                $date_text .= $calc_days . " days ago.";
            } else {
                $d_Arr = self::getQuotientAndRemainder($calc_days, 7);
                $wk = $d_Arr[0];
                $dy = $d_Arr[1];

                if ($wk == 1 && $dy == 0) {
                    $date_text .= $wk . " week ago.";
                } elseif ($wk == 1 && $dy == 1) {
                    $date_text .= $wk . " week, " . $dy . " day ago.";
                } elseif ($wk == 1 && $dy > 1) {
                    $date_text .= $wk . " week, " . $dy . " days ago.";
                } elseif ($wk > 1 && $dy > 1) {
                    $date_text .= $wk . " weeks, " . $dy . " days ago.";
                } else {
                    $date_text .= $wk . " weeks ago.";
                }
            }
        } elseif ($calc_days == 0) {
            if ($calc_mins > 1) {
                if ($calc_mins < 60) {
                    $date_text .= $calc_mins . " minutes ago.";
                } elseif ($calc_mins == 60) {
                    $date_text .= "An hour ago.";
                } else {
                    $getHrM = self::getQuotientAndRemainder($calc_mins, 60);
                    $hr = $getHrM[0];
                    $min = $getHrM[1];

                    if ($hr == 1 && $min == 1) {
                        $date_text .= "About an hour ago.";
                    } elseif ($hr == 1 && $min > 1) {
                        $date_text .= $hr . " hour, " . $min . " minutes ago.";
                    } elseif ($hr > 1 && $min == 1) {
                        $date_text .= $hr . " hours, " . $min . " minute ago.";
                    } else {
                        $date_text .= $hr . " hours, " . $min . " minutes ago.";
                    }
                }
            } elseif ($calc_mins == 0) {
                $date_text .= "A few seconds ago.";
            } else {
                $date_text .= $calc_mins . " minute ago.";
            }
        } else {
            $date_text .= "Yesterday";
        }
        return $date_text;
    }
}
