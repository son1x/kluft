/**
 * Bestellformular der Sammelbestellung (/bestellen): Positionen mit Artikel,
 * Größe oder Set (Größe je Teil, optionale Teile), freie Rüsthaus-Nummer mit
 * Nachschlagen, Summe. Eigene Datei statt Inline-Skript wegen der CSP.
 */
export default (katalog, sets, alt, nachschlagenUrl) => {
    let zaehler = 0;
    const teileFuer = (wahl, alteTeile = {}) => {
        const s = sets.find(s => s.id === wahl);
        const teile = {};
        (s ? s.teile : []).forEach(t => {
            const a = alteTeile[t.id] || {};
            teile[t.id] = { variante: a.variante || (t.varianten.length === 1 ? String(t.varianten[0].id) : ''), dabei: a.dabei !== undefined ? a.dabei === '1' || a.dabei === true : t.vorgewaehlt };
        });
        return teile;
    };
    const leer = (p = {}) => ({ key: ++zaehler, mitglied: p.mitglied || '', stufe: p.stufe || '', menge: p.menge || 1, artikel: p.frei_nummer ? 'frei' : (p.set ? 's' + p.set : ''), variante: p.variante || '', nummer: p.frei_nummer || '', text: p.frei_text || '', info: '', gesperrt: false, teile: p.set ? teileFuer('s' + p.set, p.teile || {}) : {} });
    return {
        katalog,
        sets,
        positionen: (alt && alt.length ? alt : [{}]).map(p => {
            const pos = leer(p);
            if (p.variante) {
                const a = katalog.find(a => a.varianten.some(v => String(v.id) === String(p.variante)));
                if (a) { pos.artikel = String(a.id); }
            }
            return pos;
        }),
        neu() { this.positionen.push(leer()); },
        set(pos) { return this.sets.find(s => s.id === pos.artikel) || null; },
        setTeile(pos) { const s = this.set(pos); return s ? s.teile.filter(t => pos.teile[t.id]) : []; },
        gewaehlt(pos) { pos.variante = ''; pos.teile = teileFuer(pos.artikel); },
        waehlen(wahl) {
            let pos = this.positionen.find(p => !p.artikel);
            if (!pos) { pos = leer(); const vorher = this.positionen[this.positionen.length - 1]; pos.mitglied = vorher.mitglied; pos.stufe = vorher.stufe; this.positionen.push(pos); }
            pos.artikel = wahl;
            this.gewaehlt(pos);
        },
        varianten(pos) { const a = this.katalog.find(a => String(a.id) === String(pos.artikel)); return a ? a.varianten : []; },
        euro(cent) { return (cent / 100).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' }); },
        summe() {
            return this.positionen.reduce((s, p) => {
                const set = this.set(p);
                if (set) {
                    return s + set.teile.reduce((t, teil) => {
                        const w = p.teile[teil.id] || {};
                        const v = teil.varianten.find(v => String(v.id) === String(w.variante));
                        return t + ((!teil.optional || w.dabei) && v ? v.preis * teil.menge * (parseInt(p.menge) || 1) : 0);
                    }, 0);
                }
                const v = this.varianten(p).find(v => String(v.id) === String(p.variante));
                return s + (v ? v.preis * (parseInt(p.menge) || 1) : 0);
            }, 0);
        },
        async nachschlagen(pos) {
            pos.info = 'Wird beim Rüsthaus nachgeschlagen …'; pos.gesperrt = false;
            try {
                const r = await fetch(nachschlagenUrl + '?nummer=' + encodeURIComponent(pos.nummer), { headers: { Accept: 'application/json' } });
                const d = await r.json();
                if (d.gesperrt) { pos.gesperrt = true; pos.info = 'Dieser Artikel kann über die Sammelbestellung nicht bestellt werden. Sprich bitte deine Leitung an.'; }
                else if (d.name) { pos.info = d.name + ' – ' + this.euro(d.preis_cent) + ' (vorläufig, wir prüfen den Preis)' + (d.varianten && d.varianten.length > 1 ? '. Größen: ' + d.varianten.map(v => v.bezeichnung).join(', ') : ''); }
                else { pos.info = 'Nicht gefunden. Trag unten ein, was du genau brauchst – wir prüfen es.'; }
            } catch (e) { pos.info = 'Nachschlagen klappt gerade nicht. Trag unten ein, was du genau brauchst.'; }
        },
    };
};
