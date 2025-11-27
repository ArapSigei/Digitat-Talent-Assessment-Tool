<?php
session_start();
include 'config.php';

header('Content-Type: application/json');

// Check database connection
if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $error_messages = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write to disk',
        UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
    ];
    $error = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    echo json_encode(['status' => 'error', 'message' => $error_messages[$error] ?? 'Upload error']);
    exit;
}

$file = $_FILES['file'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/webm', 'video/ogg'];
$maxSize = 20 * 1024 * 1024; // 20MB

// Better MIME type validation using finfo
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime_type, $allowedTypes)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Only JPEG, PNG, GIF, MP4, WebM, OGG allowed.']);
    exit;
}

// Validate file size
if ($file['size'] > $maxSize) {
    echo json_encode(['status' => 'error', 'message' => 'File too large. Maximum size is 20MB.']);
    exit;
}

// Validate it's actually an image/video
if (!getimagesize($file['tmp_name']) && !strpos($mime_type, 'video') !== false) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid file format']);
    exit;
}

// Generate secure filename
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$clean_filename = preg_replace('/[^a-zA-Z0-9._-]/', '', basename($file['name']));
$filename = 'upload_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $ext;
$base_folder = __DIR__ . '/uploads/';

// Create appropriate subfolder
$is_video = strpos($mime_type, 'video') !== false;
$folder = $is_video ? 'videos/' : 'images/';
$full_folder = $base_folder . $folder;

if (!is_dir($full_folder)) {
    if (!mkdir($full_folder, 0755, true)) {
        error_log("Failed to create directory: $full_folder");
        echo json_encode(['status' => 'error', 'message' => 'Failed to create upload directory']);
        exit;
    }
}

$destination = $full_folder . $filename;
$file_path_db = 'uploads/' . $folder . $filename; // Relative path for DB
$type = $is_video ? 'video' : 'image';

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    error_log("Failed to move uploaded file to: $destination");
    echo json_encode(['status' => 'error', 'message' => 'Failed to save file. Check permissions.']);
    exit;
}

// Set proper file permissions
chmod($destination, 0644);

// Insert into database - Fixed for your uploads schema (no user_id column)
$stmt = $conn->prepare("INSERT INTO uploads (file_path, file_type, uploaded_at) VALUES (?, ?, NOW())");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    unlink($destination); // Clean up uploaded file
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
    exit;
}

$stmt->bind_param("ss", $file_path_db, $type);
if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    unlink($destination); // Clean up
    $stmt->close();
    echo json_encode(['status' => 'error', 'message' => 'Database save failed']);
    exit;
}

$upload_id = $conn->insert_id;
$stmt->close();

// Get upload details for response
$stmt = $conn->prepare("SELECT id, file_path, file_type, talent_status FROM uploads WHERE id = ?");
$stmt->bind_param("i", $upload_id);
$stmt->execute();
$upload_result = $stmt->get_result();
$upload_data = $upload_result->fetch_assoc();
$stmt->close();

// Get user info securely
$stmt = $conn->prepare("SELECT username, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user_data = $user_result->fetch_assoc();
$stmt->close();

if ($upload_data && $user_data) {
    echo json_encode([
        'status' => 'success',
        'upload' => [
            'id' => $upload_data['id'],
            'file_path' => $upload_data['file_path'],
            'display_url' => $base_url . $upload_data['file_path'] . '?v=' . time(),
            'file_type' => $upload_data['file_type'],
            'talent_status' => $upload_data['talent_status'] ?? null,
            'username' => $user_data['username'],
            'profile_pic' => $user_data['profile_pic'] ?: 'Uploads/profiles/default.png'
        ]
    ]);
} else {
    // Clean up if database records are incomplete
    unlink($destination);
    echo json_encode(['status' => 'error', 'message' => 'Failed to retrieve upload info']);
}

// Clean up temporary file reference
unset($file);
?>