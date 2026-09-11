<?php
require_once __DIR__ . '/../config.php';
require_role('student');

$userId = get_current_user_id();
$reportId = (int)($_GET['id'] ?? 0);

if ($reportId > 0) {
    // Check if already favorited
    $chk = $conn->prepare('SELECT id FROM favorites WHERE user_id = ? AND report_id = ?');
    $chk->bind_param('ii', $userId, $reportId);
    $chk->execute();
    $fav = $chk->get_result()->fetch_assoc();
    $chk->close();

    if ($fav) {
        // Remove favorite
        $del = $conn->prepare('DELETE FROM favorites WHERE id = ?');
        $del->bind_param('i', $fav['id']);
        $del->execute();
        $del->close();
    } else {
        // Add favorite
        $ins = $conn->prepare('INSERT INTO favorites (user_id, report_id) VALUES (?, ?)');
        $ins->bind_param('ii', $userId, $reportId);
        $ins->execute();
        $ins->close();
    }
}

$referer = $_SERVER['HTTP_REFERER'] ?? base_url('student/dashboard.php');
header('Location: ' . $referer);
exit();
