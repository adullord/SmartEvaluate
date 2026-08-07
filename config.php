<?php
require_once __DIR__ . '/includes/icon_helper.php';

$localConfigFile = __DIR__ . '/config.local.php';
$localConfig = is_file($localConfigFile) ? require $localConfigFile : [];
if (!is_array($localConfig)) {
    throw new RuntimeException('config.local.php must return an array');
}

$envOrDefault = static function (string $name, string $default): string {
    $value = getenv($name);
    return $value === false || $value === '' ? $default : $value;
};

$basePath = (string)($localConfig['base_path'] ?? $envOrDefault('APP_BASE_PATH', '/evaluations'));
define('APP_BASE_PATH', rtrim('/' . trim($basePath, '/'), '/'));

function appUrl(string $path = ''): string
{
    return APP_BASE_PATH . ($path === '' ? '' : '/' . ltrim($path, '/'));
}

// ยกระดับความปลอดภัย Session
if (session_status() === PHP_SESSION_NONE) {
    $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $httpsValue = strtolower((string)($_SERVER['HTTPS'] ?? ''));
    $isHttps = ($httpsValue !== '' && $httpsValue !== 'off') || $forwardedProto === 'https';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true, // ป้องกัน XSS ดึง Cookie
        'samesite' => 'Strict' // ป้องกัน CSRF ระดับเบราว์เซอร์
    ]);
    session_start();
}
// ตั้งค่าการเชื่อมต่อฐานข้อมูล MySQL
$host = (string)($localConfig['db_host'] ?? $envOrDefault('DB_HOST', 'localhost'));
$port = (int)($localConfig['db_port'] ?? $envOrDefault('DB_PORT', '3306'));
$dbname = (string)($localConfig['db_name'] ?? $envOrDefault('DB_NAME', 'evaluation_db'));
$username = (string)($localConfig['db_user'] ?? $envOrDefault('DB_USER', 'root'));
$password = (string)($localConfig['db_password'] ?? $envOrDefault('DB_PASSWORD', ''));

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // ใช้ prepared statement ของ MySQL จริง ป้องกันค่าที่ bind ถูกตีความเป็น SQL
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    // กำหนดให้ PDO แสดง Error แบบ Exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // กำหนดให้ดึงข้อมูลเป็นแบบ Associative Array เป็นค่าเริ่มต้น
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // บังคับเข้ารหัสภาษาไทย
    $pdo->exec("SET NAMES utf8mb4");
    require_once __DIR__ . '/includes/default_admin_helper.php';
    ensureDefaultAdmin($pdo);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(503);
    die('ไม่สามารถเชื่อมต่อระบบได้ชั่วคราว กรุณาติดต่อผู้ดูแลระบบ');
}
?>
