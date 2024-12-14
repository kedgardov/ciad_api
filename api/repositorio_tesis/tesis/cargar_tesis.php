<?php
// upload.php

// Set headers to allow CORS (if the frontend and backend are on different domains)
//header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// Initialize the response array with default values
$response = [
    'success' => false,
    'message' => '',
    'id' => 0, // For now, return 0; you can update this later with the actual ID
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if the file was uploaded without errors
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        // Get file details
        $fileTmpPath = $_FILES['file']['tmp_name'];
        $fileName    = $_FILES['file']['name'];
        $fileSize    = $_FILES['file']['size'];
        $fileType    = $_FILES['file']['type'];
        $fileError   = $_FILES['file']['error'];

        // Extract file extension
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Set allowed file types
        $allowedExtensions = ['pdf'];

        if (in_array($fileExtension, $allowedExtensions)) {
            // Define upload directory (make sure this directory exists and is writable)
            $uploadFileDir = './lista_tesis/';
            // Create the directory if it doesn't exist
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }

            // Sanitize the file name to prevent directory traversal attacks
            $newFileName = uniqid() . '_' . basename($fileName);
            $dest_path = $uploadFileDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $response['success'] = true;
                $response['message'] = 'File uploaded successfully.';
                // For now, 'id' remains 0; later you can set it to a meaningful value
            } else {
                $response['message'] = 'Error moving the uploaded file.';
            }
        } else {
            $response['message'] = 'Upload failed. Allowed file types: ' . implode(',', $allowedExtensions);
        }
    } else {
        // Handle different upload errors
        $error_message = 'Unknown upload error.';
        if (isset($_FILES['file']['error'])) {
            switch ($_FILES['file']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $error_message = 'File is too large.';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $error_message = 'File was only partially uploaded.';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $error_message = 'No file was uploaded.';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $error_message = 'Missing a temporary folder.';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $error_message = 'Failed to write file to disk.';
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $error_message = 'File upload stopped by extension.';
                    break;
            }
        } else {
            $error_message = 'No file was uploaded.';
        }
        $response['message'] = $error_message;
    }
} else {
    $response['message'] = 'Invalid request method.';
}

// Return the response as JSON
echo json_encode($response);
