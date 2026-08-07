<?php
require_once 'config.php';
require_once 'csrf_helper.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$username = '';
$csrfToken = generate_csrf_token();
$now = time();
$attemptWindow = 15 * 60;
$maxAttempts = 5;

// เก็บเฉพาะความพยายามที่ล้มเหลวในช่วง 15 นาทีล่าสุด
$loginAttempts = array_values(array_filter(
    $_SESSION['login_attempts'] ?? [],
    static fn ($attemptTime) => is_int($attemptTime) && $attemptTime > ($now - $attemptWindow)
));
$_SESSION['login_attempts'] = $loginAttempts;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postToken = (string) ($_POST['csrf_token'] ?? '');
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!verify_csrf_token($postToken)) {
        $error = 'คำขอหมดอายุ กรุณารีเฟรชหน้าแล้วลองใหม่อีกครั้ง';
    } elseif (count($loginAttempts) >= $maxAttempts) {
        $retrySeconds = max(1, ($loginAttempts[0] + $attemptWindow) - $now);
        $retryMinutes = (int) ceil($retrySeconds / 60);
        $error = "ลองเข้าสู่ระบบหลายครั้งเกินไป กรุณารอประมาณ {$retryMinutes} นาที";
    } elseif ($username === '' || $password === '') {
        $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่านให้ครบถ้วน';
    } elseif (mb_strlen($username) > 13 || strlen($password) > 255) {
        $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    } else {
        // Prepared statement และ parameter binding ป้องกัน SQL injection
        $stmt = $pdo->prepare(
            'SELECT id, username, password, fullname, role, department_id, expected_level, is_active
             FROM users
             WHERE username = :username
             LIMIT 1'
        );
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        // ทำให้เวลาตอบสนองใกล้เคียงกัน แม้ไม่พบชื่อผู้ใช้
        $dummyHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $passwordValid = password_verify($password, $user['password'] ?? $dummyHash);

        if ($user && $passwordValid && (int) $user['is_active'] === 1) {
            session_regenerate_id(true);
            unset($_SESSION['login_attempts'], $_SESSION['csrf_token']);

            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['department_id'] = $user['department_id'];
            $_SESSION['expected_level'] = $user['expected_level'];

            header('Location: ' . ($user['role'] === 'admin' ? 'admin/index.php' : 'index.php'));
            exit;
        }

        $_SESSION['login_attempts'][] = $now;
        $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="เข้าสู่ระบบประเมินสมรรถนะบุคลากรสาธารณสุข">
    <title>เข้าสู่ระบบ — Smart Evaluate</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars(appUrl('assets/images/favicon.svg')) ?>">
</head>
<body class="login-page">
<main class="login-wrapper">
    <section class="login-card" aria-labelledby="login-title">
        <div class="login-header">
            <div class="login-icon" aria-hidden="true"><?= appIcon('bar-chart') ?></div>
            <p class="login-eyebrow">Smart Evaluate</p>
            <h1 id="login-title">เข้าสู่ระบบ</h1>
            <p>ระบบประเมินสมรรถนะ</p>
        </div>

        <?php if ($error): ?>
            <div class="login-error" role="alert">
                <?= appIcon('triangle-alert') ?>
                <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="login-form-group">
                <label for="username">ชื่อผู้ใช้</label>
                <div class="input-wrapper">
                    <span class="input-icon"><?= appIcon('id-card') ?></span>
                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>" required maxlength="13" autocomplete="username" placeholder="กรอกชื่อผู้ใช้" autofocus>
                </div>
            </div>

            <div class="login-form-group">
                <label for="password">รหัสผ่าน</label>
                <div class="input-wrapper">
                    <span class="input-icon"><?= appIcon('lock') ?></span>
                    <input type="password" id="password" name="password" required maxlength="255" autocomplete="current-password" placeholder="กรอกรหัสผ่าน">
                </div>
            </div>

            <button type="submit" class="login-btn">
                <?= appIcon('log-in') ?>
                <span>เข้าสู่ระบบ</span>
            </button>
        </form>

        <p class="login-security">สำนักงานสาธารณสุขอำเภอบันนังสตา</p>
    </section>
</main>
</body>
</html>
