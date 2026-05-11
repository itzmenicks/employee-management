<?php
require_once 'config.php';
require 'auth.php';
$pdo = getDB();

$date = sanitize($_GET['date'] ?? date('Y-m-d'));
$employees = $pdo->query("
    SELECT e.*, d.name AS dept_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE e.status = 'active'
    ORDER BY e.first_name
")->fetchAll();

// Get already marked attendance for this date
$marked = $pdo->prepare("SELECT * FROM attendance WHERE date = ?");
$marked->execute([$date]);
$markedData = [];
foreach ($marked->fetchAll() as $m) {
    $markedData[$m['employee_id']] = $m;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $saved = 0;
    foreach ($_POST['attendance'] as $empId => $att) {
        $empId    = (int)$empId;
        $status   = in_array($att['status'], ['present','absent','late','half_day']) ? $att['status'] : 'present';
        $checkIn  = $att['check_in'] ?: null;
        $checkOut = $att['check_out'] ?: null;
        $notes    = htmlspecialchars(strip_tags(trim($att['notes'] ?? '')));

        try {
            if (isset($markedData[$empId])) {
                $pdo->prepare("UPDATE attendance SET status=?,check_in=?,check_out=?,notes=? WHERE employee_id=? AND date=?")
                    ->execute([$status, $checkIn, $checkOut, $notes, $empId, $date]);
            } else {
                $pdo->prepare("INSERT INTO attendance (employee_id,date,status,check_in,check_out,notes) VALUES (?,?,?,?,?,?)")
                    ->execute([$empId, $date, $status, $checkIn, $checkOut, $notes]);
            }
            $saved++;
        } catch (PDOException $e) {
            // skip duplicates
        }
    }
    redirect('attendance.php?date='.$date, "Attendance marked for $saved employees!");
}

$pageTitle  = 'Mark Attendance';
$activePage = 'attendance';
require 'layout.php';
?>

<div style="margin-bottom:20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
    <div style="font-size:14px;color:var(--muted)">Date:</div>
    <input type="date" id="dateSwitch" value="<?= $date ?>"
           style="padding:8px 12px;background:var(--bg2);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:14px"
           onchange="window.location='attendance_mark.php?date='+this.value">
    <div style="display:flex;gap:8px">
        <span class="badge badge-active">✅ Present: <?= count(array_filter($markedData, fn($m) => $m['status']==='present')) ?></span>
        <span class="badge badge-inactive">❌ Absent: <?= count(array_filter($markedData, fn($m) => $m['status']==='absent')) ?></span>
        <span class="badge badge-pending">⏰ Late: <?= count(array_filter($markedData, fn($m) => $m['status']==='late')) ?></span>
    </div>
</div>

<form method="POST">
<div class="table-wrapper">
    <div class="table-header">
        <div class="table-title">
            Mark Attendance — <?= date('d M Y', strtotime($date)) ?>
            (<?= count($employees) ?> employees)
        </div>
        <div style="display:flex;gap:8px">
            <button type="button" class="btn btn-ghost btn-sm" onclick="markAll('present')">✅ All Present</button>
            <button type="button" class="btn btn-ghost btn-sm" onclick="markAll('absent')">❌ All Absent</button>
            <button type="submit" class="btn btn-primary btn-sm">💾 Save All</button>
        </div>
    </div>
    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th>Department</th>
                <th>Status</th>
                <th>Check In</th>
                <th>Check Out</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($employees)): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:40px">No active employees found.</td></tr>
            <?php else: foreach ($employees as $e):
                $att = $markedData[$e['id']] ?? null;
                $currentStatus = $att['status'] ?? 'present';
            ?>
            <tr>
                <td>
                    <span class="avatar"><?= strtoupper(substr($e['first_name'],0,1).substr($e['last_name'],0,1)) ?></span>
                    <span class="emp-name"><?= htmlspecialchars($e['first_name'].' '.$e['last_name']) ?></span><br>
                    <small class="emp-email"><?= htmlspecialchars($e['emp_code']) ?></small>
                </td>
                <td><span class="badge badge-dept"><?= htmlspecialchars($e['dept_name'] ?? '—') ?></span></td>
                <td>
                    <select name="attendance[<?= $e['id'] ?>][status]" class="status-select filter-select"
                            style="padding:6px 10px;font-size:13px"
                            onchange="updateRow(this)">
                        <option value="present"  <?= $currentStatus==='present'  ?'selected':'' ?>>✅ Present</option>
                        <option value="absent"   <?= $currentStatus==='absent'   ?'selected':'' ?>>❌ Absent</option>
                        <option value="late"     <?= $currentStatus==='late'     ?'selected':'' ?>>⏰ Late</option>
                        <option value="half_day" <?= $currentStatus==='half_day' ?'selected':'' ?>>◑ Half Day</option>
                    </select>
                </td>
                <td>
                    <input type="time" name="attendance[<?= $e['id'] ?>][check_in]"
                           value="<?= htmlspecialchars($att['check_in'] ?? '09:00') ?>"
                           style="padding:6px 10px;background:var(--bg3);border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:13px">
                </td>
                <td>
                    <input type="time" name="attendance[<?= $e['id'] ?>][check_out]"
                           value="<?= htmlspecialchars($att['check_out'] ?? '18:00') ?>"
                           style="padding:6px 10px;background:var(--bg3);border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:13px">
                </td>
                <td>
                    <input type="text" name="attendance[<?= $e['id'] ?>][notes]"
                           value="<?= htmlspecialchars($att['notes'] ?? '') ?>"
                           placeholder="Optional…"
                           style="padding:6px 10px;background:var(--bg3);border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:13px;width:120px">
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
    <div style="padding:16px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px">
        <a href="attendance.php" class="btn btn-ghost">Cancel</a>
        <button type="submit" class="btn btn-primary">💾 Save Attendance</button>
    </div>
</div>
</form>

<script>
function markAll(status) {
    document.querySelectorAll('.status-select').forEach(sel => {
        sel.value = status;
        updateRow(sel);
    });
}
function updateRow(sel) {
    const row = sel.closest('tr');
    const colors = {
        present:  'rgba(34,197,94,0.05)',
        absent:   'rgba(239,68,68,0.05)',
        late:     'rgba(234,179,8,0.05)',
        half_day: 'rgba(59,130,246,0.05)'
    };
    row.style.background = colors[sel.value] || '';
}
// Init colors
document.querySelectorAll('.status-select').forEach(sel => updateRow(sel));
</script>

<?php require 'layout_footer.php'; ?>
