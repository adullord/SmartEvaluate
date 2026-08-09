<?php
require_once 'config.php';
require_once 'csrf_helper.php';
require_once 'includes/expected_level_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
if (!in_array((string)($_SESSION['role'] ?? ''), ['admin','ss_amphoe','director'], true)) {
    http_response_code(403);
    exit('ไม่มีสิทธิ์ประเมินบุคลากรอื่น');
}

$evaluator_id = $_SESSION['user_id'];
$evaluatee_id = requestInt($_GET['evaluatee_id'] ?? null, 'evaluatee_id');
$cycle_id = requestInt($_GET['cycle_id'] ?? null, 'cycle_id');

if (!$evaluatee_id || !$cycle_id) {
    die("ข้อมูลไม่ครบถ้วน");
}

// ตรวจสอบสิทธิ์ว่าได้รับมอบหมายให้ประเมินคนนี้ในรอบนี้จริงหรือไม่
$stmt = $pdo->prepare("SELECT * FROM evaluator_mapping WHERE evaluator_id = ? AND evaluatee_id = ? AND cycle_id = ?");
$stmt->execute([$evaluator_id, $evaluatee_id, $cycle_id]);
if (!$stmt->fetch()) {
    die("คุณไม่มีสิทธิ์ประเมินบุคลากรท่านนี้");
}

// ดึงข้อมูลผู้รับการประเมิน
$stmt = $pdo->prepare("
    SELECT u.*, p.name as pos_name, r.name as rank_name, d.name as dept_name 
    FROM users u
    JOIN positions p ON u.position_id = p.id
    JOIN ranks r ON u.rank_id = r.id
    JOIN departments d ON u.department_id = d.id
    WHERE u.id = ?
");
$stmt->execute([$evaluatee_id]);
$evaluatee = $stmt->fetch();

if (!$evaluatee) {
    die("ไม่พบผู้รับการประเมิน");
}

/*
 * กำหนดระดับสมรรถนะจากคู่ตำแหน่ง/อันดับตามบัญชีตำแหน่งที่ใช้งานจริง
 * ระดับปฏิบัติการ ปฏิบัติงาน และชำนาญการ ใช้เกณฑ์ระดับ 1
 * ระดับชำนาญการพิเศษและอาวุโสใช้เกณฑ์ระดับ 2
 */
$expected_level = expectedLevelByPositionRank($evaluatee['pos_name'], $evaluatee['rank_name'])
    ?? (int)($evaluatee['expected_level'] ?? 1);
$position_id = (int)$evaluatee['position_id'];

// ดึงสมรรถนะและน้ำหนัก
$stmt = $pdo->prepare("
    SELECT c.id, c.name, c.description, c.type, t.weight,
           COALESCE(t.level_description, cl.level_description) AS level_description
    FROM competencies c
    JOIN evaluation_templates t ON c.id = t.competency_id
    LEFT JOIN competency_levels cl ON c.id = cl.competency_id AND cl.expected_level = t.expected_level
    WHERE t.position_id = ? AND t.expected_level = ?
    ORDER BY c.type,
             CASE t.position_id
                 WHEN 4 THEN FIELD(c.id, 1, 2, 3, 4, 10, 11, 6)
                 WHEN 5 THEN FIELD(c.id, 1, 2, 3, 4, 12, 11, 6)
                 WHEN 6 THEN FIELD(c.id, 1, 2, 3, 4, 8, 6, 13)
                 WHEN 7 THEN FIELD(c.id, 1, 2, 3, 4, 8, 5, 14)
                 WHEN 8 THEN FIELD(c.id, 1, 2, 3, 4, 8, 10, 9)
                 WHEN 9 THEN FIELD(c.id, 1, 2, 3, 4, 8, 9, 6)
                 ELSE c.order_seq
             END
");
$stmt->execute([$position_id, $expected_level]);
$competencies = $stmt->fetchAll();

// ดึงตัวชี้วัด (Indicators) สำหรับแต่ละสมรรถนะ
$indicators_by_comp = [];
foreach ($competencies as $comp) {
    // บางสมรรถนะมีพฤติกรรมบ่งชี้ไม่ถึงระดับคาดหวังของบทบาท ผอ.
    // ให้ใช้ระดับสูงสุดที่มีจริงและไม่เกินระดับคาดหวังเป็นรายสมรรถนะ
    $stmt = $pdo->prepare("
        SELECT MAX(expected_level)
        FROM indicators
        WHERE competency_id = ?
          AND expected_level <= ?
          AND (position_id IS NULL OR position_id = ?)
    ");
    $stmt->execute([$comp['id'], $expected_level, $position_id]);
    $indicatorLevel = (int)$stmt->fetchColumn();
    if ($indicatorLevel < 1) {
        $indicatorLevel = $expected_level;
    }

    $comp['indicator_level'] = $indicatorLevel;
    if ($indicatorLevel !== $expected_level) {
        $stmt = $pdo->prepare("
            SELECT level_description
            FROM competency_levels
            WHERE competency_id = ? AND expected_level = ?
        ");
        $stmt->execute([$comp['id'], $indicatorLevel]);
        $fallbackLevelDescription = $stmt->fetchColumn();
        if ($fallbackLevelDescription !== false) {
            $comp['level_description'] = $fallbackLevelDescription;
        }
    }

    $stmt = $pdo->prepare("
        SELECT * FROM indicators
        WHERE competency_id = ?
          AND expected_level = ?
          AND (position_id IS NULL OR position_id = ?)
        ORDER BY position_id DESC, order_seq
    ");
    $stmt->execute([$comp['id'], $indicatorLevel, $position_id]);
    $indicators_by_comp[$comp['id']] = $stmt->fetchAll();

    // บันทึกค่าที่คำนวณกลับไปยังรายการหลักสำหรับใช้แสดงผลใน HTML
    foreach ($competencies as &$storedComp) {
        if ((int)$storedComp['id'] === (int)$comp['id']) {
            $storedComp = $comp;
            break;
        }
    }
    unset($storedComp);
}

// ตรวจสอบว่าเคยประเมินหรือยัง (เพื่อโหมดแก้ไข)
$stmt = $pdo->prepare("SELECT * FROM evaluations WHERE evaluator_id = ? AND evaluatee_id = ? AND cycle_id = ?");
$stmt->execute([$evaluator_id, $evaluatee_id, $cycle_id]);
$evaluation = $stmt->fetch();

$existing_scores = [];
if ($evaluation) {
    if ($evaluation['status'] === 'acknowledged') {
        die("ผู้รับการประเมินรับทราบผลแล้ว ไม่สามารถแก้ไขได้");
    }
    
    $stmt = $pdo->prepare("SELECT * FROM evaluation_scores WHERE evaluation_id = ?");
    $stmt->execute([$evaluation['id']]);
    while ($row = $stmt->fetch()) {
        $existing_scores[$row['indicator_id']] = [
            'score' => $row['score'],
            'reason' => $row['reason']
        ];
    }
}

require_once 'includes/header.php';
?>

<div class="card" style="margin-bottom: 2rem;">
    <div class="evaluatee-info-grid">
        <div class="evaluatee-avatar">
            <span><?= appIcon('user-round') ?></span>
        </div>
        <div class="evaluatee-details">
            <h2 style="margin: 0; font-size: 1.4rem; color: var(--primary-700);"><?= htmlspecialchars($evaluatee['fullname']) ?></h2>
            <p style="margin: 0.2rem 0; color: var(--text-secondary);">
                <strong>ตำแหน่ง:</strong> <?= htmlspecialchars($evaluatee['pos_name']) ?> (<?= htmlspecialchars($evaluatee['rank_name']) ?>)
            </p>
            <p style="margin: 0; color: var(--text-muted); font-size: 0.9rem;">
                <strong>หน่วยงาน:</strong> <?= htmlspecialchars($evaluatee['dept_name']) ?>
            </p>
        </div>
    </div>
</div>

<form id="assessmentForm" method="POST" action="process_assessment.php" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="evaluatee_id" value="<?= $evaluatee_id ?>">
    <input type="hidden" name="cycle_id" value="<?= $cycle_id ?>">
    
    <!-- Progress and Navigation Container -->
    <div class="card" style="margin-bottom: 1.5rem; padding: 1.5rem;">
        <div class="progress-container">
            <div class="progress-bar" id="assessmentProgress" style="background-color: var(--primary); width: 0%"></div>
        </div>
        <div class="progress-text" id="progressText" style="text-align: right; color: var(--text-secondary); font-weight: 500;">ประเมินแล้ว 0 / 0 ข้อ</div>
        
        <div class="assessment-tabs" style="margin-top: 1rem; border-bottom: 2px solid var(--border);">
            <?php foreach ($competencies as $index => $comp): ?>
                <button type="button" class="tab-btn <?= $index === 0 ? 'active' : '' ?>" data-target="tab-<?= $comp['id'] ?>" onclick="switchTab('tab-<?= $comp['id'] ?>')">
                    <?= $index + 1 ?>. <?= htmlspecialchars(mb_strimwidth($comp['name'], 0, 40, '...')) ?>
                    <span class="badge" id="badge-tab-<?= $comp['id'] ?>" style="margin-left:5px; background:var(--border); color:var(--text-muted); font-size:0.7rem; padding:0.1rem 0.4rem;"></span>
                </button>
            <?php endforeach; ?>
            <button type="button" class="tab-btn" data-target="tab-summary" id="btn-tab-summary" onclick="switchTab('tab-summary')" style="font-weight: 700; color: var(--secondary);">
                <?= appIcon('bar-chart') ?> สรุปผล
            </button>
        </div>
    </div>

    <div class="tab-content-container">
        <?php 
        $total_indicators = 0;
        foreach ($competencies as $index => $comp): 
            $indicators = $indicators_by_comp[$comp['id']];
            $comp_level = (int)($comp['indicator_level'] ?? $expected_level);
            $total_indicators += count($indicators);
        ?>
            <div class="tab-pane <?= $index === 0 ? 'active' : '' ?>" id="tab-<?= $comp['id'] ?>">
                <div class="card">
                    <div class="card-header" style="display:flex; flex-direction:column; gap:0.5rem; align-items:flex-start; margin-bottom: 1.5rem;">
                        <div style="display:flex; justify-content:space-between; width:100%; align-items:center;">
                            <h2 class="card-title" style="color:var(--primary); font-size:1.2rem; margin:0;">
                                <?= htmlspecialchars($comp['name']) ?>
                            </h2>
                            <span class="badge" style="background:var(--primary-100); color:var(--primary-700);">น้ำหนัก <?= (float)$comp['weight'] ?>%</span>
                        </div>
                        <?php if (!empty($comp['description'])): ?>
                            <div style="background-color: #F8FAFC; border-left: 4px solid var(--primary); padding: 0.75rem 1rem; border-radius: 4px; font-size: 0.95rem; color: #475569; line-height: 1.5; width: 100%;">
                                <?= htmlspecialchars($comp['description']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($comp['level_description'])): ?>
                            <div style="background-color: #FFFBEB; border-left: 4px solid #F59E0B; padding: 0.75rem 1rem; border-radius: 4px; font-size: 0.95rem; color: #78350F; line-height: 1.5; width: 100%; margin-top: 0.5rem;">
                                <strong>คำอธิบายระดับ:</strong> <?= htmlspecialchars($comp['level_description']) ?>
                            </div>
                        <?php endif; ?>
                        <div style="margin-top: 0.5rem; font-weight: bold; color: var(--primary-700);">
                            พฤติกรรมบ่งชี้ ระดับที่ <?= $comp_level ?> <?= $comp_level == 1 ? ': ระดับพื้นฐาน' : ($comp_level == 2 ? ': ระดับกลาง' : '') ?>
                        </div>
                    </div>
                    
                    <?php if (count($indicators) === 0): ?>
                        <p style="color:var(--danger)">ไม่พบพฤติกรรมบ่งชี้ในระบบสำหรับสมรรถนะนี้</p>
                    <?php else: ?>
                        <div class="matrix-container">
                            <table class="matrix-table">
                                <thead>
                                    <tr>
                                        <th>พฤติกรรมบ่งชี้</th>
                                        <th title="ต้องปรับปรุง (แสดงพฤติกรรมน้อยที่สุด)">1<br><small style="font-weight:normal; opacity:0.8;">ต้องปรับปรุง</small></th>
                                        <th title="พอใช้ (แสดงพฤติกรรมน้อย)">2<br><small style="font-weight:normal; opacity:0.8;">พอใช้</small></th>
                                        <th title="ผ่านเกณฑ์ (แสดงพฤติกรรมปานกลาง)">3<br><small style="font-weight:normal; opacity:0.8;">ผ่านเกณฑ์</small></th>
                                        <th title="ดี (แสดงพฤติกรรมมาก)">4<br><small style="font-weight:normal; opacity:0.8;">ดี</small></th>
                                        <th title="ดีเด่น (แสดงพฤติกรรมมากที่สุด)">5<br><small style="font-weight:normal; opacity:0.8;">ดีเด่น</small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($indicators as $i => $ind): 
                                        $ind_id = $ind['id'];
                                        $score = $existing_scores[$ind_id]['score'] ?? null;
                                        $reason = $existing_scores[$ind_id]['reason'] ?? '';
                                    ?>
                                    <tr class="matrix-row question-item <?= $score ? 'answered' : '' ?>" data-indicator-id="<?= $ind_id ?>" data-comp-id="<?= $comp['id'] ?>" data-weight="<?= $comp['weight'] ?>" data-comp-type="<?= $comp['type'] ?>" data-expected-level="<?= $comp_level ?>">
                                        <td>
                                            <strong>ข้อ <?= $i+1 ?>:</strong> <?= htmlspecialchars($ind['indicator_text']) ?>
                                        </td>
                                        <?php for($v=1; $v<=5; $v++): ?>
                                        <td>
                                            <label class="matrix-radio-label">
                                                <input type="radio" name="scores[<?= $ind_id ?>]" value="<?= $v ?>" class="matrix-radio" <?= $score == $v ? 'checked' : '' ?>>
                                                <span class="matrix-radio-custom"><?= $v ?></span>
                                            </label>
                                        </td>
                                        <?php endfor; ?>
                                    </tr>

                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    
                    <div class="tab-navigation-buttons" style="margin-top:2rem; display:flex; justify-content:space-between;">
                        <?php if ($index > 0): ?>
                            <button type="button" class="btn btn-secondary" onclick="switchTab('tab-<?= $competencies[$index-1]['id'] ?>')">← ย้อนกลับ</button>
                        <?php else: ?>
                            <div></div>
                        <?php endif; ?>
                        
                        <?php if ($index < count($competencies) - 1): ?>
                            <button type="button" class="btn btn-primary" onclick="switchTab('tab-<?= $competencies[$index+1]['id'] ?>')">ถัดไป →</button>
                        <?php else: ?>
                            <button type="button" class="btn btn-success" onclick="switchTab('tab-summary')">ดูสรุปคะแนน →</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <!-- Summary Tab -->
        <div class="tab-pane" id="tab-summary">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><?= appIcon('bar-chart') ?> สรุปผลการประเมิน</h2>
                </div>
                
                <style>
                    .summary-table-scroll {
                        width: 100%;
                        overflow-x: auto;
                        margin-bottom: 2rem;
                    }
                    .summary-table-custom {
                        width: 100%;
                        min-width: 720px;
                        border-collapse: collapse;
                        font-family: inherit;
                        table-layout: fixed;
                        background: #fff;
                    }
                    .summary-table-custom th, .summary-table-custom td {
                        border: 1px solid #111;
                        padding: 0.48rem 0.65rem;
                        vertical-align: middle;
                        color: #111;
                        line-height: 1.45;
                    }
                    .summary-table-custom th {
                        background-color: #fff;
                        font-weight: 700;
                        text-align: center;
                    }
                    /* หัวตารางแถว 2-3 ช่องคะแนนรวมให้ต่อเนื่องกัน ไม่มีเส้นคั่นกลาง */
                    .summary-table-custom thead tr:nth-child(2) th:last-child {
                        border-bottom: 0;
                    }
                    .summary-table-custom thead tr:nth-child(3) th {
                        border-top: 0;
                    }
                    .summary-table-custom .summary-section-row td {
                        height: 2rem;
                        font-weight: 700;
                        background: #fff;
                    }
                    .summary-table-custom .summary-section-title {
                        padding-left: 1.25rem;
                    }
                    .summary-table-custom .summary-name {
                        font-weight: 400;
                        text-align: left;
                    }
                    .summary-table-custom .summary-number {
                        text-align: center;
                        white-space: nowrap;
                    }
                    .summary-table-custom tfoot td {
                        font-weight: 700;
                    }
                    .summary-table-custom .summary-total-label {
                        text-align: right;
                    }
                    .summary-table-custom .summary-conversion-text {
                        position: relative;
                        min-height: 5.5rem;
                        padding-right: 2.5rem;
                        font-weight: 400;
                        vertical-align: top;
                    }
                    .summary-table-custom .summary-conversion-arrow {
                        position: absolute;
                        right: 0.35rem;
                        top: 50%;
                        transform: translateY(-50%);
                        font-size: 1.55rem;
                        line-height: 1;
                    }
                    .btn-draft {
                        background-color: #f1f5f9;
                        color: #0f172a;
                        border: none;
                        border-radius: 8px;
                        padding: 0.75rem 1.5rem;
                        font-weight: 600;
                        cursor: pointer;
                        display: inline-flex;
                        align-items: center;
                        gap: 0.5rem;
                    }
                    .btn-submit-final {
                        background-color: #2ed573;
                        color: white;
                        border: none;
                        border-radius: 8px;
                        padding: 0.75rem 1.5rem;
                        font-weight: 600;
                        cursor: pointer;
                        display: inline-flex;
                        align-items: center;
                        gap: 0.5rem;
                    }
                </style>
                <div class="summary-table-scroll">
                    <table id="summaryTable" class="summary-table-custom">
                        <colgroup>
                            <col style="width:50%">
                            <col style="width:12%">
                            <col style="width:12%">
                            <col style="width:12%">
                            <col style="width:14%">
                        </colgroup>
                        <thead>
                            <tr>
                                <th rowspan="3">สมรรถนะ</th>
                                <th>ระดับที่</th>
                                <th>คะแนน</th>
                                <th>น้ำหนัก</th>
                                <th>คะแนนรวม</th>
                            </tr>
                            <tr>
                                <th rowspan="2">คาดหวัง</th>
                                <th rowspan="2">( ก )</th>
                                <th rowspan="2">( ข )</th>
                                <th>( ค )</th>
                            </tr>
                            <tr>
                                <th>(ค) = ก x ข</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Rendered by JS -->
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2"></td>
                                <td class="summary-total-label">รวม</td>
                                <td class="summary-number">= 100%</td>
                                <td class="summary-number" id="totalBase5">0.0</td>
                            </tr>
                            <tr>
                                <td colspan="4" class="summary-conversion-text">
                                    แปลงคะแนนรวมข้างต้นเป็นคะแนนการประเมินสมรรถนะมีฐานคะแนนเต็ม เป็น 100 คะแนน<br>
                                    (โดยนำ 20 มาคูณ)
                                    <span class="summary-conversion-arrow" aria-hidden="true">→</span>
                                </td>
                                <td class="summary-number" id="totalBase100">0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <div style="margin-top:2rem; text-align:center;">
                    <button type="submit" name="action" value="draft" class="btn-draft" style="margin-right:1rem;"><?= appIcon('save') ?> บันทึกแบบร่าง</button>
                    <button type="submit" name="action" value="submit" class="btn-submit-final" id="btnSubmitFinal"><?= appIcon('check-circle') ?> ยืนยันส่งผลการประเมิน</button>
                </div>
            </div> <!-- End card -->
        </div> <!-- End tab-pane summary -->
    </div> <!-- End tab-content-container -->
</form>
<script>
// Inline switchTab function — guaranteed to work regardless of external JS
function switchTab(targetId) {
    // Hide all tabs
    document.querySelectorAll('.tab-pane').forEach(function(pane) {
        pane.classList.remove('active');
    });
    // Deactivate all tab buttons
    document.querySelectorAll('.tab-btn').forEach(function(btn) {
        btn.classList.remove('active');
    });
    // Show target tab
    var targetPane = document.getElementById(targetId);
    if (targetPane) targetPane.classList.add('active');
    // Activate target button
    var targetBtn = document.querySelector('.tab-btn[data-target="' + targetId + '"]');
    if (targetBtn) targetBtn.classList.add('active');
    // Calculate summary if switching to summary tab
    if (targetId === 'tab-summary' && typeof calculateSummary === 'function') {
        calculateSummary();
    }
    window.scrollTo(0, 0);
}
</script>

<?php
function getScoreTooltip($score) {
    $tooltips = [
        1 => 'แสดงพฤติกรรมน้อยที่สุด',
        2 => 'แสดงพฤติกรรมน้อย',
        3 => 'แสดงพฤติกรรมปานกลาง',
        4 => 'แสดงพฤติกรรมมาก',
        5 => 'แสดงพฤติกรรมมากที่สุด'
    ];
    return $tooltips[$score] ?? '';
}
require_once 'includes/footer.php'; 
?>
