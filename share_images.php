<?php
$page_title = 'Share Images';
$page_icon = '🖼️';
$active_page = 'tools';

ob_start();

// Build flat list of all image URLs for lightbox navigation
$all_images = [];
$all_images[] = site_url('share/image');
foreach ($albums as $album) {
    $all_images[] = site_url('share/image?album=' . $album->id);
}
foreach ($songs as $song) {
    $all_images[] = site_url('share/image?song=' . $song->id);
}
?>

<p style="color:#666;margin-bottom:20px;">Preview of all generated OG images for Facebook/Twitter sharing. Click any image to view full size. Use arrow keys to navigate.</p>

<!-- Generic / Site-wide -->
<h3 style="margin:0 0 12px;font-size:16px;">Generic</h3>
<div class="share-grid">
    <div class="share-card" onclick="showFull(0)">
        <img src="<?= site_url('share/image') ?>" alt="Generic" loading="lazy">
        <div class="share-label">Site Default</div>
    </div>
</div>

<!-- Albums -->
<h3 style="margin:24px 0 12px;font-size:16px;">Albums (<?= count($albums) ?>)</h3>
<div class="share-grid">
<?php foreach ($albums as $i => $album): ?>
    <div class="share-card" onclick="showFull(<?= $i + 1 ?>)">
        <img src="<?= site_url('share/image?album=' . $album->id) ?>" alt="<?= htmlspecialchars($album->title) ?>" loading="lazy">
        <div class="share-label">
            <?= htmlspecialchars($album->title) ?>
            <a href="<?= site_url('share/test_image/' . $album->id) ?>" target="_blank" class="debug-link" onclick="event.stopPropagation()">debug</a>
        </div>
    </div>
<?php endforeach; ?>
</div>

<!-- Songs -->
<h3 style="margin:24px 0 12px;font-size:16px;">Songs (<?= count($songs) ?>)</h3>
<?php
$current_album = null;
$song_offset = 1 + count($albums);
foreach ($songs as $i => $song):
    if ($song->album_title !== $current_album):
        if ($current_album !== null) echo '</div>';
        $current_album = $song->album_title;
?>
    <h4 style="margin:16px 0 8px;font-size:14px;color:#666;"><?= htmlspecialchars($current_album) ?></h4>
    <div class="share-grid">
<?php endif; ?>
    <div class="share-card" onclick="showFull(<?= $song_offset + $i ?>)">
        <img src="<?= site_url('share/image?song=' . $song->id) ?>" alt="<?= htmlspecialchars($song->title) ?>" loading="lazy">
        <div class="share-label">
            <?= htmlspecialchars($song->title) ?>
            <a href="<?= site_url('share/debug/' . $song->id) ?>" target="_blank" class="debug-link" onclick="event.stopPropagation()">debug</a>
        </div>
    </div>
<?php endforeach; ?>
<?php if ($current_album !== null) echo '</div>'; ?>

<!-- Lightbox -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
    <img id="lightboxImg" src="" alt="Full size" onclick="event.stopPropagation()">
    <div class="lb-nav lb-prev" onclick="event.stopPropagation(); navLightbox(-1)">&#8249;</div>
    <div class="lb-nav lb-next" onclick="event.stopPropagation(); navLightbox(1)">&#8250;</div>
    <div class="lb-counter" id="lbCounter"></div>
</div>

<?php
$content = ob_get_clean();

$extra_css = '
    .share-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px; }
    .share-card { background: #1a1a2e; border-radius: 8px; overflow: hidden; cursor: pointer; transition: transform 0.15s; }
    .share-card:hover { transform: scale(1.02); }
    .share-card img { width: 100%; height: auto; display: block; }
    .share-label { padding: 8px 10px; color: #ccc; font-size: 12px; display: flex; justify-content: space-between; align-items: center; }
    .debug-link { color: #667eea; text-decoration: none; font-size: 11px; }
    .debug-link:hover { text-decoration: underline; }
    .lightbox { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.9); z-index: 1000; align-items: center; justify-content: center; padding: 20px; cursor: zoom-out; }
    .lightbox.show { display: flex; }
    .lightbox img { max-width: 90%; max-height: 85vh; border-radius: 8px; box-shadow: 0 4px 30px rgba(0,0,0,0.5); cursor: default; }
    .lb-nav { position: fixed; top: 50%; transform: translateY(-50%); color: white; font-size: 60px; cursor: pointer; padding: 20px; opacity: 0.6; transition: opacity 0.2s; user-select: none; z-index: 1001; }
    .lb-nav:hover { opacity: 1; }
    .lb-prev { left: 10px; }
    .lb-next { right: 10px; }
    .lb-counter { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); color: rgba(255,255,255,0.5); font-size: 14px; z-index: 1001; }
';

$extra_js = '
<script>
var lbImages = ' . json_encode(array_values($all_images)) . ';
var lbIndex = 0;

function showFull(idx) {
    lbIndex = idx;
    document.getElementById("lightboxImg").src = lbImages[idx];
    document.getElementById("lbCounter").textContent = (idx + 1) + " / " + lbImages.length;
    document.getElementById("lightbox").classList.add("show");
}

function closeLightbox() {
    document.getElementById("lightbox").classList.remove("show");
}

function navLightbox(dir) {
    lbIndex = (lbIndex + dir + lbImages.length) % lbImages.length;
    document.getElementById("lightboxImg").src = lbImages[lbIndex];
    document.getElementById("lbCounter").textContent = (lbIndex + 1) + " / " + lbImages.length;
}

document.addEventListener("keydown", function(e) {
    var lb = document.getElementById("lightbox");
    if (!lb.classList.contains("show")) return;
    if (e.key === "ArrowRight") { e.preventDefault(); navLightbox(1); }
    else if (e.key === "ArrowLeft") { e.preventDefault(); navLightbox(-1); }
    else if (e.key === "Escape") { closeLightbox(); }
});
</script>
';

include(APPPATH . 'views/admin/layout.php');
?>
