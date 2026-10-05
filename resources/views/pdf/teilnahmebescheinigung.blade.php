{{-- Teilnahmebescheinigung (A4) im Corporate Design der DPSG: Arvo für
     Überschriften, Calibri-kompatible Carlito für Text, DPSG-Blau #003056,
     Rot #810a1a (Unterstrich/Wegzeichen), Hellbeige #ecdfcb.
     Gerendert mit dompdf (kein Flexbox; feste Positionen in mm). --}}
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Teilnahmebescheinigung</title>
<style>
    @font-face { font-family: 'Arvo'; font-weight: normal; src: url('{{ $fonts }}/Arvo-Regular.ttf') format('truetype'); }
    @font-face { font-family: 'Arvo'; font-weight: bold; src: url('{{ $fonts }}/Arvo-Bold.ttf') format('truetype'); }
    @font-face { font-family: 'Carlito'; font-weight: normal; src: url('{{ $fonts }}/Carlito-Regular.ttf') format('truetype'); }
    @font-face { font-family: 'Carlito'; font-weight: bold; src: url('{{ $fonts }}/Carlito-Bold.ttf') format('truetype'); }
    @font-face { font-family: 'Carlito'; font-style: italic; src: url('{{ $fonts }}/Carlito-Italic.ttf') format('truetype'); }

    @page { margin: 0; }
    body { margin: 0; font-family: 'Carlito', sans-serif; font-size: 11.5pt; color: #1c1f26; }
    .blau { color: #003056; }
    .serif { font-family: 'Arvo', serif; }

    .kopf { position: absolute; top: 14mm; left: 22mm; right: 22mm; height: 32mm; }
    .absender { position: absolute; left: 0; bottom: 2mm; font-size: 9pt; color: #5c6670; }
    .absender strong { color: #003056; font-size: 10.5pt; }
    .logo { position: absolute; right: 0; top: 0; height: 30mm; }
    .linie { position: absolute; top: 50mm; left: 22mm; right: 22mm; border-top: 1.2mm solid #810a1a; }

    .inhalt { position: absolute; top: 60mm; left: 22mm; right: 22mm; }
    h1 { font-family: 'Arvo', serif; font-weight: bold; font-size: 30pt; color: #003056; margin: 0 0 9mm; letter-spacing: 0.3pt; }
    .klein { font-size: 12pt; margin: 0; }
    .name { font-family: 'Arvo', serif; font-weight: bold; font-size: 22pt; color: #1c1f26; margin: 2mm 0 5mm; }
    .veranstaltung { font-family: 'Arvo', serif; font-weight: bold; font-size: 16pt; color: #003056; margin: 3mm 0 1.5mm; }
    .wann { font-size: 12pt; margin: 0 0 5mm; }
    .box { background: #ecdfcb; padding: 4mm 5mm; margin-top: 6mm; font-size: 11pt; line-height: 1.35; }
    .box .titel { font-family: 'Arvo', serif; font-weight: bold; font-size: 10.5pt; color: #003056; margin-bottom: 1.5mm; }

    .unterschrift { margin-top: 12mm; width: 85mm; }
    .unterschrift .ort { font-size: 11pt; margin-bottom: 11mm; }
    .unterschrift .strich { border-top: 0.3mm solid #1c1f26; padding-top: 1.5mm; font-size: 10.5pt; }
    .unterschrift .hinweis { font-size: 8.5pt; color: #5c6670; font-style: italic; margin-top: 1mm; }

    .pruefung { position: absolute; bottom: 24mm; left: 22mm; right: 22mm; height: 30mm; border: 0.3mm solid #c7bdad; }
    .pruefung img { position: absolute; left: 3mm; top: 3mm; width: 24mm; height: 24mm; }
    .pruefung .text { position: absolute; left: 31mm; right: 4mm; top: 3.5mm; font-size: 9.5pt; line-height: 1.35; color: #1c1f26; }
    .pruefung .code { font-family: 'Arvo', serif; font-weight: bold; font-size: 13pt; color: #003056; letter-spacing: 0.6pt; }

    .fuss { position: absolute; bottom: 0; left: 0; right: 0; height: 18mm; background: #003056; color: #ffffff; font-size: 8.5pt; }
    .fuss .zeile { position: absolute; left: 22mm; right: 22mm; top: 6mm; }
    .fuss .akzent { position: absolute; top: 0; left: 0; right: 0; height: 1.2mm; background: #810a1a; }
</style>
</head>
<body>
    <div class="kopf">
        <div class="absender">
            <strong>{{ config('dpsg.site.name') }}</strong><br>
            {{ config('dpsg.contact.office') }} · {{ config('dpsg.contact.street') }} · {{ config('dpsg.contact.city') }}
        </div>
        @if ($logo)<img class="logo" src="{{ $logo }}" alt="">@endif
    </div>
    <div class="linie"></div>

    <div class="inhalt">
        <h1>Teilnahmebescheinigung</h1>

        <p class="klein">Hiermit bestätigen wir, dass</p>
        <p class="name">{{ $registration->fullName() }}</p>

        <p class="klein">an der Veranstaltung</p>
        <p class="veranstaltung">{{ $event->title }}</p>
        <p class="wann">{{ $event->dateLabel() }}@if ($event->location) · {{ $event->location }}@endif</p>

        <p class="klein">teilgenommen hat.</p>

        @if (filled($event->certificate_text))
            <div class="box">
                <div class="titel">Inhalte und Umfang</div>
                {!! nl2br(e($event->certificate_text)) !!}
            </div>
        @endif

        <div class="unterschrift">
            <div class="ort">{{ $ort ? $ort.', ' : '' }}{{ $datum }}</div>
            <div class="strich">
                {{ $event->certificate_signer ?: config('dpsg.site.name') }}
                <div class="hinweis">Elektronisch erstellt und ohne Unterschrift gültig.</div>
            </div>
        </div>
    </div>

    <div class="pruefung">
        <img src="{{ $qr }}" alt="">
        <div class="text">
            <span class="code">{{ $code }}</span><br>
            Echtheit prüfen: QR-Code scannen oder <strong>{{ preg_replace('#^https?://#', '', route('certificates.verify')) }}</strong> aufrufen
            und Prüfcode sowie Namen eingeben. Der Prüfcode gilt nur zusammen mit dem Namen auf dieser Bescheinigung.
        </div>
    </div>

    <div class="fuss">
        <div class="akzent"></div>
        <div class="zeile">
            {{ config('dpsg.contact.name') }} · {{ config('dpsg.contact.street') }} · {{ config('dpsg.contact.city') }}
            @if (config('dpsg.contact.phone')) · Tel. {{ config('dpsg.contact.phone') }}@endif
            · {{ config('dpsg.contact.email') }} · {{ config('dpsg.site.domain') }}
        </div>
    </div>
</body>
</html>
