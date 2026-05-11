<?php
if (!isset($pageTitle)) $pageTitle = 'Dashboard';
if (!isset($activePage)) $activePage = 'emp_dashboard';

$pdo = getDB();
require_once 'notifications.php';
$unreadCount   = getUnreadCount($pdo, $_SESSION['user_id']);
$notifications = getNotifications($pdo, $_SESSION['user_id'], 8);

if (isset($_GET['mark_read'])) {
    markAllRead($pdo, $_SESSION['user_id']);
    $unreadCount = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — EMS</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .notif-bell { position:relative; cursor:pointer; }
        .notif-badge { position:absolute; top:-6px; right:-6px; background:var(--danger); color:#fff; border-radius:50%; width:18px; height:18px; font-size:11px; font-weight:700; display:flex; align-items:center; justify-content:center; }
        .notif-dropdown { display:none; position:absolute; top:48px; right:0; width:320px; background:var(--bg2); border:1px solid var(--border); border-radius:12px; box-shadow:var(--shadow); z-index:300; overflow:hidden; }
        .notif-dropdown.open { display:block; }
        .notif-header { padding:14px 18px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; }
        .notif-header span { font-family:'Syne',sans-serif; font-weight:700; font-size:15px; }
        .notif-item { padding:12px 18px; border-bottom:1px solid var(--border); transition:background 0.15s; cursor:pointer; }
        .notif-item:hover { background:var(--bg3); }
        .notif-item.unread { background:rgba(232,197,71,0.04); border-left:3px solid var(--accent); }
        .notif-item .notif-title { font-size:13px; font-weight:600; margin-bottom:3px; }
        .notif-item .notif-msg { font-size:12px; color:var(--muted); }
        .notif-item .notif-time { font-size:11px; color:var(--muted); margin-top:4px; }
        .notif-empty { padding:30px; text-align:center; color:var(--muted); font-size:13px; }
        .notif-footer { padding:10px 18px; text-align:center; border-top:1px solid var(--border); }
    </style>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="logo-mark">
            <div class="logo-icon">E</div>
            <div>
                <div class="logo-text">EMS</div>
                <div class="logo-sub">Employee Portal</div>
            </div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-label">My Portal</div>
        <a href="emp_dashboard.php" class="nav-item <?= $activePage==='emp_dashboard'?'active':'' ?>">
            <span class="icon">⊞</span> Dashboard
        </a>
        <a href="emp_attendance.php" class="nav-item <?= $activePage==='emp_attendance'?'active':'' ?>">
            <span class="icon">📅</span> My Attendance
        </a>
        <a href="emp_leave.php" class="nav-item <?= $activePage==='emp_leave'?'active':'' ?>">
            <span class="icon">🏖️</span> My Leaves
        </a>
        <a href="emp_payslip.php" class="nav-item <?= $activePage==='emp_payslip'?'active':'' ?>">
            <span class="icon">₹</span> My Payslip
        </a>
    </nav>
    <div class="sidebar-footer">
        <div style="margin-bottom:8px;color:var(--text);font-size:13px;font-weight:500">
            👤 <?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>
        </div>
        <div style="font-size:11px;margin-bottom:10px;color:var(--muted)">Employee</div>
        <a href="logout.php" style="display:block;width:100%;padding:8px;background:rgba(239,68,68,0.12);color:#ef4444;border:1px solid rgba(239,68,68,0.2);border-radius:8px;text-align:center;text-decoration:none;font-size:13px;font-weight:500;">
            🚪 Logout
        </a>
        <div style="margin-top:10px;font-size:11px;color:var(--muted)">&copy; <?= date('Y') ?> EMS</div>
    </div>
</aside>
<main class="main">
    <div class="topbar">
        <div class="topbar-title"><?= htmlspecialchars($pageTitle) ?></div>
        <div class="topbar-actions">
            <?php if ($activePage === 'emp_leave'): ?>
                <a href="emp_leave_apply.php" class="btn btn-primary">+ Apply Leave</a>
            <?php endif; ?>

            <!-- Notification Bell -->
            <div class="notif-bell" id="notifBell" onclick="toggleNotif()">
                <div style="width:38px;height:38px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px">
                    🔔
                </div>
                <?php if ($unreadCount > 0): ?>
                <div class="notif-badge"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></div>
                <?php endif; ?>

                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-header">
                        <span>🔔 Notifications</span>
                        <?php if ($unreadCount > 0): ?>
                        <a href="?mark_read=1" style="font-size:12px;color:var(--accent);text-decoration:none">Mark all read</a>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($notifications)): ?>
                    <div class="notif-empty">No notifications yet 🎉</div>
                    <?php else: foreach ($notifications as $n):
                        $icon = match($n['type']) {
                            'leave'      => '🏖️',
                            'attendance' => '📅',
                            'salary'     => '₹',
                            'employee'   => '👤',
                            default      => '🔔'
                        };
                    ?>
                    <div class="notif-item <?= !$n['is_read'] ? 'unread' : '' ?>"
                         onclick="<?= $n['link'] ? "window.location='{$n['link']}'" : '' ?>">
                        <div class="notif-title"><span><?= $icon ?></span> <?= htmlspecialchars($n['title']) ?></div>
                        <div class="notif-msg"><?= htmlspecialchars($n['message']) ?></div>
                        <div class="notif-time"><?= timeAgo($n['created_at']) ?></div>
                    </div>
                    <?php endforeach; endif; ?>
                    <div class="notif-footer">
                        <a href="?mark_read=1" style="font-size:12px;color:var(--muted);text-decoration:none">Clear all</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="content fade-in">
        <?php flash(); ?>
