@props(['seo'])

{{--
    Every tag a crawler and a link preview need, emitted once from one object.

    Hand-written per page these drift: the canonical says one address, og:url
    another, and the sitemap a third. A crawler then picks whichever it likes
    and the page competes with itself.
--}}
<title>{{ $seo->fullTitle() }}</title>
<meta name="description" content="{{ $seo->description }}">
<link rel="canonical" href="{{ $seo->canonical() }}">

@unless ($seo->indexable)
    {{-- Applied to pages that are real but should not rank: legal boilerplate,
         thank-you pages, anything duplicated. --}}
    <meta name="robots" content="noindex, follow">
@endunless

<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:title" content="{{ $seo->fullTitle() }}">
<meta property="og:description" content="{{ $seo->description }}">
<meta property="og:url" content="{{ $seo->canonical() }}">
<meta property="og:image" content="{{ $seo->imageUrl() }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seo->fullTitle() }}">
<meta name="twitter:description" content="{{ $seo->description }}">
<meta name="twitter:image" content="{{ $seo->imageUrl() }}">

@foreach ($seo->schema as $block)
    {{-- JSON_UNESCAPED_SLASHES keeps URLs readable; the content is built
         server-side from our own data, never from user input. --}}
    <script type="application/ld+json">{!! json_encode($block, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endforeach
