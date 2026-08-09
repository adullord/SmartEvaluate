<?php
require_once '_bootstrap.php';
$positionInput=$_SERVER['REQUEST_METHOD']==='POST'?($_POST['position_id']??null):($_GET['position_id']??null);
$levelInput=$_SERVER['REQUEST_METHOD']==='POST'?($_POST['expected_level']??null):($_GET['expected_level']??null);
$positionId=requestInt($positionInput,'position_id'); $level=requestInt($levelInput,'expected_level',1,1,3);
$positions=$pdo->query('SELECT * FROM positions ORDER BY name')->fetchAll();
if(!$positionId && $positions) $positionId=(int)$positions[0]['id'];
if($_SERVER['REQUEST_METHOD']==='POST'){
  try{
    verifyAdminCsrf();
    if(!is_array($_POST['selected']??null)||!is_array($_POST['weight']??null))throw new RuntimeException('รูปแบบข้อมูลไม่ถูกต้อง');
    $selected=array_values(array_unique(array_map(fn($id)=>requestInt($id,'competency_id'),$_POST['selected']))); $weights=$_POST['weight'];
    if(!$selected) throw new RuntimeException('กรุณาเลือกสมรรถนะอย่างน้อย 1 รายการ');
    $total=0; foreach($selected as $id)$total+=(float)($weights[$id]??0);
    if(abs($total-100)>0.001) throw new RuntimeException('น้ำหนักรวมต้องเท่ากับ 100% (ปัจจุบัน '.number_format($total,2).'%)');
    $placeholders=implode(',',array_fill(0,count($selected),'?'));
    $oldStmt=$pdo->prepare('SELECT competency_id,level_description FROM evaluation_templates WHERE position_id=? AND expected_level=?');$oldStmt->execute([$positionId,$level]);$old=[];foreach($oldStmt as $o)$old[$o['competency_id']]=$o['level_description'];
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM evaluation_templates WHERE position_id=? AND expected_level=?')->execute([$positionId,$level]);
    $ins=$pdo->prepare('INSERT INTO evaluation_templates(position_id,expected_level,competency_id,weight,level_description) VALUES (?,?,?,?,?)');
    foreach($selected as $id)$ins->execute([$positionId,$level,$id,(float)$weights[$id],$old[$id]??null]);
    $pdo->commit(); adminRedirect('weights.php?position_id='.$positionId.'&expected_level='.$level,'success','บันทึกชุดสมรรถนะและน้ำหนักรวม 100% เรียบร้อย');
  }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();adminRedirect('weights.php?position_id='.$positionId.'&expected_level='.$level,'error',$e->getMessage());}
}
$stmt=$pdo->prepare('SELECT c.*,t.weight FROM competencies c LEFT JOIN evaluation_templates t ON t.competency_id=c.id AND t.position_id=? AND t.expected_level=? ORDER BY c.type,c.order_seq,c.id');$stmt->execute([$positionId,$level]);$items=$stmt->fetchAll();
require_once '../includes/header.php';adminPageHeader(appIcon('scale') . ' กำหนดน้ำหนักคะแนน','เลือกชุดสมรรถนะและกำหนดน้ำหนักตามสายงานและระดับที่คาดหวัง โดยรวมต้องเท่ากับ 100%');renderAdminFlash();
?>
<div class="card" style="margin-bottom:1rem"><form method="get" class="admin-inline-form" style="display:flex;gap:.8rem;align-items:end;flex-wrap:wrap"><div class="form-group"><label>สายงาน/ตำแหน่ง</label><select class="form-control" name="position_id"><?php foreach($positions as $p): ?><option value="<?= $p['id'] ?>" <?= $positionId==$p['id']?'selected':'' ?>><?= htmlspecialchars($p['name']) ?></option><?php endforeach ?></select></div><div class="form-group"><label>ระดับที่คาดหวัง</label><select class="form-control" name="expected_level"><?php for($i=1;$i<=3;$i++): ?><option value="<?= $i ?>" <?= $level==$i?'selected':'' ?>>ระดับ <?= $i ?></option><?php endfor ?></select></div><button class="btn btn-secondary">แสดงข้อมูล</button></form></div>
<form method="post" class="card"><?= adminCsrfField() ?><input type="hidden" name="position_id" value="<?= $positionId ?>"><input type="hidden" name="expected_level" value="<?= $level ?>"><div style="overflow:auto"><table><thead><tr><th style="width:80px">เลือก</th><th>สมรรถนะ</th><th>ประเภท</th><th style="width:180px">น้ำหนัก (%)</th></tr></thead><tbody><?php foreach($items as $x): ?><tr><td><input type="checkbox" name="selected[]" value="<?= $x['id'] ?>" <?= $x['weight']!==null?'checked':'' ?>></td><td><?= htmlspecialchars($x['name']) ?></td><td><?= $x['type']==='core'?'สมรรถนะหลัก':'สมรรถนะเฉพาะ' ?></td><td><input class="form-control weight-input" type="number" min="0" max="100" step="0.01" name="weight[<?= $x['id'] ?>]" value="<?= $x['weight']!==null?htmlspecialchars($x['weight']):0 ?>"></td></tr><?php endforeach ?></tbody><tfoot><tr><th colspan="3" style="text-align:right">รวม</th><th><span id="weightTotal">0.00</span>%</th></tr></tfoot></table></div><div style="margin-top:1rem;text-align:right"><button class="btn btn-primary">บันทึกน้ำหนัก</button></div></form>
<script>function sumWeights(){let n=0;document.querySelectorAll('input[name="selected[]"]:checked').forEach(c=>{n+=Number(c.closest('tr').querySelector('.weight-input').value)||0});document.getElementById('weightTotal').textContent=n.toFixed(2)}document.querySelectorAll('input').forEach(e=>e.addEventListener('input',sumWeights));sumWeights();</script>
<?php require_once '../includes/footer.php'; ?>
