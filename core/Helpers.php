<?php

/** Helpers Class
 * This class provides utility functions for input validation, error handling, CORS headers, and logging.
 * It is designed to be used across the application to ensure consistent behavior and reduce code duplication.
 * @package Helpers
 * @version 1.0 
 */
class Helpers
{
    /**
     * Validates input data against specified rules.
     * Throws an exception if validation fails.
     * @param array $data The input data to validate.
     * @param array $rules The validation rules.
     * @throws Exception If validation fails.
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
     * Sends a JSON response with an error message.
     * @param string $message The error message.
     * @param int $code The HTTP status code (default is 400).
     */
    public static function sendError($message, $code = 400)
    {
        http_response_code($code);
        echo json_encode(['error' => $message, 'code' => $code]);
        exit;
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
     * Logs an error message to a log file.
     * Creates the log file if it does not exist.
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
