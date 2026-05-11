<?php
require_once 'config.php';
require 'auth.php';
$pdo = getDB();

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    redirect('employees.php', 'Employee deleted successfully.');
}

// Filters
$search  = sanitize($_GET['search'] ?? '');
$deptId  = $_GET['dept'] ?? '';
$status  = $_GET['status'] ?? '';

// Pagination
$perPage = 10;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

// Build query
$where  = [];
$params = [];
if ($search) {
    $where[]  = "(e.first_name LIKE ? OR e.last_name LIKE ? OR e.email LIKE ? OR e.emp_code LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s]);
}
if ($deptId) { $where[] = "e.department_id = ?"; $params[] = $deptId; }
if ($status)  { $where[] = "e.status = ?";          $params[] = $status; }

$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM employees e $whereSQL");
$countStmt->execute($params);
$total    = $countStmt->fetchColumn();
$pages    = ceil($total / $perPage);

$stmt = $pdo->prepare("
    SELECT e.*, d.name AS dept_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    $whereSQL ORDER BY e.created_at DESC LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$employees = $stmt->fetchAll();

$departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll();

$pageTitle  = 'Employees';
$activePage = 'employees';
require 'layout.php';
?>

<!-- Search & Filter Bar -->
<form method="GET" class="search-bar">
    <div class="search-input-wrap">
        <span class="search-icon">🔍</span>
        <input type="text" name="search" class="input-search"
               placeholder="Search name, email, code…"
               value="<?= htmlspecialchars($search) ?>">
    </div>
    <select name="dept" class="filter-select">
        <option value="">All Departments</option>
        <?php foreach ($departments as $d): ?>
        <option value="<?= $d['id'] ?>" <?= $deptId == $d['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($d['name']) ?>
        </option>
        <?php endforeach; ?>
    </select>
    <select name="status" class="filter-select">
        <option value="">All Status</option>
        <option value="active"   <?= $status==='active'   ? 'selected':'' ?>>Active</option>
        <option value="inactive" <?= $status==='inactive' ? 'selected':'' ?>>Inactive</option>
    </select>
    <button type="submit" class="btn btn-primary">Filter</button>
    <?php if ($search || $deptId || $status): ?>
    <a href="employees.php" class="btn btn-ghost">Clear</a>
    <?php endif; ?>
</form>

<div class="table-wrapper">
    <div class="table-header">
        <div class="table-title">
            <?= $total ?> Employee<?= $total != 1 ? 's' : '' ?> found
        </div>
    </div>
    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th>Code</th>
                <th>Department</th>
                <th>Designation</th>
                <th>Salary (₹)</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($employees)): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:40px">No employees found.</td></tr>
            <?php else: foreach ($employees as $e): ?>
            <tr>
                <td>
                    <span class="avatar"><?= strtoupper(substr($e['first_name'],0,1).substr($e['last_name'],0,1)) ?></span>
                    <span class="emp-name"><?= htmlspecialchars($e['first_name'].' '.$e['last_name']) ?></span><br>
                    <small class="emp-email"><?= htmlspecialchars($e['email']) ?></small>
                </td>
                <td style="font-size:13px;color:var(--muted)"><?= htmlspecialchars($e['emp_code']) ?></td>
                <td><span class="badge badge-dept"><?= htmlspecialchars($e['dept_name'] ?? '—') ?></span></td>
                <td><?= htmlspecialchars($e['designation'] ?? '—') ?></td>
                <td>₹<?= number_format($e['salary'], 0) ?></td>
                <td><span class="badge badge-<?= $e['status'] ?>"><?= ucfirst($e['status']) ?></span></td>
                <td>
                    <a href="employee_form.php?id=<?= $e['id'] ?>" class="btn btn-ghost btn-sm">Edit</a>
                    <button class="btn btn-danger btn-sm"
                        onclick="confirmDelete('employees.php?delete=<?= $e['id'] ?>','<?= htmlspecialchars($e['first_name'].' '.$e['last_name'], ENT_QUOTES) ?>')">
                        Delete
                    </button>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>

    <?php if ($pages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&dept=<?= urlencode($deptId) ?>&status=<?= urlencode($status) ?>"
           class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php require 'layout_footer.php'; ?>
