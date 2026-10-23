// Umfrage: bedingte Fragen ein- und ausblenden (docs/umfragen.md).
// Liest die Antworten aus dem Formular; ohne JavaScript bleiben alle
// Fragen sichtbar und der Server verwirft ausgeblendete Antworten.
export default () => ({
    werte: {},

    init() {
        this.lesen();
        this.$el.addEventListener('change', () => this.lesen());
    },

    lesen() {
        const werte = {};
        for (const [name, wert] of new FormData(this.$el).entries()) {
            const m = name.match(/^a\[([^\]]+)\](\[\])?$/);
            if (m) {
                (werte[m[1]] ??= []).push(wert);
            }
        }
        this.werte = werte;
    },

    zeigen(el, tiefe = 0) {
        let b = null;
        try {
            b = JSON.parse(el.dataset.bedingung || 'null');
        } catch {
            return true;
        }
        if (!b || !b.frage || tiefe > 20) {
            return true;
        }
        const quelle = this.$el.querySelector(`[data-frage="${CSS.escape(b.frage)}"]`);
        if (quelle && quelle.dataset.bedingung && !this.zeigen(quelle, tiefe + 1)) {
            return false;
        }
        return (this.werte[b.frage] || []).some((w) => (b.werte || []).includes(w));
    },
});
