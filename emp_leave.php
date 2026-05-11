<?php
require_once 'config.php';
require 'auth_employee.php';
$pdo = getDB();

$empId = $_SESSION['employee_id'];

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $check = $pdo->prepare("SELECT status FROM leave_applications WHERE id = ? AND employee_id = ?");
    $check->execute([$_GET['delete'], $empId]);
    $la = $check->fetch();
    if ($la && $la['status'] === 'pending') {
        $pdo->prepare("DELETE FROM leave_applications WHERE id = ? AND employee_id = ?")->execute([$_GET['delete'], $empId]);
        redirect('emp_leave.php', 'Leave application deleted.');
    } else {
        redirect('emp_leave.php', 'Cannot delete approved/rejected application.', 'error');
    }
}

$records = $pdo->prepare("
    SELECT la.*, lt.name AS leave_type_name
    FROM leave_applications la
    JOIN leave_types lt ON la.leave_type_id = lt.id
    WHERE la.employee_id = ?
    ORDER BY la.created_at DESC
");
$records->execute([$empId]);
$records = $records->fetchAll();

$pageTitle  = 'My Leaves';
$activePage = 'emp_leave';
require 'layout_employee.php';
?>

<div class="table-wrapper">
    <div class="table-header">
        <div class="table-title">My Leave Applications (<?= count($records) ?>)</div>
        <a href="emp_leave_apply.php" class="btn btn-primary btn-sm">+ Apply Leave</a>
    </div>
    <table>
        <thead>
            <tr>
                <th>Leave Type</th>
                <th>From</th>
                <th>To</th>
                <th>Days</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Remarks</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($records)): ?>
            <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:40px">No leave applications. <a href="emp_leave_apply.php" style="color:var(--accent)">Apply Now →</a></td></tr>
            <?php else: foreach ($records as $r): ?>
            <tr>
                <td><span class="badge badge-dept"><?= htmlspecialchars($r['leave_type_name']) ?></span></td>
                <td style="font-size:13px"><?= date('d M Y', strtotime($r['from_date'])) ?></td>
                <td style="font-size:13px"><?= date('d M Y', strtotime($r['to_date'])) ?></td>
                <td style="font-weight:700;color:var(--accent)"><?= $r['total_days'] ?></td>
                <td style="color:var(--muted);font-size:13px"><?= htmlspecialchars(substr($r['reason'] ?? '—', 0, 40)) ?></td>
                <td><?php $bc=match($r['status']){'approved'=>'badge-active','rejected'=>'badge-inactive',default=>'badge-pending'}; ?><span class="badge <?= $bc ?>"><?= ucfirst($r['status']) ?></span></td>
                <td style="color:var(--muted);font-size:13px"><?= htmlspecialchars($r['remarks'] ?? '—') ?></td>
                <td>
                    <?php if ($r['status'] === 'pending'): ?>
                    <button class="btn btn-danger btn-sm"
                        onclick="confirmDelete('emp_leave.php?delete=<?= $r['id'] ?>','Leave Application')">
                        Cancel
                    </button>
                    <?php else: ?>
                    <span style="color:var(--muted);font-size:12px">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php require 'layout_employee_footer.php'; ?>
