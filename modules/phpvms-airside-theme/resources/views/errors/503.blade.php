<!DOCTYPE html>
<html lang="en">

{{-- Standalone on purpose: maintenance mode must render with no database, session or build manifest. --}}
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Server maintenance</title>
    <style>
        :root { color-scheme: light dark; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; font-family: Inter, system-ui, sans-serif; background: #f7f8fa; color: #22262f; text-align: center; }
        .code { margin: 0; font: 700 64px/1 "IBM Plex Mono", ui-monospace, monospace; color: #9aa1b2; }
        h1 { margin: 12px 0 4px; font-size: 20px; }
        p { margin: 8px 0; font-size: 13px; color: #6b7280; }
        @media (prefers-color-scheme: dark) {
            body { background: #0d0e12; color: #eef0f4; }
        }
    </style>
</head>

<body>
    <main>
        <p class="code">503</p>
        <h1>We will be right back</h1>
        <p>The server is in maintenance. It will be online again shortly. Thank you for your patience.</p>
    </main>
</body>

</html>
