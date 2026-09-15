<!doctype html>
<html lang="sr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}">
<link rel="canonical" href="{{ $url }}">
<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">

{{-- Open Graph: the card WhatsApp, Viber, Messenger and Facebook draw. --}}
<meta property="og:site_name" content="Meva Kozmetika">
<meta property="og:locale" content="sr_RS">
<meta property="og:type" content="{{ $type }}">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $social ?? $description }}">
<meta property="og:url" content="{{ $url }}">
<meta property="og:image" content="{{ $image }}">
@if ($imageSize)
{{-- The real shape of the file. Messengers lay the card out from these before
     the picture arrives, and a wrong number collapses it to the small preview. --}}
<meta property="og:image:width" content="{{ $imageSize[0] }}">
<meta property="og:image:height" content="{{ $imageSize[1] }}">
<meta property="og:image:type" content="{{ $imageType }}">
@endif
<meta property="og:image:secure_url" content="{{ $image }}">
<meta property="og:image:alt" content="{{ $body['name'] ?? 'Meva Kozmetika' }}">
@isset($price)
<meta property="product:price:amount" content="{{ number_format($price / 100, 2, '.', '') }}">
<meta property="product:price:currency" content="RSD">
<meta property="product:availability" content="in stock">
@endisset

{{-- X/Twitter --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $title }}">
<meta name="twitter:description" content="{{ $social ?? $description }}">
<meta name="twitter:image" content="{{ $image }}">

<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>

<style>
  :root { color-scheme: light }
  body { margin: 0; background: #f2efe8; color: #24342c; font: 16px/1.6 -apple-system, Segoe UI, Roboto, sans-serif; }
  .wrap { max-width: 44rem; margin: 0 auto; padding: 3rem 1.5rem; }
  img { max-width: 100%; border-radius: 1rem; }
  h1 { font-size: 2rem; line-height: 1.15; margin: 1.5rem 0 .5rem; }
  h2 { font-size: 1rem; text-transform: uppercase; letter-spacing: .12em; margin: 2rem 0 .5rem; color: #c56d59; }
  a.cta { display: inline-block; margin-top: 2rem; padding: 1rem 1.75rem; border-radius: 999px; background: #24342c; color: #f2efe8; text-decoration: none; }
  .muted { color: #5b6b62; }
</style>
</head>
<body>
  <div class="wrap">
    @if($body)
      <img src="{{ $image }}" alt="{{ $body['name'] }}">
      <h1>{{ $body['name'] }}</h1>
      @isset($price)<p class="muted">{{ number_format($price / 100, 0, ',', '.') }} RSD · besplatna dostava u celoj Srbiji</p>@endisset
      <p>{{ $body['description'] ?? $body['text'] ?? $description }}</p>
      @if(!empty($body['ingredients']))<h2>Sastav</h2><p>{{ $body['ingredients'] }}</p>@endif
      @if(!empty($body['usage']))<h2>Način upotrebe</h2><p>{{ $body['usage'] }}</p>@endif
    @else
      <h1>{{ $title }}</h1>
      <p>{{ $description }}</p>
    @endif
    <a class="cta" href="{{ $url }}">Otvori u prodavnici</a>
  </div>
</body>
</html>
