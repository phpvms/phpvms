<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

{{-- Standalone on purpose: a 500 can come from the layout's own data (database, vite manifest), so this page needs neither. --}}
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Server error</title>
    <style>
        :root { color-scheme: light dark; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; font-family: Inter, system-ui, sans-serif; background: #f7f8fa; color: #22262f; }
        main { width: 100%; max-width: 520px; text-align: center; }
        .code { margin: 0; font: 700 64px/1 "IBM Plex Mono", ui-monospace, monospace; color: #9aa1b2; }
        h1 { margin: 12px 0 4px; font-size: 20px; }
        p { margin: 8px 0; font-size: 13px; color: #6b7280; }
        .card { margin-top: 24px; padding: 16px; text-align: left; border: 1px solid #e6e8ef; border-radius: 8px; background: #fff; }
        .card h2 { margin: 0 0 8px; font-size: 13px; }
        .card li { font-size: 13px; color: #4b5262; margin: 4px 0; }
        a { color: #067ec1; }
        @media (prefers-color-scheme: dark) {
            body { background: #0d0e12; color: #eef0f4; }
            .card { background: #181b22; border-color: #22262f; }
            .card li { color: #c3c8d4; }
            a { color: #4cb4ec; }
        }
    </style>
</head>

<body>
    <main>
        <p class="code">500</p>
        <h1>@lang('errors.500.title')</h1>
        <p>@lang('errors.500.message')</p>

        <div class="card">
            <h2>For administrators</h2>
            <ul>
                <li>Check the Laravel logs for the error details.</li>
                <li>Set <code>APP_DEBUG</code> to true in the environment file and open this page again. Set it back to false afterwards.</li>
                <li>Ask for help with the details from the log, not a screenshot of this page.</li>
            </ul>
            <p><a href="https://docs.phpvms.net/help">phpvms docs: getting help</a></p>
        </div>

        <p><a href="{{ url('/') }}">Go home</a></p>
    </main>
</body>

</html>
