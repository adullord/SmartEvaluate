<?php
require_once '../config.php';
require_once '../csrf_helper.php';
require_once 'users_import_helper.php';
require_once '../includes/user_role_helper.php';
require_once '../includes/expected_level_helper.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . appUrl('index.php'));
    exit;
}

$message = '';
$error = '';
$importErrors = [];
$csrfToken = generate_csrf_token();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        die('คำขอหมดอายุ กรุณารีเฟรชหน้าแล้วลองใหม่อีกครั้ง');
    }
    $action = $_POST['action'] ?? '';
    $target_id = requestInt($_POST['target_id'] ?? null, 'target_id', 0, 0);
    
    if ($action === 'import_users') {
        try {
            $importResult = importUsersFromSpreadsheet($pdo, $_FILES['users_file'] ?? []);
            $importErrors = $importResult['errors'];
            if ($importErrors) {
                $error = 'ไม่สามารถนำเข้าข้อมูลได้ กรุณาตรวจสอบรายการด้านล่าง';
            } else {
                $message = 'นำเข้าบุคลากรสำเร็จ ' . (int)$importResult['imported'] . ' คน';
            }
        } catch (Throwable $e) {
            $error = 'ไม่สามารถนำเข้าไฟล์ได้: ' . $e->getMessage();
        }
    } elseif ($action === 'reset_password' && $target_id) {
        $new_password = $_POST['new_password'] ?? '';
        if (strlen($new_password) < 12 || strlen($new_password) > 255) {
            $error = 'รหัสผ่านต้องมีความยาว 12–255 ตัวอักษร';
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$hashed, $target_id])) {
                $message = 'รีเซ็ตรหัสผ่านสำเร็จ';
            } else {
                $error = 'เกิดข้อผิดพลาดในการรีเซ็ตรหัสผ่าน';
            }
        }
    } elseif ($action === 'toggle_status' && $target_id) {
        $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
        if ($stmt->execute([$target_id])) {
            $message = 'เปลี่ยนสถานะผู้ใช้งานสำเร็จ';
        } else {
            $error = 'เกิดข้อผิดพลาดในการเปลี่ยนสถานะ';
        }
    } elseif ($action === 'edit_user' && $target_id) {
        $username = trim($_POST['username'] ?? '');
        $fullname = trim($_POST['fullname'] ?? '');
        $role = $_POST['role'] ?? 'staff';
        if (strlen($username) < 4 || $fullname === '' || !in_array($role, ['admin','ss_amphoe','sso_assistant','director','staff'], true)) {
            $error = 'กรุณากรอกข้อมูลบุคลากรให้ครบถ้วน';
        } else {
            try {
                $departmentId = requestInt($_POST['department_id'] ?? null, 'department_id');
                $positionId = requestInt($_POST['position_id'] ?? null, 'position_id');
                $rankId = requestInt($_POST['rank_id'] ?? null, 'rank_id');
                $expectedLevel = expectedLevelFromIds($pdo, $positionId, $rankId);
                $stmt = $pdo->prepare('UPDATE users SET username=?, fullname=?, role=?, department_id=?, position_id=?, rank_id=?, expected_level=? WHERE id=?');
                $stmt->execute([$username,$fullname,$role,$departmentId,$positionId,$rankId,$expectedLevel,$target_id]);
                syncUserRoles($pdo, (int)$target_id, $role);
                $message = 'แก้ไขข้อมูลบุคลากรเรียบร้อย';
            } catch (Throwable $e) {
                $error = $e instanceof PDOException && $e->getCode() === '23000' ? 'ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว' : $e->getMessage();
            }
        }
    } elseif ($action === 'add_user') {
        $username = $_POST['username'] ?? '';
        $fullname = $_POST['fullname'] ?? '';
        $role = $_POST['role'] ?? 'staff';
        $department_id = requestInt($_POST['department_id'] ?? null, 'department_id');
        $position_id = requestInt($_POST['position_id'] ?? null, 'position_id');
        $rank_id = requestInt($_POST['rank_id'] ?? null, 'rank_id');
        $password = $_POST['password'] ?? '';
        
        if (strlen($username) < 4 || strlen($username) > 13 || strlen($password) < 12 || strlen($password) > 255 || trim($fullname) === '' || !in_array($role, ['admin','ss_amphoe','sso_assistant','director','staff'], true)) {
            $error = 'ชื่อผู้ใช้ต้องยาว 4–13 ตัวอักษร และรหัสผ่านต้องยาว 12–255 ตัวอักษร';
        } else {
            // Check if username exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'ชื่อผู้ใช้งานนี้มีอยู่ในระบบแล้ว';
            } else {
                try {
                    $expected_level = expectedLevelFromIds($pdo, (int)$position_id, (int)$rank_id);
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, password, fullname, role, department_id, position_id, rank_id, expected_level)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$username, $hashed, $fullname, $role, $department_id, $position_id, $rank_id, $expected_level]);
                    syncUserRoles($pdo, (int)$pdo->lastInsertId(), $role);
                    $message = 'เพิ่มผู้ใช้งานสำเร็จ';
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                }
            }
        }
    }
}

// Search and server-side pagination (DataTable-like)
$searchInput = $_GET['q'] ?? '';
if (is_array($searchInput)) { http_response_code(400); exit('พารามิเตอร์ค้นหาไม่ถูกต้อง'); }
$search = mb_substr(trim((string)$searchInput), 0, 100);
$perPageOptions = [10, 25, 50, 100];
$perPage = requestInt($_GET['per_page'] ?? null, 'per_page', 25, 1, 100);
if (!in_array($perPage, $perPageOptions, true)) $perPage = 25;
$page = requestInt($_GET['page'] ?? null, 'page', 1, 1, 1000000);

$fromSql = "
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    LEFT JOIN positions p ON u.position_id = p.id
    LEFT JOIN ranks r ON u.rank_id = r.id
";
$whereSql = '';
$queryParams = [];
if ($search !== '') {
    $whereSql = "
        WHERE u.username LIKE ?
           OR u.fullname LIKE ?
           OR d.name LIKE ?
           OR d.short_name LIKE ?
           OR d.service_code LIKE ?
           OR p.name LIKE ?
           OR r.name LIKE ?
           OR u.role LIKE ?
    ";
    $term = '%' . $search . '%';
    $queryParams = array_fill(0, 8, $term);
}

$countStmt = $pdo->prepare('SELECT COUNT(*) ' . $fromSql . $whereSql);
$countStmt->execute($queryParams);
$totalUsers = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalUsers / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT u.*, COALESCE(NULLIF(d.short_name, ''), d.name) as dept_name, p.name as pos_name, r.name as rank_name,
      (SELECT GROUP_CONCAT(rr.name ORDER BY rr.id SEPARATOR ', ')
       FROM user_roles ur JOIN roles rr ON rr.id = ur.role_id WHERE ur.user_id = u.id) AS role_names
    {$fromSql}
    {$whereSql}
    ORDER BY d.id, u.role, u.fullname
    LIMIT ? OFFSET ?
");
$bindIndex = 1;
foreach ($queryParams as $param) $stmt->bindValue($bindIndex++, $param, PDO::PARAM_STR);
$stmt->bindValue($bindIndex++, $perPage, PDO::PARAM_INT);
$stmt->bindValue($bindIndex, $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();

$rangeStart = $totalUsers > 0 ? $offset + 1 : 0;
$rangeEnd = min($offset + $perPage, $totalUsers);
$pageUrl = static function (int $targetPage) use ($search, $perPage): string {
    return '?' . http_build_query(['q' => $search, 'per_page' => $perPage, 'page' => $targetPage]);
};

// Fetch options for forms
$departments = $pdo->query("SELECT * FROM departments ORDER BY FIELD(type, 'SSO', 'RPST'), service_code")->fetchAll();
$positions = $pdo->query("SELECT * FROM positions ORDER BY name")->fetchAll();
$ranks = $pdo->query("SELECT * FROM ranks ORDER BY name")->fetchAll();

require_once '../includes/header.php';
?>

<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 class="card-title"><?= appIcon('users') ?> จัดการผู้ใช้งาน</h2>
            <p style="color: var(--text-muted); margin-top: 0.5rem;">ระบบจัดการบัญชีผู้ใช้ รีเซ็ตรหัสผ่าน และเปิด/ปิดการใช้งาน</p>
        </div>
        <div>
            <button class="btn btn-success" onclick="openAddModal()"><?= appIcon('user-plus') ?> เพิ่มผู้ใช้ใหม่</button>
            <a href="index.php" class="btn btn-secondary"><?= appIcon('arrow-left') ?> กลับหน้า Dashboard</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div style="background-color: #D1FAE5; color: #065F46; padding: 1rem; border-radius: 4px; margin-bottom: 1rem; border-left: 4px solid #10B981;">
            <?= appIcon('check-circle') ?> <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div style="background-color: #FEE2E2; color: #991B1B; padding: 1rem; border-radius: 4px; margin-bottom: 1rem; border-left: 4px solid #EF4444;">
            <?= appIcon('x-circle') ?> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($importErrors): ?>
        <div style="background:#FFF7ED;color:#9A3412;padding:1rem;border-radius:12px;margin-bottom:1rem;border-left:4px solid #F97316">
            <strong>รายการที่ต้องแก้ไข (<?= count($importErrors) ?> รายการ)</strong>
            <ul style="margin:.65rem 0 0 1.25rem">
                <?php foreach (array_slice($importErrors, 0, 30) as $importError): ?>
                    <li><?= htmlspecialchars($importError) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if (count($importErrors) > 30): ?><small>แสดง 30 รายการแรกจากทั้งหมด <?= count($importErrors) ?> รายการ</small><?php endif; ?>
        </div>
    <?php endif; ?>

    <div id="import-users" style="background:var(--primary-50);border:1px solid var(--primary-100);border-radius:16px;padding:1.25rem;margin-bottom:1.5rem">
        <div style="display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;flex-wrap:wrap;margin-bottom:1rem">
            <div>
                <h3 style="margin:0 0 .35rem"><?= appIcon('upload') ?> นำเข้าบุคลากรจาก Excel</h3>
                <p style="color:var(--text-muted);margin:0">ดาวน์โหลดแม่แบบ กรอกข้อมูลตั้งแต่แถวที่ 2 แล้วอัปโหลดกลับเข้าสู่ระบบ</p>
            </div>
            <a href="<?= htmlspecialchars(appUrl('outputs/personnel_import/personnel_import_template.xlsx')) ?>" download class="btn btn-secondary">
                <?= appIcon('download') ?> ดาวน์โหลด Template
            </a>
        </div>
        <form method="post" enctype="multipart/form-data" class="admin-inline-form" style="display:flex;gap:.75rem;align-items:end;flex-wrap:wrap">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="import_users">
            <div class="form-group" style="margin:0;flex:1;min-width:260px">
                <label for="users_file">ไฟล์ Excel (.xlsx หรือ .xls)</label>
                <input class="form-control" type="file" id="users_file" name="users_file" accept=".xlsx,.xls" required>
            </div>
            <button type="submit" class="btn btn-success" onclick="return confirm('ยืนยันนำเข้าข้อมูลบุคลากรจากไฟล์นี้?')">
                <?= appIcon('upload') ?> อัปโหลดและนำเข้า
            </button>
        </form>
        <small style="display:block;color:var(--text-muted);margin-top:.75rem">หากพบข้อมูลผิด ระบบจะไม่บันทึกทั้งไฟล์ และจะแจ้งเลขแถวให้แก้ไข</small>
    </div>

    <form method="get" class="admin-inline-form" style="display:flex;gap:.75rem;align-items:end;justify-content:space-between;flex-wrap:wrap;margin-bottom:1rem;padding:1rem;background:var(--bg-hover);border-radius:12px">
        <div style="display:flex;gap:.75rem;align-items:end;flex-wrap:wrap;flex:1">
            <div class="form-group" style="margin:0;min-width:280px;flex:1">
                <label for="userSearch">ค้นหารายชื่อ</label>
                <input class="form-control" type="search" id="userSearch" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="ชื่อผู้ใช้ ชื่อบุคลากร หน่วยบริการ ตำแหน่ง...">
            </div>
            <div class="form-group" style="margin:0;width:120px">
                <label for="perPage">แสดง</label>
                <select class="form-control" id="perPage" name="per_page" onchange="this.form.submit()">
                    <?php foreach ($perPageOptions as $option): ?><option value="<?= $option ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= $option ?> แถว</option><?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary" type="submit"><?= appIcon('search') ?> ค้นหา</button>
            <?php if ($search !== ''): ?><a class="btn btn-secondary" href="?per_page=<?= $perPage ?>"><?= appIcon('x-circle') ?> ล้างค้นหา</a><?php endif; ?>
        </div>
        <div style="color:var(--text-muted);white-space:nowrap">แสดง <?= number_format($rangeStart) ?>–<?= number_format($rangeEnd) ?> จาก <?= number_format($totalUsers) ?> รายการ</div>
    </form>

    <div style="overflow-x: auto;">
        <table class="table" style="width: 100%; text-align: left; border-collapse: collapse;">
            <thead>
                <tr style="background-color: var(--primary-50);">
                    <th style="width:70px;text-align:center">ลำดับ</th>
                    <th>ชื่อผู้ใช้งาน</th>
                    <th>ชื่อ-นามสกุล</th>
                    <th>หน่วยบริการ</th>
                    <th>บทบาท</th>
                    <th>ตำแหน่ง</th>
                    <th>สถานะ</th>
                    <th style="text-align: center;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $rowIndex => $u): ?>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="text-align:center"><?= number_format($offset + $rowIndex + 1) ?></td>
                    <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                    <td><?= htmlspecialchars($u['fullname']) ?></td>
                    <td><?= htmlspecialchars($u['dept_name']) ?></td>
                    <td><?= htmlspecialchars($u['role_names'] ?: $u['role']) ?></td>
                    <td><?= htmlspecialchars($u['pos_name']) ?><br><small style="color: var(--text-muted);"><?= htmlspecialchars($u['rank_name']) ?></small></td>
                    <td>
                        <?php if ($u['is_active']): ?>
                            <span class="badge" style="background-color: #D1FAE5; color: #065F46;">ใช้งานปกติ</span>
                        <?php else: ?>
                            <span class="badge" style="background-color: #FEE2E2; color: #991B1B;">ถูกระงับ</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick='openEditModal(<?= htmlspecialchars(json_encode($u, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES) ?>)'><?= appIcon('edit') ?> แก้ไข</button>
                        <button type="button" class="btn btn-primary btn-sm" onclick="openResetModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')">
                            <?= appIcon('key') ?> รีเซ็ตรหัส
                        </button>
                        <form method="POST" style="display: inline-block;" onsubmit="return confirm('ยืนยันการเปลี่ยนสถานะผู้ใช้นี้?');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-sm <?= $u['is_active'] ? 'btn-danger' : 'btn-success' ?>">
                                <?= $u['is_active'] ? 'ปิดใช้งาน' : 'เปิดใช้งาน' ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$users): ?>
                <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--text-muted)"><?= appIcon('inbox') ?> ไม่พบรายชื่อที่ตรงกับคำค้นหา</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav aria-label="แบ่งหน้ารายชื่อบุคลากร" style="display:flex;justify-content:center;align-items:center;gap:.35rem;flex-wrap:wrap;margin-top:1.25rem">
        <a class="btn btn-secondary btn-sm" href="<?= htmlspecialchars($pageUrl(1)) ?>" <?= $page === 1 ? 'aria-disabled="true" style="pointer-events:none;opacity:.5"' : '' ?>>หน้าแรก</a>
        <a class="btn btn-secondary btn-sm" href="<?= htmlspecialchars($pageUrl(max(1, $page - 1))) ?>" <?= $page === 1 ? 'aria-disabled="true" style="pointer-events:none;opacity:.5"' : '' ?>>ก่อนหน้า</a>
        <?php
        $firstPage = max(1, $page - 2);
        $lastPage = min($totalPages, $page + 2);
        for ($number = $firstPage; $number <= $lastPage; $number++):
        ?>
            <a class="btn btn-sm <?= $number === $page ? 'btn-primary' : 'btn-secondary' ?>" href="<?= htmlspecialchars($pageUrl($number)) ?>" <?= $number === $page ? 'aria-current="page"' : '' ?>><?= $number ?></a>
        <?php endfor; ?>
        <a class="btn btn-secondary btn-sm" href="<?= htmlspecialchars($pageUrl(min($totalPages, $page + 1))) ?>" <?= $page === $totalPages ? 'aria-disabled="true" style="pointer-events:none;opacity:.5"' : '' ?>>ถัดไป</a>
        <a class="btn btn-secondary btn-sm" href="<?= htmlspecialchars($pageUrl($totalPages)) ?>" <?= $page === $totalPages ? 'aria-disabled="true" style="pointer-events:none;opacity:.5"' : '' ?>>หน้าสุดท้าย</a>
        <span style="margin-left:.5rem;color:var(--text-muted)">หน้า <?= number_format($page) ?> / <?= number_format($totalPages) ?></span>
    </nav>
    <?php endif; ?>
</div>

<!-- Simple Modal for Password Reset -->
<div id="resetModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;">
    <div style="background: white; padding: 2rem; border-radius: 8px; width: 400px; max-width: 90%;">
        <h3 style="margin-top: 0;"><?= appIcon('key') ?> รีเซ็ตรหัสผ่าน</h3>
        <p>ผู้ใช้งาน: <strong id="resetUsernameDisplay"></strong></p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="target_id" id="resetTargetId" value="">
            <div class="form-group" style="margin-bottom: 1rem;">
                <label for="new_password">รหัสผ่านใหม่:</label>
                <input type="password" name="new_password" id="new_password" class="form-control" required minlength="12" maxlength="255" autocomplete="new-password">
            </div>
            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">บันทึก</button>
                <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeResetModal()">ยกเลิก</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal for Add User -->
<div id="addModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;">
    <div style="background: white; padding: 2rem; border-radius: 8px; width: 500px; max-width: 90%; max-height: 90vh; overflow-y: auto;">
        <h3 style="margin-top: 0;"><?= appIcon('user-plus') ?> เพิ่มผู้ใช้งานใหม่</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="add_user">
            
            <div class="form-group" style="margin-bottom: 1rem;">
                <label>ชื่อผู้ใช้ (Username):</label>
                <input type="text" name="username" class="form-control" required minlength="4">
            </div>
            <div class="form-group" style="margin-bottom: 1rem;">
                <label>รหัสผ่าน:</label>
                <input type="password" name="password" class="form-control" required minlength="12" maxlength="255" autocomplete="new-password">
            </div>
            <div class="form-group" style="margin-bottom: 1rem;">
                <label>ชื่อ-นามสกุล:</label>
                <input type="text" name="fullname" class="form-control" required>
            </div>
            <div class="form-group" style="margin-bottom: 1rem;">
                <label>บทบาท (Role):</label>
                <select name="role" class="form-control" required>
                    <option value="staff">บุคลากรทั่วไป (Staff)</option>
                    <option value="director">ผู้อำนวยการ รพ.สต. (Director)</option>
                    <option value="ss_amphoe">สาธารณสุขอำเภอ (SSO)</option>
                    <option value="sso_assistant">ผู้ช่วย สสอ. (SSO Assistant)</option>
                    <option value="admin">บุคลากร + admin</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 1rem;">
                <label>หน่วยบริการ:</label>
                <select name="department_id" class="form-control" required>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars(($d['service_code'] ? $d['service_code'] . ' - ' : '') . ($d['short_name'] ?: $d['name'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 1rem;">
                <label>สายงาน (Position):</label>
                <select name="position_id" id="add_position" class="form-control" required onchange="updateExpectedLevel('add')">
                    <?php foreach ($positions as $p): ?>
                        <option value="<?= $p['id'] ?>" data-name="<?= htmlspecialchars($p['name']) ?>"><?= htmlspecialchars($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group" style="flex: 1;">
                    <label>ระดับตำแหน่ง (Rank):</label>
                    <select name="rank_id" id="add_rank" class="form-control" required onchange="updateExpectedLevel('add')">
                        <?php foreach ($ranks as $r): ?>
                            <option value="<?= $r['id'] ?>" data-name="<?= htmlspecialchars($r['name']) ?>"><?= htmlspecialchars($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>ระดับที่คาดหวัง:</label>
                    <input id="add_expected_level" class="form-control" type="text" value="คำนวณอัตโนมัติ" readonly>
                </div>
            </div>
            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-success" style="flex: 1;">เพิ่มผู้ใช้งาน</button>
                <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeAddModal()">ยกเลิก</button>
            </div>
        </form>
    </div>
</div>

<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);align-items:center;justify-content:center;z-index:1000">
  <div style="background:white;padding:2rem;border-radius:8px;width:520px;max-width:92%;max-height:90vh;overflow:auto">
    <h3><?= appIcon('edit') ?> แก้ไขข้อมูลบุคลากร</h3>
    <form method="post" class="admin-stack-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="action" value="edit_user"><input type="hidden" name="target_id" id="edit_id">
      <label>ชื่อผู้ใช้</label><input class="form-control" type="text" name="username" id="edit_username" required minlength="4">
      <label>ชื่อ-นามสกุล</label><input class="form-control" type="text" name="fullname" id="edit_fullname" required>
      <label>บทบาท</label><select class="form-control" name="role" id="edit_role"><option value="staff">บุคลากร</option><option value="director">ผอ.รพ.สต.</option><option value="ss_amphoe">สสอ.</option><option value="sso_assistant">ผู้ช่วย สสอ.</option><option value="admin">บุคลากร + admin</option></select>
      <label>หน่วยบริการ</label><select class="form-control" name="department_id" id="edit_department"><?php foreach($departments as $d): ?><option value="<?= $d['id'] ?>"><?= htmlspecialchars(($d['service_code'] ? $d['service_code'] . ' - ' : '') . ($d['short_name'] ?: $d['name'])) ?></option><?php endforeach ?></select>
      <label>สายงาน/ตำแหน่ง</label><select class="form-control" name="position_id" id="edit_position" onchange="updateExpectedLevel('edit')"><?php foreach($positions as $p): ?><option value="<?= $p['id'] ?>" data-name="<?= htmlspecialchars($p['name']) ?>"><?= htmlspecialchars($p['name']) ?></option><?php endforeach ?></select>
      <label>ระดับตำแหน่ง</label><select class="form-control" name="rank_id" id="edit_rank" onchange="updateExpectedLevel('edit')"><?php foreach($ranks as $r): ?><option value="<?= $r['id'] ?>" data-name="<?= htmlspecialchars($r['name']) ?>"><?= htmlspecialchars($r['name']) ?></option><?php endforeach ?></select>
      <label>ระดับที่คาดหวัง</label><input class="form-control" type="text" id="edit_expected_level" readonly>
      <div class="admin-form-actions"><button class="btn btn-primary">บันทึก</button><button type="button" class="btn btn-secondary" onclick="closeEditModal()">ยกเลิก</button></div>
    </form>
  </div>
</div>

<script>
function openResetModal(id, username) {
    document.getElementById('resetTargetId').value = id;
    document.getElementById('resetUsernameDisplay').textContent = username;
    document.getElementById('new_password').value = '';
    document.getElementById('resetModal').style.display = 'flex';
}

function closeResetModal() {
    document.getElementById('resetModal').style.display = 'none';
}

function openAddModal() {
    document.getElementById('addModal').style.display = 'flex';
    updateExpectedLevel('add');
}

function closeAddModal() {
    document.getElementById('addModal').style.display = 'none';
}
function openEditModal(user) {
    document.getElementById('edit_id').value = user.id;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_fullname').value = user.fullname;
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_department').value = user.department_id;
    document.getElementById('edit_position').value = user.position_id;
    document.getElementById('edit_rank').value = user.rank_id;
    updateExpectedLevel('edit');
    document.getElementById('editModal').style.display = 'flex';
}
function closeEditModal() { document.getElementById('editModal').style.display = 'none'; }
const expectedLevelRules = <?= json_encode([
    'สาธารณสุขอำเภอ' => ['อำนวยการระดับสูง' => 2],
    'ผู้อำนวยการ รพ.สต.' => ['ผู้อำนวยการ' => 2],
    'นักวิชาการสาธารณสุข' => ['ปฏิบัติการ' => 1, 'ชำนาญการ' => 1, 'ชำนาญการพิเศษ' => 2],
    'นักสาธารณสุข' => ['ปฏิบัติการ' => 1, 'ชำนาญการ' => 1],
    'พยาบาลวิชาชีพ' => ['ปฏิบัติการ' => 1, 'ชำนาญการ' => 1],
    'นักวิชาการคอมพิวเตอร์' => ['ปฏิบัติการ' => 1, 'ชำนาญการ' => 1],
    'แพทย์แผนไทย' => ['ปฏิบัติการ' => 1, 'ชำนาญการ' => 1],
    'เจ้าพนักงานสาธารณสุข' => ['ปฏิบัติงาน' => 1, 'ชำนาญงาน' => 1, 'ชำนาญการ' => 1, 'อาวุโส' => 2],
    'เจ้าพนักงานทันตสาธารณสุข' => ['ปฏิบัติงาน' => 1, 'ชำนาญงาน' => 1, 'ชำนาญการ' => 1],
    'เจ้าพนักงานการเงินและบัญชี' => ['ปฏิบัติงาน' => 1, 'ชำนาญงาน' => 1, 'ชำนาญการ' => 1],
], JSON_UNESCAPED_UNICODE) ?>;
function updateExpectedLevel(prefix) {
    const position = document.getElementById(prefix + '_position');
    const rank = document.getElementById(prefix + '_rank');
    const output = document.getElementById(prefix + '_expected_level');
    const positionName = position?.selectedOptions[0]?.dataset.name;
    const rankName = rank?.selectedOptions[0]?.dataset.name;
    const level = expectedLevelRules[positionName]?.[rankName];
    if (output) output.value = level ? `ระดับ ${level}` : 'ตำแหน่งและระดับตำแหน่งไม่สัมพันธ์กัน';
}
if (window.location.hash === '#add-user') openAddModal();
</script>

<?php require_once '../includes/footer.php'; ?>
