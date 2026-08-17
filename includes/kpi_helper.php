<?php

function kpiCalculateScore(array $indicator, float $actual): float
{
    // แบบฟอร์มกำหนดช่วงคะแนนไว้ที่ 1–5 จึงให้ค่าต่ำสุดเป็น 1
    $score = 1.0;
    $direction = $indicator['scoring_direction'] ?? 'ascending';
    for ($level = 1; $level <= 5; $level++) {
        $threshold = (float)$indicator['score_' . $level . '_threshold'];
        $passes = $direction === 'descending' ? $actual <= $threshold : $actual >= $threshold;
        if ($passes) $score = (float)$level;
    }
    return $score;
}

/** Normalize indicator ordering to a continuous 1..N sequence for one cycle. */
function kpiNormalizeIndicatorOrder(PDO $pdo, int $cycleId): int
{
    $stmt = $pdo->prepare('SELECT id,order_seq FROM kpi_indicators WHERE cycle_id=? ORDER BY order_seq,id FOR UPDATE');
    $stmt->execute([$cycleId]);
    $rows = $stmt->fetchAll();
    $update = $pdo->prepare('UPDATE kpi_indicators SET order_seq=? WHERE id=? AND cycle_id=?');
    foreach ($rows as $index => $row) {
        $order = $index + 1;
        if ((int)$row['order_seq'] !== $order) $update->execute([$order,(int)$row['id'],$cycleId]);
    }
    return count($rows);
}

function kpiCalculateResult(array $indicator, float $actual, ?float $manualScore = null): array
{
    $score = $manualScore ?? kpiCalculateScore($indicator, $actual);
    $score = max(1.0, min(5.0, $score));
    $target = isset($indicator['target_value']) ? (float)$indicator['target_value'] : 0.0;
    return ['percentage' => $target != 0.0 ? ($actual / $target) * 100 : null, 'score' => $score, 'weighted_score' => ($score / 5) * (float)$indicator['weight']];
}

function kpiCycleLabel(array $cycle): string
{
    $round = preg_match('/\d+/', (string)$cycle['round_name'], $m) ? $m[0] : $cycle['round_name'];
    return 'ปีงบประมาณ ' . $cycle['fiscal_year'] . ' รอบที่ ' . $round;
}

function kpiCurrentUserDepartment(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT d.* FROM users u LEFT JOIN departments d ON d.id=u.department_id WHERE u.id=?');
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

function kpiCanManageAssignments(string $role): bool
{
    return in_array($role, ['admin', 'ss_amphoe', 'director'], true);
}

function kpiEligibleUsers(PDO $pdo, int $managerId, string $role): array
{
    $department = kpiCurrentUserDepartment($pdo, $managerId);
    if (!$department) return [];
    if (in_array($role, ['admin', 'ss_amphoe'], true)) {
        return $pdo->query("SELECT u.id,u.fullname,u.role,d.short_name,d.type FROM users u JOIN departments d ON d.id=u.department_id WHERE u.is_active=1 AND d.type='SSO' ORDER BY u.fullname")->fetchAll();
    }
    $stmt = $pdo->prepare("SELECT u.id,u.fullname,u.role,d.short_name,d.type FROM users u JOIN departments d ON d.id=u.department_id WHERE u.is_active=1 AND u.role='staff' AND d.type='RPST' AND d.id=? ORDER BY u.fullname");
    $stmt->execute([(int)$department['id']]);
    return $stmt->fetchAll();
}

/** Primary assignees use the normal scope; directors may choose themselves or staff in their own RPST. */
function kpiEligiblePrimaryUsers(PDO $pdo, int $managerId, string $role): array
{
    if ($role !== 'director') return kpiEligibleUsers($pdo, $managerId, $role);

    $stmt = $pdo->prepare(
        "SELECT u.id,u.fullname,u.role,d.short_name,d.type
         FROM users manager
         JOIN users u ON u.department_id=manager.department_id
         JOIN departments d ON d.id=u.department_id
         WHERE manager.id=? AND manager.is_active=1 AND manager.role='director'
           AND u.is_active=1 AND u.role IN ('director','staff') AND d.type='RPST'
         ORDER BY FIELD(u.role,'director','staff'),u.fullname"
    );
    $stmt->execute([$managerId]);
    return $stmt->fetchAll();
}

function kpiAssigneeRoleLabel(string $role): string
{
    return match ($role) {
        'director' => 'ผอ.รพ.สต.',
        'ss_amphoe' => 'สสอ.',
        'sso_assistant' => 'ผู้ช่วย สสอ.',
        'admin' => 'แอดมิน',
        default => 'บุคลากร',
    };
}

function kpiEnsureDirectorAssignments(PDO $pdo): void
{
    $pdo->exec(
        "INSERT IGNORE INTO kpi_assignments (indicator_id,user_id,responsibility_type,assigned_by)
         SELECT k.id,u.id,'primary',u.id
         FROM kpi_indicators k
         JOIN users u
         JOIN departments d ON d.id=u.department_id
         WHERE k.is_active=1 AND u.is_active=1 AND u.role='director' AND d.type='RPST'"
    );
    $pdo->exec(
        "INSERT IGNORE INTO kpi_assignments (indicator_id,user_id,responsibility_type,assigned_by)
         SELECT k.id,u.id,'primary',u.id
         FROM kpi_indicators k
         JOIN users u
         JOIN departments d ON d.id=u.department_id
         WHERE k.is_active=1 AND u.is_active=1 AND u.role='sso_assistant' AND d.type='SSO'"
    );
}

function kpiAllowedDepartments(PDO $pdo, int $userId, string $role): array
{
    $department = kpiCurrentUserDepartment($pdo, $userId);
    if (!$department) return [];
    if ($role === 'admin' || $department['type'] === 'SSO') {
        return $pdo->query("SELECT id,service_code,name,short_name,type FROM departments WHERE type IN ('RPST','SSO') ORDER BY FIELD(type,'RPST','SSO'),name")->fetchAll();
    }
    $stmt = $pdo->prepare('SELECT id,service_code,name,short_name,type FROM departments WHERE id=?');
    $stmt->execute([(int)$department['id']]);
    return $stmt->fetchAll();
}

function kpiAssignmentScopeIsValid(PDO $pdo, int $managerId, string $role, int $userId): bool
{
    foreach (kpiEligibleUsers($pdo, $managerId, $role) as $user) if ((int)$user['id'] === $userId) return true;
    return false;
}
