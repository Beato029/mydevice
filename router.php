<?php
// Router unico: serve frontend statico + API PHP
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = rawurldecode(strtok($path, '?'));

// Sicurezza: niente path traversal
$path = str_replace('..', '', $path);

// Health check
if ($path === '/health') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'service' => 'mydevice']);
    exit;
}

// Route API PHP
if (str_starts_with($path, '/api/')) {
    $script = __DIR__ . $path;
    if (is_file($script) && str_ends_with($script, '.php')) {
        require $script;
        exit;
    }
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint non trovato']);
    exit;
}

// Homepage
if ($path === '/' || $path === '') {
    serveFile(__DIR__ . '/index.html');
}

// File statico
$file = __DIR__ . $path;
if (is_file($file)) {
    serveFile($file);
}

// Fallback SPA-like → home
serveFile(__DIR__ . '/index.html', 404);

// ── helper ──
function serveFile(string $file, int $status = 200): never {
    if (!is_file($file)) {
        http_response_code(404);
        echo 'Not found';
        exit;
    }
    http_response_code($status);

    $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mime = [
        'html' => 'text/html; charset=utf-8',
        'css'  => 'text/css; charset=utf-8',
        'js'   => 'application/javascript; charset=utf-8',
        'json' => 'application/json',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
    ][$ext] ?? 'application/octet-stream';

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
}
