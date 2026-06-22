<?php
// Carica .env se presente (sviluppo locale). Su Render usa le env var di sistema.
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            [$key, $val] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($val, " \t\n\r\0\x0B\"'");
        }
    }
}

function envVar(string $key): string {
    return $_ENV[$key] ?? getenv($key) ?: '';
}

define('SUPABASE_URL', rtrim(envVar('SUPABASE_URL'), '/'));
define('SUPABASE_KEY', envVar('SUPABASE_SERVICE_KEY'));

function supabase(string $method, string $table, array $data = [], array $filters = [], array $extra_headers = []): array {
    $url = SUPABASE_URL . '/rest/v1/' . $table;

    // Aggiungi filtri come query string
    if (!empty($filters)) {
        $url .= '?' . implode('&', $filters);
    }

    $headers = [
        'apikey: ' . SUPABASE_KEY,
        'Authorization: Bearer ' . SUPABASE_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation',
    ];
    $headers = array_merge($headers, $extra_headers);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));

    if (!empty($data) && in_array(strtoupper($method), ['POST', 'PATCH', 'PUT'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true) ?? [];
    return ['status' => $status, 'data' => $decoded];
}

function jsonResponse(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function authRequired(): void {
    $token = null;

    // Da header Authorization: Bearer <token>
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (str_starts_with($auth, 'Bearer ')) {
        $token = substr($auth, 7);
    }

    if (!$token) {
        jsonResponse(['error' => 'Non autorizzato'], 401);
    }

    $now = date('c');
    $res = supabase('GET', 'admin_sessions', [], [
        'token=eq.' . urlencode($token),
        'expires_at=gt.' . urlencode($now),
    ]);

    if (empty($res['data'])) {
        jsonResponse(['error' => 'Sessione scaduta o non valida'], 401);
    }
}
