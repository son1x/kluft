<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="robots" content="noindex, nofollow" />
    <meta name="theme-color" content="#003056" />
    <title>Vor Ort – {{ config('dpsg.site.name') }}</title>
    <link rel="icon" href="{{ asset(config('dpsg.site.favicon', 'favicon.ico')) }}" />
    @vite(['resources/css/app.css', 'resources/js/vor-ort.js'])
</head>
{{-- Vor Ort (docs/vor-ort.md): Tablet zweispaltig (Liste | Person), Handy einspaltig. --}}
<body class="min-h-screen bg-surface font-sans text-text" x-data="vorOrt" x-cloak>
<div class="flex min-h-screen flex-col md:h-dvh md:min-h-0">
    <header class="sticky top-0 z-30 bg-dpsg-blau text-white shadow">
        <div class="flex min-h-14 items-center gap-2 px-3 sm:px-4">
            <button type="button" x-show="v" x-on:click="zurueck()" class="-ml-1 flex min-h-11 min-w-11 items-center justify-center rounded-lg hover:bg-white/10" aria-label="Zurück zur Auswahl">
                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
            <div class="min-w-0 flex-1">
                <p class="text-xs uppercase tracking-wide text-white/70">Vor Ort</p>
                <h1 class="truncate text-lg font-bold leading-tight" x-text="v ? v.titel : 'Veranstaltung wählen'"></h1>
            </div>
            <span class="hidden text-sm text-white/80 sm:inline" x-text="person"></span>
            <span class="rounded-full px-2.5 py-1 text-xs font-semibold"
                  x-bind:class="!online ? 'bg-dpsg-yellow text-dpsg-blau' : (warteschlange.length ? 'bg-white/20' : 'bg-white/10')"
                  x-text="!online ? 'Offline · ' + warteschlange.length + ' warten' : (warteschlange.length ? warteschlange.length + ' werden gesendet …' : 'Verbunden')"
                  role="status"></span>
        </div>
    </header>

    <div x-show="meldung" x-transition class="flex items-start gap-3 bg-dpsg-hellbeige px-4 py-3 text-sm" role="status">
        <p class="flex-1" x-text="meldung"></p>
        <button type="button" class="min-h-11 rounded-lg px-3 font-semibold text-dpsg-blau" x-on:click="meldung = ''">OK</button>
    </div>

    <main class="flex min-h-0 flex-1 flex-col md:overflow-hidden">
        <p x-show="laden" class="p-6 text-center text-gray-600">Lädt …</p>
        <p x-show="fehler" class="m-4 rounded-lg bg-red-50 p-4 text-red-900" x-text="fehler" role="alert"></p>

        {{-- Auswahl der Veranstaltung --}}
        <section x-show="!v && !laden" class="mx-auto w-full max-w-3xl p-4">
            <p class="mb-4 text-gray-700">Veranstaltungen, deren Teilnehmende du verwalten darfst – laufende, kommende (60 Tage) und gerade beendete (7 Tage).</p>
            <ul class="grid gap-3 sm:grid-cols-2">
                <template x-for="e in veranstaltungen" x-bind:key="e.id">
                    <li>
                        <button type="button" x-on:click="oeffnen(e.id)" class="flex min-h-24 w-full flex-col items-start gap-1 rounded-xl border border-dpsg-mittelbeige bg-white p-4 text-left shadow-sm hover:border-dpsg-blau focus-visible:outline-2 focus-visible:outline-dpsg-blau">
                            <span x-show="e.laeuft" class="rounded-full bg-dpsg-rot px-2 py-0.5 text-xs font-semibold text-white">läuft gerade</span>
                            <span class="text-lg font-bold text-dpsg-blau" x-text="e.titel"></span>
                            <span class="text-sm text-gray-700" x-text="e.datum + ' · ' + e.teilnehmende + ' bestätigt'"></span>
                        </button>
                    </li>
                </template>
            </ul>
            <div x-show="veranstaltungen.length === 0 && !fehler" class="rounded-xl bg-white p-6 text-center shadow-sm">
                <p class="font-semibold text-dpsg-blau">Gerade nichts zu betreuen</p>
                <p class="mt-1 text-sm text-gray-700">Hier erscheinen Veranstaltungen, deren Teilnehmende du verwalten darfst, ab 60 Tage vor Beginn.</p>
            </div>
        </section>

        {{-- Veranstaltung --}}
        <template x-if="v">
            <section class="flex min-h-0 flex-1 flex-col">
                <div class="border-b border-dpsg-mittelbeige bg-white px-3 py-3 sm:px-4" x-bind:class="auswahl ? 'max-md:hidden' : ''">
                    <div class="flex gap-2 overflow-x-auto pb-1" role="tablist" aria-label="Tag">
                        <template x-for="t in v.tage" x-bind:key="t.tag">
                            <button type="button" role="tab" x-bind:aria-selected="tag === t.tag" x-on:click="tag = t.tag"
                                    class="min-h-11 shrink-0 rounded-full border px-4 text-sm font-semibold"
                                    x-bind:class="tag === t.tag ? 'border-dpsg-blau bg-dpsg-blau text-white' : 'border-dpsg-mittelbeige bg-white text-dpsg-blau'"
                                    x-text="t.label + (t.tag === v.heute ? ' · heute' : '')"></button>
                        </template>
                    </div>
                    <div class="mt-2 flex flex-wrap items-baseline gap-x-5 gap-y-1 text-sm">
                        <span><strong class="text-lg text-dpsg-blau" x-text="zahlen.da"></strong> von <span x-text="zahlen.erwartet"></span> da</span>
                        <span x-show="v.beitrag"><strong class="text-lg text-dpsg-rot" x-text="zahlen.offen"></strong> noch nicht bezahlt</span>
                        <template x-for="k in v.verpflegung" x-bind:key="k.label">
                            <span class="text-gray-700" x-text="k.label + ': ' + k.anzahl"></span>
                        </template>
                    </div>
                </div>

                <div class="flex min-h-0 flex-1">
                    {{-- Liste --}}
                    <div class="flex min-h-0 w-full flex-col md:w-[22rem] md:border-r md:border-dpsg-mittelbeige lg:w-[26rem]"
                         x-bind:class="auswahl ? 'max-md:hidden' : ''">
                        <div class="space-y-2 bg-surface p-3">
                            <label class="sr-only" for="vo-suche">Name suchen</label>
                            <input id="vo-suche" type="search" x-model="suche" placeholder="Name suchen …" autocomplete="off"
                                   class="min-h-11 w-full rounded-lg border border-dpsg-mittelbeige bg-white px-3 text-base">
                            <div class="flex gap-2" role="group" aria-label="Filter">
                                <template x-for="[k, l] in [['alle', 'Alle'], ['fehlt', 'Noch nicht da'], ['offen', 'Nicht bezahlt']]" x-bind:key="k">
                                    <button type="button" x-show="k !== 'offen' || v.beitrag" x-on:click="filter = k" x-bind:aria-pressed="filter === k"
                                            class="min-h-11 flex-1 rounded-lg border px-2 text-sm font-semibold"
                                            x-bind:class="filter === k ? 'border-dpsg-blau bg-dpsg-blau text-white' : 'border-dpsg-mittelbeige bg-white text-dpsg-blau'"
                                            x-text="l"></button>
                                </template>
                            </div>
                            <label x-show="v.zusatz_felder.length" class="flex items-center gap-2 text-sm">
                                <span class="shrink-0 font-semibold">Ordnen:</span>
                                <select x-model="gruppe" aria-label="Liste ordnen" class="min-h-11 w-full rounded-lg border border-dpsg-mittelbeige bg-white px-3">
                                    <option value="">nach Name</option>
                                    <template x-for="f in v.zusatz_felder" x-bind:key="f.schluessel">
                                        <option x-bind:value="f.schluessel" x-text="'nach ' + f.label"></option>
                                    </template>
                                </select>
                            </label>
                        </div>
                        <ul class="min-h-0 flex-1 divide-y divide-dpsg-mittelbeige/60 overflow-y-auto bg-white">
                          <template x-for="g in gruppen" x-bind:key="'g' + g.wert">
                           <li class="contents">
                            <ul class="divide-y divide-dpsg-mittelbeige/60">
                            <li x-show="gruppe" class="sticky top-0 z-10 bg-dpsg-blau px-4 py-2 text-sm font-bold text-white" x-text="gruppenTitel(g)"></li>
                            <template x-for="p in g.personen" x-bind:key="p.id">
                                <li class="flex items-stretch" x-bind:class="auswahlId === p.id ? 'bg-dpsg-hellbeige' : ''">
                                    <button type="button" x-on:click="waehlen(p)" class="flex min-h-16 min-w-0 flex-1 flex-col justify-center px-4 py-2 text-left">
                                        <span class="truncate font-semibold" x-text="p.nachname + ', ' + p.vorname"></span>
                                        <span class="flex flex-wrap gap-1.5 text-xs text-gray-700">
                                            <span x-show="p.jahrgang" x-text="'Jg. ' + p.jahrgang"></span>
                                            <template x-for="z in zusatzText(p)" x-bind:key="z"><span class="rounded border border-dpsg-mittelbeige bg-white px-1.5 text-dpsg-blau" x-text="z"></span></template>
                                            <span x-show="!erwartet(p)" class="rounded bg-gray-100 px-1.5">heute nicht angemeldet</span>
                                            <span x-show="p.zahlung" class="rounded px-1.5" x-bind:class="zahlungFarbe(p)" x-text="zahlungText(p)"></span>
                                        </span>
                                    </button>
                                    <button type="button" x-on:click="anwesend(p)"
                                            class="m-2 flex w-16 shrink-0 items-center justify-center rounded-xl border-2 text-xs font-bold"
                                            x-bind:class="istDa(p) ? 'border-green-700 bg-green-700 text-white' : 'border-dpsg-mittelbeige text-gray-600'"
                                            x-bind:aria-pressed="istDa(p)" x-bind:aria-label="p.vorname + ' ' + p.nachname + (istDa(p) ? ' ist da' : ' ist nicht da')">
                                        <span x-text="istDa(p) ? '✓ da' : 'da?'"></span>
                                    </button>
                                </li>
                            </template>
                            </ul>
                           </li>
                          </template>
                            <li x-show="gefiltert.length === 0" class="p-6 text-center text-sm text-gray-700"
                                x-text="v.personen.length === 0 ? 'Noch keine bestätigten Anmeldungen.' : 'Niemand passt zu Suche und Filter.'"></li>
                        </ul>
                    </div>

                    {{-- Person --}}
                    <div class="min-h-0 flex-1 overflow-y-auto bg-surface" x-bind:class="auswahl ? '' : 'max-md:hidden'">
                        <div x-show="!auswahl" class="flex h-full items-center justify-center p-8 text-center text-gray-600">
                            <p>Person in der Liste antippen,<br>um Tage, Zahlung und Angaben zu sehen.</p>
                        </div>
                        <template x-if="auswahl">
                            <article class="mx-auto max-w-2xl space-y-4 p-4">
                                <button type="button" class="flex min-h-11 items-center gap-1 font-semibold text-dpsg-blau md:hidden" x-on:click="auswahlId = null">
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                                    Liste
                                </button>
                                <h2 class="text-2xl font-bold text-dpsg-blau" x-text="auswahl.vorname + ' ' + auswahl.nachname"></h2>

                                <section class="rounded-xl bg-white p-4 shadow-sm">
                                    <h3 class="mb-2 font-semibold">Anwesenheit</h3>
                                    <div class="flex flex-wrap gap-2">
                                        <template x-for="t in v.tage" x-bind:key="t.tag">
                                            <button type="button" x-on:click="anwesend(auswahl, t.tag)" x-bind:aria-pressed="istDa(auswahl, t.tag)"
                                                    class="min-h-11 rounded-lg border-2 px-3 text-sm font-semibold"
                                                    x-bind:class="istDa(auswahl, t.tag) ? 'border-green-700 bg-green-700 text-white' : (erwartet(auswahl, t.tag) ? 'border-dpsg-mittelbeige bg-white' : 'border-dashed border-gray-300 bg-white text-gray-500')"
                                                    x-text="(istDa(auswahl, t.tag) ? '✓ ' : '') + t.label"></button>
                                        </template>
                                    </div>
                                    <p class="mt-2 text-xs text-gray-600">Gestrichelt: an diesem Tag nicht angemeldet.</p>
                                </section>

                                <section x-show="v.zusatz_felder.length" class="rounded-xl bg-white p-4 shadow-sm">
                                    <h3 class="mb-2 font-semibold">Zusatzinfos <span class="text-xs font-normal text-gray-600">(nur Team)</span></h3>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <template x-for="f in v.zusatz_felder" x-bind:key="auswahl.id + f.schluessel">
                                            <div>
                                                <label class="block"><span class="text-sm font-semibold" x-text="f.label"></span>
                                                    <input type="text" maxlength="100" x-bind:value="auswahl.zusatz[f.schluessel] ?? ''"
                                                           x-on:change="zusatzSetzen(auswahl, f.schluessel, $event.target.value)" x-on:keydown.enter.prevent="$event.target.blur()"
                                                           class="mt-1 min-h-11 w-full rounded-lg border border-dpsg-mittelbeige px-3"></label>
                                                <p x-show="gemeinsam(auswahl, f.schluessel).length" class="mt-1 text-sm text-gray-700"
                                                   x-text="'Mit dabei: ' + gemeinsam(auswahl, f.schluessel).join(', ')"></p>
                                            </div>
                                        </template>
                                    </div>
                                </section>

                                <section x-show="v.beitrag" class="rounded-xl bg-white p-4 shadow-sm">
                                    <h3 class="mb-2 font-semibold">Teilnahmebeitrag</h3>
                                    <p x-show="!auswahl.zahlung" class="text-sm text-gray-700">Keine Forderung angelegt.</p>
                                    <div x-show="auswahl.zahlung" class="flex flex-wrap items-center gap-3">
                                        <span class="rounded-full px-3 py-1 text-sm font-semibold" x-bind:class="zahlungFarbe(auswahl)" x-text="zahlungText(auswahl)"></span>
                                        <button type="button" x-show="v.bar_moeglich && auswahl.zahlung && auswahl.zahlung.offen_cent > 0" x-on:click="bar(auswahl)"
                                                class="min-h-11 rounded-lg bg-dpsg-blau px-4 font-semibold text-white"
                                                x-text="euro(auswahl.zahlung ? auswahl.zahlung.offen_cent : 0) + ' bar erhalten'"></button>
                                    </div>
                                </section>

                                <section class="rounded-xl bg-white p-4 shadow-sm">
                                    <div class="mb-2 flex items-center justify-between gap-2">
                                        <h3 class="font-semibold">Angaben</h3>
                                        <button type="button" x-show="!bearbeiten" x-on:click="bearbeitenStarten(auswahl)" class="min-h-11 rounded-lg border border-dpsg-blau px-4 text-sm font-semibold text-dpsg-blau">Korrigieren</button>
                                    </div>
                                    <dl x-show="!bearbeiten" class="divide-y divide-gray-100">
                                        <template x-for="a in auswahl.angaben" x-bind:key="a.schluessel">
                                            <div class="grid gap-1 py-2 sm:grid-cols-3">
                                                <dt class="text-sm text-gray-600"><span x-text="a.label"></span><span x-show="a.sensibel" class="ml-1 text-xs font-semibold text-dpsg-rot">(sensibel)</span></dt>
                                                <dd class="sm:col-span-2" x-text="a.wert"></dd>
                                            </div>
                                        </template>
                                        <p x-show="auswahl.angaben.length === 0" class="py-2 text-sm text-gray-700">Keine weiteren Angaben.</p>
                                    </dl>

                                    <form x-show="bearbeiten" x-on:submit.prevent="bearbeitenSpeichern(auswahl)" class="space-y-4">
                                        <template x-if="bearbeiten">
                                            <div class="space-y-4">
                                                <div class="grid gap-3 sm:grid-cols-2">
                                                    <label class="block"><span class="text-sm font-semibold">Vorname</span>
                                                        <input type="text" x-model="bearbeiten.vorname" required maxlength="255" class="mt-1 min-h-11 w-full rounded-lg border border-dpsg-mittelbeige px-3"></label>
                                                    <label class="block"><span class="text-sm font-semibold">Nachname</span>
                                                        <input type="text" x-model="bearbeiten.nachname" required maxlength="255" class="mt-1 min-h-11 w-full rounded-lg border border-dpsg-mittelbeige px-3"></label>
                                                </div>
                                                <template x-for="f in v.felder" x-bind:key="f.schluessel">
                                                    <div>
                                                        <p class="text-sm font-semibold"><span x-text="f.label"></span><span x-show="f.sensibel" class="ml-1 text-xs text-dpsg-rot">(sensibel)</span></p>
                                                        <template x-if="['days', 'mehrfach'].includes(f.typ)">
                                                            <div class="mt-1 flex flex-wrap gap-2">
                                                                <template x-for="[k, l] in Object.entries(f.optionen || {})" x-bind:key="k">
                                                                    <label class="flex min-h-11 items-center gap-2 rounded-lg border border-dpsg-mittelbeige px-3">
                                                                        <input type="checkbox" class="size-5" x-bind:value="k" x-model="bearbeiten.werte[f.schluessel]"><span x-text="l"></span>
                                                                    </label>
                                                                </template>
                                                            </div>
                                                        </template>
                                                        <template x-if="['select', 'herkunft'].includes(f.typ)">
                                                            <select x-model="bearbeiten.werte[f.schluessel]" class="mt-1 min-h-11 w-full rounded-lg border border-dpsg-mittelbeige bg-white px-3">
                                                                <option value="">– keine Angabe –</option>
                                                                <template x-for="[k, l] in Object.entries(f.optionen || {})" x-bind:key="k">
                                                                    <option x-bind:value="k" x-text="l" x-bind:selected="bearbeiten.werte[f.schluessel] === k"></option>
                                                                </template>
                                                            </select>
                                                        </template>
                                                        <template x-if="f.typ === 'textarea'">
                                                            <textarea x-model="bearbeiten.werte[f.schluessel]" rows="3" maxlength="1000" class="mt-1 w-full rounded-lg border border-dpsg-mittelbeige px-3 py-2"></textarea>
                                                        </template>
                                                        <template x-if="!['days', 'mehrfach', 'select', 'herkunft', 'textarea'].includes(f.typ)">
                                                            <input x-bind:type="['year', 'zahl'].includes(f.typ) ? 'number' : (f.typ === 'date' ? 'date' : 'text')" x-model="bearbeiten.werte[f.schluessel]" maxlength="1000"
                                                                   class="mt-1 min-h-11 w-full rounded-lg border border-dpsg-mittelbeige px-3">
                                                        </template>
                                                    </div>
                                                </template>
                                                <div class="flex flex-wrap gap-2">
                                                    <button type="submit" class="min-h-11 rounded-lg bg-dpsg-blau px-5 font-semibold text-white">Speichern</button>
                                                    <button type="button" x-on:click="bearbeiten = null" class="min-h-11 rounded-lg border border-dpsg-mittelbeige px-5 font-semibold">Abbrechen</button>
                                                </div>
                                                <p class="text-xs text-gray-600">Die Person bekommt keine Mail. Die Änderung wird mit deinem Namen protokolliert.</p>
                                            </div>
                                        </template>
                                    </form>
                                </section>
                            </article>
                        </template>
                    </div>
                </div>
            </section>
        </template>
    </main>
</div>
</body>
</html>
