<?php
require_once 'config.php';
require 'auth.php';
$pdo = getDB();

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
$emp = null;
$errors = [];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$id]);
    $emp = $stmt->fetch();
    if (!$emp) redirect('employees.php', 'Employee not found.', 'error');
}

$departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'emp_code'      => sanitize($_POST['emp_code'] ?? ''),
        'first_name'    => sanitize($_POST['first_name'] ?? ''),
        'last_name'     => sanitize($_POST['last_name'] ?? ''),
        'email'         => sanitize($_POST['email'] ?? ''),
        'phone'         => sanitize($_POST['phone'] ?? ''),
        'department_id' => $_POST['department_id'] ?: null,
        'designation'   => sanitize($_POST['designation'] ?? ''),
        'join_date'     => sanitize($_POST['join_date'] ?? ''),
        'salary'        => (float)($_POST['salary'] ?? 0),
        'status'        => in_array($_POST['status'], ['active','inactive']) ? $_POST['status'] : 'active',
        'address'       => sanitize($_POST['address'] ?? ''),
    ];

    if (!$data['emp_code'])   $errors[] = 'Employee code is required.';
    if (!$data['first_name']) $errors[] = 'First name is required.';
    if (!$data['last_name'])  $errors[] = 'Last name is required.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
    if (!$data['join_date'])  $errors[] = 'Join date is required.';

    if (empty($errors)) {
        try {
            if ($id) {
                $stmt = $pdo->prepare("
                    UPDATE employees SET emp_code=?, first_name=?, last_name=?, email=?, phone=?,
                    department_id=?, designation=?, join_date=?, salary=?, status=?, address=?
                    WHERE id=?
                ");
                $stmt->execute([...array_values($data), $id]);
                redirect('employees.php', 'Employee updated successfully.');
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO employees (emp_code, first_name, last_name, email, phone,
                    department_id, designation, join_date, salary, status, address)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?)
                ");
                $stmt->execute(array_values($data));
                redirect('employees.php', 'Employee added successfully.');
            }
        } catch (PDOException $e) {
            $errors[] = 'Error: ' . ($e->getCode() == 23000 ? 'Employee code or email already exists.' : $e->getMessage());
        }
    }
    $emp = array_merge($emp ?? [], $data);
}

$pageTitle  = $id ? 'Edit Employee' : 'Add Employee';
$activePage = 'employees';
require 'layout.php';
?>

<?php if ($errors): ?>
<div class="flash flash-error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
<?php endif; ?>

<div class="form-card">
    <form method="POST">
        <div class="form-grid">
            <div class="form-group">
                <label>Employee Code *</label>
                <input type="text" name="emp_code" value="<?= htmlspecialchars($emp['emp_code'] ?? '') ?>" placeholder="EMP001" required>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="active"   <?= ($emp['status'] ?? 'active') === 'active'   ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($emp['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="form-group">
                <label>First Name *</label>
                <input type="text" name="first_name" value="<?= htmlspecialchars($emp['first_name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Last Name *</label>
                <input type="text" name="last_name" value="<?= htmlspecialchars($emp['last_name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" value="<?= htmlspecialchars($emp['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="tel" name="phone" value="<?= htmlspecialchars($emp['phone'] ?? '') ?>" placeholder="9876543210">
            </div>
            <div class="form-group">
                <label>Department</label>
                <select name="department_id">
                    <option value="">— Select Department —</option>
                    <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= ($emp['department_id'] ?? '') == $d['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Designation</label>
                <input type="text" name="designation" value="<?= htmlspecialchars($emp['designation'] ?? '') ?>" placeholder="e.g. Senior Developer">
            </div>
            <div class="form-group">
                <label>Join Date *</label>
                <input type="date" name="join_date" value="<?= htmlspecialchars($emp['join_date'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Basic Salary (₹)</label>
                <input type="number" name="salary" value="<?= htmlspecialchars($emp['salary'] ?? '0') ?>" min="0" step="0.01">
            </div>
            <div class="form-group full">
                <label>Address</label>
                <textarea name="address" placeholder="Full address…"><?= htmlspecialchars($emp['address'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $id ? 'Update Employee' : 'Add Employee' ?></button>
            <a href="employees.php" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</div>

<?php require 'layout_footer.php'; ?>
