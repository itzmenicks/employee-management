<?php
require_once 'config.php';
require 'auth.php';
$pdo = getDB();

$id   = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
$dept = null;
$errors = [];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
    $stmt->execute([$id]);
    $dept = $stmt->fetch();
    if (!$dept) redirect('departments.php', 'Department not found.', 'error');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $desc = sanitize($_POST['description'] ?? '');

    if (!$name) $errors[] = 'Department name is required.';

    if (empty($errors)) {
        if ($id) {
            $pdo->prepare("UPDATE departments SET name=?, description=? WHERE id=?")
                ->execute([$name, $desc, $id]);
            redirect('departments.php', 'Department updated.');
        } else {
            $pdo->prepare("INSERT INTO departments (name, description) VALUES (?,?)")
                ->execute([$name, $desc]);
            redirect('departments.php', 'Department added.');
        }
    }
    $dept = ['name' => $name, 'description' => $desc];
}

$pageTitle  = $id ? 'Edit Department' : 'Add Department';
$activePage = 'departments';
require 'layout.php';
?>

<?php if ($errors): ?>
<div class="flash flash-error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
<?php endif; ?>

<div class="form-card" style="max-width:500px">
    <form method="POST">
        <div class="form-group" style="margin-bottom:16px">
            <label>Department Name *</label>
            <input type="text" name="name" value="<?= htmlspecialchars($dept['name'] ?? '') ?>" placeholder="e.g. Engineering" required>
        </div>
        <div class="form-group" style="margin-bottom:16px">
            <label>Description</label>
            <textarea name="description" placeholder="Brief description of this department…"><?= htmlspecialchars($dept['description'] ?? '') ?></textarea>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $id ? 'Update' : 'Add Department' ?></button>
            <a href="departments.php" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</div>

<?php require 'layout_footer.php'; ?>
