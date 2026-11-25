@extends('layouts.app')

@section('title', $student->name_latin)

@php
    use App\Support\Risk;
    $km = app()->getLocale() === 'km';
    $guardian = $student->guardians->first();
    $dayNames = [1 => __('Monday'), 2 => __('Tuesday'), 3 => __('Wednesday'), 4 => __('Thursday'), 5 => __('Friday'), 6 => __('Saturday')];
    $reasonLabels = [
        'not_given' => __('Not given'), 'sick' => __('Sick'), 'family_matter' => __('Family matter'),
        'school_activity' => __('School activity'), 'other' => __('Other'),
    ];
    $reasonBars = ['not_given' => 'progress-error', 'sick' => 'progress-warning', 'family_matter' => 'progress-info', 'school_activity' => 'progress-success', 'other' => ''];
    $toneText = ['error' => 'text-error', 'warning' => 'text-warning', 'success' => 'text-success', 'neutral' => ''];
    $alertClass = ['critical' => 'alert-error', 'high' => 'alert-warning', 'medium' => 'alert-info', 'low' => 'alert-success'];

    // Why this level, in one sentence: "High risk: absent 3 school days in a row, and attendance fell 8.2 points in 4 weeks."
    $why = [];
    if ($risk->rate !== null && $risk->rate < 90) $why[] = __('attendance rate is :rate%', ['rate' => $risk->rate]);
    if ($risk->daysInARow >= 3) $why[] = __('absent :count school days in a row', ['count' => $risk->daysInARow]);
    if ($risk->trend !== null && $risk->trend <= -5) $why[] = __('attendance fell :points points in 4 weeks', ['points' => abs($risk->trend)]);
    if ($risk->unexplained >= 3) $why[] = __(':count days absent with no reason this month', ['count' => $risk->unexplained]);
    $last = array_pop($why);
    $whyText = $why ? implode(', ', $why).' '.__('and').' '.$last : $last;

    $weekLabels = $weeks->pluck('label')->all();
    $weekRates = $weeks->pluck('rate')->all();
    // Round axis: steps of 20 from just below the lowest week (never above 60) up to 100
    $chartMin = (int) (floor(min(60, ...array_filter($weekRates, fn ($r) => $r !== null) ?: [60]) / 20) * 20);
    $chartTicks = intdiv(100 - $chartMin, 20);
    $dayLabels = [__('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat')];
    $dayCounts = $byDay->values()->all();
@endphp

@section('content')
    <div class="breadcrumbs text-sm">
        <ul>
            <li><a href="{{ route('students.index') }}">{{ __('Students') }}</a></li>
            <li>{{ $km ? $student->name_km : $student->name_latin }}</li>
        </ul>
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        {{-- Profile --}}
        <div class="card bg-base-100 border-base-300 border">
            <div class="card-body items-center text-center">
                {{-- The same big rounded picture as in the student form --}}
                <span class="photo-frame is-static">
                    <span class="photo-frame-inner">
                        @if ($photo = $student->photoUrl())
                            <img src="{{ $photo }}" alt="{{ $student->name_latin }}">
                        @else
                            <span class="photo-initials">{{ $student->initials() }}</span>
                        @endif
                    </span>
                </span>
                <h2 class="mt-2 text-xl font-bold">{{ $km ? $student->name_km : $student->name_latin }}</h2>
                <p class="grow-0 opacity-60">{{ $km ? $student->name_latin : $student->name_km }} · {{ $student->student_code }}</p>
                <div class="flex flex-wrap justify-center gap-2">
                    <span class="badge badge-soft">{{ $student->schoolClass->name }}</span>
                    <span class="badge badge-soft">{{ __(ucfirst($student->gender)) }}</span>
                    @if ($student->status === 'inactive')
                        <span class="badge badge-ghost">{{ __('Inactive') }}</span>
                    @elseif ($risk->level)
                        <span class="badge {{ Risk::badge($risk->level) }}">{{ __(ucfirst($risk->level).' risk') }}</span>
                    @endif
                </div>
                <ul class="mt-4 w-full space-y-3 text-left text-sm">
                    <li class="flex justify-between gap-2"><span class="opacity-60">{{ __('Date of birth') }}</span><span class="font-medium">{{ $student->date_of_birth?->translatedFormat('j M Y') ?? '—' }}</span></li>
                    <li class="flex justify-between gap-2"><span class="opacity-60">{{ __('Parent') }}</span><span class="font-medium">{{ $guardian?->name ?? '—' }}</span></li>
                    <li class="flex justify-between gap-2"><span class="opacity-60">{{ __('Parent phone') }}</span>
                        <span class="font-medium">@if ($guardian)<a class="link link-hover" href="tel:{{ $guardian->phone }}">{{ trim(chunk_split($guardian->phone, 3, ' ')) }}</a>@else — @endif</span></li>
                    <li class="flex justify-between gap-2"><span class="opacity-60">{{ __('Messages') }}</span>
                        <span class="font-medium">{{ $guardian ? ($guardian->telegram_chat_id ? 'Telegram' : 'SMS') : '—' }}</span></li>
                </ul>
                <div class="card-actions mt-4 w-full">
                    <button class="btn grow" data-form-dialog="student-form"
                        data-action="{{ route('students.update', $student) }}" data-method="PUT"
                        data-title="{{ __('Edit student') }}" data-values="{{ json_encode($student->formValues()) }}">
                        <i data-lucide="pencil" class="size-4"></i>{{ __('Edit') }}
                    </button>
                    <a href="{{ route('interventions', ['student' => $student->id, 'add' => 1]) }}" class="btn btn-primary grow">
                        <i data-lucide="hand-heart" class="size-4"></i>{{ __('Follow up') }}
                    </a>
                </div>
                @if ($messageText)
                    <button class="btn btn-soft btn-primary mt-2 w-full" onclick="document.getElementById('message-form').showModal()">
                        <i data-lucide="send" class="size-4"></i>{{ __('Message parent') }}
                    </button>
                @endif
                @if ($messages->isNotEmpty())
                    <a href="{{ route('messages', ['q' => $student->student_code]) }}" class="link link-hover mt-1 text-xs opacity-70">
                        {{ __('Last message: :when', ['when' => $messages->first()->created_at->translatedFormat('j M, H:i')]) }}
                        ({{ trans_choice('{1} :count message|[0,*] :count messages', $student->parentMessages()->count()) }})
                    </a>
                    @if ($replied = $messages->firstWhere('reply', '!=', null))
                        <p class="text-success mt-1 inline-flex items-center gap-1.5 text-xs font-medium">
                            <i data-lucide="reply" class="size-3.5"></i>{{ __('Parent replied:') }} {{ $replied->replyLabel() }}
                            <span class="font-normal opacity-70">· {{ $replied->replied_at->translatedFormat('j M') }}</span>
                        </p>
                    @endif
                @endif
            </div>
        </div>

        {{-- Attendance summary: rate with its trend --}}
        <div class="card bg-base-100 border-base-300 border xl:col-span-2">
            <div class="card-body">
                <h3 class="card-title text-base">{{ __('Attendance this school year') }}</h3>
                @if ($risk->level)
                    <div role="alert" class="alert alert-soft {{ $alertClass[$risk->level] }} mt-1">
                        <i data-lucide="{{ $risk->isAtRisk() ? 'triangle-alert' : ($risk->level === 'low' ? 'circle-check' : 'eye') }}" class="size-5"></i>
                        <span>
                            <b>{{ __(ucfirst($risk->level).' risk') }}:</b>
                            {{ $whyText ? $whyText.'.' : __('attendance is good and stable.') }}
                            @if ($risk->action()) <span class="opacity-80">→ {{ __($risk->action()) }}</span> @endif
                        </span>
                    </div>
                @else
                    <div class="alert alert-soft mt-1"><i data-lucide="info" class="size-5"></i><span>{{ __('No attendance taken yet') }}</span></div>
                @endif

                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div class="bg-base-200 rounded-box p-4">
                        <div class="text-sm opacity-60">{{ __('Attendance rate') }}</div>
                        <div class="text-2xl font-bold {{ $toneText[Risk::tone($risk->rate)] }}">{{ $risk->rate === null ? '—' : $risk->rate.'%' }}</div>
                        @if ($risk->trend !== null)
                            <div @class(['text-xs font-semibold', 'text-error' => $risk->trend < 0, 'text-success' => $risk->trend > 0, 'opacity-60' => $risk->trend == 0])>
                                {{ $risk->trend < 0 ? '▼' : ($risk->trend > 0 ? '▲' : '') }} {{ abs($risk->trend) }} {{ __('points') }}
                            </div>
                        @endif
                    </div>
                    <div class="bg-base-200 rounded-box p-4">
                        <div class="text-sm opacity-60">{{ __('In a row') }}</div>
                        <div @class(['text-2xl font-bold', 'text-error' => $risk->daysInARow >= 3])>{{ $risk->daysInARow }}</div>
                        <div class="text-xs opacity-60">{{ __('school days absent') }}</div>
                    </div>
                    <div class="bg-success/10 rounded-box p-4">
                        <div class="text-success flex items-center gap-1 text-sm font-semibold"><i data-lucide="check" class="size-4"></i>{{ __('Present') }}</div>
                        <div class="text-2xl font-bold">{{ $risk->present }}</div>
                        <div class="text-xs opacity-60">{{ __('of :total lessons', ['total' => $risk->total]) }}</div>
                    </div>
                    <div class="bg-error/10 rounded-box p-4">
                        <div class="text-error flex items-center gap-1 text-sm font-semibold"><i data-lucide="x" class="size-4"></i>{{ __('Absent') }}</div>
                        <div class="text-2xl font-bold">{{ $risk->absent }}</div>
                        <div class="text-xs opacity-60">{{ __('of :total lessons', ['total' => $risk->total]) }}</div>
                    </div>
                </div>
                <div class="mt-2 flex items-center justify-between">
                    <span class="text-sm font-semibold">{{ __('Weekly trend') }}</span>
                    <span class="text-xs opacity-60">{{ __('last 8 weeks') }}</span>
                </div>
                <div id="chart-weeks"></div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        {{-- When absences happen --}}
        <div class="card bg-base-100 border-base-300 border">
            <div class="card-body">
                <h3 class="card-title text-base">{{ __('When absences happen') }}</h3>
                @if ($risk->absent === 0)
                    <p class="py-6 text-center text-sm opacity-60">{{ __('No absences yet.') }}</p>
                @else
                    <div class="grid grid-cols-3 gap-2 text-center text-sm">
                        <div class="bg-base-200 rounded-box p-3">
                            <div class="text-xs opacity-60">{{ __('Day') }}</div>
                            <div class="font-bold">{{ $when['day'] ? $dayNames[$when['day'][0]] : '—' }}</div>
                            @if ($when['day'])<div class="text-xs opacity-60">{{ __(':count of :total', ['count' => $when['day'][1], 'total' => $risk->absent]) }}</div>@endif
                        </div>
                        <div class="bg-base-200 rounded-box p-3">
                            <div class="text-xs opacity-60">{{ __('Period') }}</div>
                            <div class="font-bold">{{ $when['period'] ? __('Period').' '.$when['period'][0] : '—' }}</div>
                            @if ($when['period'])<div class="text-xs opacity-60">{{ __(':count of :total', ['count' => $when['period'][1], 'total' => $risk->absent]) }}</div>@endif
                        </div>
                        <div class="bg-base-200 rounded-box p-3">
                            <div class="text-xs opacity-60">{{ __('Subject') }}</div>
                            <div class="font-bold">{{ $when['subject'] ? ($km ? $when['subject'][0]->name_km : $when['subject'][0]->name_en) : '—' }}</div>
                            @if ($when['subject'])<div class="text-xs opacity-60">{{ __(':count of :total', ['count' => $when['subject'][1], 'total' => $risk->absent]) }}</div>@endif
                        </div>
                    </div>
                    <div id="chart-days"></div>
                    <p class="text-xs opacity-60">{{ __('This is a pattern to look into, not the reason itself.') }}</p>
                @endif
            </div>
        </div>

        {{-- Absence reasons --}}
        <div class="card bg-base-100 border-base-300 border">
            <div class="card-body">
                <h3 class="card-title text-base">{{ __('Absence reasons') }}</h3>
                <p class="grow-0 text-sm opacity-60">{{ __('The status is still Present or Absent. The reason is optional.') }}</p>
                <ul class="mt-2 space-y-4 text-sm">
                    @foreach ($reasons as $code => $count)
                        <li>
                            <div class="mb-1 flex justify-between"><span>{{ $reasonLabels[$code] }}</span><span class="font-medium">{{ $count }}</span></div>
                            <progress class="progress {{ $reasonBars[$code] }}" value="{{ $count }}" max="{{ max(1, $risk->absent) }}"></progress>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    {{-- What the school did, and whether attendance changed afterwards --}}
    <div class="card bg-base-100 border-base-300 border">
        <div class="card-body">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="card-title text-base">{{ __('Interventions') }}</h3>
                <a href="{{ route('interventions', ['student' => $student->id]) }}" class="btn btn-ghost btn-sm">{{ __('View all') }}</a>
            </div>
            @if ($interventions->isEmpty())
                <p class="py-4 text-sm opacity-60">{{ __('No follow-ups yet for this student.') }}</p>
            @else
                <ul class="timeline timeline-vertical timeline-compact">
                    @foreach ($interventions as $item)
                        @php $change = $item->change(); $result = $item->result(); $state = $item->state(); @endphp
                        <li>
                            @unless ($loop->first)<hr />@endunless
                            <div class="timeline-middle">
                                <i data-lucide="{{ $item->status === 'open' ? 'circle-dot' : 'circle-check' }}" @class(['size-4', 'text-warning' => $item->status === 'open', 'text-success' => $item->status !== 'open'])></i>
                            </div>
                            <div class="timeline-end mb-6 w-full">
                                <div class="text-sm opacity-60">{{ $item->action_date->translatedFormat('D j M Y') }} · {{ $item->createdBy->name }}</div>
                                <div class="font-semibold">{{ $item->actionLabel() }}</div>
                                <div class="text-sm">
                                    @if ($item->status === 'open')
                                        {{ __('Before') }}: <b>{{ $item->rate_before ?? '—' }}%</b>
                                        @if ($item->follow_up_date) · {{ __('Follow-up on') }} <b>{{ $item->follow_up_date->translatedFormat('j M') }}</b>@endif
                                    @else
                                        {{ $item->rate_before ?? '—' }}% → <b>{{ $item->rate_after ?? '—' }}%</b>
                                    @endif
                                </div>
                                @if ($state === 'due')
                                    <span class="badge badge-soft badge-error mt-1">{{ __('Follow-up due') }}</span>
                                @elseif ($state === 'open')
                                    <span class="badge badge-soft badge-warning mt-1">{{ __('Open') }}</span>
                                @elseif ($result)
                                    <span @class(['badge badge-soft mt-1', 'badge-success' => $result === 'improved', 'badge-error' => $result === 'declined', 'badge-ghost' => $result === 'none'])>
                                        {{ ['improved' => __('Improved'), 'declined' => __('Declined'), 'none' => __('No clear change')][$result] }} {{ $change > 0 ? '+' : '' }}{{ $change }}
                                    </span>
                                @endif
                                @if ($item->outcome)
                                    <div class="mt-1 text-sm opacity-70">{{ $item->outcome }}</div>
                                @endif
                            </div>
                            @unless ($loop->last)<hr />@endunless
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- Recent records --}}
    <div class="card bg-base-100 border-base-300 border">
        <div class="card-body">
            <h3 class="card-title text-base">{{ __('Recent records') }}</h3>
            @if ($recent->isEmpty())
                <p class="py-4 text-sm opacity-60">{{ __('No attendance taken yet') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Period') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Teacher') }}</th><th>{{ __('Status') }}</th><th>{{ __('Reason') }}</th></tr></thead>
                        <tbody>
                            @foreach ($recent as $record)
                                <tr>
                                    <td class="whitespace-nowrap">{{ $record->session->date->translatedFormat('D j M Y') }}</td>
                                    <td>P{{ $record->session->period }}</td>
                                    <td>{{ $km ? $record->session->subject->name_km : $record->session->subject->name_en }}</td>
                                    <td>{{ $record->session->teacher->name }}</td>
                                    <td>
                                        @if ($record->status === 'absent')
                                            <span class="badge badge-soft badge-error"><i data-lucide="x" class="size-3.5"></i>{{ __('Absent') }}</span>
                                        @else
                                            <span class="badge badge-soft badge-success"><i data-lucide="check" class="size-3.5"></i>{{ __('Present') }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $record->status === 'absent' ? $reasonLabels[$record->absence_reason ?? 'not_given'] : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <a href="{{ route('attendance.monitoring', ['q' => $student->student_code]) }}" class="btn btn-ghost btn-sm self-start">{{ __('View all records') }}</a>
            @endif
        </div>
    </div>

    @include('students._form')

    {{-- Message parent: the text starts with this month's attendance and can be changed before sending --}}
    @if ($messageText)
        <dialog id="message-form" class="modal">
            <div class="modal-box max-w-xl">
                <h3 class="text-lg font-semibold">{{ __('Message parent') }}</h3>
                <p class="mt-1 text-sm opacity-70">
                    {{ __('To') }}: {{ $guardian->name }} ·
                    {{ $guardian->telegram_chat_id ? 'Telegram' : 'SMS '.$guardian->phone }}
                </p>
                <form method="POST" action="{{ route('messages.store', $student) }}" class="mt-3">
                    @csrf
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">{{ __('Message (Khmer and English)') }}</legend>
                        <textarea name="body" rows="9" maxlength="2000" class="textarea w-full" required>{{ old('body', $messageText) }}</textarea>
                    </fieldset>
                    <label class="label mt-2 cursor-pointer gap-2 whitespace-normal">
                        <input type="checkbox" name="follow_up" value="1" class="checkbox checkbox-sm checkbox-primary" checked />
                        <span class="text-sm">{{ __('Also add a follow-up, to check the result in 2 weeks') }}</span>
                    </label>
                    <div class="modal-action">
                        <button type="button" class="btn" onclick="this.closest('dialog').close()">{{ __('Cancel') }}</button>
                        <button class="btn btn-primary"><i data-lucide="send" class="size-4"></i>{{ __('Send') }}</button>
                    </div>
                </form>
            </div>
            <form method="dialog" class="modal-backdrop"><button>{{ __('Close') }}</button></form>
        </dialog>
    @endif
@endsection

@push('scripts')
    <script>
        // Wait for app.js (it loads as a module, after this script)
        document.addEventListener('DOMContentLoaded', () => {
            const weekLabels = @json($weekLabels);
            const weekRates = @json($weekRates);
            const dayLabels = @json($dayLabels);
            const dayCounts = @json($dayCounts);
            const rateName = @json(__('Attendance rate'));
            const absencesName = @json(__('Absences'));

            makeChart('#chart-weeks', (c) => ({
                chart: { type: 'area', height: 190 },
                colors: [c.series],
                series: [{ name: rateName, data: weekRates }],
                stroke: { width: 3, curve: 'smooth' },
                markers: { size: 4, strokeWidth: 0 },
                fill: { type: 'gradient', gradient: { shadeIntensity: 0, opacityFrom: 0.3, opacityTo: 0, stops: [0, 100] } },
                xaxis: { categories: weekLabels, axisBorder: { color: c.axis }, axisTicks: { show: false } },
                yaxis: { min: @json($chartMin), max: 100, tickAmount: @json($chartTicks), labels: { formatter: (v) => v === null ? '' : v.toFixed(0) + '%' } },
                // 90% and 80% lines: the same colour scale as every rate on the website
                annotations: { yaxis: [
                    { y: 90, borderColor: c.present, strokeDashArray: 4, opacity: 0.6 },
                    { y: 80, borderColor: c.warning, strokeDashArray: 4, opacity: 0.6 },
                ] },
                tooltip: { y: { formatter: (v) => v === null ? '—' : v + '%' } },
            }));

            makeChart('#chart-days', (c) => ({
                chart: { type: 'bar', height: 180 },
                colors: [c.absent],
                series: [{ name: absencesName, data: dayCounts }],
                plotOptions: { bar: { columnWidth: '35%', borderRadius: 6, borderRadiusApplication: 'end' } },
                xaxis: { categories: dayLabels, axisBorder: { color: c.axis }, axisTicks: { show: false } },
                yaxis: { min: 0, tickAmount: 3, labels: { formatter: (v) => v.toFixed(0) } },
            }));
        });
    </script>
@endpush
