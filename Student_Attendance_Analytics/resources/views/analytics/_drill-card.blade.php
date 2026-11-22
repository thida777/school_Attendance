{{-- One clickable card of the Insights drill-down (a grade or a class). Needs: href, title, sub, stat (rate, trend), risk --}}
@php
    use App\Support\Risk;
    $rate = $stat['rate'];
    $t = $stat['trend'];
    $bar = ['error' => 'progress-error', 'warning' => 'progress-warning', 'success' => 'progress-success', 'neutral' => ''];
@endphp
<a href="{{ $href }}" class="card bg-base-200/60 hover:bg-base-200 border-base-300 border text-left transition">
    <div class="card-body gap-1 p-4">
        <div class="flex items-start justify-between">
            <span class="text-lg font-bold">{{ $title }}</span>
            @if ($t !== null)
                <span @class(['text-sm font-semibold', 'text-error' => $t <= -5, 'text-success' => $t >= 5, 'opacity-70' => $t > -5 && $t < 5])>{{ $t < 0 ? '▼' : '▲' }} {{ number_format(abs($t), 1) }}</span>
            @endif
        </div>
        <div class="text-3xl font-extrabold">{{ $rate === null ? '—' : number_format($rate, 1).'%' }}</div>
        <progress class="progress {{ $bar[Risk::tone($rate)] }}" value="{{ $rate ?? 0 }}" max="100"></progress>
        <div class="text-xs opacity-70">{{ $sub }} · <span @class(['font-semibold', 'text-error' => $risk > 0])>{{ $risk }}</span> {{ __('at high risk') }}</div>
    </div>
</a>
