<?php
require_once '_bootstrap.php';

$selectedInput = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['competency_id'] ?? null) : ($_GET['competency_id'] ?? null);
$selectedId = requestInt($selectedInput, 'competency_id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyAdminCsrf();
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'save_competency') {
            $id = requestInt($_POST['id'] ?? null, 'id', 0, 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $type = (string)($_POST['type'] ?? '');
            $order = max(1, (int)($_POST['order_seq'] ?? 1));
            if ($name === '') throw new RuntimeException('กรุณาระบุชื่อสมรรถนะ');
            if (!in_array($type, ['core', 'functional'], true)) throw new RuntimeException('ประเภทสมรรถนะไม่ถูกต้อง');

            if ($id > 0) {
                $pdo->prepare('UPDATE competencies SET name=?, description=?, type=?, order_seq=? WHERE id=?')
                    ->execute([$name, $description, $type, $order, $id]);
            } else {
                $pdo->prepare('INSERT INTO competencies (name, description, type, order_seq) VALUES (?, ?, ?, ?)')
                    ->execute([$name, $description, $type, $order]);
                $id = (int)$pdo->lastInsertId();
            }
            adminRedirect('competencies.php?competency_id=' . $id, 'success', 'บันทึกข้อมูลสมรรถนะเรียบร้อย');
        }

        if ($action === 'delete_competency') {
            $id = requestInt($_POST['id'] ?? null, 'id');
            $scores = $pdo->prepare(
                'SELECT COUNT(*) FROM evaluation_scores es
                 JOIN indicators i ON i.id=es.indicator_id
                 WHERE i.competency_id=?'
            );
            $scores->execute([$id]);
            if ((int)$scores->fetchColumn() > 0) {
                throw new RuntimeException('ไม่สามารถลบสมรรถนะที่มีคะแนนประเมินอ้างอิงอยู่');
            }
            $pdo->prepare('DELETE FROM competencies WHERE id=?')->execute([$id]);
            adminRedirect('competencies.php', 'success', 'ลบสมรรถนะและข้อมูลกำหนดที่เกี่ยวข้องเรียบร้อย');
        }

        if ($action === 'save_level') {
            $compId = requestInt($_POST['competency_id'] ?? null, 'competency_id');
            $level = (int)($_POST['expected_level'] ?? 0);
            $description = trim((string)($_POST['level_description'] ?? ''));
            if ($compId < 1 || !in_array($level, [1, 2, 3], true)) throw new RuntimeException('ข้อมูลระดับไม่ถูกต้อง');
            if ($description === '') throw new RuntimeException('กรุณาระบุคำอธิบายระดับ');
            $pdo->prepare(
                'INSERT INTO competency_levels (competency_id, expected_level, level_description)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE level_description=VALUES(level_description)'
            )->execute([$compId, $level, $description]);
            adminRedirect('competencies.php?competency_id=' . $compId, 'success', 'บันทึกคำอธิบายระดับเรียบร้อย');
        }

        if ($action === 'save_indicator') {
            $id = requestInt($_POST['id'] ?? null, 'id', 0, 0);
            $compId = requestInt($_POST['competency_id'] ?? null, 'competency_id');
            $level = (int)($_POST['expected_level'] ?? 0);
            $positionIdValue = requestInt($_POST['position_id'] ?? null, 'position_id', 0, 0);
            $positionId = $positionIdValue ?: null;
            $text = trim((string)($_POST['indicator_text'] ?? ''));
            $order = max(1, (int)($_POST['order_seq'] ?? 1));
            if ($compId < 1 || !in_array($level, [1, 2, 3], true)) throw new RuntimeException('ข้อมูลพฤติกรรมบ่งชี้ไม่ถูกต้อง');
            if ($text === '') throw new RuntimeException('กรุณาระบุพฤติกรรมบ่งชี้');

            if ($id > 0) {
                $pdo->prepare('UPDATE indicators SET expected_level=?, position_id=?, indicator_text=?, order_seq=? WHERE id=?')
                    ->execute([$level, $positionId, $text, $order, $id]);
            } else {
                $pdo->prepare('INSERT INTO indicators (competency_id, expected_level, position_id, indicator_text, order_seq) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$compId, $level, $positionId, $text, $order]);
            }
            adminRedirect('competencies.php?competency_id=' . $compId, 'success', 'บันทึกพฤติกรรมบ่งชี้เรียบร้อย');
        }

        if ($action === 'delete_indicator') {
            $id = requestInt($_POST['id'] ?? null, 'id');
            $compId = requestInt($_POST['competency_id'] ?? null, 'competency_id');
            $scores = $pdo->prepare('SELECT COUNT(*) FROM evaluation_scores WHERE indicator_id=?');
            $scores->execute([$id]);
            if ((int)$scores->fetchColumn() > 0) {
                throw new RuntimeException('ไม่สามารถลบพฤติกรรมที่มีคะแนนประเมินอ้างอิงอยู่');
            }
            $pdo->prepare('DELETE FROM indicators WHERE id=?')->execute([$id]);
            adminRedirect('competencies.php?competency_id=' . $compId, 'success', 'ลบพฤติกรรมบ่งชี้เรียบร้อย');
        }

        throw new RuntimeException('ไม่พบคำสั่งที่ต้องการ');
    } catch (Throwable $e) {
        $path = 'competencies.php' . ($selectedId > 0 ? '?competency_id=' . $selectedId : '');
        adminRedirect($path, 'error', $e->getMessage());
    }
}

$competencies = $pdo->query(
    'SELECT c.*, COUNT(DISTINCT i.id) indicator_count, COUNT(DISTINCT es.id) score_count
     FROM competencies c
     LEFT JOIN indicators i ON i.competency_id=c.id
     LEFT JOIN evaluation_scores es ON es.indicator_id=i.id
     GROUP BY c.id ORDER BY c.type, c.order_seq, c.id'
)->fetchAll();

if ($selectedId < 1 && $competencies) $selectedId = (int)$competencies[0]['id'];
$current = null;
foreach ($competencies as $competency) {
    if ((int)$competency['id'] === $selectedId) $current = $competency;
}

$levels = [];
$indicators = [];
if ($current) {
    $stmt = $pdo->prepare('SELECT * FROM competency_levels WHERE competency_id=? ORDER BY expected_level');
    $stmt->execute([$selectedId]);
    $levels = $stmt->fetchAll();
    $stmt = $pdo->prepare(
        'SELECT i.*, p.name position_name,
                (SELECT COUNT(*) FROM evaluation_scores es WHERE es.indicator_id=i.id) score_count
         FROM indicators i LEFT JOIN positions p ON p.id=i.position_id
         WHERE i.competency_id=? ORDER BY i.expected_level, i.order_seq, i.id'
    );
    $stmt->execute([$selectedId]);
    $indicators = $stmt->fetchAll();
}
$positions = $pdo->query('SELECT * FROM positions ORDER BY name')->fetchAll();

require_once '../includes/header.php';
adminPageHeader(appIcon('clipboard-list') . ' สมรรถนะและพฤติกรรมบ่งชี้', 'จัดการสมรรถนะ คำอธิบายระดับ และพฤติกรรมบ่งชี้ในรูปแบบตาราง');
renderAdminFlash();
?>

<div class="card" style="margin-bottom:1rem">
  <h3><?= appIcon('plus') ?> เพิ่มสมรรถนะ</h3>
  <form method="post" class="admin-form-grid" style="display:grid;grid-template-columns:90px minmax(220px,1fr) 180px minmax(280px,2fr) auto;gap:.7rem;align-items:end">
    <?= adminCsrfField() ?>
    <input type="hidden" name="action" value="save_competency"><input type="hidden" name="id" value="0">
    <div class="form-group" style="margin:0"><label>ลำดับ</label><input class="form-control" type="number" min="1" name="order_seq" value="1" required></div>
    <div class="form-group" style="margin:0"><label>ชื่อสมรรถนะ</label><input class="form-control" type="text" name="name" required></div>
    <div class="form-group" style="margin:0"><label>ประเภท</label><select class="form-control" name="type"><option value="core">สมรรถนะหลัก</option><option value="functional">สมรรถนะเฉพาะ</option></select></div>
    <div class="form-group" style="margin:0"><label>ความหมาย</label><input class="form-control" type="text" name="description"></div>
    <button class="btn btn-primary" type="submit"><?= appIcon('plus') ?> เพิ่ม</button>
  </form>
</div>

<div class="card" style="margin-bottom:1rem">
  <h3>ตารางสมรรถนะ</h3>
  <div class="table-wrap"><table>
    <thead><tr><th style="width:80px">ลำดับ</th><th style="min-width:230px">ชื่อสมรรถนะ</th><th style="width:170px">ประเภท</th><th style="min-width:300px">ความหมาย</th><th style="width:110px">พฤติกรรม</th><th style="width:270px">จัดการ</th></tr></thead>
    <tbody>
    <?php foreach ($competencies as $competency): $formId = 'competency-' . (int)$competency['id']; ?>
      <tr style="<?= (int)$competency['id'] === $selectedId ? 'background:var(--primary-50)' : '' ?>">
        <td><input form="<?= $formId ?>" class="form-control" type="number" min="1" name="order_seq" value="<?= (int)$competency['order_seq'] ?>"></td>
        <td><input form="<?= $formId ?>" class="form-control" type="text" name="name" value="<?= htmlspecialchars($competency['name']) ?>" required></td>
        <td><select form="<?= $formId ?>" class="form-control" name="type"><option value="core" <?= $competency['type'] === 'core' ? 'selected' : '' ?>>สมรรถนะหลัก</option><option value="functional" <?= $competency['type'] === 'functional' ? 'selected' : '' ?>>สมรรถนะเฉพาะ</option></select></td>
        <td><textarea form="<?= $formId ?>" class="form-control" name="description" rows="2"><?= htmlspecialchars($competency['description'] ?? '') ?></textarea></td>
        <td><?= (int)$competency['indicator_count'] ?> รายการ</td>
        <td><div style="display:flex;gap:.4rem;flex-wrap:wrap">
          <a class="btn btn-secondary" href="?competency_id=<?= (int)$competency['id'] ?>"><?= appIcon('eye') ?> พฤติกรรม</a>
          <form method="post" id="<?= $formId ?>">
            <?= adminCsrfField() ?>
            <input type="hidden" name="action" value="save_competency"><input type="hidden" name="id" value="<?= (int)$competency['id'] ?>">
            <button class="btn btn-secondary" type="submit"><?= appIcon('save') ?> บันทึก</button>
          </form>
          <form method="post" onsubmit="return confirm('ยืนยันการลบสมรรถนะนี้? ข้อมูลระดับ พฤติกรรม และน้ำหนักที่เกี่ยวข้องจะถูกลบด้วย')">
            <?= adminCsrfField() ?>
            <input type="hidden" name="action" value="delete_competency"><input type="hidden" name="id" value="<?= (int)$competency['id'] ?>"><input type="hidden" name="competency_id" value="<?= (int)$competency['id'] ?>">
            <button class="btn btn-danger" type="submit" <?= (int)$competency['score_count'] > 0 ? 'disabled title="มีคะแนนประเมินอ้างอิงอยู่"' : '' ?>><?= appIcon('x-circle') ?> ลบ</button>
          </form>
        </div></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$competencies): ?><tr><td colspan="6" style="text-align:center;color:var(--text-muted)">ยังไม่มีสมรรถนะ</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>

<?php if ($current): ?>
<div class="card" style="margin-bottom:1rem">
  <h3>คำอธิบายระดับ — <?= htmlspecialchars($current['name']) ?></h3>
  <?php $levelMap = []; foreach ($levels as $row) $levelMap[(int)$row['expected_level']] = $row['level_description']; ?>
  <div class="table-wrap"><table>
    <thead><tr><th style="width:100px">ระดับ</th><th>คำอธิบายระดับ</th><th style="width:130px">จัดการ</th></tr></thead>
    <tbody><?php for ($level = 1; $level <= 3; $level++): $levelForm = 'level-' . $level; ?>
      <tr><td style="text-align:center;font-weight:700">ระดับ <?= $level ?></td><td><textarea form="<?= $levelForm ?>" class="form-control" name="level_description" rows="2" required><?= htmlspecialchars($levelMap[$level] ?? '') ?></textarea></td><td>
        <form method="post" id="<?= $levelForm ?>"><?= adminCsrfField() ?><input type="hidden" name="action" value="save_level"><input type="hidden" name="competency_id" value="<?= $selectedId ?>"><input type="hidden" name="expected_level" value="<?= $level ?>"><button class="btn btn-secondary" type="submit"><?= appIcon('save') ?> บันทึก</button></form>
      </td></tr>
    <?php endfor; ?></tbody>
  </table></div>
</div>

<div class="card">
  <h3>พฤติกรรมบ่งชี้ — <?= htmlspecialchars($current['name']) ?></h3>
  <form method="post" class="admin-indicator-form" style="display:grid;grid-template-columns:110px minmax(200px,1fr) 90px minmax(320px,3fr) auto;gap:.7rem;align-items:end;padding:1rem;background:var(--primary-50);margin-bottom:1rem">
    <?= adminCsrfField() ?>
    <input type="hidden" name="action" value="save_indicator"><input type="hidden" name="id" value="0"><input type="hidden" name="competency_id" value="<?= $selectedId ?>">
    <div class="form-group" style="margin:0"><label>ระดับ</label><select class="form-control" name="expected_level"><?php for ($level=1;$level<=3;$level++): ?><option value="<?= $level ?>">ระดับ <?= $level ?></option><?php endfor; ?></select></div>
    <div class="form-group" style="margin:0"><label>ตำแหน่ง</label><select class="form-control" name="position_id"><option value="0">ใช้ทุกตำแหน่ง</option><?php foreach ($positions as $position): ?><option value="<?= (int)$position['id'] ?>"><?= htmlspecialchars($position['name']) ?></option><?php endforeach; ?></select></div>
    <div class="form-group" style="margin:0"><label>ลำดับ</label><input class="form-control" type="number" min="1" name="order_seq" value="1"></div>
    <div class="form-group" style="margin:0"><label>ข้อความพฤติกรรมบ่งชี้</label><textarea class="form-control" name="indicator_text" rows="2" required></textarea></div>
    <button class="btn btn-primary" type="submit"><?= appIcon('plus') ?> เพิ่ม</button>
  </form>

  <div class="table-wrap"><table>
    <thead><tr><th style="width:100px">ระดับ</th><th style="min-width:210px">ตำแหน่ง</th><th style="width:80px">ลำดับ</th><th style="min-width:380px">พฤติกรรมบ่งชี้</th><th style="width:190px">จัดการ</th></tr></thead>
    <tbody>
    <?php foreach ($indicators as $indicator): $indicatorForm = 'indicator-' . (int)$indicator['id']; ?>
      <tr>
        <td><select form="<?= $indicatorForm ?>" class="form-control" name="expected_level"><?php for ($level=1;$level<=3;$level++): ?><option value="<?= $level ?>" <?= (int)$indicator['expected_level'] === $level ? 'selected' : '' ?>>ระดับ <?= $level ?></option><?php endfor; ?></select></td>
        <td><select form="<?= $indicatorForm ?>" class="form-control" name="position_id"><option value="0">ใช้ทุกตำแหน่ง</option><?php foreach ($positions as $position): ?><option value="<?= (int)$position['id'] ?>" <?= (int)$indicator['position_id'] === (int)$position['id'] ? 'selected' : '' ?>><?= htmlspecialchars($position['name']) ?></option><?php endforeach; ?></select></td>
        <td><input form="<?= $indicatorForm ?>" class="form-control" type="number" min="1" name="order_seq" value="<?= (int)$indicator['order_seq'] ?>"></td>
        <td><textarea form="<?= $indicatorForm ?>" class="form-control" name="indicator_text" rows="2" required><?= htmlspecialchars($indicator['indicator_text']) ?></textarea></td>
        <td><div style="display:flex;gap:.4rem">
          <form method="post" id="<?= $indicatorForm ?>"><?= adminCsrfField() ?><input type="hidden" name="action" value="save_indicator"><input type="hidden" name="id" value="<?= (int)$indicator['id'] ?>"><input type="hidden" name="competency_id" value="<?= $selectedId ?>"><button class="btn btn-secondary" type="submit"><?= appIcon('save') ?> บันทึก</button></form>
          <form method="post" onsubmit="return confirm('ยืนยันการลบพฤติกรรมบ่งชี้นี้?')"><?= adminCsrfField() ?><input type="hidden" name="action" value="delete_indicator"><input type="hidden" name="id" value="<?= (int)$indicator['id'] ?>"><input type="hidden" name="competency_id" value="<?= $selectedId ?>"><button class="btn btn-danger" type="submit" <?= (int)$indicator['score_count'] > 0 ? 'disabled title="มีคะแนนประเมินอ้างอิงอยู่"' : '' ?>><?= appIcon('x-circle') ?> ลบ</button></form>
        </div></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$indicators): ?><tr><td colspan="5" style="text-align:center;color:var(--text-muted)">ยังไม่มีพฤติกรรมบ่งชี้</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
