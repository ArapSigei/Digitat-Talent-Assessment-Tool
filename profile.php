<?php
/* --------------------------------------------------------------
   profile.php – FULLY FIXED (no warnings, safe session access)
   -------------------------------------------------------------- */
session_start();
require 'config.php';

// === BASE URL DETECTION (same as dashboard.php) ===
$base_url = '/';
$script_name = $_SERVER['SCRIPT_NAME'];
if (strpos($script_name, '/DGT/') !== false)          $base_url = '/DGT/';
elseif (strpos($script_name, '/digital-talent/') !== false) $base_url = '/digital-talent/';
elseif (strpos($script_name, '/KID/') !== false)    $base_url = '/KID/';

// === REDIRECT IF NOT LOGGED IN ===
if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
    header("Location: " . $base_url . "login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'];  // Safe: already checked above

// === FETCH USER DATA FROM DB ===
$username = $email = $profile_pic = '';
try {
    $stmt = $conn->prepare("SELECT username, email, profile_pic FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($db_username, $db_email, $db_profile_pic);
    if ($stmt->fetch()) {
        $username    = $db_username;
        $email       = $db_email;
        $profile_pic = $db_profile_pic ?: 'uploads/profiles/default.png';
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Profile fetch error: " . $e->getMessage());
    $username = 'User';
    $email    = 'Not available';
    $profile_pic = 'uploads/profiles/default.png';
}

// === UPLOAD PROFILE PICTURE ===
$upload_error = $upload_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['profile_pic'];
    $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $max_size = 2 * 1024 * 1024; // 2MB

    if (!in_array($file['type'], $allowed)) {
        $upload_error = "Only JPG, PNG, or GIF images are allowed.";
    } elseif ($file['size'] > $max_size) {
        $upload_error = "Image must be under 2MB.";
    } else {
        $upload_dir = "uploads/profiles/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = $user_id . '_' . time() . '.' . $ext;
        $filepath = $upload_dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Delete old picture
            if ($profile_pic && $profile_pic !== 'uploads/profiles/default.png' && file_exists($profile_pic)) {
                unlink($profile_pic);
            }

            // Update DB
            $stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
            $stmt->bind_param('si', $filepath, $user_id);
            $stmt->execute();
            $stmt->close();

            $profile_pic = $filepath;
            $upload_success = "Profile picture updated successfully!";
        } else {
            $upload_error = "Failed to upload image. Please try again.";
        }
    }
}

// === CHANGE PASSWORD ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['new_password'])) {
    $new_pass = trim($_POST['new_password']);
    if (strlen($new_pass) < 6) {
        $upload_error = "Password must be at least 6 characters.";
    } else {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param('si', $hashed, $user_id);
        $stmt->execute();
        $stmt->close();
        $upload_success = "Password changed successfully!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profile - Digital Talent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
            padding-top: 90px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .profile-img {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border: 5px solid #fff;
            box-shadow: 0 4px 15px rgba(0,0,0,.2);
            border-radius: 50%;
        }
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .navbar-custom {
            background: linear-gradient(135deg, #6a11cb, #2575fc) !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            padding: 0.8rem 1rem;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
        }
        .navbar-custom .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
            color: #fff !important;
        }
        .navbar-custom .nav-link {
            color: #fff !important;
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .navbar-custom .nav-link:hover {
            color: #ffd700 !important;
            background: rgba(255,255,255,0.1);
        }
        .navbar-custom .nav-link.active {
            background: rgba(255,255,255,0.2) !important;
            font-weight: bold;
        }
        .alert {
            border-radius: 12px;
            font-size: 0.95rem;
        }
        @media (max-width: 768px) {
            .profile-img { width: 120px; height: 120px; }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= $base_url ?>dashboard.php">Digital Talent</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="<?= $base_url ?>dashboard.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $base_url ?>about.php">About</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $base_url ?>contacts.php">Contacts</a></li>
                <li class="nav-item"><a class="nav-link active" href="<?= $base_url ?>profile.php">Profile</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $base_url ?>logout.php">Logout</a></li>

                <?php if ($role === 'kid'): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= $base_url ?>talent/upload_talent.php">Upload</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= $base_url ?>talent/view_results.php">My Scores</a></li>
                <?php elseif ($role === 'parent'): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= $base_url ?>talent/view_kid_results.php">Kid Results</a></li>
                <?php elseif ($role === 'teacher'): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= $base_url ?>talent/assess_talent.php">Assess</a></li>
                <?php elseif ($role === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= $base_url ?>admin_settings.php">Admin</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card">
                <div class="card-body text-center p-5">
                    <h3 class="mb-4 text-primary">My Profile</h3>

                    <!-- Profile Picture -->
                    <div class="mb-4">
                        <img src="<?= htmlspecialchars($base_url . $profile_pic) ?>"
                             alt="Profile" class="profile-img"
                             onerror="this.src='<?= $base_url ?>uploads/profiles/default.png'">
                    </div>

                    <!-- Upload Form -->
                    <form method="POST" enctype="multipart/form-data" class="mb-4">
                        <div class="input-group">
                            <input type="file" name="profile_pic" class="form-control" accept="image/*" required>
                            <button type="submit" class="btn btn-primary">Update</button>
                        </div>
                        <small class="text-muted d-block mt-1">Max 2MB • JPG, PNG, GIF</small>
                    </form-form>

                    <!-- Messages -->
                    <?php if ($upload_success): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <?= htmlspecialchars($upload_success) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <?php if ($upload_error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?= htmlspecialchars($upload_error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- User Info -->
                    <div class="text-start bg-light p-3 rounded">
                        <p class="mb-2"><strong>Username:</strong> <span class="text-primary"><?= htmlspecialchars($username) ?></span></p>
                        <p class="mb-2"><strong>Email:</strong> <?= htmlspecialchars($email) ?></p>
                        <p class="mb-0"><strong>Role:</strong>
                            <span class="badge <?= $role === 'kid' ? 'bg-warning' : ($role === 'teacher' ? 'bg-success' : ($role === 'admin' ? 'bg-danger' : 'bg-info')) ?> text-white">
                                <?= ucfirst($role) ?>
                            </span>
                        </p>
                    </div>

                    <hr class="my-4">

                    <!-- Change Password -->
                    <form method="POST" class="text-start">
                        <div class="mb-3">
                            <label class="form-label">New Password <small class="text-muted">(leave blank to keep)</small></label>
                            <input type="password" name="new_password" class="form-control" minlength="6" placeholder="Enter new password">
                        </div>
                        <button type="submit" class="btn btn-outline-primary w-100">Change Password</button>
                    </form>

                    <div class="mt-4">
                        <a href="<?= $base_url ?>dashboard.php" class="btn btn-secondary w-100">Back to Dashboard</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
if (isset($conn)) $conn->close();
?>