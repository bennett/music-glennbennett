<?php
$page_title = 'Library';
$page_icon = '🎵';
$active_page = 'library';

$header_actions = '
<a href="' . site_url('admin/scan_library') . '" class="btn btn-secondary btn-sm" onclick="this.innerHTML=\'Scanning...\';return true;">Scan</a>
<a href="' . site_url('admin/upload_song') . '" class="btn btn-primary btn-sm">Upload</a>';

// Pre-compute lookup maps
$db_by_filename = [];
foreach ($songs as $s) {
    $db_by_filename[$s->filename] = $s;
}

// Count issues
$no_cover = array_filter($songs, function($s) {
    return empty($s->cover_filename) && empty($s->album_cover_filename) && ($s->album_title ?? '') !== 'Unknown Album';
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
    <button class="tab" onclick="showTab('songs')">Songs</button>
    <button class="tab" onclick="showTab('files')">Files</button>
    <?php if ($issue_count > 0): ?>
    <button class="tab tab-warn" onclick="showTab('issues')">Issues (<?= $issue_count ?>)</button>
    <?php endif; ?>
</div>

<!-- ========== ALBUMS TAB ========== -->
<div id="tab-albums" class="tab-content active">
    <?php foreach ($albums as $a): 
        // Try cover_url first, then build from filename
        $a_cover_url = !empty($a->cover_url) ? $a->cover_url : 
                       (!empty($a->cover_filename) ? ($cover_art_url . '/' . $a->cover_filename) : '');
        $artist = !empty($a->artist) ? $a->artist : 'Glenn Bennett';
    ?>
    <div class="album-row">
        <?php if ($a_cover_url): ?>
            <img src="<?= htmlspecialchars($a_cover_url) ?>" class="album-cover">
        <?php else: ?>
            <div class="album-cover album-cover-empty">🎵</div>
        <?php endif; ?>
        <div class="album-info">
            <div class="album-title"><?= htmlspecialchars($a->title) ?></div>
            <div class="album-meta"><?= htmlspecialchars($artist) ?> · <?= $a->year ?? '—' ?> · <?= $a->song_count ?> songs</div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ========== SONGS TAB ========== -->
<div id="tab-songs" class="tab-content">
    <input type="text" class="search-box" placeholder="🔍 Search songs..." oninput="filterSongs(this.value)">
    <div class="songs-list" id="songs-list">
        <?php foreach ($songs as $song): 
            $on_disk = in_array($song->filename, $audio_files);
            $has_cover = !empty($song->cover_filename) || !empty($song->album_cover_filename);
        ?>
        <div class="song-row" data-search="<?= strtolower($song->title . ' ' . ($song->artist ?? '') . ' ' . ($song->album_title ?? '')) ?>">
            <div class="song-main">
                <div class="song-title"><?= htmlspecialchars($song->title) ?></div>
                <div class="song-sub"><?= htmlspecialchars($song->artist ?? 'Unknown') ?> · <?= $song->album_title ? htmlspecialchars($song->album_title) : 'Misc' ?></div>
            </div>
            <div class="song-right">
                <span class="song-duration"><?= $song->duration ? gmdate("i:s", $song->duration) : '—' ?></span>
                <?php if (!$on_disk): ?>
                    <span class="dot dot-red" title="Missing file"></span>
                <?php elseif (!$has_cover): ?>
                    <span class="dot dot-orange" title="No cover"></span>
                <?php else: ?>
                    <span class="dot dot-green" title="OK"></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ========== FILES TAB ========== -->
<div id="tab-files" class="tab-content">
    <input type="text" class="search-box" placeholder="🔍 Search files..." oninput="filterFiles(this.value)">
    <div class="files-list" id="files-list">
        <?php foreach ($audio_files as $file): 
            $in_db = isset($db_by_filename[$file]);
        ?>
        <div class="file-row" data-search="<?= strtolower($file) ?>">
            <div class="file-name"><?= htmlspecialchars($file) ?></div>
            <div class="file-right">
                <?php if ($in_db): ?>
                    <span class="dot dot-green" title="In database"></span>
                <?php else: ?>
                    <span class="dot dot-orange" title="Not in database"></span>
                <?php endif; ?>
                <button class="btn-icon" onclick="deleteFile('<?= htmlspecialchars(addslashes($file)) ?>')" title="Delete">🗑️</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
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
    
    .album-row { display: flex; align-items: center; gap: 12px; padding: 10px 14px; background: white; border-radius: 10px; margin-bottom: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
    .album-cover { width: 48px; height: 48px; border-radius: 8px; object-fit: cover; flex-shrink: 0; }
    .album-cover-empty { background: #f5f5f5; display: flex; align-items: center; justify-content: center; font-size: 20px; }
    .album-info { flex: 1; min-width: 0; }
    .album-title { font-size: 14px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .album-meta { font-size: 12px; color: #888; margin-top: 2px; }
    
    .search-box { width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; margin-bottom: 12px; }
    .search-box:focus { outline: none; border-color: #667eea; }
    
    .songs-list, .files-list { max-height: 500px; overflow-y: auto; }
    
    .song-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
    .song-row:last-child { border-bottom: none; }
    .song-main { flex: 1; min-width: 0; }
    .song-title { font-size: 14px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .song-sub { font-size: 12px; color: #888; margin-top: 2px; }
    .song-right { display: flex; align-items: center; gap: 10px; }
    .song-duration { font-size: 12px; color: #888; font-family: monospace; }
    
    .file-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
    .file-row:last-child { border-bottom: none; }
    .file-name { font-size: 12px; font-family: monospace; color: #555; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .file-right { display: flex; align-items: center; gap: 8px; }
    
    .dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
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
    
    .muted { color: #888; }
    .btn-sm { padding: 6px 12px; font-size: 12px; }
    
    @media (max-width: 500px) {
        .quick-num { font-size: 18px; }
        .album-cover { width: 40px; height: 40px; }
        .album-title { font-size: 13px; }
    }
';

$delete_url = site_url("admin/delete_file");
$clean_url = site_url("admin/clean_orphans");

$extra_js = <<<JAVASCRIPT
<script>
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
    document.querySelectorAll("#songs-list .song-row").forEach(function(row) {
        row.style.display = row.dataset.search.indexOf(q) >= 0 ? "" : "none";
    });
}

function filterFiles(q) {
    q = q.toLowerCase();
    document.querySelectorAll("#files-list .file-row").forEach(function(row) {
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
</script>
JAVASCRIPT;

include(APPPATH . 'views/admin/layout.php');
?>
