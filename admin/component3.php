<?php
require_once '_bootstrap.php';
require_once __DIR__ . '/../includes/component3_helper.php';

$cycles = $pdo->query('SELECT * FROM evaluation_cycles ORDER BY fiscal_year DESC,start_date DESC,id DESC')->fetchAll();
$requestMethod = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
$cycleInput = $requestMethod === 'POST' ? ($_POST['cycle_id'] ?? null) : ($_GET['cycle_id'] ?? null);
$cycleId = requestInt($cycleInput, 'cycle_id', (int)($cycles[0]['id'] ?? 0));
if ($cycleId > 0) component3EnsureCycleItems($pdo, $cycleId);

if ($requestMethod === 'POST') {
    try {
        verifyAdminCsrf();
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save') {
            $id = requestInt($_POST['id'] ?? null, 'id', 0, 0);
            $cycleId = requestInt($_POST['cycle_id'] ?? null, 'cycle_id');
            $itemNo = requestInt($_POST['item_no'] ?? null, 'item_no');
            $name = trim((string)($_POST['name'] ?? ''));
            $weight = round((float)($_POST['weight'] ?? 0), 2);
            $targetValue = ($_POST['target_value'] ?? '') === '' ? null : round((float)$_POST['target_value'], 4);
            $targetLabel = trim((string)($_POST['target_label'] ?? '')) ?: null;
            $inputType = (string)($_POST['input_type'] ?? 'count');
            $audience = (string)($_POST['audience'] ?? 'all');
            $responsible = trim((string)($_POST['responsible'] ?? '')) ?: null;
            $active = isset($_POST['is_active']) ? 1 : 0;
            if ($itemNo < 1 || $itemNo > 999 || $name === '' || mb_strlen($name) > 1000) throw new RuntimeException('กรุณากรอกข้อและชื่อตัวชี้วัดให้ถูกต้อง');
            if ($weight <= 0 || $weight > 100) throw new RuntimeException('น้ำหนักต้องมากกว่า 0 และไม่เกิน 100');
            if (!in_array($inputType, ['count','percentage','department_score'], true)) throw new RuntimeException('รูปแบบการกรอกคะแนนไม่ถูกต้อง');
            if (!in_array($audience, ['all','sso_director'], true)) throw new RuntimeException('กลุ่มผู้รับการประเมินไม่ถูกต้อง');
            $thresholds = [];
            for ($level=1; $level<=5; $level++) {
                $raw = $_POST['score_'.$level] ?? '';
                $thresholds[$level] = $raw === '' ? null : round((float)$raw, 4);
                if ($thresholds[$level] !== null && $thresholds[$level] < 0) throw new RuntimeException('เกณฑ์คะแนนต้องไม่ติดลบ');
            }
            if ($inputType === 'department_score') {
                $targetValue = null; $thresholds = [1=>null,2=>null,3=>null,4=>null,5=>null];
                $targetLabel = $targetLabel ?: 'คะแนนตามหน่วยบริการ';
            } else {
                if ($targetValue === null || $targetValue <= 0) throw new RuntimeException('กรุณากรอกค่าเป้าหมายที่มากกว่า 0');
                if (count(array_filter($thresholds, static fn($value) => $value !== null)) === 0) throw new RuntimeException('กรุณากรอกเกณฑ์คะแนนอย่างน้อย 1 ระดับ');
                $previous = null;
                foreach ($thresholds as $value) if ($value !== null) { if ($previous !== null && $value < $previous) throw new RuntimeException('เกณฑ์คะแนนต้องเรียงจากน้อยไปมากตามระดับคะแนน'); $previous = $value; }
            }
            $params = [$cycleId,$itemNo,$name,$weight,$targetValue,$targetLabel,$inputType,$audience,$responsible,...array_values($thresholds),$active];
            if ($id > 0) {
                $currentStmt = $pdo->prepare('SELECT i.*,(SELECT COUNT(*) FROM component3_scores s JOIN component3_assessments a ON a.id=s.assessment_id WHERE a.cycle_id=i.cycle_id AND s.item_no=i.item_no) score_count FROM component3_items i WHERE i.id=? AND i.cycle_id=? LIMIT 1');
                $currentStmt->execute([$id,$cycleId]);
                $current = $currentStmt->fetch();
                if (!$current) throw new RuntimeException('ไม่พบรายการที่ต้องการแก้ไข');
                if ((int)$current['score_count'] > 0) {
                    $criticalOld = [(int)$current['item_no'],(float)$current['weight'],$current['target_value']===null?null:(float)$current['target_value'],$current['input_type'],$current['audience'],(int)$current['is_active']];
                    $criticalNew = [$itemNo,$weight,$targetValue,$inputType,$audience,$active];
                    for ($level=1;$level<=5;$level++) { $criticalOld[]=$current['score_'.$level.'_threshold']===null?null:(float)$current['score_'.$level.'_threshold']; $criticalNew[]=$thresholds[$level]; }
                    if ($criticalOld !== $criticalNew) throw new RuntimeException('รายการนี้มีผลประเมินแล้ว แก้ไขได้เฉพาะชื่อ ข้อความเป้าหมาย และผู้รับผิดชอบ เพื่อไม่ให้คะแนนเดิมเปลี่ยน');
                }
                $stmt = $pdo->prepare('UPDATE component3_items SET item_no=?,name=?,weight=?,target_value=?,target_label=?,input_type=?,audience=?,responsible=?,score_1_threshold=?,score_2_threshold=?,score_3_threshold=?,score_4_threshold=?,score_5_threshold=?,is_active=? WHERE id=? AND cycle_id=?');
                $stmt->execute([$itemNo,$name,$weight,$targetValue,$targetLabel,$inputType,$audience,$responsible,...array_values($thresholds),$active,$id,$cycleId]);
                if ($stmt->rowCount() === 0) {
                    $exists = $pdo->prepare('SELECT 1 FROM component3_items WHERE id=? AND cycle_id=?'); $exists->execute([$id,$cycleId]);
                    if (!$exists->fetchColumn()) throw new RuntimeException('ไม่พบรายการที่ต้องการแก้ไข');
                }
            } else {
                $assessmentCount = $pdo->prepare('SELECT COUNT(*) FROM component3_assessments WHERE cycle_id=?');
                $assessmentCount->execute([$cycleId]);
                if ((int)$assessmentCount->fetchColumn() > 0) throw new RuntimeException('รอบนี้เริ่มมีการประเมินแล้ว ไม่สามารถเพิ่มข้อใหม่ได้ เพราะจะทำให้คะแนนรวมเดิมเปลี่ยน');
                $stmt = $pdo->prepare('INSERT INTO component3_items(cycle_id,item_no,name,weight,target_value,target_label,input_type,audience,responsible,score_1_threshold,score_2_threshold,score_3_threshold,score_4_threshold,score_5_threshold,is_active) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute($params);
            }
            adminRedirect('component3.php?cycle_id='.$cycleId, 'success', 'บันทึกองค์ประกอบที่ 3 เรียบร้อยแล้ว');
        }
        if ($action === 'delete') {
            $id = requestInt($_POST['id'] ?? null, 'id');
            $cycleId = requestInt($_POST['cycle_id'] ?? null, 'cycle_id');
            $stmt = $pdo->prepare('SELECT item_no FROM component3_items WHERE id=? AND cycle_id=? LIMIT 1'); $stmt->execute([$id,$cycleId]);
            $itemNo = $stmt->fetchColumn();
            if ($itemNo === false) throw new RuntimeException('ไม่พบรายการที่ต้องการลบ');
            $used = $pdo->prepare('SELECT COUNT(*) FROM component3_scores s JOIN component3_assessments a ON a.id=s.assessment_id WHERE a.cycle_id=? AND s.item_no=?');
            $used->execute([$cycleId,(int)$itemNo]);
            if ((int)$used->fetchColumn() > 0) throw new RuntimeException('ไม่สามารถลบรายการที่มีผลประเมินแล้วได้ สามารถปิดใช้งานแทน');
            $pdo->prepare('DELETE FROM component3_items WHERE id=? AND cycle_id=?')->execute([$id,$cycleId]);
            adminRedirect('component3.php?cycle_id='.$cycleId, 'success', 'ลบรายการเรียบร้อยแล้ว');
        }
        throw new RuntimeException('คำสั่งไม่ถูกต้อง');
    } catch (PDOException $e) {
        error_log('Admin component 3 failed: '.$e->getMessage());
        $message = (string)$e->getCode() === '23000' ? 'เลขข้อซ้ำกับรายการที่มีอยู่ในรอบนี้' : 'ไม่สามารถบันทึกข้อมูลได้ชั่วคราว';
        adminRedirect('component3.php?cycle_id='.$cycleId, 'error', $message);
    } catch (Throwable $e) {
        adminRedirect('component3.php?cycle_id='.$cycleId, 'error', $e->getMessage());
    }
}

$stmt = $pdo->prepare('SELECT i.*,(SELECT COUNT(*) FROM component3_scores s JOIN component3_assessments a ON a.id=s.assessment_id WHERE a.cycle_id=i.cycle_id AND s.item_no=i.item_no) score_count FROM component3_items i WHERE i.cycle_id=? ORDER BY i.item_no,i.id');
$stmt->execute([$cycleId]); $items = $stmt->fetchAll();
$editId = requestInt($_GET['edit'] ?? null, 'edit', 0, 0); $editing = null;
foreach ($items as $item) if ((int)$item['id'] === $editId) $editing = $item;
$form = $editing ?: ['id'=>0,'item_no'=>(count($items) ? max(array_column($items,'item_no'))+1 : 1),'name'=>'','weight'=>'','target_value'=>'','target_label'=>'','input_type'=>'count','audience'=>'all','responsible'=>'','is_active'=>1,'score_1_threshold'=>'','score_2_threshold'=>'','score_3_threshold'=>'','score_4_threshold'=>'','score_5_threshold'=>''];
$totalAll = 0; $totalGeneral = 0;
foreach ($items as $item) if ((int)$item['is_active']) { $totalAll += (float)$item['weight']; if ($item['audience']==='all') $totalGeneral += (float)$item['weight']; }

require_once '../includes/header.php';
adminPageHeader('จัดการองค์ประกอบที่ 3', 'เพิ่ม ลบ แก้ไข เป้าหมาย น้ำหนัก เกณฑ์คะแนน และกลุ่มผู้รับการประเมินในแต่ละรอบ');
renderAdminFlash();
?>
<div class="card c3-admin-filter"><form method="get"><div class="form-group"><label>รอบการประเมิน</label><select class="form-control" name="cycle_id" onchange="this.form.submit()"><?php foreach($cycles as $cycle): ?><option value="<?= (int)$cycle['id'] ?>" <?= $cycleId===(int)$cycle['id']?'selected':'' ?>><?= htmlspecialchars(component3CycleLabel($cycle)) ?> (<?= $cycle['status']==='active'?'เปิด':'ปิด' ?>)</option><?php endforeach; ?></select></div><div class="c3-weight"><span>น้ำหนัก สสอ./ผอ.รพ.สต. <b><?= number_format($totalAll,2) ?>%</b></span><span>บุคลากร รพ.สต. <b><?= number_format($totalGeneral,2) ?>%</b></span></div></form></div>

<div class="card c3-admin-form-card">
  <div class="c3-admin-form-title"><span><?= appIcon($editing?'edit':'plus') ?></span><div><h3><?= $editing?'แก้ไของค์ประกอบที่ 3':'เพิ่มองค์ประกอบที่ 3' ?></h3><p>กำหนดรายละเอียดและเกณฑ์คะแนน รายการจะเรียงตามเลขข้อ</p></div></div>
  <form method="post" class="c3-admin-form">
    <?= adminCsrfField() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$form['id'] ?>"><input type="hidden" name="cycle_id" value="<?= $cycleId ?>">
    <div class="c3-grid c3-grid-main"><div class="form-group"><label>ข้อ <span class="required-mark">*</span></label><input class="form-control" type="number" min="1" max="999" name="item_no" value="<?= (int)$form['item_no'] ?>" required></div><div class="form-group c3-wide"><label>ชื่อตัวชี้วัด <span class="required-mark">*</span></label><textarea class="form-control" name="name" rows="3" maxlength="1000" required><?= htmlspecialchars($form['name']) ?></textarea></div><label class="c3-toggle"><input type="checkbox" name="is_active" <?= $form['is_active']?'checked':'' ?>><span><strong>เปิดใช้งาน</strong><small>แสดงในแบบประเมิน</small></span></label></div>
    <div class="c3-grid"><div class="form-group"><label>น้ำหนัก (%)</label><input class="form-control" type="number" min="0.01" max="100" step="0.01" name="weight" value="<?= htmlspecialchars((string)$form['weight']) ?>" required></div><div class="form-group"><label>ค่าเป้าหมาย</label><input class="form-control" type="number" min="0" step="0.0001" name="target_value" value="<?= htmlspecialchars((string)($form['target_value']??'')) ?>"></div><div class="form-group"><label>ข้อความเป้าหมาย</label><input class="form-control" name="target_label" value="<?= htmlspecialchars((string)($form['target_label']??'')) ?>" placeholder="เช่น 5 ครั้ง"></div><div class="form-group"><label>รูปแบบการกรอก</label><select class="form-control" name="input_type" id="c3InputType"><option value="count" <?= $form['input_type']==='count'?'selected':'' ?>>จำนวนเต็ม</option><option value="percentage" <?= $form['input_type']==='percentage'?'selected':'' ?>>ร้อยละ</option><option value="department_score" <?= $form['input_type']==='department_score'?'selected':'' ?>>คะแนนตามหน่วยบริการอัตโนมัติ</option></select></div><div class="form-group"><label>กลุ่มผู้รับการประเมิน</label><select class="form-control" name="audience"><option value="all" <?= $form['audience']==='all'?'selected':'' ?>>บุคลากรทุกคน</option><option value="sso_director" <?= $form['audience']==='sso_director'?'selected':'' ?>>บุคลากร สสอ. และ ผอ.รพ.สต.</option></select></div><div class="form-group"><label>ผู้รับผิดชอบ</label><input class="form-control" name="responsible" value="<?= htmlspecialchars((string)($form['responsible']??'')) ?>"></div></div>
    <div class="c3-rules" id="c3Rules"><h4>เกณฑ์ผลการดำเนินงานสำหรับคะแนน 1–5</h4><div class="c3-rule-grid"><?php for($level=1;$level<=5;$level++): ?><label><span>คะแนน <?= $level ?></span><input class="form-control" type="number" min="0" step="0.0001" name="score_<?= $level ?>" value="<?= htmlspecialchars((string)($form['score_'.$level.'_threshold']??'')) ?>" placeholder="เว้นว่างได้"></label><?php endfor; ?></div><small>เกณฑ์ต้องเรียงจากน้อยไปมาก เว้นระดับที่ไม่ใช้ได้ เช่น ข้อ 4 ใช้เฉพาะคะแนน 1 และ 5</small></div>
    <div class="c3-actions"><?php if($editing): ?><a class="btn btn-secondary" href="component3.php?cycle_id=<?= $cycleId ?>">ยกเลิกแก้ไข</a><?php endif; ?><button class="btn btn-primary" type="submit"><?= appIcon('save') ?> <?= $editing?'บันทึกการแก้ไข':'เพิ่มรายการ' ?></button></div>
  </form>
</div>

<div class="card c3-admin-list"><h3>รายการองค์ประกอบที่ 3</h3><div class="table-wrap"><table><thead><tr><th>ข้อ</th><th>ตัวชี้วัด</th><th>กลุ่ม</th><th>เป้าหมาย</th><th>น้ำหนัก</th><th>เกณฑ์คะแนน 1–5</th><th>สถานะ</th><th>จัดการ</th></tr></thead><tbody><?php foreach($items as $item): ?><tr><td class="center"><b><?= (int)$item['item_no'] ?></b></td><td style="min-width:260px"><strong><?= htmlspecialchars($item['name']) ?></strong><small class="block-muted"><?= htmlspecialchars($item['responsible']?:'-') ?></small></td><td><?= $item['audience']==='all'?'ทุกคน':'สสอ./ผอ.รพ.สต.' ?></td><td><?= htmlspecialchars($item['target_label']?:'-') ?><small class="block-muted"><?= $item['input_type']==='department_score'?'อัตโนมัติ':($item['target_value']!==null?number_format((float)$item['target_value'],2):'-') ?></small></td><td class="center"><?= number_format((float)$item['weight'],2) ?>%</td><td><div class="c3-rule-summary"><?php for($level=1;$level<=5;$level++): ?><span><b><?= $level ?></b><?= $item['score_'.$level.'_threshold']!==null?number_format((float)$item['score_'.$level.'_threshold'],2):'-' ?></span><?php endfor; ?></div></td><td><span class="badge <?= $item['is_active']?'badge-success':'badge-primary' ?>"><?= $item['is_active']?'ใช้งาน':'ปิดใช้งาน' ?></span><small class="block-muted"><?= (int)$item['score_count'] ?> ผลประเมิน</small></td><td><div class="c3-row-actions"><a class="btn btn-sm btn-secondary" href="component3.php?cycle_id=<?= $cycleId ?>&edit=<?= (int)$item['id'] ?>"><?= appIcon('edit') ?> แก้ไข</a><form method="post" onsubmit="return confirm('ยืนยันการลบรายการนี้?')"><?= adminCsrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="cycle_id" value="<?= $cycleId ?>"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="btn btn-sm btn-danger" type="submit" <?= (int)$item['score_count']>0?'disabled title="มีผลประเมินแล้ว"':'' ?>><?= appIcon('x-circle') ?> ลบ</button></form></div></td></tr><?php endforeach; ?><?php if(!$items): ?><tr><td colspan="8" class="empty">ยังไม่มีรายการในรอบนี้</td></tr><?php endif; ?></tbody></table></div></div>
<style>.c3-admin-filter,.c3-admin-form-card{margin-bottom:1rem}.c3-admin-filter form{display:flex;gap:1rem;align-items:end;justify-content:space-between;flex-wrap:wrap}.c3-admin-filter .form-group{margin:0;min-width:300px}.c3-weight{display:flex;gap:.6rem;flex-wrap:wrap}.c3-weight span{background:var(--primary-50);padding:.65rem .85rem;border-radius:9px}.c3-admin-form-title{display:flex;gap:.8rem;align-items:center;margin-bottom:1rem}.c3-admin-form-title>span{width:44px;height:44px;display:grid;place-items:center;border-radius:12px;background:var(--primary-color);color:#fff}.c3-admin-form-title h3,.c3-admin-form-title p{margin:0}.c3-admin-form-title p{color:var(--text-muted);margin-top:.25rem}.c3-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.8rem}.c3-grid-main{grid-template-columns:100px minmax(0,1fr) 170px}.c3-wide{grid-column:auto}.c3-toggle{display:flex;align-items:center;gap:.6rem;padding:.75rem;border:1px solid var(--border-color);border-radius:9px;height:max-content}.c3-toggle span,.c3-toggle small{display:block}.c3-toggle small,.c3-rules small,.block-muted{color:var(--text-muted)}.c3-rules{padding:1rem;background:#f8fafc;border:1px solid var(--border-color);border-radius:10px;margin-top:.5rem}.c3-rules h4{margin:0 0 .7rem}.c3-rule-grid{display:grid;grid-template-columns:repeat(5,minmax(90px,1fr));gap:.6rem;margin-bottom:.5rem}.c3-rule-grid label span{display:block;font-weight:700;margin-bottom:.3rem}.c3-actions{display:flex;justify-content:flex-end;gap:.6rem;margin-top:1rem}.c3-admin-list{padding:0!important}.c3-admin-list>h3{padding:1rem 1rem 0}.center{text-align:center}.block-muted{display:block;margin-top:.25rem}.c3-rule-summary{display:grid;grid-template-columns:repeat(5,minmax(42px,1fr));gap:.25rem;min-width:250px}.c3-rule-summary span{text-align:center;border:1px solid var(--border-color);border-radius:7px;padding:.25rem}.c3-rule-summary b{display:block;color:var(--primary-color)}.c3-row-actions{display:flex;gap:.4rem;flex-wrap:wrap;min-width:180px}.c3-row-actions form{margin:0}.empty{text-align:center;color:var(--text-muted);padding:2rem!important}@media(max-width:900px){.c3-grid,.c3-grid-main{grid-template-columns:1fr 1fr}.c3-wide{grid-column:1/-1}.c3-rule-grid{grid-template-columns:repeat(3,1fr)}}@media(max-width:560px){.c3-grid,.c3-grid-main,.c3-rule-grid{grid-template-columns:1fr}}</style>
<script>(()=>{const type=document.getElementById('c3InputType'),rules=document.getElementById('c3Rules');function toggle(){rules.style.opacity=type.value==='department_score'?'.5':'1';rules.querySelectorAll('input').forEach(input=>input.disabled=type.value==='department_score')}type.addEventListener('change',toggle);toggle()})();</script>
<?php require_once '../includes/footer.php'; ?>
