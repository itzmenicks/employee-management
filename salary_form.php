<?php
require_once 'config.php';
require 'auth.php';
$pdo = getDB();

$id     = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
$record = null;
$errors = [];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM salary_records WHERE id = ?");
    $stmt->execute([$id]);
    $record = $stmt->fetch();
    if (!$record) redirect('salary.php', 'Record not found.', 'error');
}

$employees = $pdo->query("SELECT id, emp_code, first_name, last_name, salary FROM employees WHERE status='active' ORDER BY first_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'employee_id'   => (int)$_POST['employee_id'],
        'month'         => (int)$_POST['month'],
        'year'          => (int)$_POST['year'],
        'basic_salary'  => (float)$_POST['basic_salary'],
        'allowances'    => (float)$_POST['allowances'],
        'deductions'    => (float)$_POST['deductions'],
        'net_salary'    => (float)$_POST['net_salary'],
        'status'        => in_array($_POST['status'],['pending','paid']) ? $_POST['status'] : 'pending',
        'notes'         => sanitize($_POST['notes'] ?? ''),
    ];

    if (!$data['employee_id']) $errors[] = 'Employee is required.';
    if ($data['month'] < 1 || $data['month'] > 12) $errors[] = 'Valid month required.';

    if (empty($errors)) {
        try {
            if ($id) {
                $pdo->prepare("UPDATE salary_records SET employee_id=?,month=?,year=?,basic_salary=?,allowances=?,deductions=?,net_salary=?,status=?,notes=? WHERE id=?")
                    ->execute([...array_values($data), $id]);
                redirect('salary.php', 'Salary record updated.');
            } else {
                $pdo->prepare("INSERT INTO salary_records (employee_id,month,year,basic_salary,allowances,deductions,net_salary,status,notes) VALUES (?,?,?,?,?,?,?,?,?)")
                    ->execute(array_values($data));
                redirect('salary.php', 'Salary record added.');
            }
        } catch (PDOException $e) {
            $errors[] = $e->getCode() == 23000 ? 'A record for this employee/month/year already exists.' : $e->getMessage();
        }
    }
    $record = $data;
}

$months = ['','January','February','March','April','May','June','July','August','September','October','November','December'];

$pageTitle  = $id ? 'Edit Salary Record' : 'Add Salary Record';
$activePage = 'salary';
require 'layout.php';
?>

<?php if ($errors): ?>
<div class="flash flash-error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
<?php endif; ?>

<div class="form-card">
    <form method="POST" id="salaryForm">
        <div class="form-grid">
            <div class="form-group full">
                <label>Employee *</label>
                <select name="employee_id" id="empSelect" required onchange="loadSalary(this)">
                    <option value="">— Select Employee —</option>
                    <?php foreach ($employees as $e): ?>
                    <option value="<?= $e['id'] ?>"
                        data-salary="<?= $e['salary'] ?>"
                        <?= ($record['employee_id'] ?? '') == $e['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($e['emp_code'].' — '.$e['first_name'].' '.$e['last_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Month *</label>
                <select name="month" required>
                    <?php for ($m=1;$m<=12;$m++): ?>
                    <option value="<?= $m ?>" <?= ($record['month'] ?? date('n')) == $m ? 'selected' : '' ?>><?= $months[$m] ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Year *</label>
                <select name="year">
                    <?php for ($y=date('Y');$y>=date('Y')-5;$y--): ?>
                    <option value="<?= $y ?>" <?= ($record['year'] ?? date('Y')) == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Basic Salary (₹)</label>
                <input type="number" name="basic_salary" id="basic" min="0" step="0.01"
                       value="<?= $record['basic_salary'] ?? 0 ?>" oninput="calcNet()">
            </div>
            <div class="form-group">
                <label>Allowances (₹)</label>
                <input type="number" name="allowances" id="allow" min="0" step="0.01"
                       value="<?= $record['allowances'] ?? 0 ?>" oninput="calcNet()">
            </div>
            <div class="form-group">
                <label>Deductions (₹)</label>
                <input type="number" name="deductions" id="deduct" min="0" step="0.01"
                       value="<?= $record['deductions'] ?? 0 ?>" oninput="calcNet()">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="pending" <?= ($record['status'] ?? 'pending') === 'pending' ? 'selected':'' ?>>Pending</option>
                    <option value="paid"    <?= ($record['status'] ?? '') === 'paid' ? 'selected':'' ?>>Paid</option>
                </select>
            </div>
        </div>

        <!-- Live Calculator -->
        <div class="salary-calc" style="margin-top:20px">
            <div style="font-size:12px;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);margin-bottom:10px">Net Salary (Auto-calculated)</div>
            <div class="net" id="netDisplay">₹0</div>
            <input type="hidden" name="net_salary" id="netHidden" value="<?= $record['net_salary'] ?? 0 ?>">
        </div>

        <div class="form-group full" style="margin-top:16px">
            <label>Notes</label>
            <textarea name="notes" placeholder="Optional notes…"><?= htmlspecialchars($record['notes'] ?? '') ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $id ? 'Update Record' : 'Save Record' ?></button>
            <a href="salary.php" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</div>

<script>
function calcNet() {
    const basic  = parseFloat(document.getElementById('basic').value)  || 0;
    const allow  = parseFloat(document.getElementById('allow').value)  || 0;
    const deduct = parseFloat(document.getElementById('deduct').value) || 0;
    const net    = basic + allow - deduct;
    document.getElementById('netDisplay').textContent = '₹' + net.toLocaleString('en-IN', {minimumFractionDigits:0});
    document.getElementById('netHidden').value = net.toFixed(2);
}
function loadSalary(sel) {
    const opt = sel.options[sel.selectedIndex];
    const sal = parseFloat(opt.dataset.salary) || 0;
    document.getElementById('basic').value = sal;
    calcNet();
}
// Init
calcNet();
</script>

<?php require 'layout_footer.php'; ?>
