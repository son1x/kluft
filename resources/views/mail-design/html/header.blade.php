@props(['url'])
{{-- Kopf: Logo der Gliederung (PNG, absolute Adresse – SVG/WebP zeigen viele Mailprogramme nicht), sonst der Name. --}}
@php
    $logo = config('dpsg.site.logo');
    $size = (array) config('dpsg.site.logo_size', []);
    $hoehe = 80;
    $breite = count($size) === 2 && $size[1] > 0 ? (int) round($size[0] / $size[1] * $hoehe) : null;
@endphp
<tr>
<td class="header">
<a href="{{ $url }}">
@if ($logo && str_ends_with((string) $logo, '.png') && is_file(public_path($logo)))
<img src="{{ asset($logo) }}" class="logo" alt="{{ config('dpsg.site.name') }}" height="{{ $hoehe }}" @if ($breite) width="{{ $breite }}" @endif style="height: {{ $hoehe }}px; @if ($breite) width: {{ $breite }}px; @endif">
@else
<span class="name">{{ config('dpsg.site.name') }}</span>
@endif
</a>
</td>
</tr>
