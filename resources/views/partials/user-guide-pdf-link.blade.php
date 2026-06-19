@php
    $variant = $variant ?? 'guest';
    $localeKey = app()->getLocale() === 'cg' ? 'cg' : 'en';
    $relativePath = (string) config('user-guides.'.$localeKey, '');
    $absolutePath = $relativePath !== '' ? public_path($relativePath) : '';
    $uiGroup = $variant === 'panel' ? 'panel' : 'landing';
    $label = \App\Support\UiText::t($uiGroup, 'user_guide_pdf', $localeKey === 'cg' ? 'Uputstvo (PDF)' : 'User guide (PDF)');
    $focusRing = $variant === 'panel'
        ? 'focus-visible:ring-white/50 focus-visible:ring-offset-[#9e1321]'
        : 'focus-visible:ring-red-600 focus-visible:ring-offset-2';
@endphp
@if ($relativePath !== '' && is_file($absolutePath))
    <a
        href="{{ asset($relativePath) }}"
        target="_blank"
        rel="noopener noreferrer"
        class="inline-flex items-center justify-center rounded p-1 text-white transition opacity-90 hover:opacity-100 focus:outline-none {{ $focusRing }} {{ $variant === 'panel' ? '' : 'text-red-800' }}"
        title="{{ $label }}"
        aria-label="{{ $label }}"
    >
        @if ($variant === 'panel')
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13a1 1 0 01-1 1H7a1 1 0 01-1-1V4a1 1 0 011-1z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M14 3v6h6M9 13h6M9 17h6M9 9h2" />
            </svg>
        @else
            <svg class="h-6 w-6 text-red-800" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13a1 1 0 01-1 1H7a1 1 0 01-1-1V4a1 1 0 011-1z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M14 3v6h6M9 13h6M9 17h6M9 9h2" />
            </svg>
        @endif
        <span class="sr-only">{{ $label }}</span>
    </a>
@endif
