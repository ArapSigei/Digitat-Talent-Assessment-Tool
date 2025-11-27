<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$role = $_SESSION['role'] ?? '';
$username = $_SESSION['username'] ?? 'Student';

// auto-detect base URL
$base_url = '/';
$script_name = $_SERVER['SCRIPT_NAME'];
if (strpos($script_name, '/DGT/') !== false) $base_url = '/DGT/';
elseif (strpos($script_name, '/digital-talent/') !== false) $base_url = '/digital-talent/';
elseif (strpos($script_name, '/KID/') !== false) $base_url = '/KID/';

// prevent non-kid users from using this navbar
if ($role !== 'kid') {
    echo '<script>window.location.href="' . $base_url . 'dashboard.php";</script>';
    exit;
}

// navbar links
$nav_links = [
    ['name' => 'Home', 'icon' => 'bi-house-door', 'link' => $base_url . 'dashboard.php'],
    ['name' => 'Upload Talent', 'icon' => 'bi-cloud-upload', 'link' => $base_url . 'kid/upload_talent.php'],
    ['name' => 'My Results', 'icon' => 'bi-trophy', 'link' => $base_url . 'kid/my_results.php'],
    ['name' => 'Profile', 'icon' => 'bi-person-circle', 'link' => $base_url . 'profile.php'],
];
?>
<nav class="navbar navbar-expand-lg navbar-custom fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="<?= $base_url ?>dashboard.php">
            <i class="bi bi-stars"></i> Digital Talent
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarKid">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarKid">
            <ul class="navbar-nav ms-auto">
                <?php foreach ($nav_links as $item): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) === basename($item['link'])) ? 'active' : '' ?>"
                           href="<?= htmlspecialchars($item['link']) ?>">
                            <i class="bi <?= $item['icon'] ?>"></i> <?= htmlspecialchars($item['name']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                <li class="nav-item">
                    <a class="nav-link text-danger" href="<?= $base_url ?>logout.php">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<style>
    .navbar-custom {
        background: linear-gradient(135deg, maroon, #8b0000);
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
    .navbar-custom .navbar-brand, 
    .navbar-custom .nav-link {
        color: #fff !important;
        font-weight: 500;
    }
    .navbar-custom .nav-link:hover, 
    .navbar-custom .nav-link.active {
        color: #ffd700 !important;
    }
</style>
