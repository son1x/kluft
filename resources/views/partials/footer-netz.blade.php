{{-- Fußzeile: „DPSG im Netz“ (App\Support\Fusszeile, Standard: Bundesebene und übergeordnete Gliederung). --}}
<h2 class="{{ $class ?? 'mb-3' }} font-serif text-base font-bold">DPSG im Netz</h2>
<ul class="space-y-2">
    @foreach ($links as $link)
        <li><a href="{{ str_starts_with($link['url'], '/') ? url($link['url']) : $link['url'] }}" @unless (str_starts_with($link['url'], '/')) target="_blank" rel="noopener" @endunless class="hover:underline">{{ $link['label'] }}</a></li>
    @endforeach
</ul>
