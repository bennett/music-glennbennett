<?php
$page_title = 'Dashboard';
$page_icon = '📊';
$active_page = 'dashboard';

ob_start();
?>

<!-- Problems Banner -->
<?php if (!empty($problems)): ?>
<div class="alert alert-warning">
    <strong>⚠️ <?= count($problems) ?> Issue<?= count($problems) > 1 ? 's' : '' ?></strong>
    <?php foreach ($problems as $p): ?>
    <div class="alert-item">
        <span class="badge-<?= $p['type'] ?>"><?= ucfirst($p['type']) ?></span>
        <?= htmlspecialchars($p['message']) ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Today Stats -->
<div class="today-card">
    <div class="today-title">📅 Today</div>
    <div class="today-stats">
        <div class="today-stat">
            <div class="today-num"><?= $today_plays ?? 0 ?></div>
            <div class="today-label">Plays</div>
        </div>
        <div class="today-stat">
            <div class="today-num"><?= $today_complete ?? 0 ?></div>
            <div class="today-label">Complete</div>
        </div>
        <div class="today-stat">
            <div class="today-num"><?= $today_skips ?? 0 ?></div>
            <div class="today-label">Skips</div>
        </div>
        <div class="today-stat">
            <div class="today-num"><?= $today_unique_songs ?? 0 ?></div>
            <div class="today-label">Songs</div>
        </div>
    </div>
    <?php if (!empty($today_top_song)): ?>
    <div class="today-top">🎵 Top: <strong><?= htmlspecialchars($today_top_song->title) ?></strong> (<?= $today_top_song->plays ?>)</div>
    <?php endif; ?>
</div>

<!-- This Week Stats -->
<div class="week-card">
    <div class="week-title">📈 This Week</div>
    <div class="week-stats">
        <div class="week-stat">
            <div class="week-num"><?= $week_plays ?? 0 ?></div>
            <div class="week-label">Plays</div>
        </div>
        <div class="week-stat">
            <div class="week-num"><?= $week_complete ?? 0 ?></div>
            <div class="week-label">Complete</div>
        </div>
        <div class="week-stat">
            <div class="week-num"><?= $week_skips ?? 0 ?></div>
            <div class="week-label">Skips</div>
        </div>
        <div class="week-stat">
            <div class="week-num"><?= $week_unique_songs ?? 0 ?></div>
            <div class="week-label">Songs</div>
        </div>
    </div>
    <?php if (!empty($week_top_song)): ?>
    <div class="week-top">🎵 Top: <strong><?= htmlspecialchars($week_top_song->title) ?></strong> (<?= $week_top_song->plays ?>)</div>
    <?php endif; ?>
</div>

<!-- Quick Stats -->
<div class="quick-row">
    <div class="quick-stat">
        <span class="quick-num"><?= $total_albums ?></span>
        <span class="quick-label">Albums</span>
    </div>
    <div class="quick-stat">
        <span class="quick-num"><?= $total_songs ?></span>
        <span class="quick-label">Songs</span>
    </div>
    <div class="quick-stat">
        <span class="quick-num"><?= $total_plays ?? 0 ?></span>
        <span class="quick-label">Plays</span>
    </div>
    <div class="quick-stat">
        <span class="quick-num" style="color:#4caf50;"><?= $complete_plays ?? 0 ?></span>
        <span class="quick-label">Complete</span>
    </div>
</div>

<!-- Two Column Layout -->
<div class="dash-grid">
    <!-- Recent Complete Plays -->
    <div class="card compact">
        <div class="card-title-sm">✅ Recent Complete Plays</div>
        <?php if (empty($recent_complete)): ?>
            <div class="empty-msg">No complete plays yet</div>
        <?php else: ?>
            <?php foreach (array_slice($recent_complete, 0, 8) as $play): ?>
            <div class="activity-row">
                <div class="activity-main">
                    <div class="activity-song"><?= htmlspecialchars($play->title) ?></div>
                    <div class="activity-sub">
                        <?= htmlspecialchars($play->device_name ?: 'Unknown') ?> · <?= date('M j, g:ia', strtotime($play->played_at)) ?>
                    </div>
                </div>
                <div class="activity-pct" style="color:#4caf50"><?= $play->percent ?>%</div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Top Complete Songs -->
    <div class="card compact">
        <div class="card-title-sm">🏆 Most Completed Songs</div>
        <?php if (empty($top_complete_songs)): ?>
            <div class="empty-msg">No complete plays yet</div>
        <?php else: ?>
            <?php foreach (array_slice($top_complete_songs, 0, 8) as $i => $song): ?>
            <div class="top-row">
                <div class="top-rank"><?= $i + 1 ?></div>
                <div class="top-main">
                    <div class="top-song"><?= htmlspecialchars($song->title) ?></div>
                    <div class="top-album"><?= htmlspecialchars($song->album_title ?: 'Unknown Album') ?></div>
                </div>
                <div class="top-plays" style="color:#4caf50"><?= $song->complete_count ?></div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Activity & Top Songs -->
<div class="dash-grid">
    <!-- Recent Activity -->
    <div class="card compact">
        <div class="card-title-sm">🕐 Recent Activity</div>
        <?php if (empty($recent_plays)): ?>
            <div class="empty-msg">No recent plays</div>
        <?php else: ?>
            <?php foreach (array_slice($recent_plays, 0, 8) as $play): 
                $pct = $play->percent ?? 0;
                $color = $pct >= 75 ? '#4caf50' : ($pct >= 40 ? '#ff9800' : '#ef5350');
            ?>
            <div class="activity-row">
                <div class="activity-main">
                    <div class="activity-song"><?= htmlspecialchars($play->title) ?></div>
                    <div class="activity-sub">
                        <?= htmlspecialchars($play->device_name ?: 'Unknown') ?> · <?= date('M j, g:ia', strtotime($play->played_at)) ?>
                    </div>
                </div>
                <div class="activity-pct" style="color:<?= $color ?>"><?= $pct ?>%</div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Top Songs -->
    <div class="card compact">
        <div class="card-title-sm">📊 Top Songs (All Plays)</div>
        <?php if (empty($top_songs)): ?>
            <div class="empty-msg">No plays yet</div>
        <?php else: ?>
            <?php foreach (array_slice($top_songs, 0, 8) as $i => $song): ?>
            <div class="top-row">
                <div class="top-rank"><?= $i + 1 ?></div>
                <div class="top-main">
                    <div class="top-song"><?= htmlspecialchars($song->title) ?></div>
                    <div class="top-album"><?= htmlspecialchars($song->album_title ?: 'Unknown Album') ?></div>
                </div>
                <div class="top-plays"><?= $song->play_count ?></div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();

$extra_css = '
    .alert { padding: 12px 14px; border-radius: 10px; margin-bottom: 16px; font-size: 13px; }
    .alert-warning { background: #fff8e1; border: 1px solid #ffe082; }
    .alert-item { padding: 6px 0; border-bottom: 1px solid rgba(0,0,0,0.06); }
    .alert-item:last-child { border-bottom: none; }
    
    .today-card { background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%); color: white; border-radius: 12px; padding: 16px; margin-bottom: 12px; }
    .today-title { font-size: 13px; opacity: 0.9; font-weight: 500; margin-bottom: 12px; }
    .today-stats { display: flex; justify-content: space-around; text-align: center; }
    .today-num { font-size: 28px; font-weight: 700; }
    .today-label { font-size: 11px; opacity: 0.8; }
    .today-top { margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.2); font-size: 12px; }
    
    .week-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 12px; padding: 16px; margin-bottom: 16px; }
    .week-title { font-size: 13px; opacity: 0.9; font-weight: 500; margin-bottom: 12px; }
    .week-stats { display: flex; justify-content: space-around; text-align: center; }
    .week-num { font-size: 28px; font-weight: 700; }
    .week-label { font-size: 11px; opacity: 0.8; }
    .week-top { margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.2); font-size: 12px; }
    
    .quick-row { display: flex; background: white; border-radius: 12px; padding: 12px 8px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    .quick-stat { flex: 1; text-align: center; border-right: 1px solid #eee; }
    .quick-stat:last-child { border-right: none; }
    .quick-num { font-size: 20px; font-weight: 700; color: #667eea; display: block; }
    .quick-label { font-size: 11px; color: #888; }
    
    .dash-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    @media (max-width: 800px) { .dash-grid { grid-template-columns: 1fr; } }
    
    .card.compact { padding: 14px; margin-bottom: 0; }
    .card-title-sm { font-size: 14px; font-weight: 600; color: #667eea; margin-bottom: 12px; }
    
    .empty-msg { color: #888; font-size: 13px; text-align: center; padding: 20px; }
    
    .activity-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f5f5f5; }
    .activity-row:last-child { border-bottom: none; }
    .activity-main { flex: 1; min-width: 0; }
    .activity-song { font-size: 13px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .activity-sub { font-size: 11px; color: #888; margin-top: 2px; }
    .activity-pct { font-size: 13px; font-weight: 600; margin-left: 10px; }
    
    .top-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f5f5f5; }
    .top-row:last-child { border-bottom: none; }
    .top-rank { font-size: 14px; font-weight: 700; color: #667eea; width: 20px; text-align: center; }
    .top-main { flex: 1; min-width: 0; }
    .top-song { font-size: 13px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .top-album { font-size: 11px; color: #888; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .top-plays { font-size: 14px; font-weight: 600; color: #667eea; }
    
    .badge-error { background: #ffebee; color: #c62828; padding: 2px 6px; border-radius: 10px; font-size: 10px; }
    .badge-warning { background: #fff3e0; color: #ef6c00; padding: 2px 6px; border-radius: 10px; font-size: 10px; }
    .badge-info { background: #e3f2fd; color: #1565c0; padding: 2px 6px; border-radius: 10px; font-size: 10px; }
    
    @media (max-width: 500px) {
        .today-num { font-size: 22px; }
        .week-num { font-size: 22px; }
        .quick-num { font-size: 18px; }
    }
';

include(APPPATH . 'views/admin/layout.php');
?>
