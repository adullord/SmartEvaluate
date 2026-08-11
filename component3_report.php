<?php
require_once 'config.php';
require_once __DIR__ . '/includes/component3_helper.php';

if (!isset($_SESSION['user_id'])) { header('Location: ' . appUrl('login.php')); exit; }
$viewerId = (int)$_SESSION['user_id'];
$role = (string)($_SESSION['role'] ?? 'staff');
$cycles = $pdo->query('SELECT id,fiscal_year,round_name,start_date,end_date,status FROM evaluation_cycles ORDER BY fiscal_year DESC,start_date DESC,id DESC')->fetchAll();
$cycleId = requestInt($_GET['cycle_id'] ?? null, 'cycle_id', (int)($cycles[0]['id'] ?? 0));

$sql = "SELECT a.*,u.fullname,u.role,d.service_code,d.short_name department_name,p.name position_name,r.name rank_name
    FROM component3_assessments a JOIN users u ON u.id=a.user_id JOIN departments d ON d.id=u.department_id
    JOIN positions p ON p.id=u.position_id JOIN ranks r ON r.id=u.rank_id WHERE a.cycle_id=?";
$params = [$cycleId];
if ($role !== 'admin') {
    if (in_array($role, ['ss_amphoe','director'], true)) {
        $sql .= ' AND (a.user_id=? OR EXISTS(SELECT 1 FROM evaluator_mapping em WHERE em.cycle_id=a.cycle_id AND em.evaluatee_id=a.user_id AND em.evaluator_id=?))';
        $params[] = $viewerId; $params[] = $viewerId;
    } else { $sql .= ' AND a.user_id=?'; $params[] = $viewerId; }
}
$sql .= ' ORDER BY d.type,d.short_name,u.fullname';
$stmt = $pdo->prepare($sql); $stmt->execute($params); $assessments = $stmt->fetchAll();

require_once 'includes/header.php';
?>
<div class="card c3-report-heading"><div class="card-header"><div><h2 class="card-title"><?= appIcon('file-text') ?> รายงานองค์ประกอบที่ 3</h2><p>สรุปผลประเมินงานมอบหมายพิเศษตามสิทธิ์ที่ได้รับ</p></div></div></div>
<div class="card c3-report-filter"><form method="get"><div class="form-group"><label for="cycle_id">รอบการประเมิน</label><select class="form-control" id="cycle_id" name="cycle_id" onchange="this.form.submit()"><?php foreach ($cycles as $cycle): ?><option value="<?= (int)$cycle['id'] ?>" <?= (int)$cycle['id'] === $cycleId ? 'selected' : '' ?>><?= htmlspecialchars(component3CycleLabel($cycle)) ?></option><?php endforeach; ?></select></div></form></div>
<div class="card c3-report-table"><div class="table-wrap"><table><thead><tr><th>ลำดับ</th><th>ผู้รับการประเมิน</th><th>ตำแหน่ง / ระดับ</th><th>หน่วยบริการ</th><th>สถานะ</th><th>คะแนน (100)</th><th>รายงาน</th></tr></thead><tbody>
<?php foreach ($assessments as $index => $row): ?><tr><td class="center"><?= $index + 1 ?></td><td><strong><?= htmlspecialchars($row['fullname']) ?></strong></td><td><?= htmlspecialchars($row['position_name'] . ' ' . $row['rank_name']) ?></td><td><?= htmlspecialchars($row['department_name'] ?: $row['service_code']) ?></td><td><span class="badge <?= $row['status'] === 'submitted' ? 'badge-success' : 'badge-primary' ?>"><?= htmlspecialchars(component3StatusLabel($row['status'])) ?></span></td><td class="center"><strong><?= number_format((float)$row['final_score'], 2) ?></strong></td><td class="center"><a class="btn btn-sm btn-primary" target="_blank" rel="noopener" href="<?= htmlspecialchars(appUrl('export_component3_pdf.php')) ?>?id=<?= (int)$row['id'] ?>"><?= appIcon('file-text') ?> PDF</a></td></tr><?php endforeach; ?>
<?php if (!$assessments): ?><tr><td colspan="7" class="empty">ยังไม่มีผลประเมินในรอบนี้</td></tr><?php endif; ?>
</tbody></table></div></div>
<style>.c3-report-heading,.c3-report-filter{margin-bottom:1rem}.c3-report-heading .card-header{display:flex;justify-content:space-between}.c3-report-heading p{margin:.35rem 0 0;color:var(--text-muted)}.c3-report-filter form{max-width:430px}.c3-report-filter .form-group{margin:0}.c3-report-table{padding:0!important}.center{text-align:center}.empty{text-align:center;color:var(--text-muted);padding:2rem!important}</style>
<?php require_once 'includes/footer.php'; ?>
