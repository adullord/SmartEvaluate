<?php
require_once '_bootstrap.php';
require_once __DIR__ . '/../includes/database_schema_helper.php';

$updateActions = $_SESSION['schema_update_actions'] ?? [];
unset($_SESSION['schema_update_actions']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyAdminCsrf();
        if (($_POST['confirm_backup'] ?? '') !== '1') {
            throw new RuntimeException('กรุณายืนยันว่าได้สำรองฐานข้อมูลแล้ว');
        }
        $actions = applyAppDatabaseSchema($pdo, (int) $_SESSION['user_id']);
        $_SESSION['schema_update_actions'] = $actions;
        adminRedirect(
            'database_schema.php',
            'success',
            $actions ? 'ปรับโครงสร้างฐานข้อมูลเรียบร้อย ' . count($actions) . ' รายการ' : 'โครงสร้างฐานข้อมูลเป็นปัจจุบันแล้ว'
        );
    } catch (Throwable $exception) {
        adminRedirect('database_schema.php', 'error', 'ไม่สามารถปรับโครงสร้างฐานข้อมูล: ' . $exception->getMessage());
    }
}

$schemaStatus = inspectAppDatabaseSchema($pdo);
$missingTableCount = count(array_filter($schemaStatus, static fn(array $status): bool => !$status['exists']));
$missingColumnCount = array_sum(array_map(static fn(array $status): int => count($status['missing_columns']), $schemaStatus));
$needsUpdate = $missingTableCount > 0 || $missingColumnCount > 0;

$history = [];
if (($schemaStatus['schema_migrations']['exists'] ?? false) === true && empty($schemaStatus['schema_migrations']['missing_columns'])) {
    $history = $pdo->query('SELECT sm.*,u.fullname FROM schema_migrations sm LEFT JOIN users u ON u.id=sm.executed_by ORDER BY sm.id DESC LIMIT 5')->fetchAll();
}

require_once '../includes/header.php';
adminPageHeader(appIcon('settings') . ' ปรับโครงสร้างฐานข้อมูล', 'ตรวจสอบและเพิ่มเฉพาะตารางหรือคอลัมน์ที่ระบบต้องใช้ โดยไม่ลบข้อมูลเดิม');
renderAdminFlash();
?>

<?php if ($updateActions): ?>
<div class="card schema-result-card">
    <h3><?= appIcon('check-circle') ?> รายการที่ดำเนินการสำเร็จ</h3>
    <ul><?php foreach ($updateActions as $action): ?><li><?= htmlspecialchars($action) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="schema-overview">
    <div class="card schema-stat"><strong><?= count($schemaStatus) ?></strong><span>ตารางที่ตรวจสอบ</span></div>
    <div class="card schema-stat <?= $missingTableCount ? 'has-warning' : 'is-ready' ?>"><strong><?= $missingTableCount ?></strong><span>ตารางที่ขาด</span></div>
    <div class="card schema-stat <?= $missingColumnCount ? 'has-warning' : 'is-ready' ?>"><strong><?= $missingColumnCount ?></strong><span>คอลัมน์ที่ขาด</span></div>
</div>

<div class="card schema-action-card">
    <div>
        <h3><?= $needsUpdate ? appIcon('triangle-alert') . ' พบโครงสร้างที่ต้องปรับปรุง' : appIcon('check-circle') . ' โครงสร้างฐานข้อมูลเป็นปัจจุบัน' ?></h3>
        <p>ระบบจะเพิ่มเฉพาะสิ่งที่ขาด ไม่มีคำสั่ง DROP, TRUNCATE หรือล้างข้อมูล แต่ควรสำรองฐานข้อมูลทุกครั้งก่อนดำเนินการ</p>
    </div>
    <form method="post" onsubmit="return confirm('ยืนยันปรับโครงสร้างฐานข้อมูล? ควรดำเนินการหลังจากสำรองฐานข้อมูลแล้ว')">
        <?= adminCsrfField() ?>
        <label class="schema-backup-confirm"><input type="checkbox" name="confirm_backup" value="1" required> ฉันได้สำรองฐานข้อมูลแล้ว</label>
        <button class="btn btn-primary" type="submit" <?= !$needsUpdate ? 'disabled' : '' ?>><?= appIcon('settings') ?> ปรับโครงสร้างฐานข้อมูล</button>
    </form>
</div>

<?php if ($history): ?>
<div class="card schema-history-card">
    <h3><?= appIcon('history') ?> ประวัติการปรับโครงสร้างล่าสุด</h3>
    <div class="table-wrap"><table><thead><tr><th>วันเวลา</th><th>ผู้ดำเนินการ</th><th>รายการ</th></tr></thead><tbody>
    <?php foreach ($history as $entry): ?><tr><td><?= htmlspecialchars($entry['created_at']) ?></td><td><?= htmlspecialchars($entry['fullname'] ?: 'ไม่ทราบ') ?></td><td><?= htmlspecialchars($entry['summary']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</div>
<?php endif; ?>

<style>
.schema-overview{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem;margin-bottom:1rem}.schema-stat{text-align:center;padding:1.15rem}.schema-stat strong{display:block;font-size:2rem;color:var(--primary-color)}.schema-stat span{color:var(--text-muted)}.schema-stat.has-warning strong{color:#b45309}.schema-stat.is-ready strong{color:#047857}.schema-action-card{display:flex;justify-content:space-between;align-items:center;gap:1.25rem;margin-bottom:1rem;border-left:4px solid var(--primary-color)}.schema-action-card h3{margin:0 0 .4rem}.schema-action-card p,.schema-muted{color:var(--text-muted);margin:0}.schema-action-card form{display:flex;align-items:center;gap:.8rem;flex-wrap:wrap;justify-content:flex-end}.schema-backup-confirm{display:flex;align-items:center;gap:.45rem;font-weight:600;cursor:pointer}.schema-status{display:inline-flex;align-items:center;gap:.35rem;padding:.3rem .6rem;border-radius:999px;font-weight:700;font-size:.84rem}.schema-status.ready{background:#d1fae5;color:#065f46}.schema-status.pending{background:#fef3c7;color:#92400e}.schema-status .app-icon{width:16px;height:16px}.schema-result-card{margin-bottom:1rem;border-left:4px solid #10b981}.schema-result-card h3{color:#065f46}.schema-result-card ul{margin:.5rem 0 0;padding-left:1.25rem}.schema-history-card{margin-top:1rem}.schema-history-card h3{margin-top:0}code{font-family:ui-monospace,SFMono-Regular,Consolas,monospace}@media(max-width:800px){.schema-overview{grid-template-columns:1fr}.schema-action-card{align-items:stretch;flex-direction:column}.schema-action-card form{justify-content:flex-start}}
</style>
<?php require_once '../includes/footer.php'; ?>
