@extends('layouts.app')

@section('title', __('Students'))

@php
    use App\Support\Risk;
    $km = app()->getLocale() === 'km';
@endphp

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm opacity-60">{{ __(':count students in the school', ['count' => $total]) }}</p>
        <button class="btn btn-primary" data-form-dialog="student-form" data-action="{{ route('students.store') }}" data-method="POST" data-title="{{ __('Add student') }}">
            <i data-lucide="plus" class="size-4"></i>{{ __('Add student') }}
        </button>
    </div>

    <div class="card bg-base-100 border-base-300 border scroll-mt-4" data-list>
        <div class="card-body">
            {{-- Filters: the search runs on the server, so it works on every page of results --}}
            <form method="GET" action="{{ route('students.index') }}" class="flex flex-wrap gap-2" data-auto-submit>
                <label class="input grow sm:max-w-xs">
                    <i data-lucide="search" class="size-4 opacity-50"></i>
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Search name or student code') }}" />
                </label>
                <select name="class" class="select w-auto" aria-label="{{ __('Class') }}" data-picker data-filter data-icon="school">
                    <option value="">{{ __('All classes') }}</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected(($filters['class'] ?? '') == $class->id)>{{ $class->name }}</option>
                    @endforeach
                </select>
                <select name="gender" class="select w-auto" aria-label="{{ __('Gender') }}" data-picker data-filter data-icon="users">
                    <option value="">{{ __('All genders') }}</option>
                    <option value="male" @selected(($filters['gender'] ?? '') === 'male')>{{ __('Male') }}</option>
                    <option value="female" @selected(($filters['gender'] ?? '') === 'female')>{{ __('Female') }}</option>
                </select>
                <select name="status" class="select w-auto" aria-label="{{ __('Status') }}" data-picker data-filter data-icon="user-check">
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
                                    $level = $risks->get($student->id)?->level;
                                    $guardian = $student->guardians->first();
                                @endphp
                                <tr @class(['opacity-60' => $student->status === 'inactive'])>
                                    <td>{{ $student->student_code }}</td>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            @include('partials.student-avatar', ['student' => $student, 'size' => 'size-9', 'text' => 'text-xs'])
                                            <div class="min-w-0">
                                                <a href="{{ route('students.show', $student) }}" class="link link-hover font-medium">{{ $km ? $student->name_km : $student->name_latin }}</a>
                                                <div class="text-xs opacity-60">{{ $km ? $student->name_latin : $student->name_km }}</div>
                                            </div>
                                        </div>
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
                                            data-values="{{ json_encode($student->formValues()) }}">
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

    @include('students._form')
@endsection
