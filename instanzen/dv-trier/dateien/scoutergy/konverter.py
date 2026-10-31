"""Scoutergy (wp-scoutergy) -> instanzen/dv-trier/dateien/scoutergy (inhalt.json + medien/).

Aufruf: python3 -I scoutergy_konverter.py /home/user/wp-scoutergy /home/user/kluft
"""
import html
import json
import re
import shutil
import sys
from pathlib import Path

from PIL import Image

QUELLE = Path(sys.argv[1])
ZIEL = Path(sys.argv[2]) / 'instanzen/dv-trier/dateien/scoutergy'
MEDIEN = ZIEL / 'medien'
MEDIEN.mkdir(parents=True, exist_ok=True)
INSTANZ_PUBLIC = Path(sys.argv[2]) / 'instanzen/dv-trier/public/images/scoutergy'

def eintrag(slug_teil):
    treffer = [p for p in (QUELLE / 'daten/inhalte').glob('*.json') if p.name != 'index.json' and json.loads(p.read_text())['slug'] == slug_teil]
    assert len(treffer) == 1, slug_teil
    return json.loads(treffer[0].read_text())

def ohne_bloecke(s):
    s = re.sub(r'<!--.*?-->', '', s, flags=re.S)
    s = s.replace('&nbsp;', ' ')
    return s.strip()

# ---- Medien ---------------------------------------------------------------
medien = []

def bild(quelle, name, alt, breite=None):
    q = QUELLE / 'medien' / quelle
    im = Image.open(q)
    if breite and im.size[0] > breite:
        im = im.resize((breite, round(im.size[1] * breite / im.size[0])), Image.LANCZOS)
    ziel = MEDIEN / name
    if name.endswith('.jpg'):
        im.convert('RGB').save(ziel, quality=82, optimize=True, progressive=True)
        mime = 'image/jpeg'
    else:
        im.save(ziel, optimize=True)
        mime = 'image/png'
    medien.append({'datei': name, 'mime': mime, 'alt': alt, 'quelle': 'https://www.scoutergy.de/wp-content/uploads/' + quelle})
    return '/uploads/media/' + name

LOGO = bild('2021/12/scoutergy_logo.png', 'scoutergy-logo.png',
            'Glühbirne mit Scoutergy-Schriftzug und dem Hinweis: FroLeiLa 2023 des DPSG DV Trier', 600)
HAJK = bild('2023/06/image.png', 'scoutergy-rover-hajk-glas.png',
            'Warnschild: Wanderstiefel im roten Dreieck')

KACHELN = [
    ('campfireabend', 'Campfire-Abend am Freitag für alle: mitmachen, singen, Theater, Poetry-Slam'),
    ('dpsg-jahresaktionsgruppe', 'DPSG-Jahresaktionsgruppe „Hochspannend – Pfadfinden elektrisiert“ an einem Tag für alle'),
    ('energyball-bubblesoccer', 'Energy Ball und Bubble Soccer am Freitag im Stufenprogramm für Pfadfinder*innen, Rover und Leiter*innen'),
    ('kilometer-killen', 'Nicht chillen – Kilometer killen: Tageswanderung auf dem Geo-Energie-Lehrpfad für Rover und Leiter*innen'),
    ('kraft-der-klangschalen', 'Die Kraft der Klangschalen: Energiearbeit am Donnerstagabend für Rover und Leiter*innen'),
    ('lightpainting-workshop', 'Light-Painting-Workshop für die Abschlussfeier, Donnerstag- und Samstagabend'),
    ('menschenkickerturnier', 'Menschenkicker-Turnier am Donnerstagabend, Teil II: Pfadfinder*innen gegen Rover'),
    ('photovoltaik-ringsdorfsolar', 'Photovoltaik – präsentiert von Ringsdorf Solar: Workshop, Vortrag und Modell am Freitag für alle Stufen'),
    ('voelkerball', 'Völkerball-Turnier am Donnerstag für alle Stufen'),
    ('workshop-experimente', 'Workshop Experimente am Donnerstag und Samstag für alle: kneten, löten, Windräder, Glühwürmchen, basteln'),
]
kachel_html = '\n'.join(
    '<p><img src="%s" alt="Programmkachel: %s" width="1024" /></p>' % (bild('2023/03/%s.jpeg' % n, 'scoutergy-programm-%s.jpg' % n, 'Programmkachel: ' + alt, 1024), html.escape(alt))
    for n, alt in KACHELN
)

# Charakterblatt (PDF) und Video liegen als öffentliche Instanzdateien vor.
shutil.copy(QUELLE / 'medien/2023/05/Charakterblatt.pdf', INSTANZ_PUBLIC / 'charakterblatt.pdf')
VIDEO = '/instanz/images/scoutergy/teaser-720.mp4'
POSTER = '/instanz/images/scoutergy/teaser-poster.jpg'
PDF = '/instanz/images/scoutergy/charakterblatt.pdf'

STUFEN = {'01-biber': 'Biber', '02-woelflinge': 'Wölflinge', '03-jungpfadfinder': 'Jungpfadfinder*innen', '04-pfadfinder': 'Pfadfinder*innen', '05-rover': 'Rover'}

def kurzinfo(wann, wo, wer):
    zeilen = ''.join('<tr><th scope="row">%s</th><td>%s</td></tr>' % (k, v) for k, v in (('Wann?', wann), ('Wo?', wo), ('Wer?', wer)) if v)
    return '<figure><table><tbody>%s</tbody></table><figcaption>Kurzinfo</figcaption></figure>' % zeilen

# ---- Seiten ---------------------------------------------------------------
programm = ohne_bloecke(eintrag('programm')['post_content'])
programm = programm[programm.index('<p>Hier findest Du'):]  # Slider (Shortcode) davor entfällt
programm = programm.replace('<p>Hier findest Du die immer wieder aktualisierte Version des Lagerprogramms - wir werden Dich so früh wie möglich jeweils informieren.</p>',
                            '<p>So sah der Programmplan für das Lager aus, Stand Juni 2023.</p>')
programm = programm.replace('Melde Deinen Stamm zwingend vorher an unter: mobilzumziel@scoutergy.de', 'Stämme meldeten sich dafür vorher beim Anreiseteam („Mobil zum Ziel“) an.')
programm = programm.replace('Gottesdienst mit unserem Kurat Peter in der Arena', 'Gottesdienst in der Arena')
programm = re.sub(r' class="[^"]*"', '', programm)
programm = re.sub(r'<h2>([^<]*?)\s*</h2>', lambda m: '<h2>' + m.group(1).replace(' - ', ' – ').strip() + '</h2>', programm)
programm = re.sub(r'\n{2,}', '\n', programm).strip()
assert 'scoutergy.de' not in programm and 'smartslider' not in programm
programm += '\n<h2>Programmpunkte</h2>\n<p>Ein Teil der Angebote, wie sie vorab als Kacheln auf der Lagerseite standen:</p>\n' + kachel_html

team = '''<p>Hinter dem Lager stand ein ehrenamtliches Team aus dem ganzen Diözesanverband. So war es aufgeteilt:</p>
<h2>Lagerleitung</h2>
<p>Gesamtverantwortung und erste Anlaufstelle für alle Fragen rund um das Lager.</p>
<h2>Anreise („Mobil zum Ziel“)</h2>
<p>Anreise der Stämme und der Shuttle vom Bahnhof Limburg zum Lagerplatz.</p>
<h2>Helferlein</h2>
<p>Koordination aller Helfer*innen, die beim Lager mit angepackt haben.</p>
<h2>Mampf</h2>
<p>Verpflegung: ausgewogen, nachhaltig, regional und grundsätzlich vegetarisch beziehungsweise pflanzlich.</p>
<h2>Programmgestaltung („Programmheroes“)</h2>
<p>Inhalte und Programmplanung, vom Power Day bis zur Abschlussfeier.</p>
<p>Die Adressen unter scoutergy.de gibt es nicht mehr. Fragen zum Lager oder zu diesem Archiv beantwortet das Diözesanbüro über das <a href="/kontakt">Kontaktformular</a>.</p>'''

# FAQ: Einträge ohne doppelte Anzeige in drei Gruppen.
ERSATZ = {
    ' - haltet Rücksprache mit <a href="mailto:programmheroes@scoutergy.de">programmheroes@scoutergy.de</a>!': ' – haltet Rücksprache mit dem Programmteam („Programmheroes“)!',
    'Oder wendet euch direkt an <a href="mailto:mampf@scoutergy.de">mampf@scoutergy.de</a>.': 'Oder wendet euch direkt an das Küchenteam („Mampf“).',
    'wende Dich bitte an <a href="mailto:lagerleitung@scoutergy.de">lagerleitung@scoutergy.de</a>.': 'wende Dich bitte an die Lagerleitung.',
    'Du hast Lust zu helfen? -Hier gehts direkt zur <a href="https://www.scoutergy.de/anmeldung-als-helferlein/">Anmeldung</a>!': '',
}
faq_gruppen = {'beide': [], 'vorbereitung': [], 'teilnehmer': []}
for p in sorted((QUELLE / 'daten/inhalte').glob('sp_faq-*.json')):
    e = json.loads(p.read_text())
    if e['post_status'] != 'publish':
        continue
    text = ohne_bloecke(e['post_content'])
    text = re.sub(r'\[contact-form\].*?\[/contact-form\]', '', text, flags=re.S)
    if e['slug'] == 'ab-wann-ist-eine-anreise-moeglich':
        text = 'Generell ist Mittwoch der Anreisetag, Donnerstag ist auch noch möglich. Die Eröffnung ist am Donnerstagmorgen.'
    text = re.sub(r'\s*\n\s*- haltet', ' - haltet', text)
    for alt, neu in ERSATZ.items():
        text = text.replace(alt, neu)
    assert '@scoutergy' not in text and 'scoutergy.de' not in text and '[' not in text, e['slug']
    absaetze = [a.strip() for a in re.split(r'\n\s*\n|\n', text) if a.strip()]
    antwort = ''.join('<p>%s</p>' % a for a in absaetze)
    kat = {t['slug'] for t in e['taxonomien'] if t['taxonomy'] == 'faq_cat'}
    vorb = bool(kat & {'vorbereitung', 'aufbau'})
    teil = 'teilnehmer' in kat
    gruppe = 'beide' if (vorb and teil) or e['slug'] == 'gibt-es-ein-maskottchen' else ('teilnehmer' if teil else 'vorbereitung')
    faq_gruppen[gruppe].append((e['ID'], html.escape(e['post_title'].strip(), quote=False), antwort))

titel = {'beide': 'Rund ums Lager', 'vorbereitung': 'Für Stämme und Vorbereitungsteams', 'teilnehmer': 'Für Teilnehmende'}
faq = '<p>Diese Fragen und Antworten standen vor dem Lager auf scoutergy.de. Sie zeigen, wie das Lager 2023 organisiert war.</p>\n'
for g in ('beide', 'vorbereitung', 'teilnehmer'):
    faq += '<h2>%s</h2>\n' % titel[g]
    for _, frage, antwort in sorted(faq_gruppen[g], key=lambda x: int(x[0])):
        faq += '<details><summary>%s</summary>%s</details>\n' % (frage, antwort)

seiten = [
    {
        'slug': 'scoutergy', 'title': 'Scoutergy!', 'nav_sort': 0,
        'seo_description': 'Das FroLeiLa des DPSG-Diözesanverbands Trier vom 8. bis 11. Juni 2023 im Bundeszentrum Westernohe – das Archiv der Lagerseite.',
        'body': '''<p>Über Fronleichnam, vom 8. bis 11. Juni 2023, hat der Diözesanverband Trier zum Diözesanlager ins Bundeszentrum Westernohe eingeladen: auf den Altenberg, rund zwischen Trupphaus und Kapelle. Das Motto: <strong>Scoutergy – Energie ohne Ende</strong>. Maskottchen war Birni, die Glühbirne.</p>
<p>Das „Go!“ für das Lager kam auf der Diözesanversammlung im November 2021. Hier findest du, was von der Lagerseite scoutergy.de geblieben ist: das Programm, die häufigen Fragen, wie das Team aufgeteilt war, und die Beiträge aus der Vorbereitung.</p>''',
    },
    {'slug': 'scoutergy-programm', 'title': 'Programm', 'nav_sort': 10,
     'seo_description': 'Tagesplan des Diözesanlagers Scoutergy 2023 von der Anreise bis zur Abschlussfeier, dazu die Programmkacheln.', 'body': programm},
    {'slug': 'scoutergy-faq', 'title': 'Häufige Fragen', 'nav_sort': 20,
     'seo_description': 'Fragen und Antworten zum Diözesanlager Scoutergy 2023: Anreise, Beitrag, Essen, Regeln auf dem Platz.', 'body': faq},
    {'slug': 'scoutergy-team', 'title': 'Team', 'nav_sort': 30,
     'seo_description': 'So war das ehrenamtliche Team des Diözesanlagers Scoutergy 2023 aufgeteilt.', 'body': team},
]

# ---- Beiträge -------------------------------------------------------------
def datum(slug):
    return eintrag(slug)['post_date']

alle_stufen = ', '.join(STUFEN.values())
beitraege = [
    {'slug': 'scoutergy-go-fuer-das-diözesanlager'.replace('ö', 'oe'), 'title': 'Danke!', 'published_at': datum('hallo-welt'),
     'teaser': 'Die Diözesanversammlung hat das „Go!“ für das Diözesanlager 2023 gegeben: 8. bis 11. Juni 2023.',
     'body': '<p>Seit heute ist es offiziell! Auf der Diözesanversammlung haben wir das „Go!“ für unser Diözesanlager 2023 bekommen:</p><blockquote><p>8. bis 11. Juni 2023</p><p>Diözesanlager über Fronleichnam 2023</p></blockquote>'},
    {'slug': 'scoutergy-georgsbote', 'title': 'Der Georgsbote ist raus!', 'published_at': datum('der-georgsbote-ist-raus'),
     'teaser': 'Im Georgsboten war zum ersten Mal das Logo des Diözesanlagers 2023 zu sehen.',
     'body': '<p>Der nächste Meilenstein auf unserer Reise zum Diözesanlager 2023.</p><p>Mit dem Georgsboten habt ihr erstmals unser Logo gesehen:</p><figure><img src="%s" alt="Glühbirne mit Scoutergy-Schriftzug und dem Hinweis: FroLeiLa 2023 des DPSG DV Trier" width="600" /><figcaption>Scoutergy – FroLeiLa DV Trier 2023</figcaption></figure><p>Und den Hinweis auf die Lagerseite – oder wie bist du hierhergekommen?</p>' % LOGO},
    {'slug': 'scoutergy-teaser', 'title': 'Wo führt das FroLeiLa uns hin?', 'published_at': datum('wo-fuehrt-das-froleila-uns-hin'),
     'teaser': 'Der Teaser zum Diözesanlager 2023: Es geht nach Westernohe.',
     'body': '<figure><video controls playsinline preload="none" poster="%s" src="%s"></video><figcaption>Teaser zum FroLeiLa 2023 (2:16 Minuten)</figcaption></figure>' % (POSTER, VIDEO)},
    {'slug': 'scoutergy-rollenspiel', 'title': 'Rollenspiel: Das Königreich Grüntal braucht euch', 'published_at': datum('29-rollenspiel'),
     'teaser': 'Die Kobolde von Grüntal sind verschwunden: das Rollenspiel für die Jungpfadfinder*innen am Freitag.',
     'body': None},
    {'slug': 'scoutergy-workshopzeit', 'title': 'Workshopzeit', 'published_at': datum('69-workshopzeit'),
     'teaser': 'Samstagvormittag gehörte den Workshops – angeboten von Stämmen und Team.',
     'body': kurzinfo('Samstag, 10:30–12:30 Uhr', 'Nach Absprache', alle_stufen) + '<h2>Wir suchen Dich!</h2><p>Wer möchte einen Workshop anbieten?<br />Hier veröffentlichen wir die aktuellen Angebote!</p>'},
    {'slug': 'scoutergy-rover-hajk-glas', 'title': 'Rover-Hajk: das Glas', 'published_at': datum('rover-hajk-glas'),
     'teaser': 'Eine Station des Rover-Hajks: Kannen zählen und das Glas wieder gut verschließen.',
     'body': None},
]

rollenspiel = ohne_bloecke(eintrag('29-rollenspiel')['post_content'])
geschichte = re.search(r'<p>(Werte Held.*?)</p>', rollenspiel, re.S).group(1)
beitraege[3]['body'] = kurzinfo('Freitag, 10:15 Uhr', 'Arena', 'Jungpfadfinder*innen') + '<p>' + geschichte + '</p><p><a href="%s">Charakterblatt (PDF, 390 KB)</a></p>' % PDF

hajk = ohne_bloecke(eintrag('rover-hajk-glas')['post_content'])
liste = re.search(r'<ul>.*?</ul>', hajk, re.S).group(0)
liste = re.sub(r'\s*</li>', '</li>', re.sub(r'\s+', ' ', liste)).replace(' </li>', '</li>')
beitraege[5]['body'] = '<figure><img src="%s" alt="Warnschild: Wanderstiefel im roten Dreieck" width="360" /></figure><h2>Unterwegs sein – wir hoffen, noch trockenen Fußes!</h2>%s' % (HAJK, liste)

for b in beitraege:
    assert 'scoutergy.de' not in b['body'], b['slug']

inhalt = {
    'hinweis': 'Erzeugt aus son1x/wp-scoutergy (Stand 19.06.2023) mit scoutergy_konverter.py; Personen- und Kontaktdaten entfernt.',
    'uebersicht': {
        'slug': 'dioezesanlager', 'title': 'Diözesanlager',
        'seo_description': 'Lager für alle Stufen aus dem ganzen Diözesanverband – und was davon bleibt.',
        'body': '<p>Beim Diözesanlager zelten Stämme aus dem ganzen Diözesanverband gemeinsam: Biber, Wölflinge, Jungpfadfinder*innen, Pfadfinder*innen, Rover und Leitende. Ein gemeinsames Programm, Abende in der Arena und viele neue Freund*innen.</p><p>Hier findest du die Seiten vergangener Lager als Archiv. Das nächste Diözesanlager ist in Planung (2027 oder 2028). Sobald es mehr dazu gibt, steht es hier.</p>',
    },
    'seiten': seiten,
    'beitraege': beitraege,
    'medien': medien,
}
(ZIEL / 'inhalt.json').write_text(json.dumps(inhalt, ensure_ascii=False, indent=1) + '\n')
print('Seiten', len(seiten), 'Beiträge', len(beitraege), 'Medien', len(medien), 'FAQ', {k: len(v) for k, v in faq_gruppen.items()})
