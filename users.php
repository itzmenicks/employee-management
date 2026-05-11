<?php
require_once 'config.php';
require 'auth.php';
$pdo = getDB();

// Sirf admin access kar sakta hai
if ($_SESSION['user_role'] !== 'admin') {
    redirect('index.php', 'Access denied.', 'error');
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if ($_GET['delete'] == $_SESSION['user_id']) {
        redirect('users.php', 'you not delete yourself!', 'error');
    }
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$_GET['delete']]);
    redirect('users.php', 'User deleted successfully.');
}

$users = $pdo->query("
    SELECT u.*, e.first_name, e.last_name, e.emp_code
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.id
    ORDER BY u.created_at DESC
")->fetchAll();

$pageTitle  = 'User Management';
$activePage = 'users';
require 'layout.php';
?>

<div class="table-wrapper">
    <div class="table-header">
        <div class="table-title">All Users (<?= count($users) ?>)</div>
        <a href="user_form.php" class="btn btn-primary">+ Add New User</a>
    </div>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Employee</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:40px">No users found.</td></tr>
            <?php else: foreach ($users as $u): ?>
            <tr>
                <td>
                    <span class="avatar"><?= strtoupper(substr($u['name'],0,2)) ?></span>
                    <span class="emp-name"><?= htmlspecialchars($u['name']) ?></span>
                    <?php if ($u['id'] == $_SESSION['user_id']): ?>
                    <span style="font-size:11px;color:var(--accent);margin-left:6px">(You)</span>
                    <?php endif; ?>
                </td>
                <td style="color:var(--muted);font-size:13px"><?= htmlspecialchars($u['email']) ?></td>
                <td>
                    <?php
                    $roleColor = match($u['role']) {
                        'admin'    => 'color:var(--accent)',
                        'employee' => 'color:var(--info)',
                        'hr'       => 'color:var(--success)',
                        default    => 'color:var(--muted)'
                    };
                    ?>
                    <span class="badge" style="background:rgba(255,255,255,0.05);<?= $roleColor ?>">
                        <?= ucfirst($u['role']) ?>
                    </span>
                </td>
                <td style="font-size:13px">
                    <?php if ($u['emp_code']): ?>
                    <span class="badge badge-dept"><?= htmlspecialchars($u['emp_code']) ?></span>
                    <?php else: ?>
                    <span style="color:var(--muted)">—</span>
                    <?php endif; ?>
                </td>
                <td><span class="badge badge-<?= $u['status'] === 'active' ? 'active' : 'inactive' ?>"><?= ucfirst($u['status']) ?></span></td>
                <td style="color:var(--muted);font-size:13px"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                <td>
                    <a href="user_form.php?id=<?= $u['id'] ?>" class="btn btn-ghost btn-sm">Edit</a>
                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                    <button class="btn btn-danger btn-sm"
                        onclick="confirmDelete('users.php?delete=<?= $u['id'] ?>','<?= htmlspecialchars($u['name'], ENT_QUOTES) ?>')">
                        Delete
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php require 'layout_footer.php'; ?>
