<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Metodo non supportato'], 405);
}

authRequired();

$maxSize = 5 * 1024 * 1024; // 5MB
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

// Raccoglie i file: supporta sia 'image' (singolo) che 'images' (multiplo / images[])
$files = [];

if (!empty($_FILES['images']) && is_array($_FILES['images']['name'])) {
    $count = count($_FILES['images']['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($_FILES['images']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        $files[] = [
            'name'     => $_FILES['images']['name'][$i],
            'tmp_name' => $_FILES['images']['tmp_name'][$i],
            'size'     => $_FILES['images']['size'][$i],
            'error'    => $_FILES['images']['error'][$i],
        ];
    }
} elseif (!empty($_FILES['image'])) {
    $files[] = $_FILES['image'];
}

if (empty($files)) {
    jsonResponse(['error' => 'Nessun file caricato'], 400);
}

function uploadOne(array $file, int $maxSize, array $allowed): string {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Errore upload: ' . $file['error']);
    }
    if ($file['size'] > $maxSize) {
        throw new Exception('File troppo grande (max 5MB): ' . $file['name']);
    }

    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowed)) {
        throw new Exception('Tipo file non supportato: ' . $file['name']);
    }

    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $filename = uniqid('img_', true) . '.' . strtolower($ext);
    $fileData = file_get_contents($file['tmp_name']);

    $storageUrl = SUPABASE_URL . '/storage/v1/object/products/' . $filename;

    $ch = curl_init($storageUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fileData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_KEY,
        'Authorization: Bearer ' . SUPABASE_KEY,
        'Content-Type: ' . $mimeType,
        'x-upsert: true',
    ]);
    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status >= 400) {
        // Fallback: salva localmente
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        move_uploaded_file($file['tmp_name'], $uploadDir . $filename);
        return '/uploads/' . $filename;
    }
    return SUPABASE_URL . '/storage/v1/object/public/products/' . $filename;
}

$urls = [];
try {
    foreach ($files as $f) {
        $urls[] = uploadOne($f, $maxSize, $allowed);
    }
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 400);
}

// Risposta: 'url' per retrocompatibilità singolo, 'urls' per multiplo
jsonResponse(['url' => $urls[0], 'urls' => $urls]);
