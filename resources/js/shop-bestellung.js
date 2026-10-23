/**
 * Bestellseite der Sammelbestellung (/bestellen): oben die Mitglieder, in der
 * Liste je Artikel Größe, Anzahl und „Hinzufügen“ (für das gewählte
 * Mitglied), Sets mit Größe je Teil und optionalen Teilen, freie
 * Rüsthaus-Nummer mit Nachschlagen; unten die Übersicht. Abgeschickt wird
 * wie bisher als pos[i][…]. Eigene Datei statt Inline-Skript wegen der CSP.
 */
export default (katalog, sets, alt, nachschlagenUrl, stufen = {}, besteller = '') => {
    let zaehler = 0;
    const euro = (cent) => (cent / 100).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' });
    const mitglied = (name = '', stufe = '') => ({ key: ++zaehler, name, stufe });
    const teileStart = (set, alte = {}) => {
        const teile = {};
        set.teile.forEach((t) => {
            const a = alte[t.id] || {};
            teile[t.id] = {
                variante: a.variante ? String(a.variante) : (t.varianten.length === 1 ? String(t.varianten[0].id) : ''),
                dabei: a.dabei !== undefined ? ['1', 1, true, 'true'].includes(a.dabei) : t.vorgewaehlt,
            };
        });
        return teile;
    };
    const groesse = (v) => (v && v.bezeichnung ? ' (' + v.bezeichnung + ')' : '');

    return {
        katalog,
        sets,
        besteller,
        mitglieder: [],
        aktiv: null,
        eintraege: [],
        wahl: {},
        setWahl: {},
        frei: { nummer: '', text: '', menge: 1, info: '', gesperrt: false, name: null },
        hinweis: {},

        init() {
            katalog.forEach((a) => { this.wahl[a.id] = { variante: a.varianten.length === 1 ? String(a.varianten[0].id) : '', menge: 1 }; });
            sets.forEach((s) => { this.setWahl[s.id] = { offen: false, menge: 1, teile: teileStart(s) }; });

            // Nach einem Fehler beim Absenden: Mitglieder und Einträge wiederherstellen.
            (alt || []).forEach((p) => {
                if (!p) { return; }
                let m = this.mitglieder.find((m) => m.name === (p.mitglied || '') && m.stufe === (p.stufe || ''));
                if (!m) { m = mitglied(p.mitglied || '', p.stufe || ''); this.mitglieder.push(m); }
                const menge = parseInt(p.menge) || 1;
                if (p.set) {
                    const s = sets.find((s) => s.id === 's' + p.set);
                    if (s) { this.eintraege.push(this.setEintrag(s, teileStart(s, p.teile || {}), menge, m.key)); }
                } else if (p.variante) {
                    const a = katalog.find((a) => a.varianten.some((v) => String(v.id) === String(p.variante)));
                    const v = a && a.varianten.find((v) => String(v.id) === String(p.variante));
                    if (v) { this.eintraege.push({ key: ++zaehler, mitglied: m.key, typ: 'artikel', variante: v.id, menge, label: a.name + groesse(v), detail: '', preis: v.preis }); }
                } else if (p.frei_nummer) {
                    this.eintraege.push({ key: ++zaehler, mitglied: m.key, typ: 'frei', nummer: p.frei_nummer, text: p.frei_text || '', menge, label: 'Rüsthaus-Nr. ' + p.frei_nummer, detail: p.frei_text || '', preis: null });
                }
            });
            if (!this.mitglieder.length) { this.mitglieder.push(mitglied()); }
            this.aktiv = this.mitglieder[0].key;
        },

        euro,
        stufeName(key) { return stufen[key] || key; },
        aktivesMitglied() { return this.mitglieder.find((m) => m.key === this.aktiv) || this.mitglieder[0]; },
        // Ohne Namen gilt das Mitglied als die bestellende Person selbst („Deine Angaben“).
        name(m) { return (m && m.name.trim()) || this.besteller.trim(); },
        anzeigeName(m) { return (m && m.name.trim()) || (this.besteller.trim() ? this.besteller.trim() + ' (du selbst)' : 'Du selbst'); },
        aktivName() { return this.anzeigeName(this.aktivesMitglied()); },
        mitgliedVon(e) { return this.mitglieder.find((m) => m.key === e.mitglied) || { name: '', stufe: '' }; },
        mitgliedNeu() {
            const m = mitglied();
            this.mitglieder.push(m);
            this.aktiv = m.key;
            this.$nextTick(() => document.getElementById('m-name-' + m.key)?.focus());
        },
        mitgliedEntfernen(m) {
            if (this.eintraegeFuer(m).length && !confirm('Mitglied mit allen ausgewählten Artikeln entfernen?')) { return; }
            this.eintraege = this.eintraege.filter((e) => e.mitglied !== m.key);
            this.mitglieder = this.mitglieder.filter((x) => x.key !== m.key);
            if (this.aktiv === m.key) { this.aktiv = this.mitglieder[0].key; }
        },
        eintraegeFuer(m) { return this.eintraege.filter((e) => e.mitglied === m.key); },
        entfernen(e) { this.eintraege = this.eintraege.filter((x) => x.key !== e.key); },
        summe() { return this.eintraege.reduce((s, e) => s + (e.preis === null ? 0 : e.preis * e.menge), 0); },
        setTeile(sid) { const s = this.sets.find((s) => s.id === sid); return s ? s.teile : []; },

        melden(schluessel, text, fehler = false) {
            this.hinweis[schluessel] = { text, fehler };
            if (!fehler) { setTimeout(() => { if (this.hinweis[schluessel]?.text === text) { delete this.hinweis[schluessel]; } }, 2500); }
        },
        hinzufuegen(schluessel, eintrag) {
            this.eintraege.push({ key: ++zaehler, mitglied: this.aktivesMitglied().key, ...eintrag });
            this.melden(schluessel, '✓ Hinzugefügt für ' + (this.name(this.aktivesMitglied()) || 'dich selbst'));
        },

        artikelHinzufuegen(id) {
            const a = this.katalog.find((a) => a.id === id);
            const w = this.wahl[id];
            const v = a.varianten.find((v) => String(v.id) === String(w.variante));
            if (!v) { this.melden(id, 'Bitte eine Größe wählen.', true); return; }
            this.hinzufuegen(id, { typ: 'artikel', variante: v.id, menge: Math.min(10, Math.max(1, parseInt(w.menge) || 1)), label: a.name + groesse(v), detail: '', preis: v.preis });
            w.menge = 1;
        },

        setEintrag(s, teile, menge, mitgliedKey = null) {
            const gewaehlt = s.teile.filter((t) => !t.optional || teile[t.id].dabei);
            const preis = gewaehlt.reduce((sum, t) => {
                const v = t.varianten.find((v) => String(v.id) === String(teile[t.id].variante));
                return sum + (v ? v.preis * t.menge : 0);
            }, 0);
            const detail = gewaehlt.map((t) => {
                const v = t.varianten.find((v) => String(v.id) === String(teile[t.id].variante));
                return (t.menge > 1 ? t.menge + ' × ' : '') + t.name + groesse(v);
            }).join(', ');
            return { key: ++zaehler, mitglied: mitgliedKey, typ: 'set', set: s.id.slice(1), teile: JSON.parse(JSON.stringify(teile)), menge, label: s.name, detail, preis };
        },
        setHinzufuegen(sid) {
            const s = this.sets.find((s) => s.id === sid);
            const w = this.setWahl[sid];
            const fehlt = s.teile.find((t) => (!t.optional || w.teile[t.id].dabei) && !w.teile[t.id].variante);
            if (fehlt) { this.melden(sid, 'Bitte die Größe für „' + fehlt.name + '“ wählen.', true); return; }
            const { key, mitglied: _m, ...eintrag } = this.setEintrag(s, w.teile, Math.min(10, Math.max(1, parseInt(w.menge) || 1)));
            this.hinzufuegen(sid, eintrag);
            w.menge = 1;
        },

        async nachschlagen() {
            const f = this.frei;
            if (!f.nummer.trim()) { return; }
            f.info = 'Wird beim Rüsthaus nachgeschlagen …';
            f.gesperrt = false;
            f.name = null;
            try {
                const r = await fetch(nachschlagenUrl + '?nummer=' + encodeURIComponent(f.nummer.trim()), { headers: { Accept: 'application/json' } });
                const d = await r.json();
                if (d.gesperrt) {
                    f.gesperrt = true;
                    f.info = 'Dieser Artikel kann über die Sammelbestellung nicht bestellt werden. Sprich bitte deine Leitung an.';
                } else if (d.name) {
                    f.name = d.name;
                    f.info = d.name + ' – ' + euro(d.preis_cent) + ' (vorläufig, wir prüfen den Preis)'
                        + (d.varianten && d.varianten.length > 1 ? '. Größen: ' + d.varianten.map((v) => v.bezeichnung).join(', ') : '');
                } else {
                    f.info = 'Nicht gefunden. Trag ein, was du genau brauchst – wir prüfen es.';
                }
            } catch (e) {
                f.info = 'Nachschlagen klappt gerade nicht. Trag ein, was du genau brauchst.';
            }
        },
        freiHinzufuegen() {
            const f = this.frei;
            if (!f.nummer.trim()) { this.melden('frei', 'Bitte eine Rüsthaus-Artikelnummer oder einen Link eintragen.', true); return; }
            if (f.gesperrt) { this.melden('frei', 'Dieser Artikel kann nicht bestellt werden.', true); return; }
            this.hinzufuegen('frei', { typ: 'frei', nummer: f.nummer.trim(), text: f.text.trim(), menge: Math.min(10, Math.max(1, parseInt(f.menge) || 1)),
                label: f.name || 'Rüsthaus-Nr. ' + f.nummer.trim(), detail: [f.text.trim(), 'wird geprüft'].filter(Boolean).join(' · '), preis: null });
            Object.assign(f, { nummer: '', text: '', menge: 1, info: '', gesperrt: false, name: null });
        },
    };
};
