<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../csrf_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . appUrl('login.php'));
    exit;
}
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('ไม่มีสิทธิ์เข้าถึงหน้าผู้ดูแลระบบ');
}

function adminRedirect(string $path, string $type, string $message): never
{
    $_SESSION['admin_flash'] = ['type' => $type, 'message' => $message];
    header('Location: ' . $path);
    exit;
}

function adminCsrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verifyAdminCsrf(): void
{
    if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
        throw new RuntimeException('คำขอหมดอายุ กรุณารีเฟรชหน้าแล้วลองใหม่อีกครั้ง');
    }
}

function renderAdminFlash(): void
{
    $flash = $_SESSION['admin_flash'] ?? null;
    unset($_SESSION['admin_flash']);
    if (!$flash) {
        return;
    }
    $success = $flash['type'] === 'success';
    $background = $success ? '#D1FAE5' : '#FEE2E2';
    $color = $success ? '#065F46' : '#991B1B';
    $border = $success ? '#10B981' : '#EF4444';
    $icon = appIcon($success ? 'check-circle' : 'x-circle');
    echo '<div style="background:' . $background . ';color:' . $color . ';padding:1rem;border-radius:8px;margin-bottom:1rem;border-left:4px solid ' . $border . '">'
        . $icon . ' ' . htmlspecialchars($flash['message']) . '</div>';
}

function adminPageHeader(string $title, string $subtitle): void
{
    echo '<div class="card" style="margin-bottom:1.5rem"><div class="card-header" style="display:flex;justify-content:space-between;gap:1rem;align-items:center;flex-wrap:wrap">'
        . '<div><h2 class="card-title">' . $title . '</h2><p style="color:var(--text-muted);margin:.4rem 0 0">' . htmlspecialchars($subtitle) . '</p></div>'
        . '<a href="index.php" class="btn btn-secondary">' . appIcon('arrow-left') . ' กลับหน้า Admin</a></div></div>';
}
