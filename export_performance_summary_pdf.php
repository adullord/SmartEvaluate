<?php
require_once 'config.php';
require_once 'vendor/autoload.php';
require_once __DIR__ . '/includes/performance_summary_helper.php';
require_once __DIR__ . '/includes/pdf_temp_helper.php';

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

function performancePdfE(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function performancePdfScore(?float $score): string
{
    return $score === null ? '-' : number_format($score, 2);
}

function performancePdfCheckbox(bool $checked = false): string
{
    $mark = $checked
        ? '<path d="M4 10 L8 14 L16 5" fill="none" stroke="#111" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>'
        : '';
    return '<svg class="checkbox" width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><rect x="1" y="1" width="18" height="18" fill="none" stroke="#111" stroke-width="1.2"/>'.$mark.'</svg>';
}

try {
    $cycleId = requestInt($_GET['cycle_id'] ?? null, 'cycle_id');
    $ownerId = requestInt($_GET['user_id'] ?? null, 'user_id');
    $viewerId = (int)$_SESSION['user_id'];
    $viewerRole = (string)($_SESSION['role'] ?? 'staff');
    if ($cycleId < 1 || $ownerId < 1 || !performanceSummaryCanView($pdo, $viewerId, $viewerRole, $ownerId)) {
        throw new RuntimeException('ไม่มีสิทธิ์ดูรายงานนี้');
    }

    $data = performanceSummaryLoad($pdo, $cycleId, $ownerId);
    $cycle = $data['cycle'];
    $user = $data['user'];
    $evaluation = $data['evaluation'];
    $roundNumber = performanceSummaryRoundNumber($cycle);
    $period = performanceSummaryThaiDate($cycle['start_date']) . ' - ' . performanceSummaryThaiDate($cycle['end_date']);

    $stmt = $pdo->prepare('SELECT id,fiscal_year,round_name,start_date,end_date FROM evaluation_cycles WHERE fiscal_year=? ORDER BY start_date,id');
    $stmt->execute([$cycle['fiscal_year']]);
    $fiscalCycles = $stmt->fetchAll();
    $roundRows = '';
    foreach ($fiscalCycles as $fiscalCycle) {
        $selected = (int)$fiscalCycle['id'] === $cycleId;
        $roundRows .= '<tr><td class="label">รอบการประเมิน</td><td class="box-cell">'.performancePdfCheckbox($selected).'</td><td>รอบที่ '.performancePdfE(performanceSummaryRoundNumber($fiscalCycle)).'</td><td>'.performancePdfE(performanceSummaryThaiDate($fiscalCycle['start_date'])).'</td><td>ถึง '.performancePdfE(performanceSummaryThaiDate($fiscalCycle['end_date'])).'</td></tr>';
    }
    if ($roundRows === '') {
        $roundRows = '<tr><td class="label">รอบการประเมิน</td><td class="box-cell">'.performancePdfCheckbox(true).'</td><td>รอบที่ '.performancePdfE($roundNumber).'</td><td>'.performancePdfE(performanceSummaryThaiDate($cycle['start_date'])).'</td><td>ถึง '.performancePdfE(performanceSummaryThaiDate($cycle['end_date'])).'</td></tr>';
    }

    $positionType = mb_strpos((string)$user['position_name'], 'เจ้าพนักงาน') !== false ? 'ทั่วไป' : 'วิชาการ';
    $evaluatorName = $evaluation['evaluator_name'] ?? '........................................................';
    $evaluatorPosition = trim((string)($evaluation['evaluator_position_name'] ?? '') . (string)($evaluation['evaluator_rank_name'] ?? ''));
    if ($evaluatorPosition === '') $evaluatorPosition = '........................................................';
    $positionRank = trim((string)$user['position_name'] . (string)$user['rank_name']);
    $rating = $data['rating'];

    $summaryRows = [
        ['องค์ประกอบที่ ๑ : ผลสัมฤทธิ์ของงาน', $data['kpi_score'], 70],
        ['องค์ประกอบที่ ๒ : พฤติกรรมการปฏิบัติราชการ (สมรรถนะ)', $data['competency_score'], 15],
        ['องค์ประกอบที่ ๓ : งานมอบหมายพิเศษ', $data['component3_score'], 15],
    ];
    $componentRows = '';
    foreach ($summaryRows as [$label, $score, $weight]) {
        $weighted = $score === null ? null : round($score * $weight / 100, 2, PHP_ROUND_HALF_UP);
        $componentRows .= '<tr><td>'.performancePdfE($label).'</td><td class="center">'.performancePdfScore($score).'</td><td class="center">'.$weight.'%</td><td class="center">'.performancePdfScore($weighted).'</td></tr>';
    }
    $ratingLabels = ['ดีเด่น','ดีมาก','ดี','พอใช้','ต้องปรับปรุง'];
    $ratingBoxes = '';
    foreach ($ratingLabels as $label) {
        $ratingBoxes .= '<td>'.performancePdfCheckbox($rating === $label).' '.performancePdfE($label).'</td>';
    }

    $html = '<!doctype html><html lang="th"><head><meta charset="utf-8"><style>
        @page{margin:10mm 14mm 12mm 17mm}body{font-family:thsarabunpsk;font-size:16pt;color:#111;line-height:1.12}h1{font-size:18pt;text-align:center;margin:0 0 10mm;font-weight:bold}.cover{text-align:center;font-size:26pt;font-weight:bold;line-height:1.15;padding-top:13mm}.cover-title{margin-bottom:23mm}.cover-round{margin-bottom:5mm}.cover-owner-label{margin-top:39mm}.cover-name{margin-top:24mm}.cover-position{margin-top:5mm}.cover-department{margin-top:26mm}.section-title{font-weight:bold;margin:4mm 0 2mm;line-height:1.4}.section-title span,.explanation-title span{border-bottom:.2mm solid #111;padding-bottom:.1mm}.info{width:100%;border-collapse:collapse;margin-bottom:10mm}.info td{border:0;padding:.7mm 1mm;vertical-align:middle}.info .label{white-space:nowrap}.box-cell{width:9mm}.checkbox{vertical-align:middle;margin-right:1.5mm}.summary{width:100%;border-collapse:collapse;table-layout:fixed;margin:7mm 0 7mm}.summary th,.summary td{border:.35mm solid #111;padding:1mm 1.5mm;vertical-align:middle}.summary th{text-align:center;font-weight:normal}.center{text-align:center}.rating-table{width:82%;margin:2mm auto 7mm;border-collapse:collapse;table-layout:fixed}.rating-table td{border:0;padding:0 1mm;white-space:nowrap;text-align:center}.plan{width:100%;border-collapse:collapse;table-layout:fixed;margin-top:7mm}.plan th,.plan td{border:.35mm solid #111;padding:1mm;vertical-align:middle}.plan th{text-align:center;font-weight:normal}.plan td{height:8mm}.acknowledgement{width:100%;border-collapse:collapse;table-layout:fixed;margin-top:2mm}.acknowledgement td.ack-block{border:.3mm solid #111;padding:2mm 2.5mm;vertical-align:top}.ack-layout{width:100%;border-collapse:collapse;table-layout:fixed}.ack-layout td{border:0!important;padding:0 2mm!important;vertical-align:top}.ack-sign{line-height:1.55;padding-top:2mm}.ack-witness{text-align:center;margin-top:2mm;line-height:1.45}.supervisor{width:100%;border-collapse:collapse;table-layout:fixed;margin-top:2mm}.supervisor td.supervisor-block{border:.3mm solid #111;padding:2mm 2.5mm;vertical-align:top;height:38mm}.supervisor-layout{width:100%;border-collapse:collapse;table-layout:fixed}.supervisor-layout td{border:0!important;height:auto!important;padding:0 2mm!important;vertical-align:top}.supervisor-sign{padding-top:8mm;line-height:1.55}.explanation{font-size:12pt;margin-top:0;line-height:1.12}.explanation-title{line-height:1.4}.explanation p{margin:.3mm 0}.nowrap{white-space:nowrap}
    </style></head><body><div class="cover">
        <div class="cover-title">แบบประเมินผลการปฏิบัติราชการรายบุคคล</div>
        <div class="cover-round">รอบที่ '.performancePdfE($roundNumber).'</div>
        <div>('.performancePdfE($period).')</div>
        <div class="cover-owner-label">ของ</div>
        <div class="cover-name">'.performancePdfE($user['fullname']).'</div>
        <div class="cover-position">( '.performancePdfE($positionRank).' )</div>
        <div class="cover-department">'.performancePdfE($user['department_name']).'</div>
    </div><pagebreak /><h1>แบบสรุปการประเมินผลการปฏิบัติราชการ</h1>
    <div class="section-title"><span>ส่วนที่ ๑ : ข้อมูลของผู้รับการประเมิน</span></div><table class="info">'.$roundRows.'
    <tr><td colspan="5" class="nowrap">ชื่อผู้รับการประเมิน &nbsp;&nbsp; '.performancePdfE($user['fullname']).'</td></tr>
    <tr><td colspan="3">ตำแหน่ง &nbsp; '.performancePdfE($user['position_name']).'</td><td colspan="2">ประเภทตำแหน่ง &nbsp; '.performancePdfE($positionType).'</td></tr>
    <tr><td colspan="3">ระดับตำแหน่ง &nbsp; '.performancePdfE($user['rank_name']).'</td><td colspan="2">สังกัด &nbsp; '.performancePdfE($user['department_name']).'</td></tr>
    <tr><td colspan="5" class="nowrap">ชื่อผู้ประเมิน &nbsp;&nbsp; '.performancePdfE($evaluatorName).'</td></tr><tr><td colspan="5" class="nowrap">ตำแหน่ง &nbsp;&nbsp; '.performancePdfE($evaluatorPosition).'</td></tr></table>
    <div class="section-title"><span>ส่วนที่ ๒ : การสรุปผลการประเมิน</span></div><table class="summary"><colgroup><col style="width:56%"><col style="width:12%"><col style="width:12%"><col style="width:20%"></colgroup><thead><tr><th>องค์ประกอบการประเมิน</th><th>คะแนน (ก)</th><th>น้ำหนัก (ข)</th><th>รวมคะแนน (ก) x (ข)</th></tr></thead><tbody>'.$componentRows.'<tr><td colspan="2" class="center"><b>รวม</b></td><td class="center"><b>100%</b></td><td class="center"><b>'.performancePdfScore($data['overall_score']).'</b></td></tr></tbody></table>
    <div class="section-title" style="font-weight:normal"><span>ระดับผลการประเมิน</span></div><table class="rating-table"><tr>'.$ratingBoxes.'</tr></table>
    <div class="section-title"><span>ส่วนที่ ๓ : แผนพัฒนาการปฏิบัติราชการรายบุคคล</span></div><table class="plan"><colgroup><col style="width:28%"><col style="width:53%"><col style="width:19%"></colgroup><thead><tr><th>ความรู้ / ทักษะ / สมรรถนะ<br>ที่ต้องได้รับการพัฒนา</th><th>วิธีการพัฒนา</th><th>ช่วงเวลาที่ต้องการ<br>การพัฒนา</th></tr></thead><tbody><tr><td></td><td></td><td></td></tr><tr><td></td><td></td><td></td></tr><tr><td></td><td></td><td></td></tr><tr><td></td><td></td><td></td></tr><tr><td></td><td></td><td></td></tr></tbody></table>
    <pagebreak /><div class="section-title" style="margin-top:0"><span>ส่วนที่ ๔ : การรับทราบผลการประเมิน</span></div><table class="acknowledgement" border="1" cellspacing="0" cellpadding="0"><tr><td class="ack-block"><b>ผู้รับการประเมิน :</b><table class="ack-layout"><tr><td style="width:54%">'.performancePdfCheckbox().' ได้รับทราบผลการประเมินและแผนพัฒนา<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;การปฏิบัติราชการรายบุคคลแล้ว</td><td style="width:46%"><div class="ack-sign">ลงชื่อ : ........................................................<br>('.performancePdfE($user['fullname']).')<br>ตำแหน่ง : '.performancePdfE($positionRank).'<br>วันที่ : ........................................................</div></td></tr></table></td></tr><tr><td class="ack-block"><b>ผู้ประเมิน :</b><table class="ack-layout"><tr><td style="width:54%">'.performancePdfCheckbox().' ได้แจ้งผลการประเมินและผู้รับการประเมินได้ลงนามรับทราบ<br>'.performancePdfCheckbox().' ได้แจ้งผลการประเมินเมื่อวันที่ ........................................<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;แต่ผู้รับการประเมินไม่ลงนามรับทราบ<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;โดยมี ........................................................ เป็นพยาน<div class="ack-witness">ลงชื่อ : ........................................ พยาน<br>ตำแหน่ง : ........................................<br>วันที่ : ........................................</div></td><td style="width:46%"><div class="ack-sign" style="padding-top:7mm">ลงชื่อ : ........................................................<br>('.performancePdfE($evaluatorName).')<br>ตำแหน่ง : '.performancePdfE($evaluatorPosition).'<br>วันที่ : ........................................................</div></td></tr></table></td></tr></table>
    <div class="section-title"><span>ส่วนที่ ๕ : ความเห็นของผู้บังคับบัญชาเหนือขึ้นไป</span></div><table class="supervisor" border="1" cellspacing="0" cellpadding="0"><tr><td class="supervisor-block"><table class="supervisor-layout"><tr><td style="width:54%"><b>ผู้บังคับบัญชาเหนือขึ้นไป :</b><br>'.performancePdfCheckbox().' เห็นด้วยกับผลการประเมิน<br>'.performancePdfCheckbox().' มีความเห็นต่าง ดังนี้<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;............................................................<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;............................................................</td><td style="width:46%"><div class="supervisor-sign">ลงชื่อ : ........................................................<br>ตำแหน่ง : ....................................................<br>วันที่ : ..........................................................</div></td></tr></table></td></tr><tr><td class="supervisor-block"><table class="supervisor-layout"><tr><td style="width:54%"><b>ผู้บังคับบัญชาเหนือขึ้นไปอีกชั้นหนึ่ง (ถ้ามี) :</b><br>'.performancePdfCheckbox().' เห็นด้วยกับผลการประเมิน<br>'.performancePdfCheckbox().' มีความเห็นต่าง ดังนี้<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;............................................................<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;............................................................</td><td style="width:46%"><div class="supervisor-sign">ลงชื่อ : ........................................................<br>ตำแหน่ง : ....................................................<br>วันที่ : ..........................................................</div></td></tr></table></td></tr></table>
    <div class="explanation"><div class="explanation-title"><span>คำชี้แจง</span></div><p>แบบสรุปการประเมินผลการปฏิบัติราชการ ประกอบด้วย</p><p>ส่วนที่ ๑ &nbsp; ข้อมูลของผู้รับการประเมิน &nbsp; เพื่อระบุรายละเอียดต่าง ๆ ที่เกี่ยวข้องกับผู้รับการประเมิน</p><p>ส่วนที่ ๒ &nbsp; สรุปผลการประเมิน ใช้เพื่อกรอกค่าคะแนนการประเมินในองค์ประกอบด้านผลสัมฤทธิ์ของงาน พฤติกรรมการปฏิบัติราชการ (สมรรถนะ) และงานมอบหมายพิเศษ</p><p>ส่วนที่ ๓ &nbsp; แผนพัฒนาการปฏิบัติราชการรายบุคคล ผู้ประเมินและผู้รับการประเมินร่วมกันจัดทำแผนพัฒนา</p><p>ส่วนที่ ๔ &nbsp; การรับทราบผลการประเมิน ผู้รับการประเมินลงนามรับทราบผลการประเมิน</p><p>ส่วนที่ ๕ &nbsp; ความเห็นของผู้บังคับบัญชาเหนือขึ้นไป ผู้บังคับบัญชาเหนือขึ้นไปกลั่นกรองผลการประเมินและให้ความเห็น</p></div></body></html>';

    $fontDir = __DIR__ . '/assets/fonts/th-sarabun-psk';
    $defaultConfig = (new ConfigVariables())->getDefaults();
    $fontConfig = (new FontVariables())->getDefaults();
    $mpdf = new Mpdf([
        'mode'=>'utf-8', 'format'=>'A4-P', 'tempDir'=>appMpdfTempDir(),
        'fontDir'=>array_merge($defaultConfig['fontDir'], [$fontDir]),
        'fontdata'=>$fontConfig['fontdata'] + ['thsarabunpsk'=>['R'=>'THSarabun.ttf','B'=>'THSarabun Bold.ttf','I'=>'THSarabun Italic.ttf','BI'=>'THSarabun BoldItalic.ttf']],
        'default_font'=>'thsarabunpsk',
    ]);
    $mpdf->SetTitle('สรุปผลการปฏิบัติราชการ - ' . $user['fullname']);
    $mpdf->WriteHTML($html);
    $pdf = $mpdf->Output('', Destination::STRING_RETURN);

    $qaOutput = PHP_SAPI === 'cli' ? getenv('PERFORMANCE_SUMMARY_PDF_OUTPUT') : false;
    if ($qaOutput) {
        file_put_contents($qaOutput, $pdf);
        exit;
    }
    $safeName = preg_replace('/[^\p{L}\p{N}_-]+/u', '_', (string)$user['fullname']);
    $filename = 'สรุปผลการปฏิบัติราชการ_' . ($safeName ?: 'report') . '.pdf';
    header('Content-Type: application/pdf');
    header("Content-Disposition: attachment; filename=\"performance-summary.pdf\"; filename*=UTF-8''" . rawurlencode($filename));
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
} catch (RuntimeException $e) {
    http_response_code(strpos($e->getMessage(), 'สิทธิ์') !== false ? 403 : 404);
    exit(performancePdfE($e->getMessage()));
} catch (Throwable $e) {
    error_log('Performance summary PDF failed: ' . $e->getMessage());
    http_response_code(500);
    exit('ไม่สามารถสร้างรายงานได้ชั่วคราว');
}
