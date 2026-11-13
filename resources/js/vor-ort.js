// Vor Ort (docs/vor-ort.md): Begleiter für die Durchführung von Veranstaltungen.
// Änderungen gehen als Ereignisse mit eigener Kennung an den Server; was
// (noch) nicht ankommt, bleibt in der Warteschlange und wird erneut geschickt.
import Alpine from 'alpinejs';
import SignaturePad from 'signature_pad';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

async function holen(url) {
    const antwort = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    if (antwort.status === 401 || antwort.status === 419) {
        window.location.href = '/admin/login';
        throw new Error('abgemeldet');
    }
    if (!antwort.ok) {
        throw new Error('Fehler ' + antwort.status);
    }

    return antwort.json();
}

// Unterschriftsfeld außerhalb von Alpine halten (keine reaktive Hülle um das Canvas-Objekt).
let pad = null;

// Unterschrift auf den beschriebenen Bereich zuschneiden (kleineres Bild, passt in die Listenzeile).
function zuschneiden(canvas) {
    const ctx = canvas.getContext('2d');
    const { width, height } = canvas;
    const daten = ctx.getImageData(0, 0, width, height).data;
    let [x0, y0, x1, y1] = [width, height, -1, -1];
    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            if (daten[(y * width + x) * 4 + 3] > 0) {
                if (x < x0) x0 = x;
                if (x > x1) x1 = x;
                if (y < y0) y0 = y;
                if (y > y1) y1 = y;
            }
        }
    }
    if (x1 < 0) {
        return null;
    }
    const rand = 8;
    x0 = Math.max(0, x0 - rand); y0 = Math.max(0, y0 - rand);
    x1 = Math.min(width - 1, x1 + rand); y1 = Math.min(height - 1, y1 + rand);
    const w = x1 - x0 + 1;
    const h = y1 - y0 + 1;
    const faktor = Math.min(1, 1200 / w, 500 / h);
    const ziel = document.createElement('canvas');
    ziel.width = Math.max(20, Math.round(w * faktor));
    ziel.height = Math.max(10, Math.round(h * faktor));
    ziel.getContext('2d').drawImage(canvas, x0, y0, w, h, 0, 0, ziel.width, ziel.height);

    return ziel.toDataURL('image/png');
}

const euro = (cent) => (cent / 100).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' });

function kennung() {
    if (window.crypto?.randomUUID) {
        return window.crypto.randomUUID();
    }
    // Ältere Geräte: UUID v4 aus Zufallszahlen.
    const b = window.crypto.getRandomValues(new Uint8Array(16));
    b[6] = (b[6] & 0x0f) | 0x40;
    b[8] = (b[8] & 0x3f) | 0x80;
    const h = [...b].map((x) => x.toString(16).padStart(2, '0')).join('');

    return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
}

Alpine.data('vorOrt', () => ({
    person: '',
    veranstaltungen: [],
    v: null,
    laden: true,
    fehler: '',
    tag: null,
    suche: '',
    filter: 'alle',
    gruppe: '',
    auswahlId: null,
    bearbeiten: null,
    warteschlange: [],
    sendet: false,
    online: navigator.onLine,
    meldung: '',
    nachSenden: false,

    async init() {
        window.addEventListener('online', () => { this.online = true; this.senden(); });
        window.addEventListener('offline', () => { this.online = false; });
        window.addEventListener('popstate', () => this.ausAdresse());
        setInterval(() => this.senden(), 15000);
        await this.listeLaden();
        await this.ausAdresse();
    },

    async ausAdresse() {
        const id = Number(new URLSearchParams(window.location.search).get('v'));
        if (id) {
            await this.oeffnen(id, false);
        } else {
            this.v = null;
        }
    },

    async listeLaden() {
        this.laden = true;
        this.fehler = '';
        try {
            const d = await holen('/vor-ort/api/veranstaltungen');
            this.person = d.person;
            this.veranstaltungen = d.veranstaltungen;
        } catch (e) {
            this.fehler = 'Die Veranstaltungen ließen sich nicht laden. Bitte Verbindung prüfen.';
        }
        this.laden = false;
    },

    async oeffnen(id, merken = true) {
        this.laden = true;
        this.fehler = '';
        try {
            this.v = await holen('/vor-ort/api/veranstaltungen/' + id);
            const tage = this.v.tage.map((t) => t.tag);
            this.tag = tage.includes(this.v.heute) ? this.v.heute : tage[0];
            this.auswahlId = null;
            this.bearbeiten = null;
            this.kassieren = null;
            if (!this.v.zusatz_felder.some((f) => f.schluessel === this.gruppe)) {
                this.gruppe = '';
            }
            if (merken) {
                history.pushState({}, '', '?v=' + id);
            }
        } catch (e) {
            this.fehler = 'Die Veranstaltung ließ sich nicht laden.';
        }
        this.laden = false;
    },

    zurueck() {
        history.pushState({}, '', window.location.pathname);
        this.v = null;
        this.listeLaden();
    },

    get auswahl() {
        return this.v?.personen.find((p) => p.id === this.auswahlId) ?? null;
    },

    get tagLabel() {
        return this.v?.tage.find((t) => t.tag === this.tag)?.label ?? '';
    },

    get gefiltert() {
        if (!this.v) {
            return [];
        }
        const s = this.suche.trim().toLowerCase();

        return this.v.personen.filter((p) => {
            if (s && !(p.vorname + ' ' + p.nachname + ' ' + Object.values(p.zusatz).join(' ')).toLowerCase().includes(s)) {
                return false;
            }
            if (this.filter === 'fehlt') {
                return this.erwartet(p) && !this.istDa(p);
            }
            if (this.filter === 'unterschrift') {
                return !p.unterschrieben;
            }
            if (this.filter === 'offen') {
                return p.zahlung && p.zahlung.offen_cent > 0;
            }

            return true;
        });
    },

    // Liste, nach einer Zusatzinfo (z. B. Zimmer) gruppiert oder als eine Gruppe.
    get gruppen() {
        if (!this.gruppe) {
            return [{ wert: '', personen: this.gefiltert }];
        }
        const map = new Map();
        this.gefiltert.forEach((p) => {
            const w = p.zusatz[this.gruppe] ?? '';
            map.set(w, [...(map.get(w) ?? []), p]);
        });

        return [...map.entries()]
            .sort(([a], [b]) => (a === '' ? 1 : b === '' ? -1 : a.localeCompare(b, 'de', { numeric: true })))
            .map(([wert, personen]) => ({ wert, personen }));
    },

    gruppenTitel(g) {
        const label = this.v.zusatz_felder.find((f) => f.schluessel === this.gruppe)?.label ?? '';

        return g.wert === '' ? 'Ohne ' + label : label + ' ' + g.wert + ' · ' + g.personen.length;
    },

    // Wer den gleichen Wert hat (z. B. im selben Zimmer).
    gemeinsam(p, feld) {
        const w = p.zusatz[feld];
        if (!w) {
            return [];
        }

        return this.v.personen.filter((x) => x.id !== p.id && x.zusatz[feld] === w).map((x) => x.vorname + ' ' + x.nachname);
    },

    zusatzSetzen(p, feld, wert) {
        wert = wert.trim();
        if ((p.zusatz[feld] ?? '') === wert) {
            return;
        }
        const z = { ...p.zusatz };
        if (wert === '') {
            delete z[feld];
        } else {
            z[feld] = wert;
        }
        p.zusatz = z;
        this.ereignis(p, 'zusatz', { feld, wert });
    },

    zusatzText(p) {
        return this.v.zusatz_felder.filter((f) => p.zusatz[f.schluessel]).map((f) => f.label + ' ' + p.zusatz[f.schluessel]);
    },

    erwartet(p, tag = this.tag) {
        return p.erwartet.includes(tag);
    },

    istDa(p, tag = this.tag) {
        return p.da.includes(tag);
    },

    get zahlen() {
        const p = this.v?.personen ?? [];

        return {
            unterschrieben: p.filter((x) => x.unterschrieben).length,
            da: p.filter((x) => this.istDa(x)).length,
            erwartet: p.filter((x) => this.erwartet(x)).length,
            offen: p.filter((x) => x.zahlung && x.zahlung.offen_cent > 0).length,
        };
    },

    zahlungText(p) {
        if (!p.zahlung) {
            return '';
        }

        return { bezahlt: 'bezahlt', teilweise: 'teilweise · offen ' + euro(p.zahlung.offen_cent), offen: 'offen · ' + euro(p.zahlung.offen_cent), erstattet: 'erstattet' }[p.zahlung.stand] ?? p.zahlung.stand;
    },

    zahlungFarbe(p) {
        return p.zahlung?.stand === 'bezahlt' ? 'bg-green-100 text-green-900' : (p.zahlung?.offen_cent > 0 ? 'bg-red-100 text-red-900' : 'bg-gray-100 text-gray-700');
    },

    euro,

    waehlen(p) {
        this.auswahlId = p.id;
        this.bearbeiten = null;
        this.kassieren = null;
    },

    // Ereignis lokal anwenden und in die Warteschlange legen.
    ereignis(p, typ, daten) {
        this.warteschlange.push({ id: kennung(), typ, anmeldung: p.id, daten, zeit: new Date().toISOString(), name: p.vorname + ' ' + p.nachname });
        this.senden();
    },

    anwesend(p, tag = this.tag) {
        const da = !this.istDa(p, tag);
        p.da = da ? [...p.da, tag] : p.da.filter((t) => t !== tag);
        this.ereignis(p, 'anwesend', { tag, da });
    },

    // Unterschrift (Stufe 2): die teilnehmende Person unterschreibt auf dem Gerät.
    unterschreiben: null,

    unterschreibenStarten(p) {
        this.unterschreiben = { id: p.id, name: p.vorname + ' ' + p.nachname };
        this.$nextTick(() => {
            const canvas = this.$refs.unterschriftFeld;
            const dpr = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * dpr;
            canvas.height = canvas.offsetHeight * dpr;
            canvas.getContext('2d').scale(dpr, dpr);
            pad?.off();
            pad = new SignaturePad(canvas, { penColor: '#111827', minWidth: 0.8, maxWidth: 2.6 });
        });
    },

    unterschriftLoeschen() {
        pad?.clear();
    },

    unterschriftAbbrechen() {
        pad?.off();
        pad = null;
        this.unterschreiben = null;
    },

    unterschriftFertig() {
        const p = this.v.personen.find((x) => x.id === this.unterschreiben?.id);
        if (!p || !pad || pad.isEmpty()) {
            this.meldung = 'Bitte zuerst im Feld unterschreiben.';

            return;
        }
        const bild = zuschneiden(this.$refs.unterschriftFeld);
        if (!bild) {
            this.meldung = 'Bitte zuerst im Feld unterschreiben.';

            return;
        }
        p.unterschrieben = new Date().toISOString();
        this.ereignis(p, 'unterschrift', { bild });
        this.unterschriftAbbrechen();
        this.meldung = 'Unterschrift von ' + p.vorname + ' ' + p.nachname + ' gespeichert.';
    },

    // Nachmelden: Person ist da, war aber nicht angemeldet. Lokal sofort sichtbar (Kennung „neu:…“),
    // der Server legt die Anmeldung beim Senden an; Folge-Ereignisse verweisen auf dieselbe Kennung.
    nachmeldung: null,

    nachmeldenStarten() {
        const werte = {};
        this.v.felder.forEach((f) => {
            werte[f.schluessel] = ['days', 'mehrfach'].includes(f.typ) ? [] : '';
        });
        this.nachmeldung = { vorname: '', nachname: '', werte, preis: this.v.preise.length === 1 ? '0' : '', einverstanden: false };
    },

    wertText(f, wert) {
        if (Array.isArray(wert)) {
            return wert.map((w) => f.optionen?.[w] ?? w).join(', ');
        }

        return f.optionen ? (f.optionen[wert] ?? wert) : wert;
    },

    nachmeldenSpeichern() {
        const n = this.nachmeldung;
        const vorname = n.vorname.trim();
        const nachname = n.nachname.trim();
        if (!vorname || !nachname) {
            this.meldung = 'Vor- und Nachname dürfen nicht leer sein.';

            return;
        }
        if (this.v.preise.length && n.preis === '') {
            this.meldung = 'Bitte einen Teilnahmebeitrag wählen.';

            return;
        }
        const fehlt = this.v.felder.find((f) => f.pflicht && f.typ !== 'days' && (n.werte[f.schluessel] === '' || n.werte[f.schluessel] == null || n.werte[f.schluessel]?.length === 0));
        if (fehlt) {
            this.meldung = fehlt.label + ' ist ein Pflichtfeld.';

            return;
        }
        const data = {};
        this.v.felder.forEach((f) => {
            const w = n.werte[f.schluessel];
            if (w !== '' && w != null && !(Array.isArray(w) && w.length === 0)) {
                data[f.schluessel] = w;
            }
        });
        const preis = this.v.preise.find((p) => String(p.nr) === n.preis) ?? null;
        const uuid = kennung();
        const alleTage = this.v.tage.map((t) => t.tag);
        const person = {
            id: 'neu:' + uuid,
            vorname,
            nachname,
            jahrgang: data.birth_year ?? null,
            erwartet: Array.isArray(data.tage) && data.tage.length ? data.tage : alleTage,
            da: [],
            zusatz: {},
            unterschrieben: null,
            zahlung: preis ? { stand: 'offen', offen_cent: preis.cent, betrag_cent: preis.cent } : null,
            werte: { ...n.werte },
            angaben: this.v.felder.filter((f) => data[f.schluessel] !== undefined)
                .map((f) => ({ schluessel: f.schluessel, label: f.label, sensibel: f.sensibel, wert: this.wertText(f, data[f.schluessel]) })),
        };
        this.v.personen = [...this.v.personen, person].sort((a, b) => (a.nachname + a.vorname).localeCompare(b.nachname + b.vorname, 'de'));
        this.warteschlange.push({
            id: uuid, typ: 'nachmeldung', event: this.v.id, zeit: new Date().toISOString(), name: vorname + ' ' + nachname,
            daten: { first_name: vorname, last_name: nachname, data, preis: preis ? preis.nr : null, einverstanden: true },
        });
        this.nachSenden = true;
        if (alleTage.includes(this.tag)) {
            this.anwesend(person);
        }
        this.nachmeldung = null;
        this.auswahlId = person.id;
        this.meldung = vorname + ' ' + nachname + ' nachgemeldet' + (this.online ? '.' : ' – wird gesendet, sobald wieder Netz da ist.');
        this.senden();
    },

    // Kassieren: Zahlungsart wählen (bar, Karte, PayPal …), dann bestätigen.
    kassieren: null,

    kassierenStarten(p) {
        if ((p.zahlung?.offen_cent ?? 0) > 0) {
            this.kassieren = { id: p.id };
        }
    },

    kassierenMit(p, weg) {
        const offen = p.zahlung?.offen_cent ?? 0;
        this.kassieren = null;
        if (offen <= 0) {
            return;
        }
        p.zahlung = { ...p.zahlung, stand: 'bezahlt', offen_cent: 0 };
        this.ereignis(p, 'zahlung', { cent: offen, weg });
        this.meldung = p.vorname + ' ' + p.nachname + ': ' + euro(offen) + ' (' + this.v.zahlwege[weg] + ') verbucht.';
    },

    bearbeitenStarten(p) {
        const werte = JSON.parse(JSON.stringify(p.werte));
        this.v.felder.forEach((f) => {
            if (['days', 'mehrfach'].includes(f.typ) && !Array.isArray(werte[f.schluessel])) {
                werte[f.schluessel] = [];
            }
        });
        this.bearbeiten = { vorname: p.vorname, nachname: p.nachname, werte };
    },

    bearbeitenSpeichern(p) {
        const b = this.bearbeiten;
        if (!b.vorname.trim() || !b.nachname.trim()) {
            this.meldung = 'Vor- und Nachname dürfen nicht leer sein.';

            return;
        }
        const data = {};
        this.v.felder.forEach((f) => {
            if (JSON.stringify(b.werte[f.schluessel] ?? null) !== JSON.stringify(p.werte[f.schluessel] ?? null)) {
                data[f.schluessel] = b.werte[f.schluessel];
            }
        });
        const daten = { data };
        if (b.vorname !== p.vorname) daten.first_name = b.vorname.trim();
        if (b.nachname !== p.nachname) daten.last_name = b.nachname.trim();
        if (Object.keys(data).length === 0 && !daten.first_name && !daten.last_name) {
            this.bearbeiten = null;

            return;
        }
        p.vorname = b.vorname.trim();
        p.nachname = b.nachname.trim();
        p.werte = { ...p.werte, ...data };
        this.ereignis(p, 'angaben', daten);
        this.bearbeiten = null;
        this.meldung = 'Gespeichert.';
        // Lesbare Angaben vom Server neu holen, sobald alles angekommen ist.
        this.nachSenden = true;
    },

    async senden() {
        if (this.sendet || this.warteschlange.length === 0 || !navigator.onLine) {
            return;
        }
        this.sendet = true;
        // Unterschriften sind größer: kleinere Pakete.
        const paket = this.warteschlange.slice(0, 20);
        try {
            const antwort = await fetch('/vor-ort/api/ereignisse', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ ereignisse: paket.map(({ name, ...e }) => e) }),
            });
            if (antwort.status === 401 || antwort.status === 419) {
                this.meldung = 'Abgemeldet – bitte neu anmelden. ' + this.warteschlange.length + ' Änderung(en) warten.';
                this.sendet = false;

                return;
            }
            if (!antwort.ok) {
                throw new Error('Fehler ' + antwort.status);
            }
            const { ergebnisse } = await antwort.json();
            const abgelehnt = paket.filter((e) => ergebnisse[e.id]?.ergebnis === 'abgelehnt');
            this.warteschlange = this.warteschlange.filter((e) => !ergebnisse[e.id]);
            if (abgelehnt.length) {
                this.meldung = abgelehnt.map((e) => e.name + ': ' + ergebnisse[e.id].grund).join(' · ');
                this.nachSenden = true;
            }
            if (this.nachSenden && this.warteschlange.length === 0) {
                this.nachSenden = false;
                await this.neuLaden();
            }
        } catch (e) {
            // Bleibt in der Warteschlange; nächster Versuch automatisch.
        }
        this.sendet = false;
        if (this.warteschlange.length) {
            setTimeout(() => this.senden(), 3000);
        }
    },

    async neuLaden() {
        if (!this.v) {
            return;
        }
        try {
            const frisch = await holen('/vor-ort/api/veranstaltungen/' + this.v.id);
            this.v = { ...frisch };
            if (this.auswahlId !== null && !this.v.personen.some((p) => p.id === this.auswahlId)) {
                this.auswahlId = null;
            }
        } catch (e) {
            // Später erneut.
        }
    },
}));

window.Alpine = Alpine;
Alpine.start();
