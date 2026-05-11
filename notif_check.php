<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0, 'latest' => '']);
    exit();
}
$pdo = getDB();
require_once 'notifications.php';
$count = getUnreadCount($pdo, $_SESSION['user_id']);
$latest = '';
if ($count > 0) {
    $notifs = getNotifications($pdo, $_SESSION['user_id'], 1);
    if (!empty($notifs)) $latest = $notifs[0]['message'];
}
header('Content-Type: application/json');
echo json_encode(['count' => (int)$count, 'latest' => $latest]);
