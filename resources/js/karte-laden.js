// Bezirke & Stämme: die Karte (MapLibre, ~1 MB) erst laden, wenn sie
// gebraucht wird. Desktop: Karte ist sichtbar → nach dem Laden der Seite im
// Leerlauf. Handy: Karte ist zuerst ausgeblendet → erst beim Tippen auf
// „Karte“ oder „Auf der Karte zeigen“. So bleibt der erste Aufbau schnell.
let geladen = null;
const laden = () => (geladen ??= import('./map.js'));

const imLeerlauf = (fn) => {
    const los = () => ('requestIdleCallback' in window ? requestIdleCallback(fn, { timeout: 2000 }) : setTimeout(fn, 200));
    document.readyState === 'complete' ? los() : window.addEventListener('load', los, { once: true });
};

// Wartet, bis die Karte ihre Standorte geladen hat (map.js setzt data-bereit).
const bereit = (karte) => new Promise((ok) => {
    if (!karte || karte.dataset.bereit) return ok();
    karte.addEventListener('karte-bereit', () => ok(), { once: true });
    setTimeout(ok, 10000);
});

function start() {
    const karten = document.querySelectorAll('[data-gruppierungen-map]');
    if (!karten.length) return;

    // Sichtbar geworden (Desktop beim Aufruf, Handy nach „Karte“): laden.
    if ('IntersectionObserver' in window) {
        const beobachter = new IntersectionObserver((eintraege) => {
            if (!eintraege.some((e) => e.isIntersecting)) return;
            beobachter.disconnect();
            document.readyState === 'complete' ? laden() : imLeerlauf(laden);
        }, { rootMargin: '200px' });
        karten.forEach((k) => beobachter.observe(k));
    } else {
        imLeerlauf(laden);
    }

    // „Auf der Karte zeigen“ gibt es schon vor der Karte: beim ersten Klick
    // laden und den Klick danach wiederholen (dann reagiert die Karte).
    document.querySelectorAll('[data-show-on-map]').forEach((knopf) => {
        knopf.hidden = false;
        // Dieser Listener hängt vor dem der Karte; er hält den ersten Klick an,
        // bis die Karte bereit ist (auch wenn sie gerade noch lädt).
        knopf.addEventListener('click', (e) => {
            e.stopImmediatePropagation();
            // Karte zeigen (Handy), laden und warten, bis die Standorte da sind.
            window.dispatchEvent(new CustomEvent('show-map'));
            laden().then(() => bereit(karten[0])).then(() => knopf.click());
        }, { once: true });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
} else {
    start();
}
