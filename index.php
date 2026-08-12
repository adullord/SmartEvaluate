<?php
require_once 'config.php';
require_once __DIR__.'/includes/component3_helper.php';
if (!isset($_SESSION['user_id'])) { header('Location: '.appUrl('login.php')); exit; }

$userId=(int)$_SESSION['user_id']; $role=(string)$_SESSION['role'];
$activeCycle=$pdo->query("SELECT * FROM evaluation_cycles WHERE status='active' ORDER BY id DESC LIMIT 1")->fetch();
$cycleId=(int)($activeCycle['id']??0);
$competency=['total'=>0,'done'=>0,'own_status'=>null,'own_score'=>null];
$kpi=['total'=>0,'completed'=>0];
$component3=['status'=>null,'score'=>null];
$tableExists = static function(PDO $pdo, string $table): bool {
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
};
$hasKpiSchema=$tableExists($pdo,'kpi_indicators') && $tableExists($pdo,'kpi_assignments') && $tableExists($pdo,'kpi_results');
$hasComponent3Schema=$tableExists($pdo,'component3_assessments');
$schemaMissing=!$hasKpiSchema || !$hasComponent3Schema;

if($activeCycle){
    if(in_array($role,['admin','ss_amphoe','director'],true)){
        $stmt=$pdo->prepare("SELECT COUNT(*) total,SUM(CASE WHEN e.status IN ('submitted','acknowledged') THEN 1 ELSE 0 END) done FROM evaluator_mapping em JOIN users u ON u.id=em.evaluatee_id LEFT JOIN evaluations e ON e.cycle_id=em.cycle_id AND e.evaluatee_id=em.evaluatee_id AND e.evaluator_id=em.evaluator_id WHERE em.evaluator_id=? AND em.cycle_id=? AND u.is_active=1 AND (?='admin' OR (?='ss_amphoe' AND ((u.role='staff' AND u.department_id=?) OR u.role='director')) OR (?='director' AND u.role='staff' AND u.department_id=?))");
        $stmt->execute([$userId,$cycleId,$role,$role,(int)$_SESSION['department_id'],$role,(int)$_SESSION['department_id']]); $row=$stmt->fetch(); $competency['total']=(int)$row['total']; $competency['done']=(int)$row['done'];
    }
    $stmt=$pdo->prepare("SELECT status,total_score_base100 FROM evaluations WHERE cycle_id=? AND evaluatee_id=? AND status IN ('submitted','acknowledged') ORDER BY id DESC LIMIT 1");
    $stmt->execute([$cycleId,$userId]); if($row=$stmt->fetch()){ $competency['own_status']=$row['status']; $competency['own_score']=$row['total_score_base100']; }

    if($hasKpiSchema && $role==='admin'){
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM kpi_indicators WHERE cycle_id=? AND is_active=1'); $stmt->execute([$cycleId]); $kpi['total']=(int)$stmt->fetchColumn();
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM kpi_results r JOIN kpi_indicators i ON i.id=r.indicator_id WHERE i.cycle_id=?'); $stmt->execute([$cycleId]); $kpi['completed']=(int)$stmt->fetchColumn();
    }elseif($hasKpiSchema){
        $stmt=$pdo->prepare('SELECT COUNT(DISTINCT a.indicator_id) FROM kpi_assignments a JOIN kpi_indicators i ON i.id=a.indicator_id WHERE a.user_id=? AND i.cycle_id=? AND i.is_active=1'); $stmt->execute([$userId,$cycleId]); $kpi['total']=(int)$stmt->fetchColumn();
        $stmt=$pdo->prepare('SELECT COUNT(DISTINCT r.indicator_id) FROM kpi_assignments a JOIN kpi_indicators i ON i.id=a.indicator_id JOIN users u ON u.id=a.user_id JOIN kpi_results r ON r.indicator_id=i.id AND r.department_id=u.department_id WHERE a.user_id=? AND i.cycle_id=?'); $stmt->execute([$userId,$cycleId]); $kpi['completed']=(int)$stmt->fetchColumn();
    }
    if($hasComponent3Schema){
        $stmt=$pdo->prepare('SELECT status,final_score FROM component3_assessments WHERE cycle_id=? AND user_id=? LIMIT 1'); $stmt->execute([$cycleId,$userId]); if($row=$stmt->fetch()){ $component3['status']=$row['status']; $component3['score']=$row['final_score']; }
    }
}

require_once 'includes/header.php';
?>
<section class="dashboard-hero"><div><p class="dashboard-eyebrow">Smart Evaluate</p><h1>สวัสดี <?= htmlspecialchars($_SESSION['fullname']) ?></h1><p><?= $activeCycle?'ภาพรวมการประเมิน '.htmlspecialchars(component3CycleLabel($activeCycle)):'ขณะนี้ยังไม่มีรอบการประเมินที่เปิดใช้งาน' ?></p></div><span class="dashboard-hero-icon"><?= appIcon('bar-chart') ?></span></section>

<?php if(!$activeCycle): ?><div class="alert alert-warning"><?= appIcon('triangle-alert') ?> กรุณาติดต่อผู้ดูแลระบบเพื่อเปิดรอบการประเมิน</div><?php else: ?>
<?php if($schemaMissing): ?><div class="alert alert-warning"><?= appIcon('database') ?> โครงสร้างฐานข้อมูลยังไม่ครบ กรุณา<?= $role==='admin'?'<a href="'.htmlspecialchars(appUrl('admin/database_schema.php')).'"><strong>ปรับโครงสร้างฐานข้อมูล</strong></a>':'ติดต่อผู้ดูแลระบบ' ?></div><?php endif; ?>
<div class="dashboard-overview">
  <article class="card dashboard-component component-one"><div class="dashboard-card-top"><span class="dashboard-component-icon"><?= appIcon('clipboard-list') ?></span><span class="dashboard-number">01</span></div><h2>สมรรถนะ</h2><p>องค์ประกอบที่ 1</p><?php if(in_array($role,['admin','ss_amphoe','director'],true)): ?><div class="dashboard-metrics"><span><b><?= $competency['done'] ?></b>ประเมินแล้ว</span><span><b><?= max(0,$competency['total']-$competency['done']) ?></b>รอดำเนินการ</span></div><a class="btn btn-primary" href="<?= htmlspecialchars(appUrl('competency_assessments.php')) ?>">รายชื่อผู้ที่ต้องประเมิน</a><?php else: ?><div class="dashboard-own-status"><small>ผลของฉัน</small><strong><?= $competency['own_score']!==null?number_format((float)$competency['own_score'],2):'-' ?></strong><span><?= $competency['own_status']?'ประเมินแล้ว':'ยังไม่มีผลประเมิน' ?></span></div><a class="btn btn-secondary" href="<?= htmlspecialchars(appUrl('report.php')) ?>">ดูผลการประเมิน</a><?php endif; ?></article>
  <article class="card dashboard-component component-two"><div class="dashboard-card-top"><span class="dashboard-component-icon"><?= appIcon('activity') ?></span><span class="dashboard-number">02</span></div><h2>ตัวชี้วัด</h2><p>องค์ประกอบที่ 2</p><div class="dashboard-metrics"><span><b><?= $kpi['total'] ?></b><?= $role==='admin'?'ตัวชี้วัดทั้งหมด':'ที่รับผิดชอบ' ?></span><span><b><?= $kpi['completed'] ?></b>บันทึกผลแล้ว</span></div><a class="btn btn-primary" href="<?= htmlspecialchars(appUrl('kpi_results.php')) ?>">ไปยังตัวชี้วัด</a></article>
  <article class="card dashboard-component component-three"><div class="dashboard-card-top"><span class="dashboard-component-icon"><?= appIcon('clipboard-check') ?></span><span class="dashboard-number">03</span></div><h2>งานมอบหมายพิเศษ</h2><p>องค์ประกอบที่ 3</p><div class="dashboard-own-status"><small>คะแนนของฉัน</small><strong><?= $component3['score']!==null?number_format((float)$component3['score'],2):'-' ?></strong><span><?= component3StatusLabel($component3['status']) ?></span></div><a class="btn btn-primary" href="<?= htmlspecialchars(appUrl('component3_assessment.php')) ?>">ประเมินตนเอง</a></article>
</div>
<div class="card dashboard-shortcuts"><h3>เมนูลัด</h3><div><?php if(in_array($role,['admin','ss_amphoe','director'],true)): ?><a href="<?= htmlspecialchars(appUrl('competency_assessments.php')) ?>"><?= appIcon('users') ?><span><b>รายชื่อผู้ที่ต้องประเมิน</b><small>เริ่มหรือดำเนินการประเมินสมรรถนะ</small></span></a><?php endif; ?><a href="<?= htmlspecialchars(appUrl('report.php')) ?>"><?= appIcon('bar-chart') ?><span><b>รายงานผล</b><small>ดูคะแนนและส่งออกรายงาน</small></span></a><a href="<?= htmlspecialchars(appUrl('component3_report.php')) ?>"><?= appIcon('file-text') ?><span><b>รายงานองค์ประกอบที่ 3</b><small>ดูผลและส่งออก PDF</small></span></a></div></div>
<?php endif; ?>
<style>.dashboard-hero{display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:1.6rem 1.8rem;margin-bottom:1.2rem;border-radius:18px;background:linear-gradient(135deg,#17375e,#28598c);color:#fff;box-shadow:0 12px 30px rgba(23,55,94,.18)}.dashboard-hero h1{margin:.15rem 0;font-size:1.75rem}.dashboard-hero p{margin:0;opacity:.85}.dashboard-eyebrow{text-transform:uppercase;letter-spacing:.12em;font-size:.78rem;font-weight:700}.dashboard-hero-icon{width:66px;height:66px;display:grid;place-items:center;border-radius:18px;background:rgba(255,255,255,.13)}.dashboard-hero-icon .app-icon{width:34px;height:34px}.dashboard-overview{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem;margin-bottom:1.2rem}.dashboard-component{position:relative;overflow:hidden;border-top:4px solid var(--accent);display:flex;flex-direction:column}.component-one{--accent:#2563eb}.component-two{--accent:#059669}.component-three{--accent:#7c3aed}.dashboard-card-top{display:flex;justify-content:space-between;align-items:center}.dashboard-component-icon{width:46px;height:46px;display:grid;place-items:center;border-radius:12px;background:color-mix(in srgb,var(--accent) 12%,white);color:var(--accent)}.dashboard-number{font-size:2rem;font-weight:800;color:color-mix(in srgb,var(--accent) 20%,white)}.dashboard-component h2{margin:1rem 0 .1rem}.dashboard-component>p{color:var(--text-muted);margin:0 0 1rem}.dashboard-metrics{display:grid;grid-template-columns:1fr 1fr;gap:.6rem;margin-bottom:1rem}.dashboard-metrics span,.dashboard-own-status{padding:.75rem;border-radius:10px;background:#f8fafc;color:var(--text-muted)}.dashboard-metrics b,.dashboard-own-status strong{display:block;color:var(--accent);font-size:1.45rem}.dashboard-own-status{margin-bottom:1rem}.dashboard-own-status small,.dashboard-own-status span{display:block}.dashboard-component>.btn{margin-top:auto}.dashboard-shortcuts h3{margin-top:0}.dashboard-shortcuts>div{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.7rem}.dashboard-shortcuts a{display:flex;gap:.7rem;align-items:center;padding:.85rem;border:1px solid var(--border-color);border-radius:10px;text-decoration:none;color:inherit}.dashboard-shortcuts a:hover{background:var(--primary-50)}.dashboard-shortcuts a>.app-icon{color:var(--primary-color);flex:0 0 auto}.dashboard-shortcuts b,.dashboard-shortcuts small{display:block}.dashboard-shortcuts small{color:var(--text-muted);margin-top:.2rem}@media(max-width:1000px){.dashboard-overview{grid-template-columns:1fr 1fr}.dashboard-overview article:last-child{grid-column:1/-1}}@media(max-width:700px){.dashboard-overview,.dashboard-shortcuts>div{grid-template-columns:1fr}.dashboard-overview article:last-child{grid-column:auto}.dashboard-hero{padding:1.25rem}.dashboard-hero-icon{display:none}}
</style>
<?php require_once 'includes/footer.php'; ?>
