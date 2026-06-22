# MyDevice — feature: immagini multiple + modifica prodotto

## STATO: codice completo, attesa migrazione DB utente

## FATTO
- api/upload.php → multi-file (images[]), risponde urls[]
- api/products.php → images JSONB + PATCH (gia fatto)
- admin.html → form multi-img, edit state (aEditId, aCancel, aFormTitle), galleria #imgGallery
- script.js initAdmin → upload multiplo, galleria riordino/rimozione, edit mode (startEdit→PATCH), resetForm
- script.js initProduct → galleria (img grande + thumbs + frecce)
- style.css → .admin-imgs/.admin-img, .pd-gallery/.pd-main/.pd-arrow/.pd-thumb
- syntax OK tutti i file

## BLOCCANTE
- colonna images NON esiste su Supabase. POST fallisce PGRST204. Utente deve lanciare:
  ALTER TABLE products ADD COLUMN IF NOT EXISTS images JSONB DEFAULT '[]'::jsonb;
  UPDATE products SET images = jsonb_build_array(image_url) WHERE image_url<>'' AND (images IS NULL OR images='[]');

## DOPO CONFERMA
1. test inline: login → POST 3 img → GET → PATCH edit → verify
2. ricordare: push GitHub + redeploy Render + bucket 'products' pubblico

## NOTE
- bg php muore a fine bash → test tutto in UN comando
- port 9099 puo restare occupata: pkill -f "php -S 0.0.0.0:9099" prima
- admin: admin/password
