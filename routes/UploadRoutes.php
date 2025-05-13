<?php
// === FILE: UploadRoutes.php ===
switch ($path) {
    case 'upload/image':
        if ($_FILES['image']['error'] == UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/';
            $filename = uniqid() . '-' . basename($_FILES['image']['name']);
            $filePath = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $filePath)) {
                $input = json_decode(file_get_contents("php://input"), true);
                $imagePath = $filePath;
                // Save the file path reference in the database
                (new ORM())->insert('user_images', ['user_id' => $input['user_id'], 'image_path' => $imagePath]);
                echo json_encode(['message' => 'File uploaded successfully', 'image_path' => $imagePath]);
            } else {
                echo json_encode(['error' => 'File upload failed']);
            }
        }
        break;
}
