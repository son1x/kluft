// Vor Ort (docs/vor-ort.md): Begleiter für die Durchführung von Veranstaltungen.
// Änderungen gehen als Ereignisse mit eigener Kennung an den Server; was
// (noch) nicht ankommt, bleibt in der Warteschlange und wird erneut geschickt.
import Alpine from 'alpinejs';
import SignaturePad from 'signature_pad';
import { IdbSpeicher, Tresor } from './vor-ort-tresor';

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

// Gerätespeicher (Stufe 3/4): verschlüsselt, Schlüssel nur im Arbeitsspeicher; außerhalb von Alpine halten.
const tresor = new Tresor(new IdbSpeicher());

const LEERLAUF_MS = 10 * 60 * 1000;

// Kopie für das Gerät: ohne sensible Angaben, wenn sie nicht ausdrücklich freigegeben wurden.
function fuerGeraet(v, mitSensibel) {
    const kopie = JSON.parse(JSON.stringify(v));
    if (!mitSensibel) {
        const sensibel = new Set(kopie.felder.filter((f) => f.sensibel).map((f) => f.schluessel));
        kopie.felder = kopie.felder.filter((f) => !sensibel.has(f.schluessel));
        kopie.personen.forEach((p) => {
            p.angaben = p.angaben.filter((a) => !a.sensibel);
            sensibel.forEach((k) => delete p.werte[k]);
        });
    }

    return kopie;
}

// Programmdateien für den Start ohne Netz bereitstellen (Service Worker, Stufe 3). Daten nie.
async function appBereitstellen() {
    if (!('serviceWorker' in navigator)) {
        return;
    }
    try {
        await navigator.serviceWorker.register('/vor-ort/sw.js', { scope: '/vor-ort/' });
        await navigator.serviceWorker.ready;
        const cache = await caches.open('vor-ort-v1');
        const urls = performance.getEntriesByType('resource').map((r) => new URL(r.name)).filter((u) => u.origin === location.origin && u.pathname.startsWith('/build/assets/'));
        await cache.addAll([...new Set(urls.map((u) => u.href))]);
        const seite = await fetch('/vor-ort', { credentials: 'same-origin' });
        if (seite.ok && !seite.redirected) {
            await cache.put('/vor-ort', seite);
        }
    } catch (e) {
        // Ohne Service Worker läuft alles online weiter.
    }
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

    // Offline (Stufe 3/4)
    tresorStatus: 'unbekannt', // keiner | gesperrt | offen
    offlineVe: [],
    nurOffline: false,
    lokal: false,
    imGeraet: false,
    mitSensibel: false,
    codeDialog: null,
    codeFeld: '',
    codeWiederholung: '',
    codeFehler: '',
    ausstehendGeraet: 0,
    sichernTimer: null,
    leerlaufTimer: null,

    async init() {
        window.addEventListener('online', () => { this.online = true; this.senden(); });
        window.addEventListener('offline', () => { this.online = false; });
        window.addEventListener('popstate', () => this.ausAdresse());
        setInterval(() => this.senden(), 15000);
        window.addEventListener('load', () => setTimeout(appBereitstellen, 2000));
        ['pointerdown', 'keydown'].forEach((e) => window.addEventListener(e, () => this.leerlaufNeu(), { passive: true }));
        this.$watch('warteschlange', () => this.sichernSpaeter());
        this.$watch('v', () => this.sichernSpaeter());
        setInterval(() => this.aufraeumen(), 60 * 60 * 1000);
        await this.tresorPruefen();
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
            this.nurOffline = false;
        } catch (e) {
            this.nurOffline = true;
            this.fehler = this.offlineVe.length ? '' : 'Die Veranstaltungen ließen sich nicht laden. Bitte Verbindung prüfen.';
        }
        this.laden = false;
    },

    async oeffnen(id, merken = true) {
        this.laden = true;
        this.fehler = '';
        try {
            this.v = await holen('/vor-ort/api/veranstaltungen/' + id);
            this.lokal = false;
            const kopie = this.offlineVe.find((x) => x.id === id);
            this.imGeraet = !!kopie && tresor.offen;
            this.mitSensibel = !!kopie?.sensibel;
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
            // Ohne Netz: Kopie vom Gerät, falls vorhanden.
            if (this.offlineVe.some((x) => x.id === id)) {
                await this.lokalOeffnen(id, merken);
            } else {
                this.fehler = 'Die Veranstaltung ließ sich nicht laden.';
            }
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
        this.warteschlange.push({ id: kennung(), typ, anmeldung: p.id, daten, zeit: new Date().toISOString(), name: p.vorname + ' ' + p.nachname, veranstaltung: this.v.id });
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
        this.fehltEingabe = {};
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
        this.fehltSpeichern(p, false);
        p.unterschrieben = new Date().toISOString();
        this.ereignis(p, 'unterschrift', { bild });
        this.unterschriftAbbrechen();
        this.meldung = 'Unterschrift von ' + p.vorname + ' ' + p.nachname + ' gespeichert.'
            + (p.fehlt?.length ? ' Es fehlt noch: ' + this.fehltDefs(p).map((f) => f.label).join(', ') + '.' : '');
    },

    // Nachmelden: Person ist da, war aber nicht angemeldet. Lokal sofort sichtbar (Kennung „neu:…“),
    // der Server legt die Anmeldung beim Senden an; Folge-Ereignisse verweisen auf dieselbe Kennung.
    nachmeldung: null,
    fehltEingabe: {},

    nachmeldenStarten() {
        const werte = {};
        this.v.felder.forEach((f) => {
            werte[f.schluessel] = ['days', 'mehrfach'].includes(f.typ) ? [] : '';
        });
        this.nachmeldung = { vorname: '', nachname: '', werte, preis: this.v.preise.length === 1 ? '0' : '', einverstanden: false };
    },

    // Fehlende Angaben (Pflichtangaben der Teilnahmeliste und Pflichtfelder): direkt nachtragen.
    fehltDefs(p) {
        const alle = [...(this.v?.felder ?? []), ...(this.v?.liste_felder ?? [])];

        return (p?.fehlt ?? []).map((k) => alle.find((f) => f.schluessel === k)).filter(Boolean);
    },

    get unterschreibenPerson() {
        return this.v?.personen.find((x) => x.id === this.unterschreiben?.id) ?? null;
    },

    // Eingetragenes als Ereignis „angaben“ senden. Gibt zurück, ob etwas eingetragen wurde.
    fehltSpeichern(p, melden = true) {
        const data = {};
        this.fehltDefs(p).forEach((f) => {
            const w = String(this.fehltEingabe[f.schluessel] ?? '').trim();
            if (w !== '') {
                data[f.schluessel] = w;
            }
        });
        if (Object.keys(data).length === 0) {
            return false;
        }
        p.werte = { ...p.werte, ...data };
        p.fehlt = p.fehlt.filter((k) => !(k in data));
        if (data.birth_year) {
            p.jahrgang = Number(data.birth_year);
        }
        this.ereignis(p, 'angaben', { data });
        this.fehltEingabe = {};
        this.nachSenden = true;
        if (melden) {
            this.meldung = 'Nachgetragen für ' + p.vorname + ' ' + p.nachname + (p.fehlt.length ? ' – es fehlt noch: ' + this.fehltDefs(p).map((f) => f.label).join(', ') + '.' : '.');
        }

        return true;
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
            id: uuid, typ: 'nachmeldung', event: this.v.id, zeit: new Date().toISOString(), name: vorname + ' ' + nachname, veranstaltung: this.v.id,
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

    // ---- Offline: Gerätespeicher (Stufe 3 und 4) -------------------------------------------------

    async tresorPruefen() {
        this.tresorStatus = (await tresor.vorhanden()) ? 'gesperrt' : 'keiner';
        await this.offlineListeLaden();
        this.ausstehendGeraet = this.tresorStatus === 'gesperrt' ? await tresor.ausstehend() : 0;
        if (this.ausstehendGeraet > 0) {
            this.codeFrage('nichts');
        }
        await this.aufraeumen();
    },

    async offlineListeLaden() {
        this.offlineVe = this.tresorStatus === 'keiner' ? [] : await tresor.veranstaltungen();
    },

    // Code abfragen (erster Start: festlegen; sonst entsperren). `danach` = was nach dem Entsperren geschieht.
    codeFrage(danach = 'nichts', id = null) {
        this.codeFeld = '';
        this.codeWiederholung = '';
        this.codeFehler = '';
        this.codeDialog = { art: this.tresorStatus === 'keiner' ? 'neu' : 'entsperren', danach, id };
    },

    async codeBestaetigen() {
        const d = this.codeDialog;
        this.codeFehler = '';
        if (d.art === 'neu') {
            if (this.codeFeld.length < 6) {
                this.codeFehler = 'Der Code braucht mindestens 6 Zeichen.';

                return;
            }
            if (this.codeFeld !== this.codeWiederholung) {
                this.codeFehler = 'Die beiden Eingaben passen nicht zusammen.';

                return;
            }
            await tresor.anlegen(this.codeFeld);
        } else {
            const r = await tresor.entsperren(this.codeFeld);
            if (r.geloescht) {
                this.tresorStatus = 'keiner';
                this.offlineVe = [];
                this.codeDialog = null;
                this.meldung = 'Zu viele falsche Codes: Alle Offline-Daten auf diesem Gerät wurden gelöscht.';

                return;
            }
            if (!r.ok) {
                this.codeFehler = 'Falscher Code. Noch ' + r.rest + ' Versuch(e), danach werden die Offline-Daten gelöscht.';

                return;
            }
        }
        this.codeFeld = '';
        this.codeWiederholung = '';
        this.codeDialog = null;
        this.tresorStatus = 'offen';
        this.leerlaufNeu();
        // Ungesendetes vom letzten Mal wieder aufnehmen und senden.
        const gespeichert = await tresor.warteschlangeLaden();
        this.warteschlange = [...gespeichert, ...this.warteschlange.filter((e) => !gespeichert.some((g) => g.id === e.id))];
        this.ausstehendGeraet = 0;
        await this.aufraeumen();
        this.nachSenden = this.warteschlange.length > 0;
        this.senden();
        if (d.danach === 'speichern') {
            await this.offlineSpeichern(this.mitSensibel);
        } else if (d.danach === 'oeffnen' && d.id) {
            if (!this.v) {
                await this.oeffnen(d.id);
            }
        } else if (d.danach === 'lokal' && d.id) {
            await this.lokalOeffnen(d.id);
        }
    },

    async lokalOeffnen(id, merken = true) {
        if (!tresor.offen) {
            this.codeFrage('lokal', id);

            return;
        }
        const d = await tresor.laden(id);
        if (!d) {
            this.fehler = 'Zu dieser Veranstaltung gibt es keine Offline-Daten.';

            return;
        }
        this.v = d;
        this.lokal = true;
        this.imGeraet = true;
        this.mitSensibel = !!this.offlineVe.find((x) => x.id === id)?.sensibel;
        const tage = d.tage.map((t) => t.tag);
        this.tag = tage.includes(d.heute) ? d.heute : tage[0];
        this.auswahlId = null;
        this.fehler = '';
        if (merken) {
            history.pushState({}, '', '?v=' + id);
        }
    },

    async offlineSpeichern(mitSensibel = false) {
        if (!this.v) {
            return;
        }
        this.mitSensibel = mitSensibel;
        if (!tresor.offen) {
            this.codeFrage('speichern');

            return;
        }
        this.imGeraet = true;
        await this.sichern();
        await this.offlineListeLaden();
        this.meldung = 'Für die Nutzung ohne Netz auf diesem Gerät gespeichert (verschlüsselt'
            + (mitSensibel ? ', mit sensiblen Angaben' : ', ohne sensible Angaben') + ').';
    },

    sichernSpaeter() {
        if (!tresor.offen) {
            return;
        }
        clearTimeout(this.sichernTimer);
        this.sichernTimer = setTimeout(() => this.sichern(), 800);
    },

    async sichern() {
        if (!tresor.offen) {
            return;
        }
        try {
            if (this.v && this.imGeraet) {
                await tresor.speichern(this.v.id, {
                    titel: this.v.titel, gespeichertAm: new Date().toISOString(), loeschenAm: this.v.loeschen_am, sensibel: this.mitSensibel,
                }, fuerGeraet(this.v, this.mitSensibel));
            }
            await tresor.warteschlangeSpeichern(JSON.parse(JSON.stringify(this.warteschlange)));
            await this.offlineListeLaden();
        } catch (e) {
            this.meldung = 'Das Speichern auf dem Gerät hat nicht geklappt.';
        }
    },

    // Abgelaufene Offline-Daten löschen (Frist: 7 Tage nach dem Ende der Veranstaltung).
    async aufraeumen() {
        if (this.tresorStatus === 'keiner') {
            return;
        }
        const r = await tresor.aufraeumen();
        if (r.geloescht.length) {
            this.meldung = 'Offline-Daten gelöscht (Frist abgelaufen): ' + r.geloescht.map((g) => g.titel).join(', ')
                + (r.verworfen ? ' – ' + r.verworfen + ' nicht gesendete Änderung(en) verworfen.' : '.');
            const weg = new Set(r.geloescht.map((g) => g.id));
            this.warteschlange = this.warteschlange.filter((e) => !weg.has(e.veranstaltung));
            if (this.v && weg.has(this.v.id)) {
                this.imGeraet = false;
            }
        }
        await this.offlineListeLaden();
    },

    // Sperren: Schlüssel vergessen; Daten bleiben verschlüsselt, Ungesendetes ebenfalls.
    async sperren() {
        if (!tresor.offen) {
            return;
        }
        clearTimeout(this.sichernTimer);
        await this.sichern();
        tresor.sperren();
        this.tresorStatus = 'gesperrt';
        this.ausstehendGeraet = this.warteschlange.length;
        this.warteschlange = [];
        this.v = null;
        this.auswahlId = null;
        this.unterschriftAbbrechen();
        history.pushState({}, '', window.location.pathname);
        this.codeFrage('oeffnen');
        this.codeDialog.id = null;
    },

    leerlaufNeu() {
        clearTimeout(this.leerlaufTimer);
        if (tresor.offen) {
            this.leerlaufTimer = setTimeout(() => this.sperren(), LEERLAUF_MS);
        }
    },

    async geraetedatenLoeschen() {
        if (!window.confirm('Alle Offline-Daten auf diesem Gerät löschen?' + (this.warteschlange.length ? ' Noch nicht gesendete Änderungen gehen verloren, wenn die App geschlossen wird.' : ''))) {
            return;
        }
        clearTimeout(this.sichernTimer);
        await tresor.loeschenAlles();
        this.tresorStatus = 'keiner';
        this.offlineVe = [];
        this.imGeraet = false;
        this.ausstehendGeraet = 0;
        this.meldung = 'Alle Offline-Daten auf diesem Gerät sind gelöscht.';
    },

    async abmelden() {
        const offen = this.warteschlange.length + this.ausstehendGeraet;
        if (offen > 0 && !window.confirm(offen + ' Änderung(en) wurden noch nicht gesendet und gehen beim Abmelden verloren. Trotzdem abmelden?')) {
            return;
        }
        clearTimeout(this.sichernTimer);
        await tresor.loeschenAlles();
        try {
            await fetch('/admin/logout', { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': csrf() } });
        } catch (e) {
            // Ohne Netz bleibt die Anmeldung bestehen; die Gerätedaten sind trotzdem gelöscht.
        }
        window.location.href = '/admin/login';
    },

    get standText() {
        return this.v?.stand ? new Date(this.v.stand).toLocaleString('de-DE', { dateStyle: 'short', timeStyle: 'short' }) : '';
    },

    get ablaufText() {
        return this.v?.loeschen_am ? new Date(this.v.loeschen_am).toLocaleDateString('de-DE') : '';
    },

    kopieText(k) {
        return new Date(k.gespeichertAm).toLocaleString('de-DE', { dateStyle: 'short', timeStyle: 'short' });
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
                body: JSON.stringify({ ereignisse: paket.map(({ name, veranstaltung, ...e }) => e) }),
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
            this.lokal = false;
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
