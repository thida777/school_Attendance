{{-- Page numbers under a table. The page you are on is a filled coloured button, and the text says "Page 2 of 3". --}}
<div class="flex flex-wrap items-center justify-between gap-2" data-pagination>
    <span class="text-sm opacity-60">
        {{ __('Showing :from–:to of :total', ['from' => $paginator->firstItem() ?? 0, 'to' => $paginator->lastItem() ?? 0, 'total' => $paginator->total()]) }}
        @if ($paginator->hasPages())
            · <b class="text-base-content font-semibold opacity-100">{{ __('Page :page of :last', ['page' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) }}</b>
        @endif
    </span>
    @if ($paginator->hasPages())
        <nav class="pager" aria-label="{{ __('Pages') }}">
            <a href="{{ $paginator->previousPageUrl() ?? '#' }}" @class(['pager-item', 'is-disabled' => $paginator->onFirstPage()]) aria-label="{{ __('Previous page') }}"
               @if ($paginator->onFirstPage()) aria-disabled="true" tabindex="-1" @endif>
                <i data-lucide="chevron-left" class="size-4"></i>
            </a>
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="pager-item is-gap">{{ $element }}</span>
                @else
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="pager-item is-current" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="pager-item" aria-label="{{ __('Page :page of :last', ['page' => $page, 'last' => $paginator->lastPage()]) }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
            <a href="{{ $paginator->nextPageUrl() ?? '#' }}" @class(['pager-item', 'is-disabled' => ! $paginator->hasMorePages()]) aria-label="{{ __('Next page') }}"
               @if (! $paginator->hasMorePages()) aria-disabled="true" tabindex="-1" @endif>
                <i data-lucide="chevron-right" class="size-4"></i>
            </a>
        </nav>
    @endif
</div>
