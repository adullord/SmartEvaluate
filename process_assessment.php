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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method Not Allowed');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        exit('คำขอหมดอายุ กรุณากลับไปยังแบบประเมินแล้วลองใหม่');
    }
    $actor_id = (int)$_SESSION['user_id'];
    $evaluator_id = $actor_id;
    $evaluatee_id = requestInt($_POST['evaluatee_id'] ?? null, 'evaluatee_id');
    $cycle_id = requestInt($_POST['cycle_id'] ?? null, 'cycle_id');
    $action = (string)($_POST['action'] ?? 'draft');
    $adminEvaluationId = requestInt($_POST['evaluation_id'] ?? null, 'evaluation_id', 0, 0);
    $isAdminUpdate = (string)($_SESSION['role'] ?? '') === 'admin' && $action === 'admin_update' && $adminEvaluationId > 0;
    $scores = is_array($_POST['scores'] ?? null) ? $_POST['scores'] : [];
    $reasons = is_array($_POST['reasons'] ?? null) ? $_POST['reasons'] : [];

    if (!in_array($action, ['draft', 'submit', 'admin_update'], true) || ($action === 'admin_update' && !$isAdminUpdate) || empty($scores)) {
        die("ข้อมูลไม่ครบถ้วน");
    }

    // ตรวจสอบสิทธิ์ว่าได้รับมอบหมายให้ประเมินคนนี้ในรอบนี้จริงหรือไม่
    $adminEvaluation = null;
    if ($isAdminUpdate) {
        $stmt = $pdo->prepare('SELECT id,evaluator_id,evaluatee_id,cycle_id,status FROM evaluations WHERE id=?');
        $stmt->execute([$adminEvaluationId]);
        $adminEvaluation = $stmt->fetch();
        if (!$adminEvaluation || (int)$adminEvaluation['evaluatee_id'] !== $evaluatee_id || (int)$adminEvaluation['cycle_id'] !== $cycle_id) {
            http_response_code(403);
            exit('ไม่มีสิทธิ์แก้ไขผลการประเมินรายการนี้');
        }
        $evaluator_id = (int)$adminEvaluation['evaluator_id'];
    } else {
        $stmt = $pdo->prepare("SELECT em.id,c.status FROM evaluator_mapping em JOIN evaluation_cycles c ON c.id=em.cycle_id WHERE em.evaluator_id = ? AND em.evaluatee_id = ? AND em.cycle_id = ?");
        $stmt->execute([$evaluator_id, $evaluatee_id, $cycle_id]);
        $mapping = $stmt->fetch();
        if (!$mapping) die("คุณไม่มีสิทธิ์ประเมินบุคลากรท่านนี้");
        if ($mapping['status'] !== 'active') die('รอบการประเมินนี้ปิดแล้ว');
    }

    // คำนวณคะแนนรวมฝั่ง Backend เพื่อความปลอดภัย
    // ดึง expected_level และ position_id ของผู้รับการประเมิน
    $stmt = $pdo->prepare("SELECT u.expected_level,u.position_id,p.name pos_name,r.name rank_name FROM users u JOIN positions p ON p.id=u.position_id JOIN ranks r ON r.id=u.rank_id WHERE u.id = ? AND u.is_active=1");
    $stmt->execute([$evaluatee_id]);
    $evaluatee = $stmt->fetch();
    if (!$evaluatee) die('ไม่พบผู้รับการประเมิน');
    $expected_level = expectedLevelByPositionRank($evaluatee['pos_name'], $evaluatee['rank_name'])
        ?? (int)$evaluatee['expected_level'];
    $position_id = (int)$evaluatee['position_id'];

    // ดึงน้ำหนักของแต่ละสมรรถนะ
    $stmt = $pdo->prepare("SELECT competency_id, weight FROM evaluation_templates WHERE position_id = ? AND expected_level = ?");
    $stmt->execute([$position_id, $expected_level]);
    $templates = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [competency_id => weight]

    // Whitelist เฉพาะพฤติกรรมบ่งชี้ที่แสดงจริงสำหรับตำแหน่งและระดับนี้
    $stmt = $pdo->prepare("SELECT i.id,i.competency_id
        FROM indicators i
        JOIN evaluation_templates t ON t.competency_id=i.competency_id AND t.position_id=? AND t.expected_level=?
        WHERE (i.position_id IS NULL OR i.position_id=?)
          AND i.expected_level=COALESCE((
              SELECT MAX(i2.expected_level) FROM indicators i2
              WHERE i2.competency_id=i.competency_id AND i2.expected_level<=?
                AND (i2.position_id IS NULL OR i2.position_id=?)
          ), ?)");
    $stmt->execute([$position_id,$expected_level,$position_id,$expected_level,$position_id,$expected_level]);
    $ind_map = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $comp_scores = [];
    $validatedScores = [];
    foreach ($scores as $ind_id => $val) {
        $indicatorId = requestInt($ind_id, 'indicator_id');
        if (!array_key_exists($indicatorId, $ind_map) || is_array($val) || !preg_match('/^[1-5]$/', (string)$val)) {
            http_response_code(400);
            exit('ข้อมูลคะแนนไม่ถูกต้อง');
        }
        $score = (int)$val;
        $validatedScores[$indicatorId] = $score;
        $comp_id = (int)$ind_map[$indicatorId];
        if (!isset($comp_scores[$comp_id])) $comp_scores[$comp_id] = ['sum' => 0, 'count' => 0];
        $comp_scores[$comp_id]['sum'] += $score;
        $comp_scores[$comp_id]['count'] += 1;
    }
    if (in_array($action, ['submit', 'admin_update'], true) && count($validatedScores) !== count($ind_map)) die('กรุณากรอกคะแนนให้ครบทุกพฤติกรรมบ่งชี้');

    $totalBase5 = 0;
    foreach ($comp_scores as $comp_id => $data) {
        // ให้ตรงกับแบบ Excel: ปัดคะแนนเฉลี่ย (ก) เป็น 2 ตำแหน่งก่อนคูณน้ำหนัก
        $avg = round($data['sum'] / $data['count'], 2, PHP_ROUND_HALF_UP);
        $weight = (float)($templates[$comp_id] ?? 0);
        $weighted = $avg * ($weight / 100);
        $totalBase5 += $weighted;
    }
    
    $totalBase100 = round($totalBase5 * 20, 2, PHP_ROUND_HALF_UP);

    try {
        $pdo->beginTransaction();

        // Check if evaluation exists
        $stmt = $isAdminUpdate
            ? $pdo->prepare("SELECT id, status FROM evaluations WHERE id = ? FOR UPDATE")
            : $pdo->prepare("SELECT id, status FROM evaluations WHERE evaluator_id = ? AND evaluatee_id = ? AND cycle_id = ? FOR UPDATE");
        $stmt->execute($isAdminUpdate ? [$adminEvaluationId] : [$evaluator_id, $evaluatee_id, $cycle_id]);
        $eval = $stmt->fetch();
        if ($isAdminUpdate && !$eval) throw new RuntimeException('ไม่พบผลการประเมินที่ต้องการแก้ไข');

        $status = $isAdminUpdate ? (string)$adminEvaluation['status'] : (($action === 'submit') ? 'submitted' : 'draft');

        if ($eval) {
            if ($eval['status'] === 'acknowledged' && !$isAdminUpdate) {
                throw new Exception("รับทราบผลแล้ว ไม่สามารถแก้ไขได้");
            }
            $eval_id = $eval['id'];
            $stmt = $pdo->prepare("UPDATE evaluations SET status = ?, total_score_base5 = ?, total_score_base100 = ? WHERE id = ?");
            $stmt->execute([$status, $totalBase5, $totalBase100, $eval_id]);
            
            // Delete old scores
            $pdo->prepare("DELETE FROM evaluation_scores WHERE evaluation_id = ?")->execute([$eval_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO evaluations (cycle_id, evaluatee_id, evaluator_id, status, total_score_base5, total_score_base100) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$cycle_id, $evaluatee_id, $evaluator_id, $status, $totalBase5, $totalBase100]);
            $eval_id = $pdo->lastInsertId();
        }

        // Insert new scores
        $stmt = $pdo->prepare("INSERT INTO evaluation_scores (evaluation_id, indicator_id, score, reason) VALUES (?, ?, ?, ?)");
        foreach ($validatedScores as $ind_id => $val) {
            $reasonValue = $reasons[$ind_id] ?? '';
            $reason = is_array($reasonValue) ? '' : mb_substr(trim((string)$reasonValue), 0, 2000);
            $stmt->execute([$eval_id, $ind_id, $val, $reason]);
        }
        
        // Log action
        $stmt = $pdo->prepare("INSERT INTO evaluation_logs (evaluation_id, user_id, action) VALUES (?, ?, ?)");
        $logAction = $isAdminUpdate ? 'Administrator updated evaluation scores' : ($action === 'submit' ? 'Submitted evaluation' : 'Saved draft');
        $stmt->execute([$eval_id, $actor_id, $logAction]);

        $pdo->commit();
        
        header('Location: ' . ($isAdminUpdate ? 'report_detail.php?id=' . (int)$eval_id . '&updated=1' : 'index.php?success=1'));
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Assessment save failed: ' . $e->getMessage());
        http_response_code(500);
        die('ไม่สามารถบันทึกแบบประเมินได้ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ');
    }
}
?>
