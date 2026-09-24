@if($paginator->hasPages())
@php
    if (! empty($fragment ?? null)) {
        $paginator->fragment($fragment);
    }
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $start = max(1, $current - 2);
    $end = min($last, $current + 2);
@endphp
<nav class="pagination-bar" aria-label="{{ $label ?? 'Pagination' }}">
<div class="pagination-summary">
Showing {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }}
</div>
<div class="pagination-actions">
@if($paginator->onFirstPage())
<span class="pagination-link disabled" aria-disabled="true">← Previous</span>
@else
<a class="pagination-link" href="{{ $paginator->previousPageUrl() }}">← Previous</a>
@endif

@for($page = $start; $page <= $end; $page++)
@if($page === $current)
<span class="pagination-link active" aria-current="page">{{ $page }}</span>
@else
<a class="pagination-link" href="{{ $paginator->url($page) }}">{{ $page }}</a>
@endif
@endfor

@if($paginator->hasMorePages())
<a class="pagination-link" href="{{ $paginator->nextPageUrl() }}">Next →</a>
@else
<span class="pagination-link disabled" aria-disabled="true">Next →</span>
@endif
</div>
</nav>
@endif
