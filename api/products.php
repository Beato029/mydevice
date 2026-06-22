<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = $_GET['id'] ?? null;

// Mappa da nomi Supabase → nomi frontend (italiano)
function mapProduct(array $p): array {
    $images = $p['images'] ?? [];
    if (is_string($images)) $images = json_decode($images, true) ?: [];

    // Copertina: prima immagine dell'array, fallback su image_url
    $cover = $images[0] ?? ($p['image_url'] ?? '');

    return [
        'id'          => $p['id'],
        'nome'        => $p['name'],
        'prezzo'      => $p['price'],
        'immagine'    => $cover,          // copertina (catalogo)
        'immagini'    => $images,         // array completo (scheda)
        'colore'      => $p['color'] ?? '',
        'batteria'    => $p['condition'] ?? '',   // condition = batteria
        'condizioni'  => $p['storage'] ?? '',     // storage = condizioni visive
        'descrizione' => $p['description'] ?? '',
        'disponibile' => $p['available'] ?? true,
        'created_at'  => $p['created_at'] ?? '',
    ];
}

// Normalizza array immagini dal body
function parseImages($body): array {
    $imgs = $body['immagini'] ?? null;
    if (is_array($imgs)) {
        return array_values(array_filter(array_map('trim', $imgs), fn($u) => $u !== ''));
    }
    // fallback: singola immagine
    $single = trim($body['immagine'] ?? '');
    return $single !== '' ? [$single] : [];
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

        $q          = trim($_GET['q'] ?? '');
        $colore     = trim($_GET['colore'] ?? '');
        $batteria   = trim($_GET['batteria'] ?? '');
        $condizioni = trim($_GET['condizioni'] ?? '');
        $sort       = $_GET['sort'] ?? '';

        if ($colore)     $filters[] = 'color=eq.' . urlencode($colore);
        if ($batteria)   $filters[] = 'condition=eq.' . urlencode($batteria);
        if ($condizioni) $filters[] = 'storage=eq.' . urlencode($condizioni);

        if ($sort === 'asc')       $filters[] = 'order=price.asc';
        elseif ($sort === 'desc')  $filters[] = 'order=price.desc';
        else                       $filters[] = 'order=created_at.desc';

        $res  = supabase('GET', 'products', [], $filters);
        $list = array_map('mapProduct', $res['data'] ?? []);

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

    $nome   = trim($body['nome'] ?? '');
    $prezzo = floatval($body['prezzo'] ?? 0);

    if (!$nome || $prezzo <= 0) {
        jsonResponse(['error' => 'Nome e prezzo sono obbligatori'], 400);
    }

    $images = parseImages($body);

    $res = supabase('POST', 'products', [
        'name'        => $nome,
        'price'       => $prezzo,
        'image_url'   => $images[0] ?? '',
        'images'      => $images,
        'color'       => trim($body['colore'] ?? ''),
        'condition'   => trim($body['batteria'] ?? ''),
        'storage'     => trim($body['condizioni'] ?? ''),
        'description' => trim($body['descrizione'] ?? ''),
        'available'   => true,
    ]);

    if ($res['status'] >= 400) {
        jsonResponse(['error' => 'Errore creazione prodotto', 'detail' => $res['data']], 500);
    }

    $created = $res['data'][0] ?? null;
    jsonResponse($created ? mapProduct($created) : ['ok' => true], 201);
}

// PATCH → modifica prodotto esistente
if ($method === 'PATCH') {
    authRequired();
    if (!$id) jsonResponse(['error' => 'ID mancante'], 400);

    $body = json_decode(file_get_contents('php://input'), true);
    $update = [];

    if (isset($body['nome']))        $update['name']        = trim($body['nome']);
    if (isset($body['prezzo']))      $update['price']       = floatval($body['prezzo']);
    if (isset($body['colore']))      $update['color']       = trim($body['colore']);
    if (isset($body['batteria']))    $update['condition']   = trim($body['batteria']);
    if (isset($body['condizioni']))  $update['storage']     = trim($body['condizioni']);
    if (isset($body['descrizione'])) $update['description'] = trim($body['descrizione']);
    if (isset($body['disponibile'])) $update['available']   = (bool)$body['disponibile'];

    // Immagini: se presenti, aggiorna array + copertina
    if (array_key_exists('immagini', $body)) {
        $images = parseImages($body);
        $update['images']    = $images;
        $update['image_url'] = $images[0] ?? '';
    }

    if (empty($update)) {
        jsonResponse(['error' => 'Nessun dato da aggiornare'], 400);
    }

    $res = supabase('PATCH', 'products', $update, ['id=eq.' . intval($id)]);

    if ($res['status'] >= 400) {
        jsonResponse(['error' => 'Errore modifica', 'detail' => $res['data']], 500);
    }

    $updated = $res['data'][0] ?? null;
    jsonResponse($updated ? mapProduct($updated) : ['ok' => true]);
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
