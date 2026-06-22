<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Metodo non supportato'], 405);
}

// Richiede una sessione admin valida
authRequired();

$body    = json_decode(file_get_contents('php://input'), true);
$current = trim($body['current'] ?? '');
$next    = trim($body['next'] ?? '');
$confirm = trim($body['confirm'] ?? '');

if (!$current || !$next || !$confirm) {
    jsonResponse(['error' => 'Compila tutti i campi'], 400);
}
if ($next !== $confirm) {
    jsonResponse(['error' => 'La nuova password e la conferma non coincidono'], 400);
}
if (strlen($next) < 6) {
    jsonResponse(['error' => 'La nuova password deve avere almeno 6 caratteri'], 400);
}
if ($next === $current) {
    jsonResponse(['error' => 'La nuova password deve essere diversa da quella attuale'], 400);
}

// C'è un solo account admin: lo recuperiamo
$res  = supabase('GET', 'admin_users', [], ['order=id.asc', 'limit=1']);
$user = $res['data'][0] ?? null;
if (!$user) {
    jsonResponse(['error' => 'Account admin non trovato'], 404);
}

// Verifica la password attuale
if (!password_verify($current, $user['password_hash'])) {
    jsonResponse(['error' => 'Password attuale errata'], 401);
}

// Aggiorna con il nuovo hash bcrypt
$newHash = password_hash($next, PASSWORD_BCRYPT);
$upd = supabase('PATCH', 'admin_users', ['password_hash' => $newHash], ['id=eq.' . intval($user['id'])]);

if ($upd['status'] >= 400) {
    jsonResponse(['error' => 'Errore durante l\'aggiornamento'], 500);
}

// Per sicurezza: invalida tutte le altre sessioni (logout globale)
// Manteniamo solo la sessione corrente
$auth  = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = str_starts_with($auth, 'Bearer ') ? substr($auth, 7) : '';
if ($token) {
    supabase('DELETE', 'admin_sessions', [], ['token=neq.' . urlencode($token)]);
}

jsonResponse(['ok' => true]);
