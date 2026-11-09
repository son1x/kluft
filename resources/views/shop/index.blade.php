@extends('layouts.app')

{{-- Sammelbestellung (/bestellen), Pflichtenheft docs/pflichtenheft-sammelbestellung-shop.md:
     Raster mit Kategorien und Suche, Artikel-Sheet (Größe, für wen, Anzahl), Warenkorb-Leiste,
     Warenkorb nach Mitglied und Bestellen in drei Schritten. Logik in resources/js/shop-bestellung.js
     (keine Inline-Skripte, CSP). Abgeschickt wird wie bisher als pos[i][…].
     Erwartet: $page (optional, Einleitung), $testbetrieb, $runde, $bestellbar, $fehlt, $sets, $artikel --}}

@section('seo_title', ($page->title ?? 'Ausrüstung bestellen').' – '.config('dpsg.site.name'))

@php($euro = fn (?int $cent) => \App\Support\Sammelbestellung::euro($cent))
@php($stufen = collect(config('dpsg.stages'))->map(fn ($s) => $s['label']))
@php($varianten = fn ($liste) => $liste->map(fn ($v) => ['id' => $v->id, 'bezeichnung' => $v->bezeichnung, 'preis' => $v->preis_cent])->values())
@php($katalog = $artikel->map(fn ($a) => [
    'id' => 'a'.$a->id, 'typ' => 'artikel', 'name' => $a->name, 'kategorie' => $a->kategorie, 'eigen' => $a->eigen, 'hervorheben' => $a->hervorheben,
    'nummer' => $a->eigen ? null : $a->nummer, 'beschreibung' => $a->beschreibung, 'url' => $a->eigen ? null : $a->url,
    'bild' => $a->bild?->url(), 'bildAlt' => $a->bild?->alt_text,
    'preisAb' => $a->varianten->min('preis_cent') ?? $a->preis_cent, 'varianten' => $varianten($a->varianten),
])->values())
@php($setKatalog = $sets->map(fn ($s) => [
    'id' => 's'.$s->id, 'typ' => 'set', 'name' => $s->name, 'kategorie' => null, 'eigen' => false, 'hervorheben' => $s->hervorheben, 'nummer' => null,
    'beschreibung' => $s->beschreibung, 'bild' => $s->bildArtikel?->bild?->url(), 'bildAlt' => $s->bildArtikel?->bild?->alt_text,
    'preisAb' => \App\Support\Sammelbestellung::setPreisAb($s),
    'teile' => $s->teile->filter(fn ($t) => $t->artikel?->freigegeben && $t->artikel->varianten->isNotEmpty())->map(fn ($t) => [
        'id' => $t->id, 'name' => $t->artikel->name, 'menge' => $t->menge, 'optional' => $t->optional, 'vorgewaehlt' => $t->vorgewaehlt, 'varianten' => $varianten($t->artikel->varianten),
    ])->values(),
])->values())
@php($kategorien = collect(['alle' => 'Alle'])
    ->when($artikel->contains('hervorheben', true) || $sets->contains('hervorheben', true), fn ($c) => $c->put('start', 'Für den Start'))
    ->merge($artikel->where('eigen', false)->pluck('kategorie')->filter()->unique()->sort()->mapWithKeys(fn ($k) => [$k => $k]))
    ->when($sets->isNotEmpty(), fn ($c) => $c->put('sets', 'Sets'))
    ->when($artikel->contains('eigen', true), fn ($c) => $c->put('stamm', 'Vom Stamm')))
@php($fehlerSchritt = match (true) {
    ! $errors->any() => null,
    $errors->hasAny(['pos', 'pos.*']) || collect($errors->keys())->contains(fn ($k) => str_starts_with($k, 'pos.')) => 0,
    $errors->hasAny(['name', 'email', 'telefon', 'rolle']) => 1,
    $errors->hasAny(['abholung', 'bemerkung']) => 2,
    default => 3,
})
@php($feld = 'mt-1 h-11 w-full rounded-xl border border-dpsg-mittelbeige bg-white px-3 text-base focus:border-dpsg-blau focus:outline-none focus:ring-2 focus:ring-dpsg-blau/30')
@php($kapsel = 'inline-flex h-11 items-center rounded-full px-4 text-[15px] font-semibold focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-dpsg-blau')
@php($hauptknopf = 'inline-flex h-12 items-center justify-center gap-2 rounded-full bg-dpsg-blau px-6 text-base font-semibold text-white hover:bg-[#00447a] focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-dpsg-blau disabled:opacity-50')

@section('content')
<div class="mx-auto max-w-6xl px-4 pb-40 pt-10 font-sans"
    x-data="shopBestellung(@js(['katalog' => $katalog, 'sets' => $setKatalog, 'alt' => old('pos', []), 'nachschlagenUrl' => route('shop.nachschlagen'), 'stufen' => $stufen, 'besteller' => old('name', ''), 'fehlerSchritt' => $bestellbar ? $fehlerSchritt : null]))">

    @if ($testbetrieb)
        <p class="mb-6 rounded-2xl bg-white px-4 py-3 text-sm font-semibold shadow-sm ring-1 ring-dpsg-rot/30" role="status">Testbetrieb: Diese Seite sehen nur Angemeldete aus dem Backend. Bestellungen hier sind als Test markiert und werden später gelöscht.</p>
    @endif

    {{-- Kopf: Titel und Rundenstatus (SB-110) --}}
    <header class="flex flex-col gap-2">
        <h1 class="font-serif text-3xl font-bold text-dpsg-blau md:text-4xl">{{ $page->title ?? 'Ausrüstung bestellen' }}</h1>
        <p class="flex items-start gap-2 text-[15px] text-text-secondary">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-dpsg-blau" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
            @if ($runde)
                <span><strong class="text-text">{{ $runde->name }}</strong> · bestellen und bezahlen bis {{ $runde->bestelldatum->format('d.m.Y') }}. Danach bestellen wir gesammelt beim Rüsthaus.</span>
            @else
                <span>Gerade läuft keine Sammelbestellung. Du kannst dich umsehen; die nächste kündigen wir hier und in den Gruppenstunden an.</span>
            @endif
        </p>
        @if ($page)
            <details class="mt-1 max-w-3xl text-[15px]">
                <summary class="inline-flex min-h-11 cursor-pointer items-center font-semibold text-dpsg-blau">So funktioniert die Sammelbestellung</summary>
                <div class="prose mt-2">{!! str($page->body)->sanitizeHtml()->glossar()->embeds()->mailformular() !!}</div>
            </details>
        @endif
    </header>

    @if (! $bestellbar && $fehlt !== [])
        <div class="mt-6 rounded-2xl bg-white px-4 py-3 text-sm shadow-sm ring-1 ring-dpsg-rot/30" role="status">
            <p class="font-bold">Nur für dich sichtbar (Backend): Bestellen geht, sobald Folgendes erledigt ist:</p>
            <ul class="mt-1 list-disc pl-5">@foreach ($fehlt as $punkt)<li>{{ $punkt }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Suche (SB-113) --}}
    <div class="mt-6 max-w-xl">
        <label for="shop-suche" class="sr-only">Artikel suchen</label>
        <div class="flex h-11 items-center gap-2 rounded-xl bg-surface-muted px-3">
            <svg class="h-5 w-5 shrink-0 text-text-secondary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            <input id="shop-suche" type="search" x-model.debounce.150ms="suche" placeholder="Kluft, Halstuch, Aufnäher …" class="min-w-0 flex-1 bg-transparent text-base outline-none" autocomplete="off" />
        </div>
    </div>

    {{-- Kategorien (SB-111), beim Scrollen oben --}}
    <nav class="shop-glas sticky top-0 z-20 -mx-4 mt-3 px-4 py-2.5" aria-label="Kategorien">
        <div class="-my-1 flex gap-2 overflow-x-auto py-1" role="tablist">
            @foreach ($kategorien as $schluessel => $label)
                <button type="button" role="tab" @click="kategorie = @js((string) $schluessel)" :aria-selected="kategorie === @js((string) $schluessel)"
                    :class="kategorie === @js((string) $schluessel) ? 'bg-dpsg-blau text-white' : 'bg-white text-text ring-1 ring-dpsg-mittelbeige'"
                    class="{{ $kapsel }} shrink-0 whitespace-nowrap">{{ $label }}</button>
            @endforeach
        </div>
    </nav>

    {{-- Raster (SB-112) --}}
    <section aria-label="Angebot" class="mt-4">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ([...$setKatalog, ...$katalog] as $karte)
                @php($quelle = $karte['typ'] === 'set' ? $sets->firstWhere('id', (int) substr($karte['id'], 1))?->bildArtikel?->bild : $artikel->firstWhere('id', (int) substr($karte['id'], 1))?->bild)
                <article x-show="zeigen(@js($karte['id']))" class="flex flex-col rounded-2xl bg-white p-2 shadow-sm ring-1 ring-black/5">
                    <button type="button" @click="oeffnen(@js($karte['id']))" class="group flex flex-1 flex-col gap-2 rounded-xl text-left focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-dpsg-blau" aria-label="{{ $karte['name'] }}, {{ $karte['typ'] === 'set' || $karte['varianten']->pluck('preis')->unique()->count() > 1 ? 'ab ' : '' }}{{ $euro($karte['preisAb']) }}">
                        <span class="relative block aspect-square overflow-hidden rounded-xl bg-surface-muted">
                            @if ($quelle)
                                <x-picture :media="$quelle" sizes="(min-width: 1024px) 280px, (min-width: 640px) 33vw, 50vw" class="h-full w-full object-contain p-2 transition group-hover:scale-[1.02] motion-reduce:transition-none" loading="lazy" />
                            @else
                                <svg class="absolute inset-0 m-auto h-14 w-14 text-dpsg-blau/60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" aria-hidden="true"><path d="M3 8l9-4 9 4v9l-9 4-9-4z"/><path d="M3 8l9 4 9-4M12 12v9"/></svg>
                            @endif
                            @if ($karte['typ'] === 'set' || $karte['eigen'])
                                <span class="absolute left-2 top-2 rounded-full bg-white/90 px-2 py-0.5 text-xs font-semibold text-dpsg-blau">{{ $karte['typ'] === 'set' ? 'Set' : 'Vom Stamm' }}</span>
                            @endif
                        </span>
                        <span class="flex items-end justify-between gap-2 px-1 pb-1">
                            <span class="min-w-0">
                                <span class="line-clamp-2 block text-[15px] font-semibold leading-snug">{{ $karte['name'] }}</span>
                                <span class="block text-sm text-text-secondary">{{ $karte['typ'] === 'set' || $karte['varianten']->pluck('preis')->unique()->count() > 1 ? 'ab ' : '' }}{{ $euro($karte['preisAb']) }}</span>
                            </span>
                            @if ($bestellbar)
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-dpsg-blau text-white" aria-hidden="true">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                                </span>
                            @endif
                        </span>
                    </button>
                </article>
            @endforeach
        </div>
        <p x-show="anzahlSichtbar() === 0" x-cloak class="mt-6 rounded-2xl bg-white p-4 text-[15px] shadow-sm">Nichts gefunden. Unten kannst du einen anderen Rüsthaus-Artikel per Artikelnummer bestellen.</p>
    </section>

    @if ($bestellbar)
        {{-- Anderer Rüsthaus-Artikel (SB-115), eingeklappt --}}
        <details class="mt-8 max-w-2xl rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5">
            <summary class="flex min-h-11 cursor-pointer items-center justify-between gap-3">
                <span><span class="block text-[15px] font-semibold">Etwas anderes vom Rüsthaus?</span><span class="block text-sm text-text-secondary">Per Artikelnummer oder Link bestellen; wir prüfen Artikel und Preis.</span></span>
                <span class="text-[15px] font-semibold text-dpsg-blau">Öffnen</span>
            </summary>
            <div class="mt-3 flex flex-col gap-2">
                <div class="flex flex-wrap items-end gap-2">
                    <label class="min-w-0 flex-1 basis-56 text-sm font-semibold">Rüsthaus-Artikelnummer oder Link
                        <input type="text" x-model="frei.nummer" maxlength="300" placeholder="z. B. 0100003.32" class="{{ $feld }}" />
                    </label>
                    <button type="button" @click="nachschlagen()" class="{{ $kapsel }} bg-white text-dpsg-blau ring-1 ring-dpsg-blau">Prüfen</button>
                </div>
                <p class="text-sm" x-show="frei.info" x-text="frei.info" :class="frei.gesperrt ? 'text-dpsg-rot' : 'text-text-secondary'" role="status"></p>
                <label class="text-sm font-semibold">Größe bzw. was genau <span class="font-normal text-text-secondary">(falls nötig)</span>
                    <input type="text" x-model="frei.text" maxlength="160" class="{{ $feld }}" />
                </label>
                <button type="button" @click="freiHinzufuegen()" class="{{ $hauptknopf }} self-start">In den Warenkorb</button>
            </div>
        </details>

        {{-- Meldung nach dem Hinzufügen (für Screenreader) --}}
        <p class="sr-only" role="status" aria-live="polite" x-text="meldung"></p>

        {{-- Warenkorb-Leiste (SB-130) --}}
        <div x-show="eintraege.length" x-cloak class="fixed inset-x-0 bottom-0 z-30 px-3 pb-[max(1rem,env(safe-area-inset-bottom))]">
            <div class="shop-glas mx-auto flex h-16 max-w-xl items-center justify-between rounded-full pl-5 pr-2 shadow-[0_8px_30px_rgba(0,30,60,0.18)] ring-1 ring-white/70">
                <span class="flex flex-col leading-tight">
                    <span class="text-sm text-text-secondary" x-text="anzahl() === 1 ? '1 Artikel' : anzahl() + ' Artikel'"></span>
                    <span class="text-lg font-bold" x-text="euro(summe()) + (hatVorlaeufige() ? ' +' : '')"></span>
                </span>
                <button type="button" @click="kasseOeffnen(0)" class="{{ $hauptknopf }}">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M5 8h14l-1.5 12h-11z"/><path d="M9 8V6a3 3 0 016 0v2"/></svg>
                    Warenkorb
                </button>
            </div>
        </div>
    @endif

    {{-- Artikel-Sheet (SB-120/121) --}}
    <dialog x-ref="sheet" class="shop-sheet" aria-labelledby="sheet-titel" @close="schliessen()" @click.self="schliessen()">
        <template x-if="aktuell()">
            <div class="flex max-h-[inherit] flex-col">
                <div class="flex items-center justify-between px-5 pt-3">
                    <span class="text-sm text-text-secondary" x-text="aktuell().typ === 'set' ? 'Set' : (aktuell().kategorie || (aktuell().eigen ? 'Vom Stamm' : ''))"></span>
                    <button type="button" @click="schliessen()" class="flex h-11 w-11 items-center justify-center rounded-full bg-surface-muted" aria-label="Schließen">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </div>
                <div class="flex flex-col gap-5 overflow-y-auto px-5 pb-6 pt-2">
                    <div class="flex aspect-[4/3] items-center justify-center overflow-hidden rounded-2xl bg-white">
                        <template x-if="aktuell().bild"><img :src="aktuell().bild" :alt="aktuell().bildAlt || ''" class="h-full w-full object-contain p-3" /></template>
                        <template x-if="! aktuell().bild"><svg class="h-20 w-20 text-dpsg-blau/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true"><path d="M3 8l9-4 9 4v9l-9 4-9-4z"/></svg></template>
                    </div>
                    <div>
                        <h2 id="sheet-titel" class="font-serif text-2xl font-bold text-dpsg-blau" x-text="aktuell().name"></h2>
                        <p class="mt-1 text-xl font-bold" x-text="sheetPreisText()"></p>
                        <p class="text-sm text-text-secondary" x-text="aktuell().eigen ? 'Preis des Stamms · Abholung in der Gruppenstunde' : 'inkl. MwSt. · Originalpreis des Rüsthaus · kein Versand, Abholung in der Gruppenstunde'"></p>
                    </div>

                    @if ($bestellbar)
                        {{-- Größe --}}
                        <template x-if="aktuell().typ === 'artikel' && aktuell().varianten.length > 1">
                            <fieldset>
                                <legend class="text-[17px] font-bold">Größe</legend>
                                <div class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-4">
                                    <template x-for="v in aktuell().varianten" :key="v.id">
                                        <label class="relative">
                                            <input type="radio" name="sheet-groesse" :value="String(v.id)" x-model="sheet.variante" class="peer sr-only" />
                                            <span class="flex min-h-11 items-center justify-center rounded-xl bg-white px-2 py-2 text-center text-[15px] font-semibold ring-1 ring-dpsg-mittelbeige peer-checked:bg-dpsg-blau peer-checked:text-white peer-checked:ring-dpsg-blau peer-focus-visible:outline-3 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-dpsg-blau" x-text="v.bezeichnung || 'Standard'"></span>
                                        </label>
                                    </template>
                                </div>
                            </fieldset>
                        </template>

                        {{-- Set: Teile --}}
                        <template x-if="aktuell().typ === 'set'">
                            <fieldset class="rounded-2xl bg-white p-4">
                                <legend class="sr-only">Teile des Sets</legend>
                                <div class="flex flex-col gap-3">
                                    <template x-for="t in aktuell().teile" :key="t.id">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <label class="flex min-h-11 min-w-0 flex-1 basis-40 items-center gap-3 text-[15px]">
                                                <input type="checkbox" x-show="t.optional" x-model="sheet.teile[t.id].dabei" class="h-5 w-5 accent-dpsg-blau" />
                                                <span x-text="(t.menge > 1 ? t.menge + ' × ' : '') + t.name + (t.optional ? ' (optional)' : '')"></span>
                                            </label>
                                            <select x-show="t.varianten.length > 1" x-model="sheet.teile[t.id].variante" :disabled="t.optional && ! sheet.teile[t.id].dabei" :aria-label="'Größe ' + t.name" class="h-11 min-w-0 flex-1 basis-40 rounded-xl border border-dpsg-mittelbeige bg-white px-3 text-base">
                                                <option value="">Größe wählen</option>
                                                <template x-for="v in t.varianten" :key="v.id"><option :value="String(v.id)" x-text="(v.bezeichnung || 'Standard') + ' – ' + euro(v.preis)"></option></template>
                                            </select>
                                        </div>
                                    </template>
                                </div>
                            </fieldset>
                        </template>

                        {{-- Für wen --}}
                        <fieldset>
                            <legend class="text-[17px] font-bold">Für wen?</legend>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <template x-for="m in mitglieder" :key="m.key">
                                    <button type="button" @click="sheet.mitglied = m.key; sheet.neu = false" :aria-pressed="! sheet.neu && sheet.mitglied === m.key"
                                        :class="! sheet.neu && sheet.mitglied === m.key ? 'bg-dpsg-blau text-white' : 'bg-white text-text ring-1 ring-dpsg-mittelbeige'"
                                        class="{{ $kapsel }}" x-text="mitgliedLabel(m)"></button>
                                </template>
                                <button type="button" x-show="mitglieder.length" @click="sheet.neu = true" :aria-pressed="sheet.neu" :class="sheet.neu ? 'bg-dpsg-blau text-white' : 'text-dpsg-blau border border-dashed border-dpsg-mittelbeige'" class="{{ $kapsel }}">+ Mitglied</button>
                            </div>
                            <div x-show="sheet.neu" class="mt-3 grid gap-2 sm:grid-cols-[1fr_12rem]">
                                <label class="text-sm font-semibold">Vor- und Nachname des Mitglieds
                                    <input type="text" x-model="sheet.neuName" maxlength="120" placeholder="leer lassen, wenn für dich selbst" class="{{ $feld }}" />
                                </label>
                                <label class="text-sm font-semibold">Stufe
                                    <select x-model="sheet.neuStufe" class="{{ $feld }}">
                                        <option value="">–</option>
                                        @foreach ($stufen as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                                    </select>
                                </label>
                            </div>
                        </fieldset>

                        {{-- Anzahl --}}
                        <div class="flex items-center justify-between">
                            <span id="sheet-anzahl" class="text-[17px] font-bold">Anzahl</span>
                            <div class="flex items-center rounded-full bg-surface-muted px-1" role="group" aria-labelledby="sheet-anzahl">
                                <button type="button" @click="sheet.menge = Math.max(1, sheet.menge - 1)" class="h-11 w-11 text-2xl" aria-label="weniger">−</button>
                                <span class="min-w-6 text-center text-[17px] font-semibold" x-text="sheet.menge" aria-live="polite"></span>
                                <button type="button" @click="sheet.menge = Math.min(10, sheet.menge + 1)" class="h-11 w-11 text-2xl" aria-label="mehr">+</button>
                            </div>
                        </div>
                    @endif

                    {{-- Details, aufklappbar --}}
                    <div class="divide-y divide-surface-muted rounded-2xl bg-white">
                        <template x-if="aktuell().beschreibung">
                            <details class="px-4"><summary class="flex min-h-12 cursor-pointer items-center font-semibold">Beschreibung</summary><p class="whitespace-pre-line pb-4 text-[15px] leading-relaxed" x-text="aktuell().beschreibung"></p></details>
                        </template>
                        <template x-if="aktuell().nummer">
                            <details class="px-4"><summary class="flex min-h-12 cursor-pointer items-center font-semibold">Artikelnummer</summary>
                                <p class="pb-4 text-[15px]"><span x-text="aktuell().nummer"></span><template x-if="aktuell().url"><span> · <a :href="aktuell().url" target="_blank" rel="noopener" class="text-dpsg-blau underline">beim Rüsthaus ansehen</a></span></template></p>
                            </details>
                        </template>
                    </div>
                </div>
                @if ($bestellbar)
                    <div class="shop-glas border-t border-black/5 px-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] pt-3">
                        <p class="mb-2 text-sm text-dpsg-rot" x-show="sheet.fehler" x-text="sheet.fehler" role="alert"></p>
                        <button type="button" @click="inWarenkorb()" class="{{ $hauptknopf }} w-full">In den Warenkorb · <span x-text="sheetPreisText()"></span></button>
                    </div>
                @endif
            </div>
        </template>
    </dialog>

    @if ($bestellbar)
        {{-- Warenkorb und Bestellen in drei Schritten (SB-131 bis SB-135) --}}
        <dialog x-ref="kasse" class="shop-sheet" aria-labelledby="kasse-titel" @click.self="kasseSchliessen()">
            <form x-ref="formular" method="post" action="{{ route('shop.store') }}" @submit="abschicken($event)" class="flex max-h-[inherit] flex-col">
                @csrf
                <input type="hidden" name="_t" value="{{ \App\Support\SpamGuard::token() }}" />
                <div class="hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off" /></label></div>
                <template x-for="(e, i) in eintraege" :key="e.key">
                    <div>
                        <input type="hidden" :name="'pos[' + i + '][mitglied]'" :value="name(mitgliedVon(e))" />
                        <input type="hidden" :name="'pos[' + i + '][stufe]'" :value="mitgliedVon(e).stufe" />
                        <input type="hidden" :name="'pos[' + i + '][menge]'" :value="e.menge" />
                        <template x-if="e.typ === 'artikel'"><input type="hidden" :name="'pos[' + i + '][variante]'" :value="e.variante" /></template>
                        <template x-if="e.typ === 'frei'"><div><input type="hidden" :name="'pos[' + i + '][frei_nummer]'" :value="e.nummer" /><input type="hidden" :name="'pos[' + i + '][frei_text]'" :value="e.text" /></div></template>
                        <template x-if="e.typ === 'set'">
                            <div>
                                <input type="hidden" :name="'pos[' + i + '][set]'" :value="e.set" />
                                <template x-for="[tid, t] in Object.entries(e.teile)" :key="tid">
                                    <div><input type="hidden" :name="'pos[' + i + '][teile][' + tid + '][variante]'" :value="t.variante" /><input type="hidden" :name="'pos[' + i + '][teile][' + tid + '][dabei]'" :value="t.dabei ? 1 : 0" /></div>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>

                <div class="flex items-center justify-between px-3 pt-3">
                    <button type="button" x-show="kasse.schritt > 0" @click="zurueck()" class="flex h-11 items-center gap-1 px-2 text-[17px] text-dpsg-blau">
                        <svg class="h-5 w-3" viewBox="0 0 12 20" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 2L2 10l8 8"/></svg>Zurück
                    </button>
                    <span x-show="kasse.schritt === 0" class="w-11"></span>
                    <span id="kasse-titel" class="text-[17px] font-semibold" x-text="kasse.schritt === 0 ? 'Warenkorb' : 'Bestellen'"></span>
                    <button type="button" @click="kasseSchliessen()" class="flex h-11 w-11 items-center justify-center rounded-full bg-surface-muted" aria-label="Schließen">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </div>
                <ol x-show="kasse.schritt > 0" class="grid grid-cols-3 gap-2 px-5 pt-2" aria-label="Schritte">
                    @foreach ([1 => 'Kontakt', 2 => 'Abholung', 3 => 'Prüfen'] as $nr => $label)
                        <li class="flex flex-col gap-1.5" :aria-current="kasse.schritt === {{ $nr }} ? 'step' : null">
                            <span class="h-1 rounded-full" :class="kasse.schritt >= {{ $nr }} ? 'bg-dpsg-blau' : 'bg-dpsg-mittelbeige'"></span>
                            <span class="text-sm" :class="kasse.schritt === {{ $nr }} ? 'font-bold' : 'text-text-secondary'">{{ $nr }} {{ $label }}</span>
                        </li>
                    @endforeach
                </ol>

                <div class="flex flex-col gap-5 overflow-y-auto px-5 pb-6 pt-4">
                    @if ($errors->any())
                        <p class="rounded-2xl bg-white p-3 text-sm text-dpsg-rot ring-1 ring-dpsg-rot/30" role="alert">Bitte prüfe die markierten Angaben.</p>
                    @endif

                    {{-- Schritt 0: Warenkorb nach Mitglied --}}
                    <div x-show="kasse.schritt === 0" data-schritt="0" class="flex flex-col gap-5">
                        @error('pos')<p class="text-sm text-dpsg-rot">{{ $message }}</p>@enderror
                        @foreach ($errors->getMessages() as $k => $m)@if (str_starts_with($k, 'pos.'))<p class="text-sm text-dpsg-rot">{{ $m[0] }}</p>@endif @endforeach
                        <template x-for="m in gruppen()" :key="m.key">
                            <section class="flex flex-col gap-2">
                                <h2 class="px-1 text-xl font-bold" tabindex="-1" x-text="mitgliedLabel(m)"></h2>
                                <ul class="divide-y divide-surface-muted rounded-2xl bg-white">
                                    <template x-for="e in eintraegeFuer(m)" :key="e.key">
                                        <li class="flex items-center gap-3 p-3">
                                            <span class="min-w-0 flex-1">
                                                <span class="block text-[15px] font-semibold" x-text="e.label"></span>
                                                <span class="block text-sm text-text-secondary" x-show="e.detail" x-text="e.detail"></span>
                                                <span class="block text-sm text-[#8a4b00]" x-show="e.preis === null">Wird geprüft, Preis vorläufig</span>
                                                <label x-show="mitglieder.length > 1" class="mt-1 inline-flex items-center gap-2 text-sm text-text-secondary">für
                                                    <select @change="umhaengen(e, $event.target.value)" class="h-9 rounded-lg border border-dpsg-mittelbeige bg-white px-2 text-sm">
                                                        <template x-for="z in mitglieder" :key="z.key"><option :value="z.key" :selected="z.key === e.mitglied" x-text="anzeigeName(z)"></option></template>
                                                    </select>
                                                </label>
                                            </span>
                                            <span class="flex shrink-0 flex-col items-end gap-1">
                                                <span class="text-[15px] font-semibold" x-text="e.preis === null ? '–' : euro(e.preis * e.menge)"></span>
                                                <span class="flex items-center rounded-full bg-surface-muted">
                                                    <button type="button" @click="weniger(e)" class="h-9 w-11 text-lg" :aria-label="e.label + ': eins weniger'">−</button>
                                                    <span class="min-w-4 text-center text-[15px] font-semibold" x-text="e.menge"></span>
                                                    <button type="button" @click="mehr(e)" class="h-9 w-11 text-lg" :aria-label="e.label + ': eins mehr'">+</button>
                                                </span>
                                            </span>
                                        </li>
                                    </template>
                                </ul>
                            </section>
                        </template>
                        <p class="text-sm text-text-secondary">Der Warenkorb bleibt auf diesem Gerät gespeichert, bis du bestellst (höchstens 7 Tage).</p>
                    </div>

                    {{-- Schritt 1: Kontakt --}}
                    <div x-show="kasse.schritt === 1" data-schritt="1" class="flex flex-col gap-4">
                        <h2 class="text-xl font-bold" tabindex="-1">Deine Angaben</h2>
                        <label class="text-sm font-semibold">Dein Name
                            <input type="text" name="name" x-model="besteller" required maxlength="120" autocomplete="name" class="{{ $feld }} @error('name') border-dpsg-rot @enderror" />
                            @error('name')<span class="mt-1 block text-sm font-normal text-dpsg-rot">{{ $message }}</span>@enderror
                        </label>
                        <label class="text-sm font-semibold">Deine E-Mail-Adresse
                            <input type="email" name="email" required maxlength="254" value="{{ old('email') }}" autocomplete="email" class="{{ $feld }} @error('email') border-dpsg-rot @enderror" />
                            @error('email')<span class="mt-1 block text-sm font-normal text-dpsg-rot">{{ $message }}</span>@enderror
                        </label>
                        <label class="text-sm font-semibold">Telefon <span class="font-normal text-text-secondary">(optional)</span>
                            <input type="tel" name="telefon" maxlength="40" value="{{ old('telefon') }}" autocomplete="tel" class="{{ $feld }}" />
                        </label>
                        <fieldset class="flex flex-col gap-1">
                            <legend class="text-sm font-semibold">Du bestellst als</legend>
                            @foreach (\App\Models\Shop\Bestellung::ROLLEN as $wert => $label)
                                <label class="flex min-h-11 items-center gap-3 text-[15px]"><input type="radio" name="rolle" value="{{ $wert }}" class="h-5 w-5 accent-dpsg-blau" @checked(old('rolle', 'erziehungsberechtigt') === $wert) /> {{ $label }}</label>
                            @endforeach
                        </fieldset>
                    </div>

                    {{-- Schritt 2: Abholung --}}
                    <div x-show="kasse.schritt === 2" data-schritt="2" class="flex flex-col gap-4">
                        <h2 class="text-xl font-bold" tabindex="-1">Abholung</h2>
                        <fieldset class="flex flex-col gap-1">
                            <legend class="sr-only">Abholung</legend>
                            @foreach (['Gruppenstunde' => 'in der Gruppenstunde der Stufe', 'nach Absprache' => 'nach Absprache'] as $wert => $label)
                                <label class="flex min-h-11 items-center gap-3 text-[15px]"><input type="radio" name="abholung" value="{{ $wert }}" class="h-5 w-5 accent-dpsg-blau" @checked(old('abholung', 'Gruppenstunde') === $wert) /> {{ $label }}</label>
                            @endforeach
                        </fieldset>
                        <label class="text-sm font-semibold">Bemerkung <span class="font-normal text-text-secondary">(optional)</span>
                            <textarea name="bemerkung" maxlength="2000" rows="3" class="mt-1 w-full rounded-xl border border-dpsg-mittelbeige bg-white px-3 py-2 text-base">{{ old('bemerkung') }}</textarea>
                        </label>
                    </div>

                    {{-- Schritt 3: Prüfen --}}
                    <div x-show="kasse.schritt === 3" data-schritt="3" class="flex flex-col gap-4">
                        <h2 class="text-xl font-bold" tabindex="-1">Prüfen und bestellen</h2>
                        <div class="rounded-2xl bg-white px-4 py-2">
                            <template x-for="m in gruppen()" :key="m.key">
                                <div class="flex flex-col gap-1 border-b border-surface-muted py-2 last:border-0">
                                    <span class="text-[15px] font-bold" x-text="mitgliedLabel(m)"></span>
                                    <template x-for="e in eintraegeFuer(m)" :key="e.key">
                                        <span class="flex justify-between gap-3 text-[15px]"><span x-text="e.menge + ' × ' + e.label + (e.preis === null ? ' (vorläufig)' : '')"></span><span class="shrink-0" x-text="e.preis === null ? '–' : euro(e.preis * e.menge)"></span></span>
                                    </template>
                                </div>
                            </template>
                            <div class="flex items-baseline justify-between py-3"><span class="text-[15px]">Gesamt inkl. MwSt.</span><span class="text-xl font-bold" x-text="euro(summe()) + (hatVorlaeufige() ? ' + vorläufige' : '')"></span></div>
                        </div>
                        <div class="flex flex-col gap-3 rounded-2xl bg-white p-4 text-[15px] leading-relaxed">
                            @if ($runde)
                                <p><strong>Zahlung bis {{ $runde->bestelldatum->format('d.m.') }}</strong> per Überweisung. Bankverbindung und GiroCode kommen nach der Bestätigung per Mail.</p>
                            @endif
                            <p>Stornieren kannst du über den Link in der Mail bis zum Bestellschluss, also bis wir beim Rüsthaus bestellen. Schon Bezahltes erstatten wir. Danach ist die Bestellung verbindlich.</p>
                            <p class="text-sm text-text-secondary">Die Sammelbestellung ist ein Service des Stamms für seine Mitglieder: Wir sammeln die Bestellungen und geben sie zum Originalpreis an das Rüsthaus weiter.</p>
                        </div>
                        <label class="flex items-start gap-3 text-[15px] leading-snug">
                            <input type="checkbox" name="einwilligung" value="1" class="mt-0.5 h-5 w-5 shrink-0 accent-dpsg-blau" @checked(old('einwilligung')) />
                            <span>Ich bin Mitglied des Stamms bzw. erziehungsberechtigt für die Mitglieder, für die ich bestelle. Ich willige ein, dass der {{ config('dpsg.site.name') }} meine Angaben für die Sammelbestellung, die Bezahlung und die Ausgabe verarbeitet (<a href="{{ url('/datenschutz') }}" class="text-dpsg-blau underline">Datenschutz</a>).</span>
                        </label>
                        @error('einwilligung')<p class="text-sm text-dpsg-rot">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="shop-glas flex flex-col gap-3 border-t border-black/5 px-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] pt-3">
                    <div x-show="kasse.schritt === 0" class="flex items-baseline justify-between">
                        <span class="text-[15px] text-text-secondary">Summe inkl. MwSt.</span>
                        <span class="text-xl font-bold" x-text="euro(summe()) + (hatVorlaeufige() ? ' +' : '')"></span>
                    </div>
                    <button type="button" x-show="kasse.schritt < 3" @click="kasse.schritt === 0 ? (kasse.schritt = 1) : weiter()" :disabled="! eintraege.length" class="{{ $hauptknopf }} w-full" x-text="kasse.schritt === 0 ? 'Weiter zur Bestellung' : 'Weiter'"></button>
                    <button type="submit" x-show="kasse.schritt === 3" :disabled="! eintraege.length" class="{{ $hauptknopf }} w-full">Verbindlich bestellen</button>
                </div>
            </form>
        </dialog>
    @endif
</div>
@endsection
