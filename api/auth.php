<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/db.php';

// POST /api/auth.php → login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $username = trim($body['username'] ?? '');
    $password = trim($body['password'] ?? '');

    if (!$username || !$password) {
        jsonResponse(['error' => 'Credenziali mancanti'], 400);
    }

    // Cerca utente
    $res = supabase('GET', 'admin_users', [], ['username=eq.' . urlencode($username)]);

    if (empty($res['data'])) {
        jsonResponse(['error' => 'Credenziali errate'], 401);
    }

    $user = $res['data'][0];

    if (!password_verify($password, $user['password_hash'])) {
        jsonResponse(['error' => 'Credenziali errate'], 401);
    }

    // Crea token
    $token     = bin2hex(random_bytes(32));
    $expiresAt = date('c', strtotime('+24 hours'));

    supabase('POST', 'admin_sessions', [
        'token'      => $token,
        'expires_at' => $expiresAt,
    ]);

    jsonResponse(['token' => $token]);
}

// DELETE /api/auth.php → logout
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (str_starts_with($auth, 'Bearer ')) {
        $token = substr($auth, 7);
        supabase('DELETE', 'admin_sessions', [], ['token=eq.' . urlencode($token)]);
    }
    jsonResponse(['ok' => true]);
}

jsonResponse(['error' => 'Metodo non supportato'], 405);
