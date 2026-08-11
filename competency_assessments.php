<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header('Location: '.appUrl('login.php')); exit; }

$evaluatorId=(int)$_SESSION['user_id']; $role=(string)$_SESSION['role']; $departmentId=(int)$_SESSION['department_id'];
$activeCycle=$pdo->query("SELECT * FROM evaluation_cycles WHERE status='active' ORDER BY id DESC LIMIT 1")->fetch();
$subordinates=[]; $doneCount=0;
if ($activeCycle) {
    $stmt=$pdo->prepare("SELECT u.id,u.fullname,p.name pos_name,r.name rank_name,d.name dept_name,e.id evaluation_id,e.status eval_status
        FROM evaluator_mapping em JOIN users u ON u.id=em.evaluatee_id JOIN positions p ON p.id=u.position_id
        JOIN ranks r ON r.id=u.rank_id JOIN departments d ON d.id=u.department_id
        LEFT JOIN evaluations e ON e.evaluatee_id=u.id AND e.evaluator_id=em.evaluator_id AND e.cycle_id=em.cycle_id
        WHERE em.evaluator_id=? AND em.cycle_id=? AND u.is_active=1 AND (
          ?='admin' OR (?='ss_amphoe' AND ((u.role='staff' AND u.department_id=?) OR u.role='director'))
          OR (?='director' AND u.role='staff' AND u.department_id=?)) ORDER BY d.id,p.id,r.id,u.fullname");
    $stmt->execute([$evaluatorId,(int)$activeCycle['id'],$role,$role,$departmentId,$role,$departmentId]);
    $subordinates=$stmt->fetchAll();
    foreach($subordinates as $person) if(in_array($person['eval_status'],['submitted','acknowledged'],true)) $doneCount++;
}
$pendingCount=count($subordinates)-$doneCount;
require_once 'includes/header.php';
?>
<div class="card competency-list-heading"><div class="card-header"><div><h2 class="card-title"><?= appIcon('clipboard-list') ?> รายชื่อผู้ที่ต้องประเมิน</h2><p class="card-subtitle">องค์ประกอบที่ 1 สมรรถนะ — เลือกบุคลากรเพื่อทำแบบประเมินประจำรอบ</p></div><?php if($activeCycle): ?><span class="badge badge-primary"><?= htmlspecialchars($activeCycle['round_name']) ?></span><?php endif; ?></div></div>
<?php if($activeCycle): ?><div class="competency-summary"><div class="card"><strong><?= count($subordinates) ?></strong><span>รายชื่อทั้งหมด</span></div><div class="card"><strong><?= $doneCount ?></strong><span>ประเมินแล้ว</span></div><div class="card"><strong><?= $pendingCount ?></strong><span>รอประเมิน</span></div></div><?php endif; ?>
<div class="card competency-list-card">
<?php if(!$activeCycle): ?><div class="empty-state"><span class="empty-state-icon"><?= appIcon('ban') ?></span><h3>ยังไม่มีรอบประเมินที่เปิดใช้งาน</h3><p>กรุณาติดต่อผู้ดูแลระบบ</p></div>
<?php elseif(!$subordinates): ?><div class="empty-state"><span class="empty-state-icon"><?= appIcon('inbox') ?></span><h3>ไม่มีรายชื่อผู้ที่ต้องประเมิน</h3><p>ระบบยังไม่ได้กำหนดผู้รับการประเมินให้คุณในรอบนี้</p></div>
<?php else: ?><div class="table-wrap"><table><thead><tr><th>หน่วยงาน</th><th>ชื่อ - นามสกุล</th><th>ตำแหน่ง / ระดับ</th><th class="center">สถานะ</th><th class="center">ดำเนินการ</th></tr></thead><tbody><?php foreach($subordinates as $person): ?><tr><td><?= htmlspecialchars($person['dept_name']) ?></td><td><strong><?= htmlspecialchars($person['fullname']) ?></strong></td><td><span class="badge badge-primary"><?= htmlspecialchars($person['pos_name']) ?></span> <small class="muted"><?= htmlspecialchars($person['rank_name']) ?></small></td><td class="center"><?php if($person['eval_status']==='acknowledged'): ?><span class="status-done"><?= appIcon('check-circle') ?> รับทราบแล้ว</span><?php elseif($person['eval_status']==='submitted'): ?><span class="status-done"><?= appIcon('check-circle') ?> ประเมินแล้ว</span><?php elseif($person['eval_status']==='draft'): ?><span class="status-pending"><?= appIcon('triangle-alert') ?> ฉบับร่าง</span><?php else: ?><span class="status-pending">รอประเมิน</span><?php endif; ?></td><td class="center"><?php if($person['eval_status']==='acknowledged'): ?><a href="<?= htmlspecialchars(appUrl('report_detail.php')) ?>?id=<?= (int)$person['evaluation_id'] ?>" class="btn btn-sm btn-secondary"><?= appIcon('eye') ?> ดูผล</a><?php else: ?><a href="<?= htmlspecialchars(appUrl('assessment.php')) ?>?evaluatee_id=<?= (int)$person['id'] ?>&cycle_id=<?= (int)$activeCycle['id'] ?>" class="btn btn-sm <?= $person['evaluation_id']?'btn-success':'btn-primary' ?>"><?= $person['evaluation_id']?appIcon('edit').' ทำต่อ':appIcon('clipboard-list').' เริ่มประเมิน' ?></a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</div>
<style>.competency-list-heading{margin-bottom:1rem}.competency-list-heading .card-header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap}.competency-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem;margin-bottom:1rem}.competency-summary .card{text-align:center;padding:1rem}.competency-summary strong,.competency-summary span{display:block}.competency-summary strong{font-size:1.8rem;color:var(--primary-color)}.competency-summary span,.muted{color:var(--text-muted)}.competency-list-card{padding:0!important}.center{text-align:center}@media(max-width:650px){.competency-summary{grid-template-columns:1fr}}
</style>
<?php require_once 'includes/footer.php'; ?>
