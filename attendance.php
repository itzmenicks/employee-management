<?php
require_once 'config.php';
require 'auth.php';
$pdo = getDB();

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $pdo->prepare("DELETE FROM attendance WHERE id = ?")->execute([$_GET['delete']]);
    redirect('attendance.php', 'Attendance record deleted.');
}

// Filters
$search  = sanitize($_GET['search'] ?? '');
$date    = $_GET['date'] ?? date('Y-m-d');
$status  = $_GET['status'] ?? '';
$deptId  = $_GET['dept'] ?? '';

$where  = ["1=1"];
$params = [];

if ($search) {
    $where[]  = "(e.first_name LIKE ? OR e.last_name LIKE ? OR e.emp_code LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s]);
}
if ($date)   { $where[] = "a.date = ?";            $params[] = $date; }
if ($status) { $where[] = "a.status = ?";          $params[] = $status; }
if ($deptId) { $where[] = "e.department_id = ?";   $params[] = $deptId; }

$whereSQL = "WHERE " . implode(" AND ", $where);

$records = $pdo->prepare("
    SELECT a.*, e.first_name, e.last_name, e.emp_code, d.name AS dept_name
    FROM attendance a
    JOIN employees e ON a.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    $whereSQL
    ORDER BY a.date DESC, e.first_name
");
$records->execute($params);
$records = $records->fetchAll();

// Stats for selected date
$present  = count(array_filter($records, fn($r) => $r['status'] === 'present'));
$absent   = count(array_filter($records, fn($r) => $r['status'] === 'absent'));
$late     = count(array_filter($records, fn($r) => $r['status'] === 'late'));
$halfday  = count(array_filter($records, fn($r) => $r['status'] === 'half_day'));

$departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll();

$pageTitle  = 'Attendance';
$activePage = 'attendance';
require 'layout.php';
?>

<div class="stats-grid" style="margin-bottom:24px">
    <div class="stat-card">
        <div class="stat-label">Present</div>
        <div class="stat-value" style="color:var(--success)"><?= $present ?></div>
        <div class="stat-icon">✓</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Absent</div>
        <div class="stat-value" style="color:var(--danger)"><?= $absent ?></div>
        <div class="stat-icon">✗</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Late</div>
        <div class="stat-value" style="color:#eab308"><?= $late ?></div>
        <div class="stat-icon">⏰</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Half Day</div>
        <div class="stat-value" style="color:var(--info)"><?= $halfday ?></div>
        <div class="stat-icon">◑</div>
    </div>
</div>

<!-- Filter Bar -->
<form method="GET" class="search-bar">
    <div class="search-input-wrap">
        <span class="search-icon">🔍</span>
        <input type="text" name="search" class="input-search"
               placeholder="Search employee…"
               value="<?= htmlspecialchars($search) ?>">
    </div>
    <input type="date" name="date" class="filter-select"
           value="<?= htmlspecialchars($date) ?>">
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
        <option value="present"  <?= $status==='present'  ?'selected':'' ?>>Present</option>
        <option value="absent"   <?= $status==='absent'   ?'selected':'' ?>>Absent</option>
        <option value="late"     <?= $status==='late'     ?'selected':'' ?>>Late</option>
        <option value="half_day" <?= $status==='half_day' ?'selected':'' ?>>Half Day</option>
    </select>
    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="attendance.php" class="btn btn-ghost">Clear</a>
</form>

<div class="table-wrapper">
    <div class="table-header">
        <div class="table-title">
            Attendance — <?= date('d M Y', strtotime($date)) ?>
            (<?= count($records) ?> records)
        </div>
        <a href="attendance_mark.php?date=<?= $date ?>" class="btn btn-primary btn-sm">
            + Mark Attendance
        </a>
    </div>
    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th>Department</th>
                <th>Date</th>
                <th>Check In</th>
                <th>Check Out</th>
                <th>Status</th>
                <th>Notes</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($records)): ?>
            <tr>
                <td colspan="8" style="text-align:center;color:var(--muted);padding:40px">
                    No attendance records for this date.
                    <a href="attendance_mark.php?date=<?= $date ?>" style="color:var(--accent)">Mark Now →</a>
                </td>
            </tr>
            <?php else: foreach ($records as $r): ?>
            <tr>
                <td>
                    <span class="avatar"><?= strtoupper(substr($r['first_name'],0,1).substr($r['last_name'],0,1)) ?></span>
                    <span class="emp-name"><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></span><br>
                    <small class="emp-email"><?= htmlspecialchars($r['emp_code']) ?></small>
                </td>
                <td><span class="badge badge-dept"><?= htmlspecialchars($r['dept_name'] ?? '—') ?></span></td>
                <td style="color:var(--muted);font-size:13px"><?= date('d M Y', strtotime($r['date'])) ?></td>
                <td style="color:var(--success);font-weight:500">
                    <?= $r['check_in'] ? date('h:i A', strtotime($r['check_in'])) : '—' ?>
                </td>
                <td style="color:var(--danger);font-weight:500">
                    <?= $r['check_out'] ? date('h:i A', strtotime($r['check_out'])) : '—' ?>
                </td>
                <td>
                    <?php
                    $badgeClass = match($r['status']) {
                        'present'  => 'badge-active',
                        'absent'   => 'badge-inactive',
                        'late'     => 'badge-pending',
                        'half_day' => 'badge-dept',
                        default    => 'badge-active'
                    };
                    $label = match($r['status']) {
                        'present'  => 'Present',
                        'absent'   => 'Absent',
                        'late'     => 'Late',
                        'half_day' => 'Half Day',
                        default    => ucfirst($r['status'])
                    };
                    ?>
                    <span class="badge <?= $badgeClass ?>"><?= $label ?></span>
                </td>
                <td style="color:var(--muted);font-size:13px"><?= htmlspecialchars($r['notes'] ?? '—') ?></td>
                <td>
                    <a href="attendance_form.php?id=<?= $r['id'] ?>" class="btn btn-ghost btn-sm">Edit</a>
                    <button class="btn btn-danger btn-sm"
                        onclick="confirmDelete('attendance.php?delete=<?= $r['id'] ?>','<?= htmlspecialchars($r['first_name'].' '.$r['last_name'], ENT_QUOTES) ?>')">
                        Del
                    </button>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php require 'layout_footer.php'; ?>
