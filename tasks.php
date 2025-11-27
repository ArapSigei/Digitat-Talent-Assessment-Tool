<?php
include 'config.php';
session_start();

// Only allow teacher, parent, admin
if (!in_array($_SESSION['role'], ['teacher','parent','admin'])) {
    header("Location: login.php");
    exit();
}

$result = $conn->query("SELECT t.*, u.username FROM tasks t JOIN users u ON t.user_id=u.id ORDER BY t.uploaded_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Uploaded Tasks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h3 class="mb-4">Kids' Uploaded Tasks</h3>
        <div class="row">
            <?php while($row = $result->fetch_assoc()): ?>
                <div class="col-md-4 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h6><strong><?php echo $row['username']; ?></strong></h6>
                            <?php if ($row['file_type'] == 'image'): ?>
                                <img src="<?php echo $row['file_path']; ?>" class="img-fluid rounded">
                            <?php else: ?>
                                <video controls class="w-100 rounded">
                                    <source src="<?php echo $row['file_path']; ?>" type="video/mp4">
                                </video>
                            <?php endif; ?>
                            <small class="text-muted d-block mt-2"><?php echo $row['uploaded_at']; ?></small>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</body>
</html>
