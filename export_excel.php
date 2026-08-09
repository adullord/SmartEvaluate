<?php
require_once 'config.php';
require_once 'vendor/autoload.php';
require_once 'report_export_data.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

try {
    $evaluationId = requestInt($_GET['id'] ?? null, 'id');
    if (!$evaluationId) {
        throw new RuntimeException('ไม่พบรหัสการประเมิน');
    }
    $report = loadEvaluationExportData($pdo, $evaluationId, (int)$_SESSION['user_id'], (string)$_SESSION['role']);
    $evaluation = $report['evaluation'];
    $competencies = $report['competencies'];

    $templatePath = __DIR__ . '/สรุปประเมิน.xlsx';
    if (!is_file($templatePath)) {
        throw new RuntimeException('ไม่พบไฟล์แม่แบบ สรุปประเมิน.xlsx');
    }

    $spreadsheet = IOFactory::load($templatePath);
    $sheet = $spreadsheet->getSheetByName('สรุป') ?? $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A3', 'ชื่อผู้รับการประเมิน  (นาย/นาง/นางสาว)  ' . $evaluation['evaluatee_name']);
    $sheet->setCellValue('A4', 'ชื่อผู้บังคับบัญชาชั้นต้น/ผู้ประเมิน (นาย/นาง/นางสาว) ' . $evaluation['evaluator_name']);
    $sheet->setCellValue('F2', 'รอบการประเมิน  ' . $evaluation['round_name']);
    $sheet->setCellValue('G2', '      ปีงบประมาณ ' . $evaluation['fiscal_year']);
    $sheet->setCellValue('G3', $evaluation['acknowledged_at']
        ? 'รับทราบสมรรถนะแล้ว เมื่อวันที่ ' . date('d/m/Y', strtotime($evaluation['acknowledged_at']))
        : 'รับทราบสมรรถนะแล้ว เมื่อวันที่ .....................');

    $coreRows = [10, 11, 12, 13];
    $functionalRows = [15, 16, 17];
    $coreIndex = 0;
    $functionalIndex = 0;
    $number = 1;
    foreach ($competencies as $competency) {
        $row = $competency['type'] === 'core'
            ? ($coreRows[$coreIndex++] ?? null)
            : ($functionalRows[$functionalIndex++] ?? null);
        if ($row === null) {
            continue;
        }
        $sheet->setCellValue("A{$row}", $number++ . '. ' . $competency['display_name']);
        $sheet->setCellValue("B{$row}", (int)$evaluation['expected_level']);
        // ใช้ค่าเฉลี่ยที่ปัดเป็น 2 ตำแหน่งก่อนถ่วงน้ำหนัก ให้ตรงกับวิธีคำนวณของระบบ
        $sheet->setCellValue("C{$row}", $competency['raw_average']);
        $sheet->setCellValue("D{$row}", $competency['weight'] / 100);
        $sheet->setCellValue("E{$row}", "=C{$row}*D{$row}");
        $sheet->setCellValue("F{$row}", $competency['notes'] ?? '');
    }

    foreach (array_merge($coreRows, $functionalRows) as $row) {
        $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode('0.00');
        $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode('0%');
        $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode('0.0');
    }
    $sheet->setCellValue('C18', 'รวม');
    $sheet->setCellValue('D18', '=SUM(D10:D13,D15:D17)');
    $sheet->setCellValue('E18', '=SUM(E10:E13,E15:E17)');
    $sheet->setCellValue('E19', '=E18*20');
    $sheet->getStyle('D18')->getNumberFormat()->setFormatCode('= 0%');
    $sheet->getStyle('E18')->getNumberFormat()->setFormatCode('0.0');
    $sheet->getStyle('E19')->getNumberFormat()->setFormatCode('0.00');
    $spreadsheet->getCalculationEngine()->clearCalculationCache();

    $filename = 'สรุปประเมิน_' . safeReportFilename($evaluation['evaluatee_name']) . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($filename));
    header('Cache-Control: max-age=0, no-store');
    $writer = new Xlsx($spreadsheet);
    $writer->setPreCalculateFormulas(true);
    $writer->save('php://output');
    exit;
} catch (Throwable $e) {
    http_response_code(403);
    exit(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
