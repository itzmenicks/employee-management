<?php
require_once 'config.php';
require 'auth_employee.php';
$pdo = getDB();

$empId = $_SESSION['employee_id'];
$month = (int)($_GET["month"] ?? date("n"));
$year  = (int)($_GET["year"]  ?? date("Y"));

$records = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND MONTH(date) = ? AND YEAR(date) = ? ORDER BY date DESC");
$records->execute([$empId, $month, $year]);
$records = $records->fetchAll();

$present  = count(array_filter($records, fn($r) => $r['status']==='present'));
$absent   = count(array_filter($records, fn($r) => $r['status']==='absent'));
$late     = count(array_filter($records, fn($r) => $r['status']==='late'));
$halfday  = count(array_filter($records, fn($r) => $r['status']==='half_day'));

$months = ['','January','February','March','April','May','June','July','August','September','October','November','December'];

$pageTitle  = 'My Attendance';
$activePage = 'emp_attendance';
require 'layout_employee.php';
?>

<!-- Filter -->
<form method="GET" class="search-bar" style="margin-bottom:20px">
    <select name="month" class="filter-select">
        <?php for ($m=1;$m<=12;$m++): ?>
        <option value="<?= $m ?>" <?= $month==$m?'selected':'' ?>><?= $months[$m] ?></option>
        <?php endfor; ?>
    </select>
    <select name="year" class="filter-select">
        <?php for ($y=date('Y');$y>=date('Y')-3;$y--): ?>
        <option value="<?= $y ?>" <?= $year==$y?'selected':'' ?>><?= $y ?></option>
        <?php endfor; ?>
    </select>
    <button type="submit" class="btn btn-primary">View</button>
</form>

<div class="stats-grid" style="margin-bottom:24px">
    <div class="stat-card"><div class="stat-label">Present</div><div class="stat-value" style="color:var(--success)"><?= $present ?></div></div>
    <div class="stat-card"><div class="stat-label">Absent</div><div class="stat-value" style="color:var(--danger)"><?= $absent ?></div></div>
    <div class="stat-card"><div class="stat-label">Late</div><div class="stat-value" style="color:#eab308"><?= $late ?></div></div>
    <div class="stat-card"><div class="stat-label">Half Day</div><div class="stat-value" style="color:var(--info)"><?= $halfday ?></div></div>
</div>

<div class="table-wrapper">
    <div class="table-header">
        <div class="table-title">Attendance — <?= $months[$month] ?> <?= $year ?></div>
    </div>
    <table>
        <thead><tr><th>Date</th><th>Day</th><th>Check In</th><th>Check Out</th><th>Status</th><th>Notes</th></tr></thead>
        <tbody>
            <?php if (empty($records)): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:40px">No attendance records found.</td></tr>
            <?php else: foreach ($records as $r): ?>
            <tr>
                <td style="font-weight:500"><?= date('d M Y', strtotime($r['date'])) ?></td>
                <td style="color:var(--muted);font-size:13px"><?= date('l', strtotime($r['date'])) ?></td>
                <td style="color:var(--success)"><?= $r['check_in'] ? date('h:i A', strtotime($r['check_in'])) : '—' ?></td>
                <td style="color:var(--danger)"><?= $r['check_out'] ? date('h:i A', strtotime($r['check_out'])) : '—' ?></td>
                <td><?php $bc=match($r['status']){'present'=>'badge-active','absent'=>'badge-inactive','late'=>'badge-pending',default=>'badge-dept'}; ?><span class="badge <?= $bc ?>"><?= ucfirst(str_replace('_',' ',$r['status'])) ?></span></td>
                <td style="color:var(--muted);font-size:13px"><?= htmlspecialchars($r['notes'] ?? '—') ?></td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php require 'layout_employee_footer.php'; ?>