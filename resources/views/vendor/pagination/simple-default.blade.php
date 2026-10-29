@if ($paginator->hasPages())
    <nav class="actions" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="btn btn-sm btn-outline" style="opacity:.5">Previous</span>
        @else
            <a class="btn btn-sm btn-outline" href="{{ $paginator->previousPageUrl() }}">Previous</a>
        @endif
        <span class="muted">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())
            <a class="btn btn-sm btn-outline" href="{{ $paginator->nextPageUrl() }}">Next</a>
        @else
            <span class="btn btn-sm btn-outline" style="opacity:.5">Next</span>
        @endif
    </nav>
@endif
