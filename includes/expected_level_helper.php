<?php

/** Return the competency level required by an approved position/rank pair. */
function expectedLevelByPositionRank(string $positionName, string $rankName): ?int
{
    $levels = [
        'สาธารณสุขอำเภอ' => ['อำนวยการระดับสูง' => 2],
        'ผู้อำนวยการ รพ.สต.' => ['ผู้อำนวยการ' => 2],
        'นักวิชาการสาธารณสุข' => ['ปฏิบัติการ' => 1, 'ชำนาญการ' => 1, 'ชำนาญการพิเศษ' => 2],
        'นักสาธารณสุข' => ['ปฏิบัติการ' => 1, 'ชำนาญการ' => 1],
        'พยาบาลวิชาชีพ' => ['ปฏิบัติการ' => 1, 'ชำนาญการ' => 1],
        'นักวิชาการคอมพิวเตอร์' => ['ปฏิบัติการ' => 1, 'ชำนาญการ' => 1],
        'แพทย์แผนไทย' => ['ปฏิบัติการ' => 1, 'ชำนาญการ' => 1],
        'เจ้าพนักงานสาธารณสุข' => ['ปฏิบัติงาน' => 1, 'ชำนาญงาน' => 1, 'ชำนาญการ' => 1, 'อาวุโส' => 2],
        'เจ้าพนักงานทันตสาธารณสุข' => ['ปฏิบัติงาน' => 1, 'ชำนาญงาน' => 1, 'ชำนาญการ' => 1],
        'เจ้าพนักงานการเงินและบัญชี' => ['ปฏิบัติงาน' => 1, 'ชำนาญงาน' => 1, 'ชำนาญการ' => 1],
    ];
    return $levels[trim($positionName)][trim($rankName)] ?? null;
}

function expectedLevelFromIds(PDO $pdo, int $positionId, int $rankId): int
{
    $stmt = $pdo->prepare(
        'SELECT p.name AS position_name, r.name AS rank_name
         FROM positions p CROSS JOIN ranks r WHERE p.id = ? AND r.id = ?'
    );
    $stmt->execute([$positionId, $rankId]);
    $pair = $stmt->fetch();
    if (!$pair) throw new RuntimeException('ไม่พบตำแหน่งหรือระดับตำแหน่งในระบบ');
    $level = expectedLevelByPositionRank($pair['position_name'], $pair['rank_name']);
    if ($level === null) throw new RuntimeException('ตำแหน่งและระดับตำแหน่งไม่สัมพันธ์กัน');
    return $level;
}

