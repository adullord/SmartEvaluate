<?php
require_once 'config.php';
require_once __DIR__ . '/includes/performance_summary_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . appUrl('login.php'));
    exit;
}

$viewerId = (int)$_SESSION['user_id'];
$viewerRole = (string)($_SESSION['role'] ?? 'staff');
$cycles = $pdo->query('SELECT id,fiscal_year,round_name,start_date,end_date,status FROM evaluation_cycles ORDER BY fiscal_year DESC,start_date DESC,id DESC')->fetchAll();
$cycleId = requestInt($_GET['cycle_id'] ?? null, 'cycle_id', (int)($cycles[0]['id'] ?? 0));
$users = performanceSummaryVisibleUsers($pdo, $viewerId, $viewerRole);
$overview = $cycleId > 0 ? performanceSummaryOverview($pdo, $cycleId, $users) : [];

require_once 'includes/header.php';
?>
<div class="card performance-heading">
    <div class="card-header">
        <div>
            <h2 class="card-title"><?= appIcon('clipboard-list') ?> รายงานสรุปผลการปฏิบัติราชการ</h2>
            <p>สรุปผลสัมฤทธิ์ของงาน สมรรถนะ และงานมอบหมายพิเศษ ตามสิทธิ์การเข้าถึงของคุณ</p>
        </div>
    </div>
</div>

<div class="card performance-filter">
    <form method="get">
        <div class="form-group">
            <label for="cycle_id">รอบการประเมิน</label>
            <select class="form-control" id="cycle_id" name="cycle_id" onchange="this.form.submit()">
                <?php foreach ($cycles as $cycle): ?>
                    <option value="<?= (int)$cycle['id'] ?>" <?= $cycleId === (int)$cycle['id'] ? 'selected' : '' ?>><?= htmlspecialchars(performanceSummaryCycleLabel($cycle)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if (count($users) > 1): ?>
        <div class="form-group search-group">
            <label for="summary-search">ค้นหาบุคลากร</label>
            <div class="search-input"><span><?= appIcon('search') ?></span><input class="form-control" id="summary-search" type="search" placeholder="ชื่อ ตำแหน่ง หรือหน่วยบริการ"></div>
        </div>
        <?php endif; ?>
    </form>
</div>

<div class="card performance-table-card">
    <div class="table-wrap">
        <table id="performance-summary-table">
            <thead><tr><th class="center">ลำดับ</th><th>ผู้รับการประเมิน</th><th>ตำแหน่ง / ระดับ</th><th>หน่วยบริการ</th><th class="center">องค์ประกอบ 1<br><small>70%</small></th><th class="center">องค์ประกอบ 2<br><small>15%</small></th><th class="center">องค์ประกอบ 3<br><small>15%</small></th><th class="center">คะแนนรวม</th><th class="center">ระดับผล</th><th class="center">ดาวน์โหลด</th></tr></thead>
            <tbody>
            <?php foreach ($users as $index => $user): $score = $overview[(int)$user['id']] ?? []; ?>
                <tr data-search="<?= htmlspecialchars(mb_strtolower($user['fullname'].' '.$user['position_name'].' '.$user['rank_name'].' '.$user['department_name']), ENT_QUOTES, 'UTF-8') ?>">
                    <td class="center row-number"><?= $index + 1 ?></td>
                    <td><strong><?= htmlspecialchars($user['fullname']) ?></strong></td>
                    <td><?= htmlspecialchars($user['position_name'].' '.$user['rank_name']) ?></td>
                    <td><?= htmlspecialchars($user['department_short_name'] ?: $user['department_name']) ?></td>
                    <?php foreach (['kpi_score','competency_score','component3_score'] as $key): ?>
                        <td class="center score-cell"><?= isset($score[$key]) && $score[$key] !== null ? number_format((float)$score[$key], 2) : '<span class="muted">-</span>' ?></td>
                    <?php endforeach; ?>
                    <td class="center total-score"><?= isset($score['overall_score']) && $score['overall_score'] !== null ? number_format((float)$score['overall_score'], 2) : '<span class="muted">รอผลครบ</span>' ?></td>
                    <td class="center"><span class="rating-badge"><?= htmlspecialchars((string)($score['rating'] ?? '-')) ?></span></td>
                    <td class="center"><a class="btn btn-danger btn-sm" href="<?= htmlspecialchars(appUrl('export_performance_summary_pdf.php')) ?>?cycle_id=<?= $cycleId ?>&amp;user_id=<?= (int)$user['id'] ?>"><?= appIcon('download') ?> PDF</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$users): ?><tr><td colspan="10" class="empty">ไม่พบบุคลากรตามสิทธิ์ของคุณ</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.performance-heading,.performance-filter{margin-bottom:1rem}.performance-heading p{margin:.35rem 0 0;color:var(--text-muted)}.performance-filter form{display:grid;grid-template-columns:minmax(260px,420px) minmax(260px,1fr);gap:1rem;align-items:end}.performance-filter .form-group{margin:0}.search-input{position:relative}.search-input>span{position:absolute;left:.85rem;top:50%;transform:translateY(-50%);color:var(--text-muted);display:flex}.search-input input{padding-left:2.7rem}.performance-table-card{padding:0!important}.performance-table-card th small{font-weight:500}.center{text-align:center}.score-cell{font-variant-numeric:tabular-nums}.total-score{font-weight:800;color:var(--primary-color);white-space:nowrap}.muted{color:var(--text-muted);font-weight:400}.rating-badge{display:inline-block;min-width:76px;padding:.28rem .55rem;border-radius:999px;background:var(--primary-50);color:var(--primary-color);font-weight:700}.empty{text-align:center;color:var(--text-muted);padding:2rem!important}@media(max-width:760px){.performance-filter form{grid-template-columns:1fr}}
</style>
<script>
(() => {
    const input = document.getElementById('summary-search');
    if (!input) return;
    const rows = Array.from(document.querySelectorAll('#performance-summary-table tbody tr[data-search]'));
    input.addEventListener('input', () => {
        const term = input.value.trim().toLocaleLowerCase('th-TH');
        let number = 0;
        rows.forEach(row => {
            const visible = !term || (row.dataset.search || '').includes(term);
            row.hidden = !visible;
            if (visible) row.querySelector('.row-number').textContent = String(++number);
        });
    });
})();
</script>
<?php require_once 'includes/footer.php'; ?>
