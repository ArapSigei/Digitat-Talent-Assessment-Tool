<?php
/* --------------------------------------------------------------
   login.php – FULLY FIXED (no syntax errors, CSRF, sub‑folder safe)
   -------------------------------------------------------------- */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* --------------------------------------------------------------
   1. BASE URL DETECTION (same as dashboard.php)
   -------------------------------------------------------------- */
$base_url = '/';
$script_name = $_SERVER['SCRIPT_NAME'];
if (strpos($script_name, '/DGT/') !== false) {
    $base_url = '/DGT/';
} elseif (strpos($script_name, '/digital-talent/') !== false) {
    $base_url = '/digital-talent/';
} elseif (strpos($script_name, '/KID/') !== false) {
    $base_url = '/KID/';
}

/* --------------------------------------------------------------
   2. CSRF TOKEN (generated once per session)
   -------------------------------------------------------------- */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

/* --------------------------------------------------------------
   3. PROCESS LOGIN (only on POST)
   -------------------------------------------------------------- */
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---- CSRF CHECK ----
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {

        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $errors[] = 'Both email and password are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            try {
                $stmt = $conn->prepare(
                    "SELECT id, username, password, role FROM users WHERE email = ? LIMIT 1"
                );
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($row = $result->fetch_assoc()) {
                    if (password_verify($password, $row['password'])) {
                        // ---- LOGIN SUCCESS ----
                        $_SESSION['user_id'] = (int)$row['id'];
                        $_SESSION['role']    = $row['role'];
                        $_SESSION['user']    = $row['username'];
                        $_SESSION['email']   = $email;

                        // Clear redirect guard & CSRF token
                        unset($_SESSION['login_redirect'], $_SESSION['csrf_token']);

                        header("Location: {$base_url}dashboard.php");
                        exit();
                    } else {
                        $errors[] = 'Incorrect password.';
                    }
                } else {
                    $errors[] = 'No account found with that email.';
                }
                $stmt->close();
            } catch (Exception $e) {
                error_log("Login error: " . $e->getMessage());
                $errors[] = 'An unexpected error occurred. Please try again later.';
            }
        }
    }

    // If we get here → login failed → stay on page
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login – Digital Talent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: maroon;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-card {
            background: #fff;
            padding: 35px;
            border-radius: 25% 10%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            max-width: 420px;
            width: 100%;
        }
        .login-card h3 {
            color: #8B0000;
            text-align: center;
            margin-bottom: 25px;
            font-weight: 600;
        }
        .btn-login {
            background: linear-gradient(135deg, #8B0000, #A52A2A);
            color: #fff;
            font-weight: bold;
            border: none;
            padding: 12px;
            border-radius: 8px;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #A52A2A, #8B0000);
        }
        .form-control:focus {
            border-color: #8B0000;
            box-shadow: 0 0 0 0.2rem rgba(139,0,0,.25);
        }
        .alert {
            border-radius: 10px;
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
<div class="login-card">
    <h3>Digital Talent Login</h3>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <div class="mb-3">
            <label for="email" class="form-label">Email Address</label>
            <input type="email" class="form-control" id="email" name="email" required
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>

        <button type="submit" class="btn btn-login w-100">Log In</button>
    </form>

    <div class="text-center mt-4">
        <small>
            <a href="<?= $base_url ?>register.php" class="text-decoration-none text-primary">
                Don't have an account? Register
            </a>
        </small>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
if (isset($conn)) $conn->close();
?>