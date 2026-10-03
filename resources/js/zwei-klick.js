// Zwei-Klick-Einbettung (Pflichtenheft 6.8): lädt den fremden Inhalt erst
// nach Klick oder wenn „Externe Inhalte“ im Consent-Banner erlaubt sind.
const STORAGE_KEY = 'dpsg-consent-v1';

function externalAllowed() {
    try {
        const data = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
        return Boolean(data && data.prefs && data.prefs.external);
    } catch (e) {
        return false;
    }
}

export default function zweiKlick() {
    return {
        loaded: false,
        remember: false,
        src: '',
        init() {
            this.src = this.$el.dataset.src;
            if (externalAllowed()) {
                this.loaded = true;
            }
            window.addEventListener('consent-changed', (event) => {
                if (event.detail && event.detail.external) {
                    this.loaded = true;
                }
            });
        },
        load() {
            if (this.remember) {
                // Wie „Externe Inhalte“ im Consent-Banner: gilt für alle Einbettungen.
                let data = { prefs: { stats: false, external: true }, savedAt: Date.now() };
                try {
                    const stored = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
                    if (stored && stored.prefs) {
                        data = { prefs: { ...stored.prefs, external: true }, savedAt: Date.now() };
                    }
                    localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
                } catch (e) {}
                window.dispatchEvent(new CustomEvent('consent-changed', { detail: data.prefs }));
            }
            this.loaded = true;
        },
    };
}
