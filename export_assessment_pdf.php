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
    $evaluationId = requestInt($_GET['id'] ?? null, 'id');
    if (!$evaluationId) throw new RuntimeException('ไม่พบรหัสการประเมิน');

    $report = loadEvaluationExportData($pdo, $evaluationId, (int)$_SESSION['user_id'], (string)$_SESSION['role']);
    $evaluation = $report['evaluation'];
    $competencies = $report['competencies'];
    $h = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

    $scoreStmt = $pdo->prepare('SELECT indicator_id,score,reason FROM evaluation_scores WHERE evaluation_id=?');
    $scoreStmt->execute([$evaluationId]);
    $scoreMap = [];
    foreach ($scoreStmt->fetchAll() as $scoreRow) {
        $scoreMap[(int)$scoreRow['indicator_id']] = $scoreRow;
    }

    $levelStmt = $pdo->prepare("SELECT MAX(expected_level) FROM indicators
        WHERE competency_id=? AND expected_level<=? AND (position_id IS NULL OR position_id=?)");
    $indicatorStmt = $pdo->prepare("SELECT id,indicator_text,order_seq FROM indicators
        WHERE competency_id=? AND expected_level=? AND (position_id IS NULL OR position_id=?)
        ORDER BY position_id DESC,order_seq,id");
    $detailStmt = $pdo->prepare("SELECT c.description,COALESCE(t.level_description,cl.level_description) level_description
        FROM competencies c
        JOIN evaluation_templates t ON t.competency_id=c.id AND t.position_id=? AND t.expected_level=?
        LEFT JOIN competency_levels cl ON cl.competency_id=c.id AND cl.expected_level=t.expected_level
        WHERE c.id=? LIMIT 1");
    $fallbackLevelStmt = $pdo->prepare('SELECT level_description FROM competency_levels WHERE competency_id=? AND expected_level=?');

    foreach ($competencies as &$competency) {
        $levelStmt->execute([(int)$competency['id'], (int)$evaluation['expected_level'], (int)$evaluation['position_id']]);
        $indicatorLevel = (int)$levelStmt->fetchColumn();
        if ($indicatorLevel < 1) $indicatorLevel = (int)$evaluation['expected_level'];

        $detailStmt->execute([(int)$evaluation['position_id'], (int)$evaluation['expected_level'], (int)$competency['id']]);
        $detail = $detailStmt->fetch() ?: ['description' => '', 'level_description' => ''];
        if ($indicatorLevel !== (int)$evaluation['expected_level']) {
            $fallbackLevelStmt->execute([(int)$competency['id'], $indicatorLevel]);
            $fallbackDescription = $fallbackLevelStmt->fetchColumn();
            if ($fallbackDescription !== false) $detail['level_description'] = $fallbackDescription;
        }

        $indicatorStmt->execute([(int)$competency['id'], $indicatorLevel, (int)$evaluation['position_id']]);
        $competency['description'] = (string)($detail['description'] ?? '');
        $competency['level_description'] = (string)($detail['level_description'] ?? '');
        $competency['indicator_level'] = $indicatorLevel;
        $competency['indicators'] = $indicatorStmt->fetchAll();
    }
    unset($competency);

    preg_match('/รอบ(?:ที่)?\s*(\d+)/u', (string)$evaluation['round_name'], $roundMatch);
    $roundNumber = (int)($roundMatch[1] ?? 1);
    $roundDate = $roundNumber === 2 ? '1 ตุลาคม' : '1 เมษายน';
    $roundDisplay = 'รอบที่ ' . $roundNumber . ' ' . $roundDate . ' ' . $evaluation['fiscal_year'];
    $acknowledged = $evaluation['acknowledged_at']
        ? date('d/m/Y', strtotime($evaluation['acknowledged_at']))
        : '................................';

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
    $summaryRows = '<tr class="section"><td>สมรรถนะหลัก</td><td></td><td></td><td></td><td></td><td></td><td rowspan="11" class="guidance-cell">' . $guidance . '</td></tr>';
    foreach ($coreCompetencies as $competency) {
        $summaryRows .= '<tr><td>' . $number++ . '. ' . $h($competency['display_name']) . '</td>'
            . '<td class="center">' . (int)$evaluation['expected_level'] . '</td>'
            . '<td class="center">' . number_format($competency['raw_average'], 2) . '</td>'
            . '<td class="center">' . number_format($competency['weight'], 0) . '%</td>'
            . '<td class="center">' . number_format($competency['weighted'], 1) . '</td>'
            . '<td>' . $h($competency['notes'] ?? '') . '</td></tr>';
    }
    $summaryRows .= '<tr class="section"><td>สมรรถนะเฉพาะตามลักษณะงานที่ปฏิบัติ</td><td></td><td></td><td></td><td></td><td></td></tr>';
    foreach ($functionalCompetencies as $competency) {
        $summaryRows .= '<tr><td>' . $number++ . '. ' . $h($competency['display_name']) . '</td>'
            . '<td class="center">' . (int)$evaluation['expected_level'] . '</td>'
            . '<td class="center">' . number_format($competency['raw_average'], 2) . '</td>'
            . '<td class="center">' . number_format($competency['weight'], 0) . '%</td>'
            . '<td class="center">' . number_format($competency['weighted'], 1) . '</td>'
            . '<td>' . $h($competency['notes'] ?? '') . '</td></tr>';
    }
    $summaryRows .= '<tr><td colspan="2"></td><td class="center summary-label">รวม</td><td class="center summary-label">= 100%</td><td class="center summary-label">' . number_format($totalBase5, 1) . '</td><td></td></tr>';
    $summaryRows .= '<tr><td colspan="4" class="summary-label conversion">แปลงคะแนนรวมข้างต้นเป็นคะแนนการประเมินสมรรถนะมีฐานคะแนนเต็ม เป็น 100 คะแนน<br><span class="small">(โดยนำ 20 มาคูณ)</span></td><td class="center summary-label score100">' . number_format($totalBase100, 2) . '</td><td></td></tr>';

    $html = '<!doctype html><html lang="th"><head><meta charset="utf-8"><style>
        @page{margin:5mm} body{font-family:thsarabunpsk;font-size:14pt;color:#111;margin:0;line-height:1.05}
        table{border-collapse:collapse;table-layout:fixed;width:100%}.center{text-align:center}.bold{font-weight:bold}
        .summary{font-size:16pt;line-height:1}.top{margin-bottom:1.2mm}.top td{padding:.7mm 1.1mm;vertical-align:middle;line-height:1.05}
        .titlebox{border:.3mm solid #111;text-align:center;font-size:18pt;font-weight:bold}.top-label{font-weight:bold}.sign-line{white-space:nowrap}
        .summary-table th,.summary-table td{border:.25mm solid #111;padding:.85mm 1mm;vertical-align:middle;line-height:1.05}
        .summary-table th{text-align:center;font-weight:bold;font-size:16pt}.section td{font-weight:bold}.summary-label{font-weight:bold}.small{font-size:16pt}
        .conversion{line-height:1}.score100{font-size:16pt}.guidance-cell{vertical-align:top!important;padding:1mm!important}.guidance{font-size:16pt}.guidance p{margin:0 0 1.2mm}
        .detail-page{font-size:12.5pt;line-height:1}.detail-header{text-align:center;font-weight:bold;font-size:14pt;margin-bottom:.5mm}
        .person-lines{width:100%;margin-bottom:.5mm}.person-lines td{border-bottom:.2mm solid #333;padding:.25mm .7mm;font-size:12.5pt}
        .competency-block{margin-top:.8mm}.competency-title{font-weight:bold;font-size:12.5pt;line-height:1;margin-bottom:.35mm}
        .behavior th,.behavior td{border:.22mm solid #111;padding:.3mm .5mm;vertical-align:middle;line-height:1}
        .behavior th{text-align:center;font-weight:bold}.behavior .level-band{background:#e8f3f8;font-weight:bold;text-align:left}
        .behavior .level-description{vertical-align:top}.behavior .indicator{text-align:left}.behavior .score{text-align:center;font-size:12pt}
        .behavior .note{text-align:left}.behavior .result-row td{border-left:0;border-right:0;font-weight:bold;text-align:center;padding:.7mm}
        .observation-note{font-size:12pt;margin:.45mm 0 0}.detail-page-number{font-size:11pt;text-align:right;color:#555;margin-top:.35mm}
    </style></head><body>';

    $html .= '<section class="summary"><table class="top"><colgroup><col style="width:50%"><col style="width:25%"><col style="width:25%"></colgroup>'
        . '<tr><td class="titlebox">แบบประเมินพฤติกรรมการปฏิบัติราชการหรือสมรรถนะ</td><td colspan="2"><b>รอบการประเมิน </b>' . $h($roundDisplay) . '</td></tr>'
        . '<tr><td><span class="top-label">ชื่อผู้รับการประเมิน (นาย/นาง/นางสาว)</span> ' . $h($evaluation['evaluatee_name']) . '</td><td class="sign-line"><b>ลงนาม</b> ....................................</td><td><b>รับทราบสมรรถนะแล้ว เมื่อวันที่</b> ' . $acknowledged . '</td></tr>'
        . '<tr><td><span class="top-label">ชื่อผู้บังคับบัญชาชั้นต้น/ผู้ประเมิน (นาย/นาง/นางสาว)</span> ' . $h($evaluation['evaluator_name']) . '</td><td class="sign-line"><b>ลงนาม</b> ....................................</td><td><b>รับทราบสมรรถนะแล้ว เมื่อวันที่</b> ' . $acknowledged . '</td></tr></table>'
        . '<table class="summary-table"><colgroup><col style="width:30%"><col style="width:8%"><col style="width:8%"><col style="width:8%"><col style="width:10%"><col style="width:18%"><col style="width:18%"></colgroup><thead><tr>'
        . '<th>สมรรถนะ</th><th>ระดับที่<br>คาดหวัง</th><th>คะแนน<br>( ก )</th><th>น้ำหนัก<br>( ข )</th><th>คะแนนรวม<br>( ค )<br><span class="small">(ค) = ก x ข</span></th>'
        . '<th>บันทึกการประเมินโดยผู้ประเมิน (ถ้ามี)<br><span class="small">และกรณีพื้นที่ไม่พอให้บันทึกลงในเอกสารหน้าหลัง</span></th><th>แนวทางการประเมินพฤติกรรม<br>การปฏิบัติราชการ</th>'
        . '</tr></thead><tbody>' . $summaryRows . '</tbody></table></section>';

    $detailGroups = array_chunk($competencies, 2);
    $competencyNumber = 1;
    foreach ($detailGroups as $groupIndex => $group) {
        $html .= '<pagebreak /><section class="detail-page"><div class="detail-header">แบบบันทึกพฤติกรรมที่แสดงออกต่อพฤติกรรมที่จำเป็นสำหรับการปฏิบัติงาน</div>'
            . '<table class="person-lines"><colgroup><col style="width:34%"><col style="width:32%"><col style="width:34%"></colgroup>'
            . '<tr><td>ชื่อผู้รับการประเมิน ' . $h($evaluation['evaluatee_name']) . '</td><td>ตำแหน่ง ' . $h($evaluation['pos_name']) . '</td><td>' . $h($evaluation['rank_name']) . '</td></tr>'
            . '<tr><td>ชื่อผู้ประเมิน ' . $h($evaluation['evaluator_name']) . '</td><td>ตำแหน่ง ' . $h($evaluation['evaluator_pos_name']) . '</td><td>' . $h($evaluation['evaluator_rank_name']) . '</td></tr></table>';

        foreach ($group as $competency) {
            $indicators = $competency['indicators'];
            $indicatorCount = max(1, count($indicators));
            $html .= '<div class="competency-block"><div class="competency-title">' . $competencyNumber . '. ' . $h($competency['name']) . ' ' . $h($competency['description']) . '</div>'
                . '<table class="behavior"><colgroup><col style="width:16%"><col style="width:47%"><col style="width:5%"><col style="width:5%"><col style="width:5%"><col style="width:5%"><col style="width:5%"><col style="width:12%"></colgroup>'
                . '<thead><tr><th rowspan="3">คำอธิบายระดับ</th><th rowspan="3">พฤติกรรมบ่งชี้</th><th colspan="5">ให้ทำเครื่องหมายลงในช่องการแสดงพฤติกรรมของผู้ถูกประเมิน</th><th rowspan="3">บันทึกพฤติกรรมประกอบการประเมินสมรรถนะ</th></tr>'
                . '<tr><th>ระดับ 1</th><th>ระดับ 2</th><th>ระดับ 3</th><th>ระดับ 4</th><th>ระดับ 5</th></tr>'
                . '<tr><th>น้อยที่สุด</th><th>น้อย</th><th>ปานกลาง</th><th>มาก</th><th>มากที่สุด</th></tr></thead><tbody>'
                . '<tr><td class="level-description" rowspan="' . ($indicatorCount + 1) . '">' . $h($competency['level_description']) . '</td><td class="level-band" colspan="7">ระดับที่ ' . (int)$competency['indicator_level'] . ' : ระดับพื้นฐาน</td></tr>';

            if (!$indicators) {
                $html .= '<tr><td class="indicator">ไม่พบพฤติกรรมบ่งชี้</td><td colspan="5"></td><td></td></tr>';
            } else {
                foreach ($indicators as $indicator) {
                    $savedScore = $scoreMap[(int)$indicator['id']] ?? null;
                    $selectedScore = $savedScore ? (int)$savedScore['score'] : 0;
                    $html .= '<tr><td class="indicator">• ' . $h($indicator['indicator_text']) . '</td>';
                    for ($score = 1; $score <= 5; $score++) {
                        $html .= '<td class="score">' . ($selectedScore === $score ? $score : '') . '</td>';
                    }
                    $html .= '<td class="note">' . $h($savedScore['reason'] ?? '') . '</td></tr>';
                }
            }
            $html .= '<tr class="result-row"><td colspan="2">สรุปผลคะแนนสมรรถนะ ' . $competencyNumber . '</td><td colspan="5">ระดับ ' . (int)$competency['indicator_level'] . '</td><td>' . number_format($competency['raw_average'], 2) . '</td></tr></tbody></table></div>';
            $competencyNumber++;
        }
        $html .= '<div class="observation-note">หมายเหตุ : ผู้ประเมินต้องสังเกตพฤติกรรมผู้รับการประเมินอย่างสม่ำเสมอ</div>'
            . '<div class="detail-page-number">หน้า ' . ($groupIndex + 2) . '</div></section>';
    }
    $html .= '</body></html>';

    $defaultConfig = (new ConfigVariables())->getDefaults();
    $fontConfig = (new FontVariables())->getDefaults();
    $fontDir = __DIR__ . '/assets/fonts/th-sarabun-psk';
    foreach (['THSarabun.ttf', 'THSarabun Bold.ttf', 'THSarabun Italic.ttf', 'THSarabun BoldItalic.ttf'] as $fontFile) {
        if (!is_file($fontDir . '/' . $fontFile)) throw new RuntimeException('ไม่พบไฟล์ฟอนต์ TH Sarabun PSK: ' . $fontFile);
    }
    $tempDir = __DIR__ . '/tmp/mpdf';
    if (!is_dir($tempDir) && !mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
        throw new RuntimeException('ไม่สามารถสร้างพื้นที่ชั่วคราวสำหรับ PDF ได้');
    }
    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4-L',
        'tempDir' => $tempDir,
        'fontDir' => array_merge($defaultConfig['fontDir'], [$fontDir]),
        'fontdata' => $fontConfig['fontdata'] + ['thsarabunpsk' => [
            'R' => 'THSarabun.ttf', 'B' => 'THSarabun Bold.ttf',
            'I' => 'THSarabun Italic.ttf', 'BI' => 'THSarabun BoldItalic.ttf',
        ]],
        'default_font' => 'thsarabunpsk',
    ]);
    $mpdf->SetTitle('แบบประเมินสมรรถนะ - ' . $evaluation['evaluatee_name']);
    $mpdf->WriteHTML($html);
    $filename = 'แบบประเมินสมรรถนะ_' . safeReportFilename($evaluation['evaluatee_name']) . '.pdf';
    $pdfContent = $mpdf->Output('', Destination::STRING_RETURN);
    $qaOutputPath = PHP_SAPI === 'cli' ? getenv('EVALUATION_FULL_PDF_OUTPUT') : false;
    if ($qaOutputPath) {
        file_put_contents($qaOutputPath, $pdfContent);
        exit;
    }
    header('Content-Type: application/pdf');
    header("Content-Disposition: inline; filename=\"assessment_form.pdf\"; filename*=UTF-8''" . rawurlencode($filename));
    header('Content-Length: ' . strlen($pdfContent));
    echo $pdfContent;
    exit;
} catch (Throwable $e) {
    error_log('Assessment PDF export failed: ' . $e->getMessage());
    http_response_code(403);
    exit(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
