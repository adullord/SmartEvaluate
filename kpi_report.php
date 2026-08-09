<?php
require_once 'config.php';
require_once __DIR__ . '/includes/kpi_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . appUrl('login.php'));
    exit;
}

$userId = (int) $_SESSION['user_id'];
$role = (string) ($_SESSION['role'] ?? 'staff');
$reportRoles = ['admin', 'ss_amphoe', 'director'];
if (!in_array($role, $reportRoles, true)) {
    http_response_code(403);
    die('ไม่มีสิทธิ์ดูรายงานตัวชี้วัด');
}

$cycles = $pdo->query('SELECT * FROM evaluation_cycles ORDER BY fiscal_year DESC,start_date DESC')->fetchAll();
$cycleId = requestInt($_GET['cycle_id'] ?? null, 'cycle_id', (int) ($cycles[0]['id'] ?? 0));
$departments = kpiAllowedDepartments($pdo, $userId, $role);
$departmentIds = array_map(static fn(array $department): int => (int) $department['id'], $departments);

$stmt = $pdo->prepare('SELECT * FROM kpi_indicators WHERE cycle_id=? AND is_active=1 ORDER BY order_seq,id');
$stmt->execute([$cycleId]);
$indicators = $stmt->fetchAll();
$results = [];
$responsible = [];

if ($indicators) {
    $indicatorIds = array_map(static fn(array $indicator): int => (int) $indicator['id'], $indicators);
    $indicatorMarks = implode(',', array_fill(0, count($indicatorIds), '?'));
    if ($departmentIds) {
        $departmentMarks = implode(',', array_fill(0, count($departmentIds), '?'));
        $stmt = $pdo->prepare("SELECT r.*,u.fullname FROM kpi_results r JOIN users u ON u.id=r.entered_by WHERE r.indicator_id IN ($indicatorMarks) AND r.department_id IN ($departmentMarks)");
        $stmt->execute([...$indicatorIds, ...$departmentIds]);
        foreach ($stmt->fetchAll() as $result) {
            $results[(int) $result['indicator_id']][(int) $result['department_id']] = $result;
        }
    }
    if ($departmentIds) {
        $departmentMarks = implode(',', array_fill(0, count($departmentIds), '?'));
        $stmt = $pdo->prepare("SELECT a.indicator_id,u.fullname,a.responsibility_type,d.short_name FROM kpi_assignments a JOIN users u ON u.id=a.user_id JOIN departments d ON d.id=u.department_id WHERE a.indicator_id IN ($indicatorMarks) AND u.department_id IN ($departmentMarks) ORDER BY FIELD(a.responsibility_type,'primary','secondary'),u.fullname");
        $stmt->execute([...$indicatorIds, ...$departmentIds]);
        foreach ($stmt->fetchAll() as $assignment) {
            $responsible[(int) $assignment['indicator_id']][] = $assignment;
        }
    }
}

require_once 'includes/header.php';
?>
<div class="card kpi-report-heading">
    <div class="card-header">
        <div>
            <h2 class="card-title"><?= appIcon('bar-chart') ?> รายงานผลตัวชี้วัด</h2>
            <p>ติดตามผลรายหน่วยบริการ ผลรวม และผู้รับผิดชอบของแต่ละตัวชี้วัด</p>
        </div>
        <?php if ($role === 'admin'): ?><span class="badge badge-primary">ผู้ดูแลระบบ</span><?php endif; ?>
    </div>
</div>

<div class="card kpi-report-filter">
    <form method="get">
        <div class="form-group">
            <label for="cycle_id">รอบการประเมิน</label>
            <select class="form-control" id="cycle_id" name="cycle_id" onchange="this.form.submit()">
                <?php foreach ($cycles as $cycle): ?>
                    <option value="<?= (int) $cycle['id'] ?>" <?= $cycleId === (int) $cycle['id'] ? 'selected' : '' ?>><?= htmlspecialchars(kpiCycleLabel($cycle)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<?php foreach ($indicators as $indicatorIndex => $indicator): ?>
    <?php
    $indicatorId = (int) $indicator['id'];
    $indicatorResults = $results[$indicatorId] ?? [];
    ?>
    <details class="card kpi-report-accordion" <?= $indicatorIndex === 0 ? 'open' : '' ?>>
        <summary class="kpi-report-accordion-header">
            <span class="kpi-report-number"><?= (int) $indicator['order_seq'] ?></span>
            <span class="kpi-report-title">
                <strong><?= htmlspecialchars($indicator['name']) ?></strong>
                <small>บันทึกผลแล้ว <?= count($indicatorResults) ?>/<?= count($departments) ?> หน่วยบริการ</small>
            </span>
            <span class="kpi-report-meta">
                <span>เป้าหมาย <?= htmlspecialchars($indicator['target_label'] ?: '-') ?></span>
                <span>น้ำหนัก <?= number_format((float) $indicator['weight'], 2) ?></span>
            </span>
            <span class="kpi-report-chevron" aria-hidden="true">⌄</span>
        </summary>
        <div class="kpi-report-accordion-content">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>หน่วยบริการ</th><th>ผลการดำเนินงาน</th><th>ร้อยละ</th><th>คะแนน</th><th>คะแนนถ่วงน้ำหนัก</th><th>ปรับปรุงล่าสุดโดย</th></tr></thead>
                    <tbody>
                    <?php foreach ($departments as $department): ?>
                        <?php $result = $indicatorResults[(int) $department['id']] ?? null; ?>
                        <tr>
                            <td><strong><?= $department['type'] === 'SSO' ? 'รวม' : htmlspecialchars($department['short_name'] ?: $department['name']) ?></strong></td>
                            <td><?= $result ? number_format((float) $result['actual_value'], 2) : '-' ?></td>
                            <td><?= $result ? number_format((float) $result['percentage'], 2) : '-' ?></td>
                            <td><?= $result ? number_format((float) $result['score'], 2) : '-' ?></td>
                            <td><?= $result ? number_format((float) $result['weighted_score'], 2) : '-' ?></td>
                            <td><?= $result ? htmlspecialchars($result['fullname']) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </details>
<?php endforeach; ?>

<?php if (!$indicators): ?><div class="card kpi-report-empty-state"><?= appIcon('inbox') ?> ยังไม่มีตัวชี้วัดในรอบนี้</div><?php endif; ?>

<style>
.kpi-report-heading,.kpi-report-filter{margin-bottom:1rem}.kpi-report-heading .card-header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap}.kpi-report-heading p{color:var(--text-muted);margin:.4rem 0 0}.kpi-report-filter form{max-width:420px}.kpi-report-filter .form-group{margin:0}
.kpi-report-accordion{padding:0!important;margin-bottom:.8rem;overflow:hidden}.kpi-report-accordion>summary{list-style:none}.kpi-report-accordion>summary::-webkit-details-marker{display:none}.kpi-report-accordion-header{display:grid;grid-template-columns:42px minmax(260px,1fr) auto 28px;gap:.8rem;align-items:center;padding:1rem 1.15rem;cursor:pointer;background:#fff;transition:.18s ease}.kpi-report-accordion-header:hover{background:var(--primary-50)}.kpi-report-accordion[open]>.kpi-report-accordion-header{background:var(--primary-50);border-bottom:1px solid var(--border-color)}
.kpi-report-number{width:38px;height:38px;border-radius:10px;display:grid;place-items:center;background:var(--primary-color);color:#fff;font-weight:800}.kpi-report-title strong{display:block;font-size:1.02rem;line-height:1.45}.kpi-report-title small{display:block;color:var(--text-muted);margin-top:.2rem}.kpi-report-meta{display:flex;gap:.4rem;flex-wrap:wrap;justify-content:flex-end}.kpi-report-meta span{padding:.35rem .6rem;border-radius:999px;background:#fff;border:1px solid var(--border-color);font-size:.82rem;white-space:nowrap}.kpi-report-chevron{font-size:1.45rem;color:var(--primary-color);transition:transform .18s ease;text-align:center}.kpi-report-accordion[open] .kpi-report-chevron{transform:rotate(180deg)}
.kpi-report-accordion-content{padding:1rem 1.15rem 1.2rem}.kpi-report-responsible{display:flex;align-items:flex-start;gap:.65rem;flex-wrap:wrap;margin-bottom:1rem}.kpi-report-responsible>strong{padding:.32rem 0}.kpi-report-responsible-list{display:flex;gap:.45rem;flex-wrap:wrap}.kpi-report-person{display:inline-flex;align-items:center;gap:.4rem;padding:.32rem .65rem;border:1px solid var(--border-color);border-radius:999px;background:#f8fafc}.kpi-report-person small{padding:.08rem .38rem;border-radius:999px;background:var(--primary-50);color:var(--primary-color);font-weight:700}.kpi-report-empty{color:var(--text-muted);padding:.32rem 0}.kpi-report-empty-state{text-align:center;color:var(--text-muted);padding:2rem}
@media(max-width:900px){.kpi-report-accordion-header{grid-template-columns:42px minmax(0,1fr) 26px}.kpi-report-meta{grid-column:2/4;justify-content:flex-start}}@media(max-width:560px){.kpi-report-accordion-header{padding:.85rem;gap:.6rem}.kpi-report-meta span{font-size:.76rem}.kpi-report-accordion-content{padding:.85rem}}
</style>
<?php require_once 'includes/footer.php'; ?>
