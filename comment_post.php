<?php
session_start();
header('Content-Type: application/json');
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['status'=>'error','message'=>'Unauthorized']);
    exit();
}

$upload_id = isset($_POST['upload_id']) ? intval($_POST['upload_id']) : 0;
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

if ($upload_id <= 0 || $comment === '') {
    echo json_encode(['status'=>'error','message'=>'Invalid input']);
    exit();
}

// Insert comment
$stmt = $conn->prepare("INSERT INTO comments (upload_id, user_id, comment) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $upload_id, $_SESSION['user_id'], $comment);
if ($stmt->execute()) {
    // return comment with username and timestamp for immediate display
    $id = $stmt->insert_id;

    // fetch username and timestamp
    $res = $conn->query("SELECT c.commented_at, u.username FROM comments c JOIN users u ON c.user_id = u.id WHERE c.id = $id");
    $row = $res->fetch_assoc();

    echo json_encode([
        'status' => 'success',
        'comment' => [
            'id' => $id,
            'username' => $row['username'],
            'comment' => htmlspecialchars($comment, ENT_QUOTES),
            'commented_at' => $row['commented_at']
        ]
    ]);
} else {
    echo json_encode(['status'=>'error','message'=>$stmt->error]);
}
$stmt->close();
?>
