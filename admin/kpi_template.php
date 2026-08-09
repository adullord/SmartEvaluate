<?php
require_once '_bootstrap.php';
require_once __DIR__ . '/../includes/kpi_helper.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$cycleId = requestInt($_GET['cycle_id'] ?? null, 'cycle_id');
$stmt = $pdo->prepare('SELECT * FROM evaluation_cycles WHERE id=?');
$stmt->execute([$cycleId]);
$cycle = $stmt->fetch();
if (!$cycle) { http_response_code(404); die('ไม่พบรอบการประเมิน'); }

$templatePath = __DIR__ . '/../outputs/kpi_template/kpi_indicator_template.xlsx';
if (!is_file($templatePath)) { http_response_code(500); die('ไม่พบไฟล์ต้นแบบตัวชี้วัด'); }
$spreadsheet = IOFactory::load($templatePath);
$sheet = $spreadsheet->getSheetByName('ตัวชี้วัด') ?: $spreadsheet->getActiveSheet();
$lists = $spreadsheet->getSheetByName('รายการ');
if (!$lists) { $lists = new Worksheet($spreadsheet, 'รายการ'); $spreadsheet->addSheet($lists); }

$sheet->setCellValue('A1', 'Template นำเข้าตัวชี้วัดผลสัมฤทธิ์ของงาน ' . kpiCycleLabel($cycle));
$lists->setCellValue('A1', 'ผู้รับผิดชอบงานหลัก');
$lists->getStyle('A2:A500')->getFill()->setFillType('none');
$lists->fromArray(array_fill(0, 499, [null]), null, 'A2');

$users = $pdo->query("SELECT u.fullname FROM users u JOIN departments d ON d.id=u.department_id WHERE u.is_active=1 AND d.type='SSO' ORDER BY u.fullname")->fetchAll(PDO::FETCH_COLUMN);
foreach ($users as $index => $fullname) $lists->setCellValue('A' . ($index + 2), $fullname);

$dropdowns = ['J' => "'รายการ'!\$A\$2:\$A\$500"];
foreach ($dropdowns as $column => $formula) {
    $validation = new DataValidation();
    $validation->setType(DataValidation::TYPE_LIST)
        ->setErrorStyle(DataValidation::STYLE_STOP)
        ->setAllowBlank(true)
        ->setShowErrorMessage(true)
        ->setShowDropDown(true)
        ->setErrorTitle('ข้อมูลไม่ถูกต้อง')
        ->setError('กรุณาเลือกค่าจากรายการ')
        ->setFormula1($formula);
    for ($row = 4; $row <= 203; $row++) {
        $sheet->getCell($column . $row)->setDataValidation(clone $validation);
    }
}
$lists->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
$spreadsheet->setActiveSheetIndex($spreadsheet->getIndex($sheet));

$safeYear = preg_replace('/[^0-9]/', '', (string)$cycle['fiscal_year']);
$round = preg_match('/\d+/', (string)$cycle['round_name'], $m) ? $m[0] : '1';
$filename = "kpi_template_{$safeYear}_round_{$round}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0, no-store');
(new Xlsx($spreadsheet))->save('php://output');
exit;
