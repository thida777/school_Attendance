@extends('layouts.app')

@section('title', __('Teaching Assignments'))

@php
    $km = app()->getLocale() === 'km';
    $dialogOpen = $errors->any() && old('_modal') === 'assignment-form';
@endphp

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm opacity-60">{{ __('Who teaches which subject in which class. A teacher only sees their own classes in the app.') }}</p>
        <button class="btn btn-primary" data-form-dialog="assignment-form" data-action="{{ route('assignments.store') }}" data-method="POST" data-title="{{ __('Add assignment') }}">
            <i data-lucide="plus" class="size-4"></i>{{ __('Add assignment') }}
        </button>
    </div>

    <div class="card bg-base-100 border-base-300 border">
        <div class="card-body">
            <form method="GET" action="{{ route('assignments.index') }}" class="flex flex-wrap gap-2" data-auto-submit>
                <select name="class" class="select w-auto" aria-label="{{ __('Class') }}">
                    <option value="">{{ __('All classes') }}</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected(($filters['class'] ?? '') == $class->id)>{{ $class->name }}</option>
                    @endforeach
                </select>
                <select name="teacher" class="select w-auto" aria-label="{{ __('Teacher') }}">
                    <option value="">{{ __('All teachers') }}</option>
                    @foreach ($teachers as $teacher)
                        <option value="{{ $teacher->id }}" @selected(($filters['teacher'] ?? '') == $teacher->id)>{{ $teacher->name }}</option>
                    @endforeach
                </select>
                @if (array_filter($filters))
                    <a href="{{ route('assignments.index') }}" class="btn btn-ghost">{{ __('Clear') }}</a>
                @endif
                <span class="ms-auto self-center text-sm opacity-60">{{ __(':count of :total assignments', ['count' => $assignments->total(), 'total' => $total]) }}</span>
            </form>

            @if ($assignments->isEmpty())
                <div class="py-12 text-center">
                    <i data-lucide="calendar-check" class="mx-auto size-10 opacity-40"></i>
                    <p class="mt-2 font-medium">{{ __('No assignments found.') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr><th>{{ __('Class') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Teacher') }}</th><th class="num">{{ __('Hours / week') }}</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($assignments as $assignment)
                                <tr>
                                    <td><span class="badge badge-ghost">{{ $assignment->schoolClass->name }}</span></td>
                                    <td class="font-medium">{{ $km ? $assignment->subject->name_km : $assignment->subject->name_en }}</td>
                                    <td>{{ $km ? $assignment->teacher->name_km : $assignment->teacher->name }}</td>
                                    <td class="num">{{ $assignment->subject->hours_per_week }}</td>
                                    <td class="text-right whitespace-nowrap">
                                        <button class="btn btn-ghost btn-xs btn-square" aria-label="{{ __('Edit') }}"
                                            data-form-dialog="assignment-form" data-action="{{ route('assignments.update', $assignment) }}" data-method="PUT"
                                            data-title="{{ __('Edit assignment') }}"
                                            data-values="{{ json_encode($assignment->only(['school_class_id', 'subject_id', 'teacher_id'])) }}">
                                            <i data-lucide="pencil" class="size-4"></i>
                                        </button>
                                        <button class="btn btn-ghost btn-xs btn-square" aria-label="{{ __('Delete') }}"
                                            data-delete="{{ route('assignments.destroy', $assignment) }}"
                                            data-name="{{ $assignment->schoolClass->name }} · {{ $assignment->subject->name_en }}">
                                            <i data-lucide="trash-2" class="size-4"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $assignments->links('partials.pagination') }}
            @endif
        </div>
    </div>

    {{-- Add / edit assignment --}}
    <dialog id="assignment-form" class="modal" @if ($dialogOpen) data-reopen @endif>
        <div class="modal-box max-w-md">
            <h3 class="text-lg font-semibold" data-dialog-title>{{ old('_title', __('Add assignment')) }}</h3>
            <form method="POST" action="{{ old('_action', route('assignments.store')) }}" class="mt-2" data-dialog-form>
                @csrf
                <input type="hidden" name="_method" value="{{ old('_method', 'POST') }}">
                <input type="hidden" name="_action" value="{{ old('_action', route('assignments.store')) }}">
                <input type="hidden" name="_title" value="{{ old('_title', __('Add assignment')) }}">
                <input type="hidden" name="_modal" value="assignment-form">

                @include('partials.field', ['name' => 'school_class_id', 'label' => __('Class'), 'options' => $classes->pluck('name', 'id')->all()])
                @include('partials.field', ['name' => 'subject_id', 'label' => __('Subject'), 'options' => $subjects->pluck($km ? 'name_km' : 'name_en', 'id')->all()])
                @include('partials.field', ['name' => 'teacher_id', 'label' => __('Teacher'), 'options' => $teachers->pluck('name', 'id')->all(),
                    'hint' => __('A new teacher also takes over this subject\'s lessons in the timetable.')])

                <div class="modal-action">
                    <button type="button" class="btn" onclick="this.closest('dialog').close()">{{ __('Cancel') }}</button>
                    <button class="btn btn-primary">{{ __('Save assignment') }}</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>{{ __('Close') }}</button></form>
    </dialog>
@endsection
