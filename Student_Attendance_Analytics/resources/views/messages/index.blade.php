@extends('layouts.app')

@section('title', __('Parent Messages'))

@php
    $km = app()->getLocale() === 'km';
    $typeLabel = ['absence_alert' => __('Absence alert'), 'manual' => __('Written by staff'), 'monthly_report' => __('Monthly report')];
@endphp

@section('content')
    {{-- Live or demo: say it clearly, so nobody thinks a demo message reached a parent --}}
    @if ($live)
        <div role="alert" class="alert alert-soft alert-success">
            <i data-lucide="send" class="size-5"></i>
            <span>{{ __('Telegram is connected. Parents who joined the school bot receive messages on Telegram. SMS is saved as a demo (no SMS provider yet).') }}</span>
        </div>
    @else
        <div role="alert" class="alert alert-soft alert-warning">
            <i data-lucide="flask-conical" class="size-5"></i>
            <span>{{ __('Demo mode: messages are written and saved here, but not really sent. Add TELEGRAM_BOT_TOKEN to the .env file to send real Telegram messages.') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <div class="stats bg-base-100 border-base-300 border">
            <div class="stat">
                <div class="stat-figure" style="--chip: var(--color-primary)"><i data-lucide="mail"></i></div>
                <div class="stat-title">{{ __('Messages this month') }}</div>
                <div class="stat-value">{{ $counts['month'] }}</div>
                <div class="stat-desc">{{ __(':count automatic absence alerts', ['count' => $counts['alerts']]) }}</div>
            </div>
        </div>
        <a href="{{ route('messages', ['status' => 'delivered']) }}" class="stats bg-base-100 border-base-300 border transition hover:shadow-md">
            <div class="stat">
                <div class="stat-figure" style="--chip: var(--color-success)"><i data-lucide="check-check"></i></div>
                <div class="stat-title">{{ __('Delivered') }}</div>
                <div class="stat-value">{{ $counts['delivered'] }}</div>
                <div class="stat-desc">{{ __('by Telegram, this month') }}</div>
            </div>
        </a>
        <a href="{{ route('messages', ['status' => 'simulated']) }}" class="stats bg-base-100 border-base-300 border transition hover:shadow-md">
            <div class="stat">
                <div class="stat-figure" style="--chip: var(--color-warning)"><i data-lucide="flask-conical"></i></div>
                <div class="stat-title">{{ __('Demo (not sent)') }}</div>
                <div class="stat-value">{{ $counts['simulated'] }}</div>
                <div class="stat-desc">{{ __('saved only, this month') }}</div>
            </div>
        </a>
        <a href="{{ route('messages', ['status' => 'failed']) }}" class="stats bg-base-100 border-base-300 border transition hover:shadow-md">
            <div class="stat">
                <div class="stat-figure" style="--chip: var(--color-error)"><i data-lucide="circle-x"></i></div>
                <div class="stat-title">{{ __('Failed') }}</div>
                <div @class(['stat-value', 'text-error' => $counts['failed'] > 0])>{{ $counts['failed'] }}</div>
                <div class="stat-desc">{{ __('press Resend to try again') }}</div>
            </div>
        </a>
    </div>

    <div class="card bg-base-100 border-base-300 border" data-list>
        <div class="card-body">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <form method="GET" action="{{ route('messages') }}" class="flex flex-wrap gap-2" data-auto-submit>
                    <label class="input w-full sm:w-64">
                        <i data-lucide="search" class="size-4 opacity-50"></i>
                        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Search name or student code') }}" />
                    </label>
                    <select name="status" class="select w-auto" aria-label="{{ __('Status') }}" data-picker data-filter data-icon="check-check">
                        <option value="">{{ __('All statuses') }}</option>
                        <option value="delivered" @selected(($filters['status'] ?? '') === 'delivered')>{{ __('Delivered') }}</option>
                        <option value="simulated" @selected(($filters['status'] ?? '') === 'simulated')>{{ __('Demo (not sent)') }}</option>
                        <option value="failed" @selected(($filters['status'] ?? '') === 'failed')>{{ __('Failed') }}</option>
                    </select>
                    <select name="type" class="select w-auto" aria-label="{{ __('Type') }}" data-picker data-filter data-icon="mail">
                        <option value="">{{ __('All messages') }}</option>
                        <option value="absence_alert" @selected(($filters['type'] ?? '') === 'absence_alert')>{{ __('Absence alert') }}</option>
                        <option value="manual" @selected(($filters['type'] ?? '') === 'manual')>{{ __('Written by staff') }}</option>
                    </select>
                    @if (array_filter($filters))
                        <a href="{{ route('messages') }}" class="btn btn-ghost">{{ __('Clear') }}</a>
                    @endif
                </form>
            </div>

            @if ($messages->isEmpty())
                <div class="py-12 text-center">
                    <i data-lucide="mail" class="mx-auto size-10 opacity-40"></i>
                    <p class="mt-2 font-medium">{{ array_filter($filters) ? __('No messages match this filter.') : __('No messages yet.') }}</p>
                    <p class="text-sm opacity-60">{{ __('Parents get a message when their child is absent :days school days in a row. You can also write one from a student\'s page.', ['days' => $streakDays]) }}</p>
                </div>
            @else
                <ul class="divide-base-300 divide-y">
                    @foreach ($messages as $message)
                        @php $s = $message->student; @endphp
                        <li class="py-3">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <a href="{{ route('students.show', $s) }}" class="link link-hover font-medium">{{ $km ? $s->name_km : $s->name_latin }}</a>
                                    <span class="text-sm opacity-60">· {{ $s->schoolClass->name }}</span>
                                    <div class="text-sm opacity-70">
                                        <i data-lucide="{{ $message->channel === 'telegram' ? 'send' : 'message-square' }}" class="inline size-3.5"></i>
                                        {{ $message->guardian->name }} · {{ $message->channel === 'telegram' ? 'Telegram' : 'SMS '.$message->guardian->phone }}
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 text-right">
                                    <span class="badge badge-soft {{ $message->type === 'absence_alert' ? 'badge-error' : 'badge-info' }}">{{ $typeLabel[$message->type] ?? $message->type }}</span>
                                    @if ($message->status === 'failed')
                                        <span class="badge badge-error">{{ __('Failed') }}</span>
                                        <form method="POST" action="{{ route('messages.resend', $message) }}">
                                            @csrf
                                            <button class="btn btn-xs btn-primary"><i data-lucide="rotate-cw" class="size-3.5"></i>{{ __('Resend') }}</button>
                                        </form>
                                    @elseif ($message->simulated)
                                        <span class="badge badge-warning badge-soft" title="{{ __('Saved only. Not really sent.') }}">{{ __('Demo (not sent)') }}</span>
                                    @else
                                        <span class="badge badge-success badge-soft"><i data-lucide="check-check" class="size-3"></i>{{ __('Delivered') }}</span>
                                    @endif
                                    <span class="text-xs whitespace-nowrap opacity-60">
                                        {{ $message->created_at->translatedFormat('j M, H:i') }} · {{ $message->sentBy?->name ?? __('System') }}
                                    </span>
                                </div>
                            </div>
                            {{-- The parent's answer with a button under the Telegram message --}}
                            @if ($message->reply)
                                <p class="bg-success/10 text-success mt-2 inline-flex items-center gap-2 rounded-full px-3 py-1 text-sm font-medium">
                                    <i data-lucide="reply" class="size-4"></i>
                                    {{ __('Parent replied:') }} {{ $message->replyLabel() }}
                                    <span class="text-xs font-normal opacity-70">· {{ $message->replied_at->translatedFormat('j M, H:i') }}</span>
                                </p>
                            @elseif ($message->type === 'absence_alert' && $message->channel === 'telegram' && ! $message->simulated && $message->status === 'sent')
                                <p class="mt-2 inline-flex items-center gap-2 text-xs opacity-60">
                                    <i data-lucide="hourglass" class="size-3.5"></i>{{ __('Waiting for the parent to answer in Telegram') }}
                                </p>
                            @endif
                            <details class="mt-1">
                                <summary class="cursor-pointer text-sm opacity-80">{{ \Illuminate\Support\Str::limit(collect(preg_split('/\R/u', $message->body))->filter()->get(1) ?? $message->body, 120) }}</summary>
                                <p class="bg-base-200 mt-2 rounded-box p-3 text-sm whitespace-pre-line">{{ $message->body }}</p>
                                @if ($message->error)
                                    <p class="text-error mt-1 text-xs">{{ $message->error }}</p>
                                @endif
                            </details>
                        </li>
                    @endforeach
                </ul>
                {{ $messages->links('partials.pagination') }}
            @endif
        </div>
    </div>
@endsection
