<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = $_GET['id'] ?? null;

// Mappa da nomi Supabase → nomi frontend (italiano)
function mapProduct(array $p): array {
    return [
        'id'          => $p['id'],
        'nome'        => $p['name'],
        'prezzo'      => $p['price'],
        'immagine'    => $p['image_url'] ?? '',
        'colore'      => $p['color'] ?? '',
        'batteria'    => $p['condition'] ?? '',   // usiamo "condition" per batteria
        'condizioni'  => $p['storage'] ?? '',     // usiamo "storage" per condizioni visive
        'descrizione' => $p['description'] ?? '',
        'disponibile' => $p['available'] ?? true,
        'created_at'  => $p['created_at'] ?? '',
    ];
}

// GET → lista o singolo
if ($method === 'GET') {
    if ($id) {
        $res  = supabase('GET', 'products', [], ['id=eq.' . intval($id)]);
        $item = $res['data'][0] ?? null;
        if (!$item) jsonResponse(['error' => 'Prodotto non trovato'], 404);
        jsonResponse(mapProduct($item));
    } else {
        $filters = ['available=eq.true'];

        // Filtri opzionali
        $q        = trim($_GET['q'] ?? '');
        $colore   = trim($_GET['colore'] ?? '');
        $batteria = trim($_GET['batteria'] ?? '');
        $condizioni = trim($_GET['condizioni'] ?? '');
        $sort     = $_GET['sort'] ?? '';

        if ($colore)    $filters[] = 'color=eq.' . urlencode($colore);
        if ($batteria)  $filters[] = 'condition=eq.' . urlencode($batteria);
        if ($condizioni) $filters[] = 'storage=eq.' . urlencode($condizioni);

        // Ordinamento prezzo
        if ($sort === 'asc')  $filters[] = 'order=price.asc';
        elseif ($sort === 'desc') $filters[] = 'order=price.desc';
        else $filters[] = 'order=created_at.desc';

        $res  = supabase('GET', 'products', [], $filters);
        $list = array_map('mapProduct', $res['data'] ?? []);

        // Ricerca testo lato server non supportata da PostgREST senza fts
        // Filtriamo lato PHP se c'è una query
        if ($q) {
            $ql = strtolower($q);
            $list = array_values(array_filter($list, fn($p) =>
                str_contains(strtolower($p['nome']), $ql) ||
                str_contains(strtolower($p['descrizione']), $ql) ||
                str_contains(strtolower($p['colore']), $ql)
            ));
        }

        jsonResponse($list);
    }
}

// POST → crea prodotto
if ($method === 'POST') {
    authRequired();
    $body = json_decode(file_get_contents('php://input'), true);

    $nome     = trim($body['nome'] ?? '');
    $prezzo   = floatval($body['prezzo'] ?? 0);

    if (!$nome || $prezzo <= 0) {
        jsonResponse(['error' => 'Nome e prezzo sono obbligatori'], 400);
    }

    $res = supabase('POST', 'products', [
        'name'        => $nome,
        'price'       => $prezzo,
        'image_url'   => trim($body['immagine'] ?? ''),
        'color'       => trim($body['colore'] ?? ''),
        'condition'   => trim($body['batteria'] ?? ''),   // batteria → condition
        'storage'     => trim($body['condizioni'] ?? ''), // condizioni → storage
        'description' => trim($body['descrizione'] ?? ''),
        'available'   => true,
    ]);

    if ($res['status'] >= 400) {
        jsonResponse(['error' => 'Errore creazione prodotto', 'detail' => $res['data']], 500);
    }

    $created = $res['data'][0] ?? null;
    jsonResponse($created ? mapProduct($created) : ['ok' => true], 201);
}

// DELETE → elimina prodotto
if ($method === 'DELETE') {
    authRequired();
    if (!$id) jsonResponse(['error' => 'ID mancante'], 400);

    $res = supabase('DELETE', 'products', [], ['id=eq.' . intval($id)]);

    if ($res['status'] >= 400) {
        jsonResponse(['error' => 'Errore eliminazione'], 500);
    }

    jsonResponse(['ok' => true]);
}

jsonResponse(['error' => 'Metodo non supportato'], 405);
