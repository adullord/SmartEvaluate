<?php

use PhpOffice\PhpSpreadsheet\IOFactory;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/user_role_helper.php';
require_once __DIR__ . '/../includes/expected_level_helper.php';

function normalizeImportText(mixed $value): string
{
    $text = str_replace("\xC2\xA0", ' ', trim((string)$value));
    return preg_replace('/\s+/u', ' ', $text) ?? $text;
}

function importLookupByName(PDO $pdo, string $table): array
{
    // ชื่อตาราง/คอลัมน์ bind ไม่ได้ จึงเลือกได้เฉพาะ SQL คงที่ใน whitelist นี้
    $lookupQueries = [
        'departments' => 'SELECT id, name, service_code, short_name FROM departments',
        'positions' => 'SELECT id, name FROM positions',
        'ranks' => 'SELECT id, name FROM ranks',
    ];
    if (!isset($lookupQueries[$table])) {
        throw new InvalidArgumentException('Invalid lookup table');
    }

    $lookup = [];
    foreach ($pdo->query($lookupQueries[$table])->fetchAll() as $row) {
        $lookup[normalizeImportText($row['name'])] = (int)$row['id'];
        if ($table === 'departments') {
            $lookup[normalizeImportText($row['service_code'] ?? '')] = (int)$row['id'];
            $lookup[normalizeImportText($row['short_name'] ?? '')] = (int)$row['id'];
            $lookup[normalizeImportText(($row['service_code'] ?? '') . ' - ' . ($row['short_name'] ?? ''))] = (int)$row['id'];
        }
    }
    return $lookup;
}

/**
 * Validate and import personnel from the first worksheet of an Excel file.
 * No rows are written when any validation error is found.
 */
function importUsersFromSpreadsheet(PDO $pdo, array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('กรุณาเลือกไฟล์ Excel ที่ต้องการนำเข้า');
    }

    if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
        throw new RuntimeException('ไฟล์มีขนาดเกิน 10 MB');
    }

    $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($extension, ['xlsx', 'xls'], true)) {
        throw new RuntimeException('รองรับเฉพาะไฟล์ .xlsx และ .xls');
    }

    if (PHP_SAPI !== 'cli' && !is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
        throw new RuntimeException('ไฟล์อัปโหลดไม่ถูกต้อง');
    }
    $readerType = IOFactory::identify((string)$file['tmp_name']);
    if (!in_array($readerType, ['Xlsx', 'Xls'], true)) {
        throw new RuntimeException('เนื้อหาไฟล์ไม่ใช่ Excel ที่รองรับ');
    }

    $spreadsheet = IOFactory::load($file['tmp_name']);
    $sheet = $spreadsheet->getSheetByName('ข้อมูลนำเข้า') ?? $spreadsheet->getActiveSheet();
    $requiredHeaders = [
        'A' => 'เลขประจำตัวประชาชน',
        'B' => 'รหัสผ่าน',
        'C' => 'ชื่อ-นามสกุล',
        'D' => 'บทบาท',
        'E' => 'หน่วยบริการ',
        'F' => 'ตำแหน่ง',
        'G' => 'ระดับตำแหน่ง',
        'H' => 'ระดับที่คาดหวัง',
        'I' => 'สถานะใช้งาน',
    ];

    $errors = [];
    foreach ($requiredHeaders as $column => $expectedHeader) {
        $actual = normalizeImportText($sheet->getCell($column . '1')->getFormattedValue());
        if ($actual !== $expectedHeader) {
            $errors[] = "คอลัมน์ {$column}1 ต้องเป็น “{$expectedHeader}”";
        }
    }
    if ($errors) {
        return ['imported' => 0, 'errors' => $errors];
    }

    $departments = importLookupByName($pdo, 'departments');
    $positions = importLookupByName($pdo, 'positions');
    $ranks = importLookupByName($pdo, 'ranks');
    $existingUsers = array_fill_keys($pdo->query('SELECT username FROM users')->fetchAll(PDO::FETCH_COLUMN), true);
    $seenUsers = [];
    $validRows = [];

    $roleAliases = [
        'staff' => 'staff', 'บุคลากร' => 'staff',
        'director' => 'director', 'ผอ.รพ.สต.' => 'director', 'ผู้อำนวยการ รพ.สต.' => 'director',
        'ss_amphoe' => 'ss_amphoe', 'สสอ.' => 'ss_amphoe', 'สาธารณสุขอำเภอ' => 'ss_amphoe',
    ];
    $activeAliases = [
        '1' => 1, 'ใช้งาน' => 1, 'active' => 1,
        '0' => 0, 'ปิดใช้งาน' => 0, 'inactive' => 0,
    ];

    $highestRow = min(5000, $sheet->getHighestDataRow());
    for ($row = 2; $row <= $highestRow; $row++) {
        $values = [];
        foreach (array_keys($requiredHeaders) as $column) {
            $values[$column] = normalizeImportText($sheet->getCell($column . $row)->getFormattedValue());
        }
        if (implode('', $values) === '') {
            continue;
        }

        $rowErrors = [];
        $username = preg_replace('/\.0$/', '', $values['A']) ?? $values['A'];
        if (!preg_match('/^\d{13}$/', $username)) {
            $rowErrors[] = 'เลขประจำตัวประชาชนต้องเป็นตัวเลข 13 หลัก';
        } elseif (isset($existingUsers[$username])) {
            $rowErrors[] = 'เลขประจำตัวประชาชนมีอยู่ในระบบแล้ว';
        } elseif (isset($seenUsers[$username])) {
            $rowErrors[] = 'เลขประจำตัวประชาชนซ้ำภายในไฟล์';
        }
        $seenUsers[$username] = true;

        $password = $values['B'] !== '' ? $values['B'] : substr($username, -6);
        if (strlen($password) < 4) {
            $rowErrors[] = 'รหัสผ่านต้องมีอย่างน้อย 4 ตัวอักษร';
        }
        if ($values['C'] === '') {
            $rowErrors[] = 'กรุณากรอกชื่อ-นามสกุล';
        }

        $role = $roleAliases[$values['D']] ?? null;
        if ($role === null) {
            $rowErrors[] = 'บทบาทไม่ถูกต้อง';
        }
        $departmentId = $departments[$values['E']] ?? null;
        if ($departmentId === null) {
            $rowErrors[] = 'ไม่พบหน่วยบริการในระบบ';
        }
        $positionId = $positions[$values['F']] ?? null;
        if ($positionId === null) {
            $rowErrors[] = 'ไม่พบตำแหน่งในระบบ';
        }
        $rankId = $ranks[$values['G']] ?? null;
        if ($rankId === null) {
            $rowErrors[] = 'ไม่พบระดับตำแหน่งในระบบ';
        }

        $expectedLevel = null;
        if ($positionId !== null && $rankId !== null) {
            $expectedLevel = expectedLevelByPositionRank($values['F'], $values['G']);
            if ($expectedLevel === null) $rowErrors[] = 'ตำแหน่งและระดับตำแหน่งไม่สัมพันธ์กัน';
        }
        $activeText = $values['I'] === '' ? 'ใช้งาน' : $values['I'];
        $isActive = $activeAliases[$activeText] ?? null;
        if ($isActive === null) {
            $rowErrors[] = 'สถานะใช้งานต้องเป็น ใช้งาน หรือ ปิดใช้งาน';
        }

        if ($rowErrors) {
            $errors[] = 'แถวที่ ' . $row . ': ' . implode(', ', $rowErrors);
            continue;
        }

        $validRows[] = [
            $username, password_hash($password, PASSWORD_DEFAULT), $values['C'], $role,
            $departmentId, $positionId, $rankId, $expectedLevel, $isActive,
        ];
    }

    if (!$validRows && !$errors) {
        $errors[] = 'ไม่พบข้อมูลบุคลากรในไฟล์ กรุณากรอกข้อมูลตั้งแต่แถวที่ 2';
    }
    if ($errors) {
        return ['imported' => 0, 'errors' => $errors];
    }

    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare(
            'INSERT INTO users (username,password,fullname,role,department_id,position_id,rank_id,expected_level,is_active) VALUES (?,?,?,?,?,?,?,?,?)'
        );
        foreach ($validRows as $validRow) {
            $insert->execute($validRow);
            syncUserRoles($pdo, (int)$pdo->lastInsertId(), $validRow[3]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return ['imported' => count($validRows), 'errors' => []];
}
