// Vor Ort (docs/vor-ort.md, Stufe 3): Service Worker.
// Hält nur die Programmdateien (Seite und Dateien unter /build/assets) bereit, damit die App ohne Netz
// startet. Daten (/vor-ort/api/…) werden NIE zwischengespeichert: Was offline gebraucht wird, legt die
// Seite selbst verschlüsselt im Gerätespeicher ab (vor-ort-tresor.js, Stufe 4).
const CACHE = 'vor-ort-v1';
const SEITE = '/vor-ort';

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((namen) => Promise.all(namen.filter((n) => n.startsWith('vor-ort-') && n !== CACHE).map((n) => caches.delete(n))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const anfrage = event.request;
    if (anfrage.method !== 'GET') {
        return;
    }
    const url = new URL(anfrage.url);
    if (url.origin !== self.location.origin || url.pathname.startsWith('/vor-ort/api/')) {
        return;
    }

    // Die App-Seite: zuerst Netz (aktueller Stand), ohne Netz die zuletzt gemerkte.
    if (anfrage.mode === 'navigate' && url.pathname === SEITE) {
        event.respondWith(
            fetch(anfrage)
                .then((antwort) => {
                    // Weiterleitung zur Anmeldung nicht als App-Seite merken.
                    if (antwort.ok && !antwort.redirected) {
                        const kopie = antwort.clone();
                        caches.open(CACHE).then((c) => c.put(SEITE, kopie));
                    }

                    return antwort;
                })
                .catch(() => caches.open(CACHE).then((c) => c.match(SEITE)).then((a) => a ?? Response.error())),
        );

        return;
    }

    // Programmdateien mit Hash im Namen ändern sich nie: aus dem Speicher, sonst holen und merken.
    if (url.pathname.startsWith('/build/assets/') || url.pathname.startsWith('/vor-ort/symbol/')) {
        event.respondWith(
            caches.open(CACHE).then((c) => c.match(anfrage).then((treffer) => treffer ?? fetch(anfrage).then((antwort) => {
                if (antwort.ok) {
                    c.put(anfrage, antwort.clone());
                }

                return antwort;
            }))),
        );
    }
});
