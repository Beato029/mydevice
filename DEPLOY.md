# Deploy MyDevice su Render (senza GitHub)

Tutto (frontend + backend + API) gira da un unico servizio Render.

## Metodo: Deploy manuale via Render CLI o Blueprint

### Opzione A — Deploy diretto con Render CLI (consigliato)

1. Installa la Render CLI:
   ```bash
   # macOS
   brew install render

   # oppure scarica da https://render.com/docs/cli
   ```

2. Login:
   ```bash
   render login
   ```

3. Dalla cartella del progetto, crea il servizio:
   ```bash
   cd mydevice-backend
   render deploy
   ```
   Segui le istruzioni — Render rileva il `Dockerfile` automaticamente.

### Opzione B — Carica un archivio ZIP (più semplice, no CLI)

Alcuni piani Render permettono deploy via upload diretto. In alternativa
puoi collegare il repo Git interno di Render. Se preferisci, posso comunque
prepararti tutto per il push.

---

## Variabili d'ambiente (OBBLIGATORIE)

Nel dashboard Render del servizio → **Environment**, aggiungi:

| Key | Value |
|-----|-------|
| `SUPABASE_URL` | il Project URL del tuo progetto Supabase |
| `SUPABASE_SERVICE_KEY` | la service_role key del tuo progetto Supabase |

---

## Dopo il deploy

Render ti dà un URL tipo `https://mydevice.onrender.com`.

- Sito: `https://mydevice.onrender.com`
- Admin: `https://mydevice.onrender.com/login.html` → `admin` / `password`
- Health: `https://mydevice.onrender.com/health`

**Non serve cambiare niente nel codice** — il frontend usa path relativi
(`/api/...`), quindi funziona automaticamente sullo stesso dominio.

---

## NOTE

- **Piano Free**: sleep dopo 15 min inattività → primo accesso ~30-50s.
- **Immagini**: il filesystem Render è effimero. Per le immagini caricate
  dall'admin, crea su Supabase → Storage un bucket pubblico chiamato
  `products`. Il codice (`api/upload.php`) lo usa già automaticamente.
- **Password admin**: cambiala generando un nuovo hash e aggiornando la
  riga `password_hash` nella tabella `admin_users` su Supabase.
