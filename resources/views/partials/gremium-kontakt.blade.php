{{-- Kontaktformular unter einer Gremiumsseite (config dpsg.gremien.<g>.formular):
     an eine Person der Seite oder an alle. Erwartet: $gremium, $people --}}
@php($mitAdresse = $people->filter(fn ($p) => $p->publicEmail() !== null))
@if ($mitAdresse->isNotEmpty())
    @php($label = config("dpsg.gremien.{$gremium}.label", $gremium))
    <section id="gremium-kontakt" class="mx-auto mb-16 max-w-3xl scroll-mt-28 px-4" aria-labelledby="gremium-kontakt-heading">
        <div class="bg-white p-6 shadow-md md:p-8">
            <h2 id="gremium-kontakt-heading" class="font-serif text-2xl font-bold">Kontakt</h2>
            <p class="mt-1 text-text-secondary">Schreib an alle oder an eine einzelne Person.</p>

            @if (session('gremium_status') === 'gesendet')
                <div class="mt-6 border-l-4 border-stufe-pfadi bg-surface-muted p-4" role="status">
                    <p class="font-bold">Danke für deine Nachricht!</p>
                    <p class="mt-1 text-sm">Sie ist unterwegs. Die Antwort kommt an die E-Mail-Adresse, die du angegeben hast.</p>
                </div>
            @else
                @if (session('gremium_status') === 'fehler')
                    <p class="mt-4 border-l-4 border-dpsg-rot bg-surface-muted p-3 text-sm" role="alert">Das hat leider nicht geklappt. Bitte versuch es später noch einmal.</p>
                @elseif (session('gremium_status') === 'abgelaufen')
                    <p class="mt-4 border-l-4 border-dpsg-mittelbeige bg-surface-muted p-3 text-sm" role="alert">Das Formular war sehr lange geöffnet. Bitte schick es noch einmal ab – deine Eingaben sind noch da.</p>
                @endif

                <form method="post" action="{{ route('gremium.nachricht', $gremium) }}" class="mt-6 space-y-4" novalidate>
                    <div>
                        <label for="gremium-an" class="block text-sm font-bold">An</label>
                        <select id="gremium-an" name="an" required
                                @error('an', 'gremium') aria-invalid="true" aria-describedby="gremium-an-error" @enderror
                                class="mt-1 w-full border border-dpsg-mittelbeige bg-white px-3 py-2">
                            <option value="alle" @selected(old('an', 'alle') === 'alle')>Alle – {{ $label }}</option>
                            @foreach ($mitAdresse as $person)
                                <option value="{{ $person->id }}" @selected((string) old('an') === (string) $person->id)>{{ $person->name }}{{ $person->role ? ' – '.$person->role : '' }}</option>
                            @endforeach
                        </select>
                        @error('an', 'gremium')<p id="gremium-an-error" class="mt-1 text-sm text-dpsg-rot">{{ $message }}</p>@enderror
                    </div>
                    @include('partials.message-form-fields', ['idPrefix' => 'gremium', 'withSubject' => true, 'bag' => 'gremium'])
                    <button type="submit" class="inline-flex items-center gap-2 bg-dpsg-blau px-5 py-2 font-bold text-white hover:bg-dpsg-rot">
                        <x-heroicon-o-paper-airplane class="h-5 w-5" aria-hidden="true" /> Nachricht senden
                    </button>
                </form>
            @endif
        </div>
    </section>
@endif
