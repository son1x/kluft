@extends('layouts.app')

@section('seo_title', 'Material ausleihen – '.config('dpsg.site.name'))
@section('seo_description', 'Zelte, Technik und mehr: Material des '.config('dpsg.site.name_genitive').' zum Ausleihen anfragen.')

{{-- Materialverleih (docs/materialverleih.md). Erwartet: $gruppen (Kategorie => Material), $material, $gewaehlt --}}
@section('content')
    <div class="mx-auto max-w-6xl px-4 py-10">
        <h1 class="font-serif text-3xl font-bold">Material ausleihen</h1>
        <p class="mt-3 max-w-3xl text-lg text-text-secondary">
            Für Gruppenstunden, Aktionen und Lager verleihen wir Material. Such dir aus, was du brauchst, und schick uns unten eine Anfrage. Wir melden uns per E-Mail.
        </p>

        @if ($material->isEmpty())
            <p class="mt-8 text-text-secondary">Gerade ist kein Material zum Ausleihen eingetragen.</p>
        @else
            @foreach ($gruppen as $kategorie => $liste)
                <section class="mt-10" aria-labelledby="kat-{{ \Illuminate\Support\Str::slug($kategorie) }}">
                    <h2 id="kat-{{ \Illuminate\Support\Str::slug($kategorie) }}" class="font-serif text-2xl font-bold">{{ $kategorie }}</h2>
                    <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($liste as $m)
                            <li class="flex flex-col overflow-hidden rounded-lg bg-white shadow-md">
                                @if ($m->bildUrl())
                                    <img src="{{ $m->bildUrl() }}" alt="" loading="lazy" class="aspect-[4/3] w-full object-cover" />
                                @endif
                                <div class="flex flex-1 flex-col p-4">
                                    <h3 class="font-bold text-dpsg-blau">{{ $m->name }}</h3>
                                    @if ($m->menge > 1)<p class="text-sm text-text-secondary">{{ $m->menge }} Stück</p>@endif
                                    @if ($m->beschreibung)<p class="mt-2 text-sm">{{ $m->beschreibung }}</p>@endif
                                    <div class="mt-auto pt-4">
                                        <a href="{{ route('material.index', ['material' => $m->id]) }}#anfrage"
                                           class="inline-flex min-h-11 items-center justify-center rounded-md bg-dpsg-rot px-4 text-sm font-bold text-white hover:bg-dpsg-rot/90">
                                            {{ $m->name }} anfragen
                                        </a>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach

            <section id="anfrage" class="mt-12 scroll-mt-24 bg-white p-6 shadow-md md:p-8" aria-labelledby="anfrage-heading">
                <h2 id="anfrage-heading" class="font-serif text-2xl font-bold">Anfrage</h2>

                @if (session('material_status') === 'gesendet')
                    <div class="mt-6 border-l-4 border-stufe-pfadi bg-surface-muted p-4" role="status">
                        <p class="font-bold">Danke für deine Anfrage!</p>
                        <p class="mt-1 text-sm">Wir prüfen, ob das Material frei ist, und melden uns per E-Mail mit einer Zusage oder Absage.</p>
                    </div>
                @else
                    @if (session('material_status') === 'abgelaufen')
                        <p class="mt-4 border-l-4 border-dpsg-mittelbeige bg-surface-muted p-3 text-sm" role="alert">Das Formular war sehr lange geöffnet. Bitte schick es noch einmal ab – deine Eingaben sind noch da.</p>
                    @endif
                    <form method="post" action="{{ route('material.anfrage') }}" class="mt-6 space-y-4" novalidate>
                        @csrf
                        <input type="hidden" name="_t" value="{{ \App\Support\SpamGuard::token() }}" />
                        <div class="hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off" /></label></div>
                        <altcha-widget challenge="{{ route('spamschutz.aufgabe') }}" auto="onfocus" display="invisible" language="de" name="altcha"></altcha-widget>

                        @php($feld = 'mt-1 w-full border border-dpsg-mittelbeige bg-white px-3 py-2 min-h-11')
                        <div class="grid gap-4 sm:grid-cols-[2fr_1fr]">
                            <div>
                                <label for="material-id" class="block text-sm font-bold">Was möchtest du ausleihen?</label>
                                <select id="material-id" name="material_id" required class="{{ $feld }}" @error('material_id') aria-invalid="true" aria-describedby="material-id-error" @enderror>
                                    <option value="">Bitte wählen</option>
                                    @foreach ($gruppen as $kategorie => $liste)
                                        <optgroup label="{{ $kategorie }}">
                                            @foreach ($liste as $m)
                                                <option value="{{ $m->id }}" @selected((int) $gewaehlt === $m->id)>{{ $m->name }}{{ $m->menge > 1 ? ' ('.$m->menge.' Stück)' : '' }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                                @error('material_id')<p id="material-id-error" class="mt-1 text-sm text-dpsg-rot">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="material-menge" class="block text-sm font-bold">Anzahl</label>
                                <input id="material-menge" type="number" name="menge" min="1" max="999" inputmode="numeric" value="{{ old('menge', 1) }}" class="{{ $feld }}" />
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach (['von' => 'Abholung (von)', 'bis' => 'Rückgabe (bis)'] as $name => $label)
                                <div>
                                    <label for="material-{{ $name }}" class="block text-sm font-bold">{{ $label }}</label>
                                    <input id="material-{{ $name }}" type="date" name="{{ $name }}" required min="{{ today()->toDateString() }}" value="{{ old($name) }}" class="{{ $feld }}"
                                           @error($name) aria-invalid="true" aria-describedby="material-{{ $name }}-error" @enderror />
                                    @error($name)<p id="material-{{ $name }}-error" class="mt-1 text-sm text-dpsg-rot">{{ $message }}</p>@enderror
                                </div>
                            @endforeach
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach (['name' => ['Dein Name', 'text', 'name', true], 'gruppierung' => ['Stamm / Gruppe (optional)', 'text', 'organization', false], 'email' => ['Deine E-Mail-Adresse', 'email', 'email', true], 'telefon' => ['Telefon (optional)', 'tel', 'tel', false]] as $name => [$label, $typ, $auto, $pflicht])
                                <div>
                                    <label for="material-{{ $name }}" class="block text-sm font-bold">{{ $label }}</label>
                                    <input id="material-{{ $name }}" type="{{ $typ }}" name="{{ $name }}" @if ($pflicht) required @endif maxlength="150" value="{{ old($name) }}" autocomplete="{{ $auto }}" class="{{ $feld }}"
                                           @error($name) aria-invalid="true" aria-describedby="material-{{ $name }}-error" @enderror />
                                    @error($name)<p id="material-{{ $name }}-error" class="mt-1 text-sm text-dpsg-rot">{{ $message }}</p>@enderror
                                </div>
                            @endforeach
                        </div>
                        <div>
                            <label for="material-zweck" class="block text-sm font-bold">Wofür? <span class="font-normal text-text-secondary">(optional)</span></label>
                            <textarea id="material-zweck" name="zweck" maxlength="500" rows="3" class="{{ $feld }}">{{ old('zweck') }}</textarea>
                        </div>
                        <p class="text-sm text-text-secondary">
                            Deine Angaben speichern wir nur für den Verleih und löschen sie {{ \App\Support\Materialverleih::LOESCHEN_MONATE }} Monate nach der Rückgabe.
                            Mehr in der <a href="{{ url('/datenschutz') }}" class="text-dpsg-rot underline">Datenschutzerklärung</a>.
                        </p>
                        <button type="submit" class="min-h-11 rounded-md bg-dpsg-rot px-6 py-2.5 font-bold text-white hover:bg-dpsg-rot/90">Anfrage senden</button>
                    </form>
                @endif
            </section>
        @endif
    </div>
@endsection
