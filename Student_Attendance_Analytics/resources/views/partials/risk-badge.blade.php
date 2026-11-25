{{--
    Risk of a student, the same everywhere: @include('partials.risk-badge', ['level' => 'high'])
    "Good" is quiet (a small dot and text), so the students who need help stand out:
    Watch, High risk and Critical are coloured badges with a dot.
--}}
@php use App\Support\Risk; @endphp
@if (($level ?? null) === 'low')
    <span class="text-success inline-flex items-center gap-1.5 text-sm whitespace-nowrap opacity-80"><span class="bg-success size-1.5 rounded-full"></span>{{ Risk::label('low') }}</span>
@elseif ($level ?? null)
    <span class="badge badge-soft {{ Risk::badge($level) }} gap-1.5 font-semibold whitespace-nowrap"><span class="size-2 rounded-full bg-current"></span>{{ Risk::label($level) }}</span>
@else
    <span class="opacity-60">—</span>
@endif
