// Vor Ort (docs/vor-ort.md, Stufe 3 und 4): verschlüsselter Gerätespeicher.
// Alles, was offline gebraucht wird (Veranstaltung mit Teilnehmenden, Warteschlange), liegt nur
// verschlüsselt auf dem Gerät: AES-GCM 256, Schlüssel aus einem Gerätecode (PBKDF2-SHA-256).
// Der Schlüssel bleibt nur im Arbeitsspeicher; wer die App schließt, muss den Code neu eingeben.
// Zu viele falsche Codes löschen alles. Offene Angaben (Titel, Löschzeitpunkt) sind keine Personendaten.

export const ITERATIONEN = 600000;
export const MAX_FEHLVERSUCHE = 10;
const PRUEFTEXT = 'vor-ort-tresor-1';

const kodieren = new TextEncoder();
const dekodieren = new TextDecoder();

export async function schluesselAbleiten(code, salz, iterationen = ITERATIONEN) {
    const roh = await crypto.subtle.importKey('raw', kodieren.encode(code), 'PBKDF2', false, ['deriveKey']);

    return crypto.subtle.deriveKey(
        { name: 'PBKDF2', salt: salz, iterations: iterationen, hash: 'SHA-256' },
        roh,
        { name: 'AES-GCM', length: 256 },
        false,
        ['encrypt', 'decrypt'],
    );
}

export async function verschluesseln(schluessel, objekt) {
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const daten = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, schluessel, kodieren.encode(JSON.stringify(objekt)));

    return { iv, daten: new Uint8Array(daten) };
}

export async function entschluesseln(schluessel, blob) {
    const klar = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: blob.iv }, schluessel, blob.daten);

    return JSON.parse(dekodieren.decode(klar));
}

/** Speicher im Arbeitsspeicher (Tests); gleiche Schnittstelle wie IdbSpeicher. */
export class MemSpeicher {
    constructor() {
        this.daten = { meta: new Map(), ve: new Map(), queue: new Map() };
    }

    async lesen(store, key) {
        const w = this.daten[store].get(key);

        return w === undefined ? undefined : structuredClone(w);
    }

    async schreiben(store, key, wert) {
        this.daten[store].set(key, structuredClone(wert));
    }

    async entfernen(store, key) {
        this.daten[store].delete(key);
    }

    async alle(store) {
        return [...this.daten[store].values()].map((w) => structuredClone(w));
    }

    async leeren() {
        Object.values(this.daten).forEach((m) => m.clear());
    }
}

/** Speicher in IndexedDB des Browsers. */
export class IdbSpeicher {
    constructor(name = 'vor-ort', fabrik = globalThis.indexedDB) {
        this.name = name;
        this.fabrik = fabrik;
        this.db = null;
    }

    oeffnen() {
        if (this.db) {
            return Promise.resolve(this.db);
        }

        return new Promise((ok, fehler) => {
            const anfrage = this.fabrik.open(this.name, 1);
            anfrage.onupgradeneeded = () => {
                ['meta', 've', 'queue'].forEach((s) => anfrage.result.createObjectStore(s));
            };
            anfrage.onsuccess = () => {
                this.db = anfrage.result;
                ok(this.db);
            };
            anfrage.onerror = () => fehler(anfrage.error);
        });
    }

    async lauf(store, modus, aktion) {
        const db = await this.oeffnen();

        return new Promise((ok, fehler) => {
            const t = db.transaction(store, modus);
            const anfrage = aktion(t.objectStore(store));
            t.oncomplete = () => ok(anfrage.result);
            t.onerror = () => fehler(t.error);
            t.onabort = () => fehler(t.error);
        });
    }

    lesen(store, key) {
        return this.lauf(store, 'readonly', (s) => s.get(key));
    }

    schreiben(store, key, wert) {
        return this.lauf(store, 'readwrite', (s) => s.put(wert, key));
    }

    entfernen(store, key) {
        return this.lauf(store, 'readwrite', (s) => s.delete(key));
    }

    alle(store) {
        return this.lauf(store, 'readonly', (s) => s.getAll());
    }

    leeren() {
        this.db?.close();
        this.db = null;

        return new Promise((ok, fehler) => {
            const anfrage = this.fabrik.deleteDatabase(this.name);
            anfrage.onsuccess = () => ok();
            anfrage.onerror = () => fehler(anfrage.error);
            anfrage.onblocked = () => ok();
        });
    }
}

export class Tresor {
    constructor(speicher, { iterationen = ITERATIONEN, jetzt = () => Date.now() } = {}) {
        this.speicher = speicher;
        this.iterationen = iterationen;
        this.jetzt = jetzt;
        this.schluessel = null;
    }

    get offen() {
        return this.schluessel !== null;
    }

    async vorhanden() {
        return (await this.speicher.lesen('meta', 'tresor')) !== undefined;
    }

    /** Erster Start auf dem Gerät: Code festlegen. */
    async anlegen(code) {
        if (await this.vorhanden()) {
            throw new Error('Es gibt schon einen Gerätespeicher.');
        }
        if (typeof code !== 'string' || code.length < 6) {
            throw new Error('Der Code braucht mindestens 6 Zeichen.');
        }
        const salz = crypto.getRandomValues(new Uint8Array(16));
        const schluessel = await schluesselAbleiten(code, salz, this.iterationen);
        await this.speicher.schreiben('meta', 'tresor', {
            salz, iterationen: this.iterationen, pruefung: await verschluesseln(schluessel, PRUEFTEXT), fehlversuche: 0, angelegt: this.jetzt(),
        });
        this.schluessel = schluessel;
    }

    /** @returns {Promise<{ok: boolean, rest?: number, geloescht?: boolean}>} */
    async entsperren(code) {
        const meta = await this.speicher.lesen('meta', 'tresor');
        if (!meta) {
            return { ok: false };
        }
        // Zählen, bevor geprüft wird: Abbruch mitten im Versuch hilft nicht beim Raten.
        meta.fehlversuche += 1;
        await this.speicher.schreiben('meta', 'tresor', meta);
        try {
            const schluessel = await schluesselAbleiten(String(code), meta.salz, meta.iterationen);
            if ((await entschluesseln(schluessel, meta.pruefung)) === PRUEFTEXT) {
                meta.fehlversuche = 0;
                await this.speicher.schreiben('meta', 'tresor', meta);
                this.schluessel = schluessel;

                return { ok: true };
            }
        } catch (e) {
            // falscher Code
        }
        if (meta.fehlversuche >= MAX_FEHLVERSUCHE) {
            await this.loeschenAlles();

            return { ok: false, geloescht: true };
        }

        return { ok: false, rest: MAX_FEHLVERSUCHE - meta.fehlversuche };
    }

    /** Schlüssel vergessen (App schließen oder „Sperren“). */
    sperren() {
        this.schluessel = null;
    }

    /** Offene Angaben zu den gespeicherten Veranstaltungen (ohne Schlüssel lesbar). */
    async veranstaltungen() {
        return (await this.speicher.alle('ve')).map(({ id, titel, gespeichertAm, loeschenAm, sensibel }) => ({ id, titel, gespeichertAm, loeschenAm, sensibel }));
    }

    async speichern(id, kopf, objekt) {
        this.pruefen();
        await this.speicher.schreiben('ve', id, { id, ...kopf, blob: await verschluesseln(this.schluessel, objekt) });
    }

    async laden(id) {
        this.pruefen();
        const ve = await this.speicher.lesen('ve', id);

        return ve ? entschluesseln(this.schluessel, ve.blob) : null;
    }

    async entfernen(id) {
        await this.speicher.entfernen('ve', id);
    }

    async warteschlangeSpeichern(eintraege) {
        this.pruefen();
        await this.speicher.schreiben('queue', 'q', await verschluesseln(this.schluessel, eintraege));
        // Nur die Anzahl (keine Personendaten): so weiß die App schon vor dem Entsperren, dass etwas wartet.
        const meta = await this.speicher.lesen('meta', 'tresor');
        if (meta) {
            meta.ausstehend = eintraege.length;
            await this.speicher.schreiben('meta', 'tresor', meta);
        }
    }

    /** Wie viele ungesendete Änderungen im Gerätespeicher warten (ohne Schlüssel lesbar). */
    async ausstehend() {
        return (await this.speicher.lesen('meta', 'tresor'))?.ausstehend ?? 0;
    }

    async warteschlangeLaden() {
        this.pruefen();
        const blob = await this.speicher.lesen('queue', 'q');

        return blob ? entschluesseln(this.schluessel, blob) : [];
    }

    /**
     * Abgelaufene Veranstaltungen löschen (Frist `loeschenAm`) samt ihren noch nicht gesendeten Einträgen.
     * Ohne Schlüssel nur die Veranstaltungen; die Einträge folgen beim nächsten Aufruf nach dem Entsperren
     * (die Nummern der gelöschten Veranstaltungen werden bis dahin gemerkt).
     *
     * @returns {Promise<{geloescht: Array<{id: number, titel: string}>, verworfen: number}>}
     */
    async aufraeumen() {
        const jetzt = this.jetzt();
        const alle = await this.veranstaltungen();
        const abgelaufen = alle.filter((v) => new Date(v.loeschenAm).getTime() <= jetzt);
        for (const v of abgelaufen) {
            await this.entfernen(v.id);
        }
        // Gelöschte Veranstaltungen merken (nur Nummern), bis ihre Einträge mit Schlüssel entfernt werden können.
        const meta = await this.speicher.lesen('meta', 'tresor');
        const verfallen = new Set([...(meta?.verfallen ?? []), ...abgelaufen.map((v) => v.id)]);
        let verworfen = 0;
        if (this.offen && verfallen.size > 0) {
            const q = await this.warteschlangeLaden();
            const rest = q.filter((e) => !verfallen.has(e.veranstaltung));
            verworfen = q.length - rest.length;
            if (verworfen > 0) {
                await this.warteschlangeSpeichern(rest);
            }
            verfallen.clear();
        }
        if (meta) {
            const aktuell = await this.speicher.lesen('meta', 'tresor');
            aktuell.verfallen = [...verfallen];
            await this.speicher.schreiben('meta', 'tresor', aktuell);
        }

        return { geloescht: abgelaufen.map(({ id, titel }) => ({ id, titel })), verworfen };
    }

    /** Alles vom Gerät entfernen (Abmelden, Löschknopf, zu viele falsche Codes). */
    async loeschenAlles() {
        this.schluessel = null;
        await this.speicher.leeren();
    }

    pruefen() {
        if (!this.offen) {
            throw new Error('Der Gerätespeicher ist gesperrt.');
        }
    }
}
