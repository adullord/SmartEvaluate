<?php
require_once '_bootstrap.php';
if($_SERVER['REQUEST_METHOD']==='POST'){
  try{
    verifyAdminCsrf();
    $id=requestInt($_POST['id']??null,'id');
    $pdo->beginTransaction();
    $stmt=$pdo->prepare("UPDATE evaluations SET status='draft',acknowledged_at=NULL WHERE id=? AND status<>'draft'"); $stmt->execute([$id]);
    if(!$stmt->rowCount()) throw new RuntimeException('ไม่พบแบบประเมินที่ปลดล็อกได้');
    $pdo->prepare("INSERT INTO evaluation_logs(evaluation_id,user_id,action) VALUES (?,?,'Unlocked by administrator')")->execute([$id,(int)$_SESSION['user_id']]);
    $pdo->commit(); adminRedirect('unlock.php','success','ปลดล็อกแบบประเมินเรียบร้อย ผู้ประเมินสามารถแก้ไขและส่งใหม่ได้');
  }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();adminRedirect('unlock.php','error',$e->getMessage());}
}
$rows=$pdo->query("SELECT e.*,ee.fullname evaluatee_name,er.fullname evaluator_name,d.name dept_name,c.fiscal_year,c.round_name FROM evaluations e JOIN users ee ON ee.id=e.evaluatee_id JOIN users er ON er.id=e.evaluator_id JOIN departments d ON d.id=ee.department_id JOIN evaluation_cycles c ON c.id=e.cycle_id WHERE e.status<>'draft' ORDER BY e.updated_at DESC")->fetchAll();
require_once '../includes/header.php'; adminPageHeader(appIcon('unlock') . ' ปลดล็อกแบบประเมิน','ส่งแบบประเมินที่ส่งหรือรับทราบแล้วกลับเป็นฉบับร่าง'); renderAdminFlash();
?>
<div class="card"><div style="overflow:auto"><table><thead><tr><th>ผู้รับการประเมิน</th><th>หน่วยงาน</th><th>ผู้ประเมิน</th><th>รอบ</th><th>สถานะ</th><th></th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= htmlspecialchars($r['evaluatee_name']) ?></td><td><?= htmlspecialchars($r['dept_name']) ?></td><td><?= htmlspecialchars($r['evaluator_name']) ?></td><td><?= htmlspecialchars($r['fiscal_year'].' '.$r['round_name']) ?></td><td><?= htmlspecialchars($r['status']) ?></td><td><form method="post" onsubmit="return confirm('ยืนยันปลดล็อกแบบประเมินนี้?')"><?= adminCsrfField() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-warning">ปลดล็อก</button></form></td></tr><?php endforeach ?><?php if(!$rows): ?><tr><td colspan="6" style="text-align:center;color:var(--text-muted)">ไม่มีแบบประเมินที่ต้องปลดล็อก</td></tr><?php endif ?></tbody></table></div></div>
<?php require_once '../includes/footer.php'; ?>
