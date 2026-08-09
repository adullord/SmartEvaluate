<?php
require_once '_bootstrap.php';
require_once __DIR__ . '/../includes/kpi_helper.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: kpis.php'); exit; }
$cycleId=requestInt($_POST['cycle_id']??null,'cycle_id');
try {
    verifyAdminCsrf();
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM evaluation_cycles WHERE id=?');$stmt->execute([$cycleId]);
    if(!(int)$stmt->fetchColumn())throw new RuntimeException('ไม่พบรอบการประเมิน');
    $file=$_FILES['kpi_file']??null;
    if(!$file||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('กรุณาเลือกไฟล์ Excel ที่ต้องการนำเข้า');
    if((int)$file['size']>10*1024*1024)throw new RuntimeException('ไฟล์มีขนาดเกิน 10 MB');
    if(!in_array(strtolower(pathinfo((string)$file['name'],PATHINFO_EXTENSION)),['xlsx','xls'],true))throw new RuntimeException('รองรับเฉพาะไฟล์ .xlsx และ .xls');
    if(PHP_SAPI!=='cli'&&!is_uploaded_file((string)$file['tmp_name']))throw new RuntimeException('ไฟล์อัปโหลดไม่ถูกต้อง');
    $readerType=IOFactory::identify((string)$file['tmp_name']);
    if(!in_array($readerType,['Xlsx','Xls'],true))throw new RuntimeException('เนื้อหาไฟล์ไม่ใช่ Excel ที่รองรับ');

    $book=IOFactory::load($file['tmp_name']);
    $sheet=$book->getSheetByName('ตัวชี้วัด')?:$book->getActiveSheet();
    $highestRow=min(2000,$sheet->getHighestDataRow());
    $users=$pdo->query("SELECT u.id,u.fullname FROM users u JOIN departments d ON d.id=u.department_id WHERE u.is_active=1 AND d.type='SSO' ORDER BY u.fullname")->fetchAll();
    $userMap=[];$ssoUserIds=[];
    foreach($users as $user){$userMap[trim($user['fullname'])]=(int)$user['id'];$ssoUserIds[]=(int)$user['id'];}

    $records=[];
    for($row=4;$row<=$highestRow;$row++){
        $name=trim((string)$sheet->getCell("B{$row}")->getValue());
        if($name==='')continue;
        $thresholds=[];foreach(['E','F','G','H','I'] as $column)$thresholds[]=$sheet->getCell("{$column}{$row}")->getValue()===''?null:(float)$sheet->getCell("{$column}{$row}")->getCalculatedValue();
        $records[]=[
            'row'=>$row,
            'order'=>max(1,(int)$sheet->getCell("A{$row}")->getCalculatedValue()),
            'name'=>$name,
            'target_label'=>trim((string)$sheet->getCell("C{$row}")->getFormattedValue())?:null,
            'target_value'=>is_numeric($sheet->getCell("C{$row}")->getValue())?(float)$sheet->getCell("C{$row}")->getCalculatedValue():null,
            'weight'=>(float)$sheet->getCell("D{$row}")->getCalculatedValue(),
            'thresholds'=>$thresholds,
            'primary'=>trim((string)$sheet->getCell("J{$row}")->getFormattedValue()),
        ];
    }
    if(!$records)throw new RuntimeException('ไม่พบข้อมูลตัวชี้วัดในไฟล์');
    foreach($records as $record){
        if($record['weight']<=0)throw new RuntimeException("แถว {$record['row']}: ค่าน้ำหนักต้องมากกว่า 0");
        if(in_array(null,$record['thresholds'],true))throw new RuntimeException("แถว {$record['row']}: กรุณากรอกเกณฑ์คะแนน 1–5 ให้ครบ");
        if($record['primary']===''||!isset($userMap[$record['primary']]))throw new RuntimeException("แถว {$record['row']}: กรุณาเลือกผู้รับผิดชอบงานหลักจากบุคลากร สสอ.");
    }
    usort($records,fn($a,$b)=>$a['order']<=>$b['order']?:$a['row']<=>$b['row']);

    $pdo->beginTransaction();
    $nextOrder=kpiNormalizeIndicatorOrder($pdo,$cycleId)+1;
    $insert=$pdo->prepare('INSERT INTO kpi_indicators (cycle_id,name,target_label,weight,target_value,score_1_threshold,score_2_threshold,score_3_threshold,score_4_threshold,score_5_threshold,scoring_direction,order_seq,is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1)');
    $assign=$pdo->prepare('INSERT INTO kpi_assignments (indicator_id,user_id,responsibility_type,assigned_by) VALUES (?,?,\'primary\',?) ON DUPLICATE KEY UPDATE responsibility_type=\'primary\',assigned_by=VALUES(assigned_by)');
    $deleteSso=null;if($ssoUserIds){$marks=implode(',',array_fill(0,count($ssoUserIds),'?'));$deleteSso=$pdo->prepare("DELETE FROM kpi_assignments WHERE indicator_id=? AND user_id IN ($marks)");}
    foreach($records as $record){
        $thresholds=$record['thresholds'];$direction=$thresholds[0]<=$thresholds[4]?'ascending':'descending';
        $insert->execute([$cycleId,$record['name'],$record['target_label'],$record['weight'],$record['target_value'],...$thresholds,$direction,$nextOrder++]);
        $indicatorId=(int)$pdo->lastInsertId();
        if($deleteSso)$deleteSso->execute([$indicatorId,...$ssoUserIds]);
        $assign->execute([$indicatorId,$userMap[$record['primary']],(int)$_SESSION['user_id']]);
    }
    kpiEnsureDirectorAssignments($pdo);
    $pdo->commit();
    adminRedirect('kpis.php?cycle_id='.$cycleId,'success','นำเข้า '.count($records).' ตัวชี้วัดเรียบร้อย โดยเรียงลำดับต่อจากรายการเดิม');
} catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    adminRedirect('kpis.php?cycle_id='.$cycleId,'error',$e->getMessage());
}
