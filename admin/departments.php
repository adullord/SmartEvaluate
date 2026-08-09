<?php
require_once '_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyAdminCsrf();
        $action = (string)($_POST['action'] ?? '');
        $id = requestInt($_POST['id'] ?? null, 'id');

        if ($action === 'delete') {
            $users = $pdo->prepare('SELECT COUNT(*) FROM users WHERE department_id=?');
            $users->execute([$id]);
            if ((int)$users->fetchColumn() > 0) {
                throw new RuntimeException('ไม่สามารถลบหน่วยบริการที่ยังมีบุคลากรใช้งานอยู่');
            }
            $pdo->prepare('DELETE FROM departments WHERE id=?')->execute([$id]);
            adminRedirect('departments.php', 'success', 'ลบหน่วยบริการเรียบร้อย');
        }

        $serviceCode = trim((string)($_POST['service_code'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));
        $shortName = trim((string)($_POST['short_name'] ?? ''));
        $type = (string)($_POST['type'] ?? '');

        if (!preg_match('/^\d{5}$/', $serviceCode)) throw new RuntimeException('รหัสหน่วยบริการต้องเป็นตัวเลข 5 หลัก');
        if ($name === '' || $shortName === '') throw new RuntimeException('กรุณาระบุชื่อและชื่อแสดงผลของหน่วยบริการ');
        if (!in_array($type, ['SSO', 'RPST'], true)) throw new RuntimeException('ประเภทหน่วยบริการไม่ถูกต้อง');

        if ($action === 'add') {
            $pdo->prepare('INSERT INTO departments (service_code, name, short_name, type) VALUES (?, ?, ?, ?)')
                ->execute([$serviceCode, $name, $shortName, $type]);
        } elseif ($action === 'update') {
            $pdo->prepare('UPDATE departments SET service_code=?, name=?, short_name=?, type=? WHERE id=?')
                ->execute([$serviceCode, $name, $shortName, $type, $id]);
        } else {
            throw new RuntimeException('ไม่พบคำสั่งที่ต้องการ');
        }
        adminRedirect('departments.php', 'success', 'บันทึกหน่วยบริการเรียบร้อย');
    } catch (Throwable $e) {
        $message = (string)$e->getCode() === '23000' ? 'รหัสหรือชื่อหน่วยบริการนี้มีอยู่แล้ว' : $e->getMessage();
        adminRedirect('departments.php', 'error', $message);
    }
}

$departments = $pdo->query(
    'SELECT d.*, COUNT(u.id) AS user_count
     FROM departments d LEFT JOIN users u ON u.department_id=d.id
     GROUP BY d.id ORDER BY FIELD(d.type, "SSO", "RPST"), d.service_code'
)->fetchAll();

require_once '../includes/header.php';
adminPageHeader(appIcon('building') . ' จัดการหน่วยบริการ', 'เพิ่ม แก้ไข หรือลบหน่วยบริการที่ไม่มีบุคลากรใช้งาน');
renderAdminFlash();
?>
<div class="card" style="margin-bottom:1.5rem">
  <h3><?= appIcon('plus') ?> เพิ่มหน่วยบริการ</h3>
  <form method="post" class="admin-inline-form" style="display:grid;grid-template-columns:130px minmax(200px,1fr) minmax(220px,1fr) 150px auto;gap:.75rem;align-items:end">
    <?= adminCsrfField() ?>
    <input type="hidden" name="action" value="add">
    <div class="form-group" style="margin:0"><label>รหัสหน่วยบริการ</label><input class="form-control" type="text" name="service_code" inputmode="numeric" pattern="\d{5}" maxlength="5" required></div>
    <div class="form-group" style="margin:0"><label>ชื่อหน่วยบริการ</label><input class="form-control" type="text" name="name" required></div>
    <div class="form-group" style="margin:0"><label>ชื่อแสดงผล</label><input class="form-control" type="text" name="short_name" required></div>
    <div class="form-group" style="margin:0"><label>ประเภท</label><select class="form-control" name="type" required><option value="SSO">สสอ.</option><option value="RPST">รพ.สต.</option></select></div>
    <button class="btn btn-primary" type="submit"><?= appIcon('plus') ?> เพิ่ม</button>
  </form>
</div>
<div class="card">
  <div class="table-wrap"><table>
    <thead><tr><th style="width:130px">รหัส</th><th>ชื่อหน่วยบริการ</th><th>ชื่อแสดงผล</th><th style="width:130px">ประเภท</th><th style="width:90px">บุคลากร</th><th style="width:190px">จัดการ</th></tr></thead>
    <tbody>
    <?php foreach ($departments as $department): $formId = 'department-' . (int)$department['id']; ?>
      <tr>
        <td><input form="<?= $formId ?>" class="form-control" type="text" name="service_code" inputmode="numeric" pattern="\d{5}" maxlength="5" value="<?= htmlspecialchars($department['service_code'] ?? '') ?>" required></td>
        <td><input form="<?= $formId ?>" class="form-control" type="text" name="name" value="<?= htmlspecialchars($department['name']) ?>" required></td>
        <td><input form="<?= $formId ?>" class="form-control" type="text" name="short_name" value="<?= htmlspecialchars($department['short_name'] ?? $department['name']) ?>" required></td>
        <td><select form="<?= $formId ?>" class="form-control" name="type"><option value="SSO" <?= $department['type'] === 'SSO' ? 'selected' : '' ?>>สสอ.</option><option value="RPST" <?= $department['type'] === 'RPST' ? 'selected' : '' ?>>รพ.สต.</option></select></td>
        <td><?= (int)$department['user_count'] ?> คน</td>
        <td><div style="display:flex;gap:.4rem">
          <form method="post" id="<?= $formId ?>">
            <?= adminCsrfField() ?>
            <input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$department['id'] ?>">
            <button class="btn btn-secondary" type="submit"><?= appIcon('save') ?> บันทึก</button>
          </form>
          <form method="post" onsubmit="return confirm('ยืนยันการลบ <?= htmlspecialchars($department['short_name'] ?? $department['name'], ENT_QUOTES) ?>?')">
            <?= adminCsrfField() ?>
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$department['id'] ?>">
            <button class="btn btn-danger" type="submit" <?= (int)$department['user_count'] > 0 ? 'disabled title="มีบุคลากรใช้งานอยู่"' : '' ?>><?= appIcon('x-circle') ?> ลบ</button>
          </form>
        </div></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$departments): ?><tr><td colspan="6" style="text-align:center;color:var(--text-muted)">ยังไม่มีหน่วยบริการ</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php require_once '../includes/footer.php'; ?>
