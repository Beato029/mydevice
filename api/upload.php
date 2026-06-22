<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Metodo non supportato'], 405);
}

authRequired();

if (empty($_FILES['image'])) {
    jsonResponse(['error' => 'Nessun file caricato'], 400);
}

$file    = $_FILES['image'];
$maxSize = 5 * 1024 * 1024; // 5MB
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['error' => 'Errore upload: ' . $file['error']], 400);
}

if ($file['size'] > $maxSize) {
    jsonResponse(['error' => 'File troppo grande (max 5MB)'], 400);
}

$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowed)) {
    jsonResponse(['error' => 'Tipo file non supportato'], 400);
}

// Carica su Supabase Storage
$ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
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
    $publicUrl = '/uploads/' . $filename;
} else {
    $publicUrl = SUPABASE_URL . '/storage/v1/object/public/products/' . $filename;
}

jsonResponse(['url' => $publicUrl]);
