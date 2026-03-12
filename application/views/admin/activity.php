<?php
$page_title = 'Activity';
$page_icon = '📋';
$active_page = 'dashboard';

if (!function_exists('to_pacific')) {
    function to_pacific($utc_time, $format = 'M j, g:ia') {
        if (empty($utc_time)) return '';
        $dt = new DateTime($utc_time, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('America/Los_Angeles'));
        return $dt->format($format);
    }
}

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

$start = ($current_page - 1) * $per_page + 1;
$end = min($current_page * $per_page, $total);

ob_start();
?>

<!-- Type Toggle -->
<div class="type-toggle">
    <a href="<?= site_url('admin/activity/all') ?>" class="toggle-btn<?= $type === 'all' ? ' active' : '' ?>">All Plays</a>
    <a href="<?= site_url('admin/activity/complete') ?>" class="toggle-btn<?= $type === 'complete' ? ' active' : '' ?>">Complete</a>
</div>

<!-- Count + Pagination Header -->
<div class="activity-header">
    <span class="showing"><?= $start ?>–<?= $end ?> of <?= number_format($total) ?></span>
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
    <?php foreach ($plays as $play):
        $pct = $play->percent ?? 0;
        $color = $pct >= 90 ? '#4caf50' : ($pct >= 50 ? '#ff9800' : '#ef5350');
    ?>
    <div class="activity-row">
        <div class="activity-main">
            <div class="activity-song"><?= htmlspecialchars($play->title) ?></div>
            <div class="activity-sub">
                <a href="<?= site_url('admin/device/' . urlencode($play->device_id)) ?>" class="device-link"><?= htmlspecialchars(device_label($play)) ?></a>
                <?php if (!empty($play->album_title)): ?> · <?= htmlspecialchars($play->album_title) ?><?php endif; ?>
                · <?= to_pacific($play->played_at) ?>
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
    .type-toggle { display: flex; gap: 6px; margin-bottom: 16px; }
    .toggle-btn { padding: 8px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; color: #888; background: #f5f5f5; text-decoration: none; transition: all 0.2s; }
    .toggle-btn.active { background: #667eea; color: white; }
    .toggle-btn:hover:not(.active) { background: #eee; color: #555; }

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
    .device-link { color: #667eea; text-decoration: none; }
    .device-link:hover { text-decoration: underline; }
    .empty-msg { color: #888; font-size: 13px; text-align: center; padding: 20px; }
';

include(APPPATH . 'views/admin/layout.php');
?>
