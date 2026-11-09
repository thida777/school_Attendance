@extends('layouts.app')

@section('title', __('Take Attendance'))

@php
    $km = app()->getLocale() === 'km';
    $subjectName = $km ? $session->subject->name_km : $session->subject->name_en;
    $reasonLabels = [
        'sick' => __('Sick'), 'family_matter' => __('Family matter'),
        'school_activity' => __('School activity'), 'other' => __('Other'),
    ];
@endphp

@section('content')
    @include('attendance._steps', ['step' => 4])

    {{-- ===== Step 4: done ===== --}}
    <div class="card bg-base-100 border-base-300 mx-auto max-w-lg border">
        <div class="card-body items-center text-center">
            <span class="bg-success/10 text-success grid size-20 place-items-center rounded-full"><i data-lucide="circle-check-big" class="size-10"></i></span>
            <h2 class="mt-2 text-2xl font-bold">{{ __('Attendance submitted') }}</h2>
            <p class="opacity-60">
                {{ $session->schoolClass->name }} · {{ $subjectName }} · {{ __('Period') }} {{ $session->period }} · {{ $session->date->translatedFormat('D j M Y') }}
            </p>

            <div class="mt-4 grid w-full grid-cols-2 gap-3">
                <div class="bg-success/10 rounded-box p-4">
                    <div class="text-success flex items-center justify-center gap-1 text-sm font-semibold"><i data-lucide="check" class="size-4"></i>{{ __('Present') }}</div>
                    <div class="text-3xl font-bold">{{ $present }}</div>
                </div>
                <div class="bg-error/10 rounded-box p-4">
                    <div class="text-error flex items-center justify-center gap-1 text-sm font-semibold"><i data-lucide="x" class="size-4"></i>{{ __('Absent') }}</div>
                    <div class="text-3xl font-bold">{{ $absent->count() }}</div>
                </div>
            </div>

            @if ($absent->isNotEmpty())
                <div class="mt-2 flex flex-wrap justify-center gap-1">
                    @foreach ($absent as $record)
                        <span class="badge badge-soft badge-error">
                            {{ $km ? $record->student->name_km : $record->student->name_latin }}@isset($reasonLabels[$record->absence_reason]) · {{ $reasonLabels[$record->absence_reason] }}@endisset
                        </span>
                    @endforeach
                </div>
            @endif

            <div class="card-actions mt-6 w-full flex-col sm:flex-row">
                @if ($next)
                    <a href="{{ route('attendance.mark', ['slot' => $next->id]) }}" class="btn btn-primary w-full sm:w-auto sm:grow">
                        {{ __('Next') }}: {{ $next->schoolClass->name }} · {{ $km ? $next->subject->name_km : $next->subject->name_en }}<i data-lucide="arrow-right" class="size-4"></i>
                    </a>
                @else
                    <a href="{{ route('attendance.take') }}" class="btn btn-primary w-full sm:w-auto sm:grow">
                        {{ __("Back to today's classes") }}<i data-lucide="arrow-right" class="size-4"></i>
                    </a>
                @endif
                <a href="{{ route('attendance.monitoring') }}" class="btn w-full sm:w-auto sm:grow"><i data-lucide="list-checks" class="size-4"></i>{{ __('View records') }}</a>
            </div>
            @if ($canEdit)
                <a href="{{ route('attendance.mark', ['school_class_id' => $session->school_class_id, 'subject_id' => $session->subject_id, 'date' => $session->date->toDateString(), 'period' => $session->period]) }}"
                    class="btn btn-ghost btn-sm mt-1"><i data-lucide="pencil" class="size-4"></i>{{ __('Edit this attendance') }}</a>
            @endif
        </div>
    </div>
@endsection
