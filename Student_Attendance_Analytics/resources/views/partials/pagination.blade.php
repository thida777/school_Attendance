{{-- Page numbers under a table, in the daisyUI "join" style of the design --}}
<div class="flex flex-wrap items-center justify-between gap-2">
    <span class="text-sm opacity-60">
        {{ __('Showing :from–:to of :total', ['from' => $paginator->firstItem() ?? 0, 'to' => $paginator->lastItem() ?? 0, 'total' => $paginator->total()]) }}
    </span>
    @if ($paginator->hasPages())
        <div class="join">
            <a href="{{ $paginator->previousPageUrl() ?? '#' }}" @class(['join-item btn btn-sm', 'btn-disabled' => $paginator->onFirstPage()]) aria-label="{{ __('Previous page') }}">«</a>
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="join-item btn btn-sm btn-disabled">{{ $element }}</span>
                @else
                    @foreach ($element as $page => $url)
                        <a href="{{ $url }}" @class(['join-item btn btn-sm', 'btn-active' => $page == $paginator->currentPage()])>{{ $page }}</a>
                    @endforeach
                @endif
            @endforeach
            <a href="{{ $paginator->nextPageUrl() ?? '#' }}" @class(['join-item btn btn-sm', 'btn-disabled' => ! $paginator->hasMorePages()]) aria-label="{{ __('Next page') }}">»</a>
        </div>
    @endif
</div>
