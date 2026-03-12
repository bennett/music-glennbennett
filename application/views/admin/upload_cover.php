<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Upload Cover - Bennett Music</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f5f5; }
        
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 20px; }
        .header h1 { font-size: 20px; margin-bottom: 10px; }
        .header-nav { display: flex; gap: 8px; flex-wrap: wrap; }
        .header-nav a { color: white; text-decoration: none; padding: 6px 12px; background: rgba(255,255,255,0.2); border-radius: 5px; font-size: 13px; }
        
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        
        .card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 20px; }
        .card h2 { font-size: 18px; color: #667eea; margin-bottom: 20px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #333; }
        .form-group input, .form-group select { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #667eea; }
        .form-group small { display: block; margin-top: 5px; color: #888; font-size: 12px; }
        
        .file-input { position: relative; }
        .file-input input[type="file"] { position: absolute; left: 0; top: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
        .file-input-label { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 40px; border: 2px dashed #ddd; border-radius: 8px; background: #fafafa; cursor: pointer; transition: all 0.2s; }
        .file-input-label:hover { border-color: #667eea; background: #f0f0ff; }
        .file-input-label.has-file { border-color: #4caf50; background: #e8f5e9; }
        .file-input-label .icon { font-size: 40px; }
        .file-input-label .text { color: #666; }
        .file-name { margin-top: 10px; font-size: 13px; color: #667eea; font-weight: 500; }
        
        .preview-img { max-width: 200px; max-height: 200px; border-radius: 8px; margin-top: 10px; display: none; }
        
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 14px 28px; background: #667eea; color: white; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; }
        .btn:hover { background: #5a6fd6; }
        .btn-secondary { background: #f5f5f5; color: #333; }
        .btn-secondary:hover { background: #eee; }
        
        .actions { display: flex; gap: 10px; margin-top: 25px; }
        
        .info-box { background: #e3f2fd; border-radius: 8px; padding: 15px; margin-bottom: 20px; font-size: 13px; color: #1565c0; }
        
        .album-list { max-height: 200px; overflow-y: auto; }
        .album-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; font-size: 13px; }
        .album-item:last-child { border-bottom: none; }
        .album-item .name { font-weight: 500; }
        .album-item .file { color: #888; font-family: monospace; font-size: 11px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🖼️ Upload Cover Image</h1>
        <div class="header-nav">
            <a href="<?= site_url('admin') ?>">← Dashboard</a>
            <a href="<?= site_url('admin/songs') ?>">🎵 Songs</a>
            <a href="<?= site_url('admin/upload_song') ?>">⬆️ Upload Song</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($this->session->flashdata('success')): ?>
            <div class="alert alert-success"><?= $this->session->flashdata('success') ?></div>
        <?php endif; ?>
        <?php if ($this->session->flashdata('error')): ?>
            <div class="alert alert-error"><?= $this->session->flashdata('error') ?></div>
        <?php endif; ?>
        
        <div class="card">
            <h2>Upload Album Cover</h2>
            
            <div class="info-box">
                📁 Covers will be saved to: <strong><?= htmlspecialchars($imgs_dir) ?>/</strong><br>
                The filename should match the album name (e.g., "Milestones.jpg" for album "Milestones")
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Cover Image *</label>
                    <div class="file-input">
                        <div class="file-input-label" id="fileLabel">
                            <span class="icon">🖼️</span>
                            <span class="text">Click or drag to select image</span>
                        </div>
                        <input type="file" name="cover_file" id="coverFile" accept=".jpg,.jpeg,.png,.webp,.gif" required onchange="updatePreview(this)">
                    </div>
                    <div class="file-name" id="fileName"></div>
                    <img id="preview" class="preview-img">
                    <small>Supported: JPG, PNG, WebP, GIF. Recommended: Square image, at least 500x500px</small>
                </div>
                
                <div class="form-group">
                    <label>Album Name *</label>
                    <input type="text" name="album_name" id="albumName" placeholder="e.g., Milestones" required list="albumSuggestions">
                    <datalist id="albumSuggestions">
                        <?php foreach ($albums as $album): ?>
                            <option value="<?= htmlspecialchars($album->title) ?>">
                        <?php endforeach; ?>
                    </datalist>
                    <small>The image will be saved as "[Album Name].jpg" (or appropriate extension)</small>
                </div>
                
                <div class="actions">
                    <button type="submit" class="btn">⬆️ Upload Cover</button>
                    <a href="<?= site_url('admin') ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
        
        <div class="card">
            <h2>Existing Albums</h2>
            <p style="color:#888;font-size:13px;margin-bottom:15px;">Select an album name above or type a new one:</p>
            <div class="album-list">
                <?php foreach ($albums as $album): 
                    // Check if cover exists
                    $has_cover = false;
                    $cover_file = '';
                    if (is_dir($imgs_dir)) {
                        $album_norm = strtolower(str_replace(['_', ' ', '-'], '', $album->title));
                        $files = @scandir($imgs_dir);
                        if ($files) {
                            foreach ($files as $file) {
                                if ($file === '.' || $file === '..') continue;
                                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) continue;
                                $file_norm = strtolower(str_replace(['_', ' ', '-'], '', pathinfo($file, PATHINFO_FILENAME)));
                                if ($file_norm === $album_norm) {
                                    $has_cover = true;
                                    $cover_file = $file;
                                    break;
                                }
                            }
                        }
                    }
                ?>
                <div class="album-item">
                    <span class="name" onclick="document.getElementById('albumName').value='<?= htmlspecialchars($album->title, ENT_QUOTES) ?>'" style="cursor:pointer;">
                        <?= $has_cover ? '✓' : '✕' ?> <?= htmlspecialchars($album->title) ?>
                    </span>
                    <span class="file"><?= $cover_file ? htmlspecialchars($cover_file) : 'No cover' ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <script>
    function updatePreview(input) {
        const label = document.getElementById('fileLabel');
        const nameDiv = document.getElementById('fileName');
        const preview = document.getElementById('preview');
        
        if (input.files && input.files[0]) {
            label.classList.add('has-file');
            label.innerHTML = '<span class="icon">✓</span><span class="text">Image selected</span>';
            nameDiv.textContent = input.files[0].name + ' (' + (input.files[0].size / 1024).toFixed(0) + ' KB)';
            
            // Show preview
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    </script>
</body>
</html>
