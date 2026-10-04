#!/usr/bin/env python3
"""QR-Code für die WhatsApp-Anmeldung als statisches SVG erzeugen.

Läuft nur beim Entwickeln (pip install segno), nicht auf dem Webspace.
Aufruf: python3 scripts/whatsapp-qr.py "https://wa.me/4965146298290?text=START"
Ein Test prüft, dass die URL im SVG zu config/whatsapp.php passt.
"""
import io
import sys

import segno

url = sys.argv[1]
qr = segno.make(url, error='m')
buf = io.BytesIO()
qr.save(buf, kind='svg', scale=8, border=2, dark='#003056', light='#ffffff', xmldecl=False, svgns=True, title='QR-Code: WhatsApp-Newsletter mit START abonnieren')
svg = buf.getvalue().decode()
svg = svg.replace('<svg ', f'<!-- data: {url} -->\n<svg role="img" ', 1)
open('public/images/whatsapp/anmelden-qr.svg', 'w').write(svg + '\n')
print('geschrieben:', url)
