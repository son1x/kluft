// Vor Ort (docs/vor-ort.md): Begleiter für die Durchführung von Veranstaltungen.
// Änderungen gehen als Ereignisse mit eigener Kennung an den Server; was
// (noch) nicht ankommt, bleibt in der Warteschlange und wird erneut geschickt.
import Alpine from 'alpinejs';

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

    bar(p) {
        const offen = p.zahlung?.offen_cent ?? 0;
        if (offen <= 0 || !window.confirm(p.vorname + ' ' + p.nachname + ': ' + euro(offen) + ' bar erhalten?')) {
            return;
        }
        p.zahlung = { ...p.zahlung, stand: 'bezahlt', offen_cent: 0 };
        this.ereignis(p, 'bar', { cent: offen });
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
        const paket = this.warteschlange.slice(0, 50);
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
        } catch (e) {
            // Später erneut.
        }
    },
}));

window.Alpine = Alpine;
Alpine.start();
