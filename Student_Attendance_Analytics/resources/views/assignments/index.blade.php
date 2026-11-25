@extends('layouts.app')

@section('title', __('Teaching Assignments'))

@php
    $km = app()->getLocale() === 'km';
    $dialogOpen = $errors->any() && old('_modal') === 'assignment-form';
    $copyOpen = $errors->any() && old('_modal') === 'copy-form';
    $subjectName = fn ($s) => $km ? $s->name_km : $s->name_en;
    $teacherName = fn ($t) => $km && $t->name_km ? $t->name_km : $t->name;
    $tabUrl = fn ($g) => route('assignments.index', array_filter(['view' => $view === 'table' ? 'table' : null, 'grade' => $g]));
    $overloaded = $loads->where('state', 'over')->count();
@endphp

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm opacity-60">{{ __('Who teaches which subject in which class. A teacher only sees their own classes in the app.') }}</p>
        <div class="flex flex-wrap gap-2">
            <button class="btn" data-copy-open><i data-lucide="copy-plus" class="size-4"></i>{{ __('Copy to other classes') }}</button>
            <button class="btn btn-primary" data-form-dialog="assignment-form" data-action="{{ route('assignments.store') }}" data-method="POST" data-title="{{ __('Add assignment') }}">
                <i data-lucide="plus" class="size-4"></i>{{ __('Add assignment') }}
            </button>
        </div>
    </div>

    {{-- Teachers' load: hours a week from their subjects. By default only teachers who need attention. --}}
    <div class="card bg-base-100 border-base-300 border" id="loads">
        <div class="card-body gap-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="card-title text-base">{{ __('Teachers\' load') }}</h2>
                    <p class="text-sm opacity-60">
                        {{ __('Hours a week · more than :max h is too many', ['max' => $maxHours]) }}
                        @if ($overloaded) · <span class="text-error font-semibold">{{ trans_choice(':count teacher has too many hours|:count teachers have too many hours', $overloaded, ['count' => $overloaded]) }}</span> @endif
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <label class="input input-sm w-52">
                        <i data-lucide="search" class="size-3.5 opacity-50"></i>
                        <input type="search" placeholder="{{ __('Find a teacher') }}" data-load-search aria-label="{{ __('Find a teacher') }}">
                    </label>
                    <button type="button" class="btn btn-sm btn-ghost" data-load-all data-more="{{ __('Show all teachers') }}" data-less="{{ __('Only who needs attention') }}">{{ __('Show all teachers') }}</button>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3" data-load-list>
                @foreach ($loads as $load)
                    @php
                        $pct = min(100, round(100 * $load['hours'] / max(1, $maxHours * 1.4)));
                        $bar = ['over' => 'progress-error', 'none' => '', 'ok' => $load['hours'] >= $maxHours * 0.85 ? 'progress-warning' : 'progress-success'][$load['state']];
                    @endphp
                    <button type="button" @class(['load-item', 'is-problem' => $load['state'] !== 'ok'])
                        data-load-teacher="{{ $load['teacher']->id }}" data-search="{{ mb_strtolower($load['teacher']->name.' '.$load['teacher']->name_km) }}"
                        title="{{ __('Show this teacher\'s classes in the grid') }}">
                        @include('partials.avatar', ['person' => $load['teacher'], 'size' => 'w-9', 'tone' => 'bg-primary/15 text-primary'])
                        <span class="min-w-0 flex-1 text-start">
                            <span class="flex items-center justify-between gap-2">
                                <span class="truncate font-medium">{{ $teacherName($load['teacher']) }}</span>
                                <span @class(['text-sm font-semibold whitespace-nowrap tabular-nums', 'text-error' => $load['state'] === 'over'])>
                                    @if ($load['state'] === 'over')<i data-lucide="triangle-alert" class="inline size-3.5"></i>@endif
                                    {{ $load['hours'] }} h
                                </span>
                            </span>
                            <span class="mt-1 flex items-center gap-2">
                                <progress class="progress {{ $bar }} h-1.5 flex-1" value="{{ $pct }}" max="100"></progress>
                                <span class="text-xs whitespace-nowrap opacity-60">
                                    {{ $load['state'] === 'none' ? __('No class yet') : trans_choice(':count class|:count classes', $load['classes'], ['count' => $load['classes']]) }}
                                </span>
                            </span>
                        </span>
                    </button>
                @endforeach
            </div>
            <p class="hidden text-sm opacity-60" data-load-fine>{{ __('Every teacher is within :max hours a week.', ['max' => $maxHours]) }}</p>
        </div>
    </div>

    <div class="card bg-base-100 border-base-300 border scroll-mt-4" data-list>
        <div class="card-body gap-4">
            {{-- Grade tabs (one grade at a time) and Grid / Table --}}
            <div class="flex flex-wrap items-center gap-3">
                <div role="tablist" class="tabs tabs-box tabs-sm" aria-label="{{ __('Grade') }}">
                    @if ($view === 'table')
                        <a role="tab" href="{{ $tabUrl(null) }}" @class(['tab gap-1.5', 'tab-active' => ! $grade])>{{ __('All') }}</a>
                    @endif
                    @foreach ($gradeInfo as $g => $info)
                        <a role="tab" href="{{ $tabUrl($g) }}" @class(['tab gap-1.5', 'tab-active' => $grade === $g]) aria-selected="{{ $grade === $g ? 'true' : 'false' }}">
                            {{ __('Grade :grade', ['grade' => $g]) }}
                            <span class="badge badge-sm badge-ghost">{{ $info['classes']->count() }}</span>
                            @if ($info['missing'])
                                <span class="badge badge-sm badge-warning gap-0.5" title="{{ __('Subjects without a teacher') }}"><i data-lucide="triangle-alert" class="size-3"></i>{{ $info['missing'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
                <div class="join ms-auto">
                    <a href="{{ route('assignments.index', array_filter(['grade' => $grade])) }}" @class(['btn btn-sm join-item', 'btn-active' => $view === 'grid'])><i data-lucide="grid-3x3" class="size-4"></i>{{ __('Grid') }}</a>
                    <a href="{{ route('assignments.index', array_filter(['view' => 'table', 'grade' => $grade])) }}" @class(['btn btn-sm join-item', 'btn-active' => $view === 'table'])><i data-lucide="list" class="size-4"></i>{{ __('Table') }}</a>
                </div>
            </div>

            @if ($view === 'grid')
                @php $info = $gradeInfo[$grade] ?? null; @endphp
                @if (! $info || $info['subjects']->isEmpty())
                    <div class="py-12 text-center">
                        <i data-lucide="calendar-check" class="mx-auto size-10 opacity-40"></i>
                        <p class="mt-2 font-medium">{{ __('No assignments in this grade yet.') }}</p>
                        <p class="text-sm opacity-60">{{ __('Add the subjects of one class, then copy them to the other classes.') }}</p>
                    </div>
                @else
                    <p class="text-sm opacity-60">
                        {{ __('Click a box to change the teacher. A box with ⚠ means this class has no teacher for a subject the other classes of the grade have.') }}
                    </p>
                    {{-- Classes × subjects: the first column and the header row stay in place while scrolling --}}
                    <div class="grid-wrap">
                        <table class="assign-grid">
                            <thead>
                                <tr>
                                    <th class="corner">{{ __('Class') }}</th>
                                    @foreach ($info['subjects'] as $subject)
                                        <th title="{{ $subjectName($subject) }} · {{ $subject->hours_per_week }} h">
                                            <span class="block font-semibold">{{ $subject->code }}</span>
                                            <span class="block truncate text-[11px] font-normal opacity-60">{{ $subjectName($subject) }}</span>
                                        </th>
                                    @endforeach
                                    <th class="text-end">{{ __('h/week') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($info['classes'] as $class)
                                    <tr>
                                        <th class="row-head">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="font-semibold">{{ $class->name }}</span>
                                                <button type="button" class="btn btn-ghost btn-xs btn-square" data-copy-open data-copy-from="{{ $class->id }}"
                                                    title="{{ __('Copy :class to other classes', ['class' => $class->name]) }}" aria-label="{{ __('Copy :class to other classes', ['class' => $class->name]) }}">
                                                    <i data-lucide="copy-plus" class="size-3.5"></i>
                                                </button>
                                            </div>
                                        </th>
                                        @foreach ($info['subjects'] as $subject)
                                            @php $a = $class->teachingAssignments->firstWhere('subject_id', $subject->id); @endphp
                                            <td>
                                                @if ($a)
                                                    <button type="button" class="cell" data-teacher="{{ $a->teacher_id }}"
                                                        title="{{ $class->name }} · {{ $subjectName($subject) }} · {{ $teacherName($a->teacher) }}"
                                                        data-form-dialog="assignment-form" data-action="{{ route('assignments.update', $a) }}" data-method="PUT"
                                                        data-title="{{ __('Edit assignment') }}"
                                                        data-values="{{ json_encode($a->only(['school_class_id', 'subject_id', 'teacher_id'])) }}">
                                                        @include('partials.avatar', ['person' => $a->teacher, 'size' => 'w-7', 'text' => 'text-[10px]', 'tone' => 'bg-primary/15 text-primary'])
                                                        <span class="truncate text-xs">{{ $teacherName($a->teacher) }}</span>
                                                    </button>
                                                @else
                                                    <button type="button" class="cell is-missing"
                                                        title="{{ $class->name }} · {{ $subjectName($subject) }}: {{ __('no teacher yet') }}"
                                                        data-form-dialog="assignment-form" data-action="{{ route('assignments.store') }}" data-method="POST"
                                                        data-title="{{ __('Add assignment') }}"
                                                        data-values="{{ json_encode(['school_class_id' => $class->id, 'subject_id' => $subject->id]) }}">
                                                        <i data-lucide="triangle-alert" class="size-3.5"></i><span class="text-xs font-semibold">{{ __('Assign') }}</span>
                                                    </button>
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="text-end text-sm font-semibold tabular-nums">{{ $class->teachingAssignments->sum(fn ($x) => $x->subject->hours_per_week) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @else
                <form method="GET" action="{{ route('assignments.index') }}" class="flex flex-wrap gap-2" data-auto-submit>
                    <input type="hidden" name="view" value="table">
                    @if ($grade)<input type="hidden" name="grade" value="{{ $grade }}">@endif
                    <select name="class" class="select w-auto" aria-label="{{ __('Class') }}" data-picker data-filter data-icon="school">
                        <option value="">{{ __('All classes') }}</option>
                        @foreach ($classes->when($grade, fn ($c) => $c->where('grade', $grade)) as $class)
                            <option value="{{ $class->id }}" @selected(($filters['class'] ?? '') == $class->id)>{{ $class->name }}</option>
                        @endforeach
                    </select>
                    <select name="teacher" class="select w-auto" aria-label="{{ __('Teacher') }}" data-picker data-filter data-icon="graduation-cap">
                        <option value="">{{ __('All teachers') }}</option>
                        @foreach ($teachers as $teacher)
                            <option value="{{ $teacher->id }}" @selected(($filters['teacher'] ?? '') == $teacher->id)>{{ $teacher->name }}</option>
                        @endforeach
                    </select>
                    @if (array_filter($filters))
                        <a href="{{ route('assignments.index', array_filter(['view' => 'table', 'grade' => $grade])) }}" class="btn btn-ghost">{{ __('Clear') }}</a>
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
                                        <td class="font-medium">{{ $subjectName($assignment->subject) }}</td>
                                        <td>
                                            <span class="flex items-center gap-2">
                                                @include('partials.avatar', ['person' => $assignment->teacher, 'size' => 'w-7', 'text' => 'text-[10px]', 'tone' => 'bg-primary/15 text-primary'])
                                                {{ $teacherName($assignment->teacher) }}
                                            </span>
                                        </td>
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
                                                data-name="{{ $assignment->schoolClass->name }} · {{ $subjectName($assignment->subject) }}">
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

                @include('partials.field', ['name' => 'school_class_id', 'label' => __('Class'), 'options' => $classes->pluck('name', 'id')->all(), 'icon' => 'school'])
                @include('partials.field', ['name' => 'subject_id', 'label' => __('Subject'), 'options' => $subjects->pluck($km ? 'name_km' : 'name_en', 'id')->all(), 'icon' => 'book-open'])
                @include('partials.field', ['name' => 'teacher_id', 'label' => __('Teacher'), 'icon' => 'graduation-cap',
                    'options' => $loads->sortBy(fn ($l) => $l['teacher']->name)->mapWithKeys(fn ($l) => [$l['teacher']->id => $teacherName($l['teacher'])])->all(),
                    'hints' => $loads->mapWithKeys(fn ($l) => [$l['teacher']->id => $l['hours'].' h'])->all(),
                    'hint' => __('A new teacher also takes over this subject\'s lessons in the timetable.')])

                <div class="modal-action">
                    <button type="button" class="btn" onclick="this.closest('dialog').close()">{{ __('Cancel') }}</button>
                    <button class="btn btn-primary">{{ __('Save assignment') }}</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>{{ __('Close') }}</button></form>
    </dialog>

    {{-- Copy one class's subjects and teachers to other classes --}}
    <dialog id="copy-form" class="modal" @if ($copyOpen) data-reopen @endif>
        <div class="modal-box max-w-lg">
            <h3 class="text-lg font-semibold">{{ __('Copy to other classes') }}</h3>
            <p class="mt-1 text-sm opacity-70">{{ __('All subjects and teachers of one class are added to the classes you tick. A subject a class already has keeps its own teacher.') }}</p>
            <form method="POST" action="{{ route('assignments.copy') }}" class="mt-3" id="copy-form-fields">
                @csrf
                <input type="hidden" name="_modal" value="copy-form">
                @include('partials.field', ['name' => 'from', 'label' => __('Copy from'), 'icon' => 'school',
                    'options' => $classes->mapWithKeys(fn ($c) => [$c->id => $c->name.' · '.trans_choice(':count subject|:count subjects', $c->teachingAssignments->count(), ['count' => $c->teachingAssignments->count()])])->all()])
                <fieldset class="fieldset">
                    <legend class="fieldset-legend">{{ __('Copy to') }}</legend>
                    <div class="max-h-60 space-y-3 overflow-y-auto pe-1">
                        @foreach ($gradeInfo as $g => $info)
                            <div data-copy-grade="{{ $g }}">
                                <p class="mb-1 text-xs font-semibold opacity-60">{{ __('Grade :grade', ['grade' => $g]) }}</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($info['classes'] as $class)
                                        <label class="copy-chip">
                                            <input type="checkbox" name="to[]" value="{{ $class->id }}" class="checkbox checkbox-xs checkbox-primary"
                                                data-copy-target data-grade="{{ $g }}" @checked(in_array($class->id, (array) old('to', [])))>
                                            {{ $class->name }}
                                            <span class="text-xs opacity-50">{{ $class->teachingAssignments->count() }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @error('to')
                        <p class="label text-error whitespace-normal" data-error>{{ $message }}</p>
                    @enderror
                    @error('to.*')
                        <p class="label text-error whitespace-normal" data-error>{{ $message }}</p>
                    @enderror
                </fieldset>
                <div class="modal-action">
                    <button type="button" class="btn" onclick="this.closest('dialog').close()">{{ __('Cancel') }}</button>
                    <button class="btn btn-primary"><i data-lucide="copy-plus" class="size-4"></i>{{ __('Copy') }}</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>{{ __('Close') }}</button></form>
    </dialog>
@endsection

@push('scripts')
<script>
    (() => {
        // Teachers' load: only who needs attention, or everyone; search; click = show their boxes in the grid
        const list = document.querySelector('[data-load-list]');
        const items = [...list.querySelectorAll('[data-load-teacher]')];
        const search = document.querySelector('[data-load-search]');
        const allBtn = document.querySelector('[data-load-all]');
        const fine = document.querySelector('[data-load-fine]');
        let showAll = !items.some((i) => i.classList.contains('is-problem'));
        let picked = null;

        const draw = () => {
            const q = search.value.trim().toLowerCase();
            items.forEach((i) => {
                i.hidden = q ? !i.dataset.search.includes(q) : !(showAll || i.classList.contains('is-problem'));
                i.classList.toggle('is-picked', i.dataset.loadTeacher === picked);
            });
            allBtn.textContent = showAll ? allBtn.dataset.less : allBtn.dataset.more;
            allBtn.hidden = !items.some((i) => i.classList.contains('is-problem'));
            fine.classList.toggle('hidden', items.some((i) => i.classList.contains('is-problem')));
            document.querySelectorAll('.assign-grid [data-teacher]').forEach((c) => {
                c.classList.toggle('is-picked', c.dataset.teacher === picked);
                c.classList.toggle('is-dim', picked !== null && c.dataset.teacher !== picked);
            });
        };
        search.addEventListener('input', draw);
        allBtn.addEventListener('click', () => { showAll = !showAll; draw(); });
        list.addEventListener('click', (e) => {
            const item = e.target.closest('[data-load-teacher]');
            if (!item) return;
            picked = picked === item.dataset.loadTeacher ? null : item.dataset.loadTeacher;
            draw();
        });
        draw();

        // Copy: open from the top button or from a class row (that class is the source; the rest of its grade is ticked)
        const copy = document.getElementById('copy-form');
        const from = document.querySelector('#copy-form-fields [name="from"]');
        const targets = [...copy.querySelectorAll('[data-copy-target]')];
        const tickGrade = () => {
            const source = targets.find((t) => t.value === from.value);
            targets.forEach((t) => {
                t.disabled = t.value === from.value;
                t.checked = !t.disabled && source && t.dataset.grade === source.dataset.grade;
            });
        };
        from.addEventListener('change', tickGrade);
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-copy-open]');
            if (!btn) return;
            if (btn.dataset.copyFrom) {
                from.value = btn.dataset.copyFrom;
                from.dispatchEvent(new Event('change', { bubbles: true }));
            } else {
                tickGrade();
            }
            copy.showModal();
        });
    })();
</script>
@endpush
