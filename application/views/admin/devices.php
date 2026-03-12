<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Devices - Bennett Music Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f5f5; color: #333; font-size: 14px; }
        
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; }
        .header h1 { font-size: 22px; margin-bottom: 15px; }
        .header-nav { display: flex; gap: 10px; flex-wrap: wrap; }
        .header-nav a { color: white; text-decoration: none; padding: 8px 14px; background: rgba(255,255,255,0.2); border-radius: 6px; font-size: 14px; }
        .header-nav a:hover { background: rgba(255,255,255,0.3); }
        
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px; margin-bottom: 25px; }
        .stat { background: white; padding: 15px; border-radius: 10px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .stat-num { font-size: 28px; font-weight: 700; color: #667eea; }
        .stat-label { font-size: 11px; color: #888; margin-top: 4px; }
        .stat.green .stat-num { color: #4caf50; }
        
        .section { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .section h2 { font-size: 16px; color: #667eea; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        
        .device-card { border: 1px solid #eee; border-radius: 10px; padding: 16px; margin-bottom: 12px; transition: all 0.2s; }
        .device-card:hover { border-color: #667eea; box-shadow: 0 2px 12px rgba(102,126,234,0.15); }
        .device-card:last-child { margin-bottom: 0; }
        
        .device-top { display: flex; align-items: center; gap: 14px; margin-bottom: 10px; }
        .device-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; flex-shrink: 0; }
        .device-icon.phone { background: #e3f2fd; }
        .device-icon.desktop { background: #f3e5f5; }
        .device-icon.tablet { background: #e8f5e9; }
        .device-icon.unknown { background: #f5f5f5; }
        
        .device-info { flex: 1; min-width: 0; }
        .device-name { font-weight: 600; font-size: 16px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .device-name .edit-btn { background: none; border: none; cursor: pointer; font-size: 13px; color: #667eea; padding: 2px 6px; border-radius: 4px; }
        .device-name .edit-btn:hover { background: #e3f2fd; }
        .device-ua { font-size: 12px; color: #888; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .device-id-text { font-family: monospace; font-size: 11px; color: #aaa; }
        
        .device-stats { display: flex; gap: 20px; margin-top: 10px; flex-wrap: wrap; }
        .device-stat { font-size: 13px; }
        .device-stat .num { font-weight: 600; color: #667eea; }
        .device-stat .lbl { color: #888; margin-left: 4px; }
        
        .device-meta { display: flex; gap: 20px; margin-top: 8px; font-size: 12px; color: #888; flex-wrap: wrap; }
        .device-last-song { margin-top: 6px; font-size: 13px; color: #555; }
        .device-last-song .song { font-weight: 500; }
        
        .badge { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 11px; font-weight: 500; }
        .badge-green { background: #e8f5e9; color: #2e7d32; }
        .badge-orange { background: #fff3e0; color: #ef6c00; }
        
        .view-link { color: #667eea; text-decoration: none; font-size: 13px; font-weight: 500; }
        .view-link:hover { text-decoration: underline; }
        
        .name-edit { display: none; align-items: center; gap: 8px; margin-top: 8px; }
        .name-edit input { padding: 6px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; width: 200px; }
        .name-edit button { padding: 6px 12px; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; }
        .name-edit .save-btn { background: #667eea; color: white; }
        .name-edit .cancel-btn { background: #eee; color: #666; }
        
        .exclude-btn { font-size: 11px; padding: 4px 10px; border: 1px solid #ddd; border-radius: 4px; background: #fff; color: #666; cursor: pointer; }
        .exclude-btn:hover { background: #f5f5f5; }
        
        .empty { text-align: center; padding: 40px; color: #888; }
        
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        
        /* Collapsible excluded section */
        .excluded-section { background: #fafafa; border: 1px solid #eee; }
        .excluded-section h2 { color: #888; cursor: pointer; user-select: none; }
        .excluded-section h2:hover { color: #666; }
        .excluded-toggle { font-size: 12px; color: #aaa; margin-left: auto; }
        .excluded-content { display: none; }
        .excluded-content.show { display: block; }
        .excluded-section .device-card { opacity: 0.7; border-left: 3px solid #ff9800; }
        .include-btn { font-size: 11px; padding: 4px 10px; border: 1px solid #ff9800; border-radius: 4px; background: #fff3e0; color: #e65100; cursor: pointer; }
        .include-btn:hover { background: #ffe0b2; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Bennett Music Admin</h1>
        <div class="header-nav">
            <a href="<?= site_url('admin') ?>">Dashboard</a>
            <a href="<?= site_url('/') ?>">Player</a>
            <a href="<?= site_url('admin/devices') ?>">Devices</a>
            <a href="<?= site_url('admin/songs') ?>">Songs</a>
            <a href="<?= site_url('auth/logout') ?>">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($this->session->flashdata('error')): ?>
            <div class="alert alert-error"><?= $this->session->flashdata('error') ?></div>
        <?php endif; ?>
        
        <?php
        // Separate included and excluded devices
        $included_devices = [];
        $excluded_devices = [];
        foreach ($devices as $d) {
            if (!empty($d->excluded)) {
                $excluded_devices[] = $d;
            } else {
                $included_devices[] = $d;
            }
        }
        $included_count = count($included_devices);
        $excluded_count = count($excluded_devices);
        ?>
        
        <div class="stats">
            <div class="stat">
                <div class="stat-num"><?= $included_count ?></div>
                <div class="stat-label">Devices</div>
            </div>
            <div class="stat green">
                <div class="stat-num"><?= $active_today ?></div>
                <div class="stat-label">Active Today</div>
            </div>
        </div>
        
        <!-- INCLUDED DEVICES -->
        <div class="section">
            <h2>Devices (<?= $included_count ?>)</h2>
            
            <?php if (empty($included_devices)): ?>
                <div class="empty">No devices. <?php if ($excluded_count > 0): ?>All devices are currently excluded.<?php else: ?>Devices appear once someone opens the player.<?php endif; ?></div>
            <?php endif; ?>
            
            <?php foreach ($included_devices as $d): 
                $ua = strtolower($d->user_agent ?? '');
                $icon_type = 'unknown'; $icon_emoji = '?';
                if (strpos($ua, 'iphone') !== false || (strpos($ua, 'android') !== false && strpos($ua, 'mobile') !== false)) {
                    $icon_type = 'phone'; $icon_emoji = 'P';
                } elseif (strpos($ua, 'ipad') !== false || strpos($ua, 'tablet') !== false) {
                    $icon_type = 'tablet'; $icon_emoji = 'T';
                } elseif (strpos($ua, 'mac') !== false || strpos($ua, 'windows') !== false || strpos($ua, 'linux') !== false) {
                    $icon_type = 'desktop'; $icon_emoji = 'D';
                }
                
                $friendly_ua = $d->user_agent ?? 'Unknown';
                if (preg_match('/iPhone/', $friendly_ua)) $device_type = 'iPhone';
                elseif (preg_match('/iPad/', $friendly_ua)) $device_type = 'iPad';
                elseif (preg_match('/Android/', $friendly_ua)) $device_type = 'Android';
                elseif (preg_match('/Macintosh/', $friendly_ua)) $device_type = 'Mac';
                elseif (preg_match('/Windows/', $friendly_ua)) $device_type = 'Windows';
                else $device_type = 'Unknown Device';
                
                if (preg_match('/Safari.*Version\/([0-9.]+)/', $friendly_ua, $m) && strpos($friendly_ua, 'Chrome') === false) $browser = 'Safari ' . $m[1];
                elseif (preg_match('/Chrome\/([0-9.]+)/', $friendly_ua, $m)) $browser = 'Chrome ' . explode('.', $m[1])[0];
                elseif (preg_match('/Firefox\/([0-9.]+)/', $friendly_ua, $m)) $browser = 'Firefox ' . explode('.', $m[1])[0];
                else $browser = '';
                
                $display_name = $d->name ?: $device_type . ($browser ? ' - ' . $browser : '');
                $is_recent = $d->last_seen && (time() - strtotime($d->last_seen)) < 3600;
            ?>
            <div class="device-card">
                <div class="device-top">
                    <div class="device-icon <?= $icon_type ?>"><?= $icon_emoji ?></div>
                    <div class="device-info">
                        <div class="device-name">
                            <span id="name-display-<?= htmlspecialchars($d->id) ?>"><?= htmlspecialchars($display_name) ?></span>
                            <?php if ($is_recent): ?>
                                <span class="badge badge-green">Online</span>
                            <?php endif; ?>
                            <button class="edit-btn" onclick="editName('<?= htmlspecialchars($d->id, ENT_QUOTES) ?>', '<?= htmlspecialchars($d->name ?? '', ENT_QUOTES) ?>')">Edit</button>
                        </div>
                        <div class="name-edit" id="name-edit-<?= htmlspecialchars($d->id) ?>">
                            <input type="text" id="name-input-<?= htmlspecialchars($d->id) ?>" placeholder="Enter a name for this device...">
                            <button class="save-btn" onclick="saveName('<?= htmlspecialchars($d->id, ENT_QUOTES) ?>')">Save</button>
                            <button class="cancel-btn" onclick="cancelEdit('<?= htmlspecialchars($d->id, ENT_QUOTES) ?>')">Cancel</button>
                        </div>
                        <div class="device-ua"><?= htmlspecialchars(substr($d->user_agent ?? 'Unknown', 0, 120)) ?></div>
                        <div class="device-id-text"><?= htmlspecialchars($d->id) ?><?php if (!empty($d->ip_address)): ?> | IP: <?= htmlspecialchars($d->ip_address) ?><?php endif; ?></div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;">
                        <a href="<?= site_url('admin/device/' . urlencode($d->id)) ?>" class="view-link">View Detail &rarr;</a>
                        <button onclick="toggleExclude('<?= htmlspecialchars($d->id, ENT_QUOTES) ?>')" class="exclude-btn">Exclude from Stats</button>
                    </div>
                </div>
                
                <div class="device-stats">
                    <div class="device-stat">
                        <span class="num"><?= $d->total_plays ?></span><span class="lbl">plays</span>
                    </div>
                    <div class="device-stat">
                        <span class="num"><?= $d->total_favorites ?></span><span class="lbl">favorites</span>
                    </div>
                    <?php if ($d->avg_percent): ?>
                    <div class="device-stat">
                        <span class="num"><?= $d->avg_percent ?>%</span><span class="lbl">avg listened</span>
                    </div>
                    <?php endif; ?>
                    <?php if ($d->total_listened > 0): ?>
                    <div class="device-stat">
                        <span class="num"><?php 
                            $mins = floor($d->total_listened / 60);
                            if ($mins >= 60) echo floor($mins / 60) . 'h ' . ($mins % 60) . 'm';
                            else echo $mins . 'm';
                        ?></span><span class="lbl">total time</span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($d->last_song): ?>
                <div class="device-last-song">
                    Last played: <span class="song"><?= htmlspecialchars($d->last_song) ?></span>
                    <?php if ($d->last_played): ?>
                        <span style="color:#aaa;margin-left:6px;"><?= date('M j g:ia', strtotime($d->last_played)) ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <div class="device-meta">
                    <span>First seen: <?= $d->first_seen ? date('M j, Y g:ia', strtotime($d->first_seen)) : 'Unknown' ?></span>
                    <span>Last seen: <?= $d->last_seen ? date('M j, Y g:ia', strtotime($d->last_seen)) : 'Unknown' ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- EXCLUDED DEVICES (Collapsible) -->
        <?php if ($excluded_count > 0): ?>
        <div class="section excluded-section">
            <h2 onclick="toggleExcludedSection()">
                Excluded Devices (<?= $excluded_count ?>)
                <span class="excluded-toggle" id="excludedToggle">Show</span>
            </h2>
            
            <div class="excluded-content" id="excludedContent">
                <?php foreach ($excluded_devices as $d): 
                    $ua = strtolower($d->user_agent ?? '');
                    $icon_type = 'unknown'; $icon_emoji = '?';
                    if (strpos($ua, 'iphone') !== false || (strpos($ua, 'android') !== false && strpos($ua, 'mobile') !== false)) {
                        $icon_type = 'phone'; $icon_emoji = 'P';
                    } elseif (strpos($ua, 'ipad') !== false || strpos($ua, 'tablet') !== false) {
                        $icon_type = 'tablet'; $icon_emoji = 'T';
                    } elseif (strpos($ua, 'mac') !== false || strpos($ua, 'windows') !== false || strpos($ua, 'linux') !== false) {
                        $icon_type = 'desktop'; $icon_emoji = 'D';
                    }
                    
                    $friendly_ua = $d->user_agent ?? 'Unknown';
                    if (preg_match('/iPhone/', $friendly_ua)) $device_type = 'iPhone';
                    elseif (preg_match('/iPad/', $friendly_ua)) $device_type = 'iPad';
                    elseif (preg_match('/Android/', $friendly_ua)) $device_type = 'Android';
                    elseif (preg_match('/Macintosh/', $friendly_ua)) $device_type = 'Mac';
                    elseif (preg_match('/Windows/', $friendly_ua)) $device_type = 'Windows';
                    else $device_type = 'Unknown Device';
                    
                    if (preg_match('/Safari.*Version\/([0-9.]+)/', $friendly_ua, $m) && strpos($friendly_ua, 'Chrome') === false) $browser = 'Safari ' . $m[1];
                    elseif (preg_match('/Chrome\/([0-9.]+)/', $friendly_ua, $m)) $browser = 'Chrome ' . explode('.', $m[1])[0];
                    elseif (preg_match('/Firefox\/([0-9.]+)/', $friendly_ua, $m)) $browser = 'Firefox ' . explode('.', $m[1])[0];
                    else $browser = '';
                    
                    $display_name = $d->name ?: $device_type . ($browser ? ' - ' . $browser : '');
                ?>
                <div class="device-card">
                    <div class="device-top">
                        <div class="device-icon <?= $icon_type ?>"><?= $icon_emoji ?></div>
                        <div class="device-info">
                            <div class="device-name">
                                <span><?= htmlspecialchars($display_name) ?></span>
                                <span class="badge badge-orange">Excluded</span>
                            </div>
                            <div class="device-id-text"><?= htmlspecialchars($d->id) ?></div>
                        </div>
                        <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;">
                            <a href="<?= site_url('admin/device/' . urlencode($d->id)) ?>" class="view-link">View Detail &rarr;</a>
                            <button onclick="toggleExclude('<?= htmlspecialchars($d->id, ENT_QUOTES) ?>')" class="include-btn">Include in Stats</button>
                        </div>
                    </div>
                    <div class="device-meta">
                        <span>Last seen: <?= $d->last_seen ? date('M j, Y g:ia', strtotime($d->last_seen)) : 'Unknown' ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
    function toggleExcludedSection() {
        var content = document.getElementById('excludedContent');
        var toggle = document.getElementById('excludedToggle');
        if (content.classList.contains('show')) {
            content.classList.remove('show');
            toggle.textContent = 'Show';
        } else {
            content.classList.add('show');
            toggle.textContent = 'Hide';
        }
    }
    
    function editName(id, currentName) {
        document.getElementById('name-edit-' + id).style.display = 'flex';
        var input = document.getElementById('name-input-' + id);
        input.value = currentName;
        input.focus();
    }
    
    function cancelEdit(id) {
        document.getElementById('name-edit-' + id).style.display = 'none';
    }
    
    function saveName(id) {
        var name = document.getElementById('name-input-' + id).value.trim();
        
        fetch('<?= site_url('admin/device_rename') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'device_id=' + encodeURIComponent(id) + '&name=' + encodeURIComponent(name)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                location.reload();
            }
        });
    }
    
    document.querySelectorAll('.name-edit input').forEach(function(input) {
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                var id = this.id.replace('name-input-', '');
                saveName(id);
            }
            if (e.key === 'Escape') {
                var id = this.id.replace('name-input-', '');
                cancelEdit(id);
            }
        });
    });
    
    function toggleExclude(id) {
        fetch('<?= site_url('admin/toggle_exclude') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'device_id=' + encodeURIComponent(id)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                location.reload();
            }
        });
    }
    </script>
</body>
</html>
