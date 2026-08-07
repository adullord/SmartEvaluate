<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $evaluator_id = $_SESSION['user_id'];
    $evaluatee_id = $_POST['evaluatee_id'] ?? 0;
    $cycle_id = $_POST['cycle_id'] ?? 0;
    $action = $_POST['action'] ?? 'draft'; // draft or submit
    $scores = $_POST['scores'] ?? [];
    $reasons = $_POST['reasons'] ?? [];

    if (!$evaluatee_id || !$cycle_id || empty($scores)) {
        die("ข้อมูลไม่ครบถ้วน");
    }

    // ตรวจสอบสิทธิ์ว่าได้รับมอบหมายให้ประเมินคนนี้ในรอบนี้จริงหรือไม่
    $stmt = $pdo->prepare("SELECT * FROM evaluator_mapping WHERE evaluator_id = ? AND evaluatee_id = ? AND cycle_id = ?");
    $stmt->execute([$evaluator_id, $evaluatee_id, $cycle_id]);
    if (!$stmt->fetch()) {
        die("คุณไม่มีสิทธิ์ประเมินบุคลากรท่านนี้");
    }

    // คำนวณคะแนนรวมฝั่ง Backend เพื่อความปลอดภัย
    // ดึง expected_level และ position_id ของผู้รับการประเมิน
    $stmt = $pdo->prepare("SELECT expected_level, position_id FROM users WHERE id = ?");
    $stmt->execute([$evaluatee_id]);
    $evaluatee = $stmt->fetch();
    $expected_level = $evaluatee['expected_level'];
    $position_id = $evaluatee['position_id'];

    // ดึงน้ำหนักของแต่ละสมรรถนะ
    $stmt = $pdo->prepare("SELECT competency_id, weight FROM evaluation_templates WHERE position_id = ? AND expected_level = ?");
    $stmt->execute([$position_id, $expected_level]);
    $templates = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [competency_id => weight]

    // ดึงว่าแต่ละ indicator อยู่ competency ไหน
    $stmt = $pdo->query("SELECT id, competency_id FROM indicators");
    $ind_map = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [indicator_id => competency_id]

    $comp_scores = [];
    foreach ($scores as $ind_id => $val) {
        $val = (int)$val;
        if ($val < 1 || $val > 5) continue;
        
        $comp_id = $ind_map[$ind_id] ?? 0;
        if ($comp_id) {
            if (!isset($comp_scores[$comp_id])) {
                $comp_scores[$comp_id] = ['sum' => 0, 'count' => 0];
            }
            $comp_scores[$comp_id]['sum'] += $val;
            $comp_scores[$comp_id]['count'] += 1;
        }
    }

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
        $stmt = $pdo->prepare("SELECT id, status FROM evaluations WHERE evaluator_id = ? AND evaluatee_id = ? AND cycle_id = ?");
        $stmt->execute([$evaluator_id, $evaluatee_id, $cycle_id]);
        $eval = $stmt->fetch();

        $status = ($action === 'submit') ? 'submitted' : 'draft';

        if ($eval) {
            if ($eval['status'] === 'acknowledged') {
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
        foreach ($scores as $ind_id => $val) {
            $reason = trim($reasons[$ind_id] ?? '');
            $stmt->execute([$eval_id, $ind_id, $val, $reason]);
        }
        
        // Log action
        $stmt = $pdo->prepare("INSERT INTO evaluation_logs (evaluation_id, user_id, action) VALUES (?, ?, ?)");
        $stmt->execute([$eval_id, $evaluator_id, $action === 'submit' ? 'Submitted evaluation' : 'Saved draft']);

        $pdo->commit();
        
        header("Location: index.php?success=1");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error: " . $e->getMessage());
    }
}
?>
