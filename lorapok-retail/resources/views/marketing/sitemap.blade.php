{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($paths as $path)
    <url>
        <loc>{{ $base }}{{ $path === '/' ? '/' : $path }}</loc>
        {{-- No <lastmod>: a date that is not maintained is worse than none,
             and an always-today value trains crawlers to ignore it. --}}
        <changefreq>{{ $path === '/' ? 'weekly' : 'monthly' }}</changefreq>
        <priority>{{ $path === '/' ? '1.0' : '0.7' }}</priority>
    </url>
@endforeach
</urlset>
