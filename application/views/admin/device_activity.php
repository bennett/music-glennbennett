<?php
// Detect device type for display
$ua = strtolower($device->user_agent ?? '');
if (strpos($ua, 'iphone') !== false) { $emoji = '📱'; $device_type = 'iPhone'; }
elseif (strpos($ua, 'ipad') !== false) { $emoji = '📱'; $device_type = 'iPad'; }
elseif (strpos($ua, 'android') !== false) { $emoji = '📱'; $device_type = 'Android'; }
elseif (strpos($ua, 'mac') !== false) { $emoji = '💻'; $device_type = 'Mac'; }
elseif (strpos($ua, 'windows') !== false) { $emoji = '💻'; $device_type = 'Windows'; }
else { $emoji = '❓'; $device_type = 'Unknown'; }

$short_id = strtoupper(substr($device->id, -5));
$display_name = $device->name ?: $device_type . '-' . $short_id;

$page_title = htmlspecialchars($display_name) . ' — Activity';
$page_icon = $emoji;
$active_page = 'devices';

if (!function_exists('to_pacific')) {
    function to_pacific($utc_time, $format = 'M j, g:ia') {
        if (empty($utc_time)) return '';
        $dt = new DateTime($utc_time, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('America/Los_Angeles'));
        return $dt->format($format);
    }
}

$start = ($current_page - 1) * $per_page + 1;
$end = min($current_page * $per_page, $total);

$header_actions = '<a href="' . site_url('admin/device/' . urlencode($device->id)) . '" class="btn btn-secondary btn-sm">← Back</a>';

ob_start();
?>

<!-- Count + Pagination Header -->
<div class="activity-header">
    <span class="showing"><?= $start ?>–<?= $end ?> of <?= number_format($total) ?> plays</span>
    <?php if ($total_pages > 1): ?>
    <div class="pager">
        <?php if ($current_page > 1): ?>
            <a href="?page=<?= $current_page - 1 ?>" class="page-btn">&laquo; Prev</a>
        <?php endif; ?>
        <span class="page-info">Page <?= $current_page ?> of <?= $total_pages ?></span>
        <?php if ($current_page < $total_pages): ?>
            <a href="?page=<?= $current_page + 1 ?>" class="page-btn">Next &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Activity List -->
<div class="card compact">
    <?php foreach ($plays as $p):
        $pct = $p->percent ?? 0;
        $color = $pct >= 90 ? '#4caf50' : ($pct >= 50 ? '#ff9800' : '#ef5350');
    ?>
    <div class="activity-row">
        <div class="activity-main">
            <div class="activity-song"><?= htmlspecialchars($p->title) ?></div>
            <div class="activity-sub">
                <?php if (!empty($p->album_title)): ?><?= htmlspecialchars($p->album_title) ?> · <?php endif; ?>
                <?= to_pacific($p->played_at) ?>
            </div>
        </div>
        <div class="activity-pct" style="color:<?= $color ?>"><?= $pct ?>%</div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($plays)): ?>
        <div class="empty-msg">No plays found.</div>
    <?php endif; ?>
</div>

<!-- Bottom Pagination -->
<?php if ($total_pages > 1): ?>
<div class="activity-header" style="margin-top:12px;">
    <span></span>
    <div class="pager">
        <?php if ($current_page > 1): ?>
            <a href="?page=<?= $current_page - 1 ?>" class="page-btn">&laquo; Prev</a>
        <?php endif; ?>
        <span class="page-info">Page <?= $current_page ?> of <?= $total_pages ?></span>
        <?php if ($current_page < $total_pages): ?>
            <a href="?page=<?= $current_page + 1 ?>" class="page-btn">Next &raquo;</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();

$extra_css = '
    .activity-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
    .showing { font-size: 13px; color: #888; }
    .pager { display: flex; align-items: center; gap: 10px; }
    .page-btn { font-size: 13px; color: #667eea; text-decoration: none; font-weight: 500; }
    .page-btn:hover { text-decoration: underline; }
    .page-info { font-size: 12px; color: #aaa; }

    .card.compact { padding: 14px; }
    .activity-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f5f5f5; }
    .activity-row:last-child { border-bottom: none; }
    .activity-main { flex: 1; min-width: 0; }
    .activity-song { font-size: 13px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .activity-sub { font-size: 11px; color: #888; margin-top: 2px; }
    .activity-pct { font-size: 13px; font-weight: 600; margin-left: 10px; }
    .empty-msg { color: #888; font-size: 13px; text-align: center; padding: 20px; }
';

include(APPPATH . 'views/admin/layout.php');
?>
