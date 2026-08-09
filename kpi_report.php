<?php
require_once 'config.php';
require_once __DIR__ . '/includes/kpi_helper.php';
if(!isset($_SESSION['user_id'])){header('Location: '.appUrl('login.php'));exit;}
$userId=(int)$_SESSION['user_id']; $role=(string)($_SESSION['role']??'staff');
if(!kpiCanManageAssignments($role)){http_response_code(403);die('ไม่มีสิทธิ์ดูรายงานตัวชี้วัด');}
$cycles=$pdo->query('SELECT * FROM evaluation_cycles ORDER BY fiscal_year DESC,start_date DESC')->fetchAll();
$cycleId=requestInt($_GET['cycle_id']??null,'cycle_id',(int)($cycles[0]['id']??0));
$departments=kpiAllowedDepartments($pdo,$userId,$role);
$departmentIds=array_map(fn($d)=>(int)$d['id'],$departments);
$stmt=$pdo->prepare('SELECT * FROM kpi_indicators WHERE cycle_id=? AND is_active=1 ORDER BY order_seq,id');$stmt->execute([$cycleId]);$indicators=$stmt->fetchAll();
$results=[];$responsible=[];$eligibleUserIds=array_map(fn($u)=>(int)$u['id'],kpiEligibleUsers($pdo,$userId,$role));
if($indicators){
  $ids=array_map(fn($k)=>(int)$k['id'],$indicators);$marks=implode(',',array_fill(0,count($ids),'?'));
  if($departmentIds){$dmarks=implode(',',array_fill(0,count($departmentIds),'?'));$stmt=$pdo->prepare("SELECT r.*,u.fullname FROM kpi_results r JOIN users u ON u.id=r.entered_by WHERE r.indicator_id IN ($marks) AND r.department_id IN ($dmarks)");$stmt->execute([...$ids,...$departmentIds]);foreach($stmt->fetchAll() as $r)$results[(int)$r['indicator_id']][(int)$r['department_id']]=$r;}
  if($eligibleUserIds){$umarks=implode(',',array_fill(0,count($eligibleUserIds),'?'));$stmt=$pdo->prepare("SELECT a.indicator_id,u.fullname,a.responsibility_type,d.short_name FROM kpi_assignments a JOIN users u ON u.id=a.user_id JOIN departments d ON d.id=u.department_id WHERE a.indicator_id IN ($marks) AND a.user_id IN ($umarks) ORDER BY a.responsibility_type,u.fullname");$stmt->execute([...$ids,...$eligibleUserIds]);foreach($stmt->fetchAll() as $a)$responsible[(int)$a['indicator_id']][]=$a;}
}
require_once 'includes/header.php';
?>
<div class="card" style="margin-bottom:1rem"><div class="card-header"><div><h2 class="card-title"><?= appIcon('bar-chart') ?> รายงานผลตัวชี้วัด</h2><p style="color:var(--text-muted);margin:.4rem 0 0">ติดตามผลราย รพ.สต. และผลรวม พร้อมผู้รับผิดชอบของแต่ละตัวชี้วัด</p></div></div></div>
<div class="card" style="margin-bottom:1rem"><form method="get" style="max-width:420px"><div class="form-group"><label>รอบการประเมิน</label><select class="form-control" name="cycle_id" onchange="this.form.submit()"><?php foreach($cycles as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $cycleId===(int)$c['id']?'selected':'' ?>><?= htmlspecialchars(kpiCycleLabel($c)) ?></option><?php endforeach; ?></select></div></form></div>
<?php foreach($indicators as $k): ?><div class="card" style="margin-bottom:1rem"><h3><?= (int)$k['order_seq'] ?>. <?= htmlspecialchars($k['name']) ?></h3><p style="color:var(--text-muted)">เป้าหมาย <?= htmlspecialchars($k['target_label']??'-') ?> · น้ำหนัก <?= number_format((float)$k['weight'],2) ?></p><div style="margin-bottom:.8rem"><strong>ผู้รับผิดชอบ:</strong> <?php if(empty($responsible[(int)$k['id']])): ?>ยังไม่ได้กำหนด<?php else: ?><?= implode(', ',array_map(fn($a)=>htmlspecialchars($a['fullname']).' ('.($a['responsibility_type']==='primary'?'หลัก':'รอง').')',$responsible[(int)$k['id']])) ?><?php endif; ?></div><div class="table-wrap"><table><thead><tr><th>หน่วยบริการ</th><th>ผลการดำเนินงาน</th><th>ร้อยละ</th><th>คะแนน</th><th>คะแนนถ่วงน้ำหนัก</th><th>ปรับปรุงล่าสุดโดย</th></tr></thead><tbody><?php foreach($departments as $d):$r=$results[(int)$k['id']][(int)$d['id']]??null;?><tr><td><strong><?= $d['type']==='SSO'?'รวม':htmlspecialchars($d['short_name']?:$d['name']) ?></strong></td><td><?= $r?number_format((float)$r['actual_value'],2):'-' ?></td><td><?= $r?number_format((float)$r['percentage'],2):'-' ?></td><td><?= $r?number_format((float)$r['score'],2):'-' ?></td><td><?= $r?number_format((float)$r['weighted_score'],2):'-' ?></td><td><?= $r?htmlspecialchars($r['fullname']):'-' ?></td></tr><?php endforeach; ?></tbody></table></div></div><?php endforeach; ?>
<?php if(!$indicators): ?><div class="card" style="text-align:center;color:var(--text-muted);padding:2rem">ยังไม่มีตัวชี้วัดในรอบนี้</div><?php endif; ?>
<?php require_once 'includes/footer.php'; ?>
