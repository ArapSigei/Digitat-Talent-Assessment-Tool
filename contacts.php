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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Digital Talent - Contacts</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; margin-bottom: 60px; }
        .navbar-custom {
            background-color: maroon;
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
                    <a class="nav-link" href="about.php">About</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="contacts.php">Contacts</a>
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
    <h2>Contact Us</h2>
    <p>For support or inquiries, please reach out to us:</p>
    <ul class="list-unstyled">
        <li><i class="fas fa-envelope"></i> Email: support@digitaltalent.com</li>
        <li><i class="fas fa-phone"></i> Phone: +254 757 666 159 </li>
        <li><i class="fas fa-map-marker-alt"></i> Address: 123 Talent Lane, kabarak, Kenya</li>
    </ul>
    <p>24/7 assistance</p>
</div>

<!-- Footer -->
<footer class="footer">
    <p>&copy; 2025 Digital Talent. All rights reserved. | <a href="mailto:support@digitaltalent.com">Contact Us</a></p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>