/**
 * Autovervollständigung „Stamm / Bezirk / Verband“ im Anmeldeformular: sucht in
 * Name, Ort und Bezirk, ohne Rücksicht auf Groß-/Kleinschreibung und
 * Umlaute. Treffer am Wortanfang stehen vorn. Freie Eingabe bleibt möglich.
 */
const normalize = (text) => text
    .toLowerCase()
    .replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
    .normalize('NFD').replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9]+/g, ' ')
    .trim();

export default (options, initial = '') => ({
    query: initial,
    open: false,
    active: -1,
    index: options.map((option) => ({
        ...option,
        name: ' ' + normalize(option.value),
        haystack: ' ' + normalize(option.search),
    })),

    get results() {
        const words = normalize(this.query).split(' ').filter(Boolean);
        if (words.length === 0) {
            return [];
        }

        const phrase = words.join(' ');
        // 0: Name beginnt mit der Eingabe, 1: alle Wörter am Wortanfang im
        // Namen, 2: am Wortanfang in Ort/Bezirk, 3: irgendwo enthalten.
        const score = (option) => {
            if (option.name.startsWith(' ' + phrase) || option.name.startsWith(' bezirk ' + phrase)) return 0;
            if (words.every((word) => option.name.includes(' ' + word))) return 1;
            if (words.every((word) => option.haystack.includes(' ' + word))) return 2;
            return 3;
        };

        return this.index
            .filter((option) => words.every((word) => option.haystack.includes(word)))
            .map((option) => ({ option, score: score(option) }))
            .sort((a, b) => a.score - b.score)
            .slice(0, 8)
            .map(({ option }) => option);
    },

    move(step) {
        if (!this.open) {
            this.open = true;
        }
        const count = this.results.length;
        if (count > 0) {
            this.active = (this.active + step + count) % count;
        }
    },

    choose(option) {
        this.query = option.value;
        this.open = false;
        this.active = -1;
    },
});
