"""Abrechnungsformulare 2026 (Fahrt-, Reise-, Sachkosten) als ausfüllbare,
selbst rechnende PDFs.

Neu erzeugen (braucht pypdf und reportlab, Schrift Trebuchet MS Bold):
  python3 abrechnungsformulare.py            # alle drei
  python3 abrechnungsformulare.py sachkosten # nur eines

Grundlage sind die Vorlagen *-2026-vorlage.pdf (Druck der Word-Fassungen
*-2026.docx). Darüber kommen eine Überschrift und Formularfelder mit
JavaScript: Zeilen summieren, Auszahlungsbetrag berechnen, IBAN in
Vierergruppen formatieren und per Prüfziffer (mod 97) prüfen, Datum
vereinheitlichen. Die Feldpositionen sind aus den Vorlagen ausgemessen;
wer eine Vorlage ändert, muss sie hier anpassen.
"""
import glob
import io
import os
import sys

from pypdf import PdfReader, PdfWriter
from pypdf.generic import (ArrayObject, BooleanObject, DictionaryObject, FloatObject,
                           NameObject, NumberObject, TextStringObject)
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfgen import canvas

H = 841.9
SATZ_KM = 0.30
ORDNER = os.path.dirname(os.path.abspath(__file__))


def zeilen(start, ende, n=13):
    schritt = (ende - start) / n
    return [round(start + i * schritt, 1) for i in range(n + 1)]


FORMULARE = {
    'fahrtkosten': {
        'vorlage': 'fahrtkostenabrechnung-2026-vorlage.pdf',
        'ziel': 'fahrtkostenabrechnung-2026.pdf',
        'titel': 'Fahrtkostenabrechnung / Ehrenamt',
        'art': 'km',
        'tabelle_oben': 273.6,
        'spalten': [53.0, 98.2, 268.1, 373.1, 435.5],
        'felder': [('strecke', 'Fahrtstrecke von > über > nach'), ('anlass', 'Anlass der Fahrt')],
        'zeilen': zeilen(299.7, 555.2),
        'zeile_summe': (555.2, 568.5),
        'zeile_betrag': (580.0, 594.5),
        'kopf': 160.0,
        'unterschrift': (668.0, 690.0),
    },
    'reisekosten': {
        'vorlage': 'reisekostenabrechnung-2026-vorlage.pdf',
        'ziel': 'reisekostenabrechnung-2026.pdf',
        'titel': 'Reisekostenabrechnung / Ehrenamt',
        'art': 'euro',
        'tabelle_oben': 273.7,
        'spalten': [52.9, 98.1, 269.3, 375.1, 435.4],
        'felder': [('ziel', 'Fahrtziel (Bahn, Bus, Bahncard)'), ('anlass', 'Anlass der Fahrt')],
        'zeilen': zeilen(299.7, 553.9),
        'zeile_summe': (553.9, 567.1),
        'zeile_betrag': (579.0, 593.2),
        'kopf': 157.4,
        'unterschrift': (663.0, 685.0),
    },
    'sachkosten': {
        'vorlage': 'sachkostenabrechnung-2026-vorlage.pdf',
        'ziel': 'sachkostenabrechnung-2026.pdf',
        'titel': 'Sachkostenabrechnung / Ehrenamt',
        'art': 'euro',
        'tabelle_oben': 273.7,
        'spalten': [52.9, 98.1, 269.3, 375.1, 435.4],
        'felder': [('ausgabeart', 'Ausgabeart (Telefon, Porto, Büromaterial, Geschenke, Bewirtung)'), ('grund', 'Grund der Ausgabe')],
        'zeilen': zeilen(312.5, 566.6),
        'zeile_summe': (566.6, 579.9),
        'zeile_betrag': (591.7, 605.9),
        'kopf': 157.4,
        'unterschrift': (676.0, 698.0),
    },
}

# --- JavaScript (Acrobat/Reader, Firefox, Chrome) ---------------------------

def zahl_js(feld, stellen):
    return ('function zahl(f){var v=String(this.getField(f).value).replace(/\\s/g,"").replace(",",".");'
            'var n=parseFloat(v);return isNaN(n)?0:Math.round(n*%d)/%d;}' % (10 ** stellen, 10 ** stellen))

ZAHL_TASTE = 'if(!event.willCommit){event.rc=/^[0-9.,]*$/.test(event.change);}'
KM_FORMAT = 'var v=String(event.value).replace(",",".");var n=parseFloat(v);event.value=(v===""||isNaN(n))?"":String(Math.round(n*10)/10).replace(".",",");'
EURO_FORMAT = 'var v=String(event.value).replace(",",".");var n=parseFloat(v);event.value=(v===""||isNaN(n))?"":n.toFixed(2).replace(".",",");'
DATUM_FORMAT = ('var v=String(event.value).replace(/\\s/g,"");var m=v.match(/^(\\d{1,2})[.\\/-](\\d{1,2})[.\\/-]?(\\d{2,4})?$/);'
                'if(m){var j=m[3]?(m[3].length===4?m[3].substr(2):("0"+m[3]).slice(-2)):String(new Date().getFullYear()).substr(2);'
                'event.value=("0"+m[1]).slice(-2)+"."+("0"+m[2]).slice(-2)+"."+j;}')
IBAN_TASTE = 'if(!event.willCommit){event.change=event.change.toUpperCase();event.rc=/^[A-Z0-9 ]*$/.test(event.change);}'
IBAN_FORMAT = 'var v=String(event.value).replace(/\\s/g,"").toUpperCase();event.value=v.replace(/(.{4})/g,"$1 ").replace(/\\s+$/,"");'
IBAN_PRUEFEN = ('var v=String(event.value).replace(/\\s/g,"").toUpperCase();if(v!==""){var ok=/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/.test(v)&&(v.substr(0,2)!=="DE"||v.length===22);'
                'if(ok){var r=v.substr(4)+v.substr(0,4),z="";for(var i=0;i<r.length;i++){var c=r.charAt(i);z+=(c>="A"&&c<="Z")?String(c.charCodeAt(0)-55):c;}'
                'var rest=0;for(var k=0;k<z.length;k+=7){rest=parseInt(String(rest)+z.substr(k,7),10)%97;}ok=(rest===1);}'
                'if(!ok){app.alert("Die IBAN scheint nicht zu stimmen. Bitte noch einmal prüfen.",1);}}')


def ueberschrift(text, x, top):
    """Einseitiges PDF mit der Überschrift (Trebuchet MS Bold) zum Überlagern."""
    schrift = next(iter(glob.glob(os.path.expanduser('~/.fonts/trebucbd.ttf')) + glob.glob('/usr/share/fonts/**/trebucbd.ttf', recursive=True)), None)
    if not schrift:
        sys.exit('Schrift Trebuchet MS Bold (trebucbd.ttf) nicht gefunden.')
    pdfmetrics.registerFont(TTFont('TrebuchetBold', schrift))
    puffer = io.BytesIO()
    c = canvas.Canvas(puffer, pagesize=(595.3, H))
    c.setFont('TrebuchetBold', 13)
    c.drawString(x, H - top, text)
    c.save()
    puffer.seek(0)
    return PdfReader(puffer).pages[0]


def erzeugen(schluessel, f):
    writer = PdfWriter()
    writer.append(PdfReader(os.path.join(ORDNER, f['vorlage'])))
    page = writer.pages[0]
    sp, zl = f['spalten'], f['zeilen']
    page.merge_page(ueberschrift(f['titel'], sp[0], f['tabelle_oben'] - 11))

    font = DictionaryObject({NameObject('/Type'): NameObject('/Font'), NameObject('/Subtype'): NameObject('/Type1'),
                             NameObject('/BaseFont'): NameObject('/Helvetica'), NameObject('/Encoding'): NameObject('/WinAnsiEncoding')})
    font_ref = writer._add_object(font)
    felder = []

    def js(code):
        return DictionaryObject({NameObject('/S'): NameObject('/JavaScript'), NameObject('/JS'): TextStringObject(code)})

    def feld(name, x0, top, x1, bottom, *, size=10, align=0, readonly=False, tip='', aa=None, maxlen=None):
        d = DictionaryObject({
            NameObject('/Type'): NameObject('/Annot'), NameObject('/Subtype'): NameObject('/Widget'),
            NameObject('/FT'): NameObject('/Tx'), NameObject('/T'): TextStringObject(name),
            NameObject('/Rect'): ArrayObject([FloatObject(v) for v in (x0 + 1.5, H - bottom + 1, x1 - 1.5, H - top - 1)]),
            NameObject('/DA'): TextStringObject(f'/Helv {size} Tf 0 g'),
            NameObject('/Q'): NumberObject(align), NameObject('/F'): NumberObject(4),
            NameObject('/Ff'): NumberObject(1 if readonly else 0),
            NameObject('/MK'): DictionaryObject(), NameObject('/P'): page.indirect_reference,
        })
        if tip:
            d[NameObject('/TU')] = TextStringObject(tip)
        if maxlen:
            d[NameObject('/MaxLen')] = NumberObject(maxlen)
        if aa:
            d[NameObject('/AA')] = DictionaryObject({NameObject('/' + k): js(v) for k, v in aa.items()})
        ref = writer._add_object(d)
        felder.append(ref)
        return ref

    # Kopf: Name und IBAN
    kopf = f['kopf']
    feld('name', 100, kopf, 355, kopf + 15, size=11, tip='Name')
    feld('iban', 100, kopf + 23, 355, kopf + 38, size=11, tip='IBAN (wird automatisch in Vierergruppen formatiert)', maxlen=42,
         aa={'K': IBAN_TASTE, 'F': IBAN_FORMAT, 'V': IBAN_PRUEFEN})

    km = f['art'] == 'km'
    wert = 'km' if km else 'betrag'
    for i in range(len(zl) - 1):
        top, bottom, n = zl[i], zl[i + 1], i + 1
        feld(f'datum{n}', sp[0], top, sp[1], bottom, size=9, align=1, tip=f'Datum, Zeile {n} (z. B. 12.3.)', aa={'F': DATUM_FORMAT})
        (a, a_tip), (b, b_tip) = f['felder']
        feld(f'{a}{n}', sp[1], top, sp[2], bottom, size=9, tip=f'{a_tip}, Zeile {n}')
        feld(f'{b}{n}', sp[2], top, sp[3], bottom, size=9, tip=f'{b_tip}, Zeile {n}')
        feld(f'{wert}{n}', sp[3], top, sp[4], bottom, size=10, align=2,
             tip=f'Gefahrene Kilometer, Zeile {n}' if km else f'Betrag in €, Zeile {n}',
             aa={'K': ZAHL_TASTE, 'F': KM_FORMAT if km else EURO_FORMAT})

    stellen = 1 if km else 2
    summe = zahl_js(wert, stellen) + 'var s=0,a=0;for(var i=1;i<=%d;i++){var w=zahl.call(this,"%s"+i);s+=w;if(w>0){a++;}}' % (len(zl) - 1, wert)
    if km:
        oben = feld('summe_km', sp[3], f['zeile_summe'][0], sp[4], f['zeile_summe'][1], size=10, align=2, readonly=True,
                    tip='Gesamtsumme der Kilometer (wird berechnet)',
                    aa={'C': summe + 'event.value=s?String(Math.round(s*10)/10).replace(".",","):"";'})
        unten = feld('betrag', sp[3], f['zeile_betrag'][0], sp[4], f['zeile_betrag'][1], size=10, align=2, readonly=True,
                     tip='Auszahlungsbetrag (0,30 € pro km, wird berechnet)',
                     aa={'C': summe + 's=Math.round(s*10)/10;event.value=s?(Math.round(s*%s*100)/100).toFixed(2).replace(".",","):"";' % SATZ_KM})
    else:
        oben = feld('anzahl_belege', sp[3], f['zeile_summe'][0], sp[4], f['zeile_summe'][1], size=10, align=2, readonly=True,
                    tip='Anzahl der Belege (Zeilen mit Betrag, wird berechnet)',
                    aa={'C': summe + 'event.value=a?String(a):"";'})
        unten = feld('auszahlung', sp[3], f['zeile_betrag'][0], sp[4], f['zeile_betrag'][1], size=10, align=2, readonly=True,
                     tip='Auszahlungsbetrag, Summe der Belege (wird berechnet)',
                     aa={'C': summe + 'event.value=s?(Math.round(s*100)/100).toFixed(2).replace(".",","):"";'})

    feld('ort_datum', sp[0], f['unterschrift'][0], 134.5, f['unterschrift'][1], size=10, tip='Ort, Datum')

    page[NameObject('/Annots')] = ArrayObject(felder)
    writer._root_object[NameObject('/AcroForm')] = DictionaryObject({
        NameObject('/Fields'): ArrayObject(felder),
        NameObject('/NeedAppearances'): BooleanObject(True),
        NameObject('/DA'): TextStringObject('/Helv 10 Tf 0 g'),
        NameObject('/DR'): DictionaryObject({NameObject('/Font'): DictionaryObject({NameObject('/Helv'): font_ref})}),
        NameObject('/CO'): ArrayObject([oben, unten]),
    })
    writer.add_metadata({'/Title': f['titel'].replace(' / ', ' ') + ' 2026 – DPSG Diözesanverband Trier', '/Author': 'DPSG Diözesanverband Trier'})
    with open(os.path.join(ORDNER, f['ziel']), 'wb') as out:
        writer.write(out)
    print(f'{f["ziel"]}: {len(felder)} Felder')


if __name__ == '__main__':
    auswahl = sys.argv[1:] or list(FORMULARE)
    for schluessel in auswahl:
        erzeugen(schluessel, FORMULARE[schluessel])
