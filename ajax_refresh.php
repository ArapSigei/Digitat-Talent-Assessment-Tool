<?php
require 'config.php';

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
    } catch (Exception $e) { error_log($e->getMessage()); }
    return $count;
}

header('Content-Type: application/json');
$response = [];

$response['students'] = getCount($conn, "SELECT COUNT(*) AS total FROM users WHERE role = ?", ['kid'], "s");
$response['assessments'] = getCount($conn, "
    SELECT COUNT(*) AS total 
    FROM talent_assessments ta
    JOIN uploads up ON ta.upload_id = up.id
    JOIN users u ON up.user_id = u.id
    WHERE u.role = 'kid'
");
$response['talents'] = getCount($conn, "SELECT COUNT(*) AS total FROM uploads WHERE talent_status = 'Identified'");

$avg = 0;
try {
    $stmt = $conn->prepare("SELECT AVG(ta.grade) AS avg FROM talent_assessments ta JOIN uploads u ON ta.upload_id = u.id WHERE u.talent_status = 'Identified'");
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) $avg = $row['avg'] ? round((float)$row['avg']) : 0;
    $stmt->close();
} catch (Exception $e) { error_log($e->getMessage()); }
$response['talents_avg'] = $avg;

$response['users'] = getCount($conn, "SELECT COUNT(*) AS total FROM users");

echo json_encode($response);
$conn->close();
?>