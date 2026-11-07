// Titelbilder der Georgsboten aus Seite 1 des PDFs (docs/auftrag-titelbilder.md).
// Gerendert wird im Browser der angemeldeten Redaktion mit pdf.js, weil der
// Webspace kein Ghostscript/Imagick garantiert. Alles kommt von der eigenen
// Herkunft (CSP 'self'), es gehen keine Anfragen an fremde Server.
import { getDocument, GlobalWorkerOptions } from 'pdfjs-dist/legacy/build/pdf.mjs';
// Der Worker als eigene Datei, Vite liefert die URL (wie bei MapLibre).
import workerUrl from 'pdfjs-dist/legacy/build/pdf.worker.min.mjs?worker&url';

GlobalWorkerOptions.workerSrc = workerUrl;

// Standard-Schriften, CMaps, WASM-Decoder (JPEG 2000, JBIG2, Farbprofile) und
// das CMYK-Profil lädt pdf.js nur bei Bedarf nach. Vite legt sie unter festem
// Namen in je ein Verzeichnis (vite.config.js); hier nur dessen Adresse.
const verzeichnis = (dateien) => {
    const url = Object.values(dateien)[0];

    return url.slice(0, url.lastIndexOf('/') + 1);
};
const glob = {
    fonts: import.meta.glob('/node_modules/pdfjs-dist/standard_fonts/*.{pfb,ttf}', { query: '?url', import: 'default', eager: true }),
    cmaps: import.meta.glob('/node_modules/pdfjs-dist/cmaps/*.bcmap', { query: '?url', import: 'default', eager: true }),
    wasm: import.meta.glob('/node_modules/pdfjs-dist/wasm/{openjpeg,jbig2,qcms_bg}.wasm', { query: '?url', import: 'default', eager: true }),
    iccs: import.meta.glob('/node_modules/pdfjs-dist/iccs/*.icc', { query: '?url', import: 'default', eager: true }),
};
const absolut = (pfad) => new URL(pfad, window.location.href).href;
const PDFJS_OPTIONEN = {
    standardFontDataUrl: absolut(verzeichnis(glob.fonts)),
    cMapUrl: absolut(verzeichnis(glob.cmaps)),
    cMapPacked: true,
    wasmUrl: absolut(verzeichnis(glob.wasm)),
    iccUrl: absolut(verzeichnis(glob.iccs)),
    // pdf.js 6 nutzt kein eval mehr; für ältere Versionen bleibt es aus.
    isEvalSupported: false,
    enableXfa: false,
};

export const BREITE = 800;
const QUALITAET = 0.85;

/**
 * Seite 1 eines PDFs als Bild: 800 px breit, Höhe nach Seitenverhältnis,
 * weißer Hintergrund. WebP, wenn der Browser es kann, sonst JPEG.
 *
 * @param {string} pdfUrl
 * @returns {Promise<Blob>}
 */
export async function renderSeiteEins(pdfUrl) {
    const aufgabe = getDocument({ url: pdfUrl, withCredentials: true, ...PDFJS_OPTIONEN });
    try {
        const pdf = await aufgabe.promise;
        const seite = await pdf.getPage(1);
        const basis = seite.getViewport({ scale: 1 });
        const viewport = seite.getViewport({ scale: BREITE / basis.width });

        const canvas = document.createElement('canvas');
        canvas.width = BREITE;
        canvas.height = Math.round(viewport.height);
        const ctx = canvas.getContext('2d', { alpha: false });
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        await seite.render({ canvas, canvasContext: ctx, viewport, background: '#ffffff' }).promise;
        seite.cleanup();

        const bild = (await alsBlob(canvas, 'image/webp')) ?? (await alsBlob(canvas, 'image/jpeg'));
        // Canvas-Speicher sofort freigeben (viele Ausgaben nacheinander).
        canvas.width = 0;
        canvas.height = 0;
        if (!bild) {
            throw new Error('Der Browser konnte kein Bild erzeugen.');
        }

        return bild;
    } finally {
        await aufgabe.destroy();
    }
}

/** Blob im gewünschten Format oder null, wenn der Browser es nicht liefert (toBlob fällt dann auf PNG zurück). */
function alsBlob(canvas, typ) {
    return new Promise((resolve) => {
        canvas.toBlob((blob) => resolve(blob && blob.type === typ ? blob : null), typ, QUALITAET);
    });
}

/**
 * Bild an Kluft schicken. Antwort { ok, cover_url } bzw. { ok: false, grund }.
 *
 * @param {string} uploadUrl
 * @param {Blob} bild
 * @param {boolean} ueberschreiben
 */
export async function hochladen(uploadUrl, bild, ueberschreiben = false) {
    const daten = new FormData();
    daten.append('bild', bild, bild.type === 'image/webp' ? 'titel.webp' : 'titel.jpg');
    daten.append('ueberschreiben', ueberschreiben ? '1' : '0');

    const antwort = await fetch(uploadUrl, {
        method: 'POST',
        body: daten,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
    let json = null;
    try {
        json = await antwort.json();
    } catch {
        // keine JSON-Antwort, z. B. abgelaufene Sitzung
    }
    if (!antwort.ok || !json?.ok) {
        throw new Error(json?.grund ?? json?.message ?? `Fehler ${antwort.status}`);
    }

    return json;
}

/**
 * Alpine-Komponente: arbeitet die Ausgaben nacheinander ab (Speicher
 * schonen), zeigt den Fortschritt und lädt danach die Tabelle neu.
 *
 * @param {{ ausgaben: Array<{ id: number, label: string, pdfUrl: string, uploadUrl: string }>, ueberschreiben?: boolean, automatisch?: boolean }} optionen
 */
function titelbilder({ ausgaben = [], ueberschreiben = false, automatisch = false } = {}) {
    return {
        ausgaben,
        ueberschreiben,
        laeuft: false,
        fertig: false,
        aktuell: 0,
        aktuellLabel: '',
        erzeugt: 0,
        fehler: [],

        init() {
            if (automatisch) {
                this.$nextTick(() => this.start());
            }
        },

        get fortschritt() {
            return `${this.aktuell} von ${this.ausgaben.length} – ${this.aktuellLabel}`;
        },

        get prozent() {
            return this.ausgaben.length ? Math.round((this.aktuell / this.ausgaben.length) * 100) : 0;
        },

        get ergebnis() {
            const teile = [`Erzeugt: ${this.erzeugt}`, `Fehler: ${this.fehler.length}`];
            const text = teile.join(', ');

            return this.fehler.length ? `${text} (${this.fehler.map((f) => f.label).join(', ')})` : text;
        },

        async start() {
            if (this.laeuft || !this.ausgaben.length) {
                return;
            }
            this.laeuft = true;
            this.fertig = false;
            this.erzeugt = 0;
            this.fehler = [];

            for (const [index, ausgabe] of this.ausgaben.entries()) {
                this.aktuell = index + 1;
                this.aktuellLabel = ausgabe.label;
                try {
                    const bild = await renderSeiteEins(ausgabe.pdfUrl);
                    await hochladen(ausgabe.uploadUrl, bild, this.ueberschreiben);
                    this.erzeugt++;
                } catch (fehler) {
                    console.warn(`Titelbild ${ausgabe.label}:`, fehler);
                    this.fehler.push({ label: ausgabe.label, grund: String(fehler?.message ?? fehler) });
                }
            }

            this.laeuft = false;
            this.fertig = true;
            // Tabelle bzw. Formular neu laden, damit die Titelbilder erscheinen.
            window.Livewire?.dispatch('titelbilder-erzeugt', { erzeugt: this.erzeugt, fehler: this.fehler.map((f) => f.label) });
        },
    };
}

// Filament bringt Alpine selbst mit; registrieren, sobald es startet
// (oder sofort, falls es schon läuft).
if (window.Alpine) {
    window.Alpine.data('titelbilder', titelbilder);
} else {
    document.addEventListener('alpine:init', () => window.Alpine.data('titelbilder', titelbilder));
}
