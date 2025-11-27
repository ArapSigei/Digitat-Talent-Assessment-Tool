<?php
session_start();
include 'config.php';
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// --- LOG ACTIVITY FUNCTION ---
function logActivity($conn, $action, $details = '') {
    if (!isset($_SESSION['user_id'])) return;
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'] ?? $_SESSION['user'] ?? 'Unknown';
    $role = $_SESSION['role'] ?? 'unknown';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
   
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, username, role, action, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $user_id, $username, $role, $action, $details, $ip);
    $stmt->execute();
    $stmt->close();
}

// --- HANDLE USER ADD/EDIT/DELETE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && ($_POST['action'] === 'add_user' || $_POST['action'] === 'edit_user')) {
        $id = intval($_POST['user_id'] ?? 0);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $password = $_POST['password'] ?? '';
        if ($id > 0) {
            if ($password) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET username=?, email=?, role=?, password=? WHERE id=?");
                $stmt->bind_param("ssssi", $username, $email, $role, $hash, $id);
                logActivity($conn, "Edited user (with password)", "ID: $id, Username: $username");
            } else {
                $stmt = $conn->prepare("UPDATE users SET username=?, email=?, role=? WHERE id=?");
                $stmt->bind_param("sssi", $username, $email, $role, $id);
                logActivity($conn, "Edited user", "ID: $id, Username: $username");
            }
            $stmt->execute();
            $stmt->close();
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users(username,email,password,role) VALUES(?,?,?,?)");
            $stmt->bind_param("ssss", $username, $email, $hash, $role);
            $stmt->execute();
            $stmt->close();
            logActivity($conn, "Added user", "Username: $username, Role: $role");
        }
    }
    if (isset($_POST['delete_user'])) {
        $uid = intval($_POST['user_id']);
        $stmt = $conn->prepare("SELECT username FROM users WHERE id=?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        $name = $res->fetch_assoc()['username'] ?? 'Unknown';
        $stmt->close();
        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $stmt->close();
        logActivity($conn, "Deleted user", "ID: $uid, Username: $name");
    }
    if (isset($_POST['delete_upload'])) {
        $upload_id = intval($_POST['upload_id']);
        $stmt = $conn->prepare("SELECT file_path FROM uploads WHERE id=?");
        $stmt->bind_param("i", $upload_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $file = __DIR__ . '/../' . $row['file_path'];
            if (file_exists($file)) unlink($file);
        }
        $stmt->close();
        $stmt = $conn->prepare("DELETE FROM uploads WHERE id=?");
        $stmt->bind_param("i", $upload_id);
        $stmt->execute();
        $stmt->close();
        logActivity($conn, "Deleted upload", "Upload ID: $upload_id");
    }
    if (isset($_POST['update_talent'])) {
        $upload_id = intval($_POST['upload_id']);
        $status = $_POST['talent_status'];
        $stmt = $conn->prepare("UPDATE uploads SET talent_status=? WHERE id=?");
        $stmt->bind_param("si", $status, $upload_id);
        $stmt->execute();
        $stmt->close();
        logActivity($conn, "Updated talent status", "Upload ID: $upload_id to $status");
    }
}

// --- FETCH DATA ---
$users = $conn->query("SELECT * FROM users ORDER BY id DESC");
$uploads = $conn->query("SELECT u.*, usr.username FROM uploads u JOIN users usr ON u.user_id = usr.id ORDER BY uploaded_at DESC");
$assessments = $conn->query("SELECT ta.*, u.file_path, u.file_type, usr.username FROM talent_assessments ta JOIN uploads u ON ta.upload_id = u.id JOIN users usr ON u.user_id = usr.id ORDER BY assessed_at DESC");

// --- AUDIT LOGS ---
$audit_logs = $conn->query("
    SELECT al.*, u.username
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 50
");

// --- DASHBOARD COUNTS ---
function countQuery($conn, $sql) { return $conn->query($sql)->fetch_row()[0]; }
$studentCount = countQuery($conn, "SELECT COUNT(*) FROM users WHERE role='kid'");
$teacherCount = countQuery($conn, "SELECT COUNT(*) FROM users WHERE role='teacher'");
$adminCount = countQuery($conn, "SELECT COUNT(*) FROM users WHERE role='admin'");
$assessmentCount = countQuery($conn, "SELECT COUNT(*) FROM talent_assessments");
$talentsFound = countQuery($conn, "SELECT COUNT(*) FROM uploads WHERE talent_status='Identified'");
$auditCount = countQuery($conn, "SELECT COUNT(*) FROM audit_logs");

// --- CURRENT PAGE ---
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Settings - Digital Talent</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background:#f8f9fa; padding-top:70px; font-family: 'Segoe UI', sans-serif; }
.navbar-custom { background: linear-gradient(135deg,#6a11cb,#2575fc); box-shadow:0 4px 12px rgba(0,0,0,0.15); }
.navbar-custom .nav-link { color:white; font-weight:500; }
.navbar-custom .nav-link.active { color: #ffd700 !important; font-weight:bold; background:rgba(255,255,255,0.1); border-radius:8px; }
.card { border-radius:12px; box-shadow:0 4px 15px rgba(0,0,0,0.08); }
.stat-card { color:white; padding:20px; border-radius:12px; text-align:center; margin-bottom:15px; transition:0.3s; }
.stat-card:hover { transform:translateY(-5px); box-shadow:0 8px 20px rgba(0,0,0,0.2); }
.stat-students { background:#1abc9c; }
.stat-teachers { background:#3498db; }
.stat-admins { background:#9b59b6; }
.stat-assessments { background:#e67e22; }
.stat-talents { background:#e74c3c; }
.stat-audit { background:#2c3e50; }
.audit-row { font-size:0.9rem; }
.audit-icon { font-size:1.2rem; }
.dropdown-menu { min-width:200px; }
</style>
</head>
<body>

<!-- FIXED NAVBAR - ALL LINKS CORRECT -->
<nav class="navbar navbar-expand-lg navbar-custom fixed-top">
<div class="container-fluid">
    <a class="navbar-brand fw-bold" href="dashboard.php">
        Admin Panel
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="adminNav">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item">
                <a class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                    Home
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page == 'admin_settings.php' ? 'active' : '' ?>" href="admin_settings.php">
                    Settings
                </a>
            </li>
        </ul>
        <ul class="navbar-nav">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle text-white" href="#" data-bs-toggle="dropdown">
                    <?= htmlspecialchars($_SESSION['username'] ?? $_SESSION['user']) ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                    <li><a class="dropdown-item" href="admin_settings.php">Admin Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
                </ul>
            </li>
        </ul>
    </div>
</div>
</nav>

<div class="container mt-4">
<h2 class="mb-4">Admin Dashboard - Manage Everything</h2>

<!-- DASHBOARD CARDS -->
<div class="row">
<div class="col-md-2 stat-card stat-students"><h5>Students</h5><p class="fs-3"><?= $studentCount ?></p></div>
<div class="col-md-2 stat-card stat-teachers"><h5>Teachers</h5><p class="fs-3"><?= $teacherCount ?></p></div>
<div class="col-md-2 stat-card stat-admins"><h5>Admins</h5><p class="fs-3"><?= $adminCount ?></p></div>
<div class="col-md-2 stat-card stat-assessments"><h5>Assessments</h5><p class="fs-3"><?= $assessmentCount ?></p></div>
<div class="col-md-2 stat-card stat-talents"><h5>Talents Found</h5><p class="fs-3"><?= $talentsFound ?></p></div>
<div class="col-md-2 stat-card stat-audit"><h5>Audit Logs</h5><p class="fs-3"><?= $auditCount ?></p></div>
</div>

<!-- USERS MANAGEMENT -->
<h4 class="mt-4">Users</h4>
<button class="btn btn-primary mb-2" data-bs-toggle="collapse" data-bs-target="#addUserForm">Add New User</button>
<div class="collapse mb-3" id="addUserForm">
<form method="POST">
<input type="hidden" name="action" value="add_user">
<div class="row g-2">
<div class="col-md-3"><input type="text" class="form-control" name="username" placeholder="Username" required></div>
<div class="col-md-3"><input type="email" class="form-control" name="email" placeholder="Email" required></div>
<div class="col-md-2"><input type="password" class="form-control" name="password" placeholder="Password" required></div>
<div class="col-md-2">
<select class="form-select" name="role" required>
<option value="kid">Student</option>
<option value="teacher">Teacher</option>
<option value="admin">Admin</option>
</select>
</div>
<div class="col-md-2"><button type="submit" class="btn btn-success">Add User</button></div>
</div>
</form>
</div>
<table class="table table-bordered table-striped">
<tr><th>#</th><th>Username</th><th>Email</th><th>Role</th><th>Actions</th></tr>
<?php $num=1; while($u=$users->fetch_assoc()): ?>
<tr>
<td><?= $num++ ?></td>
<td><?= htmlspecialchars($u['username']) ?></td>
<td><?= htmlspecialchars($u['email']) ?></td>
<td><?= ucfirst($u['role']) ?></td>
<td>
<form method="POST" style="display:inline-block;" onsubmit="return confirm('Delete user?');">
<input type="hidden" name="user_id" value="<?= $u['id'] ?>">
<button type="submit" name="delete_user" class="btn btn-danger btn-sm">Delete</button>
</form>
</td>
</tr>
<?php endwhile; ?>
</table>

<!-- UPLOADS MANAGEMENT -->
<h4 class="mt-4">Uploads / Activities</h4>
<div class="row g-3">
<?php while($up=$uploads->fetch_assoc()): ?>
<div class="col-md-4">
<div class="card p-2">
<h6><?= htmlspecialchars($up['username']) ?></h6>
<?php if($up['file_type']=='image'): ?>
<img src="<?= htmlspecialchars($up['file_path']) ?>" class="img-fluid mb-2">
<?php else: ?>
<video src="<?= htmlspecialchars($up['file_path']) ?>" controls class="img-fluid mb-2"></video>
<?php endif; ?>
<form method="POST" class="mb-2">
<input type="hidden" name="upload_id" value="<?= $up['id'] ?>">
<select class="form-select mb-1" name="talent_status" onchange="this.form.submit()">
<option value="">Set Talent Status</option>
<option value="Identified" <?= $up['talent_status']=='Identified'?'selected':'' ?>>Identified</option>
<option value="Potential" <?= $up['talent_status']=='Potential'?'selected':'' ?>>Potential</option>
</select>
<input type="hidden" name="update_talent">
</form>
<form method="POST" onsubmit="return confirm('Delete this upload?');">
<input type="hidden" name="upload_id" value="<?= $up['id'] ?>">
<button type="submit" name="delete_upload" class="btn btn-danger btn-sm w-100">Delete</button>
</form>
</div>
</div>
<?php endwhile; ?>
</div>

<!-- ASSESSMENTS -->
<h4 class="mt-4">Assessments</h4>
<table class="table table-bordered table-striped">
<tr><th>#</th><th>Student</th><th>File Type</th><th>Grade</th><th>Feedback</th><th>Date</th></tr>
<?php $num2=1; while($a=$assessments->fetch_assoc()): ?>
<tr>
<td><?= $num2++ ?></td>
<td><?= htmlspecialchars($a['username']) ?></td>
<td><?= htmlspecialchars($a['file_type']) ?></td>
<td><?= htmlspecialchars($a['grade']) ?></td>
<td><?= htmlspecialchars($a['feedback']) ?></td>
<td><?= $a['assessed_at'] ?></td>
</tr>
<?php endwhile; ?>
</table>

<!-- AUDIT LOG CARD -->
<div class="card mt-5">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">User Activity Audit Log</h5>
        <span class="badge bg-light text-dark">Last 50 Actions</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i=1; while($log = $audit_logs->fetch_assoc()): ?>
                    <tr class="audit-row">
                        <td><?= $i++ ?></td>
                        <td><strong><?= htmlspecialchars($log['username']) ?></strong></td>
                        <td><span class="badge bg-<?= $log['role']=='admin'?'danger':($log['role']=='teacher'?'warning':($log['role']=='parent'?'info':'success')) ?>"><?= ucfirst($log['role']) ?></span></td>
                        <td><?= htmlspecialchars($log['action']) ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($log['details']) ?></td>
                        <td><small><?= date('M j, Y g:i A', strtotime($log['created_at'])) ?></small></td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($audit_logs->num_rows == 0): ?>
                    <tr><td colspan="6" class="text-center text-muted py-3">No activity recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>