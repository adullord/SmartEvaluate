<?php
require_once '_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $serviceCode = trim($_POST['service_code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $shortName = trim($_POST['short_name'] ?? '');
    $type = $_POST['type'] ?? '';
    try {
        if (!preg_match('/^\d{5}$/', $serviceCode)) throw new RuntimeException('รหัสหน่วยบริการต้องเป็นตัวเลข 5 หลัก');
        if ($name === '' || $shortName === '') throw new RuntimeException('กรุณาระบุชื่อและชื่อแสดงผลของหน่วยบริการ');
        if (!in_array($type, ['SSO', 'RPST'], true)) throw new RuntimeException('ประเภทหน่วยบริการไม่ถูกต้อง');
        if ($action === 'add') {
            $pdo->prepare('INSERT INTO departments (service_code, name, short_name, type) VALUES (?, ?, ?, ?)')->execute([$serviceCode, $name, $shortName, $type]);
        } elseif ($action === 'update') {
            $pdo->prepare('UPDATE departments SET service_code = ?, name = ?, short_name = ?, type = ? WHERE id = ?')
                ->execute([$serviceCode, $name, $shortName, $type, (int)($_POST['id'] ?? 0)]);
        } else {
            throw new RuntimeException('ไม่พบคำสั่งที่ต้องการ');
        }
        adminRedirect('departments.php', 'success', 'บันทึกหน่วยบริการเรียบร้อย');
    } catch (Throwable $e) {
        adminRedirect('departments.php', 'error', $e->getCode() === '23000' ? 'ชื่อหน่วยบริการนี้มีอยู่แล้ว' : $e->getMessage());
    }
}

$departments = $pdo->query(
    'SELECT d.*, COUNT(u.id) AS user_count
     FROM departments d LEFT JOIN users u ON u.department_id = d.id
     GROUP BY d.id ORDER BY FIELD(d.type, "SSO", "RPST"), d.service_code'
)->fetchAll();

require_once '../includes/header.php';
adminPageHeader(appIcon('building') . ' จัดการหน่วยบริการ', 'เพิ่มหรือแก้ไขสำนักงานสาธารณสุขอำเภอและ รพ.สต.');
renderAdminFlash();
?>
<div class="card" style="margin-bottom:1.5rem">
  <h3><?= appIcon('plus') ?> เพิ่มหน่วยบริการ</h3>
  <form method="post" class="admin-inline-form" style="display:grid;grid-template-columns:130px minmax(200px,1fr) minmax(220px,1fr) 150px auto;gap:.75rem;align-items:end">
    <input type="hidden" name="action" value="add">
    <div class="form-group" style="margin:0"><label>รหัสหน่วยบริการ</label><input class="form-control" type="text" name="service_code" inputmode="numeric" pattern="\d{5}" maxlength="5" required></div>
    <div class="form-group" style="margin:0"><label>ชื่อหน่วยบริการ</label><input class="form-control" type="text" name="name" required></div>
    <div class="form-group" style="margin:0"><label>ชื่อแสดงผล</label><input class="form-control" type="text" name="short_name" required></div>
    <div class="form-group" style="margin:0"><label>ประเภท</label><select class="form-control" name="type" required><option value="SSO">สสอ.</option><option value="RPST">รพ.สต.</option></select></div>
    <button class="btn btn-primary" type="submit"><?= appIcon('plus') ?> เพิ่ม</button>
  </form>
</div>
<div class="card">
  <div class="table-wrap"><table><thead><tr><th style="width:130px">รหัสหน่วยบริการ</th><th>ชื่อหน่วยบริการ</th><th>ชื่อแสดงผล</th><th style="width:150px">ประเภท</th><th style="width:100px">บุคลากร</th><th style="width:120px">บันทึก</th></tr></thead><tbody>
  <?php foreach ($departments as $department): $formId = 'department-' . (int)$department['id']; ?>
    <tr>
      <td><input form="<?= $formId ?>" class="form-control" type="text" name="service_code" inputmode="numeric" pattern="\d{5}" maxlength="5" value="<?= htmlspecialchars($department['service_code'] ?? '') ?>" required></td>
      <td><input form="<?= $formId ?>" class="form-control" type="text" name="name" value="<?= htmlspecialchars($department['name']) ?>" required></td>
      <td><input form="<?= $formId ?>" class="form-control" type="text" name="short_name" value="<?= htmlspecialchars($department['short_name'] ?? $department['name']) ?>" required></td>
      <td><select form="<?= $formId ?>" class="form-control" name="type"><option value="SSO" <?= $department['type'] === 'SSO' ? 'selected' : '' ?>>สสอ.</option><option value="RPST" <?= $department['type'] === 'RPST' ? 'selected' : '' ?>>รพ.สต.</option></select></td>
      <td><?= (int)$department['user_count'] ?> คน</td>
      <td><form method="post" id="<?= $formId ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$department['id'] ?>"><button class="btn btn-secondary" type="submit"><?= appIcon('save') ?> บันทึก</button></form></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
</div>
<?php require_once '../includes/footer.php'; ?>
