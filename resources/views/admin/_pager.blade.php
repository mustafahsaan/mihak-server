@if ($paginator->hasPages())
    <nav class="pager" aria-label="الصفحات">
        @if ($paginator->previousPageUrl())<a href="{{ $paginator->previousPageUrl() }}">السابق</a>@endif
        <span class="muted">صفحة {{ $paginator->currentPage() }} من {{ $paginator->lastPage() }}</span>
        @if ($paginator->nextPageUrl())<a href="{{ $paginator->nextPageUrl() }}">التالي</a>@endif
    </nav>
@endif
