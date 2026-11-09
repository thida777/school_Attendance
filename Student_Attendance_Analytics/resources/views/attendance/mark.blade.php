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
                            <button type="button" role="tab" class="tab tab-active" data-filter="all">{{ __('All') }}&nbsp;<span data-count="all"></span></button>
                            <button type="button" role="tab" class="tab" data-filter="present">{{ __('Present') }}&nbsp;<span data-count="present"></span></button>
                            <button type="button" role="tab" class="tab" data-filter="absent">{{ __('Absent') }}&nbsp;<span data-count="absent"></span></button>
                        </div>
                        <button type="button" class="btn btn-sm ms-auto" data-all-present><i data-lucide="check-check" class="size-4"></i>{{ __('Mark all present') }}</button>
                    </div>
                    <p class="flex items-center gap-2 text-sm opacity-70">
                        <i data-lucide="hand" class="size-4"></i>{{ __('Everyone starts as Present. Tap the card of each student who is absent.') }}
                    </p>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 2xl:grid-cols-6">
                        @foreach ($students as $student)
                            @php
                                $status = old("statuses.{$student->id}", $saved->get($student->id)?->status ?? 'present');
                                $name = $km ? $student->name_km : $student->name_latin;
                            @endphp
                            <button type="button" class="rounded-box relative flex flex-col items-center gap-2 border-2 p-3 text-center transition active:scale-95"
                                data-tile="{{ $student->id }}" data-search-text="{{ mb_strtolower($student->name_latin.' '.$student->name_km.' '.$student->student_code) }}"
                                data-name="{{ $name }}">
                                <input type="hidden" name="statuses[{{ $student->id }}]" value="{{ $status }}">
                                <span class="absolute end-2 top-2 grid size-6 place-items-center rounded-full" data-badge><i data-lucide="check" class="size-3.5"></i></span>
                                <span class="avatar avatar-placeholder"><span class="grid size-12 place-items-center rounded-full" data-avatar><span>{{ $initials($student->name_latin) }}</span></span></span>
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
        <dialog class="modal" data-review-dialog @if ($errors->has('correction_reason')) data-reopen @endif>
            <div class="modal-box max-w-md">
                <h3 class="text-lg font-bold">{{ __('Review attendance') }}</h3>
                <p class="mt-1 text-sm opacity-60">{{ $class->name }} · {{ $km ? $subject->name_km : $subject->name_en }} · {{ __('Period') }} {{ $lesson['period'] }}</p>
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div class="bg-success/10 rounded-box p-4">
                        <div class="text-success flex items-center gap-1 text-sm font-semibold"><i data-lucide="check" class="size-4"></i>{{ __('Present') }}</div>
                        <div class="text-3xl font-bold" data-count="present">0</div>
                    </div>
                    <div class="bg-error/10 rounded-box p-4">
                        <div class="text-error flex items-center gap-1 text-sm font-semibold"><i data-lucide="x" class="size-4"></i>{{ __('Absent') }}</div>
                        <div class="text-3xl font-bold" data-count="absent">0</div>
                    </div>
                </div>

                <div class="mt-3" data-reasons>
                    <div class="text-sm font-semibold">{{ __('Absence reason') }} <span class="font-normal opacity-60">({{ __('optional, can be added later') }})</span></div>
                    <ul class="mt-2 max-h-52 space-y-2 overflow-y-auto">
                        @foreach ($students as $student)
                            @php $reason = old("reasons.{$student->id}", $saved->get($student->id)?->absence_reason ?? 'not_given'); @endphp
                            <li class="flex items-center justify-between gap-2" data-reason-row="{{ $student->id }}">
                                <span class="text-sm font-medium">{{ $km ? $student->name_km : $student->name_latin }}</span>
                                <select name="reasons[{{ $student->id }}]" class="select select-sm w-40" aria-label="{{ __('Absence reason') }}">
                                    @foreach ($reasonLabels as $value => $label)
                                        <option value="{{ $value }}" @selected($reason === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </li>
                        @endforeach
                    </ul>
                </div>

                @if ($isCorrection)
                    <fieldset class="fieldset mt-3">
                        <legend class="fieldset-legend">{{ __('Reason for the correction') }}</legend>
                        <input name="correction_reason" value="{{ old('correction_reason') }}" placeholder="{{ __('e.g. The student had a medical letter') }}"
                            @class(['input w-full', 'input-error' => $errors->has('correction_reason')]) required maxlength="255">
                        @error('correction_reason')<p class="label text-error whitespace-normal">{{ $message }}</p>@enderror
                    </fieldset>
                @endif

                <div class="modal-action">
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
