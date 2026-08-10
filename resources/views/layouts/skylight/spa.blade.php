{{--
  Inertia mount shell for the SPA theme. This is the theme's ONLY Blade file.
  Inertia's rootView is set to `spa` (NOT the default `app`) for this theme, so
  the theme view finder resolves `view('spa')` to THIS file. The rootView was
  deliberately renamed away from `app` to avoid colliding with the core app's
  own `app` view.

  Assets are web-served from public/build/<theme>/ (published by the theme's
  pnpm Vite build). In dev, a `hot` file (written by `pnpm dev`) points at the
  Vite HMR server instead.

  DEV-ONLY import-map caveat (addon authors: read this): in prod the host build
  externalizes `vue`, so host + every pre-built addon resolve the bare "vue"
  specifier through the import-map below to ONE shared Vue. In dev, the Vite HMR
  server serves the host's Vue from node_modules while addon vendor modules still
  resolve "vue" via the import-map — so there are TWO Vue instances in DEV ONLY.
  This does not occur in a production build.
--}}
@php
    // Active theme name (rename-safe); fall back to this dir's name.
    $theme = 'skylight';
    try {
        $active = \Igaster\LaravelTheme\Facades\Theme::get();
        if (is_string($active) && $active !== '') {
            $theme = $active;
        }
    } catch (\Throwable $e) {
        // keep fallback
    }

    $buildBase = "build/{$theme}";
    $hotFile   = public_path("{$buildBase}/hot");
    $isDev     = is_file($hotFile);

    // Single shared Vue for the whole page: the host build externalizes `vue`, and
    // pre-built third-party ESM addon widgets also `import 'vue'`. This import-map
    // resolves every bare "vue" specifier to ONE module URL so the browser loads a
    // single Vue instance (ESM caches by resolved URL). It MUST precede the main
    // module script. The shared file is web-served from public/ (not the Vite dev
    // server), so the URL is the same in dev and prod.
    $vueImportMap = '<script type="importmap">'
        . json_encode(['imports' => ['vue' => asset("{$buildBase}/vendor/vue.js")]], JSON_UNESCAPED_SLASHES)
        . '</script>';

    $styleTags = [];
    $scriptTags = [$vueImportMap];
    if ($isDev) {
        $hot = trim(@file_get_contents($hotFile)) ?: 'http://localhost:5273';
        $styleTags[] = '<link rel="stylesheet" href="' . e($hot) . '/src/app/app.css">';
        $scriptTags[] = '<script type="module" src="' . e($hot) . '/@vite/client"></script>';
        $scriptTags[] = '<script type="module" src="' . e($hot) . '/src/app/main.ts"></script>';
    } else {
        $manifestPath = public_path("{$buildBase}/manifest.json");
        $manifest = is_file($manifestPath)
            ? (json_decode(file_get_contents($manifestPath), true) ?: [])
            : [];
        $appStylesheet = $manifest['src/app/app.css']['file'] ?? null;
        if ($appStylesheet) {
            $styleTags[] = '<link rel="stylesheet" href="' . e(asset("{$buildBase}/{$appStylesheet}")) . '">';
        }
        $entry = $manifest['src/app/main.ts'] ?? null;
        if ($entry) {
            foreach (($entry['css'] ?? []) as $css) {
                $styleTags[] = '<link rel="stylesheet" href="' . e(asset("{$buildBase}/{$css}")) . '">';
            }
            $scriptTags[] = '<script type="module" src="' . e(asset("{$buildBase}/{$entry['file']}")) . '"></script>';
        }
    }

    $publication = app(\App\Services\Theme\ActiveThemeService::class)->revision($theme);
    if ($publication) {
        $runtimeStyles = app(\App\Services\Theme\ActiveThemeService::class)->stylesheetUrls($publication);
        $styleTags[] = '<link rel="stylesheet" href="' . e($runtimeStyles['theme']) . '">';
        if ($runtimeStyles['custom']) {
            $styleTags[] = '<link rel="stylesheet" href="' . e($runtimeStyles['custom']) . '">';
        }
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="shortcut icon" type="image/png" href="{{ public_asset('/assets/img/favicon.png') }}">
    <title inertia>{{ config('app.name', 'phpVMS') }}</title>
    <script>
        (() => {
            let saved = 'auto';
            try {
                saved = localStorage.getItem('skylight.theme') ?? 'auto';
            } catch {
                // Storage can be unavailable in privacy-restricted contexts.
            }
            const mode = ['light', 'dark', 'auto'].includes(saved) ? saved : 'auto';
            const dark = mode === 'dark' || (mode === 'auto' && matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
            document.documentElement.dataset.themeMode = mode;
        })();
    </script>
    <style>@layer properties, theme, base, components, utilities;</style>
    {!! implode("\n    ", $styleTags) !!}
    {!! implode("\n    ", $scriptTags) !!}
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
