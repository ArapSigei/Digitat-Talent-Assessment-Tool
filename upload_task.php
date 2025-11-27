<?php
include 'config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'kid') {
    header("Location: login.php");
    exit();
}

if (isset($_POST['upload'])) {
    $user_id = $_SESSION['user_id'];
    $fileType = null;
    $filePath = null;

    if (!empty($_FILES['task_file']['name'])) {
        $fileName = time() . "_" . basename($_FILES['task_file']['name']);
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (in_array($fileExt, ['jpg','jpeg','png','gif'])) {
            $fileType = "image";
            $targetDir = "uploads/images/";
        } elseif (in_array($fileExt, ['mp4','avi','mov','mkv'])) {
            $fileType = "video";
            $targetDir = "uploads/videos/";
        } else {
            die("Invalid file type.");
        }

        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

        $filePath = $targetDir . $fileName;
        if (move_uploaded_file($_FILES['task_file']['tmp_name'], $filePath)) {
            $stmt = $conn->prepare("INSERT INTO tasks (user_id, file_path, file_type) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $user_id, $filePath, $fileType);
            $stmt->execute();
            $msg = "File uploaded successfully!";
        } else {
            $msg = "Error uploading file.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Task</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-lg">
                    <div class="card-header bg-success text-white text-center">
                        <h4>Upload Your Task</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($msg)) echo "<div class='alert alert-info'>$msg</div>"; ?>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label>Select Image/Video</label>
                                <input type="file" name="task_file" class="form-control" required>
                            </div>
                            <button type="submit" name="upload" class="btn btn-success w-100">Upload Task</button>
                        </form>
                        <a href="dashboard.php" class="btn btn-link mt-3">Back to Dashboard</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
