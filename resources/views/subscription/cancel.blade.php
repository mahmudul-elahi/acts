<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment cancelled</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #faf8f2; color: #1f2937; display: flex; min-height: 100vh; align-items: center; justify-content: center; margin: 0; }
        .card { text-align: center; padding: 2rem; max-width: 22rem; }
        .badge { width: 64px; height: 64px; border-radius: 9999px; background: #e5e7eb; color: #6b7280; display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 1.25rem; }
        h1 { font-size: 1.4rem; margin: 0 0 .5rem; }
        p { color: #6b7280; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="card" data-checkout-status="cancel">
        <div class="badge">&times;</div>
        <h1>Payment cancelled</h1>
        <p>No charge was made. You can return to the app and try again whenever you&rsquo;re ready.</p>
    </div>
</body>
</html>
