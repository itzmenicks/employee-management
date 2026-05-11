<?php
// notifications.php - Notification helper functions

function createNotification($pdo, $userId, $title, $message, $type = 'general', $link = '') {
    try {
        $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?,?,?,?,?)")
            ->execute([$userId, $title, $message, $type, $link]);
    } catch (PDOException $e) {}
}

function notifyAllAdmins($pdo, $title, $message, $type = 'general', $link = '') {
    $stmt = $pdo->query("SELECT id FROM users WHERE role IN ('admin','hr') AND status='active'");
    $admins = $stmt->fetchAll();
    foreach ($admins as $admin) {
        createNotification($pdo, $admin['id'], $title, $message, $type, $link);
    }
}

function getUnreadCount($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}

function getNotifications($pdo, $userId, $limit = 10) {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
}

function markAllRead($pdo, $userId) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$userId]);
}

function timeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff/60) . ' min ago';
    if ($diff < 86400)  return floor($diff/3600) . ' hr ago';
    return floor($diff/86400) . ' days ago';
}
?>
