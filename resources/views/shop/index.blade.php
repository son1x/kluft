@extends('layouts.app')

{{-- Sammelbestellung beim Rüsthaus: Shop der freigegebenen Artikel und
     Bestellformular. Erwartet: $page (optional, Einleitung), $testbetrieb, $runde, $bestellbar, $fehlt, $sets, $artikel --}}

@section('seo_title', ($page->title ?? 'Ausrüstung bestellen').' – '.config('dpsg.site.name'))

@php($katalog = $artikel->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'varianten' => $a->varianten->map(fn ($v) => ['id' => $v->id, 'bezeichnung' => $v->bezeichnung, 'preis' => $v->preis_cent])->values()])->values())
@php($setKatalog = $sets->map(fn ($s) => ['id' => 's'.$s->id, 'name' => $s->name, 'teile' => $s->teile->filter(fn ($t) => $t->artikel?->freigegeben && $t->artikel->varianten->isNotEmpty())->map(fn ($t) => [
    'id' => $t->id, 'name' => $t->artikel->name, 'menge' => $t->menge, 'optional' => $t->optional, 'vorgewaehlt' => $t->vorgewaehlt,
    'varianten' => $t->artikel->varianten->map(fn ($v) => ['id' => $v->id, 'bezeichnung' => $v->bezeichnung, 'preis' => $v->preis_cent])->values(),
])->values()])->values())
@php($stufen = collect(config('dpsg.stages'))->map(fn ($s) => $s['label']))

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-12">
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
            <p class="mt-6 inline-block border-l-4 border-dpsg-rot bg-white px-4 py-3 shadow-sm">
                <strong>{{ $runde->name }}:</strong> Bestellen und bezahlen bis zum <strong>{{ $runde->bestelldatum->format('d.m.Y') }}</strong>. Dann bestellen wir beim Rüsthaus.
            </p>
        @else
            <p class="mt-6 border-l-4 border-dpsg-mittelbeige bg-white px-4 py-3 shadow-sm">Gerade läuft keine Sammelbestellung. Die nächste kündigen wir hier und in den Gruppenstunden an.</p>
        @endif

        @if ($artikel->isNotEmpty() || $sets->isNotEmpty())
            <section class="mt-10" aria-labelledby="shop-heading">
                <h2 id="shop-heading" class="font-serif text-2xl font-bold">Unser Angebot</h2>
                <div class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($sets as $s)
                        <article class="flex flex-col border-t-4 border-dpsg-rot bg-white shadow-md">
                            @if ($s->bildArtikel?->bild)
                                <x-picture :media="$s->bildArtikel->bild" sizes="(min-width: 1024px) 360px, 100vw" class="aspect-square w-full bg-white object-contain p-4" loading="lazy" />
                            @endif
                            <div class="flex flex-1 flex-col p-4">
                                <p class="text-xs font-bold uppercase tracking-wide text-dpsg-rot">Set</p>
                                <h3 class="font-serif text-lg font-bold">{{ $s->name }}</h3>
                                <p class="text-sm text-text-secondary">ab {{ \App\Support\Sammelbestellung::euro(\App\Support\Sammelbestellung::setPreisAb($s)) }}</p>
                                @if ($s->beschreibung)
                                    <p class="mt-2 whitespace-pre-line text-sm">{{ $s->beschreibung }}</p>
                                @endif
                                <ul class="mt-2 list-disc pl-5 text-sm">
                                    @foreach ($s->teile as $t)
                                        @if ($t->artikel?->freigegeben)
                                            <li>{{ $t->menge > 1 ? $t->menge.' × ' : '' }}{{ $t->artikel->name }}@if ($t->optional) <span class="text-text-secondary">(optional)</span>@endif</li>
                                        @endif
                                    @endforeach
                                </ul>
                                @if ($bestellbar)
                                    <a href="#bestellformular" x-data @click="$dispatch('shop-waehlen', { wahl: @js('s'.$s->id) })" class="mt-auto self-start pt-4 font-bold text-dpsg-rot underline">Bestellen</a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                    @foreach ($artikel as $a)
                        <article class="flex flex-col bg-white shadow-md">
                            @if ($a->bild)
                                <x-picture :media="$a->bild" sizes="(min-width: 1024px) 360px, 100vw" class="aspect-square w-full bg-white object-contain p-4" loading="lazy" />
                            @endif
                            <div class="flex flex-1 flex-col p-4">
                                <h3 class="font-serif text-lg font-bold">{{ $a->name }}</h3>
                                <p class="text-sm text-text-secondary">Rüsthaus-Nr. {{ $a->nummer }} · {{ $a->varianten->min('preis_cent') !== $a->varianten->max('preis_cent') ? 'ab ' : '' }}{{ \App\Support\Sammelbestellung::euro($a->varianten->min('preis_cent') ?? $a->preis_cent) }}</p>
                                @if ($a->beschreibung)
                                    <details class="mt-2 text-sm">
                                        <summary class="cursor-pointer font-bold text-dpsg-rot">Beschreibung</summary>
                                        <p class="mt-1 whitespace-pre-line">{{ $a->beschreibung }}</p>
                                    </details>
                                @endif
                                @if ($a->varianten->count() > 1)
                                    <p class="mt-2 text-xs text-text-secondary">Größen: {{ $a->varianten->pluck('bezeichnung')->filter()->implode(' · ') }}</p>
                                @endif
                                @if ($bestellbar)
                                    <a href="#bestellformular" x-data @click="$dispatch('shop-waehlen', { wahl: @js((string) $a->id) })" class="mt-auto self-start pt-4 font-bold text-dpsg-rot underline">Bestellen</a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @if (! $bestellbar && $fehlt !== [])
            <div class="mt-10 border-l-4 border-dpsg-rot bg-white px-4 py-3 text-sm shadow-sm" role="status">
                <p class="font-bold">Nur für dich sichtbar (Backend): Das Bestellformular erscheint, sobald Folgendes erledigt ist:</p>
                <ul class="mt-1 list-disc pl-5">@foreach ($fehlt as $punkt)<li>{{ $punkt }}</li>@endforeach</ul>
            </div>
        @endif

        @if ($bestellbar)
            <section id="bestellformular" class="mt-12 scroll-mt-24 bg-white p-6 shadow-md md:p-8" aria-labelledby="bestellen-heading"
                x-data="shopBestellung(@js($katalog), @js($setKatalog), @js(old('pos', [[]])), @js(route('shop.nachschlagen')))" @shop-waehlen.window="waehlen($event.detail.wahl)">
                <h2 id="bestellen-heading" class="font-serif text-2xl font-bold">Bestellen</h2>
                <p class="mt-2 text-sm text-text-secondary">Bestellen dürfen Mitglieder des Stamms bzw. ihre Erziehungsberechtigten. Etwas, das oben fehlt? Trag die Rüsthaus-Artikelnummer ein – wir prüfen es und melden uns mit dem Preis.</p>

                @if ($errors->any())
                    <div class="mt-4 border-l-4 border-dpsg-rot bg-surface-muted p-3 text-sm text-dpsg-rot" role="alert">
                        <ul class="list-disc pl-5">@foreach ($errors->all() as $fehler)<li>{{ $fehler }}</li>@endforeach</ul>
                    </div>
                @endif

                <form method="post" action="{{ route('shop.store') }}" class="mt-6 space-y-6" novalidate>
                    @csrf
                    <input type="hidden" name="_t" value="{{ \App\Support\SpamGuard::token() }}" />
                    <div class="hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off" /></label></div>

                    <fieldset class="space-y-4">
                        <legend class="font-bold">Artikel</legend>
                        <template x-for="(pos, i) in positionen" :key="pos.key">
                            <div class="grid gap-3 border border-dpsg-mittelbeige p-4 md:grid-cols-6">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-bold" :for="'pos-mitglied-' + i">Für (Vor- und Nachname)</label>
                                    <input :id="'pos-mitglied-' + i" type="text" :name="'pos[' + i + '][mitglied]'" x-model="pos.mitglied" maxlength="120" required class="mt-1 w-full border border-dpsg-mittelbeige px-3 py-2" />
                                </div>
                                <div>
                                    <label class="block text-sm font-bold" :for="'pos-stufe-' + i">Stufe</label>
                                    <select :id="'pos-stufe-' + i" :name="'pos[' + i + '][stufe]'" x-model="pos.stufe" class="mt-1 w-full border border-dpsg-mittelbeige bg-white px-3 py-2">
                                        <option value="">–</option>
                                        @foreach ($stufen as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-bold" :for="'pos-artikel-' + i">Artikel</label>
                                    <select :id="'pos-artikel-' + i" x-model="pos.artikel" @change="gewaehlt(pos)" class="mt-1 w-full border border-dpsg-mittelbeige bg-white px-3 py-2">
                                        <option value="">bitte wählen</option>
                                        <template x-for="s in sets" :key="s.id"><option :value="s.id" x-text="'Set: ' + s.name"></option></template>
                                        <template x-for="a in katalog" :key="a.id"><option :value="String(a.id)" x-text="a.name"></option></template>
                                        <option value="frei">Anderer Rüsthaus-Artikel (Artikelnummer)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold" :for="'pos-menge-' + i">Anzahl</label>
                                    <input :id="'pos-menge-' + i" type="number" min="1" max="10" :name="'pos[' + i + '][menge]'" x-model="pos.menge" class="mt-1 w-full border border-dpsg-mittelbeige px-3 py-2" />
                                </div>

                                <div class="space-y-3 md:col-span-6" x-show="set(pos)">
                                    <input type="hidden" :name="set(pos) ? 'pos[' + i + '][set]' : ''" :value="set(pos) ? set(pos).id.slice(1) : ''" />
                                    <template x-for="t in setTeile(pos)" :key="t.id">
                                        <div class="grid items-end gap-2 border-l-4 border-dpsg-mittelbeige pl-3 sm:grid-cols-2">
                                            <div>
                                                <label class="inline-flex items-center gap-2 text-sm font-bold" x-show="t.optional">
                                                    <input type="checkbox" value="1" :name="'pos[' + i + '][teile][' + t.id + '][dabei]'" x-model="pos.teile[t.id].dabei" />
                                                    <span x-text="(t.menge > 1 ? t.menge + ' × ' : '') + t.name + ' (optional)'"></span>
                                                </label>
                                                <p class="text-sm font-bold" x-show="!t.optional" x-text="(t.menge > 1 ? t.menge + ' × ' : '') + t.name"></p>
                                                <p class="text-xs text-text-secondary" x-show="t.varianten.length === 1" x-text="euro(t.varianten[0].preis)"></p>
                                            </div>
                                            <div x-show="t.varianten.length > 1 && (!t.optional || pos.teile[t.id].dabei)">
                                                <label class="sr-only" :for="'pos-teil-' + i + '-' + t.id" x-text="'Größe ' + t.name"></label>
                                                <select :id="'pos-teil-' + i + '-' + t.id" :name="t.varianten.length > 1 ? 'pos[' + i + '][teile][' + t.id + '][variante]' : ''" x-model="pos.teile[t.id].variante" class="w-full border border-dpsg-mittelbeige bg-white px-3 py-2">
                                                    <option value="">Größe wählen</option>
                                                    <template x-for="v in t.varianten" :key="v.id"><option :value="v.id" x-text="(v.bezeichnung || 'Standard') + ' – ' + euro(v.preis)"></option></template>
                                                </select>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <div class="md:col-span-4" x-show="pos.artikel && pos.artikel !== 'frei' && !set(pos)">
                                    <label class="block text-sm font-bold" :for="'pos-variante-' + i">Größe / Ausführung</label>
                                    <select :id="'pos-variante-' + i" :name="pos.artikel && pos.artikel !== 'frei' && !set(pos) ? 'pos[' + i + '][variante]' : ''" x-model="pos.variante" class="mt-1 w-full border border-dpsg-mittelbeige bg-white px-3 py-2">
                                        <option value="">bitte wählen</option>
                                        <template x-for="v in varianten(pos)" :key="v.id"><option :value="v.id" x-text="(v.bezeichnung || 'Standard') + ' – ' + euro(v.preis)"></option></template>
                                    </select>
                                </div>

                                <div class="md:col-span-4" x-show="pos.artikel === 'frei'">
                                    <label class="block text-sm font-bold" :for="'pos-nummer-' + i">Rüsthaus-Artikelnummer oder Link</label>
                                    <div class="mt-1 flex gap-2">
                                        <input :id="'pos-nummer-' + i" type="text" :name="pos.artikel === 'frei' ? 'pos[' + i + '][frei_nummer]' : ''" x-model="pos.nummer" maxlength="300" placeholder="z. B. 0100003.32" class="w-full border border-dpsg-mittelbeige px-3 py-2" />
                                        <button type="button" @click="nachschlagen(pos)" class="shrink-0 border border-dpsg-blau px-3 py-2 font-bold text-dpsg-blau hover:bg-dpsg-blau hover:text-white">Prüfen</button>
                                    </div>
                                    <p class="mt-1 text-sm" x-show="pos.info" x-text="pos.info" :class="pos.gesperrt ? 'text-dpsg-rot' : 'text-text-secondary'"></p>
                                    <label class="mt-2 block text-sm font-bold" :for="'pos-text-' + i">Größe bzw. was genau <span class="font-normal text-text-secondary">(falls nötig)</span></label>
                                    <input :id="'pos-text-' + i" type="text" :name="pos.artikel === 'frei' ? 'pos[' + i + '][frei_text]' : ''" x-model="pos.text" maxlength="160" class="mt-1 w-full border border-dpsg-mittelbeige px-3 py-2" />
                                </div>
                                <div class="flex items-end md:col-span-2" x-show="positionen.length > 1">
                                    <button type="button" @click="positionen.splice(i, 1)" class="text-sm text-dpsg-rot underline">Entfernen</button>
                                </div>
                            </div>
                        </template>
                        <button type="button" @click="neu()" class="border border-dpsg-blau px-4 py-2 font-bold text-dpsg-blau hover:bg-dpsg-blau hover:text-white">+ Weiterer Artikel</button>
                        <p class="text-sm">Summe (ohne frei eingegebene Artikel): <strong x-text="euro(summe())"></strong></p>
                    </fieldset>

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

                    <button type="submit" class="inline-flex items-center gap-2 bg-dpsg-blau px-5 py-2 font-bold text-white hover:bg-dpsg-rot">Bestellung abschicken</button>
                    <p class="text-xs text-text-secondary">Du bekommst eine Mail mit einem Link zum Bestätigen. Danach folgt die Zahlungsaufforderung.</p>
                </form>
            </section>

        @endif
    </div>
@endsection
