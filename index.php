<?php
require_once 'config.php';
require 'auth.php';
$pdo = getDB();

$totalEmp   = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
$activeEmp  = $pdo->query("SELECT COUNT(*) FROM employees WHERE status='active'")->fetchColumn();
$totalDepts = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
$totalPaid  = $pdo->query("SELECT COALESCE(SUM(net_salary),0) FROM salary_records WHERE status='paid'")->fetchColumn();
$pendingLeaves = $pdo->query("SELECT COUNT(*) FROM leave_applications WHERE status='pending'")->fetchColumn();
$todayAtt   = $pdo->query("SELECT COUNT(*) FROM attendance WHERE date=CURDATE() AND status='present'")->fetchColumn();

$recentEmps = $pdo->query("
    SELECT e.*, d.name AS dept_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    ORDER BY e.created_at DESC LIMIT 5
")->fetchAll();

$pendingLeavesData = $pdo->query("
    SELECT la.*, e.first_name, e.last_name, lt.name AS leave_type_name
    FROM leave_applications la
    JOIN employees e ON la.employee_id = e.id
    JOIN leave_types lt ON la.leave_type_id = lt.id
    WHERE la.status = 'pending'
    ORDER BY la.created_at DESC LIMIT 5
")->fetchAll();

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
require 'layout.php';
?>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total Employees</div>
        <div class="stat-value"><?= $totalEmp ?></div>
        <div class="stat-icon">◎</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Active</div>
        <div class="stat-value" style="color:var(--success)"><?= $activeEmp ?></div>
        <div class="stat-icon">✓</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Departments</div>
        <div class="stat-value"><?= $totalDepts ?></div>
        <div class="stat-icon">❖</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Paid (₹)</div>
        <div class="stat-value" style="font-size:24px;color:var(--accent)">₹<?= number_format($totalPaid, 0) ?></div>
        <div class="stat-icon">₹</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Pending Leaves</div>
        <div class="stat-value" style="color:#eab308"><?= $pendingLeaves ?></div>
        <div class="stat-icon">⏳</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Present Today</div>
        <div class="stat-value" style="color:var(--success)"><?= $todayAtt ?></div>
        <div class="stat-icon">📅</div>
    </div>
</div>

<!-- Quick Actions -->
<div style="margin-bottom:28px">
    <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:16px;margin-bottom:14px;color:var(--muted);text-transform:uppercase;letter-spacing:0.08em;font-size:12px">Quick Actions</div>
    <div style="display:flex;flex-wrap:wrap;gap:10px">
        <a href="employee_form.php" class="btn btn-primary">+ Add Employee</a>
        <a href="department_form.php" class="btn btn-ghost">+ Add Department</a>
        <a href="attendance_mark.php" class="btn btn-ghost">📅 Mark Attendance</a>
        <a href="salary_form.php" class="btn btn-ghost">₹ Add Salary</a>
        <a href="leaves.php" class="btn btn-ghost" style="<?= $pendingLeaves > 0 ? 'color:#eab308;border-color:#eab308' : '' ?>">
            🏖️ Leave Approvals <?= $pendingLeaves > 0 ? "($pendingLeaves)" : '' ?>
        </a>
        <a href="user_form.php" class="btn btn-ghost">👥 Add User</a>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">

<!-- Recent Employees -->
<div class="table-wrapper">
    <div class="table-header">
        <div class="table-title">Recent Employees</div>
        <a href="employees.php" class="btn btn-ghost btn-sm">View All →</a>
    </div>
    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th>Department</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($recentEmps)): ?>
            <tr><td colspan="4" style="text-align:center;color:var(--muted);padding:30px">No employees yet.</td></tr>
            <?php else: foreach ($recentEmps as $e): ?>
            <tr>
                <td>
                    <span class="avatar"><?= strtoupper(substr($e['first_name'],0,1).substr($e['last_name'],0,1)) ?></span>
                    <span class="emp-name"><?= htmlspecialchars($e['first_name'].' '.$e['last_name']) ?></span>
                </td>
                <td><span class="badge badge-dept"><?= htmlspecialchars($e['dept_name'] ?? '—') ?></span></td>
                <td><span class="badge badge-<?= $e['status'] ?>"><?= ucfirst($e['status']) ?></span></td>
                <td>
                    <a href="employee_form.php?id=<?= $e['id'] ?>" class="btn btn-ghost btn-sm">Edit</a>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- Pending Leaves -->
<div class="table-wrapper">
    <div class="table-header">
        <div class="table-title">Pending Leaves ⏳</div>
        <a href="leaves.php" class="btn btn-ghost btn-sm">View All →</a>
    </div>
    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th>Type</th>
                <th>Days</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($pendingLeavesData)): ?>
            <tr><td colspan="4" style="text-align:center;color:var(--muted);padding:30px">No pending leaves! ✅</td></tr>
            <?php else: foreach ($pendingLeavesData as $l): ?>
            <tr>
                <td>
                    <span class="avatar"><?= strtoupper(substr($l['first_name'],0,1).substr($l['last_name'],0,1)) ?></span>
                    <span class="emp-name"><?= htmlspecialchars($l['first_name'].' '.$l['last_name']) ?></span>
                </td>
                <td><span class="badge badge-dept" style="font-size:11px"><?= htmlspecialchars($l['leave_type_name']) ?></span></td>
                <td style="font-weight:700;color:var(--accent)"><?= $l['total_days'] ?></td>
                <td>
                    <a href="leaves.php?action=approved&id=<?= $l['id'] ?>"
                       class="btn btn-sm" style="background:rgba(34,197,94,0.12);color:var(--success);border:1px solid rgba(34,197,94,0.2)">✓</a>
                    <a href="leaves.php?action=rejected&id=<?= $l['id'] ?>"
                       class="btn btn-sm" style="background:rgba(239,68,68,0.12);color:var(--danger);border:1px solid rgba(239,68,68,0.2)">✗</a>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

</div>

<?php require 'layout_footer.php'; ?>