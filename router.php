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

// ── Accesso admin via path segreto (env var ADMIN_PATH) ──
// Nome reale (non indovinabile) del file di login
$ADMIN_LOGIN_FILE = '_a1b2c3-login.html';
// Path segreto pubblico: da env var su Render. Fallback locale per sviluppo.
$ADMIN_PATH = trim(getenv('ADMIN_PATH') ?: ($_ENV['ADMIN_PATH'] ?? ''));
if ($ADMIN_PATH === '') $ADMIN_PATH = 'admin-dev'; // solo locale

$reqSlug = ltrim($path, '/');

// Se l'URL combacia col path segreto → mostra il login
if ($reqSlug === $ADMIN_PATH) {
    serveFile(__DIR__ . '/' . $ADMIN_LOGIN_FILE);
}

// Redirect neutro: /admin e /login → vanno al path segreto (302), senza esporlo nel JS
if ($reqSlug === 'admin' || $reqSlug === 'login') {
    header('Location: /' . $ADMIN_PATH);
    http_response_code(302);
    exit;
}

// Blocca l'accesso diretto al file di login reale e alla vecchia pagina
if ($reqSlug === $ADMIN_LOGIN_FILE || $reqSlug === 'admin-login-page.html' || $reqSlug === 'login.html') {
    serveFile(__DIR__ . '/index.html', 404);
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
