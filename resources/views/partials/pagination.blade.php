@if ($paginator->hasPages())
    <nav class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm" aria-label="{{ __('Pagination') }}">
        <p class="text-muted">
            @if (method_exists($paginator, 'total'))
                {{ __(':from to :to of :total', ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total()]) }}
            @endif
        </p>
        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="btn btn-secondary btn-sm opacity-50" aria-disabled="true">{{ __('Previous') }}</span>
            @else
                <a class="btn btn-secondary btn-sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('Previous') }}</a>
            @endif

            @isset($elements)
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="hidden px-2 text-muted sm:inline">{{ $element }}</span>
                    @endif
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="btn btn-primary btn-sm hidden sm:inline-flex" aria-current="page">{{ $page }}</span>
                            @else
                                <a class="btn btn-ghost btn-sm hidden sm:inline-flex" href="{{ $url }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            @endisset

            @if ($paginator->hasMorePages())
                <a class="btn btn-secondary btn-sm" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('Next') }}</a>
            @else
                <span class="btn btn-secondary btn-sm opacity-50" aria-disabled="true">{{ __('Next') }}</span>
            @endif
        </div>
    </nav>
@endif
