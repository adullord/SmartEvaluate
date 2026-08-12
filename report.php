<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// SQL เป็นข้อความคงที่ทั้งหมด และ bind ค่าที่เปลี่ยนแปลงทุกค่า
$stmt = $pdo->prepare("
    SELECT e.*, 
           u.fullname as evaluatee_name, p.name as pos_name, r.name as rank_name, d.name as dept_name,
           u2.fullname as evaluator_name,
           c.round_name, c.fiscal_year
    FROM evaluations e
    JOIN users u ON e.evaluatee_id = u.id
    JOIN positions p ON u.position_id = p.id
    JOIN ranks r ON u.rank_id = r.id
    JOIN departments d ON u.department_id = d.id
    JOIN users u2 ON e.evaluator_id = u2.id
    JOIN evaluation_cycles c ON e.cycle_id = c.id
    WHERE :is_admin = 1
       OR (e.evaluatee_id = :evaluatee_uid AND e.status IN ('submitted','acknowledged'))
       OR (:can_view_assigned = 1 AND e.evaluator_id = :evaluator_uid)
    ORDER BY c.id DESC, e.id DESC
");
$stmt->execute([
    'is_admin' => $user_role === 'admin' ? 1 : 0,
    'evaluatee_uid' => (int)$user_id,
    'can_view_assigned' => in_array($user_role, ['ss_amphoe','director'], true) ? 1 : 0,
    'evaluator_uid' => (int)$user_id,
]);
$reports = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h2 class="card-title"><?= appIcon('bar-chart') ?> รายงานผลการประเมิน</h2>
            <p class="card-subtitle"><?= $user_role === 'staff' ? 'แสดงเฉพาะผลการประเมินของคุณ' : 'รายการผลการประเมินตามสิทธิ์ของคุณ' ?></p>
        </div>
    </div>
    
    <?php if (count($reports) === 0): ?>
        <div class="empty-state">
            <span class="empty-state-icon"><?= appIcon('inbox') ?></span>
            <h3>ไม่พบข้อมูลผลการประเมิน</h3>
            <p>ยังไม่มีการบันทึกผลการประเมินในระบบที่คุณเกี่ยวข้อง</p>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>รอบการประเมิน</th>
                        <th>ผู้รับการประเมิน (ตำแหน่ง)</th>
                        <th>ผู้ประเมิน</th>
                        <th style="text-align:center;">คะแนน (100)</th>
                        <th style="text-align:center;">สถานะ</th>
                        <th style="text-align:center;">รายละเอียด</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($reports as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['round_name']) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($r['evaluatee_name']) ?></strong><br>
                                <small style="color:var(--text-muted)"><?= htmlspecialchars($r['pos_name']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($r['evaluator_name']) ?></td>
                            <td style="text-align:center; font-weight:bold; color:var(--primary);">
                                <?= number_format($r['total_score_base100'], 2) ?>
                            </td>
                            <td style="text-align:center;">
                                <?php if ($r['status'] === 'acknowledged'): ?>
                                    <span class="status-done" style="background:#10B981; color:white"><?= appIcon('check-circle') ?> รับทราบแล้ว</span>
                                <?php elseif ($r['status'] === 'submitted'): ?>
                                    <span class="status-done"><?= appIcon('check-circle') ?> ประเมินแล้ว</span>
                                <?php else: ?>
                                    <span class="status-pending" style="color:#F59E0B"><?= appIcon('triangle-alert') ?> ฉบับร่าง</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <?php if ($user_role === 'admin'): ?>
                                    <a href="assessment.php?evaluation_id=<?= (int)$r['id'] ?>" class="btn btn-primary" style="padding:0.45rem 0.7rem;font-size:0.84rem;margin:0.15rem;"><?= appIcon('edit') ?> แก้ไขคะแนน</a>
                                <?php endif; ?>
                                <?php if ($user_role === 'admin' || (int)$r['evaluator_id'] === (int)$user_id): ?>
                                    <a href="export_excel.php?id=<?= $r['id'] ?>" class="btn btn-success" style="padding:0.45rem 0.7rem;font-size:0.84rem;margin:0.15rem;"><?= appIcon('file-spreadsheet') ?> Excel</a>
                                    <a href="export_pdf.php?id=<?= $r['id'] ?>" class="btn btn-danger" style="padding:0.45rem 0.7rem;font-size:0.84rem;margin:0.15rem;"><?= appIcon('download') ?> PDF สรุป</a>
                                    <a href="export_assessment_pdf.php?id=<?= $r['id'] ?>" class="btn btn-danger" style="padding:0.45rem 0.7rem;font-size:0.84rem;margin:0.15rem;"><?= appIcon('download') ?> PDF แบบประเมิน</a>
                                <?php endif; ?>
                                <a href="report_detail.php?id=<?= $r['id'] ?>" class="btn btn-secondary" style="padding: 0.45rem 1rem; font-size: 0.88rem;">
                                    <?= appIcon('eye') ?> ดูผล
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
