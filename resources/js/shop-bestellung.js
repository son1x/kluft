/**
 * Bestellseite der Sammelbestellung (/bestellen), Pflichtenheft
 * docs/pflichtenheft-sammelbestellung-shop.md (SB-110 bis SB-135):
 * Artikelraster mit Kategorien und Suche, Artikel-Sheet (Größe, für wen,
 * Anzahl), Warenkorb nach Mitglied, Bestellen in drei Schritten.
 * Abgeschickt wird wie bisher als pos[i][…], der Server prüft alles neu.
 * Der Warenkorb bleibt 7 Tage im Browser (localStorage), nur Artikel-IDs,
 * Größen, Mengen und eingegebene Mitgliedsnamen. Eigene Datei wegen der CSP.
 */
const SPEICHER = 'kluft-warenkorb-v1';
const SPEICHER_TAGE = 7;

export default (daten) => {
    const { katalog, sets, alt, nachschlagenUrl, stufen = {}, besteller = '', fehlerSchritt = null } = daten;
    let zaehler = 0;
    const euro = (cent) => (cent / 100).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' });
    const mitglied = (name = '', stufe = '') => ({ key: ++zaehler, name, stufe });
    const groesse = (v) => (v && v.bezeichnung ? ' (' + v.bezeichnung + ')' : '');
    const alle = [...sets, ...katalog];
    const finden = (id) => alle.find((x) => x.id === id);
    const variante = (artikel, id) => artikel && artikel.varianten.find((v) => String(v.id) === String(id));
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
    const lesen = () => {
        try {
            const d = JSON.parse(localStorage.getItem(SPEICHER) || 'null');
            return d && Date.now() - d.zeit < SPEICHER_TAGE * 864e5 ? d : null;
        } catch (e) { return null; }
    };

    return {
        euro,
        suche: '',
        kategorie: 'alle',
        mitglieder: [],
        eintraege: [],
        besteller,
        sheet: { id: null, variante: '', teile: {}, mitglied: null, menge: 1, neu: false, neuName: '', neuStufe: '', fehler: '' },
        kasse: { schritt: 0, fehler: '' },
        frei: { nummer: '', text: '', menge: 1, info: '', gesperrt: false, name: null },
        meldung: '',

        init() {
            // Nach einem Fehler beim Absenden: aus den alten Eingaben, sonst aus dem Browser.
            if ((alt || []).length) {
                (alt || []).forEach((p) => this.ausPosition(p));
            } else {
                const d = lesen();
                if (d) {
                    (d.mitglieder || []).forEach((m) => this.mitglieder.push(mitglied(m.name || '', m.stufe || '')));
                    (d.eintraege || []).forEach((e) => {
                        const m = this.mitglieder[e.m];
                        if (m) { this.ausGespeichert(e, m.key); }
                    });
                }
            }
            this.$watch('eintraege', () => this.speichern());
            this.$watch('mitglieder', () => this.speichern());
            if (fehlerSchritt !== null) { this.$nextTick(() => this.kasseOeffnen(fehlerSchritt)); }
            const ziel = (location.hash.match(/^#artikel-([as]\d+)$/) || [])[1];
            if (ziel && finden(ziel)) { this.$nextTick(() => this.oeffnen(ziel)); }
        },

        // ---- Raster ----
        zeigen(id) {
            const x = finden(id);
            if (!x) { return false; }
            const k = this.kategorie;
            const passt = k === 'alle' || (k === 'start' && x.hervorheben) || (k === 'sets' && x.typ === 'set')
                || (k === 'stamm' && x.eigen) || (x.typ === 'artikel' && x.kategorie === k);
            const q = this.suche.trim().toLowerCase();
            return passt && (q === '' || [x.name, x.kategorie, x.nummer].filter(Boolean).join(' ').toLowerCase().includes(q));
        },
        anzahlSichtbar() { return alle.filter((x) => this.zeigen(x.id)).length; },

        // ---- Mitglieder ----
        stufeName(key) { return stufen[key] || key; },
        anzeigeName(m) { return (m && m.name.trim()) || 'Für dich selbst'; },
        mitgliedLabel(m) { return this.anzeigeName(m) + (m.stufe ? ' · ' + this.stufeName(m.stufe) : ''); },
        mitgliedVon(e) { return this.mitglieder.find((m) => m.key === e.mitglied) || { name: '', stufe: '' }; },
        name(m) { return (m && m.name.trim()) || this.besteller.trim(); },
        eintraegeFuer(m) { return this.eintraege.filter((e) => e.mitglied === m.key); },
        gruppen() { return this.mitglieder.filter((m) => this.eintraegeFuer(m).length); },

        // ---- Artikel-Sheet ----
        aktuell() { return finden(this.sheet.id) || null; },
        oeffnen(id) {
            const x = finden(id);
            if (!x) { return; }
            Object.assign(this.sheet, {
                id, menge: 1, fehler: '',
                variante: x.typ === 'artikel' && x.varianten.length === 1 ? String(x.varianten[0].id) : '',
                teile: x.typ === 'set' ? teileStart(x) : {},
                mitglied: this.mitglieder.length ? (this.sheet.mitglied && this.mitglieder.some((m) => m.key === this.sheet.mitglied) ? this.sheet.mitglied : this.mitglieder[0].key) : null,
                neu: this.mitglieder.length === 0, neuName: '', neuStufe: '',
            });
            this.$refs.sheet.showModal();
            history.replaceState(null, '', '#artikel-' + id);
        },
        schliessen() {
            if (this.$refs.sheet.open) { this.$refs.sheet.close(); }
            if (location.hash.startsWith('#artikel-')) { history.replaceState(null, '', location.pathname + location.search); }
        },
        sheetPreis() {
            const x = this.aktuell();
            if (!x) { return 0; }
            const kleinster = (t) => Math.min(...t.varianten.map((v) => v.preis));
            if (x.typ === 'set') {
                return x.teile.filter((t) => !t.optional || this.sheet.teile[t.id].dabei)
                    .reduce((s, t) => { const v = variante(t, this.sheet.teile[t.id].variante); return s + (v ? v.preis : kleinster(t)) * t.menge; }, 0) * this.sheet.menge;
            }
            const v = variante(x, this.sheet.variante);
            return (v ? v.preis : x.preisAb) * this.sheet.menge;
        },
        // „ab …“, solange eine Größe mit anderem Preis noch nicht gewählt ist.
        sheetPreisText() {
            const x = this.aktuell();
            if (!x) { return ''; }
            const offen = x.typ === 'set'
                ? x.teile.some((t) => (!t.optional || this.sheet.teile[t.id].dabei) && !this.sheet.teile[t.id].variante && new Set(t.varianten.map((v) => v.preis)).size > 1)
                : !this.sheet.variante && new Set(x.varianten.map((v) => v.preis)).size > 1;
            return (offen ? 'ab ' : '') + euro(this.sheetPreis());
        },
        inWarenkorb() {
            const x = this.aktuell();
            const s = this.sheet;
            if (x.typ === 'artikel' && !variante(x, s.variante)) { s.fehler = 'Bitte eine Größe wählen.'; return; }
            if (x.typ === 'set') {
                const fehlt = x.teile.find((t) => (!t.optional || s.teile[t.id].dabei) && !s.teile[t.id].variante);
                if (fehlt) { s.fehler = 'Bitte die Größe für „' + fehlt.name + '“ wählen.'; return; }
            }
            let key = s.mitglied;
            if (s.neu || key === null) {
                const m = mitglied(s.neuName.trim(), s.neuStufe);
                this.mitglieder.push(m);
                key = m.key;
                s.mitglied = key;
            }
            const menge = Math.min(10, Math.max(1, parseInt(s.menge) || 1));
            if (x.typ === 'set') {
                this.eintraege.push(this.setEintrag(x, s.teile, menge, key));
            } else {
                const v = variante(x, s.variante);
                this.eintraege.push({ key: ++zaehler, mitglied: key, typ: 'artikel', artikel: x.id, variante: v.id, menge, label: x.name + groesse(v), detail: '', preis: v.preis });
            }
            this.melden('„' + x.name + '“ ist im Warenkorb (' + this.mitgliedLabel(this.mitglieder.find((m) => m.key === key)) + ').');
            this.schliessen();
        },
        setEintrag(s, teile, menge, mitgliedKey) {
            const gewaehlt = s.teile.filter((t) => !t.optional || teile[t.id].dabei);
            const preis = gewaehlt.reduce((sum, t) => { const v = variante(t, teile[t.id].variante); return sum + (v ? v.preis * t.menge : 0); }, 0);
            const detail = gewaehlt.map((t) => (t.menge > 1 ? t.menge + ' × ' : '') + t.name + groesse(variante(t, teile[t.id].variante))).join(', ');
            return { key: ++zaehler, mitglied: mitgliedKey, typ: 'set', set: s.id.slice(1), teile: JSON.parse(JSON.stringify(teile)), menge, label: s.name, detail, preis };
        },

        // ---- Warenkorb ----
        anzahl() { return this.eintraege.reduce((s, e) => s + e.menge, 0); },
        summe() { return this.eintraege.reduce((s, e) => s + (e.preis === null ? 0 : e.preis * e.menge), 0); },
        hatVorlaeufige() { return this.eintraege.some((e) => e.preis === null); },
        mehr(e) { e.menge = Math.min(10, e.menge + 1); },
        weniger(e) { if (e.menge > 1) { e.menge--; } else { this.entfernen(e); } },
        entfernen(e) {
            this.eintraege = this.eintraege.filter((x) => x.key !== e.key);
            this.mitglieder = this.mitglieder.filter((m) => this.eintraegeFuer(m).length || m.key === this.sheet.mitglied);
            if (!this.eintraege.length) { this.kasse.schritt = 0; }
        },
        melden(text) { this.meldung = ''; this.$nextTick(() => { this.meldung = text; }); },

        // ---- Bestellen in drei Schritten ----
        kasseOeffnen(schritt = 0) { this.kasse.schritt = schritt; this.kasse.fehler = ''; this.$refs.kasse.showModal(); },
        kasseSchliessen() { this.$refs.kasse.close(); },
        weiter() {
            this.kasse.fehler = '';
            if (this.kasse.schritt === 1) {
                const form = this.$refs.formular;
                const felder = ['name', 'email'].map((n) => form.elements[n]);
                const fehlt = felder.find((f) => !f.checkValidity());
                if (fehlt) { fehlt.reportValidity(); return; }
            }
            this.kasse.schritt = Math.min(3, this.kasse.schritt + 1);
            this.$nextTick(() => this.$refs.kasse.querySelector('[data-schritt="' + this.kasse.schritt + '"] h2')?.focus());
        },
        zurueck() { this.kasse.schritt = Math.max(0, this.kasse.schritt - 1); },
        abschicken(ereignis) {
            if (!this.eintraege.length) { ereignis.preventDefault(); return; }
            try { localStorage.removeItem(SPEICHER); } catch (e) { /* ohne Speicher */ }
        },

        // ---- Merken im Browser ----
        speichern() {
            try {
                if (!this.eintraege.length) { localStorage.removeItem(SPEICHER); return; }
                const index = new Map(this.mitglieder.map((m, i) => [m.key, i]));
                localStorage.setItem(SPEICHER, JSON.stringify({
                    zeit: Date.now(),
                    mitglieder: this.mitglieder.map((m) => ({ name: m.name, stufe: m.stufe })),
                    eintraege: this.eintraege.map((e) => ({ m: index.get(e.mitglied), typ: e.typ, artikel: e.artikel, variante: e.variante, set: e.set, teile: e.teile, nummer: e.nummer, text: e.text, menge: e.menge })),
                }));
            } catch (e) { /* privates Fenster o. Ä.: dann eben ohne Merken */ }
        },
        ausGespeichert(e, key) {
            const menge = Math.min(10, Math.max(1, parseInt(e.menge) || 1));
            if (e.typ === 'set') {
                const s = finden('s' + e.set);
                if (s) { this.eintraege.push(this.setEintrag(s, teileStart(s, e.teile || {}), menge, key)); }
            } else if (e.typ === 'artikel') {
                const a = katalog.find((a) => a.varianten.some((v) => String(v.id) === String(e.variante)));
                const v = variante(a, e.variante);
                if (v) { this.eintraege.push({ key: ++zaehler, mitglied: key, typ: 'artikel', artikel: a.id, variante: v.id, menge, label: a.name + groesse(v), detail: '', preis: v.preis }); }
            } else if (e.typ === 'frei' && e.nummer) {
                this.eintraege.push({ key: ++zaehler, mitglied: key, typ: 'frei', nummer: e.nummer, text: e.text || '', menge, label: 'Rüsthaus-Nr. ' + e.nummer, detail: [e.text, 'wird geprüft'].filter(Boolean).join(' · '), preis: null });
            }
        },
        ausPosition(p) {
            if (!p) { return; }
            let m = this.mitglieder.find((m) => m.name === (p.mitglied || '') && m.stufe === (p.stufe || ''));
            if (!m) { m = mitglied(p.mitglied || '', p.stufe || ''); this.mitglieder.push(m); }
            if (p.set) {
                this.ausGespeichert({ typ: 'set', set: p.set, teile: p.teile, menge: p.menge }, m.key);
            } else if (p.variante) {
                this.ausGespeichert({ typ: 'artikel', variante: p.variante, menge: p.menge }, m.key);
            } else if (p.frei_nummer) {
                this.ausGespeichert({ typ: 'frei', nummer: p.frei_nummer, text: p.frei_text, menge: p.menge }, m.key);
            }
        },

        // ---- Anderer Rüsthaus-Artikel ----
        async nachschlagen() {
            const f = this.frei;
            if (!f.nummer.trim()) { return; }
            Object.assign(f, { info: 'Wird beim Rüsthaus nachgeschlagen …', gesperrt: false, name: null });
            try {
                const r = await fetch(nachschlagenUrl + '?nummer=' + encodeURIComponent(f.nummer.trim()), { headers: { Accept: 'application/json' } });
                const d = await r.json();
                if (d.gesperrt) {
                    f.gesperrt = true;
                    f.info = 'Diesen Artikel bestellen wir nicht über die Sammelbestellung. Sprich bitte deine Leitung an.';
                } else if (d.name) {
                    // Bei der Nummer einer bestimmten Größe kommt genau diese Größe zurück.
                    const groesse = d.varianten && d.varianten.length === 1 && String(d.varianten[0].nummer).toLowerCase() === f.nummer.trim().toLowerCase()
                        ? d.varianten[0].bezeichnung : null;
                    f.name = d.name;
                    if (groesse && !f.text.trim()) { f.text = groesse; }
                    f.info = d.name + (groesse ? ', ' + groesse : '') + ' – ' + euro(d.preis_cent) + ' (vorläufig, wir prüfen den Preis)';
                } else {
                    f.info = 'Nicht gefunden. Trag ein, was du genau brauchst, wir prüfen es.';
                }
            } catch (e) {
                f.info = 'Nachschlagen klappt gerade nicht. Trag ein, was du genau brauchst.';
            }
        },
        freiHinzufuegen() {
            const f = this.frei;
            if (!f.nummer.trim() || f.gesperrt) { f.info = f.gesperrt ? f.info : 'Bitte eine Rüsthaus-Artikelnummer oder einen Link eintragen.'; return; }
            let key = this.sheet.mitglied;
            if (key === null || !this.mitglieder.some((m) => m.key === key)) {
                const m = this.mitglieder[0] || mitglied();
                if (!this.mitglieder.includes(m)) { this.mitglieder.push(m); }
                key = m.key;
            }
            this.eintraege.push({ key: ++zaehler, mitglied: key, typ: 'frei', nummer: f.nummer.trim(), text: f.text.trim(), menge: Math.min(10, Math.max(1, parseInt(f.menge) || 1)),
                label: f.name || 'Rüsthaus-Nr. ' + f.nummer.trim(), detail: [f.text.trim(), 'wird geprüft'].filter(Boolean).join(' · '), preis: null });
            this.melden('Im Warenkorb. Für wen, kannst du im Warenkorb noch ändern.');
            Object.assign(f, { nummer: '', text: '', menge: 1, info: '', gesperrt: false, name: null });
        },
        umhaengen(e, key) { e.mitglied = Number(key); },
    };
};
