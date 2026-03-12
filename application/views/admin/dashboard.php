<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admin - Bennett Music</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f5f5; color: #333; font-size: 14px; }
        
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; }
        .header h1 { font-size: 22px; margin-bottom: 15px; }
        .header-nav { display: flex; gap: 10px; flex-wrap: wrap; }
        .header-nav a { color: white; text-decoration: none; padding: 8px 14px; background: rgba(255,255,255,0.2); border-radius: 6px; font-size: 14px; }
        .header-nav a:hover { background: rgba(255,255,255,0.3); }
        
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        
        /* Problems/Status Banner */
        .status-banner { padding: 16px 20px; border-radius: 10px; margin-bottom: 20px; }
        .status-ok { background: #e8f5e9; border: 1px solid #a5d6a7; color: #2e7d32; }
        .status-problems { background: #fff8e1; border: 1px solid #ffe082; color: #f57f17; }
        .status-title { font-weight: 600; font-size: 15px; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
        .problem-list { font-size: 13px; }
        .problem-item { padding: 6px 0; border-bottom: 1px solid rgba(0,0,0,0.08); display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        .problem-item:last-child { border-bottom: none; }
        .problem-details { color: #666; font-size: 12px; margin-top: 2px; }
        .problem-action { font-size: 12px; color: #667eea; cursor: pointer; text-decoration: underline; white-space: nowrap; }
        .badge-error { background: #ffebee; color: #c62828; padding: 2px 8px; border-radius: 10px; font-size: 11px; }
        .badge-warning { background: #fff3e0; color: #ef6c00; padding: 2px 8px; border-radius: 10px; font-size: 11px; }
        .badge-info { background: #e3f2fd; color: #1565c0; padding: 2px 8px; border-radius: 10px; font-size: 11px; }
        
        /* Section styling */
        .section { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #eee; }
        .section-title { font-size: 18px; font-weight: 600; color: #333; display: flex; align-items: center; gap: 10px; }
        .section-title .icon { font-size: 22px; }
        
        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; }
        .stat-card { background: #f8f9fa; padding: 16px; border-radius: 10px; text-align: center; }
        .stat-card.highlight { background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%); border: 1px solid #667eea30; }
        .stat-num { font-size: 28px; font-weight: 700; color: #667eea; }
        .stat-num.success { color: #4caf50; }
        .stat-num.warning { color: #ff9800; }
        .stat-label { font-size: 12px; color: #666; margin-top: 4px; }
        
        /* This Week highlight */
        .week-stats { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; }
        .week-title { font-size: 14px; opacity: 0.9; margin-bottom: 12px; font-weight: 500; }
        .week-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 16px; }
        .week-stat { text-align: center; }
        .week-num { font-size: 32px; font-weight: 700; }
        .week-label { font-size: 11px; opacity: 0.8; margin-top: 2px; }
        .week-top { margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.2); font-size: 13px; }
        .week-top strong { font-weight: 600; }
        
        /* Songs Table */
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; color: #555; position: sticky; top: 0; }
        tr:hover { background: #fafafa; }
        .cover-thumb { width: 40px; height: 40px; border-radius: 6px; object-fit: cover; background: #eee; }
        .cover-missing { width: 40px; height: 40px; border-radius: 6px; background: #ffebee; display: flex; align-items: center; justify-content: center; font-size: 18px; }
        .song-title { font-weight: 500; }
        .song-album { color: #888; font-size: 12px; }
        .duration { font-family: monospace; color: #666; }
        .play-count { font-weight: 600; color: #667eea; }
        
        /* Albums Grid */
        .albums-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; }
        .album-card { background: #f8f9fa; border-radius: 10px; padding: 12px; display: flex; gap: 12px; align-items: center; }
        .album-cover { width: 60px; height: 60px; border-radius: 8px; object-fit: cover; background: #ddd; flex-shrink: 0; }
        .album-cover-missing { width: 60px; height: 60px; border-radius: 8px; background: #ffebee; display: flex; align-items: center; justify-content: center; font-size: 24px; flex-shrink: 0; }
        .album-info { flex: 1; min-width: 0; }
        .album-title { font-weight: 600; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .album-meta { font-size: 12px; color: #888; }
        
        /* Action Buttons */
        .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; font-size: 13px; font-weight: 500; border: none; cursor: pointer; }
        .btn:hover { background: #5a6fd6; }
        .btn-secondary { background: #f5f5f5; color: #333; }
        .btn-secondary:hover { background: #eee; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        
        /* Info Box */
        .info-box { background: #e3f2fd; border-radius: 8px; padding: 14px; font-size: 13px; color: #1565c0; margin-top: 16px; }
        .info-box strong { font-weight: 600; }
        .info-box code { background: rgba(0,0,0,0.1); padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 12px; }
        
        /* Config Display */
        .config-grid { display: grid; gap: 8px; font-size: 13px; }
        .config-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
        .config-row:last-child { border-bottom: none; }
        .config-label { color: #888; }
        .config-value { font-family: monospace; font-size: 12px; color: #333; max-width: 400px; overflow: hidden; text-overflow: ellipsis; }
        
        /* Two Column Layout */
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 800px) { .two-col { grid-template-columns: 1fr; } }

        /* Flash messages */
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎵 Bennett Music Admin</h1>
        <div class="header-nav">
            <a href="<?= site_url('admin') ?>">Dashboard</a>
            <a href="<?= site_url() ?>">Player</a>
            <a href="<?= site_url('admin/devices') ?>">Devices</a>
            <a href="<?= site_url('admin/songs') ?>">Songs</a>
            <a href="<?= site_url('auth/logout') ?>">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($this->session->flashdata('success')): ?>
            <div class="alert alert-success"><?= $this->session->flashdata('success') ?></div>
        <?php endif; ?>
        <?php if ($this->session->flashdata('error')): ?>
            <div class="alert alert-error"><?= $this->session->flashdata('error') ?></div>
        <?php endif; ?>
        
        <!-- ==================== PROBLEMS / STATUS ==================== -->
        <?php if (empty($problems)): ?>
        <div class="status-banner status-ok">
            <div class="status-title">✅ No Problems Found</div>
            <div style="font-size:13px;">All songs have covers, files are present, and play history is clean.</div>
        </div>
        <?php else: ?>
        <div class="status-banner status-problems">
            <div class="status-title">⚠️ <?= count($problems) ?> Issue<?= count($problems) > 1 ? 's' : '' ?> Found</div>
            <div class="problem-list">
                <?php foreach ($problems as $p): ?>
                <div class="problem-item">
                    <div>
                        <span class="badge-<?= $p['type'] ?>"><?= ucfirst($p['type']) ?></span>
                        <?= htmlspecialchars($p['message']) ?>
                        <?php if (!empty($p['details'])): ?>
                        <div class="problem-details"><?= htmlspecialchars($p['details']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($p['action']) && $p['action'] === 'purge'): ?>
                    <span class="problem-action" onclick="purgeFalseStarts()">Purge Now</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- ==================== SECTION 1: STATS ==================== -->
        <div class="week-stats">
            <div class="week-title">This Week</div>
            <div class="week-grid">
                <div class="week-stat">
                    <div class="week-num"><?= $week_plays ?></div>
                    <div class="week-label">Plays</div>
                </div>
                <div class="week-stat">
                    <div class="week-num"><?= $week_complete ?></div>
                    <div class="week-label">Complete</div>
                </div>
                <div class="week-stat">
                    <div class="week-num"><?php
                        $mins = floor($week_listen_time / 60);
                        echo $mins >= 60 ? floor($mins/60) . 'h' : $mins . 'm';
                    ?></div>
                    <div class="week-label">Listen Time</div>
                </div>
                <div class="week-stat">
                    <div class="week-num"><?= $week_devices ?></div>
                    <div class="week-label">Devices</div>
                </div>
            </div>
            <?php if ($week_top_song !== 'None'): ?>
            <div class="week-top"><strong>Top Song:</strong> <?= htmlspecialchars($week_top_song) ?></div>
            <?php endif; ?>
        </div>
        
        <div class="section">
            <div class="section-header">
                <div class="section-title"><span class="icon">📈</span> All-Time Play Stats</div>
            </div>
            <div class="stats-grid">
                <div class="stat-card highlight">
                    <div class="stat-num"><?= $total_plays ?></div>
                    <div class="stat-label">Total Plays</div>
                </div>
                <div class="stat-card">
                    <div class="stat-num success"><?= $complete_plays ?></div>
                    <div class="stat-label">Complete Plays</div>
                </div>
                <div class="stat-card">
                    <div class="stat-num"><?php
                        $mins = floor($total_listen_time / 60);
                        echo $mins >= 60 ? floor($mins/60) . 'h ' . ($mins%60) . 'm' : $mins . 'm';
                    ?></div>
                    <div class="stat-label">Total Listen Time</div>
                </div>
                <div class="stat-card">
                    <div class="stat-num"><?= $total_devices ?></div>
                    <div class="stat-label">Devices</div>
                </div>
            </div>
        </div>
        
        <div class="section">
            <div class="section-header">
                <div class="section-title"><span class="icon">📚</span> Library</div>
            </div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-num"><?= $total_songs ?></div>
                    <div class="stat-label">Songs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-num"><?= $total_albums ?></div>
                    <div class="stat-label">Albums</div>
                </div>
                <div class="stat-card">
                    <div class="stat-num"><?= $misc_songs ?></div>
                    <div class="stat-label">Misc Songs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-num"><?php
                        $hours = floor($total_duration / 3600);
                        $mins = floor(($total_duration % 3600) / 60);
                        echo $hours > 0 ? $hours . 'h ' . $mins . 'm' : $mins . 'm';
                    ?></div>
                    <div class="stat-label">Duration</div>
                </div>
            </div>
        </div>
        
        <!-- ==================== SECTION 2: SONGS ==================== -->
        <div class="section">
            <div class="section-header">
                <div class="section-title"><span class="icon">🎵</span> Songs (<?= count($all_songs) ?>)</div>
                <div class="actions">
                    <a href="<?= site_url('admin/upload_song') ?>" class="btn btn-sm btn-secondary">⬆️ Upload</a>
                    <a href="<?= site_url('admin/songs') ?>" class="btn btn-sm btn-secondary">📋 Manage</a>
                </div>
            </div>
            
            <div class="table-container" style="max-height: 400px; overflow-y: auto;">
                <table>
                    <thead>
                        <tr>
                            <th style="width:50px;">Cover</th>
                            <th>Title</th>
                            <th>Album</th>
                            <th style="width:70px;">Duration</th>
                            <th style="width:60px;">Plays</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_songs as $song): ?>
                        <tr>
                            <td>
                                <?php if (!empty($song->cover_filename)): ?>
                                <img src="<?= $cover_art_url ?>/<?= $song->cover_filename ?><?= !empty($song->file_hash) ? '?h=' . substr($song->file_hash, 0, 8) : '' ?>" class="cover-thumb" onerror="this.outerHTML='<div class=\'cover-missing\'>🎵</div>'">
                                <?php else: ?>
                                <div class="cover-missing">❌</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="song-title"><?= htmlspecialchars($song->title) ?></div>
                                <div class="song-album"><?= htmlspecialchars($song->artist ?: 'Unknown Artist') ?></div>
                            </td>
                            <td><?= htmlspecialchars($song->album_title ?: '—') ?></td>
                            <td class="duration"><?= $song->duration ? floor($song->duration/60) . ':' . str_pad($song->duration%60, 2, '0', STR_PAD_LEFT) : '—' ?></td>
                            <td class="play-count"><?= $song->play_count ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="info-box">
                <strong>📷 Song Covers:</strong> Embedded cover art is automatically extracted from audio files during library scan and saved to <code>/songs/imgs/songs/</code>. Songs without embedded art will show ❌. To fix, embed cover art in the audio file's ID3 tags and re-scan.
            </div>
        </div>
        
        <!-- Albums -->
        <div class="section">
            <div class="section-header">
                <div class="section-title"><span class="icon">💿</span> Albums (<?= count($albums) ?>)</div>
                <a href="<?= site_url('admin/albums') ?>" class="btn btn-sm btn-secondary">Manage Albums</a>
            </div>
            
            <div class="albums-grid">
                <?php foreach ($albums as $album): 
                    // Check if album has cover in imgs/albums folder
                    $has_cover = false;
                    $cover_file = null;
                    $album_normalized = strtolower(str_replace(['_', ' ', '-'], '', $album->title));
                    if (is_dir($imgs_dir)) {
                        $files = @scandir($imgs_dir);
                        if ($files) {
                            foreach ($files as $f) {
                                if ($f === '.' || $f === '..') continue;
                                $fn = strtolower(str_replace(['_', ' ', '-'], '', pathinfo($f, PATHINFO_FILENAME)));
                                if ($fn === $album_normalized) { $has_cover = true; $cover_file = $f; break; }
                            }
                        }
                    }
                ?>
                <div class="album-card">
                    <?php if ($has_cover): ?>
                    <img src="<?= $cover_art_url ?>/albums/<?= urlencode($cover_file) ?>" class="album-cover" onerror="this.outerHTML='<div class=\'album-cover-missing\'>💿</div>'">
                    <?php else: ?>
                    <div class="album-cover-missing">❌</div>
                    <?php endif; ?>
                    <div class="album-info">
                        <div class="album-title"><?= htmlspecialchars($album->title) ?></div>
                        <div class="album-meta"><?= $album->song_count ?> song<?= $album->song_count != 1 ? 's' : '' ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="info-box">
                <strong>📷 Album Covers:</strong> Place cover images in <code><?= htmlspecialchars($imgs_dir) ?>/</code> with filename matching album title exactly (e.g., <code>Colorado Snow.jpg</code> for album "Colorado Snow"). Supported formats: jpg, jpeg, png, webp, gif.
            </div>
        </div>
        
        <!-- ==================== SECTION 3: APP MANAGEMENT ==================== -->
        <div class="section">
            <div class="section-header">
                <div class="section-title"><span class="icon">⚙️</span> App Management</div>
            </div>
            
            <div class="two-col">
                <div>
                    <h3 style="font-size:14px; color:#666; margin-bottom:12px;">Quick Actions</h3>
                    <div class="actions" style="flex-direction:column;">
                        <a href="<?= site_url('admin/scan_library') ?>" class="btn">🔄 Scan Library</a>
                        <a href="<?= site_url('admin/devices') ?>" class="btn btn-secondary">📱 Manage Devices (<?= $total_devices ?>)</a>
                        <a href="<?= site_url('admin/generate_icons') ?>" class="btn btn-secondary">📲 Generate PWA Icons</a>
                        <?php if ($false_starts > 0): ?>
                        <button onclick="purgeFalseStarts()" class="btn btn-secondary" style="color:#c62828;">🗑️ Purge <?= $false_starts ?> False Starts</button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div>
                    <h3 style="font-size:14px; color:#666; margin-bottom:12px;">Configuration</h3>
                    <div class="config-grid">
                        <div class="config-row">
                            <span class="config-label">Music Path</span>
                            <span class="config-value"><?= htmlspecialchars($music_path) ?></span>
                        </div>
                        <div class="config-row">
                            <span class="config-label">CDN URL</span>
                            <span class="config-value"><?= htmlspecialchars($cdn_url) ?></span>
                        </div>
                        <div class="config-row">
                            <span class="config-label">Album Covers Folder</span>
                            <span class="config-value"><?= htmlspecialchars($imgs_dir) ?>/</span>
                        </div>
                        <div class="config-row">
                            <span class="config-label">Last Scan</span>
                            <span class="config-value"><?= htmlspecialchars($last_scan) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
    
    <script>
    function purgeFalseStarts() {
        if (!confirm('Remove ALL plays under 20 seconds across all devices?')) return;
        
        fetch('<?= site_url('admin/purge_false_starts') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Removed ' + data.removed + ' false start' + (data.removed !== 1 ? 's' : ''));
                location.reload();
            }
        });
    }
    </script>
</body>
</html>
