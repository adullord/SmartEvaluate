<?php
require_once 'config.php';
require_once 'csrf_helper.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method Not Allowed');
}
if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    exit('คำขอหมดอายุ');
}
destroyCurrentSession();
header('Location: ' . appUrl('login.php'));
exit;
?>
