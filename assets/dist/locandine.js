
/* FV Workshop — Locandine Admin (Vanilla JS, wv-dash style) */
(function () {
    'use strict';

    function restUrl(path) {
        return ((window.WSMA_CONFIG && window.WSMA_CONFIG.restUrl) || (window.WSMA_CONFIG && window.WSMA_CONFIG.restUrl) || '/wp-json/workshop-suite/v1/') + path;
    }
    function headers(extra) {
        return Object.assign({ 'X-WP-Nonce': (window.WSMA_CONFIG && window.WSMA_CONFIG.nonce) || (window.WSMA_CONFIG && window.WSMA_CONFIG.nonce) || '' }, extra || {});
    }

    var FORMATS = [
        { value: 'sq',    label: 'Quadrato \u2014 1080\u00D71080 (Instagram / Feed)', w: 1080, h: 1080 },
        { value: 'fb',    label: 'Post Facebook \u2014 1200\u00D7628',                w: 1200, h: 628  },
        { value: 'story', label: 'Storia IG / Reels \u2014 1080\u00D71920',           w: 1080, h: 1920 }
    ];

    var FONTS = [
        { value: '"Bebas Neue", sans-serif',      label: 'Bebas Neue' },
        { value: '"Special Elite", monospace',    label: 'Special Elite' },
        { value: 'Poppins, sans-serif',           label: 'Poppins' },
        { value: 'Antonio, sans-serif',           label: 'Antonio' },
        { value: 'Montserrat, Arial, sans-serif', label: 'Montserrat (Modern)' },
        { value: '"Courier New", monospace',      label: 'Courier New (Typewriter)' },
        { value: 'Arial, sans-serif',             label: 'Arial (Clean)' },
        { value: 'Georgia, serif',                label: 'Georgia (Classic Editorial)' },
        { value: 'Impact, sans-serif',            label: 'Impact (Bold Poster)' },
        { value: '"Trebuchet MS", sans-serif',    label: 'Trebuchet MS' }
    ];

    var S = {
        format: 'sq',
        
        // 1. Brand / Intestazione
        brand: 'FRANCESCOVEROLINO',
        brandFont: '"Bebas Neue", sans-serif',
        brandFontSize: 28,
        brandColor: '#ffffff',
        brandX: 60,
        brandY: 75,

        // 2. Categoria / Titolo Principale
        title: 'FIRENZE STREET',
        titleFont: '"Special Elite", monospace',
        titleFontSize: null,
        titleColor: '#E11D48',
        titleX: null,
        titleY: null,

        // 3. Tipologia / Sottotitolo
        subtitle: 'Talk & Masterclass',
        subtitleFont: '"Courier New", monospace',
        subtitleFontSize: null,
        subtitleColor: '#ffffff',
        subtitleX: null,
        subtitleY: null,

        // 4. Data / Periodo
        dates: "16 - 18 OTT'26",
        datesFont: '"Courier New", monospace',
        datesFontSize: null,
        datesColor: '#ffffff',
        datesX: null,
        datesY: null,

        // 5. Descrizione / Programma
        description: '3 GIORNATE DI STREET PHOTOGRAPHY...',
        descFont: 'Arial, sans-serif',
        descFontSize: null,
        descColor: 'rgba(255,255,255,0.85)',
        descX: null,
        descY: null,

        // Fotografia di sfondo unica
        imageUrl: '',
        imgScale: 1.0,      // Zoom (1.0 = 100%)
        imgOffsetX: 0,     // Posizione X
        imgOffsetY: 0,     // Posizione Y
        darkOverlay: 0.5,  // Opacità overlay scuro

        modelName: '', currentModelId: null,
        eventsList: [], savedModels: [],
        activeModal: null,
    };

    /* Performance optimization: Image Cache to prevent redundant downloads & decoding */
    var imgCache = {};
    function getCachedImage(src, callback) {
        if (!src) { callback(null); return; }
        if (imgCache[src]) {
            if (imgCache[src].complete && imgCache[src].naturalWidth !== 0) callback(imgCache[src]);
            else imgCache[src].addEventListener('load', function() { callback(imgCache[src]); }, { once: true });
            return;
        }
        var im = new Image();
        im.crossOrigin = 'anonymous';
        im.onload = function () { imgCache[src] = im; callback(im); };
        im.onerror = function () { callback(null); };
        im.src = src;
    }

    function formatImgLabel(url) {
        if (!url) return 'Nessuna immagine';
        if (url.startsWith('data:')) return 'Immagine caricata (File locale)';
        var clean = url.split('?')[0];
        var parts = clean.split('/');
        var fn = parts.pop();
        if (fn && fn.length > 0 && fn.length < 35 && fn.indexOf('.') !== -1) return fn;
        return 'Immagine di sfondo selezionata';
    }

    /* ───────── notify ───────── */
    function notify(text, err) {
        var n = document.getElementById('fvw-loc-msg');
        if (!n) return;
        n.textContent = text;
        n.style.borderLeftColor = err ? '#e11d48' : '#10b981';
        n.style.display = 'block';
        setTimeout(function () { if (n.textContent === text) n.style.display = 'none'; }, 4000);
    }

    /* ───────── canvas throttling ───────── */
    var renderPending = false;
    function requestRenderCanvas() {
        if (renderPending) return;
        renderPending = true;
        window.requestAnimationFrame(function () {
            renderPending = false;
            renderCanvas();
        });
    }

    function fmt() { return FORMATS.find(function (f) { return f.value === S.format; }) || FORMATS[0]; }

    function renderCanvas() {
        var cv = document.getElementById('fvw-loc-canvas'); if (!cv) return;
        var ctx = cv.getContext('2d'), f = fmt();
        cv.width = f.w; cv.height = f.h;
        cv.style.width  = Math.min(400, f.w) + 'px';
        cv.style.height = 'auto';

        function text() {
            ctx.fillStyle = 'rgba(0,0,0,' + S.darkOverlay + ')';
            ctx.fillRect(0, 0, f.w, f.h);

            /* 1. Brand / Intestazione */
            ctx.fillStyle = S.brandColor || '#ffffff';
            var bSize = S.brandFontSize || 28;
            var bFamily = S.brandFont || '"Bebas Neue", sans-serif';
            ctx.font = '600 ' + bSize + 'px ' + bFamily;
            ctx.textAlign = 'left';
            var bX = (S.brandX !== undefined && S.brandX !== null) ? +S.brandX : 60;
            var bY = (S.brandY !== undefined && S.brandY !== null) ? +S.brandY : 75;
            ctx.fillText((S.brand || 'FRANCESCOVEROLINO').toUpperCase(), bX, bY);

            var cx = f.w / 2;

            /* 2. Categoria / Titolo Principale */
            ctx.fillStyle = S.titleColor || '#E11D48';
            var tFamily = S.titleFont || '"Special Elite", monospace';

            /* 3. Tipologia / Sottotitolo */
            var subFamily = S.subtitleFont || '"Courier New", monospace';
            var subColor = S.subtitleColor || '#ffffff';

            /* 4. Data / Periodo */
            var dFamily = S.datesFont || '"Courier New", monospace';
            var dColor = S.datesColor || '#ffffff';

            /* 5. Descrizione / Programma */
            var descFamily = S.descFont || 'Arial, sans-serif';
            var descColor = S.descColor || 'rgba(255,255,255,0.85)';

            if (S.format === 'fb') {
                // Titolo FB
                var tSize = S.titleFontSize || 60;
                ctx.font = 'bold ' + tSize + 'px ' + tFamily;
                var tX = (S.titleX !== null && S.titleX !== undefined) ? +S.titleX : 60;
                var tY = (S.titleY !== null && S.titleY !== undefined) ? +S.titleY : 240;
                ctx.textAlign = 'left';
                ctx.fillText(S.title || 'CATEGORIA EVENTO', tX, tY);

                // Sottotitolo FB
                ctx.fillStyle = subColor;
                var subSize = S.subtitleFontSize || 28;
                ctx.font = '600 ' + subSize + 'px ' + subFamily;
                var subX = (S.subtitleX !== null && S.subtitleX !== undefined) ? +S.subtitleX : 60;
                var subY = (S.subtitleY !== null && S.subtitleY !== undefined) ? +S.subtitleY : 290;
                ctx.textAlign = 'left';
                ctx.fillText(S.subtitle || 'Sottotitolo', subX, subY);

                // Date FB
                ctx.fillStyle = dColor;
                var dSize = S.datesFontSize || 30;
                ctx.font = 'bold ' + dSize + 'px ' + dFamily;
                var dX = (S.datesX !== null && S.datesX !== undefined) ? +S.datesX : 60;
                var dY = (S.datesY !== null && S.datesY !== undefined) ? +S.datesY : 340;
                ctx.textAlign = 'left';
                ctx.fillText(S.dates || "16 - 18 OTT'26", dX, dY);

                // Descrizione FB
                ctx.fillStyle = descColor;
                var descSize = S.descFontSize || 17;
                ctx.font = '400 ' + descSize + 'px ' + descFamily;
                var descX = (S.descX !== null && S.descX !== undefined) ? +S.descX : 60;
                var descY = (S.descY !== null && S.descY !== undefined) ? +S.descY : 400;
                ctx.textAlign = (S.descX !== null) ? 'left' : 'left';
                var lines = (S.description || '').split('\n');
                var lh = descSize * 1.4;
                lines.forEach(function (l, i) { ctx.fillText(l, descX, descY + i * lh); });

            } else if (S.format === 'story') {
                // Titolo Story
                var tSize = S.titleFontSize || 80;
                ctx.font = 'bold ' + tSize + 'px ' + tFamily;
                var tX = (S.titleX !== null && S.titleX !== undefined) ? +S.titleX : cx;
                var tY = (S.titleY !== null && S.titleY !== undefined) ? +S.titleY : 1150;
                ctx.textAlign = (S.titleX !== null) ? 'left' : 'center';
                ctx.fillText(S.title || 'CATEGORIA EVENTO', tX, tY);

                // Sottotitolo Story
                ctx.fillStyle = subColor;
                var subSize = S.subtitleFontSize || 38;
                ctx.font = '600 ' + subSize + 'px ' + subFamily;
                var subX = (S.subtitleX !== null && S.subtitleX !== undefined) ? +S.subtitleX : cx;
                var subY = (S.subtitleY !== null && S.subtitleY !== undefined) ? +S.subtitleY : 1220;
                ctx.textAlign = (S.subtitleX !== null) ? 'left' : 'center';
                ctx.fillText(S.subtitle || 'Sottotitolo', subX, subY);

                // Date Story
                ctx.fillStyle = dColor;
                var dSize = S.datesFontSize || 40;
                ctx.font = 'bold ' + dSize + 'px ' + dFamily;
                var dX = (S.datesX !== null && S.datesX !== undefined) ? +S.datesX : cx;
                var dY = (S.datesY !== null && S.datesY !== undefined) ? +S.datesY : 1300;
                ctx.textAlign = (S.datesX !== null) ? 'left' : 'center';
                ctx.fillText(S.dates || "16 - 18 OTT'26", dX, dY);

                // Descrizione Story
                ctx.fillStyle = descColor;
                var descSize = S.descFontSize || 24;
                ctx.font = '400 ' + descSize + 'px ' + descFamily;
                var descX = (S.descX !== null && S.descX !== undefined) ? +S.descX : cx;
                var descY = (S.descY !== null && S.descY !== undefined) ? +S.descY : 1390;
                ctx.textAlign = (S.descX !== null) ? 'left' : 'center';
                var lines = (S.description || '').split('\n');
                var lh = descSize * 1.4;
                lines.forEach(function (l, i) { ctx.fillText(l, descX, descY + i * lh); });

            } else {
                // Titolo Sq
                var tSize = S.titleFontSize || 72;
                ctx.font = 'bold ' + tSize + 'px ' + tFamily;
                var tX = (S.titleX !== null && S.titleX !== undefined) ? +S.titleX : cx;
                var tY = (S.titleY !== null && S.titleY !== undefined) ? +S.titleY : 545;
                ctx.textAlign = (S.titleX !== null) ? 'left' : 'center';
                ctx.fillText(S.title || 'CATEGORIA EVENTO', tX, tY);

                // Sottotitolo Sq
                ctx.fillStyle = subColor;
                var subSize = S.subtitleFontSize || 34;
                ctx.font = '600 ' + subSize + 'px ' + subFamily;
                var subX = (S.subtitleX !== null && S.subtitleX !== undefined) ? +S.subtitleX : cx;
                var subY = (S.subtitleY !== null && S.subtitleY !== undefined) ? +S.subtitleY : 605;
                ctx.textAlign = (S.subtitleX !== null) ? 'left' : 'center';
                ctx.fillText(S.subtitle || 'Sottotitolo', subX, subY);

                // Date Sq
                ctx.fillStyle = dColor;
                var dSize = S.datesFontSize || 36;
                ctx.font = 'bold ' + dSize + 'px ' + dFamily;
                var dX = (S.datesX !== null && S.datesX !== undefined) ? +S.datesX : cx;
                var dY = (S.datesY !== null && S.datesY !== undefined) ? +S.datesY : 665;
                ctx.textAlign = (S.datesX !== null) ? 'left' : 'center';
                ctx.fillText(S.dates || "16 - 18 OTT'26", dX, dY);

                // Descrizione Sq
                ctx.fillStyle = descColor;
                var descSize = S.descFontSize || 22;
                ctx.font = '400 ' + descSize + 'px ' + descFamily;
                var descX = (S.descX !== null && S.descX !== undefined) ? +S.descX : cx;
                var descY = (S.descY !== null && S.descY !== undefined) ? +S.descY : 740;
                ctx.textAlign = (S.descX !== null) ? 'left' : 'center';
                var lines = (S.description || '').split('\n');
                var lh = descSize * 1.4;
                lines.forEach(function (l, i) { ctx.fillText(l, descX, descY + i * lh); });
            }
        }

        ctx.fillStyle = '#0f172a'; ctx.fillRect(0, 0, f.w, f.h);
        var imgUrl = S.imageUrl || '';
        if (imgUrl) {
            getCachedImage(imgUrl, function (im) {
                if (im) {
                    var asp = im.width / im.height, dW = f.w, dH = f.w / asp;
                    if (dH < f.h) { dH = f.h; dW = f.h * asp; }

                    var scale = S.imgScale || 1.0;
                    var finalW = dW * scale;
                    var finalH = dH * scale;
                    var posX = (f.w - finalW) / 2 + (+S.imgOffsetX || 0);
                    var posY = (f.h - finalH) / 2 + (+S.imgOffsetY || 0);

                    ctx.drawImage(im, posX, posY, finalW, finalH);
                }
                text();
            });
        } else { text(); }
    }

    /* Mini canvas renderer for saved models thumbnail in Column 3 (Optimized with image cache) */
    function renderMiniCanvas(cv, m, targetW) {
        if (!cv) return;
        targetW = targetW || 110;
        var f = FORMATS.find(function (x) { return x.value === (m.format || 'sq'); }) || FORMATS[0];
        var ctx = cv.getContext('2d');
        cv.width = f.w; cv.height = f.h;
        cv.style.width = targetW + 'px';
        cv.style.height = 'auto';
        cv.style.display = 'block';
        cv.style.borderRadius = '3px';

        function text() {
            ctx.fillStyle = 'rgba(0,0,0,' + (m.darkOverlay !== undefined ? m.darkOverlay : 0.5) + ')';
            ctx.fillRect(0, 0, f.w, f.h);

            ctx.fillStyle = m.brandColor || '#ffffff';
            var bSize = m.brandFontSize || 28;
            var bFamily = m.brandFont || '"Bebas Neue", sans-serif';
            ctx.font = '600 ' + bSize + 'px ' + bFamily;
            ctx.textAlign = 'left';
            var bX = (m.brandX !== undefined && m.brandX !== null) ? +m.brandX : 60;
            var bY = (m.brandY !== undefined && m.brandY !== null) ? +m.brandY : 75;
            ctx.fillText((m.brand || 'FRANCESCOVEROLINO').toUpperCase(), bX, bY);

            var cx = f.w / 2;
            var tFamily = m.titleFont || '"Special Elite", monospace';
            var subFamily = m.subtitleFont || '"Courier New", monospace';
            var subColor = m.subtitleColor || '#ffffff';
            var dFamily = m.datesFont || '"Courier New", monospace';
            var dColor = m.datesColor || '#ffffff';
            var descFamily = m.descFont || 'Arial, sans-serif';
            var descColor = m.descColor || 'rgba(255,255,255,0.85)';

            if (m.format === 'fb') {
                ctx.fillStyle = m.titleColor || '#E11D48';
                var tSize = m.titleFontSize || 60;
                ctx.font = 'bold ' + tSize + 'px ' + tFamily;
                ctx.textAlign = 'left';
                ctx.fillText(m.title || 'CATEGORIA EVENTO', (m.titleX !== null && m.titleX !== undefined) ? +m.titleX : 60, (m.titleY !== null && m.titleY !== undefined) ? +m.titleY : 240);

                ctx.fillStyle = subColor;
                var subSize = m.subtitleFontSize || 28;
                ctx.font = '600 ' + subSize + 'px ' + subFamily;
                ctx.fillText(m.subtitle || 'Sottotitolo', (m.subtitleX !== null && m.subtitleX !== undefined) ? +m.subtitleX : 60, (m.subtitleY !== null && m.subtitleY !== undefined) ? +m.subtitleY : 290);

                ctx.fillStyle = dColor;
                var dSize = m.datesFontSize || 30;
                ctx.font = 'bold ' + dSize + 'px ' + dFamily;
                ctx.fillText(m.dates || "16 - 18 OTT'26", (m.datesX !== null && m.datesX !== undefined) ? +m.datesX : 60, (m.datesY !== null && m.datesY !== undefined) ? +m.datesY : 340);

                ctx.fillStyle = descColor;
                var descSize = m.descFontSize || 17;
                ctx.font = '400 ' + descSize + 'px ' + descFamily;
                var lines = (m.description || '').split('\n');
                var lh = descSize * 1.4;
                var descX = (m.descX !== null && m.descX !== undefined) ? +m.descX : 60;
                var descY = (m.descY !== null && m.descY !== undefined) ? +m.descY : 400;
                lines.forEach(function (l, i) { ctx.fillText(l, descX, descY + i * lh); });
            } else if (m.format === 'story') {
                ctx.fillStyle = m.titleColor || '#E11D48';
                var tSize = m.titleFontSize || 80;
                ctx.font = 'bold ' + tSize + 'px ' + tFamily;
                ctx.textAlign = (m.titleX !== null) ? 'left' : 'center';
                ctx.fillText(m.title || 'CATEGORIA EVENTO', (m.titleX !== null && m.titleX !== undefined) ? +m.titleX : cx, (m.titleY !== null && m.titleY !== undefined) ? +m.titleY : 1150);

                ctx.fillStyle = subColor;
                var subSize = m.subtitleFontSize || 38;
                ctx.font = '600 ' + subSize + 'px ' + subFamily;
                ctx.textAlign = (m.subtitleX !== null) ? 'left' : 'center';
                ctx.fillText(m.subtitle || 'Sottotitolo', (m.subtitleX !== null && m.subtitleX !== undefined) ? +m.subtitleX : cx, (m.subtitleY !== null && m.subtitleY !== undefined) ? +m.subtitleY : 1220);

                ctx.fillStyle = dColor;
                var dSize = m.datesFontSize || 40;
                ctx.font = 'bold ' + dSize + 'px ' + dFamily;
                ctx.textAlign = (m.datesX !== null) ? 'left' : 'center';
                ctx.fillText(m.dates || "16 - 18 OTT'26", (m.datesX !== null && m.datesX !== undefined) ? +m.datesX : cx, (m.datesY !== null && m.datesY !== undefined) ? +m.datesY : 1300);

                ctx.fillStyle = descColor;
                var descSize = m.descFontSize || 24;
                ctx.font = '400 ' + descSize + 'px ' + descFamily;
                ctx.textAlign = (m.descX !== null) ? 'left' : 'center';
                var lines = (m.description || '').split('\n');
                var lh = descSize * 1.4;
                var descX = (m.descX !== null && m.descX !== undefined) ? +m.descX : cx;
                var descY = (m.descY !== null && m.descY !== undefined) ? +m.descY : 1390;
                lines.forEach(function (l, i) { ctx.fillText(l, descX, descY + i * lh); });
            } else {
                ctx.fillStyle = m.titleColor || '#E11D48';
                var tSize = m.titleFontSize || 72;
                ctx.font = 'bold ' + tSize + 'px ' + tFamily;
                ctx.textAlign = (m.titleX !== null) ? 'left' : 'center';
                ctx.fillText(m.title || 'CATEGORIA EVENTO', (m.titleX !== null && m.titleX !== undefined) ? +m.titleX : cx, (m.titleY !== null && m.titleY !== undefined) ? +m.titleY : 545);

                ctx.fillStyle = subColor;
                var subSize = m.subtitleFontSize || 34;
                ctx.font = '600 ' + subSize + 'px ' + subFamily;
                ctx.textAlign = (m.subtitleX !== null) ? 'left' : 'center';
                ctx.fillText(m.subtitle || 'Sottotitolo', (m.subtitleX !== null && m.subtitleX !== undefined) ? +m.subtitleX : cx, (m.subtitleY !== null && m.subtitleY !== undefined) ? +m.subtitleY : 605);

                ctx.fillStyle = dColor;
                var dSize = m.datesFontSize || 36;
                ctx.font = 'bold ' + dSize + 'px ' + dFamily;
                ctx.textAlign = (m.datesX !== null) ? 'left' : 'center';
                ctx.fillText(m.dates || "16 - 18 OTT'26", (m.datesX !== null && m.datesX !== undefined) ? +m.datesX : cx, (m.datesY !== null && m.datesY !== undefined) ? +m.datesY : 665);

                ctx.fillStyle = descColor;
                var descSize = m.descFontSize || 22;
                ctx.font = '400 ' + descSize + 'px ' + descFamily;
                ctx.textAlign = (m.descX !== null) ? 'left' : 'center';
                var lines = (m.description || '').split('\n');
                var lh = descSize * 1.4;
                var descX = (m.descX !== null && m.descX !== undefined) ? +m.descX : cx;
                var descY = (m.descY !== null && m.descY !== undefined) ? +m.descY : 740;
                lines.forEach(function (l, i) { ctx.fillText(l, descX, descY + i * lh); });
            }
        }

        ctx.fillStyle = '#0f172a'; ctx.fillRect(0, 0, f.w, f.h);
        var imgUrl = m.imageUrl || m.imageUrl1 || '';
        if (imgUrl) {
            getCachedImage(imgUrl, function (im) {
                if (im) {
                    var asp = im.width / im.height, dW = f.w, dH = f.w / asp;
                    if (dH < f.h) { dH = f.h; dW = f.h * asp; }

                    var scale = m.imgScale || 1.0;
                    var finalW = dW * scale;
                    var finalH = dH * scale;
                    var posX = (f.w - finalW) / 2 + (+m.imgOffsetX || 0);
                    var posY = (f.h - finalH) / 2 + (+m.imgOffsetY || 0);

                    ctx.drawImage(im, posX, posY, finalW, finalH);
                }
                text();
            });
        } else { text(); }
    }

    /* ───────── REST ───────── */
    async function loadEvents() {
        try {
            var r = await fetch(restUrl('admin/eventi-tab'), { headers: headers() });
            if (r.ok) { var d = await r.json(); S.eventsList = d.eventi_options || d.eventi || []; refreshEvtSel(); }
        } catch (e) { console.warn(e); }
    }
    async function loadModels() {
        try {
            var r = await fetch(restUrl('admin/locandine-modelli'), { headers: headers() });
            if (r.ok) { var d = await r.json(); S.savedModels = d.modelli || []; renderModels(); }
        } catch (e) { console.warn(e); }
    }
    async function saveModel() {
        if (!S.modelName.trim()) { notify('Inserisci un nome modello.', true); return; }
        var p = { id: S.currentModelId, nome: S.modelName.trim(), format: S.format,
            brand: S.brand, brandFont: S.brandFont, brandFontSize: S.brandFontSize, brandColor: S.brandColor, brandX: S.brandX, brandY: S.brandY,
            title: S.title, titleFont: S.titleFont, titleFontSize: S.titleFontSize, titleColor: S.titleColor, titleX: S.titleX, titleY: S.titleY,
            subtitle: S.subtitle, subtitleFont: S.subtitleFont, subtitleFontSize: S.subtitleFontSize, subtitleColor: S.subtitleColor, subtitleX: S.subtitleX, subtitleY: S.subtitleY,
            dates: S.dates, datesFont: S.datesFont, datesFontSize: S.datesFontSize, datesColor: S.datesColor, datesX: S.datesX, datesY: S.datesY,
            description: S.description, descFont: S.descFont, descFontSize: S.descFontSize, descColor: S.descColor, descX: S.descX, descY: S.descY,
            imageUrl: S.imageUrl, imgScale: S.imgScale, imgOffsetX: S.imgOffsetX, imgOffsetY: S.imgOffsetY, darkOverlay: S.darkOverlay };
        try {
            var r = await fetch(restUrl('admin/locandine-modelli'),
                { method: 'POST', headers: headers({ 'Content-Type': 'application/json' }), body: JSON.stringify(p) });
            var d = await r.json();
            if (r.ok) { S.savedModels = d.modelli || []; renderModels(); notify(d.msg || 'Modello salvato.'); }
            else notify(d.message || 'Errore.', true);
        } catch (e) { notify('Errore connessione.', true); }
    }
    async function deleteModel(id) {
        if (!confirm('Eliminare questo modello?')) return;
        try {
            var r = await fetch(restUrl('admin/locandine-modelli/' + id), { method: 'DELETE', headers: headers() });
            var d = await r.json();
            if (r.ok) { S.savedModels = d.modelli || []; if (S.currentModelId === id) reset(); renderModels(); notify('Eliminato.'); }
        } catch (e) { console.warn(e); }
    }

    /* ───────── media ───────── */
    function mediaPickerSingle() {
        if (window.wp && window.wp.media) {
            var fr = window.wp.media({ title: 'Seleziona immagine di sfondo', button: { text: 'Usa questa immagine' }, multiple: false });
            fr.on('select', function () {
                var a = fr.state().get('selection').first().toJSON();
                if (a && a.url) {
                    S.imageUrl = a.url;
                    var inp = document.getElementById('fvw-img-single'); if (inp) inp.value = a.url;
                    updateAllDisplayVals();
                    requestRenderCanvas();
                }
            });
            fr.open();
        } else notify('WP Media non disponibile.', true);
    }

    /* ───────── load / reset ───────── */
    function applyModel(m) {
        S.currentModelId = m.id; S.modelName = m.nome; S.format = m.format || 'sq';
        S.brand = m.brand || 'FRANCESCOVEROLINO';
        S.brandFont = m.brandFont || '"Bebas Neue", sans-serif';
        S.brandFontSize = m.brandFontSize || 28;
        S.brandColor = m.brandColor || '#ffffff';
        S.brandX = (m.brandX !== undefined) ? m.brandX : 60;
        S.brandY = (m.brandY !== undefined) ? m.brandY : 75;

        S.title = m.title || 'FIRENZE STREET';
        S.titleFont = m.titleFont || '"Special Elite", monospace';
        S.titleFontSize = m.titleFontSize || null;
        S.titleColor = m.titleColor || '#E11D48';
        S.titleX = (m.titleX !== undefined) ? m.titleX : null;
        S.titleY = (m.titleY !== undefined) ? m.titleY : null;

        S.subtitle = m.subtitle || 'Talk & Masterclass';
        S.subtitleFont = m.subtitleFont || '"Courier New", monospace';
        S.subtitleFontSize = m.subtitleFontSize || null;
        S.subtitleColor = m.subtitleColor || '#ffffff';
        S.subtitleX = (m.subtitleX !== undefined) ? m.subtitleX : null;
        S.subtitleY = (m.subtitleY !== undefined) ? m.subtitleY : null;

        S.dates = m.dates || "16 - 18 OTT'26";
        S.datesFont = m.datesFont || '"Courier New", monospace';
        S.datesFontSize = m.datesFontSize || null;
        S.datesColor = m.datesColor || '#ffffff';
        S.datesX = (m.datesX !== undefined) ? m.datesX : null;
        S.datesY = (m.datesY !== undefined) ? m.datesY : null;

        S.description = m.description || '3 GIORNATE DI STREET PHOTOGRAPHY...';
        S.descFont = m.descFont || 'Arial, sans-serif';
        S.descFontSize = m.descFontSize || null;
        S.descColor = m.descColor || 'rgba(255,255,255,0.85)';
        S.descX = (m.descX !== undefined) ? m.descX : null;
        S.descY = (m.descY !== undefined) ? m.descY : null;

        S.imageUrl = m.imageUrl || m.imageUrl1 || '';
        S.imgScale = (m.imgScale !== undefined) ? m.imgScale : 1.0;
        S.imgOffsetX = (m.imgOffsetX !== undefined) ? m.imgOffsetX : 0;
        S.imgOffsetY = (m.imgOffsetY !== undefined) ? m.imgOffsetY : 0;
        S.darkOverlay = (m.darkOverlay !== undefined) ? m.darkOverlay : 0.5;

        rebuildForm(); requestRenderCanvas(); notify('Modello "' + m.nome + '" caricato.');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    function reset() {
        S.currentModelId = null; S.modelName = ''; S.title = 'FIRENZE STREET'; S.subtitle = 'Talk & Masterclass'; S.dates = "16 - 18 OTT'26";
        S.brand = 'FRANCESCOVEROLINO'; S.brandFont = '"Bebas Neue", sans-serif';
        S.brandFontSize = 28; S.brandColor = '#ffffff'; S.brandX = 60; S.brandY = 75;
        S.titleFont = '"Special Elite", monospace'; S.titleFontSize = null; S.titleColor = '#E11D48'; S.titleX = null; S.titleY = null;
        S.subtitleFont = '"Courier New", monospace'; S.subtitleFontSize = null; S.subtitleColor = '#ffffff'; S.subtitleX = null; S.subtitleY = null;
        S.datesFont = '"Courier New", monospace'; S.datesFontSize = null; S.datesColor = '#ffffff'; S.datesX = null; S.datesY = null;

        S.description = '3 GIORNATE DI STREET PHOTOGRAPHY...'; S.descFont = 'Arial, sans-serif'; S.descFontSize = null; S.descColor = 'rgba(255,255,255,0.85)'; S.descX = null; S.descY = null;
        S.imageUrl = ''; S.imgScale = 1.0; S.imgOffsetX = 0; S.imgOffsetY = 0; S.darkOverlay = 0.5;

        rebuildForm(); requestRenderCanvas();
    }
    function downloadPng() {
        var cv = document.getElementById('fvw-loc-canvas'); if (!cv) return;
        var a = document.createElement('a');
        a.download = 'locandina-' + (S.title || S.modelName || 'grafica').toLowerCase().replace(/[^a-z0-9]/g, '-') + '-' + S.format + '.png';
        a.href = cv.toDataURL('image/png'); a.click();
    }

    /* ───────── modal drawer helpers ───────── */
    function openModal(modalType) {
        S.activeModal = modalType;
        var drawer = document.getElementById('fvw-loc-drawer');
        var backdrop = document.getElementById('fvw-loc-backdrop');
        if (drawer && backdrop) {
            renderDrawerContent(modalType);
            drawer.classList.add('active');
            backdrop.classList.add('active');
        }
    }
    function closeModal() {
        S.activeModal = null;
        var drawer = document.getElementById('fvw-loc-drawer');
        var backdrop = document.getElementById('fvw-loc-backdrop');
        if (drawer && backdrop) {
            drawer.classList.remove('active');
            backdrop.classList.remove('active');
        }
    }

    function updateAllDisplayVals() {
        var dispB = document.getElementById('fvw-brand-display-val');
        if (dispB) dispB.textContent = S.brand || 'FRANCESCOVEROLINO';
        var dispT = document.getElementById('fvw-title-display-val');
        if (dispT) dispT.textContent = S.title || 'FIRENZE STREET';
        var dispS = document.getElementById('fvw-sub-display-val');
        if (dispS) dispS.textContent = S.subtitle || 'Talk & Masterclass';
        var dispD = document.getElementById('fvw-dates-display-val');
        if (dispD) dispD.textContent = S.dates || "16 - 18 OTT'26";
        var dispDesc = document.getElementById('fvw-desc-display-val');
        if (dispDesc) dispDesc.textContent = S.description ? (S.description.slice(0, 30) + (S.description.length > 30 ? '...' : '')) : 'Nessuna descrizione';
        var dispImg = document.getElementById('fvw-img-display-val');
        if (dispImg) dispImg.textContent = formatImgLabel(S.imageUrl);
    }

    function renderDrawerContent(type) {
        var body = document.getElementById('fvw-drawer-body');
        var title = document.getElementById('fvw-drawer-title');
        if (!body || !title) return;

        while (body.firstChild) body.removeChild(body.firstChild);

        if (type === 'brand') {
            title.textContent = 'Brand / Intestazione';

            /* 1. Testo */
            var fTesto = document.createElement('div'); fTesto.className = 'wv-field';
            var lb1 = document.createElement('label'); lb1.textContent = 'Testo Brand'; fTesto.appendChild(lb1);
            var inpTesto = document.createElement('input'); inpTesto.type = 'text';
            inpTesto.placeholder = 'Es. FRANCESCOVEROLINO'; inpTesto.value = S.brand || '';
            inpTesto.addEventListener('input', function () {
                S.brand = inpTesto.value;
                updateAllDisplayVals();
                requestRenderCanvas();
            });
            fTesto.appendChild(inpTesto); body.appendChild(fTesto);

            /* 2. Font */
            var fFont = document.createElement('div'); fFont.className = 'wv-field';
            var lb2 = document.createElement('label'); lb2.textContent = '1. Font / Carattere'; fFont.appendChild(lb2);
            var selFont = document.createElement('select');
            FONTS.forEach(function (f) {
                var opt = document.createElement('option'); opt.value = f.value; opt.textContent = f.label;
                if (f.value === S.brandFont) opt.selected = true;
                selFont.appendChild(opt);
            });
            selFont.addEventListener('change', function () {
                S.brandFont = selFont.value;
                if (document.fonts && document.fonts.ready) { document.fonts.ready.then(requestRenderCanvas); } else { requestRenderCanvas(); }
            });
            fFont.appendChild(selFont); body.appendChild(fFont);

            /* 3. Dimensione Font */
            var fSize = document.createElement('div'); fSize.className = 'wv-field';
            var lb3 = document.createElement('label');
            var sizeSpan = document.createElement('span'); sizeSpan.style.float = 'right'; sizeSpan.textContent = (S.brandFontSize || 28) + 'px';
            lb3.textContent = '2. Dimensione Font '; lb3.appendChild(sizeSpan); fSize.appendChild(lb3);
            var inpSize = document.createElement('input'); inpSize.type = 'range';
            inpSize.min = 10; inpSize.max = 120; inpSize.value = S.brandFontSize || 28; inpSize.style.width = '100%';
            inpSize.addEventListener('input', function () {
                S.brandFontSize = +inpSize.value;
                sizeSpan.textContent = inpSize.value + 'px';
                requestRenderCanvas();
            });
            fSize.appendChild(inpSize); body.appendChild(fSize);

            /* 4. Colore */
            var fColor = document.createElement('div'); fColor.className = 'wv-field';
            var lb4 = document.createElement('label'); lb4.textContent = '3. Colore Testo'; fColor.appendChild(lb4);
            var colorWrap = document.createElement('div'); colorWrap.style.cssText = 'display:flex;gap:10px;align-items:center;';
            var inpColor = document.createElement('input'); inpColor.type = 'color';
            inpColor.value = S.brandColor || '#ffffff';
            inpColor.style.cssText = 'width:44px;height:36px;padding:0;border:none;background:none;cursor:pointer;';
            var hexText = document.createElement('input'); hexText.type = 'text'; hexText.value = S.brandColor || '#ffffff';
            hexText.style.flex = '1';
            inpColor.addEventListener('input', function () { S.brandColor = inpColor.value; hexText.value = inpColor.value; requestRenderCanvas(); });
            hexText.addEventListener('input', function () { S.brandColor = hexText.value; inpColor.value = hexText.value; requestRenderCanvas(); });
            colorWrap.appendChild(inpColor); colorWrap.appendChild(hexText);
            fColor.appendChild(colorWrap); body.appendChild(fColor);

            /* 5. Posizione */
            var fPos = document.createElement('div'); fPos.className = 'wv-field';
            var lb5 = document.createElement('label'); lb5.textContent = '4. Posizione (Orizzontale / Verticale)'; fPos.appendChild(lb5);
            var posXWrap = document.createElement('div'); posXWrap.style.marginBottom = '10px';
            var lbX = document.createElement('div'); lbX.style.cssText = 'font-size:11px;color:#888;margin-bottom:4px;display:flex;justify-content:space-between;';
            var spanX = document.createElement('span'); spanX.textContent = (S.brandX !== undefined ? S.brandX : 60) + 'px';
            lbX.innerHTML = 'Orizzontale (X): '; lbX.appendChild(spanX);
            var inpX = document.createElement('input'); inpX.type = 'range'; inpX.min = 0; inpX.max = 800; inpX.value = S.brandX !== undefined ? S.brandX : 60; inpX.style.width = '100%';
            inpX.addEventListener('input', function () { S.brandX = +inpX.value; spanX.textContent = inpX.value + 'px'; requestRenderCanvas(); });
            posXWrap.appendChild(lbX); posXWrap.appendChild(inpX); fPos.appendChild(posXWrap);

            var posYWrap = document.createElement('div');
            var lbY = document.createElement('div'); lbY.style.cssText = 'font-size:11px;color:#888;margin-bottom:4px;display:flex;justify-content:space-between;';
            var spanY = document.createElement('span'); spanY.textContent = (S.brandY !== undefined ? S.brandY : 75) + 'px';
            lbY.innerHTML = 'Verticale (Y): '; lbY.appendChild(spanY);
            var inpY = document.createElement('input'); inpY.type = 'range'; inpY.min = 0; inpY.max = 800; inpY.value = S.brandY !== undefined ? S.brandY : 75; inpY.style.width = '100%';
            inpY.addEventListener('input', function () { S.brandY = +inpY.value; spanY.textContent = inpY.value + 'px'; requestRenderCanvas(); });
            posYWrap.appendChild(lbY); posYWrap.appendChild(inpY); fPos.appendChild(posYWrap);

            body.appendChild(fPos);

        } else if (type === 'title') {
            title.textContent = 'Categoria / Titolo';

            var fTesto = document.createElement('div'); fTesto.className = 'wv-field';
            var lb1 = document.createElement('label'); lb1.textContent = 'Testo Categoria / Titolo'; fTesto.appendChild(lb1);
            var inpTesto = document.createElement('input'); inpTesto.type = 'text';
            inpTesto.placeholder = 'Es. FIRENZE STREET'; inpTesto.value = S.title || '';
            inpTesto.addEventListener('input', function () { S.title = inpTesto.value; updateAllDisplayVals(); requestRenderCanvas(); });
            fTesto.appendChild(inpTesto); body.appendChild(fTesto);

            var fFont = document.createElement('div'); fFont.className = 'wv-field';
            var lb2 = document.createElement('label'); lb2.textContent = '1. Font / Carattere'; fFont.appendChild(lb2);
            var selFont = document.createElement('select');
            FONTS.forEach(function (f) {
                var opt = document.createElement('option'); opt.value = f.value; opt.textContent = f.label;
                if (f.value === S.titleFont) opt.selected = true; selFont.appendChild(opt);
            });
            selFont.addEventListener('change', function () { S.titleFont = selFont.value; if (document.fonts && document.fonts.ready) { document.fonts.ready.then(requestRenderCanvas); } else { requestRenderCanvas(); } });
            fFont.appendChild(selFont); body.appendChild(fFont);

            var currSize = S.titleFontSize || (S.format === 'fb' ? 60 : S.format === 'story' ? 80 : 72);
            var fSize = document.createElement('div'); fSize.className = 'wv-field';
            var lb3 = document.createElement('label'); var sizeSpan = document.createElement('span'); sizeSpan.style.float = 'right'; sizeSpan.textContent = currSize + 'px';
            lb3.textContent = '2. Dimensione Font '; lb3.appendChild(sizeSpan); fSize.appendChild(lb3);
            var inpSize = document.createElement('input'); inpSize.type = 'range'; inpSize.min = 20; inpSize.max = 160; inpSize.value = currSize; inpSize.style.width = '100%';
            inpSize.addEventListener('input', function () { S.titleFontSize = +inpSize.value; sizeSpan.textContent = inpSize.value + 'px'; requestRenderCanvas(); });
            fSize.appendChild(inpSize); body.appendChild(fSize);

            var fColor = document.createElement('div'); fColor.className = 'wv-field';
            var lb4 = document.createElement('label'); lb4.textContent = '3. Colore Testo'; fColor.appendChild(lb4);
            var colorWrap = document.createElement('div'); colorWrap.style.cssText = 'display:flex;gap:10px;align-items:center;';
            var inpColor = document.createElement('input'); inpColor.type = 'color'; inpColor.value = S.titleColor || '#E11D48'; inpColor.style.cssText = 'width:44px;height:36px;padding:0;border:none;background:none;cursor:pointer;';
            var hexText = document.createElement('input'); hexText.type = 'text'; hexText.value = S.titleColor || '#E11D48'; hexText.style.flex = '1';
            inpColor.addEventListener('input', function () { S.titleColor = inpColor.value; hexText.value = inpColor.value; requestRenderCanvas(); });
            hexText.addEventListener('input', function () { S.titleColor = hexText.value; inpColor.value = hexText.value; requestRenderCanvas(); });
            colorWrap.appendChild(inpColor); colorWrap.appendChild(hexText); fColor.appendChild(colorWrap); body.appendChild(fColor);

            var fPos = document.createElement('div'); fPos.className = 'wv-field';
            var lb5 = document.createElement('label'); lb5.textContent = '4. Posizione (Orizzontale / Verticale)'; fPos.appendChild(lb5);
            var defaultX = S.format === 'fb' ? 60 : (fmt().w / 2); var defaultY = S.format === 'fb' ? 240 : (S.format === 'story' ? 1150 : 545);
            var currX = (S.titleX !== null && S.titleX !== undefined) ? S.titleX : defaultX; var currY = (S.titleY !== null && S.titleY !== undefined) ? S.titleY : defaultY;
            var posXWrap = document.createElement('div'); posXWrap.style.marginBottom = '10px';
            var lbX = document.createElement('div'); lbX.style.cssText = 'font-size:11px;color:#888;margin-bottom:4px;display:flex;justify-content:space-between;';
            var spanX = document.createElement('span'); spanX.textContent = Math.round(currX) + 'px'; lbX.innerHTML = 'Orizzontale (X): '; lbX.appendChild(spanX);
            var inpX = document.createElement('input'); inpX.type = 'range'; inpX.min = 0; inpX.max = 1200; inpX.value = currX; inpX.style.width = '100%';
            inpX.addEventListener('input', function () { S.titleX = +inpX.value; spanX.textContent = inpX.value + 'px'; requestRenderCanvas(); });
            posXWrap.appendChild(lbX); posXWrap.appendChild(inpX); fPos.appendChild(posXWrap);

            var posYWrap = document.createElement('div'); var lbY = document.createElement('div'); lbY.style.cssText = 'font-size:11px;color:#888;margin-bottom:4px;display:flex;justify-content:space-between;';
            var spanY = document.createElement('span'); spanY.textContent = Math.round(currY) + 'px'; lbY.innerHTML = 'Verticale (Y): '; lbY.appendChild(spanY);
            var inpY = document.createElement('input'); inpY.type = 'range'; inpY.min = 0; inpY.max = 1920; inpY.value = currY; inpY.style.width = '100%';
            inpY.addEventListener('input', function () { S.titleY = +inpY.value; spanY.textContent = inpY.value + 'px'; requestRenderCanvas(); });
            posYWrap.appendChild(lbY); posYWrap.appendChild(inpY); fPos.appendChild(posYWrap);

            body.appendChild(fPos);

        } else if (type === 'subtitle') {
            title.textContent = 'Tipologia / Sottotitolo';

            var fTesto = document.createElement('div'); fTesto.className = 'wv-field';
            var lb1 = document.createElement('label'); lb1.textContent = 'Testo Sottotitolo'; fTesto.appendChild(lb1);
            var inpTesto = document.createElement('input'); inpTesto.type = 'text'; inpTesto.placeholder = 'Es. Talk & Masterclass'; inpTesto.value = S.subtitle || '';
            inpTesto.addEventListener('input', function () { S.subtitle = inpTesto.value; updateAllDisplayVals(); requestRenderCanvas(); });
            fTesto.appendChild(inpTesto); body.appendChild(fTesto);

            var fFont = document.createElement('div'); fFont.className = 'wv-field';
            var lb2 = document.createElement('label'); lb2.textContent = '1. Font / Carattere'; fFont.appendChild(lb2);
            var selFont = document.createElement('select');
            FONTS.forEach(function (f) { var opt = document.createElement('option'); opt.value = f.value; opt.textContent = f.label; if (f.value === S.subtitleFont) opt.selected = true; selFont.appendChild(opt); });
            selFont.addEventListener('change', function () { S.subtitleFont = selFont.value; if (document.fonts && document.fonts.ready) { document.fonts.ready.then(requestRenderCanvas); } else { requestRenderCanvas(); } });
            fFont.appendChild(selFont); body.appendChild(fFont);

            var currSize = S.subtitleFontSize || (S.format === 'fb' ? 28 : S.format === 'story' ? 38 : 34);
            var fSize = document.createElement('div'); fSize.className = 'wv-field';
            var lb3 = document.createElement('label'); var sizeSpan = document.createElement('span'); sizeSpan.style.float = 'right'; sizeSpan.textContent = currSize + 'px';
            lb3.textContent = '2. Dimensione Font '; lb3.appendChild(sizeSpan); fSize.appendChild(lb3);
            var inpSize = document.createElement('input'); inpSize.type = 'range'; inpSize.min = 10; inpSize.max = 100; inpSize.value = currSize; inpSize.style.width = '100%';
            inpSize.addEventListener('input', function () { S.subtitleFontSize = +inpSize.value; sizeSpan.textContent = inpSize.value + 'px'; requestRenderCanvas(); });
            fSize.appendChild(inpSize); body.appendChild(fSize);

            var fColor = document.createElement('div'); fColor.className = 'wv-field';
            var lb4 = document.createElement('label'); lb4.textContent = '3. Colore Testo'; fColor.appendChild(lb4);
            var colorWrap = document.createElement('div'); colorWrap.style.cssText = 'display:flex;gap:10px;align-items:center;';
            var inpColor = document.createElement('input'); inpColor.type = 'color'; inpColor.value = S.subtitleColor || '#ffffff'; inpColor.style.cssText = 'width:44px;height:36px;padding:0;border:none;background:none;cursor:pointer;';
            var hexText = document.createElement('input'); hexText.type = 'text'; hexText.value = S.subtitleColor || '#ffffff'; hexText.style.flex = '1';
            inpColor.addEventListener('input', function () { S.subtitleColor = inpColor.value; hexText.value = inpColor.value; requestRenderCanvas(); });
            hexText.addEventListener('input', function () { S.subtitleColor = hexText.value; inpColor.value = hexText.value; requestRenderCanvas(); });
            colorWrap.appendChild(inpColor); colorWrap.appendChild(hexText); fColor.appendChild(colorWrap); body.appendChild(fColor);

            var fPos = document.createElement('div'); fPos.className = 'wv-field';
            var lb5 = document.createElement('label'); lb5.textContent = '4. Posizione (Orizzontale / Verticale)'; fPos.appendChild(lb5);
            var defaultX = S.format === 'fb' ? 60 : (fmt().w / 2); var defaultY = S.format === 'fb' ? 290 : (S.format === 'story' ? 1220 : 605);
            var currX = (S.subtitleX !== null && S.subtitleX !== undefined) ? S.subtitleX : defaultX; var currY = (S.subtitleY !== null && S.subtitleY !== undefined) ? S.subtitleY : defaultY;
            var posXWrap = document.createElement('div'); posXWrap.style.marginBottom = '10px';
            var lbX = document.createElement('div'); lbX.style.cssText = 'font-size:11px;color:#888;margin-bottom:4px;display:flex;justify-content:space-between;';
            var spanX = document.createElement('span'); spanX.textContent = Math.round(currX) + 'px'; lbX.innerHTML = 'Orizzontale (X): '; lbX.appendChild(spanX);
            var inpX = document.createElement('input'); inpX.type = 'range'; inpX.min = 0; inpX.max = 1200; inpX.value = currX; inpX.style.width = '100%';
            inpX.addEventListener('input', function () { S.subtitleX = +inpX.value; spanX.textContent = inpX.value + 'px'; requestRenderCanvas(); });
            posXWrap.appendChild(lbX); posXWrap.appendChild(inpX); fPos.appendChild(posXWrap);

            var posYWrap = document.createElement('div'); var lbY = document.createElement('div'); lbY.style.cssText = 'font-size:11px;color:#888;margin-bottom:4px;display:flex;justify-content:space-between;';
            var spanY = document.createElement('span'); spanY.textContent = Math.round(currY) + 'px'; lbY.innerHTML = 'Verticale (Y): '; lbY.appendChild(spanY);
            var inpY = document.createElement('input'); inpY.type = 'range'; inpY.min = 0; inpY.max = 1920; inpY.value = currY; inpY.style.width = '100%';
            inpY.addEventListener('input', function () { S.subtitleY = +inpY.value; spanY.textContent = inpY.value + 'px'; requestRenderCanvas(); });
            posYWrap.appendChild(lbY); posYWrap.appendChild(inpY); fPos.appendChild(posYWrap);

            body.appendChild(fPos);

        } else if (type === 'dates') {
            title.textContent = 'Data / Periodo';

            var fTesto = document.createElement('div'); fTesto.className = 'wv-field';
            var lb1 = document.createElement('label'); lb1.textContent = 'Testo Data / Periodo'; fTesto.appendChild(lb1);
            var inpTesto = document.createElement('input'); inpTesto.type = 'text'; inpTesto.placeholder = "Es. 16 - 18 OTT'26"; inpTesto.value = S.dates || '';
            inpTesto.addEventListener('input', function () { S.dates = inpTesto.value; updateAllDisplayVals(); requestRenderCanvas(); });
            fTesto.appendChild(inpTesto); body.appendChild(fTesto);

            var fFont = document.createElement('div'); fFont.className = 'wv-field';
            var lb2 = document.createElement('label'); lb2.textContent = '1. Font / Carattere'; fFont.appendChild(lb2);
            var selFont = document.createElement('select');
            FONTS.forEach(function (f) { var opt = document.createElement('option'); opt.value = f.value; opt.textContent = f.label; if (f.value === S.datesFont) opt.selected = true; selFont.appendChild(opt); });
            selFont.addEventListener('change', function () { S.datesFont = selFont.value; if (document.fonts && document.fonts.ready) { document.fonts.ready.then(requestRenderCanvas); } else { requestRenderCanvas(); } });
            fFont.appendChild(selFont); body.appendChild(fFont);

            var currSize = S.datesFontSize || (S.format === 'fb' ? 30 : S.format === 'story' ? 40 : 36);
            var fSize = document.createElement('div'); fSize.className = 'wv-field';
            var lb3 = document.createElement('label'); var sizeSpan = document.createElement('span'); sizeSpan.style.float = 'right'; sizeSpan.textContent = currSize + 'px';
            lb3.textContent = '2. Dimensione Font '; lb3.appendChild(sizeSpan); fSize.appendChild(lb3);
            var inpSize = document.createElement('input'); inpSize.type = 'range'; inpSize.min = 10; inpSize.max = 100; inpSize.value = currSize; inpSize.style.width = '100%';
            inpSize.addEventListener('input', function () { S.datesFontSize = +inpSize.value; sizeSpan.textContent = inpSize.value + 'px'; requestRenderCanvas(); });
            fSize.appendChild(inpSize); body.appendChild(fSize);

            var fColor = document.createElement('div'); fColor.className = 'wv-field';
            var lb4 = document.createElement('label'); lb4.textContent = '3. Colore Testo'; fColor.appendChild(lb4);
            var colorWrap = document.createElement('div'); colorWrap.style.cssText = 'display:flex;gap:10px;align-items:center;';
            var inpColor = document.createElement('input'); inpColor.type = 'color'; inpColor.value = S.datesColor || '#ffffff'; inpColor.style.cssText = 'width:44px;height:36px;padding:0;border:none;background:none;cursor:pointer;';
            var hexText = document.createElement('input'); hexText.type = 'text'; hexText.value = S.datesColor || '#ffffff'; hexText.style.flex = '1';
            inpColor.addEventListener('input', function () { S.datesColor = inpColor.value; hexText.value = inpColor.value; requestRenderCanvas(); });
            hexText.addEventListener('input', function () { S.datesColor = hexText.value; inpColor.value = hexText.value; requestRenderCanvas(); });
            colorWrap.appendChild(inpColor); colorWrap.appendChild(hexText); fColor.appendChild(colorWrap); body.appendChild(fColor);

            var fPos = document.createElement('div'); fPos.className = 'wv-field';
            var lb5 = document.createElement('label'); lb5.textContent = '4. Posizione (Orizzontale / Verticale)'; fPos.appendChild(lb5);
            var defaultX = S.format === 'fb' ? 60 : (fmt().w / 2); var defaultY = S.format === 'fb' ? 340 : (S.format === 'story' ? 1300 : 665);
            var currX = (S.datesX !== null && S.datesX !== undefined) ? S.datesX : defaultX; var currY = (S.datesY !== null && S.datesY !== undefined) ? S.datesY : defaultY;
            var posXWrap = document.createElement('div'); posXWrap.style.marginBottom = '10px';
            var lbX = document.createElement('div'); lbX.style.cssText = 'font-size:11px;color:#888;margin-bottom:4px;display:flex;justify-content:space-between;';
            var spanX = document.createElement('span'); spanX.textContent = Math.round(currX) + 'px'; lbX.innerHTML = 'Orizzontale (X): '; lbX.appendChild(spanX);
            var inpX = document.createElement('input'); inpX.type = 'range'; inpX.min = 0; inpX.max = 1200; inpX.value = currX; inpX.style.width = '100%';
            inpX.addEventListener('input', function () { S.datesX = +inpX.value; spanX.textContent = inpX.value + 'px'; requestRenderCanvas(); });
            posXWrap.appendChild(lbX); posXWrap.appendChild(inpX); fPos.appendChild(posXWrap);

            var posYWrap = document.createElement('div'); var lbY = document.createElement('div'); lbY.style.cssText = 'font-size:11px;color:#888;margin-bottom:4px;display:flex;justify-content:space-between;';
            var spanY = document.createElement('span'); spanY.textContent = Math.round(currY) + 'px'; lbY.innerHTML = 'Verticale (Y): '; lbY.appendChild(spanY);
            var inpY = document.createElement('input'); inpY.type = 'range'; inpY.min = 0; inpY.max = 1920; inpY.value = currY; inpY.style.width = '100%';
            inpY.addEventListener('input', function () { S.datesY = +inpY.value; spanY.textContent = inpY.value + 'px'; requestRenderCanvas(); });
            posYWrap.appendChild(lbY); posYWrap.appendChild(inpY); fPos.appendChild(posYWrap);

            body.appendChild(fPos);

        } else if (type === 'description') {
            title.textContent = 'Descrizione / Programma';

            /* 1. Testo */
            var fTesto = document.createElement('div'); fTesto.className = 'wv-field';
            var lb1 = document.createElement('label'); lb1.textContent = 'Testo Descrizione / Programma'; fTesto.appendChild(lb1);
            var taTesto = document.createElement('textarea'); taTesto.rows = 4;
            taTesto.placeholder = 'Es. 3 GIORNATE DI STREET PHOTOGRAPHY...'; taTesto.value = S.description || '';
            taTesto.addEventListener('input', function () {
                S.description = taTesto.value;
                updateAllDisplayVals();
                requestRenderCanvas();
            });
            fTesto.appendChild(taTesto); body.appendChild(fTesto);

            /* 2. Font */
            var fFont = document.createElement('div'); fFont.className = 'wv-field';
            var lb2 = document.createElement('label'); lb2.textContent = '1. Font / Carattere'; fFont.appendChild(lb2);
            var selFont = document.createElement('select');
            FONTS.forEach(function (f) {
                var opt = document.createElement('option'); opt.value = f.value; opt.textContent = f.label;
                if (f.value === S.descFont) opt.selected = true; selFont.appendChild(opt);
            });
            selFont.addEventListener('change', function () {
                S.descFont = selFont.value;
                if (document.fonts && document.fonts.ready) { document.fonts.ready.then(requestRenderCanvas); } else { requestRenderCanvas(); }
            });
            fFont.appendChild(selFont); body.appendChild(fFont);

            /* 3. Dimensione Font */
            var currSize = S.descFontSize || (S.format === 'fb' ? 17 : S.format === 'story' ? 24 : 22);
            var fSize = document.createElement('div'); fSize.className = 'wv-field';
            var lb3 = document.createElement('label'); var sizeSpan = document.createElement('span'); sizeSpan.style.float = 'right'; sizeSpan.textContent = currSize + 'px';
            lb3.textContent = '2. Dimensione Font '; lb3.appendChild(sizeSpan); fSize.appendChild(lb3);
            var inpSize = document.createElement('input'); inpSize.type = 'range'; inpSize.min = 10; inpSize.max = 80; inpSize.value = currSize; inpSize.style.width = '100%';
            inpSize.addEventListener('input', function () { S.descFontSize = +inpSize.value; sizeSpan.textContent = inpSize.value + 'px'; requestRenderCanvas(); });
            fSize.appendChild(inpSize); body.appendChild(fSize);

            /* 4. Colore */
            var fColor = document.createElement('div'); fColor.className = 'wv-field';
            var lb4 = document.createElement('label'); lb4.textContent = '3. Colore Testo'; fColor.appendChild(lb4);
            var colorWrap = document.createElement('div'); colorWrap.style.cssText = 'display:flex;gap:10px;align-items:center;';
            var inpColor = document.createElement('input'); inpColor.type = 'color'; inpColor.value = S.descColor || '#ffffff'; inpColor.style.cssText = 'width:44px;height:36px;padding:0;border:none;background:none;cursor:pointer;';
            var hexText = document.createElement('input'); hexText.type = 'text'; hexText.value = S.descColor || '#ffffff'; hexText.style.flex = '1';
            inpColor.addEventListener('input', function () { S.descColor = inpColor.value; hexText.value = inpColor.value; requestRenderCanvas(); });
            hexText.addEventListener('input', function () { S.descColor = hexText.value; inpColor.value = hexText.value; requestRenderCanvas(); });
            colorWrap.appendChild(inpColor); colorWrap.appendChild(hexText); fColor.appendChild(colorWrap); body.appendChild(fColor);

            /* 5. Posizione (X e Y) */
            var fPos = document.createElement('div'); fPos.className = 'wv-field';
            var lb5 = document.createElement('label'); lb5.textContent = '4. Posizione (Orizzontale / Verticale)'; fPos.appendChild(lb5);
            var defaultX = S.format === 'fb' ? 60 : (fmt().w / 2); var defaultY = S.format === 'fb' ? 400 : (S.format === 'story' ? 1390 : 740);
            var currX = (S.descX !== null && S.descX !== undefined) ? S.descX : defaultX; var currY = (S.descY !== null && S.descY !== undefined) ? S.descY : defaultY;
            var posXWrap = document.createElement('div'); posXWrap.style.marginBottom = '10px';
            var lbX = document.createElement('div'); lbX.style.cssText = 'font-size:11px;color:#888;margin-bottom:4px;display:flex;justify-content:space-between;';
            var spanX = document.createElement('span'); spanX.textContent = Math.round(currX) + 'px'; lbX.innerHTML = 'Orizzontale (X): '; lbX.appendChild(spanX);
            var inpX = document.createElement('input'); inpX.type = 'range'; inpX.min = 0; inpX.max = 1200; inpX.value = currX; inpX.style.width = '100%';
            inpX.addEventListener('input', function () { S.descX = +inpX.value; spanX.textContent = inpX.value + 'px'; requestRenderCanvas(); });
            posXWrap.appendChild(lbX); posXWrap.appendChild(inpX); fPos.appendChild(posXWrap);

            var posYWrap = document.createElement('div'); var lbY = document.createElement('div'); lbY.style.cssText = 'font-size:11px;color:#888;margin-bottom:4px;display:flex;justify-content:space-between;';
            var spanY = document.createElement('span'); spanY.textContent = Math.round(currY) + 'px'; lbY.innerHTML = 'Verticale (Y): '; lbY.appendChild(spanY);
            var inpY = document.createElement('input'); inpY.type = 'range'; inpY.min = 0; inpY.max = 1920; inpY.value = currY; inpY.style.width = '100%';
            inpY.addEventListener('input', function () { S.descY = +inpY.value; spanY.textContent = inpY.value + 'px'; requestRenderCanvas(); });
            posYWrap.appendChild(lbY); posYWrap.appendChild(inpY); fPos.appendChild(posYWrap);

            body.appendChild(fPos);

        } else if (type === 'image') {
            title.textContent = 'Fotografia di Sfondo';

            /* 1. Selezione Media / Upload */
            var fMedia = document.createElement('div'); fMedia.className = 'wv-field';
            var lb1 = document.createElement('label'); lb1.textContent = 'Immagine Sfondo (URL / WP Media / Upload)'; fMedia.appendChild(lb1);

            var row = document.createElement('div'); row.className = 'fvw-photo-row';
            var ti = document.createElement('input'); ti.type = 'text'; ti.id = 'fvw-img-single'; ti.placeholder = 'URL o carica\u2026'; ti.value = S.imageUrl || '';
            ti.addEventListener('input', function () { S.imageUrl = ti.value; updateAllDisplayVals(); requestRenderCanvas(); });

            var bM = document.createElement('button'); bM.type = 'button'; bM.className = 'wv-btn wv-btn-sm';
            bM.textContent = 'Media'; bM.addEventListener('click', mediaPickerSingle);

            var lbF = document.createElement('label'); lbF.className = 'wv-btn wv-btn-sm fvw-file-label';
            lbF.textContent = 'Carica';
            var fi = document.createElement('input'); fi.type = 'file'; fi.accept = 'image/*'; fi.style.display = 'none';
            fi.addEventListener('change', function () {
                var file = fi.files && fi.files[0]; if (!file) return;
                var rd = new FileReader(); rd.onload = function (e) {
                    S.imageUrl = e.target.result;
                    ti.value = e.target.result;
                    updateAllDisplayVals();
                    requestRenderCanvas();
                };
                rd.readAsDataURL(file);
            });
            lbF.appendChild(fi);

            row.appendChild(ti); row.appendChild(bM); row.appendChild(lbF);
            fMedia.appendChild(row); body.appendChild(fMedia);

            /* 2. Zoom (Scala) */
            var fScale = document.createElement('div'); fScale.className = 'wv-field';
            var lbScale = document.createElement('label');
            var scaleSpan = document.createElement('span'); scaleSpan.style.float = 'right'; scaleSpan.textContent = Math.round((S.imgScale || 1.0) * 100) + '%';
            lbScale.textContent = '1. Zoom (Scala) '; lbScale.appendChild(scaleSpan); fScale.appendChild(lbScale);
            var inpScale = document.createElement('input'); inpScale.type = 'range';
            inpScale.min = 0.5; inpScale.max = 3.0; inpScale.step = 0.05; inpScale.value = S.imgScale || 1.0; inpScale.style.width = '100%';
            inpScale.addEventListener('input', function () {
                S.imgScale = +inpScale.value;
                scaleSpan.textContent = Math.round(S.imgScale * 100) + '%';
                requestRenderCanvas();
            });
            fScale.appendChild(inpScale); body.appendChild(fScale);

            /* 3. Posizione X e Y */
            var fPos = document.createElement('div'); fPos.className = 'wv-field';
            var lbPos = document.createElement('label'); lbPos.textContent = '2. Posizione Immagine (X / Y)'; fPos.appendChild(lbPos);

            var posXWrap = document.createElement('div'); posXWrap.style.marginBottom = '10px';
            var lbX = document.createElement('div'); lbX.style.cssText = 'font-size:11px;color:#888;margin-bottom:4px;display:flex;justify-content:space-between;';
            var spanX = document.createElement('span'); spanX.textContent = (S.imgOffsetX || 0) + 'px';
            lbX.innerHTML = 'Orizzontale (X): '; lbX.appendChild(spanX);
            var inpX = document.createElement('input'); inpX.type = 'range'; inpX.min = -500; inpX.max = 500; inpX.step = 5; inpX.value = S.imgOffsetX || 0; inpX.style.width = '100%';
            inpX.addEventListener('input', function () {
                S.imgOffsetX = +inpX.value; spanX.textContent = inpX.value + 'px'; requestRenderCanvas();
            });
            posXWrap.appendChild(lbX); posXWrap.appendChild(inpX); fPos.appendChild(posXWrap);

            var posYWrap = document.createElement('div');
            var lbY = document.createElement('div'); lbY.style.cssText = 'font-size:11px;color:#888;margin-bottom:4px;display:flex;justify-content:space-between;';
            var spanY = document.createElement('span'); spanY.textContent = (S.imgOffsetY || 0) + 'px';
            lbY.innerHTML = 'Verticale (Y): '; lbY.appendChild(spanY);
            var inpY = document.createElement('input'); inpY.type = 'range'; inpY.min = -500; inpY.max = 500; inpY.step = 5; inpY.value = S.imgOffsetY || 0; inpY.style.width = '100%';
            inpY.addEventListener('input', function () {
                S.imgOffsetY = +inpY.value; spanY.textContent = inpY.value + 'px'; requestRenderCanvas();
            });
            posYWrap.appendChild(lbY); posYWrap.appendChild(inpY); fPos.appendChild(posYWrap);
            body.appendChild(fPos);

            /* 4. Opacità Overlay Scuro */
            var fOverlay = document.createElement('div'); fOverlay.className = 'wv-field';
            var lbOv = document.createElement('label');
            var ovSpan = document.createElement('span'); ovSpan.style.float = 'right'; ovSpan.textContent = Math.round((S.darkOverlay || 0.5) * 100) + '%';
            lbOv.textContent = '3. Intensit\u00e0 Overlay Scuro '; lbOv.appendChild(ovSpan); fOverlay.appendChild(lbOv);
            var inpOv = document.createElement('input'); inpOv.type = 'range';
            inpOv.min = 0.0; inpOv.max = 0.95; inpOv.step = 0.05; inpOv.value = S.darkOverlay || 0.5; inpOv.style.width = '100%';
            inpOv.addEventListener('input', function () {
                S.darkOverlay = +inpOv.value;
                ovSpan.textContent = Math.round(S.darkOverlay * 100) + '%';
                requestRenderCanvas();
            });
            fOverlay.appendChild(inpOv); body.appendChild(fOverlay);
        }
    }

    /* ───────── refresh partials ───────── */
    function refreshEvtSel() {
        var s = document.getElementById('fvw-evt-sel'); if (!s) return;
        while (s.firstChild) s.removeChild(s.firstChild);
        var o = document.createElement('option'); o.value = '0'; o.textContent = '\u2014 scegli \u2014'; s.appendChild(o);
        S.eventsList.forEach(function (ev) {
            var opt = document.createElement('option'); opt.value = ev.id; opt.textContent = ev.label; s.appendChild(opt);
        });
    }

    function renderModels() {
        var c = document.getElementById('fvw-models'); if (!c) return;
        while (c.firstChild) c.removeChild(c.firstChild);
        if (!S.savedModels.length) {
            var p = document.createElement('p'); p.className = 'hint'; p.style.cssText = 'color:#646970;font-size:13px;font-style:italic;'; p.textContent = 'Nessun modello salvato.'; c.appendChild(p); return;
        }
        S.savedModels.forEach(function (m) {
            var card = document.createElement('div'); card.className = 'fvw-model-card';

            /* Mini Canvas Anteprima (170px) */
            var prevWrap = document.createElement('div');
            prevWrap.className = 'fvw-model-preview-wrap';
            var miniCv = document.createElement('canvas');
            miniCv.className = 'fvw-model-canvas';
            prevWrap.appendChild(miniCv);
            card.appendChild(prevWrap);

            var metaWrap = document.createElement('div');
            metaWrap.className = 'fvw-model-info';

            var h4 = document.createElement('h4');
            h4.className = 'fvw-model-name';
            h4.textContent = m.nome;
            metaWrap.appendChild(h4);

            var acts = document.createElement('div');
            acts.className = 'fvw-model-actions';

            var bL = document.createElement('button');
            bL.type = 'button';
            bL.className = 'button button-small button-primary';
            bL.textContent = 'Carica';
            bL.addEventListener('click', function () { applyModel(m); });

            var bD = document.createElement('button');
            bD.type = 'button';
            bD.className = 'button button-small button-link-delete';
            bD.textContent = 'Elimina';
            bD.addEventListener('click', function () { deleteModel(m.id); });

            acts.appendChild(bL);
            acts.appendChild(bD);
            metaWrap.appendChild(acts);
            card.appendChild(metaWrap);

            c.appendChild(card);

            /* Renderizza il mini canvas del modello a 170px */
            renderMiniCanvas(miniCv, m, 170);
        });
    }

    /* ───────── form helpers ───────── */
    function field(labelTxt, ctrl, btnEditType) {
        var w = document.createElement('div'); w.className = 'wv-field';
        var labelRow = document.createElement('div');
        labelRow.style.cssText = 'display:flex;justify-content:space-between;align-items:center;margin-bottom:2px;';
        
        var lb = document.createElement('label');
        lb.style.marginBottom = '0';
        lb.textContent = labelTxt;
        labelRow.appendChild(lb);

        if (btnEditType) {
            var bEdit = document.createElement('button');
            bEdit.type = 'button';
            bEdit.className = 'button button-small button-secondary';
            bEdit.textContent = 'Modifica';
            bEdit.addEventListener('click', function () {
                openModal(btnEditType);
            });
            labelRow.appendChild(bEdit);
        }

        w.appendChild(labelRow);
        w.appendChild(ctrl);
        return w;
    }
    function selInput(opts, stateKey, cb) {
        var s = document.createElement('select');
        opts.forEach(function (o) {
            var op = document.createElement('option'); op.value = o.v; op.textContent = o.l;
            if (o.v == S[stateKey]) op.selected = true; s.appendChild(op);
        });
        s.addEventListener('change', function () { S[stateKey] = s.value; requestRenderCanvas(); if (cb) cb(s.value); });
        return s;
    }

    function createDisplayRow(id, defaultVal, modalType, colorCss) {
        var wrap = document.createElement('div');
        wrap.className = 'fvw-loc-field-row';

        var valSpan = document.createElement('span');
        valSpan.id = id;
        valSpan.className = 'fvw-loc-field-val';
        if (colorCss) valSpan.style.color = colorCss;
        valSpan.textContent = defaultVal;
        wrap.appendChild(valSpan);

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'button button-small button-secondary';
        btn.textContent = 'Modifica';
        btn.addEventListener('click', function () { openModal(modalType); });
        wrap.appendChild(btn);

        return wrap;
    }

    /* ───────── form build ───────── */
    function buildForm() {
        var f = document.createDocumentFragment();

        /* title */
        var h3 = document.createElement('h3'); h3.textContent = 'Modello'; f.appendChild(h3);

        /* model name + save inline */
        var modelField = document.createElement('div');
        modelField.className = 'wv-field';
        var lbModel = document.createElement('label');
        lbModel.textContent = 'Nome modello';
        modelField.appendChild(lbModel);

        var modelRow = document.createElement('div');
        modelRow.style.cssText = 'display:flex;gap:8px;align-items:center;';

        var inpModel = document.createElement('input');
        inpModel.type = 'text';
        inpModel.placeholder = 'Es. Locandina Street Quadrato';
        inpModel.value = S.modelName || '';
        inpModel.style.flex = '1';
        inpModel.addEventListener('input', function () { S.modelName = inpModel.value; });
        modelRow.appendChild(inpModel);

        var bSave = document.createElement('button');
        bSave.type = 'button';
        bSave.className = 'button button-primary';
        bSave.style.whiteSpace = 'nowrap';
        bSave.textContent = S.currentModelId ? 'Aggiorna' : 'Salva modello';
        bSave.addEventListener('click', saveModel);
        modelRow.appendChild(bSave);

        if (S.currentModelId) {
            var bNew = document.createElement('button');
            bNew.type = 'button';
            bNew.className = 'button button-secondary';
            bNew.style.whiteSpace = 'nowrap';
            bNew.textContent = 'Nuovo';
            bNew.addEventListener('click', reset);
            modelRow.appendChild(bNew);
        }

        modelField.appendChild(modelRow);
        f.appendChild(modelField);

        var h3b = document.createElement('h3'); h3b.textContent = 'Contenuto'; f.appendChild(h3b);

        /* event prefill */
        var evSel = document.createElement('select'); evSel.id = 'fvw-evt-sel';
        var defOpt = document.createElement('option'); defOpt.value = '0'; defOpt.textContent = '\u2014 scegli \u2014'; evSel.appendChild(defOpt);
        evSel.addEventListener('change', function () {
            if (!evSel.value || evSel.value === '0') return;
            var ev = S.eventsList.find(function (e) { return e.id == evSel.value; });
            if (ev) {
                if (ev.categoria_nome) S.title = ev.categoria_nome;
                else {
                    var p = ev.label.split(' – ');
                    S.title = p[0] || '';
                }
                if (ev.data_formattata) S.dates = ev.data_formattata;
                if (ev.categoria_tipo) S.subtitle = ev.categoria_tipo;
                if (ev.categoria_foto) {
                    S.imageUrl = ev.categoria_foto;
                    cachedImg = null;
                }
                updateAllDisplayVals();
                rebuildForm();
                requestRenderCanvas();
            }
        });
        f.appendChild(field('Precompila da evento', evSel));

        /* format */
        f.appendChild(field('Formato grafica',
            selInput(FORMATS.map(function (ff) { return { v: ff.value, l: ff.label }; }), 'format')));

        /* 1. Brand display row */
        f.appendChild(field('Brand / intestazione',
            createDisplayRow('fvw-brand-display-val', S.brand || 'FRANCESCOVEROLINO', 'brand')));

        /* 2. Categoria / Titolo Principale display row */
        f.appendChild(field('Categoria / titolo principale',
            createDisplayRow('fvw-title-display-val', S.title || 'FIRENZE STREET', 'title', '#E11D48')));

        /* 3. Tipologia / Sottotitolo display row */
        f.appendChild(field('Tipologia / sottotitolo',
            createDisplayRow('fvw-sub-display-val', S.subtitle || 'Talk & Masterclass', 'subtitle')));

        /* 4. Data / Periodo display row */
        f.appendChild(field('Data / periodo',
            createDisplayRow('fvw-dates-display-val', S.dates || "16 - 18 OTT'26", 'dates')));

        /* 5. Descrizione / Programma display row */
        var descInitLabel = S.description ? (S.description.slice(0, 30) + (S.description.length > 30 ? '...' : '')) : '3 GIORNATE DI STREET...';
        f.appendChild(field('Descrizione / programma',
            createDisplayRow('fvw-desc-display-val', descInitLabel, 'description')));

        /* 6. Immagine di sfondo display row */
        var imgInitLabel = formatImgLabel(S.imageUrl);
        f.appendChild(field('Immagine di sfondo',
            createDisplayRow('fvw-img-display-val', imgInitLabel, 'image')));

        var actsBot = document.createElement('div'); actsBot.className = 'wv-form-actions';
        var bDl = document.createElement('button'); bDl.type = 'button'; bDl.className = 'button button-primary';
        bDl.textContent = 'Scarica PNG'; bDl.addEventListener('click', downloadPng);
        actsBot.appendChild(bDl); f.appendChild(actsBot);

        return f;
    }

    function rebuildForm() {
        var c = document.getElementById('fvw-loc-form'); if (!c) return;
        while (c.firstChild) c.removeChild(c.firstChild);
        c.appendChild(buildForm());
        refreshEvtSel();
    }

    /* ───────── mount ───────── */
    function mount() {
        var root = (document.getElementById('ws-locandine-app')||document.getElementById('fvw-locandine-app'));
        if (!root || root.dataset.mounted) return;
        root.dataset.mounted = '1';

        var dash = document.createElement('div'); dash.className = 'wv-dash';

        /* heading */
        var h2 = document.createElement('h2'); h2.textContent = 'Modelli Locandine';
        dash.appendChild(h2);

        /* notify bar */
        var msg = document.createElement('div'); msg.id = 'fvw-loc-msg'; msg.className = 'msg'; msg.style.display = 'none';
        dash.appendChild(msg);

        /* Left Drawer Modal Container */
        var backdrop = document.createElement('div');
        backdrop.id = 'fvw-loc-backdrop';
        backdrop.className = 'fvw-drawer-backdrop';
        backdrop.addEventListener('click', closeModal);
        dash.appendChild(backdrop);

        var drawer = document.createElement('div');
        drawer.id = 'fvw-loc-drawer';
        drawer.className = 'fvw-drawer-left';

        var drawerHeader = document.createElement('div');
        drawerHeader.className = 'fvw-drawer-header';
        
        var drawerTitle = document.createElement('h3');
        drawerTitle.id = 'fvw-drawer-title';
        drawerTitle.textContent = 'Modifica Elemento';
        drawerHeader.appendChild(drawerTitle);

        var closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'wv-btn wv-btn-sm';
        closeBtn.textContent = '✕ Chiudi';
        closeBtn.addEventListener('click', closeModal);
        drawerHeader.appendChild(closeBtn);

        drawer.appendChild(drawerHeader);

        var drawerBody = document.createElement('div');
        drawerBody.id = 'fvw-drawer-body';
        drawerBody.className = 'fvw-drawer-body';
        drawer.appendChild(drawerBody);

        dash.appendChild(drawer);

        /* three-column layout */
        var grid = document.createElement('div'); grid.className = 'fvw-loc-grid';

        /* Colonna 1: Form opzioni e sintesi */
        var col1 = document.createElement('div'); col1.id = 'fvw-loc-form'; col1.className = 'fvw-loc-col-1';
        grid.appendChild(col1);

        /* Colonna 2: Canvas Anteprima (Sticky) */
        var col2 = document.createElement('div'); col2.className = 'fvw-loc-col-2';
        var canvasWrap = document.createElement('div'); canvasWrap.className = 'fvw-loc-canvas-wrap';
        var cv = document.createElement('canvas'); cv.id = 'fvw-loc-canvas'; cv.className = 'fvw-loc-canvas';
        canvasWrap.appendChild(cv);
        col2.appendChild(canvasWrap);
        grid.appendChild(col2);

        /* Colonna 3: Modelli realizzati / salvati (Grid 2 colonne) */
        var col3 = document.createElement('div'); col3.className = 'fvw-loc-col-3';
        var modSec = document.createElement('div'); modSec.className = 'fvw-models-section';
        var modH3 = document.createElement('h3'); modH3.textContent = 'Modelli Salvati';
        var modList = document.createElement('div'); modList.id = 'fvw-models';
        modSec.appendChild(modH3); modSec.appendChild(modList);
        col3.appendChild(modSec);
        grid.appendChild(col3);

        dash.appendChild(grid);
        root.appendChild(dash);

        rebuildForm();
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(function() {
                requestRenderCanvas();
            });
        }
        setTimeout(requestRenderCanvas, 150);
        setTimeout(requestRenderCanvas, 600);
        loadEvents();
        loadModels();
    }

    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', mount); }
    else { mount(); }
    setTimeout(mount, 400);
})();
