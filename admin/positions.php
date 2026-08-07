<?php
require_once '_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $name = trim($_POST['name'] ?? '');
    try {
        if ($name === '') throw new RuntimeException('กรุณาระบุชื่อ');
        if ($action === 'add_position') $pdo->prepare('INSERT INTO positions (name) VALUES (?)')->execute([$name]);
        elseif ($action === 'update_position') $pdo->prepare('UPDATE positions SET name=? WHERE id=?')->execute([$name,(int)$_POST['id']]);
        elseif ($action === 'add_rank') $pdo->prepare('INSERT INTO ranks (name) VALUES (?)')->execute([$name]);
        elseif ($action === 'update_rank') $pdo->prepare('UPDATE ranks SET name=? WHERE id=?')->execute([$name,(int)$_POST['id']]);
        adminRedirect('positions.php', 'success', 'บันทึกข้อมูลเรียบร้อย');
    } catch (Throwable $e) { adminRedirect('positions.php', 'error', $e->getMessage()); }
}
$positions=$pdo->query('SELECT p.*,COUNT(u.id) user_count FROM positions p LEFT JOIN users u ON u.position_id=p.id GROUP BY p.id ORDER BY p.name')->fetchAll();
$ranks=$pdo->query('SELECT r.*,COUNT(u.id) user_count FROM ranks r LEFT JOIN users u ON u.rank_id=r.id GROUP BY r.id ORDER BY r.name')->fetchAll();
require_once '../includes/header.php'; adminPageHeader(appIcon('layers') . ' สายงานและระดับตำแหน่ง','เพิ่มและแก้ไขสายงานหรือลำดับระดับตำแหน่ง'); renderAdminFlash();
?>
<div class="admin-split-layout" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:1.5rem">
<?php foreach ([['ตำแหน่ง/สายงาน','position',$positions],['ระดับตำแหน่ง','rank',$ranks]] as [$title,$type,$items]): ?>
<div class="card"><h3><?= $title ?></h3><form method="post" class="admin-inline-form" style="display:flex;gap:.5rem;margin-bottom:1rem"><input type="hidden" name="action" value="add_<?= $type ?>"><input class="form-control" type="text" name="name" required><button class="btn btn-primary">เพิ่ม</button></form>
<div class="table-wrap"><table><thead><tr><th>ชื่อ</th><th>บุคลากร</th><th>บันทึก</th></tr></thead><tbody><?php foreach($items as $item): ?><?php $formId=$type.'-'.$item['id']; ?><tr><td><input form="<?= $formId ?>" class="form-control" type="text" name="name" value="<?= htmlspecialchars($item['name']) ?>" required></td><td><?= (int)$item['user_count'] ?></td><td><form method="post" id="<?= $formId ?>"><input type="hidden" name="action" value="update_<?= $type ?>"><input type="hidden" name="id" value="<?= $item['id'] ?>"><button class="btn btn-secondary">แก้ไข</button></form></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php endforeach; ?>
</div><?php require_once '../includes/footer.php'; ?>
