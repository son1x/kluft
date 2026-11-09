{{-- Fußzeile: „DPSG im Netz“ (Bundesebene, übergeordnete Gliederung, weitere Links). --}}
<h2 class="{{ $class ?? 'mb-3' }} font-serif text-base font-bold">DPSG im Netz</h2>
<ul class="space-y-2">
    <li><a href="https://www.dpsg.de" target="_blank" rel="noopener" class="hover:underline">dpsg.de – Bundesebene</a></li>
    @foreach (array_filter([config('dpsg.site.parent'), ...config('dpsg.site.links', [])]) as $link)
        <li><a href="{{ $link['url'] }}" target="_blank" rel="noopener" class="hover:underline">{{ $link['label'] }}</a></li>
    @endforeach
</ul>
