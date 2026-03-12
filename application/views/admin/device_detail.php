<?php
// Helper to convert UTC timestamp to Pacific
if (!function_exists('to_pacific')) {
    function to_pacific($utc_time, $format = 'M j, g:ia') {
        if (empty($utc_time)) return '';
        $dt = new DateTime($utc_time, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('America/Los_Angeles'));
        return $dt->format($format);
    }
}

// Detect device type
$ua = strtolower($device->user_agent ?? '');
if (strpos($ua, 'iphone') !== false) { $emoji = '📱'; $device_type = 'iPhone'; }
elseif (strpos($ua, 'ipad') !== false) { $emoji = '📱'; $device_type = 'iPad'; }
elseif (strpos($ua, 'android') !== false) { $emoji = '📱'; $device_type = 'Android'; }
elseif (strpos($ua, 'mac') !== false) { $emoji = '💻'; $device_type = 'Mac'; }
elseif (strpos($ua, 'windows') !== false) { $emoji = '💻'; $device_type = 'Windows'; }
else { $emoji = '❓'; $device_type = 'Unknown'; }

// Generate default name: Type + short ID
$short_id = strtoupper(substr($device->id, -5));
$default_name = $device_type . '-' . $short_id;
$display_name = $device->name ?: $default_name;

$page_title = htmlspecialchars($display_name);
$page_icon = $emoji;
$active_page = 'devices';

$total_plays = count($plays);
$total_favs = count($favorites);

// Exclusion status
$is_excluded = !empty($device->excluded);
$is_admin_device = ($device->excluded == 2);

// Calculate listening patterns (in Pacific time)
$listening_hours = [];
$listening_days = [];
foreach ($plays as $p) {
    if (!empty($p->played_at)) {
        $hour = (int)to_pacific($p->played_at, 'G');
        $day = to_pacific($p->played_at, 'l');
        $listening_hours[$hour] = ($listening_hours[$hour] ?? 0) + 1;
        $listening_days[$day] = ($listening_days[$day] ?? 0) + 1;
    }
}

// Find peak listening time
$peak_hour = 0;
$peak_count = 0;
foreach ($listening_hours as $h => $c) {
    if ($c > $peak_count) { $peak_hour = $h; $peak_count = $c; }
}
$peak_time = $peak_hour < 12 ? $peak_hour . 'am' : ($peak_hour == 12 ? '12pm' : ($peak_hour - 12) . 'pm');

// Find favorite day
arsort($listening_days);
$fav_day = !empty($listening_days) ? key($listening_days) : null;

// Calculate engagement rate (plays that went past 50%)
$engaged_plays = 0;
foreach ($plays as $p) {
    if (($p->percent ?? 0) >= 50) $engaged_plays++;
}
$engagement_rate = $total_plays > 0 ? round(($engaged_plays / $total_plays) * 100) : 0;

$header_actions = '<a href="' . site_url('admin/devices') . '" class="btn btn-secondary btn-sm">← Back</a>';

ob_start();
?>

<!-- Quick Stats Bar -->
<div class="quick-stats">
    <div class="qs-item">
        <div class="qs-num"><?= $total_plays ?></div>
        <div class="qs-label">plays</div>
    </div>
    <div class="qs-item">
        <div class="qs-num"><?= $avg_percent ?>%</div>
        <div class="qs-label">avg listen</div>
    </div>
    <div class="qs-item">
        <div class="qs-num"><?php 
            $mins = floor($total_listened / 60);
            if ($mins >= 60) echo floor($mins / 60) . 'h';
            elseif ($mins > 0) echo $mins . 'm';
            else echo '0';
        ?></div>
        <div class="qs-label">total time</div>
    </div>
    <div class="qs-item">
        <div class="qs-num"><?= $total_favs ?></div>
        <div class="qs-label">favorites</div>
    </div>
</div>

<!-- Device Info (collapsible) -->
<details class="card compact" open>
    <summary class="card-summary">
        <span><?= $emoji ?> <?= htmlspecialchars($display_name) ?></span>
        <span class="summary-meta"><?= $device->last_seen ? 'Last seen ' . to_pacific($device->last_seen, 'M j, g:ia') : '' ?></span>
    </summary>
    <div class="card-details">
        <div class="detail-row">
            <span class="detail-label">Name</span>
            <span class="detail-value" id="nameDisplay">
                <?= htmlspecialchars($display_name) ?> 
                <button class="edit-btn" onclick="toggleNameEdit()">✏️</button>
            </span>
        </div>
        <div class="name-edit-row" id="nameEditRow" style="display:none;">
            <input type="text" id="deviceName" value="<?= htmlspecialchars($device->name ?? '') ?>" placeholder="Custom name...">
            <button class="btn btn-primary btn-xs" onclick="saveName()">Save</button>
            <button class="btn btn-xs" onclick="toggleNameEdit()">Cancel</button>
        </div>
        <div class="detail-row">
            <span class="detail-label">Device Type</span>
            <span class="detail-value"><?= $device_type ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">First seen</span>
            <span class="detail-value"><?= $device->first_seen ? to_pacific($device->first_seen, 'M j, Y g:ia') : '—' ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Status</span>
            <span class="detail-value">
                <?php if ($is_admin_device): ?>
                    <span class="badge-admin">Admin Device</span>
                <?php elseif ($is_excluded): ?>
                    <span class="badge-manual">Excluded</span>
                <?php else: ?>
                    <span class="badge-active">Active</span>
                <?php endif; ?>
            </span>
        </div>
        <div class="detail-row">
            <span class="detail-label">User Agent</span>
            <span class="detail-value small"><?= htmlspecialchars(substr($device->user_agent ?? 'Unknown', 0, 100)) ?></span>
        </div>
    </div>
</details>

<!-- Behavior Insights -->
<?php if ($total_plays > 5): ?>
<div class="card compact">
    <div class="card-title-sm">📊 Behavior Insights</div>
    <div class="insights-grid">
        <div class="insight">
            <div class="insight-icon">🎯</div>
            <div class="insight-text">
                <strong><?= $engagement_rate ?>%</strong> engagement
                <span class="insight-sub"><?= $engaged_plays ?> of <?= $total_plays ?> plays past 50%</span>
            </div>
        </div>
        <?php if ($peak_count > 2): ?>
        <div class="insight">
            <div class="insight-icon">🕐</div>
            <div class="insight-text">
                <strong><?= $peak_time ?></strong> peak time
                <span class="insight-sub"><?= $peak_count ?> plays around this hour</span>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($fav_day): ?>
        <div class="insight">
            <div class="insight-icon">📅</div>
            <div class="insight-text">
                <strong><?= $fav_day ?>s</strong> most active
                <span class="insight-sub"><?= $listening_days[$fav_day] ?> plays on this day</span>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Activity Chart -->
<?php if (!empty($daily_plays)): ?>
<div class="card compact">
    <div class="card-title-sm">📈 Last 30 Days</div>
    <?php
    $max_plays = 1;
    foreach ($daily_plays as $dp) {
        if ($dp->plays > $max_plays) $max_plays = $dp->plays;
    }
    $day_map = [];
    foreach ($daily_plays as $dp) $day_map[$dp->play_date] = $dp->plays;
    $total_recent = array_sum($day_map);
    $pacific = new DateTimeZone('America/Los_Angeles');
    ?>
    <div class="chart-summary"><?= $total_recent ?> plays this month</div>
    <div class="mini-chart">
        <?php for ($i = 29; $i >= 0; $i--): 
            $chart_date = new DateTime("-{$i} days", $pacific);
            $date = $chart_date->format('Y-m-d');
            $count = $day_map[$date] ?? 0;
            $height = $count > 0 ? max(4, ($count / $max_plays) * 40) : 2;
            $bg = $count > 0 ? '#667eea' : '#e0e0e0';
        ?>
        <div class="mini-bar" style="height:<?= $height ?>px;background:<?= $bg ?>" title="<?= $chart_date->format('M j') ?>: <?= $count ?>"></div>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>

<!-- Top Songs -->
<?php if (!empty($top_songs)): ?>
<div class="card compact">
    <div class="card-title-sm">🔥 Top Songs</div>
    <?php foreach (array_slice($top_songs, 0, 5) as $ts): ?>
    <div class="song-row-compact">
        <div class="song-info-compact">
            <div class="song-title-compact"><?= htmlspecialchars($ts->title) ?></div>
            <div class="song-meta-compact"><?= $ts->plays ?> plays · <?= $ts->avg_percent ?? 0 ?>% avg</div>
        </div>
        <div class="mini-progress" title="<?= $ts->avg_percent ?? 0 ?>% average completion">
            <div class="mini-progress-fill" style="width:<?= $ts->avg_percent ?? 0 ?>%"></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Favorites -->
<?php if (!empty($favorites)): ?>
<div class="card compact">
    <div class="card-title-sm">❤️ Favorites (<?= $total_favs ?>)</div>
    <?php foreach (array_slice($favorites, 0, 5) as $fav): ?>
    <div class="song-row-compact">
        <div class="song-info-compact">
            <div class="song-title-compact"><?= htmlspecialchars($fav->title) ?></div>
            <div class="song-meta-compact"><?= htmlspecialchars($fav->artist ?? '') ?></div>
        </div>
        <div class="fav-date"><?= $fav->favorited_at ? to_pacific($fav->favorited_at, 'M j, g:ia') : '' ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Recent Activity -->
<?php if (!empty($plays)): ?>
<div class="card compact">
    <div class="card-title-row">
        <span class="card-title-sm">▶️ Recent Activity</span>
        <span class="card-count"><?= min(count($plays), 10) ?> of <?= number_format($total_play_count) ?></span>
    </div>
    <?php foreach (array_slice($plays, 0, 10) as $p):
        $pct = $p->percent ?? 0;
        $color = $pct >= 75 ? '#4caf50' : ($pct >= 40 ? '#ff9800' : '#ef5350');
    ?>
    <div class="activity-row">
        <div class="activity-song"><?= htmlspecialchars($p->title) ?></div>
        <div class="activity-meta">
            <span class="activity-pct" style="color:<?= $color ?>"><?= $pct ?>%</span>
            <span class="activity-time"><?= $p->played_at ? to_pacific($p->played_at, 'M j g:ia') : '' ?></span>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if ($total_plays > 10): ?>
    <div id="morePlays" style="display:none;">
        <?php foreach (array_slice($plays, 10) as $p):
            $pct = $p->percent ?? 0;
            $color = $pct >= 75 ? '#4caf50' : ($pct >= 40 ? '#ff9800' : '#ef5350');
        ?>
        <div class="activity-row">
            <div class="activity-song"><?= htmlspecialchars($p->title) ?></div>
            <div class="activity-meta">
                <span class="activity-pct" style="color:<?= $color ?>"><?= $pct ?>%</span>
                <span class="activity-time"><?= $p->played_at ? to_pacific($p->played_at, 'M j g:ia') : '' ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="more-link" id="showMoreBtn" onclick="toggleMorePlays()">+ <?= min($total_plays, 100) - 10 ?> more plays</div>
    <?php endif; ?>

    <?php if ($total_play_count > 100): ?>
    <a href="<?= site_url('admin/device_activity/' . urlencode($device->id)) ?>" class="see-all">See all <?= number_format($total_play_count) ?> plays &raquo;</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Actions (collapsible) -->
<details class="card compact">
    <summary class="card-summary">⚙️ Actions</summary>
    <div class="card-details">
        <div class="action-buttons">
            <?php if (!$is_excluded): ?>
            <button class="btn btn-secondary btn-sm" onclick="toggleExclude()">Exclude from Stats</button>
            <?php else: ?>
            <button class="btn btn-primary btn-sm" onclick="toggleExclude()">Include in Stats</button>
            <?php endif; ?>
            
            <?php if (!$is_admin_device): ?>
            <button class="btn btn-secondary btn-sm" onclick="markAdminDevice()">Mark as Admin</button>
            <?php endif; ?>
            
            <button class="btn btn-danger btn-sm" onclick="clearAllHistory()">Clear History</button>
            <button class="btn btn-danger btn-sm" onclick="deleteDevice()">Delete Device</button>
        </div>
    </div>
</details>

<?php
$content = ob_get_clean();

$extra_css = '
    .quick-stats { display: flex; background: white; border-radius: 12px; padding: 12px 8px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    .qs-item { flex: 1; text-align: center; border-right: 1px solid #eee; }
    .qs-item:last-child { border-right: none; }
    .qs-num { font-size: 20px; font-weight: 700; color: #333; }
    .qs-label { font-size: 11px; color: #888; margin-top: 2px; }
    
    .card.compact { padding: 14px; margin-bottom: 12px; }
    .card-title-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
    .card-title-sm { font-size: 14px; font-weight: 600; color: #667eea; margin-bottom: 12px; }
    .card-title-row .card-title-sm { margin-bottom: 0; }
    .card-count { font-size: 11px; color: #aaa; }
    .see-all { display: block; text-align: center; padding: 10px 0 2px; font-size: 12px; color: #667eea; text-decoration: none; font-weight: 500; }
    .see-all:hover { text-decoration: underline; }
    .card-summary { display: flex; justify-content: space-between; align-items: center; cursor: pointer; font-weight: 500; }
    .card-summary::-webkit-details-marker { display: none; }
    .summary-meta { font-size: 12px; color: #888; font-weight: 400; }
    .card-details { margin-top: 14px; padding-top: 14px; border-top: 1px solid #eee; }
    
    .detail-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f5f5f5; }
    .detail-row:last-child { border-bottom: none; }
    .detail-label { font-size: 13px; color: #888; }
    .detail-value { font-size: 13px; font-weight: 500; }
    .detail-value.small { font-size: 11px; font-weight: 400; color: #666; word-break: break-all; }
    
    .name-edit-row { display: flex; gap: 8px; padding: 10px 0; }
    .name-edit-row input { flex: 1; padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
    
    .edit-btn { background: none; border: none; cursor: pointer; opacity: 0.5; padding: 0 4px; }
    .edit-btn:hover { opacity: 1; }
    
    .badge-admin { background: #e3f2fd; color: #1565c0; padding: 3px 8px; border-radius: 10px; font-size: 11px; }
    .badge-manual { background: #fff3e0; color: #ef6c00; padding: 3px 8px; border-radius: 10px; font-size: 11px; }
    .badge-active { background: #e8f5e9; color: #2e7d32; padding: 3px 8px; border-radius: 10px; font-size: 11px; }
    
    .insights-grid { display: grid; gap: 12px; }
    .insight { display: flex; align-items: flex-start; gap: 10px; }
    .insight-icon { font-size: 20px; }
    .insight-text { font-size: 14px; }
    .insight-text strong { color: #333; }
    .insight-sub { display: block; font-size: 12px; color: #888; margin-top: 2px; }
    
    .chart-summary { font-size: 13px; color: #888; margin-bottom: 10px; }
    .mini-chart { display: flex; align-items: flex-end; gap: 2px; height: 44px; }
    .mini-bar { flex: 1; border-radius: 2px 2px 0 0; min-width: 4px; }
    
    .song-row-compact { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f5f5f5; }
    .song-row-compact:last-child { border-bottom: none; }
    .song-info-compact { flex: 1; min-width: 0; margin-right: 12px; }
    .song-title-compact { font-size: 14px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .song-meta-compact { font-size: 12px; color: #888; margin-top: 2px; }
    
    .mini-progress { width: 50px; height: 4px; background: #eee; border-radius: 2px; }
    .mini-progress-fill { height: 100%; background: #667eea; border-radius: 2px; }
    
    .fav-date { font-size: 12px; color: #aaa; }
    
    .activity-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f5f5f5; }
    .activity-row:last-child { border-bottom: none; }
    .activity-song { font-size: 13px; flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-right: 12px; }
    .activity-meta { display: flex; gap: 10px; align-items: center; font-size: 12px; }
    .activity-pct { font-weight: 600; }
    .activity-time { color: #aaa; }
    
    .more-link { text-align: center; padding: 10px; font-size: 13px; color: #667eea; }
    
    .action-buttons { display: flex; flex-wrap: wrap; gap: 8px; }
    .btn-xs { padding: 6px 12px; font-size: 12px; }
    .btn-sm { padding: 8px 14px; font-size: 13px; }
';

$device_id = $device->id;
$rename_url = site_url("admin/device_rename");
$exclude_url = site_url("admin/toggle_exclude");
$admin_url = site_url("admin/mark_admin_device");
$clear_url = site_url("admin/clear_device_history");
$delete_url = site_url("admin/delete_device");
$devices_url = site_url("admin/devices");

$extra_js = <<<JAVASCRIPT
<script>
function toggleMorePlays() {
    var more = document.getElementById("morePlays");
    var btn = document.getElementById("showMoreBtn");
    if (more.style.display === "none") {
        more.style.display = "block";
        btn.textContent = "Show less";
    } else {
        more.style.display = "none";
        btn.textContent = "+ " + more.children.length + " more plays";
    }
}

function toggleNameEdit() {
    var row = document.getElementById("nameEditRow");
    row.style.display = row.style.display === "none" ? "flex" : "none";
    if (row.style.display === "flex") {
        document.getElementById("deviceName").focus();
    }
}

function saveName() {
    var name = document.getElementById("deviceName").value.trim();
    fetch("{$rename_url}", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "device_id=" + encodeURIComponent("{$device_id}") + "&name=" + encodeURIComponent(name)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) location.reload();
    });
}

function toggleExclude() {
    fetch("{$exclude_url}", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "device_id=" + encodeURIComponent("{$device_id}")
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) location.reload();
    });
}

function markAdminDevice() {
    if (!confirm("Mark as admin device? This excludes it from stats.")) return;
    fetch("{$admin_url}", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "device_id=" + encodeURIComponent("{$device_id}")
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) location.reload();
    });
}

function clearAllHistory() {
    if (!confirm("Delete all play history for this device?")) return;
    fetch("{$clear_url}", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "device_id=" + encodeURIComponent("{$device_id}")
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) location.reload();
    });
}

function deleteDevice() {
    if (!confirm("Delete this device and all history?")) return;
    fetch("{$delete_url}", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "device_id=" + encodeURIComponent("{$device_id}")
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) window.location.href = "{$devices_url}";
    });
}
</script>
JAVASCRIPT;

include(APPPATH . 'views/admin/layout.php');
?>
