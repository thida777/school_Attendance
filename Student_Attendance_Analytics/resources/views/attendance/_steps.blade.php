{{-- Where the teacher is in the flow. $step = 1 choose, 2 mark, 3 review, 4 done --}}
<div class="card bg-base-100 border-base-300 border">
    <div class="card-body py-4">
        <ul class="steps w-full text-xs sm:text-sm" id="steps">
            @foreach ([__('Choose class'), __('Mark'), __('Review'), __('Done')] as $i => $label)
                <li @class(['step', 'step-primary' => $i < $step])>{{ $label }}</li>
            @endforeach
        </ul>
    </div>
</div>
