{{--
    Shared <head> contents for the landing page (welcome.blade.php) and the
    public pages (layouts/public.blade.php), so fonts, meta and favicon never
    drift apart. Each page passes its own Vite entries in $entries.
    Title: "<section> | Levictas" when a page sets @section('title'), else
    "Levictas". @yield output is raw (inline @section values are already
    escaped), so it is echoed raw here too to avoid double-escaping.
--}}
@php($pageTitle = trim($__env->yieldContent('title')))
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{!! $pageTitle !== '' ? $pageTitle . ' | Levictas' : 'Levictas' !!}</title>

@vite($entries)

<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&display=swap"
    rel="stylesheet">
<link rel="icon" type="image/svg+xml" href="images/1.png" />
