{{-- Gemeinsamer Stil der Zuschuss-Unterlagen (dompdf). Erwartet: $fonts --}}
<style>
    @font-face { font-family: 'Arvo'; font-weight: bold; src: url('{{ $fonts }}/Arvo-Bold.ttf') format('truetype'); }
    @font-face { font-family: 'Carlito'; font-weight: normal; src: url('{{ $fonts }}/Carlito-Regular.ttf') format('truetype'); }
    @font-face { font-family: 'Carlito'; font-weight: bold; src: url('{{ $fonts }}/Carlito-Bold.ttf') format('truetype'); }
    @font-face { font-family: 'Carlito'; font-style: italic; src: url('{{ $fonts }}/Carlito-Italic.ttf') format('truetype'); }
    @page { margin: 13mm 15mm 14mm; }
    body { font-family: 'Carlito', sans-serif; font-size: 9.5pt; color: #1c1f26; }
    h1 { font-family: 'Arvo', serif; font-size: 14pt; margin: 0 0 1mm; }
    .unter { font-size: 8.5pt; color: #444; margin: 0 0 3mm; }
    table.f { width: 100%; border-collapse: collapse; margin-top: 2.5mm; }
    table.f th, table.f td { border: 0.25mm solid #555; padding: 1.1mm 1.6mm; text-align: left; vertical-align: top; }
    table.f th.kopf { background: #d9d9d9; font-weight: bold; }
    table.f td.l { width: 38mm; color: #333; }
    table.f td.z, table.f th.z { text-align: right; white-space: nowrap; }
    .box { display: inline-block; width: 2.8mm; height: 2.8mm; border: 0.25mm solid #333; text-align: center; line-height: 2.8mm; font-size: 8pt; margin-right: 1mm; }
    .klein { font-size: 7.5pt; color: #444; }
    .sig { margin-top: 12mm; width: 100%; }
    .sig td { width: 50%; padding-right: 10mm; vertical-align: bottom; }
    .sig .linie { border-top: 0.3mm solid #1c1f26; padding-top: 1mm; font-size: 8pt; }
    .neu { page-break-before: always; }
    .hinweis { font-size: 7.5pt; color: #555; margin-top: 3mm; }
</style>
