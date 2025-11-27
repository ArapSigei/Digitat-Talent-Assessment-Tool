<?php
// register.php – FULLY FIXED (user types email, no hard-coded values)

require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// === CSRF TOKEN ===
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$errors = [];
$success = false;

// Preserve form values on error
$username = $_POST['username'] ?? '';
$email    = $_POST['email'] ?? '';
$role     = $_POST['role'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // === CSRF CHECK ===
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid CSRF token. Please try again.";
    } else {

        $username = trim($_POST['username'] ?? '');
        $email    = trim(strtolower($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? '';

        // === VALIDATION ===
        if ($username === '') {
            $errors[] = "Username is required.";
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "A valid email address is required.";
        }
        if ($password === '') {
            $errors[] = "Password is required.";
        } elseif (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters.";
        }
        if (!in_array($role, ['kid', 'teacher', 'parent', 'admin'])) {
            $errors[] = "Please select a valid role.";
        }

        // === CHECK IF EMAIL ALREADY EXISTS ===
        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $errors[] = "This email is already registered. Try <a href='login.php'>logging in</a>.";
            }
            $stmt->close();
        }

        // === INSERT USER IF NO ERRORS ===
        if (empty($errors)) {
            $hashedPwd   = password_hash($password, PASSWORD_DEFAULT);
            $profilePic  = "uploads/profiles/default.png";

            $stmt = $conn->prepare(
                "INSERT INTO users (username, email, password, role, profile_pic)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("sssss", $username, $email, $hashedPwd, $role, $profilePic);

            if ($stmt->execute()) {
                $success = true;
                // Optional: log the user in automatically
                $user_id = $stmt->insert_id;
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user']    = $username;
                $_SESSION['role']    = $role;
                $_SESSION['email']   = $email;

                // Clear CSRF token after use
                unset($_SESSION['csrf_token']);

                header("Location: dashboard.php");
                exit();
            } else {
                $errors[] = "Registration failed. Please try again.";
                error_log("Register error: " . $stmt->error);
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register – Digital Talent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #8B0000, #A52A2A); min-height: 100vh; display: flex; align-items: center; }
        .card { border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .card-header { background: #8B0000; border-radius:... }
        .btn-register { background: #ffc107; color: #212529; font-weight: bold; }
        .btn-register:hover { background: #e0a800; }
        small { font-size: 0.85rem; }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card">
                <div class="card-header text-center text-white">
                    <h4 class="mb-0">Create Account</h4>
                </div>
                <div class="card-body p-4">

                    <?php if ($success): ?>
                        <div class="alert alert-success text-center">
                            Registration successful! Redirecting...
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $e): ?>
                                    <li><?= $e ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required
                                   value="<?= htmlspecialchars($username) ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" required
                                   value="<?= htmlspecialchars($email) ?>">
                            <small class="text-muted">You'll log in with this email</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required minlength="6">
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">I am a...</label>
                            <select name="role" class="form-select" required>
                                <option value="">-- Select Role --</option>
                                <option value="kid" <?= $role === 'kid' ? 'selected' : '' ?>>Kid / Student</option>
                                <option value="teacher" <?= $role === 'teacher' ? 'selected' : '' ?>>Teacher</option>
                                <option value="parent" <?= $role === 'parent' ? 'selected' : '' ?>>Parent</option>
                                <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-register w-100">
                            Register Now
                        </button>
                    </form>

                    <p class="mt-3 text-center text-muted small">
                        Already have an account? <a href="login.php" class="text-warning">Log in here</a>
                    </p>
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