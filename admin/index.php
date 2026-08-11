<?php
require_once '_bootstrap.php';

$stats = [
    'users' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'active_users' => (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_active = 1')->fetchColumn(),
    'departments' => (int)$pdo->query('SELECT COUNT(*) FROM departments')->fetchColumn(),
    'active_cycles' => (int)$pdo->query("SELECT COUNT(*) FROM evaluation_cycles WHERE status='active'")->fetchColumn(),
];

$menuGroups = [
    [
        'title' => 'บุคลากรและหน่วยบริการ',
        'description' => 'จัดการบัญชี ข้อมูลบุคลากร สายงาน และหน่วยบริการ',
        'icon' => 'users',
        'color' => '#2563EB',
        'menus' => [
            ['users.php#accounts', 'users', 'จัดการบัญชีผู้ใช้', 'เพิ่ม แก้ไข เปิดหรือปิดใช้งานบัญชี และรีเซ็ตรหัสผ่าน'],
            ['users.php', 'user-plus', 'เพิ่มข้อมูลบุคลากร', 'บันทึกบุคลากร หน่วยบริการ ตำแหน่ง ระดับ และสิทธิ์ใช้งาน'],
            ['departments.php', 'building', 'จัดการหน่วยบริการ', 'เพิ่ม แก้ไข หรือลบข้อมูล สสอ. และ รพ.สต.'],
            ['positions.php', 'layers', 'สายงานและระดับตำแหน่ง', 'จัดการชื่อตำแหน่งและระดับตำแหน่ง'],
        ],
    ],
    [
        'title' => 'รอบและแบบประเมินสมรรถนะ',
        'description' => 'ตั้งค่ารอบ ผู้ประเมิน สมรรถนะ และน้ำหนักคะแนน',
        'icon' => 'clipboard-list',
        'color' => '#7C3AED',
        'menus' => [
            ['cycles.php', 'calendar', 'รอบการประเมิน', 'สร้างรอบใหม่ และเปิดหรือปิดรอบการประเมิน'],
            ['evaluators.php', 'link', 'กำหนดผู้ประเมิน', 'จับคู่ผู้ประเมินกับผู้รับการประเมินในแต่ละรอบ'],
            ['competencies.php', 'clipboard-list', 'สมรรถนะและพฤติกรรมบ่งชี้', 'จัดการสมรรถนะ คำอธิบายระดับ และพฤติกรรมบ่งชี้'],
            ['weights.php', 'scale', 'กำหนดน้ำหนักคะแนน', 'กำหนดชุดสมรรถนะและน้ำหนักตามสายงานและระดับที่คาดหวัง'],
            ['component3.php', 'clipboard-check', 'จัดการองค์ประกอบที่ 3', 'เพิ่ม ลบ แก้ไข เป้าหมาย น้ำหนัก และเกณฑ์คะแนนรายรอบ'],
            ['unlock.php', 'unlock', 'ปลดล็อกแบบประเมิน', 'ส่งแบบประเมินที่ยืนยันแล้วกลับไปเป็นฉบับร่างเพื่อแก้ไข'],
        ],
    ],
    [
        'title' => 'ตัวชี้วัดผลสัมฤทธิ์ของงาน',
        'description' => 'จัดการตัวชี้วัด ผู้รับผิดชอบ และติดตามผลรายหน่วยบริการ',
        'icon' => 'activity',
        'color' => '#059669',
        'menus' => [
            ['kpis.php', 'activity', 'จัดการตัวชี้วัด', 'เพิ่ม ลบ แก้ไข เป้าหมาย น้ำหนัก และเกณฑ์คะแนนรายรอบ'],
            [appUrl('kpi_assignments.php'), 'link', 'กำหนดผู้รับผิดชอบ', 'กำหนดผู้รับผิดชอบหลักและรองของแต่ละตัวชี้วัด'],
            [appUrl('kpi_report.php'), 'file-text', 'รายงานตัวชี้วัด', 'ติดตามผลราย รพ.สต. ผลรวม และผู้รับผิดชอบ'],
        ],
    ],
    [
        'title' => 'รายงานและตรวจสอบระบบ',
        'description' => 'ดูผลการประเมินทุกหน่วยงานและประวัติการแก้ไข',
        'icon' => 'bar-chart',
        'color' => '#D97706',
        'menus' => [
            [appUrl('report.php'), 'bar-chart', 'รายงานผลการประเมิน', 'ดูผลการประเมินของ สสอ. และ รพ.สต. ทุกแห่ง'],
            ['logs.php', 'history', 'ประวัติการแก้ไข', 'ตรวจสอบการบันทึก ยืนยันรับทราบ และการปลดล็อกแบบประเมิน'],
        ],
    ],
    [
        'title' => 'บำรุงรักษาระบบ',
        'description' => 'ตรวจสอบความพร้อมและปรับโครงสร้างฐานข้อมูลอย่างปลอดภัย',
        'icon' => 'settings',
        'color' => '#475569',
        'menus' => [
            ['database_schema.php', 'settings', 'ปรับโครงสร้างฐานข้อมูล', 'ตรวจสอบและเพิ่มตารางหรือคอลัมน์ที่ขาด โดยไม่ลบข้อมูลเดิม'],
        ],
    ],
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
<div class="admin-menu-groups">
<?php foreach ($menuGroups as $group): ?>
  <section class="card admin-menu-section" style="--group-color:<?= htmlspecialchars($group['color']) ?>">
    <div class="admin-group-header">
      <span class="admin-group-icon"><?= appIcon($group['icon']) ?></span>
      <div><h3><?= htmlspecialchars($group['title']) ?></h3><p><?= htmlspecialchars($group['description']) ?></p></div>
    </div>
    <div class="admin-group-grid">
      <?php foreach ($group['menus'] as [$href,$icon,$title,$description]): ?>
        <a href="<?= htmlspecialchars($href) ?>" class="admin-menu-link">
          <span class="admin-menu-icon"><?= appIcon($icon) ?></span>
          <span><strong><?= htmlspecialchars($title) ?></strong><small><?= htmlspecialchars($description) ?></small></span>
          <span class="admin-menu-arrow">›</span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>
</div>
<style>
.admin-menu-groups{display:grid;gap:1.25rem}.admin-menu-section{padding:0;overflow:hidden;border-top:4px solid var(--group-color)}
.admin-group-header{display:flex;gap:.9rem;align-items:center;padding:1.15rem 1.25rem;background:linear-gradient(90deg,color-mix(in srgb,var(--group-color) 9%,white),white);border-bottom:1px solid var(--border-color)}
.admin-group-icon{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;background:var(--group-color);color:white;flex:0 0 auto}.admin-group-icon .app-icon{width:23px;height:23px}
.admin-group-header h3{margin:0;color:var(--text-color)}.admin-group-header p{margin:.25rem 0 0;color:var(--text-muted);font-size:.9rem}
.admin-group-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));padding:.55rem 1rem 1rem;gap:.25rem 1rem}
.admin-menu-link{display:grid;grid-template-columns:42px minmax(0,1fr) 20px;gap:.75rem;align-items:center;text-decoration:none;color:inherit;padding:.85rem;border-radius:10px;border:1px solid transparent;transition:.18s ease}
.admin-menu-link:hover{background:color-mix(in srgb,var(--group-color) 6%,white);border-color:color-mix(in srgb,var(--group-color) 22%,white);transform:translateY(-1px)}
.admin-menu-link .admin-menu-icon{color:var(--group-color);width:42px;height:42px;border-radius:10px;display:grid;place-items:center;background:color-mix(in srgb,var(--group-color) 10%,white)}
.admin-menu-link strong{display:block;margin-bottom:.22rem}.admin-menu-link small{display:block;color:var(--text-muted);line-height:1.4}.admin-menu-arrow{font-size:1.6rem;color:var(--group-color);text-align:center}
@media(max-width:800px){.admin-group-grid{grid-template-columns:1fr}.admin-menu-link{padding:.75rem .55rem}}
</style>
<?php require_once '../includes/footer.php'; ?>
