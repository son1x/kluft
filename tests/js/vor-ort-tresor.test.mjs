// node --test tests/js
import assert from 'node:assert/strict';
import test from 'node:test';
import { IdbSpeicher, MAX_FEHLVERSUCHE, MemSpeicher, Tresor, entschluesseln, schluesselAbleiten, verschluesseln } from '../../resources/js/vor-ort-tresor.js';

// Wenige Iterationen, damit die Tests schnell laufen; die App nutzt 600.000.
const neu = (jetzt = () => Date.parse('2027-05-10T12:00:00Z')) => new Tresor(new MemSpeicher(), { iterationen: 1000, jetzt });
const kopf = (loeschenAm = '2027-06-01T00:00:00Z') => ({ titel: 'Pfingstlager', gespeichertAm: '2027-05-10T10:00:00Z', loeschenAm, sensibel: false });

test('verschlüsseln und entschlüsseln: Rundlauf, falscher Schlüssel scheitert, Klartext steht nicht im Blob', async () => {
    const salz = crypto.getRandomValues(new Uint8Array(16));
    const k1 = await schluesselAbleiten('geheim-123', salz, 1000);
    const k2 = await schluesselAbleiten('falsch-456', salz, 1000);
    const blob = await verschluesseln(k1, { name: 'Mia Beispiel', zahl: 3 });
    assert.deepEqual(await entschluesseln(k1, blob), { name: 'Mia Beispiel', zahl: 3 });
    await assert.rejects(entschluesseln(k2, blob));
    assert.equal(Buffer.from(blob.daten).includes('Mia'), false);
    // gleiche Eingabe, anderer Zufallswert (iv)
    assert.notDeepEqual((await verschluesseln(k1, { a: 1 })).daten, (await verschluesseln(k1, { a: 1 })).daten);
});

test('Tresor: anlegen, sperren, entsperren; Code mindestens 6 Zeichen; nur einmal anlegen', async () => {
    const t = neu();
    assert.equal(await t.vorhanden(), false);
    await assert.rejects(t.anlegen('12345'), /mindestens 6/);
    await t.anlegen('mein-code');
    assert.equal(t.offen, true);
    await assert.rejects(t.anlegen('mein-code'), /schon/);
    t.sperren();
    assert.equal(t.offen, false);
    await assert.rejects(t.laden(1), /gesperrt/);
    assert.deepEqual(await t.entsperren('mein-code'), { ok: true });
    assert.equal(t.offen, true);
});

test('Tresor: Veranstaltung und Warteschlange liegen verschlüsselt im Speicher und kommen nach dem Entsperren zurück', async () => {
    const t = neu();
    await t.anlegen('mein-code');
    await t.speichern(7, kopf(), { personen: [{ vorname: 'Mia', nachname: 'Beispiel' }] });
    await t.warteschlangeSpeichern([{ id: 'a', typ: 'anwesend', veranstaltung: 7 }]);

    const roh = JSON.stringify([...t.speicher.daten.ve.values(), ...t.speicher.daten.queue.values()].map((w) => Array.from(w.blob?.daten ?? w.daten)));
    assert.equal(roh.includes('Mia'), false);

    t.sperren();
    assert.deepEqual((await t.veranstaltungen()).map((v) => v.titel), ['Pfingstlager']); // offene Angaben ohne Schlüssel
    await t.entsperren('mein-code');
    assert.deepEqual((await t.laden(7)).personen[0].vorname, 'Mia');
    assert.equal((await t.warteschlangeLaden()).length, 1);
});

test('Tresor: falscher Code zählt, richtiger setzt zurück, nach 10 Fehlversuchen ist alles gelöscht', async () => {
    const t = neu();
    await t.anlegen('mein-code');
    await t.speichern(7, kopf(), { x: 1 });
    t.sperren();

    assert.deepEqual(await t.entsperren('falsch-1'), { ok: false, rest: MAX_FEHLVERSUCHE - 1 });
    assert.deepEqual(await t.entsperren('mein-code'), { ok: true }); // zurückgesetzt
    t.sperren();
    for (let i = 1; i < MAX_FEHLVERSUCHE; i++) {
        assert.equal((await t.entsperren('falsch-' + i)).ok, false);
    }
    assert.deepEqual(await t.entsperren('falsch-x'), { ok: false, geloescht: true });
    assert.equal(await t.vorhanden(), false);
    assert.deepEqual(await t.veranstaltungen(), []);
    assert.deepEqual(await t.entsperren('mein-code'), { ok: false }); // gibt es nicht mehr
});

test('Tresor: abgelaufene Veranstaltungen und ihre ungesendeten Einträge werden gelöscht, andere bleiben', async () => {
    let jetzt = Date.parse('2027-05-10T12:00:00Z');
    const t = neu(() => jetzt);
    await t.anlegen('mein-code');
    await t.speichern(1, kopf('2027-05-20T00:00:00Z'), { a: 1 });
    await t.speichern(2, kopf('2027-07-01T00:00:00Z'), { b: 2 });
    await t.warteschlangeSpeichern([{ id: 'a', veranstaltung: 1 }, { id: 'b', veranstaltung: 2 }, { id: 'c', veranstaltung: 1 }, { id: 'd', veranstaltung: 9 }]);
    assert.equal(await t.ausstehend(), 4);

    assert.deepEqual(await t.aufraeumen(), { geloescht: [], verworfen: 0 });

    jetzt = Date.parse('2027-05-21T00:00:00Z');
    const ergebnis = await t.aufraeumen();
    assert.deepEqual(ergebnis.geloescht.map((v) => v.id), [1]);
    assert.equal(ergebnis.verworfen, 2);
    assert.equal(await t.laden(1), null);
    assert.deepEqual(await t.laden(2), { b: 2 });
    // Einträge zu Veranstaltungen, die nie auf dem Gerät gespeichert waren (9), bleiben.
    assert.deepEqual((await t.warteschlangeLaden()).map((e) => e.id), ['b', 'd']);
    assert.equal(await t.ausstehend(), 2);
});

test('Tresor: gesperrt werden abgelaufene Veranstaltungen trotzdem gelöscht, die Einträge folgen nach dem Entsperren', async () => {
    let jetzt = Date.parse('2027-05-10T12:00:00Z');
    const t = neu(() => jetzt);
    await t.anlegen('mein-code');
    await t.speichern(1, kopf('2027-05-20T00:00:00Z'), { a: 1 });
    await t.warteschlangeSpeichern([{ id: 'a', veranstaltung: 1 }]);
    t.sperren();

    jetzt = Date.parse('2027-05-21T00:00:00Z');
    assert.deepEqual((await t.aufraeumen()).verworfen, 0);
    assert.deepEqual(await t.veranstaltungen(), []);
    await t.entsperren('mein-code');
    assert.equal((await t.aufraeumen()).verworfen, 1);
    assert.deepEqual(await t.warteschlangeLaden(), []);
});

test('Tresor: loeschenAlles entfernt Schlüssel, Veranstaltungen und Warteschlange', async () => {
    const t = neu();
    await t.anlegen('mein-code');
    await t.speichern(1, kopf(), { a: 1 });
    await t.warteschlangeSpeichern([{ id: 'a', veranstaltung: 1 }]);
    await t.loeschenAlles();
    assert.equal(t.offen, false);
    assert.equal(await t.vorhanden(), false);
    assert.deepEqual(await t.veranstaltungen(), []);
});

test('IdbSpeicher (IndexedDB): Tresor-Rundlauf und vollständiges Löschen', async () => {
    const { indexedDB } = await import('fake-indexeddb');
    const t = new Tresor(new IdbSpeicher('vor-ort-test', indexedDB), { iterationen: 1000 });
    await t.anlegen('mein-code');
    await t.speichern(5, kopf(), { personen: [{ vorname: 'Ben' }] });
    await t.warteschlangeSpeichern([{ id: 'x', veranstaltung: 5 }]);
    t.sperren();
    assert.deepEqual((await t.veranstaltungen()).map((v) => v.id), [5]);
    assert.deepEqual(await t.entsperren('mein-code'), { ok: true });
    assert.equal((await t.laden(5)).personen[0].vorname, 'Ben');
    assert.equal((await t.warteschlangeLaden()).length, 1);
    await t.loeschenAlles();
    const frisch = new Tresor(new IdbSpeicher('vor-ort-test', indexedDB), { iterationen: 1000 });
    assert.equal(await frisch.vorhanden(), false);
    assert.deepEqual(await frisch.veranstaltungen(), []);
});
