@extends('layouts.app')

@section('title', __('Corrections & Audit Log'))

@php
    $km = app()->getLocale() === 'km';
    $actionLabels = [
        'submitted' => __('Submitted'), 'corrected' => __('Corrected'),
        'approved' => __('Request approved'), 'rejected' => __('Request rejected'),
    ];
    $badge = fn ($status) => $status === 'present'
        ? '<span class="badge badge-soft badge-success">'.e(__('Present')).'</span>'
        : '<span class="badge badge-soft badge-error">'.e(__('Absent')).'</span>';
@endphp

@section('content')
    <div role="alert" class="alert alert-soft alert-info">
        <i data-lucide="clock" class="size-5"></i>
        <span>{{ __('Teachers can correct their own attendance for 24 hours. After that, they send a request here, and an Admin approves or rejects it. Every change is logged and can never be deleted.') }}</span>
    </div>

    @error('request')
        <div role="alert" class="alert alert-soft alert-warning"><i data-lucide="circle-alert" class="size-5"></i><span>{{ $message }}</span></div>
    @enderror

    {{-- Correction requests --}}
    <div class="card bg-base-100 border-base-300 border">
        <div class="card-body">
            <h3 class="card-title text-base">{{ __('Correction requests') }}
                <span @class(['badge', 'badge-warning' => $requests->isNotEmpty(), 'badge-ghost' => $requests->isEmpty()])>{{ $requests->count() }}</span>
            </h3>

            @if ($requests->isEmpty())
                <div class="flex flex-col items-center gap-2 py-8 text-center">
                    <span class="bg-success/10 text-success grid size-12 place-items-center rounded-full"><i data-lucide="inbox" class="size-6"></i></span>
                    <div class="font-medium">{{ __('No requests waiting.') }}</div>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Session') }}</th><th>{{ __('Change') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Requested by') }}</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($requests as $item)
                                @php $session = $item->record->session; @endphp
                                <tr>
                                    <td>
                                        <div class="font-medium">{{ $km ? $item->record->student->name_km : $item->record->student->name_latin }}</div>
                                        <div class="text-xs opacity-60">{{ $item->record->student->student_code }}</div>
                                    </td>
                                    <td>
                                        {{ $session->schoolClass->name }} · {{ $km ? $session->subject->name_km : $session->subject->name_en }} · P{{ $session->period }}
                                        <div class="text-xs opacity-60">{{ $session->date->translatedFormat('D j M Y') }}</div>
                                    </td>
                                    <td class="whitespace-nowrap">{!! $badge($item->old_status) !!} → {!! $badge($item->new_status) !!}</td>
                                    <td class="max-w-xs">{{ $item->reason }}</td>
                                    <td>
                                        {{ $item->changedBy->name }}
                                        <div class="text-xs opacity-60">{{ $item->created_at->translatedFormat('D j M Y, H:i') }}</div>
                                    </td>
                                    <td class="text-right whitespace-nowrap">
                                        <form method="POST" action="{{ route('attendance.corrections.approve', $item) }}" class="inline">
                                            @csrf
                                            <button class="btn btn-sm btn-success"><i data-lucide="check" class="size-4"></i>{{ __('Approve') }}</button>
                                        </form>
                                        <button class="btn btn-sm" data-form-dialog="reject-form" data-action="{{ route('attendance.corrections.reject', $item) }}" data-method="POST"
                                            data-title="{{ __('Reject request') }}: {{ $item->record->student->name_latin }}">
                                            <i data-lucide="x" class="size-4"></i>{{ __('Reject') }}
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

    {{-- Audit log --}}
    <div class="card bg-base-100 border-base-300 border">
        <div class="card-body">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="card-title text-base">{{ __('Audit log') }}</h3>
                <form method="GET" action="{{ route('attendance.corrections') }}" class="flex flex-wrap gap-2" data-auto-submit>
                    <label class="input w-auto"><i data-lucide="search" class="size-4 opacity-50"></i><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Search name, ID or class') }}" /></label>
                    <select name="action" class="select w-auto" aria-label="{{ __('Action') }}">
                        <option value="">{{ __('All actions') }}</option>
                        @foreach ($actionLabels as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['action'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            @if ($log->isEmpty())
                <div class="py-10 text-center opacity-60">{{ __('Nothing in the log yet.') }}</div>
            @else
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>{{ __('Time') }}</th><th>{{ __('User') }}</th><th>{{ __('Action') }}</th><th>{{ __('Record') }}</th><th>{{ __('Old → New') }}</th><th>{{ __('Reason') }}</th></tr></thead>
                        <tbody>
                            @foreach ($log as $event)
                                <tr>
                                    <td class="whitespace-nowrap">{{ $event->time?->translatedFormat('D j M Y, H:i') }}</td>
                                    <td>{{ $event->user ?? '—' }}</td>
                                    <td>
                                        <span @class(['badge badge-soft', 'badge-ghost' => $event->action === 'submitted', 'badge-info' => $event->action === 'corrected',
                                            'badge-success' => $event->action === 'approved', 'badge-error' => $event->action === 'rejected'])>{{ $actionLabels[$event->action] }}</span>
                                    </td>
                                    <td>{{ $event->record }}<div class="text-xs opacity-60">{{ $event->detail }}</div></td>
                                    <td class="whitespace-nowrap">
                                        @if ($event->old)
                                            {!! $badge($event->old) !!} → {!! $badge($event->new) !!}
                                        @else
                                            <span class="opacity-60">—</span>
                                        @endif
                                    </td>
                                    <td class="max-w-xs">{{ $event->reason ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $log->links('partials.pagination') }}
            @endif
            <p class="text-xs opacity-60">{{ __('The audit log is read-only. Nobody can edit or delete it.') }}</p>
        </div>
    </div>

    {{-- Reject with an optional note --}}
    <dialog id="reject-form" class="modal">
        <div class="modal-box max-w-md">
            <h3 class="text-lg font-semibold" data-dialog-title>{{ __('Reject request') }}</h3>
            <form method="POST" class="mt-2" data-dialog-form>
                @csrf
                <input type="hidden" name="_method" value="POST">
                <input type="hidden" name="_action">
                <input type="hidden" name="_title">
                <input type="hidden" name="_modal" value="reject-form">
                @include('partials.field', ['name' => 'decision_note', 'label' => __('Note to the teacher'), 'required' => false,
                    'placeholder' => __('e.g. No evidence; record kept as Absent')])
                <div class="modal-action">
                    <button type="button" class="btn" onclick="this.closest('dialog').close()">{{ __('Cancel') }}</button>
                    <button class="btn btn-error">{{ __('Reject') }}</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>{{ __('Close') }}</button></form>
    </dialog>
@endsection
