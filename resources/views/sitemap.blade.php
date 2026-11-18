{{-- Split so the server's short_open_tag never reads "<?" as PHP. --}}
{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as $u)
    <url>
        <loc>{{ $u['loc'] }}</loc>
@if ($u['lastmod'])
        <lastmod>{{ $u['lastmod'] }}</lastmod>
@endif
    </url>
@endforeach
</urlset>
