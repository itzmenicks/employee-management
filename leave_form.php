<?php
require_once 'config.php';
require 'auth.php';
$pdo = getDB();

$id     = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
$record = null;
$errors = [];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM leave_applications WHERE id = ?");
    $stmt->execute([$id]);
    $record = $stmt->fetch();
    if (!$record) redirect('leaves.php', 'Record not found.', 'error');
}

$employees  = $pdo->query("SELECT id, emp_code, first_name, last_name FROM employees WHERE status='active' ORDER BY first_name")->fetchAll();
$leaveTypes = $pdo->query("SELECT * FROM leave_types ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fromDate = sanitize($_POST['from_date'] ?? '');
    $toDate   = sanitize($_POST['to_date'] ?? '');
    $totalDays = 0;
    if ($fromDate && $toDate) {
        $diff = (strtotime($toDate) - strtotime($fromDate)) / 86400;
        $totalDays = max(1, $diff + 1);
    }

    $data = [
        'employee_id'   => (int)$_POST['employee_id'],
        'leave_type_id' => (int)$_POST['leave_type_id'],
        'from_date'     => $fromDate,
        'to_date'       => $toDate,
        'total_days'    => $totalDays,
        'reason'        => sanitize($_POST['reason'] ?? ''),
        'status'        => in_array($_POST['status'], ['pending','approved','rejected']) ? $_POST['status'] : 'pending',
        'remarks'       => sanitize($_POST['remarks'] ?? ''),
    ];

    if (!$data['employee_id'])   $errors[] = 'Employee required.';
    if (!$data['leave_type_id']) $errors[] = 'Leave type required.';
    if (!$data['from_date'])     $errors[] = 'From date required.';
    if (!$data['to_date'])       $errors[] = 'To date required.';
    if ($data['from_date'] > $data['to_date']) $errors[] = 'From date cannot be after To date.';

    if (empty($errors)) {
        try {
            if ($id) {
                $pdo->prepare("UPDATE leave_applications SET employee_id=?,leave_type_id=?,from_date=?,to_date=?,total_days=?,reason=?,status=?,remarks=? WHERE id=?")
                    ->execute([...array_values($data), $id]);
                redirect('leaves.php', 'Leave application updated.');
            } else {
                $pdo->prepare("INSERT INTO leave_applications (employee_id,leave_type_id,from_date,to_date,total_days,reason,status,remarks) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute(array_values($data));
                redirect('leaves.php', 'Leave application submitted.');
            }
        } catch (PDOException $e) {
            $errors[] = $e->getMessage();
        }
    }
    $record = $data;
}

$pageTitle  = $id ? 'Edit Leave Application' : 'Apply for Leave';
$activePage = 'leaves';
require 'layout.php';
?>

<?php if ($errors): ?>
<div class="flash flash-error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
<?php endif; ?>

<div class="form-card" style="max-width:650px">
    <form method="POST">
        <div class="form-grid">
            <div class="form-group full">
                <label>Employee *</label>
                <select name="employee_id" required>
                    <option value="">— Select Employee —</option>
                    <?php foreach ($employees as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= ($record['employee_id'] ?? '') == $e['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($e['emp_code'].' — '.$e['first_name'].' '.$e['last_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group full">
                <label>Leave Type *</label>
                <select name="leave_type_id" required>
                    <option value="">— Select Leave Type —</option>
                    <?php foreach ($leaveTypes as $lt): ?>
                    <option value="<?= $lt['id'] ?>" <?= ($record['leave_type_id'] ?? '') == $lt['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($lt['name']) ?> (<?= $lt['days_allowed'] ?> days allowed)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>From Date *</label>
                <input type="date" name="from_date"
                       value="<?= htmlspecialchars($record['from_date'] ?? '') ?>"
                       required onchange="calcDays()">
            </div>
            <div class="form-group">
                <label>To Date *</label>
                <input type="date" name="to_date"
                       value="<?= htmlspecialchars($record['to_date'] ?? '') ?>"
                       required onchange="calcDays()">
            </div>

            <!-- Days Calculator -->
            <div class="form-group full">
                <div class="salary-calc">
                    <div style="font-size:12px;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);margin-bottom:8px">Total Leave Days</div>
                    <div class="net" id="daysDisplay">0 Days</div>
                </div>
            </div>

            <div class="form-group full">
                <label>Reason *</label>
                <textarea name="reason" placeholder="Reason for leave…" required><?= htmlspecialchars($record['reason'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="pending"  <?= ($record['status'] ?? 'pending') === 'pending'  ? 'selected' : '' ?>>⏳ Pending</option>
                    <option value="approved" <?= ($record['status'] ?? '') === 'approved' ? 'selected' : '' ?>>✅ Approved</option>
                    <option value="rejected" <?= ($record['status'] ?? '') === 'rejected' ? 'selected' : '' ?>>❌ Rejected</option>
                </select>
            </div>
            <div class="form-group">
                <label>Remarks (HR/Manager)</label>
                <input type="text" name="remarks"
                       value="<?= htmlspecialchars($record['remarks'] ?? '') ?>"
                       placeholder="Optional remarks…">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $id ? 'Update Application' : 'Submit Application' ?></button>
            <a href="leaves.php" class="btn btn-ghost">Cancel</a>
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
calcDays();
</script>

<?php require 'layout_footer.php'; ?>
