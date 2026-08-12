<?php
require_once 'config.php';
require_once 'csrf_helper.php';
require_once 'includes/expected_level_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$evaluation_id = requestInt($_GET['id'] ?? null, 'id');

if (!$evaluation_id) {
    die("ไม่พบข้อมูล");
}

$stmt = $pdo->prepare("
    SELECT e.*, 
           u.fullname as evaluatee_name, u.expected_level, u.position_id, p.name as pos_name, r.name as rank_name, d.name as dept_name,
           u2.fullname as evaluator_name,
           c.round_name, c.fiscal_year
    FROM evaluations e
    JOIN users u ON e.evaluatee_id = u.id
    JOIN positions p ON u.position_id = p.id
    JOIN ranks r ON u.rank_id = r.id
    JOIN departments d ON u.department_id = d.id
    JOIN users u2 ON e.evaluator_id = u2.id
    JOIN evaluation_cycles c ON e.cycle_id = c.id
    WHERE e.id = ?
");
$stmt->execute([$evaluation_id]);
$evaluation = $stmt->fetch();

if (!$evaluation) {
    die("ไม่พบข้อมูลการประเมิน");
}

// เช็คสิทธิ์: เป็นผู้ประเมิน, ผู้ถูกประเมิน, หรือ Admin
$isEvaluator = in_array((string)($_SESSION['role'] ?? ''), ['ss_amphoe','director'], true)
    && $user_id === (int)$evaluation['evaluator_id'];
$isEvaluateeWithReleasedResult = $user_id === (int)$evaluation['evaluatee_id'] && in_array($evaluation['status'], ['submitted', 'acknowledged'], true);
if (!$isEvaluator && !$isEvaluateeWithReleasedResult && $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die("คุณไม่มีสิทธิ์เข้าถึงข้อมูลนี้");
}

// จัดการการกดรับทราบ (Acknowledge)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'acknowledge') {
    if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        die('คำขอหมดอายุ กรุณารีเฟรชหน้าแล้วลองใหม่');
    }
    if ($user_id === (int)$evaluation['evaluatee_id'] && $evaluation['status'] === 'submitted') {
        $stmt = $pdo->prepare("UPDATE evaluations SET status = 'acknowledged', acknowledged_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$evaluation_id]);
        
        $stmt = $pdo->prepare("INSERT INTO evaluation_logs (evaluation_id, user_id, action) VALUES (?, ?, 'Acknowledged evaluation')");
        $stmt->execute([$evaluation_id, $user_id]);
        
        header("Location: report_detail.php?id=$evaluation_id&success=1");
        exit;
    }
}

// ดึงคะแนน
$report_expected_level = expectedLevelByPositionRank($evaluation['pos_name'], $evaluation['rank_name'])
    ?? (int)($evaluation['expected_level'] ?? 1);
$stmt = $pdo->prepare("
    SELECT es.*, i.indicator_text, i.competency_id, comp.name as comp_name, comp.description as comp_desc, comp.type, t.weight
    FROM evaluation_scores es
    JOIN indicators i ON es.indicator_id = i.id
    JOIN competencies comp ON i.competency_id = comp.id
    JOIN evaluation_templates t ON comp.id = t.competency_id AND t.position_id = ? AND t.expected_level = ?
    WHERE es.evaluation_id = ?
    ORDER BY comp.type,
             CASE t.position_id
                 WHEN 4 THEN FIELD(comp.id, 1, 2, 3, 4, 10, 11, 6)
                 WHEN 5 THEN FIELD(comp.id, 1, 2, 3, 4, 12, 11, 6)
                 WHEN 6 THEN FIELD(comp.id, 1, 2, 3, 4, 8, 6, 13)
                 WHEN 7 THEN FIELD(comp.id, 1, 2, 3, 4, 8, 5, 14)
                 WHEN 8 THEN FIELD(comp.id, 1, 2, 3, 4, 8, 10, 9)
                 WHEN 9 THEN FIELD(comp.id, 1, 2, 3, 4, 8, 9, 6)
                 ELSE comp.order_seq
             END,
             i.order_seq
");
$stmt->execute([(int)$evaluation['position_id'], $report_expected_level, $evaluation_id]);
$scores = $stmt->fetchAll();

// จัดกลุ่มตามสมรรถนะ
$grouped_scores = [];
$comp_summaries = [];

foreach ($scores as $s) {
    $c_id = $s['competency_id'];
    if (!isset($grouped_scores[$c_id])) {
        $grouped_scores[$c_id] = [
            'name' => trim((string)preg_replace('/\s*\([A-Za-z][^)]*\)\s*$/u', '', $s['comp_name'])),
            'desc' => $s['comp_desc'],
            'type' => $s['type'],
            'weight' => $s['weight'],
            'items' => [],
            'sum' => 0,
            'count' => 0
        ];
    }
    $grouped_scores[$c_id]['items'][] = $s;
    $grouped_scores[$c_id]['sum'] += $s['score'];
    $grouped_scores[$c_id]['count']++;
}

foreach ($grouped_scores as $c_id => $data) {
    // ใช้ค่าเฉลี่ย 2 ตำแหน่งเดียวกับหน้าแบบประเมินและ Excel
    $avg = round($data['sum'] / $data['count'], 2, PHP_ROUND_HALF_UP);
    $weighted = $avg * ($data['weight'] / 100);
    $comp_summaries[$c_id] = [
        'name' => $data['name'],
        'type' => $data['type'],
        'weight' => $data['weight'],
        'avg' => $avg,
        'weighted' => $weighted
    ];
}

$summary_total_base5 = array_sum(array_column($comp_summaries, 'weighted'));
$summary_total_base100 = round($summary_total_base5 * 20, 2, PHP_ROUND_HALF_UP);
$summary_total_weight = array_sum(array_column($comp_summaries, 'weight'));

require_once 'includes/header.php';
?>
<?php if ($_SESSION['role'] === 'admin' && ($_GET['updated'] ?? '') === '1'): ?>
<div class="card" style="margin-bottom:1rem;padding:1rem;border-left:4px solid #10b981;background:#ecfdf5;color:#065f46"><?= appIcon('check-circle') ?> บันทึกการแก้ไขคะแนนและคำนวณคะแนนรวมใหม่เรียบร้อยแล้ว</div>
<?php endif; ?>

<div class="card" style="margin-bottom: 2rem;">
    <div class="evaluatee-info-grid">
        <div class="evaluatee-avatar">
            <span><?= appIcon('bar-chart') ?></span>
        </div>
        <div class="evaluatee-details">
            <h2 style="margin: 0; font-size: 1.4rem; color: var(--primary-700);">รายละเอียดผลการประเมิน</h2>
            <p style="margin: 0.2rem 0; color: var(--text-secondary);">
                <strong>ผู้รับการประเมิน:</strong> <?= htmlspecialchars($evaluation['evaluatee_name']) ?> <br>
                <strong>ตำแหน่ง:</strong> <?= htmlspecialchars($evaluation['pos_name']) ?> (<?= htmlspecialchars($evaluation['rank_name']) ?>)
            </p>
            <p style="margin: 0; color: var(--text-muted); font-size: 0.9rem;">
                <strong>รอบการประเมิน:</strong> <?= htmlspecialchars($evaluation['round_name']) ?> | <strong>ผู้ประเมิน:</strong> <?= htmlspecialchars($evaluation['evaluator_name']) ?>
            </p>
            
            <div style="margin-top: 1rem; display: flex; gap: 1rem; align-items: center;">
                <div>
                    <?php if ($evaluation['status'] === 'acknowledged'): ?>
                        <span class="badge" style="background:#10B981; color:white; font-size: 1rem; padding: 0.5rem 1rem;"><?= appIcon('check-circle') ?> ผู้รับการประเมินรับทราบผลแล้ว เมื่อ <?= $evaluation['acknowledged_at'] ?></span>
                    <?php elseif ($evaluation['status'] === 'submitted'): ?>
                        <span class="badge" style="background:#10B981; color:white; font-size: 1rem; padding: 0.5rem 1rem;"><?= appIcon('check-circle') ?> ประเมินแล้ว</span>
                    <?php else: ?>
                        <span class="badge" style="background:#6B7280; color:white; font-size: 1rem; padding: 0.5rem 1rem;"><?= appIcon('triangle-alert') ?> ฉบับร่าง (ยังไม่ส่งผล)</span>
                    <?php endif; ?>
                </div>
                <?php if ($_SESSION['role'] === 'admin' || (int)$evaluation['evaluator_id'] === (int)$user_id): ?>
                    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                        <?php if ($_SESSION['role'] === 'admin'): ?><a href="assessment.php?evaluation_id=<?= $evaluation_id ?>" class="btn btn-primary" style="padding:0.5rem 1rem;"><?= appIcon('edit') ?> แก้ไขคะแนน</a><?php endif; ?>
                        <a href="export_excel.php?id=<?= $evaluation_id ?>" class="btn btn-success" style="padding: 0.5rem 1rem;"><?= appIcon('file-spreadsheet') ?> ส่งออก Excel</a>
                        <a href="export_pdf.php?id=<?= $evaluation_id ?>" class="btn btn-danger" style="padding: 0.5rem 1rem;"><?= appIcon('download') ?> PDF สรุป</a>
                        <a href="export_assessment_pdf.php?id=<?= $evaluation_id ?>" class="btn btn-danger" style="padding: 0.5rem 1rem;"><?= appIcon('download') ?> PDF แบบประเมิน</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($user_id === (int)$evaluation['evaluatee_id'] && $evaluation['status'] === 'submitted'): ?>
<div class="card" style="background-color: #FEF3C7; border: 1px solid #F59E0B; margin-bottom: 2rem;">
    <h3 style="color: #D97706; margin-top:0;"><?= appIcon('triangle-alert') ?> การรับทราบผลการประเมิน</h3>
    <p style="color: #92400E;">กรุณาตรวจสอบผลการประเมินของท่าน และกดยืนยันการรับทราบผลด้านล่าง หากมีข้อโต้แย้งกรุณาติดต่อผู้ประเมินก่อนกดยอมรับ</p>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" name="action" value="acknowledge" class="btn btn-success" style="font-size: 1.1rem; padding: 0.75rem 2rem;">
            <?= appIcon('check-circle') ?> ข้าพเจ้าขอรับทราบผลการประเมิน
        </button>
    </form>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
        <h2 class="card-title">สรุปคะแนน</h2>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th style="vertical-align:middle;">สมรรถนะ</th>
                <th style="text-align:center; vertical-align:middle; width: 120px;">ระดับที่<br>คาดหวัง</th>
                <th style="text-align:center; vertical-align:middle; width: 120px;">คะแนน<br>( ก )</th>
                <th style="text-align:center; vertical-align:middle; width: 120px;">น้ำหนัก<br>( ข )</th>
                <th style="text-align:center; vertical-align:middle; width: 150px;">คะแนนรวม<br>( ค )<br><span style="font-size:0.85em; font-weight:normal;">(ค) = ก x ข</span></th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $core_comps = array_filter($comp_summaries, fn($c) => $c['type'] === 'core');
            $func_comps = array_filter($comp_summaries, fn($c) => $c['type'] === 'functional');
            $expected_level = $report_expected_level;
            
            if (!empty($core_comps)): ?>
                <tr>
                    <td colspan="5" style="font-weight:bold; background-color:#f8fafc;">สมรรถนะหลัก</td>
                </tr>
                <?php $i = 1; foreach ($core_comps as $c): ?>
                <tr>
                    <td><?= $i++ ?>. <?= htmlspecialchars($c['name']) ?></td>
                    <td style="text-align:center;"><?= $expected_level ?></td>
                    <td style="text-align:center;"><?= number_format($c['avg'], 2) ?></td>
                    <td style="text-align:center;"><?= number_format($c['weight'], 0) ?>%</td>
                    <td style="text-align:center;"><?= number_format($c['weighted'], 1) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($func_comps)): ?>
                <tr>
                    <td colspan="5" style="font-weight:bold; background-color:#f8fafc;">สมรรถนะเฉพาะตามลักษณะงานที่ปฏิบัติ</td>
                </tr>
                <?php $j = 1; foreach ($func_comps as $c): ?>
                <tr>
                    <td><?= $i + $j - 1 ?>. <?= htmlspecialchars($c['name']) ?></td>
                    <td style="text-align:center;"><?= $expected_level ?></td>
                    <td style="text-align:center;"><?= number_format($c['avg'], 2) ?></td>
                    <td style="text-align:center;"><?= number_format($c['weight'], 0) ?>%</td>
                    <td style="text-align:center;"><?= number_format($c['weighted'], 1) ?></td>
                </tr>
                <?php $j++; endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3"></td>
                <td style="text-align:right; font-weight:bold;">รวม = <?= number_format($summary_total_weight, 0) ?>%</td>
                <td style="text-align:center; font-weight:bold; color:var(--primary); font-size:1.1rem;"><?= number_format($summary_total_base5, 1) ?></td>
            </tr>
            <tr>
                <td colspan="4" style="font-weight:bold; line-height: 1.5;">
                    แปลงคะแนนรวมข้างต้นเป็นคะแนนการประเมินสมรรถนะมีฐานคะแนนเต็ม เป็น 100 คะแนน<br>
                    <span style="font-weight:normal;">(โดยนำ 20 มาคูณ)</span>
                </td>
                <td style="text-align:center; font-weight:bold; color:var(--success); font-size:1.3rem; vertical-align:middle;"><?= number_format($summary_total_base100, 2) ?></td>
            </tr>
        </tfoot>
    </table>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">รายละเอียดคะแนนรายข้อ</h2>
    </div>
    
    <?php foreach ($grouped_scores as $c_id => $group): ?>
    <div style="margin-bottom: 2rem;">
        <h3 style="color: var(--primary-700); padding-bottom: 0.5rem; margin-bottom: 0.5rem;">
            <?= htmlspecialchars($group['name']) ?> (น้ำหนัก <?= number_format($group['weight'], 2) ?>%)
        </h3>
        <?php if (!empty($group['desc'])): ?>
            <div style="background-color: #F8FAFC; border-left: 4px solid var(--primary); padding: 0.75rem 1rem; border-radius: 4px; font-size: 0.9rem; color: #475569; line-height: 1.5; margin-bottom: 1rem;">
                <?= htmlspecialchars($group['desc']) ?>
            </div>
        <?php else: ?>
            <div style="border-bottom: 1px solid var(--border-color); margin-bottom: 1rem;"></div>
        <?php endif; ?>
        <table>
            <thead>
                <tr>
                    <th style="width: 5%">ข้อ</th>
                    <th style="width: 75%">พฤติกรรมบ่งชี้</th>
                    <th style="width: 20%; text-align:center;">คะแนนที่ได้</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($group['items'] as $index => $item): ?>
                <tr>
                    <td style="text-align:center;"><?= $index + 1 ?></td>
                    <td>
                        <?= htmlspecialchars($item['indicator_text']) ?>
                    </td>
                    <td style="text-align:center;">
                        <span class="badge <?= $item['score'] >= 4 ? 'badge-success' : ($item['score'] <= 2 ? 'badge-danger' : 'badge-primary') ?>" style="font-size: 1.1rem; padding: 0.4rem 0.8rem;">
                            <?= $item['score'] ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>
    
    <div style="text-align:center; margin-top: 2rem;">
        <a href="index.php" class="btn btn-secondary">← กลับหน้าหลัก</a>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
