@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $paginator */
@endphp

@if ($paginator->hasPages())
    <nav class="pagination" aria-label="Navigasi halaman">
        @if ($paginator->onFirstPage())
            <span class="page-btn disabled" aria-disabled="true">&laquo;</span>
        @else
            <a class="page-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">&laquo;</a>
        @endif

        @foreach ($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
            @if ($page == $paginator->currentPage())
                <span class="page-btn active" aria-current="page">{{ $page }}</span>
            @else
                <a class="page-btn" href="{{ $url }}">{{ $page }}</a>
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a class="page-btn" href="{{ $paginator->nextPageUrl() }}" rel="next">&raquo;</a>
        @else
            <span class="page-btn disabled" aria-disabled="true">&raquo;</span>
        @endif
    </nav>
    <p class="pagination-meta muted">
        Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}
        · Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }} produk
    </p>
@endif
