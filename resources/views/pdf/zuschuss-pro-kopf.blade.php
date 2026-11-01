{{-- Pro-Kopf-Zuschuss der Kreisstadt Saarlouis: namentliche Auflistung der
     jugendlichen Mitglieder mit Richtigkeitsbestätigung (§ 13 Abs. 2). --}}
<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"><title>Pro-Kopf-Zuschuss</title>@include('pdf.partials.zuschuss-stil')</head>
<body>
<h1>Antrag auf Pro-Kopf-Zuschuss {{ $stichtag->year }}</h1>
<p class="unter">an die Kreisstadt Saarlouis, Stadtamt 85, Abteilung Jugend · Richtlinien zur Förderung der außerschulischen Jugendarbeit §§ 12–15 · Antrag bis spätestens 15.02. (Ausschlussfrist)</p>
<table class="f">
    <tr><td class="l">Antragsteller</td><td><strong>{{ $as['name'] }}</strong>, {{ $as['anschrift'] }}</td></tr>
    <tr><td class="l">Bankverbindung</td><td>{{ $as['inhaber'] }}{{ $as['iban'] ? ', IBAN '.$as['iban'] : '' }}</td></tr>
    <tr><td class="l">Jugendliche Mitglieder am {{ $stichtag->format('d.m.Y') }}</td><td><strong>{{ count($personen) }}</strong> (Personen bis zum vollendeten 18. Lebensjahr)</td></tr>
</table>
<table class="f">
    <tr><th class="kopf" style="width:7mm">Nr.</th><th class="kopf">Name</th><th class="kopf">Vorname</th><th class="kopf">Wohnanschrift</th><th class="kopf">Geburtsdatum</th></tr>
    @foreach ($personen as $i => $p)
        <tr><td class="z">{{ $i + 1 }}</td><td>{{ $p['nachname'] }}</td><td>{{ $p['vorname'] }}</td><td>{{ trim($p['strasse'].', '.$p['plz'].' '.$p['ort'], ', ') }}</td><td>{{ $p['geburtsdatum']->format('d.m.Y') }}</td></tr>
    @endforeach
</table>
<p style="margin-top:4mm">Die Richtigkeit der Angaben wird bestätigt.</p>
<table class="sig"><tr><td><div class="linie">Datum, Unterschrift Vorsitzende*r</div></td><td><div class="linie">Datum, Unterschrift Kassierer*in</div></td></tr></table>
<p class="hinweis">Mindestzahl 5 Jugendliche (§ 14). Die Höhe ergibt sich aus den Haushaltsmitteln und der Zahl der gemeldeten Jugendlichen; Richtwert {{ number_format(\App\Services\Zuschuss\ProKopfListe::PAUSCHALE_CENT / 100, 2, ',', '.') }} € je Kopf, hier also etwa {{ number_format(count($personen) * \App\Services\Zuschuss\ProKopfListe::PAUSCHALE_CENT / 100, 2, ',', '.') }} €. Erstellt mit Kluft aus dem Mitglieder-Export aus NaMi; die Daten wurden nicht gespeichert.</p>
</body></html>
