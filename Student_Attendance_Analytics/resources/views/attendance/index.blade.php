@extends('layouts.app')

@section('title', __('Take Attendance'))

@php
    $km = app()->getLocale() === 'km';
    $isTeacher = auth()->user()->isTeacher();
    $nextId = $schedule->first(fn ($lesson) => $lesson->session === null)?->id;
@endphp

@section('content')
    @include('attendance._steps', ['step' => 1])

    {{-- ===== Step 1: today's lessons ===== --}}
    <section class="space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2 class="text-xl font-bold">{{ __("Today's classes") }}</h2>
                <p class="text-sm opacity-60">{{ today()->translatedFormat('l j F Y') }}</p>
            </div>
            @if ($schedule->isNotEmpty())
                <p class="text-sm opacity-60">{{ __(':done of :total classes marked', ['done' => $doneCount, 'total' => $schedule->count()]) }}</p>
            @endif
        </div>

        @if ($schedule->isEmpty())
            <div class="card bg-base-100 border-base-300 border">
                <div class="card-body items-center py-12 text-center">
                    <i data-lucide="calendar-off" class="size-10 opacity-40"></i>
                    <p class="mt-2 font-medium">{{ __('No lessons on the timetable today.') }}</p>
                    <p class="text-sm opacity-60">{{ __('Use "Other class or date" below for an extra lesson.') }}</p>
                </div>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($schedule as $lesson)
                    @php $isNext = $lesson->id === $nextId; @endphp
                    <div class="card bg-base-100 border-base-300 border transition hover:-translate-y-0.5" @if ($isNext) style="border: 2px solid var(--color-primary)" @endif>
                        <div class="card-body gap-3">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-medium opacity-60">{{ __('Period') }} {{ $lesson->period }} · {{ substr($lesson->start_time, 0, 5) }}–{{ substr($lesson->end_time, 0, 5) }}</span>
                                @if ($lesson->session)
                                    <span class="badge badge-soft badge-success"><i data-lucide="check" class="size-3.5"></i>{{ __('Done') }} · {{ $lesson->session->present_count }}/{{ $lesson->session->records_count }}</span>
                                @elseif ($isNext)
                                    <span class="badge badge-soft badge-primary"><i data-lucide="clock" class="size-3.5"></i>{{ __('Up next') }}</span>
                                @else
                                    <span class="badge badge-soft">{{ __('Not marked') }}</span>
                                @endif
                            </div>
                            <div>
                                <div class="text-2xl font-bold">{{ $lesson->schoolClass->name }}</div>
                                <div class="opacity-70">{{ $km ? $lesson->subject->name_km : $lesson->subject->name_en }}</div>
                                @unless ($isTeacher)
                                    <div class="text-sm opacity-60">{{ $km ? $lesson->teacher->name_km : $lesson->teacher->name }}</div>
                                @endunless
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="flex items-center gap-1 text-sm opacity-60"><i data-lucide="users" class="size-4"></i>{{ $lesson->size }} {{ __('students') }}</span>
                                <a href="{{ route('attendance.mark', ['slot' => $lesson->id]) }}" @class(['btn btn-sm', 'btn-primary' => $isNext])>
                                    @if ($lesson->session)
                                        <i data-lucide="pencil" class="size-4"></i>{{ __('Edit') }}
                                    @else
                                        <i data-lucide="play" class="size-4"></i>{{ __('Start') }}
                                    @endif
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Rare case: a lesson that is not on today's timetable --}}
        <div class="collapse collapse-arrow bg-base-100 border-base-300 border">
            <input type="checkbox" @checked($errors->any()) aria-label="{{ __('Other class or date') }}" />
            <div class="collapse-title font-medium">
                <span class="flex items-center gap-2"><i data-lucide="calendar-search" class="size-4"></i>{{ $isTeacher ? __('Other class today') : __('Other class or date') }}</span>
            </div>
            <div class="collapse-content">
                <form method="GET" action="{{ route('attendance.mark') }}" class="grid grid-cols-1 items-start gap-x-4 sm:grid-cols-2 xl:grid-cols-5">
                    @include('partials.field', ['name' => 'school_class_id', 'label' => __('Class'), 'options' => $classes->pluck('name', 'id')->all()])
                    @include('partials.field', ['name' => 'subject_id', 'label' => __('Subject'), 'options' => $subjects->pluck($km ? 'name_km' : 'name_en', 'id')->all()])
                    @if ($isTeacher)
                        <input type="hidden" name="date" value="{{ today()->toDateString() }}">
                        <fieldset class="fieldset"><legend class="fieldset-legend">{{ __('Date') }}</legend><input class="input w-full" value="{{ today()->translatedFormat('j M Y') }}" disabled></fieldset>
                    @else
                        <fieldset class="fieldset">
                            <legend class="fieldset-legend">{{ __('Date') }}</legend>
                            <input type="date" name="date" value="{{ old('date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" @class(['input w-full', 'input-error' => $errors->has('date')]) required>
                            @error('date')<p class="label text-error whitespace-normal">{{ $message }}</p>@enderror
                        </fieldset>
                    @endif
                    @include('partials.field', ['name' => 'period', 'label' => __('Period'),
                        'options' => collect($periods)->mapWithKeys(fn ($t, $p) => [$p => __('Period').' '.$p.' · '.$t[0]])->all()])
                    <button class="btn btn-primary mt-8"><i data-lucide="play" class="size-4"></i>{{ __('Start') }}</button>
                </form>
                @if ($isTeacher)
                    <p class="text-sm opacity-60">{{ __('Teachers take attendance for today only. To fix a past day, ask the admin.') }}</p>
                @endif
            </div>
        </div>
    </section>
@endsection
