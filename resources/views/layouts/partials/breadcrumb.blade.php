@if(!empty($items))
<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol class="breadcrumb__list">
        @foreach($items as $i => $item)
            <li class="breadcrumb__item">
                @if(!empty($item['url']) && $i < count($items) - 1)
                    <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                @else
                    <span aria-current="page">{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
@endif
