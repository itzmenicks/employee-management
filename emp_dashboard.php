<?php
require_once 'config.php';
require 'auth_employee.php';
$pdo = getDB();

$empId = $_SESSION['employee_id'];

$emp = $pdo->prepare("SELECT e.*, d.name AS dept_name FROM employees e LEFT JOIN departments d ON e.department_id = d.id WHERE e.id = ?");
$emp->execute([$empId]);
$emp = $emp->fetch();

$month = date('m'); $year = date('Y');
$att = $pdo->prepare("SELECT COUNT(*) as total, SUM(status='present') as present, SUM(status='absent') as absent, SUM(status='late') as late FROM attendance WHERE employee_id = ? AND MONTH(date) = ? AND YEAR(date) = ?");
$att->execute([$empId, $month, $year]);
$attStats = $att->fetch();

$leaves = $pdo->prepare("SELECT SUM(status='pending') as pending, SUM(status='approved') as approved FROM leave_applications WHERE employee_id = ?");
$leaves->execute([$empId]);
$leaveStats = $leaves->fetch();

$recentAtt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? ORDER BY date DESC LIMIT 7");
$recentAtt->execute([$empId]);
$recentAtt = $recentAtt->fetchAll();

$recentLeaves = $pdo->prepare("SELECT la.*, lt.name AS leave_type_name FROM leave_applications la JOIN leave_types lt ON la.leave_type_id = lt.id WHERE la.employee_id = ? ORDER BY la.created_at DESC LIMIT 5");
$recentLeaves->execute([$empId]);
$recentLeaves = $recentLeaves->fetchAll();

$pageTitle = 'My Dashboard';
$activePage = 'emp_dashboard';
require 'layout_employee.php';
?>

<div style="background:linear-gradient(135deg,var(--bg2),var(--bg3));border:1px solid var(--border);border-radius:12px;padding:28px 32px;margin-bottom:28px">
    <div style="font-size:13px;color:var(--muted);margin-bottom:6px"><?= date('l, d F Y') ?></div>
    <div style="font-family:'Syne',sans-serif;font-size:26px;font-weight:800;margin-bottom:4px">
        Namaste, <?= htmlspecialchars($emp['first_name']) ?>! 👋
    </div>
    <div style="color:var(--muted);font-size:14px">
        <?= htmlspecialchars($emp['designation'] ?? '') ?> &bull; <?= htmlspecialchars($emp['dept_name'] ?? '') ?>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:28px">
    <div class="stat-card">
        <div class="stat-label">Present This Month</div>
        <div class="stat-value" style="color:var(--success)"><?= $attStats['present'] ?? 0 ?></div>
        <div class="stat-icon">✓</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Absent This Month</div>
        <div class="stat-value" style="color:var(--danger)"><?= $attStats['absent'] ?? 0 ?></div>
        <div class="stat-icon">✗</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Leave Pending</div>
        <div class="stat-value" style="color:#eab308"><?= $leaveStats['pending'] ?? 0 ?></div>
        <div class="stat-icon">⏳</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Leave Approved</div>
        <div class="stat-value" style="color:var(--success)"><?= $leaveStats['approved'] ?? 0 ?></div>
        <div class="stat-icon">🏖️</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
<div class="table-wrapper">
    <div class="table-header">
        <div class="table-title">Recent Attendance</div>
        <a href="emp_attendance.php" class="btn btn-ghost btn-sm">View All →</a>
    </div>
    <table>
        <thead><tr><th>Date</th><th>Check In</th><th>Check Out</th><th>Status</th></tr></thead>
        <tbody>
            <?php if (empty($recentAtt)): ?>
            <tr><td colspan="4" style="text-align:center;color:var(--muted);padding:20px">No records</td></tr>
            <?php else: foreach ($recentAtt as $a): ?>
            <tr>
                <td style="font-size:13px"><?= date('d M', strtotime($a['date'])) ?></td>
                <td style="color:var(--success);font-size:13px"><?= $a['check_in'] ? date('h:i A', strtotime($a['check_in'])) : '—' ?></td>
                <td style="color:var(--danger);font-size:13px"><?= $a['check_out'] ? date('h:i A', strtotime($a['check_out'])) : '—' ?></td>
                <td><?php $bc=match($a['status']){'present'=>'badge-active','absent'=>'badge-inactive','late'=>'badge-pending',default=>'badge-dept'}; ?><span class="badge <?= $bc ?>"><?= ucfirst(str_replace('_',' ',$a['status'])) ?></span></td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
<div class="table-wrapper">
    <div class="table-header">
        <div class="table-title">My Leave Applications</div>
        <a href="emp_leave.php" class="btn btn-ghost btn-sm">View All →</a>
    </div>
    <table>
        <thead><tr><th>Type</th><th>From</th><th>Days</th><th>Status</th></tr></thead>
        <tbody>
            <?php if (empty($recentLeaves)): ?>
            <tr><td colspan="4" style="text-align:center;color:var(--muted);padding:20px">No applications</td></tr>
            <?php else: foreach ($recentLeaves as $l): ?>
            <tr>
                <td><span class="badge badge-dept" style="font-size:11px"><?= htmlspecialchars($l['leave_type_name']) ?></span></td>
                <td style="font-size:13px"><?= date('d M', strtotime($l['from_date'])) ?></td>
                <td style="font-weight:700;color:var(--accent)"><?= $l['total_days'] ?></td>
                <td><?php $bc=match($l['status']){'approved'=>'badge-active','rejected'=>'badge-inactive',default=>'badge-pending'}; ?><span class="badge <?= $bc ?>"><?= ucfirst($l['status']) ?></span></td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
</div>

<?php require 'layout_employee_footer.php'; ?>
