<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> — Bennett Music Docs</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Georgia, 'Times New Roman', serif;
            background: #fff;
            color: #374151;
            line-height: 1.7;
            font-size: 15px;
        }

        /* Header */
        .docs-header {
            background: #fff;
            padding: 14px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            border-bottom: 1px solid #e5e7eb;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .docs-header a {
            color: #7c3aed;
            text-decoration: none;
            font-size: 14px;
        }
        .docs-header a:hover { color: #6d28d9; }
        .docs-header h1 {
            font-size: 16px;
            font-weight: 600;
            color: #111827;
            flex: 1;
        }
        .md-link {
            font-size: 12px;
            color: #9ca3af;
            text-decoration: none;
            white-space: nowrap;
        }
        .md-link:hover { color: #7c3aed; }
        .menu-toggle {
            display: none;
            background: none;
            border: 1px solid #d1d5db;
            color: #374151;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }

        /* Layout */
        .docs-layout {
            display: flex;
            min-height: calc(100vh - 53px);
        }

        /* Sidebar */
        .docs-sidebar {
            width: 260px;
            min-width: 260px;
            background: #f9fafb;
            border-right: 1px solid #e5e7eb;
            padding: 20px 0;
            overflow-y: auto;
            position: sticky;
            top: 53px;
            height: calc(100vh - 53px);
        }
        .sidebar-section {
            margin-bottom: 12px;
        }
        .sidebar-section-title {
            padding: 10px 20px 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #9ca3af;
        }
        .sidebar-section.active .sidebar-section-title {
            color: #7c3aed;
        }
        .sidebar-link {
            display: block;
            padding: 7px 20px 7px 28px;
            color: #6b7280;
            text-decoration: none;
            font-size: 13.5px;
            transition: background 0.15s, color 0.15s;
        }
        .sidebar-link:hover {
            background: #f3f4f6;
            color: #111827;
        }
        .sidebar-link.active {
            color: #7c3aed;
            background: #ede9fe;
            font-weight: 600;
            border-right: 2px solid #7c3aed;
        }

        /* Content */
        .docs-content {
            flex: 1;
            padding: 40px 56px 80px;
            max-width: 860px;
            min-width: 0;
        }

        /* Markdown styles */
        .docs-content h1 {
            font-size: 30px;
            font-weight: 700;
            color: #111827;
            margin: 0 0 8px;
            letter-spacing: -0.02em;
        }
        .docs-content h1 + hr { margin-top: 16px; }
        .docs-content h2 {
            font-size: 21px;
            font-weight: 600;
            color: #1f2937;
            margin: 40px 0 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        .docs-content h3 {
            font-size: 17px;
            font-weight: 600;
            color: #374151;
            margin: 32px 0 12px;
        }
        .docs-content h4 {
            font-size: 15px;
            font-weight: 600;
            color: #4b5563;
            margin: 24px 0 8px;
        }
        .docs-content p {
            margin: 0 0 16px;
            color: #4b5563;
        }
        .docs-content a {
            color: #7c3aed;
            text-decoration: none;
        }
        .docs-content a:hover {
            text-decoration: underline;
            color: #6d28d9;
        }
        .docs-content strong {
            color: #1f2937;
            font-weight: 600;
        }
        .docs-content em {
            color: #6b7280;
        }
        .docs-content ul, .docs-content ol {
            margin: 0 0 20px 24px;
            color: #4b5563;
        }
        .docs-content li {
            margin-bottom: 6px;
            padding-left: 4px;
        }
        .docs-content li strong {
            color: #1f2937;
        }
        .docs-content hr {
            border: none;
            border-top: 1px solid #e5e7eb;
            margin: 36px 0;
        }

        /* Inline code */
        .docs-content code {
            background: #f3f0ff;
            border: 1px solid #e9e5f5;
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 13px;
            font-family: 'SF Mono', 'Fira Code', 'Consolas', monospace;
            color: #6d28d9;
        }

        /* Code blocks */
        .docs-content pre {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 18px 20px;
            overflow-x: auto;
            margin: 4px 0 20px;
            line-height: 1.5;
        }
        .docs-content pre code {
            background: none;
            border: none;
            padding: 0;
            color: #374151;
            font-size: 13px;
        }

        /* Tables */
        .docs-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 4px 0 20px;
            font-size: 13.5px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            overflow: hidden;
        }
        .docs-content thead th {
            text-align: left;
            padding: 10px 14px;
            background: #f9fafb;
            color: #374151;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 1px solid #e5e7eb;
        }
        .docs-content th {
            text-align: left;
            padding: 10px 14px;
            background: #f9fafb;
            color: #374151;
            font-weight: 600;
            border-bottom: 1px solid #e5e7eb;
        }
        .docs-content td {
            padding: 9px 14px;
            border-bottom: 1px solid #f3f4f6;
            color: #4b5563;
        }
        .docs-content tr:last-child td { border-bottom: none; }
        .docs-content tbody tr:hover { background: #f9fafb; }
        .docs-content td code {
            font-size: 12px;
            padding: 1px 5px;
        }

        /* Blockquotes */
        .docs-content blockquote {
            border-left: 3px solid #7c3aed;
            padding: 12px 20px;
            margin: 0 0 20px;
            background: #faf5ff;
            border-radius: 0 6px 6px 0;
        }
        .docs-content blockquote p {
            margin-bottom: 0;
        }

        /* Mobile */
        @media (max-width: 768px) {
            .menu-toggle { display: block; }
            .docs-sidebar {
                position: fixed;
                top: 53px;
                left: -280px;
                width: 280px;
                min-width: 280px;
                z-index: 90;
                transition: left 0.25s ease;
                box-shadow: none;
            }
            .docs-sidebar.open {
                left: 0;
                box-shadow: 4px 0 24px rgba(0,0,0,0.15);
            }
            .docs-content {
                padding: 24px 20px 64px;
            }
            .sidebar-overlay {
                display: none;
                position: fixed;
                inset: 53px 0 0 0;
                background: rgba(0,0,0,0.3);
                z-index: 89;
            }
            .sidebar-overlay.open { display: block; }
        }
    </style>
</head>
<body>

<header class="docs-header">
    <button class="menu-toggle" onclick="toggleSidebar()" aria-label="Toggle menu">&#9776; Menu</button>
    <a href="<?= site_url('admin') ?>">&#8592; Admin</a>
    <h1><?= htmlspecialchars($title) ?></h1>
    <span class="md-link"><?= htmlspecialchars($md_path) ?></span>
</header>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<div class="docs-layout">
    <nav class="docs-sidebar" id="sidebar">
        <?php foreach ($sidebar as $sec): ?>
        <div class="sidebar-section<?= $sec['active'] ? ' active' : '' ?>">
            <div class="sidebar-section-title"><?= htmlspecialchars($sec['label']) ?></div>
            <?php foreach ($sec['pages'] as $page): ?>
            <a href="<?= $page['url'] ?>" class="sidebar-link<?= $page['active'] ? ' active' : '' ?>"><?= htmlspecialchars($page['label']) ?></a>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </nav>

    <main class="docs-content">
        <?= $content ?>
    </main>
</div>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('open');
}
</script>

</body>
</html>
