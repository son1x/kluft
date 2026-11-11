{{--
    Cookie-/Consent-Banner, s. Pflichtenheft 6.8 (F-CON-01 bis 07).
    Selbst gehostet (Alpine.js, bereits im App-Bundle), keine externen
    Dienste. Einwilligung wird nur im Browser gespeichert (localStorage,
    6 Monate), nicht serverseitig personenbezogen (F-CON-04).
--}}
<div
    x-data="{
        open: false,
        details: false,
        prefs: { stats: false, external: false },
        STORAGE_KEY: 'dpsg-consent-v1',
        init() {
            const stored = this.readStored();
            if (stored === null) {
                this.open = true;
            } else {
                this.prefs = stored.prefs;
            }
            // Aus der Fußzeile geöffnet: gleich mit der Einzelauswahl.
            window.addEventListener('open-consent-settings', () => { this.details = true; this.open = true; });
        },
        readStored() {
            try {
                const raw = localStorage.getItem(this.STORAGE_KEY);
                if (!raw) return null;
                const data = JSON.parse(raw);
                const sixMonthsMs = 1000 * 60 * 60 * 24 * 182;
                if (!data.savedAt || (Date.now() - data.savedAt) > sixMonthsMs) return null;
                return data;
            } catch (e) { return null; }
        },
        save() {
            try {
                localStorage.setItem(this.STORAGE_KEY, JSON.stringify({ prefs: this.prefs, savedAt: Date.now() }));
            } catch (e) {}
            window.dispatchEvent(new CustomEvent('consent-changed', { detail: { ...this.prefs } }));
            this.open = false;
        },
        acceptAll() {
            this.prefs = { stats: true, external: true };
            this.save();
        },
        acceptNecessaryOnly() {
            this.prefs = { stats: false, external: false };
            this.save();
        },
    }"
    x-cloak
    x-show="open"
    class="fixed inset-x-0 bottom-0 z-50 bg-dpsg-blau text-white border-t-4 border-dpsg-rot"
    role="dialog"
    aria-modal="true"
    aria-labelledby="consent-heading"
>
    {{-- Kompakt (Apple HIG: Inhalte nicht verdecken): kurzer Text, zwei
         gleichwertige 44-pt-Knöpfe; die Einzelauswahl erst auf Wunsch. --}}
    <div class="mx-auto max-w-4xl px-4 pt-4 pb-[calc(1rem+env(safe-area-inset-bottom))]">
        <h2 id="consent-heading" class="mb-1 font-serif text-base font-bold">Datenschutz-Einstellungen</h2>
        <p class="mb-3 text-sm text-white/85">
            Videos und Karten anderer Anbieter laden wir nur mit deiner Einwilligung. Keine Statistik, keine Werbung.
            Mehr in der <a href="{{ url('/datenschutz') }}" class="underline">Datenschutzerklärung</a>.
        </p>

        <div x-show="details" x-cloak class="mb-3 text-sm">
            <label class="flex min-h-11 items-center gap-3 opacity-70">
                <input type="checkbox" checked disabled class="rounded">
                Notwendig (immer aktiv)
            </label>
            <label class="flex min-h-11 items-center gap-3">
                <input type="checkbox" x-model="prefs.external" class="rounded">
                Externe Inhalte (z. B. eingebettete Karten/Videos)
            </label>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:flex sm:flex-wrap">
            <button type="button" @click="acceptAll()" class="min-h-11 rounded bg-dpsg-rot px-4 py-2 text-sm font-bold">
                Alle akzeptieren
            </button>
            <button type="button" @click="acceptNecessaryOnly()" class="min-h-11 rounded border border-white/60 px-4 py-2 text-sm font-bold">
                Nur notwendige
            </button>
            <button type="button" x-show="details" x-cloak @click="save()" class="col-span-2 min-h-11 rounded border border-white/60 px-4 py-2 text-sm font-bold">
                Auswahl speichern
            </button>
            <button type="button" x-show="!details" @click="details = true" class="col-span-2 min-h-11 px-2 text-sm underline sm:ml-auto">
                Einstellungen
            </button>
        </div>
    </div>
</div>
