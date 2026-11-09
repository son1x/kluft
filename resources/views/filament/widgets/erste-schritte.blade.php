{{-- Dashboard: Erste Schritte (ErsteSchritte), passend zu den Rollen. --}}
<x-filament-widgets::widget>
    <x-filament::section heading="Erste Schritte" description="Was du hier tun kannst und wie ein Beitrag auf die Website kommt." collapsible persist-collapsed id="erste-schritte">
        <div class="grid gap-6 text-sm leading-relaxed lg:grid-cols-2">
            @if ($freigeben && $wartend > 0)
                <div class="rounded-lg border border-warning-300 bg-warning-50 p-4 lg:col-span-2 dark:border-warning-500/30 dark:bg-warning-500/10">
                    <p class="font-semibold">{{ $wartend === 1 ? '1 Beitrag wartet' : $wartend.' Beiträge warten' }} auf deine Freigabe.</p>
                    <p class="mt-1">{{ $wartend === 1 ? 'Lies ihn durch' : 'Lies sie durch' }}, stell den Status auf „Veröffentlicht“ und speichere. Passt etwas nicht, setz den Status auf „Entwurf“ zurück und sag der Person Bescheid.</p>
                    <x-filament::button tag="a" :href="$freigabeUrl" size="sm" color="warning" class="mt-3">Zu den Beiträgen</x-filament::button>
                </div>
            @endif

            <div class="space-y-3">
                <h3 class="text-base font-semibold">So kommt dein Beitrag online</h3>
                <ol class="list-decimal space-y-2 ps-5">
                    <li><strong>Foto vorbereiten:</strong> erst in der Mediathek hochladen, dann im Beitrag als Titelbild wählen.</li>
                    <li><strong>Beitrag schreiben:</strong> Titel, ein bis zwei Sätze Teaser, Text. Status zuerst „Entwurf“, dann siehst nur du ihn.</li>
                    @if ($freigeben)
                        <li><strong>Veröffentlichen:</strong> Du darfst Beiträge selbst freigeben. Status „Veröffentlicht“ wählen und speichern, fertig.</li>
                    @else
                        <li><strong>Zur Prüfung geben:</strong> Status „Zur Prüfung“ wählen und speichern. Die Leitung deiner Stufe bzw. deines Fachbereichs, der Vorstand oder die Verwaltung schaltet ihn frei.</li>
                    @endif
                </ol>
                <div class="flex flex-wrap gap-2 pt-1">
                    <x-filament::button tag="a" :href="$neuerBeitrag" size="sm" icon="heroicon-o-pencil-square">Neuer Beitrag</x-filament::button>
                    @if ($eigeneEntwuerfe > 0)
                        <x-filament::button tag="a" :href="$eigeneUrl" size="sm" color="gray">{{ $eigeneEntwuerfe === 1 ? '1 Entwurf' : $eigeneEntwuerfe.' Entwürfe' }} von dir</x-filament::button>
                    @endif
                </div>
            </div>

            <div class="space-y-3">
                <h3 class="text-base font-semibold">Fotos: was gilt</h3>
                <ul class="list-disc space-y-2 ps-5">
                    <li><strong>Querformat:</strong> Titelbilder werden im Verhältnis 16 : 9 gezeigt; am besten mindestens 1600 Pixel breit.</li>
                    <li><strong>Alt-Text und Bildnachweis</strong> sind Pflicht: kurz beschreiben, was zu sehen ist, und wer das Foto gemacht hat.</li>
                    <li><strong>Personen nur mit Einwilligung,</strong> bei Kindern und Jugendlichen die der Eltern. Im Zweifel ein Foto ohne erkennbare Gesichter nehmen.</li>
                </ul>
                <x-filament::button tag="a" :href="$mediathek" size="sm" color="gray" icon="heroicon-o-photo">Zur Mediathek</x-filament::button>
            </div>

            @if ($bereich !== [])
                <div class="space-y-3">
                    <h3 class="text-base font-semibold">Dein Bereich</h3>
                    <ul class="space-y-2">
                        @foreach ($bereich as [$titel, $url, $text])
                            <li><a href="{{ $url }}" class="font-semibold text-primary-600 underline dark:text-primary-400">{{ $titel }}</a><br><span class="text-gray-600 dark:text-gray-400">{{ $text }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($rollen || $eintrag)
                <div class="space-y-3">
                    <h3 class="text-base font-semibold">Dein Konto</h3>
                    <ul class="space-y-2">
                        @if ($rollen)
                            <li><a href="{{ $rollen }}" class="font-semibold text-primary-600 underline dark:text-primary-400">Meine Rollen und Rechte</a><br><span class="text-gray-600 dark:text-gray-400">Was du darfst und wen du fragst, wenn dir etwas fehlt.</span></li>
                        @endif
                        @if ($eintrag)
                            <li><a href="{{ $eintrag }}" class="font-semibold text-primary-600 underline dark:text-primary-400">Mein Eintrag auf der Website</a><br><span class="text-gray-600 dark:text-gray-400">Vorstellungstext und Foto selbst pflegen.</span></li>
                        @endif
                    </ul>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
