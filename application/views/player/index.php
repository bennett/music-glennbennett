<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= isset($current_album) ? html_escape($current_album->title) . ' - ' : '' ?>Glenn Bennett Music</title>
    
    <!-- Open Graph -->
<?php
$og_song_id  = isset($autoplay_song_id) ? (int)$autoplay_song_id : null;
$og_album_id = isset($current_album) ? (int)$current_album->id : null;
if ($og_song_id):
    $og_title = isset($autoplay_song) ? html_escape($autoplay_song->title) . ' - Glenn Bennett' : 'Glenn Bennett Music';
    $og_desc  = isset($autoplay_song) ? 'Listen to "' . html_escape($autoplay_song->title) . '" by Glenn L. Bennett' : 'Original songs by Glenn L. Bennett';
    $og_image = base_url('share/image?song=' . $og_song_id);
    $og_url   = base_url('?song=' . $og_song_id);
elseif ($og_album_id):
    $og_title = html_escape($current_album->title) . ' - Glenn Bennett';
    $og_desc  = 'Listen to "' . html_escape($current_album->title) . '" by Glenn L. Bennett';
    $og_image = base_url('share/image?album=' . $og_album_id);
    $og_url   = base_url('?album=' . $og_album_id);
else:
    $og_title = 'Glenn Bennett Music';
    $og_desc  = 'Original songs by Glenn L. Bennett';
    $og_image = base_url('share/image');
    $og_url   = base_url();
endif;
?>
    <meta property="og:type" content="music.song">
    <meta property="og:title" content="<?= $og_title ?>">
    <meta property="og:description" content="<?= $og_desc ?>">
    <meta property="og:image" content="<?= $og_image ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:url" content="<?= $og_url ?>">
    <meta property="og:site_name" content="Glenn Bennett Music">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= $og_title ?>">
    <meta name="twitter:description" content="<?= $og_desc ?>">
    <meta name="twitter:image" content="<?= $og_image ?>">

    <!-- PWA Meta -->
    <link rel="manifest" href="<?= base_url('manifest.json') ?>">
    <meta name="theme-color" content="#667eea">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Bennett Music">
    <link rel="apple-touch-icon" href="<?= base_url('api/app_icon') ?>">
    
    <!-- Styles -->
    <link rel="stylesheet" href="<?= base_url('assets/css/music-player.css') ?>?v=3.1.5">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        /* Update Banner */
        .update-banner { position: fixed; top: 0; left: 0; right: 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); text-align: center; padding: 12px 20px; padding-top: calc(12px + env(safe-area-inset-top, 0px)); font-size: 14px; z-index: 1001; transform: translateY(-100%); transition: transform 0.3s; display: flex; align-items: center; justify-content: center; gap: 15px; }
        .update-banner.show { transform: translateY(0); }
        .update-banner button { background: white; color: #667eea; border: none; padding: 6px 16px; border-radius: 20px; font-weight: 600; cursor: pointer; }
        .update-banner .dismiss { background: transparent; color: white; padding: 6px; font-size: 18px; }
        
        /* Menu Button */
        .menu-btn { background: none; border: none; color: white; padding: 8px; cursor: pointer; opacity: 0.8; transition: opacity 0.2s; }
        .menu-btn:hover { opacity: 1; }
        
        /* Menu Overlay & Panel */
        .menu-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 300; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
        .menu-overlay.show { opacity: 1; pointer-events: auto; }
        .menu-panel { position: fixed; top: 0; left: 0; bottom: 0; width: 300px; max-width: 85%; background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); z-index: 301; transform: translateX(-100%); transition: transform 0.3s; padding: 20px; padding-top: calc(20px + env(safe-area-inset-top, 0px)); display: flex; flex-direction: column; }
        .menu-panel.show { transform: translateX(0); }
        .menu-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .menu-header h3 { font-size: 20px; font-weight: 600; }
        .menu-close { background: none; border: none; color: white; font-size: 24px; cursor: pointer; opacity: 0.7; padding: 5px; }
        .menu-items { flex: 1; overflow-y: auto; }
        .menu-item { display: flex; align-items: center; gap: 15px; width: 100%; padding: 15px 10px; background: none; border: none; color: white; font-size: 16px; cursor: pointer; border-radius: 12px; text-align: left; text-decoration: none; transition: background 0.2s; }
        .menu-item:hover { background: rgba(255,255,255,0.1); }
        .menu-item-icon { width: 40px; height: 40px; background: rgba(255,255,255,0.1); border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .menu-item-content { flex: 1; }
        .menu-item-desc { font-size: 13px; opacity: 0.6; margin-top: 2px; }
        .menu-chevron { font-size: 20px; opacity: 0.5; transition: transform 0.2s; }
        .menu-chevron.open { transform: rotate(90deg); }
        .menu-sub-group { max-height: 0; overflow: hidden; transition: max-height 0.25s ease; }
        .menu-sub-group.open { max-height: 250px; }
        .menu-sub-item { display: block; width: 100%; padding: 10px 10px 10px 65px; color: rgba(255,255,255,0.7); font-size: 14px; text-decoration: none; border-radius: 8px; transition: background 0.2s; background: none; border: none; cursor: pointer; text-align: left; }
        .menu-sub-item:hover { background: rgba(255,255,255,0.1); color: white; }
        .menu-sep { height: 1px; background: rgba(255,255,255,0.1); margin: 10px 0; }
        .menu-version { text-align: center; padding: 15px; font-size: 12px; opacity: 0.4; }
        .menu-update-result { padding: 4px 0 0; font-size: 13px; }
        .menu-update-result.uptodate { color: #4CAF50; }
        .menu-update-result.available { color: #FFD700; }
        
        /* Install Modal */
        .install-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 400; display: flex; align-items: center; justify-content: center; padding: 20px; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
        .install-modal.show { opacity: 1; pointer-events: auto; }
        .install-content { background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); border-radius: 20px; padding: 25px; max-width: 400px; width: 100%; max-height: 80vh; overflow-y: auto; }
        .install-content h3 { font-size: 20px; margin-bottom: 15px; text-align: center; }
        .install-content p { opacity: 0.8; margin-bottom: 15px; line-height: 1.5; }
        .install-content ol { margin: 15px 0; padding-left: 25px; opacity: 0.8; line-height: 1.8; }
        .install-close { width: 100%; padding: 14px; background: rgba(255,255,255,0.15); border: none; border-radius: 12px; color: white; font-size: 16px; font-weight: 600; cursor: pointer; margin-top: 15px; transition: background 0.2s; }
        .install-close:hover { background: rgba(255,255,255,0.25); }
        
        /* Help Modal */
        .help-header { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .help-header h3 { flex: 1; margin: 0; font-size: 20px; }
        .help-back { background: none; border: none; color: white; font-size: 24px; cursor: pointer; padding: 5px; opacity: 0.7; }
        .help-close-x { background: none; border: none; color: white; font-size: 20px; cursor: pointer; padding: 5px; opacity: 0.7; }
        .help-topics { list-style: none; padding: 0; }
        .help-topics li { padding: 15px; background: rgba(255,255,255,0.08); border-radius: 12px; margin-bottom: 10px; cursor: pointer; display: flex; align-items: center; gap: 12px; transition: background 0.2s; }
        .help-topics li:hover { background: rgba(255,255,255,0.12); }
        .help-article { font-size: 15px; line-height: 1.6; opacity: 0.85; }
        .help-article h4 { margin: 20px 0 10px; font-size: 16px; opacity: 1; }
        .help-article p { margin-bottom: 12px; }
        
        /* QR Modal */
        .qr-content { text-align: center; }
        .qr-box { background: white; padding: 16px; border-radius: 12px; display: inline-block; margin: 15px 0; }
        .qr-url { font-size: 13px; color: #888; word-break: break-all; }
        
        /* Header layout */
        .header-row { display: flex; align-items: center; margin-bottom: 15px; max-width: 500px; margin-left: auto; margin-right: auto; padding: 0 20px; }
        .header-row h1 { font-size: 24px; font-weight: 700; flex: 1; text-align: center; margin-right: 32px; }
    </style>
</head>
<body class="player-page">
    
    <!-- Toast -->
    <div class="toast-container"><div class="toast" id="toast"></div></div>
    
    <!-- Offline Banner -->
    <div class="offline-banner" id="offlineBanner">You're offline - playback may be limited</div>
    
    <!-- Update Banner -->
    <div class="update-banner" id="updateBanner">
        <span>Update available!</span>
        <button onclick="applyUpdate()">Refresh</button>
        <button class="dismiss" onclick="dismissUpdate()">✕</button>
    </div>
    
    <!-- Menu Overlay -->
    <div class="menu-overlay" id="menuOverlay" onclick="closeMenu()"></div>
    
    <!-- Menu Panel -->
    <div class="menu-panel" id="menuPanel">
        <div class="menu-header">
            <h3>Menu</h3>
            <button class="menu-close" onclick="closeMenu()">✕</button>
        </div>
        <div class="menu-items">
            <!-- Check for Updates -->
            <button class="menu-item" onclick="checkForUpdates()">
                <span class="menu-item-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
                </span>
                <div class="menu-item-content">
                    <div>Check for Updates</div>
                    <div class="menu-item-desc">Get the latest version</div>
                    <div id="menuUpdateStatus"></div>
                </div>
            </button>
            
            <div class="menu-sep"></div>
            
            <!-- Save to Home Screen -->
            <button class="menu-item" id="menuInstallBtn" onclick="showInstallPrompt(); closeMenu();">
                <span class="menu-item-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12" y2="18.01"/></svg>
                </span>
                <div class="menu-item-content">
                    <div id="menuInstallLabel">Save to Home Screen</div>
                    <div class="menu-item-desc" id="menuInstallDesc">Install as app for the best experience</div>
                </div>
            </button>
            
            <div class="menu-sep"></div>
            
            <!-- Share -->
            <button class="menu-item" onclick="toggleShareSub(event)">
                <span class="menu-item-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>
                </span>
                <div class="menu-item-content">
                    <div>Share</div>
                    <div class="menu-item-desc">Share songs, albums &amp; more</div>
                </div>
                <span class="menu-chevron" id="shareChevron">›</span>
            </button>
            <div class="menu-sub-group" id="shareSubMenu">
                <button class="menu-sub-item" id="menuShareSong" onclick="player.shareCurrentTrack(); closeMenu();" style="display:none;">🎵 Share Current Song</button>
                <button class="menu-sub-item" id="menuShareAlbum" onclick="player.shareAlbum(); closeMenu();">💿 Share Album</button>
                <button class="menu-sub-item" onclick="shareApp(); closeMenu();">📲 Share This App</button>
                <button class="menu-sub-item" onclick="showQRCode(); closeMenu();">📱 Show QR Code</button>
            </div>
            
            <div class="menu-sep"></div>

            <!-- About -->
            <button class="menu-item" onclick="toggleAboutSub(event)">
                <span class="menu-item-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                </span>
                <div class="menu-item-content">
                    <div>About</div>
                    <div class="menu-item-desc">About this app</div>
                </div>
                <span class="menu-chevron" id="aboutChevron">›</span>
            </button>
            <div class="menu-sub-group" id="aboutSubMenu">
                <button class="menu-sub-item" onclick="showAbout(); closeMenu();">ℹ️ About This App</button>
                <a class="menu-sub-item" href="<?= base_url('podcast') ?>" onclick="closeMenu();">🎙️ Featured Podcast Episode</a>
                <a class="menu-sub-item" href="<?= base_url('promos') ?>" onclick="closeMenu();">🎬 How to Actually Listen</a>
                <a class="menu-sub-item" href="https://glennbennett.com" target="_blank" rel="noopener" onclick="closeMenu();">🌐 Visit GlennBennett.com</a>
            </div>
            
            <!-- Help -->
            <button class="menu-item" onclick="showHelp(); closeMenu();">
                <span class="menu-item-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </span>
                <div class="menu-item-content">
                    <div>Help</div>
                    <div class="menu-item-desc">How to use this app</div>
                </div>
            </button>
        </div>
        <div class="menu-version">Bennett Music <span id="menuVersion">v3.1.5</span></div>
    </div>
    
    <!-- Install Modal -->
    <div class="install-modal" id="installModal" onclick="if(event.target===this)hideInstallPrompt()">
        <div class="install-content">
            <h3>Install App</h3>
            <div id="installInstructions"></div>
            <button class="install-close" onclick="hideInstallPrompt()">Close</button>
        </div>
    </div>
    
    <!-- About Modal -->
    <div class="install-modal" id="aboutModal" onclick="if(event.target===this)hideAbout()">
        <div class="install-content">
            <h3>About My Music App</h3>
            <p>I'm Glenn Bennett — a solo acoustic performer based in Ventura County, California, playing folk-rock, soft rock, and classic rock at local venues and farmers markets.</p>
            <p>I built this app so you'd have the best way to listen to my original music. I write and create my own songs using some of the fantastic new music creation tools available today, and this is where they live. No ads. No subscription. No account to create. No algorithm deciding what you hear next. Just open it and press play.</p>
            <p>It works offline so you can listen anywhere — even without cell service. It plays from your lock screen and CarPlay just like Spotify or Apple Music. You can share an album with a friend through a simple link, or scan a QR code at one of my live shows to start listening instantly.</p>
            <p>As I release new albums and songs, they'll show up right here first. This is the best place to hear what I've been working on.</p>
            <p>One tip — bookmark this app or add it to your home screen. On iPhone, tap the share button in Safari and choose "Add to Home Screen." It'll look and feel like a real app — full screen, no browser bar, and always ready when you want to listen.</p>
            <p>Just remember to come back here when you want to hear my music. This is the home for it — not Spotify, not YouTube, right here.</p>
            <p>Thanks for listening.<br>— Glenn</p>
            <button class="install-close" onclick="hideAbout()">Close</button>
        </div>
    </div>
    
    <!-- Help Modal -->
    <div class="install-modal" id="helpModal" onclick="if(event.target===this)hideHelp()">
        <div class="install-content">
            <div class="help-header">
                <button class="help-back" id="helpBackBtn" onclick="showHelpTopics()" style="display:none;">←</button>
                <h3 id="helpTitle">Help</h3>
                <button class="help-close-x" onclick="hideHelp()">✕</button>
            </div>
            <div id="helpBody"></div>
            <button class="install-close" onclick="hideHelp()">Close</button>
        </div>
    </div>
    
    <!-- QR Code Modal -->
    <div class="install-modal" id="qrModal" onclick="if(event.target===this)hideQRCode()">
        <div class="install-content qr-content">
            <h3>Scan to Open App</h3>
            <p>Point your camera at the QR code</p>
            <div class="qr-box">
                <img id="qrCodeImg" src="" alt="QR Code" style="width:200px;height:200px;">
            </div>
            <p class="qr-url" id="qrUrl"></p>
            <button class="install-close" onclick="hideQRCode()">Close</button>
        </div>
    </div>
    
    <!-- Header -->
    <header class="player-header">
        <div class="header-row">
            <button class="menu-btn" onclick="openMenu()" title="Menu">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
            </button>
            <h1>Glenn Bennett Music</h1>
        </div>
        <select class="album-selector" id="albumSelect" onchange="player.loadAlbum(this.value)">
            <?php foreach ($albums as $album): ?>
            <option value="<?= $album->id ?>" <?= (isset($current_album) && $current_album->id == $album->id) ? 'selected' : '' ?>>
                <?= html_escape($album->title) ?><?= $album->year ? ' (' . $album->year . ')' : '' ?>
            </option>
            <?php endforeach; ?>
            <?php if (!empty($has_misc)): ?>
            <option value="misc">Misc Songs</option>
            <?php endif; ?>
        </select>
    </header>
    
    <!-- Main Content -->
    <main class="player-container" id="albumDisplay">
        <?php if (isset($current_album)): ?>
            <?php $this->load->view('player/partials/album_content', ['album' => $current_album]); ?>
        <?php else: ?>
            <div class="loading"><p>No albums found</p></div>
        <?php endif; ?>
    </main>
    
    <!-- Mini Player Bar -->
    <div class="player-bar" id="playerBar">
        <div class="mini-progress"><div class="mini-progress-fill" id="miniProgress"></div></div>
        <div class="mini-player" onclick="player.expandPlayer()">
            <img class="mini-player-cover" id="miniCover" src="" alt="">
            <div class="mini-player-info">
                <div class="mini-player-title" id="miniTitle">Not playing</div>
                <div class="mini-player-artist" id="miniArtist"></div>
            </div>
            <div class="mini-player-controls" onclick="event.stopPropagation()">
                <button class="control-btn play-btn" id="miniPlayBtn" onclick="player.togglePlayPause()">
                    <svg viewBox="0 0 24 24" fill="currentColor" id="miniPlayIcon"><path d="M8 5v14l11-7z"/></svg>
                </button>
                <button class="control-btn" onclick="player.nextTrack()">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/></svg>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Expanded Player -->
    <div class="expanded-player" id="expandedPlayer">
        <div class="swipe-pill" onclick="player.collapsePlayer()"></div>
        <div class="expanded-header">
            <button class="expanded-close" onclick="player.collapsePlayer()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <span class="expanded-album-name" id="expandedAlbum"></span>
            <button onclick="player.shareCurrentTrack()" title="Share Song" style="background:none;border:none;color:white;padding:8px;cursor:pointer;opacity:0.8;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>
            </button>
        </div>
        <img class="expanded-cover" id="expandedCover" src="" alt="">
        <div class="expanded-controls">
            <button class="control-btn" onclick="player.previousTrack()">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/></svg>
            </button>
            <button class="control-btn play-btn" id="expandedPlayBtn" onclick="player.togglePlayPause()">
                <svg viewBox="0 0 24 24" fill="currentColor" id="expandedPlayIcon"><path d="M8 5v14l11-7z"/></svg>
            </button>
            <button class="control-btn" onclick="player.nextTrack()">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/></svg>
            </button>
        </div>
        <div class="expanded-progress">
            <div class="progress-bar" id="progressBar">
                <div class="progress-track"></div>
                <div class="progress-fill" id="progressFill"></div>
                <div class="progress-handle" id="progressHandle"></div>
            </div>
            <div class="progress-times"><span id="currentTime">0:00</span><span id="totalTime">0:00</span></div>
        </div>
        <div class="secondary-controls">
            <button class="control-btn" id="shuffleBtn" onclick="player.toggleShuffle()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 3 21 3 21 8"></polyline><line x1="4" y1="20" x2="21" y2="3"></line><polyline points="21 16 21 21 16 21"></polyline><line x1="15" y1="15" x2="21" y2="21"></line><line x1="4" y1="4" x2="9" y2="9"></line></svg>
            </button>
            <button class="control-btn" id="repeatBtn" onclick="player.toggleRepeat()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="17 1 21 5 17 9"></polyline><path d="M3 11V9a4 4 0 0 1 4-4h14"></path><polyline points="7 23 3 19 7 15"></polyline><path d="M21 13v2a4 4 0 0 1-4 4H3"></path></svg>
            </button>
            <button class="control-btn" id="favoriteBtn" onclick="player.toggleFavorite()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
            </button>
        </div>
        <div class="expanded-info">
            <div class="expanded-title" id="expandedTitle">Not playing</div>
            <div class="expanded-artist" id="expandedArtist"></div>
        </div>
    </div>
    
    <!-- Music Player (native HTML5 Audio) -->
    <script src="<?= base_url('assets/js/music-player.js') ?>?v=3.1.5"></script>
    
    <script>
    // ============================================================================
    // MENU FUNCTIONS
    // ============================================================================
    function openMenu() {
        document.getElementById('menuOverlay').classList.add('show');
        document.getElementById('menuPanel').classList.add('show');
    }
    
    function closeMenu() {
        document.getElementById('menuOverlay').classList.remove('show');
        document.getElementById('menuPanel').classList.remove('show');
    }
    
    // ============================================================================
    // UPDATE FUNCTIONS
    // ============================================================================
    var newWorker = null;
    var menuUpdateInProgress = false;
    
    function checkForUpdates() {
        var status = document.getElementById('menuUpdateStatus');
        status.innerHTML = '<span style="color:rgba(255,255,255,0.6);font-style:italic;font-size:13px;">Checking...</span>';
        menuUpdateInProgress = true;

        // Fetch sw.js to check version
        fetch('/sw.js?_=' + Date.now(), { cache: 'no-store' })
            .then(function(r) { return r.text(); })
            .then(function(text) {
                var match = text.match(/APP_VERSION\s*=\s*['"]([^'"]+)['"]/);
                var serverVer = match ? match[1] : null;
                var currentVer = document.getElementById('menuVersion').textContent.replace('v', '');

                if (serverVer && serverVer !== currentVer) {
                    status.innerHTML = '<div class="menu-update-result available">Updating to v' + serverVer + '...</div>';
                    closeMenu();
                    menuUpdateInProgress = false;

                    // If there's already a waiting worker, activate it now
                    if (newWorker) {
                        newWorker.postMessage('skipWaiting');
                        return;
                    }

                    // Otherwise trigger SW update and auto-apply when ready
                    if ('serviceWorker' in navigator) {
                        navigator.serviceWorker.ready.then(function(reg) {
                            reg.update().then(function() {
                                // If update found a new worker, it will fire updatefound
                                // The controllerchange handler will reload the page
                                // As a fallback, just reload after a short delay
                                setTimeout(function() { window.location.reload(); }, 2000);
                            });
                        });
                    } else {
                        window.location.reload();
                    }
                } else {
                    status.innerHTML = '<div class="menu-update-result uptodate">✓ Up to date (v' + currentVer + ')</div>';
                    menuUpdateInProgress = false;
                }
            })
            .catch(function() {
                status.innerHTML = '<span style="color:#ff6b6b;font-size:13px;">Could not check</span>';
                menuUpdateInProgress = false;
            });
    }
    
    function applyUpdate() {
        if (newWorker) {
            newWorker.postMessage('skipWaiting');
            // controllerchange handler will reload the page once the new SW activates
        } else {
            window.location.reload();
        }
    }
    
    function dismissUpdate() {
        document.getElementById('updateBanner').classList.remove('show');
    }
    
    // ============================================================================
    // INSTALL FUNCTIONS
    // ============================================================================
    var deferredPrompt = null;
    
    function showInstallPrompt() {
        var instructions = document.getElementById('installInstructions');
        var ua = navigator.userAgent;
        
        // Check if already installed
        if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) {
            instructions.innerHTML = '<p style="color:#4CAF50;text-align:center;font-size:18px;">✓ Already installed!</p><p style="text-align:center;">This app is running in standalone mode.</p>';
        } else if (deferredPrompt) {
            // Chrome/Edge install prompt available
            instructions.innerHTML = '<p style="text-align:center;">Click below to install:</p>';
            var btn = document.createElement('button');
            btn.className = 'install-close';
            btn.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
            btn.style.marginTop = '15px';
            btn.textContent = 'Install App';
            btn.onclick = function() {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then(function() { deferredPrompt = null; hideInstallPrompt(); });
            };
            instructions.innerHTML = '';
            instructions.appendChild(document.createTextNode('Tap the button below to install:'));
            instructions.appendChild(document.createElement('br'));
            instructions.appendChild(document.createElement('br'));
            instructions.appendChild(btn);
        } else if (/iPad|iPhone|iPod/.test(ua)) {
            instructions.innerHTML = '<p><b>iPhone/iPad:</b></p><ol><li>Tap the <b>Share</b> button <span style="font-size:18px;">⬆️</span></li><li>Scroll down and tap <b>Add to Home Screen</b></li><li>Tap <b>Add</b> in the top right</li></ol>';
        } else if (/Android/.test(ua)) {
            instructions.innerHTML = '<p><b>Android:</b></p><ol><li>Tap the <b>menu</b> button <span style="font-size:18px;">⋮</span></li><li>Tap <b>Add to Home Screen</b> or <b>Install App</b></li><li>Follow the prompts to install</li></ol>';
        } else {
            instructions.innerHTML = '<p>Look for the install icon in your browser\'s address bar, or use your browser\'s menu to "Install" or "Add to Home Screen".</p>';
        }
        
        document.getElementById('installModal').classList.add('show');
    }
    
    function hideInstallPrompt() {
        document.getElementById('installModal').classList.remove('show');
    }
    
    // Capture install prompt
    window.addEventListener('beforeinstallprompt', function(e) {
        e.preventDefault();
        deferredPrompt = e;
        // Update menu item to show it's installable
        document.getElementById('menuInstallLabel').textContent = 'Install App';
        document.getElementById('menuInstallDesc').textContent = 'Add to your home screen';
    });
    
    // ============================================================================
    // SHARE FUNCTIONS
    // ============================================================================
    function shareApp() {
        var url = window.location.origin;
        var title = 'Glenn Bennett Music';
        var text = 'Listen to music by Glenn Bennett';
        
        if (navigator.share) {
            navigator.share({ title: title, text: text, url: url }).catch(function() {});
        } else {
            // Fallback: copy to clipboard
            navigator.clipboard.writeText(url).then(function() {
                if (typeof player !== 'undefined' && player.toast) {
                    player.toast('Link copied to clipboard!');
                } else {
                    alert('Link copied to clipboard!');
                }
            });
        }
    }
    
    // ============================================================================
    // QR CODE FUNCTIONS
    // ============================================================================
    function showQRCode() {
        var url = window.location.origin;
        document.getElementById('qrCodeImg').src = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(url);
        document.getElementById('qrUrl').textContent = url;
        document.getElementById('qrModal').classList.add('show');
    }
    
    function hideQRCode() {
        document.getElementById('qrModal').classList.remove('show');
    }
    
    // ============================================================================
    // ABOUT FUNCTIONS
    // ============================================================================
    function toggleAboutSub(e) {
        e.stopPropagation();
        document.getElementById('aboutSubMenu').classList.toggle('open');
        document.getElementById('aboutChevron').classList.toggle('open');
    }

    function toggleShareSub(e) {
        e.stopPropagation();
        document.getElementById('shareSubMenu').classList.toggle('open');
        document.getElementById('shareChevron').classList.toggle('open');
    }

    function showAbout() {
        document.getElementById('aboutModal').classList.add('show');
    }
    
    function hideAbout() {
        document.getElementById('aboutModal').classList.remove('show');
    }
    
    // ============================================================================
    // HELP FUNCTIONS
    // ============================================================================
    var helpTopics = {
        'start': {
            title: 'Getting Started',
            content: '<div class="help-article"><p>Welcome to Glenn Bennett Music! Here\'s how to get started:</p><h4>Playing Music</h4><p>Select an album from the dropdown menu at the top, then tap any song to start playing.</p><h4>Controls</h4><p>Use the mini player at the bottom to control playback. Tap it to expand for more options.</p></div>'
        },
        'controls': {
            title: 'Player Controls',
            content: '<div class="help-article"><h4>Mini Player</h4><p>The bar at the bottom shows what\'s playing. Tap it to expand the full player.</p><h4>Expanded Player</h4><p><b>Shuffle</b> - Randomize the play order<br><b>Repeat</b> - Tap to cycle: Off → Repeat All → Repeat One<br><b>Heart</b> - Add to your Favorites</p><h4>Progress Bar</h4><p>Tap anywhere on the progress bar to jump to that point in the song.</p></div>'
        },
        'favorites': {
            title: 'Favorites',
            content: '<div class="help-article"><p>Build your own playlist of favorite songs!</p><h4>Adding Favorites</h4><p>Tap the heart icon in the expanded player to add the current song to your favorites.</p><h4>Playing Favorites</h4><p>Select "Favorites" from the album dropdown to see all your saved songs.</p><h4>Removing Favorites</h4><p>Tap the heart icon again to remove a song from your favorites.</p></div>'
        },
        'offline': {
            title: 'Offline Playback',
            content: '<div class="help-article"><p>This app works offline after your first visit!</p><h4>How It Works</h4><p>The app automatically caches songs as you listen. Previously played songs will be available offline.</p><h4>Offline Indicator</h4><p>When you\'re offline, a banner will appear at the top of the screen. You can still play cached content.</p></div>'
        },
        'install': {
            title: 'Installing the App',
            content: '<div class="help-article"><p>Install this app on your device for the best experience!</p><h4>Benefits</h4><p>• Launch from your home screen<br>• Full-screen experience<br>• Works offline<br>• Faster loading</p><h4>How to Install</h4><p>Use the "Save to Home Screen" option in the menu, or look for the install prompt in your browser.</p></div>'
        }
    };
    
    function showHelp() {
        document.getElementById('helpModal').classList.add('show');
        showHelpTopics();
    }
    
    function hideHelp() {
        document.getElementById('helpModal').classList.remove('show');
    }
    
    function showHelpTopics() {
        document.getElementById('helpBackBtn').style.display = 'none';
        document.getElementById('helpTitle').textContent = 'Help';
        document.getElementById('helpBody').innerHTML = 
            '<ul class="help-topics">' +
            '<li onclick="showHelpTopic(\'start\')">🚀 Getting Started</li>' +
            '<li onclick="showHelpTopic(\'controls\')">🎛️ Player Controls</li>' +
            '<li onclick="showHelpTopic(\'favorites\')">❤️ Favorites</li>' +
            '<li onclick="showHelpTopic(\'offline\')">📶 Offline Playback</li>' +
            '<li onclick="showHelpTopic(\'install\')">📱 Installing the App</li>' +
            '</ul>';
    }
    
    function showHelpTopic(id) {
        var topic = helpTopics[id];
        if (!topic) return;
        document.getElementById('helpBackBtn').style.display = 'block';
        document.getElementById('helpTitle').textContent = topic.title;
        document.getElementById('helpBody').innerHTML = topic.content;
    }
    
    // ============================================================================
    // SERVICE WORKER REGISTRATION
    // ============================================================================
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').then(function(reg) {
            // Check for updates periodically
            setInterval(function() { reg.update(); }, 60 * 60 * 1000); // Every hour
            
            reg.addEventListener('updatefound', function() {
                newWorker = reg.installing;
                newWorker.addEventListener('statechange', function() {
                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                        // Auto-apply the update immediately
                        newWorker.postMessage('skipWaiting');
                    }
                });
            });
        });
        
        navigator.serviceWorker.addEventListener('controllerchange', function() {
            window.location.reload();
        });
    }
    
    // ============================================================================
    // INITIALIZE PLAYER
    // ============================================================================
    const player = new MusicPlayer({
        baseUrl: '<?= rtrim(base_url(), '/') ?>',
        initialAlbum: <?= isset($current_album) ? json_encode($current_album) : 'null' ?>,
        autoplaySongId: <?= isset($autoplay_song_id) ? json_encode($autoplay_song_id) : 'null' ?>,
        gapBetweenTracks: 2000
    });
    
    // Check if already installed and update menu
    if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) {
        document.getElementById('menuInstallLabel').textContent = 'App Installed';
        document.getElementById('menuInstallDesc').textContent = 'Running in standalone mode';
        document.getElementById('menuInstallBtn').style.opacity = '0.5';
    }
    </script>
</body>
</html>
