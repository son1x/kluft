"""Fahrtkostenabrechnung 2026 als ausfüllbares, selbst rechnendes PDF.

Neu erzeugen (braucht pypdf):
  python3 fahrtkosten-formular.py fahrtkostenabrechnung-2026-vorlage.pdf fahrtkostenabrechnung-2026.pdf

Legt über die Vorlage (Druck aus Word) Formularfelder mit JavaScript:
Kilometer summieren, Auszahlungsbetrag (0,30 €/km) berechnen, IBAN in
Vierergruppen formatieren und per Prüfziffer (mod 97) prüfen.
"""
import sys
from pypdf import PdfReader, PdfWriter
from pypdf.generic import (ArrayObject, BooleanObject, DictionaryObject, FloatObject,
                           NameObject, NumberObject, TextStringObject)

SRC, DST = sys.argv[1], sys.argv[2]
H = 841.9
ROWS = [299.7, 319.3, 339.0, 358.6, 378.3, 397.9, 417.6, 437.3, 456.9, 476.6, 496.2, 515.9, 535.6, 555.2]
COLS = [53.0, 98.2, 268.1, 373.1, 435.5]
SATZ = 0.30

reader = PdfReader(SRC)
writer = PdfWriter()
writer.append(reader)
page = writer.pages[0]

font = DictionaryObject({NameObject('/Type'): NameObject('/Font'), NameObject('/Subtype'): NameObject('/Type1'),
                         NameObject('/BaseFont'): NameObject('/Helvetica'), NameObject('/Encoding'): NameObject('/WinAnsiEncoding')})
font_ref = writer._add_object(font)

def js(code):
    return DictionaryObject({NameObject('/S'): NameObject('/JavaScript'), NameObject('/JS'): TextStringObject(code)})

fields = []

def feld(name, x0, top, x1, bottom, *, size=10, align=0, readonly=False, tip='', aa=None, maxlen=None):
    rect = [x0 + 1.5, H - bottom + 1, x1 - 1.5, H - top - 1]
    d = DictionaryObject({
        NameObject('/Type'): NameObject('/Annot'), NameObject('/Subtype'): NameObject('/Widget'),
        NameObject('/FT'): NameObject('/Tx'), NameObject('/T'): TextStringObject(name),
        NameObject('/Rect'): ArrayObject([FloatObject(v) for v in rect]),
        NameObject('/DA'): TextStringObject(f'/Helv {size} Tf 0 g'),
        NameObject('/Q'): NumberObject(align), NameObject('/F'): NumberObject(4),
        NameObject('/Ff'): NumberObject(1 if readonly else 0),
        NameObject('/MK'): DictionaryObject(),
        NameObject('/P'): page.indirect_reference,
    })
    if tip:
        d[NameObject('/TU')] = TextStringObject(tip)
    if maxlen:
        d[NameObject('/MaxLen')] = NumberObject(maxlen)
    if aa:
        d[NameObject('/AA')] = DictionaryObject({NameObject('/' + k): js(v) for k, v in aa.items()})
    ref = writer._add_object(d)
    fields.append(ref)
    return ref

ZAHL = 'function zahl(f){var v=String(this.getField(f).value).replace(/\\s/g,"").replace(",",".");var n=parseFloat(v);return isNaN(n)?0:Math.round(n*10)/10;}'
SUMME = ZAHL + 'var s=0;for(var i=1;i<=%d;i++){s+=zahl.call(this,"km"+i);}' % (len(ROWS) - 1)
KM_TASTE = 'if(!event.willCommit){event.rc=/^[0-9.,]*$/.test(event.change);}'
KM_FORMAT = 'var v=String(event.value).replace(",",".");var n=parseFloat(v);event.value=(v===""||isNaN(n))?"":String(Math.round(n*10)/10).replace(".",",");'
DATUM_FORMAT = ('var v=String(event.value).replace(/\\s/g,"");var m=v.match(/^(\\d{1,2})[.\\/-](\\d{1,2})[.\\/-]?(\\d{2,4})?$/);'
                'if(m){var j=m[3]?(m[3].length===4?m[3].substr(2):("0"+m[3]).slice(-2)):String(new Date().getFullYear()).substr(2);'
                'event.value=("0"+m[1]).slice(-2)+"."+("0"+m[2]).slice(-2)+"."+j;}')
IBAN_TASTE = 'if(!event.willCommit){event.change=event.change.toUpperCase();event.rc=/^[A-Z0-9 ]*$/.test(event.change);}'
IBAN_FORMAT = 'var v=String(event.value).replace(/\\s/g,"").toUpperCase();event.value=v.replace(/(.{4})/g,"$1 ").replace(/\\s+$/,"");'
IBAN_PRUEFEN = ('var v=String(event.value).replace(/\\s/g,"").toUpperCase();if(v!==""){var ok=/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/.test(v)&&(v.substr(0,2)!=="DE"||v.length===22);'
                'if(ok){var r=v.substr(4)+v.substr(0,4),z="";for(var i=0;i<r.length;i++){var c=r.charAt(i);z+=(c>="A"&&c<="Z")?String(c.charCodeAt(0)-55):c;}'
                'var rest=0;for(var k=0;k<z.length;k+=7){rest=parseInt(String(rest)+z.substr(k,7),10)%97;}ok=(rest===1);}'
                'if(!ok){app.alert("Die IBAN scheint nicht zu stimmen. Bitte noch einmal prüfen.",1);}}')

# Kopf
feld('name', 100, 160, 355, 175, size=11, tip='Name')
feld('iban', 100, 183, 355, 198, size=11, tip='IBAN (wird automatisch in Vierergruppen formatiert)', maxlen=42,
     aa={'K': IBAN_TASTE, 'F': IBAN_FORMAT, 'V': IBAN_PRUEFEN})

# Tabelle
for i in range(len(ROWS) - 1):
    top, bottom = ROWS[i], ROWS[i + 1]
    n = i + 1
    feld(f'datum{n}', COLS[0], top, COLS[1], bottom, size=9, align=1, tip=f'Datum, Zeile {n} (z. B. 12.3.)', aa={'F': DATUM_FORMAT})
    feld(f'strecke{n}', COLS[1], top, COLS[2], bottom, size=9, tip=f'Fahrtstrecke von > über > nach, Zeile {n}')
    feld(f'anlass{n}', COLS[2], top, COLS[3], bottom, size=9, tip=f'Anlass der Fahrt, Zeile {n}')
    feld(f'km{n}', COLS[3], top, COLS[4], bottom, size=10, align=2, tip=f'Gefahrene Kilometer, Zeile {n}', aa={'K': KM_TASTE, 'F': KM_FORMAT})

summe = feld('summe_km', COLS[3], 555.2, COLS[4], 568.5, size=10, align=2, readonly=True, tip='Gesamtsumme der Kilometer (wird berechnet)',
             aa={'C': SUMME + 'event.value=s?String(Math.round(s*10)/10).replace(".",","):"";'})
betrag = feld('betrag', COLS[3], 580.0, COLS[4], 594.5, size=10, align=2, readonly=True, tip='Auszahlungsbetrag (0,30 € pro km, wird berechnet)',
              aa={'C': SUMME + 's=Math.round(s*10)/10;event.value=s?(Math.round(s*%s*100)/100).toFixed(2).replace(".",","):"";' % SATZ})

# Unterschriftszeile
feld('ort_datum', 53.0, 668, 134.7, 690, size=10, tip='Ort, Datum')

page[NameObject('/Annots')] = ArrayObject(fields)
writer._root_object[NameObject('/AcroForm')] = DictionaryObject({
    NameObject('/Fields'): ArrayObject(fields),
    NameObject('/NeedAppearances'): BooleanObject(True),
    NameObject('/DA'): TextStringObject('/Helv 10 Tf 0 g'),
    NameObject('/DR'): DictionaryObject({NameObject('/Font'): DictionaryObject({NameObject('/Helv'): font_ref})}),
    NameObject('/CO'): ArrayObject([summe, betrag]),
})
writer.add_metadata({'/Title': 'Fahrtkostenabrechnung Ehrenamt 2026 – DPSG Diözesanverband Trier', '/Author': 'DPSG Diözesanverband Trier'})
with open(DST, 'wb') as f:
    writer.write(f)
print('ok', len(fields), 'Felder')
