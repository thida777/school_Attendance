@extends('layouts.app')

@section('title', __('Parents'))

@php
    $km = app()->getLocale() === 'km';
    $dialogOpen = $errors->any() && old('_modal') === 'parent-form';
    $childOptions = $students->mapWithKeys(fn ($s) => [$s->id => ($km ? $s->name_km : $s->name_latin).' · '.$s->schoolClass->name])->all();
@endphp

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm opacity-60">{{ __('Parents are contacts only. The school sends them absence alerts; they do not log in.') }}</p>
        <button class="btn btn-primary" data-form-dialog="parent-form" data-action="{{ route('parents.store') }}" data-method="POST" data-title="{{ __('Add parent') }}">
            <i data-lucide="plus" class="size-4"></i>{{ __('Add parent') }}
        </button>
    </div>

    <div class="card bg-base-100 border-base-300 border">
        <div class="card-body">
            <form method="GET" action="{{ route('parents.index') }}" class="flex flex-wrap gap-2">
                <label class="input grow sm:max-w-xs">
                    <i data-lucide="search" class="size-4 opacity-50"></i>
                    <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('Search parent, phone or child') }}" />
                </label>
                <button class="btn"><i data-lucide="search" class="size-4"></i>{{ __('Search') }}</button>
                @if ($search !== '')
                    <a href="{{ route('parents.index') }}" class="btn btn-ghost">{{ __('Clear') }}</a>
                @endif
            </form>

            @if ($guardians->isEmpty())
                <div class="py-12 text-center">
                    <i data-lucide="contact" class="mx-auto size-10 opacity-40"></i>
                    <p class="mt-2 font-medium">{{ __('No parents found.') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr><th>{{ __('Parent') }}</th><th>{{ __('Relation') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Children') }}</th><th>{{ __('Last notified') }}</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($guardians as $guardian)
                                @php $first = $guardian->students->first(); @endphp
                                <tr>
                                    <td>
                                        <div class="font-medium">{{ $km && $guardian->name_km ? $guardian->name_km : $guardian->name }}</div>
                                        @if ($guardian->name_km)<div class="text-xs opacity-60">{{ $km ? $guardian->name : $guardian->name_km }}</div>@endif
                                    </td>
                                    <td>{{ $guardian->students->pluck('pivot.relation')->unique()->map(fn ($r) => __(ucfirst($r)))->join(', ') ?: '—' }}</td>
                                    <td class="whitespace-nowrap">
                                        <div>{{ trim(chunk_split($guardian->phone, 3, ' ')) }}</div>
                                        <div class="flex items-center gap-1 text-xs opacity-60">
                                            <i data-lucide="{{ $guardian->telegram_chat_id ? 'send' : 'message-square' }}" class="size-3"></i>{{ $guardian->telegram_chat_id ? 'Telegram' : 'SMS' }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-1">
                                            @forelse ($guardian->students as $child)
                                                <span class="badge badge-ghost gap-1 pe-1">
                                                    {{ $km ? $child->name_km : $child->name_latin }} · {{ $child->schoolClass->name }}
                                                    <button class="hover:text-error opacity-60 hover:opacity-100" aria-label="{{ __('Remove child') }}"
                                                        data-delete="{{ route('parents.unlink', [$guardian, $child]) }}"
                                                        data-name="{{ $child->name_latin }} ↔ {{ $guardian->name }}">
                                                        <i data-lucide="x" class="size-3"></i>
                                                    </button>
                                                </span>
                                            @empty
                                                <span class="opacity-60">—</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap">
                                        @if ($guardian->last_notified_at)
                                            {{ \Illuminate\Support\Carbon::parse($guardian->last_notified_at)->translatedFormat('D j M Y') }}
                                        @else
                                            <span class="opacity-60">{{ __('Never') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-right whitespace-nowrap">
                                        <button class="btn btn-ghost btn-xs btn-square" aria-label="{{ __('Edit') }}"
                                            data-form-dialog="parent-form" data-action="{{ route('parents.update', $guardian) }}" data-method="PUT"
                                            data-title="{{ __('Edit parent') }}"
                                            data-values="{{ json_encode($guardian->only(['name_km', 'name', 'phone', 'telegram_chat_id']) + [
                                                'relation' => $first?->pivot->relation ?? 'guardian',
                                                'student_id' => $first?->id,
                                            ]) }}">
                                            <i data-lucide="pencil" class="size-4"></i>
                                        </button>
                                        <button class="btn btn-ghost btn-xs btn-square" aria-label="{{ __('Delete') }}"
                                            data-delete="{{ route('parents.destroy', $guardian) }}" data-name="{{ $guardian->name }}">
                                            <i data-lucide="trash-2" class="size-4"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $guardians->links('partials.pagination') }}
            @endif
        </div>
    </div>

    {{-- Add / edit parent --}}
    <dialog id="parent-form" class="modal" @if ($dialogOpen) data-reopen @endif>
        <div class="modal-box max-w-lg">
            <h3 class="text-lg font-semibold" data-dialog-title>{{ old('_title', __('Add parent')) }}</h3>
            <form method="POST" action="{{ old('_action', route('parents.store')) }}" class="mt-2 grid grid-cols-1 gap-x-4 sm:grid-cols-2" data-dialog-form>
                @csrf
                <input type="hidden" name="_method" value="{{ old('_method', 'POST') }}">
                <input type="hidden" name="_action" value="{{ old('_action', route('parents.store')) }}">
                <input type="hidden" name="_title" value="{{ old('_title', __('Add parent')) }}">
                <input type="hidden" name="_modal" value="parent-form">

                @include('partials.field', ['name' => 'name_km', 'label' => __('Name (Khmer)'), 'placeholder' => 'សុខ ចន្ថា', 'required' => false])
                @include('partials.field', ['name' => 'name', 'label' => __('Name (Latin)'), 'placeholder' => 'Sok Chantha'])
                @include('partials.field', ['name' => 'phone', 'label' => __('Phone (for absence alerts)'), 'placeholder' => '012 111 222'])
                @include('partials.field', ['name' => 'telegram_chat_id', 'label' => __('Telegram chat ID'), 'placeholder' => '123456789', 'required' => false,
                    'hint' => __('Empty = alerts are sent by SMS.')])
                @include('partials.field', ['name' => 'student_id', 'label' => __('Child'), 'options' => $childOptions,
                    'hint' => __('To link another child, edit the parent, choose the child and save again.')])
                @include('partials.field', ['name' => 'relation', 'label' => __('Relation'), 'options' => ['father' => __('Father'), 'mother' => __('Mother'), 'guardian' => __('Guardian')]])

                <div class="modal-action sm:col-span-2">
                    <button type="button" class="btn" onclick="this.closest('dialog').close()">{{ __('Cancel') }}</button>
                    <button class="btn btn-primary">{{ __('Save parent') }}</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>{{ __('Close') }}</button></form>
    </dialog>
@endsection
