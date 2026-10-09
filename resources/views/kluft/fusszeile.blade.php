@php($revision = \App\Support\KluftVersion::kurz())
@php($link = \App\Support\KluftVersion::link())
@php($jahre = now()->year > 2026 ? '2026–'.now()->year : '2026')
@php($version = \App\Support\KluftVersion::version())
<div class="kluft-fuss">
    <style>
        .kluft-fuss{display:flex;align-items:center;justify-content:center;gap:.5rem;padding:1.25rem 1rem;font-size:.8125rem;line-height:1;color:#57606a}
        .kluft-fuss svg{width:1.5rem;height:1.5rem;flex:none}
        .kluft-fuss .g{stroke:#1f5a47}.kluft-fuss .t{fill:#9cc7b3}
        .kluft-fuss .marke{display:inline-flex;align-items:center;gap:.5rem;padding:.25rem .4rem;margin:-.25rem -.4rem;border:0;border-radius:.375rem;background:none;font:inherit;cursor:pointer}
        .kluft-fuss .marke:hover,.kluft-fuss .marke:focus-visible{background:rgba(31,90,71,.08)}
        .kluft-fuss .name{font-weight:700;color:#1f5a47;letter-spacing:-.01em}
        .kluft-fuss .version{font-size:.75rem;color:#57606a}
        .kluft-fuss .tag{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:.75rem;padding:.2rem .45rem;border-radius:.375rem;border:1px solid #d0d7de;background:#f6f8fa;color:#57606a;text-decoration:none}
        .kluft-fuss a.tag:hover{border-color:#1f5a47;color:#1f5a47}
        .kluft-info{box-sizing:border-box;width:min(22rem,calc(100vw - 2rem));padding:1.25rem 1.25rem 1rem;border:1px solid #d0d7de;border-radius:.75rem;background:#fff;color:#24292f;box-shadow:0 12px 32px rgba(0,0,0,.18);font-size:.875rem;line-height:1.5;text-align:left}
        .kluft-info::backdrop{background:rgba(0,0,0,.25)}
        .kluft-info .kopf{display:flex;align-items:center;gap:.6rem;margin-bottom:.75rem}
        .kluft-info .kopf svg{width:2.25rem;height:2.25rem}
        .kluft-info .kopf strong{font-size:1.125rem;color:#1f5a47}
        .kluft-info .kopf span{display:block;font-size:.75rem;color:#57606a}
        .kluft-info p{margin:0 0 .6rem}
        .kluft-info .recht{margin-top:.9rem;padding-top:.6rem;border-top:1px solid #d0d7de;font-size:.75rem;color:#57606a}
        .kluft-info .zu{position:absolute;top:.5rem;right:.6rem;border:0;background:none;font-size:1.25rem;line-height:1;color:#57606a;cursor:pointer;padding:.25rem}
        .dark .kluft-fuss{color:#9ca3af}
        .dark .kluft-fuss .g{stroke:#4f9f80}.dark .kluft-fuss .t{fill:#2c5546}
        .dark .kluft-fuss .name,.dark .kluft-info .kopf strong{color:#eef3ef}
        .dark .kluft-fuss .marke:hover,.dark .kluft-fuss .marke:focus-visible{background:rgba(255,255,255,.06)}
        .dark .kluft-fuss .tag{border-color:#374151;background:#1f2937;color:#d1d5db}
        .dark .kluft-fuss a.tag:hover{border-color:#4f9f80;color:#eef3ef}
        .dark .kluft-info{background:#18181b;border-color:#374151;color:#e5e7eb}
        .dark .kluft-info .kopf span,.dark .kluft-info .recht,.dark .kluft-info .zu{color:#9ca3af}
        .dark .kluft-info .recht{border-color:#374151}
    </style>
    <button type="button" class="marke" popovertarget="kluft-info" aria-haspopup="dialog" title="Über Kluft">
        @include('kluft.zeichen')
        <span class="name">Kluft</span>
        @if ($version)<span class="version">{{ $version }}</span>@endif
    </button>
    @if ($revision)
        @if ($link)
            <a class="tag" href="{{ $link }}" target="_blank" rel="noopener noreferrer" title="Version {{ \App\Support\KluftVersion::revision() }}">{{ $revision }}</a>
        @else
            <span class="tag" title="Version {{ \App\Support\KluftVersion::revision() }}">{{ $revision }}</span>
        @endif
    @else
        <span class="tag">Entwicklungsstand</span>
    @endif

    <div id="kluft-info" class="kluft-info" popover role="dialog" aria-labelledby="kluft-info-titel">
        <button type="button" class="zu" popovertarget="kluft-info" popovertargetaction="hide" aria-label="Schließen">×</button>
        <div class="kopf">
            @include('kluft.zeichen')
            <div><strong id="kluft-info-titel">Kluft</strong><span>Das Verbands-CMS für Gliederungen der DPSG</span></div>
        </div>
        <p>
            <b>Der Name:</b> Die Kluft tragen alle gleich, das Halstuch zeigt, wo man herkommt.
            Genauso hier: Der Code ist für alle derselbe – Name, Logo, Inhalte und
            eingeschaltete Funktionen gehören der jeweiligen Gliederung.
        </p>
        <p>
            <b>Das Zeichen:</b> ein Halstuch, dessen Knoten die Gliederungen zusammenhält.
        </p>
        <div class="recht">
            © {{ $jahre }} {{ config('app.copyright') }} · Alle Rechte vorbehalten.<br>
            Version {{ $version ?? '–' }}{{ $revision ? ' · '.$revision : ' · Entwicklungsstand' }}
        </div>
    </div>
</div>
