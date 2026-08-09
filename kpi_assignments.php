<?php
require_once 'config.php';
require_once 'csrf_helper.php';
require_once __DIR__ . '/includes/kpi_helper.php';

if (!isset($_SESSION['user_id'])) { header('Location: ' . appUrl('login.php')); exit; }
$userId = (int)$_SESSION['user_id'];
$role = (string)($_SESSION['role'] ?? 'staff');
if (!kpiCanManageAssignments($role)) { http_response_code(403); die('ไม่มีสิทธิ์กำหนดผู้รับผิดชอบตัวชี้วัด'); }
kpiEnsureDirectorAssignments($pdo);

$cycles = $pdo->query('SELECT * FROM evaluation_cycles ORDER BY fiscal_year DESC,start_date DESC')->fetchAll();
$cycleInput = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['cycle_id'] ?? null) : ($_GET['cycle_id'] ?? null);
$cycleId = requestInt($cycleInput, 'cycle_id', (int)($cycles[0]['id'] ?? 0));
$eligibleUsers = kpiEligibleUsers($pdo, $userId, $role);
$eligibleIds = array_map(fn($u)=>(int)$u['id'], $eligibleUsers);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) throw new RuntimeException('คำขอหมดอายุ กรุณาลองใหม่');
        $action = isset($_POST['save_indicator_id']) ? 'save_indicator' : (string)($_POST['action'] ?? '');
        if (!in_array($action, ['save_indicator', 'save_all'], true)) throw new RuntimeException('คำสั่งบันทึกไม่ถูกต้อง');

        $primaryValues = is_array($_POST['primary_user_id'] ?? null) ? $_POST['primary_user_id'] : [];
        $secondaryValues = is_array($_POST['secondary_user_ids'] ?? null) ? $_POST['secondary_user_ids'] : [];
        if ($action === 'save_indicator') {
            $indicatorIds = [requestInt($_POST['save_indicator_id'] ?? null, 'indicator_id')];
        } else {
            $stmt = $pdo->prepare('SELECT id FROM kpi_indicators WHERE cycle_id=? AND is_active=1 ORDER BY order_seq,id');
            $stmt->execute([$cycleId]);
            $indicatorIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }
        if (!$indicatorIds || in_array(0, $indicatorIds, true)) throw new RuntimeException('ไม่พบตัวชี้วัดในรอบที่เลือก');

        $check = $pdo->prepare('SELECT COUNT(*) FROM kpi_indicators WHERE id=? AND cycle_id=? AND is_active=1');
        $rowsToSave = [];
        foreach ($indicatorIds as $indicatorId) {
            $check->execute([$indicatorId,$cycleId]);
            if (!(int)$check->fetchColumn()) throw new RuntimeException('ไม่พบตัวชี้วัดในรอบที่เลือก');
            $primaryId = requestInt($primaryValues[$indicatorId] ?? null, 'primary_user_id', 0, 0);
            $secondaryRaw = $secondaryValues[$indicatorId] ?? [];
            if (!is_array($secondaryRaw)) throw new RuntimeException('รูปแบบผู้รับผิดชอบรองไม่ถูกต้อง');
            if (count($secondaryRaw) > count($eligibleIds) + 1) throw new RuntimeException('จำนวนผู้รับผิดชอบรองไม่ถูกต้อง');
            $secondaryIds = [];
            foreach ($secondaryRaw as $secondaryValue) {
                $secondaryId = requestInt($secondaryValue, 'secondary_user_id', 0, 0);
                if ($secondaryId === 0) continue;
                if (!in_array($secondaryId,$eligibleIds,true)) throw new RuntimeException('ผู้รับผิดชอบรองอยู่นอกหน่วยงานที่รับผิดชอบ');
                if ($primaryId > 0 && $primaryId === $secondaryId) throw new RuntimeException('ผู้รับผิดชอบหลักและรองต้องเป็นคนละคน');
                $secondaryIds[$secondaryId] = $secondaryId;
            }
            if ($primaryId === 0) throw new RuntimeException('กรุณาเลือกผู้รับผิดชอบหลักให้ตัวชี้วัดทุกข้อ');
            if (!in_array($primaryId,$eligibleIds,true)) throw new RuntimeException('ผู้รับผิดชอบหลักอยู่นอกหน่วยงานที่รับผิดชอบ');
            $rowsToSave[] = [$indicatorId,$primaryId,array_values($secondaryIds)];
        }

        $pdo->beginTransaction();
        $delete = null;
        if ($eligibleIds) {
            $marks = implode(',', array_fill(0, count($eligibleIds), '?'));
            $delete = $pdo->prepare("DELETE FROM kpi_assignments WHERE indicator_id=? AND user_id IN ($marks)");
        }
        $insert = $pdo->prepare('INSERT INTO kpi_assignments (indicator_id,user_id,responsibility_type,assigned_by) VALUES (?,?,?,?)');
        foreach ($rowsToSave as [$indicatorId,$primaryId,$secondaryIds]) {
            if ($delete) $delete->execute([$indicatorId, ...$eligibleIds]);
            if ($primaryId > 0) $insert->execute([$indicatorId,$primaryId,'primary',$userId]);
            foreach ($secondaryIds as $secondaryId) $insert->execute([$indicatorId,$secondaryId,'secondary',$userId]);
        }
        $pdo->commit();
        $_SESSION['kpi_flash']=['type'=>'success','message'=>$action === 'save_all' ? 'บันทึกผู้รับผิดชอบทุกตัวชี้วัดเรียบร้อย' : 'บันทึกผู้รับผิดชอบตัวชี้วัดเรียบร้อย'];
    } catch(Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['kpi_flash']=['type'=>'error','message'=>$e->getMessage()];
    }
    header('Location: '.appUrl('kpi_assignments.php').'?cycle_id='.$cycleId); exit;
}

$stmt=$pdo->prepare('SELECT * FROM kpi_indicators WHERE cycle_id=? AND is_active=1 ORDER BY order_seq,id');
$stmt->execute([$cycleId]); $indicators=$stmt->fetchAll();
$assignments=[];
if ($eligibleIds) {
    $marks=implode(',',array_fill(0,count($eligibleIds),'?'));
    $sql="SELECT a.indicator_id,a.responsibility_type,a.user_id FROM kpi_assignments a JOIN kpi_indicators k ON k.id=a.indicator_id WHERE k.cycle_id=? AND a.user_id IN ($marks) ORDER BY a.id";
    $stmt=$pdo->prepare($sql); $stmt->execute([$cycleId,...$eligibleIds]);
    foreach ($stmt->fetchAll() as $assignment) {
        $indicatorId = (int)$assignment['indicator_id'];
        if ($assignment['responsibility_type'] === 'primary') {
            $assignments[$indicatorId]['primary'] = (int)$assignment['user_id'];
        } else {
            $assignments[$indicatorId]['secondary'][] = (int)$assignment['user_id'];
        }
    }
}
$flash=$_SESSION['kpi_flash']??null; unset($_SESSION['kpi_flash']);
require_once 'includes/header.php';
?>
<div class="card" style="margin-bottom:1rem"><div class="card-header"><div><h2 class="card-title"><?= appIcon('link') ?> กำหนดผู้รับผิดชอบตัวชี้วัด</h2><p style="color:var(--text-muted);margin:.4rem 0 0"><?= $role==='director'?'ผอ.รพ.สต. รับผิดชอบทุกตัวชี้วัด และกำหนดงานให้บุคลากรภายใน รพ.สต. ของตน':'สสอ. และแอดมิน สสอ. กำหนดผู้รับผิดชอบตัวชี้วัดของ สสอ.' ?></p></div></div></div>
<?php if($flash): ?><div style="padding:1rem;margin-bottom:1rem;border-radius:8px;background:<?= $flash['type']==='success'?'#D1FAE5':'#FEE2E2' ?>;color:<?= $flash['type']==='success'?'#065F46':'#991B1B' ?>"><?= htmlspecialchars($flash['message']) ?></div><?php endif; ?>
<div class="card" style="margin-bottom:1rem"><form method="get" style="max-width:420px"><div class="form-group"><label>รอบการประเมิน</label><select class="form-control" name="cycle_id" onchange="this.form.submit()"><?php foreach($cycles as $cycle): ?><option value="<?= (int)$cycle['id'] ?>" <?= $cycleId===(int)$cycle['id']?'selected':'' ?>><?= htmlspecialchars(kpiCycleLabel($cycle)) ?></option><?php endforeach; ?></select></div></form></div>

<div class="card"><form method="post" id="all-assignments"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>"><input type="hidden" name="cycle_id" value="<?= $cycleId ?>"><div style="display:flex;justify-content:space-between;gap:1rem;align-items:center;flex-wrap:wrap;margin-bottom:1rem"><div><h3 style="margin:0">ตัวชี้วัดทั้งหมด</h3><p style="color:var(--text-muted);margin:.35rem 0 0">กำหนดผู้รับผิดชอบหลัก 1 คน และเลือกผู้รับผิดชอบรองได้หลายคน</p></div><div style="display:flex;gap:.6rem;align-items:center"><span style="padding:.5rem .8rem;background:var(--primary-50);border-radius:8px"><?= count($indicators) ?> ตัวชี้วัด</span><button class="btn btn-primary" type="submit" name="action" value="save_all" <?= (!$indicators||!$eligibleUsers)?'disabled':'' ?>><?= appIcon('save') ?> บันทึกทั้งหมด</button></div></div>
<?php if(!$eligibleUsers): ?><p style="color:#991B1B">ไม่พบบุคลากรในขอบเขตที่สามารถมอบหมายได้</p><?php endif; ?>
<div class="table-wrap"><table><thead><tr><th style="width:75px">ลำดับ</th><th style="min-width:360px">ตัวชี้วัด</th><th style="min-width:260px">ผู้รับผิดชอบหลัก</th><th style="min-width:260px">ผู้รับผิดชอบรอง</th><th style="width:130px">จัดการ</th></tr></thead><tbody>
<?php foreach($indicators as $k): $indicatorId=(int)$k['id']; $primaryId=$assignments[$indicatorId]['primary']??0; $secondaryIds=$assignments[$indicatorId]['secondary']??[]; ?>
  <tr>
    <td style="text-align:center;font-weight:800"><?= (int)$k['order_seq'] ?></td>
    <td><strong><?= htmlspecialchars($k['name']) ?></strong><small style="display:block;color:var(--text-muted);margin-top:.3rem">เป้าหมาย <?= htmlspecialchars($k['target_label']??'-') ?> · น้ำหนัก <?= number_format((float)$k['weight'],2) ?></small></td>
    <td><select class="form-control" name="primary_user_id[<?= $indicatorId ?>]"><option value="0">— เลือกผู้รับผิดชอบหลัก —</option><?php foreach($eligibleUsers as $u): ?><option value="<?= (int)$u['id'] ?>" <?= $primaryId===(int)$u['id']?'selected':'' ?>><?= htmlspecialchars($u['fullname']) ?> — <?= htmlspecialchars($u['short_name']) ?></option><?php endforeach; ?></select></td>
    <td><input type="hidden" name="secondary_user_ids[<?= $indicatorId ?>][]" value="0"><details class="kpi-secondary-picker"><summary><span><?= appIcon('users') ?> เลือกผู้รับผิดชอบรอง</span><b class="kpi-secondary-count"><?= count($secondaryIds) ?> คน</b></summary><div class="kpi-secondary-panel"><input class="form-control kpi-secondary-search" type="search" placeholder="ค้นหาชื่อบุคลากร" autocomplete="off" onkeydown="if(event.key==='Enter')event.preventDefault()"><div class="kpi-secondary-options"><?php foreach($eligibleUsers as $u): ?><label><input type="checkbox" name="secondary_user_ids[<?= $indicatorId ?>][]" value="<?= (int)$u['id'] ?>" <?= in_array((int)$u['id'],$secondaryIds,true)?'checked':'' ?>><span><strong><?= htmlspecialchars($u['fullname']) ?></strong><small><?= htmlspecialchars($u['short_name']) ?></small></span></label><?php endforeach; ?></div></div></details></td>
    <td><button class="btn btn-secondary" type="submit" name="save_indicator_id" value="<?= $indicatorId ?>" <?= !$eligibleUsers?'disabled':'' ?>><?= appIcon('save') ?> บันทึกแถวนี้</button></td>
  </tr>
<?php endforeach; ?>
<?php if(!$indicators): ?><tr><td colspan="5" style="text-align:center;color:var(--text-muted)">ยังไม่มีตัวชี้วัดในรอบการประเมินนี้</td></tr><?php endif; ?>
</tbody></table></div></form></div>
<script>document.querySelectorAll('.kpi-secondary-picker').forEach(picker=>{const count=picker.querySelector('.kpi-secondary-count');const boxes=[...picker.querySelectorAll('input[type="checkbox"]')];const refresh=()=>count.textContent=boxes.filter(box=>box.checked).length+' คน';boxes.forEach(box=>box.addEventListener('change',refresh));const search=picker.querySelector('.kpi-secondary-search');search?.addEventListener('input',()=>{const query=search.value.trim().toLocaleLowerCase('th');picker.querySelectorAll('.kpi-secondary-options label').forEach(label=>label.hidden=query!==''&&!label.textContent.toLocaleLowerCase('th').includes(query))});refresh()});</script>
<?php require_once 'includes/footer.php'; ?>
