<?php
require_once 'config.php';
require 'auth.php';
$pdo = getDB();

$id     = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
$record = null;
$errors = [];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE id = ?");
    $stmt->execute([$id]);
    $record = $stmt->fetch();
    if (!$record) redirect('attendance.php', 'Record not found.', 'error');
}

$employees = $pdo->query("SELECT id, emp_code, first_name, last_name FROM employees WHERE status='active' ORDER BY first_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'employee_id' => (int)$_POST['employee_id'],
        'date'        => sanitize($_POST['date'] ?? ''),
        'check_in'    => $_POST['check_in'] ?: null,
        'check_out'   => $_POST['check_out'] ?: null,
        'status'      => in_array($_POST['status'], ['present','absent','late','half_day']) ? $_POST['status'] : 'present',
        'notes'       => sanitize($_POST['notes'] ?? ''),
    ];

    if (!$data['employee_id']) $errors[] = 'Employee required.';
    if (!$data['date'])        $errors[] = 'Date required.';

    if (empty($errors)) {
        try {
            if ($id) {
                $pdo->prepare("UPDATE attendance SET employee_id=?,date=?,check_in=?,check_out=?,status=?,notes=? WHERE id=?")
                    ->execute([...array_values($data), $id]);
                redirect('attendance.php', 'Attendance updated.');
            } else {
                $pdo->prepare("INSERT INTO attendance (employee_id,date,check_in,check_out,status,notes) VALUES (?,?,?,?,?,?)")
                    ->execute(array_values($data));
                redirect('attendance.php', 'Attendance added.');
            }
        } catch (PDOException $e) {
            $errors[] = $e->getCode() == 23000 ? 'Attendance already marked for this employee on this date.' : $e->getMessage();
        }
    }
    $record = $data;
}

$pageTitle  = $id ? 'Edit Attendance' : 'Add Attendance';
$activePage = 'attendance';
require 'layout.php';
?>

<?php if ($errors): ?>
<div class="flash flash-error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
<?php endif; ?>

<div class="form-card" style="max-width:600px">
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
            <div class="form-group">
                <label>Date *</label>
                <input type="date" name="date" value="<?= htmlspecialchars($record['date'] ?? date('Y-m-d')) ?>" required>
            </div>
            <div class="form-group">
                <label>Status *</label>
                <select name="status">
                    <option value="present"  <?= ($record['status'] ?? 'present') === 'present'  ? 'selected' : '' ?>>✅ Present</option>
                    <option value="absent"   <?= ($record['status'] ?? '') === 'absent'   ? 'selected' : '' ?>>❌ Absent</option>
                    <option value="late"     <?= ($record['status'] ?? '') === 'late'     ? 'selected' : '' ?>>⏰ Late</option>
                    <option value="half_day" <?= ($record['status'] ?? '') === 'half_day' ? 'selected' : '' ?>>◑ Half Day</option>
                </select>
            </div>
            <div class="form-group">
                <label>Check In Time</label>
                <input type="time" name="check_in" value="<?= htmlspecialchars($record['check_in'] ?? '09:00') ?>">
            </div>
            <div class="form-group">
                <label>Check Out Time</label>
                <input type="time" name="check_out" value="<?= htmlspecialchars($record['check_out'] ?? '18:00') ?>">
            </div>
            <div class="form-group full">
                <label>Notes</label>
                <textarea name="notes" placeholder="Optional notes…"><?= htmlspecialchars($record['notes'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $id ? 'Update' : 'Save Attendance' ?></button>
            <a href="attendance.php" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</div>

<?php require 'layout_footer.php'; ?>
