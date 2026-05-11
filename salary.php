<?php
require_once 'config.php';
require 'auth.php';
$pdo = getDB();

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $pdo->prepare("DELETE FROM salary_records WHERE id = ?")->execute([$_GET['delete']]);
    redirect('salary.php', 'Salary record deleted.');
}

if (isset($_GET['pay']) && is_numeric($_GET['pay'])) {
    $pdo->prepare("UPDATE salary_records SET status='paid', payment_date=CURDATE() WHERE id=?")
        ->execute([$_GET['pay']]);
    redirect('salary.php', 'Marked as paid.');
}

// Filters
$search  = sanitize($_GET['search'] ?? '');
$month   = $_GET['month'] ?? '';
$year    = $_GET['year'] ?? date('Y');
$status  = $_GET['status'] ?? '';

$where  = [];
$params = [];
if ($search) {
    $where[]  = "(e.first_name LIKE ? OR e.last_name LIKE ? OR e.emp_code LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s]);
}
if ($month)  { $where[] = "sr.month = ?";  $params[] = $month; }
if ($year)   { $where[] = "sr.year = ?";   $params[] = $year; }
if ($status) { $where[] = "sr.status = ?"; $params[] = $status; }

$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

$records = $pdo->prepare("
    SELECT sr.*, e.first_name, e.last_name, e.emp_code, d.name AS dept_name
    FROM salary_records sr
    JOIN employees e ON sr.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    $whereSQL
    ORDER BY sr.year DESC, sr.month DESC, e.first_name
");
$records->execute($params);
$records = $records->fetchAll();

$totalPaid    = array_sum(array_column(array_filter($records, fn($r) => $r['status']==='paid'),    'net_salary'));
$totalPending = array_sum(array_column(array_filter($records, fn($r) => $r['status']==='pending'), 'net_salary'));

$months = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

$pageTitle  = 'Salary Records';
$activePage = 'salary';
require 'layout.php';
?>

<div class="stats-grid" style="margin-bottom:24px">
    <div class="stat-card">
        <div class="stat-label">Records Found</div>
        <div class="stat-value"><?= count($records) ?></div>
        <div class="stat-icon">📋</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Paid (₹)</div>
        <div class="stat-value" style="font-size:22px;color:var(--success)">₹<?= number_format($totalPaid,0) ?></div>
        <div class="stat-icon">✓</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Pending (₹)</div>
        <div class="stat-value" style="font-size:22px;color:#eab308">₹<?= number_format($totalPending,0) ?></div>
        <div class="stat-icon">⏳</div>
    </div>
</div>

<!-- Filter -->
<form method="GET" class="search-bar">
    <div class="search-input-wrap">
        <span class="search-icon">🔍</span>
        <input type="text" name="search" class="input-search" placeholder="Search employee…" value="<?= htmlspecialchars($search) ?>">
    </div>
    <select name="month" class="filter-select">
        <option value="">All Months</option>
        <?php for ($m=1;$m<=12;$m++): ?>
        <option value="<?= $m ?>" <?= $month==$m?'selected':'' ?>><?= $months[$m] ?></option>
        <?php endfor; ?>
    </select>
    <select name="year" class="filter-select">
        <?php for ($y=date('Y');$y>=date('Y')-5;$y--): ?>
        <option value="<?= $y ?>" <?= $year==$y?'selected':'' ?>><?= $y ?></option>
        <?php endfor; ?>
    </select>
    <select name="status" class="filter-select">
        <option value="">All Status</option>
        <option value="paid"    <?= $status==='paid'   ?'selected':'' ?>>Paid</option>
        <option value="pending" <?= $status==='pending'?'selected':'' ?>>Pending</option>
    </select>
    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="salary.php" class="btn btn-ghost">Clear</a>
</form>

<div class="table-wrapper">
    <div class="table-header">
        <div class="table-title">Salary Records</div>
    </div>
    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th>Period</th>
                <th>Basic (₹)</th>
                <th>Allowances (₹)</th>
                <th>Deductions (₹)</th>
                <th>Net Salary (₹)</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($records)): ?>
            <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:40px">No salary records. <a href="salary_form.php" style="color:var(--accent)">Add one →</a></td></tr>
            <?php else: foreach ($records as $r): ?>
            <tr>
                <td>
                    <span class="avatar"><?= strtoupper(substr($r['first_name'],0,1).substr($r['last_name'],0,1)) ?></span>
                    <span class="emp-name"><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></span><br>
                    <small class="emp-email"><?= htmlspecialchars($r['emp_code']) ?></small>
                </td>
                <td style="font-weight:500"><?= $months[$r['month']] ?> <?= $r['year'] ?></td>
                <td>₹<?= number_format($r['basic_salary'],0) ?></td>
                <td style="color:var(--success)">+₹<?= number_format($r['allowances'],0) ?></td>
                <td style="color:var(--danger)">-₹<?= number_format($r['deductions'],0) ?></td>
                <td style="font-weight:700;color:var(--accent)">₹<?= number_format($r['net_salary'],0) ?></td>
                <td><span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
                <td>
                    <a href="salary_form.php?id=<?= $r['id'] ?>" class="btn btn-ghost btn-sm">Edit</a>
                    <?php if ($r['status']==='pending'): ?>
                    <a href="salary.php?pay=<?= $r['id'] ?>" class="btn btn-sm" style="background:rgba(34,197,94,0.12);color:var(--success);border:1px solid rgba(34,197,94,0.2)">Pay</a>
                    <?php endif; ?>
                    <button class="btn btn-danger btn-sm"
                        onclick="confirmDelete('salary.php?delete=<?= $r['id'] ?>','<?= htmlspecialchars($r['first_name'].' '.$r['last_name'], ENT_QUOTES) ?> – <?= $months[$r['month']].' '.$r['year'] ?>')">Del</button>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php require 'layout_footer.php'; ?>
