<?php
$page_title = 'Tools';
$page_icon = '🛠️';
$active_page = 'tools';

ob_start();
?>

<div class="tools-grid">
    <!-- Scan Library -->
    <div class="card tool-card">
        <div class="tool-icon">🔍</div>
        <h3>Scan Library</h3>
        <p>Scan your music directory for new songs, update metadata, and extract cover art.</p>
        <form method="post" action="<?= site_url('admin/scan_library') ?>" style="margin-top:16px;">
            <button type="submit" class="btn btn-primary" onclick="this.innerHTML='<span>Scanning...</span>';this.disabled=true;this.form.submit();">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
                Scan Now
            </button>
        </form>
        <?php if (!empty($last_scan)): ?>
        <div class="tool-meta">Last scan: <?= $last_scan ?></div>
        <?php endif; ?>
    </div>
    
    <!-- Upload Song -->
    <div class="card tool-card">
        <div class="tool-icon">📤</div>
        <h3>Upload Song</h3>
        <p>Upload MP3 files with staging preview. Review metadata before importing to library.</p>
        <a href="<?= site_url('admin/upload_song') ?>" class="btn btn-primary" style="margin-top:16px;display:inline-flex;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Go to Upload
        </a>
    </div>
    
    <!-- Generate PWA Icons -->
    <div class="card tool-card">
        <div class="tool-icon">📱</div>
        <h3>Generate PWA Icons</h3>
        <p>Create app icons for iOS and Android from the first album's cover art.</p>
        <form method="post" action="<?= site_url('admin/generate_icons') ?>" style="margin-top:16px;">
            <button type="submit" class="btn btn-secondary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                Generate Icons
            </button>
        </form>
    </div>
    
    <!-- Purge False Starts -->
    <div class="card tool-card">
        <div class="tool-icon">🧹</div>
        <h3>Purge False Starts</h3>
        <p>Remove play history entries where less than 5% of the song was played.</p>
        <form method="post" action="<?= site_url('admin/purge_false_starts') ?>" style="margin-top:16px;" onsubmit="return confirm('This will delete all plays with less than 5% completion. Continue?');">
            <button type="submit" class="btn btn-danger">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                Purge
            </button>
        </form>
        <?php if (isset($false_start_count)): ?>
        <div class="tool-meta"><?= $false_start_count ?> false starts found</div>
        <?php endif; ?>
    </div>
    
    <!-- Clear Cache -->
    <div class="card tool-card">
        <div class="tool-icon">💾</div>
        <h3>Clear API Cache</h3>
        <p>Clear cached API responses. Use if changes aren't appearing in the player.</p>
        <form method="post" action="<?= site_url('admin/clear_cache') ?>" style="margin-top:16px;">
            <button type="submit" class="btn btn-secondary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 12a9 9 0 11-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/></svg>
                Clear Cache
            </button>
        </form>
    </div>
    
    <!-- Diagnose -->
    <div class="card tool-card">
        <div class="tool-icon">🔬</div>
        <h3>Diagnostics</h3>
        <p>Run system diagnostics to check paths, database, and configuration.</p>
        <a href="<?= site_url('diagnose') ?>" class="btn btn-secondary" style="margin-top:16px;display:inline-flex;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            Run Diagnostics
        </a>
    </div>
</div>

<!-- Config Info -->
<div class="card" style="margin-top:24px;">
    <div class="card-header">
        <div class="card-title">⚙️ Current Configuration</div>
    </div>
    <div class="config-grid">
        <div class="config-item">
            <div class="config-label">Music Path</div>
            <div class="config-value"><code><?= htmlspecialchars($music_path ?? 'Not set') ?></code></div>
        </div>
        <div class="config-item">
            <div class="config-label">Cover Art Path</div>
            <div class="config-value"><code><?= htmlspecialchars($cover_art_path ?? 'Not set') ?></code></div>
        </div>
        <div class="config-item">
            <div class="config-label">CDN URL</div>
            <div class="config-value"><code><?= htmlspecialchars($cdn_url ?? 'Not set') ?></code></div>
        </div>
        <div class="config-item">
            <div class="config-label">Min Songs per Album</div>
            <div class="config-value"><?= $min_songs ?? 4 ?></div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

$extra_css = '
    .tools-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
    .tool-card { text-align: center; padding: 24px; }
    .tool-card h3 { margin: 12px 0 8px; font-size: 16px; }
    .tool-card p { font-size: 13px; color: #666; line-height: 1.5; }
    .tool-icon { font-size: 36px; }
    .tool-meta { font-size: 12px; color: #888; margin-top: 12px; }
    .config-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 16px; }
    .config-item { padding: 12px; background: #f8f9fa; border-radius: 8px; }
    .config-label { font-size: 12px; color: #666; margin-bottom: 4px; }
    .config-value { font-size: 13px; word-break: break-all; }
    .config-value code { background: #e8e8e8; padding: 2px 6px; border-radius: 4px; font-size: 12px; }
';

include(APPPATH . 'views/admin/layout.php');
?>
