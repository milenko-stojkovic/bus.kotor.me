<!DOCTYPE html>
<html lang="sr-Latn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1e3a5f">
    <title>Limo evidencija</title>
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
        input[type="text"] {
            width: 100%;
            font-size: 1.05rem;
            padding: 0.75rem 0.85rem;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            margin-bottom: 0.75rem;
        }
        input:focus {
            outline: 2px solid var(--primary);
            border-color: var(--primary);
        }
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
            aspect-ratio: 4/3;
            margin-bottom: 0.65rem;
            display: none;
        }
        #scanWrap.active { display: block; }
        #scanVideo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            vertical-align: middle;
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
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Limo evidencija</h1>

        <div class="card">
            <div id="scanWrap">
                <video id="scanVideo" playsinline muted></video>
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
            <div id="platePreviewWrap" style="display:none;margin-top:0.75rem;">
                <img id="platePreviewImg" alt="Pregled fotografije tablice" width="600" height="400">
                <label for="plateConfirmInput">Potvrđena registarska tablica</label>
                <input type="text" id="plateConfirmInput" autocomplete="off" autocorrect="off" spellcheck="false" placeholder="npr. PG1234AB">
                <p class="hint">OCR prijedlog je samo prijedlog — uvijek provjerite i ispravite ručno prije potvrde.</p>
                <button type="button" class="btn btn-primary" id="btnPlateConfirm" disabled>Potvrdi tablicu</button>
                <button type="button" class="btn btn-secondary" id="btnPlateReset">Počni ponovo</button>
            </div>
        </div>

        <div id="status" role="status" aria-live="polite"></div>
    </div>

    <script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const postUrl = @json(route('limo.pickup.qr'));
    const plateOcrUrl = @json(route('limo.pickup.plate.ocr'));
    const plateConfirmUrl = @json(route('limo.pickup.plate.confirm'));

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
    const plateConfirmInput = document.getElementById('plateConfirmInput');
    const btnPlateConfirm = document.getElementById('btnPlateConfirm');
    const btnPlateReset = document.getElementById('btnPlateReset');

    let mediaStream = null;
    let scanRaf = null;
    let scanning = false;
    let submitting = false;

    let plateUploadToken = null;
    let platePreviewObjectUrl = null;
    let plateUploading = false;
    let plateConfirming = false;

    const errMessages = {
        invalid_token: 'QR kod nije važeći.',
        token_already_used: 'QR kod je već iskorišćen.',
        insufficient_advance: 'Agencija nema dovoljno avansa.',
        validation_error: 'Došlo je do greške. Pokušajte ponovo.',
        plate_not_registered: 'Tablica nije pronađena u voznom parku nijedne agencije.',
        invalid_upload: 'Fotografija više nije važeća. Pokušajte ponovo.',
    };
    const genericErr = 'Došlo je do greške. Pokušajte ponovo.';
    const scanSupported = ('BarcodeDetector' in window);

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

    function resetPlateFlow() {
        revokePlatePreview();
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

    function stopCamera() {
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

    window.addEventListener('pagehide', stopCamera);
    window.addEventListener('beforeunload', stopCamera);

    document.addEventListener('DOMContentLoaded', function () {
        btnScan.disabled = !scanSupported;
        if (!scanSupported) {
            noDetectorBanner.style.display = 'block';
            noDetectorBanner.textContent = 'Automatsko skeniranje QR-a nije podržano u ovom pregledaču. Koristite polje ispod za ručni unos tokena.';
            btnScan.title = 'Skeniranje nije podržano u ovom pregledaču';
        }
        if (!window.isSecureContext) {
            secureContextHint.style.display = 'block';
            secureContextHint.textContent = 'Kamera i lokacija obično zahtijevaju HTTPS (ili localhost). Ručni unos tokena radi i bez kamere.';
        }
    });

    function hasCameraApi() {
        return !!(navigator.mediaDevices && typeof navigator.mediaDevices.getUserMedia === 'function');
    }

    async function startScan() {
        setStatus('', '');
        if (!('BarcodeDetector' in window)) {
            setStatus('Skeniranje nije podržano u ovom pregledaču. Unesite token ručno.', 'info');
            return;
        }
        if (!hasCameraApi()) {
            setStatus('Kamera nije dostupna u ovom okruženju (često potreban HTTPS osim na localhost). Unesite token ručno.', 'info');
            return;
        }

        try {
            mediaStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' } },
                audio: false,
            });
        } catch (e) {
            var name = e && e.name ? e.name : '';
            if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
                setStatus('Dozvola za kameru je odbijena. Unesite token ručno u polje ispod.', 'info');
            } else {
                setStatus('Kamera nije dostupna. Unesite token ručno.', 'info');
            }
            return;
        }

        scanVideo.srcObject = mediaStream;
        scanWrap.classList.add('active');
        btnStopScan.style.display = 'block';
        scanning = true;

        const detector = new BarcodeDetector({ formats: ['qr_code'] });
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');

        let frames = 0;
        function tick() {
            if (!scanning) return;
            frames++;
            if (scanVideo.readyState === scanVideo.HAVE_ENOUGH_DATA && frames % 3 === 0) {
                canvas.width = scanVideo.videoWidth;
                canvas.height = scanVideo.videoHeight;
                ctx.drawImage(scanVideo, 0, 0);
                detector.detect(canvas).then(function (codes) {
                    if (!scanning) return;
                    if (codes && codes.length > 0) {
                        var raw = codes[0].rawValue;
                        tokenInput.value = raw;
                        stopCamera();
                        setStatus('QR pročitan. Provjerite i dodirnite „Potvrdi dolazak”.', 'info');
                        return;
                    }
                    scanRaf = requestAnimationFrame(tick);
                }).catch(function () {
                    scanRaf = requestAnimationFrame(tick);
                });
                return;
            }
            scanRaf = requestAnimationFrame(tick);
        }
        scanRaf = requestAnimationFrame(tick);
    }

    btnScan.addEventListener('click', function () {
        stopCamera();
        startScan();
    });

    btnStopScan.addEventListener('click', function () {
        stopCamera();
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
            if (!navigator.geolocation) {
                resolve({ lat: null, lng: null });
                return;
            }
            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude });
                },
                function () {
                    resolve({ lat: null, lng: null });
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
            );
        });
    }

    btnSubmit.addEventListener('click', async function () {
        if (submitting) return;
        var token = (tokenInput.value || '').trim();
        if (!token) {
            setStatus('Unesite ili skenirajte QR token prije potvrde.', 'err');
            return;
        }

        stopCamera();

        submitting = true;
        btnSubmit.disabled = true;
        btnScan.disabled = true;
        setPlateInputsBlocked(true);
        setStatus('Slanje…', 'info');

        var coords = await getPositionBestEffort();

        var body = {
            token: token,
            gps_lat: coords.lat,
            gps_lng: coords.lng,
            device_info: buildDeviceInfo(),
        };

        try {
            var res = await fetch(postUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(body),
            });

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
            }
        } catch (e) {
            setStatus(genericErr, 'err');
        } finally {
            submitting = false;
            btnSubmit.disabled = false;
            btnScan.disabled = !scanSupported;
            setPlateInputsBlocked(false);
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
            handlePlateImageUpload(f);
        }
    }
    plateFileInput.addEventListener('change', onPlateFileChosen);
    plateGalleryInput.addEventListener('change', onPlateFileChosen);

    btnPlateReset.addEventListener('click', function () {
        resetPlateFlow();
        setStatus('', '');
    });

    async function handlePlateImageUpload(file) {
        if (plateUploading || submitting) return;
        plateUploading = true;
        setPlateInputsBlocked(true);
        btnSubmit.disabled = true;
        btnScan.disabled = true;
        setStatus('Učitavanje slike…', 'info');
        try {
            var coords = await getPositionBestEffort();
            var fd = new FormData();
            fd.append('image', file);
            if (coords.lat != null) fd.append('gps_lat', String(coords.lat));
            if (coords.lng != null) fd.append('gps_lng', String(coords.lng));
            fd.append('device_info', buildDeviceInfo());
            var res = await fetch(plateOcrUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: fd,
            });
            var data = await res.json().catch(function () { return {}; });
            if (!res.ok || data.status !== 'ok') {
                var ec = data.code;
                setStatus(errMessages[ec] || data.message || genericErr, 'err');
                resetPlateFlow();
                return;
            }
            plateUploadToken = data.upload_token;
            revokePlatePreview();
            platePreviewObjectUrl = URL.createObjectURL(file);
            platePreviewImg.src = platePreviewObjectUrl;
            platePreviewWrap.style.display = 'block';
            plateConfirmInput.value = data.suggested_plate ? String(data.suggested_plate) : '';
            btnPlateConfirm.disabled = false;
            setStatus('Provjerite tablicu ispod i dodirnite „Potvrdi tablicu”.', 'info');
        } catch (e) {
            setStatus(genericErr, 'err');
            resetPlateFlow();
        } finally {
            plateUploading = false;
            btnSubmit.disabled = false;
            btnScan.disabled = !scanSupported;
            setPlateInputsBlocked(false);
            if (plateUploadToken) {
                btnPlateConfirm.disabled = false;
            }
        }
    }

    btnPlateConfirm.addEventListener('click', async function () {
        if (plateConfirming || submitting || plateUploading) return;
        var pv = (plateConfirmInput.value || '').trim();
        if (!plateUploadToken || !pv) {
            setStatus('Prvo učitajte fotografiju i unesite ili potvrdite tablicu.', 'err');
            return;
        }
        plateConfirming = true;
        btnPlateConfirm.disabled = true;
        btnSubmit.disabled = true;
        btnScan.disabled = true;
        setPlateInputsBlocked(true);
        setStatus('Slanje…', 'info');
        var coords = await getPositionBestEffort();
        var body = {
            upload_token: plateUploadToken,
            license_plate: pv,
            gps_lat: coords.lat,
            gps_lng: coords.lng,
            device_info: buildDeviceInfo(),
        };
        try {
            var res = await fetch(plateConfirmUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(body),
            });
            var data = await res.json().catch(function () { return {}; });
            if (res.ok && data.status === 'ok') {
                resetPlateFlow();
                var bal = data.remaining_balance != null ? String(data.remaining_balance) : '—';
                setStatus(
                    '<strong>Dolazak potvrđen (tablica).</strong>' +
                    '<div class="success-detail">Transakcija: <code>' + escapeHtml(data.merchant_transaction_id) + '</code></div>' +
                    '<div class="success-detail">Preostali avans: <strong>' + escapeHtml(bal) + ' EUR</strong></div>',
                    'ok'
                );
            } else {
                var pc = data.code;
                setStatus(errMessages[pc] || data.message || genericErr, 'err');
            }
        } catch (e) {
            setStatus(genericErr, 'err');
        } finally {
            plateConfirming = false;
            btnSubmit.disabled = false;
            btnScan.disabled = !scanSupported;
            setPlateInputsBlocked(false);
            if (plateUploadToken) {
                btnPlateConfirm.disabled = false;
            }
        }
    });

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
})();
    </script>
</body>
</html>
