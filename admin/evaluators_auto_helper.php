<?php

/**
 * Rebuild evaluator mappings for a cycle using the organization hierarchy.
 * Existing evaluations keep their original evaluator.
 */
function autoAssignEvaluators(PDO $pdo, int $cycleId, bool $dryRun = false): array
{
    $cycleCheck = $pdo->prepare('SELECT COUNT(*) FROM evaluation_cycles WHERE id = ?');
    $cycleCheck->execute([$cycleId]);
    if (!$cycleCheck->fetchColumn()) {
        throw new RuntimeException('ไม่พบรอบการประเมินที่เลือก');
    }

    $ssoUsers = $pdo->query(
        "SELECT u.id,u.fullname FROM users u JOIN departments d ON d.id=u.department_id
         WHERE u.is_active=1 AND u.role='ss_amphoe' AND d.type='SSO' ORDER BY u.id"
    )->fetchAll();
    if (!$ssoUsers) {
        throw new RuntimeException('ไม่พบบัญชี สสอ. ที่เปิดใช้งาน จึงไม่สามารถประมวลผลได้');
    }

    $warnings = [];
    $ssoEvaluatorId = (int)$ssoUsers[0]['id'];
    if (count($ssoUsers) > 1) {
        $warnings[] = 'พบ สสอ. มากกว่า 1 คน ระบบเลือก ' . $ssoUsers[0]['fullname'];
    }

    $people = $pdo->query(
        "SELECT u.id,u.fullname,u.role,u.department_id,d.name department_name,d.type department_type
         FROM users u JOIN departments d ON d.id=u.department_id
         WHERE u.is_active=1 ORDER BY d.id,u.id"
    )->fetchAll();

    $directorsByDepartment = [];
    foreach ($people as $person) {
        if ($person['department_type'] === 'RPST' && $person['role'] === 'director') {
            $departmentId = (int)$person['department_id'];
            if (isset($directorsByDepartment[$departmentId])) {
                $warnings[] = 'หน่วยงาน ' . $person['department_name'] . ' มี ผอ.รพ.สต. มากกว่า 1 คน ระบบเลือกคนแรก';
                continue;
            }
            $directorsByDepartment[$departmentId] = (int)$person['id'];
        }
    }

    $evaluationStmt = $pdo->prepare(
        'SELECT evaluatee_id,evaluator_id FROM evaluations WHERE cycle_id=? ORDER BY id'
    );
    $evaluationStmt->execute([$cycleId]);
    $protectedEvaluations = [];
    foreach ($evaluationStmt->fetchAll() as $evaluation) {
        $protectedEvaluations[(int)$evaluation['evaluatee_id']] = (int)$evaluation['evaluator_id'];
    }

    $assignments = $protectedEvaluations;
    $skipped = 0;
    foreach ($people as $person) {
        $evaluateeId = (int)$person['id'];

        if (isset($protectedEvaluations[$evaluateeId])) {
            continue;
        }

        // The SSO account is the top-level evaluator and is not assigned to evaluate itself.
        if ($person['role'] === 'ss_amphoe') {
            continue;
        }

        if ($person['department_type'] === 'SSO' || $person['role'] === 'director') {
            $evaluatorId = $ssoEvaluatorId;
        } else {
            $departmentId = (int)$person['department_id'];
            $evaluatorId = $directorsByDepartment[$departmentId] ?? 0;
            if (!$evaluatorId) {
                $warnings[] = 'ข้าม ' . $person['fullname'] . ': ไม่พบ ผอ.รพ.สต. ของ ' . $person['department_name'];
                $skipped++;
                continue;
            }
        }

        if ($evaluateeId === $evaluatorId) {
            $warnings[] = 'ข้าม ' . $person['fullname'] . ': ผู้ประเมินและผู้รับการประเมินเป็นบุคคลเดียวกัน';
            $skipped++;
            continue;
        }
        $assignments[$evaluateeId] = $evaluatorId;
    }

    if (!$dryRun) {
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM evaluator_mapping WHERE cycle_id=?')->execute([$cycleId]);
            $insert = $pdo->prepare(
                'INSERT INTO evaluator_mapping (cycle_id,evaluatee_id,evaluator_id) VALUES (?,?,?)'
            );
            foreach ($assignments as $evaluateeId => $evaluatorId) {
                $insert->execute([$cycleId, $evaluateeId, $evaluatorId]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    return [
        'assigned' => count($assignments) - count($protectedEvaluations),
        'preserved' => count($protectedEvaluations),
        'skipped' => $skipped,
        'warnings' => array_values(array_unique($warnings)),
    ];
}
