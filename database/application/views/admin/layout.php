<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?? 'Admin' ?> - Bennett Music</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f5f5; color: #333; font-size: 14px; }
        
        /* Sidebar Layout */
        .admin-wrapper { display: flex; min-height: 100vh; }
        
        .sidebar { width: 220px; background: linear-gradient(180deg, #667eea 0%, #764ba2 100%); color: white; position: fixed; top: 0; left: 0; bottom: 0; display: flex; flex-direction: column; z-index: 100; transition: transform 0.3s ease; }
        .sidebar-header { padding: 16px; border-bottom: 1px solid rgba(255,255,255,0.15); }
        .sidebar-header h1 { font-size: 16px; font-weight: 700; }
        .sidebar-header .subtitle { font-size: 11px; opacity: 0.7; margin-top: 2px; }
        
        .sidebar-nav { flex: 1; padding: 12px 0; overflow-y: auto; }
        .nav-section { padding: 0 8px; margin-bottom: 4px; }
        .nav-section-title { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.5; padding: 8px 12px; }
        .nav-item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; color: white; text-decoration: none; border-radius: 8px; margin: 2px 0; transition: background 0.15s; font-size: 14px; }
        .nav-item:hover { background: rgba(255,255,255,0.15); }
        .nav-item.active { background: rgba(255,255,255,0.25); font-weight: 600; }
        .nav-item svg { width: 18px; height: 18px; opacity: 0.9; flex-shrink: 0; }
        .nav-item span { flex: 1; }
        .nav-divider { border-top: 1px solid rgba(255,255,255,0.15); margin: 8px 12px; }
        
        .sidebar-footer { padding: 12px; border-top: 1px solid rgba(255,255,255,0.15); }
        .sidebar-footer a { display: flex; align-items: center; gap: 8px; color: white; text-decoration: none; padding: 8px 10px; border-radius: 8px; font-size: 13px; opacity: 0.8; }
        .sidebar-footer a:hover { background: rgba(255,255,255,0.1); opacity: 1; }
        .sidebar-footer svg { width: 16px; height: 16px; flex-shrink: 0; }
        
        /* Main Content */
        .main-content { flex: 1; margin-left: 220px; min-height: 100vh; }
        .content-header { background: white; padding: 14px 20px; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 50; gap: 12px; }
        .content-header h2 { font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .content-header .actions { display: flex; gap: 8px; flex-shrink: 0; }
        .content-body { padding: 20px; max-width: 100%; overflow-x: hidden; }
        
        /* Mobile menu toggle */
        .mobile-toggle { display: none; background: none; border: none; color: #333; padding: 8px; cursor: pointer; flex-shrink: 0; }
        .mobile-toggle svg { width: 24px; height: 24px; }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 99; }
        
        /* Buttons */
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; border: none; transition: all 0.15s; text-decoration: none; white-space: nowrap; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .btn-primary:hover { opacity: 0.9; }
        .btn-secondary { background: #f0f0f0; color: #333; }
        .btn-secondary:hover { background: #e0e0e0; }
        .btn-danger { background: #ffebee; color: #c62828; }
        .btn-danger:hover { background: #ffcdd2; }
        .btn-sm { padding: 8px 12px; font-size: 13px; }
        .btn svg { width: 16px; height: 16px; flex-shrink: 0; }
        
        /* Flash messages */
        .flash-message { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; font-size: 13px; }
        .flash-success { background: #e8f5e9; border: 1px solid #a5d6a7; color: #2e7d32; }
        .flash-error { background: #ffebee; border: 1px solid #ef9a9a; color: #c62828; }
        .flash-warning { background: #fff8e1; border: 1px solid #ffe082; color: #f57f17; }
        
        /* Cards */
        .card { background: white; border-radius: 12px; padding: 16px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid #eee; flex-wrap: wrap; gap: 8px; }
        .card-title { font-size: 15px; font-weight: 600; color: #333; }
        
        /* Stats */
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .stat-card { background: white; border-radius: 12px; padding: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); text-align: center; }
        .stat-card.highlight { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .stat-card.highlight .stat-label { opacity: 0.85; }
        .stat-num { font-size: 24px; font-weight: 700; color: #667eea; }
        .stat-card.highlight .stat-num { color: white; }
        .stat-label { font-size: 12px; color: #666; margin-top: 2px; }
        
        /* Tables */
        .table-container { overflow-x: auto; margin: 0 -16px; padding: 0 16px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 400px; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; color: #555; position: sticky; top: 0; }
        tr:hover { background: #fafafa; }
        
        /* Tabs */
        .tabs { display: flex; gap: 4px; margin-bottom: 16px; border-bottom: 1px solid #eee; padding-bottom: 2px; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .tab { padding: 10px 14px; cursor: pointer; border-radius: 8px 8px 0 0; font-size: 13px; white-space: nowrap; color: #666; }
        .tab:hover { background: #f5f5f5; }
        .tab.active { background: #667eea; color: white; font-weight: 500; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: 260px; }
            .sidebar.open { transform: translateX(0); }
            .sidebar-overlay.open { display: block; }
            .main-content { margin-left: 0; }
            .mobile-toggle { display: block; }
            .content-header { padding: 12px 16px; }
            .content-header h2 { font-size: 16px; }
            .content-body { padding: 16px; }
            .stats-row { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .stat-card { padding: 12px; }
            .stat-num { font-size: 20px; }
            .stat-label { font-size: 11px; }
            .card { padding: 14px; margin-bottom: 12px; }
            .btn { padding: 8px 12px; font-size: 13px; }
            .btn-sm { padding: 6px 10px; font-size: 12px; }
            table { font-size: 12px; }
            th, td { padding: 8px 10px; }
            .tabs { gap: 2px; }
            .tab { padding: 8px 12px; font-size: 12px; }
        }
        
        @media (max-width: 400px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .stat-num { font-size: 18px; }
            .content-header h2 { font-size: 15px; }
        }
        
        <?= $extra_css ?? '' ?>
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div class="app-icon">
                        <svg viewBox="0 0 40 40" width="36" height="36">
                            <defs>
                                <linearGradient id="iconGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" style="stop-color:#fff;stop-opacity:0.9"/>
                                    <stop offset="100%" style="stop-color:#fff;stop-opacity:0.6"/>
                                </linearGradient>
                            </defs>
                            <circle cx="20" cy="20" r="18" fill="rgba(255,255,255,0.15)" stroke="rgba(255,255,255,0.3)" stroke-width="1"/>
                            <path d="M16 12v12.5c-.8-.5-1.8-.8-3-1-2.5 0-4 1.3-4 3 0 1.5 1.5 2.8 4 2.8 2 0 4-1 5-2.5V15l10-2v9.5c-.8-.5-1.8-.8-3-1-2.5 0-4 1.3-4 3 0 1.5 1.5 2.8 4 2.8 2 0 4-1 5-2.5V10L16 12z" fill="url(#iconGrad)"/>
                        </svg>
                    </div>
                    <div>
                        <h1>Bennett Music</h1>
                        <div class="subtitle">Admin</div>
                    </div>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <a href="<?= site_url('admin') ?>" class="nav-item <?= ($active_page ?? '') === 'dashboard' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                        <span>Dashboard</span>
                    </a>
                    <a href="<?= site_url('admin/songs') ?>" class="nav-item <?= ($active_page ?? '') === 'library' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
                        <span>Library</span>
                    </a>
                    <a href="<?= site_url('admin/upload_song') ?>" class="nav-item <?= ($active_page ?? '') === 'upload' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        <span>Upload</span>
                    </a>
                    <a href="<?= site_url('admin/devices') ?>" class="nav-item <?= ($active_page ?? '') === 'devices' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                        <span>Devices</span>
                    </a>
                </div>
                
                <div class="nav-divider"></div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Tools</div>
                    <a href="<?= site_url('admin/tools') ?>" class="nav-item <?= ($active_page ?? '') === 'tools' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg>
                        <span>Tools</span>
                    </a>
                </div>
                
                <div class="nav-divider"></div>
                
                <div class="nav-section">
                    <a href="<?= site_url('admin/settings') ?>" class="nav-item <?= ($active_page ?? '') === 'settings' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                        <span>Settings</span>
                    </a>
                </div>
            </nav>
            
            <div class="sidebar-footer">
                <a href="<?= site_url('/') ?>" target="_blank">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    Open Player
                </a>
                <a href="<?= site_url('admin/logout') ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    Logout
                </a>
            </div>
        </aside>
        
        <!-- Overlay for mobile -->
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
        
        <!-- Main Content -->
        <main class="main-content">
            <header class="content-header">
                <div style="display:flex;align-items:center;gap:12px;">
                    <button class="mobile-toggle" onclick="toggleSidebar()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                    </button>
                    <h2><?= $page_icon ?? '' ?> <?= $page_title ?? 'Dashboard' ?></h2>
                </div>
                <div class="actions">
                    <?= $header_actions ?? '' ?>
                </div>
            </header>
            
            <div class="content-body">
                <?php if ($this->session->flashdata('success')): ?>
                    <div class="flash-message flash-success">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <?= $this->session->flashdata('success') ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($this->session->flashdata('error')): ?>
                    <div class="flash-message flash-error">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        <?= $this->session->flashdata('error') ?>
                    </div>
                <?php endif; ?>
                
                <?= $content ?>
            </div>
        </main>
    </div>
    
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebarOverlay').classList.toggle('open');
        }
        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('sidebarOverlay').classList.remove('open');
        }
    </script>
    <?= $extra_js ?? '' ?>
</body>
</html>
