<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Help - Bennett Music</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        h1 { color: white; text-align: center; margin-bottom: 24px; font-size: 24px; }
        .topics { background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.2); }
        .topic { display: flex; align-items: center; gap: 14px; padding: 16px 20px; border-bottom: 1px solid #eee; text-decoration: none; color: #333; transition: background 0.15s; }
        .topic:last-child { border-bottom: none; }
        .topic:hover { background: #f5f5f5; }
        .topic-icon { font-size: 24px; width: 36px; text-align: center; }
        .topic-title { font-weight: 500; font-size: 16px; }
        .back-link { display: block; text-align: center; margin-top: 20px; color: white; text-decoration: none; opacity: 0.9; }
        .back-link:hover { opacity: 1; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Help</h1>
        <div class="topics">
            <?php foreach ($topics as $topic): ?>
            <a href="<?= htmlspecialchars($topic['url']) ?>" class="topic">
                <span class="topic-icon"><?= $topic['icon'] ?></span>
                <span class="topic-title"><?= htmlspecialchars($topic['title']) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <a href="<?= site_url('/') ?>" class="back-link">← Back to Player</a>
    </div>
</body>
</html>
