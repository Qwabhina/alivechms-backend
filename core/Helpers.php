<?php

/** 
 * Helpers Class
 * Provides utility functions for validation, error handling, and date calculations.
 * Includes methods for validating input data, adding CORS headers, calculating date differences,
 * and sending error responses.
 */
class Helpers
{
    /**
     * Validates input data against specified rules.
     * Throws an exception if validation fails.
     * @param array $data The input data to validate.
     * @param array $rules The validation rules, e.g., ['name' => 'required', 'email' => 'email'].
     * @throws Exception if validation fails with specific error messages.
     */
    public static function validateInput($data, $rules)
    {
        $errors = [];
        foreach ($rules as $field => $rule) {
            if (strpos($rule, 'required') !== false && empty($data[$field])) {
                $errors[] = "$field is required";
            }
            if (strpos($rule, 'email') !== false && !filter_var($data[$field], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "$field must be a valid email";
            }
            if (strpos($rule, 'numeric') !== false && !is_numeric($data[$field])) {
                $errors[] = "$field must be numeric";
            }
        }
        if (!empty($errors)) {
            throw new Exception(implode(', ', $errors));
        }
    }
    /**
     * Adds CORS headers to the response.
     * This allows cross-origin requests from any domain.
     */
    public static function addCorsHeaders()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
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
    /**
     * Sends a JSON error response with a specific message and HTTP status code.
     * Sets the HTTP response code and returns a JSON object with the error message.
     * @param string $message The error message to return.
     * @param int $code The HTTP status code to set (default is 400).
     */
    public static function sendFeedback($message, $code = 400, $type = "error")
    {
        http_response_code($code);
        echo json_encode([$type => $message, 'code' => $code]);
        exit;
    }
    /**
     * Logs an error message to a file.
     * Creates the logs directory if it does not exist.
     * @param string $message The error message to log.
     */
    public static function logError($message)
    {
        if (!file_exists($_SERVER['DOCUMENT_ROOT'] . "/logs/app.log")) {
            mkdir($_SERVER['DOCUMENT_ROOT'] . "/logs", 0777, true);
        }
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . "/logs/app.log", date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
    }
}
