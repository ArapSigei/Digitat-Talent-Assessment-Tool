<?php
// navbar_teacher.php
if (session_status() === PHP_SESSION_NONE) session_start();
$teacher_name = $_SESSION['username'] ?? 'Teacher';
?>
<nav class="navbar navbar-expand-lg navbar-custom fixed-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="../teacher/dashboard.php">
            <i class="bi bi-mortarboard-fill"></i> Digital Talent
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#teacherNav" aria-controls="teacherNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon text-white"></span>
        </button>

        <div class="collapse navbar-collapse" id="teacherNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>" href="../teacher/dashboard.php">
                        <i class="bi bi-house-door"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'view_submissions.php' ? 'active' : '' ?>" href="../teacher/view_submissions.php">
                        <i class="bi bi-folder2-open"></i> View Submissions
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : '' ?>" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle"></i> <?= htmlspecialchars($teacher_name) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../teacher/profile.php"><i class="bi bi-person"></i> My Profile</a></li>
                        <li><a class="dropdown-item text-danger" href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<style>
.navbar-custom {
    background: linear-gradient(135deg, maroon, #8b0000);
}
.navbar-custom .nav-link,
.navbar-custom .navbar-brand {
    color: #fff !important;
}
.navbar-custom .nav-link:hover,
.navbar-custom .nav-link.active {
    color: #ffd700 !important;
}
</style>
