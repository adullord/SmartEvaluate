<?php

/** Apply baseline HTTP hardening to every PHP entry point. */
function applySecurityHeaders(): void
{
    if (headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'; img-src 'self' data:; font-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'");
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('Cache-Control: no-store, max-age=0');
    header('Pragma: no-cache');
}

/** Reject malformed paths and HTTP methods before application routing occurs. */
function validateHttpRequest(): void
{
    if (PHP_SAPI === 'cli') return;
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['GET', 'POST', 'HEAD'], true)) {
        header('Allow: GET, POST, HEAD');
        http_response_code(405);
        exit('Method Not Allowed');
    }

    $rawUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $rawPath = explode('?', $rawUri, 2)[0];
    $decodedPath = $rawPath;
    for ($i = 0; $i < 3; $i++) {
        $next = rawurldecode($decodedPath);
        if ($next === $decodedPath) break;
        $decodedPath = $next;
    }
    if (
        str_contains($rawPath, "\0") || str_contains($decodedPath, "\0") ||
        preg_match('/%(?:00|2e|2f|5c)/i', $rawPath) ||
        str_starts_with($decodedPath, '//') ||
        str_contains($decodedPath, '\\') ||
        preg_match('~(?:^|/)\.\.(?:/|$)~', $decodedPath) ||
        preg_match('/[\x00-\x1F\x7F]/', $decodedPath)
    ) {
        http_response_code(400);
        exit('Bad Request');
    }
}

/** Strict integer parser for URL/form identifiers; unlike a cast, "1abc" is rejected. */
function requestInt(mixed $value, string $name, int $default = 0, int $min = 1, ?int $max = null): int
{
    if ($value === null || $value === '') return $default;
    if (is_array($value) || !preg_match('/^[0-9]+$/D', (string)$value)) {
        http_response_code(400);
        exit('พารามิเตอร์ ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ' ไม่ถูกต้อง');
    }
    $parsed = filter_var((string)$value, FILTER_VALIDATE_INT);
    if ($parsed === false || $parsed < $min || ($max !== null && $parsed > $max)) {
        http_response_code(400);
        exit('พารามิเตอร์ ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ' ไม่ถูกต้อง');
    }
    return (int)$parsed;
}

function destroyCurrentSession(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
}
