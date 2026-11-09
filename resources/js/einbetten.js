// Einbett-Ansicht (events/einbetten): Höhe an die einbindende Seite melden,
// damit das iframe ohne eigenen Scrollbalken mitwächst. Nur die Zahl, keine Daten.
export default function hoeheMelden() {
    if (window.parent === window) {
        return;
    }
    const melden = () => window.parent.postMessage({ kluftHoehe: Math.ceil(document.documentElement.scrollHeight) }, '*');
    new ResizeObserver(melden).observe(document.body);
    window.addEventListener('load', melden);
    melden();
}
