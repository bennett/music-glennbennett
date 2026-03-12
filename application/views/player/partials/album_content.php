<?php
/**
 * Album Content Partial
 *
 * Renders album display and track list server-side.
 * MUST produce identical HTML to renderAlbum() in music-player.js
 *
 * @var object $album - Album object with songs array
 */

$total_duration = 0;
foreach ($album->songs as $song) {
    $total_duration += $song->duration ?? 0;
}

function format_duration($seconds) {
    if (!$seconds) return '0:00';
    $m = floor($seconds / 60);
    $s = floor($seconds % 60);
    return $m . ':' . str_pad($s, 2, '0', STR_PAD_LEFT);
}
?>

<div class="album-display">
    <?php if (!empty($album->cover_url) && $album->title !== 'Misc'): ?>
        <img src="<?= html_escape($album->cover_url) ?>" class="album-cover" alt="<?= html_escape($album->title) ?>">
    <?php else: ?>
        <?php
        // Stacked covers for virtual albums (Favorites, Misc) — same logic as JS getStackedCovers()
        $covers = [];
        $seen = [];
        foreach ($album->songs as $s) {
            if (!empty($s->cover_url) && !in_array($s->cover_url, $seen)) {
                $seen[] = $s->cover_url;
                $covers[] = $s->cover_url;
                if (count($covers) >= 3) break;
            }
        }
        ?>
        <?php if (count($covers) > 0): ?>
            <div class="stacked-covers count-<?= count($covers) ?>">
                <?php foreach ($covers as $cover): ?>
                    <img src="<?= html_escape($cover) ?>" alt="">
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="album-cover placeholder"><?= strtoupper(substr($album->title ?? '?', 0, 1)) ?></div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="album-info">
        <h2><?= html_escape($album->title) ?></h2>
    </div>

    <div class="play-actions">
        <button class="play-action-btn primary" id="mainPlayBtn" onclick="player.mainPlayToggle()">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
            Play
        </button>
        <button class="play-action-btn" onclick="player.shufflePlay()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 3 21 3 21 8"></polyline><line x1="4" y1="20" x2="21" y2="3"></line><polyline points="21 16 21 21 16 21"></polyline><line x1="15" y1="15" x2="21" y2="21"></line><line x1="4" y1="4" x2="9" y2="9"></line></svg>
            Shuffle
        </button>
        <button class="play-action-btn" onclick="player.shareAlbum()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>
            Share
        </button>
    </div>
    <div class="album-meta"><?= count($album->songs) ?> songs · <?= format_duration($total_duration) ?></div>
    <div class="track-list">
        <?php foreach ($album->songs as $index => $song): ?>
        <div class="track" data-index="<?= $index ?>" onclick="player.playTrack(<?= $index ?>)">
            <?php if (!empty($song->cover_url)): ?>
                <img src="<?= html_escape($song->cover_url) ?>" class="track-thumb" alt="">
            <?php else: ?>
                <span class="track-number"><?= $index + 1 ?></span>
            <?php endif; ?>
            <div class="track-info">
                <div class="track-title"><?= html_escape($song->title) ?></div>
            </div>
            <span class="track-duration"><?= format_duration($song->duration) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
