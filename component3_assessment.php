<?php
require_once 'config.php';
require_once __DIR__ . '/includes/component3_helper.php';
require_once __DIR__ . '/csrf_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . appUrl('login.php'));
    exit;
}

$userId = (int)$_SESSION['user_id'];
$cycles = $pdo->query('SELECT id,fiscal_year,round_name,start_date,end_date,status FROM evaluation_cycles ORDER BY fiscal_year DESC,start_date DESC,id DESC')->fetchAll();
$cycleId = requestInt($_GET['cycle_id'] ?? null, 'cycle_id', (int)($cycles[0]['id'] ?? 0));
$cycle = null;
foreach ($cycles as $candidate) if ((int)$candidate['id'] === $cycleId) $cycle = $candidate;
if (!$cycle) {
    http_response_code(404);
    exit('ไม่พบรอบการประเมิน');
}

$person = component3UserContext($pdo, $userId);
$items = component3ItemsForCycle($pdo, $cycleId, (bool)$person['includes_items_1_2'], $person['department_score']);
$stmt = $pdo->prepare('SELECT * FROM component3_assessments WHERE cycle_id=? AND user_id=? LIMIT 1');
$stmt->execute([$cycleId, $userId]);
$assessment = $stmt->fetch() ?: null;
$savedScores = [];
if ($assessment) {
    $stmt = $pdo->prepare('SELECT * FROM component3_scores WHERE assessment_id=?');
    $stmt->execute([(int)$assessment['id']]);
    foreach ($stmt->fetchAll() as $row) $savedScores[(int)$row['item_no']] = $row;
}
$readonly = $cycle['status'] !== 'active' || ($assessment && $assessment['status'] === 'submitted');
$message = isset($_GET['success']) ? ((string)$_GET['success'] === 'submitted' ? 'ยืนยันส่งผลประเมินเรียบร้อยแล้ว' : 'บันทึกฉบับร่างเรียบร้อยแล้ว') : '';

require_once 'includes/header.php';
?>
<div class="card component3-heading">
  <div class="card-header"><div><h2 class="card-title"><?= appIcon('clipboard-check') ?> องค์ประกอบที่ 3 งานมอบหมายพิเศษ</h2><p>ประเมินตนเองตามรอบการประเมิน ระบบคำนวณคะแนนและคะแนนถ่วงน้ำหนักให้อัตโนมัติ</p></div>
  <span class="badge <?= ($assessment['status'] ?? '') === 'submitted' ? 'badge-success' : 'badge-primary' ?>"><?= htmlspecialchars(component3StatusLabel($assessment['status'] ?? null)) ?></span></div>
</div>
<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<div class="card component3-filter"><form method="get"><div class="form-group"><label for="cycle_id">รอบการประเมิน</label><select class="form-control" name="cycle_id" id="cycle_id" onchange="this.form.submit()"><?php foreach ($cycles as $row): ?><option value="<?= (int)$row['id'] ?>" <?= (int)$row['id'] === $cycleId ? 'selected' : '' ?>><?= htmlspecialchars(component3CycleLabel($row)) ?></option><?php endforeach; ?></select></div></form></div>

<div class="card component3-person"><div><small>ผู้รับการประเมิน</small><strong><?= htmlspecialchars($person['fullname']) ?></strong></div><div><small>ตำแหน่ง / ระดับ</small><strong><?= htmlspecialchars($person['position_name'] . ' ' . $person['rank_name']) ?></strong></div><div><small>หน่วยบริการ</small><strong><?= htmlspecialchars($person['department_short_name'] ?: $person['department_name']) ?> (<?= htmlspecialchars($person['service_code']) ?>)</strong></div></div>

<?php if (!$person['includes_items_1_2']): ?><div class="alert alert-info">ข้อ 1–2 วัดเฉพาะบุคลากรใน สสอ. และผู้อำนวยการ รพ.สต. จึงไม่นำมาคำนวณสำหรับท่าน</div><?php endif; ?>
<?php if ($cycle['status'] !== 'active'): ?><div class="alert alert-warning">รอบการประเมินนี้ปิดแล้ว ไม่สามารถแก้ไขข้อมูลได้</div><?php elseif ($assessment && $assessment['status'] === 'submitted'): ?><div class="alert alert-info">ส่งผลประเมินแล้ว จึงไม่สามารถแก้ไขข้อมูลได้</div><?php endif; ?>

<form method="post" action="<?= htmlspecialchars(appUrl('process_component3.php')) ?>" id="component3Form">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
  <input type="hidden" name="cycle_id" value="<?= $cycleId ?>">
  <div class="card table-wrap component3-table-card"><table class="component3-table"><thead><tr><th>ข้อ</th><th>ตัวชี้วัด</th><th>น้ำหนัก</th><th>ค่าเป้าหมาย</th><th>ผลการดำเนินงาน</th><th>คะแนน (1–5)</th><th>คะแนนถ่วงน้ำหนัก</th></tr></thead><tbody>
  <?php foreach ($items as $itemNo => $item): $saved = $savedScores[$itemNo] ?? null; $auto = array_key_exists('automatic_score', $item); ?>
    <tr data-item="<?= $itemNo ?>" data-weight="<?= $item['weight'] ?>" data-target="<?= $item['target'] ?? '' ?>" data-input-type="<?= htmlspecialchars($item['input_type']) ?>" data-rules="<?= htmlspecialchars(json_encode($item['thresholds'], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>" data-auto-score="<?= $auto ? (int)$item['automatic_score'] : '' ?>">
      <td class="center"><span class="component3-number"><?= $itemNo ?></span></td>
      <td><strong><?= htmlspecialchars($item['name']) ?></strong><?php if ($auto): ?><small class="component3-note">คะแนนอัตโนมัติตามรหัสหน่วยบริการ <?= htmlspecialchars($person['service_code']) ?></small><?php endif; ?></td>
      <td class="center"><?= number_format($item['weight'], 0) ?>%</td><td class="center"><?= htmlspecialchars($item['target_label']) ?></td>
      <td><?php if ($auto): ?><div class="auto-value">คำนวณอัตโนมัติ</div><?php else: ?><input class="form-control actual-input" type="number" min="0" <?= $item['input_type'] === 'percentage' ? 'max="100" step="0.01"' : 'step="1"' ?> name="actual[<?= $itemNo ?>]" value="<?= $saved && $saved['actual_value'] !== null ? htmlspecialchars(rtrim(rtrim(number_format((float)$saved['actual_value'], 2, '.', ''), '0'), '.')) : '' ?>" <?= $readonly ? 'disabled' : '' ?>><?php endif; ?></td>
      <td class="center score-cell"><?= $saved ? number_format((float)$saved['score'], 0) : ($auto ? number_format((float)$item['automatic_score'], 0) : '-') ?></td>
      <td class="center weighted-cell"><?= $saved ? number_format((float)$saved['weighted_score'], 2) : ($auto ? number_format((float)$item['automatic_score'] / 5 * $item['weight'], 2) : '-') ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody><tfoot><tr><th colspan="2">รวม</th><th><?= number_format(array_sum(array_column($items, 'weight')), 0) ?>%</th><th colspan="3">คะแนนองค์ประกอบที่ 3 (ฐาน 100)</th><th id="finalScore"><?= $assessment ? number_format((float)$assessment['final_score'], 2) : '0.00' ?></th></tr></tfoot></table></div>
  <?php if (!$readonly): ?><div class="component3-actions"><button class="btn btn-secondary" type="submit" name="action" value="draft"><?= appIcon('save') ?> บันทึกฉบับร่าง</button><button class="btn btn-primary" type="submit" name="action" value="submit" onclick="return confirm('ยืนยันส่งผลประเมินหรือไม่ เมื่อส่งแล้วจะไม่สามารถแก้ไขได้')"><?= appIcon('send') ?> ยืนยันส่งผลประเมิน</button></div><?php endif; ?>
</form>
<style>
.component3-heading,.component3-filter,.component3-person{margin-bottom:1rem}.component3-heading .card-header{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap}.component3-heading p{margin:.35rem 0 0;color:var(--text-muted)}.component3-filter form{max-width:430px}.component3-filter .form-group{margin:0}.component3-person{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem}.component3-person small,.component3-person strong{display:block}.component3-person small{color:var(--text-muted);margin-bottom:.25rem}.component3-table-card{padding:0!important}.component3-table{min-width:980px}.component3-table th{text-align:center}.component3-number{display:inline-grid;place-items:center;width:34px;height:34px;border-radius:9px;background:var(--primary-color);color:#fff;font-weight:800}.component3-note{display:block;color:var(--text-muted);margin-top:.3rem}.auto-value{padding:.65rem;border:1px dashed var(--border-color);border-radius:8px;text-align:center;color:var(--text-muted);background:#f8fafc}.center{text-align:center}.component3-actions{display:flex;justify-content:flex-end;gap:.7rem;margin-top:1rem;flex-wrap:wrap}@media(max-width:800px){.component3-person{grid-template-columns:1fr}}
</style>
<script>
(() => { const rows=[...document.querySelectorAll('.component3-table tbody tr')], final=document.getElementById('finalScore'); function calc(){let total=0,weight=0; rows.forEach(row=>{const w=Number(row.dataset.weight),auto=row.dataset.autoScore,input=row.querySelector('.actual-input');weight+=w;if(auto===''&&input?.value===''){row.querySelector('.score-cell').textContent='-';row.querySelector('.weighted-cell').textContent='-';return;}let score=1;if(auto!==''){score=Number(auto)}else{const v=Number(input.value),rules=JSON.parse(row.dataset.rules||'{}');Object.entries(rules).sort((a,b)=>Number(a[0])-Number(b[0])).forEach(([threshold,level])=>{if(v>=Number(threshold))score=Number(level)});}const weighted=score/5*w;row.querySelector('.score-cell').textContent=score.toFixed(0);row.querySelector('.weighted-cell').textContent=weighted.toFixed(2);total+=weighted;});final.textContent=(weight?total/weight*100:0).toFixed(2)} rows.forEach(r=>r.querySelector('.actual-input')?.addEventListener('input',calc));calc(); })();
</script>
<?php require_once 'includes/footer.php'; ?>
