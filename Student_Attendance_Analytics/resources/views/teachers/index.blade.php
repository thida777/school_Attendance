@extends('layouts.app')

@section('title', __('Teachers'))

@php
    $km = app()->getLocale() === 'km';
    $dialogOpen = $errors->any() && old('_modal') === 'teacher-form';
@endphp

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm opacity-60">{{ __(':count teachers · each teacher logs in to the Android app', ['count' => $total]) }}</p>
        <button class="btn btn-primary" data-form-dialog="teacher-form" data-action="{{ route('teachers.store') }}" data-method="POST" data-title="{{ __('Add teacher') }}">
            <i data-lucide="plus" class="size-4"></i>{{ __('Add teacher') }}
        </button>
    </div>

    <div class="card bg-base-100 border-base-300 border">
        <div class="card-body">
            <form method="GET" action="{{ route('teachers.index') }}" class="flex flex-wrap gap-2">
                <label class="input grow sm:max-w-xs">
                    <i data-lucide="search" class="size-4 opacity-50"></i>
                    <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('Search teacher name or phone') }}" />
                </label>
                <button class="btn"><i data-lucide="search" class="size-4"></i>{{ __('Search') }}</button>
                @if ($search !== '')
                    <a href="{{ route('teachers.index') }}" class="btn btn-ghost">{{ __('Clear') }}</a>
                @endif
            </form>

            @if ($teachers->isEmpty())
                <div class="py-12 text-center">
                    <i data-lucide="graduation-cap" class="mx-auto size-10 opacity-40"></i>
                    <p class="mt-2 font-medium">{{ __('No teachers found.') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('Teacher') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Subjects') }}</th><th>{{ __('Classes') }}</th>
                                <th>{{ __('Sessions this week') }}</th><th>{{ __('Account') }}</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($teachers as $teacher)
                                @php
                                    $subjects = $teacher->teachingAssignments->pluck('subject')->unique('id')
                                        ->map(fn ($s) => $km ? $s->name_km : $s->name_en)->sort()->join(', ');
                                    $classNames = $teacher->teachingAssignments->pluck('schoolClass.name')->unique()->sort()->join(', ');
                                    $initials = collect(explode(' ', $teacher->name))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->join('');
                                    $done = $recorded[$teacher->id] ?? 0;
                                    $due = $planned[$teacher->id] ?? 0;
                                @endphp
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <div class="avatar avatar-placeholder"><div class="bg-base-300 w-9 rounded-full"><span class="text-xs">{{ $initials }}</span></div></div>
                                            <div>
                                                <div class="font-medium">{{ $km ? $teacher->name_km : $teacher->name }}</div>
                                                <div class="text-xs opacity-60">{{ $km ? $teacher->name : $teacher->name_km }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap">{{ $teacher->phone ? trim(chunk_split($teacher->phone, 3, ' ')) : '—' }}</td>
                                    <td>{{ $subjects ?: '—' }}</td>
                                    <td>{{ $classNames ?: '—' }}</td>
                                    <td @class(['text-warning' => $done < $due])>{{ $due ? __(':done of :due recorded', ['done' => $done, 'due' => $due]) : '—' }}</td>
                                    <td>
                                        @if ($teacher->last_login_at)
                                            <span class="badge badge-soft badge-success" title="{{ __('Last login') }}: {{ $teacher->last_login_at->diffForHumans() }}">{{ __('Active') }}</span>
                                        @else
                                            <span class="badge badge-soft">{{ __('Not logged in yet') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-right whitespace-nowrap">
                                        <button class="btn btn-ghost btn-xs btn-square" aria-label="{{ __('Edit') }}"
                                            data-form-dialog="teacher-form" data-action="{{ route('teachers.update', $teacher) }}" data-method="PUT"
                                            data-title="{{ __('Edit teacher') }}"
                                            data-values="{{ json_encode($teacher->only(['name_km', 'name', 'phone', 'email'])) }}">
                                            <i data-lucide="pencil" class="size-4"></i>
                                        </button>
                                        <button class="btn btn-ghost btn-xs btn-square" aria-label="{{ __('Delete') }}"
                                            data-delete="{{ route('teachers.destroy', $teacher) }}" data-name="{{ $teacher->name }}">
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

    {{-- Add / edit teacher --}}
    <dialog id="teacher-form" class="modal" @if ($dialogOpen) data-reopen @endif>
        <div class="modal-box max-w-lg">
            <h3 class="text-lg font-semibold" data-dialog-title>{{ old('_title', __('Add teacher')) }}</h3>
            <form method="POST" action="{{ old('_action', route('teachers.store')) }}" class="mt-2 grid grid-cols-1 gap-x-4 sm:grid-cols-2" data-dialog-form>
                @csrf
                <input type="hidden" name="_method" value="{{ old('_method', 'POST') }}">
                <input type="hidden" name="_action" value="{{ old('_action', route('teachers.store')) }}">
                <input type="hidden" name="_title" value="{{ old('_title', __('Add teacher')) }}">
                <input type="hidden" name="_modal" value="teacher-form">

                @include('partials.field', ['name' => 'name_km', 'label' => __('Name (Khmer)'), 'placeholder' => 'ប៉ែន សុវណ្ណ'])
                @include('partials.field', ['name' => 'name', 'label' => __('Name (Latin)'), 'placeholder' => 'Pen Sovann'])
                @include('partials.field', ['name' => 'phone', 'label' => __('Phone'), 'placeholder' => '012 345 678', 'hint' => __('Used to log in.')])
                @include('partials.field', ['name' => 'email', 'label' => __('Email'), 'type' => 'email', 'placeholder' => 'teacher@school.edu.kh', 'required' => false])
                @include('partials.field', ['name' => 'password', 'label' => __('Password'), 'type' => 'password', 'span' => true,
                    'required' => false, 'markOptional' => false,
                    'hint' => __('At least 8 characters. When adding: the first password, the teacher can change it later. When editing: leave empty to keep the old password.')])

                <div class="modal-action sm:col-span-2">
                    <button type="button" class="btn" onclick="this.closest('dialog').close()">{{ __('Cancel') }}</button>
                    <button class="btn btn-primary">{{ __('Save teacher') }}</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>{{ __('Close') }}</button></form>
    </dialog>
@endsection
