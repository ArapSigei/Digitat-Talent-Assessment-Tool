<?php
session_start();
include 'config.php';

// CSRF token generation function
if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;
$base_url = '/';

// Auto-detect base URL for XAMPP/local development
if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'localhost') {
    $script_name = $_SERVER['SCRIPT_NAME'];
    if (strpos($script_name, '/DGT/') !== false) {
        $base_url = '/DGT/';
    } elseif (strpos($script_name, '/digital-talent/') !== false) {
        $base_url = '/digital-talent/';
    }
}

// Debug session
error_log("Dashboard accessed: user_id=$user_id, username={$_SESSION['user']}, base_url=$base_url at " . date('Y-m-d H:i:s'));

// Fetch user details
$user = ['username' => $_SESSION['user'], 'profile_pic' => 'Uploads/profiles/default.png'];
try {
    $stmt = $conn->prepare("SELECT username, profile_pic FROM users WHERE id = ?");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        throw new Exception("Database prepare failed");
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($row = $res->fetch_assoc()) {
        $user['username'] = $row['username'];
        $profile_pic = $row['profile_pic'] ?: 'Uploads/profiles/default.png';
        $profile_pic_path = __DIR__ . '/' . $profile_pic;
        
        if (!file_exists($profile_pic_path)) {
            $user['profile_pic'] = 'Uploads/profiles/default.png';
        } else {
            $user['profile_pic'] = $profile_pic;
        }
        $_SESSION['profile_pic'] = $user['profile_pic'];
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("User fetch error: " . $e->getMessage());
}

// Stats functions with error handling
function getCount($conn, $sql, $params = [], $types = "") {
    try {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed for count: " . $conn->error);
            return 0;
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $count = (int)($row['total'] ?? 0);
        $stmt->close();
        return $count;
    } catch (Exception $e) {
        error_log("Count query error: " . $e->getMessage());
        return 0;
    }
}

// Check if comments table exists and create if not
function ensureCommentsTable($conn) {
    try {
        $check = $conn->query("SHOW TABLES LIKE 'comments'");
        if ($check->num_rows == 0) {
            $sql = "CREATE TABLE IF NOT EXISTS comments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                upload_id INT NOT NULL,
                user_id INT NOT NULL,
                username VARCHAR(100) NOT NULL,
                comment TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_upload (upload_id),
                INDEX idx_user (user_id)
            )";
            $result = $conn->query($sql);
            if (!$result) {
                error_log("Failed to create comments table: " . $conn->error);
                return false;
            }
            error_log("Comments table created or already exists");
            return true;
        }
        return true;
    } catch (Exception $e) {
        error_log("ensureCommentsTable error: " . $e->getMessage());
        return false;
    }
}

// FIXED: Function to get comments for an upload with proper error handling
function getComments($conn, $upload_id, $limit = 3) {
    $comments = [];
    try {
        if (!is_numeric($upload_id) || $upload_id <= 0) {
            error_log("Invalid upload_id: " . $upload_id);
            return $comments;
        }
        
        // First check if comments table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'comments'");
        if ($table_check->num_rows == 0) {
            error_log("Comments table does not exist for upload_id: " . $upload_id);
            return $comments;
        }
        
        // Prepare the statement with error checking
        $sql = "SELECT c.comment, c.username, c.created_at 
                FROM comments c 
                WHERE c.upload_id = ? 
                ORDER BY c.created_at DESC 
                LIMIT ?";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Failed to prepare comments query: " . $conn->error . " | SQL: " . $sql);
            return $comments;
        }
        
        $stmt->bind_param("ii", $upload_id, $limit);
        
        if (!$stmt->execute()) {
            error_log("Failed to execute comments query: " . $stmt->error);
            $stmt->close();
            return $comments;
        }
        
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $comments[] = $row;
        }
        
        $stmt->close();
        return $comments;
        
    } catch (Exception $e) {
        error_log("Get comments error for upload_id $upload_id: " . $e->getMessage());
        return $comments;
    }
}

// Create comments table if needed
$comments_table_exists = ensureCommentsTable($conn);

// Get stats
$studentCount = getCount($conn, "SELECT COUNT(*) AS total FROM users WHERE role = ?", ['kid'], "s");
$assessmentsCompleted = getCount($conn, "SELECT COUNT(*) AS total FROM talent_assessments");
$talentsIdentified = getCount($conn, "SELECT COUNT(*) AS total FROM uploads WHERE talent_status = ?", ['Identified'], "s");

// Role check for buttons
$allowed_roles = ['teacher', 'admin'];
$has_access = in_array($role, $allowed_roles);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Digital Talent Assessment Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background: #f8f9fa; 
            margin-bottom: 60px; 
            min-height: 100vh; 
        }
        .navbar-custom { 
            background-color: maroon; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
        }
        .navbar-custom .navbar-brand, 
        .navbar-custom .nav-link { 
            color: #ffffff !important; 
        }
        .navbar-custom .nav-link:hover { 
            color: #adb5bd !important; 
        }
        .nav-link.active { 
            background-color: rgba(255,255,255,0.1); 
            border-radius: 5px; 
        }
        .main-content { 
            padding: 20px; 
            max-width: 1200px; 
            margin: 0 auto; 
        }
        .cards-grid { 
            display: flex; 
            gap: 20px; 
            flex-wrap: wrap; 
            justify-content: center; 
        }
        .stat-card { 
            flex: 1; 
            min-width: 200px; 
            max-width: 300px; 
        }
        .card-value { 
            font-size: 2rem; 
            font-weight: bold; 
            color: #495057; 
        }
        .user-info { 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            margin: 20px 0; 
        }
        .user-info img { 
            width: 80px; 
            height: 80px; 
            border-radius: 50%; 
            object-fit: cover; 
            border: 3px solid #ddd; 
        }
        .talent-actions { 
            margin: 30px 0; 
            text-align: center; 
        }
        .talent-actions .btn { 
            margin: 5px; 
            padding: 12px 24px; 
            font-size: 1.1rem; 
        }
        .footer { 
            background-color: green; 
            color: #ffffff; 
            text-align: center; 
            padding: 15px 0; 
            position: fixed; 
            bottom: 0; 
            width: 100%; 
            z-index: 1000; 
            left: 0; 
        }
        .upload-card { 
            transition: transform 0.2s; 
            border-radius: 10px; 
        }
        .upload-card:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 4px 8px rgba(0,0,0,0.1); 
        }
        .upload-media { 
            max-width: 100%; 
            height: auto; 
            border-radius: 8px; 
            max-height: 250px; 
            object-fit: cover; 
            cursor: pointer; 
        }
        .comments-section {
            border-top: 1px solid #dee2e6;
            padding-top: 10px;
            margin-top: 10px;
        }
        .comments-list { 
            max-height: 120px; 
            overflow-y: auto; 
            padding: 8px; 
            border: 1px solid #e9ecef; 
            border-radius: 6px; 
            background: #f8f9fa; 
            font-size: 0.85em;
        }
        .comment-item {
            padding: 6px 0;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 5px;
        }
        .comment-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .comment-author {
            font-weight: 600;
            color: #495057;
            margin-bottom: 2px;
        }
        .comment-text {
            color: #6c757d;
            word-break: break-word;
        }
        .comment-time {
            font-size: 0.75em;
            color: #adb5bd;
        }
        .comment-form {
            margin-top: 10px;
        }
        .comment-form textarea {
            resize: vertical;
            min-height: 60px;
        }
        .comment-count {
            background: #007bff;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75em;
            margin-left: 5px;
        }
        #debug-info { 
            background: #fff3cd; 
            border: 1px solid #ffeaa7; 
            padding: 15px; 
            margin: 20px 0; 
            border-radius: 5px; 
            font-family: monospace; 
            font-size: 0.85em; 
        }
        .media-container {
            position: relative;
            height: 250px;
        }
        .media-placeholder {
            height: 250px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
        }
        @media (max-width: 768px) {
            .cards-grid { gap: 10px; }
            .stat-card { min-width: 150px; }
            .comments-list { max-height: 100px; }
        }
    </style>
</head>
<body>
<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            <i class="fas fa-star"></i> Digital Talent
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-home"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="about.php">
                        <i class="fas fa-info-circle"></i> About
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="contacts.php">
                        <i class="fas fa-phone"></i> Contacts
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php">
                        <i class="fas fa-user"></i> Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="main-content">
    <!-- Header -->
    <header class="mb-4">
        <h2><i class="fas fa-trophy"></i> Talent Hub Dashboard</h2>
        <div class="user-info">
            <img src="<?php echo htmlspecialchars($base_url . $user['profile_pic']); ?>" 
                 alt="Profile" 
                 onerror="this.src='Uploads/profiles/default.png'">
            <h4><?php echo htmlspecialchars($user['username']); ?></h4>
            <p class="text-muted"><?php echo ucfirst(htmlspecialchars($role)); ?> Role</p>
            <?php if (!$comments_table_exists): ?>
                <div class="alert alert-warning mt-2">
                    <i class="fas fa-exclamation-triangle"></i> Comments system not available
                </div>
            <?php endif; ?>
        </div>
    </header>

    <!-- Role-based Actions -->
    <?php if ($role): ?>
    <div class="talent-actions">
        <?php if ($role === 'kid'): ?>
            <a href="talent/upload_talent.php" class="btn btn-primary btn-lg">
                <i class="fas fa-upload"></i> Upload Activity
            </a>
            <a href="talent/view_results.php" class="btn btn-success btn-lg">
                <i class="fas fa-star"></i> My Scores
            </a>
        <?php elseif ($role === 'parent'): ?>
            <a href="talent/view_kid_results.php" class="btn btn-info btn-lg">
                <i class="fas fa-child"></i> View Kid Results
            </a>
        <?php elseif (in_array($role, ['teacher', 'admin'])): ?>
            <a href="talent/view_submissions.php" class="btn btn-warning btn-lg">
                <i class="fas fa-tasks"></i> Assess Submissions
            </a>
            <?php if ($role === 'admin'): ?>
                <a href="admin_settings.php" class="btn btn-secondary btn-lg">
                    <i class="fas fa-cogs"></i> Admin Settings
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <section class="cards-grid mt-4">
        <div class="card stat-card p-4 text-center">
            <i class="fas fa-users fa-2x text-primary mb-2"></i>
            <h5>Total Students</h5>
            <p class="card-value"><?php echo $studentCount; ?></p>
        </div>
        <div class="card stat-card p-4 text-center">
            <i class="fas fa-clipboard-check fa-2x text-success mb-2"></i>
            <h5>Assessments</h5>
            <p class="card-value"><?php echo $assessmentsCompleted; ?></p>
        </div>
        <div class="card stat-card p-4 text-center">
            <i class="fas fa-lightbulb fa-2x text-warning mb-2"></i>
            <h5>Talents Found</h5>
            <p class="card-value"><?php echo $talentsIdentified; ?></p>
        </div>
    </section>

    <!-- Kids' Activities with Comments -->
    <div class="mt-5">
        <h3><i class="fas fa-camera"></i> Kids' Activities</h3>
        <div id="uploadsContainer">
            <?php
            try {
                // Check uploads table
                $table_check = $conn->query("SHOW TABLES LIKE 'uploads'");
                if ($table_check->num_rows === 0) {
                    echo '<div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> 
                            Uploads table not found. Please set up database.
                          </div>';
                } else {
                    // Check table structure
                    $columns = $conn->query("DESCRIBE uploads");
                    $has_file_path = false;
                    $has_file_type = false;
                    $has_created_at = false;
                    
                    while ($col = $columns->fetch_assoc()) {
                        if ($col['Field'] === 'file_path') $has_file_path = true;
                        if ($col['Field'] === 'file_type') $has_file_type = true;
                        if ($col['Field'] === 'created_at') $has_created_at = true;
                    }

                    if ($has_file_path) {
                        $order_by = $has_created_at ? 'u.created_at DESC' : 'u.id DESC';
                        
                        // Simplified query without subquery to avoid prepare issues
                        $sql = "SELECT u.id, u.file_path, u.file_type" . 
                               ($has_created_at ? ", u.created_at" : "") . ", u.user_id,
                                      usr.username
                               FROM uploads u 
                               JOIN users usr ON u.user_id = usr.id 
                               WHERE usr.role = 'kid'
                                 AND u.file_path IS NOT NULL 
                                 AND u.file_path != ''
                               ORDER BY $order_by 
                               LIMIT 12";
                        
                        $stmt = $conn->prepare($sql);
                        if (!$stmt) {
                            error_log("Main uploads query failed: " . $conn->error);
                            echo '<div class="alert alert-danger">Database query error</div>';
                        } else {
                            $stmt->execute();
                            $res = $stmt->get_result();
                            $total_comments = 0;
                            
                            if ($res->num_rows > 0) {
                                echo '<div class="row">';
                                $count = 0;
                                
                                while ($row = $res->fetch_assoc()) {
                                    $file_path = trim($row['file_path']);
                                    $file_type = trim($row['file_type'] ?? 'image');
                                    $full_path = __DIR__ . '/' . ltrim($file_path, '/');
                                    $display_url = rtrim($base_url, '/') . '/' . ltrim($file_path, '/');
                                    $exists = file_exists($full_path);
                                    
                                    // Count comments separately to avoid subquery issues
                                    $comment_count = 0;
                                    if ($comments_table_exists) {
                                        $comment_count = getCount($conn, 
                                            "SELECT COUNT(*) AS total FROM comments WHERE upload_id = ?", 
                                            [$row['id']], "i");
                                        $total_comments += $comment_count;
                                    }
                                    
                                    $count++;
                                    
                                    // Get recent comments with error handling
                                    $comments = [];
                                    if ($comments_table_exists) {
                                        $comments = getComments($conn, $row['id'], 3);
                                    }
                                    ?>
                                    <div class="col-md-6 col-lg-4 mb-4">
                                        <div class="card upload-card h-100">
                                            <div class="card-body d-flex flex-column">
                                                <h6 class="card-title text-muted">
                                                    <i class="fas fa-user"></i> 
                                                    <?php echo htmlspecialchars($row['username']); ?>
                                                    <?php if ($comment_count > 0): ?>
                                                        <span class="badge bg-primary ms-2">
                                                            <i class="fas fa-comments"></i> <?php echo $comment_count; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </h6>
                                                
                                                <div class="media-container flex-grow-1 mb-3">
                                                    <?php 
                                                    $is_video = in_array(strtolower(pathinfo($file_path, PATHINFO_EXTENSION)), 
                                                                ['mp4', 'avi', 'mov', 'webm']) || 
                                                                strtolower($file_type) === 'video';
                                                    ?>
                                                    <?php if ($exists && $file_path): ?>
                                                        <?php if ($is_video): ?>
                                                            <video src="<?php echo htmlspecialchars($display_url); ?>" 
                                                                   controls 
                                                                   class="upload-media w-100 rounded"
                                                                   style="height: 200px; object-fit: cover;"
                                                                   preload="metadata"
                                                                   onerror="this.nextElementSibling.style.display='block'; this.style.display='none';">
                                                                <div>Your browser does not support video.</div>
                                                            </video>
                                                        <?php else: ?>
                                                            <img src="<?php echo htmlspecialchars($display_url . '?v=' . time()); ?>" 
                                                                 class="upload-media w-100 rounded"
                                                                 style="height: 200px; object-fit: cover;"
                                                                 alt="Upload"
                                                                 onerror="this.nextElementSibling.style.display='block'; this.style.display='none';">
                                                        <?php endif; ?>
                                                        <div class="media-placeholder d-none">
                                                            <i class="fas fa-image fa-3x text-muted"></i>
                                                            <p class="text-muted mt-2">Media not available</p>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="media-placeholder">
                                                            <i class="fas fa-image fa-3x text-muted"></i>
                                                            <p class="text-muted mt-2"><?php echo $exists ? 'Loading...' : 'File Missing'; ?></p>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="mt-auto">
                                                    <small class="text-muted">
                                                        <?php echo $exists ? 
                                                            ($is_video ? 'Video' : 'Image') . ' • ' . 
                                                            number_format(filesize($full_path) / 1024, 1) . ' KB' : 
                                                            'Not available'; ?>
                                                    </small>
                                                    
                                                    <!-- Comments Section -->
                                                    <div class="comments-section">
                                                        <div class="comments-list" id="comments-<?php echo $row['id']; ?>">
                                                            <?php if ($comments_table_exists && !empty($comments)): ?>
                                                                <?php foreach ($comments as $comment): ?>
                                                                    <div class="comment-item">
                                                                        <div class="comment-author">
                                                                            <?php echo htmlspecialchars($comment['username']); ?>
                                                                        </div>
                                                                        <div class="comment-text">
                                                                            <?php echo htmlspecialchars($comment['comment']); ?>
                                                                        </div>
                                                                        <div class="comment-time">
                                                                            <?php echo date('M j, Y g:i A', strtotime($comment['created_at'])); ?>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                                <?php if (count($comments) >= 3): ?>
                                                                    <div class="text-center pt-2">
                                                                        <small class="text-primary">
                                                                            +<?php echo $comment_count - 3; ?> more...
                                                                        </small>
                                                                    </div>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <small class="text-muted">
                                                                    <?php echo $comments_table_exists ? 'No comments yet' : 'Comments disabled'; ?>
                                                                </small>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <!-- Comment Form - Only if table exists and user can comment -->
                                                        <?php if ($comments_table_exists && in_array($role, ['teacher', 'admin', 'parent'])): ?>
                                                            <form class="comment-form" method="POST" 
                                                                  action="add_comment.php" id="comment-form-<?php echo $row['id']; ?>">
                                                                <input type="hidden" name="upload_id" value="<?php echo $row['id']; ?>">
                                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                                <div class="input-group input-group-sm mt-2">
                                                                    <textarea class="form-control" name="comment" 
                                                                              placeholder="Add a comment..." 
                                                                              maxlength="500" 
                                                                              required></textarea>
                                                                    <button type="submit" class="btn btn-outline-primary">
                                                                        <i class="fas fa-paper-plane"></i>
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php 
                                }
                                echo '</div>';
                                echo '<p class="text-center text-muted mt-3">
                                        Showing ' . $count . ' activities' . 
                                        ($comments_table_exists ? ' • Total Comments: ' . $total_comments : '') . '
                                      </p>';
                                $stmt->close();
                            } else {
                                echo '<div class="alert alert-info text-center py-4">
                                        <i class="fas fa-inbox fa-3x mb-3 text-muted"></i>
                                        <h5>No Activities Yet</h5>
                                        <p class="mb-0">No student uploads available.</p>
                                        ' . ($role === 'kid' ? 
                                            '<a href="talent/upload_talent.php" class="btn btn-primary btn-sm mt-2">Upload First!</a>' : 
                                            '') . '
                                      </div>';
                            }
                        }
                    } else {
                        echo '<div class="alert alert-danger">
                                Uploads table missing file_path column
                              </div>';
                    }
                }
            } catch (Exception $e) {
                error_log("Activities error: " . $e->getMessage());
                echo '<div class="alert alert-danger">
                        Error: ' . htmlspecialchars($e->getMessage()) . '
                      </div>';
            }
            ?>
        </div>
    </div>
</main>

<!-- Footer -->
<footer class="footer">
    <p>&copy; <?php echo date('Y'); ?> Digital Talent | 
       <a href="mailto:support@digitaltalent.com">
           <i class="fas fa-envelope"></i> Contact Us
       </a>
    </p>
</footer>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // Navbar active state
    $('.nav-link').click(function() {
        $('.nav-link').removeClass('active');
        $(this).addClass('active');
    });

    // Handle comment form submissions with error handling
    $('form[id^="comment-form-"]').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const uploadId = form.find('input[name="upload_id"]').val();
        const commentText = form.find('textarea[name="comment"]').val().trim();
        
        if (!commentText) {
            alert('Please enter a comment');
            return;
        }

        // Disable submit button during request
        const submitBtn = form.find('button[type="submit"]').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: 'add_comment.php',
            method: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Add new comment to list
                    const newComment = `
                        <div class="comment-item">
                            <div class="comment-author">${$('<div>').text(response.username).html()}</div>
                            <div class="comment-text">${$('<div>').text(commentText).html()}</div>
                            <div class="comment-time">${response.timestamp}</div>
                        </div>
                    `;
                    const commentsList = $(`#comments-${uploadId}`);
                    
                    // Remove "no comments" message if present
                    commentsList.find('.text-muted').hide();
                    commentsList.prepend(newComment);
                    
                    // Update comment count badge
                    let badge = commentsList.siblings('.badge');
                    let currentCount = parseInt(badge.find('i + *').text() || 0);
                    currentCount++;
                    if (badge.length) {
                        badge.html(`<i class="fas fa-comments"></i> ${currentCount}`);
                    } else {
                        commentsList.before(`<span class="badge bg-primary ms-2"><i class="fas fa-comments"></i> ${currentCount}</span>`);
                    }
                    
                    // Clear form and re-enable button
                    form.find('textarea').val('');
                    submitBtn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i>');
                    
                    // Show success message
                    const successMsg = `<div class="alert alert-success alert-dismissible fade show mt-2 small" role="alert">
                        Comment added successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>`;
                    form.after(successMsg);
                    
                    // Auto-remove success message after 3 seconds
                    setTimeout(() => $('.alert-success').fadeOut(), 3000);
                } else {
                    alert('Error: ' + (response.message || 'Failed to add comment'));
                    submitBtn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                alert('Connection error. Please try again.');
                submitBtn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i>');
            }
        });
    });

    // Auto-resize textarea
    $('textarea').on('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
});
</script>
</body>
</html>
<?php
if (isset($conn)) {
    $conn->close();
}
?>