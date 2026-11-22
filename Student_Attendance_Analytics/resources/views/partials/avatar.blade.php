{{--
    Round picture of a staff member: the profile photo, or the initials when there is none.
    @include('partials.avatar', ['person' => $user, 'size' => 'w-9', 'text' => 'text-xs', 'tone' => 'bg-base-300'])
--}}
@php
    $size = $size ?? 'w-9';
    $photo = $person->photoUrl();
@endphp
@if ($photo)
    <div class="avatar">
        <div class="{{ $size }} rounded-full"><img src="{{ $photo }}" alt="{{ $person->name }}" loading="lazy" /></div>
    </div>
@else
    <div class="avatar avatar-placeholder">
        <div class="{{ $size }} {{ $tone ?? '' }} rounded-full"><span class="{{ $text ?? 'text-xs' }}">{{ $person->initials() }}</span></div>
    </div>
@endif
