{{--
    Cookie-/Consent-Banner, s. Pflichtenheft 6.8 (F-CON-01 bis 07).
    Selbst gehostet (Alpine.js, bereits im App-Bundle), keine externen
    Dienste. Einwilligung wird nur im Browser gespeichert (localStorage,
    6 Monate), nicht serverseitig personenbezogen (F-CON-04).
--}}
<div
    x-data="{
        open: false,
        prefs: { stats: false, external: false },
        STORAGE_KEY: 'dpsg-consent-v1',
        init() {
            const stored = this.readStored();
            if (stored === null) {
                this.open = true;
            } else {
                this.prefs = stored.prefs;
            }
            window.addEventListener('open-consent-settings', () => { this.open = true; });
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
    <div class="max-w-4xl mx-auto px-4 py-5 sm:py-6">
        <h2 id="consent-heading" class="font-serif text-lg font-bold mb-2">Datenschutz-Einstellungen</h2>
        <p class="text-sm text-white/85 mb-4">
            Notwendige Cookies sind für den Betrieb der Seite immer aktiv. Statistik- und
            Externe-Inhalte-Cookies werden nur mit deiner Einwilligung geladen. Mehr dazu in
            unserer <a href="{{ url('/datenschutz') }}" class="underline">Datenschutzerklärung</a>.
        </p>

        <div class="space-y-2 mb-4 text-sm">
            <label class="flex items-center gap-2 opacity-70">
                <input type="checkbox" checked disabled class="rounded">
                Notwendig (immer aktiv)
            </label>
            <label class="flex items-center gap-2">
                <input type="checkbox" x-model="prefs.stats" class="rounded">
                Statistik (anonymisiert, selbst gehostet)
            </label>
            <label class="flex items-center gap-2">
                <input type="checkbox" x-model="prefs.external" class="rounded">
                Externe Inhalte (z. B. eingebettete Karten/Videos)
            </label>
        </div>

        <div class="flex flex-wrap gap-3">
            <button type="button" @click="acceptAll()" class="rounded bg-dpsg-rot px-4 py-2 text-sm font-medium">
                Alle akzeptieren
            </button>
            <button type="button" @click="save()" class="rounded border border-white/40 px-4 py-2 text-sm font-medium">
                Auswahl speichern
            </button>
            <button type="button" @click="acceptNecessaryOnly()" class="rounded border border-white/40 px-4 py-2 text-sm font-medium">
                Nur notwendige
            </button>
        </div>
    </div>
</div>
