// Selbstgehostete Karte (Pflichtenheft F-MAP-04): Vektorkacheln aus einer
// PMTiles-Datei auf dem eigenen Server, Schriften und Symbole ebenfalls
// lokal – es gehen keine Anfragen an externe Kartendienste.
import * as maplibregl from 'maplibre-gl';
// MapLibre 6 lädt seinen Web-Worker als eigene Datei; Vite bündelt ihn
// samt gemeinsamem Code und liefert die URL (gleiche Herkunft, CSP 'self').
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url';
import 'maplibre-gl/dist/maplibre-gl.css';
import { FetchSource, PMTiles, Protocol } from 'pmtiles';
import { layers, namedFlavor } from '@protomaps/basemaps';

maplibregl.setWorkerUrl(workerUrl);

const protocol = new Protocol();
maplibregl.addProtocol('pmtiles', protocol.tile);

const STAGES = {
    biber: { label: 'Biber', cls: 'bg-stufe-biber text-white' },
    woelflinge: { label: 'Wölflinge', cls: 'bg-stufe-woelflinge text-text' },
    jupfi: { label: 'Jungpfadfinder', cls: 'bg-stufe-jupfi text-white' },
    pfadi: { label: 'Pfadfinder', cls: 'bg-stufe-pfadi text-white' },
    rover: { label: 'Rover', cls: 'bg-stufe-rover text-white' },
};

const COLORS = { bezirk: '#003056', stamm: '#810a1a' };

function baseStyle(pmtilesUrl) {
    const origin = window.location.origin;

    return {
        version: 8,
        glyphs: `${origin}/maps/fonts/{fontstack}/{range}.pbf`,
        sprite: `${origin}/maps/sprites/light`,
        sources: {
            protomaps: {
                type: 'vector',
                url: `pmtiles://${pmtilesUrl}`,
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>-Mitwirkende · <a href="https://protomaps.com">Protomaps</a>',
            },
        },
        layers: layers('protomaps', namedFlavor('light'), { lang: 'de' }),
    };
}

// Range-Requests am Browser-Cache vorbei: Chrome bricht gecachte
// Teilabrufe sonst teils mit "Failed to fetch" ab (pmtiles umgeht das
// von sich aus nur unter Windows).
function registerArchive(url) {
    if (protocol.get(url)) return;
    const source = new FetchSource(url);
    source.chromeWindowsNoCache = true;
    protocol.add(new PMTiles(source));
}

function createMap(container, { pmtiles, bbox }) {
    registerArchive(pmtiles);
    const [w, s, e, n] = bbox;
    const pad = 0.6;

    const map = new maplibregl.Map({
        container,
        style: baseStyle(pmtiles),
        bounds: [[w, s], [e, n]],
        maxBounds: [[w - pad, s - pad], [e + pad, n + pad]],
        minZoom: 6,
        maxZoom: 17,
        cooperativeGestures: true,
        locale: {
            'CooperativeGesturesHandler.WindowsHelpText': 'Zum Zoomen Strg + Mausrad verwenden',
            'CooperativeGesturesHandler.MacHelpText': 'Zum Zoomen ⌘ + Mausrad verwenden',
            'CooperativeGesturesHandler.MobileHelpText': 'Mit zwei Fingern verschieben',
            'NavigationControl.ZoomIn': 'Vergrößern',
            'NavigationControl.ZoomOut': 'Verkleinern',
            'NavigationControl.ResetBearing': 'Nach Norden ausrichten',
        },
    });
    map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');

    return map;
}

function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;

    return node;
}

function safeUrl(value) {
    try {
        const url = new URL(value);

        return ['http:', 'https:'].includes(url.protocol) ? url.href : null;
    } catch {
        return null;
    }
}

function popupContent(p) {
    const root = el('div', 'font-sans text-sm text-text max-w-64');
    const kind = p.typ === 'bezirk' ? 'Bezirk' : (p.bezirk ? `Stamm · Bezirk ${p.bezirk}` : 'Stamm');
    root.append(el('p', 'text-xs uppercase tracking-wide text-text-secondary', p.nummer ? `${p.nummer} · ${kind}` : kind));
    root.append(el('h3', 'font-serif text-base font-bold text-dpsg-blau', p.name));

    const showOrt = p.strasse || (p.ort && !p.name.includes(p.ort));
    const address = showOrt ? [p.strasse, [p.plz, p.ort].filter(Boolean).join(' ')].filter(Boolean) : [];
    if (address.length) {
        const a = el('p', 'mt-1');
        address.forEach((line, i) => { if (i) a.append(document.createElement('br')); a.append(line); });
        root.append(a);
    }

    const stufen = typeof p.stufen === 'string' ? JSON.parse(p.stufen) : (p.stufen || []);
    if (stufen.length) {
        const badges = el('div', 'mt-2 flex flex-wrap gap-1');
        stufen.forEach((key) => STAGES[key] && badges.append(el('span', `rounded px-1.5 py-0.5 text-xs font-medium ${STAGES[key].cls}`, STAGES[key].label)));
        root.append(badges);
    }

    const contact = el('p', 'mt-2 space-x-2');
    if (p.ansprechperson) root.append(el('p', 'mt-2', `Ansprechperson: ${p.ansprechperson}`));
    if (p.email) { const m = el('a', 'text-dpsg-rot underline', p.email); m.href = `mailto:${p.email}`; contact.append(m); }
    if (p.telefon) { const t = el('a', 'text-dpsg-rot underline', p.telefon); t.href = `tel:${p.telefon.replace(/[^+\d]/g, '')}`; contact.append(t); }
    if (contact.childNodes.length) root.append(contact);

    const links = el('p', 'mt-1 space-x-3');
    [[p.website, 'Website'], [p.social, 'Social Media']].forEach(([href, label]) => {
        const url = href && safeUrl(href);
        if (url) { const l = el('a', 'text-dpsg-rot underline', label); l.href = url; l.target = '_blank'; l.rel = 'noopener'; links.append(l); }
    });
    if (links.childNodes.length) root.append(links);

    if (p.ungefaehr === true || p.ungefaehr === 'true') {
        root.append(el('p', 'mt-2 text-xs text-text-secondary', 'Standort ungefähr (Ortsmitte)'));
    }

    return root;
}

function initDirectory(container) {
    const map = createMap(container, {
        pmtiles: container.dataset.pmtiles,
        bbox: JSON.parse(container.dataset.bbox),
    });
    let features = [];
    let fitted = false;
    const popup = new maplibregl.Popup({ maxWidth: '18rem', offset: 12 });

    const openFeature = (feature) => {
        popup.setLngLat(feature.geometry.coordinates).setDOMContent(popupContent(feature.properties)).addTo(map);
    };

    map.on('load', async () => {
        const response = await fetch(container.dataset.geojson, { headers: { Accept: 'application/geo+json' } });
        const data = await response.json();
        features = data.features;

        map.addSource('gruppierungen', { type: 'geojson', data, cluster: true, clusterRadius: 36, clusterMaxZoom: 11 });
        map.addLayer({
            id: 'cluster', type: 'circle', source: 'gruppierungen', filter: ['has', 'point_count'],
            paint: { 'circle-color': COLORS.bezirk, 'circle-radius': ['step', ['get', 'point_count'], 16, 10, 20, 30, 26], 'circle-stroke-width': 3, 'circle-stroke-color': '#ffffff' },
        });
        map.addLayer({
            id: 'cluster-count', type: 'symbol', source: 'gruppierungen', filter: ['has', 'point_count'],
            layout: { 'text-field': ['get', 'point_count_abbreviated'], 'text-font': ['Noto Sans Medium'], 'text-size': 13 },
            paint: { 'text-color': '#ffffff' },
        });
        map.addLayer({
            id: 'punkt', type: 'circle', source: 'gruppierungen', filter: ['!', ['has', 'point_count']],
            paint: {
                'circle-color': ['match', ['get', 'typ'], 'bezirk', COLORS.bezirk, COLORS.stamm],
                'circle-radius': ['match', ['get', 'typ'], 'bezirk', 10, 8],
                'circle-stroke-width': 3, 'circle-stroke-color': '#ffffff',
            },
        });
        map.addLayer({
            id: 'punkt-name', type: 'symbol', source: 'gruppierungen', filter: ['!', ['has', 'point_count']], minzoom: 10,
            layout: { 'text-field': ['get', 'name'], 'text-font': ['Noto Sans Medium'], 'text-size': 12, 'text-offset': [0, 1.3], 'text-anchor': 'top', 'text-optional': true },
            paint: { 'text-color': '#1c1f26', 'text-halo-color': '#ffffff', 'text-halo-width': 1.5 },
        });

        map.on('click', 'punkt', (e) => openFeature(e.features[0]));
        map.on('click', 'cluster', async (e) => {
            const zoom = await map.getSource('gruppierungen').getClusterExpansionZoom(e.features[0].properties.cluster_id);
            map.easeTo({ center: e.features[0].geometry.coordinates, zoom });
        });
        ['punkt', 'cluster'].forEach((layer) => {
            map.on('mouseenter', layer, () => { map.getCanvas().style.cursor = 'pointer'; });
            map.on('mouseleave', layer, () => { map.getCanvas().style.cursor = ''; });
        });

        fitToFeatures();
    });

    const fitToFeatures = () => {
        if (!features.length || container.offsetWidth === 0) return;
        const bounds = new maplibregl.LngLatBounds();
        features.forEach((f) => bounds.extend(f.geometry.coordinates));
        map.fitBounds(bounds, { padding: 60, maxZoom: 12, duration: 0 });
        fitted = true;
    };

    // Listeneinträge: "Auf der Karte zeigen" (Liste und Karte synchron).
    document.querySelectorAll('[data-show-on-map]').forEach((button) => {
        button.hidden = false;
        button.addEventListener('click', () => {
            const feature = features.find((f) => String(f.id) === button.dataset.showOnMap);
            if (!feature) return;
            window.dispatchEvent(new CustomEvent('show-map'));
            requestAnimationFrame(() => {
                map.resize();
                map.flyTo({ center: feature.geometry.coordinates, zoom: Math.max(map.getZoom(), 13) });
                openFeature(feature);
                container.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        });
    });

    window.addEventListener('map-visible', () => {
        map.resize();
        if (!fitted) fitToFeatures();
    });
}

// Standort im Backend per Klick setzen (F-MAP-05), schreibt in die
// Livewire-Formularfelder data.lat / data.lng.
function initPicker(container) {
    if (container.dataset.initialized) return;
    container.dataset.initialized = '1';

    const component = () => window.Livewire?.find(container.closest('[wire\\:id]')?.getAttribute('wire:id'));
    const map = createMap(container, {
        pmtiles: container.dataset.pmtiles,
        bbox: JSON.parse(container.dataset.bbox),
    });
    const marker = new maplibregl.Marker({ color: COLORS.stamm, draggable: true });

    const place = (lng, lat, write) => {
        marker.setLngLat([lng, lat]).addTo(map);
        const wire = write && component();
        if (wire) {
            wire.set('data.lat', Number(lat.toFixed(6)));
            wire.set('data.lng', Number(lng.toFixed(6)));
        }
    };

    const lat = parseFloat(container.dataset.lat);
    const lng = parseFloat(container.dataset.lng);
    if (!Number.isNaN(lat) && !Number.isNaN(lng)) {
        place(lng, lat, false);
        map.jumpTo({ center: [lng, lat], zoom: 13 });
    }

    map.on('click', (e) => place(e.lngLat.lng, e.lngLat.lat, true));
    marker.on('dragend', () => { const p = marker.getLngLat(); place(p.lng, p.lat, true); });
}

function boot() {
    document.querySelectorAll('[data-gruppierungen-map]').forEach(initDirectory);
    document.querySelectorAll('[data-map-picker]').forEach(initPicker);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
document.addEventListener('livewire:navigated', boot);
window.dpsgMapBoot = boot;
