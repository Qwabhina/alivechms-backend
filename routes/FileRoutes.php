<?php
// === FILE: FileRoutes.php ===
switch ($path) {
    case 'download/image':
        $input = json_decode(file_get_contents("php://input"), true);
        $imagePath = $input['image_path'] ?? '';
        if (file_exists($imagePath)) {
            header('Content-Type: ' . mime_content_type($imagePath));
            readfile($imagePath);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'File not found']);
        }
        break;
}
