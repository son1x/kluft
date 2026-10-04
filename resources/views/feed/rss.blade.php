{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ $title }}</title>
        <link>{{ $link }}</link>
        <atom:link href="{{ $self }}" rel="self" type="application/rss+xml" />
        <description>Neuigkeiten der Deutschen Pfadfinderschaft Sankt Georg im {{ config('dpsg.site.region') }}</description>
        <language>de-de</language>
        @if ($posts->isNotEmpty())
            <lastBuildDate>{{ $posts->first()->published_at->toRssString() }}</lastBuildDate>
        @endif
        @foreach ($posts as $post)
            @php($url = route('posts.show', $post->slug))
            @php($summary = $post->teaser ?: \Illuminate\Support\Str::limit(trim(html_entity_decode(strip_tags((string) $post->body))), 300))
            <item>
                <title>{{ $post->title }}</title>
                <link>{{ $url }}</link>
                <guid isPermaLink="true">{{ $url }}</guid>
                <pubDate>{{ $post->published_at->toRssString() }}</pubDate>
                <description>{{ $summary }}</description>
                @if ($post->coverMedia && str_starts_with((string) $post->coverMedia->mime, 'image/'))
                    <enclosure url="{{ $post->coverMedia->url() }}" type="{{ $post->coverMedia->mime }}" length="{{ (int) $post->coverMedia->size }}" />
                @endif
            </item>
        @endforeach
    </channel>
</rss>
