{{--
    Add / edit student pop-up. Used on the Students page and on the Student details page.
    Needs: $classes. Opened by a button with data-form-dialog="student-form" (and data-values for Edit,
    see Student::formValues(): "_photo", "_initials" and "_telegram" are shown, not sent).
    Three parts: the student (with photo), class and status, and the parent.
--}}
@php
    $dialogOpen = $errors->any() && old('_modal') === 'student-form';
    $km = app()->getLocale() === 'km';
    $months = $km
        ? ['មករា', 'កុម្ភៈ', 'មីនា', 'មេសា', 'ឧសភា', 'មិថុនា', 'កក្កដា', 'សីហា', 'កញ្ញា', 'តុលា', 'វិច្ឆិកា', 'ធ្នូ']
        : ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $thisYear = (int) date('Y');
    $dob = old('date_of_birth') ? explode('-', old('date_of_birth')) : [null, null, null];
    $gender = old('gender', 'male');
    $section = fn ($icon, $text) => '<h4 class="text-primary mt-5 mb-1 flex items-center gap-2 text-sm font-semibold sm:col-span-2"><i data-lucide="'.$icon.'" class="size-4"></i>'.e($text).'</h4>';
@endphp
<dialog id="student-form" class="modal" @if ($dialogOpen) data-reopen @endif>
    <div class="modal-box max-w-2xl">
        <h3 class="text-lg font-semibold" data-dialog-title>{{ old('_title', __('Add student')) }}</h3>
        <form method="POST" action="{{ old('_action', route('students.store')) }}" enctype="multipart/form-data"
              class="mt-2 grid grid-cols-1 gap-x-4 sm:grid-cols-2" data-dialog-form id="student-form-fields">
            @csrf
            <input type="hidden" name="_method" value="{{ old('_method', 'POST') }}">
            <input type="hidden" name="_action" value="{{ old('_action', route('students.store')) }}">
            <input type="hidden" name="_title" value="{{ old('_title', __('Add student')) }}">
            <input type="hidden" name="_modal" value="student-form">

            {{-- Photo: a preview right away, saved together with the form --}}
            <div class="flex items-center gap-4 sm:col-span-2">
                <div class="avatar avatar-placeholder" data-photo-circle>
                    <div class="bg-primary/15 text-primary size-20 overflow-hidden rounded-full">
                        <img src="" alt="" class="hidden size-full object-cover" data-photo-img>
                        <span class="text-2xl font-semibold" data-photo-initials><i data-lucide="user" class="size-8"></i></span>
                    </div>
                </div>
                <div class="min-w-0">
                    <div class="flex flex-wrap gap-2">
                        <label class="btn btn-sm">
                            <i data-lucide="camera" class="size-4"></i><span data-photo-choose>{{ __('Add photo') }}</span>
                            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="hidden" data-photo-input>
                        </label>
                        <button type="button" class="btn btn-sm btn-ghost text-error hidden" data-photo-remove>
                            <i data-lucide="trash-2" class="size-4"></i>{{ __('Remove photo') }}
                        </button>
                    </div>
                    <input type="hidden" name="remove_photo" value="" data-photo-remove-flag>
                    <p class="mt-1 text-xs opacity-60">{{ __('JPG, PNG or WebP, up to 5 MB. It is cut to a square and made small (400 × 400).') }}</p>
                    @error('photo')
                        <p class="text-error mt-1 text-xs" data-error>{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- 1. The student --}}
            {!! $section('user', __('Student')) !!}
            @include('partials.field', ['name' => 'name_km', 'label' => __('Name (Khmer)'), 'placeholder' => 'សុខ ដារា'])
            @include('partials.field', ['name' => 'name_latin', 'label' => __('Name (Latin)'), 'placeholder' => 'Sok Dara'])
            @include('partials.field', ['name' => 'student_code', 'label' => __('Student code'), 'placeholder' => 'S-10A-008'])
            <fieldset class="fieldset">
                <legend class="fieldset-legend">{{ __('Gender') }}</legend>
                <div class="join w-full" role="radiogroup" aria-label="{{ __('Gender') }}">
                    <input type="radio" name="gender" value="male" class="btn join-item flex-1" aria-label="{{ __('Male') }}" @checked($gender === 'male')>
                    <input type="radio" name="gender" value="female" class="btn join-item flex-1" aria-label="{{ __('Female') }}" @checked($gender === 'female')>
                </div>
                @error('gender')
                    <p class="label text-error whitespace-normal" data-error>{{ $message }}</p>
                @enderror
            </fieldset>

            {{-- Date of birth: day, month and year (a calendar would need many clicks back to 2010) --}}
            <fieldset class="fieldset sm:col-span-2" data-dob>
                <legend class="fieldset-legend">{{ __('Date of birth') }} <span class="font-normal opacity-60">({{ __('optional') }})</span></legend>
                <input type="hidden" name="date_of_birth" value="{{ old('date_of_birth') }}" data-dob-value>
                <div class="grid grid-cols-[5.5rem_1fr_7rem] gap-2">
                    <select class="select w-full" aria-label="{{ __('Day') }}" data-picker data-dob-part="day">
                        <option value="">{{ __('Day') }}</option>
                        @for ($d = 1; $d <= 31; $d++)
                            <option value="{{ sprintf('%02d', $d) }}" @selected(($dob[2] ?? null) === sprintf('%02d', $d))>{{ $d }}</option>
                        @endfor
                    </select>
                    <select class="select w-full" aria-label="{{ __('Month') }}" data-picker data-dob-part="month">
                        <option value="">{{ __('Month') }}</option>
                        @foreach ($months as $i => $name)
                            <option value="{{ sprintf('%02d', $i + 1) }}" @selected(($dob[1] ?? null) === sprintf('%02d', $i + 1))>{{ $name }}</option>
                        @endforeach
                    </select>
                    <select class="select w-full" aria-label="{{ __('Year') }}" data-picker data-dob-part="year">
                        <option value="">{{ __('Year') }}</option>
                        @for ($y = $thisYear - 5; $y >= $thisYear - 30; $y--)
                            <option value="{{ $y }}" @selected(($dob[0] ?? null) === (string) $y)>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                @error('date_of_birth')
                    <p class="label text-error whitespace-normal" data-error>{{ $message }}</p>
                @enderror
            </fieldset>

            {{-- 2. Class and status --}}
            {!! $section('school', __('Class and status')) !!}
            @include('partials.field', ['name' => 'school_class_id', 'label' => __('Class'), 'options' => $classes->pluck('name', 'id')->all(), 'icon' => 'school'])
            @include('partials.field', ['name' => 'status', 'label' => __('Status'), 'options' => ['active' => __('Active'), 'inactive' => __('Inactive (left the school)')], 'icon' => 'user-check'])
            <p class="bg-warning/10 text-warning-content rounded-box hidden items-start gap-2 p-3 text-sm sm:col-span-2" data-inactive-note>
                <i data-lucide="triangle-alert" class="text-warning mt-0.5 size-4 shrink-0"></i>
                {{ __('An inactive student is no longer on attendance lists and gets no messages. The attendance history is kept.') }}
            </p>

            {{-- 3. The parent --}}
            {!! $section('users', __('Parent (receives Telegram or SMS messages)')) !!}
            @include('partials.field', ['name' => 'guardian_name', 'label' => __('Parent name'), 'placeholder' => 'Sok Chantha'])
            @include('partials.field', ['name' => 'guardian_phone', 'label' => __('Parent phone'), 'placeholder' => '012 345 678'])
            @include('partials.field', ['name' => 'guardian_relation', 'label' => __('Relation'), 'options' => ['father' => __('Father'), 'mother' => __('Mother'), 'guardian' => __('Guardian')], 'icon' => 'heart-handshake'])
            <div class="flex items-end pb-2" data-telegram-status>
                <span class="badge badge-soft badge-success hidden gap-1.5" data-telegram="yes"><i data-lucide="send" class="size-3.5"></i>{{ __('Telegram linked') }}</span>
                <span class="badge badge-soft badge-ghost hidden gap-1.5" data-telegram="no" title="{{ __('The parent can join the school bot; then add the chat ID on the Parents page.') }}"><i data-lucide="message-square" class="size-3.5"></i>{{ __('No Telegram yet: SMS (demo)') }}</span>
            </div>

            <div class="modal-action sm:col-span-2">
                <button type="button" class="btn" onclick="this.closest('dialog').close()">{{ __('Cancel') }}</button>
                <button class="btn btn-primary">{{ __('Save student') }}</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop"><button>{{ __('Close') }}</button></form>
</dialog>

@push('scripts')
<script>
    // Student form: photo preview, date of birth from three boxes, the inactive note and the parent's Telegram badge
    (() => {
        const form = document.getElementById('student-form-fields');
        if (!form) return;
        const img = form.querySelector('[data-photo-img]');
        const initials = form.querySelector('[data-photo-initials]');
        const input = form.querySelector('[data-photo-input]');
        const removeBtn = form.querySelector('[data-photo-remove]');
        const removeFlag = form.querySelector('[data-photo-remove-flag]');
        const choose = form.querySelector('[data-photo-choose]');
        const words = { add: @json(__('Add photo')), change: @json(__('Change photo')) };
        let saved = { photo: null, initials: '' };

        const showPhoto = (src) => {
            img.src = src || '';
            img.classList.toggle('hidden', !src);
            initials.classList.toggle('hidden', !!src);
            removeBtn.classList.toggle('hidden', !src);
            choose.textContent = src ? words.change : words.add;
        };
        const showInitials = (text) => {
            initials.innerHTML = text ? '' : '<i data-lucide="user" class="size-8"></i>';
            if (text) initials.textContent = text;
            window.drawIcons?.();
        };

        input.addEventListener('change', () => {
            const file = input.files[0];
            removeFlag.value = '';
            if (file) showPhoto(URL.createObjectURL(file));
        });
        removeBtn.addEventListener('click', () => {
            input.value = '';
            removeFlag.value = saved.photo ? '1' : '';
            showPhoto(null);
        });

        // Date of birth: the three boxes write "YYYY-MM-DD" into the hidden field (empty until all three are chosen)
        const dobValue = form.querySelector('[data-dob-value]');
        const part = (p) => form.querySelector(`[data-dob-part="${p}"]`);
        const writeDob = () => {
            const [y, m, d] = ['year', 'month', 'day'].map((p) => part(p).value);
            dobValue.value = y && m && d ? `${y}-${m}-${d}` : '';
        };
        ['day', 'month', 'year'].forEach((p) => part(p).addEventListener('change', writeDob));
        const readDob = () => {
            const [y = '', m = '', d = ''] = (dobValue.value || '').split('-');
            [['year', y], ['month', m], ['day', d]].forEach(([p, v]) => {
                const box = part(p);
                if (box.value === v) return;
                box.value = v;
                box.dispatchEvent(new Event('change'));
            });
        };

        // Inactive: show what it means
        const status = form.elements.status;
        const note = form.querySelector('[data-inactive-note]');
        const showNote = () => {
            note.classList.toggle('hidden', status.value !== 'inactive');
            note.classList.toggle('flex', status.value === 'inactive');
        };
        status.addEventListener('change', showNote);

        // Edit / Add opened: fill the extra parts from the button's values
        form.addEventListener('dialog:fill', (e) => {
            const v = e.detail || {};
            saved = { photo: v._photo || null, initials: v._initials || '' };
            removeFlag.value = '';
            showInitials(saved.initials);
            showPhoto(saved.photo);
            readDob();
            showNote();
            form.querySelectorAll('[data-telegram]').forEach((b) => {
                b.classList.toggle('hidden', v._telegram === undefined || v._telegram === null || String(v._telegram) !== (b.dataset.telegram === 'yes' ? 'true' : 'false'));
            });
        });

        readDob();
        showNote();
    })();
</script>
@endpush
