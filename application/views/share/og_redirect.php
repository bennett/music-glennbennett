<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="music.song">
    <meta property="og:title" content="<?= htmlspecialchars($og_title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($og_description) ?>">
    <meta property="og:image" content="<?= $og_image ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:url" content="<?= $og_url ?>">
    <meta property="og:site_name" content="Glenn Bennett Music">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($og_title) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($og_description) ?>">
    <meta name="twitter:image" content="<?= $og_image ?>">
    
    <!-- Standard meta -->
    <title><?= htmlspecialchars($og_title) ?></title>
    <meta name="description" content="<?= htmlspecialchars($og_description) ?>">
    
    <!-- Redirect for humans (bots read meta tags, humans get redirected) -->
    <meta http-equiv="refresh" content="0;url=<?= $redirect_url ?>">
    
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: white;
        }
        .loading {
            text-align: center;
        }
        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 16px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        a {
            color: #60a5fa;
        }
    </style>
</head>
<body>
    <div class="loading">
        <div class="spinner"></div>
        <p>Loading...</p>
        <p><a href="<?= $redirect_url ?>">Click here</a> if not redirected</p>
    </div>
    
    <script>
        // Immediate redirect for JavaScript-enabled browsers
        window.location.href = <?= json_encode($redirect_url) ?>;
    </script>
</body>
</html>
