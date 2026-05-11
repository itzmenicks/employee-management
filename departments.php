<?php
require_once 'config.php';
require 'auth.php';
$pdo = getDB();

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $count = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE department_id = ?");
    $count->execute([$_GET['delete']]);
    if ($count->fetchColumn() > 0) {
        redirect('departments.php', 'Cannot delete: department has employees assigned.', 'error');
    }
    $pdo->prepare("DELETE FROM departments WHERE id = ?")->execute([$_GET['delete']]);
    redirect('departments.php', 'Department deleted.');
}

$departments = $pdo->query("
    SELECT d.*, COUNT(e.id) AS emp_count
    FROM departments d
    LEFT JOIN employees e ON d.id = e.department_id
    GROUP BY d.id
    ORDER BY d.name
")->fetchAll();

$pageTitle  = 'Departments';
$activePage = 'departments';
require 'layout.php';
?>

<div class="stats-grid" style="margin-bottom:24px">
    <?php foreach ($departments as $d): ?>
    <div class="stat-card">
        <div class="stat-label"><?= htmlspecialchars($d['name']) ?></div>
        <div class="stat-value"><?= $d['emp_count'] ?></div>
        <div class="stat-icon">◎</div>
        <div style="margin-top:10px;display:flex;gap:8px;">
            <a href="department_form.php?id=<?= $d['id'] ?>" class="btn btn-ghost btn-sm">Edit</a>
            <button class="btn btn-danger btn-sm"
                onclick="confirmDelete('departments.php?delete=<?= $d['id'] ?>','<?= htmlspecialchars($d['name'], ENT_QUOTES) ?>')">
                Del
            </button>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="table-wrapper">
    <div class="table-header">
        <div class="table-title">All Departments (<?= count($departments) ?>)</div>
    </div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Department Name</th>
                <th>Description</th>
                <th>Employees</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($departments)): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:40px">No departments yet.</td></tr>
            <?php else: foreach ($departments as $i => $d): ?>
            <tr>
                <td style="color:var(--muted);font-size:13px"><?= $i+1 ?></td>
                <td style="font-weight:600"><?= htmlspecialchars($d['name']) ?></td>
                <td style="color:var(--muted);font-size:13px"><?= htmlspecialchars($d['description'] ?? '—') ?></td>
                <td><span class="badge badge-dept"><?= $d['emp_count'] ?> employees</span></td>
                <td style="color:var(--muted);font-size:13px"><?= date('d M Y', strtotime($d['created_at'])) ?></td>
                <td>
                    <a href="department_form.php?id=<?= $d['id'] ?>" class="btn btn-ghost btn-sm">Edit</a>
                    <button class="btn btn-danger btn-sm"
                        onclick="confirmDelete('departments.php?delete=<?= $d['id'] ?>','<?= htmlspecialchars($d['name'], ENT_QUOTES) ?>')">
                        Delete
                    </button>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php require 'layout_footer.php'; ?>
