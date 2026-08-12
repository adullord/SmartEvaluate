<?php
require_once 'config.php';
require_once 'vendor/autoload.php';
require_once __DIR__ . '/includes/component3_helper.php';
require_once __DIR__ . '/includes/pdf_temp_helper.php';

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

if (!isset($_SESSION['user_id'])) { http_response_code(401); exit('Unauthorized'); }
try {
    $assessmentId = requestInt($_GET['id'] ?? null, 'id');
    if (!$assessmentId) throw new RuntimeException('ไม่พบผลประเมิน');
    $stmt = $pdo->prepare("SELECT a.*,u.fullname,u.role,d.name department_full_name,d.short_name department_name,p.name position_name,r.name rank_name,c.fiscal_year,c.round_name
        FROM component3_assessments a JOIN users u ON u.id=a.user_id JOIN departments d ON d.id=u.department_id
        JOIN positions p ON p.id=u.position_id JOIN ranks r ON r.id=u.rank_id JOIN evaluation_cycles c ON c.id=a.cycle_id WHERE a.id=? LIMIT 1");
    $stmt->execute([$assessmentId]); $assessment = $stmt->fetch();
    if (!$assessment) throw new RuntimeException('ไม่พบผลประเมิน');
    if (!component3CanView($pdo, (int)$_SESSION['user_id'], (string)$_SESSION['role'], (int)$assessment['user_id'], (int)$assessment['cycle_id'])) throw new RuntimeException('ไม่มีสิทธิ์ดูรายงานนี้');
    $person = component3UserContext($pdo, (int)$assessment['user_id']);
    $items = component3ItemsForCycle($pdo, (int)$assessment['cycle_id'], (bool)$person['includes_items_1_2'], $person['department_score']);
    $stmt = $pdo->prepare('SELECT * FROM component3_scores WHERE assessment_id=?'); $stmt->execute([$assessmentId]);
    $scores = []; foreach ($stmt->fetchAll() as $row) $scores[(int)$row['item_no']] = $row;

    $rows = ''; $index = 0;
    foreach ($items as $itemNo => $item) {
        $index++; $score = $scores[$itemNo] ?? null;
        $criteria = [];
        if ($item['input_type'] === 'department_score') $criteria = ['ตามรหัสหน่วยบริการ','','','',''];
        else foreach ([1,2,3,4,5] as $level) { $found='-'; foreach ($item['thresholds'] as $threshold=>$levelScore) if ((int)$levelScore === $level) $found=(string)$threshold; $criteria[]=$found; }
        $actual = $item['input_type'] === 'department_score' ? 'อัตโนมัติ' : ($score && $score['actual_value'] !== null ? number_format((float)$score['actual_value'], 2) : '-');
        $rows .= '<tr><td class="c">'.$itemNo.'</td><td>'.htmlspecialchars($item['name']).'</td><td class="c">'.number_format($item['weight'],0).'</td><td class="c">'.htmlspecialchars($item['target_label']).'</td><td class="c">'.$actual.'</td><td class="c">'.($score && $score['percentage'] !== null ? number_format((float)$score['percentage'],2) : '-').'</td>';
        foreach ($criteria as $criterion) $rows .= '<td class="c">'.htmlspecialchars($criterion).'</td>';
        $rows .= '<td class="c">'.($score ? number_format((float)$score['score'],0) : '-').'</td><td class="c">'.($score ? number_format((float)$score['weighted_score'],2) : '-').'</td><td>'.htmlspecialchars($item['responsible']).'</td></tr>';
    }
    $html = '<!doctype html><html lang="th"><head><meta charset="utf-8"><style>@page{margin:8mm}body{font-family:thsarabunpsk;font-size:16pt;color:#111;line-height:1.1}h1{text-align:center;font-size:20pt;margin:0 0 2mm}p{margin:0 0 1.5mm}.meta{width:100%;border-collapse:collapse;margin-bottom:3mm}.meta td{border:0;padding:1mm 2mm}.report{width:100%;border-collapse:collapse;table-layout:fixed}.report th,.report td{border:.25mm solid #111;padding:1mm;vertical-align:middle}.report th{text-align:center;font-weight:bold}.c{text-align:center}.summary{font-weight:bold}.small{font-size:14pt}</style></head><body><h1>องค์ประกอบที่ 3 งานมอบหมายพิเศษ</h1><table class="meta"><tr><td><b>ผู้รับการประเมิน:</b> '.htmlspecialchars($assessment['fullname']).'</td><td><b>รอบการประเมิน:</b> '.htmlspecialchars(component3CycleLabel($assessment)).'</td></tr><tr><td><b>ตำแหน่ง:</b> '.htmlspecialchars($assessment['position_name'].' '.$assessment['rank_name']).'</td><td><b>หน่วยบริการ:</b> '.htmlspecialchars($assessment['department_name'] ?: $assessment['department_full_name']).'</td></tr></table><table class="report"><colgroup><col style="width:3%"><col style="width:24%"><col style="width:5%"><col style="width:7%"><col style="width:7%"><col style="width:6%"><col span="5" style="width:4%"><col style="width:5%"><col style="width:7%"><col style="width:16%"></colgroup><thead><tr><th rowspan="2">ข้อ</th><th rowspan="2">ตัวชี้วัด</th><th rowspan="2">น้ำหนัก</th><th rowspan="2">ค่าเป้าหมาย</th><th rowspan="2">ผลการดำเนินงาน</th><th rowspan="2">ร้อยละ</th><th colspan="5">เกณฑ์คะแนน</th><th rowspan="2">คะแนน<br>(1–5)</th><th rowspan="2">คะแนนถ่วงน้ำหนัก</th><th rowspan="2">ผู้รับผิดชอบ</th></tr><tr><th>1</th><th>2</th><th>3</th><th>4</th><th>5</th></tr></thead><tbody>'.$rows.'<tr class="summary"><td colspan="2" class="c">รวม</td><td class="c">'.number_format((float)$assessment['applicable_weight'],0).'</td><td colspan="9" class="c">คะแนนองค์ประกอบที่ 3 (ฐาน 100)</td><td class="c">'.number_format((float)$assessment['total_weighted_score'],2).'</td><td class="c">'.number_format((float)$assessment['final_score'],2).'</td></tr></tbody></table><p class="small">หมายเหตุ: ข้อ 1–2 วัดเฉพาะบุคลากรใน สสอ. และผู้อำนวยการ รพ.สต. คะแนนข้อ 5 กำหนดอัตโนมัติตามรหัสหน่วยบริการ</p></body></html>';

    $fontDir = __DIR__ . '/assets/fonts/th-sarabun-psk'; $tempDir = appMpdfTempDir();
    $defaultConfig=(new ConfigVariables())->getDefaults(); $fontConfig=(new FontVariables())->getDefaults();
    $mpdf = new Mpdf(['mode'=>'utf-8','format'=>'A4-L','tempDir'=>$tempDir,'fontDir'=>array_merge($defaultConfig['fontDir'],[$fontDir]),'fontdata'=>$fontConfig['fontdata']+['thsarabunpsk'=>['R'=>'THSarabun.ttf','B'=>'THSarabun Bold.ttf','I'=>'THSarabun Italic.ttf','BI'=>'THSarabun BoldItalic.ttf']],'default_font'=>'thsarabunpsk']);
    $mpdf->SetTitle('องค์ประกอบที่ 3 - '.$assessment['fullname']); $mpdf->WriteHTML($html); $pdf=$mpdf->Output('',Destination::STRING_RETURN);
    $qa = PHP_SAPI === 'cli' ? getenv('COMPONENT3_PDF_OUTPUT') : false; if ($qa) { file_put_contents($qa,$pdf); exit; }
    $filename='องค์ประกอบที่3_'.preg_replace('/[^\p{L}\p{N}_-]+/u','_',$assessment['fullname']).'.pdf'; header('Content-Type: application/pdf'); header("Content-Disposition: attachment; filename=\"component3.pdf\"; filename*=UTF-8''".rawurlencode($filename)); header('Content-Length: '.strlen($pdf)); echo $pdf; exit;
} catch (PDOException $e) { error_log('Component 3 PDF failed: '.$e->getMessage()); http_response_code(500); exit('ไม่สามารถสร้างรายงานได้ชั่วคราว'); }
catch (Throwable $e) { http_response_code(403); exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8')); }
