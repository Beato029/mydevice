// MyDevice — script.js
// Backend: PHP + SQLite

// Backend sullo stesso server → path relativo
const API = '/api';
const imgPlaceholder = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200'%3E%3Crect fill='%23111' width='100%25' height='100%25'/%3E%3Ctext fill='%23333' x='50%25' y='50%25' text-anchor='middle' dominant-baseline='middle' font-size='40'%3E%F0%9F%93%B1%3C/text%3E%3C/svg%3E";

// ── API helpers ──
function getToken() {
    return localStorage.getItem('md_admin_token') || '';
}

async function apiFetch(path, options = {}) {
    const token = getToken();
    const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
    if (token) headers['Authorization'] = 'Bearer ' + token;

    let res;
    try {
        res = await fetch(API + path, { ...options, headers });
    } catch (netErr) {
        throw new Error('Connessione al server fallita. Riprova.');
    }

    const text = await res.text();
    let data = {};
    try { data = text ? JSON.parse(text) : {}; }
    catch { throw new Error('Risposta server non valida (HTTP ' + res.status + ')'); }

    if (!res.ok) throw new Error(data.error || ('Errore API (HTTP ' + res.status + ')'));
    return data;
}

// ── toast ──
function toast(msg, type = 'info') {
    let wrap = document.querySelector('.toasts');
    if (!wrap) {
        wrap = document.createElement('div');
        wrap.className = 'toasts';
        document.body.appendChild(wrap);
    }
    const t = document.createElement('div');
    t.className = 'toast ' + (type === 'success' ? 'ok' : type === 'error' ? 'err' : '');
    t.textContent = msg;
    wrap.appendChild(t);
    setTimeout(() => {
        t.style.transition = 'opacity 0.2s';
        t.style.opacity = '0';
        setTimeout(() => t.remove(), 250);
    }, 2800);
}

// ── helpers ──
function dotClass(c) {
    return { Perfetto: 'dot-perfetto', Ottimo: 'dot-ottimo', Buono: 'dot-buono', Pessimo: 'dot-pessimo' }[c] || '';
}
function batPillClass(b) {
    return { Nuova: 'pill-bat-nuova', Ottima: 'pill-bat-ottima', Buona: 'pill-bat-buona', Usurata: 'pill-bat-usurata' }[b] || '';
}
function imgFallback(img) {
    img.onerror = null;
    img.src = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200'%3E%3Crect fill='%23111' width='100%25' height='100%25'/%3E%3Ctext fill='%23333' x='50%25' y='50%25' text-anchor='middle' dominant-baseline='middle' font-size='40'%3E📱%3C/text%3E%3C/svg%3E";
}

// ── navbar ──
function initNav() {
    const burger = document.querySelector('.burger');
    const links  = document.querySelector('.nav-links');
    if (!burger) return;

    burger.addEventListener('click', () => links.classList.toggle('on'));
    links.querySelectorAll('a').forEach(a => a.addEventListener('click', () => links.classList.remove('on')));

    const page = document.body.dataset.page;
    const map  = { index: 'index.html', prodotto: 'prodotto.html', contatti: 'contatti.html', admin: 'admin.html', login: 'admin-login-page.html' };
    links.querySelectorAll('a').forEach(a => {
        if (a.getAttribute('href') === map[page]) a.classList.add('on');
    });
}

// ── INDEX ──
async function initIndex() {
    const inp  = document.getElementById('searchInput');
    const fCol = document.getElementById('fCol');
    const fBat = document.getElementById('fBat');
    const fCon = document.getElementById('fCon');
    const btnA = document.getElementById('btnA');
    const btnD = document.getElementById('btnD');
    const btnR = document.getElementById('btnR');
    const grid = document.getElementById('grid');
    const cnt  = document.getElementById('cnt');

    let sort = null;

    async function render() {
        grid.innerHTML = '<div class="empty"><strong>Caricamento...</strong></div>';

        const params = new URLSearchParams();
        if (inp.value.trim())  params.set('q', inp.value.trim());
        if (fCol.value)        params.set('colore', fCol.value);
        if (fBat.value)        params.set('batteria', fBat.value);
        if (fCon.value)        params.set('condizioni', fCon.value);
        if (sort)              params.set('sort', sort);

        let list;
        try {
            list = await apiFetch('/products.php?' + params.toString(), { method: 'GET' });
        } catch (e) {
            grid.innerHTML = '<div class="empty"><strong>Errore caricamento</strong>' + e.message + '</div>';
            return;
        }

        if (cnt) cnt.textContent = list.length;
        grid.innerHTML = '';

        if (!list.length) {
            grid.innerHTML = '<div class="empty"><strong>Nessun risultato</strong>Prova a cambiare i filtri</div>';
            return;
        }

        list.forEach((p, i) => {
            const card = document.createElement('div');
            card.className = 'card';
            card.style.animationDelay = i * 0.035 + 's';
            card.innerHTML = `
                <div class="card-img">
                    <img src="${p.immagine || imgPlaceholder}" alt="${p.nome}" loading="lazy" onerror="imgFallback(this)">
                    <span class="cond-dot ${dotClass(p.condizioni)}"></span>
                </div>
                <div class="card-body">
                    <div class="card-name">${p.nome}</div>
                    <div class="card-price">€${parseFloat(p.prezzo).toLocaleString('it-IT')} <small>iva incl.</small></div>
                    <div class="card-pills">
                        <span class="pill">${p.colore}</span>
                        <span class="pill ${batPillClass(p.batteria)}">bat. ${p.batteria.toLowerCase()}</span>
                        <span class="pill">${p.condizioni.toLowerCase()}</span>
                    </div>
                    <a href="prodotto.html?id=${p.id}" class="card-link">Vedi scheda</a>
                </div>`;
            grid.appendChild(card);
        });
    }

    inp.addEventListener('input', render);
    fCol.addEventListener('change', render);
    fBat.addEventListener('change', render);
    fCon.addEventListener('change', render);

    btnA.addEventListener('click', () => {
        sort = sort === 'asc' ? null : 'asc';
        btnA.classList.toggle('on', sort === 'asc');
        btnD.classList.remove('on');
        render();
    });
    btnD.addEventListener('click', () => {
        sort = sort === 'desc' ? null : 'desc';
        btnD.classList.toggle('on', sort === 'desc');
        btnA.classList.remove('on');
        render();
    });
    btnR.addEventListener('click', () => {
        inp.value = ''; fCol.value = ''; fBat.value = ''; fCon.value = '';
        sort = null;
        btnA.classList.remove('on'); btnD.classList.remove('on');
        render();
    });

    await render();

    // update stat count
    const el = document.getElementById('statCount');
    if (el && cnt) el.textContent = cnt.textContent + '+';
}

// ── PRODOTTO ──
async function initProduct() {
    const id  = parseInt(new URLSearchParams(location.search).get('id'));
    const box = document.getElementById('pdBox');
    if (!box || !id) return;

    box.innerHTML = '<div class="empty" style="padding:60px"><strong>Caricamento...</strong></div>';

    let p;
    try {
        p = await apiFetch('/products.php?id=' + id, { method: 'GET' });
    } catch (e) {
        box.innerHTML = `<div class="empty" style="padding:60px"><strong>Prodotto non trovato</strong><a href="index.html" style="color:var(--blue);font-size:13px">← torna al catalogo</a></div>`;
        return;
    }

    document.title = p.nome + ' — MyDevice';

    let imgs = Array.isArray(p.immagini) ? p.immagini.filter(Boolean) : [];
    if (!imgs.length && p.immagine) imgs = [p.immagine];
    if (!imgs.length) imgs = [imgPlaceholder];

    const thumbs = imgs.length > 1
        ? `<div class="pd-thumbs">${imgs.map((u, i) =>
            `<button class="pd-thumb${i === 0 ? ' active' : ''}" data-i="${i}"><img src="${u}" alt="" onerror="imgFallback(this)"></button>`).join('')}</div>`
        : '';
    const arrows = imgs.length > 1
        ? `<button class="pd-arrow prev" id="pdPrev" aria-label="precedente">‹</button>
           <button class="pd-arrow next" id="pdNext" aria-label="successiva">›</button>`
        : '';

    box.innerHTML = `
        <button class="back" onclick="history.back()">← catalogo</button>
        <div class="pd-grid">
            <div class="pd-gallery">
                <div class="pd-main">
                    ${arrows}
                    <img id="pdMainImg" src="${imgs[0]}" alt="${p.nome}" onerror="imgFallback(this)">
                </div>
                ${thumbs}
            </div>
            <div class="pd-info">
                <h1>${p.nome}</h1>
                <div class="pd-price">€${parseFloat(p.prezzo).toLocaleString('it-IT')} <small>iva inclusa</small></div>
                <p class="pd-desc">${p.descrizione}</p>
                <div class="pd-specs">
                    <div class="spec"><div class="spec-k">Colore</div><div class="spec-v">${p.colore}</div></div>
                    <div class="spec"><div class="spec-k">Batteria</div><div class="spec-v">${p.batteria}</div></div>
                    <div class="spec"><div class="spec-k">Condizioni</div><div class="spec-v">${p.condizioni}</div></div>
                    <div class="spec"><div class="spec-k">ID</div><div class="spec-v">#${String(p.id).padStart(4,'0')}</div></div>
                </div>
                <button class="btn-buy" id="btnBuy">Acquista — €${parseFloat(p.prezzo).toLocaleString('it-IT')}</button>
            </div>
        </div>`;

    // ── Galleria ──
    if (imgs.length > 1) {
        let cur = 0;
        const mainImg = document.getElementById('pdMainImg');
        const thumbBtns = [...box.querySelectorAll('.pd-thumb')];
        const show = i => {
            cur = (i + imgs.length) % imgs.length;
            mainImg.src = imgs[cur];
            thumbBtns.forEach((b, j) => b.classList.toggle('active', j === cur));
        };
        thumbBtns.forEach(b => b.addEventListener('click', () => show(parseInt(b.dataset.i))));
        document.getElementById('pdPrev')?.addEventListener('click', () => show(cur - 1));
        document.getElementById('pdNext')?.addEventListener('click', () => show(cur + 1));
    }

    document.getElementById('btnBuy').addEventListener('click', () => {
        const m = document.getElementById('modal');
        document.getElementById('mTitle').textContent = p.nome;
        document.getElementById('mMsg').textContent = `Prezzo: €${parseFloat(p.prezzo).toLocaleString('it-IT')}. Contattaci su WhatsApp o email per completare l'acquisto.`;
        m.classList.add('on');
    });
}

// ── CONTATTI ──
// ── LOGIN ──
function initLogin() {
    // Se già loggato, vai ad admin
    if (getToken()) { location.href = 'admin.html'; return; }

    const form = document.getElementById('loginForm');
    const err  = document.getElementById('loginErr');
    if (!form) return;

    form.addEventListener('submit', async e => {
        e.preventDefault();
        err.textContent = '';
        const btn = form.querySelector('button[type=submit]');
        btn.disabled = true;
        btn.textContent = 'Accesso...';

        try {
            const res = await apiFetch('/auth.php', {
                method: 'POST',
                body: JSON.stringify({
                    username: document.getElementById('lUser').value.trim(),
                    password: document.getElementById('lPass').value
                })
            });
            localStorage.setItem('md_admin_token', res.token);
            location.href = 'admin.html';
        } catch (e) {
            err.textContent = e.message;
            btn.disabled = false;
            btn.textContent = 'Accedi';
        }
    });
}

// ── ADMIN ──
async function initAdmin() {
    // Verifica login
    if (!getToken()) { location.href = 'admin-login-page.html'; return; }

    const form      = document.getElementById('aForm');
    const list      = document.getElementById('aList');
    const imgInput  = document.getElementById('aImgFile');
    const imgUrl    = document.getElementById('aImgUrl');
    const imgUrlAdd = document.getElementById('aImgUrlAdd');
    const gallery   = document.getElementById('imgGallery');
    const galWrap   = document.getElementById('imgGalleryWrap');
    const formTitle = document.getElementById('aFormTitle');
    const editIdEl  = document.getElementById('aEditId');
    const btnCancel = document.getElementById('aCancel');
    const btnLogout = document.getElementById('btnLogout');
    const btnAdd    = form.querySelector('.btn-add');

    // Stato immagini (la prima è la copertina)
    let images = [];

    function renderGallery() {
        if (!images.length) { galWrap.style.display = 'none'; gallery.innerHTML = ''; return; }
        galWrap.style.display = 'block';
        gallery.innerHTML = images.map((u, i) => `
            <div class="admin-img${i === 0 ? ' cover' : ''}">
                ${i === 0 ? '<span class="badge">COPERTINA</span>' : ''}
                <img src="${u}" alt="" onerror="imgFallback(this)">
                <div class="ctrls">
                    <button type="button" data-act="left" data-i="${i}" title="sposta a sinistra">‹</button>
                    <button type="button" class="rm" data-act="rm" data-i="${i}" title="rimuovi">✕</button>
                    <button type="button" data-act="right" data-i="${i}" title="sposta a destra">›</button>
                </div>
            </div>`).join('');
    }

    gallery?.addEventListener('click', e => {
        const b = e.target.closest('button[data-act]');
        if (!b) return;
        const i = parseInt(b.dataset.i), act = b.dataset.act;
        if (act === 'rm') images.splice(i, 1);
        else if (act === 'left' && i > 0) { [images[i-1], images[i]] = [images[i], images[i-1]]; }
        else if (act === 'right' && i < images.length - 1) { [images[i+1], images[i]] = [images[i], images[i+1]]; }
        renderGallery();
    });

    // Logout
    btnLogout?.addEventListener('click', async () => {
        try { await apiFetch('/auth.php', { method: 'DELETE' }); } catch {}
        localStorage.removeItem('md_admin_token');
        location.href = 'admin-login-page.html';
    });

    // Upload file multipli
    imgInput?.addEventListener('change', async () => {
        const files = [...imgInput.files];
        if (!files.length) return;

        const fd = new FormData();
        files.forEach(f => fd.append('images[]', f));

        const token = getToken();
        toast('Caricamento immagini...', 'success');
        try {
            const res = await fetch(API + '/upload.php', {
                method: 'POST',
                headers: token ? { 'Authorization': 'Bearer ' + token } : {},
                body: fd
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error);
            const urls = data.urls || (data.url ? [data.url] : []);
            images.push(...urls);
            renderGallery();
            imgInput.value = '';
            toast(urls.length + ' immagine/i caricate.', 'success');
        } catch (e) {
            toast('Upload fallito: ' + e.message, 'error');
        }
    });

    // Aggiungi per URL
    imgUrlAdd?.addEventListener('click', () => {
        const u = imgUrl.value.trim();
        if (!u) return;
        images.push(u);
        imgUrl.value = '';
        renderGallery();
    });

    function resetForm() {
        form.reset();
        images = [];
        renderGallery();
        editIdEl.value = '';
        formTitle.textContent = 'Aggiungi prodotto';
        btnAdd.textContent = 'Aggiungi';
        btnCancel.style.display = 'none';
    }

    btnCancel?.addEventListener('click', resetForm);

    async function startEdit(p) {
        editIdEl.value = p.id;
        document.getElementById('aNome').value   = p.nome || '';
        document.getElementById('aPrezzo').value = p.prezzo || '';
        document.getElementById('aCol').value    = p.colore || '';
        document.getElementById('aBat').value    = p.batteria || '';
        document.getElementById('aCon').value    = p.condizioni || '';
        document.getElementById('aDesc').value   = p.descrizione || '';
        images = Array.isArray(p.immagini) ? p.immagini.filter(Boolean) : (p.immagine ? [p.immagine] : []);
        renderGallery();
        formTitle.textContent = 'Modifica prodotto';
        btnAdd.textContent = 'Salva modifiche';
        btnCancel.style.display = 'block';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    async function renderList() {
        list.innerHTML = '<div class="admin-empty">Caricamento...</div>';
        let prods;
        try { prods = await apiFetch('/products.php', { method: 'GET' }); }
        catch (e) { list.innerHTML = '<div class="admin-empty">Errore: ' + e.message + '</div>'; return; }

        list.innerHTML = '';
        if (!prods.length) { list.innerHTML = '<div class="admin-empty">Nessun prodotto.</div>'; return; }

        prods.forEach((p, i) => {
            const row = document.createElement('div');
            row.className = 'admin-row';
            row.style.animationDelay = i * 0.025 + 's';
            const n = Array.isArray(p.immagini) ? p.immagini.length : (p.immagine ? 1 : 0);
            row.innerHTML = `
                <img class="admin-row-img" src="${p.immagine || imgPlaceholder}" alt="${p.nome}" onerror="imgFallback(this)">
                <div class="admin-row-info">
                    <div class="admin-row-name">${p.nome}</div>
                    <div class="admin-row-meta">${p.colore} · bat. ${p.batteria} · ${p.condizioni}${n > 1 ? ' · ' + n + ' foto' : ''}</div>
                </div>
                <div class="admin-row-price">€${parseFloat(p.prezzo).toLocaleString('it-IT')}</div>
                <button class="btn-edit" data-id="${p.id}">modifica</button>
                <button class="btn-del" data-id="${p.id}">elimina</button>`;
            list.appendChild(row);
            row.querySelector('.btn-edit').addEventListener('click', () => startEdit(p));
        });

        list.querySelectorAll('.btn-del').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('Eliminare questo prodotto?')) return;
                try {
                    await apiFetch('/products.php?id=' + btn.dataset.id, { method: 'DELETE' });
                    toast('Prodotto eliminato.', 'success');
                    if (editIdEl.value == btn.dataset.id) resetForm();
                    renderList();
                } catch (e) {
                    toast('Errore: ' + e.message, 'error');
                }
            });
        });
    }

    form.addEventListener('submit', async e => {
        e.preventDefault();

        const nome        = document.getElementById('aNome').value.trim();
        const prezzo      = parseFloat(document.getElementById('aPrezzo').value);
        const colore      = document.getElementById('aCol').value;
        const batteria    = document.getElementById('aBat').value;
        const condizioni  = document.getElementById('aCon').value;
        const descrizione = document.getElementById('aDesc').value.trim();
        const editId      = editIdEl.value;

        if (!nome || !prezzo || !colore || !batteria || !condizioni) {
            toast('Compila tutti i campi obbligatori.', 'error');
            return;
        }

        btnAdd.disabled = true;
        const wasEdit = !!editId;
        btnAdd.textContent = 'Salvataggio...';

        const payload = { nome, prezzo, colore, batteria, condizioni, descrizione, immagini: images, immagine: images[0] || '' };

        try {
            if (wasEdit) {
                await apiFetch('/products.php?id=' + editId, { method: 'PATCH', body: JSON.stringify(payload) });
                toast(nome + ' aggiornato.', 'success');
            } else {
                await apiFetch('/products.php', { method: 'POST', body: JSON.stringify(payload) });
                toast(nome + ' aggiunto.', 'success');
            }
            resetForm();
            renderList();
        } catch (e) {
            toast('Errore: ' + e.message, 'error');
        } finally {
            btnAdd.disabled = false;
            btnAdd.textContent = wasEdit ? 'Salva modifiche' : 'Aggiungi';
        }
    });

    // ── Cambia password (modale) ──
    const pwForm = document.getElementById('pwForm');
    if (pwForm) {
        const pwErr    = document.getElementById('pwErr');
        const pwSubmit = document.getElementById('pwSubmit');
        const pwModal  = document.getElementById('pwModal');
        const btnPw    = document.getElementById('btnChangePw');
        const btnCancelPw = document.getElementById('pwCancel');
        const showErr  = msg => { pwErr.textContent = msg; pwErr.style.display = 'block'; };

        const openPw  = () => { pwForm.reset(); pwErr.style.display = 'none'; pwModal.classList.add('on'); document.getElementById('pwCurrent').focus(); };
        const closePw = () => pwModal.classList.remove('on');

        btnPw?.addEventListener('click', openPw);
        btnCancelPw?.addEventListener('click', closePw);
        pwModal?.addEventListener('click', e => { if (e.target === pwModal) closePw(); });

        pwForm.addEventListener('submit', async e => {
            e.preventDefault();
            pwErr.style.display = 'none';

            const current = document.getElementById('pwCurrent').value;
            const next    = document.getElementById('pwNext').value;
            const confirm = document.getElementById('pwConfirm').value;

            if (next !== confirm) { showErr('La nuova password e la conferma non coincidono.'); return; }
            if (next.length < 6)  { showErr('La nuova password deve avere almeno 6 caratteri.'); return; }

            pwSubmit.disabled = true;
            pwSubmit.textContent = 'Aggiornamento...';
            try {
                await apiFetch('/change-password.php', {
                    method: 'POST',
                    body: JSON.stringify({ current, next, confirm })
                });
                toast('Password aggiornata. Usala al prossimo accesso.', 'success');
                pwForm.reset();
                closePw();
            } catch (err) {
                showErr(err.message);
            } finally {
                pwSubmit.disabled = false;
                pwSubmit.textContent = 'Aggiorna password';
            }
        });
    }

    await renderList();
}

// ── MODAL ──
function initModal() {
    const m = document.getElementById('modal');
    if (!m) return;
    document.getElementById('mClose')?.addEventListener('click', () => m.classList.remove('on'));
    document.getElementById('mContact')?.addEventListener('click', () => { m.classList.remove('on'); location.href = 'contatti.html'; });
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('on'); });
}

// ── BOOT ──
document.addEventListener('DOMContentLoaded', () => {
    initNav();
    initModal();
    const p = document.body.dataset.page;
    if (p === 'index')    initIndex();
    if (p === 'prodotto') initProduct();
    if (p === 'login')    initLogin();
    if (p === 'admin')    initAdmin();
});
