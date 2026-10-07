@php($revision = \App\Support\KluftVersion::kurz())
@php($link = \App\Support\KluftVersion::link())
<div class="kluft-fuss">
    <style>
        .kluft-fuss{display:flex;align-items:center;justify-content:center;gap:.5rem;padding:1.25rem 1rem;font-size:.8125rem;line-height:1;color:#57606a}
        .kluft-fuss svg{width:1.5rem;height:1.5rem;flex:none}
        .kluft-fuss .g{stroke:#1f5a47}.kluft-fuss .t{fill:#9cc7b3}
        .kluft-fuss .name{font-weight:700;color:#1f5a47;letter-spacing:-.01em}
        .kluft-fuss .tag{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:.75rem;padding:.2rem .45rem;border-radius:.375rem;border:1px solid #d0d7de;background:#f6f8fa;color:#57606a;text-decoration:none}
        .kluft-fuss a.tag:hover{border-color:#1f5a47;color:#1f5a47}
        .dark .kluft-fuss{color:#9ca3af}
        .dark .kluft-fuss .g{stroke:#4f9f80}.dark .kluft-fuss .t{fill:#2c5546}
        .dark .kluft-fuss .name{color:#eef3ef}
        .dark .kluft-fuss .tag{border-color:#374151;background:#1f2937;color:#d1d5db}
        .dark .kluft-fuss a.tag:hover{border-color:#4f9f80;color:#eef3ef}
    </style>
    <svg viewBox="0 0 64 64" aria-hidden="true">
        <path class="t" d="M10 9 Q32 4 54 9 Q45 23 32 30 Q19 23 10 9 Z"/>
        <g class="g" fill="none" stroke-width="7.5" stroke-linecap="round">
            <path d="M10 9 Q19 23 31 30"/>
            <path d="M54 9 Q45 23 33 30"/>
            <path d="M29.5 34 L24 57"/>
            <path d="M34.5 34 L40 57"/>
        </g>
        <circle cx="32" cy="31" r="7" fill="none" stroke="#e8a92a" stroke-width="5"/>
    </svg>
    <span class="name">Kluft</span>
    @if ($revision)
        @if ($link)
            <a class="tag" href="{{ $link }}" target="_blank" rel="noopener noreferrer" title="Version {{ \App\Support\KluftVersion::revision() }}">{{ $revision }}</a>
        @else
            <span class="tag" title="Version {{ \App\Support\KluftVersion::revision() }}">{{ $revision }}</span>
        @endif
    @else
        <span class="tag">Entwicklungsstand</span>
    @endif
</div>
