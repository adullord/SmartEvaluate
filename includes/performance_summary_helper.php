<?php

function performanceSummaryCanView(PDO $pdo, int $viewerId, string $viewerRole, int $ownerId): bool
{
    if ($viewerId === $ownerId || in_array($viewerRole, ['admin', 'ss_amphoe'], true)) {
        return true;
    }
    if ($viewerRole !== 'director') {
        return false;
    }

    $stmt = $pdo->prepare('SELECT viewer.department_id = owner.department_id FROM users viewer JOIN users owner ON owner.id=? WHERE viewer.id=? LIMIT 1');
    $stmt->execute([$ownerId, $viewerId]);
    return (bool)$stmt->fetchColumn();
}

function performanceSummaryVisibleUsers(PDO $pdo, int $viewerId, string $viewerRole): array
{
    $sql = "SELECT u.id,u.fullname,u.role,u.department_id,p.name position_name,r.name rank_name,
                   d.name department_name,d.short_name department_short_name,d.service_code,d.type department_type
            FROM users u
            JOIN positions p ON p.id=u.position_id
            JOIN ranks r ON r.id=u.rank_id
            JOIN departments d ON d.id=u.department_id
            WHERE u.is_active=1";
    $params = [];
    if ($viewerRole === 'director') {
        $sql .= ' AND u.department_id=(SELECT department_id FROM users WHERE id=?)';
        $params[] = $viewerId;
    } elseif (!in_array($viewerRole, ['admin', 'ss_amphoe'], true)) {
        $sql .= ' AND u.id=?';
        $params[] = $viewerId;
    }
    $sql .= " ORDER BY FIELD(d.type,'SSO','RPST','ADMIN'),d.name,u.fullname";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function performanceSummaryCycleLabel(array $cycle): string
{
    $round = preg_match('/\d+/', (string)$cycle['round_name'], $matches) ? $matches[0] : trim((string)$cycle['round_name']);
    return 'ปีงบประมาณ ' . $cycle['fiscal_year'] . ' รอบที่ ' . $round;
}

function performanceSummaryRoundNumber(array $cycle): string
{
    return preg_match('/\d+/', (string)$cycle['round_name'], $matches) ? $matches[0] : trim((string)$cycle['round_name']);
}

function performanceSummaryThaiDate(string $date): string
{
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    $time = strtotime($date);
    if ($time === false) return $date;
    return (int)date('j', $time) . ' ' . $months[(int)date('n', $time)] . ' ' . ((int)date('Y', $time) + 543);
}

function performanceSummaryRating(?float $score): string
{
    if ($score === null) return '-';
    if ($score >= 90) return 'ดีเด่น';
    if ($score >= 80) return 'ดีมาก';
    if ($score >= 70) return 'ดี';
    if ($score >= 60) return 'พอใช้';
    return 'ต้องปรับปรุง';
}

function performanceSummaryStatusLabel(?string $status): string
{
    if (in_array($status, ['submitted', 'acknowledged'], true)) return 'ประเมินแล้ว';
    if ($status === 'draft' || $status === 'returned') return 'ฉบับร่าง';
    return 'ยังไม่มีผล';
}

function performanceSummaryLoad(PDO $pdo, int $cycleId, int $ownerId): array
{
    $stmt = $pdo->prepare('SELECT id,fiscal_year,round_name,start_date,end_date,status FROM evaluation_cycles WHERE id=? LIMIT 1');
    $stmt->execute([$cycleId]);
    $cycle = $stmt->fetch();
    if (!$cycle) throw new RuntimeException('ไม่พบรอบการประเมิน');

    $stmt = $pdo->prepare("SELECT u.id,u.fullname,u.role,u.department_id,u.is_active,p.name position_name,r.name rank_name,
            d.name department_name,d.short_name department_short_name,d.service_code,d.type department_type
        FROM users u JOIN positions p ON p.id=u.position_id JOIN ranks r ON r.id=u.rank_id
        JOIN departments d ON d.id=u.department_id WHERE u.id=? LIMIT 1");
    $stmt->execute([$ownerId]);
    $user = $stmt->fetch();
    if (!$user) throw new RuntimeException('ไม่พบข้อมูลบุคลากร');

    $stmt = $pdo->prepare("SELECT e.id,e.status,e.total_score_base100,e.evaluator_id,ev.fullname evaluator_name,
            ep.name evaluator_position_name,er.name evaluator_rank_name
        FROM evaluations e JOIN users ev ON ev.id=e.evaluator_id
        JOIN positions ep ON ep.id=ev.position_id JOIN ranks er ON er.id=ev.rank_id
        WHERE e.cycle_id=? AND e.evaluatee_id=?
        ORDER BY FIELD(e.status,'acknowledged','submitted','draft','returned'),e.id DESC LIMIT 1");
    $stmt->execute([$cycleId, $ownerId]);
    $evaluation = $stmt->fetch() ?: null;

    if (!$evaluation) {
        $stmt = $pdo->prepare("SELECT ev.id evaluator_id,ev.fullname evaluator_name,p.name evaluator_position_name,r.name evaluator_rank_name
            FROM evaluator_mapping em JOIN users ev ON ev.id=em.evaluator_id
            JOIN positions p ON p.id=ev.position_id JOIN ranks r ON r.id=ev.rank_id
            WHERE em.cycle_id=? AND em.evaluatee_id=? ORDER BY em.id DESC LIMIT 1");
        $stmt->execute([$cycleId, $ownerId]);
        $mapped = $stmt->fetch();
        if ($mapped) $evaluation = array_merge(['id'=>null,'status'=>null,'total_score_base100'=>null], $mapped);
    }

    // Historical/imported records may not yet have evaluator_mapping. Resolve the supervisor
    // from the same rules used by the system so the official summary never shows a blank name.
    if (!$evaluation) {
        if ((string)$user['department_type'] === 'RPST' && (string)$user['role'] !== 'director') {
            $stmt = $pdo->prepare("SELECT ev.id evaluator_id,ev.fullname evaluator_name,p.name evaluator_position_name,r.name evaluator_rank_name
                FROM users ev JOIN positions p ON p.id=ev.position_id JOIN ranks r ON r.id=ev.rank_id
                WHERE ev.is_active=1 AND ev.role='director' AND ev.department_id=? ORDER BY ev.id LIMIT 1");
            $stmt->execute([(int)$user['department_id']]);
        } else {
            $stmt = $pdo->query("SELECT ev.id evaluator_id,ev.fullname evaluator_name,p.name evaluator_position_name,r.name evaluator_rank_name
                FROM users ev JOIN positions p ON p.id=ev.position_id JOIN ranks r ON r.id=ev.rank_id
                WHERE ev.is_active=1 AND ev.role='ss_amphoe' ORDER BY ev.id LIMIT 1");
        }
        $supervisor = $stmt->fetch();
        if ($supervisor) $evaluation = array_merge(['id'=>null,'status'=>null,'total_score_base100'=>null], $supervisor);
    }

    $directorGetsAll = (string)$user['role'] === 'director';
    $sql = "SELECT k.id,k.order_seq,k.name,k.target_label,k.target_value,k.unit,k.weight,
                   k.score_1_threshold,k.score_2_threshold,k.score_3_threshold,k.score_4_threshold,k.score_5_threshold,
                   a.responsibility_type,kr.actual_value,kr.percentage,kr.score,kr.weighted_score
            FROM kpi_indicators k
            LEFT JOIN kpi_assignments a ON a.indicator_id=k.id AND a.user_id=?
            LEFT JOIN kpi_results kr ON kr.indicator_id=k.id AND kr.department_id=?
            WHERE k.cycle_id=? AND k.is_active=1 AND (a.id IS NOT NULL OR ?=1)
            ORDER BY k.order_seq,k.id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ownerId, (int)$user['department_id'], $cycleId, $directorGetsAll ? 1 : 0]);
    $kpis = $stmt->fetchAll();

    $kpiWeight = 0.0;
    $kpiWeightedScore = 0.0;
    $kpiCompleted = 0;
    foreach ($kpis as &$kpi) {
        $kpi['weight'] = (float)$kpi['weight'];
        $kpiWeight += $kpi['weight'];
        if ($kpi['score'] !== null && $kpi['weighted_score'] !== null) {
            $kpiCompleted++;
            $kpiWeightedScore += (float)$kpi['weighted_score'];
        }
    }
    unset($kpi);
    $kpiComplete = count($kpis) > 0 && $kpiCompleted === count($kpis) && $kpiWeight > 0;
    $kpiScore = $kpiComplete ? round($kpiWeightedScore / $kpiWeight * 100, 2, PHP_ROUND_HALF_UP) : null;

    $competencyComplete = $evaluation && in_array((string)$evaluation['status'], ['submitted','acknowledged'], true);
    $competencyScore = $competencyComplete ? round((float)$evaluation['total_score_base100'], 2, PHP_ROUND_HALF_UP) : null;

    $stmt = $pdo->prepare('SELECT id,status,final_score FROM component3_assessments WHERE cycle_id=? AND user_id=? LIMIT 1');
    $stmt->execute([$cycleId, $ownerId]);
    $component3 = $stmt->fetch() ?: null;
    $component3Score = $component3 && (string)$component3['status'] === 'submitted'
        ? round((float)$component3['final_score'], 2, PHP_ROUND_HALF_UP) : null;

    $overallScore = ($kpiScore !== null && $competencyScore !== null && $component3Score !== null)
        ? round($kpiScore * 0.70 + $competencyScore * 0.15 + $component3Score * 0.15, 2, PHP_ROUND_HALF_UP)
        : null;

    return [
        'cycle'=>$cycle,
        'user'=>$user,
        'evaluation'=>$evaluation,
        'kpis'=>$kpis,
        'kpi_total_weight'=>$kpiWeight,
        'kpi_completed'=>$kpiCompleted,
        'kpi_score'=>$kpiScore,
        'competency_score'=>$competencyScore,
        'component3'=>$component3,
        'component3_score'=>$component3Score,
        'overall_score'=>$overallScore,
        'rating'=>performanceSummaryRating($overallScore),
    ];
}

function performanceSummaryOverview(PDO $pdo, int $cycleId, array $users): array
{
    if (!$users) return [];
    $userIds = array_map(static fn(array $user): int => (int)$user['id'], $users);
    $marks = implode(',', array_fill(0, count($userIds), '?'));
    $overview = [];
    foreach ($users as $user) {
        $overview[(int)$user['id']] = [
            'kpi_score'=>null, 'competency_score'=>null, 'component3_score'=>null,
            'overall_score'=>null, 'rating'=>'-',
        ];
    }

    $stmt = $pdo->prepare("SELECT evaluatee_id,status,total_score_base100,id FROM evaluations
        WHERE cycle_id=? AND evaluatee_id IN ($marks)
        ORDER BY FIELD(status,'acknowledged','submitted','draft','returned'),id DESC");
    $stmt->execute([$cycleId, ...$userIds]);
    $seen = [];
    foreach ($stmt->fetchAll() as $row) {
        $uid = (int)$row['evaluatee_id'];
        if (isset($seen[$uid])) continue;
        $seen[$uid] = true;
        if (in_array((string)$row['status'], ['submitted','acknowledged'], true)) {
            $overview[$uid]['competency_score'] = round((float)$row['total_score_base100'], 2, PHP_ROUND_HALF_UP);
        }
    }

    $stmt = $pdo->prepare("SELECT user_id,status,final_score FROM component3_assessments WHERE cycle_id=? AND user_id IN ($marks)");
    $stmt->execute([$cycleId, ...$userIds]);
    foreach ($stmt->fetchAll() as $row) {
        if ((string)$row['status'] === 'submitted') {
            $overview[(int)$row['user_id']]['component3_score'] = round((float)$row['final_score'], 2, PHP_ROUND_HALF_UP);
        }
    }

    $stmt = $pdo->prepare("SELECT a.user_id,k.id indicator_id,k.weight,kr.department_id,kr.score,kr.weighted_score
        FROM kpi_assignments a JOIN kpi_indicators k ON k.id=a.indicator_id AND k.cycle_id=? AND k.is_active=1
        JOIN users u ON u.id=a.user_id
        LEFT JOIN kpi_results kr ON kr.indicator_id=k.id AND kr.department_id=u.department_id
        WHERE a.user_id IN ($marks)");
    $stmt->execute([$cycleId, ...$userIds]);
    $assigned = [];
    foreach ($stmt->fetchAll() as $row) $assigned[(int)$row['user_id']][] = $row;

    $directorDepartments = [];
    foreach ($users as $user) if ((string)$user['role'] === 'director') $directorDepartments[(int)$user['department_id']] = true;
    $allForDepartment = [];
    if ($directorDepartments) {
        $departmentIds = array_keys($directorDepartments);
        $departmentMarks = implode(',', array_fill(0, count($departmentIds), '?'));
        $stmt = $pdo->prepare("SELECT k.id indicator_id,k.weight,d.id department_id,kr.score,kr.weighted_score
            FROM kpi_indicators k CROSS JOIN departments d
            LEFT JOIN kpi_results kr ON kr.indicator_id=k.id AND kr.department_id=d.id
            WHERE k.cycle_id=? AND k.is_active=1 AND d.id IN ($departmentMarks) ORDER BY k.order_seq,k.id");
        $stmt->execute([$cycleId, ...$departmentIds]);
        foreach ($stmt->fetchAll() as $row) $allForDepartment[(int)$row['department_id']][] = $row;
    }

    foreach ($users as $user) {
        $uid = (int)$user['id'];
        $rows = (string)$user['role'] === 'director'
            ? ($allForDepartment[(int)$user['department_id']] ?? [])
            : ($assigned[$uid] ?? []);
        $weight = 0.0; $weighted = 0.0; $completed = 0;
        foreach ($rows as $row) {
            $weight += (float)$row['weight'];
            if ($row['score'] !== null && $row['weighted_score'] !== null) {
                $completed++;
                $weighted += (float)$row['weighted_score'];
            }
        }
        if ($rows && $completed === count($rows) && $weight > 0) {
            $overview[$uid]['kpi_score'] = round($weighted / $weight * 100, 2, PHP_ROUND_HALF_UP);
        }
        $kpi = $overview[$uid]['kpi_score'];
        $competency = $overview[$uid]['competency_score'];
        $component3 = $overview[$uid]['component3_score'];
        if ($kpi !== null && $competency !== null && $component3 !== null) {
            $total = round($kpi * 0.70 + $competency * 0.15 + $component3 * 0.15, 2, PHP_ROUND_HALF_UP);
            $overview[$uid]['overall_score'] = $total;
            $overview[$uid]['rating'] = performanceSummaryRating($total);
        }
    }
    return $overview;
}
