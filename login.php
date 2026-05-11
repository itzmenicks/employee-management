<?php
session_start();
session_unset();
session_destroy();
session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    try {
        $pdo = new PDO("mysql:host=127.0.0.1;dbname=ems_db;charset=utf8mb4", "root", "",
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $password === $user['password']) {
            $_SESSION['user_id']     = $user['id'];
            $_SESSION['user_name']   = $user['name'];
            $_SESSION['user_role']   = $user['role'];
            $_SESSION['employee_id'] = $user['employee_id'] ?? null;

            if ($user['role'] === 'employee') {
                header('Location: emp_dashboard.php');
            } else {
                header('Location: index.php');
            }
            exit();
        } else {
            $error = 'Email ya password galat hai.';
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — EMS</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { display:flex; align-items:center; justify-content:center; min-height:100vh; background:var(--bg); }
        .login-box { width:100%; max-width:420px; background:var(--bg2); border:1px solid var(--border); border-radius:16px; padding:40px; }
        .logo-icon { width:44px; height:44px; background:var(--accent); border-radius:10px; display:grid; place-items:center; font-weight:800; font-size:20px; color:#0d0f14; }
        .login-title { font-family:'Syne',sans-serif; font-size:26px; font-weight:800; margin-bottom:6px; }
        .login-sub { color:var(--muted); font-size:14px; margin-bottom:28px; }
        .login-group { margin-bottom:16px; }
        .login-group label { display:block; font-size:13px; color:var(--muted); margin-bottom:6px; }
        .login-group input { width:100%; padding:12px 14px; background:var(--bg3); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:15px; }
        .login-group input:focus { outline:none; border-color:var(--accent); }
        .login-btn { width:100%; padding:13px; background:var(--accent); color:#0d0f14; border:none; border-radius:8px; font-size:15px; font-weight:700; cursor:pointer; margin-top:8px; }
        .login-btn:hover { background:#f0d980; }
        .login-error { background:rgba(239,68,68,0.12); border:1px solid rgba(239,68,68,0.3); color:#ef4444; padding:11px 14px; border-radius:8px; font-size:14px; margin-bottom:18px; }
        .login-hint { margin-top:20px; padding:14px; background:var(--bg3); border-radius:8px; font-size:13px; color:var(--muted); text-align:center; border:1px solid var(--border); }
        .login-hint strong { color:var(--accent); }
    </style>
</head>
<body>
    <div class="login-box">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:32px">
            <div class="logo-icon">E</div>
            <div>
                <div style="font-weight:700;font-size:22px">EMS</div>
                <div style="font-size:12px;color:var(--muted)">Employee Management System</div>
            </div>
        </div>
        <div class="login-title">Welcome back 👋</div>
        <div class="login-sub">Apna account me login karo</div>
        <?php if ($error): ?>
        <div class="login-error">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="login-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="admin@ems.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
            </div>
            <div class="login-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="login-btn">Login →</button>
        </form>
        <div class="login-hint">
            Admin: <strong>admin@ems.com</strong> / <strong>admin123</strong><br>
            Employee: <strong>arjun@company.com</strong> / <strong>arjun123</strong>
        </div>
    </div>
</body>
</html>
