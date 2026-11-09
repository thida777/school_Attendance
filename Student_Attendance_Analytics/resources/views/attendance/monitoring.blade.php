@extends('layouts.app')

@section('title', auth()->user()->isTeacher() ? __('Attendance History') : __('Attendance Monitoring'))

@php
    $km = app()->getLocale() === 'km';
    $isTeacher = auth()->user()->isTeacher();
    $dialogOpen = $errors->any() && old('_modal') === 'correct-form';
    $reasonLabels = [
        'sick' => __('Sick'), 'family_matter' => __('Family matter'),
        'school_activity' => __('School activity'), 'other' => __('Other'),
    ];
@endphp

@section('content')
    {{-- Filters --}}
    <div class="card bg-base-100 border-base-300 border">
        <div class="card-body">
            <form method="GET" action="{{ route('attendance.monitoring') }}" class="grid grid-cols-1 gap-x-4 sm:grid-cols-2 xl:grid-cols-5" data-auto-submit>
                <fieldset class="fieldset">
                    <legend class="fieldset-legend">{{ __('Date') }}</legend>
                    <input type="date" name="date" value="{{ $filters['date'] ?? '' }}" max="{{ today()->toDateString() }}" class="input w-full" onchange="this.form.submit()" />
                </fieldset>
                <fieldset class="fieldset">
                    <legend class="fieldset-legend">{{ __('Class') }}</legend>
                    <select name="class" class="select w-full">
                        <option value="">{{ __('All classes') }}</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}" @selected(($filters['class'] ?? '') == $class->id)>{{ $class->name }}</option>
                        @endforeach
                    </select>
                </fieldset>
                <fieldset class="fieldset">
                    <legend class="fieldset-legend">{{ __('Subject') }}</legend>
                    <select name="subject" class="select w-full">
                        <option value="">{{ __('All subjects') }}</option>
                        @foreach ($subjects as $subject)
                            <option value="{{ $subject->id }}" @selected(($filters['subject'] ?? '') == $subject->id)>{{ $km ? $subject->name_km : $subject->name_en }}</option>
                        @endforeach
                    </select>
                </fieldset>
                <fieldset class="fieldset">
                    <legend class="fieldset-legend">{{ __('Status') }}</legend>
                    <select name="status" class="select w-full">
                        <option value="">{{ __('All statuses') }}</option>
                        <option value="present" @selected(($filters['status'] ?? '') === 'present')>{{ __('Present') }}</option>
                        <option value="absent" @selected(($filters['status'] ?? '') === 'absent')>{{ __('Absent') }}</option>
                    </select>
                </fieldset>
                <fieldset class="fieldset">
                    <legend class="fieldset-legend">{{ __('Search') }}</legend>
                    <label class="input w-full"><i data-lucide="search" class="size-4 opacity-50"></i><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Name or student ID') }}" /></label>
                </fieldset>
            </form>
        </div>
    </div>

    {{-- Totals for the current filter --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="stats bg-base-100 border-base-300 border"><div class="stat"><div class="stat-figure" style="--chip: var(--color-primary)"><i data-lucide="list-checks"></i></div><div class="stat-title">{{ __('Records') }}</div><div class="stat-value">{{ number_format($total) }}</div></div></div>
        <div class="stats bg-base-100 border-base-300 border"><div class="stat"><div class="stat-figure" style="--chip: var(--color-success)"><i data-lucide="circle-check"></i></div><div class="stat-title">{{ __('Present') }}</div><div class="stat-value">{{ number_format($present) }}</div></div></div>
        <div class="stats bg-base-100 border-base-300 border"><div class="stat"><div class="stat-figure" style="--chip: var(--color-error)"><i data-lucide="circle-x"></i></div><div class="stat-title">{{ __('Absent') }}</div><div class="stat-value">{{ number_format($total - $present) }}</div></div></div>
    </div>

    {{-- Records --}}
    <div class="card bg-base-100 border-base-300 border">
        <div class="card-body">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="card-title text-base">{{ __('Attendance records') }}</h3>
                @if (array_filter($filters))
                    <a href="{{ route('attendance.monitoring') }}" class="btn btn-sm"><i data-lucide="filter-x" class="size-4"></i>{{ __('Clear filters') }}</a>
                @endif
            </div>

            @if ($records->isEmpty())
                <div class="flex flex-col items-center gap-2 py-12 text-center">
                    <span class="bg-base-200 grid size-14 place-items-center rounded-full"><i data-lucide="search-x" class="size-7 opacity-60"></i></span>
                    <div class="font-semibold">{{ array_filter($filters) ? __('No records match these filters') : __('No attendance recorded yet') }}</div>
                    <p class="text-sm opacity-60">{{ array_filter($filters) ? __('Try another date or class.') : __('Records appear here after the first lesson is submitted.') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('Student') }}</th><th>{{ __('Student ID') }}</th><th>{{ __('Class') }}</th><th>{{ __('Subject') }}</th>
                                <th>{{ __('Date') }}</th>@unless ($isTeacher)<th>{{ __('Teacher') }}</th>@endunless<th>{{ __('Status') }}</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($records as $record)
                                @php
                                    $session = $record->session;
                                    $asks = $isTeacher && ! $recorder->isOpen($session); // after 24 hours a teacher asks the admin
                                    $other = $record->status === 'present' ? 'absent' : 'present';
                                @endphp
                                <tr>
                                    <td class="font-medium">{{ $km ? $record->student->name_km : $record->student->name_latin }}</td>
                                    <td>{{ $record->student->student_code }}</td>
                                    <td>{{ $session->schoolClass->name }}</td>
                                    <td>{{ $km ? $session->subject->name_km : $session->subject->name_en }}</td>
                                    <td class="whitespace-nowrap">{{ $session->date->translatedFormat('D j M Y') }}<div class="text-xs opacity-60">{{ __('Period') }} {{ $session->period }}</div></td>
                                    @unless ($isTeacher)<td>{{ $km ? $session->teacher->name_km : $session->teacher->name }}</td>@endunless
                                    <td class="whitespace-nowrap">
                                        @if ($record->status === 'present')
                                            <span class="badge badge-soft badge-success">{{ __('Present') }}</span>
                                        @else
                                            <span class="badge badge-soft badge-error">{{ __('Absent') }}</span>
                                            @isset($reasonLabels[$record->absence_reason])<div class="text-xs opacity-60">{{ $reasonLabels[$record->absence_reason] }}</div>@endisset
                                        @endif
                                    </td>
                                    <td class="text-right whitespace-nowrap">
                                        @if ($record->has_pending_request)
                                            <span class="badge badge-soft badge-warning"><i data-lucide="hourglass" class="size-3.5"></i>{{ __('Request pending') }}</span>
                                        @else
                                            <button class="btn btn-ghost btn-xs" data-form-dialog="correct-form"
                                                data-action="{{ route('attendance.records.correct', $record) }}" data-method="POST"
                                                data-title="{{ $asks ? __('Request a change') : __('Change attendance') }}: {{ $record->student->name_latin }}"
                                                data-values="{{ json_encode(['new_status' => $other]) }}">
                                                <i data-lucide="{{ $asks ? 'send' : 'pencil' }}" class="size-4"></i>{{ $asks ? __('Request change') : __('Change') }}
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $records->links('partials.pagination') }}
            @endif
        </div>
    </div>

    {{-- Change one record --}}
    <dialog id="correct-form" class="modal" @if ($dialogOpen) data-reopen @endif>
        <div class="modal-box max-w-md">
            <h3 class="text-lg font-semibold" data-dialog-title>{{ old('_title', __('Change attendance')) }}</h3>
            <form method="POST" action="{{ old('_action') }}" class="mt-2" data-dialog-form>
                @csrf
                <input type="hidden" name="_method" value="POST">
                <input type="hidden" name="_action" value="{{ old('_action') }}">
                <input type="hidden" name="_title" value="{{ old('_title') }}">
                <input type="hidden" name="_modal" value="correct-form">

                @include('partials.field', ['name' => 'new_status', 'label' => __('New status'), 'options' => ['present' => __('Present'), 'absent' => __('Absent')]])
                @include('partials.field', ['name' => 'reason', 'label' => __('Reason'), 'required' => false, 'markOptional' => false,
                    'placeholder' => __('e.g. The student had a medical letter'),
                    'hint' => $isTeacher
                        ? __('Within 24 hours of submitting, the change is made at once. After that it is sent to the admin as a request, and a reason is required.')
                        : __('After 24 hours a reason is required. Every change is saved in the audit log.')])

                <div class="modal-action">
                    <button type="button" class="btn" onclick="this.closest('dialog').close()">{{ __('Cancel') }}</button>
                    <button class="btn btn-primary">{{ __('Save') }}</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>{{ __('Close') }}</button></form>
    </dialog>
@endsection
