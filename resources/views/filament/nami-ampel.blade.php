{{-- Ampel NaMi: Server und eigener Zugang (App\Support\NamiAmpel). --}}
@php($farben = ['ok' => '#2e7d32', 'warn' => '#f9a825', 'fail' => '#c62828', 'off' => '#9e9e9e'])
@php($worte = ['ok' => 'grün', 'warn' => 'gelb', 'fail' => 'rot', 'off' => 'grau'])
<ul class="nami-ampel space-y-1 text-sm" data-nami-ampel>
    @foreach ($lampen as $lampe)
        <li class="flex items-start gap-2" data-status="{{ $lampe['status'] }}">
            <span aria-hidden="true" style="display:inline-block;flex:none;width:.75rem;height:.75rem;margin-top:.3rem;border-radius:9999px;background:{{ $farben[$lampe['status']] }}"></span>
            <span><strong>{{ $lampe['label'] }}:</strong> {{ $lampe['message'] }}<span class="sr-only"> (Ampel {{ $worte[$lampe['status']] }})</span></span>
        </li>
    @endforeach
</ul>
