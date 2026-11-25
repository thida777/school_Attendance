@extends('layouts.app')

@section('title', __('Parents'))

@php
    $km = app()->getLocale() === 'km';
    $dialogOpen = $errors->any() && old('_modal') === 'parent-form';
    // Every student for the children search: name in the page language, class, and the words to search in
    $childData = $students->map(fn ($s) => [
        'id' => $s->id,
        'label' => ($km ? $s->name_km : $s->name_latin).' · '.$s->schoolClass->name,
        'sub' => ($km ? $s->name_latin : $s->name_km).' · '.$s->student_code,
        'search' => mb_strtolower($s->name_latin.' '.$s->name_km.' '.$s->student_code.' '.$s->schoolClass->name),
        'active' => $s->status === 'active',
    ])->values();
@endphp

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm opacity-60">{{ __('Parents are contacts only. The school sends them absence alerts; they do not log in.') }}</p>
        <button class="btn btn-primary" data-form-dialog="parent-form" data-action="{{ route('parents.store') }}" data-method="POST" data-title="{{ __('Add parent') }}">
            <i data-lucide="plus" class="size-4"></i>{{ __('Add parent') }}
        </button>
    </div>

    {{-- How many parents the school can reach on Telegram --}}
    @php $notYet = $total - $onTelegram; @endphp
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <a href="{{ route('parents.index') }}" class="stats bg-base-100 border-base-300 border transition hover:shadow-md">
            <div class="stat">
                <div class="stat-figure bg-primary/10 text-primary rounded-box p-3"><i data-lucide="contact" class="size-6"></i></div>
                <div class="stat-title">{{ __('Parents') }}</div>
                <div class="stat-value">{{ $total }}</div>
                <div class="stat-desc">{{ __('contacts of the students') }}</div>
            </div>
        </a>
        <a href="{{ route('parents.index', ['telegram' => 'yes']) }}" class="stats bg-base-100 border-base-300 border transition hover:shadow-md">
            <div class="stat">
                <div class="stat-figure bg-success/10 text-success rounded-box p-3"><i data-lucide="send" class="size-6"></i></div>
                <div class="stat-title">{{ __('On Telegram') }}</div>
                <div class="stat-value text-success">{{ $onTelegram }}</div>
                <div class="stat-desc">{{ __('receive alerts and can answer') }}</div>
            </div>
        </a>
        <div class="stats bg-base-100 border-base-300 border">
            <div class="stat">
                <div class="stat-figure bg-warning/10 text-warning rounded-box p-3"><i data-lucide="qr-code" class="size-6"></i></div>
                <div class="stat-title">{{ __('Not on Telegram yet') }}</div>
                <div class="stat-value">{{ $notYet }}</div>
                <div class="stat-desc flex flex-wrap items-center gap-x-3">
                    <a href="{{ route('parents.index', ['telegram' => 'no']) }}" class="link link-hover">{{ __('Show them') }}</a>
                    @if ($telegramLive && $notYet > 0)
                        <a href="{{ route('parents.invites', array_filter(['class' => $filters['class'] ?? null])) }}" target="_blank" class="link link-primary font-semibold">
                            <i data-lucide="printer" class="inline size-3.5"></i> {{ __('Print QR invites') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card bg-base-100 border-base-300 border scroll-mt-4" data-list>
        <div class="card-body">
            <form method="GET" action="{{ route('parents.index') }}" class="flex flex-wrap gap-2" data-auto-submit>
                <label class="input grow sm:max-w-xs">
                    <i data-lucide="search" class="size-4 opacity-50"></i>
                    <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('Search parent, phone or child') }}" />
                </label>
                <select name="telegram" class="select w-auto" aria-label="Telegram" data-picker data-filter data-icon="send">
                    <option value="">{{ __('Telegram: everyone') }}</option>
                    <option value="yes" @selected(($filters['telegram'] ?? '') === 'yes')>{{ __('On Telegram') }}</option>
                    <option value="no" @selected(($filters['telegram'] ?? '') === 'no')>{{ __('Not on Telegram yet') }}</option>
                </select>
                <select name="class" class="select w-auto" aria-label="{{ __('Class') }}" data-picker data-filter data-icon="school">
                    <option value="">{{ __('All classes') }}</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected(($filters['class'] ?? '') == $class->id)>{{ $class->name }}</option>
                    @endforeach
                </select>
                <button class="btn"><i data-lucide="search" class="size-4"></i>{{ __('Search') }}</button>
                @if ($search !== '' || array_filter($filters))
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
                                        {{-- Can the school reach this parent on Telegram? If not yet: a personal QR invite --}}
                                        @if ($guardian->telegram_chat_id)
                                            <span class="badge badge-soft badge-success mt-1 gap-1"><i data-lucide="send" class="size-3"></i>Telegram ✓</span>
                                        @else
                                            <div class="mt-1 flex items-center gap-1.5">
                                                <span class="badge badge-ghost gap-1"><i data-lucide="message-square" class="size-3"></i>{{ __('Not on Telegram yet') }}</span>
                                                @if ($telegramLive && ($link = $guardian->telegramInviteLink()))
                                                    <button type="button" class="btn btn-xs btn-primary btn-soft" data-invite
                                                        data-invite-link="{{ $link }}"
                                                        data-invite-name="{{ $km && $guardian->name_km ? $guardian->name_km : $guardian->name }}"
                                                        data-invite-children="{{ $guardian->students->map(fn ($c) => ($km ? $c->name_km : $c->name_latin).' · '.$c->schoolClass->name)->join(', ') }}"
                                                        data-invite-print="{{ route('parents.invites', ['parent' => $guardian->id]) }}">
                                                        <i data-lucide="qr-code" class="size-3.5"></i>{{ __('Invite') }}
                                                    </button>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-1">
                                            @forelse ($guardian->students as $child)
                                                <span class="badge badge-ghost gap-1 pe-1">
                                                    {{ $km ? $child->name_km : $child->name_latin }} · {{ $child->schoolClass->name }}
                                                    <button class="hover:text-error opacity-60 hover:opacity-100" aria-label="{{ __('Remove child') }}" title="{{ __('Remove child') }}"
                                                        data-delete="{{ route('parents.unlink', [$guardian, $child]) }}"
                                                        data-name="{{ $km ? $child->name_km : $child->name_latin }}"
                                                        data-confirm-title="{{ __('Remove this child from the parent?') }}"
                                                        data-confirm-text="{{ __(':parent will no longer get messages about this child. The child and the parent both stay in the system.', ['parent' => $km && $guardian->name_km ? $guardian->name_km : $guardian->name]) }}"
                                                        data-confirm-button="{{ __('Remove') }}">
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
                                            <span class="opacity-40" title="{{ __('Never') }}">—</span>
                                        @endif
                                    </td>
                                    <td class="text-right whitespace-nowrap">
                                        <button class="btn btn-ghost btn-xs btn-square" aria-label="{{ __('Edit') }}"
                                            data-form-dialog="parent-form" data-action="{{ route('parents.update', $guardian) }}" data-method="PUT"
                                            data-title="{{ __('Edit parent') }}"
                                            data-values="{{ json_encode($guardian->only(['name_km', 'name', 'phone', 'telegram_chat_id']) + [
                                                'relation' => $first?->pivot->relation ?? 'guardian',
                                                'student_ids' => $guardian->students->pluck('id')->all(),
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
    {{-- Add / edit parent: the parent, then the children (search, several at once) and the relation --}}
    @php
        $relation = old('relation', 'father');
        $oldChildren = collect(old('student_ids', []))->map(fn ($id) => (int) $id)->all();
        $section = fn ($icon, $text) => '<h4 class="text-primary mt-4 mb-1 flex items-center gap-2 text-sm font-semibold sm:col-span-2"><i data-lucide="'.$icon.'" class="size-4"></i>'.e($text).'</h4>';
    @endphp
    <dialog id="parent-form" class="modal" @if ($dialogOpen) data-reopen @endif>
        <div class="modal-box max-w-xl">
            <h3 class="text-lg font-semibold" data-dialog-title>{{ old('_title', __('Add parent')) }}</h3>
            <form method="POST" action="{{ old('_action', route('parents.store')) }}" class="mt-1 grid grid-cols-1 gap-x-4 sm:grid-cols-2" data-dialog-form id="parent-form-fields">
                @csrf
                <input type="hidden" name="_method" value="{{ old('_method', 'POST') }}">
                <input type="hidden" name="_action" value="{{ old('_action', route('parents.store')) }}">
                <input type="hidden" name="_title" value="{{ old('_title', __('Add parent')) }}">
                <input type="hidden" name="_modal" value="parent-form">

                {!! $section('user', __('Parent')) !!}
                @include('partials.field', ['name' => 'name_km', 'label' => __('Name (Khmer)'), 'placeholder' => 'សុខ ចន្ថា', 'required' => false])
                @include('partials.field', ['name' => 'name', 'label' => __('Name (Latin)'), 'placeholder' => 'Sok Chantha'])
                @include('partials.field', ['name' => 'phone', 'label' => __('Phone (for absence alerts)'), 'placeholder' => '012 111 222'])

                {!! $section('users', __('Children')) !!}
                {{-- Children: type to find a student; each one chosen becomes a chip (several at once) --}}
                <fieldset class="fieldset sm:col-span-2" data-children>
                    <legend class="fieldset-legend">{{ __('Children of this parent') }}</legend>
                    <div class="flex flex-wrap gap-1.5" data-children-chips></div>
                    <label @class(['input w-full', 'input-error' => $errors->has('student_ids') || $errors->has('student_ids.*')])>
                        <i data-lucide="search" class="size-4 opacity-50"></i>
                        <input type="text" autocomplete="off" placeholder="{{ __('Type a name, student code or class (e.g. 10A)') }}"
                               aria-label="{{ __('Find a child') }}" data-children-search>
                    </label>
                    <ul class="child-list hidden" role="listbox" data-children-list></ul>
                    @error('student_ids')
                        <p class="label text-error whitespace-normal" data-error>{{ $message }}</p>
                    @enderror
                </fieldset>
                <fieldset class="fieldset sm:col-span-2">
                    <legend class="fieldset-legend">{{ __('Relation') }}</legend>
                    <div class="join w-full" role="radiogroup" aria-label="{{ __('Relation') }}">
                        @foreach (['father' => __('Father'), 'mother' => __('Mother'), 'guardian' => __('Guardian')] as $value => $label)
                            <input type="radio" name="relation" value="{{ $value }}" class="btn join-item flex-1" aria-label="{{ $label }}" @checked($relation === $value)>
                        @endforeach
                    </div>
                </fieldset>

                {{-- Telegram: normally by the QR invite after saving; typing the chat ID is only for special cases --}}
                <details class="mt-3 sm:col-span-2" @if (old('telegram_chat_id') || $errors->has('telegram_chat_id')) open @endif>
                    <summary class="cursor-pointer text-sm opacity-70">{{ __('Advanced: type the Telegram chat ID by hand') }}</summary>
                    @include('partials.field', ['name' => 'telegram_chat_id', 'label' => __('Telegram chat ID'), 'placeholder' => '123456789', 'required' => false,
                        'hint' => __('Easier: save, then press "Invite" and let the parent scan the QR code. Empty = alerts by SMS.')])
                </details>
                @if ($telegramLive)
                    <p class="text-success mt-2 flex items-start gap-2 text-xs sm:col-span-2"><i data-lucide="qr-code" class="mt-0.5 size-4 shrink-0"></i>{{ __('After saving a new parent, their Telegram QR invite opens, so they can scan it right away.') }}</p>
                @endif

                <div class="modal-action sm:col-span-2">
                    <button type="button" class="btn" onclick="this.closest('dialog').close()">{{ __('Cancel') }}</button>
                    <button class="btn btn-primary">{{ __('Save parent') }}</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>{{ __('Close') }}</button></form>
    </dialog>

    {{-- Telegram invite of one parent: QR code to scan, or the link to send --}}
    <dialog id="invite-dialog" class="modal">
        <div class="modal-box max-w-md text-center">
            <h3 class="text-lg font-semibold">{{ __('Invite to Telegram') }}</h3>
            <p class="mt-1 font-medium" data-invite-name></p>
            <p class="text-sm opacity-60" data-invite-children></p>
            <div class="mx-auto mt-4 w-60 rounded-2xl border border-base-300 bg-white p-3 shadow-sm" data-qr="" data-qr-label="{{ __('QR code: Telegram invite') }}"></div>
            <ol class="mx-auto mt-4 max-w-xs space-y-1 text-start text-sm">
                <li>1. {{ __('The parent scans the QR code with the phone camera (or opens the link).') }}</li>
                <li>2. {{ __('Telegram opens the school bot: press Start.') }}</li>
                <li>3. {{ __('Done: the parent is linked and gets attendance news here.') }}</li>
            </ol>
            <div class="join mt-4 w-full">
                <input type="text" class="input join-item w-full text-xs" readonly data-invite-link aria-label="{{ __('Invite link') }}">
                <button type="button" class="btn join-item" data-invite-copy><i data-lucide="copy" class="size-4"></i><span>{{ __('Copy') }}</span></button>
            </div>
            <p class="mt-2 text-xs opacity-60">{{ __('The link is for this parent only and works once.') }}</p>
            <div class="modal-action justify-center">
                <a href="#" target="_blank" class="btn" data-invite-print><i data-lucide="printer" class="size-4"></i>{{ __('Print') }}</a>
                <form method="dialog"><button class="btn btn-primary">{{ __('Close') }}</button></form>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop"><button>{{ __('Close') }}</button></form>
    </dialog>

    @php
        // Just saved a new parent who is not on Telegram: open their QR invite straight away
        $newParent = $telegramLive && session('invite_parent')
            ? \App\Models\Guardian::with('students.schoolClass')->find(session('invite_parent'))
            : null;
        $newLink = $newParent?->telegramInviteLink();
    @endphp
    @if ($newParent && $newLink)
        <button type="button" class="hidden" data-invite data-invite-auto
            data-invite-link="{{ $newLink }}"
            data-invite-name="{{ $km && $newParent->name_km ? $newParent->name_km : $newParent->name }}"
            data-invite-children="{{ $newParent->students->map(fn ($c) => ($km ? $c->name_km : $c->name_latin).' · '.$c->schoolClass->name)->join(', ') }}"
            data-invite-print="{{ route('parents.invites', ['parent' => $newParent->id]) }}"></button>
    @endif
@endsection


@push('scripts')
<script>
    // Children of the parent: type to filter all students, pick one or more, each becomes a chip with ×
    (() => {
        const form = document.getElementById('parent-form-fields');
        if (!form) return;
        const students = @json($childData);
        const byId = new Map(students.map((s) => [s.id, s]));
        const chips = form.querySelector('[data-children-chips]');
        const search = form.querySelector('[data-children-search]');
        const list = form.querySelector('[data-children-list]');
        const noMatch = @json(__('No student matches.'));
        let chosen = [];
        let active = 0;

        const esc = (t) => String(t).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);
        const drawChips = () => {
            chips.innerHTML = chosen.map((id) => {
                const s = byId.get(id);
                return `<span class="badge badge-soft badge-primary h-auto gap-1.5 py-1 ps-2.5 pe-1">
                    <input type="hidden" name="student_ids[]" value="${id}">
                    ${esc(s ? s.label : '#' + id)}
                    <button type="button" class="hover:text-error grid size-5 place-items-center rounded-full" data-remove="${id}" aria-label="${esc(s ? s.label : '')} ×">✕</button>
                </span>`;
            }).join('');
            chips.classList.toggle('mb-2', chosen.length > 0);
        };
        const matches = () => {
            const q = search.value.trim().toLowerCase();
            if (!q) return [];
            return students.filter((s) => s.active && !chosen.includes(s.id) && s.search.includes(q)).slice(0, 8);
        };
        const drawList = () => {
            const found = matches();
            active = Math.min(active, Math.max(0, found.length - 1));
            list.classList.toggle('hidden', !search.value.trim());
            list.innerHTML = found.length
                ? found.map((s, i) => `<li role="option" class="child-item${i === active ? ' is-active' : ''}" data-pick="${s.id}" aria-selected="${i === active}">
                        <span class="font-medium">${esc(s.label)}</span><span class="text-xs opacity-60">${esc(s.sub)}</span></li>`).join('')
                : `<li class="px-3 py-2 text-sm opacity-60">${esc(noMatch)}</li>`;
        };
        const pick = (id) => {
            if (!chosen.includes(id)) chosen.push(id);
            search.value = '';
            active = 0;
            drawChips();
            drawList();
            search.focus();
        };

        search.addEventListener('input', () => { active = 0; drawList(); });
        search.addEventListener('keydown', (e) => {
            const found = matches();
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                active = (active + (e.key === 'ArrowDown' ? 1 : -1) + found.length) % Math.max(1, found.length);
                drawList();
            } else if (e.key === 'Enter') {
                e.preventDefault(); // Enter picks the child, it does not send the form
                if (found[active]) pick(found[active].id);
            } else if (e.key === 'Escape' && search.value) {
                e.preventDefault();
                search.value = '';
                drawList();
            }
        });
        list.addEventListener('mousedown', (e) => {
            const li = e.target.closest('[data-pick]');
            if (li) { e.preventDefault(); pick(Number(li.dataset.pick)); }
        });
        chips.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-remove]');
            if (!btn) return;
            chosen = chosen.filter((id) => id !== Number(btn.dataset.remove));
            drawChips();
        });

        // Add / Edit opened: the parent's children (none for Add)
        form.addEventListener('dialog:fill', (e) => {
            chosen = (e.detail.student_ids || []).map(Number);
            search.value = '';
            drawChips();
            drawList();
            form.querySelector('details')?.removeAttribute('open');
        });

        // After a failed save the page comes back with the children that were chosen
        chosen = @json($oldChildren);
        drawChips();
    })();
</script>
<script>
    // "Invite" button: fill the invite window, draw the QR code, and copy the link on request
    (() => {
        const dialog = document.getElementById('invite-dialog');
        if (!dialog) return;
        const copied = @json(__('Copied'));
        const copyWord = @json(__('Copy'));
        document.addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-invite]');
            if (!btn) return;
            dialog.querySelector('[data-invite-name]').textContent = btn.dataset.inviteName;
            dialog.querySelector('[data-invite-children]').textContent = btn.dataset.inviteChildren;
            dialog.querySelector('[data-invite-link]').value = btn.dataset.inviteLink;
            dialog.querySelector('[data-invite-print]').href = btn.dataset.invitePrint;
            dialog.querySelector('[data-qr]').dataset.qr = btn.dataset.inviteLink;
            dialog.querySelector('[data-invite-copy] span').textContent = copyWord;
            dialog.showModal();
            await window.drawQr(dialog);
        });
        dialog.querySelector('[data-invite-copy]').addEventListener('click', async (e) => {
            const input = dialog.querySelector('[data-invite-link]');
            try { await navigator.clipboard.writeText(input.value); } catch { input.select(); document.execCommand('copy'); }
            e.currentTarget.querySelector('span').textContent = copied;
        });
        // A parent was just added: open their QR invite once the page (and the QR helper) has loaded
        window.addEventListener('DOMContentLoaded', () => document.querySelector('[data-invite-auto]')?.click());
    })();
</script>
@endpush
