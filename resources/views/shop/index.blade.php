@extends('layouts.app')

{{-- Sammelbestellung: Angebot als kompakte Liste (Rüsthaus-Artikel, eigene
     Artikel, Sets) und Bestellung wie ein Formular: oben die Mitglieder, je
     Artikel Größe, Anzahl und „Hinzufügen“, unten die Übersicht. Die Logik
     steckt in resources/js/shop-bestellung.js (keine Inline-Skripte, CSP).
     Erwartet: $page (optional, Einleitung), $testbetrieb, $runde, $bestellbar, $fehlt, $sets, $artikel --}}

@section('seo_title', ($page->title ?? 'Ausrüstung bestellen').' – '.config('dpsg.site.name'))

@php($euro = fn (?int $cent) => \App\Support\Sammelbestellung::euro($cent))
@php($katalog = $artikel->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'varianten' => $a->varianten->map(fn ($v) => ['id' => $v->id, 'bezeichnung' => $v->bezeichnung, 'preis' => $v->preis_cent])->values()])->values())
@php($setKatalog = $sets->map(fn ($s) => ['id' => 's'.$s->id, 'name' => $s->name, 'teile' => $s->teile->filter(fn ($t) => $t->artikel?->freigegeben && $t->artikel->varianten->isNotEmpty())->map(fn ($t) => [
    'id' => $t->id, 'name' => $t->artikel->name, 'menge' => $t->menge, 'optional' => $t->optional, 'vorgewaehlt' => $t->vorgewaehlt,
    'varianten' => $t->artikel->varianten->map(fn ($v) => ['id' => $v->id, 'bezeichnung' => $v->bezeichnung, 'preis' => $v->preis_cent])->values(),
])->values()])->values())
@php($stufen = collect(config('dpsg.stages'))->map(fn ($s) => $s['label']))
@php($gruppen = $artikel->groupBy(fn ($a) => $a->kategorie ?: ($a->eigen ? 'Vom Stamm' : 'Weitere Artikel')))
@php($feld = 'border border-dpsg-mittelbeige bg-white px-3 py-2')

@section('content')
    <div class="mx-auto max-w-4xl px-4 py-12">
        @if ($testbetrieb)
            <p class="mb-6 border-l-4 border-dpsg-rot bg-white px-4 py-3 font-semibold shadow-sm" role="status">Testbetrieb: Diese Seite sehen nur Angemeldete aus dem Backend. Bestellungen hier sind als Test markiert und werden später gelöscht.</p>
        @endif
        <h1 class="font-serif text-3xl font-bold md:text-4xl">{{ $page->title ?? 'Ausrüstung bestellen' }}</h1>
        <div class="prose prose-lg mt-4 max-w-3xl">
            @if ($page)
                {!! str($page->body)->sanitizeHtml()->glossar()->embeds()->mailformular() !!}
            @else
                <p>Kluft, Halstuch und Aufnäher bestellen wir gesammelt beim Rüsthaus. Du bezahlst vorab, wir bestellen zum Bestelldatum und geben die Sachen in der Gruppenstunde aus.</p>
            @endif
        </div>

        @if ($runde)
            <p class="mt-6 border-l-4 border-dpsg-rot bg-white px-4 py-3 shadow-sm">
                <strong>{{ $runde->name }}:</strong> Bestellen und bezahlen bis zum <strong>{{ $runde->bestelldatum->format('d.m.Y') }}</strong>. Dann bestellen wir beim Rüsthaus.
            </p>
        @else
            <p class="mt-6 border-l-4 border-dpsg-mittelbeige bg-white px-4 py-3 shadow-sm">Gerade läuft keine Sammelbestellung. Die nächste kündigen wir hier und in den Gruppenstunden an.</p>
        @endif

        @if (! $bestellbar && $fehlt !== [])
            <div class="mt-10 border-l-4 border-dpsg-rot bg-white px-4 py-3 text-sm shadow-sm" role="status">
                <p class="font-bold">Nur für dich sichtbar (Backend): Das Bestellformular erscheint, sobald Folgendes erledigt ist:</p>
                <ul class="mt-1 list-disc pl-5">@foreach ($fehlt as $punkt)<li>{{ $punkt }}</li>@endforeach</ul>
            </div>
        @endif


        @if ($bestellbar)
        <div x-data="shopBestellung(@js($katalog), @js($setKatalog), @js(old('pos', [])), @js(route('shop.nachschlagen')), @js($stufen))">
            {{-- 1. Für wen --}}
            <section class="mt-10 bg-white p-4 shadow-md md:p-6" aria-labelledby="fuer-heading">
                <h2 id="fuer-heading" class="font-serif text-xl font-bold">1. Für wen bestellst du?</h2>
                <p class="mt-1 text-sm text-text-secondary">Vor- und Nachname des Mitglieds. Für Geschwister einfach ein weiteres Mitglied hinzufügen.</p>
                <div class="mt-3 space-y-2">
                    <template x-for="(m, mi) in mitglieder" :key="m.key">
                        <div class="flex flex-wrap items-center gap-2">
                            <input type="radio" name="_aktiv" :value="m.key" x-model.number="aktiv" x-show="mitglieder.length > 1" class="h-5 w-5" :aria-label="'Artikel für ' + (m.name || 'dieses Mitglied') + ' hinzufügen'" />
                            <label class="sr-only" :for="'m-name-' + m.key">Vor- und Nachname</label>
                            <input :id="'m-name-' + m.key" type="text" x-model="m.name" @focus="aktiv = m.key" maxlength="120" placeholder="Vor- und Nachname" class="{{ $feld }} min-w-0 flex-1 basis-48" />
                            <label class="sr-only" :for="'m-stufe-' + m.key">Stufe</label>
                            <select :id="'m-stufe-' + m.key" x-model="m.stufe" class="{{ $feld }} basis-36">
                                <option value="">Stufe</option>
                                @foreach ($stufen as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                            </select>
                            <button type="button" x-show="mitglieder.length > 1" @click="mitgliedEntfernen(m)" class="text-sm text-dpsg-rot underline">entfernen</button>
                        </div>
                    </template>
                </div>
                <button type="button" @click="mitgliedNeu()" class="mt-3 text-sm font-bold text-dpsg-blau underline">+ weiteres Mitglied</button>
            </section>
        @endif

            {{-- 2. Angebot --}}
            <section class="mt-8" aria-labelledby="shop-heading">
                <h2 id="shop-heading" class="font-serif text-xl font-bold">{{ $bestellbar ? '2. Artikel auswählen' : 'Unser Angebot' }}</h2>
                @if ($bestellbar)
                    <p class="sticky top-20 z-10 mt-2 bg-surface-muted px-3 py-2 text-sm shadow-sm" x-show="mitglieder.length > 1">
                        Hinzufügen für: <strong x-text="aktivName()"></strong> <span class="text-text-secondary">(oben umschalten)</span>
                    </p>
                @endif

                @if ($sets->isNotEmpty())
                    <h3 class="mt-6 text-sm font-bold uppercase tracking-wide text-text-secondary">Sets</h3>
                    <ul class="mt-2 divide-y divide-surface-muted bg-white shadow-md">
                        @foreach ($sets as $s)
                            @php($sid = 's'.$s->id)
                            <li class="flex gap-3 p-3">
                                @if ($s->bildArtikel?->bild)
                                    <x-picture :media="$s->bildArtikel->bild" sizes="64px" class="h-16 w-16 shrink-0 bg-white object-contain" loading="lazy" />
                                @endif
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <h4 class="font-bold">{{ $s->name }} <span class="ml-1 text-xs font-bold uppercase text-dpsg-rot">Set</span></h4>
                                        <span class="whitespace-nowrap text-sm">ab {{ $euro(\App\Support\Sammelbestellung::setPreisAb($s)) }}</span>
                                    </div>
                                    <p class="text-sm text-text-secondary">
                                        @foreach ($s->teile->filter(fn ($t) => $t->artikel?->freigegeben) as $t){{ $loop->first ? '' : ', ' }}{{ $t->menge > 1 ? $t->menge.' × ' : '' }}{{ $t->artikel->name }}{{ $t->optional ? ' (optional)' : '' }}@endforeach
                                    </p>
                                    @if ($s->beschreibung)
                                        <details class="mt-1 text-sm"><summary class="cursor-pointer text-dpsg-rot">Details</summary><p class="mt-1 whitespace-pre-line">{{ $s->beschreibung }}</p></details>
                                    @endif
                                    @if ($bestellbar)
                                        <button type="button" class="mt-2 text-sm font-bold text-dpsg-blau underline" @click="setWahl[@js($sid)].offen = ! setWahl[@js($sid)].offen" x-text="setWahl[@js($sid)].offen ? 'schließen' : 'Größen wählen und hinzufügen'"></button>
                                        <div x-show="setWahl[@js($sid)].offen" class="mt-2 space-y-2 border-l-4 border-dpsg-mittelbeige pl-3">
                                            <template x-for="t in setTeile(@js($sid))" :key="t.id">
                                                <div class="flex flex-wrap items-center gap-2 text-sm">
                                                    <label class="inline-flex min-w-0 flex-1 basis-40 items-center gap-2">
                                                        <input type="checkbox" x-show="t.optional" x-model="setWahl[@js($sid)].teile[t.id].dabei" class="h-5 w-5" />
                                                        <span x-text="(t.menge > 1 ? t.menge + ' × ' : '') + t.name + (t.optional ? ' (optional)' : '')"></span>
                                                    </label>
                                                    <select x-show="t.varianten.length > 1" x-model="setWahl[@js($sid)].teile[t.id].variante" :disabled="t.optional && ! setWahl[@js($sid)].teile[t.id].dabei" :aria-label="'Größe ' + t.name" class="{{ $feld }} min-w-0 flex-1 basis-48">
                                                        <option value="">Größe wählen</option>
                                                        <template x-for="v in t.varianten" :key="v.id"><option :value="v.id" x-text="(v.bezeichnung || 'Standard') + ' – ' + euro(v.preis)"></option></template>
                                                    </select>
                                                    <span x-show="t.varianten.length === 1" class="text-text-secondary" x-text="euro(t.varianten[0].preis)"></span>
                                                </div>
                                            </template>
                                            @include('shop.partials.menge-hinzufuegen', ['ziel' => "setWahl['$sid']", 'aktion' => "setHinzufuegen('$sid')", 'schluessel' => $sid])
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @foreach ($gruppen as $kategorie => $liste)
                    <h3 class="mt-6 text-sm font-bold uppercase tracking-wide text-text-secondary">{{ $kategorie }}</h3>
                    <ul class="mt-2 divide-y divide-surface-muted bg-white shadow-md">
                        @foreach ($liste as $a)
                            @php($ab = $a->varianten->min('preis_cent') ?? $a->preis_cent)
                            <li class="flex gap-3 p-3">
                                @if ($a->bild)
                                    <x-picture :media="$a->bild" sizes="64px" class="h-16 w-16 shrink-0 bg-white object-contain" loading="lazy" />
                                @endif
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <h4 class="font-bold">{{ $a->name }}</h4>
                                        <span class="whitespace-nowrap text-sm">{{ $a->varianten->max('preis_cent') !== $ab ? 'ab ' : '' }}{{ $euro($ab) }}</span>
                                    </div>
                                    <p class="text-xs text-text-secondary">{{ $a->eigen ? 'vom Stamm' : 'Rüsthaus-Nr. '.$a->nummer }}@if (! $bestellbar && $a->varianten->count() > 1) · Größen: {{ $a->varianten->pluck('bezeichnung')->filter()->implode(', ') }}@endif</p>
                                    @if ($a->beschreibung)
                                        <details class="mt-1 text-sm"><summary class="cursor-pointer text-dpsg-rot">Details</summary><p class="mt-1 whitespace-pre-line">{{ $a->beschreibung }}</p></details>
                                    @endif
                                    @if ($bestellbar)
                                        <div class="mt-2 flex flex-wrap items-center gap-2">
                                            @if ($a->varianten->count() > 1)
                                                <select x-model="wahl[{{ $a->id }}].variante" aria-label="Größe {{ $a->name }}" class="{{ $feld }} min-w-0 flex-1 basis-48 text-sm">
                                                    <option value="">Größe wählen</option>
                                                    @foreach ($a->varianten as $v)<option value="{{ $v->id }}">{{ $v->bezeichnung ?: 'Standard' }}{{ $v->preis_cent !== $ab ? ' – '.$euro($v->preis_cent) : '' }}</option>@endforeach
                                                </select>
                                            @endif
                                            @include('shop.partials.menge-hinzufuegen', ['ziel' => 'wahl['.$a->id.']', 'aktion' => 'artikelHinzufuegen('.$a->id.')', 'schluessel' => (string) $a->id, 'inline' => true])
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endforeach

                @if ($bestellbar)
                    <h3 class="mt-6 text-sm font-bold uppercase tracking-wide text-text-secondary">Etwas anderes vom Rüsthaus</h3>
                    <div class="mt-2 bg-white p-3 shadow-md">
                        <p class="text-sm text-text-secondary">Rüsthaus-Artikelnummer oder Link eintragen. Wir prüfen Artikel und Preis und melden uns.</p>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <label class="sr-only" for="frei-nummer">Rüsthaus-Artikelnummer oder Link</label>
                            <input id="frei-nummer" type="text" x-model="frei.nummer" maxlength="300" placeholder="z. B. 0100003.32" class="{{ $feld }} min-w-0 flex-1 basis-40" />
                            <button type="button" @click="nachschlagen()" class="border border-dpsg-blau px-3 py-2 text-sm font-bold text-dpsg-blau hover:bg-dpsg-blau hover:text-white">Prüfen</button>
                        </div>
                        <p class="mt-1 text-sm" x-show="frei.info" x-text="frei.info" :class="frei.gesperrt ? 'text-dpsg-rot' : 'text-text-secondary'"></p>
                        <label class="sr-only" for="frei-text">Größe bzw. was genau</label>
                        <input id="frei-text" type="text" x-model="frei.text" maxlength="160" placeholder="Größe bzw. was genau (falls nötig)" class="{{ $feld }} mt-2 w-full" />
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            @include('shop.partials.menge-hinzufuegen', ['ziel' => 'frei', 'aktion' => 'freiHinzufuegen()', 'schluessel' => 'frei', 'inline' => true])
                        </div>
                    </div>
                @endif
            </section>

        @if ($bestellbar)
            {{-- 3. Übersicht und Angaben --}}
            <section id="bestellformular" class="mt-8 scroll-mt-24 bg-white p-4 shadow-md md:p-6" aria-labelledby="bestellen-heading">
                <h2 id="bestellen-heading" class="font-serif text-xl font-bold">3. Deine Bestellung</h2>

                @if ($errors->any())
                    <div class="mt-4 border-l-4 border-dpsg-rot bg-surface-muted p-3 text-sm text-dpsg-rot" role="alert">
                        <ul class="list-disc pl-5">@foreach ($errors->all() as $fehler)<li>{{ $fehler }}</li>@endforeach</ul>
                    </div>
                @endif

                <p class="mt-3 text-sm text-text-secondary" x-show="! eintraege.length">Noch nichts ausgewählt. Oben bei einem Artikel Größe und Anzahl wählen und „Hinzufügen“ tippen.</p>
                <template x-for="m in mitglieder" :key="m.key">
                    <div class="mt-4" x-show="eintraegeFuer(m).length">
                        <h3 class="font-bold" x-text="(m.name || 'Ohne Namen') + (m.stufe ? ' · ' + stufeName(m.stufe) : '')"></h3>
                        <ul class="mt-1 divide-y divide-surface-muted text-sm">
                            <template x-for="e in eintraegeFuer(m)" :key="e.key">
                                <li class="flex items-start justify-between gap-3 py-2">
                                    <span class="min-w-0">
                                        <span x-text="e.menge + ' × ' + e.label"></span>
                                        <span class="block text-xs text-text-secondary" x-show="e.detail" x-text="e.detail"></span>
                                    </span>
                                    <span class="flex shrink-0 items-center gap-3">
                                        <span x-text="e.preis === null ? 'wird geprüft' : euro(e.preis * e.menge)"></span>
                                        <button type="button" @click="entfernen(e)" class="text-dpsg-rot" :aria-label="'Entfernen: ' + e.label">✕</button>
                                    </span>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>
                <p class="mt-4 border-t border-surface-muted pt-3 text-right" x-show="eintraege.length">Summe: <strong x-text="euro(summe())"></strong><span class="block text-xs text-text-secondary" x-show="eintraege.some(e => e.preis === null)">ohne frei eingegebene Artikel</span></p>
                <p class="mt-2 text-sm text-dpsg-rot" x-show="eintraege.some(e => ! mitgliedVon(e).name.trim())">Bitte oben bei jedem Mitglied den Namen eintragen.</p>

                <form method="post" action="{{ route('shop.store') }}" class="mt-6 space-y-6" novalidate>
                    @csrf
                    <input type="hidden" name="_t" value="{{ \App\Support\SpamGuard::token() }}" />
                    <div class="hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off" /></label></div>
                    <template x-for="(e, i) in eintraege" :key="e.key">
                        <div>
                            <input type="hidden" :name="'pos[' + i + '][mitglied]'" :value="mitgliedVon(e).name" />
                            <input type="hidden" :name="'pos[' + i + '][stufe]'" :value="mitgliedVon(e).stufe" />
                            <input type="hidden" :name="'pos[' + i + '][menge]'" :value="e.menge" />
                            <template x-if="e.typ === 'artikel'"><input type="hidden" :name="'pos[' + i + '][variante]'" :value="e.variante" /></template>
                            <template x-if="e.typ === 'frei'">
                                <div>
                                    <input type="hidden" :name="'pos[' + i + '][frei_nummer]'" :value="e.nummer" />
                                    <input type="hidden" :name="'pos[' + i + '][frei_text]'" :value="e.text" />
                                </div>
                            </template>
                            <template x-if="e.typ === 'set'">
                                <div>
                                    <input type="hidden" :name="'pos[' + i + '][set]'" :value="e.set" />
                                    <template x-for="[tid, t] in Object.entries(e.teile)" :key="tid">
                                        <div>
                                            <input type="hidden" :name="'pos[' + i + '][teile][' + tid + '][variante]'" :value="t.variante" />
                                            <input type="hidden" :name="'pos[' + i + '][teile][' + tid + '][dabei]'" :value="t.dabei ? 1 : 0" />
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>

                    <fieldset class="grid gap-4 sm:grid-cols-2">
                        <legend class="mb-2 font-bold">Deine Angaben</legend>
                        <div>
                            <label for="shop-name" class="block text-sm font-bold">Dein Name</label>
                            <input id="shop-name" type="text" name="name" required maxlength="120" value="{{ old('name') }}" autocomplete="name" class="mt-1 w-full border border-dpsg-mittelbeige px-3 py-2" />
                        </div>
                        <div>
                            <label for="shop-email" class="block text-sm font-bold">Deine E-Mail-Adresse</label>
                            <input id="shop-email" type="email" name="email" required maxlength="254" value="{{ old('email') }}" autocomplete="email" class="mt-1 w-full border border-dpsg-mittelbeige px-3 py-2" />
                        </div>
                        <div>
                            <label for="shop-telefon" class="block text-sm font-bold">Telefon <span class="font-normal text-text-secondary">(optional)</span></label>
                            <input id="shop-telefon" type="tel" name="telefon" maxlength="40" value="{{ old('telefon') }}" autocomplete="tel" class="mt-1 w-full border border-dpsg-mittelbeige px-3 py-2" />
                        </div>
                        <div>
                            <label for="shop-abholung" class="block text-sm font-bold">Abholung</label>
                            <select id="shop-abholung" name="abholung" class="mt-1 w-full border border-dpsg-mittelbeige bg-white px-3 py-2">
                                <option value="Gruppenstunde" @selected(old('abholung') === 'Gruppenstunde')>in der Gruppenstunde</option>
                                <option value="nach Absprache" @selected(old('abholung') === 'nach Absprache')>nach Absprache</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            @foreach (\App\Models\Shop\Bestellung::ROLLEN as $wert => $label)
                                <label class="mr-6 inline-flex items-center gap-2"><input type="radio" name="rolle" value="{{ $wert }}" @checked(old('rolle', 'erziehungsberechtigt') === $wert) /> {{ $label }}</label>
                            @endforeach
                        </div>
                        <div class="sm:col-span-2">
                            <label for="shop-bemerkung" class="block text-sm font-bold">Bemerkung <span class="font-normal text-text-secondary">(optional)</span></label>
                            <textarea id="shop-bemerkung" name="bemerkung" maxlength="2000" rows="3" class="mt-1 w-full border border-dpsg-mittelbeige px-3 py-2">{{ old('bemerkung') }}</textarea>
                        </div>
                    </fieldset>

                    <label class="flex items-start gap-2 text-sm">
                        <input type="checkbox" name="einwilligung" value="1" class="mt-1" @checked(old('einwilligung')) />
                        <span>Ich bin Mitglied des Stamms bzw. erziehungsberechtigt für die Mitglieder, für die ich bestelle. Ich willige ein, dass der {{ config('dpsg.site.name') }} meine Angaben für die Sammelbestellung, die Bezahlung und die Ausgabe verarbeitet. Mehr in der <a href="{{ url('/datenschutz') }}" class="text-dpsg-rot underline">Datenschutzerklärung</a>.</span>
                    </label>

                    <button type="submit" :disabled="! eintraege.length" class="inline-flex items-center gap-2 bg-dpsg-blau px-5 py-2 font-bold text-white hover:bg-dpsg-rot disabled:opacity-50">Bestellung abschicken</button>
                    <p class="text-xs text-text-secondary">Du bekommst eine Mail mit einem Link zum Bestätigen. Danach folgt die Zahlungsaufforderung.</p>
                </form>
            </section>
        </div>
        @endif
    </div>
@endsection
