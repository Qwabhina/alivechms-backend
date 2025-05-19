<?php
class Helpers
{
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

    public static function sendError($message, $code = 400)
    {
        http_response_code($code);
        echo json_encode(['error' => $message, 'code' => $code]);
        exit;
    }

    public static function addCorsHeaders()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
    }

    public static function logError($message)
    {
        if (!file_exists($_SERVER['DOCUMENT_ROOT'] . "/logs/app.log")) {
            mkdir($_SERVER['DOCUMENT_ROOT'] . "/logs", 0777, true);
        }
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . "/logs/app.log", date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
    }
}
