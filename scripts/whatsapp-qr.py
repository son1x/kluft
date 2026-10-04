#!/usr/bin/env python3
"""QR-Code für die WhatsApp-Anmeldung als statisches SVG erzeugen.

Läuft nur beim Entwickeln (pip install segno), nicht auf dem Webspace.
Aufruf:
  Business (Cloud API): python3 scripts/whatsapp-qr.py "https://wa.me/4965146298290?text=START"
  Kanal:                python3 scripts/whatsapp-qr.py "https://whatsapp.com/channel/…" kanal-qr.svg
Die Seite /whatsapp zeigt den QR-Code nur, wenn die URL im SVG zur Konfiguration passt.
"""
import io
import sys

import segno

url = sys.argv[1]
out = sys.argv[2] if len(sys.argv) > 2 else 'anmelden-qr.svg'
qr = segno.make(url, error='m')
buf = io.BytesIO()
qr.save(buf, kind='svg', scale=8, border=2, dark='#003056', light='#ffffff', xmldecl=False, svgns=True, title='QR-Code: WhatsApp-Newsletter')
svg = buf.getvalue().decode()
svg = svg.replace('<svg ', f'<!-- data: {url} -->\n<svg role="img" ', 1)
open('public/images/whatsapp/' + out, 'w').write(svg + '\n')
print('geschrieben:', url)
