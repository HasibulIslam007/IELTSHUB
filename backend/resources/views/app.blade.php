@php($seo = app(\App\Services\SeoService::class)->metadata(request()->path()))
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#4147d5">
    <title>{{ $seo['title'] }}</title>
    <meta name="description" content="{{ $seo['description'] }}">
    <meta name="robots" content="{{ $seo['public'] ? 'index, follow' : 'noindex, nofollow' }}">
    <link rel="canonical" href="{{ $seo['canonical'] }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="IELTS Practice Hub">
    <meta property="og:title" content="{{ $seo['title'] }}">
    <meta property="og:description" content="{{ $seo['description'] }}">
    <meta property="og:url" content="{{ $seo['canonical'] }}">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $seo['title'] }}">
    <meta name="twitter:description" content="{{ $seo['description'] }}">
    <link rel="icon" href="/favicon.svg">
    @if(!config('hub.frontend_dev') && file_exists(public_path('build/manifest.json')))
        @php($manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true))
        @foreach($manifest['frontend/src/main.tsx']['css'] ?? [] as $css)
            <link rel="stylesheet" href="/build/{{ $css }}">
        @endforeach
        <script type="module" src="/build/{{ $manifest['frontend/src/main.tsx']['file'] }}"></script>
    @else
        <script type="module" nonce="{{ request()->attributes->get('csp_nonce') }}">
            import RefreshRuntime from 'http://127.0.0.1:5173/build/@react-refresh';
            RefreshRuntime.injectIntoGlobalHook(window);
            window.$RefreshReg$ = () => {};
            window.$RefreshSig$ = () => (type) => type;
            window.__vite_plugin_react_preamble_installed__ = true;
        </script>
        <script type="module" src="http://127.0.0.1:5173/build/@vite/client"></script>
        <script type="module" src="http://127.0.0.1:5173/build/frontend/src/main.tsx"></script>
    @endif
</head>
<body>
    <div id="root"></div>
    <noscript>This learning application requires JavaScript to save and submit answers.</noscript>
</body>
</html>
