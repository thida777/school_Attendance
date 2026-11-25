{{--
    Round picture of a student: the photo, or the initials in the student's own colour.
    @include('partials.student-avatar', ['student' => $student, 'size' => 'size-10', 'text' => 'text-sm'])
    'attrs' (optional): extra attributes on the circle, e.g. 'data-avatar' for Take attendance.
--}}
@php
    $size = $size ?? 'size-10';
    $photo = $student->photoUrl();
@endphp
@if ($photo)
    <span class="avatar"><span class="{{ $size }} block shrink-0 overflow-hidden rounded-full" {!! $attrs ?? '' !!}><img src="{{ $photo }}" alt="{{ $student->name_latin }}" loading="lazy" class="size-full object-cover" /></span></span>
@else
    <span class="avatar avatar-placeholder"><span class="av-{{ $student->colour() }} {{ $size }} grid place-items-center rounded-full {{ $text ?? 'text-sm' }}" {!! $attrs ?? '' !!}><span>{{ $student->initials() }}</span></span></span>
@endif
