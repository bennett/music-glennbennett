<?php
$page_title = 'Purge Complete';
$page_icon = '🧹';
$active_page = 'tools';

$header_actions = '<a href="' . site_url('admin') . '" class="btn btn-secondary btn-sm">← Dashboard</a>';

ob_start();
?>

<div class="result-card">
    <div class="result-icon"><?= $removed > 0 ? '✅' : 'ℹ️' ?></div>
    <div class="result-title"><?= $removed > 0 ? 'Purge Complete' : 'Nothing to Purge' ?></div>
    <div class="result-message">
        <?php if ($removed > 0): ?>
            Removed <strong><?= $removed ?></strong> false start<?= $removed != 1 ? 's' : '' ?> from play history.
        <?php else: ?>
            No false starts found. Play history is clean!
        <?php endif; ?>
    </div>
    
    <div class="result-actions">
        <a href="<?= site_url('admin') ?>" class="btn btn-primary">Back to Dashboard</a>
        <?php if (!empty($device_id)): ?>
        <a href="<?= site_url('admin/device/' . urlencode($device_id)) ?>" class="btn btn-secondary">Back to Device</a>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();

$extra_css = '
    .result-card { 
        background: white; 
        border-radius: 16px; 
        padding: 40px 24px; 
        text-align: center;
        box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        max-width: 400px;
        margin: 40px auto;
    }
    .result-icon { font-size: 48px; margin-bottom: 16px; }
    .result-title { font-size: 20px; font-weight: 600; color: #333; margin-bottom: 12px; }
    .result-message { font-size: 14px; color: #666; margin-bottom: 24px; line-height: 1.5; }
    .result-actions { display: flex; flex-direction: column; gap: 10px; }
    .result-actions .btn { padding: 12px 24px; }
';

include(APPPATH . 'views/admin/layout.php');
?>
