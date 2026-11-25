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
        @php
            $byGrade = $classes->groupBy('grade');
            // Words the search box looks in: class name, room and homeroom teacher (Latin and Khmer)
            $searchText = fn ($class) => mb_strtolower(trim(implode(' ', [
                $class->name, $class->room, $class->homeroomTeacher?->name, $class->homeroomTeacher?->name_km,
            ])));
        @endphp

        {{-- Grade tabs, search and Cards / List switch. Everything filters in the browser, no reload. --}}
        <div class="flex flex-wrap items-center gap-3" id="class-tools">
            <div role="tablist" class="tabs tabs-box tabs-sm" aria-label="{{ __('Grade') }}">
                <button type="button" role="tab" class="tab tab-active gap-1.5" aria-selected="true" data-grade="">
                    {{ __('All') }}<span class="badge badge-sm badge-neutral">{{ $classes->count() }}</span>
                </button>
                @foreach ($byGrade as $grade => $group)
                    <button type="button" role="tab" class="tab gap-1.5" aria-selected="false" data-grade="{{ $grade }}">
                        {{ __('Grade :grade', ['grade' => $grade]) }}<span class="badge badge-sm badge-ghost">{{ $group->count() }}</span>
                    </button>
                @endforeach
            </div>
            <label class="input w-full sm:w-72">
                <i data-lucide="search" class="size-4 opacity-50"></i>
                <input type="search" id="class-search" placeholder="{{ __('Search class, room or teacher') }}" aria-label="{{ __('Search class, room or teacher') }}">
            </label>
            <div class="join ms-auto" role="group" aria-label="{{ __('View') }}">
                <button type="button" class="btn btn-sm join-item btn-active" data-view="cards" aria-pressed="true" title="{{ __('Show as cards') }}">
                    <i data-lucide="layout-grid" class="size-4"></i>{{ __('Cards') }}
                </button>
                <button type="button" class="btn btn-sm join-item" data-view="list" aria-pressed="false" title="{{ __('Show as list') }}">
                    <i data-lucide="list" class="size-4"></i>{{ __('List') }}
                </button>
            </div>
        </div>

        {{-- Cards view: one card per class, grouped under a heading per grade --}}
        <div data-view-panel="cards" class="space-y-6">
            @foreach ($byGrade as $grade => $group)
                <section data-grade-group="{{ $grade }}">
                    <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold opacity-70">
                        <i data-lucide="layers" class="size-4"></i>{{ __('Grade :grade', ['grade' => $grade]) }}
                        <span class="badge badge-sm badge-ghost">{{ $group->count() }}</span>
                    </h2>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($group as $class)
                            @php $rate = Risk::rate($class->records_present, $class->records_total); @endphp
                            <div class="card bg-base-100 border-base-300 border" data-class-item data-grade="{{ $class->grade }}" data-search="{{ $searchText($class) }}">
                                <div class="card-body">
                                    <div>
                                        <h3 class="card-title">{{ $class->name }}</h3>
                                        <p class="text-sm opacity-60">{{ __('Grade') }} {{ $class->grade }}@if ($class->room) · {{ __('Room') }} {{ $class->room }}@endif</p>
                                    </div>
                                    @include('partials.class-rate', [
                                        'rate' => $rate,
                                        'atRisk' => $atRisk[$class->id] ?? 0,
                                        'riskLink' => route('students.index', ['class' => $class->id]),
                                    ])
                                    <dl class="mt-3 grid grid-cols-2 gap-y-1 text-sm">
                                        <dt class="opacity-60">{{ __('Homeroom teacher') }}</dt>
                                        <dd class="text-right">{{ $class->homeroomTeacher?->name ?? '—' }}</dd>
                                        <dt class="opacity-60">{{ __('Students') }}</dt>
                                        <dd class="text-right">{{ __(':count (:girls girls)', ['count' => $class->students_count, 'girls' => $class->girls_count]) }}</dd>
                                    </dl>
                                    <div class="card-actions mt-2 items-center justify-end">
                                        @include('classes.partials.edit-delete', ['class' => $class, 'last' => 'me-auto'])
                                        <a href="{{ route('students.index', ['class' => $class->id]) }}" class="btn btn-sm">{{ __('Students') }}</a>
                                        <a href="{{ route('attendance.take') }}" class="btn btn-sm">{{ __('Take attendance') }}</a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        {{-- List view: one row per class, to see and compare many classes at once --}}
        <div data-view-panel="list" class="card bg-base-100 border-base-300 border" hidden>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Class') }}</th>
                            <th>{{ __('Grade') }}</th>
                            <th>{{ __('Room') }}</th>
                            <th>{{ __('Homeroom teacher') }}</th>
                            <th>{{ __('Students') }}</th>
                            <th class="w-56">{{ __('Attendance') }}</th>
                            <th>{{ __('At risk') }}</th>
                            <th><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($classes as $class)
                            @php
                                $rate = Risk::rate($class->records_present, $class->records_total);
                                $risky = $atRisk[$class->id] ?? 0;
                                $textColour = ['success' => 'text-success', 'warning' => 'text-warning', 'error' => 'text-error'][Risk::tone($rate)] ?? '';
                            @endphp
                            <tr data-class-item data-grade="{{ $class->grade }}" data-search="{{ $searchText($class) }}">
                                <td>
                                    <a href="{{ route('students.index', ['class' => $class->id]) }}" class="font-semibold hover:underline">{{ $class->name }}</a>
                                </td>
                                <td>{{ $class->grade }}</td>
                                <td>{{ $class->room ?: '—' }}</td>
                                <td>{{ $class->homeroomTeacher?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap">{{ __(':count (:girls girls)', ['count' => $class->students_count, 'girls' => $class->girls_count]) }}</td>
                                <td>
                                    @if ($rate === null)
                                        <span class="text-sm opacity-60">{{ __('No attendance taken yet') }}</span>
                                    @else
                                        <div class="flex items-center gap-3">
                                            <progress class="progress {{ Risk::progress($rate) }} w-24" value="{{ $rate }}" max="100"></progress>
                                            <span class="{{ $textColour }} font-semibold tabular-nums">{{ $rate }}%</span>
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if ($risky > 0)
                                        <a href="{{ route('students.index', ['class' => $class->id]) }}" class="badge badge-soft badge-error gap-1 whitespace-nowrap">
                                            <i data-lucide="triangle-alert" class="size-3.5"></i>{{ $risky }}
                                        </a>
                                    @else
                                        <span class="badge badge-soft badge-success"><i data-lucide="circle-check" class="size-3.5"></i></span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex items-center justify-end">
                                        @include('classes.partials.edit-delete', ['class' => $class, 'last' => ''])
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div id="class-empty" class="card bg-base-100 border-base-300 border" hidden>
            <div class="card-body py-10 text-center">
                <i data-lucide="search-x" class="mx-auto size-8 opacity-40"></i>
                <p class="mt-1 text-sm opacity-70">{{ __('No class matches your search.') }}</p>
            </div>
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
                @include('partials.field', ['name' => 'grade', 'label' => __('Grade'), 'options' => [7 => '7', 8 => '8', 9 => '9', 10 => '10', 11 => '11', 12 => '12']])
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

@push('scripts')
<script>
    // Classes: grade tabs + search filter the cards and the list; the Cards / List choice is remembered in the browser
    (() => {
        const tools = document.getElementById('class-tools');
        if (!tools) return;
        const search = document.getElementById('class-search');
        const panels = document.querySelectorAll('[data-view-panel]');
        const state = { grade: '', words: '', view: 'cards' };
        try { if (localStorage.getItem('classes-view') === 'list') state.view = 'list'; } catch {}

        const apply = () => {
            document.querySelectorAll('[data-class-item]').forEach((item) => {
                item.hidden = (state.grade && item.dataset.grade !== state.grade)
                    || (state.words && !item.dataset.search.includes(state.words));
            });
            // A grade heading disappears when none of its cards is left
            document.querySelectorAll('[data-grade-group]').forEach((group) => {
                group.hidden = !group.querySelector('[data-class-item]:not([hidden])');
            });
            panels.forEach((panel) => (panel.hidden = panel.dataset.viewPanel !== state.view));
            const shown = document.querySelector(`[data-view-panel="${state.view}"] [data-class-item]:not([hidden])`);
            document.getElementById('class-empty').hidden = !!shown;
            if (!shown) panels.forEach((panel) => (panel.hidden = true));

            tools.querySelectorAll('[data-grade]').forEach((tab) => {
                const on = tab.dataset.grade === state.grade;
                tab.classList.toggle('tab-active', on);
                tab.setAttribute('aria-selected', on);
            });
            tools.querySelectorAll('[data-view]').forEach((btn) => {
                const on = btn.dataset.view === state.view;
                btn.classList.toggle('btn-active', on);
                btn.setAttribute('aria-pressed', on);
            });
        };

        tools.addEventListener('click', (e) => {
            const tab = e.target.closest('[data-grade]');
            const view = e.target.closest('[data-view]');
            if (tab) state.grade = tab.dataset.grade;
            if (view) {
                state.view = view.dataset.view;
                try { localStorage.setItem('classes-view', state.view); } catch {}
            }
            if (tab || view) apply();
        });
        search.addEventListener('input', () => {
            state.words = search.value.trim().toLowerCase();
            apply();
        });

        apply();
    })();
</script>
@endpush
