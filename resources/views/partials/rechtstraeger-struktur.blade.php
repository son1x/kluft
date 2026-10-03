{{-- Strukturgrafik auf der Seite „Rechtsträger“: wer trägt was. Als HTML
     statt Bild, damit sie auf dem Handy lesbar und für Screenreader eine
     Liste ist. --}}
<figure class="not-prose my-8" aria-labelledby="struktur-caption">
    <figcaption id="struktur-caption" class="mb-4 font-serif text-xl font-bold">So hängt alles zusammen</figcaption>

    <div class="grid items-stretch gap-3 md:grid-cols-[1fr_auto_1fr]">
        <div class="border-t-4 border-dpsg-rot bg-white p-4 shadow-md">
            <p class="text-xs font-bold uppercase tracking-wider text-text-secondary">Verband</p>
            <p class="font-serif text-lg font-bold">DPSG Diözesanverband Trier</p>
            <p class="mt-1 text-sm text-text-secondary">Diözesanversammlung und Diözesanleitung gestalten die pfadfinderische Arbeit.</p>
        </div>
        <div class="flex items-center justify-center text-sm text-text-secondary" aria-hidden="true">
            <span class="hidden md:inline">getragen von →</span><span class="md:hidden">↓ getragen von</span>
        </div>
        <div class="border-t-4 border-dpsg-blau bg-white p-4 shadow-md">
            <p class="text-xs font-bold uppercase tracking-wider text-text-secondary">Rechtsträger · eingetragener Verein</p>
            <p class="font-serif text-lg font-bold">Landesamt St. Georg e. V.</p>
            <p class="mt-1 text-sm text-text-secondary">Personal, Haushalt, Zuschüsse, Häuser. Geschäftsführer: Marc Basenach.</p>
        </div>
    </div>

    <div class="mt-3 flex justify-center md:justify-end md:pr-[24%]" aria-hidden="true">
        <span class="text-sm text-text-secondary">↓ gründet und fördert</span>
    </div>

    <ul class="mt-3 grid gap-3 md:grid-cols-3">
        <li class="border-t-4 border-dpsg-blau/60 bg-white p-4 shadow-md">
            <p class="text-xs font-bold uppercase tracking-wider text-text-secondary">Tochtergesellschaft</p>
            <p class="font-serif text-lg font-bold">dpsg trevirensis gemeinnützige GmbH</p>
            <p class="mt-1 text-sm text-text-secondary">Kinder- und Jugendhilfe, Bildung, Bildungshäuser und Schullandheime. Gesellschafter: Landesamt St. Georg e. V.</p>
        </li>
        <li class="border-t-4 border-dpsg-blau/60 bg-white p-4 shadow-md">
            <p class="text-xs font-bold uppercase tracking-wider text-text-secondary">Stiftung</p>
            <p class="font-serif text-lg font-bold"><a href="{{ url('/stiftung') }}" class="hover:text-dpsg-rot hover:underline">Stiftung DPSG Diözesanverband Trier</a></p>
            <p class="mt-1 text-sm text-text-secondary">Sichert die Arbeit langfristig ab; unter dem Dach der Stiftung GLAUBEN LEBEN im Bistum Trier.</p>
        </li>
        <li class="border-2 border-dashed border-dpsg-mittelbeige bg-white p-4">
            <p class="text-xs font-bold uppercase tracking-wider text-text-secondary">Eigenständiger Förderverein</p>
            <p class="font-serif text-lg font-bold"><a href="{{ url('/freunde-und-foerderer') }}" class="hover:text-dpsg-rot hover:underline">Freunde &amp; Förderer der DPSG – DV Trier e. V.</a></p>
            <p class="mt-1 text-sm text-text-secondary">Ehemalige, Mitglieder und Freund*innen unterstützen die DPSG-Arbeit.</p>
        </li>
    </ul>
</figure>
