<?php
$page_title = 'Dashboard';
$page_icon = '📊';
$active_page = 'dashboard';

// Helper to convert UTC timestamp to Pacific
if (!function_exists('to_pacific')) {
    function to_pacific($utc_time, $format = 'M j, g:ia') {
        if (empty($utc_time)) return '';
        $dt = new DateTime($utc_time, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('America/Los_Angeles'));
        return $dt->format($format);
    }
}

// Helper to derive a friendly device label from user_agent + device_id
if (!function_exists('device_label')) {
    function device_label($play) {
        if (!empty($play->device_name)) return $play->device_name;
        $ua = strtolower($play->device_ua ?? '');
        if (strpos($ua, 'iphone') !== false) $type = 'iPhone';
        elseif (strpos($ua, 'ipad') !== false) $type = 'iPad';
        elseif (strpos($ua, 'android') !== false) $type = 'Android';
        elseif (strpos($ua, 'mac') !== false) $type = 'Mac';
        elseif (strpos($ua, 'windows') !== false) $type = 'Windows';
        else return 'Unknown';
        $short = strtoupper(substr($play->device_id ?? '', -5));
        return $short ? $type . '-' . $short : $type;
    }
}

// Helper to format seconds as Xh Ym
if (!function_exists('fmt_time')) {
    function fmt_time($secs) {
        if ($secs < 60) return $secs . 's';
        $h = floor($secs / 3600);
        $m = floor(($secs % 3600) / 60);
        return $h > 0 ? $h . 'h ' . $m . 'm' : $m . 'm';
    }
}

// Build tab data arrays
$tabs = [
    'today' => [
        'label' => 'Today',
        'plays' => $today_plays ?? 0,
        'complete' => $today_complete ?? 0,
        'skips' => $today_skips ?? 0,
        'songs' => $today_unique_songs ?? 0,
        'listen_time' => $today_listen_time ?? 0,
        'devices' => $today_devices ?? 0,
        'top_song' => $today_top_song ?? null,
        'top_songs' => $today_top_songs ?? [],
        'top_complete' => $today_top_complete ?? [],
        'podcast_plays' => $podcast_today_plays ?? 0,
        'podcast_complete' => $podcast_today_complete ?? 0,
        'podcast_listen_time' => $podcast_today_listen_time ?? 0,
        'podcast_listeners' => $podcast_today_listeners ?? 0,
        'promo_views' => $promo_today_views ?? 0,
        'promo_complete' => $promo_today_complete ?? 0,
        'promo_watch_time' => $promo_today_watch_time ?? 0,
        'promo_viewers' => $promo_today_viewers ?? 0,
    ],
    'week' => [
        'label' => 'This Week',
        'plays' => $week_plays ?? 0,
        'complete' => $week_complete ?? 0,
        'skips' => $week_skips ?? 0,
        'songs' => $week_unique_songs ?? 0,
        'listen_time' => $week_listen_time ?? 0,
        'devices' => $week_devices ?? 0,
        'top_song' => $week_top_song ?? null,
        'top_songs' => $week_top_songs ?? [],
        'top_complete' => $week_top_complete ?? [],
        'podcast_plays' => $podcast_week_plays ?? 0,
        'podcast_complete' => $podcast_week_complete ?? 0,
        'podcast_listen_time' => $podcast_week_listen_time ?? 0,
        'podcast_listeners' => $podcast_week_listeners ?? 0,
        'promo_views' => $promo_week_views ?? 0,
        'promo_complete' => $promo_week_complete ?? 0,
        'promo_watch_time' => $promo_week_watch_time ?? 0,
        'promo_viewers' => $promo_week_viewers ?? 0,
    ],
    'month' => [
        'label' => 'This Month',
        'plays' => $month_plays ?? 0,
        'complete' => $month_complete ?? 0,
        'skips' => $month_skips ?? 0,
        'songs' => $month_unique_songs ?? 0,
        'listen_time' => $month_listen_time ?? 0,
        'devices' => $month_devices ?? 0,
        'top_song' => $month_top_song ?? null,
        'top_songs' => $month_top_songs ?? [],
        'top_complete' => $month_top_complete ?? [],
        'podcast_plays' => $podcast_month_plays ?? 0,
        'podcast_complete' => $podcast_month_complete ?? 0,
        'podcast_listen_time' => $podcast_month_listen_time ?? 0,
        'podcast_listeners' => $podcast_month_listeners ?? 0,
        'promo_views' => $promo_month_views ?? 0,
        'promo_complete' => $promo_month_complete ?? 0,
        'promo_watch_time' => $promo_month_watch_time ?? 0,
        'promo_viewers' => $promo_month_viewers ?? 0,
    ],
    'alltime' => [
        'label' => 'All Time',
        'plays' => $total_plays ?? 0,
        'complete' => $complete_plays ?? 0,
        'skips' => $alltime_skips ?? 0,
        'songs' => $alltime_unique_songs ?? 0,
        'listen_time' => $total_listen_time ?? 0,
        'devices' => $total_devices ?? 0,
        'top_song' => $alltime_top_song ?? null,
        'top_songs' => $top_songs ?? [],
        'top_complete' => $top_complete_songs ?? [],
        'podcast_plays' => $podcast_alltime_plays ?? 0,
        'podcast_complete' => $podcast_alltime_complete ?? 0,
        'podcast_listen_time' => $podcast_alltime_listen_time ?? 0,
        'podcast_listeners' => $podcast_alltime_listeners ?? 0,
        'promo_views' => $promo_alltime_views ?? 0,
        'promo_complete' => $promo_alltime_complete ?? 0,
        'promo_watch_time' => $promo_alltime_watch_time ?? 0,
        'promo_viewers' => $promo_alltime_viewers ?? 0,
    ],
];

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

<!-- Tabbed Stats Card -->
<div class="stats-card">
    <div class="tab-bar">
        <?php $first = true; foreach ($tabs as $key => $tab): ?>
        <button class="tab-btn<?= $first ? ' active' : '' ?>" data-tab="<?= $key ?>"><?= $tab['label'] ?></button>
        <?php $first = false; endforeach; ?>
    </div>

    <?php $first = true; foreach ($tabs as $key => $tab): ?>
    <div class="tab-panel<?= $first ? ' active' : '' ?>" id="panel-<?= $key ?>">
        <div class="stat-numbers">
            <div class="stat-box">
                <div class="stat-num"><?= number_format($tab['plays']) ?></div>
                <div class="stat-label">Plays</div>
                <div class="stat-hint">20s+ listens</div>
            </div>
            <div class="stat-box">
                <div class="stat-num complete"><?= number_format($tab['complete']) ?></div>
                <div class="stat-label">Complete</div>
                <div class="stat-hint">90%+ listened</div>
            </div>
            <div class="stat-box">
                <div class="stat-num skip"><?= number_format($tab['skips']) ?></div>
                <div class="stat-label">Skips</div>
                <div class="stat-hint">under 50%</div>
            </div>
            <div class="stat-box">
                <div class="stat-num"><?= number_format($tab['songs']) ?></div>
                <div class="stat-label">Songs</div>
                <div class="stat-hint">unique tracks</div>
            </div>
        </div>
        <div class="stat-meta">
            <span>Listen Time: <strong><?= fmt_time($tab['listen_time']) ?></strong></span>
            <span>Devices: <strong><?= $tab['devices'] ?></strong></span>
        </div>
        <?php if (!empty($tab['top_song'])): ?>
        <div class="stat-top-song">Top: <strong><?= htmlspecialchars($tab['top_song']->title) ?></strong> (<?= $tab['top_song']->plays ?> plays)</div>
        <?php endif; ?>

        <?php if (!empty($tab['top_songs']) || !empty($tab['top_complete'])): ?>
        <div class="tab-lists">
            <?php if (!empty($tab['top_complete'])): ?>
            <div class="stat-top-list">
                <div class="stat-top-list-title">Most Completed <span class="list-hint">90%+ listens only</span></div>
                <?php foreach (array_slice($tab['top_complete'], 0, 8) as $i => $song): ?>
                <div class="top-row">
                    <div class="top-rank"><?= $i + 1 ?></div>
                    <div class="top-main">
                        <div class="top-song"><?= htmlspecialchars($song->title) ?></div>
                        <div class="top-album"><?= htmlspecialchars($song->album_title ?: 'Misc') ?></div>
                    </div>
                    <div class="top-plays complete"><?= $song->complete_count ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($tab['top_songs'])): ?>
            <div class="stat-top-list">
                <div class="stat-top-list-title">Top Songs <span class="list-hint">complete + partial plays</span></div>
                <?php foreach (array_slice($tab['top_songs'], 0, 8) as $i => $song): ?>
                <div class="top-row">
                    <div class="top-rank"><?= $i + 1 ?></div>
                    <div class="top-main">
                        <div class="top-song"><?= htmlspecialchars($song->title) ?></div>
                        <div class="top-album"><?= htmlspecialchars($song->album_title ?: 'Misc') ?></div>
                    </div>
                    <div class="top-plays"><?= $song->play_count ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
    <?php $first = false; endforeach; ?>
</div>

<!-- Library Quick Stats -->
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
        <span class="quick-num"><?= $total_duration ?></span>
        <span class="quick-label">Duration</span>
    </div>
    <div class="quick-stat">
        <?php $other_count = ($podcast_alltime_plays ?? 0) + ($promo_alltime_views ?? 0); ?>
        <span class="quick-num"><?= $other_count ?></span>
        <span class="quick-label">Other</span>
    </div>
</div>

<!-- Recent Activity -->
<div class="dash-grid">
    <div class="card compact">
        <div class="card-title-row">
            <span class="card-title-sm">Recent Complete Plays</span>
            <span class="card-count"><?= min(8, count($recent_complete)) ?> of <?= number_format($total_complete_plays) ?></span>
        </div>
        <?php if (empty($recent_complete)): ?>
            <div class="empty-msg">No complete plays yet</div>
        <?php else: ?>
            <?php foreach (array_slice($recent_complete, 0, 8) as $play): ?>
            <div class="activity-row">
                <div class="activity-main">
                    <div class="activity-song"><?= htmlspecialchars($play->title) ?></div>
                    <div class="activity-sub">
                        <a href="<?= site_url('admin/device/' . urlencode($play->device_id)) ?>" class="device-link"><?= htmlspecialchars(device_label($play)) ?></a> · <?= to_pacific($play->played_at) ?>
                    </div>
                </div>
                <div class="activity-pct" style="color:#4caf50"><?= $play->percent ?>%</div>
            </div>
            <?php endforeach; ?>
            <a href="<?= site_url('admin/activity/complete') ?>" class="see-all">See all <?= number_format($total_complete_plays) ?> &raquo;</a>
        <?php endif; ?>
    </div>

    <div class="card compact">
        <div class="card-title-row">
            <span class="card-title-sm">Recent Activity</span>
            <span class="card-count"><?= min(8, count($recent_plays)) ?> of <?= number_format($total_recent_plays) ?></span>
        </div>
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
                        <a href="<?= site_url('admin/device/' . urlencode($play->device_id)) ?>" class="device-link"><?= htmlspecialchars(device_label($play)) ?></a> · <?= to_pacific($play->played_at) ?>
                    </div>
                </div>
                <div class="activity-pct" style="color:<?= $color ?>"><?= $pct ?>%</div>
            </div>
            <?php endforeach; ?>
            <a href="<?= site_url('admin/activity/all') ?>" class="see-all">See all <?= number_format($total_recent_plays) ?> &raquo;</a>
        <?php endif; ?>
    </div>
</div>


<!-- Other Stats (Tabbed) -->
<div class="stats-card" style="margin-top: 16px;">
    <div class="card-title" style="padding: 14px 16px 8px; font-size: 15px; font-weight: 600;">Other</div>
    <div class="tab-bar">
        <?php $first = true; foreach ($tabs as $key => $tab): ?>
        <button class="tab-btn-other<?= $first ? ' active' : '' ?>" data-tab-other="<?= $key ?>"><?= $tab['label'] ?></button>
        <?php $first = false; endforeach; ?>
    </div>

    <?php $first = true; foreach ($tabs as $key => $tab): ?>
    <div class="tab-panel-other<?= $first ? ' active' : '' ?>" id="panel-other-<?= $key ?>">
        <div class="other-sub-title">🎙️ Podcast</div>
        <div class="stat-numbers">
            <div class="stat-box">
                <div class="stat-num"><?= number_format($tab['podcast_plays']) ?></div>
                <div class="stat-label">Plays</div>
            </div>
            <div class="stat-box">
                <div class="stat-num complete"><?= number_format($tab['podcast_complete']) ?></div>
                <div class="stat-label">Complete</div>
            </div>
            <div class="stat-box">
                <div class="stat-num"><?= number_format($tab['podcast_listeners']) ?></div>
                <div class="stat-label">Listeners</div>
            </div>
        </div>
        <div class="stat-meta">
            <span>Listen Time: <strong><?= fmt_time($tab['podcast_listen_time']) ?></strong></span>
        </div>

        <div style="border-top: 1px solid #f0f0f0; margin: 12px 0;"></div>

        <div class="other-sub-title">🎬 Promo Video</div>
        <div class="stat-numbers">
            <div class="stat-box">
                <div class="stat-num"><?= number_format($tab['promo_views']) ?></div>
                <div class="stat-label">Views</div>
            </div>
            <div class="stat-box">
                <div class="stat-num complete"><?= number_format($tab['promo_complete']) ?></div>
                <div class="stat-label">Complete</div>
            </div>
            <div class="stat-box">
                <div class="stat-num"><?= number_format($tab['promo_viewers']) ?></div>
                <div class="stat-label">Viewers</div>
            </div>
        </div>
        <div class="stat-meta">
            <span>Watch Time: <strong><?= fmt_time($tab['promo_watch_time']) ?></strong></span>
        </div>
    </div>
    <?php $first = false; endforeach; ?>
</div>

<script>
(function() {
    var btns = document.querySelectorAll('.tab-btn');
    var panels = document.querySelectorAll('.tab-panel');
    btns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            btns.forEach(function(b) { b.classList.remove('active'); });
            panels.forEach(function(p) { p.classList.remove('active'); });
            btn.classList.add('active');
            document.getElementById('panel-' + btn.getAttribute('data-tab')).classList.add('active');
        });
    });

    var btns2 = document.querySelectorAll('.tab-btn-other');
    var panels2 = document.querySelectorAll('.tab-panel-other');
    btns2.forEach(function(btn) {
        btn.addEventListener('click', function() {
            btns2.forEach(function(b) { b.classList.remove('active'); });
            panels2.forEach(function(p) { p.classList.remove('active'); });
            btn.classList.add('active');
            document.getElementById('panel-other-' + btn.getAttribute('data-tab-other')).classList.add('active');
        });
    });
})();
</script>

<?php
$content = ob_get_clean();

$extra_css = '
    .alert { padding: 12px 14px; border-radius: 10px; margin-bottom: 16px; font-size: 13px; }
    .alert-warning { background: #fff8e1; border: 1px solid #ffe082; }
    .alert-item { padding: 6px 0; border-bottom: 1px solid rgba(0,0,0,0.06); }
    .alert-item:last-child { border-bottom: none; }

    /* Tabbed Stats Card */
    .stats-card { background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 16px; overflow: hidden; }
    .tab-bar { display: flex; gap: 6px; padding: 12px 12px 0; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .tab-btn { padding: 8px 16px; border: 1px solid #ddd; background: #f8f8f8; font-size: 13px; font-weight: 600; color: #888; cursor: pointer; border-radius: 8px 8px 0 0; white-space: nowrap; transition: all 0.2s; }
    .tab-btn.active { background: #667eea; color: white; border-color: #667eea; }
    .tab-btn:not(.active):hover { background: #eee; color: #555; }
    .tab-panel { display: none; padding: 20px 16px; border-top: 2px solid #667eea; }
    .tab-panel.active { display: block; }

    .stat-numbers { display: flex; justify-content: space-around; text-align: center; margin-bottom: 16px; }
    .stat-box { flex: 1; }
    .stat-num { font-size: 28px; font-weight: 700; color: #667eea; }
    .stat-num.complete { color: #4caf50; }
    .stat-num.skip { color: #ef5350; }
    .stat-label { font-size: 11px; color: #888; margin-top: 2px; }

    .stat-hint { font-size: 9px; color: #aaa; margin-top: 1px; }
    .stat-meta { display: flex; justify-content: center; gap: 24px; font-size: 13px; color: #666; margin-bottom: 10px; }
    .stat-top-song { font-size: 13px; color: #555; text-align: center; margin-bottom: 16px; }

    .tab-lists { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; border-top: 1px solid #f0f0f0; padding-top: 12px; }
    .stat-top-list-title { font-size: 13px; font-weight: 600; color: #667eea; margin-bottom: 8px; }
    .list-hint { font-weight: 400; font-size: 10px; color: #aaa; }
    .top-plays.complete { color: #4caf50; }

    /* Quick Stats Row */
    .quick-row { display: flex; background: white; border-radius: 12px; padding: 12px 8px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    .quick-stat { flex: 1; text-align: center; border-right: 1px solid #eee; }
    .quick-stat:last-child { border-right: none; }
    .quick-num { font-size: 20px; font-weight: 700; color: #667eea; display: block; }
    .quick-label { font-size: 11px; color: #888; }

    /* Grid */
    .dash-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
    @media (max-width: 800px) { .dash-grid { grid-template-columns: 1fr; } }

    .card.compact { padding: 14px; margin-bottom: 0; }
    .card-title-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
    .card-title-sm { font-size: 14px; font-weight: 600; color: #667eea; margin-bottom: 0; }
    .card-count { font-size: 11px; color: #aaa; }
    .see-all { display: block; text-align: center; padding: 10px 0 2px; font-size: 12px; color: #667eea; text-decoration: none; font-weight: 500; }
    .see-all:hover { text-decoration: underline; }

    .empty-msg { color: #888; font-size: 13px; text-align: center; padding: 20px; }

    .activity-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f5f5f5; }
    .activity-row:last-child { border-bottom: none; }
    .activity-main { flex: 1; min-width: 0; }
    .activity-song { font-size: 13px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .activity-sub { font-size: 11px; color: #888; margin-top: 2px; }
    .activity-sub .device-link { color: #667eea; text-decoration: none; }
    .activity-sub .device-link:hover { text-decoration: underline; }
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

    /* Other section (podcast/promo in tabs) */
    .other-section { border-top: 1px solid #f0f0f0; padding-top: 12px; margin-top: 12px; }
    .other-title { font-size: 13px; font-weight: 600; color: #667eea; margin-bottom: 8px; }
    .other-row { display: flex; align-items: flex-start; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f5f5f5; }
    .other-row:last-child { border-bottom: none; }
    .other-icon { font-size: 18px; line-height: 1; }
    .other-main { flex: 1; min-width: 0; }
    .other-name { font-size: 13px; font-weight: 500; }
    .other-detail { font-size: 11px; color: #888; margin-top: 2px; }
    .other-sub-title { font-size: 13px; font-weight: 600; color: #555; margin-bottom: 10px; }
    .tab-btn-other { padding: 8px 16px; border: 1px solid #ddd; background: #f8f8f8; font-size: 13px; font-weight: 600; color: #888; cursor: pointer; border-radius: 8px 8px 0 0; white-space: nowrap; transition: all 0.2s; }
    .tab-btn-other.active { background: #667eea; color: white; border-color: #667eea; }
    .tab-btn-other:not(.active):hover { background: #eee; color: #555; }
    .tab-panel-other { display: none; padding: 20px 16px; border-top: 2px solid #667eea; }
    .tab-panel-other.active { display: block; }

    @media (max-width: 600px) {
        .tab-lists { grid-template-columns: 1fr; }
    }
    @media (max-width: 500px) {
        .stat-numbers { flex-wrap: wrap; }
        .stat-box { width: 50%; margin-bottom: 12px; }
        .stat-num { font-size: 22px; }
        .quick-num { font-size: 18px; }
        .tab-btn { padding: 10px 12px; font-size: 12px; }
    }
';

include(APPPATH . 'views/admin/layout.php');
?>
