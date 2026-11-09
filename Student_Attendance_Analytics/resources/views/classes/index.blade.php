@extends('layouts.app')

@section('title', __('Classes'))

@php
    use App\Support\Risk;
    $dialogOpen = $errors->any() && old('_modal') === 'class-form';
    $teacherOptions = ['' => __('No homeroom teacher yet')] + $teachers->pluck('name', 'id')->all();
@endphp

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm opacity-60">
            {{ __(':count classes', ['count' => $classes->count()]) }}
            @if ($classes->isNotEmpty()) · {{ __('grades :from to :to', ['from' => $classes->min('grade'), 'to' => $classes->max('grade')]) }} @endif
            @if ($year) · {{ __('academic year :name', ['name' => $year->name]) }} @endif
        </p>
        <button class="btn btn-primary" data-form-dialog="class-form" data-action="{{ route('classes.store') }}" data-method="POST" data-title="{{ __('Add class') }}">
            <i data-lucide="plus" class="size-4"></i>{{ __('Add class') }}
        </button>
    </div>

    @if ($classes->isEmpty())
        <div class="card bg-base-100 border-base-300 border">
            <div class="card-body py-12 text-center">
                <i data-lucide="school" class="mx-auto size-10 opacity-40"></i>
                <p class="mt-2 font-medium">{{ __('No classes yet.') }}</p>
                <p class="text-sm opacity-60">{{ __('Add the first class with the button above.') }}</p>
            </div>
        </div>
    @else
        {{-- One card per class --}}
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($classes as $class)
                @php $rate = Risk::rate($class->records_present, $class->records_total); @endphp
                <div class="card bg-base-100 border-base-300 border">
                    <div class="card-body">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="card-title">{{ $class->name }}</h3>
                                <p class="text-sm opacity-60">{{ __('Grade') }} {{ $class->grade }}@if ($class->room) · {{ __('Room') }} {{ $class->room }}@endif</p>
                            </div>
                            @if ($rate === null)
                                <span class="badge badge-soft badge-ghost">{{ __('No records yet') }}</span>
                            @else
                                <span class="badge badge-soft {{ Risk::badge(Risk::level($rate)) }}">{{ $rate }}%</span>
                            @endif
                        </div>
                        <progress class="progress {{ Risk::progress($rate) }} mt-2" value="{{ $rate ?? 0 }}" max="100"></progress>
                        <dl class="mt-2 grid grid-cols-2 gap-y-1 text-sm">
                            <dt class="opacity-60">{{ __('Homeroom teacher') }}</dt>
                            <dd class="text-right">{{ $class->homeroomTeacher?->name ?? '—' }}</dd>
                            <dt class="opacity-60">{{ __('Students') }}</dt>
                            <dd class="text-right">{{ __(':count (:girls girls)', ['count' => $class->students_count, 'girls' => $class->girls_count]) }}</dd>
                        </dl>
                        <div class="card-actions mt-2 items-center justify-end">
                            <button class="btn btn-ghost btn-sm btn-square" aria-label="{{ __('Edit') }}"
                                data-form-dialog="class-form" data-action="{{ route('classes.update', $class) }}" data-method="PUT"
                                data-title="{{ __('Edit class') }}"
                                data-values="{{ json_encode($class->only(['name', 'grade', 'room', 'homeroom_teacher_id'])) }}">
                                <i data-lucide="pencil" class="size-4"></i>
                            </button><button class="btn btn-ghost btn-sm btn-square -ms-1 me-auto" aria-label="{{ __('Delete') }}"
                                data-delete="{{ route('classes.destroy', $class) }}" data-name="{{ __('Class') }} {{ $class->name }}">
                                <i data-lucide="trash-2" class="size-4"></i>
                            </button>
                            <a href="{{ route('students.index', ['class' => $class->id]) }}" class="btn btn-sm">{{ __('Students') }}</a>
                            <a href="{{ route('attendance.take') }}" class="btn btn-sm">{{ __('Take attendance') }}</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Add / edit class --}}
    <dialog id="class-form" class="modal" @if ($dialogOpen) data-reopen @endif>
        <div class="modal-box max-w-lg">
            <h3 class="text-lg font-semibold" data-dialog-title>{{ old('_title', __('Add class')) }}</h3>
            <form method="POST" action="{{ old('_action', route('classes.store')) }}" class="mt-2 grid grid-cols-1 gap-x-4 sm:grid-cols-2" data-dialog-form>
                @csrf
                <input type="hidden" name="_method" value="{{ old('_method', 'POST') }}">
                <input type="hidden" name="_action" value="{{ old('_action', route('classes.store')) }}">
                <input type="hidden" name="_title" value="{{ old('_title', __('Add class')) }}">
                <input type="hidden" name="_modal" value="class-form">

                @include('partials.field', ['name' => 'name', 'label' => __('Class name'), 'placeholder' => '10C'])
                @include('partials.field', ['name' => 'grade', 'label' => __('Grade'), 'options' => [10 => '10', 11 => '11', 12 => '12']])
                @include('partials.field', ['name' => 'room', 'label' => __('Room'), 'placeholder' => '103', 'required' => false])
                @include('partials.field', ['name' => 'homeroom_teacher_id', 'label' => __('Homeroom teacher'), 'options' => $teacherOptions, 'required' => false])

                <div class="modal-action sm:col-span-2">
                    <button type="button" class="btn" onclick="this.closest('dialog').close()">{{ __('Cancel') }}</button>
                    <button class="btn btn-primary">{{ __('Save class') }}</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>{{ __('Close') }}</button></form>
    </dialog>
@endsection
