<?php
require_once '_bootstrap.php';
require_once 'evaluators_auto_helper.php';
$cycleInput = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['cycle_id'] ?? null) : ($_GET['cycle_id'] ?? null);
$cycleId = requestInt($cycleInput, 'cycle_id');
if (!$cycleId) $cycleId = (int)$pdo->query("SELECT id FROM evaluation_cycles ORDER BY status='active' DESC,id DESC LIMIT 1")->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyAdminCsrf();
        $action = $_POST['action'] ?? '';
        if ($action === 'assign') {
            $evaluateeId=requestInt($_POST['evaluatee_id']??null,'evaluatee_id'); $evaluatorId=requestInt($_POST['evaluator_id']??null,'evaluator_id');
            if (!$cycleId || !$evaluateeId || !$evaluatorId || $evaluateeId === $evaluatorId) throw new RuntimeException('กรุณาเลือกข้อมูลให้ครบ และผู้ประเมินต้องไม่ใช่บุคคลเดียวกับผู้รับการประเมิน');
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM evaluator_mapping WHERE cycle_id=? AND evaluatee_id=?')->execute([$cycleId,$evaluateeId]);
            $pdo->prepare('INSERT INTO evaluator_mapping (cycle_id,evaluatee_id,evaluator_id) VALUES (?,?,?)')->execute([$cycleId,$evaluateeId,$evaluatorId]);
            $pdo->commit();
            adminRedirect('evaluators.php?cycle_id='.$cycleId,'success','กำหนดผู้ประเมินเรียบร้อย');
        }
        if ($action === 'remove') {
            $id=requestInt($_POST['id']??null,'id');
            $stmt=$pdo->prepare('DELETE FROM evaluator_mapping WHERE id=? AND cycle_id=?');$stmt->execute([$id,$cycleId]);
            if(!$stmt->rowCount())throw new RuntimeException('ไม่พบรายการกำหนดผู้ประเมิน');
            adminRedirect('evaluators.php?cycle_id='.$cycleId,'success','ยกเลิกการกำหนดผู้ประเมินเรียบร้อย');
        }
        if ($action === 'auto_assign') {
            if (!$cycleId) throw new RuntimeException('กรุณาเลือกรอบการประเมิน');
            $result = autoAssignEvaluators($pdo, $cycleId);
            $message = 'ประมวลผลสำเร็จ: กำหนดผู้ประเมิน ' . $result['assigned'] . ' คน';
            if ($result['preserved']) $message .= ', คงผู้ประเมินเดิมสำหรับแบบประเมินที่เริ่มแล้ว ' . $result['preserved'] . ' คน';
            if ($result['skipped']) $message .= ', ข้าม ' . $result['skipped'] . ' คน';
            if ($result['warnings']) $message .= ' — ' . implode(' | ', array_slice($result['warnings'], 0, 5));
            adminRedirect('evaluators.php?cycle_id='.$cycleId,'success',$message);
        }
    } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); adminRedirect('evaluators.php?cycle_id='.$cycleId,'error',$e->getMessage()); }
}
$cycles=$pdo->query('SELECT * FROM evaluation_cycles ORDER BY id DESC')->fetchAll();
$people=$pdo->query("SELECT u.id,u.fullname,u.role,d.name dept_name FROM users u JOIN departments d ON d.id=u.department_id WHERE u.is_active=1 ORDER BY d.name,u.fullname")->fetchAll();
$evaluators=array_values(array_filter($people,fn($u)=>in_array($u['role'],['admin','ss_amphoe','director'],true)));
$stmt=$pdo->prepare('SELECT em.id,e.fullname evaluatee_name,e.role evaluatee_role,er.fullname evaluator_name,d.name dept_name FROM evaluator_mapping em JOIN users e ON e.id=em.evaluatee_id JOIN users er ON er.id=em.evaluator_id JOIN departments d ON d.id=e.department_id WHERE em.cycle_id=? ORDER BY d.name,e.fullname');
$stmt->execute([$cycleId]); $mappings=$stmt->fetchAll();
require_once '../includes/header.php'; adminPageHeader(appIcon('link') . ' กำหนดผู้ประเมิน','กำหนดผู้รับผิดชอบประเมินบุคลากรแต่ละคน แยกตามรอบการประเมิน'); renderAdminFlash();
?>
<div class="card" style="margin-bottom:1rem"><form method="get" class="admin-inline-form" style="display:flex;gap:.7rem;align-items:end;flex-wrap:wrap"><div class="form-group"><label>รอบการประเมิน</label><select class="form-control" name="cycle_id" onchange="this.form.submit()"><?php foreach($cycles as $c): ?><?php $roundLabel = ctype_digit((string)$c['round_name']) ? 'รอบที่ '.$c['round_name'] : $c['round_name']; ?><option value="<?= $c['id'] ?>" <?= $cycleId==$c['id']?'selected':'' ?>>ปี <?= htmlspecialchars($c['fiscal_year'].' '.$roundLabel) ?> (<?= $c['status']==='active'?'เปิด':'ปิด' ?>)</option><?php endforeach ?></select></div></form></div>
<div class="card" style="margin-bottom:1rem">
  <div style="display:flex;justify-content:space-between;gap:1rem;align-items:center;flex-wrap:wrap">
    <div>
      <h3 style="margin:0 0 .35rem"><?= appIcon('settings') ?> กำหนดผู้ประเมินอัตโนมัติ</h3>
      <p style="margin:0;color:var(--text-muted)">สสอ. ประเมินบุคลากรใน สสอ. และ ผอ.รพ.สต. ส่วน ผอ.รพ.สต. ประเมินบุคลากรในหน่วยงานตนเอง</p>
      <small style="color:var(--text-muted)">แบบประเมินที่เริ่มบันทึกแล้วจะคงผู้ประเมินเดิม</small>
    </div>
    <form method="post" onsubmit="return confirm('ยืนยันประมวลผลผู้ประเมินอัตโนมัติสำหรับรอบที่เลือก? การกำหนดเดิมที่ยังไม่เริ่มประเมินจะถูกแทนที่')">
      <?= adminCsrfField() ?>
      <input type="hidden" name="action" value="auto_assign">
      <input type="hidden" name="cycle_id" value="<?= $cycleId ?>">
      <button class="btn btn-success" type="submit" <?= !$cycleId ? 'disabled' : '' ?>><?= appIcon('settings') ?> ประมวลผล</button>
    </form>
  </div>
</div>
<div class="card" style="margin-bottom:1rem"><h3>เพิ่มหรือเปลี่ยนผู้ประเมิน</h3><form method="post" class="admin-form-grid" style="display:grid;grid-template-columns:1fr 1fr auto;gap:.7rem;align-items:end"><?= adminCsrfField() ?><input type="hidden" name="action" value="assign"><input type="hidden" name="cycle_id" value="<?= $cycleId ?>"><div class="form-group"><label>ผู้รับการประเมิน</label><select class="form-control" name="evaluatee_id" required><option value="">-- เลือก --</option><?php foreach($people as $u): ?><?php $personType = $u['role']==='director' ? 'ผอ.รพ.สต.' : ($u['role']==='ss_amphoe' ? 'สสอ.' : 'บุคลากร'); ?><option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['fullname'].' — '.$personType.' — '.$u['dept_name']) ?></option><?php endforeach ?></select></div><div class="form-group"><label>ผู้ประเมิน</label><select class="form-control" name="evaluator_id" required><option value="">-- เลือก --</option><?php foreach($evaluators as $u): ?><option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['fullname'].' — '.$u['dept_name']) ?></option><?php endforeach ?></select></div><button class="btn btn-primary">บันทึก</button></form></div>
<div class="card"><h3>รายชื่อที่กำหนดแล้ว (<?= count($mappings) ?> คน)</h3><div style="overflow:auto"><table><thead><tr><th>ผู้รับการประเมิน</th><th>ประเภท</th><th>หน่วยงาน</th><th>ผู้ประเมิน</th><th></th></tr></thead><tbody><?php foreach($mappings as $m): ?><tr><td><?= htmlspecialchars($m['evaluatee_name']) ?></td><td><?php if($m['evaluatee_role']==='director'): ?><span class="badge badge-primary">ผอ.รพ.สต.</span><?php else: ?><span class="badge badge-success">บุคลากร</span><?php endif ?></td><td><?= htmlspecialchars($m['dept_name']) ?></td><td><?= htmlspecialchars($m['evaluator_name']) ?></td><td><form method="post" onsubmit="return confirm('ยกเลิกการกำหนดผู้ประเมินรายการนี้?')"><?= adminCsrfField() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="cycle_id" value="<?= $cycleId ?>"><input type="hidden" name="id" value="<?= $m['id'] ?>"><button class="btn btn-danger">ยกเลิก</button></form></td></tr><?php endforeach ?><?php if(!$mappings): ?><tr><td colspan="5" style="text-align:center;color:var(--text-muted)">ยังไม่มีข้อมูลในรอบนี้</td></tr><?php endif ?></tbody></table></div></div>
<?php require_once '../includes/footer.php'; ?>
