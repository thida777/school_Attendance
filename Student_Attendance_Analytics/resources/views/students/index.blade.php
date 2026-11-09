@extends('layouts.app')

@section('title', __('Students'))

@php
    use App\Support\Risk;
    $km = app()->getLocale() === 'km';
    $dialogOpen = $errors->any() && old('_modal') === 'student-form';
@endphp

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm opacity-60">{{ __(':count students in the school', ['count' => $total]) }}</p>
        <button class="btn btn-primary" data-form-dialog="student-form" data-action="{{ route('students.store') }}" data-method="POST" data-title="{{ __('Add student') }}">
            <i data-lucide="plus" class="size-4"></i>{{ __('Add student') }}
        </button>
    </div>

    <div class="card bg-base-100 border-base-300 border">
        <div class="card-body">
            {{-- Filters: the search runs on the server, so it works on every page of results --}}
            <form method="GET" action="{{ route('students.index') }}" class="flex flex-wrap gap-2" data-auto-submit>
                <label class="input grow sm:max-w-xs">
                    <i data-lucide="search" class="size-4 opacity-50"></i>
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Search name or student code') }}" />
                </label>
                <select name="class" class="select w-auto" aria-label="{{ __('Class') }}">
                    <option value="">{{ __('All classes') }}</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected(($filters['class'] ?? '') == $class->id)>{{ $class->name }}</option>
                    @endforeach
                </select>
                <select name="gender" class="select w-auto" aria-label="{{ __('Gender') }}">
                    <option value="">{{ __('All genders') }}</option>
                    <option value="male" @selected(($filters['gender'] ?? '') === 'male')>{{ __('Male') }}</option>
                    <option value="female" @selected(($filters['gender'] ?? '') === 'female')>{{ __('Female') }}</option>
                </select>
                <select name="status" class="select w-auto" aria-label="{{ __('Status') }}">
                    <option value="">{{ __('Active and inactive') }}</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('Active') }}</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('Inactive') }}</option>
                </select>
                <button class="btn"><i data-lucide="search" class="size-4"></i>{{ __('Search') }}</button>
                @if (array_filter($filters))
                    <a href="{{ route('students.index') }}" class="btn btn-ghost">{{ __('Clear') }}</a>
                @endif
            </form>

            @if ($students->isEmpty())
                <div class="py-12 text-center">
                    <i data-lucide="users" class="mx-auto size-10 opacity-40"></i>
                    <p class="mt-2 font-medium">{{ __('No students found.') }}</p>
                    <p class="text-sm opacity-60">{{ array_filter($filters) ? __('Try another search or clear the filters.') : __('Add the first student with the button above.') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('Code') }}</th><th>{{ __('Student') }}</th><th>{{ __('Gender') }}</th><th>{{ __('Class') }}</th>
                                <th>{{ __('Parent') }}</th><th>{{ __('Attendance rate') }}</th><th>{{ __('Risk') }}</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($students as $student)
                                @php
                                    $rate = Risk::rate($student->records_present, $student->records_total);
                                    $level = Risk::level($rate);
                                    $guardian = $student->guardians->first();
                                @endphp
                                <tr @class(['opacity-60' => $student->status === 'inactive'])>
                                    <td>{{ $student->student_code }}</td>
                                    <td>
                                        <div class="font-medium">{{ $km ? $student->name_km : $student->name_latin }}</div>
                                        <div class="text-xs opacity-60">{{ $km ? $student->name_latin : $student->name_km }}</div>
                                    </td>
                                    <td>{{ __(ucfirst($student->gender)) }}</td>
                                    <td>{{ $student->schoolClass->name }}</td>
                                    <td>
                                        @if ($guardian)
                                            <div>{{ $guardian->name }}</div>
                                            <div class="flex items-center gap-1 text-xs opacity-60">
                                                <i data-lucide="{{ $guardian->telegram_chat_id ? 'send' : 'message-square' }}" class="size-3"></i>
                                                {{ $guardian->telegram_chat_id ? 'Telegram' : 'SMS' }} · {{ $guardian->phone }}
                                            </div>
                                        @else
                                            <span class="opacity-60">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($rate === null)
                                            <span class="text-sm opacity-60">{{ __('No records yet') }}</span>
                                        @else
                                            <div class="flex items-center gap-2"><progress class="progress {{ Risk::progress($rate) }} w-24" value="{{ $rate }}" max="100"></progress><span>{{ $rate }}%</span></div>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($student->status === 'inactive')
                                            <span class="badge badge-soft badge-ghost">{{ __('Inactive') }}</span>
                                        @elseif ($level)
                                            <span class="badge badge-soft {{ Risk::badge($level) }}">{{ __(ucfirst($level)) }}</span>
                                        @else
                                            <span class="opacity-60">—</span>
                                        @endif
                                    </td>
                                    <td class="text-right whitespace-nowrap">
                                        <button class="btn btn-ghost btn-xs btn-square" aria-label="{{ __('Edit') }}"
                                            data-form-dialog="student-form"
                                            data-action="{{ route('students.update', $student) }}" data-method="PUT"
                                            data-title="{{ __('Edit student') }}"
                                            data-values="{{ json_encode([
                                                'name_km' => $student->name_km,
                                                'name_latin' => $student->name_latin,
                                                'student_code' => $student->student_code,
                                                'gender' => $student->gender,
                                                'school_class_id' => $student->school_class_id,
                                                'date_of_birth' => $student->date_of_birth?->toDateString(),
                                                'status' => $student->status,
                                                'guardian_name' => $guardian?->name,
                                                'guardian_phone' => $guardian?->phone,
                                                'guardian_relation' => $guardian?->pivot->relation ?? 'guardian',
                                            ]) }}">
                                            <i data-lucide="pencil" class="size-4"></i>
                                        </button>
                                        <button class="btn btn-ghost btn-xs btn-square" aria-label="{{ __('Delete') }}"
                                            data-delete="{{ route('students.destroy', $student) }}" data-name="{{ $student->name_latin }}">
                                            <i data-lucide="trash-2" class="size-4"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $students->links('partials.pagination') }}
            @endif
        </div>
    </div>

    {{-- Add / edit student --}}
    <dialog id="student-form" class="modal" @if ($dialogOpen) data-reopen @endif>
        <div class="modal-box max-w-2xl">
            <h3 class="text-lg font-semibold" data-dialog-title>{{ old('_title', __('Add student')) }}</h3>
            <form method="POST" action="{{ old('_action', route('students.store')) }}" class="mt-2 grid grid-cols-1 gap-x-4 sm:grid-cols-2" data-dialog-form>
                @csrf
                <input type="hidden" name="_method" value="{{ old('_method', 'POST') }}">
                <input type="hidden" name="_action" value="{{ old('_action', route('students.store')) }}">
                <input type="hidden" name="_title" value="{{ old('_title', __('Add student')) }}">
                <input type="hidden" name="_modal" value="student-form">

                @include('partials.field', ['name' => 'name_km', 'label' => __('Name (Khmer)'), 'placeholder' => 'សុខ ដារា'])
                @include('partials.field', ['name' => 'name_latin', 'label' => __('Name (Latin)'), 'placeholder' => 'Sok Dara'])
                @include('partials.field', ['name' => 'student_code', 'label' => __('Student code'), 'placeholder' => 'S-10A-008'])
                @include('partials.field', ['name' => 'gender', 'label' => __('Gender'), 'options' => ['male' => __('Male'), 'female' => __('Female')]])
                @include('partials.field', ['name' => 'school_class_id', 'label' => __('Class'), 'options' => $classes->pluck('name', 'id')->all()])
                @include('partials.field', ['name' => 'date_of_birth', 'label' => __('Date of birth'), 'type' => 'date', 'required' => false])
                @include('partials.field', ['name' => 'status', 'label' => __('Status'), 'options' => ['active' => __('Active'), 'inactive' => __('Inactive (left the school)')]])

                <div class="divider sm:col-span-2 my-1 text-xs opacity-70">{{ __('Parent (receives Telegram or SMS messages)') }}</div>
                @include('partials.field', ['name' => 'guardian_name', 'label' => __('Parent name'), 'placeholder' => 'Sok Chantha'])
                @include('partials.field', ['name' => 'guardian_phone', 'label' => __('Parent phone'), 'placeholder' => '012 345 678'])
                @include('partials.field', ['name' => 'guardian_relation', 'label' => __('Relation'), 'options' => ['father' => __('Father'), 'mother' => __('Mother'), 'guardian' => __('Guardian')]])

                <div class="modal-action sm:col-span-2">
                    <button type="button" class="btn" onclick="this.closest('dialog').close()">{{ __('Cancel') }}</button>
                    <button class="btn btn-primary">{{ __('Save student') }}</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>{{ __('Close') }}</button></form>
    </dialog>
@endsection
