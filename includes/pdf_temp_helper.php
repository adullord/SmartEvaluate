<?php

/** Return a private writable base directory for mPDF cache files. */
function appMpdfTempDir(): string
{
    $projectRoot = dirname(__DIR__);
    $runtimeUid = function_exists('posix_geteuid') ? (string)posix_geteuid() : 'web';
    $systemBase = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'smartevaluate-' . $runtimeUid . '-' . substr(hash('sha256', $projectRoot), 0, 12);

    // Prefer the operating system's temporary area so the web directory does not need write permission.
    foreach ([$systemBase, $projectRoot . DIRECTORY_SEPARATOR . 'tmp'] as $base) {
        if (is_link($base)) continue;
        if (!is_dir($base) && !@mkdir($base, 0770, true) && !is_dir($base)) continue;
        $cache = $base . DIRECTORY_SEPARATOR . 'mpdf';
        if (is_link($cache)) continue;
        if (!is_dir($cache) && !@mkdir($cache, 0770, true) && !is_dir($cache)) continue;
        if (!is_writable($base) || !is_writable($cache)) continue;
        try {
            $probe = $cache . DIRECTORY_SEPARATOR . '.write-' . bin2hex(random_bytes(8));
            if (@file_put_contents($probe, 'ok', LOCK_EX) === false) continue;
            @unlink($probe);
            return $base;
        } catch (Throwable $e) {
            continue;
        }
    }
    throw new RuntimeException('ไม่พบโฟลเดอร์ชั่วคราวที่เขียนได้สำหรับสร้าง PDF กรุณาติดต่อผู้ดูแลเซิร์ฟเวอร์');
}
