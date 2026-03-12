<?php
$page_title = 'Devices';
$page_icon = '📱';
$active_page = 'devices';

// Helper to convert UTC timestamp to Pacific
if (!function_exists('to_pacific')) {
    function to_pacific($utc_time, $format = 'M j, g:ia') {
        if (empty($utc_time)) return '';
        $dt = new DateTime($utc_time, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('America/Los_Angeles'));
        return $dt->format($format);
    }
}

// Separate devices into categories
$active_devices = [];
$inactive_devices = [];
$excluded_devices = [];

foreach ($devices as $d) {
    if (!empty($d->excluded)) {
        $excluded_devices[] = $d;
    } else if ($d->total_plays > 0) {
        $active_devices[] = $d;
    } else {
        $inactive_devices[] = $d;
    }
}
$active_count = count($active_devices);
$inactive_count = count($inactive_devices);
$excluded_count = count($excluded_devices);

ob_start();
?>

<!-- Stats -->
<div class="quick-row">
    <div class="quick-stat">
        <span class="quick-num"><?= $active_count ?></span>
        <span class="quick-label">Active</span>
    </div>
    <div class="quick-stat">
        <span class="quick-num" style="color:#888;"><?= $inactive_count ?></span>
        <span class="quick-label">Inactive</span>
    </div>
    <div class="quick-stat">
        <span class="quick-num" style="color:#4caf50;"><?= $active_today ?></span>
        <span class="quick-label">Today</span>
    </div>
    <?php if ($excluded_count > 0): ?>
    <div class="quick-stat">
        <span class="quick-num" style="color:#ff9800;"><?= $excluded_count ?></span>
        <span class="quick-label">Excluded</span>
    </div>
    <?php endif; ?>
</div>

<!-- Active Devices -->
<div class="card compact">
    <div class="card-title-sm">🟢 Active Devices (<?= $active_count ?>)</div>
    
    <?php if (empty($active_devices)): ?>
        <div class="empty-state">No devices with plays yet.</div>
    <?php endif; ?>
    
    <?php foreach ($active_devices as $d): 
        $ua = strtolower($d->user_agent ?? '');
        $icon = '❓';
        if (strpos($ua, 'iphone') !== false || (strpos($ua, 'android') !== false && strpos($ua, 'mobile') !== false)) {
            $icon = '📱';
        } elseif (strpos($ua, 'ipad') !== false || strpos($ua, 'tablet') !== false) {
            $icon = '📱';
        } elseif (strpos($ua, 'mac') !== false || strpos($ua, 'windows') !== false || strpos($ua, 'linux') !== false) {
            $icon = '💻';
        }
        
        $friendly_ua = $d->user_agent ?? 'Unknown';
        if (preg_match('/iPhone/', $friendly_ua)) $device_type = 'iPhone';
        elseif (preg_match('/iPad/', $friendly_ua)) $device_type = 'iPad';
        elseif (preg_match('/Android/', $friendly_ua)) $device_type = 'Android';
        elseif (preg_match('/Macintosh/', $friendly_ua)) $device_type = 'Mac';
        elseif (preg_match('/Windows/', $friendly_ua)) $device_type = 'Windows';
        else $device_type = 'Unknown Device';
        
        // Generate default name: Type + short ID
        $short_id = strtoupper(substr($d->id, -5));
        $default_name = $device_type . '-' . $short_id;
        $custom_name = $d->name ?: $default_name;
        // Check if seen in last hour - last_seen is UTC, so compare with UTC now
        $is_recent = $d->last_seen && (strtotime('now UTC') - strtotime($d->last_seen . ' UTC')) < 3600;
    ?>
    <div class="device-row">
        <div class="device-icon"><?= $icon ?></div>
        <div class="device-info">
            <div class="device-name-row">
                <a href="<?= site_url('admin/device/' . urlencode($d->id)) ?>" class="device-link"><?= htmlspecialchars($custom_name) ?></a>
                <?php if ($is_recent): ?><span class="badge-online">Online</span><?php endif; ?>
                <button class="btn-edit" onclick="editName('<?= htmlspecialchars($d->id, ENT_QUOTES) ?>', '<?= htmlspecialchars($d->name ?? '', ENT_QUOTES) ?>')" title="Rename">✏️</button>
            </div>
            <div class="name-edit" id="name-edit-<?= htmlspecialchars($d->id) ?>">
                <input type="text" id="name-input-<?= htmlspecialchars($d->id) ?>" placeholder="Custom name...">
                <button class="btn btn-sm btn-primary" onclick="saveName('<?= htmlspecialchars($d->id, ENT_QUOTES) ?>')">Save</button>
                <button class="btn btn-sm" onclick="cancelEdit('<?= htmlspecialchars($d->id, ENT_QUOTES) ?>')">Cancel</button>
            </div>
            <div class="device-meta">
                <?= $device_type ?> · <?= $d->total_plays ?> plays
                <?php if ($d->total_favorites): ?> · <?= $d->total_favorites ?> ❤️<?php endif; ?>
                <?php if ($d->avg_percent): ?> · <?= round($d->avg_percent) ?>%<?php endif; ?>
            </div>
        </div>
        <div class="device-actions">
            <button onclick="markAdmin('<?= htmlspecialchars($d->id, ENT_QUOTES) ?>')" class="btn-icon" title="Mark as Admin">👤</button>
            <button onclick="toggleExclude('<?= htmlspecialchars($d->id, ENT_QUOTES) ?>')" class="btn-icon" title="Exclude">✕</button>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Inactive Devices (Collapsible) -->
<?php if ($inactive_count > 0): ?>
<details class="card compact">
    <summary class="card-summary">
        <span class="card-title-sm" style="margin:0;">😴 Inactive Devices (<?= $inactive_count ?>)</span>
        <span class="summary-hint">0 plays</span>
    </summary>
    <div class="details-content">
        <?php 
        foreach ($inactive_devices as $d): 
            $ua = strtolower($d->user_agent ?? '');
            $icon = '❓';
            if (strpos($ua, 'iphone') !== false || strpos($ua, 'android') !== false) $icon = '📱';
            elseif (strpos($ua, 'mac') !== false || strpos($ua, 'windows') !== false) $icon = '💻';
            
            $friendly_ua = $d->user_agent ?? 'Unknown';
            if (preg_match('/iPhone/', $friendly_ua)) $device_type = 'iPhone';
            elseif (preg_match('/iPad/', $friendly_ua)) $device_type = 'iPad';
            elseif (preg_match('/Android/', $friendly_ua)) $device_type = 'Android';
            elseif (preg_match('/Macintosh/', $friendly_ua)) $device_type = 'Mac';
            elseif (preg_match('/Windows/', $friendly_ua)) $device_type = 'Windows';
            else $device_type = 'Unknown';
            
            $short_id = strtoupper(substr($d->id, -5));
            $default_name = $device_type . '-' . $short_id;
            $custom_name = $d->name ?: $default_name;
        ?>
        <div class="device-row inactive">
            <div class="device-icon"><?= $icon ?></div>
            <a href="<?= site_url('admin/device/' . urlencode($d->id)) ?>" class="device-info">
                <div class="device-name-row"><?= htmlspecialchars($custom_name) ?></div>
                <div class="device-meta"><?= $device_type ?> · First seen: <?= $d->first_seen ? to_pacific($d->first_seen, 'M j, Y g:ia') : 'Unknown' ?></div>
            </a>
            <div class="device-actions">
                <button onclick="event.preventDefault();toggleExclude('<?= htmlspecialchars($d->id, ENT_QUOTES) ?>')" class="btn-icon" title="Exclude">✕</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</details>
<?php endif; ?>

<!-- Excluded Devices (Collapsible) -->
<?php if ($excluded_count > 0): ?>
<details class="card compact excluded-section">
    <summary class="card-summary">
        <span class="card-title-sm" style="margin:0;color:#888;">🚫 Excluded Devices (<?= $excluded_count ?>)</span>
    </summary>
    <div class="details-content">
        <?php foreach ($excluded_devices as $d): 
            $display_name = $d->name ?: 'Device ' . substr($d->id, 0, 8);
            $is_admin_device = ($d->excluded == 2);
        ?>
        <div class="device-row excluded">
            <div class="device-info" style="flex:1;">
                <div class="device-name-row">
                    <?= htmlspecialchars($display_name) ?>
                    <?php if ($is_admin_device): ?>
                        <span class="badge-admin">Admin</span>
                    <?php else: ?>
                        <span class="badge-manual">Manual</span>
                    <?php endif; ?>
                </div>
            </div>
            <button onclick="toggleExclude('<?= htmlspecialchars($d->id, ENT_QUOTES) ?>')" class="btn btn-sm" style="background:#e8f5e9;color:#2e7d32;">Include</button>
        </div>
        <?php endforeach; ?>
    </div>
</details>
<?php endif; ?>

<?php
$content = ob_get_clean();

$extra_css = '
    .quick-row { display: flex; background: white; border-radius: 12px; padding: 12px 8px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    .quick-stat { flex: 1; text-align: center; border-right: 1px solid #eee; }
    .quick-stat:last-child { border-right: none; }
    .quick-num { font-size: 20px; font-weight: 700; color: #667eea; display: block; }
    .quick-label { font-size: 11px; color: #888; }
    
    .card.compact { padding: 14px; margin-bottom: 12px; }
    .card-title-sm { font-size: 14px; font-weight: 600; color: #667eea; margin-bottom: 12px; }
    .card-summary { display: flex; justify-content: space-between; align-items: center; cursor: pointer; padding: 0; }
    .card-summary::-webkit-details-marker { display: none; }
    .summary-hint { font-size: 11px; color: #aaa; }
    .details-content { margin-top: 12px; padding-top: 12px; border-top: 1px solid #eee; }
    
    .device-row { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f5f5f5; }
    .device-row:last-child { border-bottom: none; }
    .device-row.inactive { opacity: 0.7; }
    .device-row.excluded { opacity: 0.6; }
    .device-icon { font-size: 20px; flex-shrink: 0; }
    .device-info { flex: 1; min-width: 0; }
    .device-link { text-decoration: none; color: inherit; }
    .device-link:hover { color: #667eea; }
    .device-name-row { font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
    .device-meta { font-size: 11px; color: #888; margin-top: 2px; }
    .device-actions { display: flex; gap: 6px; }
    
    .btn-edit { background: none; border: none; cursor: pointer; font-size: 12px; opacity: 0.4; padding: 2px; }
    .btn-edit:hover { opacity: 1; }
    .name-edit { display: none; margin: 6px 0; align-items: center; gap: 6px; }
    .name-edit input { padding: 6px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; width: 150px; }
    .name-edit .btn { padding: 6px 10px; }
    
    .badge-online { background: #e8f5e9; color: #2e7d32; padding: 2px 6px; border-radius: 10px; font-size: 10px; }
    .badge-admin { background: #e3f2fd; color: #1565c0; padding: 2px 6px; border-radius: 10px; font-size: 10px; }
    .badge-manual { background: #fff3e0; color: #ef6c00; padding: 2px 6px; border-radius: 10px; font-size: 10px; }
    
    .btn-icon { background: none; border: none; cursor: pointer; font-size: 14px; opacity: 0.4; padding: 4px 8px; }
    .btn-icon:hover { opacity: 1; }
    
    .empty-state { text-align: center; padding: 20px; color: #888; font-size: 13px; }
    .excluded-section { background: #fafafa; }
    
    details[open] .card-summary { margin-bottom: 0; }
';

$exclude_url = site_url("admin/toggle_exclude");
$rename_url = site_url("admin/device_rename");
$admin_url = site_url("admin/mark_admin_device");

$extra_js = <<<JAVASCRIPT
<script>
function editName(id, currentName) {
    document.getElementById("name-edit-" + id).style.display = "flex";
    var input = document.getElementById("name-input-" + id);
    input.value = currentName;
    input.focus();
}

function cancelEdit(id) {
    document.getElementById("name-edit-" + id).style.display = "none";
}

function saveName(id) {
    var name = document.getElementById("name-input-" + id).value.trim();
    fetch("{$rename_url}", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "device_id=" + encodeURIComponent(id) + "&name=" + encodeURIComponent(name)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) location.reload();
    });
}

function toggleExclude(id) {
    fetch("{$exclude_url}", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "device_id=" + encodeURIComponent(id)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) location.reload();
    });
}

function markAdmin(id) {
    if (!confirm("Mark as admin device?\\n\\nAdmin devices are excluded from play stats.")) return;
    fetch("{$admin_url}", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "device_id=" + encodeURIComponent(id)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) location.reload();
    });
}

document.querySelectorAll(".name-edit input").forEach(function(input) {
    input.addEventListener("keydown", function(e) {
        if (e.key === "Enter") saveName(this.id.replace("name-input-", ""));
        if (e.key === "Escape") cancelEdit(this.id.replace("name-input-", ""));
    });
});
</script>
JAVASCRIPT;

include(APPPATH . 'views/admin/layout.php');
?>
