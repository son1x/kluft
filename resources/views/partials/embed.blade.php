{{-- Zwei-Klick-Einbettung, s. App\Support\Embeds. Nichts Fremdes wird
     geladen, bevor jemand klickt oder „Externe Inhalte“ erlaubt hat. --}}
<figure class="not-prose my-6" x-data="zweiKlick" data-src="{{ $embed }}" data-kind="{{ $provider['kind'] }}">
    <div @class(['relative w-full overflow-hidden bg-dpsg-blau text-white', 'aspect-video' => $provider['kind'] === 'video', 'aspect-[4/3] sm:aspect-[16/9]' => $provider['kind'] === 'map'])>
        <template x-if="loaded">
            <iframe x-bind:src="src" class="absolute inset-0 h-full w-full border-0" title="{{ $title ?? ($provider['kind'] === 'video' ? 'Video' : 'Karte') }} ({{ $provider['label'] }})" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="fullscreen; picture-in-picture; encrypted-media" allowfullscreen></iframe>
        </template>
        <div x-show="! loaded" class="absolute inset-0 flex flex-col items-center justify-center gap-3 p-6 text-center">
            <p class="font-serif text-lg font-bold">{{ $title ?? ($provider['kind'] === 'video' ? 'Video' : 'Karte') }}</p>
            <p class="max-w-md text-sm text-white/85">Beim Laden {{ $provider['kind'] === 'video' ? 'des Videos' : 'der Karte' }} werden Daten (u. a. deine IP-Adresse) an {{ $provider['label'] }} ({{ $provider['owner'] }}) übertragen. Mehr in der <a href="{{ url('/datenschutz') }}" class="underline">Datenschutzerklärung</a>.</p>
            <button type="button" x-on:click="load()" class="bg-white px-5 py-2 font-bold text-dpsg-blau hover:bg-dpsg-hellbeige">{{ $provider['kind'] === 'video' ? 'Video laden' : 'Karte laden' }}</button>
            <label class="flex items-center gap-2 text-xs text-white/85"><input type="checkbox" x-model="remember" class="h-4 w-4" /> Externe Inhalte immer laden</label>
        </div>
    </div>
    <figcaption class="mt-1 text-right text-xs text-text-secondary"><a href="{{ $url }}" target="_blank" rel="noopener" class="underline">Auf {{ $provider['label'] }} öffnen</a></figcaption>
</figure>
