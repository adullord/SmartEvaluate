<?php
require_once __DIR__ . '/../csrf_helper.php';
$currentNavPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
$navItemClass = static function ($paths) use ($currentNavPath): string {
    foreach ((array)$paths as $path) {
        if (rtrim($currentNavPath, '/') === rtrim(appUrl((string)$path), '/')) {
            return 'nav-item active';
        }
    }
    return 'nav-item';
};
$isAdminSection = strpos($currentNavPath, appUrl('admin/')) === 0
    && rtrim($currentNavPath, '/') !== rtrim(appUrl('admin/database_schema.php'), '/');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ระบบประเมินผลการปฏิบัติราชการ Smart Evaluate">
    <title>ระบบประเมินผลการปฏิบัติราชการ</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(appUrl('assets/css/style.css')) ?>?v=<?= time() ?>">
    <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars(appUrl('assets/images/favicon.svg')) ?>">
</head>
<body>
    <div class="app-layout">
        <!-- Global Sidebar (Dark Mode) -->
        <aside class="global-sidebar" id="globalSidebar">
            <div class="sidebar-brand">
                <span class="brand-icon"><?= appIcon('bar-chart') ?></span>
                <span class="brand-text">ระบบประเมินผล<br><small>การปฏิบัติราชการ</small></span>
            </div>

            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="sidebar-user">
                    <div class="user-avatar"><?= appIcon('user-round') ?></div>
                    <div class="user-info">
                        <div class="user-name"><?= htmlspecialchars($_SESSION['fullname']) ?></div>
                        <?php if($_SESSION['role'] === 'admin'): ?>
                            <div class="user-role">ผู้ดูแลระบบ (Admin)</div>
                        <?php else: ?>
                            <div class="user-role">ผู้ใช้งานระบบ</div>
                        <?php endif; ?>
                    </div>
                </div>

                <nav class="sidebar-nav-menu">
                    <div class="nav-section">
                        <div class="nav-header">เมนูหลัก</div>
                        <a href="<?= htmlspecialchars(appUrl('index.php')) ?>" class="<?= $navItemClass('index.php') ?>">
                            <span class="nav-icon"><?= appIcon('home') ?></span> <span class="nav-text">หน้าแรก</span>
                        </a>
                    </div>

                    <div class="nav-section">
                        <div class="nav-header">องค์ประกอบที่ 1 สมรรถนะ</div>
                        <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'ss_amphoe', 'director'], true)): ?>
                        <a href="<?= htmlspecialchars(appUrl('competency_assessments.php')) ?>" class="<?= $navItemClass(['competency_assessments.php', 'assessment.php']) ?>">
                            <span class="nav-icon"><?= appIcon('users') ?></span> <span class="nav-text">รายชื่อผู้ที่ต้องประเมิน</span>
                        </a>
                        <?php endif; ?>
                        <a href="<?= htmlspecialchars(appUrl('report.php')) ?>" class="<?= $navItemClass(['report.php', 'report_detail.php']) ?>">
                            <span class="nav-icon"><?= appIcon('bar-chart') ?></span> <span class="nav-text">รายงานผลการประเมิน</span>
                        </a>
                    </div>

                    <div class="nav-section">
                        <div class="nav-header">องค์ประกอบที่ 2 ตัวชี้วัด</div>
                        <a href="<?= htmlspecialchars(appUrl('kpi_results.php')) ?>" class="<?= $navItemClass('kpi_results.php') ?>">
                            <span class="nav-icon"><?= appIcon('activity') ?></span> <span class="nav-text">บันทึกผลตัวชี้วัด</span>
                        </a>
                        <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'ss_amphoe', 'director'], true)): ?>
                            <a href="<?= htmlspecialchars(appUrl('kpi_assignments.php')) ?>" class="<?= $navItemClass('kpi_assignments.php') ?>">
                                <span class="nav-icon"><?= appIcon('link') ?></span> <span class="nav-text">กำหนดผู้รับผิดชอบ</span>
                            </a>
                            <a href="<?= htmlspecialchars(appUrl('kpi_report.php')) ?>" class="<?= $navItemClass('kpi_report.php') ?>">
                                <span class="nav-icon"><?= appIcon('file-text') ?></span> <span class="nav-text">รายงานตัวชี้วัด</span>
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="nav-section">
                        <div class="nav-header">องค์ประกอบที่ 3 งานมอบหมายพิเศษ</div>
                        <a href="<?= htmlspecialchars(appUrl('component3_assessment.php')) ?>" class="<?= $navItemClass(['component3_assessment.php', 'process_component3.php']) ?>">
                            <span class="nav-icon"><?= appIcon('clipboard-check') ?></span> <span class="nav-text">ประเมินตนเอง</span>
                        </a>
                        <a href="<?= htmlspecialchars(appUrl('component3_report.php')) ?>" class="<?= $navItemClass(['component3_report.php', 'export_component3_pdf.php']) ?>">
                            <span class="nav-icon"><?= appIcon('file-text') ?></span> <span class="nav-text">รายงานองค์ประกอบที่ 3</span>
                        </a>
                    </div>

                    <div class="nav-section">
                        <div class="nav-header">รายงานสรุปผล</div>
                        <a href="<?= htmlspecialchars(appUrl('performance_summary.php')) ?>" class="<?= $navItemClass(['performance_summary.php', 'export_performance_summary_pdf.php']) ?>">
                            <span class="nav-icon"><?= appIcon('clipboard-list') ?></span> <span class="nav-text">สรุปผลการปฏิบัติราชการ</span>
                        </a>
                    </div>

                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <div class="nav-section nav-section-admin">
                            <div class="nav-header">การจัดการระบบ</div>
                            <a href="<?= htmlspecialchars(appUrl('admin/index.php')) ?>" class="<?= $isAdminSection ? 'nav-item active' : 'nav-item' ?>">
                                <span class="nav-icon"><?= appIcon('settings') ?></span> <span class="nav-text">ศูนย์ผู้ดูแลระบบ</span>
                            </a>
                            <a href="<?= htmlspecialchars(appUrl('admin/database_schema.php')) ?>" class="<?= $navItemClass('admin/database_schema.php') ?>">
                                <span class="nav-icon"><?= appIcon('database') ?></span> <span class="nav-text">ปรับโครงสร้างฐานข้อมูล</span>
                            </a>
                        </div>
                    <?php endif; ?>
                </nav>

                <div class="sidebar-footer">
                    <form method="post" action="<?= htmlspecialchars(appUrl('logout.php')) ?>" style="margin:0">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="nav-item nav-logout" style="width:100%;border:0;background:transparent;text-align:left;cursor:pointer;font:inherit">
                        <span class="nav-icon"><?= appIcon('log-out') ?></span> <span class="nav-text">ออกจากระบบ</span>
                    </button>
                    </form>
                </div>
            <?php else: ?>
                <nav class="sidebar-nav-menu">
                    <a href="<?= htmlspecialchars(appUrl('login.php')) ?>" class="nav-item">
                        <span class="nav-icon"><?= appIcon('log-in') ?></span> <span class="nav-text">เข้าสู่ระบบ</span>
                    </a>
                </nav>
            <?php endif; ?>
        </aside>

        <!-- Main Workspace -->
        <div class="app-main">
            <!-- Mobile Header (Visible only on small screens) -->
            <header class="mobile-header">
                <div class="mobile-brand">
                    <span class="brand-icon"><?= appIcon('bar-chart') ?></span>
                    <span class="brand-text">ประเมินสมรรถนะฯ</span>
                </div>
                <button class="hamburger" id="hamburgerBtn" aria-label="เมนู">
                    <span></span><span></span><span></span>
                </button>
            </header>
            
            <main class="app-content">
                <div class="container">
