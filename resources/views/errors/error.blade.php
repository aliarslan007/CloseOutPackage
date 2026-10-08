<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $status }} - {{ config('app.name', 'Laravel') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; color: #0f172a; margin: 0; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 48px 56px; text-align: center; max-width: 420px; box-shadow: 0 4px 16px rgba(15, 23, 42, .06); }
        .status { font-size: 56px; font-weight: 700; color: #475569; margin: 0 0 8px; }
        .message { font-size: 16px; color: #334155; margin: 0 0 24px; }
        a { display: inline-block; background: #0f172a; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 8px; font-size: 14px; }
        a:hover { background: #1e293b; }
    </style>
</head>
<body>
    <div class="card">
        <p class="status">{{ $status }}</p>
        <p class="message">{{ $status >= 500 ? 'An unexpected error occurred.' : ($exception?->getMessage() ?? 'Something went wrong.') }}</p>
        <a href="{{ url('/') }}">Go to dashboard</a>
    </div>
</body>
</html>
