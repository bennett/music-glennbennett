<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?> - Bennett Music Help</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f5f5; min-height: 100vh; color: #333; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; }
        .header h1 { font-size: 22px; margin-bottom: 8px; }
        .header a { color: white; opacity: 0.9; text-decoration: none; font-size: 14px; }
        .header a:hover { opacity: 1; text-decoration: underline; }
        .content { max-width: 700px; margin: 0 auto; padding: 24px 20px; }
        .content h1 { font-size: 28px; margin-bottom: 16px; color: #333; }
        .content h2 { font-size: 20px; margin: 24px 0 12px; color: #444; }
        .content h3 { font-size: 16px; margin: 20px 0 8px; color: #555; }
        .content p { line-height: 1.7; margin-bottom: 16px; color: #444; }
        .content ul, .content ol { margin: 0 0 16px 24px; line-height: 1.7; }
        .content li { margin-bottom: 8px; }
        .content a { color: #667eea; text-decoration: none; }
        .content a:hover { text-decoration: underline; }
        .content strong { font-weight: 600; }
        .content em { font-style: italic; color: #666; }
        .content hr { border: none; border-top: 1px solid #ddd; margin: 24px 0; }
        .back-btn { display: inline-flex; align-items: center; gap: 6px; background: white; color: #667eea; padding: 10px 16px; border-radius: 8px; text-decoration: none; font-weight: 500; margin-top: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .back-btn:hover { background: #f0f0f0; text-decoration: none; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Bennett Music</h1>
        <a href="<?= site_url('help') ?>">← All Help Topics</a>
    </div>
    <div class="content">
        <?= $content ?>
        <a href="<?= site_url('help') ?>" class="back-btn">← Back to Help</a>
    </div>
</body>
</html>
