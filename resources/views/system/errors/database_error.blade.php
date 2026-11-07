<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>{{ $title }}</title>
  <style>
    :root { color-scheme: light dark; --bg: #f8fafc; --fg: #0f172a; --muted: #475569; --card: #fff; --line: #e2e8f0; --accent: #2563eb; }
    @media (prefers-color-scheme: dark) {
      :root { --bg: #0b1120; --fg: #e2e8f0; --muted: #94a3b8; --card: #111a2e; --line: #1e293b; --accent: #60a5fa; }
    }
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem;
      background: var(--bg); color: var(--fg); font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
    main { width: 100%; max-width: 32rem; background: var(--card); border: 1px solid var(--line); border-radius: .75rem; padding: 2rem; }
    .brand { font-weight: 700; letter-spacing: .02em; color: var(--accent); margin: 0 0 1.25rem; }
    h1 { font-size: 1.375rem; margin: 0 0 .5rem; }
    p { margin: 0 0 1rem; color: var(--muted); }
    a { color: var(--accent); }
    pre { margin: 1rem 0 0; padding: .75rem; overflow: auto; font-size: .8125rem; border: 1px solid var(--line); border-radius: .5rem; white-space: pre-wrap; word-break: break-word; }
  </style>
</head>
<body>
  <main>
    <p class="brand">phpvms</p>
    <h1>{{ $title }}</h1>
    <p>{{ $message }}</p>
    @isset($link)
      <p><a href="{{ $link['url'] }}">{{ $link['text'] }}</a></p>
    @endisset
    @isset($detail)
      <pre>{{ $detail }}</pre>
    @endisset
  </main>
</body>
</html>
