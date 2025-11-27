<?php
session_start();
require '../config.php';
require_role(['kid']);

$user_id  = $_SESSION['user_id'];
$kid_name = $_SESSION['username'] ?? 'Student';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Talent Results</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background:#f8f9fa; padding-top:80px; }
.navbar-custom { background:linear-gradient(135deg, maroon, #8b0000); }
.navbar-custom .nav-link, .navbar-custom .navbar-brand { color:#fff !important; }
.navbar-custom .nav-link:hover { color:#ffd700 !important; }
.navbar-custom .nav-link.active { background:rgba(255,215,0,.2); border-radius:6px; }
.card { border:none; box-shadow:0 4px 15px rgba(0,0,0,.1); border-radius:12px; }
.media { height:220px; object-fit:cover; border-radius:8px; }
.no-results { text-align:center; padding:60px 20px; color:#6c757d; }
.result-card { background:#fff; border-radius:12px; padding:20px; box-shadow:0 4px 12px rgba(0,0,0,.08); }
.grade-value { font-size:2rem; font-weight:bold; color:#28a745; }
.status-badge { font-size:1rem; }
.assess-item { border-left:4px solid #ffc107; padding-left:12px; margin-bottom:12px; }
</style>
</head>
<body>
<?php $current_page = basename($_SERVER['SCRIPT_NAME']); ?>
<nav class="navbar navbar-expand-lg navbar-custom fixed-top">
<div class="container-fluid">
<a class="navbar-brand fw-bold" href="../dashboard.php">Digital Talent</a>
<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#kidNav">
<span class="navbar-toggler-icon"></span>
</button>
<div class="collapse navbar-collapse" id="kidNav">
<ul class="navbar-nav me-auto mb-2 mb-lg-0">
<li class="nav-item"><a class="nav-link <?= ($current_page === 'dashboard.php') ? 'active' : '' ?>" href="../dashboard.php">Home</a></li>
<li class="nav-item"><a class="nav-link <?= ($current_page === 'upload_talent.php') ? 'active' : '' ?>" href="upload_talent.php">Upload</a></li>
<li class="nav-item"><a class="nav-link <?= ($current_page === 'view_results.php') ? 'active' : '' ?>" href="view_results.php">My Results</a></li>
<li class="nav-item"><a class="nav-link <?= ($current_page === 'profile.php') ? 'active' : '' ?>" href="../profile.php">Profile</a></li>
</ul>
<ul class="navbar-nav ms-auto">
<li class="nav-item dropdown">
<a class="nav-link dropdown-toggle" href="#" id="userDropdown" data-bs-toggle="dropdown">
<?= htmlspecialchars($kid_name) ?>
</a>
<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
<li><a class="dropdown-item" href="../profile.php">Profile</a></li>
<li><hr class="dropdown-divider"></li>
<li><a class="dropdown-item text-danger" href="../logout.php">Logout</a></li>
</ul>
</li>
</ul>
</div>
</div>
</nav>

<div class="container">
<h2 class="mb-4">My Talent Results</h2>

<?php
// Fetch uploads + teacher assessments
$q = "SELECT 
        u.id, u.file_path, u.file_type, u.uploaded_at,
        GROUP_CONCAT(ta.grade ORDER BY ta.id SEPARATOR ', ') AS grades,
        GROUP_CONCAT(ta.status ORDER BY ta.id SEPARATOR ', ') AS statuses,
        GROUP_CONCAT(ta.feedback ORDER BY ta.id SEPARATOR '||') AS feedbacks,
        GROUP_CONCAT(ta.assessed_at ORDER BY ta.id SEPARATOR '||') AS dates
      FROM uploads u
      LEFT JOIN talent_assessments ta ON u.id = ta.upload_id
      WHERE u.user_id = ?
      GROUP BY u.id
      ORDER BY u.uploaded_at DESC";

$stmt = $conn->prepare($q);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
?>

<?php if ($res->num_rows === 0): ?>
<div class="no-results">
<h4>No Uploads Yet</h4>
<p class="lead">Show your talent!</p>
<a href="upload_talent.php" class="btn btn-primary btn-lg">Upload Now</a>
</div>
<?php else: ?>
<div class="row">
<?php while($row = $res->fetch_assoc()):
    $grades    = $row['grades'] ? explode(', ', $row['grades']) : [];
    $statuses  = $row['statuses'] ? explode(', ', $row['statuses']) : [];
    $feedbacks = $row['feedbacks'] ? explode('||', $row['feedbacks']) : [];
    $dates     = $row['dates'] ? explode('||', $row['dates']) : [];
?>
<div class="col-md-6 col-lg-4 mb-4">
<div class="card h-100">
<?php if ($row['file_type'] === 'image'): ?>
<img src="../<?= htmlspecialchars($row['file_path']) ?>" class="card-img-top media" alt="Talent">
<?php else: ?>
<video src="../<?= htmlspecialchars($row['file_path']) ?>" controls class="card-img-top media"></video>
<?php endif; ?>
<div class="card-body d-flex flex-column">
<h6 class="card-title text-success">Talent Result</h6>
<div class="result-card mt-3">
<?php if(!empty($grades)): ?>
<?php foreach($grades as $i => $grade):
    $status = $statuses[$i] ?? 'pending';
    $fb = $feedbacks[$i] ?? '';
    $dt = $dates[$i] ?? $row['uploaded_at'];

    $badge = match($status) {
        'assessed' => 'bg-success',
        'pending'  => 'bg-secondary text-light',
        default    => 'bg-secondary text-light'
    };
?>
<div class="assess-item mb-3">
<div class="d-flex align-items-center mb-1">
<strong>Grade:</strong>
<span class="grade-value ms-2"><?= htmlspecialchars($grade) ?></span>
</div>
<div class="d-flex align-items-center mb-1">
<strong>Status:</strong>
<span class="badge <?= $badge ?> status-badge ms-2"><?= htmlspecialchars($status) ?></span>
</div>
<div class="d-flex align-items-center mb-1">
<strong>Feedback:</strong>
<span class="ms-2"><?= htmlspecialchars($fb ?: 'Assessed') ?></span>
</div>
<div class="d-flex align-items-center text-muted small">
<span>Assessed: <?= date('M j, Y', strtotime($dt)) ?></span>
</div>
</div>
<?php endforeach; ?>
<?php else: ?>
<p class="text-muted text-center mb-0">Awaiting assessment</p>
<?php endif; ?>
</div>
<small class="text-muted mt-3">Uploaded: <?= date('M j, Y', strtotime($row['uploaded_at'])) ?></small>
<div class="mt-auto text-end">
<a href="delete_talent.php?id=<?= $row['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to delete this upload?');">Delete</a>
</div>
</div>
</div>
</div>
<?php endwhile; ?>
</div>
<?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>
