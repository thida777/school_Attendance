{{-- Edit and delete buttons of one class, used on its card and in its list row.
     $last: extra class on the delete button (e.g. "me-auto" pushes the other card buttons to the right) --}}
<button class="btn btn-ghost btn-sm btn-square" aria-label="{{ __('Edit') }}"
    data-form-dialog="class-form" data-action="{{ route('classes.update', $class) }}" data-method="PUT"
    data-title="{{ __('Edit class') }}"
    data-values="{{ json_encode($class->only(['name', 'grade', 'room', 'homeroom_teacher_id'])) }}">
    <i data-lucide="pencil" class="size-4"></i>
</button><button class="btn btn-ghost btn-sm btn-square -ms-1 {{ $last }}" aria-label="{{ __('Delete') }}"
    data-delete="{{ route('classes.destroy', $class) }}" data-name="{{ __('Class') }} {{ $class->name }}">
    <i data-lucide="trash-2" class="size-4"></i>
</button>
