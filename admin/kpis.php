<?php
require_once '_bootstrap.php';
require_once __DIR__ . '/../includes/kpi_helper.php';

$cycles = $pdo->query('SELECT * FROM evaluation_cycles ORDER BY fiscal_year DESC, start_date DESC')->fetchAll();
$cycleInput = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['cycle_id'] ?? null) : ($_GET['cycle_id'] ?? null);
$cycleId = requestInt($cycleInput, 'cycle_id', (int)($cycles[0]['id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyAdminCsrf();
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save') {
            $id = requestInt($_POST['id'] ?? null, 'id', 0, 0);
            $cycleId = requestInt($_POST['cycle_id'] ?? null, 'cycle_id');
            $name = trim((string)($_POST['name'] ?? ''));
            $targetLabel = trim((string)($_POST['target_label'] ?? '')) ?: null;
            $unit = trim((string)($_POST['unit'] ?? '')) ?: null;
            $weight = (float)($_POST['weight'] ?? 0);
            $targetValue = ($_POST['target_value'] ?? '') === '' ? null : round((float)$_POST['target_value'], 2);
            $order = max(1, (int)($_POST['order_seq'] ?? 1));
            $active = isset($_POST['is_active']) ? 1 : 0;
            $thresholds = [];
            for ($level=1; $level<=5; $level++) {
                if (($_POST['score_' . $level] ?? '') === '') throw new RuntimeException('กรุณากรอกเกณฑ์คะแนนให้ครบ 1–5');
                $thresholds[] = (float)$_POST['score_' . $level];
            }
            $direction = $thresholds[0] <= $thresholds[4] ? 'ascending' : 'descending';
            if ($cycleId < 1 || $name === '' || $weight <= 0) throw new RuntimeException('กรุณากรอกชื่อรอบ ตัวชี้วัด และน้ำหนักให้ครบ');

            $pdo->beginTransaction();
            $itemCount = kpiNormalizeIndicatorOrder($pdo, $cycleId);
            if ($id > 0) {
                $currentStmt = $pdo->prepare('SELECT order_seq FROM kpi_indicators WHERE id=? AND cycle_id=? FOR UPDATE');
                $currentStmt->execute([$id,$cycleId]);
                $oldOrder = (int)$currentStmt->fetchColumn();
                if ($oldOrder < 1) throw new RuntimeException('ไม่พบตัวชี้วัดที่ต้องการแก้ไข');
                $order = min($order, max(1,$itemCount));
                if ($order < $oldOrder) {
                    $pdo->prepare('UPDATE kpi_indicators SET order_seq=order_seq+1 WHERE cycle_id=? AND id<>? AND order_seq>=? AND order_seq<?')
                        ->execute([$cycleId,$id,$order,$oldOrder]);
                } elseif ($order > $oldOrder) {
                    $pdo->prepare('UPDATE kpi_indicators SET order_seq=order_seq-1 WHERE cycle_id=? AND id<>? AND order_seq>? AND order_seq<=?')
                        ->execute([$cycleId,$id,$oldOrder,$order]);
                }
                $params = [$name,$targetLabel,$unit,$weight,$targetValue,...$thresholds,$direction,$order,$active,$id,$cycleId];
                $pdo->prepare('UPDATE kpi_indicators SET name=?,target_label=?,unit=?,weight=?,target_value=?,score_1_threshold=?,score_2_threshold=?,score_3_threshold=?,score_4_threshold=?,score_5_threshold=?,scoring_direction=?,order_seq=?,is_active=? WHERE id=? AND cycle_id=?')->execute($params);
            } else {
                $order = min($order, $itemCount + 1);
                $pdo->prepare('UPDATE kpi_indicators SET order_seq=order_seq+1 WHERE cycle_id=? AND order_seq>=?')
                    ->execute([$cycleId,$order]);
                $params = [$cycleId,$name,$targetLabel,$unit,$weight,$targetValue,...$thresholds,$direction,$order,$active];
                $pdo->prepare('INSERT INTO kpi_indicators (cycle_id,name,target_label,unit,weight,target_value,score_1_threshold,score_2_threshold,score_3_threshold,score_4_threshold,score_5_threshold,scoring_direction,order_seq,is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute($params);
            }
            kpiEnsureDirectorAssignments($pdo);
            $pdo->commit();
            adminRedirect('kpis.php?cycle_id=' . $cycleId, 'success', 'บันทึกตัวชี้วัดเรียบร้อย');
        }
        if ($action === 'delete') {
            $id = requestInt($_POST['id'] ?? null, 'id');
            $cycleId = requestInt($_POST['cycle_id'] ?? null, 'cycle_id');
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM kpi_indicators WHERE id=? AND cycle_id=?')->execute([$id,$cycleId]);
            kpiNormalizeIndicatorOrder($pdo,$cycleId);
            $pdo->commit();
            adminRedirect('kpis.php?cycle_id=' . $cycleId, 'success', 'ลบตัวชี้วัดเรียบร้อย');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        adminRedirect('kpis.php?cycle_id=' . $cycleId, 'error', $e->getMessage());
    }
}

$stmt = $pdo->prepare('SELECT k.*, (SELECT COUNT(*) FROM kpi_assignments a WHERE a.indicator_id=k.id) assignment_count, (SELECT COUNT(*) FROM kpi_results r WHERE r.indicator_id=k.id) result_count FROM kpi_indicators k WHERE k.cycle_id=? ORDER BY k.order_seq,k.id');
$stmt->execute([$cycleId]);
$indicators = $stmt->fetchAll();
$totalWeight = array_sum(array_map(fn($row)=>(float)$row['weight'], $indicators));

require_once '../includes/header.php';
adminPageHeader('ตัวชี้วัดผลสัมฤทธิ์ของงาน', 'เพิ่ม ลบ แก้ไขตัวชี้วัด เป้าหมาย น้ำหนัก และเกณฑ์คะแนนของแต่ละรอบประเมิน');
renderAdminFlash();
?>
<div class="card" style="margin-bottom:1rem;border-left:4px solid #059669">
  <div style="display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;flex-wrap:wrap;margin-bottom:1rem"><div><h3 style="margin:0"><?= appIcon('file-spreadsheet') ?> นำเข้าตัวชี้วัดจาก Excel</h3><p style="color:var(--text-muted);margin:.4rem 0 0">แอดมิน สสอ. นำเข้าตัวชี้วัด ค่าเป้าหมาย น้ำหนัก เกณฑ์คะแนน และผู้รับผิดชอบงานหลักของ สสอ.</p></div><a class="btn btn-secondary" href="kpi_template.php?cycle_id=<?= $cycleId ?>"><?= appIcon('download') ?> ดาวน์โหลด Template</a></div>
  <form method="post" action="kpi_import.php" enctype="multipart/form-data" style="display:flex;gap:.7rem;align-items:end;flex-wrap:wrap">
    <?= adminCsrfField() ?><input type="hidden" name="cycle_id" value="<?= $cycleId ?>">
    <div class="form-group" style="margin:0;min-width:300px;flex:1"><label>ไฟล์ตัวชี้วัด (.xlsx หรือ .xls)</label><input class="form-control" type="file" name="kpi_file" accept=".xlsx,.xls" required></div>
    <button class="btn btn-primary" type="submit"><?= appIcon('upload') ?> อัปโหลดและนำเข้า</button>
  </form>
  <small style="display:block;color:var(--text-muted);margin-top:.7rem">รายการจากไฟล์จะเรียงต่อจากตัวชี้วัดเดิม และใช้ร่วมกันทั้ง สสอ. กับ รพ.สต. ระบบกำหนดให้ ผอ.รพ.สต. ทุกคนรับผิดชอบโดยอัตโนมัติ</small>
</div>
<div class="card" style="margin-bottom:1rem">
  <form method="get" style="display:flex;gap:.8rem;align-items:end;flex-wrap:wrap">
    <div class="form-group" style="margin:0;min-width:280px"><label>รอบการประเมิน</label><select class="form-control" name="cycle_id" onchange="this.form.submit()"><?php foreach($cycles as $cycle): ?><option value="<?= (int)$cycle['id'] ?>" <?= $cycleId===(int)$cycle['id']?'selected':'' ?>><?= htmlspecialchars(kpiCycleLabel($cycle)) ?> (<?= $cycle['status']==='active'?'เปิด':'ปิด' ?>)</option><?php endforeach; ?></select></div>
    <div style="padding:.75rem 1rem;background:var(--primary-50);border-radius:8px"><strong>น้ำหนักรวม <?= number_format($totalWeight,2) ?></strong></div>
  </form>
</div>

<div class="card kpi-create-card" style="margin-bottom:1rem">
  <div class="kpi-create-header"><div class="kpi-create-icon"><?= appIcon('plus') ?></div><div><h3>เพิ่มตัวชี้วัด</h3><p>กรอกข้อมูลพื้นฐาน เป้าหมาย และเกณฑ์คะแนน ระบบจะแทรกลำดับให้อัตโนมัติ</p></div></div>
  <form method="post" class="kpi-create-form">
    <?= adminCsrfField() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="0"><input type="hidden" name="cycle_id" value="<?= $cycleId ?>">
    <section class="kpi-form-section">
      <div class="kpi-section-title"><span>1</span><div><strong>ข้อมูลตัวชี้วัด</strong><small>ระบุลำดับและชื่อตัวชี้วัด</small></div></div>
      <div class="kpi-basic-grid">
        <div class="form-group"><label>ลำดับที่</label><input class="form-control" type="number" min="1" max="<?= count($indicators)+1 ?>" name="order_seq" value="<?= count($indicators)+1 ?>" required><small>หากลำดับซ้ำ ระบบจะแทรกรายการนี้และเลื่อนรายการเดิม</small></div>
        <div class="form-group kpi-field-wide"><label>ชื่อตัวชี้วัด <span class="required-mark">*</span></label><textarea class="form-control" name="name" rows="3" required placeholder="ระบุชื่อตัวชี้วัดให้ชัดเจน"></textarea></div>
        <label class="kpi-active-toggle"><input type="checkbox" name="is_active" checked><span><strong>เปิดใช้งาน</strong><small>แสดงตัวชี้วัดในรอบนี้</small></span></label>
      </div>
    </section>
    <section class="kpi-form-section">
      <div class="kpi-section-title"><span>2</span><div><strong>เป้าหมายและน้ำหนัก</strong><small>กำหนดค่าที่ใช้แสดงผลและคำนวณคะแนน</small></div></div>
      <div class="kpi-target-grid">
        <div class="form-group"><label>ค่าเป้าหมาย (ข้อความ)</label><input class="form-control" name="target_label" placeholder="เช่น ร้อยละ 100"></div>
        <div class="form-group"><label>เป้าหมาย (ตัวเลข)</label><input class="form-control" type="number" step="0.01" name="target_value" placeholder="0.00"></div>
        <div class="form-group"><label>หน่วย</label><input class="form-control" name="unit" placeholder="เช่น ร้อยละ, แห่ง, คน"></div>
        <div class="form-group"><label>น้ำหนัก <span class="required-mark">*</span></label><input class="form-control" type="number" min="0.01" step="0.01" name="weight" required placeholder="0.00"></div>
      </div>
    </section>
    <section class="kpi-form-section">
      <div class="kpi-section-title"><span>3</span><div><strong>เกณฑ์คะแนน</strong><small>กรอกค่าเกณฑ์สำหรับคะแนน 1–5 ให้ครบ</small></div></div>
      <div class="kpi-score-grid"><?php for($level=1;$level<=5;$level++): ?><label class="kpi-score-field"><span class="kpi-score-number"><?= $level ?></span><span>คะแนน <?= $level ?></span><input class="form-control" type="number" step="0.0001" name="score_<?= $level ?>" required placeholder="เกณฑ์คะแนน"></label><?php endfor; ?></div>
    </section>
    <div class="kpi-form-actions"><button class="btn btn-primary" type="submit"><?= appIcon('save') ?> บันทึกตัวชี้วัด</button></div>
  </form>
</div>

<div class="card">
  <h3>รายการตัวชี้วัด</h3>
  <div class="table-wrap"><table><thead><tr><th>ลำดับ/ตัวชี้วัด</th><th>เป้าหมาย</th><th>น้ำหนัก</th><th>เกณฑ์คะแนน 1–5</th><th>สถานะ</th><th>จัดการ</th></tr></thead><tbody>
  <?php foreach($indicators as $row): $fid='kpi-'.$row['id']; ?>
    <tr>
      <td style="min-width:300px"><div style="display:flex;gap:.5rem"><input form="<?= $fid ?>" class="form-control" style="width:70px" type="number" min="1" name="order_seq" value="<?= (int)$row['order_seq'] ?>"><textarea form="<?= $fid ?>" class="form-control" name="name" rows="3" required><?= htmlspecialchars($row['name']) ?></textarea></div></td>
      <td style="min-width:190px"><input form="<?= $fid ?>" class="form-control" name="target_label" value="<?= htmlspecialchars($row['target_label']??'') ?>"><div style="display:flex;gap:.4rem;margin-top:.4rem"><input form="<?= $fid ?>" class="form-control" type="number" step=".01" name="target_value" value="<?= $row['target_value']!==null?number_format((float)$row['target_value'],2,'.',''):'' ?>"><input form="<?= $fid ?>" class="form-control" name="unit" value="<?= htmlspecialchars($row['unit']??'') ?>"></div></td>
      <td><input form="<?= $fid ?>" class="form-control" style="width:85px" type="number" min=".01" step=".01" name="weight" value="<?= htmlspecialchars($row['weight']) ?>"></td>
      <td style="min-width:290px"><div style="display:grid;grid-template-columns:repeat(5,1fr);gap:.3rem"><?php for($level=1;$level<=5;$level++): ?><input form="<?= $fid ?>" class="form-control" type="number" step=".0001" name="score_<?= $level ?>" title="คะแนน <?= $level ?>" value="<?= htmlspecialchars($row['score_'.$level.'_threshold']) ?>"><?php endfor; ?></div></td>
      <td><label><input form="<?= $fid ?>" type="checkbox" name="is_active" <?= $row['is_active']?'checked':'' ?>> ใช้งาน</label><small style="display:block;color:var(--text-muted)"><?= (int)$row['assignment_count'] ?> ผู้รับผิดชอบ<br><?= (int)$row['result_count'] ?> ผลคะแนน</small></td>
      <td style="min-width:190px"><div style="display:flex;gap:.4rem;flex-wrap:wrap"><form method="post" id="<?= $fid ?>"><?= adminCsrfField() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="cycle_id" value="<?= $cycleId ?>"><button class="btn btn-secondary" type="submit"><?= appIcon('save') ?> บันทึก</button></form><form method="post" onsubmit="return confirm('ยืนยันการลบตัวชี้วัดนี้? การมอบหมายและคะแนนที่เกี่ยวข้องจะถูกลบด้วย')"><?= adminCsrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="cycle_id" value="<?= $cycleId ?>"><button class="btn btn-danger" type="submit"><?= appIcon('x-circle') ?> ลบ</button></form></div></td>
    </tr>
  <?php endforeach; ?>
  <?php if(!$indicators): ?><tr><td colspan="6" style="text-align:center;color:var(--text-muted)">ยังไม่มีตัวชี้วัดในรอบนี้</td></tr><?php endif; ?>
  </tbody></table></div>
</div>
<?php require_once '../includes/footer.php'; ?>
