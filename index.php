<?php
// ==== 1. Start output buffering ====
ob_start();

// ==== 2. Start session ====
session_start();

// ==== 3. Include config (if needed for DB in future) ====
require_once 'config.php';

// ==== 4. Redirect if already logged in ====
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// ==== 5. If we get here → show welcome page ====
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Welcome | Digital Talent Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa !important;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .card {
            border: none;
            border-radius: 15px;
        }
        .btn {
            min-width: 120px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-lg">
                    <div class="card-header text-center bg-primary text-white py-4">
                        <h3 class="mb-0">Digital Talent Assessment</h3>
                    </div>
                    <div class="card-body text-center py-5">
                        <p class="lead mb-4">Please register or login to continue.</p>
                        <div>
                            <a href="register.php" class="btn btn-success m-2 px-4">Register</a>
                            <a href="login.php" class="btn btn-primary m-2 px-4">Login</a>
                        </div>
                    </div>
                </div>
                <p class="text-center text-muted mt-4 small">
                    © <?= date('Y') ?> Digital Talent Academy, Kenya
                </p>
            </div>
        </div>
    </div>

<?php
// ==== 6. Flush output buffer ====
ob_end_flush();
?>
</body>
</html>