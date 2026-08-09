<?php
require_once '_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyAdminCsrf();
        $action = (string)($_POST['action'] ?? '');
        $id = requestInt($_POST['id'] ?? null, 'id');
        $name = trim((string)($_POST['name'] ?? ''));

        if (in_array($action, ['add_position', 'update_position', 'add_rank', 'update_rank'], true) && $name === '') {
            throw new RuntimeException('กรุณาระบุชื่อ');
        }

        if ($action === 'add_position') {
            $pdo->prepare('INSERT INTO positions (name) VALUES (?)')->execute([$name]);
            $message = 'เพิ่มสายงานเรียบร้อย';
        } elseif ($action === 'update_position') {
            $pdo->prepare('UPDATE positions SET name=? WHERE id=?')->execute([$name, $id]);
            $message = 'แก้ไขสายงานเรียบร้อย';
        } elseif ($action === 'delete_position') {
            $users = $pdo->prepare('SELECT COUNT(*) FROM users WHERE position_id=?');
            $users->execute([$id]);
            if ((int)$users->fetchColumn() > 0) {
                throw new RuntimeException('ไม่สามารถลบสายงานที่ยังมีบุคลากรใช้งานอยู่');
            }
            $scores = $pdo->prepare(
                'SELECT COUNT(*) FROM evaluation_scores es
                 JOIN indicators i ON i.id=es.indicator_id
                 WHERE i.position_id=?'
            );
            $scores->execute([$id]);
            if ((int)$scores->fetchColumn() > 0) {
                throw new RuntimeException('ไม่สามารถลบสายงานที่มีคะแนนประเมินอ้างอิงอยู่');
            }
            $pdo->prepare('DELETE FROM positions WHERE id=?')->execute([$id]);
            $message = 'ลบสายงานเรียบร้อย';
        } elseif ($action === 'add_rank') {
            $pdo->prepare('INSERT INTO ranks (name) VALUES (?)')->execute([$name]);
            $message = 'เพิ่มระดับตำแหน่งเรียบร้อย';
        } elseif ($action === 'update_rank') {
            $pdo->prepare('UPDATE ranks SET name=? WHERE id=?')->execute([$name, $id]);
            $message = 'แก้ไขระดับตำแหน่งเรียบร้อย';
        } elseif ($action === 'delete_rank') {
            $users = $pdo->prepare('SELECT COUNT(*) FROM users WHERE rank_id=?');
            $users->execute([$id]);
            if ((int)$users->fetchColumn() > 0) {
                throw new RuntimeException('ไม่สามารถลบระดับตำแหน่งที่ยังมีบุคลากรใช้งานอยู่');
            }
            $pdo->prepare('DELETE FROM ranks WHERE id=?')->execute([$id]);
            $message = 'ลบระดับตำแหน่งเรียบร้อย';
        } else {
            throw new RuntimeException('ไม่พบคำสั่งที่ต้องการ');
        }

        adminRedirect('positions.php', 'success', $message);
    } catch (Throwable $e) {
        adminRedirect('positions.php', 'error', $e->getMessage());
    }
}

$positions = $pdo->query(
    'SELECT p.*, COUNT(u.id) user_count
     FROM positions p LEFT JOIN users u ON u.position_id=p.id
     GROUP BY p.id ORDER BY p.name'
)->fetchAll();
$ranks = $pdo->query(
    'SELECT r.*, COUNT(u.id) user_count
     FROM ranks r LEFT JOIN users u ON u.rank_id=r.id
     GROUP BY r.id ORDER BY r.name'
)->fetchAll();

require_once '../includes/header.php';
adminPageHeader(appIcon('layers') . ' สายงานและระดับตำแหน่ง', 'เพิ่ม แก้ไข หรือลบรายการที่ไม่มีบุคลากรและคะแนนประเมินอ้างอิง');
renderAdminFlash();
?>
<div class="admin-split-layout" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:1.5rem">
<?php foreach ([['ตำแหน่ง/สายงาน', 'position', $positions], ['ระดับตำแหน่ง', 'rank', $ranks]] as [$title, $type, $items]): ?>
  <div class="card">
    <h3><?= htmlspecialchars($title) ?></h3>
    <form method="post" class="admin-inline-form" style="display:flex;gap:.5rem;margin-bottom:1rem">
      <?= adminCsrfField() ?>
      <input type="hidden" name="action" value="add_<?= $type ?>">
      <input class="form-control" type="text" name="name" placeholder="ชื่อ<?= htmlspecialchars($title) ?>" required>
      <button class="btn btn-primary" type="submit"><?= appIcon('plus') ?> เพิ่ม</button>
    </form>
    <div class="table-wrap">
      <table>
        <thead><tr><th>ชื่อ</th><th style="width:90px">บุคลากร</th><th style="width:190px">จัดการ</th></tr></thead>
        <tbody>
        <?php foreach ($items as $item): $formId = $type . '-' . (int)$item['id']; ?>
          <tr>
            <td><input form="<?= $formId ?>" class="form-control" type="text" name="name" value="<?= htmlspecialchars($item['name']) ?>" required></td>
            <td><?= (int)$item['user_count'] ?> คน</td>
            <td>
              <div style="display:flex;gap:.4rem;align-items:center">
                <form method="post" id="<?= $formId ?>">
                  <?= adminCsrfField() ?>
                  <input type="hidden" name="action" value="update_<?= $type ?>">
                  <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                  <button class="btn btn-secondary" type="submit"><?= appIcon('save') ?> บันทึก</button>
                </form>
                <form method="post" onsubmit="return confirm('ยืนยันการลบ <?= htmlspecialchars($item['name'], ENT_QUOTES) ?>?')">
                  <?= adminCsrfField() ?>
                  <input type="hidden" name="action" value="delete_<?= $type ?>">
                  <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                  <button class="btn btn-danger" type="submit" <?= (int)$item['user_count'] > 0 ? 'disabled title="มีบุคลากรใช้งานอยู่"' : '' ?>><?= appIcon('x-circle') ?> ลบ</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="3" style="text-align:center;color:var(--text-muted)">ยังไม่มีข้อมูล</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php require_once '../includes/footer.php'; ?>
