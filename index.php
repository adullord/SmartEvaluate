<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$evaluator_id = (int)$_SESSION['user_id'];
$evaluator_role = (string)$_SESSION['role'];
$evaluator_dept_id = (int)$_SESSION['department_id'];

// Get active cycle
$stmt = $pdo->query("SELECT * FROM evaluation_cycles WHERE status = 'active' ORDER BY id DESC LIMIT 1");
$active_cycle = $stmt->fetch();

$subordinates = [];
$total_count = 0;
$done_count = 0;
$pending_count = 0;

if ($active_cycle) {
    $cycle_id = $active_cycle['id'];
    
    $stmt = $pdo->prepare("
        SELECT u.id, u.fullname, p.name as pos_name, r.name as rank_name, d.name as dept_name,
               e.id as evaluation_id, e.status as eval_status
        FROM evaluator_mapping em
        JOIN users u ON em.evaluatee_id = u.id
        JOIN positions p ON u.position_id = p.id
        JOIN ranks r ON u.rank_id = r.id
        JOIN departments d ON u.department_id = d.id
        LEFT JOIN evaluations e ON e.evaluatee_id = u.id AND e.evaluator_id = em.evaluator_id AND e.cycle_id = em.cycle_id
        WHERE em.evaluator_id = ?
          AND em.cycle_id = ?
          AND u.is_active = 1
          AND (
              ? = 'admin'
              OR (
                  ? = 'ss_amphoe'
                  AND ((u.role = 'staff' AND u.department_id = ?) OR u.role = 'director')
              )
              OR (? = 'director' AND u.role = 'staff' AND u.department_id = ?)
          )
        ORDER BY d.id, p.id, r.id, u.fullname
    ");
    $stmt->execute([
        $evaluator_id,
        $cycle_id,
        $evaluator_role,
        $evaluator_role,
        $evaluator_dept_id,
        $evaluator_role,
        $evaluator_dept_id,
    ]);
    $subordinates = $stmt->fetchAll();
    
    $total_count = count($subordinates);
    foreach ($subordinates as $s) {
        if ($s['eval_status'] === 'submitted' || $s['eval_status'] === 'acknowledged') {
            $done_count++;
        }
    }
    $pending_count = $total_count - $done_count;
}

require_once 'includes/header.php';
?>

<?php if ($evaluator_role !== 'staff'): ?>
<div class="welcome-section">
    <div class="welcome-info">
        <h2><?= appIcon('user-round') ?> สวัสดี, <?= htmlspecialchars($_SESSION['fullname']) ?></h2>
        <?php if($active_cycle): ?>
            <p>ระบบประเมินผลการปฏิบัติงาน — <?= htmlspecialchars($active_cycle['round_name']) ?></p>
        <?php else: ?>
            <p style="color:var(--danger)"><?= appIcon('triangle-alert') ?> ขณะนี้ยังไม่มีรอบการประเมินที่เปิดใช้งาน</p>
        <?php endif; ?>
    </div>
    <div class="welcome-stats">
        <div class="stat-item">
            <span class="stat-number"><?= $total_count ?></span>
            <span class="stat-label">รับผิดชอบ (คน)</span>
        </div>
        <div class="stat-item">
            <span class="stat-number"><?= $done_count ?></span>
            <span class="stat-label">ประเมินเสร็จสิ้น</span>
        </div>
        <div class="stat-item">
            <span class="stat-number"><?= $pending_count ?></span>
            <span class="stat-label">รอประเมิน</span>
        </div>
    </div>
</div>
<?php else: ?>
<div class="welcome-section">
    <div class="welcome-info">
        <h2><?= appIcon('user-round') ?> สวัสดี, <?= htmlspecialchars($_SESSION['fullname']) ?></h2>
        <?php if($active_cycle): ?>
            <p>ระบบประเมินผลการปฏิบัติงาน — <?= htmlspecialchars($active_cycle['round_name']) ?></p>
        <?php else: ?>
            <p style="color:var(--danger)"><?= appIcon('triangle-alert') ?> ขณะนี้ยังไม่มีรอบการประเมินที่เปิดใช้งาน</p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><?= appIcon('clipboard-list') ?> รายชื่อผู้ที่ต้องประเมิน</h2>
        <p class="card-subtitle">เลือกบุคลากรเพื่อทำแบบประเมินพฤติกรรมการปฏิบัติราชการประจำรอบ</p>
    </div>
    
    <?php if (!$active_cycle): ?>
        <div class="empty-state">
            <span class="empty-state-icon"><?= appIcon('ban') ?></span>
            <h3>ระบบปิดรับการประเมินชั่วคราว</h3>
            <p>ยังไม่มีการเปิดรอบประเมินในขณะนี้ กรุณาติดต่อผู้ดูแลระบบ</p>
        </div>
    <?php elseif ($evaluator_role === 'staff' && count($subordinates) == 0): ?>
        <div class="empty-state">
            <span class="empty-state-icon"><?= appIcon('lock') ?></span>
            <h3>คุณไม่มีผู้ใต้บังคับบัญชาที่ต้องประเมิน</h3>
            <p>คุณสามารถดูผลการประเมินของตนเองได้ที่เมนู "รายงานผล"</p>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>หน่วยงาน</th>
                        <th>ชื่อ - นามสกุล</th>
                        <th>ตำแหน่ง (ระดับ)</th>
                        <th style="text-align: center;">สถานะ</th>
                        <th style="text-align: center;">การดำเนินการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($subordinates) > 0): ?>
                        <?php foreach($subordinates as $sub): ?>
                            <tr>
                                <td><?= htmlspecialchars($sub['dept_name']) ?></td>
                                <td><strong><?= htmlspecialchars($sub['fullname']) ?></strong></td>
                                <td><span class="badge badge-primary"><?= htmlspecialchars($sub['pos_name']) ?></span> <small style="color: var(--text-muted);">(<?= htmlspecialchars($sub['rank_name']) ?>)</small></td>
                                <td style="text-align: center;">
                                    <?php if ($sub['eval_status'] === 'acknowledged'): ?>
                                        <span class="status-done" style="background:#10B981; color:white"><?= appIcon('check-circle') ?> รับทราบแล้ว</span>
                                    <?php elseif ($sub['eval_status'] === 'submitted'): ?>
                                        <span class="status-done"><?= appIcon('edit') ?> รอรับทราบ</span>
                                    <?php elseif ($sub['eval_status'] === 'draft'): ?>
                                        <span class="status-pending" style="color:#F59E0B"><?= appIcon('triangle-alert') ?> ร่าง</span>
                                    <?php else: ?>
                                        <span class="status-pending">⏳ รอประเมิน</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($sub['eval_status'] === 'acknowledged'): ?>
                                        <a href="report_detail.php?id=<?= $sub['evaluation_id'] ?>" class="btn" style="padding: 0.45rem 1rem; font-size: 0.88rem; background: var(--bg-hover);">
                                            <?= appIcon('eye') ?> ดูผล
                                        </a>
                                    <?php else: ?>
                                        <a href="assessment.php?evaluatee_id=<?= $sub['id'] ?>&cycle_id=<?= $cycle_id ?>" class="btn <?= $sub['evaluation_id'] ? 'btn-success' : 'btn-primary' ?>" style="padding: 0.45rem 1rem; font-size: 0.88rem;">
                                            <?= $sub['evaluation_id'] ? appIcon('edit') . ' ทำต่อ' : appIcon('clipboard-list') . ' เริ่มประเมิน' ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <span class="empty-state-icon"><?= appIcon('inbox') ?></span>
                                    <h3>ไม่มีรายชื่อผู้ที่ต้องประเมิน</h3>
                                    <p>ยังไม่มีการกำหนดผู้รับการประเมินให้คุณในรอบนี้</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
