{{--
    Attendance of one class on a class card (Classes and My Classes).
    $rate      attendance rate in % of this school year, or null when nothing is recorded yet
    $atRisk    number of students at risk (level High or Critical)
    $riskLink  where the "at risk" chip leads (the class's student list)
    One colour scale for the number and the bar: green 90%+, orange 80–89%, red under 80%.
--}}
@php
    use App\Support\Risk;
    // Full class names, so Tailwind sees them when it builds the CSS
    $textColour = ['success' => 'text-success', 'warning' => 'text-warning', 'error' => 'text-error'][Risk::tone($rate)] ?? '';
@endphp
@if ($rate === null)
    <p class="bg-base-200/60 rounded-box mt-3 flex items-center gap-2 px-3 py-2 text-sm opacity-70">
        <i data-lucide="clock" class="size-4"></i>{{ __('No attendance taken yet') }}
    </p>
@else
    <div class="mt-3">
        <div class="flex items-end justify-between gap-2">
            <div>
                <div class="{{ $textColour }} text-2xl leading-none font-bold">{{ $rate }}%</div>
                <div class="mt-1 text-xs opacity-60">{{ __('Attendance this school year') }}</div>
            </div>
            @if ($atRisk > 0)
                <a href="{{ $riskLink }}" class="badge badge-soft badge-error gap-1 hover:brightness-95">
                    <i data-lucide="triangle-alert" class="size-3.5"></i>{{ trans_choice(':count student at risk|:count students at risk', $atRisk, ['count' => $atRisk]) }}
                </a>
            @else
                <span class="badge badge-soft badge-success gap-1"><i data-lucide="circle-check" class="size-3.5"></i>{{ __('All on track') }}</span>
            @endif
        </div>
        <progress class="progress {{ Risk::progress($rate) }} mt-2" value="{{ $rate }}" max="100"></progress>
    </div>
@endif
