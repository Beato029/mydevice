<?php
// Router unico: serve frontend statico + API PHP
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = strtok($path, '?');

// Health check
if ($path === '/health') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'service' => 'mydevice']);
    exit;
}

// Route API PHP
if (str_starts_with($path, '/api/')) {
    $script = __DIR__ . $path;
    if (file_exists($script) && str_ends_with($script, '.php')) {
        require $script;
        exit;
    }
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Endpoint non trovato']);
    exit;
}

// Homepage
if ($path === '/' || $path === '') {
    require __DIR__ . '/index.html';
    exit;
}

// File statici esistenti (html, css, js, immagini, uploads)
$file = __DIR__ . $path;
if (file_exists($file) && !is_dir($file)) {
    return false; // PHP built-in server serve il file con il MIME corretto
}

// 404 → torna alla home
http_response_code(404);
require __DIR__ . '/index.html';
