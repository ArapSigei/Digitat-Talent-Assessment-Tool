<?php
// Start session
if (session_status() === PHP_SESSION_NONE) session_start();
include 'config.php';

// Check if user is logged in, redirect if not
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE htm>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Digital Talent - About Us</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; margin-bottom: 60px; }
        .navbar-custom {
            background-color: purple;
        }
        .navbar-custom .navbar-brand,
        .navbar-custom .nav-link {
            color: #ffffff !important;
        }
        .navbar-custom .nav-link:hover {
            color: #adb5bd !important;
        }
        .content { padding: 20px; }
        .footer {
            background-color: green;
            color: #ffffff;
            text-align: center;
            padding: 10px 0;
            width: 100%;
            position: fixed;
            bottom: 0;
            z-index: 1000;
        }
        .footer a { color: #adb5bd; text-decoration: none; }
        .footer a:hover { color: #ffffff; }
    </style>
</head>
<body>
<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">Digital Talent</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="about.php">About</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="contacts.php">Contacts</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php">Profile</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="content">
    <h2>About Us</h2>
    <p>Welcome to Digital Talent, a platform dedicated to identifying and nurturing young talents through innovative assessments and support. Our mission is to empower kids, guide parents, and assist teachers and admins in recognizing and developing potential in a digital environment.</p>
    <p>Founded in 2025, we aim to bridge the gap between talent discovery and opportunity by providing tools for uploading talents, assessing submissions, and tracking progress. Join us in shaping the future of talent development!</p>
</div>

<!-- Footer -->
<footer class="footer">
    <p>&copy; 2025 Digital Talent. All rights reserved. | <a href="mailto:support@digitaltalent.com">Contact Us</a></p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>