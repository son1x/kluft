{{-- Erwartet: $section (Schlüssel), optional $current (Slug oder 'aktuelles') --}}
@php($config = config("dpsg.sections.{$section}"))
@php($sectionPages = \App\Http\Controllers\Public\SectionController::sectionPages($section, $config['landing']))
<nav aria-label="{{ $config['label'] }}" class="fach-{{ $config['color'] ?? 'internationales' }} bg-(--fach) text-(--fach-ink)">
    <ul class="mx-auto flex max-w-6xl flex-wrap gap-x-6 gap-y-1 px-4 py-3 text-sm">
        <li><a href="{{ url('/'.$config['landing']) }}" @class(['font-bold hover:underline', 'underline' => ($current ?? null) === $config['landing']])>{{ $config['label'] }}</a></li>
        @foreach ($sectionPages as $sectionPage)
            <li><a href="{{ url('/'.$sectionPage->slug) }}" @class(['hover:underline', 'underline' => ($current ?? null) === $sectionPage->slug])>{{ $sectionPage->title }}</a></li>
        @endforeach
        <li><a href="{{ route('sections.posts', $section) }}" @class(['hover:underline', 'underline' => ($current ?? null) === 'aktuelles'])>Aktuelles</a></li>
    </ul>
</nav>
