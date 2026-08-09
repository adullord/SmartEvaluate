<?php
require_once 'config.php';
require_once 'csrf_helper.php';
require_once __DIR__ . '/includes/kpi_helper.php';

if (!isset($_SESSION['user_id'])) { header('Location: '.appUrl('login.php')); exit; }
$userId=(int)$_SESSION['user_id']; $role=(string)($_SESSION['role']??'staff');
kpiEnsureDirectorAssignments($pdo);
$userDepartment=kpiCurrentUserDepartment($pdo,$userId);
$canEnterResults=$role==='admin'||($userDepartment&&$userDepartment['type']==='SSO');
$cycles=$pdo->query('SELECT * FROM evaluation_cycles ORDER BY fiscal_year DESC,start_date DESC')->fetchAll();
$cycleInput=$_SERVER['REQUEST_METHOD']==='POST'?($_POST['cycle_id']??null):($_GET['cycle_id']??null);
$cycleId=requestInt($cycleInput,'cycle_id',(int)($cycles[0]['id']??0));
$cycle=null; foreach($cycles as $c) if((int)$c['id']===$cycleId) $cycle=$c;
$departments=kpiAllowedDepartments($pdo,$userId,$role);
$departmentIds=array_map(fn($d)=>(int)$d['id'],$departments);

if ($role === 'admin') {
    $indicatorStmt=$pdo->prepare("SELECT k.*,'admin' responsibility_type FROM kpi_indicators k WHERE k.cycle_id=? AND k.is_active=1 ORDER BY k.order_seq,k.id");
    $indicatorStmt->execute([$cycleId]);
} else {
    $indicatorStmt=$pdo->prepare('SELECT k.*,a.responsibility_type FROM kpi_indicators k JOIN kpi_assignments a ON a.indicator_id=k.id WHERE k.cycle_id=? AND k.is_active=1 AND a.user_id=? ORDER BY k.order_seq,k.id');
    $indicatorStmt->execute([$cycleId,$userId]);
}
$indicators=$indicatorStmt->fetchAll();
$indicatorMap=[]; foreach($indicators as $k) $indicatorMap[(int)$k['id']]=$k;

if($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if(!verify_csrf_token((string)($_POST['csrf_token']??''))) throw new RuntimeException('คำขอหมดอายุ กรุณาลองใหม่');
        if(($cycle['status']??'closed')!=='active') throw new RuntimeException('รอบการประเมินนี้ปิดแล้ว ไม่สามารถแก้ไขคะแนนได้');
        $action=isset($_POST['save_result_key'])?'save_result':(string)($_POST['action']??'');
        if($action==='save_settings') {
            $indicatorId=requestInt($_POST['indicator_id']??null,'indicator_id');
            if(!isset($indicatorMap[$indicatorId])) throw new RuntimeException('คุณไม่ได้รับมอบหมายตัวชี้วัดนี้');
            if($role!=='admin') throw new RuntimeException('เฉพาะแอดมินเท่านั้นที่แก้ไขค่าเป้าหมายและน้ำหนักได้');
            $targetLabel=trim((string)($_POST['target_label']??''))?:null;
            $unit=trim((string)($_POST['unit']??''))?:null;
            $weight=(float)($_POST['weight']??0);
            $targetValue=($_POST['target_value']??'')===''?null:round((float)$_POST['target_value'],2);
            if($weight<=0) throw new RuntimeException('ค่าน้ำหนักต้องมากกว่า 0');
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE kpi_indicators SET target_label=?,unit=?,weight=?,target_value=? WHERE id=? AND cycle_id=?')->execute([$targetLabel,$unit,$weight,$targetValue,$indicatorId,$cycleId]);
            $pdo->prepare('UPDATE kpi_results SET percentage=CASE WHEN ? IS NULL OR ?=0 THEN NULL ELSE actual_value/?*100 END, weighted_score=ROUND(score/5*?,2) WHERE indicator_id=?')->execute([$targetValue,$targetValue,$targetValue,$weight,$indicatorId]);
            $pdo->commit();
            $_SESSION['kpi_flash']=['type'=>'success','message'=>'บันทึกค่าเป้าหมาย ค่าน้ำหนัก และเป้าหมายเรียบร้อย'];
            header('Location: '.appUrl('kpi_results.php').'?cycle_id='.$cycleId); exit;
        }
        if(!in_array($action,['save_result','save_all_results'],true)) throw new RuntimeException('คำสั่งบันทึกไม่ถูกต้อง');
        if(!$canEnterResults) throw new RuntimeException('เฉพาะผู้รับผิดชอบตัวชี้วัด สสอ. และแอดมินเท่านั้นที่บันทึกผลและคะแนนได้');
        $actualValues=is_array($_POST['actual_value']??null)?$_POST['actual_value']:[];
        $scoreValues=is_array($_POST['score']??null)?$_POST['score']:[];
        $keys=[];
        if($action==='save_result') {
            $parts=explode(':',(string)$_POST['save_result_key'],2);
            if(count($parts)!==2)throw new RuntimeException('ข้อมูลแถวที่บันทึกไม่ถูกต้อง');
            $keys[]=[(int)$parts[0],(int)$parts[1]];
        } else {
            foreach($indicatorMap as $indicatorId=>$unused)foreach($departmentIds as $departmentId)$keys[]=[$indicatorId,$departmentId];
        }
        $rowsToSave=[];
        foreach($keys as [$indicatorId,$departmentId]) {
            if(!isset($indicatorMap[$indicatorId]))throw new RuntimeException('คุณไม่ได้รับมอบหมายตัวชี้วัดนี้');
            if(!in_array($departmentId,$departmentIds,true))throw new RuntimeException('ไม่มีสิทธิ์บันทึกผลของหน่วยบริการนี้');
            $actualRaw=trim((string)($actualValues[$indicatorId][$departmentId]??''));
            $scoreRaw=trim((string)($scoreValues[$indicatorId][$departmentId]??''));
            if($action==='save_all_results'&&$actualRaw===''&&$scoreRaw==='')continue;
            if($actualRaw===''||$scoreRaw==='')throw new RuntimeException('กรุณากรอกผลการดำเนินงานและค่าคะแนนให้ครบทุกช่องในแถวที่ต้องการบันทึก');
            $actual=round((float)$actualRaw,2);
            if(!preg_match('/^[1-5]$/',$scoreRaw))throw new RuntimeException('ค่าคะแนนที่ได้ต้องเป็นจำนวนเต็มตั้งแต่ 1–5');
            $score=(int)$scoreRaw;
            $calculated=kpiCalculateResult($indicatorMap[$indicatorId],$actual,$score);
            $rowsToSave[]=[$indicatorId,$departmentId,$actual,$calculated['percentage'],$calculated['score'],round((float)$calculated['weighted_score'],2)];
        }
        if(!$rowsToSave)throw new RuntimeException('ยังไม่มีข้อมูลผลการดำเนินงานและคะแนนสำหรับบันทึก');
        $pdo->beginTransaction();
        $save=$pdo->prepare('INSERT INTO kpi_results (indicator_id,department_id,actual_value,percentage,score,weighted_score,note,entered_by) VALUES (?,?,?,?,?,?,NULL,?) ON DUPLICATE KEY UPDATE actual_value=VALUES(actual_value),percentage=VALUES(percentage),score=VALUES(score),weighted_score=VALUES(weighted_score),entered_by=VALUES(entered_by)');
        foreach($rowsToSave as [$indicatorId,$departmentId,$actual,$percentage,$score,$weighted])$save->execute([$indicatorId,$departmentId,$actual,$percentage,$score,$weighted,$userId]);
        $pdo->commit();
        $_SESSION['kpi_flash']=['type'=>'success','message'=>$action==='save_all_results'?'บันทึกผลและคะแนนทั้งหมดเรียบร้อย':'บันทึกผลและคำนวณคะแนนเรียบร้อย'];
    } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); $_SESSION['kpi_flash']=['type'=>'error','message'=>$e->getMessage()]; }
    header('Location: '.appUrl('kpi_results.php').'?cycle_id='.$cycleId); exit;
}

$results=[];
if($indicatorMap && $departmentIds) {
    $im=implode(',',array_fill(0,count($indicatorMap),'?')); $dm=implode(',',array_fill(0,count($departmentIds),'?'));
    $stmt=$pdo->prepare("SELECT * FROM kpi_results WHERE indicator_id IN ($im) AND department_id IN ($dm)");
    $stmt->execute([...array_keys($indicatorMap),...$departmentIds]);
    foreach($stmt->fetchAll() as $r) $results[(int)$r['indicator_id']][(int)$r['department_id']]=$r;
}
$summary=[]; foreach($departments as $d) $summary[(int)$d['id']]=['weighted'=>0.0,'weight'=>0.0,'count'=>0];
foreach($indicators as $k) foreach($departments as $d) { $did=(int)$d['id']; $r=$results[(int)$k['id']][$did]??null; if($r){$summary[$did]['weighted']+=(float)$r['weighted_score'];$summary[$did]['weight']+=(float)$k['weight'];$summary[$did]['count']++;} }
$flash=$_SESSION['kpi_flash']??null; unset($_SESSION['kpi_flash']);
require_once 'includes/header.php';
?>
<div class="card" style="margin-bottom:1rem"><div class="card-header" style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap"><div><h2 class="card-title"><?= appIcon('bar-chart') ?> บันทึกผลตัวชี้วัด</h2><p style="color:var(--text-muted);margin:.4rem 0 0"><?= $canEnterResults?'ผู้รับผิดชอบตัวชี้วัด สสอ. กรอกผลการดำเนินงานและค่าคะแนน 1–5 ระบบคำนวณคะแนนถ่วงน้ำหนัก = คะแนน ÷ 5 × น้ำหนัก':'แสดงผลตัวชี้วัดแบบอ่านอย่างเดียว การกรอกผลและคะแนนดำเนินการโดยผู้รับผิดชอบตัวชี้วัด สสอ.' ?></p></div><?php if(kpiCanManageAssignments($role)): ?><a class="btn btn-secondary" href="<?= htmlspecialchars(appUrl('kpi_assignments.php')) ?>?cycle_id=<?= $cycleId ?>"><?= appIcon('link') ?> กำหนดผู้รับผิดชอบ</a><?php endif; ?></div></div>
<?php if($flash): ?><div style="padding:1rem;margin-bottom:1rem;border-radius:8px;background:<?= $flash['type']==='success'?'#D1FAE5':'#FEE2E2' ?>;color:<?= $flash['type']==='success'?'#065F46':'#991B1B' ?>"><?= htmlspecialchars($flash['message']) ?></div><?php endif; ?>
<form method="post" id="all-results-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>"><input type="hidden" name="cycle_id" value="<?= $cycleId ?>"></form>
<div class="card" style="margin-bottom:1rem;display:flex;justify-content:space-between;gap:1rem;align-items:end;flex-wrap:wrap"><form method="get" style="min-width:280px;max-width:420px;flex:1"><div class="form-group" style="margin:0"><label>รอบการประเมิน</label><select class="form-control" name="cycle_id" onchange="this.form.submit()"><?php foreach($cycles as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $cycleId===(int)$c['id']?'selected':'' ?>><?= htmlspecialchars(kpiCycleLabel($c)) ?> (<?= $c['status']==='active'?'เปิด':'ปิด' ?>)</option><?php endforeach; ?></select></div></form><button class="btn btn-primary" type="submit" form="all-results-form" name="action" value="save_all_results" <?= (!$indicators||!$canEnterResults||($cycle['status']??'closed')!=='active')?'disabled':'' ?>><?= appIcon('save') ?> บันทึกทั้งหมด</button></div>

<?php if(!$indicators): ?><div class="card" style="text-align:center;padding:2.5rem;color:var(--text-muted)"><?= appIcon('inbox') ?><h3>ยังไม่มีตัวชี้วัดที่ได้รับมอบหมาย</h3><p>กรุณาติดต่อ สสอ. แอดมิน สสอ. หรือ ผอ.รพ.สต. ตามหน่วยงานของท่าน</p></div><?php endif; ?>
<?php foreach($indicators as $indicatorIndex=>$k): ?>
<details class="card kpi-accordion" <?= $indicatorIndex===0?'open':'' ?>>
  <summary class="kpi-accordion-header">
    <span class="kpi-accordion-number"><?= (int)$k['order_seq'] ?></span>
    <span class="kpi-accordion-title"><strong><?= htmlspecialchars($k['name']) ?></strong><small><?= $k['responsibility_type']==='admin'?'ผู้ดูแลระบบ':($k['responsibility_type']==='primary'?'ผู้รับผิดชอบหลัก':'ผู้รับผิดชอบรอง') ?></small></span>
    <span class="kpi-accordion-meta"><span>เป้าหมาย <?= htmlspecialchars($k['target_label']??'-') ?></span><span>น้ำหนัก <?= number_format((float)$k['weight'],2) ?></span></span>
    <span class="kpi-accordion-chevron">⌄</span>
  </summary>
  <div class="kpi-accordion-content">
  <?php if($role==='admin'): ?><form method="post" class="kpi-setting-grid" style="display:grid;grid-template-columns:minmax(180px,1.4fr) minmax(120px,.8fr) minmax(120px,.8fr) minmax(120px,.8fr) auto;gap:.65rem;align-items:end;padding:.85rem;background:#F8FAFC;border:1px solid var(--border-color);border-radius:10px;margin-bottom:1rem">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>"><input type="hidden" name="action" value="save_settings"><input type="hidden" name="indicator_id" value="<?= (int)$k['id'] ?>"><input type="hidden" name="cycle_id" value="<?= $cycleId ?>">
    <div class="form-group" style="margin:0"><label>ค่าเป้าหมาย</label><input class="form-control" name="target_label" value="<?= htmlspecialchars($k['target_label']??'') ?>" placeholder="เช่น ร้อยละ 100"></div>
    <div class="form-group" style="margin:0"><label>ค่าน้ำหนัก</label><input class="form-control" type="number" min=".01" step=".01" name="weight" value="<?= htmlspecialchars($k['weight']) ?>" required></div>
    <div class="form-group" style="margin:0"><label>เป้าหมาย</label><input class="form-control" type="number" step=".01" name="target_value" value="<?= $k['target_value']!==null?number_format((float)$k['target_value'],2,'.',''):'' ?>"></div>
    <div class="form-group" style="margin:0"><label>หน่วย</label><input class="form-control" name="unit" value="<?= htmlspecialchars($k['unit']??'') ?>"></div>
    <button class="btn btn-secondary" type="submit" <?= ($cycle['status']??'closed')!=='active'?'disabled':'' ?>><?= appIcon('save') ?> บันทึกค่า</button>
  </form><?php endif; ?>
  <div class="table-wrap"><table><thead><tr><th>รหัส/หน่วยบริการ</th><th>ผลการดำเนินงาน</th><th>ร้อยละ</th><th>คะแนน (1–5)</th><th>คะแนนถ่วงน้ำหนัก</th><th>จัดการ</th></tr></thead><tbody>
  <?php foreach($departments as $d): $r=$results[(int)$k['id']][(int)$d['id']]??null; $indicatorId=(int)$k['id']; $departmentId=(int)$d['id']; ?>
    <tr><td style="min-width:220px"><strong><?= $d['type']==='SSO'?'รวม':htmlspecialchars($d['short_name']?:$d['name']) ?></strong><small style="display:block;color:var(--text-muted)"><?= htmlspecialchars($d['service_code']??'') ?></small></td><td><input form="all-results-form" class="form-control" style="min-width:130px" type="number" step=".01" name="actual_value[<?= $indicatorId ?>][<?= $departmentId ?>]" value="<?= $r?number_format((float)$r['actual_value'],2,'.',''):'' ?>" <?= !$canEnterResults?'disabled':'' ?>></td><td><?= $r?number_format((float)$r['percentage'],2):'-' ?></td><td><input form="all-results-form" class="form-control" style="min-width:100px" type="number" min="1" max="5" step="1" name="score[<?= $indicatorId ?>][<?= $departmentId ?>]" value="<?= $r?(string)(int)$r['score']:'' ?>" <?= !$canEnterResults?'disabled':'' ?>></td><td><?= $r?number_format((float)$r['weighted_score'],2):'-' ?><small style="display:block;color:var(--text-muted)">คะแนน ÷ 5 × น้ำหนัก</small></td><td><button class="btn btn-secondary" type="submit" form="all-results-form" name="save_result_key" value="<?= $indicatorId ?>:<?= $departmentId ?>" <?= (($cycle['status']??'closed')!=='active'||!$canEnterResults)?'disabled':'' ?>><?= appIcon('save') ?> บันทึกแถวนี้</button></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <details style="margin-top:.8rem"><summary style="cursor:pointer;font-weight:700">ดูเกณฑ์คะแนน</summary><div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.6rem"><?php for($level=1;$level<=5;$level++): ?><span style="padding:.45rem .7rem;background:#F3F4F6;border-radius:7px">คะแนน <?= $level ?>: <?= number_format((float)$k['score_'.$level.'_threshold'],4) ?></span><?php endfor; ?></div></details>
</div></details>
<?php endforeach; ?>

<?php if($indicators): ?><div class="card"><h3>สรุปคะแนนตัวชี้วัด</h3><div class="table-wrap"><table><thead><tr><th>หน่วยบริการ</th><th>บันทึกแล้ว</th><th>น้ำหนักที่มีผล</th><th>คะแนนถ่วงน้ำหนักรวม</th><th>แปลงเป็น 100 คะแนน</th></tr></thead><tbody><?php foreach($departments as $d): $s=$summary[(int)$d['id']]; $hundred=$s['weight']>0?$s['weighted']/$s['weight']*100:0; ?><tr><td><strong><?= $d['type']==='SSO'?'รวม':htmlspecialchars($d['short_name']?:$d['name']) ?></strong></td><td><?= $s['count'] ?>/<?= count($indicators) ?></td><td><?= number_format($s['weight'],2) ?></td><td><?= number_format($s['weighted'],2) ?></td><td style="font-size:1.05rem;font-weight:800"><?= number_format($hundred,2) ?></td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?>
<style>
.kpi-accordion{padding:0!important;margin-bottom:.8rem;overflow:hidden}.kpi-accordion>summary{list-style:none}.kpi-accordion>summary::-webkit-details-marker{display:none}
.kpi-accordion-header{display:grid;grid-template-columns:42px minmax(260px,1fr) auto 28px;gap:.8rem;align-items:center;padding:1rem 1.15rem;cursor:pointer;background:#fff;transition:.18s ease}
.kpi-accordion-header:hover{background:var(--primary-50)}.kpi-accordion[open]>.kpi-accordion-header{background:var(--primary-50);border-bottom:1px solid var(--border-color)}
.kpi-accordion-number{width:38px;height:38px;border-radius:10px;display:grid;place-items:center;background:var(--primary-color);color:#fff;font-weight:800}
.kpi-accordion-title strong{display:block;font-size:1.02rem;line-height:1.45}.kpi-accordion-title small{display:block;color:var(--text-muted);margin-top:.2rem}
.kpi-accordion-meta{display:flex;gap:.4rem;flex-wrap:wrap;justify-content:flex-end}.kpi-accordion-meta span{padding:.35rem .6rem;border-radius:999px;background:#fff;border:1px solid var(--border-color);font-size:.82rem;white-space:nowrap}
.kpi-accordion-chevron{font-size:1.45rem;color:var(--primary-color);transition:transform .18s ease;text-align:center}.kpi-accordion[open] .kpi-accordion-chevron{transform:rotate(180deg)}
.kpi-accordion-content{padding:1rem 1.15rem 1.2rem}
@media(max-width:900px){.kpi-setting-grid{grid-template-columns:1fr 1fr!important}.kpi-accordion-header{grid-template-columns:42px minmax(0,1fr) 26px}.kpi-accordion-meta{grid-column:2/4;justify-content:flex-start}}
@media(max-width:560px){.kpi-setting-grid{grid-template-columns:1fr!important}.kpi-accordion-header{padding:.85rem;gap:.6rem}.kpi-accordion-meta span{font-size:.76rem}}
</style>
<?php require_once 'includes/footer.php'; ?>
