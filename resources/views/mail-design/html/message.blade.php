{{-- Auf dem Testsystem führt „Website“ zur echten Website (Live bzw. vor dem Livegang die alte), nicht zum geschlossenen Test. --}}
@php($website = (\App\Support\Testsystem::aktiv() ? \App\Support\Testsystem::zurueckUrl() : null) ?? config('app.url'))
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="$website">
{{ config('dpsg.site.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer: Absender, Kontakt, Impressum und Datenschutz --}}
<x-slot:footer>
<x-mail::footer>
**{{ config('dpsg.contact.name') ?: config('dpsg.site.name') }}**<br>
{{ collect([config('dpsg.contact.office'), config('dpsg.contact.street'), config('dpsg.contact.city')])->filter()->implode(' · ') }}<br>
@if (config('dpsg.contact.email'))[{{ config('dpsg.contact.email') }}](mailto:{{ config('dpsg.contact.email') }})@endif @if (config('dpsg.contact.phone')) · Tel. {{ config('dpsg.contact.phone') }}@endif<br>
[Website]({{ $website }}) · [Impressum]({{ url('/impressum') }}) · [Datenschutz]({{ url('/datenschutz') }})
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
