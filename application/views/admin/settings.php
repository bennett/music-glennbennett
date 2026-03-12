<?php
$page_title = 'Settings';
$page_icon = '⚙️';
$active_page = 'settings';

ob_start();
?>

<div class="card">
    <div class="card-header">
        <div class="card-title">Music Configuration</div>
    </div>
    <p style="color:#666;font-size:13px;margin-bottom:20px;">
        These settings are defined in <code>application/config/music_config.php</code>. 
        To change them, edit the config file directly on the server.
    </p>
    
    <div class="settings-list">
        <div class="setting-item">
            <div class="setting-label">Music Origin Path</div>
            <div class="setting-value"><code><?= htmlspecialchars($music_path ?: 'Not set') ?></code></div>
            <div class="setting-desc">Local filesystem path where MP3 files are stored</div>
        </div>
        
        <div class="setting-item">
            <div class="setting-label">Music CDN URL</div>
            <div class="setting-value"><code><?= htmlspecialchars($music_cdn_url ?: 'Not set') ?></code></div>
            <div class="setting-desc">CDN URL for streaming audio files (Bunny CDN or similar)</div>
        </div>
        
        <div class="setting-item">
            <div class="setting-label">Cover Art Path</div>
            <div class="setting-value"><code><?= htmlspecialchars($cover_art_path ?: 'Not set') ?></code></div>
            <div class="setting-desc">Local path for storing extracted cover art</div>
        </div>
        
        <div class="setting-item">
            <div class="setting-label">Cover Art URL</div>
            <div class="setting-value"><code><?= htmlspecialchars($cover_art_url ?: 'Not set') ?></code></div>
            <div class="setting-desc">CDN URL for serving cover images</div>
        </div>
        
        <div class="setting-item">
            <div class="setting-label">Min Songs Per Album</div>
            <div class="setting-value"><?= $min_songs_per_album ?></div>
            <div class="setting-desc">Albums with fewer songs go to "Misc" collection</div>
        </div>
        
        <div class="setting-item">
            <div class="setting-label">Excluded Directories</div>
            <div class="setting-value"><?= !empty($exclude_directories) ? implode(', ', $exclude_directories) : 'None' ?></div>
            <div class="setting-desc">Directories skipped during library scan</div>
        </div>
        
        <div class="setting-item">
            <div class="setting-label">Admin User ID</div>
            <div class="setting-value"><?= $admin_user_id ?></div>
            <div class="setting-desc">Plays from this user ID are not counted in statistics</div>
        </div>
        
        <div class="setting-item">
            <div class="setting-label">Default Artist</div>
            <div class="setting-value"><?= htmlspecialchars($default_artist ?: 'Not set') ?></div>
            <div class="setting-desc">Artist name shown in player header</div>
        </div>
        
        <div class="setting-item">
            <div class="setting-label">Site Title</div>
            <div class="setting-value"><?= htmlspecialchars($site_title ?: 'Not set') ?></div>
            <div class="setting-desc">Title shown in browser tab</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">File Structure</div>
    </div>
    <div class="file-tree">
        <div class="tree-item"><code>/songs/</code> — MP3 files</div>
        <div class="tree-item" style="padding-left:24px;"><code>/songs/imgs/albums/</code> — Album cover images</div>
        <div class="tree-item" style="padding-left:24px;"><code>/songs/imgs/songs/</code> — Extracted song cover art</div>
    </div>
</div>

<?php
$content = ob_get_clean();

$extra_css = '
    .settings-list { }
    .setting-item { padding: 16px 0; border-bottom: 1px solid #eee; }
    .setting-item:last-child { border-bottom: none; }
    .setting-label { font-weight: 600; font-size: 14px; margin-bottom: 4px; }
    .setting-value { font-size: 14px; margin-bottom: 4px; }
    .setting-value code { background: #f0f0f0; padding: 4px 8px; border-radius: 4px; font-size: 12px; word-break: break-all; }
    .setting-desc { font-size: 12px; color: #888; }
    .file-tree { font-size: 13px; }
    .tree-item { padding: 8px 0; }
    .tree-item code { background: #f0f0f0; padding: 2px 6px; border-radius: 4px; }
';

include(APPPATH . 'views/admin/layout.php');
?>
