<?php
require_once 'config.php';
require 'auth_employee.php';
$pdo = getDB();

$empId = $_SESSION['employee_id'];
$month = (int)($_GET['month'] ?? date('n'));
$year  = (int)($_GET['year']  ?? date('Y'));

$emp = $pdo->prepare("SELECT e.*, d.name AS dept_name FROM employees e LEFT JOIN departments d ON e.department_id = d.id WHERE e.id = ?");
$emp->execute([$empId]);
$emp = $emp->fetch();

$payslip = $pdo->prepare("SELECT * FROM salary_records WHERE employee_id = ? AND month = ? AND year = ?");
$payslip->execute([$empId, $month, $year]);
$payslip = $payslip->fetch();

$allPayslips = $pdo->prepare("SELECT month, year, net_salary, status FROM salary_records WHERE employee_id = ? ORDER BY year DESC, month DESC");
$allPayslips->execute([$empId]);
$allPayslips = $allPayslips->fetchAll();

$months = ['','January','February','March','April','May','June','July','August','September','October','November','December'];

$pageTitle  = 'My Payslip';
$activePage = 'emp_payslip';
require 'layout_employee.php';
?>

<!-- Filter -->
<form method="GET" class="search-bar" style="margin-bottom:24px">
    <select name="month" class="filter-select">
        <?php for ($m=1;$m<=12;$m++): ?>
        <option value="<?= $m ?>" <?= $month==$m?'selected':'' ?>><?= $months[$m] ?></option>
        <?php endfor; ?>
    </select>
    <select name="year" class="filter-select">
        <?php for ($y=date('Y');$y>=date('Y')-5;$y--): ?>
        <option value="<?= $y ?>" <?= $year==$y?'selected':'' ?>><?= $y ?></option>
        <?php endfor; ?>
    </select>
    <button type="submit" class="btn btn-primary">View Payslip</button>
</form>

<?php if ($payslip): ?>
<div class="form-card" style="max-width:650px">
    <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:28px;padding-bottom:20px;border-bottom:1px solid var(--border)">
        <div>
            <div style="font-family:'Syne',sans-serif;font-size:22px;font-weight:800">PAYSLIP</div>
            <div style="color:var(--muted);font-size:13px"><?= $months[$month] ?> <?= $year ?></div>
        </div>
        <div style="text-align:right">
            <div style="font-size:13px;font-weight:600"><?= htmlspecialchars($emp['first_name'].' '.$emp['last_name']) ?></div>
            <div style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($emp['emp_code']) ?></div>
            <div style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($emp['dept_name'] ?? '') ?></div>
            <div style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($emp['designation'] ?? '') ?></div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
        <div style="background:var(--bg3);border-radius:8px;padding:16px">
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:8px">Earnings</div>
            <div style="display:flex;justify-content:space-between;margin-bottom:8px">
                <span style="font-size:14px">Basic Salary</span>
                <span style="font-weight:600">₹<?= number_format($payslip['basic_salary'],0) ?></span>
            </div>
            <div style="display:flex;justify-content:space-between">
                <span style="font-size:14px;color:var(--success)">Allowances</span>
                <span style="font-weight:600;color:var(--success)">+₹<?= number_format($payslip['allowances'],0) ?></span>
            </div>
        </div>
        <div style="background:var(--bg3);border-radius:8px;padding:16px">
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:8px">Deductions</div>
            <div style="display:flex;justify-content:space-between">
                <span style="font-size:14px;color:var(--danger)">Deductions</span>
                <span style="font-weight:600;color:var(--danger)">-₹<?= number_format($payslip['deductions'],0) ?></span>
            </div>
        </div>
    </div>

    <div style="background:rgba(232,197,71,0.08);border:1px solid rgba(232,197,71,0.2);border-radius:10px;padding:20px;display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
        <div>
            <div style="font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted)">Net Salary</div>
            <div style="font-family:'Syne',sans-serif;font-size:32px;font-weight:800;color:var(--accent)">₹<?= number_format($payslip['net_salary'],0) ?></div>
        </div>
        <div>
            <span class="badge badge-<?= $payslip['status'] ?>"><?= ucfirst($payslip['status']) ?></span>
            <?php if ($payslip['payment_date']): ?>
            <div style="font-size:12px;color:var(--muted);margin-top:4px">Paid: <?= date('d M Y', strtotime($payslip['payment_date'])) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($payslip['notes']): ?>
    <div style="font-size:13px;color:var(--muted);padding:12px;background:var(--bg3);border-radius:8px">
        📝 <?= htmlspecialchars($payslip['notes']) ?>
    </div>
    <?php endif; ?>

    <div style="margin-top:16px;text-align:right">
        <button onclick="window.print()" class="btn btn-ghost btn-sm">🖨️ Print</button>
    </div>
</div>

<?php else: ?>
<div class="table-wrapper">
    <div style="text-align:center;padding:50px;color:var(--muted)">
        <?= $months[$month] ?> <?= $year ?> no payslip here
    </div>
</div>
<?php endif; ?>

<?php if (!empty($allPayslips)): ?>
<div class="table-wrapper" style="margin-top:24px">
    <div class="table-header"><div class="table-title">Payslip History</div></div>
    <table>
        <thead><tr><th>Month</th><th>Year</th><th>Net Salary</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
            <?php foreach ($allPayslips as $p): ?>
            <tr>
                <td><?= $months[(int)$p['month']] ?></td>
                <td><?= $p['year'] ?></td>
                <td style="font-weight:700;color:var(--accent)">₹<?= number_format($p['net_salary'],0) ?></td>
                <td><span class="badge badge-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></td>
                <td><a href="emp_payslip.php?month=<?= (int)$p['month'] ?>&year=<?= $p['year'] ?>" class="btn btn-ghost btn-sm">View</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require 'layout_employee_footer.php'; ?>