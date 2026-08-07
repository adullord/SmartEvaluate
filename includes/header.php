<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ระบบประเมินผลการปฏิบัติงานออนไลน์ Smart Evaluate">
    <title>ระบบประเมินสมรรถนะบุคลากรสาธารณสุข</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(appUrl('assets/css/style.css')) ?>?v=<?= time() ?>">
    <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars(appUrl('assets/images/favicon.svg')) ?>">
</head>
<body>
    <div class="app-layout">
        <!-- Global Sidebar (Dark Mode) -->
        <aside class="global-sidebar" id="globalSidebar">
            <div class="sidebar-brand">
                <span class="brand-icon"><?= appIcon('bar-chart') ?></span>
                <span class="brand-text">ระบบประเมินสมรรถนะ<br><small>บุคลากรสาธารณสุข</small></span>
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
                    <a href="<?= htmlspecialchars(appUrl('index.php')) ?>" class="nav-item">
                        <span class="nav-icon"><?= appIcon('home') ?></span> <span class="nav-text">หน้าแรก</span>
                    </a>
                    <a href="<?= htmlspecialchars(appUrl('report.php')) ?>" class="nav-item">
                        <span class="nav-icon"><?= appIcon('bar-chart') ?></span> <span class="nav-text">รายงานผล</span>
                    </a>
                    
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <div class="nav-divider"></div>
                        <div class="nav-header">การจัดการระบบ</div>
                        <a href="<?= htmlspecialchars(appUrl('admin/index.php')) ?>" class="nav-item">
                            <span class="nav-icon"><?= appIcon('settings') ?></span> <span class="nav-text">เมนูผู้ดูแลระบบ</span>
                        </a>
                    <?php endif; ?>
                </nav>

                <div class="sidebar-footer">
                    <a href="<?= htmlspecialchars(appUrl('logout.php')) ?>" class="nav-item nav-logout">
                        <span class="nav-icon"><?= appIcon('log-out') ?></span> <span class="nav-text">ออกจากระบบ</span>
                    </a>
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
