<?php
require_once '_bootstrap.php';
$logs=$pdo->query("SELECT l.*,u.fullname actor,ee.fullname evaluatee_name,er.fullname evaluator_name,d.name dept_name FROM evaluation_logs l JOIN users u ON u.id=l.user_id JOIN evaluations e ON e.id=l.evaluation_id JOIN users ee ON ee.id=e.evaluatee_id JOIN users er ON er.id=e.evaluator_id JOIN departments d ON d.id=ee.department_id ORDER BY l.created_at DESC,l.id DESC LIMIT 500")->fetchAll();
require_once '../includes/header.php'; adminPageHeader(appIcon('history') . ' ประวัติการแก้ไข','แสดงประวัติการทำรายการของแบบประเมินล่าสุดไม่เกิน 500 รายการ');
?>
<div class="card"><div style="overflow:auto"><table><thead><tr><th>วันเวลา</th><th>รายการ</th><th>ผู้ดำเนินการ</th><th>ผู้รับการประเมิน</th><th>ผู้ประเมิน</th><th>หน่วยงาน</th></tr></thead><tbody><?php foreach($logs as $l): ?><tr><td><?= htmlspecialchars($l['created_at']) ?></td><td><?= htmlspecialchars($l['action']) ?></td><td><?= htmlspecialchars($l['actor']) ?></td><td><?= htmlspecialchars($l['evaluatee_name']) ?></td><td><?= htmlspecialchars($l['evaluator_name']) ?></td><td><?= htmlspecialchars($l['dept_name']) ?></td></tr><?php endforeach ?><?php if(!$logs): ?><tr><td colspan="6" style="text-align:center;color:var(--text-muted)">ยังไม่มีประวัติการทำรายการ</td></tr><?php endif ?></tbody></table></div></div>
<?php require_once '../includes/footer.php'; ?>
