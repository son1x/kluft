@extends('layouts.app')

{{-- Sammelbestellung beim Rüsthaus: Shop der freigegebenen Artikel und
     Bestellformular. Erwartet: $page (optional, Einleitung), $testbetrieb, $runde, $bestellbar, $artikel --}}

@section('seo_title', ($page->title ?? 'Ausrüstung bestellen').' – '.config('dpsg.site.name'))

@php($katalog = $artikel->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'varianten' => $a->varianten->map(fn ($v) => ['id' => $v->id, 'bezeichnung' => $v->bezeichnung, 'preis' => $v->preis_cent])->values()])->values())
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

        @if ($artikel->isNotEmpty())
            <section class="mt-10" aria-labelledby="shop-heading">
                <h2 id="shop-heading" class="font-serif text-2xl font-bold">Unser Angebot</h2>
                <div class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
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
                                @if ($a->url)
                                    <a href="{{ $a->url }}" class="mt-auto pt-3 text-sm text-dpsg-rot underline" rel="noopener" target="_blank">Beim Rüsthaus ansehen</a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($bestellbar)
            <section id="bestellformular" class="mt-12 scroll-mt-24 bg-white p-6 shadow-md md:p-8" aria-labelledby="bestellen-heading"
                x-data="shopBestellung(@js($katalog), @js(old('pos', [[]])))">
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
                                    <select :id="'pos-artikel-' + i" x-model="pos.artikel" @change="pos.variante = ''" class="mt-1 w-full border border-dpsg-mittelbeige bg-white px-3 py-2">
                                        <option value="">bitte wählen</option>
                                        <template x-for="a in katalog" :key="a.id"><option :value="a.id" x-text="a.name"></option></template>
                                        <option value="frei">Anderer Rüsthaus-Artikel (Artikelnummer)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold" :for="'pos-menge-' + i">Anzahl</label>
                                    <input :id="'pos-menge-' + i" type="number" min="1" max="10" :name="'pos[' + i + '][menge]'" x-model="pos.menge" class="mt-1 w-full border border-dpsg-mittelbeige px-3 py-2" />
                                </div>

                                <div class="md:col-span-4" x-show="pos.artikel && pos.artikel !== 'frei'">
                                    <label class="block text-sm font-bold" :for="'pos-variante-' + i">Größe / Ausführung</label>
                                    <select :id="'pos-variante-' + i" :name="pos.artikel !== 'frei' ? 'pos[' + i + '][variante]' : ''" x-model="pos.variante" class="mt-1 w-full border border-dpsg-mittelbeige bg-white px-3 py-2">
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

            <script>
                function shopBestellung(katalog, alt) {
                    let zaehler = 0;
                    const leer = (p = {}) => ({ key: ++zaehler, mitglied: p.mitglied || '', stufe: p.stufe || '', menge: p.menge || 1, artikel: p.frei_nummer ? 'frei' : '', variante: p.variante || '', nummer: p.frei_nummer || '', text: p.frei_text || '', info: '', gesperrt: false });
                    return {
                        katalog,
                        positionen: (alt && alt.length ? alt : [{}]).map(p => {
                            const pos = leer(p);
                            if (p.variante) {
                                const a = katalog.find(a => a.varianten.some(v => String(v.id) === String(p.variante)));
                                if (a) { pos.artikel = String(a.id); }
                            }
                            return pos;
                        }),
                        neu() { this.positionen.push(leer()); },
                        varianten(pos) { const a = this.katalog.find(a => String(a.id) === String(pos.artikel)); return a ? a.varianten : []; },
                        euro(cent) { return (cent / 100).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' }); },
                        summe() {
                            return this.positionen.reduce((s, p) => {
                                const v = this.varianten(p).find(v => String(v.id) === String(p.variante));
                                return s + (v ? v.preis * (parseInt(p.menge) || 1) : 0);
                            }, 0);
                        },
                        async nachschlagen(pos) {
                            pos.info = 'Wird beim Rüsthaus nachgeschlagen …'; pos.gesperrt = false;
                            try {
                                const r = await fetch(@js(route('shop.nachschlagen')) + '?nummer=' + encodeURIComponent(pos.nummer), { headers: { Accept: 'application/json' } });
                                const d = await r.json();
                                if (d.gesperrt) { pos.gesperrt = true; pos.info = 'Dieser Artikel kann über die Sammelbestellung nicht bestellt werden. Sprich bitte deine Leitung an.'; }
                                else if (d.name) { pos.info = d.name + ' – ' + this.euro(d.preis_cent) + ' (vorläufig, wir prüfen den Preis)' + (d.varianten && d.varianten.length > 1 ? '. Größen: ' + d.varianten.map(v => v.bezeichnung).join(', ') : ''); }
                                else { pos.info = 'Nicht gefunden. Trag unten ein, was du genau brauchst – wir prüfen es.'; }
                            } catch (e) { pos.info = 'Nachschlagen klappt gerade nicht. Trag unten ein, was du genau brauchst.'; }
                        },
                    };
                }
            </script>
        @endif
    </div>
@endsection
