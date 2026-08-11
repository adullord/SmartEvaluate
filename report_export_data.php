<?php

function loadEvaluationExportData(PDO $pdo, int $evaluationId, int $userId, string $userRole): array
{
    $stmt = $pdo->prepare("
        SELECT e.*, u.fullname AS evaluatee_name, u.expected_level, u.position_id,
               p.name AS pos_name, r.name AS rank_name, d.name AS dept_name,
               evaluator.fullname AS evaluator_name,
               evaluator_position.name AS evaluator_pos_name,
               evaluator_rank.name AS evaluator_rank_name,
               c.round_name, c.fiscal_year
        FROM evaluations e
        JOIN users u ON u.id = e.evaluatee_id
        JOIN users evaluator ON evaluator.id = e.evaluator_id
        JOIN positions evaluator_position ON evaluator_position.id = evaluator.position_id
        JOIN ranks evaluator_rank ON evaluator_rank.id = evaluator.rank_id
        JOIN positions p ON p.id = u.position_id
        JOIN ranks r ON r.id = u.rank_id
        JOIN departments d ON d.id = u.department_id
        JOIN evaluation_cycles c ON c.id = e.cycle_id
        WHERE e.id = ?
    ");
    $stmt->execute([$evaluationId]);
    $evaluation = $stmt->fetch();

    if (!$evaluation) {
        throw new RuntimeException('ไม่พบข้อมูลการประเมิน');
    }
    $canExportAssigned = in_array($userRole, ['ss_amphoe', 'director'], true)
        && (int)$evaluation['evaluator_id'] === $userId;
    if ($userRole !== 'admin' && !$canExportAssigned) {
        throw new RuntimeException('เฉพาะแอดมินหรือผู้ประเมินของรายการนี้เท่านั้นที่ส่งออกรายงานได้');
    }

    $stmt = $pdo->prepare("
        SELECT c.id, c.name, c.type, c.order_seq, t.weight,
               COALESCE(SUM(es.score), 0) AS score_sum,
               COUNT(es.id) AS score_count,
               GROUP_CONCAT(DISTINCT NULLIF(TRIM(es.reason), '') SEPARATOR ' | ') AS notes
        FROM evaluation_templates t
        JOIN competencies c ON c.id = t.competency_id
        LEFT JOIN indicators i ON i.competency_id = c.id
        LEFT JOIN evaluation_scores es ON es.indicator_id = i.id AND es.evaluation_id = ?
        WHERE t.position_id = ? AND t.expected_level = ?
        GROUP BY c.id, c.name, c.type, c.order_seq, t.weight
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
    $stmt->execute([$evaluationId, (int)$evaluation['position_id'], (int)$evaluation['expected_level']]);
    $competencies = $stmt->fetchAll();

    foreach ($competencies as &$competency) {
        $competency['display_name'] = trim((string)preg_replace('/\s*\([A-Za-z][^)]*\)\s*$/u', '', $competency['name']));
        $competency['raw_average'] = (int)$competency['score_count'] > 0
            ? round((float)$competency['score_sum'] / (int)$competency['score_count'], 2, PHP_ROUND_HALF_UP)
            : 0.0;
        $competency['weight'] = (float)$competency['weight'];
        $competency['weighted'] = $competency['raw_average'] * ($competency['weight'] / 100);
    }
    unset($competency);

    return ['evaluation' => $evaluation, 'competencies' => $competencies];
}

function safeReportFilename(string $name): string
{
    $clean = preg_replace('/[\\\\\/:*?"<>|]+/u', '_', trim($name));
    return $clean !== '' ? $clean : 'report';
}
