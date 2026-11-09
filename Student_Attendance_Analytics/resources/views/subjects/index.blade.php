@extends('layouts.app')

@section('title', __('Subjects'))

@php
    $km = app()->getLocale() === 'km';
    $dialogOpen = $errors->any() && old('_modal') === 'subject-form';
@endphp

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm opacity-60">{{ __(':count subjects taught in the school', ['count' => $subjects->count()]) }}</p>
        <button class="btn btn-primary" data-form-dialog="subject-form" data-action="{{ route('subjects.store') }}" data-method="POST" data-title="{{ __('Add subject') }}">
            <i data-lucide="plus" class="size-4"></i>{{ __('Add subject') }}
        </button>
    </div>

    <div class="card bg-base-100 border-base-300 border">
        <div class="card-body">
            @if ($subjects->isEmpty())
                <div class="py-12 text-center">
                    <i data-lucide="book-open" class="mx-auto size-10 opacity-40"></i>
                    <p class="mt-2 font-medium">{{ __('No subjects yet.') }}</p>
                    <p class="text-sm opacity-60">{{ __('Add the first subject with the button above.') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('Code') }}</th><th>{{ __('Subject') }}</th><th>{{ $km ? __('English name') : __('Khmer name') }}</th>
                                <th class="num">{{ __('Hours / week') }}</th><th class="num">{{ __('Classes') }}</th><th class="num">{{ __('Teachers') }}</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($subjects as $subject)
                                <tr>
                                    <td>{{ $subject->code }}</td>
                                    <td class="font-medium">{{ $km ? $subject->name_km : $subject->name_en }}</td>
                                    <td>{{ $km ? $subject->name_en : $subject->name_km }}</td>
                                    <td class="num">{{ $subject->hours_per_week }}</td>
                                    <td class="num">{{ $subject->teachingAssignments->count() }}</td>
                                    <td class="num">{{ $subject->teachingAssignments->unique('teacher_id')->count() }}</td>
                                    <td class="text-right whitespace-nowrap">
                                        <button class="btn btn-ghost btn-xs btn-square" aria-label="{{ __('Edit') }}"
                                            data-form-dialog="subject-form" data-action="{{ route('subjects.update', $subject) }}" data-method="PUT"
                                            data-title="{{ __('Edit subject') }}"
                                            data-values="{{ json_encode($subject->only(['code', 'name_en', 'name_km', 'hours_per_week'])) }}">
                                            <i data-lucide="pencil" class="size-4"></i>
                                        </button>
                                        <button class="btn btn-ghost btn-xs btn-square" aria-label="{{ __('Delete') }}"
                                            data-delete="{{ route('subjects.destroy', $subject) }}" data-name="{{ $subject->name_en }}">
                                            <i data-lucide="trash-2" class="size-4"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Add / edit subject --}}
    <dialog id="subject-form" class="modal" @if ($dialogOpen) data-reopen @endif>
        <div class="modal-box max-w-lg">
            <h3 class="text-lg font-semibold" data-dialog-title>{{ old('_title', __('Add subject')) }}</h3>
            <form method="POST" action="{{ old('_action', route('subjects.store')) }}" class="mt-2 grid grid-cols-1 gap-x-4 sm:grid-cols-2" data-dialog-form>
                @csrf
                <input type="hidden" name="_method" value="{{ old('_method', 'POST') }}">
                <input type="hidden" name="_action" value="{{ old('_action', route('subjects.store')) }}">
                <input type="hidden" name="_title" value="{{ old('_title', __('Add subject')) }}">
                <input type="hidden" name="_modal" value="subject-form">

                @include('partials.field', ['name' => 'code', 'label' => __('Code'), 'placeholder' => 'GEO'])
                @include('partials.field', ['name' => 'hours_per_week', 'label' => __('Hours per week'), 'type' => 'number', 'placeholder' => '2'])
                @include('partials.field', ['name' => 'name_en', 'label' => __('Name (English)'), 'placeholder' => 'Geography'])
                @include('partials.field', ['name' => 'name_km', 'label' => __('Name (Khmer)'), 'placeholder' => 'ភូមិវិទ្យា'])

                <div class="modal-action sm:col-span-2">
                    <button type="button" class="btn" onclick="this.closest('dialog').close()">{{ __('Cancel') }}</button>
                    <button class="btn btn-primary">{{ __('Save subject') }}</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>{{ __('Close') }}</button></form>
    </dialog>
@endsection
