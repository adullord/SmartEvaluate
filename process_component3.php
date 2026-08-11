<?php
require_once 'config.php';
require_once __DIR__ . '/includes/component3_helper.php';
require_once __DIR__ . '/csrf_helper.php';

if (!isset($_SESSION['user_id'])) { http_response_code(401); exit('Unauthorized'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method Not Allowed'); }
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { http_response_code(403); exit('คำขอไม่ถูกต้อง กรุณาลองใหม่'); }

$userId = (int)$_SESSION['user_id'];
try {
    $cycleId = requestInt($_POST['cycle_id'] ?? null, 'cycle_id');
    if (!$cycleId) throw new RuntimeException('ไม่พบรอบการประเมิน');
    $action = (string)($_POST['action'] ?? 'draft');
    if (!in_array($action, ['draft','submit'], true)) throw new RuntimeException('คำสั่งไม่ถูกต้อง');
    $stmt = $pdo->prepare('SELECT id,status FROM evaluation_cycles WHERE id=? LIMIT 1');
    $stmt->execute([$cycleId]);
    $cycle = $stmt->fetch();
    if (!$cycle || $cycle['status'] !== 'active') throw new RuntimeException('รอบการประเมินปิดแล้ว');
    $person = component3UserContext($pdo, $userId);
    $items = component3ItemsForCycle($pdo, $cycleId, (bool)$person['includes_items_1_2'], $person['department_score']);
    $postedActual = $_POST['actual'] ?? [];
    if (!is_array($postedActual)) throw new RuntimeException('รูปแบบข้อมูลไม่ถูกต้อง');
    foreach (array_keys($postedActual) as $postedNo) if (!isset($items[(int)$postedNo])) throw new RuntimeException('พบรายการประเมินที่ไม่ได้รับอนุญาต');

    $results = []; $total = 0.0; $weight = 0.0;
    foreach ($items as $itemNo => $item) {
        $actual = array_key_exists('automatic_score', $item) ? null : (isset($postedActual[$itemNo]) && $postedActual[$itemNo] !== '' ? filter_var($postedActual[$itemNo], FILTER_VALIDATE_FLOAT) : null);
        if ($actual === false) $actual = null;
        if ($action === 'submit' && !array_key_exists('automatic_score', $item) && $actual === null) throw new RuntimeException('กรุณากรอกข้อมูลให้ครบทุกข้อก่อนส่งผลประเมิน');
        if ($action === 'draft' && !array_key_exists('automatic_score', $item) && $actual === null) continue;
        $result = component3CalculateItem($item, $actual === null ? null : (float)$actual);
        $results[$itemNo] = $result;
        $total += $result['weighted_score']; $weight += (float)$item['weight'];
    }
    $applicableWeight = array_sum(array_column($items, 'weight'));
    $finalScore = $applicableWeight > 0 ? round($total / $applicableWeight * 100, 2, PHP_ROUND_HALF_UP) : 0;

    $pdo->beginTransaction();
    $lock = $pdo->prepare('SELECT id,status FROM component3_assessments WHERE cycle_id=? AND user_id=? FOR UPDATE');
    $lock->execute([$cycleId,$userId]);
    $existing = $lock->fetch();
    if ($existing && $existing['status'] === 'submitted') throw new RuntimeException('ผลประเมินถูกส่งแล้ว ไม่สามารถแก้ไขได้');
    $status = $action === 'submit' ? 'submitted' : 'draft';
    if ($existing) {
        $assessmentId = (int)$existing['id'];
        $stmt = $pdo->prepare("UPDATE component3_assessments SET status=?,applicable_weight=?,total_weighted_score=?,final_score=?,submitted_at=IF(?='submitted',CURRENT_TIMESTAMP,NULL) WHERE id=?");
        $stmt->execute([$status,$applicableWeight,$total,$finalScore,$status,$assessmentId]);
        $pdo->prepare('DELETE FROM component3_scores WHERE assessment_id=?')->execute([$assessmentId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO component3_assessments(cycle_id,user_id,status,applicable_weight,total_weighted_score,final_score,submitted_at) VALUES(?,?,?,?,?,?,IF(?='submitted',CURRENT_TIMESTAMP,NULL))");
        $stmt->execute([$cycleId,$userId,$status,$applicableWeight,$total,$finalScore,$status]);
        $assessmentId = (int)$pdo->lastInsertId();
    }
    $insert = $pdo->prepare('INSERT INTO component3_scores(assessment_id,item_no,actual_value,percentage,score,weight,weighted_score) VALUES(?,?,?,?,?,?,?)');
    foreach ($results as $itemNo => $result) $insert->execute([$assessmentId,$itemNo,$result['actual_value'],$result['percentage'],$result['score'],$items[$itemNo]['weight'],$result['weighted_score']]);
    $pdo->prepare('INSERT INTO component3_logs(assessment_id,user_id,action) VALUES(?,?,?)')->execute([$assessmentId,$userId,$status === 'submitted' ? 'ยืนยันส่งผลประเมิน' : 'บันทึกฉบับร่าง']);
    $pdo->commit();
    header('Location: ' . appUrl('component3_assessment.php') . '?cycle_id=' . $cycleId . '&success=' . ($status === 'submitted' ? 'submitted' : 'draft'));
    exit;
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Component 3 save failed: ' . $e->getMessage());
    http_response_code(500);
    exit('ไม่สามารถบันทึกข้อมูลได้ชั่วคราว กรุณาลองใหม่อีกครั้ง');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    exit(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
