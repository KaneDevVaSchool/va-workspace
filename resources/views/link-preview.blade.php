<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    @php
        /** @var array{title: string, description: string, image: ?string, url: string} $meta */
        $ogTitle = $meta['title'];
        $ogDescription = $meta['description'];
        $ogImage = $meta['image'] ?? asset('images/og-cover.jpg');
        // Zalo hay lỗi khi og:image có query string (?v=…).
        $ogImageStable = preg_replace('/\?.*$/', '', (string) $ogImage) ?: $ogImage;
        $ogPageUrl = $meta['url'];
    @endphp
    <title>{{ $ogTitle }}</title>
    <meta name="title" content="{{ $ogTitle }}">
    <meta name="description" content="{{ $ogDescription }}">
    <link rel="canonical" href="{{ $ogPageUrl }}">
    @if ($ogImageStable)
    <link rel="image_src" href="{{ $ogImageStable }}">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:locale" content="vi_VN">
    <meta property="og:site_name" content="VA Workspace">
    <meta property="og:title" content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDescription }}">
    <meta property="og:url" content="{{ $ogPageUrl }}">
    @if ($ogImageStable)
    <meta property="og:image" content="{{ $ogImageStable }}">
    @if (str_starts_with($ogImageStable, 'https://'))
    <meta property="og:image:secure_url" content="{{ $ogImageStable }}">
    @endif
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="zalo:title" content="{{ $ogTitle }}">
    <meta property="zalo:description" content="{{ $ogDescription }}">
    <meta property="zalo:image" content="{{ $ogImageStable }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $ogTitle }}">
    <meta name="twitter:description" content="{{ $ogDescription }}">
    @if ($ogImageStable)
    <meta name="twitter:image" content="{{ $ogImageStable }}">
    @endif
</head>
<body>
    <h1>{{ $ogTitle }}</h1>
    <p>{{ $ogDescription }}</p>
    @if ($ogImageStable)
    <img src="{{ $ogImageStable }}" alt="">
    @endif
    <p><a href="{{ $ogPageUrl }}">{{ $ogPageUrl }}</a></p>
</body>
</html>
