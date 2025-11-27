<?php
/* --------------------------------------------------------------
   dashboard.php – 100% fixed (no warnings, no redirect loops)
   -------------------------------------------------------------- */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ==============================================================
   1. REDIRECT GUARD – eliminates infinite loops
   ============================================================== */
if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {

    $current_script = basename($_SERVER['SCRIPT_NAME']);

    // Never redirect when we are already on login.php
    if ($current_script !== 'login.php') {

        // One‑time guard – allow only ONE redirect per fresh session
        if (!isset($_SESSION['login_redirect'])) {
            $_SESSION['login_redirect'] = true;

            // $base_url is built later, but we need it now → default to root
            $login_url = '/login.php';

            if (!headers_sent()) {
                header('Location: ' . $login_url);
                exit();
            } else {
                echo "<script>window.location.href=" . json_encode($login_url) . ";</script>";
                exit();
            }
        }
    }
    // If we are on login.php without a session → just show the login form.
}

/* --------------------------------------------------------------
   2. USER SESSION DATA – now guaranteed to exist
   -------------------------------------------------------------- */
$user_id = $_SESSION['user_id'] ?? null;
$role    = $_SESSION['role']    ?? null;

/* --------------------------------------------------------------
   3. BASE URL DETECTION
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
   4. FETCH USER INFO
   -------------------------------------------------------------- */
$user = [
    'username'    => $_SESSION['user'] ?? 'User',
    'profile_pic' => 'uploads/profiles/default.png'
];
try {
    $stmt = $conn->prepare("SELECT username, profile_pic FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $user['username'] = $row['username'];
        $db_pic = $row['profile_pic'] ?: 'uploads/profiles/default.png';
        $full_path = __DIR__ . '/' . $db_pic;
        if (file_exists($full_path) && is_file($full_path)) {
            $user['profile_pic'] = $db_pic;
        }
        $_SESSION['profile_pic'] = $user['profile_pic'];
        $_SESSION['user']        = $user['username'];
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("User fetch error: " . $e->getMessage());
}

/* --------------------------------------------------------------
   5. GENERIC COUNT FUNCTION
   -------------------------------------------------------------- */
function getCount($conn, $sql, $params = [], $types = "") {
    $count = 0;
    try {
        $stmt = $conn->prepare($sql);
        if (!$stmt) return 0;
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) $count = (int)($row['total'] ?? 0);
        $stmt->close();
    } catch (Exception $e) {
        error_log("getCount error: " . $e->getMessage());
    }
    return $count;
}

/* --------------------------------------------------------------
   6. ALL COUNTS
   -------------------------------------------------------------- */
$studentCount        = getCount($conn, "SELECT COUNT(*) AS total FROM users WHERE role = ?", ['kid'], "s");
$assessmentsCompleted = getCount($conn, "
    SELECT COUNT(*) AS total
    FROM talent_assessments ta
    JOIN uploads up ON ta.upload_id = up.id
    JOIN users u ON up.user_id = u.id
    WHERE u.role = 'kid'
");
$talentsIdentified   = getCount($conn, "SELECT COUNT(*) AS total FROM uploads WHERE talent_status = 'Identified'");
$allUsersCount       = getCount($conn, "SELECT COUNT(*) AS total FROM users");

/* --------------------------------------------------------------
   7. AVERAGE GRADE
   -------------------------------------------------------------- */
$talents_avg = 0;
try {
    $stmt = $conn->prepare("
        SELECT AVG(ta.grade) AS avg_grade
        FROM talent_assessments ta
        JOIN uploads up ON ta.upload_id = up.id
        WHERE up.talent_status = 'Identified'
    ");
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $talents_avg = $row['avg_grade'] ? round((float)$row['avg_grade']) : 0;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Avg grade error: " . $e->getMessage());
}

/* --------------------------------------------------------------
   8. ADMIN USERS LIST
   -------------------------------------------------------------- */
$all_users_list = [];
if ($role === 'admin') {
    try {
        $stmt = $conn->prepare("SELECT id, username, role FROM users ORDER BY role, username");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $all_users_list[] = $row;
        $stmt->close();
    } catch (Exception $e) {
        error_log("Admin fetch users error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Digital Talent Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
/* (unchanged – same CSS you already had) */
body { background: maroon; margin-bottom: 60px; min-height: 100vh; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
.navbar-custom { background: linear-gradient(135deg, #8B0000, #A52A2A); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.navbar-custom .navbar-brand, .navbar-custom .nav-link { color: #fff !important; font-weight: 500; }
.navbar-custom .nav-link:hover { color: #ffd700 !important; }
.nav-link.active { background: rgba(255,255,255,0.2); border-radius: 8px; font-weight: bold; }
.main-content { padding: 30px 15px; max-width: 1300px; margin: 0 auto; }
.user-info { text-align: center; margin: 25px 0; padding: 20px; background: #fff; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
.user-info img { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 4px solid #ddd; box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
.user-info h4 { margin: 15px 0 5px; color: #333; }
.user-info .role-badge { display: inline-block; padding: 5px 15px; border-radius: 20px; font-size: 0.9rem; font-weight: bold; text-transform: uppercase; }
.cards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin: 30px 0; }
.stat-card { background: #fff; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.08); transition: all 0.3s ease; border: 2px solid transparent; }
.stat-card:hover { transform: translateY(-5px); }
.clickable-card { cursor: pointer; }
.clickable-card:hover { border-color: #007bff; box-shadow: 0 8px 20px rgba(0,123,255,0.2) !important; }
.stat-card i { font-size: 2.5rem; margin-bottom: 15px; }
.card-value { font-size: 2.2rem; font-weight: bold; color: #2c3e50; }
.talent-actions { text-align: center; margin: 40px 0; }
.talent-actions .btn { margin: 8px; padding: 14px 28px; font-size: 1.1rem; border-radius: 50px; min-width: 180px; }
.footer { background: #2c3e50; color: #ecf0f1; text-align: center; padding: 20px; position: fixed; bottom: 0; width: 100%; font-size: 0.9rem; z-index: 1000; }
.footer a { color: #ffd700; text-decoration: none; }
.footer a:hover { text-decoration: underline; }
/* Average Grade */
#talents-avg { font-weight: bold; }
.text-grade-90 { color: #28a745 !important; }
.text-grade-70 { color: #ffc107 !important; }
.text-grade-low { color: #dc3545 !important; }
@media (max-width: 768px) {
    .user-info img { width: 80px; height: 80px; }
    .talent-actions .btn { display: block; width: 100%; margin: 10px 0; }
}
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= $base_url ?>dashboard.php">Digital Talent</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link active" href="<?= $base_url ?>dashboard.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $base_url ?>about.php">About</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $base_url ?>contacts.php">Contacts</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $base_url ?>profile.php">Profile</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $base_url ?>logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<main class="main-content">
    <div class="user-info">
        <img src="<?= htmlspecialchars($base_url . $user['profile_pic']) ?>" alt="Profile"
             onerror="this.src='<?= $base_url ?>uploads/profiles/default.png'">
        <h4><?= htmlspecialchars($user['username']) ?></h4>
        <span class="role-badge <?= $role === 'admin' ? 'bg-danger' : ($role === 'teacher' ? 'bg-warning' : ($role === 'parent' ? 'bg-info' : 'bg-success')) ?> text-white">
            <?= ucfirst($role ?? 'User') ?>
        </span>
    </div>

    <div class="talent-actions">
        <?php if ($role === 'kid'): ?>
            <a href="<?= $base_url ?>talent/upload_talent.php" class="btn btn-primary btn-lg">Upload Activity</a>
            <a href="<?= $base_url ?>talent/view_results.php" class="btn btn-success btn-lg">My Scores</a>
        <?php elseif ($role === 'parent'): ?>
            <a href="<?= $base_url ?>talent/view_kid_results.php" class="btn btn-info btn-lg text-white">View Kid Results</a>
        <?php elseif ($role === 'teacher'): ?>
            <a href="<?= $base_url ?>talent/assess_talent.php" class="btn btn-warning btn-lg">Assess Submissions</a>
        <?php elseif ($role === 'admin'): ?>
            <a href="<?= $base_url ?>admin_settings.php" class="btn btn-dark btn-lg">Admin Panel</a>
        <?php endif; ?>
    </div>

    <div class="cards-grid">
        <?php if (in_array($role, ['admin', 'teacher'])): ?>
            <!-- Students -->
            <div class="card stat-card clickable-card" data-bs-toggle="modal" data-bs-target="#studentsModal">
                <i class="fas fa-users fa-2x text-primary"></i>
                <h5>Total Kids</h5>
                <p class="card-value" id="total-students"><?= $studentCount ?></p>
            </div>

            <!-- Assessments -->
            <div class="card stat-card clickable-card" data-bs-toggle="modal" data-bs-target="#assessmentsModal">
                <i class="fas fa-clipboard-check fa-2x text-success"></i>
                <h5>Assessments</h5>
                <p class="card-value" id="total-assessments"><?= $assessmentsCompleted ?></p>
            </div>

            <!-- Talents Found -->
            <div class="card stat-card clickable-card" data-bs-toggle="modal" data-bs-target="#talentsModal">
                <i class="fas fa-lightbulb fa-2x text-warning"></i>
                <h5>Talents Found</h5>
                <p class="card-value" id="total-talents"><?= $talentsIdentified ?></p>
                <p class="card-text small text-muted mb-0">
                    <span id="talents-avg" class="text-grade-low">Avg: <?= $talents_avg ?>/100</span>
                </p>
            </div>
        <?php endif; ?>

        <!-- Admin: All Users -->
        <?php if ($role === 'admin'): ?>
            <div class="card stat-card clickable-card" data-bs-toggle="modal" data-bs-target="#usersModal">
                <i class="fas fa-user-friends fa-2x text-info"></i>
                <h5>Registered Users</h5>
                <p class="card-value" id="total-users"><?= $allUsersCount ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ==== MODALS ==== -->
    <?php if (in_array($role, ['admin', 'teacher'])): ?>
    <div class="modal fade" id="studentsModal" tabindex="-1">
        <div class="modal-dialog modal-lg"><div class="modal-content">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title">Student Status</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="students-modal-body"><div class="text-center"><div class="spinner-border text-primary"></div></div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
        </div></div>
    </div>

    <div class="modal fade" id="assessmentsModal" tabindex="-1">
        <div class="modal-dialog modal-xl"><div class="modal-content">
            <div class="modal-header bg-success text-white"><h5 class="modal-title">Assessment History</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="assessments-modal-body"><div class="text-center"><div class="spinner-border text-success"></div></div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
        </div></div>
    </div>

    <div class="modal fade" id="talentsModal" tabindex="-1">
        <div class="modal-dialog modal-lg"><div class="modal-content">
            <div class="modal-header bg-warning text-dark"><h5 class="modal-title">Students with Identified Talents</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="talents-modal-body"><div class="text-center"><div class="spinner-border text-warning"></div></div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
        </div></div>
    </div>
    <?php endif; ?>

    <?php if ($role === 'admin'): ?>
    <div class="modal fade" id="usersModal" tabindex="-1">
        <div class="modal-dialog modal-lg"><div class="modal-content">
            <div class="modal-header bg-info text-white"><h5 class="modal-title">All Registered Users</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if (!empty($all_users_list)): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead><tr><th>#</th><th>Username</th><th>Role</th></tr></thead>
                        <tbody>
                            <?php foreach ($all_users_list as $i => $u): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= htmlspecialchars($u['username']) ?></td>
                                <td><span class="badge <?= $u['role']==='admin'?'bg-danger':($u['role']==='teacher'?'bg-warning':($u['role']==='parent'?'bg-info':'bg-success')) ?> text-white"><?= ucfirst($u['role']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted">No users found.</p>
                <?php endif; ?>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
        </div></div>
    </div>
    <?php endif; ?>
</main>

<footer class="footer">
    <p>© <?= date('Y') ?> Digital Talent | <a href="mailto:support@digitaltalent.com">Contact Support</a></p>
</footer>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(function() {
    $('.nav-link').removeClass('active');
    $('.nav-link[href$="dashboard.php"]').addClass('active');

    function refreshDashboard() {
        $.get('<?= $base_url ?>ajax_refresh.php?t=' + Date.now(), function(data) {
            if (data.students !== undefined) $('#total-students').text(data.students);
            if (data.assessments !== undefined) $('#total-assessments').text(data.assessments);
            if (data.talents !== undefined) $('#total-talents').text(data.talents);
            if (data.users !== undefined) $('#total-users').text(data.users);
            if (data.talents_avg !== undefined) {
                const avg = data.talents_avg;
                let cls = 'text-grade-low';
                if (avg >= 90) cls = 'text-grade-90';
                else if (avg >= 70) cls = 'text-grade-70';
                $('#talents-avg').text('Avg: ' + avg + '/100')
                    .removeClass('text-grade-90 text-grade-70 text-grade-low')
                    .addClass(cls);
            }
        }, 'json').fail(function() { console.error("Refresh failed"); });
    }

    $('#studentsModal').on('show.bs.modal', function() {
        $('#students-modal-body').html('<div class="text-center"><div class="spinner-border text-primary"></div></div>');
        $.get('<?= $base_url ?>talent/ajax_students.php', function(html) {
            $('#students-modal-body').html(html);
        }).fail(() => $('#students-modal-body').html('<p class="text-danger">Failed to load.</p>'));
    });

    $('#assessmentsModal').on('show.bs.modal', function() {
        $('#assessments-modal-body').html('<div class="text-center"><div class="spinner-border text-success"></div></div>');
        $.get('<?= $base_url ?>talent/ajax_assessments.php', function(html) {
            $('#assessments-modal-body').html(html);
        }).fail(() => $('#assessments-modal-body').html('<p class="text-danger">Failed to load.</p>'));
    });

    $('#talentsModal').on('show.bs.modal', function() {
        $('#talents-modal-body').html('<div class="text-center"><div class="spinner-border text-warning"></div></div>');
        $.get('<?= $base_url ?>talent/ajax_talents_identified.php', function(html) {
            $('#talents-modal-body').html(html);
        }).fail(() => $('#talents-modal-body').html('<p class="text-danger">Failed to load identified talents.</p>'));
    });

    setInterval(refreshDashboard, 10000);
    refreshDashboard();
});
</script>
</body>
</html>
<?php
if (isset($conn)) $conn->close();
?>