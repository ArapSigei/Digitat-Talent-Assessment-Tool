<?php
// Start session once at the top
session_start();
include '../config.php';

// Only allow kids (client-side check for initial load)
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'kid') {
    header("Location: ../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Upload Talent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .navbar-custom {
            background-color: #343a40;
        }
        .navbar-custom .navbar-brand,
        .navbar-custom .nav-link {
            color: #ffffff !important;
        }
        .navbar-custom .nav-link:hover {
            color: #adb5bd !important;
        }
    </style>
</head>
<body>
<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container-fluid">
        <a class="navbar-brand" href="home.php">Digital Talent</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="home.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="about.php">About</a>
                </li>
                <?php if (isset($_SESSION['user'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php
                            switch ($_SESSION['role']) {
                                case 'kid':
                                    echo 'dashboard.php';
                                    break;
                                case 'parent':
                                    echo 'parent_dashboard.php';
                                    break;
                                case 'teacher':
                                case 'admin':
                                    echo 'talent/view_submissions.php';
                                    break;
                                default:
                                    echo 'dashboard.php';
                            }
                        ?>">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link" href="contacts.php">Contacts</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-5">
    <h2>Upload Your Talent</h2>
    <form id="uploadForm" action="upload_talent.php" method="post" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="file">Select File (Image/Video)</label>
            <input type="file" name="file" class="form-control" id="file" required>
        </div>
        <button type="submit" class="btn btn-primary">Upload</button>
    </form>
    <div id="message" class="mt-3"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const form = document.getElementById('uploadForm');
form.addEventListener('submit', function(e) {
    e.preventDefault();
    const fileInput = document.getElementById('file');
    if (!fileInput.files || !fileInput.files[0]) {
        document.getElementById('message').innerHTML = '<div class="alert alert-danger">Please select a file to upload.</div>';
        return;
    }

    const formData = new FormData(form);
    for (let pair of formData.entries()) {
        console.log('FormData Entry:', pair[0] + ', ' + (pair[1] instanceof File ? pair[1].name : pair[1]));
    }
    console.log('File Details:', {
        name: fileInput.files[0].name,
        size: fileInput.files[0].size,
        type: fileInput.files[0].type
    });

    fetch('upload_talent.php', {
        method: 'POST',
        body: formData
    })
    .then(res => {
        if (!res.ok) {
            console.log('Response Status:', res.status, res.statusText);
            throw new Error('Network response was not ok: ' + res.statusText);
        }
        return res.json();
    })
    .then(data => {
        document.getElementById('message').innerHTML = data.status === 'success'
            ? `<div class="alert alert-success">${data.message}</div>`
            : `<div class="alert alert-danger">${data.message}</div>`;
        if (data.status === 'success') form.reset();
    })
    .catch(err => {
        console.error('Fetch error:', err);
        document.getElementById('message').innerHTML = '<div class="alert alert-danger">Upload failed. Check console for details.</div>';
    });
});
</script>
</body>
</html>

<?php
include '../config.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'kid') {
    echo json_encode(['status'=>'error','message'=>'Unauthorized']);
    exit();
}

error_log("Received POST request at " . date('Y-m-d H:i:s') . " for user " . ($_SESSION['user'] ?? 'unknown'));
$contentType = $_SERVER['CONTENT_TYPE'] ?? 'not set';
error_log("Content-Type received: $contentType");
if (strpos($contentType, 'multipart/form-data') === false) {
    error_log("Invalid Content-Type detected, but attempting to proceed if file data exists");
}

if (!isset($_FILES) || empty($_FILES)) {
    error_log("No FILES array received. POST: " . print_r($_POST, true) . ", FILES: " . print_r($_FILES, true) . " at " . date('Y-m-d H:i:s') . " for user " . ($_SESSION['user'] ?? 'unknown'));
    echo json_encode(['status'=>'error','message'=>'No file data received']);
    exit();
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
    error_log("No file uploaded. Error code: " . ($_FILES['file']['error'] ?? 'not set') . " at " . date('Y-m-d H:i:s') . " for user " . ($_SESSION['user'] ?? 'unknown'));
    echo json_encode(['status'=>'error','message'=>'No file uploaded']);
    exit();
}

$file = $_FILES['file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => "File too large (server limit)",
        UPLOAD_ERR_FORM_SIZE => "File exceeds form limit",
        UPLOAD_ERR_PARTIAL => "Partial upload",
        UPLOAD_ERR_NO_FILE => "No file uploaded",
        UPLOAD_ERR_NO_TMP_DIR => "Missing temp folder",
        UPLOAD_ERR_CANT_WRITE => "Failed to write file",
        UPLOAD_ERR_EXTENSION => "Upload stopped by extension"
    ];
    $err = $errors[$file['error']] ?? "Unknown error (code: " . $file['error'] . ")";
    error_log("Upload error: $err at " . date('Y-m-d H:i:s') . " for user " . ($_SESSION['user'] ?? 'unknown'));
    echo json_encode(['status'=>'error','message'=>$err]);
    exit();
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska'];
$maxSize = 20 * 1024 * 1024;

if (!in_array($mime, $allowedMimes) || $file['size'] > $maxSize) {
    error_log("Invalid file type or size: mime=$mime, size=" . $file['size'] . " at " . date('Y-m-d H:i:s') . " for user " . ($_SESSION['user'] ?? 'unknown'));
    echo json_encode(['status'=>'error','message'=>'Invalid file type or size too large']);
    exit();
}

$uploadDir = '../uploads/talent/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
    error_log("Created upload directory $uploadDir at " . date('Y-m-d H:i:s'));
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = time() . '_' . rand(1000, 9999) . '.' . $ext;
$filepath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    error_log("Failed to move file to $filepath. Error: " . print_r(error_get_last(), true) . " at " . date('Y-m-d H:i:s') . " for user " . ($_SESSION['user'] ?? 'unknown'));
    echo json_encode(['status'=>'error','message'=>'Failed to move file']);
    exit();
}

$type = strpos($mime, 'video') !== false ? 'video' : 'image';
$relativePath = 'uploads/talent/' . $filename;

if (!isset($_SESSION['user_id'])) {
    error_log("user_id not set in session for user " . ($_SESSION['user'] ?? 'unknown') . " at " . date('Y-m-d H:i:s'));
    echo json_encode(['status'=>'error','message'=>'Session error: user ID not found']);
    exit();
}

$stmt = $conn->prepare("INSERT INTO uploads (user_id, file_path, file_type) VALUES (?, ?, ?)");
$user_id = $_SESSION['user_id'];
$stmt->bind_param("iss", $user_id, $relativePath, $type);
if ($stmt->execute()) {
    error_log("Upload successful for user " . ($_SESSION['user'] ?? 'unknown') . " at " . date('Y-m-d H:i:s'));
    echo json_encode(['status'=>'success','message'=>'File uploaded successfully', 'file_path'=>$relativePath, 'file_type'=>$type]);
} else {
    error_log("Database error: " . $conn->error . " for user " . ($_SESSION['user'] ?? 'unknown') . " at " . date('Y-m-d H:i:s'));
    echo json_encode(['status'=>'error','message'=>'Database error: ' . $conn->error]);
}
$stmt->close();
?>