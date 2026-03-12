<?php
$page_title = 'Upload Song';
$page_icon = '⬆️';
$active_page = 'upload';

$header_actions = '<a href="' . site_url('admin/songs') . '" class="btn btn-secondary">← Back to Library</a>';

// Build content
ob_start();
?>

<?php if (!empty($staged_files)): ?>
<!-- Staged Files Section -->
<div class="card staged-card">
    <div class="card-header">
        <div class="card-title">📋 Files Ready to Import (<?= count($staged_files) ?>)</div>
    </div>
    <p class="staged-intro">Review the metadata extracted from ID3 tags. Click <strong>Import</strong> to add to your library or <strong>Cancel</strong> to remove.</p>
    
    <?php foreach ($staged_files as $staged): ?>
    <div class="staged-file">
        <div class="staged-info">
            <div class="staged-cover <?= $staged['has_cover'] ? 'has-art' : 'no-art' ?>">
                <?= $staged['has_cover'] ? '🖼' : '🎵' ?>
            </div>
            <div class="staged-meta">
                <div class="staged-title"><?= htmlspecialchars($staged['title']) ?></div>
                <div class="staged-artist"><?= htmlspecialchars($staged['artist'] ?? 'Unknown Artist') ?></div>
                <div class="staged-details">
                    <?php if (!empty($staged['album'])): ?>
                    <span>💿 <?= htmlspecialchars($staged['album']) ?></span>
                    <?php endif; ?>
                    <span>⏱ <?= $staged['duration'] ? gmdate("i:s", $staged['duration']) : '--:--' ?></span>
                    <?php if (!empty($staged['track'])): ?><span>#<?= $staged['track'] ?></span><?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="staged-dest">
            <div class="dest-label">Will be saved as:</div>
            <div class="dest-path">/songs/<?= htmlspecialchars($staged['filename']) ?></div>
            <?php if (!empty($staged['exists'])): ?>
                <div class="dest-warning">⚠️ File already exists and will be overwritten!</div>
            <?php endif; ?>
        </div>
        
        <div class="staged-actions">
            <form method="POST" action="<?= site_url('admin/commit_upload') ?>" style="display:inline;">
                <input type="hidden" name="staged_file" value="<?= htmlspecialchars($staged['staged_path']) ?>">
                <button type="submit" class="btn btn-primary btn-sm">✓ Import</button>
            </form>
            <form method="POST" action="<?= site_url('admin/cancel_upload') ?>" style="display:inline;">
                <input type="hidden" name="staged_file" value="<?= htmlspecialchars($staged['staged_path']) ?>">
                <button type="submit" class="btn btn-secondary btn-sm">✕ Cancel</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php if (count($staged_files) > 1): ?>
    <div class="staged-bulk">
        <form method="POST" action="<?= site_url('admin/commit_all') ?>" style="display:inline;">
            <button type="submit" class="btn btn-primary">Import All (<?= count($staged_files) ?>)</button>
        </form>
        <form method="POST" action="<?= site_url('admin/cancel_all') ?>" style="display:inline;">
            <button type="submit" class="btn btn-secondary">Cancel All</button>
        </form>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Upload Form -->
<div class="card">
    <div class="card-header">
        <div class="card-title">Upload Audio File</div>
    </div>
    
    <div class="info-box">
        <strong>How it works:</strong>
        <ol>
            <li>Select your MP3/audio file</li>
            <li>File goes to staging area</li>
            <li>Review the ID3 metadata</li>
            <li>Import to add to <code>/songs/</code> folder</li>
        </ol>
    </div>
    
    <form method="POST" enctype="multipart/form-data" action="<?= site_url('admin/do_upload') ?>">
        <div class="form-group">
            <label>Select Audio File</label>
            <div class="file-drop-zone" id="dropZone">
                <input type="file" name="audio_file" id="audioFile" accept=".mp3,.m4a,.flac,.ogg,.wav" required>
                <div class="drop-content">
                    <div class="drop-icon">🎵</div>
                    <div class="drop-text">Click to select or drag & drop</div>
                    <div class="drop-hint">MP3, M4A, FLAC, OGG, WAV supported</div>
                </div>
            </div>
            <div class="file-selected" id="fileSelected">
                <span class="file-icon">✓</span>
                <span class="file-name" id="fileName"></span>
                <span class="file-size" id="fileSize"></span>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary" id="uploadBtn" disabled>
                ⬆️ Upload & Preview
            </button>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();

$extra_css = '
    .info-box { background: #e3f2fd; border-radius: 8px; padding: 15px 20px; margin-bottom: 25px; color: #1565c0; font-size: 14px; }
    .info-box ol { margin: 12px 0 0 20px; }
    .info-box li { margin: 6px 0; }
    .info-box code { background: rgba(0,0,0,0.1); padding: 2px 6px; border-radius: 4px; font-size: 12px; }
    
    .form-group { margin-bottom: 25px; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 10px; color: #333; }
    
    .file-drop-zone { position: relative; border: 2px dashed #ddd; border-radius: 12px; padding: 50px 20px; text-align: center; cursor: pointer; transition: all 0.2s; background: #fafafa; }
    .file-drop-zone:hover { border-color: #667eea; background: #f5f5ff; }
    .file-drop-zone.dragover { border-color: #667eea; background: #ede7f6; border-style: solid; }
    .file-drop-zone.has-file { display: none; }
    .file-drop-zone input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
    .drop-icon { font-size: 48px; margin-bottom: 12px; }
    .drop-text { font-size: 16px; font-weight: 500; color: #333; }
    .drop-hint { font-size: 13px; color: #888; margin-top: 8px; }
    
    .file-selected { display: none; align-items: center; gap: 12px; padding: 18px 20px; background: #e8f5e9; border-radius: 10px; border: 1px solid #a5d6a7; }
    .file-selected.show { display: flex; }
    .file-icon { color: #2e7d32; font-size: 24px; }
    .file-name { font-weight: 600; color: #2e7d32; flex: 1; }
    .file-size { color: #666; font-size: 13px; }
    
    .form-actions { margin-top: 25px; }
    
    .staged-card { border: 2px solid #667eea; }
    .staged-intro { color: #666; font-size: 14px; margin-bottom: 20px; }
    
    .staged-file { background: #f8f9fa; border-radius: 10px; padding: 20px; margin-bottom: 15px; border: 1px solid #e9ecef; }
    .staged-info { display: flex; gap: 15px; align-items: flex-start; margin-bottom: 15px; }
    .staged-cover { width: 60px; height: 60px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
    .staged-cover.has-art { background: #e8f5e9; color: #2e7d32; }
    .staged-cover.no-art { background: #fff3e0; color: #ef6c00; }
    .staged-meta { flex: 1; }
    .staged-title { font-size: 17px; font-weight: 600; color: #333; }
    .staged-artist { font-size: 14px; color: #666; margin-top: 3px; }
    .staged-details { display: flex; gap: 15px; margin-top: 10px; font-size: 13px; color: #888; }
    
    .staged-dest { background: #fff; border: 1px solid #dee2e6; border-radius: 8px; padding: 12px 15px; margin-bottom: 15px; }
    .dest-label { font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
    .dest-path { font-family: monospace; font-size: 13px; color: #333; }
    .dest-warning { color: #ef6c00; font-size: 13px; margin-top: 10px; font-weight: 500; background: #fff3e0; padding: 8px 12px; border-radius: 6px; margin-top: 10px; }
    
    .staged-actions { display: flex; gap: 10px; }
    .staged-bulk { margin-top: 20px; padding-top: 20px; border-top: 1px solid #dee2e6; display: flex; gap: 12px; }
    
    .btn-sm { padding: 8px 16px; font-size: 13px; }
';

$extra_js = <<<JAVASCRIPT
<script>
var dropZone = document.getElementById("dropZone");
var fileInput = document.getElementById("audioFile");
var uploadBtn = document.getElementById("uploadBtn");
var fileSelected = document.getElementById("fileSelected");
var fileName = document.getElementById("fileName");
var fileSize = document.getElementById("fileSize");

["dragenter", "dragover"].forEach(function(e) {
    dropZone.addEventListener(e, function(ev) {
        ev.preventDefault();
        dropZone.classList.add("dragover");
    });
});

["dragleave", "drop"].forEach(function(e) {
    dropZone.addEventListener(e, function(ev) {
        ev.preventDefault();
        dropZone.classList.remove("dragover");
    });
});

dropZone.addEventListener("drop", function(e) {
    if (e.dataTransfer.files.length) {
        fileInput.files = e.dataTransfer.files;
        updateFileDisplay();
    }
});

fileInput.addEventListener("change", updateFileDisplay);

function updateFileDisplay() {
    if (fileInput.files && fileInput.files[0]) {
        var file = fileInput.files[0];
        dropZone.classList.add("has-file");
        fileSelected.classList.add("show");
        fileName.textContent = file.name;
        fileSize.textContent = (file.size / 1024 / 1024).toFixed(2) + " MB";
        uploadBtn.disabled = false;
    }
}
</script>
JAVASCRIPT;

include(APPPATH . 'views/admin/layout.php');
?>
