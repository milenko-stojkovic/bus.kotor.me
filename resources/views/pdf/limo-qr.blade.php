@php
    /** @var \App\Models\LimoQrToken $token */
    /** @var \App\Models\User $user */
    $locale = isset($locale) && is_string($locale) && $locale !== '' ? $locale : 'cg';
    $t = fn (string $key, ?string $fallback = null) => \App\Support\UiText::t('panel', $key, $fallback, $locale);

    $agency = (string) ($user->name ?? '');
    $validOn = $token->valid_on?->timezone('Europe/Podgorica')->format('d.m.Y.') ?? '—';
    $qrDataUri = (string) ($qrDataUri ?? '');

    $title = $t('limo_qr_pdf_title', $locale === 'en' ? 'Limo QR code' : 'Limo QR kod');
    $agencyLabel = $t('limo_qr_pdf_agency', $locale === 'en' ? 'Agency' : 'Agencija');
    $validForLabel = $t('limo_qr_pdf_valid_for', $locale === 'en' ? 'Valid for' : 'Važi za');
    $instruction = $t('limo_qr_pdf_instruction', $locale === 'en'
        ? 'Show this QR code to the officer upon arrival.'
        : 'Prikažite ovaj QR kod službeniku prilikom dolaska.');
@endphp
<!doctype html>
<html lang="sr-Latn-ME">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        h1 { font-size: 18px; margin: 0 0 10px; text-align: center; }
        .meta { margin: 8px 0 18px; text-align: center; color: #374151; }
        .meta strong { color: #111827; }
        .qr { text-align: center; margin: 16px 0 12px; }
        .qr img { width: 320px; height: 320px; border: 1px solid #e5e7eb; border-radius: 8px; }
        .hint { margin-top: 16px; text-align: center; font-size: 12px; color: #374151; }
        .muted { color: #6b7280; font-size: 11px; text-align: center; margin-top: 4px; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>

    <div class="meta">
        <div><span>{{ $agencyLabel }}:</span> <strong>{{ $agency }}</strong></div>
        <div class="muted"><span>{{ $validForLabel }}:</span> <strong>{{ $validOn }}</strong></div>
    </div>

    <div class="qr">
        @if ($qrDataUri !== '')
            <img src="{{ $qrDataUri }}" alt="QR" />
        @else
            <div class="muted">QR</div>
        @endif
    </div>

    <div class="hint">{{ $instruction }}</div>
</body>
</html>

