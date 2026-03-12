<?php
$page_title = 'Library';
$page_icon = '🎵';
$active_page = 'library';

$header_actions = '
<a href="' . site_url('admin/scan_library') . '" class="btn btn-secondary btn-sm" onclick="this.innerHTML=\'Scanning...\';return true;">Scan</a>
<a href="' . site_url('admin/upload_song') . '" class="btn btn-primary btn-sm">Upload</a>';

// Helper: compute freshness background for recently updated songs
// Returns inline style string or empty. Linear fade over 5 days.
function song_freshness_style($updated_at) {
    if (empty($updated_at)) return '';
    $age_hours = (time() - strtotime($updated_at)) / 3600;
    $max_hours = 120; // 5 days
    if ($age_hours >= $max_hours) return '';
    $opacity = round((1 - ($age_hours / $max_hours)) * 0.3, 3);
    return "background:rgba(46,125,50,{$opacity});";
}

// Pre-compute lookup maps
$db_by_filename = [];
foreach ($songs as $s) {
    $db_by_filename[$s->filename] = $s;
}

// Count issues
$no_cover = array_filter($songs, function($s) {
    return empty($s->cover_filename) && empty($s->album_cover_filename) && !in_array($s->album_title ?? '', ['Misc', 'Unknown Album']);
});
$issue_count = count($files_not_in_db) + count($db_not_on_disk) + count($no_cover);

ob_start();
?>

<!-- Quick Stats -->
<div class="quick-row">
    <div class="quick-stat">
        <span class="quick-num"><?= count($albums) ?></span>
        <span class="quick-label">Albums</span>
    </div>
    <div class="quick-stat">
        <span class="quick-num"><?= count($songs) ?></span>
        <span class="quick-label">Songs</span>
    </div>
    <div class="quick-stat">
        <span class="quick-num"><?= count($audio_files) ?></span>
        <span class="quick-label">Files</span>
    </div>
    <div class="quick-stat <?= $issue_count > 0 ? 'has-issues' : '' ?>">
        <span class="quick-num"><?= $issue_count ?></span>
        <span class="quick-label">Issues</span>
    </div>
</div>

<!-- Tabs -->
<div class="tabs">
    <button class="tab active" onclick="showTab('albums')">Albums</button>
    <button class="tab" onclick="showTab('songs')">Songs (Detail)</button>
    <button class="tab" onclick="showTab('covers')">Cover Art</button>
    <?php if ($issue_count > 0): ?>
    <button class="tab tab-warn" onclick="showTab('issues')">Issues (<?= $issue_count ?>)</button>
    <?php endif; ?>
</div>

<!-- ========== ALBUMS TAB (DETAILED) ========== -->
<div id="tab-albums" class="tab-content active">
    
    <?php foreach ($albums as $a): 
        // Album model already populates cover_url properly
        $artist = !empty($a->artist) ? $a->artist : 'Glenn Bennett';
        
        // Get songs for this album
        $album_songs = array_filter($songs, function($s) use ($a) {
            return ($s->album_id ?? null) == $a->id;
        });
        
        // Check for issues (focus on MP3 file metadata problems)
        $issues = [];
        $warnings = [];
        
        // Check: Album has cover?
        if (empty($a->cover_url)) {
            $warnings[] = "No album cover";
        }
        
        // Check each song using file tags (the source of truth)
        $file_track_numbers = [];
        foreach ($album_songs as $s) {
            // Get file tags for this song
            $ft = isset($file_tags[$s->filename]) ? $file_tags[$s->filename] : [];
            $file_track = $ft['track'] ?? null;
            $file_album = $ft['album'] ?? null;
            
            // File has no track tag
            if (empty($file_track) && isset($file_tags[$s->filename])) {
                $warnings[] = "\"" . $s->title . "\" has no track# in MP3 file";
            } else if ($file_track) {
                $ftn = (int)explode('/', $file_track)[0];
                if (in_array($ftn, $file_track_numbers)) {
                    $warnings[] = "Duplicate file track #{$ftn}";
                }
                $file_track_numbers[] = $ftn;
            }
            
            // Album tag in file doesn't match this album
            if ($file_album && strtolower(trim($file_album)) != strtolower(trim($a->title))) {
                $issues[] = "\"" . $s->title . "\" - MP3 album tag \"$file_album\" ≠ \"" . $a->title . "\"";
            }
        }
        
        // Sequential track check
        if (count($file_track_numbers) > 0) {
            sort($file_track_numbers);
            $expected = range(1, count($file_track_numbers));
            if ($file_track_numbers != $expected) {
                $warnings[] = "Track numbers not sequential (got: " . implode(',', $file_track_numbers) . ")";
            }
        }
        
        $status_class = count($issues) > 0 ? 'status-error' : (count($warnings) > 0 ? 'status-warn' : 'status-ok');
    ?>
    <div class="album-detail-card <?= $status_class ?>">
        <div class="album-detail-header">
            <?php if (!empty($a->cover_url)): ?>
                <img src="<?= htmlspecialchars($a->cover_url) ?>" class="album-cover-lg">
            <?php else: ?>
                <div class="album-cover-lg album-cover-empty">🎵</div>
            <?php endif; ?>
            <div class="album-detail-info">
                <div class="album-detail-title"><?= htmlspecialchars($a->title) ?></div>
                <div class="album-detail-meta">
                    <span><strong>Artist:</strong> <?= htmlspecialchars($artist) ?></span>
                    <span><strong>Year:</strong> <?= $a->year ?? '<em class="muted">Not set</em>' ?></span>
                    <span><strong>Songs:</strong> <?= $a->song_count ?></span>
                    <span><strong>Album ID:</strong> <?= $a->id ?></span>
                </div>
                <div class="album-detail-meta">
                    <span><strong>Cover:</strong> <?= $a->cover_filename ? '<code>' . htmlspecialchars(basename($a->cover_filename)) . '</code>' : '<em class="muted">None</em>' ?></span>
                </div>
                
                <?php if (count($issues) > 0 || count($warnings) > 0): ?>
                <div class="album-issues">
                    <?php foreach ($issues as $issue): ?>
                        <div class="issue-badge error">❌ <?= htmlspecialchars($issue) ?></div>
                    <?php endforeach; ?>
                    <?php foreach ($warnings as $warn): ?>
                        <div class="issue-badge warn">⚠️ <?= htmlspecialchars($warn) ?></div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="album-issues">
                    <div class="issue-badge ok">✅ All checks passed</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Song list for this album -->
        <div class="album-songs-table">
            <table class="sortable" data-album="<?= $a->id ?>">
                <thead>
                    <tr>
                        <th data-sort="num">Row</th>
                        <th data-sort="num">File#</th>
                        <th data-sort="num">DB#</th>
                        <th data-sort="text">Title</th>
                        <th data-sort="text">Artist (MP3)</th>
                        <th data-sort="text">Filename</th>
                        <th>Cover</th>
                        <th data-sort="num">Dur</th>
                        <th>Issues</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Build array with file track info for sorting
                    $songs_with_file_track = [];
                    foreach ($album_songs as $s) {
                        $ft = isset($file_tags[$s->filename]) ? $file_tags[$s->filename] : [];
                        $file_track = $ft['track'] ?? null;
                        $file_track_num = $file_track ? (int)explode('/', $file_track)[0] : 999;
                        $s->_file_track = $file_track;
                        $s->_file_track_num = $file_track_num;
                        $s->_file_artist = $ft['artist'] ?? null;
                        $s->_file_album = $ft['album'] ?? null;
                        $songs_with_file_track[] = $s;
                    }
                    
                    // Sort by FILE track number (from MP3 ID3 tag)
                    usort($songs_with_file_track, function($a, $b) {
                        return $a->_file_track_num - $b->_file_track_num;
                    });
                    
                    $row_num = 0;
                    foreach ($songs_with_file_track as $s): 
                        $row_num++;
                        
                        $file_track = $s->_file_track;
                        $file_artist = $s->_file_artist;
                        $file_album = $s->_file_album;
                        
                        $song_issues = [];
                        
                        // Check: No track number in file
                        if (empty($file_track)) {
                            $song_issues[] = 'no-file-track';
                        }
                        
                        // Check: Album tag in file doesn't match this album
                        if ($file_album && strtolower(trim($file_album)) != strtolower(trim($a->title))) {
                            $song_issues[] = 'album-mismatch';
                        }
                        
                        // Check: File exists on disk
                        $on_disk = in_array($s->filename, $audio_files);
                        if (!$on_disk) {
                            $song_issues[] = 'MISSING-FILE';
                        }
                        
                        $row_class = count($song_issues) > 0 ? 'row-warn' : '';
                        // Escalate to error for critical issues
                        foreach ($song_issues as $issue) {
                            if ($issue === 'MISSING-FILE' || $issue === 'album-mismatch') {
                                $row_class = 'row-error';
                                break;
                            }
                        }
                    ?>
                    <tr class="<?= $row_class ?>" style="<?= song_freshness_style($s->updated_at ?? null) ?>" data-row="<?= $row_num ?>" data-filetrack="<?= $s->_file_track_num ?>" data-dbtrack="<?= $s->track_number ?? 999 ?>" data-duration="<?= $s->duration ?? 0 ?>">
                        <td class="center mono"><?= $row_num ?></td>
                        <td class="center <?= empty($file_track) ? 'muted' : '' ?>" title="From MP3 ID3 tag"><?= $file_track ?? '—' ?></td>
                        <td class="center <?= empty($s->track_number) ? 'muted' : '' ?>"><?= $s->track_number ?? '—' ?></td>
                        <td><strong><?= htmlspecialchars($s->title) ?></strong></td>
                        <td class="<?= ($file_artist && strtolower($file_artist) != strtolower($a->artist ?? '')) ? 'text-warn' : '' ?>" title="From MP3 ID3 tag"><?= htmlspecialchars($file_artist ?? $s->artist ?? '') ?: '<span class="muted">—</span>' ?></td>
                        <td><code class="small"><?= htmlspecialchars($s->filename) ?></code></td>
                        <td class="center"><?= $s->cover_filename ? '<span class="dot dot-green" title="Has cover"></span>' : '<span class="dot dot-orange" title="No cover"></span>' ?></td>
                        <td class="mono"><?= $s->duration ? gmdate('i:s', $s->duration) : '—' ?></td>
                        <td>
                            <?php if (count($song_issues) > 0): ?>
                                <span class="issue-tags"><?= implode(', ', $song_issues) ?></span>
                            <?php else: ?>
                                <span class="dot dot-green"></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>
    
    <!-- Misc songs (not in any album) - informational only -->
    <?php 
    $misc_songs = array_filter($songs, function($s) {
        return empty($s->album_id) || in_array($s->album_title, ['Misc', 'Unknown Album']);
    });
    if (count($misc_songs) > 0):
    ?>
    <div class="album-detail-card status-ok">
        <div class="album-detail-header">
            <div class="album-cover-lg album-cover-empty">📁</div>
            <div class="album-detail-info">
                <div class="album-detail-title">Miscellaneous</div>
                <div class="album-detail-meta">
                    <span><strong>Songs:</strong> <?= count($misc_songs) ?></span>
                </div>
                <div class="album-issues">
                    <div class="issue-badge ok">ℹ️ Songs not assigned to a specific album</div>
                </div>
            </div>
        </div>
        <div class="album-songs-table">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Artist (MP3)</th>
                        <th>Filename</th>
                        <th>Dur</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($misc_songs as $s): ?>
                    <tr style="<?= song_freshness_style($s->updated_at ?? null) ?>">
                        <td class="center"><?= $s->id ?></td>
                        <td><strong><?= htmlspecialchars($s->title) ?></strong></td>
                        <td><?= htmlspecialchars($s->artist ?? '') ?: '<span class="muted">—</span>' ?></td>
                        <td><code class="small"><?= htmlspecialchars($s->filename) ?></code></td>
                        <td class="mono"><?= $s->duration ? gmdate('i:s', $s->duration) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ========== SONGS TAB (DETAILED) ========== -->
<div id="tab-songs" class="tab-content">
    <input type="text" class="search-box" placeholder="🔍 Search songs..." oninput="filterSongs(this.value)">
    
    <div class="table-scroll">
        <table class="songs-table sortable">
            <thead>
                <tr>
                    <th class="col-id" data-sort="num">ID</th>
                    <th class="col-title" data-sort="text">Title</th>
                    <th class="col-artist" data-sort="text">Artist</th>
                    <th class="col-album" data-sort="text">Album</th>
                    <th class="col-track" data-sort="num">#</th>
                    <th class="col-filename" data-sort="text">Filename</th>
                    <th class="col-cover-file" data-sort="text">Cover File</th>
                    <th class="col-duration" data-sort="num">Dur</th>
                    <th class="col-plays" data-sort="num">Plays</th>
                    <th class="col-hash">File Hash</th>
                    <th class="col-created" data-sort="text">Created</th>
                    <th class="col-updated" data-sort="text">Updated</th>
                    <th class="col-status">Status</th>
                </tr>
            </thead>
            <tbody id="songs-tbody">
                <?php foreach ($songs as $song): 
                    $on_disk = in_array($song->filename, $audio_files);
                    $has_cover = !empty($song->cover_filename) || !empty($song->album_cover_filename);
                ?>
                <tr class="song-row" style="<?= song_freshness_style($song->updated_at ?? null) ?>" data-search="<?= strtolower($song->title . ' ' . ($song->artist ?? '') . ' ' . ($song->album_title ?? '') . ' ' . $song->filename) ?>">
                    <td class="col-id"><span class="mono"><?= $song->id ?></span></td>
                    <td class="col-title" title="<?= htmlspecialchars($song->title) ?>">
                        <strong><?= htmlspecialchars($song->title) ?></strong>
                    </td>
                    <td class="col-artist" title="<?= htmlspecialchars($song->artist ?? '') ?>">
                        <?= htmlspecialchars($song->artist ?? '—') ?>
                    </td>
                    <td class="col-album" title="<?= htmlspecialchars($song->album_title ?? '') ?>">
                        <?= $song->album_title ? htmlspecialchars($song->album_title) : '<span class="muted">Misc</span>' ?>
                    </td>
                    <td class="col-track"><?= $song->track_number ?? '—' ?></td>
                    <td class="col-filename" title="<?= htmlspecialchars($song->filename) ?>">
                        <span class="mono"><?= htmlspecialchars($song->filename) ?></span>
                    </td>
                    <td class="col-cover-file" title="<?= htmlspecialchars($song->cover_filename ?? '') ?>">
                        <span class="mono"><?= $song->cover_filename ? htmlspecialchars(basename($song->cover_filename)) : '<span class="muted">—</span>' ?></span>
                    </td>
                    <td class="col-duration">
                        <span class="mono"><?= $song->duration ? gmdate("i:s", $song->duration) : '—' ?></span>
                    </td>
                    <td class="col-plays">
                        <span class="plays <?= ($song->play_count ?? 0) == 0 ? 'zero' : '' ?>"><?= $song->play_count ?? 0 ?></span>
                    </td>
                    <td class="col-hash" title="<?= htmlspecialchars($song->file_hash ?? '') ?>">
                        <span class="mono"><?= $song->file_hash ? substr($song->file_hash, 0, 8) . '…' : '—' ?></span>
                    </td>
                    <td class="col-created" title="<?= $song->created_at ?? '' ?>">
                        <span class="mono"><?= $song->created_at ? date('Y-m-d', strtotime($song->created_at)) : '—' ?></span>
                    </td>
                    <td class="col-updated" title="<?= $song->updated_at ?? '' ?>">
                        <span class="mono"><?= $song->updated_at ? date('Y-m-d', strtotime($song->updated_at)) : '—' ?></span>
                    </td>
                    <td class="col-status">
                        <?php if (!$on_disk): ?>
                            <span class="dot dot-red" title="Missing file"></span>
                        <?php elseif (!$has_cover): ?>
                            <span class="dot dot-orange" title="No cover"></span>
                        <?php else: ?>
                            <span class="dot dot-green" title="OK"></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ========== COVER ART TAB ========== -->
<div id="tab-covers" class="tab-content">
    <div class="covers-grid">
        <?php
        $cover_images = [];
        $cover_titles = [];
        foreach ($songs as $ci => $s):
            $has_cover = !empty($s->cover_filename);
            if ($has_cover && $cover_art_url) {
                $bust = !empty($s->file_hash) ? '?h=' . substr($s->file_hash, 0, 8) : '';
                $img_url = $cover_art_url . '/' . $s->cover_filename . $bust;
            } else {
                $img_url = '';
            }
            $cover_images[] = $img_url;
            $cover_titles[] = $s->title;
        ?>
        <div class="cover-card <?= $has_cover ? '' : 'no-cover' ?>" title="<?= htmlspecialchars($s->title) ?>" <?= $img_url ? 'onclick="openLightbox(' . $ci . ')"' : '' ?>>
            <?php if ($img_url): ?>
                <img src="<?= htmlspecialchars($img_url) ?>" class="cover-img" alt="" loading="lazy" onerror="this.parentElement.classList.add('no-cover');this.removeAttribute('onclick');this.outerHTML='<div class=\'cover-placeholder\'>?</div>'">
            <?php else: ?>
                <div class="cover-placeholder">?</div>
            <?php endif; ?>
            <div class="cover-label"><?= htmlspecialchars($s->title) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Lightbox (reusable pattern for image grids) -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
    <img id="lightboxImg" src="" alt="Full size" onclick="event.stopPropagation()">
    <div class="lb-nav lb-prev" onclick="event.stopPropagation(); navLightbox(-1)">&#8249;</div>
    <div class="lb-nav lb-next" onclick="event.stopPropagation(); navLightbox(1)">&#8250;</div>
    <div class="lb-caption" id="lbCaption"></div>
    <div class="lb-counter" id="lbCounter"></div>
</div>

<!-- ========== ISSUES TAB ========== -->
<?php if ($issue_count > 0): ?>
<div id="tab-issues" class="tab-content">
    <?php if (count($files_not_in_db) > 0): ?>
    <div class="issue-card">
        <div class="issue-header warn">
            <span>📁 New Files (<?= count($files_not_in_db) ?>)</span>
            <a href="<?= site_url('admin/scan_library') ?>" class="btn btn-sm btn-primary">Scan</a>
        </div>
        <div class="issue-list">
            <?php foreach (array_slice($files_not_in_db, 0, 10) as $file): ?>
            <div class="issue-item"><code><?= htmlspecialchars($file) ?></code></div>
            <?php endforeach; ?>
            <?php if (count($files_not_in_db) > 10): ?>
            <div class="issue-more">+<?= count($files_not_in_db) - 10 ?> more</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (count($db_not_on_disk) > 0): ?>
    <div class="issue-card">
        <div class="issue-header err">
            <span>❌ Missing Files (<?= count($db_not_on_disk) ?>)</span>
            <button class="btn btn-sm btn-danger" onclick="cleanOrphans()">Clean Up</button>
        </div>
        <div class="issue-list">
            <?php foreach (array_slice($db_not_on_disk, 0, 10) as $file): ?>
            <div class="issue-item"><code><?= htmlspecialchars($file) ?></code></div>
            <?php endforeach; ?>
            <?php if (count($db_not_on_disk) > 10): ?>
            <div class="issue-more">+<?= count($db_not_on_disk) - 10 ?> more</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (count($no_cover) > 0): ?>
    <div class="issue-card">
        <div class="issue-header warn">
            <span>🖼️ No Cover Art (<?= count($no_cover) ?>)</span>
        </div>
        <div class="issue-list">
            <?php foreach (array_slice($no_cover, 0, 10) as $s): ?>
            <div class="issue-item"><?= htmlspecialchars($s->title) ?> <span class="muted">— <?= htmlspecialchars($s->album_title ?? 'Misc') ?></span></div>
            <?php endforeach; ?>
            <?php if (count($no_cover) > 10): ?>
            <div class="issue-more">+<?= count($no_cover) - 10 ?> more</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();

$extra_css = '
    .quick-row { display: flex; background: white; border-radius: 12px; padding: 12px 8px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    .quick-stat { flex: 1; text-align: center; border-right: 1px solid #eee; }
    .quick-stat:last-child { border-right: none; }
    .quick-stat.has-issues .quick-num { color: #ff9800; }
    .quick-num { font-size: 20px; font-weight: 700; color: #667eea; display: block; }
    .quick-label { font-size: 11px; color: #888; }
    
    .tabs { display: flex; gap: 4px; margin-bottom: 16px; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .tab { padding: 8px 16px; background: white; border: none; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 500; color: #666; box-shadow: 0 1px 3px rgba(0,0,0,0.08); white-space: nowrap; }
    .tab:hover { background: #f0f0f0; }
    .tab.active { background: #667eea; color: white; }
    .tab-warn { color: #ff9800; }
    .tab-warn.active { background: #ff9800; color: white; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    
    /* Album detail cards */
    .album-detail-card { background: white; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); overflow: hidden; border-left: 4px solid #4caf50; }
    .album-detail-card.status-error { border-left-color: #f44336; }
    .album-detail-card.status-warn { border-left-color: #ff9800; }
    .album-detail-card.status-ok { border-left-color: #4caf50; }
    
    .album-detail-header { display: flex; gap: 20px; padding: 20px; border-bottom: 1px solid #f0f0f0; }
    .album-cover-lg { width: 100px; height: 100px; border-radius: 8px; object-fit: cover; flex-shrink: 0; }
    .album-cover-empty { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; font-size: 36px; color: white; }
    
    .album-detail-info { flex: 1; min-width: 0; }
    .album-detail-title { font-size: 20px; font-weight: 700; margin-bottom: 8px; }
    .album-detail-meta { display: flex; flex-wrap: wrap; gap: 15px; font-size: 13px; color: #666; margin-bottom: 10px; }
    .album-detail-meta span { display: inline-block; }
    .album-detail-meta strong { color: #333; }
    .album-detail-meta code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-size: 11px; }
    
    .album-issues { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
    .issue-badge { padding: 4px 10px; border-radius: 4px; font-size: 12px; }
    .issue-badge.error { background: #ffebee; color: #c62828; }
    .issue-badge.warn { background: #fff8e1; color: #f57f17; }
    .issue-badge.ok { background: #e8f5e9; color: #2e7d32; }
    
    .album-songs-table { overflow-x: auto; }
    .album-songs-table table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .album-songs-table th { background: #f8f9fa; padding: 8px 10px; text-align: left; font-weight: 600; color: #666; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
    .album-songs-table th[data-sort] { cursor: pointer; user-select: none; }
    .album-songs-table th[data-sort]:hover { background: #e9ecef; }
    .album-songs-table th[data-sort]::after { content: " ⇅"; opacity: 0.3; }
    .album-songs-table th.sort-asc::after { content: " ↑"; opacity: 1; }
    .album-songs-table th.sort-desc::after { content: " ↓"; opacity: 1; }
    .album-songs-table th { background: #f8f9fa; padding: 8px 10px; text-align: left; font-weight: 600; color: #666; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
    .album-songs-table td { padding: 8px 10px; border-bottom: 1px solid #f5f5f5; }
    .album-songs-table tr:last-child td { border-bottom: none; }
    .album-songs-table tr:hover { background: #fafafa; }
    .album-songs-table tr.row-warn { background: #fffde7; }
    .album-songs-table tr.row-warn:hover { background: #fff9c4; }
    .album-songs-table .center { text-align: center; }
    .album-songs-table code { background: #f5f5f5; padding: 2px 5px; border-radius: 3px; }
    .album-songs-table .small { font-size: 10px; }
    .album-songs-table .mono { font-family: "SF Mono", Monaco, monospace; }
    
    .issue-tags { font-size: 10px; color: #f57f17; background: #fff8e1; padding: 2px 6px; border-radius: 3px; }
    
    .text-warn { color: #f57f17 !important; font-weight: 500; }
    .text-error { color: #c62828 !important; font-weight: 600; }
    .row-error { background: #ffebee !important; }
    .row-error:hover { background: #ffcdd2 !important; }
    
    /* Legacy album row style (keeping for reference) */
    .album-row { display: flex; align-items: center; gap: 12px; padding: 10px 14px; background: white; border-radius: 10px; margin-bottom: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
    .album-cover { width: 48px; height: 48px; border-radius: 8px; object-fit: cover; flex-shrink: 0; }
    .album-info { flex: 1; min-width: 0; }
    .album-title { font-size: 14px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .album-meta { font-size: 12px; color: #888; margin-top: 2px; }
    
    .search-box { width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; margin-bottom: 12px; }
    .search-box:focus { outline: none; border-color: #667eea; }
    
    /* Songs table styles */
    .table-scroll { overflow-x: auto; background: white; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
    .songs-table { width: 100%; border-collapse: collapse; font-size: 12px; min-width: 1200px; }
    .songs-table th { background: #f8f9fa; padding: 10px 8px; text-align: left; font-weight: 600; color: #666; text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px; border-bottom: 2px solid #eee; position: sticky; top: 0; }
    .songs-table th[data-sort] { cursor: pointer; user-select: none; }
    .songs-table th[data-sort]:hover { background: #e9ecef; }
    .songs-table th[data-sort]::after { content: " ⇅"; opacity: 0.3; }
    .songs-table th.sort-asc::after { content: " ↑"; opacity: 1; }
    .songs-table th.sort-desc::after { content: " ↓"; opacity: 1; }
    .songs-table td { padding: 8px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .songs-table tr:hover { background: #f8f9fa; }
    
    .col-id { width: 45px; }
    .col-title { min-width: 150px; }
    .col-artist { width: 100px; }
    .col-album { width: 120px; }
    .col-track { width: 35px; text-align: center; }
    .col-filename { min-width: 150px; }
    .col-cover-file { width: 120px; }
    .col-duration { width: 50px; }
    .col-plays { width: 45px; text-align: center; }
    .col-hash { width: 80px; }
    .col-created { width: 85px; }
    .col-updated { width: 85px; }
    .col-status { width: 40px; text-align: center; }
    
    .mono { font-family: "SF Mono", Monaco, monospace; font-size: 11px; }
    .plays { color: #667eea; font-weight: 600; }
    .plays.zero { color: #ccc; }
    .muted { color: #aaa; }
    
    /* Cover art grid */
    .covers-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; }
    .cover-card { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.08); text-align: center; cursor: pointer; transition: transform 0.15s; }
    .cover-card:hover { transform: scale(1.03); }
    .cover-card.no-cover { border: 2px dashed #ff9800; cursor: default; }
    .cover-card.no-cover:hover { transform: none; }
    .cover-img { width: 100%; aspect-ratio: 1; object-fit: cover; display: block; }
    .cover-placeholder { width: 100%; aspect-ratio: 1; display: flex; align-items: center; justify-content: center; background: #f5f5f5; color: #ccc; font-size: 28px; font-weight: 700; }
    .cover-card.no-cover .cover-placeholder { background: #fff8e1; color: #ff9800; }
    .cover-label { padding: 6px 4px; font-size: 11px; font-weight: 500; color: #555; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    /* Lightbox — reusable pattern for any image grid */
    .lightbox { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.9); z-index: 1000; align-items: center; justify-content: center; padding: 20px; cursor: zoom-out; }
    .lightbox.show { display: flex; }
    .lightbox img { max-width: 90%; max-height: 80vh; border-radius: 8px; box-shadow: 0 4px 30px rgba(0,0,0,0.5); cursor: default; }
    .lb-nav { position: fixed; top: 50%; transform: translateY(-50%); color: white; font-size: 60px; cursor: pointer; padding: 20px; opacity: 0.6; transition: opacity 0.2s; user-select: none; z-index: 1001; }
    .lb-nav:hover { opacity: 1; }
    .lb-prev { left: 10px; }
    .lb-next { right: 10px; }
    .lb-caption { position: fixed; top: 20px; left: 50%; transform: translateX(-50%); color: white; font-size: 16px; font-weight: 600; z-index: 1001; text-shadow: 0 1px 4px rgba(0,0,0,0.5); }
    .lb-counter { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); color: rgba(255,255,255,0.5); font-size: 14px; z-index: 1001; }
    
    .dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; display: inline-block; }
    .dot-green { background: #4caf50; }
    .dot-orange { background: #ff9800; }
    .dot-red { background: #f44336; }
    
    .btn-icon { background: none; border: none; cursor: pointer; font-size: 14px; opacity: 0.5; padding: 4px; }
    .btn-icon:hover { opacity: 1; }
    
    .issue-card { background: white; border-radius: 10px; margin-bottom: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
    .issue-header { display: flex; justify-content: space-between; align-items: center; padding: 12px 14px; font-weight: 500; font-size: 13px; }
    .issue-header.warn { background: #fff8e1; color: #f57f17; }
    .issue-header.err { background: #ffebee; color: #c62828; }
    .issue-list { padding: 8px 14px; }
    .issue-item { padding: 6px 0; font-size: 12px; border-bottom: 1px solid #f5f5f5; }
    .issue-item:last-child { border-bottom: none; }
    .issue-item code { font-size: 11px; color: #555; }
    .issue-more { text-align: center; padding: 8px; font-size: 12px; color: #888; }
    
    .btn-sm { padding: 6px 12px; font-size: 12px; }
    
    @media (max-width: 768px) {
        .album-detail-header { flex-direction: column; align-items: center; text-align: center; }
        .album-detail-meta { justify-content: center; }
        .album-issues { justify-content: center; }
    }
    @media (max-width: 500px) {
        .quick-num { font-size: 18px; }
        .album-cover { width: 40px; height: 40px; }
        .album-title { font-size: 13px; }
    }
';

$delete_url = site_url("admin/delete_file");
$clean_url = site_url("admin/clean_orphans");

// Lightbox data for cover art grid (JSON-encoded for JS)
$lb_images_json = json_encode(array_values($cover_images));
$lb_titles_json = json_encode(array_values($cover_titles));

$extra_js = <<<JAVASCRIPT
<script>
// Lightbox — reusable pattern for image grids
var lbImages = {$lb_images_json};
var lbTitles = {$lb_titles_json};
var lbIndex = 0;

function openLightbox(idx) {
    if (!lbImages[idx]) return;
    lbIndex = idx;
    document.getElementById("lightboxImg").src = lbImages[idx];
    document.getElementById("lbCaption").textContent = lbTitles[idx] || "";
    document.getElementById("lbCounter").textContent = (idx + 1) + " / " + lbImages.length;
    document.getElementById("lightbox").classList.add("show");
}

function closeLightbox() {
    document.getElementById("lightbox").classList.remove("show");
}

function navLightbox(dir) {
    // Skip entries with no image
    var start = lbIndex;
    do {
        lbIndex = (lbIndex + dir + lbImages.length) % lbImages.length;
    } while (!lbImages[lbIndex] && lbIndex !== start);
    if (!lbImages[lbIndex]) return;
    document.getElementById("lightboxImg").src = lbImages[lbIndex];
    document.getElementById("lbCaption").textContent = lbTitles[lbIndex] || "";
    document.getElementById("lbCounter").textContent = (lbIndex + 1) + " / " + lbImages.length;
}

document.addEventListener("keydown", function(e) {
    var lb = document.getElementById("lightbox");
    if (!lb || !lb.classList.contains("show")) return;
    if (e.key === "ArrowRight") { e.preventDefault(); navLightbox(1); }
    else if (e.key === "ArrowLeft") { e.preventDefault(); navLightbox(-1); }
    else if (e.key === "Escape") { closeLightbox(); }
});

function showTab(name) {
    document.querySelectorAll(".tab").forEach(function(t) { t.classList.remove("active"); });
    document.querySelectorAll(".tab-content").forEach(function(t) { t.classList.remove("active"); });
    var tabs = document.querySelectorAll(".tab");
    for (var i = 0; i < tabs.length; i++) {
        if (tabs[i].getAttribute("onclick").indexOf(name) !== -1) {
            tabs[i].classList.add("active");
            break;
        }
    }
    document.getElementById("tab-" + name).classList.add("active");
}

function filterSongs(q) {
    q = q.toLowerCase();
    document.querySelectorAll("#songs-tbody .song-row").forEach(function(row) {
        row.style.display = row.dataset.search.indexOf(q) >= 0 ? "" : "none";
    });
}

function deleteFile(filename) {
    if (!confirm("Delete file: " + filename + "?")) return;
    fetch("{$delete_url}", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "filename=" + encodeURIComponent(filename)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) location.reload();
        else alert(data.message || "Failed");
    });
}

function cleanOrphans() {
    if (!confirm("Remove all missing file entries from database?")) return;
    fetch("{$clean_url}", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" }
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) location.reload();
        else alert(data.message || "Failed");
    });
}

// Sortable tables
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.sortable th[data-sort]').forEach(function(th) {
        th.addEventListener('click', function() {
            var table = th.closest('table');
            var tbody = table.querySelector('tbody');
            var rows = Array.from(tbody.querySelectorAll('tr'));
            var colIndex = Array.from(th.parentNode.children).indexOf(th);
            var sortType = th.dataset.sort;
            var isAsc = th.classList.contains('sort-asc');
            
            // Clear other sort indicators
            table.querySelectorAll('th').forEach(function(h) {
                h.classList.remove('sort-asc', 'sort-desc');
            });
            
            // Set new sort direction
            th.classList.add(isAsc ? 'sort-desc' : 'sort-asc');
            var direction = isAsc ? -1 : 1;
            
            rows.sort(function(a, b) {
                var aVal = a.children[colIndex].textContent.trim();
                var bVal = b.children[colIndex].textContent.trim();
                
                // Handle empty/dash values
                if (aVal === '—' || aVal === '') aVal = sortType === 'num' ? '999999' : 'zzzzz';
                if (bVal === '—' || bVal === '') bVal = sortType === 'num' ? '999999' : 'zzzzz';
                
                if (sortType === 'num') {
                    // Extract number from string like "3/12" or "3:45"
                    var aNum = parseFloat(aVal.split(/[\/:]/).shift()) || 999999;
                    var bNum = parseFloat(bVal.split(/[\/:]/).shift()) || 999999;
                    return (aNum - bNum) * direction;
                } else {
                    return aVal.localeCompare(bVal) * direction;
                }
            });
            
            rows.forEach(function(row) {
                tbody.appendChild(row);
            });
        });
    });
});
</script>
JAVASCRIPT;

include(APPPATH . 'views/admin/layout.php');
?>
