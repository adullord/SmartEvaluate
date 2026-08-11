<?php

function component3DepartmentScore(?string $serviceCode): ?int
{
    $scores = [
        '10047' => 5, '10040' => 5, '10045' => 5,
        '10039' => 4, '10046' => 4, '14992' => 4, '10042' => 4,
        '10041' => 3, '10043' => 3, '00914' => 3,
    ];
    $code = trim((string)$serviceCode);
    return $scores[$code] ?? null;
}

function component3UserContext(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT u.id,u.fullname,u.role,u.department_id,u.is_active,
            d.service_code,d.name department_name,d.short_name department_short_name,
            p.name position_name,r.name rank_name
        FROM users u
        JOIN departments d ON d.id=u.department_id
        JOIN positions p ON p.id=u.position_id
        JOIN ranks r ON r.id=u.rank_id
        WHERE u.id=? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user || !(int)$user['is_active']) throw new RuntimeException('ไม่พบข้อมูลบุคลากรที่ใช้งานอยู่');
    $user['includes_items_1_2'] = (string)$user['service_code'] === '00914' || (string)$user['role'] === 'director';
    $user['department_score'] = component3DepartmentScore($user['service_code']);
    return $user;
}

function component3ItemDefinitions(bool $includeItemsOneTwo, ?int $departmentScore): array
{
    $items = [
        1 => [
            'number' => 1,
            'name' => 'การประชุมประจำเดือน สสอ. และถ่ายทอดเจ้าหน้าที่ในหน่วยงาน',
            'weight' => 15.0,
            'target' => 5.0,
            'target_label' => '5 ครั้ง',
            'thresholds' => [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5],
            'responsible' => 'ผอ.รพ.สต. / เจ้าหน้าที่ สสอ.',
            'input_type' => 'count',
            'audience' => 'sso_director',
            'applicable' => $includeItemsOneTwo,
            'input_label' => 'จำนวนครั้งที่เข้าร่วม',
        ],
        2 => [
            'number' => 2,
            'name' => 'การประชุม คปสอ. และถ่ายทอดเจ้าหน้าที่ในหน่วยงาน',
            'weight' => 15.0,
            'target' => 5.0,
            'target_label' => '5 ครั้ง',
            'thresholds' => [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5],
            'responsible' => 'คณะกรรมการ คปสอ.',
            'input_type' => 'count',
            'audience' => 'sso_director',
            'applicable' => $includeItemsOneTwo,
            'input_label' => 'จำนวนครั้งที่เข้าร่วม',
        ],
        4 => [
            'number' => 4,
            'name' => 'การเข้าร่วมกิจกรรมขององค์กร เช่น เดิน วิ่ง ป้องกันอัมพาต',
            'weight' => 20.0,
            'target' => 2.0,
            'target_label' => '2 ครั้ง',
            'thresholds' => [1 => 1, 2 => 5],
            'responsible' => '',
            'input_type' => 'count',
            'audience' => 'all',
            'applicable' => true,
            'input_label' => 'จำนวนครั้งที่เข้าร่วม',
        ],
        5 => [
            'number' => 5,
            'name' => 'พื้นที่ลำบากห่างไกลจากอำเภอบันนังสตา',
            'weight' => 15.0,
            'target' => null,
            'target_label' => 'คะแนนตามหน่วยบริการ',
            'thresholds' => [],
            'responsible' => '',
            'input_type' => 'department_score',
            'audience' => 'all',
            'applicable' => true,
            'input_label' => 'คำนวณอัตโนมัติ',
            'automatic_score' => $departmentScore,
        ],
        6 => [
            'number' => 6,
            'name' => 'พฤติกรรมการมาทำงานตามเวลาราชการ ไม่น้อยกว่าร้อยละ 100',
            'weight' => 35.0,
            'target' => 100.0,
            'target_label' => 'ร้อยละ 100',
            'thresholds' => [60 => 1, 70 => 2, 80 => 3, 90 => 4, 100 => 5],
            'responsible' => '',
            'input_type' => 'percentage',
            'audience' => 'all',
            'applicable' => true,
            'input_label' => 'ร้อยละการมาทำงานตรงเวลา',
        ],
    ];
    return array_filter($items, static fn(array $item): bool => (bool)$item['applicable']);
}

function component3EnsureCycleItems(PDO $pdo, int $cycleId): void
{
    $check = $pdo->prepare('SELECT 1 FROM component3_cycle_settings WHERE cycle_id=? LIMIT 1');
    $check->execute([$cycleId]);
    if ($check->fetchColumn()) return;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT IGNORE INTO component3_cycle_settings(cycle_id) VALUES(?)');
        $stmt->execute([$cycleId]);
        if ($stmt->rowCount() === 1) {
            $insert = $pdo->prepare('INSERT IGNORE INTO component3_items(cycle_id,item_no,name,weight,target_value,target_label,input_type,audience,responsible,score_1_threshold,score_2_threshold,score_3_threshold,score_4_threshold,score_5_threshold,is_active) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)');
            foreach (component3ItemDefinitions(true, 3) as $item) {
                $scoreThresholds = [1=>null,2=>null,3=>null,4=>null,5=>null];
                foreach ($item['thresholds'] as $threshold => $level) $scoreThresholds[(int)$level] = (float)$threshold;
                $insert->execute([$cycleId,$item['number'],$item['name'],$item['weight'],$item['target'],$item['target_label'],$item['input_type'],$item['audience'],$item['responsible'],...array_values($scoreThresholds)]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function component3ItemsForCycle(PDO $pdo, int $cycleId, bool $includeRestricted, ?int $departmentScore, bool $activeOnly = true): array
{
    component3EnsureCycleItems($pdo, $cycleId);
    $sql = 'SELECT * FROM component3_items WHERE cycle_id=?' . ($activeOnly ? ' AND is_active=1' : '') . ' ORDER BY item_no,id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cycleId]);
    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        if ($row['audience'] === 'sso_director' && !$includeRestricted) continue;
        $thresholds = [];
        for ($level=1; $level<=5; $level++) {
            $value = $row['score_' . $level . '_threshold'];
            if ($value !== null && $value !== '') $thresholds[(string)(float)$value] = $level;
        }
        $item = [
            'id' => (int)$row['id'], 'number' => (int)$row['item_no'], 'name' => $row['name'],
            'weight' => (float)$row['weight'], 'target' => $row['target_value'] === null ? null : (float)$row['target_value'],
            'target_label' => $row['target_label'] ?: '-', 'thresholds' => $thresholds,
            'responsible' => (string)($row['responsible'] ?? ''), 'input_type' => $row['input_type'],
            'audience' => $row['audience'], 'applicable' => true,
            'input_label' => $row['input_type'] === 'percentage' ? 'ร้อยละผลการดำเนินงาน' : 'ผลการดำเนินงาน',
        ];
        if ($row['input_type'] === 'department_score') {
            $item['automatic_score'] = $departmentScore;
            $item['input_label'] = 'คำนวณอัตโนมัติ';
        }
        $items[(int)$row['item_no']] = $item;
    }
    return $items;
}

function component3CalculateItem(array $item, ?float $actualValue): array
{
    if (array_key_exists('automatic_score', $item)) {
        if ($item['automatic_score'] === null) throw new RuntimeException('ยังไม่ได้กำหนดคะแนนข้อ 5 สำหรับหน่วยบริการนี้');
        $score = (float)$item['automatic_score'];
        return ['actual_value' => null, 'percentage' => null, 'score' => $score, 'weighted_score' => round($score / 5 * $item['weight'], 4, PHP_ROUND_HALF_UP)];
    }
    if ($actualValue === null || !is_finite($actualValue) || $actualValue < 0) {
        throw new RuntimeException('กรุณากรอกผลการดำเนินงานข้อ ' . $item['number'] . ' ให้ถูกต้อง');
    }
    if (($item['input_type'] ?? '') === 'percentage' && $actualValue > 100) {
        throw new RuntimeException('ผลการดำเนินงานข้อ ' . $item['number'] . ' ต้องอยู่ระหว่าง 0 ถึง 100');
    }
    if (($item['input_type'] ?? 'count') === 'count' && floor($actualValue) !== $actualValue) {
        throw new RuntimeException('ผลการดำเนินงานข้อ ' . $item['number'] . ' ต้องเป็นจำนวนเต็ม');
    }
    if (($item['input_type'] ?? 'count') === 'count' && $actualValue > 1000) {
        throw new RuntimeException('ผลการดำเนินงานข้อ ' . $item['number'] . ' มีค่าสูงเกินกว่าที่ระบบรองรับ');
    }
    // องค์ประกอบที่ 3 กำหนดคะแนนต่ำสุดเป็น 1 แม้ผลยังไม่ถึงเกณฑ์ระดับถัดไป
    $score = 1.0;
    foreach ($item['thresholds'] as $threshold => $thresholdScore) {
        if ($actualValue >= (float)$threshold) $score = (float)$thresholdScore;
    }
    $percentage = $item['target'] > 0 ? round($actualValue / $item['target'] * 100, 2, PHP_ROUND_HALF_UP) : null;
    return [
        'actual_value' => $actualValue,
        'percentage' => $percentage,
        'score' => $score,
        'weighted_score' => round($score / 5 * $item['weight'], 4, PHP_ROUND_HALF_UP),
    ];
}

function component3CanView(PDO $pdo, int $viewerId, string $viewerRole, int $ownerId, int $cycleId): bool
{
    if ($viewerId === $ownerId || $viewerRole === 'admin') return true;
    if (!in_array($viewerRole, ['ss_amphoe', 'director'], true)) return false;
    $stmt = $pdo->prepare('SELECT 1 FROM evaluator_mapping WHERE evaluator_id=? AND evaluatee_id=? AND cycle_id=? LIMIT 1');
    $stmt->execute([$viewerId, $ownerId, $cycleId]);
    return (bool)$stmt->fetchColumn();
}

function component3StatusLabel(?string $status): string
{
    return $status === 'submitted' ? 'ประเมินแล้ว' : ($status === 'draft' ? 'ฉบับร่าง' : 'ยังไม่ประเมิน');
}

function component3CycleLabel(array $cycle): string
{
    return 'ปีงบประมาณ ' . $cycle['fiscal_year'] . ' รอบที่ ' . preg_replace('/\D+/u', '', (string)$cycle['round_name']);
}
