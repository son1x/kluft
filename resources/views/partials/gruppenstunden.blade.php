{{-- Gruppenstunden aller Stufen für die Startseite (Verwaltung → Gruppenstunden).
     Je Stufe eine Zeile, mehrere Gruppen einer Stufe untereinander.
     Erwartet: $gruppen (Collection<Gruppenstunde>) --}}
@php($schnuppern = \App\Models\ContactTopic::urlFor('slug', 'mitmachen') ?? url('/'.config('dpsg.contact_page')))
<section class="mx-auto mt-16 max-w-6xl px-4" aria-labelledby="gruppenstunden-heading">
    @include('partials.section-heading', ['id' => 'gruppenstunden-heading', 'title' => 'Unsere Gruppenstunden'])

    <div class="divide-y divide-surface-muted overflow-hidden rounded-lg bg-white shadow-md">
        @foreach ($gruppen->groupBy('stage') as $stage => $stufenGruppen)
            @php($s = config('dpsg.stages.'.$stage))
            <div class="stufe-{{ $stage }} grid gap-3 border-l-8 border-(--stufe) px-5 py-4 md:grid-cols-[14rem_1fr] md:items-start">
                <div>
                    <a href="{{ url('/stufe-'.$stage) }}" class="font-serif text-lg font-bold hover:text-dpsg-rot">{{ $s['label'] }}</a>
                    <span class="block text-sm text-text-secondary">{{ $s['age'] }}</span>
                </div>
                <ul class="space-y-2">
                    @foreach ($stufenGruppen as $gruppe)
                        <li class="grid gap-x-6 sm:grid-cols-[1fr_1fr]">
                            <span>
                                @if ($gruppe->name)<span class="font-bold">{{ $gruppe->name }}:</span> @endif
                                <span class="font-bold">{{ $gruppe->zeitText() ?: $gruppe->hinweis }}</span>
                                @if ($gruppe->zeitText() && $gruppe->hinweis)<span class="text-sm text-text-secondary">({{ $gruppe->hinweis }})</span>@endif
                            </span>
                            <span class="text-sm text-text-secondary">{{ $gruppe->ortText() }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
    <p class="mt-4 text-text-secondary">
        Komm einfach mal vorbei – sag uns vorher kurz Bescheid:
        <a href="{{ $schnuppern }}" class="font-bold text-dpsg-rot underline">Schnupperstunde anfragen</a>
    </p>
</section>
