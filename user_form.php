<?php
require_once 'config.php';
require 'auth.php';
$pdo = getDB();

if ($_SESSION['user_role'] !== 'admin') {
    redirect('index.php', 'Access denied.', 'error');
}

$id     = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
$user   = null;
$errors = [];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) redirect('users.php', 'User not found.', 'error');
}

$employees = $pdo->query("SELECT id, emp_code, first_name, last_name FROM employees WHERE status='active' ORDER BY first_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = sanitize($_POST['name'] ?? '');
    $email    = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = in_array($_POST['role'], ['admin','hr','employee','viewer']) ? $_POST['role'] : 'employee';
    $status   = in_array($_POST['status'], ['active','inactive']) ? $_POST['status'] : 'active';
    $empId    = $_POST['employee_id'] ?: null;

    if (!$name)  $errors[] = 'Name required.';
    if (!$email) $errors[] = 'Email required.';
    if (!$id && !$password) $errors[] = 'Password required.';

    if (empty($errors)) {
        try {
            if ($id) {
                if ($password) {
                    $pdo->prepare("UPDATE users SET name=?,email=?,password=?,role=?,status=?,employee_id=? WHERE id=?")
                        ->execute([$name, $email, $password, $role, $status, $empId, $id]);
                } else {
                    $pdo->prepare("UPDATE users SET name=?,email=?,role=?,status=?,employee_id=? WHERE id=?")
                        ->execute([$name, $email, $role, $status, $empId, $id]);
                }
                redirect('users.php', 'User updated successfully!');
            } else {
                $pdo->prepare("INSERT INTO users (name,email,password,role,status,employee_id) VALUES (?,?,?,?,?,?)")
                    ->execute([$name, $email, $password, $role, $status, $empId]);
                redirect('users.php', 'User created successfully!');
            }
        } catch (PDOException $e) {
            $errors[] = $e->getCode() == 23000 ? 'Email already exists!' : $e->getMessage();
        }
    }
    $user = compact('name','email','role','status') + ['employee_id' => $empId];
}

$pageTitle  = $id ? 'Edit User' : 'Add New User';
$activePage = 'users';
require 'layout.php';
?>

<?php if ($errors): ?>
<div class="flash flash-error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
<?php endif; ?>

<div class="form-card" style="max-width:550px">
    <form method="POST">
        <div class="form-grid">
            <div class="form-group full">
                <label>Full Name *</label>
                <input type="text" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" placeholder="Rahul Patil" required>
            </div>
            <div class="form-group full">
                <label>Email Address *</label>
                <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" placeholder="rahul@company.com" required>
            </div>
            <div class="form-group full">
                <label><?= $id ? 'New Password' : 'Password *' ?></label>
                <input type="text" name="password" placeholder="password123" <?= $id ? '' : 'required' ?>>
                <small style="color:var(--muted);font-size:12px">⚠️ Password</small>
            </div>
            <div class="form-group">
                <label>Role *</label>
                <select name="role" onchange="toggleEmployee(this)">
                    <option value="employee" <?= ($user['role'] ?? '') === 'employee' ? 'selected' : '' ?>>👤 Employee</option>
                    <option value="admin"    <?= ($user['role'] ?? '') === 'admin'    ? 'selected' : '' ?>>⚡ Admin</option>
                    <option value="hr"       <?= ($user['role'] ?? '') === 'hr'       ? 'selected' : '' ?>>🧑‍💼 HR</option>
                    <option value="viewer"   <?= ($user['role'] ?? '') === 'viewer'   ? 'selected' : '' ?>>👁️ Viewer</option>
                </select>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="active"   <?= ($user['status'] ?? 'active') === 'active'   ? 'selected' : '' ?>>✅ Active</option>
                    <option value="inactive" <?= ($user['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>❌ Inactive</option>
                </select>
            </div>
            <div class="form-group full" id="empGroup">
                <label>Link to Employee</label>
                <select name="employee_id">
                    <option value="">— Select Employee (optional) —</option>
                    <?php foreach ($employees as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= ($user['employee_id'] ?? '') == $e['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($e['emp_code'].' — '.$e['first_name'].' '.$e['last_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $id ? 'Update User' : 'Create User' ?></button>
            <a href="users.php" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</div>

<script>
function toggleEmployee(sel) {
    const empGroup = document.getElementById('empGroup');
    empGroup.style.display = sel.value === 'employee' ? 'flex' : 'none';
}
toggleEmployee(document.querySelector('[name="role"]'));
</script>

<?php require 'layout_footer.php'; ?>
