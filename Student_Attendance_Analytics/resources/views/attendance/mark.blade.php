@extends('layouts.app')

@section('title', __('Take Attendance'))

@php
    $km = app()->getLocale() === 'km';
    $reasonLabels = [
        'not_given' => __('Not given'), 'sick' => __('Sick'), 'family_matter' => __('Family matter'),
        'school_activity' => __('School activity'), 'other' => __('Other'),
    ];
    $initials = fn ($name) => collect(explode(' ', $name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->join('');
@endphp

@section('content')
    @include('attendance._steps', ['step' => 2])

    <form method="POST" action="{{ route('attendance.store') }}" class="space-y-4" data-attendance>
        @csrf
        @foreach ($lesson as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach

        @if ($errors->any())
            <div role="alert" class="alert alert-error alert-soft">
                <i data-lucide="circle-alert" class="size-5"></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        @if ($isCorrection)
            <div role="alert" class="alert alert-warning alert-soft">
                <i data-lucide="file-pen-line" class="size-5"></i>
                <span>{{ __('This lesson was submitted more than 24 hours ago. Each change needs a reason and is saved in the audit log.') }}</span>
            </div>
        @elseif ($session)
            <div role="alert" class="alert alert-info alert-soft">
                <i data-lucide="info" class="size-5"></i>
                <span>{{ __('Already recorded at :time. Changes are saved in the audit log.', ['time' => $session->updated_at->format('H:i')]) }}</span>
            </div>
        @endif

        {{-- Lesson header with the live progress ring --}}
        <div class="card bg-base-100 border-base-300 border">
            <div class="card-body flex-row flex-wrap items-center gap-4">
                <a href="{{ route('attendance.take') }}" class="btn btn-ghost btn-square" aria-label="{{ __("Back to today's classes") }}"><i data-lucide="arrow-left" class="size-5"></i></a>
                <div class="min-w-0 grow">
                    <h2 class="text-xl font-bold">{{ $class->name }} · {{ $km ? $subject->name_km : $subject->name_en }}</h2>
                    <p class="text-sm opacity-60">
                        {{ __('Period') }} {{ $lesson['period'] }}@if ($time) · {{ $time[0] }}–{{ $time[1] }}@endif · {{ $date->translatedFormat('D j M Y') }}
                    </p>
                </div>
                <div class="flex items-center gap-4">
                    <div class="radial-progress text-success font-bold" data-ring style="--value:100; --size:4.75rem; --thickness:7px;" role="progressbar" aria-valuenow="100">100%</div>
                    <div class="space-y-1 text-sm">
                        <div class="flex items-center gap-2"><i data-lucide="circle-check" class="text-success size-4"></i><span data-count="present" class="font-semibold">0</span><span class="opacity-60">{{ __('Present') }}</span></div>
                        <div class="flex items-center gap-2"><i data-lucide="circle-x" class="text-error size-4"></i><span data-count="absent" class="font-semibold">0</span><span class="opacity-60">{{ __('Absent') }}</span></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tools and student cards --}}
        <div class="card bg-base-100 border-base-300 border">
            <div class="card-body gap-4">
                @if ($students->isEmpty())
                    <div class="py-10 text-center">
                        <i data-lucide="users" class="mx-auto size-10 opacity-40"></i>
                        <p class="mt-2 font-medium">{{ __('This class has no active students.') }}</p>
                    </div>
                @else
                    <div class="flex flex-wrap items-center gap-2">
                        <label class="input grow sm:max-w-xs">
                            <i data-lucide="search" class="size-4 opacity-50"></i>
                            <input type="search" data-search placeholder="{{ __('Search name or ID') }}" />
                        </label>
                        <div role="tablist" class="tabs tabs-box tabs-sm">
                            <button type="button" role="tab" class="tab tab-active gap-1.5" data-filter="all">{{ __('All') }}<span class="badge badge-sm badge-neutral" data-count="all"></span></button>
                            <button type="button" role="tab" class="tab gap-1.5" data-filter="present">{{ __('Present') }}<span class="badge badge-sm badge-success" data-count="present"></span></button>
                            <button type="button" role="tab" class="tab gap-1.5" data-filter="absent">{{ __('Absent') }}<span class="badge badge-sm badge-error" data-count="absent"></span></button>
                        </div>
                        <button type="button" class="btn btn-sm ms-auto" data-all-present><i data-lucide="check-check" class="size-4"></i>{{ __('Mark all present') }}</button>
                    </div>
                    <p class="flex items-center gap-2 text-sm opacity-70">
                        <i data-lucide="hand" class="size-4"></i>{{ __('Everyone starts as Present. Tap the card of each student who is absent.') }}
                    </p>

                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-8">
                        @foreach ($students as $student)
                            @php
                                $status = old("statuses.{$student->id}", $saved->get($student->id)?->status ?? 'present');
                                $name = $km ? $student->name_km : $student->name_latin;
                            @endphp
                            <button type="button" class="rounded-box relative flex flex-col items-center gap-1.5 border-2 px-2 py-2.5 text-center transition active:scale-95"
                                data-tile="{{ $student->id }}" data-search-text="{{ mb_strtolower($student->name_latin.' '.$student->name_km.' '.$student->student_code) }}"
                                data-name="{{ $name }}">
                                <input type="hidden" name="statuses[{{ $student->id }}]" value="{{ $status }}">
                                <span class="absolute end-1.5 top-1.5 grid size-5 place-items-center rounded-full" data-badge><i data-lucide="check" class="size-3"></i></span>
                                {{-- One colour per student, always the same (from the student code) --}}
                                <span class="avatar avatar-placeholder"><span class="av-{{ crc32($student->student_code) % 8 }} grid size-10 place-items-center rounded-full text-sm" data-avatar><span>{{ $initials($student->name_latin) }}</span></span></span>
                                <span class="w-full">
                                    <span class="block truncate text-sm font-semibold">{{ $name }}</span>
                                    <span class="block text-xs opacity-60">{{ $student->student_code }}</span>
                                </span>
                                <span class="text-xs font-semibold" data-label></span>
                            </button>
                        @endforeach
                    </div>
                    <div class="hidden flex-col items-center gap-2 py-10 text-center" data-empty>
                        <span class="bg-base-200 grid size-12 place-items-center rounded-full"><i data-lucide="search-x" class="size-6 opacity-60"></i></span>
                        <div class="font-medium">{{ __('No students found') }}</div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Floating action bar --}}
        @if ($students->isNotEmpty())
            <div class="sticky bottom-4 z-20">
                <div class="bg-base-100 border-base-300 rounded-box flex flex-wrap items-center justify-between gap-3 border p-3 ps-5 shadow-lg" style="background-color: var(--glass-strong); backdrop-filter: blur(22px);">
                    <span class="text-sm" data-bar></span>
                    <button type="button" class="btn btn-primary" data-review>{{ __('Review & submit') }}<i data-lucide="arrow-right" class="size-4"></i></button>
                </div>
            </div>
        @endif

        {{-- ===== Step 3: review before saving ===== --}}
        <dialog class="modal modal-bottom sm:modal-middle" data-review-dialog @if ($errors->has('correction_reason')) data-reopen @endif>
            <div class="modal-box max-w-md overflow-hidden p-0">
                {{-- Header --}}
                <div class="flex items-start gap-3 p-5 pb-4">
                    <span class="bg-primary/10 text-primary grid size-11 shrink-0 place-items-center rounded-2xl"><i data-lucide="clipboard-check" class="size-5"></i></span>
                    <div class="min-w-0 grow">
                        <h3 class="text-lg leading-tight font-bold">{{ __('Review attendance') }}</h3>
                        <p class="truncate text-sm opacity-60">{{ $class->name }} · {{ $km ? $subject->name_km : $subject->name_en }} · {{ __('Period') }} {{ $lesson['period'] }} · {{ $date->translatedFormat('D j M') }}</p>
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm btn-circle" data-close aria-label="{{ __('Close') }}"><i data-lucide="x" class="size-4"></i></button>
                </div>

                {{-- Summary: rate, present, absent --}}
                <div class="px-5">
                    <div class="rounded-box bg-base-200/70 p-4">
                        <div class="flex items-end justify-between gap-4">
                            <div>
                                <div class="text-xs font-medium tracking-wide uppercase opacity-60">{{ __('Attendance rate') }}</div>
                                <div class="text-4xl leading-none font-bold" data-review-rate>100%</div>
                            </div>
                            <div class="flex gap-5 text-right">
                                <div>
                                    <div class="text-success flex items-center justify-end gap-1 text-xs font-semibold"><i data-lucide="check" class="size-3.5"></i>{{ __('Present') }}</div>
                                    <div class="text-2xl font-bold" data-count="present">0</div>
                                </div>
                                <div>
                                    <div class="text-error flex items-center justify-end gap-1 text-xs font-semibold"><i data-lucide="x" class="size-3.5"></i>{{ __('Absent') }}</div>
                                    <div class="text-2xl font-bold" data-count="absent">0</div>
                                </div>
                            </div>
                        </div>
                        <progress class="progress progress-success mt-3 w-full" data-review-bar value="100" max="100"></progress>
                    </div>
                </div>

                {{-- Absent students, each with a one-tap reason --}}
                <div class="px-5 pt-4" data-reasons>
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="text-sm font-semibold">{{ __('Absent students') }}</span>
                        <span class="text-xs opacity-60">{{ __('Reason is optional') }}</span>
                    </div>
                    <ul class="mt-2 max-h-64 space-y-2 overflow-y-auto pe-1">
                        @foreach ($students as $student)
                            @php $reason = old("reasons.{$student->id}", $saved->get($student->id)?->absence_reason ?? 'not_given'); @endphp
                            <li class="rounded-box border-base-300 border p-3" data-reason-row="{{ $student->id }}">
                                <div class="flex items-center gap-3">
                                    <span class="bg-error/10 text-error grid size-9 shrink-0 place-items-center rounded-full text-xs font-semibold">{{ $initials($student->name_latin) }}</span>
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-medium">{{ $km ? $student->name_km : $student->name_latin }}</div>
                                        <div class="text-xs opacity-60">{{ $student->student_code }}</div>
                                    </div>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-1.5" role="radiogroup" aria-label="{{ __('Absence reason') }}">
                                    @foreach ($reasonLabels as $value => $label)
                                        <input type="radio" name="reasons[{{ $student->id }}]" value="{{ $value }}" aria-label="{{ $label }}" @checked($reason === $value)
                                            class="btn btn-xs rounded-full">
                                    @endforeach
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- Nobody absent --}}
                <div class="hidden px-5 pt-4" data-all-present-note>
                    <div class="rounded-box bg-success/10 text-success flex items-center gap-3 p-4">
                        <i data-lucide="party-popper" class="size-6 shrink-0"></i>
                        <span class="font-medium">{{ __('Everyone is present!') }}</span>
                    </div>
                </div>

                @if ($isCorrection)
                    <fieldset class="fieldset px-5 pt-3">
                        <legend class="fieldset-legend">{{ __('Reason for the correction') }}</legend>
                        <input name="correction_reason" value="{{ old('correction_reason') }}" placeholder="{{ __('e.g. The student had a medical letter') }}"
                            @class(['input w-full', 'input-error' => $errors->has('correction_reason')]) required maxlength="255">
                        @error('correction_reason')<p class="label text-error whitespace-normal">{{ $message }}</p>@enderror
                    </fieldset>
                @endif

                {{-- Footer --}}
                <div class="border-base-300 bg-base-200/40 mt-5 flex flex-wrap items-center gap-2 border-t px-5 py-4">
                    <span class="grow text-xs opacity-60">
                        {{ $isCorrection ? __('The change is saved in the audit log.') : __('You can change it for 24 hours.') }}
                    </span>
                    <button type="button" class="btn btn-ghost" data-close>{{ __('Back') }}</button>
                    <button class="btn btn-primary"><i data-lucide="send" class="size-4"></i>{{ __('Submit') }}</button>
                </div>
            </div>
            <div class="modal-backdrop"><button type="button" data-close>{{ __('Close') }}</button></div>
        </dialog>
    </form>

    {{-- Undo bar, shown for a few seconds after each tap --}}
    <div class="toast toast-center toast-bottom z-50 mb-20 hidden" data-undo>
        <div class="alert bg-neutral text-neutral-content border-0 shadow-lg">
            <span data-undo-text></span>
            <button type="button" class="btn btn-sm btn-ghost text-neutral-content" data-undo-button><i data-lucide="undo-2" class="size-4"></i>{{ __('Undo') }}</button>
        </div>
    </div>

    @php
        $jsText = [
            'present' => __('Present'),
            'absent' => __('Absent'),
            'allPresent' => __('All students are present'),
            'presentLower' => __('present'),
            'absentLower' => __('absent'),
        ];
    @endphp
    <script>
        window.attendanceText = @json($jsText);
    </script>
@endsection
