{{--
    Add / edit student pop-up. Used on the Students page and on the Student details page.
    Needs: $classes. Opened by a button with data-form-dialog="student-form" (and data-values for Edit).
--}}
@php $dialogOpen = $errors->any() && old('_modal') === 'student-form'; @endphp
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
