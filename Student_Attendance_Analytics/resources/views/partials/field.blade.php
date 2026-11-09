{{--
    One form field with its label and error message.
    @include('partials.field', ['name' => 'name_km', 'label' => 'Name (Khmer)'])                       text input
    @include('partials.field', ['name' => 'gender', 'label' => 'Gender', 'options' => [...]])         dropdown
    Optional: 'type' (date, email, password...), 'placeholder', 'required' (default true), 'span' (full width),
              'hint' (help text), 'default' (value shown before the form is sent), 'markOptional' (false = no "(optional)")
--}}
@php
    $required = $required ?? true;
    $hasError = $errors->has($name);
    $current = (string) old($name, $default ?? '');
@endphp
<fieldset @class(['fieldset', 'sm:col-span-2' => $span ?? false])>
    <legend class="fieldset-legend">{{ $label }}@unless ($required || ! ($markOptional ?? true)) <span class="font-normal opacity-60">({{ __('optional') }})</span>@endunless</legend>
    @isset($options)
        <select name="{{ $name }}" id="{{ $name }}" @class(['select w-full', 'select-error' => $hasError]) @required($required)>
            @foreach ($options as $value => $text)
                <option value="{{ $value }}" @selected($current === (string) $value)>{{ $text }}</option>
            @endforeach
        </select>
    @else
        <input type="{{ $type ?? 'text' }}" name="{{ $name }}" id="{{ $name }}" value="{{ ($type ?? 'text') === 'password' ? '' : $current }}" placeholder="{{ $placeholder ?? '' }}"
            @class(['input w-full', 'input-error' => $hasError]) @required($required) />
    @endisset
    @error($name)
        <p class="label text-error whitespace-normal" data-error>{{ $message }}</p>
    @enderror
    @isset($hint)
        <p class="label whitespace-normal">{{ $hint }}</p>
    @endisset
</fieldset>
