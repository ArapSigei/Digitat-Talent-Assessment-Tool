<?php
// config.php – DB connection + session + helpers

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database credentials
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'kid';

// Create connection
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

/**
 * Require user to have one of the specified roles
 * @param array|string $roles Allowed roles
 */
function require_role($roles) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], (array)$roles)) {
        header('Location: login.php?error=unauthorized');
        exit;
    }
}

/**
 * Escape string for safe SQL use
 * @param string $str
 * @return string
 */
function esc($str) {
    global $conn;
    return $conn->real_escape_string(trim($str));
}

/**
 * Helper: Convert grade letter to numeric score
 * @param string $grade
 * @return int
 */
function grade_to_numeric($grade) {
    return match($grade) {
        'A+' => 95,
        'A'  => 85,
        'B+' => 75,
        'B'  => 65,
        'C'  => 55,
        'D'  => 45,
        'F'  => 35,
        default => 0
    };
}
?>
