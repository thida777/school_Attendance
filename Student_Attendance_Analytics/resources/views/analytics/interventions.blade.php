@extends('layouts.app')

@section('title', __('Interventions'))

@php
    use App\Models\Intervention;
    use App\Support\Risk;
    $km = app()->getLocale() === 'km';
    $name = fn ($s) => $km ? $s->name_km : $s->name_latin;
    // Written out in full so Tailwind finds these classes
    $riskDot = ['critical' => 'bg-error', 'high' => 'bg-warning', 'medium' => 'bg-info'];
    $resultBadge = ['improved' => 'badge-success', 'none' => 'badge-ghost', 'declined' => 'badge-error'];
    $resultText = ['improved' => 'text-success', 'none' => 'opacity-70', 'declined' => 'text-error'];
    $resultLabel = ['improved' => __('Improved'), 'none' => __('No clear change'), 'declined' => __('Declined')];
    $formOpen = $openForm || ($errors->any() && old('_modal') === 'intervention-form');
    $closeOpen = $errors->any() && old('_modal') === 'close-form';
    $signed = fn (?float $n) => $n === null ? '—' : ($n > 0 ? '+' : ($n < 0 ? '−' : '')).number_format(abs($n), 1);
@endphp

@section('content')
    {{-- The loop the school follows --}}
    <ul class="steps steps-horizontal w-full text-xs">
        <li class="step step-primary">{{ __('Detect') }}</li>
        <li class="step step-primary">{{ __('Act') }}</li>
        <li class="step step-primary">{{ __('Measure') }}</li>
        <li class="step step-primary">{{ __('Evaluate') }}</li>
        <li class="step">{{ __('Improve') }}</li>
    </ul>

    <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <a href="{{ route('interventions', ['status' => 'open']) }}" class="stats bg-base-100 border-base-300 border transition hover:shadow-md">
            <div class="stat">
                <div class="stat-figure" style="--chip: var(--color-warning)"><i data-lucide="circle-dot"></i></div>
                <div class="stat-title">{{ __('Open') }}</div>
                <div class="stat-value">{{ $counts['open'] }}</div>
                <div class="stat-desc">{{ __('waiting for the follow-up day') }}</div>
            </div>
        </a>
        <a href="{{ route('interventions', ['status' => 'due']) }}" class="stats bg-base-100 border-base-300 border transition hover:shadow-md">
            <div class="stat">
                <div class="stat-figure" style="--chip: var(--color-error)"><i data-lucide="calendar-clock"></i></div>
                <div class="stat-title">{{ __('Follow-up due') }}</div>
                <div @class(['stat-value', 'text-error' => $counts['due'] > 0])>{{ $counts['due'] }}</div>
                <div class="stat-desc">{{ __('measure the result now') }}</div>
            </div>
        </a>
        <div class="stats bg-base-100 border-base-300 border">
            <div class="stat">
                <div class="stat-figure" style="--chip: var(--color-success)"><i data-lucide="trending-up"></i></div>
                <div class="stat-title">{{ __('Improved after action') }}</div>
                <div class="stat-value">{{ $counts['improved'] }} / {{ $counts['closed'] }}</div>
                <div class="stat-desc">{{ __('of closed follow-ups') }}</div>
            </div>
        </div>
        <div class="stats bg-base-100 border-base-300 border">
            <div class="stat">
                <div class="stat-figure" style="--chip: var(--color-primary)"><i data-lucide="chart-line"></i></div>
                <div class="stat-title">{{ __('Average change') }}</div>
                <div class="stat-value">{{ $signed($counts['average']) }}</div>
                <div class="stat-desc">{{ __('points, before → after') }}</div>
            </div>
        </div>
    </div>

    <div class="card bg-base-100 border-base-300 border" data-list>
        <div class="card-body">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <form method="GET" action="{{ route('interventions') }}" class="flex flex-wrap gap-2" data-auto-submit>
                    @isset($filters['student'])<input type="hidden" name="student" value="{{ $filters['student'] }}">@endisset
                    <label class="input w-full sm:w-64">
                        <i data-lucide="search" class="size-4 opacity-50"></i>
                        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Search name or student code') }}" />
                    </label>
                    <select name="status" class="select w-auto" aria-label="{{ __('Status') }}" data-picker data-filter data-icon="circle-dot">
                        <option value="">{{ __('All statuses') }}</option>
                        <option value="open" @selected(($filters['status'] ?? '') === 'open')>{{ __('Open') }}</option>
                        <option value="due" @selected(($filters['status'] ?? '') === 'due')>{{ __('Follow-up due') }}</option>
                        <option value="closed" @selected(($filters['status'] ?? '') === 'closed')>{{ __('Closed') }}</option>
                    </select>
                    <select name="result" class="select w-auto" aria-label="{{ __('Result') }}" data-picker data-filter data-icon="chart-line">
                        <option value="">{{ __('All results') }}</option>
                        @foreach ($resultLabel as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['result'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @if (array_filter($filters))
                        <a href="{{ route('interventions') }}" class="btn btn-ghost">{{ __('Clear') }}</a>
                    @endif
                </form>
                <button class="btn btn-primary" onclick="document.getElementById('intervention-form').showModal()">
                    <i data-lucide="plus" class="size-4"></i>{{ __('Add follow-up') }}
                </button>
            </div>

            @if ($selected)
                <div class="flex items-center gap-2 text-sm">
                    <span class="opacity-60">{{ __('Showing follow-ups for') }}</span>
                    <a href="{{ route('students.show', $selected) }}" class="link link-hover font-semibold">{{ $name($selected) }} · {{ $selected->schoolClass->name }}</a>
                    <a href="{{ route('interventions', array_filter([...$filters, 'student' => null])) }}" class="btn btn-ghost btn-xs btn-circle" aria-label="{{ __('Show everyone') }}">
                        <i data-lucide="x" class="size-4"></i>
                    </a>
                </div>
            @endif

            @if ($interventions->isEmpty())
                <div class="py-12 text-center">
                    <i data-lucide="hand-heart" class="mx-auto size-10 opacity-40"></i>
                    <p class="mt-2 font-medium">{{ array_filter($filters) ? __('No follow-ups match this filter.') : __('No follow-ups yet.') }}</p>
                    <p class="text-sm opacity-60">{{ __('Start from the At-Risk list: press Follow up next to a student.') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('Student') }}</th><th>{{ __('Action') }}</th><th>{{ __('Follow-up') }}</th>
                                <th class="text-right">{{ __('Before → After') }}</th><th>{{ __('Result') }}</th><th>{{ __('Status') }}</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($interventions as $item)
                                @php $state = $item->state(); $result = $item->result(); $s = $item->student; @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('students.show', $s) }}" class="link link-hover font-medium">{{ $name($s) }}</a>
                                        <div class="text-xs opacity-60">{{ $s->schoolClass->name }}</div>
                                    </td>
                                    <td>
                                        <div class="whitespace-nowrap">{{ $item->actionLabel() }}</div>
                                        <div class="text-xs opacity-60">{{ $item->action_date->translatedFormat('j M') }} · {{ $item->createdBy->name }}</div>
                                        @if ($item->notes)
                                            <div class="mt-1 max-w-xs text-xs opacity-70">{{ $item->notes }}</div>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap">
                                        {{ $item->follow_up_date?->translatedFormat('j M') ?? '—' }}
                                    </td>
                                    <td class="text-right whitespace-nowrap tabular-nums">
                                        {{ $item->rate_before !== null ? number_format($item->rate_before, 1).'%' : '—' }} →
                                        {{ $item->rate_after !== null ? number_format($item->rate_after, 1).'%' : '…' }}
                                    </td>
                                    <td>
                                        @if ($result)
                                            <span class="badge {{ $resultBadge[$result] }} whitespace-nowrap">{{ $resultLabel[$result] }}</span>
                                            <span class="{{ $resultText[$result] }} text-xs font-semibold">{{ $signed($item->change()) }}</span>
                                            @if ($item->outcome)
                                                <div class="mt-1 max-w-xs text-xs opacity-70">{{ $item->outcome }}</div>
                                            @endif
                                        @elseif ($state === 'closed')
                                            <span class="opacity-60">—</span>
                                        @else
                                            <span class="opacity-60">{{ __('Waiting') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($state === 'due')
                                            <span class="badge badge-soft badge-error whitespace-nowrap">{{ __('Follow-up due') }}</span>
                                        @elseif ($state === 'open')
                                            <span class="badge badge-soft badge-warning">{{ __('Open') }}</span>
                                        @else
                                            <span class="badge badge-soft">{{ __('Closed') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-right whitespace-nowrap">
                                        @if ($state !== 'closed')
                                            <button @class(['btn btn-sm', 'btn-primary' => $state === 'due'])
                                                data-form-dialog="close-form" data-action="{{ route('interventions.close', $item) }}" data-method="POST"
                                                data-title="{{ __('Measure the result: :name', ['name' => $name($s)]) }}">
                                                <i data-lucide="ruler" class="size-4"></i>{{ __('Measure') }}
                                            </button>
                                        @endif
                                        <button class="btn btn-ghost btn-sm btn-square text-error" aria-label="{{ __('Delete') }}"
                                            data-delete="{{ route('interventions.destroy', $item) }}" data-name="{{ $item->actionLabel() }} · {{ $name($s) }}">
                                            <i data-lucide="trash-2" class="size-4"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $interventions->links('partials.pagination') }}
            @endif

            <div role="alert" class="alert alert-soft alert-info mt-2 text-sm">
                <i data-lucide="info" class="size-5"></i>
                <span>{{ __('Before = rate in the 10 school days before the action. After = the 10 school days after. Improved = +5 points or more; Declined = −5 or less. The result shows a change, not proof that the action caused it.') }}</span>
            </div>
        </div>
    </div>

    {{-- Add a follow-up --}}
    <dialog id="intervention-form" class="modal" @if ($formOpen) data-reopen @endif>
        <div class="modal-box max-w-xl">
            <h3 class="text-lg font-semibold">{{ __('Add follow-up') }}</h3>
            <form method="POST" action="{{ route('interventions.store') }}" class="mt-2 grid grid-cols-1 gap-x-4 sm:grid-cols-2">
                @csrf
                <input type="hidden" name="_modal" value="intervention-form">
                @php $current = (string) old('student_id', $selected?->id ?? ''); @endphp
                <fieldset class="fieldset sm:col-span-2">
                    <legend class="fieldset-legend">{{ __('Student') }}</legend>
                    <select name="student_id" @class(['select w-full', 'select-error' => $errors->has('student_id')]) required data-picker data-filter data-icon="user">
                        <option value="">{{ __('Choose a student') }}</option>
                        @foreach ($students as $s)
                            @php $level = $risks->get($s->id)?->level; @endphp
                            <option value="{{ $s->id }}" @selected($current === (string) $s->id)
                                @isset($riskDot[$level]) data-dot="{{ $riskDot[$level] }}" data-hint="{{ \App\Support\Risk::label($level) }} · {{ $risks->get($s->id)->rate }}%" @endisset>
                                {{ $name($s) }} · {{ $s->schoolClass->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('student_id')<p class="label text-error whitespace-normal" data-error>{{ $message }}</p>@enderror
                    @if ($selected && ($r = $risks->get($selected->id)) && $r->action())
                        <p class="label whitespace-normal">{{ __('Suggested') }}: {{ __($r->action()) }}</p>
                    @endif
                </fieldset>

                @include('partials.field', ['name' => 'action', 'label' => __('Action'), 'span' => true, 'icon' => 'hand-heart',
                    'options' => array_map(fn ($label) => __($label), Intervention::ACTIONS)])

                <fieldset class="fieldset">
                    <legend class="fieldset-legend">{{ __('Action date') }}</legend>
                    <input type="date" name="action_date" value="{{ old('action_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}"
                        @class(['input w-full', 'input-error' => $errors->has('action_date')]) required data-date-picker />
                    @error('action_date')<p class="label text-error whitespace-normal" data-error>{{ $message }}</p>@enderror
                </fieldset>
                <fieldset class="fieldset">
                    <legend class="fieldset-legend">{{ __('Follow-up date') }} <span class="font-normal opacity-60">({{ __('optional') }})</span></legend>
                    <input type="date" name="follow_up_date" value="{{ old('follow_up_date', ($d = today()->addDays(14))->isSunday() ? $d->addDay()->toDateString() : $d->toDateString()) }}"
                        @class(['input w-full', 'input-error' => $errors->has('follow_up_date')]) data-date-picker data-clearable />
                    @error('follow_up_date')<p class="label text-error whitespace-normal" data-error>{{ $message }}</p>@enderror
                    <p class="label whitespace-normal">{{ __('About 2 weeks later, to see if attendance changed.') }}</p>
                </fieldset>

                <fieldset class="fieldset sm:col-span-2">
                    <legend class="fieldset-legend">{{ __('Notes') }} <span class="font-normal opacity-60">({{ __('optional') }})</span></legend>
                    <textarea name="notes" rows="3" maxlength="1000" class="textarea w-full" placeholder="{{ __('e.g. Mother says he helps on the farm on Mondays.') }}">{{ old('notes') }}</textarea>
                </fieldset>

                <div class="modal-action sm:col-span-2">
                    <button type="button" class="btn" onclick="this.closest('dialog').close()">{{ __('Cancel') }}</button>
                    <button class="btn btn-primary">{{ __('Save follow-up') }}</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>{{ __('Close') }}</button></form>
    </dialog>

    {{-- Measure and close (filled by the Measure button) --}}
    <dialog id="close-form" class="modal" @if ($closeOpen) data-reopen @endif>
        <div class="modal-box">
            <h3 class="text-lg font-semibold" data-dialog-title>{{ old('_title', __('Measure the result')) }}</h3>
            <p class="mt-1 text-sm opacity-70">{{ __('The system works out the rate in the 10 school days after the action and closes the follow-up.') }}</p>
            <form method="POST" action="{{ old('_action', '') }}" class="mt-2" data-dialog-form>
                @csrf
                <input type="hidden" name="_method" value="POST">
                <input type="hidden" name="_action" value="{{ old('_action') }}">
                <input type="hidden" name="_title" value="{{ old('_title') }}">
                <input type="hidden" name="_modal" value="close-form">
                <fieldset class="fieldset">
                    <legend class="fieldset-legend">{{ __('What happened?') }} <span class="font-normal opacity-60">({{ __('optional') }})</span></legend>
                    <textarea name="outcome" rows="3" maxlength="1000" class="textarea w-full" placeholder="{{ __('e.g. Parent came to school; the student is back every day.') }}">{{ old('outcome') }}</textarea>
                    @error('outcome')<p class="label text-error whitespace-normal" data-error>{{ $message }}</p>@enderror
                </fieldset>
                <div class="modal-action">
                    <button type="button" class="btn" onclick="this.closest('dialog').close()">{{ __('Cancel') }}</button>
                    <button class="btn btn-primary"><i data-lucide="ruler" class="size-4"></i>{{ __('Measure and close') }}</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>{{ __('Close') }}</button></form>
    </dialog>
@endsection
