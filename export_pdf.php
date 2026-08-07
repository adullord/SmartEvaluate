<?php
require_once 'config.php';
require_once 'vendor/autoload.php';
require_once 'report_export_data.php';

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

try {
    $evaluationId = (int)($_GET['id'] ?? 0);
    if (!$evaluationId) throw new RuntimeException('ไม่พบรหัสการประเมิน');
    $report = loadEvaluationExportData($pdo, $evaluationId, (int)$_SESSION['user_id'], (string)$_SESSION['role']);
    $evaluation = $report['evaluation'];
    $competencies = $report['competencies'];

    $coreCompetencies = array_values(array_filter($competencies, fn($item) => $item['type'] === 'core'));
    $functionalCompetencies = array_values(array_filter($competencies, fn($item) => $item['type'] === 'functional'));
    $totalBase5 = array_sum(array_column($competencies, 'weighted'));
    $totalBase100 = round($totalBase5 * 20, 2, PHP_ROUND_HALF_UP);
    $number = 1;

    $guidance = '<div class="guidance">'
        . '<p>□ ได้นำคะแนนมาจากแบบประเมินสมรรถนะอื่น ๆ มาสรุปไว้ในแบบประเมินนี้</p>'
        . '<p>ระบุที่มา .....................................................</p>'
        . '<p>□ ใช้แบบประเมินนี้ในการประเมินสมรรถนะโดยตั้งมาตรวัดสมรรถนะ<br>(ระบุรายละเอียดมาตรวัดสำหรับแต่ละระดับคะแนน)</p>'
        . '<p><b>หมายเหตุ</b> ในช่องน้ำหนัก (ข) หากส่วนราชการประสงค์ประเมินสมรรถนะแต่ละตัวโดยถ่วงน้ำหนัก ก็ให้ระบุน้ำหนักของสมรรถนะแต่ละตัว แต่ส่วนราชการสามารถเลือกที่จะไม่กำหนดให้มีการถ่วงน้ำหนักสมรรถนะก็ได้</p>'
        . '</div>';

    $rows = '<tr class="section"><td>สมรรถนะหลัก</td><td></td><td></td><td></td><td></td><td></td><td rowspan="11" class="guidance-cell">' . $guidance . '</td></tr>';
    foreach ($coreCompetencies as $competency) {
        $rows .= '<tr><td>' . $number++ . '. ' . htmlspecialchars($competency['display_name']) . '</td>'
            . '<td class="center">' . (int)$evaluation['expected_level'] . '</td>'
            . '<td class="center">' . number_format($competency['raw_average'], 2) . '</td>'
            . '<td class="center">' . number_format($competency['weight'], 0) . '%</td>'
            . '<td class="center">' . number_format($competency['weighted'], 1) . '</td>'
            . '<td>' . htmlspecialchars($competency['notes'] ?? '') . '</td></tr>';
    }
    $rows .= '<tr class="section"><td>สมรรถนะเฉพาะตามลักษณะงานที่ปฏิบัติ</td><td></td><td></td><td></td><td></td><td></td></tr>';
    foreach ($functionalCompetencies as $competency) {
        $rows .= '<tr><td>' . $number++ . '. ' . htmlspecialchars($competency['display_name']) . '</td>'
            . '<td class="center">' . (int)$evaluation['expected_level'] . '</td>'
            . '<td class="center">' . number_format($competency['raw_average'], 2) . '</td>'
            . '<td class="center">' . number_format($competency['weight'], 0) . '%</td>'
            . '<td class="center">' . number_format($competency['weighted'], 1) . '</td>'
            . '<td>' . htmlspecialchars($competency['notes'] ?? '') . '</td></tr>';
    }
    $rows .= '<tr><td colspan="2"></td><td class="center summary-label">รวม</td><td class="center summary-label">= 100%</td><td class="center summary-label">' . number_format($totalBase5, 1) . '</td><td></td></tr>';
    $rows .= '<tr><td colspan="4" class="summary-label conversion">แปลงคะแนนรวมข้างต้นเป็นคะแนนการประเมินสมรรถนะมีฐานคะแนนเต็ม เป็น 100 คะแนน<br><span class="small">(โดยนำ 20 มาคูณ)</span></td><td class="center summary-label score100">' . number_format($totalBase100, 2) . '</td><td></td></tr>';

    $acknowledged = $evaluation['acknowledged_at']
        ? date('d/m/Y', strtotime($evaluation['acknowledged_at']))
        : '................................';
    preg_match('/รอบ(?:ที่)?\s*(\d+)/u', (string)$evaluation['round_name'], $roundMatch);
    $roundNumber = (int)($roundMatch[1] ?? 1);
    $roundDate = $roundNumber === 2 ? '1 ตุลาคม' : '1 เมษายน';
    $roundDisplay = 'รอบที่ ' . $roundNumber . ' ' . $roundDate . ' ' . $evaluation['fiscal_year'];
    $html = '<!doctype html><html lang="th"><head><meta charset="utf-8"><style>
        @page{margin:5mm} body{font-family:thsarabun;font-size:16pt;color:#111;margin:0;line-height:1}
        table{border-collapse:collapse;table-layout:fixed;width:100%}.top{margin-bottom:1.2mm}.top td{padding:0.7mm 1.1mm;vertical-align:middle;line-height:1.05}
        .titlebox{border:0.3mm solid #111;text-align:center;font-size:18pt;font-weight:bold}.top-label{font-weight:bold}.sign-line{white-space:nowrap}
        table.report{width:100%;border-collapse:collapse;table-layout:fixed}.report th,.report td{border:0.25mm solid #111;padding:0.85mm 1mm;vertical-align:middle;line-height:1.05}
        .report th{text-align:center;font-weight:bold;font-size:16pt;line-height:1.05}.section td{font-weight:bold;background:#fff}.center{text-align:center}
        .summary-label{font-weight:bold}.small{font-size:16pt}.conversion{line-height:1}.score100{font-size:16pt}
        .guidance-cell{vertical-align:top!important;padding:1mm!important}.guidance{font-size:16pt;line-height:1}.guidance p{margin:0 0 1.2mm}
    </style></head><body>
    <table class="top"><colgroup><col style="width:50%"><col style="width:25%"><col style="width:25%"></colgroup>
      <tr><td class="titlebox">แบบประเมินพฤติกรรมการปฏิบัติราชการหรือสมรรถนะ</td><td colspan="2"><b>รอบการประเมิน </b>' . htmlspecialchars($roundDisplay) . '</td></tr>
      <tr><td><span class="top-label">ชื่อผู้รับการประเมิน (นาย/นาง/นางสาว)</span> ' . htmlspecialchars($evaluation['evaluatee_name']) . '</td><td class="sign-line"><b>ลงนาม</b> ....................................</td><td><b>รับทราบสมรรถนะแล้ว เมื่อวันที่</b> ' . $acknowledged . '</td></tr>
      <tr><td><span class="top-label">ชื่อผู้บังคับบัญชาชั้นต้น/ผู้ประเมิน (นาย/นาง/นางสาว)</span> ' . htmlspecialchars($evaluation['evaluator_name']) . '</td><td class="sign-line"><b>ลงนาม</b> ....................................</td><td><b>รับทราบสมรรถนะแล้ว เมื่อวันที่</b> ' . $acknowledged . '</td></tr>
    </table>
    <table class="report"><colgroup><col style="width:30%"><col style="width:8%"><col style="width:8%"><col style="width:8%"><col style="width:10%"><col style="width:18%"><col style="width:18%"></colgroup>
      <thead>
        <tr>
          <th>สมรรถนะ</th>
          <th>ระดับที่<br>คาดหวัง</th>
          <th>คะแนน<br>( ก )</th>
          <th>น้ำหนัก<br>( ข )</th>
          <th>คะแนนรวม<br>( ค )<br><span class="small">(ค) = ก x ข</span></th>
          <th>บันทึกการประเมินโดยผู้ประเมิน (ถ้ามี)<br><span class="small">และกรณีพื้นที่ไม่พอให้บันทึกลงในเอกสารหน้าหลัง</span></th>
          <th>แนวทางการประเมินพฤติกรรม<br>การปฏิบัติราชการ</th>
        </tr>
      </thead><tbody>' . $rows . '</tbody>
    </table></body></html>';

    $defaultConfig = (new ConfigVariables())->getDefaults();
    $fontConfig = (new FontVariables())->getDefaults();
    $tempDir = __DIR__ . '/tmp/mpdf';
    if (!is_dir($tempDir)) mkdir($tempDir, 0777, true);
    $mpdf = new Mpdf([
        'mode' => 'utf-8', 'format' => 'A4-L', 'tempDir' => $tempDir,
        'fontDir' => array_merge($defaultConfig['fontDir'], ['C:/Windows/Fonts']),
        'fontdata' => $fontConfig['fontdata'] + ['thsarabun' => [
            'R' => 'THSarabun.ttf', 'B' => 'THSarabun Bold.ttf',
            'I' => 'THSarabun Italic.ttf', 'BI' => 'THSarabun BoldItalic.ttf',
        ]],
        'default_font' => 'thsarabun',
    ]);
    $mpdf->SetTitle('สรุปผลการประเมิน - ' . $evaluation['evaluatee_name']);
    $mpdf->WriteHTML($html);
    $filename = 'สรุปประเมิน_' . safeReportFilename($evaluation['evaluatee_name']) . '.pdf';
    // เปิดในตัวดู PDF ของเบราว์เซอร์ เพื่อให้ผู้ใช้กดพิมพ์หรือดาวน์โหลดได้
    $pdfContent = $mpdf->Output('', Destination::STRING_RETURN);
    $qaOutputPath = PHP_SAPI === 'cli' ? getenv('EVALUATION_PDF_OUTPUT') : false;
    if ($qaOutputPath) {
        file_put_contents($qaOutputPath, $pdfContent);
        exit;
    }
    header('Content-Type: application/pdf');
    header("Content-Disposition: inline; filename=\"evaluation_report.pdf\"; filename*=UTF-8''" . rawurlencode($filename));
    header('Content-Length: ' . strlen($pdfContent));
    echo $pdfContent;
    exit;
} catch (Throwable $e) {
    http_response_code(403);
    exit(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
