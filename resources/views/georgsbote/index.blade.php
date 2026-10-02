@extends('layouts.app')

@section('seo_title', 'Georgsbote – DPSG Diözesanverband Trier')
@section('seo_description', 'Der Georgsbote, die Zeitschrift des DPSG Diözesanverbands Trier: alle Ausgaben als PDF.')

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-10">
        <h1 class="font-serif text-3xl font-bold">Georgsbote</h1>

        @if ($intro)
            <div class="prose mt-4 max-w-3xl">
                {!! str($intro->body)->before('[TODO')->sanitizeHtml() !!}
            </div>
        @endif

        <div class="mt-10 grid gap-10 lg:grid-cols-3">
            <section class="lg:col-span-2" aria-labelledby="archiv-heading">
                @include('partials.section-heading', ['id' => 'archiv-heading', 'title' => 'Archiv'])

                @forelse ($jahrgaenge as $jahr => $ausgaben)
                    <h3 class="mb-4 mt-2 font-serif text-xl font-bold">Jahrgang {{ $jahr }}</h3>
                    <ul class="mb-10 grid grid-cols-2 gap-5 sm:grid-cols-3">
                        @foreach ($ausgaben as $ausgabe)
                            <li>
                                <a href="{{ $ausgabe->downloadUrl() }}" class="group block bg-white shadow-md transition hover:-translate-y-0.5 hover:shadow-lg" target="_blank" rel="noopener">
                                    <div class="flex aspect-[3/4] items-center justify-center overflow-hidden bg-dpsg-hellbeige">
                                        @if ($cover = $ausgabe->coverUrl())
                                            <img src="{{ $cover }}" alt="" class="h-full w-full object-cover" loading="lazy" />
                                        @else
                                            <span class="px-4 text-center font-serif text-2xl font-bold text-dpsg-blau">Georgsbote<br />{{ $ausgabe->issue_no }}/{{ $ausgabe->year }}</span>
                                        @endif
                                    </div>
                                    <div class="p-3">
                                        <span class="block text-xs text-text-secondary">{{ $ausgabe->label() }}</span>
                                        <span class="block font-bold group-hover:text-dpsg-rot">{{ $ausgabe->title }}</span>
                                        <span class="mt-1 block text-sm text-dpsg-rot">PDF öffnen</span>
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @empty
                    <p class="text-text-secondary">Die Ausgaben werden gerade ins Archiv übernommen.</p>
                @endforelse
            </section>

            {{-- Hinweis auf den Newsletter (Georgsbrief) --}}
            <aside aria-label="Georgsbrief" class="self-start overflow-hidden bg-white shadow-md">
                <a href="{{ route('georgsbrief.index') }}" class="block bg-[#eff2f7] p-4">
                    <picture>
                        <source type="image/webp" srcset="{{ asset('images/georgsbrief-logo.webp') }}" />
                        <img src="{{ asset('images/georgsbrief-logo.png') }}" alt="Georgsbrief – der DPSG Trier Newsletter" width="970" height="251" class="h-auto w-full" />
                    </picture>
                </a>
                <div class="p-5">
                    <p class="text-sm">Lieber regelmäßig kurz informiert? Der <strong>Georgsbrief</strong> ist unser Newsletter mit Terminen und Neuigkeiten aus dem Diözesanverband – direkt ins Postfach.</p>
                    <a href="{{ route('georgsbrief.index') }}" class="mt-4 inline-block bg-dpsg-blau px-4 py-2 text-sm font-bold text-white hover:bg-dpsg-rot">Zum Georgsbrief</a>
                </div>
            </aside>
        </div>
    </div>
@endsection
