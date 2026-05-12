<!DOCTYPE html>
<html lang="sr-Latn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1e3a5f">
    <title>Limo evidencija</title>
    @vite(['resources/js/limo-jsqr.js'])
    <style>
        :root {
            --bg: #f0f4f8;
            --card: #fff;
            --text: #1a1a1a;
            --muted: #5c6b7a;
            --primary: #1e5a8e;
            --primary-hover: #174a73;
            --danger: #b91c1c;
            --success: #15803d;
            --radius: 12px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.45;
            min-height: 100dvh;
            padding: max(12px, env(safe-area-inset-top)) 16px max(20px, env(safe-area-inset-bottom));
        }
        .wrap { max-width: 480px; margin: 0 auto; }
        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 0 1rem;
            text-align: center;
        }
        .card {
            background: var(--card);
            border-radius: var(--radius);
            padding: 1rem 1.1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
            margin-bottom: 1rem;
        }
        label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.35rem;
            color: var(--muted);
        }
        input[type="text"], select, textarea {
            width: 100%;
            font-size: 1.05rem;
            padding: 0.75rem 0.85rem;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            margin-bottom: 0.75rem;
            font-family: inherit;
        }
        textarea { min-height: 5rem; resize: vertical; }
        input:focus, select:focus, textarea:focus {
            outline: 2px solid var(--primary);
            border-color: var(--primary);
        }
        .hint.warn { color: #9a3412; font-weight: 600; }
        .btn {
            display: block;
            width: 100%;
            font-size: 1.1rem;
            font-weight: 600;
            padding: 0.95rem 1rem;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            text-align: center;
            margin-bottom: 0.65rem;
        }
        .btn:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:not(:disabled):active { background: var(--primary-hover); }
        .btn-secondary { background: #e2e8f0; color: var(--text); }
        .btn-danger { background: #fecaca; color: var(--danger); }
        #scanWrap {
            position: relative;
            border-radius: var(--radius);
            overflow: hidden;
            background: #000;
            width: 100%;
            min-height: 240px;
            aspect-ratio: 4 / 3;
            margin-bottom: 0.65rem;
            display: none;
            flex-direction: column;
        }
        #scanWrap.active {
            display: flex;
        }
        #scanVideo {
            width: 100%;
            flex: 1;
            min-height: 200px;
            object-fit: cover;
            vertical-align: middle;
            background: #000;
        }
        #status {
            font-size: 0.95rem;
            padding: 0.75rem;
            border-radius: 10px;
            margin-top: 0.5rem;
            white-space: pre-wrap;
            word-break: break-word;
        }
        #status.info { background: #e0f2fe; color: #0369a1; }
        #status.ok { background: #dcfce7; color: var(--success); }
        #status.err { background: #fee2e2; color: var(--danger); }
        #status:empty { display: none; }
        .hint { font-size: 0.85rem; color: var(--muted); margin-top: 0.25rem; }
        .success-detail { font-size: 0.9rem; margin-top: 0.5rem; }
        .section-title { font-size: 1.1rem; margin: 0 0 0.65rem; font-weight: 700; }
        .instr-list { margin: 0.45rem 0 0 1.1rem; padding: 0; }
        .instr-list li { margin-bottom: 0.25rem; }
        #platePreviewImg { display: block; max-width: 100%; border-radius: 10px; margin-bottom: 0.65rem; }
        /* TEMP: raw getUserMedia diagnostic — HTML block only when APP_DEBUG; class used so CSS does not leak debug id */
        .limo-debug-camera-card .limo-debug-preview-wrap {
            display: none;
            border-radius: var(--radius);
            overflow: hidden;
            background: #000;
            width: 100%;
            min-height: 200px;
            aspect-ratio: 4 / 3;
            margin-bottom: 0.65rem;
        }
        .limo-debug-camera-video {
            width: 100%;
            height: 100%;
            min-height: 200px;
            object-fit: cover;
            vertical-align: middle;
        }
        .limo-debug-camera-status {
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            word-break: break-word;
        }
        .limo-debug-trace-pre {
            font-size: 0.75rem;
            background: #1e293b;
            color: #e2e8f0;
            padding: 0.5rem;
            border-radius: 8px;
            max-height: 140px;
            overflow: auto;
            white-space: pre-wrap;
            word-break: break-word;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Limo evidencija</h1>

        @if(config('app.debug'))
        <div class="card limo-debug-camera-card" id="limoDebugCameraSection" style="border: 2px dashed #ca8a04;">
            <h2 class="section-title" style="color:#92400e;">DEBUG — test kamere</h2>
            <p class="hint warn">Privremeno: vidljivo samo kada je <code>APP_DEBUG=true</code>. Ne šalje ništa na server.</p>
            <button type="button" class="btn btn-secondary" id="limoDebugBtnStartCamera">Test kamera</button>
            <button type="button" class="btn btn-danger" id="limoDebugBtnStopCamera" style="display:none;">Zaustavi test kameru</button>
            <p id="limoDebugCameraStatus" class="limo-debug-camera-status" style="display:none;" role="status" aria-live="polite"></p>
            <div id="limoDebugCameraPreviewWrap" class="limo-debug-preview-wrap">
                <video id="limoDebugCameraVideo" class="limo-debug-camera-video" autoplay playsinline muted></video>
            </div>
            <h3 class="section-title" style="color:#92400e;margin-top:1rem;">DEBUG — Potvrdi dolazak (fetch)</h3>
            <p class="hint">Linije se dodaju pri tapu na „Potvrdi dolazak”; nema tokena u tekstu.</p>
            <pre id="limoDebugSubmitTrace" class="limo-debug-trace-pre" aria-live="polite"></pre>
            <h3 class="section-title" style="color:#92400e;margin-top:1rem;">DEBUG — tablica (OCR upload)</h3>
            <p class="hint">Linije pri izboru/slanju fotografije tablice; nema binarnog sadržaja slike.</p>
            <pre id="limoDebugPlateTrace" class="limo-debug-trace-pre" aria-live="polite"></pre>
        </div>
        @endif

        <div class="card">
            <div id="scanWrap">
                <video id="scanVideo" autoplay playsinline muted></video>
            </div>
            <p id="noDetectorBanner" class="hint" style="display:none;" role="status" aria-live="polite"></p>
            <p id="secureContextHint" class="hint" style="display:none;" role="status"></p>

            <button type="button" class="btn btn-primary" id="btnScan">Skeniraj QR</button>
            <button type="button" class="btn btn-danger" id="btnStopScan" style="display:none;">Zaustavi kameru</button>

            <label for="tokenInput">QR token (ručno)</label>
            <input type="text" id="tokenInput" name="token" autocomplete="off" autocorrect="off" spellcheck="false" placeholder="Zalijepite ili unesite token" inputmode="text">
            <p class="hint">Ako skeniranje nije dostupno ili kamera nije dozvoljena, unesite vrijednost koju je agencija dobila pri generisanju QR-a.</p>

            <button type="button" class="btn btn-primary" id="btnSubmit">Potvrdi dolazak</button>
        </div>

        <div class="card" id="plateSection">
            <h2 class="section-title">Bez QR koda</h2>
            <div class="hint" style="margin-bottom: 0.75rem;">
                Fotografišite tablicu tako da:
                <ul class="instr-list">
                    <li>tablica bude u sredini slike</li>
                    <li>zauzima većinu kadra</li>
                    <li>slika bude oštra</li>
                    <li>telefon bude što ravnije ispred tablice</li>
                    <li>izbjegavajte odsjaj, farove, mrak i veliku udaljenost</li>
                </ul>
                Ako sistem pogrešno pročita tablicu, ispravite je ručno prije potvrde.
            </div>
            <input type="file" id="plateFileInput" accept="image/jpeg,image/png,image/webp" capture="environment" style="display:none;">
            <input type="file" id="plateGalleryInput" accept="image/jpeg,image/png,image/webp" style="display:none;">
            <button type="button" class="btn btn-secondary" id="btnPlateSnap">Nema QR? Slikaj tablicu</button>
            <button type="button" class="btn btn-secondary" id="btnPlateGallery">Izaberi sliku iz galerije</button>
            <div id="plateCropStage" style="display:none;margin-top:0.75rem;">
                <p class="hint">Povucite pravokutnik oko tablice (opciono). Ako ne označite, šalje se cijela slika.</p>
                <div id="plateCropBox" style="position:relative;display:inline-block;max-width:100%;vertical-align:top;touch-action:none;cursor:crosshair;">
                    <img id="plateCropImg" alt="Izbor tablice" style="max-width:100%;height:auto;display:block;user-select:none;-webkit-user-drag:none;pointer-events:none;">
                    <div id="plateCropShade" style="display:none;position:absolute;left:0;top:0;right:0;bottom:0;background:rgba(0,0,0,0.35);pointer-events:none;"></div>
                    <div id="plateCropRect" style="display:none;position:absolute;border:2px solid #22c55e;box-sizing:border-box;pointer-events:none;z-index:2;"></div>
                </div>
                <div style="margin-top:0.5rem;display:flex;flex-wrap:wrap;gap:0.5rem;">
                    <button type="button" class="btn btn-primary" id="btnPlateCropSend">Pošalji za OCR</button>
                    <button type="button" class="btn btn-secondary" id="btnPlateCropClear">Cijela slika (bez izreza)</button>
                    <button type="button" class="btn btn-secondary" id="btnPlateCropCancel">Otkaži</button>
                </div>
            </div>
            <div id="platePreviewWrap" style="display:none;margin-top:0.75rem;">
                <img id="platePreviewImg" alt="Pregled fotografije tablice" width="600" height="400">
                <label for="plateConfirmInput">Potvrđena registarska tablica</label>
                <input type="text" id="plateConfirmInput" data-limo-plate-input="1" inputmode="text" autocapitalize="characters" autocomplete="off" autocorrect="off" spellcheck="false" placeholder="npr. PG1234AB">
                <p class="hint">OCR prijedlog je samo prijedlog — uvijek provjerite i ispravite ručno prije potvrde.</p>
                <button type="button" class="btn btn-primary" id="btnPlateConfirm" disabled>Potvrdi tablicu</button>
                <button type="button" class="btn btn-secondary" id="btnPlateReset">Počni ponovo</button>
            </div>
        </div>

        <div class="card" id="incidentSection">
            <h2 class="section-title">Prijavi incident</h2>
            <p class="hint">Incident prijavite samo kada postoji osnov sumnje da se Limo pickup obavlja bez plaćanja. Fotografija tablice je obavezna.</p>
            <p class="hint warn">Ako vozilo <strong>nije</strong> u voznom parku nijedne agencije i <strong>ne vidi se</strong> ime agencije / brending na vozilu, <strong>ne prijavljujte</strong> incident (u zoni mogu biti i privatna vozila).</p>
            <p class="hint warn" id="incidentUnregisteredWarn" style="display:none;">Za tip „Neregistrovano vozilo — vidljiv brending“ po mogućnosti fotografišite i tablicu i vidljivo označavanje agencije.</p>

            <label for="incidentType">Tip incidenta</label>
            <select id="incidentType" name="incident_type">
                <option value="qr_insufficient_funds">Nedovoljan avans (QR postoji)</option>
                <option value="plate_insufficient_funds">Nedovoljan avans (bez QR, tablica u sistemu)</option>
                <option value="unregistered_vehicle_with_branding">Neregistrovano vozilo — vidljiv brending agencije</option>
                <option value="invalid_qr_token">QR nevažeći / istekao / ponovo korišćen</option>
                <option value="driver_non_cooperative">Vozač ne saradjuje</option>
            </select>

            <label>Fotografija tablice (obavezno)</label>
            <input type="file" id="incidentPlateFile" accept="image/jpeg,image/png,image/webp" capture="environment" style="display:none;">
            <input type="file" id="incidentPlateGallery" accept="image/jpeg,image/png,image/webp" style="display:none;">
            <button type="button" class="btn btn-secondary" id="btnIncidentPlateCam">Slikaj tablicu</button>
            <button type="button" class="btn btn-secondary" id="btnIncidentPlateGallery">Tablica iz galerije</button>
            <p class="hint" id="incidentPlatePicked" style="display:none;"></p>

            <label>Fotografija brendinga (opciono)</label>
            <input type="file" id="incidentBrandingFile" accept="image/jpeg,image/png,image/webp" capture="environment" style="display:none;">
            <input type="file" id="incidentBrandingGallery" accept="image/jpeg,image/png,image/webp" style="display:none;">
            <button type="button" class="btn btn-secondary" id="btnIncidentBrandingCam">Slikaj brending</button>
            <button type="button" class="btn btn-secondary" id="btnIncidentBrandingGallery">Brending iz galerije</button>
            <p class="hint" id="incidentBrandingPicked" style="display:none;"></p>

            <label for="incidentPlateText">Registarska tablica (ako je poznata)</label>
            <input type="text" id="incidentPlateText" data-limo-plate-input="1" inputmode="text" autocapitalize="characters" autocomplete="off" autocorrect="off" spellcheck="false" placeholder="npr. PG1234AB">

            <label for="incidentVisibleAgency">Vidljivo ime agencije na vozilu</label>
            <input type="text" id="incidentVisibleAgency" autocomplete="off" placeholder="Ako se vidi na vozilu">

            <label for="incidentNote">Napomena</label>
            <textarea id="incidentNote" placeholder="Kratko stanje na licu mjesta"></textarea>

            <button type="button" class="btn btn-primary" id="btnIncidentSubmit">Pošalji prijavu</button>
        </div>

        <div id="status" role="status" aria-live="polite"></div>
    </div>

    <script>
(function () {
    var APP_DEBUG = @json(config('app.debug'));
    /** Isti origin kao stranica — izbjegava APP_URL / proxy probleme na mobilnom. */
    var pickupQrUrl = '/limo/pickup/qr';
    var plateOcrUrl = '/limo/pickup/plate/ocr';
    var plateConfirmUrl = '/limo/pickup/plate/confirm';
    const incidentUrl = @json(route('limo.incident.store'));

    const btnScan = document.getElementById('btnScan');
    const btnStopScan = document.getElementById('btnStopScan');
    const btnSubmit = document.getElementById('btnSubmit');
    const scanWrap = document.getElementById('scanWrap');
    const scanVideo = document.getElementById('scanVideo');
    const tokenInput = document.getElementById('tokenInput');
    const statusEl = document.getElementById('status');
    const noDetectorBanner = document.getElementById('noDetectorBanner');
    const secureContextHint = document.getElementById('secureContextHint');
    const btnPlateSnap = document.getElementById('btnPlateSnap');
    const btnPlateGallery = document.getElementById('btnPlateGallery');
    const plateFileInput = document.getElementById('plateFileInput');
    const plateGalleryInput = document.getElementById('plateGalleryInput');
    const platePreviewWrap = document.getElementById('platePreviewWrap');
    const platePreviewImg = document.getElementById('platePreviewImg');
    const plateCropStage = document.getElementById('plateCropStage');
    const plateCropBox = document.getElementById('plateCropBox');
    const plateCropImg = document.getElementById('plateCropImg');
    const plateCropShade = document.getElementById('plateCropShade');
    const plateCropRect = document.getElementById('plateCropRect');
    const btnPlateCropSend = document.getElementById('btnPlateCropSend');
    const btnPlateCropClear = document.getElementById('btnPlateCropClear');
    const btnPlateCropCancel = document.getElementById('btnPlateCropCancel');
    const plateConfirmInput = document.getElementById('plateConfirmInput');
    const btnPlateConfirm = document.getElementById('btnPlateConfirm');
    const btnPlateReset = document.getElementById('btnPlateReset');

    const incidentType = document.getElementById('incidentType');
    const incidentUnregisteredWarn = document.getElementById('incidentUnregisteredWarn');
    const incidentPlateFile = document.getElementById('incidentPlateFile');
    const incidentPlateGallery = document.getElementById('incidentPlateGallery');
    const btnIncidentPlateCam = document.getElementById('btnIncidentPlateCam');
    const btnIncidentPlateGallery = document.getElementById('btnIncidentPlateGallery');
    const incidentPlatePicked = document.getElementById('incidentPlatePicked');
    const incidentBrandingFile = document.getElementById('incidentBrandingFile');
    const incidentBrandingGallery = document.getElementById('incidentBrandingGallery');
    const btnIncidentBrandingCam = document.getElementById('btnIncidentBrandingCam');
    const btnIncidentBrandingGallery = document.getElementById('btnIncidentBrandingGallery');
    const incidentBrandingPicked = document.getElementById('incidentBrandingPicked');
    const incidentPlateText = document.getElementById('incidentPlateText');
    const incidentVisibleAgency = document.getElementById('incidentVisibleAgency');
    const incidentNote = document.getElementById('incidentNote');
    const btnIncidentSubmit = document.getElementById('btnIncidentSubmit');

    let incidentPlateBlob = null;
    let incidentBrandingBlob = null;
    let incidentSubmitting = false;

    let mediaStream = null;
    let scanRaf = null;
    let scanning = false;
    let submitting = false;

    let plateUploadToken = null;
    let platePreviewObjectUrl = null;
    let plateCropObjectUrl = null;
    let plateUploading = false;
    let plateConfirming = false;
    let platePendingFile = null;
    let plateCropDragging = false;
    let plateCropStart = null;
    let plateCropCurrent = null;
    let plateCropUsesManualRect = false;

    const errMessages = {
        invalid_token: 'QR kod nije važeći.',
        token_already_used: 'QR kod je već iskorišćen.',
        insufficient_advance: 'Agencija nema dovoljno avansa.',
        validation_error: 'Došlo je do greške. Pokušajte ponovo.',
        plate_not_registered: 'Tablica nije pronađena u voznom parku nijedne agencije.',
        invalid_upload: 'Fotografija više nije važeća. Pokušajte ponovo.',
        generic: 'Došlo je do greške. Pokušajte ponovo.',
    };
    const genericErr = 'Došlo je do greške. Pokušajte ponovo.';
    let qrDecoderLoadTimedOut = false;

    function readCsrfToken() {
        var m = document.querySelector('meta[name="csrf-token"]');
        if (!m) return '';
        var c = m.getAttribute('content');
        return typeof c === 'string' ? c : '';
    }

    /** Samo UI: velika slova, samo A–Z i 0–9; backend i dalje normalizuje pri potvrdi. */
    function normalizePlateInputValue(raw) {
        return String(raw || '')
            .toUpperCase()
            .replace(/[^A-Z0-9]/g, '');
    }

    function attachLimoPlateInputBehavior(el) {
        if (!el) return;
        function apply() {
            var cur = el.value;
            var next = normalizePlateInputValue(cur);
            if (cur !== next) {
                el.value = next;
            }
        }
        el.addEventListener('input', apply);
        el.addEventListener('blur', apply);
    }

    attachLimoPlateInputBehavior(plateConfirmInput);
    attachLimoPlateInputBehavior(incidentPlateText);

    function debugTraceAppend(elementId, msg) {
        if (!APP_DEBUG) return;
        var el = document.getElementById(elementId);
        if (!el) return;
        var t = new Date();
        var pad = function (n) { return (n < 10 ? '0' : '') + n; };
        var stamp = pad(t.getHours()) + ':' + pad(t.getMinutes()) + ':' + pad(t.getSeconds());
        el.textContent += stamp + ' ' + String(msg) + '\n';
        el.scrollTop = el.scrollHeight;
    }

    function debugSubmitLine(msg) {
        debugTraceAppend('limoDebugSubmitTrace', msg);
    }

    function debugPlateLine(msg) {
        debugTraceAppend('limoDebugPlateTrace', msg);
    }

    /** Čitljiv sažetak OCR odgovora (samo APP_DEBUG; podaci sa servera, bez slike). */
    function appendPlateOcrDebugSummary(data) {
        if (!APP_DEBUG || !data || !data.debug) {
            return;
        }
        var d = data.debug;
        debugPlateLine('—— OCR sažetak (server) ——');
        if (d.ocr_used_user_crop != null) {
            debugPlateLine('OCR izrez: ' + (d.ocr_used_user_crop ? 'da' : 'ne') +
                ' | dim. izreza: ' + (d.ocr_crop_width_px != null ? String(d.ocr_crop_width_px) : '—') + '×' +
                (d.ocr_crop_height_px != null ? String(d.ocr_crop_height_px) : '—'));
        }
        var attempts = d.variant_attempts;
        if (attempts && attempts.length) {
            for (var i = 0; i < attempts.length; i++) {
                var a = attempts[i] || {};
                var rawP = a.raw_preview != null ? String(a.raw_preview) : '';
                var normP = a.normalized_preview != null ? String(a.normalized_preview) : '';
                var cand = a.candidate != null ? String(a.candidate) : 'null';
                var err = a.error != null ? String(a.error) : '';
                debugPlateLine(
                    '[' + String(i + 1) + '] ' + String(a.variant || '') +
                        ' psm=' + String(a.psm != null ? a.psm : '') +
                        ' raw="' + rawP.slice(0, 140) + '"' +
                        ' norm="' + normP.slice(0, 100) + '"' +
                        ' candidate=' + cand +
                        (err ? ' error="' + err.slice(0, 120) + '"' : '')
                );
            }
        } else {
            debugPlateLine('(nema variant_attempts u odgovoru)');
        }
        debugPlateLine(
            'Konačno: selected_candidate=' + (d.selected_candidate != null ? String(d.selected_candidate) : 'null') +
                ' | variant=' + String(d.selected_variant || '—') +
                ' | psm=' + (d.selected_psm != null ? String(d.selected_psm) : '—') +
                ' | reason=' + String(d.reason || '') +
                ' | early_exit=' + (d.early_exit ? 'yes' : 'no')
        );
        debugPlateLine('—— kraj OCR sažetka ——');
    }

    function httpStatusUserMessage(status) {
        if (status === 419) {
            return 'Sesija je istekla (419). Osvježite stranicu (F5) i prijavite se ponovo.';
        }
        if (status === 401) {
            return 'Niste prijavljeni (401). Osvježite stranicu i prijavite se.';
        }
        if (status === 422) {
            return 'Zahtjev nije prihvaćen (422). Provjerite podatke i pokušajte ponovo.';
        }
        if (status === 403) {
            return 'Pristup odbijen (403).';
        }
        if (status === 404) {
            return 'Adresa nije pronađena (404).';
        }
        if (status >= 500) {
            return 'Greška na serveru (' + status + '). Pokušajte kasnije.';
        }
        return '';
    }

    async function parseResponseJson(res) {
        var raw = await res.text().catch(function () { return ''; });
        if (!raw || typeof raw !== 'string' || !raw.trim()) {
            return {};
        }
        try {
            return JSON.parse(raw);
        } catch (e) {
            return {};
        }
    }

    function appendDebugHttpStatus(msg, httpStatus) {
        if (!APP_DEBUG || httpStatus == null) {
            return msg;
        }
        return String(msg) + ' (HTTP ' + String(httpStatus) + ')';
    }

    function hasNativeQr() {
        return 'BarcodeDetector' in window;
    }
    function hasJsQrDecoder() {
        return typeof window.jsQR === 'function';
    }
    function isMobileCoarse() {
        var ua = navigator.userAgent || '';
        if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(ua)) {
            return true;
        }
        return typeof navigator.maxTouchPoints === 'number' && navigator.maxTouchPoints > 2;
    }
    /** Na mobilnom ne koristimo BarcodeDetector u petlji (nepouzdan preview); desktop može koristiti nativni API kad postoji. */
    function useBarcodeDetectorInLoop() {
        return hasNativeQr() && !isMobileCoarse();
    }
    function canUseQrCamera() {
        if (useBarcodeDetectorInLoop()) return true;
        return hasJsQrDecoder();
    }

    function syncQrUi() {
        var busy = submitting || plateUploading || plateConfirming;
        if (!busy) {
            btnScan.disabled = !canUseQrCamera();
        }
        if (canUseQrCamera()) {
            noDetectorBanner.style.display = 'none';
            btnScan.title = '';
            return;
        }
        if (qrDecoderLoadTimedOut) {
            noDetectorBanner.style.display = 'block';
            noDetectorBanner.textContent = 'Automatsko skeniranje QR-a nije dostupno. Koristite polje ispod za ručni unos tokena.';
            btnScan.title = 'Skeniranje nije dostupno';
            return;
        }
        noDetectorBanner.style.display = 'block';
        noDetectorBanner.textContent = 'Učitavanje QR dekodera…';
        if (!busy) {
            btnScan.disabled = true;
        }
    }

    function setStatus(html, kind) {
        statusEl.className = kind || '';
        statusEl.innerHTML = html;
    }

    function revokePlatePreview() {
        if (platePreviewObjectUrl) {
            URL.revokeObjectURL(platePreviewObjectUrl);
            platePreviewObjectUrl = null;
        }
    }

    function revokePlateCropPreview() {
        if (plateCropObjectUrl) {
            URL.revokeObjectURL(plateCropObjectUrl);
            plateCropObjectUrl = null;
        }
        if (plateCropImg) {
            plateCropImg.removeAttribute('src');
        }
    }

    function hidePlateCropRectUi() {
        plateCropUsesManualRect = false;
        plateCropDragging = false;
        plateCropStart = null;
        plateCropCurrent = null;
        if (plateCropRect) plateCropRect.style.display = 'none';
        if (plateCropShade) plateCropShade.style.display = 'none';
    }

    function resetPlateCropStage() {
        hidePlateCropRectUi();
        if (plateCropStage) plateCropStage.style.display = 'none';
        revokePlateCropPreview();
        platePendingFile = null;
    }

    function resetPlateFlow() {
        revokePlatePreview();
        resetPlateCropStage();
        plateUploadToken = null;
        platePreviewWrap.style.display = 'none';
        plateConfirmInput.value = '';
        plateFileInput.value = '';
        plateGalleryInput.value = '';
        platePreviewImg.removeAttribute('src');
        btnPlateConfirm.disabled = true;
    }

    function setPlateInputsBlocked(disabled) {
        btnPlateSnap.disabled = disabled;
        btnPlateGallery.disabled = disabled;
        btnPlateConfirm.disabled = disabled || !plateUploadToken;
        btnPlateReset.disabled = disabled;
    }

    async function stopCamera() {
        scanning = false;
        if (scanRaf) {
            cancelAnimationFrame(scanRaf);
            scanRaf = null;
        }
        if (mediaStream) {
            mediaStream.getTracks().forEach(function (t) { t.stop(); });
            mediaStream = null;
        }
        scanVideo.srcObject = null;
        scanWrap.classList.remove('active');
        btnStopScan.style.display = 'none';
    }

    window.addEventListener('pagehide', function () { void stopCamera(); });
    window.addEventListener('beforeunload', function () { void stopCamera(); });

    function syncIncidentUnregisteredWarn() {
        if (!incidentType || !incidentUnregisteredWarn) return;
        incidentUnregisteredWarn.style.display = incidentType.value === 'unregistered_vehicle_with_branding' ? 'block' : 'none';
    }
    if (incidentType) {
        incidentType.addEventListener('change', syncIncidentUnregisteredWarn);
        syncIncidentUnregisteredWarn();
    }

    function setIncidentPlateFile(f) {
        incidentPlateBlob = f || null;
        if (f && incidentPlatePicked) {
            incidentPlatePicked.style.display = 'block';
            incidentPlatePicked.textContent = 'Odabrano: ' + (f.name || 'fotografija');
        } else if (incidentPlatePicked) {
            incidentPlatePicked.style.display = 'none';
        }
    }
    function setIncidentBrandingFile(f) {
        incidentBrandingBlob = f || null;
        if (f && incidentBrandingPicked) {
            incidentBrandingPicked.style.display = 'block';
            incidentBrandingPicked.textContent = 'Odabrano: ' + (f.name || 'fotografija');
        } else if (incidentBrandingPicked) {
            incidentBrandingPicked.style.display = 'none';
        }
    }
    function onIncidentPlateChosen(ev) {
        var f = ev.target.files && ev.target.files[0];
        ev.target.value = '';
        if (f) setIncidentPlateFile(f);
    }
    function onIncidentBrandingChosen(ev) {
        var f = ev.target.files && ev.target.files[0];
        ev.target.value = '';
        if (f) setIncidentBrandingFile(f);
    }
    if (incidentPlateFile) incidentPlateFile.addEventListener('change', onIncidentPlateChosen);
    if (incidentPlateGallery) incidentPlateGallery.addEventListener('change', onIncidentPlateChosen);
    if (incidentBrandingFile) incidentBrandingFile.addEventListener('change', onIncidentBrandingChosen);
    if (incidentBrandingGallery) incidentBrandingGallery.addEventListener('change', onIncidentBrandingChosen);
    if (btnIncidentPlateCam) btnIncidentPlateCam.addEventListener('click', function () { incidentPlateFile.click(); });
    if (btnIncidentPlateGallery) btnIncidentPlateGallery.addEventListener('click', function () { incidentPlateGallery.click(); });
    if (btnIncidentBrandingCam) btnIncidentBrandingCam.addEventListener('click', function () { incidentBrandingFile.click(); });
    if (btnIncidentBrandingGallery) btnIncidentBrandingGallery.addEventListener('click', function () { incidentBrandingGallery.click(); });

    document.addEventListener('DOMContentLoaded', function () {
        window.addEventListener('limoJsQrReady', function () {
            syncQrUi();
        });
        syncQrUi();
        setTimeout(function () {
            if (!useBarcodeDetectorInLoop() && !hasJsQrDecoder()) {
                qrDecoderLoadTimedOut = true;
                syncQrUi();
            }
        }, 12000);
        if (!window.isSecureContext) {
            secureContextHint.style.display = 'block';
            secureContextHint.textContent = 'Kamera i lokacija obično zahtijevaju HTTPS (ili localhost). Ručni unos tokena radi i bez kamere.';
        }
    });

    function hasCameraApi() {
        return !!(navigator.mediaDevices && typeof navigator.mediaDevices.getUserMedia === 'function');
    }

    async function startVideoQrScan() {
        setStatus('Kamera se pokreće…', 'info');
        if (!useBarcodeDetectorInLoop() && !hasJsQrDecoder()) {
            setStatus('Skeniranje QR-a nije dostupno. Unesite token ručno.', 'info');
            return false;
        }
        try {
            mediaStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' } },
                audio: false,
            });
        } catch (e) {
            setStatus('Kamera nije dostupna / dozvola odbijena.', 'info');
            return false;
        }

        scanVideo.srcObject = mediaStream;
        scanWrap.classList.add('active');
        btnStopScan.style.display = 'block';
        try {
            await scanVideo.play();
        } catch (ePlay) {}

        scanning = true;
        setStatus('Kamera je pokrenuta, usmjerite je ka QR kodu.', 'info');

        var useNative = useBarcodeDetectorInLoop();
        var detector = useNative ? new BarcodeDetector({ formats: ['qr_code'] }) : null;
        var canvas = document.createElement('canvas');
        var ctx = canvas.getContext('2d', { willReadFrequently: true }) || canvas.getContext('2d');
        var frames = 0;
        var maxDecodeEdge = 960;

        function scheduleNext() {
            if (scanning) {
                scanRaf = requestAnimationFrame(tick);
            }
        }

        function applyDecodedPayload(payload) {
            if (!scanning || typeof payload !== 'string' || !payload) {
                return;
            }
            scanning = false;
            if (scanRaf) {
                cancelAnimationFrame(scanRaf);
                scanRaf = null;
            }
            tokenInput.value = payload;
            void stopCamera();
            submitting = false;
            btnSubmit.disabled = false;
            btnScan.disabled = !canUseQrCamera();
            syncQrUi();
            setStatus('QR pročitan. Provjerite i dodirnite „Potvrdi dolazak”.', 'info');
        }

        function tick() {
            if (!scanning) {
                return;
            }
            frames++;
            if (scanVideo.readyState < scanVideo.HAVE_ENOUGH_DATA) {
                scheduleNext();
                return;
            }
            if (frames % 3 !== 0) {
                scheduleNext();
                return;
            }
            var vw = scanVideo.videoWidth;
            var vh = scanVideo.videoHeight;
            if (vw < 2 || vh < 2) {
                scheduleNext();
                return;
            }
            var scale = Math.min(1, maxDecodeEdge / Math.max(vw, vh));
            var cw = Math.max(2, Math.floor(vw * scale));
            var ch = Math.max(2, Math.floor(vh * scale));
            canvas.width = cw;
            canvas.height = ch;
            ctx.drawImage(scanVideo, 0, 0, cw, ch);

            if (useNative && detector) {
                detector.detect(canvas).then(function (codes) {
                    if (!scanning) {
                        return;
                    }
                    if (codes && codes.length > 0 && codes[0].rawValue) {
                        applyDecodedPayload(codes[0].rawValue);
                        return;
                    }
                    scheduleNext();
                }).catch(function () {
                    scheduleNext();
                });
                return;
            }

            if (typeof window.jsQR === 'function') {
                try {
                    var imageData = ctx.getImageData(0, 0, cw, ch);
                    var result = window.jsQR(imageData.data, imageData.width, imageData.height, {
                        inversionAttempts: 'attemptBoth',
                    });
                    if (result && result.data) {
                        applyDecodedPayload(result.data);
                        return;
                    }
                } catch (eDec) {}
            }
            scheduleNext();
        }

        scanRaf = requestAnimationFrame(tick);
        return true;
    }

    async function startScan() {
        setStatus('', '');
        if (!hasCameraApi()) {
            setStatus('Kamera nije dostupna u ovom okruženju (često potreban HTTPS osim na localhost). Unesite token ručno.', 'info');
            return;
        }
        await startVideoQrScan();
    }

    btnScan.addEventListener('click', async function () {
        await stopCamera();
        await startScan();
    });

    btnStopScan.addEventListener('click', async function () {
        await stopCamera();
        setStatus('Kamera zaustavljena.', 'info');
    });

    function buildDeviceInfo() {
        return JSON.stringify({
            userAgent: navigator.userAgent,
            platform: navigator.platform || '',
            viewport: window.innerWidth + 'x' + window.innerHeight,
            ts: Date.now(),
        });
    }

    function getPositionBestEffort() {
        return new Promise(function (resolve) {
            var settled = false;
            function fin(v) {
                if (settled) return;
                settled = true;
                resolve(v);
            }
            setTimeout(function () {
                fin({ lat: null, lng: null });
            }, 8500);
            if (!navigator.geolocation) {
                fin({ lat: null, lng: null });
                return;
            }
            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    fin({ lat: pos.coords.latitude, lng: pos.coords.longitude });
                },
                function () {
                    fin({ lat: null, lng: null });
                },
                { enableHighAccuracy: true, timeout: 7000, maximumAge: 60000 }
            );
        });
    }

    btnSubmit.addEventListener('click', async function () {
        debugSubmitLine('submit: click');
        if (submitting) {
            setStatus('Sačekajte da se završi prethodni zahtjev, zatim pokušajte ponovo.', 'info');
            debugSubmitLine('submit: skipped (already submitting)');
            return;
        }
        var token = (tokenInput.value || '').trim();
        if (!token) {
            setStatus('Unesite ili skenirajte QR token prije potvrde.', 'err');
            debugSubmitLine('submit: aborted (empty token field)');
            return;
        }
        var csrfHdr = readCsrfToken();
        if (!csrfHdr) {
            setStatus('Greška: nedostaje CSRF meta oznaka na stranici. Osvježite stranicu (F5).', 'err');
            debugSubmitLine('submit: aborted (no csrf meta)');
            return;
        }

        await stopCamera();
        submitting = true;
        btnSubmit.disabled = true;
        btnScan.disabled = true;
        setPlateInputsBlocked(true);
        debugSubmitLine('submit: started (after stopCamera)');
        setStatus('Čekam lokaciju (najviše ~8 s)…', 'info');

        var coords;
        try {
            coords = await getPositionBestEffort();
        } catch (eGeo) {
            coords = { lat: null, lng: null };
        }
        debugSubmitLine('submit: geolocation step finished');

        var body = {
            token: token,
            gps_lat: coords.lat,
            gps_lng: coords.lng,
            device_info: buildDeviceInfo(),
        };

        setStatus('Slanje…', 'info');
        debugSubmitLine('submit: fetch sending to ' + pickupQrUrl);

        try {
            var res = await fetch(pickupQrUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfHdr,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(body),
            });
            debugSubmitLine('submit: fetch HTTP status ' + String(res.status));

            var data = await res.json().catch(function () { return {}; });

            if (res.ok && data.status === 'ok') {
                tokenInput.value = '';
                var bal = data.remaining_balance != null ? String(data.remaining_balance) : '—';
                setStatus(
                    '<strong>Dolazak potvrđen.</strong>' +
                    '<div class="success-detail">Transakcija: <code>' + escapeHtml(data.merchant_transaction_id) + '</code></div>' +
                    '<div class="success-detail">Preostali avans: <strong>' + escapeHtml(bal) + ' EUR</strong></div>',
                    'ok'
                );
            } else {
                var code = data.code;
                var msg = errMessages[code] || data.message || genericErr;
                setStatus(msg, 'err');
                debugSubmitLine('submit: response not ok (code in JSON body)');
            }
        } catch (e) {
            setStatus(genericErr + ' (' + (e && e.name ? e.name : 'mreža') + ')', 'err');
            debugSubmitLine('submit: fetch threw');
        } finally {
            submitting = false;
            btnSubmit.disabled = false;
            syncQrUi();
            setPlateInputsBlocked(false);
            debugSubmitLine('submit: finished (state reset)');
        }
    });

    btnPlateSnap.addEventListener('click', function () {
        plateFileInput.click();
    });
    btnPlateGallery.addEventListener('click', function () {
        plateGalleryInput.click();
    });

    function onPlateFileChosen(ev) {
        var f = ev.target.files && ev.target.files[0];
        ev.target.value = '';
        if (f) {
            startPlateCropFlow(f);
        } else {
            setStatus('Nije izabrana fotografija.', 'info');
            debugPlateLine('plate: change event, no file (otkazano?)');
        }
    }
    plateFileInput.addEventListener('change', onPlateFileChosen);
    plateGalleryInput.addEventListener('change', onPlateFileChosen);

    btnPlateReset.addEventListener('click', function () {
        resetPlateFlow();
        setStatus('', '');
    });

    function clampPlate(n, lo, hi) {
        return Math.max(lo, Math.min(hi, n));
    }

    function plateCropLocalPoint(ev) {
        if (!plateCropBox || !plateCropImg.naturalWidth) {
            return null;
        }
        var r = plateCropBox.getBoundingClientRect();
        var cx = ev.clientX != null ? ev.clientX : (ev.touches && ev.touches[0] ? ev.touches[0].clientX : 0);
        var cy = ev.clientY != null ? ev.clientY : (ev.touches && ev.touches[0] ? ev.touches[0].clientY : 0);
        var x = clampPlate(cx - r.left, 0, r.width);
        var y = clampPlate(cy - r.top, 0, r.height);
        return { x: x, y: y, bw: r.width, bh: r.height };
    }

    function updatePlateCropOverlay() {
        if (!plateCropRect || !plateCropShade || !plateCropStart || !plateCropCurrent) {
            return;
        }
        var x0 = Math.min(plateCropStart.x, plateCropCurrent.x);
        var y0 = Math.min(plateCropStart.y, plateCropCurrent.y);
        var w = Math.abs(plateCropCurrent.x - plateCropStart.x);
        var h = Math.abs(plateCropCurrent.y - plateCropStart.y);
        var minPx = Math.min(plateCropStart.bw, plateCropStart.bh) * 0.03;
        if (w < minPx || h < minPx) {
            plateCropRect.style.display = 'none';
            plateCropShade.style.display = 'none';
            return;
        }
        plateCropRect.style.display = 'block';
        plateCropShade.style.display = 'block';
        plateCropRect.style.left = x0 + 'px';
        plateCropRect.style.top = y0 + 'px';
        plateCropRect.style.width = w + 'px';
        plateCropRect.style.height = h + 'px';
    }

    function computePlateCropBasisPoints() {
        if (!plateCropUsesManualRect || !plateCropStart || !plateCropCurrent || !plateCropImg.naturalWidth) {
            return null;
        }
        var bw = plateCropBox.clientWidth;
        var bh = plateCropBox.clientHeight;
        if (bw < 2 || bh < 2) {
            return null;
        }
        var x0 = Math.min(plateCropStart.x, plateCropCurrent.x);
        var y0 = Math.min(plateCropStart.y, plateCropCurrent.y);
        var w = Math.abs(plateCropCurrent.x - plateCropStart.x);
        var h = Math.abs(plateCropCurrent.y - plateCropStart.y);
        var minPx = Math.min(bw, bh) * 0.03;
        if (w < minPx || h < minPx) {
            return null;
        }
        var leftBp = Math.round((x0 / bw) * 10000);
        var topBp = Math.round((y0 / bh) * 10000);
        var widthBp = Math.round((w / bw) * 10000);
        var heightBp = Math.round((h / bh) * 10000);
        leftBp = clampPlate(leftBp, 0, 9999);
        topBp = clampPlate(topBp, 0, 9999);
        widthBp = clampPlate(widthBp, 100, 10000 - leftBp);
        heightBp = clampPlate(heightBp, 100, 10000 - topBp);
        return { left: leftBp, top: topBp, width: widthBp, height: heightBp };
    }

    function startPlateCropFlow(file) {
        if (!file) {
            return;
        }
        resetPlateCropStage();
        platePendingFile = file;
        plateCropStage.style.display = 'block';
        platePreviewWrap.style.display = 'none';
        plateCropObjectUrl = URL.createObjectURL(file);
        plateCropImg.src = plateCropObjectUrl;
        hidePlateCropRectUi();
        setStatus('Označite tablicu (opciono), zatim „Pošalji za OCR”.', 'info');
        debugPlateLine('plate-crop: stage opened');
    }

    if (plateCropBox) {
        plateCropBox.addEventListener('mousedown', function (ev) {
            if (!platePendingFile || plateUploading) {
                return;
            }
            var p = plateCropLocalPoint(ev);
            if (!p) {
                return;
            }
            plateCropDragging = true;
            plateCropStart = p;
            plateCropCurrent = p;
            plateCropUsesManualRect = false;
            ev.preventDefault();
        });
        plateCropBox.addEventListener('touchstart', function (ev) {
            if (!platePendingFile || plateUploading || !ev.touches || !ev.touches[0]) {
                return;
            }
            var p = plateCropLocalPoint(ev);
            if (!p) {
                return;
            }
            plateCropDragging = true;
            plateCropStart = p;
            plateCropCurrent = p;
            plateCropUsesManualRect = false;
        }, { passive: true });
    }
    document.addEventListener('mousemove', function (ev) {
        if (!plateCropDragging || !plateCropStart) {
            return;
        }
        var p = plateCropLocalPoint(ev);
        if (!p) {
            return;
        }
        plateCropCurrent = p;
        updatePlateCropOverlay();
    });
    document.addEventListener('mouseup', function () {
        if (!plateCropDragging || !plateCropStart || !plateCropCurrent || !plateCropBox) {
            plateCropDragging = false;
            return;
        }
        plateCropDragging = false;
        var bw = plateCropBox.clientWidth;
        var bh = plateCropBox.clientHeight;
        var minPx = Math.min(bw, bh) * 0.03;
        var w = Math.abs(plateCropCurrent.x - plateCropStart.x);
        var h = Math.abs(plateCropCurrent.y - plateCropStart.y);
        plateCropUsesManualRect = w >= minPx && h >= minPx;
        if (!plateCropUsesManualRect) {
            hidePlateCropRectUi();
        }
    });
    document.addEventListener('touchmove', function (ev) {
        if (!plateCropDragging || !plateCropStart || !ev.touches || !ev.touches[0]) {
            return;
        }
        var p = plateCropLocalPoint(ev);
        if (!p) {
            return;
        }
        plateCropCurrent = p;
        updatePlateCropOverlay();
    }, { passive: true });
    document.addEventListener('touchend', function () {
        if (!plateCropDragging || !plateCropStart || !plateCropCurrent || !plateCropBox) {
            plateCropDragging = false;
            return;
        }
        plateCropDragging = false;
        var bw = plateCropBox.clientWidth;
        var bh = plateCropBox.clientHeight;
        var minPx = Math.min(bw, bh) * 0.03;
        var w = Math.abs(plateCropCurrent.x - plateCropStart.x);
        var h = Math.abs(plateCropCurrent.y - plateCropStart.y);
        plateCropUsesManualRect = w >= minPx && h >= minPx;
        if (!plateCropUsesManualRect) {
            hidePlateCropRectUi();
        }
    });

    if (btnPlateCropSend) {
        btnPlateCropSend.addEventListener('click', function () {
            if (!platePendingFile || plateUploading) {
                return;
            }
            var bp = plateCropUsesManualRect ? computePlateCropBasisPoints() : null;
            void handlePlateImageUpload(platePendingFile, bp);
        });
    }
    if (btnPlateCropClear) {
        btnPlateCropClear.addEventListener('click', function () {
            hidePlateCropRectUi();
            debugPlateLine('plate-crop: cleared (full image)');
        });
    }
    if (btnPlateCropCancel) {
        btnPlateCropCancel.addEventListener('click', function () {
            resetPlateCropStage();
            setStatus('', '');
            debugPlateLine('plate-crop: cancelled');
        });
    }

    async function handlePlateImageUpload(file, cropBp) {
        debugPlateLine('plate: handler called');
        if (!file || !file.size) {
            setStatus('Nije izabrana valjana fotografija.', 'err');
            debugPlateLine('plate: abort (empty file)');
            return;
        }
        if (plateUploading) {
            setStatus('Slanje fotografije je već u toku.', 'info');
            debugPlateLine('plate: skip (plateUploading)');
            return;
        }
        if (submitting) {
            setStatus('Prvo završite potvrdu dolaska (QR), zatim pokušajte sa tablicom.', 'info');
            debugPlateLine('plate: skip (submitting)');
            return;
        }
        var csrfHdr = readCsrfToken();
        if (!csrfHdr) {
            setStatus('Greška: nedostaje CSRF meta oznaka. Osvježite stranicu (F5).', 'err');
            debugPlateLine('plate: abort (no csrf)');
            return;
        }

        plateUploading = true;
        setPlateInputsBlocked(true);
        btnSubmit.disabled = true;
        btnScan.disabled = true;
        setStatus('Fotografija je izabrana.', 'info');
        debugPlateLine('plate: file ok, bytes=' + String(file.size));

        try {
            setStatus('Čekam lokaciju (najviše ~8 s)…', 'info');
            var coords;
            try {
                coords = await getPositionBestEffort();
            } catch (eG) {
                coords = { lat: null, lng: null };
            }
            debugPlateLine('plate: geolocation step done');

            setStatus('Šaljem fotografiju…', 'info');
            var fd = new FormData();
            fd.append('image', file, file.name || 'plate.jpg');
            if (coords.lat != null) {
                fd.append('gps_lat', String(coords.lat));
            }
            if (coords.lng != null) {
                fd.append('gps_lng', String(coords.lng));
            }
            fd.append('device_info', buildDeviceInfo());
            if (cropBp && cropBp.left != null && cropBp.top != null && cropBp.width != null && cropBp.height != null) {
                fd.append('plate_crop_left', String(cropBp.left));
                fd.append('plate_crop_top', String(cropBp.top));
                fd.append('plate_crop_width', String(cropBp.width));
                fd.append('plate_crop_height', String(cropBp.height));
                debugPlateLine('plate-crop: bp ' + [cropBp.left, cropBp.top, cropBp.width, cropBp.height].join(','));
            }

            if (plateCropStage) {
                plateCropStage.style.display = 'none';
            }

            debugPlateLine('plate: fetch POST ' + plateOcrUrl);
            var res = await fetch(plateOcrUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfHdr,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: fd,
            });
            debugPlateLine('plate: HTTP status ' + String(res.status));

            var data = await parseResponseJson(res);
            if (APP_DEBUG) {
                debugPlateLine('plate: parsed status=' + String(data.status || '') + ' code=' + String(data.code || ''));
            }

            if (!res.ok) {
                var httpMsg = httpStatusUserMessage(res.status);
                var ocrErrBase = (data && data.message) || (data && data.code && errMessages[data.code]) || httpMsg || genericErr;
                setStatus(appendDebugHttpStatus(ocrErrBase, res.status), 'err');
                debugPlateLine('plate: response not OK, code=' + String((data && data.code) || '') + ' message=' + String((data && data.message) || '').slice(0, 200));
                resetPlateFlow();
                return;
            }

            if (!data || data.status !== 'ok') {
                var ec = data && data.code;
                setStatus(appendDebugHttpStatus((ec && errMessages[ec]) || (data && data.message) || genericErr, res.status), 'err');
                debugPlateLine('plate: JSON status not ok');
                resetPlateFlow();
                return;
            }

            plateUploadToken = data.upload_token;
            revokePlateCropPreview();
            if (plateCropStage) {
                plateCropStage.style.display = 'none';
            }
            platePendingFile = null;
            revokePlatePreview();
            platePreviewObjectUrl = URL.createObjectURL(file);
            platePreviewImg.src = platePreviewObjectUrl;
            platePreviewWrap.style.display = 'block';
            plateConfirmInput.value = normalizePlateInputValue(data.suggested_plate ? String(data.suggested_plate) : '');
            btnPlateConfirm.disabled = false;
            setStatus('Fotografija je obrađena. Provjerite tablicu.', 'info');
            debugPlateLine('plate: success');
            if (APP_DEBUG) {
                if (data.debug) {
                    var d = data.debug;
                    debugPlateLine('plate-ocr: OCR enabled: ' + (d.ocr_enabled ? 'yes' : 'no'));
                    debugPlateLine('plate-ocr: OCR runner available: ' + (d.ocr_available ? 'yes' : 'no'));
                    debugPlateLine('plate-ocr: agregat raw_preview: ' + String(d.raw_preview != null ? d.raw_preview : '').slice(0, 160));
                    debugPlateLine('plate-ocr: agregat normalized_preview: ' + String(d.normalized_preview != null ? d.normalized_preview : '').slice(0, 120));
                    debugPlateLine('plate-ocr: suggested_plate: ' + (data.suggested_plate != null ? String(data.suggested_plate) : 'null'));
                    if (d.failure_detail) {
                        debugPlateLine('plate-ocr: failure_detail: ' + String(d.failure_detail).slice(0, 160));
                    }
                    appendPlateOcrDebugSummary(data);
                } else {
                    debugPlateLine('plate-ocr: (nema debug objekta — server APP_DEBUG=false ili stariji odgovor)');
                }
            }
        } catch (e) {
            setStatus(genericErr + ' (' + (e && e.name ? e.name : 'mreža') + ')', 'err');
            debugPlateLine('plate: exception ' + (e && e.name ? e.name : ''));
            resetPlateFlow();
        } finally {
            plateUploading = false;
            btnSubmit.disabled = false;
            syncQrUi();
            setPlateInputsBlocked(false);
            if (plateUploadToken) {
                btnPlateConfirm.disabled = false;
            }
        }
    }

    btnPlateConfirm.addEventListener('click', async function () {
        debugPlateLine('plate-confirm: confirm clicked');
        if (plateConfirming) {
            setStatus('Potvrda tablice je već u toku.', 'info');
            return;
        }
        if (submitting) {
            setStatus('Prvo završite potvrdu dolaska (QR).', 'info');
            return;
        }
        if (plateUploading) {
            setStatus('Sačekajte da se završi slanje fotografije.', 'info');
            return;
        }
        var pv = (plateConfirmInput.value || '').trim();
        if (!plateUploadToken || !pv) {
            setStatus('Prvo učitajte fotografiju i unesite ili potvrdite tablicu.', 'err');
            return;
        }
        var csrfHdr = readCsrfToken();
        if (!csrfHdr) {
            setStatus('Greška: nedostaje CSRF meta oznaka. Osvježite stranicu (F5).', 'err');
            debugPlateLine('plate-confirm: no csrf');
            return;
        }

        plateConfirming = true;
        btnPlateConfirm.disabled = true;
        btnSubmit.disabled = true;
        btnScan.disabled = true;
        setPlateInputsBlocked(true);
        setStatus('Čekam lokaciju (najviše ~8 s)…', 'info');
        var coords;
        try {
            coords = await getPositionBestEffort();
        } catch (eG) {
            coords = { lat: null, lng: null };
        }
        setStatus('Šaljem potvrdu tablice…', 'info');
        var body = {
            upload_token: plateUploadToken,
            license_plate: pv,
            gps_lat: coords.lat,
            gps_lng: coords.lng,
            device_info: buildDeviceInfo(),
        };
        debugPlateLine('plate-confirm: confirm fetch sent');
        debugPlateLine('plate-confirm: fetch POST ' + plateConfirmUrl);
        try {
            var res = await fetch(plateConfirmUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfHdr,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(body),
            });
            debugPlateLine('plate-confirm: confirm HTTP status ' + String(res.status));

            var data = await parseResponseJson(res);
            if (APP_DEBUG) {
                debugPlateLine(
                    'plate-confirm: confirm response status=' +
                        String(data.status || '') +
                        ' code=' +
                        String(data.code || '') +
                        ' message=' +
                        String((data.message || '')).slice(0, 200)
                );
            }

            if (!res.ok) {
                var httpMsg = httpStatusUserMessage(res.status);
                var cErrBase = (data && data.message) || (data && data.code && errMessages[data.code]) || httpMsg || genericErr;
                setStatus(appendDebugHttpStatus(cErrBase, res.status), 'err');
                debugPlateLine('plate-confirm: error code=' + String((data && data.code) || '') + ' message=' + String((data && data.message) || '').slice(0, 200));
                return;
            }

            if (data && data.status === 'ok') {
                resetPlateFlow();
                var bal = data.remaining_balance != null ? String(data.remaining_balance) : '—';
                var txId = data.merchant_transaction_id != null ? escapeHtml(String(data.merchant_transaction_id)) : '—';
                setStatus(
                    '<strong>Limo pickup je evidentiran.</strong>' +
                    '<div class="success-detail">Transakcija: <code>' + txId + '</code></div>' +
                    '<div class="success-detail">Preostali avans: <strong>' + escapeHtml(bal) + ' EUR</strong></div>',
                    'ok'
                );
                debugPlateLine('plate-confirm: success merchant_tx=' + String((data.merchant_transaction_id || '')).slice(0, 36));
            } else {
                var pc = data && data.code;
                var cErrOk = (data && data.message) || (pc && errMessages[pc]) || genericErr;
                setStatus(appendDebugHttpStatus(cErrOk, res.status), 'err');
                debugPlateLine('plate-confirm: JSON not ok code=' + String(pc || '') + ' message=' + String((data && data.message) || '').slice(0, 200));
            }
        } catch (e) {
            setStatus(genericErr + ' (' + (e && e.name ? e.name : 'mreža') + ')', 'err');
            debugPlateLine('plate-confirm: thrown');
        } finally {
            plateConfirming = false;
            btnSubmit.disabled = false;
            syncQrUi();
            setPlateInputsBlocked(false);
            if (plateUploadToken) {
                btnPlateConfirm.disabled = false;
            }
        }
    });

    if (btnIncidentSubmit) {
        btnIncidentSubmit.addEventListener('click', async function () {
            if (incidentSubmitting || submitting || plateUploading || plateConfirming) return;
            if (!incidentPlateBlob) {
                setStatus('Za prijavu incidenta potrebna je fotografija tablice.', 'err');
                return;
            }
            incidentSubmitting = true;
            btnIncidentSubmit.disabled = true;
            setStatus('Slanje prijave incidenta…', 'info');
            var coords = await getPositionBestEffort();
            try {
                var fd = new FormData();
                fd.append('type', incidentType.value);
                fd.append('plate_photo', incidentPlateBlob, incidentPlateBlob.name || 'plate.jpg');
                if (incidentBrandingBlob) {
                    fd.append('branding_photo', incidentBrandingBlob, incidentBrandingBlob.name || 'branding.jpg');
                }
                var pt = (incidentPlateText.value || '').trim();
                if (pt) fd.append('license_plate', pt);
                var va = (incidentVisibleAgency.value || '').trim();
                if (va) fd.append('visible_agency_name', va);
                var nt = (incidentNote.value || '').trim();
                if (nt) fd.append('note', nt);
                if (coords.lat != null) fd.append('gps_lat', String(coords.lat));
                if (coords.lng != null) fd.append('gps_lng', String(coords.lng));
                fd.append('device_info', buildDeviceInfo());
                var res = await fetch(incidentUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': readCsrfToken(),
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: fd,
                });
                var data = await res.json().catch(function () { return {}; });
                if (res.ok && data.status === 'ok') {
                    var mailOk = data.communal_email_sent === true;
                    setStatus(
                        '<strong>Prijava incidenta poslata.</strong>' +
                        '<div class="success-detail">UUID: <code>' + escapeHtml(data.incident_uuid) + '</code></div>' +
                        '<div class="success-detail">Email Komunalnoj policiji: <strong>' + (mailOk ? 'poslat' : 'nije poslat (provjerite log)') + '</strong></div>',
                        'ok'
                    );
                    setIncidentPlateFile(null);
                    setIncidentBrandingFile(null);
                    incidentPlateText.value = '';
                    incidentVisibleAgency.value = '';
                    incidentNote.value = '';
                } else {
                    var ic = data.code;
                    setStatus((ic === 'validation_error' ? (data.message || 'Provjerite unos i fotografiju tablice.') : (data.message || genericErr)), 'err');
                }
            } catch (e) {
                setStatus(genericErr, 'err');
            } finally {
                incidentSubmitting = false;
                btnIncidentSubmit.disabled = false;
            }
        });
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
})();
    </script>
    @if(config('app.debug'))
    <script>
(function () {
    var btnStart = document.getElementById('limoDebugBtnStartCamera');
    if (!btnStart) return;
    var btnStop = document.getElementById('limoDebugBtnStopCamera');
    var videoEl = document.getElementById('limoDebugCameraVideo');
    var statusEl = document.getElementById('limoDebugCameraStatus');
    var previewWrap = document.getElementById('limoDebugCameraPreviewWrap');
    var debugStream = null;

    function setDbgStatus(text) {
        statusEl.textContent = text;
        statusEl.style.display = 'block';
    }

    function stopDebugCamera() {
        if (debugStream) {
            debugStream.getTracks().forEach(function (t) { t.stop(); });
            debugStream = null;
        }
        if (videoEl) {
            videoEl.srcObject = null;
        }
        if (previewWrap) {
            previewWrap.style.display = 'none';
        }
        if (btnStop) {
            btnStop.style.display = 'none';
        }
    }

    window.addEventListener('pagehide', stopDebugCamera);
    window.addEventListener('beforeunload', stopDebugCamera);

    btnStart.addEventListener('click', function () {
        stopDebugCamera();
        if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
            setDbgStatus('getUserMedia error: no_api');
            return;
        }
        navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' } },
            audio: false,
        }).then(function (stream) {
            debugStream = stream;
            videoEl.srcObject = stream;
            previewWrap.style.display = 'block';
            btnStop.style.display = 'block';
            setDbgStatus('camera preview started');
        }).catch(function (err) {
            var name = err && err.name ? String(err.name) : 'unknown';
            if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
                setDbgStatus('permission denied');
            } else {
                setDbgStatus('getUserMedia error: ' + name);
            }
        });
    });

    btnStop.addEventListener('click', function () {
        stopDebugCamera();
        setDbgStatus('test stopped');
    });
})();
    </script>
    @endif
</body>
</html>
