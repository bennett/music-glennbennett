<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Manage Albums - Music Player</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background: #f5f5f5; font-size: 14px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .header h1 { font-size: 24px; }
        .header-nav { display: flex; gap: 10px; flex-wrap: wrap; }
        .header a { color: white; text-decoration: none; padding: 8px 16px; background: rgba(255,255,255,0.2); border-radius: 5px; font-size: 14px; }
        .header a:hover { background: rgba(255,255,255,0.3); }
        .container { max-width: 1800px; margin: 0 auto; padding: 30px 20px; }
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px; }
        .page-title { font-size: 28px; color: #333; }
        .btn { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; font-size: 14px; font-weight: 500; border: none; cursor: pointer; }
        .btn:hover { background: #5a6fd6; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        
        .stats-bar { display: flex; gap: 30px; margin-bottom: 30px; padding: 20px; background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); flex-wrap: wrap; }
        .stat { text-align: center; min-width: 100px; }
        .stat-value { font-size: 24px; font-weight: 600; color: #667eea; }
        .stat-label { font-size: 12px; color: #888; }
        
        .album-section { background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 30px; overflow: hidden; }
        
        .album-header { display: flex; gap: 25px; padding: 25px; border-bottom: 1px solid #eee; align-items: flex-start; }
        .album-cover { width: 180px; height: 180px; border-radius: 10px; object-fit: cover; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); flex-shrink: 0; }
        .album-cover.no-cover { display: flex; align-items: center; justify-content: center; color: white; font-size: 60px; }
        
        .album-details { flex: 1; min-width: 0; }
        .album-title { font-size: 24px; font-weight: 700; color: #333; margin-bottom: 8px; }
        .album-artist { font-size: 16px; color: #666; margin-bottom: 12px; }
        
        .album-meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px 20px; font-size: 13px; color: #666; margin-bottom: 15px; }
        .album-meta-item { display: flex; align-items: center; gap: 8px; }
        .album-meta-item .label { color: #999; min-width: 80px; }
        .album-meta-item .value { color: #333; font-weight: 500; font-family: monospace; }
        .album-meta-item .value.text { font-family: inherit; }
        
        .album-description { font-size: 14px; color: #888; line-height: 1.5; margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 6px; }
        .album-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        
        .tracks-header { display: flex; align-items: center; padding: 10px 20px; background: #f8f9fa; border-bottom: 1px solid #eee; font-size: 11px; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: 0.5px; }
        .tracks-header > div { padding: 0 4px; }
        
        .col-num { width: 35px; text-align: center; }
        .col-cover { width: 45px; }
        .col-title { width: 160px; }
        .col-artist { width: 100px; }
        .col-filename { flex: 1; min-width: 120px; }
        .col-coverfile { width: 140px; }
        .col-duration { width: 50px; text-align: right; }
        .col-plays { width: 45px; text-align: right; }
        .col-hash { width: 80px; }
        .col-id { width: 40px; text-align: center; }
        .col-created { width: 85px; }
        .col-actions { width: 60px; text-align: right; }
        
        .track-row { display: flex; align-items: center; padding: 8px 20px; border-bottom: 1px solid #f0f0f0; font-size: 13px; transition: background 0.15s; }
        .track-row:last-child { border-bottom: none; }
        .track-row:hover { background: #f8f9fa; }
        .track-row > div { padding: 0 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        
        .track-num { color: #999; font-weight: 500; }
        .track-cover { width: 36px; height: 36px; border-radius: 4px; object-fit: cover; background: #eee; }
        .track-cover.no-cover { display: flex; align-items: center; justify-content: center; color: #ccc; font-size: 14px; }
        .track-title { font-weight: 500; color: #333; }
        .track-artist { color: #666; }
        .track-filename { font-family: monospace; font-size: 11px; color: #888; }
        .track-duration { color: #888; }
        .track-plays { color: #667eea; font-weight: 500; }
        .track-plays.zero { color: #ccc; }
        .track-hash { font-family: monospace; font-size: 10px; color: #aaa; }
        .track-id { font-family: monospace; font-size: 11px; color: #999; }
        .track-actions { display: flex; gap: 3px; justify-content: flex-end; }
        .track-actions a { color: #667eea; text-decoration: none; padding: 3px 6px; border-radius: 4px; font-size: 11px; }
        .track-actions a:hover { background: #667eea; color: white; }
        
        .empty-state { text-align: center; padding: 60px 20px; color: #888; }
        .empty-state h3 { font-size: 20px; margin-bottom: 10px; color: #666; }
        
        .tracks-container { max-height: 600px; overflow-y: auto; }
        
        code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; font-size: 11px; }
        
        @media (max-width: 1400px) {
            .col-hash, .col-created { display: none; }
        }
        @media (max-width: 1200px) {
            .col-coverfile, .col-artist { display: none; }
        }
        @media (max-width: 900px) {
            .col-filename { display: none; }
            .album-header { flex-direction: column; align-items: center; text-align: center; }
            .album-cover { width: 150px; height: 150px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📀 Manage Albums</h1>
        <div class="header-nav">
            <a href="<?= site_url('admin') ?>">Dashboard</a>
            <a href="<?= site_url('/') ?>">Player</a>
            <a href="<?= site_url('admin/devices') ?>">Devices</a>
            <a href="<?= site_url('admin/songs') ?>">Songs</a>
            <a href="<?= site_url('auth/logout') ?>">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="page-header">
            <h2 class="page-title">Albums (Full Detail View)</h2>
            <div style="display: flex; gap: 10px;">
                <a href="<?= site_url('admin/scan_library') ?>" class="btn">🔄 Scan Library</a>
            </div>
        </div>
        
        <?php if (!empty($albums)): ?>
        <?php 
            $total_songs = array_sum(array_column($albums, 'song_count'));
            $total_duration = array_sum(array_column($albums, 'total_duration'));
            $with_covers = count(array_filter($albums, function($a) { return !empty($a->cover_url); }));
            $total_plays = 0;
            foreach ($albums as $a) {
                if (!empty($a->songs)) {
                    foreach ($a->songs as $s) {
                        $total_plays += $s->play_count ?? 0;
                    }
                }
            }
        ?>
        <div class="stats-bar">
            <div class="stat">
                <div class="stat-value"><?= count($albums) ?></div>
                <div class="stat-label">Albums</div>
            </div>
            <div class="stat">
                <div class="stat-value"><?= $total_songs ?></div>
                <div class="stat-label">Total Songs</div>
            </div>
            <div class="stat">
                <div class="stat-value"><?= gmdate('G:i:s', $total_duration) ?></div>
                <div class="stat-label">Total Duration</div>
            </div>
            <div class="stat">
                <div class="stat-value"><?= $with_covers ?></div>
                <div class="stat-label">With Covers</div>
            </div>
            <div class="stat">
                <div class="stat-value"><?= number_format($total_plays) ?></div>
                <div class="stat-label">Total Plays</div>
            </div>
        </div>
        
        <?php foreach ($albums as $album): ?>
        <div class="album-section">
            <div class="album-header">
                <?php if (!empty($album->cover_url)): ?>
                    <img src="<?= htmlspecialchars($album->cover_url) ?>" class="album-cover" alt="<?= htmlspecialchars($album->title) ?>">
                <?php else: ?>
                    <div class="album-cover no-cover">🎵</div>
                <?php endif; ?>
                
                <div class="album-details">
                    <div class="album-title"><?= htmlspecialchars($album->title) ?></div>
                    <div class="album-artist"><?= htmlspecialchars($album->artist ?? 'Glenn L. Bennett') ?></div>
                    
                    <div class="album-meta-grid">
                        <div class="album-meta-item">
                            <span class="label">Album ID:</span>
                            <span class="value"><?= $album->id ?></span>
                        </div>
                        <div class="album-meta-item">
                            <span class="label">Year:</span>
                            <span class="value"><?= $album->year ?? '—' ?></span>
                        </div>
                        <div class="album-meta-item">
                            <span class="label">Songs:</span>
                            <span class="value"><?= $album->song_count ?></span>
                        </div>
                        <div class="album-meta-item">
                            <span class="label">Duration:</span>
                            <span class="value"><?= gmdate('H:i:s', $album->total_duration ?? 0) ?></span>
                        </div>
                        <div class="album-meta-item">
                            <span class="label">Total Plays:</span>
                            <span class="value"><?php 
                                $ap = 0;
                                if (!empty($album->songs)) foreach ($album->songs as $s) $ap += $s->play_count ?? 0;
                                echo number_format($ap);
                            ?></span>
                        </div>
                        <div class="album-meta-item">
                            <span class="label">Release:</span>
                            <span class="value"><?= $album->release_date ?? '—' ?></span>
                        </div>
                        <div class="album-meta-item">
                            <span class="label">Cover File:</span>
                            <span class="value" title="<?= htmlspecialchars($album->cover_filename ?? '') ?>"><?= $album->cover_filename ? basename($album->cover_filename) : '—' ?></span>
                        </div>
                        <div class="album-meta-item">
                            <span class="label">Created:</span>
                            <span class="value"><?= $album->created_at ?? '—' ?></span>
                        </div>
                        <div class="album-meta-item">
                            <span class="label">Updated:</span>
                            <span class="value"><?= $album->updated_at ?? '—' ?></span>
                        </div>
                    </div>
                    
                    <?php if (!empty($album->description)): ?>
                    <div class="album-description"><?= htmlspecialchars($album->description) ?></div>
                    <?php endif; ?>
                    
                    <div class="album-actions">
                        <a href="<?= site_url('admin/upload_cover?album=' . $album->id) ?>" class="btn btn-sm btn-secondary">📷 Upload Cover</a>
                        <a href="<?= site_url('?album=' . $album->id) ?>" class="btn btn-sm" target="_blank">▶️ Play</a>
                        <a href="<?= site_url('share/album/' . $album->id) ?>" class="btn btn-sm btn-secondary" target="_blank">🔗 Share</a>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($album->songs)): ?>
            <div class="tracks-header">
                <div class="col-num">#</div>
                <div class="col-cover">Img</div>
                <div class="col-title">Title</div>
                <div class="col-artist">Artist</div>
                <div class="col-filename">Filename (audio)</div>
                <div class="col-coverfile">Cover Filename</div>
                <div class="col-duration">Dur</div>
                <div class="col-plays">Plays</div>
                <div class="col-hash">File Hash</div>
                <div class="col-id">ID</div>
                <div class="col-created">Updated</div>
                <div class="col-actions">Act</div>
            </div>
            <div class="tracks-container">
                <?php foreach ($album->songs as $song): ?>
                <div class="track-row">
                    <div class="col-num">
                        <span class="track-num"><?= $song->track_number ?? '-' ?></span>
                    </div>
                    <div class="col-cover">
                        <?php if (!empty($song->cover_url)): ?>
                            <img src="<?= htmlspecialchars($song->cover_url) ?>" class="track-cover" alt="" title="<?= htmlspecialchars($song->cover_filename ?? '') ?>">
                        <?php elseif (!empty($album->cover_url)): ?>
                            <img src="<?= htmlspecialchars($album->cover_url) ?>" class="track-cover" alt="" style="opacity: 0.5;" title="Using album cover">
                        <?php else: ?>
                            <div class="track-cover no-cover">🎵</div>
                        <?php endif; ?>
                    </div>
                    <div class="col-title">
                        <span class="track-title" title="<?= htmlspecialchars($song->title) ?>"><?= htmlspecialchars($song->title) ?></span>
                    </div>
                    <div class="col-artist">
                        <span class="track-artist" title="<?= htmlspecialchars($song->artist ?? '') ?>"><?= htmlspecialchars($song->artist ?? '-') ?></span>
                    </div>
                    <div class="col-filename">
                        <span class="track-filename" title="<?= htmlspecialchars($song->filename) ?>"><?= htmlspecialchars($song->filename) ?></span>
                    </div>
                    <div class="col-coverfile">
                        <span class="track-filename" title="<?= htmlspecialchars($song->cover_filename ?? '') ?>"><?= $song->cover_filename ? htmlspecialchars(basename($song->cover_filename)) : '—' ?></span>
                    </div>
                    <div class="col-duration">
                        <span class="track-duration"><?= $song->duration ? gmdate('i:s', $song->duration) : '-' ?></span>
                    </div>
                    <div class="col-plays">
                        <span class="track-plays <?= ($song->play_count ?? 0) == 0 ? 'zero' : '' ?>"><?= $song->play_count ?? 0 ?></span>
                    </div>
                    <div class="col-hash">
                        <span class="track-hash" title="<?= htmlspecialchars($song->file_hash ?? '') ?>"><?= $song->file_hash ? substr($song->file_hash, 0, 8) . '…' : '-' ?></span>
                    </div>
                    <div class="col-id">
                        <span class="track-id"><?= $song->id ?></span>
                    </div>
                    <div class="col-created">
                        <span class="track-hash" title="<?= $song->updated_at ?? '' ?>"><?= $song->updated_at ? date('Y-m-d', strtotime($song->updated_at)) : '-' ?></span>
                    </div>
                    <div class="col-actions">
                        <div class="track-actions">
                            <a href="<?= site_url('?song=' . $song->id) ?>" title="Play" target="_blank">▶️</a>
                            <a href="<?= site_url('share/song/' . $song->id) ?>" title="Share" target="_blank">🔗</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        
        <?php else: ?>
        <div class="empty-state">
            <h3>No Albums Yet</h3>
            <p>Create your first album or run a library scan to import music.</p>
            <a href="<?= site_url('admin/scan_library') ?>" class="btn" style="margin-top:20px;">🔄 Scan Library</a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
