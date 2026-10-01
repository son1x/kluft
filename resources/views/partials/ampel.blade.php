{{-- Erwartet: $status (done|partial|open|later|info) --}}
@php
    $lit = ['open' => 0, 'partial' => 1, 'done' => 2][$status] ?? null;
    $colors = ['bg-[#d93025]', 'bg-[#f2b600]', 'bg-[#1e8e3e]'];
@endphp
<span class="inline-flex shrink-0 flex-col items-center gap-[3px] rounded-md bg-[#1c1f26] px-[5px] py-[5px]" role="img" aria-label="Status: {{ \App\Support\Roadmap::STATUSES[$status] ?? $status }}" title="{{ \App\Support\Roadmap::STATUSES[$status] ?? $status }}">
    @foreach ($colors as $i => $color)
        <span @class(['block h-2.5 w-2.5 rounded-full', $color => $lit === $i, 'bg-white/15' => $lit !== $i])></span>
    @endforeach
</span>
