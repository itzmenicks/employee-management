<?php
require_once 'config.php';
require 'auth.php';
require_once 'notifications.php';
$pdo = getDB();

if ($_SESSION['user_role'] === 'employee') {
    header('Location: emp_leave.php');
    exit();
}

// Handle approve/reject
if (isset($_GET['action']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $action = in_array($_GET['action'], ['approved','rejected']) ? $_GET['action'] : null;
    if ($action) {
        $pdo->prepare("UPDATE leave_applications SET status=?, approved_by=? WHERE id=?")
            ->execute([$action, $_SESSION['user_id'], $_GET['id']]);

        // Get leave details
        $la = $pdo->prepare("
            SELECT la.*, e.first_name, e.last_name, lt.name AS leave_type_name, u.id AS user_id
            FROM leave_applications la
            JOIN employees e ON la.employee_id = e.id
            JOIN leave_types lt ON la.leave_type_id = lt.id
            LEFT JOIN users u ON u.employee_id = e.id
            WHERE la.id = ?
        ");
        $la->execute([$_GET['id']]);
        $la = $la->fetch();

        if ($la) {
            $empName = $la['first_name'] . ' ' . $la['last_name'];
            $emoji   = $action === 'approved' ? '✅' : '❌';

            // Notify employee
            if ($la['user_id']) {
                createNotification(
                    $pdo,
                    $la['user_id'],
                    "Leave $emoji " . ucfirst($action),
                    "Your {$la['leave_type_name']} for {$la['total_days']} day(s) has been " . ucfirst($action) . ".",
                    'leave',
                    'emp_leave.php'
                );
            }

            // Notify all admins
            notifyAllAdmins(
                $pdo,
                "Leave $emoji " . ucfirst($action),
                "$empName's {$la['leave_type_name']} for {$la['total_days']} day(s) has been " . ucfirst($action) . ".",
                'leave',
                'leaves.php'
            );
        }

        $flashMsg = $action === 'approved'
            ? '✅ Leave Approved successfully!'
            : '❌ Leave Rejected successfully!';
        redirect('leaves.php', $flashMsg);
    }
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $pdo->prepare("DELETE FROM leave_applications WHERE id = ?")->execute([$_GET['delete']]);
    redirect('leaves.php', 'Leave application deleted.');
}

// Filters
$search  = sanitize($_GET['search'] ?? '');
$status  = $_GET['status'] ?? '';
$typeId  = $_GET['type'] ?? '';

$where  = ["1=1"];
$params = [];
if ($search) {
    $where[]  = "(e.first_name LIKE ? OR e.last_name LIKE ? OR e.emp_code LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s]);
}
if ($status) { $where[] = "la.status = ?";        $params[] = $status; }
if ($typeId) { $where[] = "la.leave_type_id = ?"; $params[] = $typeId; }

$whereSQL = "WHERE " . implode(" AND ", $where);

$records = $pdo->prepare("
    SELECT la.*, e.first_name, e.last_name, e.emp_code, lt.name AS leave_type_name, d.name AS dept_name
    FROM leave_applications la
    JOIN employees e ON la.employee_id = e.id
    JOIN leave_types lt ON la.leave_type_id = lt.id
    LEFT JOIN departments d ON e.department_id = d.id
    $whereSQL
    ORDER BY la.status = 'pending' DESC, la.created_at DESC
");
$records->execute($params);
$records = $records->fetchAll();

$pending  = count(array_filter($records, fn($r) => $r['status'] === 'pending'));
$approved = count(array_filter($records, fn($r) => $r['status'] === 'approved'));
$rejected = count(array_filter($records, fn($r) => $r['status'] === 'rejected'));

$leaveTypes = $pdo->query("SELECT * FROM leave_types ORDER BY name")->fetchAll();

$pageTitle  = 'Leave Approvals';
$activePage = 'leaves';
require 'layout.php';
?>

<div class="stats-grid" style="margin-bottom:24px">
    <div class="stat-card">
        <div class="stat-label">Total</div>
        <div class="stat-value"><?= count($records) ?></div>
        <div class="stat-icon">📋</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Pending</div>
        <div class="stat-value" style="color:#eab308"><?= $pending ?></div>
        <div class="stat-icon">⏳</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Approved</div>
        <div class="stat-value" style="color:var(--success)"><?= $approved ?></div>
        <div class="stat-icon">✓</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Rejected</div>
        <div class="stat-value" style="color:var(--danger)"><?= $rejected ?></div>
        <div class="stat-icon">✗</div>
    </div>
</div>

<form method="GET" class="search-bar">
    <div class="search-input-wrap">
        <span class="search-icon">🔍</span>
        <input type="text" name="search" class="input-search" placeholder="Search employee…" value="<?= htmlspecialchars($search) ?>">
    </div>
    <select name="type" class="filter-select">
        <option value="">All Leave Types</option>
        <?php foreach ($leaveTypes as $lt): ?>
        <option value="<?= $lt['id'] ?>" <?= $typeId==$lt['id']?'selected':'' ?>><?= htmlspecialchars($lt['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="status" class="filter-select">
        <option value="">All Status</option>
        <option value="pending"  <?= $status==='pending' ?'selected':'' ?>>⏳ Pending</option>
        <option value="approved" <?= $status==='approved'?'selected':'' ?>>✅ Approved</option>
        <option value="rejected" <?= $status==='rejected'?'selected':'' ?>>❌ Rejected</option>
    </select>
    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="leaves.php" class="btn btn-ghost">Clear</a>
</form>

<div class="table-wrapper">
    <div class="table-header">
        <div class="table-title">Leave Applications (<?= count($records) ?>)</div>
    </div>
    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th>Leave Type</th>
                <th>From</th>
                <th>To</th>
                <th>Days</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($records)): ?>
            <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:40px">No leave applications found.</td></tr>
            <?php else: foreach ($records as $r): ?>
            <tr style="<?= $r['status']==='pending'?'background:rgba(234,179,8,0.03)':'' ?>">
                <td>
                    <span class="avatar"><?= strtoupper(substr($r['first_name'],0,1).substr($r['last_name'],0,1)) ?></span>
                    <span class="emp-name"><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></span><br>
                    <small class="emp-email"><?= htmlspecialchars($r['emp_code']) ?></small>
                </td>
                <td><span class="badge badge-dept"><?= htmlspecialchars($r['leave_type_name']) ?></span></td>
                <td style="font-size:13px"><?= date('d M Y', strtotime($r['from_date'])) ?></td>
                <td style="font-size:13px"><?= date('d M Y', strtotime($r['to_date'])) ?></td>
                <td style="font-weight:700;color:var(--accent)"><?= $r['total_days'] ?></td>
                <td style="color:var(--muted);font-size:13px"><?= htmlspecialchars(substr($r['reason'] ?? '—', 0, 40)) ?></td>
                <td>
                    <?php $bc=match($r['status']){'approved'=>'badge-active','rejected'=>'badge-inactive',default=>'badge-pending'}; ?>
                    <span class="badge <?= $bc ?>"><?= ucfirst($r['status']) ?></span>
                </td>
                <td>
                    <?php if ($r['status'] === 'pending'): ?>
                    <a href="leaves.php?action=approved&id=<?= $r['id'] ?>"
                       class="btn btn-sm" style="background:rgba(34,197,94,0.12);color:var(--success);border:1px solid rgba(34,197,94,0.2)">✓ Approve</a>
                    <a href="leaves.php?action=rejected&id=<?= $r['id'] ?>"
                       class="btn btn-sm" style="background:rgba(239,68,68,0.12);color:var(--danger);border:1px solid rgba(239,68,68,0.2)">✗ Reject</a>
                    <?php endif; ?>
                    <button class="btn btn-danger btn-sm"
                        onclick="confirmDelete('leaves.php?delete=<?= $r['id'] ?>','<?= htmlspecialchars($r['first_name'].' '.$r['last_name'], ENT_QUOTES) ?>')">Del</button>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php require 'layout_footer.php'; ?>