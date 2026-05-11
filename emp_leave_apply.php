<?php
require_once 'config.php';
require 'auth_employee.php';
require_once 'notifications.php';
$pdo = getDB();

$empId  = $_SESSION['employee_id'];
$errors = [];
$leaveTypes = $pdo->query("SELECT * FROM leave_types ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fromDate  = sanitize($_POST['from_date'] ?? '');
    $toDate    = sanitize($_POST['to_date'] ?? '');
    $totalDays = 0;
    if ($fromDate && $toDate) {
        $totalDays = max(1, (strtotime($toDate) - strtotime($fromDate)) / 86400 + 1);
    }
    $leaveTypeId = (int)$_POST['leave_type_id'];
    $reason      = sanitize($_POST['reason'] ?? '');

    if (!$leaveTypeId) $errors[] = 'Leave type required.';
    if (!$fromDate)    $errors[] = 'From date required.';
    if (!$toDate)      $errors[] = 'To date required.';
    if (!$reason)      $errors[] = 'Reason required.';
    if ($fromDate > $toDate) $errors[] = 'From date cannot be after To date.';

    if (empty($errors)) {
        $pdo->prepare("INSERT INTO leave_applications (employee_id,leave_type_id,from_date,to_date,total_days,reason,status,remarks) VALUES (?,?,?,?,?,?,'pending','')")
            ->execute([$empId, $leaveTypeId, $fromDate, $toDate, $totalDays, $reason]);

        // Get employee name
        $empInfo = $pdo->prepare("SELECT first_name, last_name FROM employees WHERE id = ?");
        $empInfo->execute([$empId]);
        $empInfo = $empInfo->fetch();
        $empName = $empInfo['first_name'] . ' ' . $empInfo['last_name'];

        // Get leave type name
        $ltInfo = $pdo->prepare("SELECT name FROM leave_types WHERE id = ?");
        $ltInfo->execute([$leaveTypeId]);
        $ltName = $ltInfo->fetchColumn();

        // Notify all admins
        notifyAllAdmins(
            $pdo,
            "🏖️ New Leave Application",
            "$empName ne $ltName ke liye $totalDays days off have been taken.",
            'leave',
            'leaves.php'
        );

        redirect('emp_leave.php', 'Leave application submitted successfully!');
    }
}

$pageTitle  = 'Apply for Leave';
$activePage = 'emp_leave';
require 'layout_employee.php';
?>

<?php if ($errors): ?>
<div class="flash flash-error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
<?php endif; ?>

<div class="form-card" style="max-width:550px">
    <form method="POST">
        <div class="form-grid">
            <div class="form-group full">
                <label>Leave Type *</label>
                <select name="leave_type_id" required>
                    <option value="">— Select Leave Type —</option>
                    <?php foreach ($leaveTypes as $lt): ?>
                    <option value="<?= $lt['id'] ?>"><?= htmlspecialchars($lt['name']) ?> (<?= $lt['days_allowed'] ?> days/year)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>From Date *</label>
                <input type="date" name="from_date" required onchange="calcDays()" min="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group">
                <label>To Date *</label>
                <input type="date" name="to_date" required onchange="calcDays()" min="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group full">
                <div class="salary-calc">
                    <div style="font-size:12px;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);margin-bottom:8px">Total Leave Days</div>
                    <div class="net" id="daysDisplay">0 Days</div>
                </div>
            </div>
            <div class="form-group full">
                <label>Reason *</label>
                <textarea name="reason" placeholder="Please mention reason for leave…" required></textarea>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Submit Application</button>
            <a href="emp_leave.php" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</div>

<script>
function calcDays() {
    const from = new Date(document.querySelector('[name="from_date"]').value);
    const to   = new Date(document.querySelector('[name="to_date"]').value);
    if (from && to && to >= from) {
        const diff = Math.round((to - from) / 86400000) + 1;
        document.getElementById('daysDisplay').textContent = diff + ' Day' + (diff > 1 ? 's' : '');
    } else {
        document.getElementById('daysDisplay').textContent = '0 Days';
    }
}
</script>

<?php require 'layout_employee_footer.php'; ?>