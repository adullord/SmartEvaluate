<?php
require_once '_bootstrap.php';

$stats = [
    'users' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'active_users' => (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_active = 1')->fetchColumn(),
    'departments' => (int)$pdo->query('SELECT COUNT(*) FROM departments')->fetchColumn(),
    'active_cycles' => (int)$pdo->query("SELECT COUNT(*) FROM evaluation_cycles WHERE status='active'")->fetchColumn(),
];

$menus = [
    ['users.php#accounts', 'users', 'จัดการบัญชีผู้ใช้', 'เพิ่ม แก้ไข เปิดหรือปิดใช้งานบัญชี และรีเซ็ตรหัสผ่าน'],
    ['users.php', 'user-plus', 'เพิ่มข้อมูลบุคลากร', 'บันทึกบุคลากร หน่วยบริการ ตำแหน่ง ระดับ และสิทธิ์ใช้งาน'],
    ['departments.php', 'building', 'จัดการหน่วยบริการ', 'เพิ่มหรือแก้ไขสำนักงานสาธารณสุขอำเภอและ รพ.สต.'],
    ['positions.php', 'layers', 'สายงานและระดับตำแหน่ง', 'เพิ่มหรือแก้ไขชื่อตำแหน่งและระดับตำแหน่ง'],
    ['evaluators.php', 'link', 'กำหนดผู้ประเมิน', 'จับคู่ผู้ประเมินกับผู้รับการประเมินในแต่ละรอบ'],
    ['cycles.php', 'calendar', 'รอบการประเมิน', 'สร้างรอบใหม่ และเปิดหรือปิดรอบการประเมิน'],
    ['competencies.php', 'clipboard-list', 'สมรรถนะและพฤติกรรมบ่งชี้', 'จัดการสมรรถนะ คำอธิบายระดับ และพฤติกรรมบ่งชี้'],
    ['weights.php', 'scale', 'กำหนดน้ำหนักคะแนน', 'กำหนดชุดสมรรถนะและน้ำหนักตามสายงานและระดับที่คาดหวัง'],
    ['unlock.php', 'unlock', 'ปลดล็อกแบบประเมิน', 'ส่งแบบประเมินที่ยืนยันแล้วกลับไปเป็นฉบับร่างเพื่อแก้ไข'],
    [appUrl('report.php'), 'bar-chart', 'รายงานทุกหน่วยงาน', 'ดูผลการประเมินของ สสอ. และ รพ.สต. ทุกแห่ง'],
    ['logs.php', 'history', 'ประวัติการแก้ไข', 'ตรวจสอบการบันทึก ยืนยันรับทราบ และการปลดล็อกแบบประเมิน'],
];
require_once '../includes/header.php';
?>
<div class="card" style="margin-bottom:1.5rem">
  <div class="card-header"><div><h2 class="card-title"><?= appIcon('settings') ?> ผู้ดูแลระบบ</h2><p style="color:var(--text-muted);margin:.4rem 0 0">ศูนย์กลางจัดการบุคลากร แบบประเมิน และรายงานทั้งระบบ</p></div></div>
</div>
<?php renderAdminFlash(); ?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:1rem;margin-bottom:1.5rem">
<?php foreach ([['บุคลากรทั้งหมด',$stats['users']],['บัญชีที่ใช้งาน',$stats['active_users']],['หน่วยบริการ',$stats['departments']],['รอบที่เปิด',$stats['active_cycles']]] as [$label,$value]): ?>
  <div class="card" style="text-align:center;padding:1.25rem"><div style="font-size:2rem;font-weight:800;color:var(--primary-color)"><?= $value ?></div><div style="color:var(--text-muted)"><?= $label ?></div></div>
<?php endforeach; ?>
</div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(270px,1fr));gap:1rem">
<?php foreach ($menus as [$href,$icon,$title,$description]): ?>
  <a href="<?= $href ?>" class="card" style="text-decoration:none;color:inherit;padding:1.3rem;display:flex;gap:1rem;align-items:flex-start;border-left:4px solid var(--primary-color)">
    <span class="admin-menu-icon"><?= appIcon($icon) ?></span><span><strong style="display:block;font-size:1.05rem;margin-bottom:.35rem"><?= $title ?></strong><small style="color:var(--text-muted);line-height:1.5"><?= $description ?></small></span>
  </a>
<?php endforeach; ?>
</div>
<?php require_once '../includes/footer.php'; ?>
