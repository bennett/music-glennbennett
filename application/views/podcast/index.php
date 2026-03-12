<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= html_escape($episode->title) ?> - <?= html_escape($podcast_title) ?></title>

    <!-- Open Graph -->
    <meta property="og:type" content="music.song">
    <meta property="og:title" content="<?= html_escape($episode->title) ?> - <?= html_escape($podcast_title) ?>">
    <meta property="og:description" content="<?= html_escape($episode->description) ?>">
    <meta property="og:image" content="<?= base_url('share/image?podcast=1&v=2') ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:url" content="<?= base_url('podcast') ?>">
    <meta property="og:site_name" content="Glenn Bennett Music">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= html_escape($episode->title) ?> - <?= html_escape($podcast_title) ?>">
    <meta name="twitter:description" content="<?= html_escape($episode->description) ?>">
    <meta name="twitter:image" content="<?= base_url('share/image?podcast=1&v=2') ?>">

    <!-- PWA Meta -->
    <link rel="manifest" href="<?= base_url('manifest.json') ?>">
    <meta name="theme-color" content="#667eea">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="apple-touch-icon" href="<?= base_url('api/app_icon') ?>">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(165deg, #667eea 0%, #4A90E2 100%);
            min-height: 100vh;
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Back link */
        .back-link {
            position: absolute;
            top: 16px;
            top: calc(16px + env(safe-area-inset-top, 0px));
            left: 16px;
            color: white;
            text-decoration: none;
            font-size: 14px;
            opacity: 0.8;
            display: flex;
            align-items: center;
            gap: 6px;
            z-index: 10;
        }
        .back-link:hover { opacity: 1; }

        /* Episode Card */
        .episode-card {
            background: rgba(0,0,0,0.2);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 30px;
            max-width: 440px;
            width: calc(100% - 40px);
            margin-top: calc(80px + env(safe-area-inset-top, 0px));
            text-align: center;
        }

        /* Podcast artwork */
        .podcast-art {
            width: 200px;
            height: 200px;
            border-radius: 20px;
            margin: 0 auto 24px;
            background: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }
        .podcast-art svg { width: 80px; height: 80px; opacity: 0.6; }

        .podcast-badge {
            display: inline-block;
            background: rgba(255,255,255,0.15);
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .episode-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .episode-desc {
            font-size: 14px;
            opacity: 0.75;
            margin-bottom: 24px;
            line-height: 1.5;
        }

        /* Audio Player */
        .player-section {
            background: rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .progress-container {
            width: 100%;
            height: 6px;
            background: rgba(255,255,255,0.2);
            border-radius: 3px;
            cursor: pointer;
            margin-bottom: 10px;
            position: relative;
        }
        .progress-fill {
            height: 100%;
            background: white;
            border-radius: 3px;
            width: 0%;
            transition: width 0.1s linear;
        }
        .progress-handle {
            width: 14px;
            height: 14px;
            background: white;
            border-radius: 50%;
            position: absolute;
            top: 50%;
            transform: translate(-50%, -50%);
            left: 0%;
            box-shadow: 0 2px 6px rgba(0,0,0,0.3);
            display: none;
        }
        .progress-container:hover .progress-handle { display: block; }

        .time-row {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            opacity: 0.7;
            font-variant-numeric: tabular-nums;
            margin-bottom: 16px;
        }

        .controls {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 24px;
        }

        .control-btn {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 8px;
            opacity: 0.8;
            transition: opacity 0.2s, transform 0.1s;
        }
        .control-btn:hover { opacity: 1; }
        .control-btn:active { transform: scale(0.95); }
        .control-btn svg { width: 24px; height: 24px; }

        .play-btn {
            width: 64px;
            height: 64px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 1;
            box-shadow: 0 4px 16px rgba(0,0,0,0.2);
        }
        .play-btn svg { width: 28px; height: 28px; color: #667eea; fill: #667eea; }

        /* Speed selector */
        .speed-btn {
            font-size: 13px;
            font-weight: 600;
            min-width: 40px;
        }

        /* Share section */
        .share-section {
            text-align: center;
        }
        .share-label {
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.6;
            margin-bottom: 12px;
        }
        .share-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .share-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            background: rgba(255,255,255,0.15);
            border: none;
            color: white;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
        }
        .share-btn:hover { background: rgba(255,255,255,0.25); }
        .share-btn svg { width: 20px; height: 20px; }

        .share-btn-wide {
            width: auto;
            padding: 0 16px;
            gap: 8px;
            font-size: 13px;
        }

        .back-to-music {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 600;
            background: rgba(255,255,255,0.08);
            margin-top: 20px;
            transition: background 0.2s;
        }
        .back-to-music:hover { background: rgba(255,255,255,0.15); }

        /* Toast */
        .toast-container { position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 2000; pointer-events: none; }
        .toast { background: rgba(0,0,0,0.85); padding: 12px 24px; border-radius: 30px; font-size: 14px; opacity: 0; transform: translateY(-20px); transition: all 0.3s; }
        .toast.show { opacity: 1; transform: translateY(0); }
    </style>
</head>
<body>
    <!-- Toast -->
    <div class="toast-container"><div class="toast" id="toast"></div></div>

    <!-- Back link -->
    <a href="<?= base_url() ?>" class="back-link">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Music
    </a>

    <div class="episode-card">
        <!-- Podcast artwork -->
        <div class="podcast-art">
            <img src="<?= html_escape($episode->cover_url) ?>" alt="Podcast Cover" style="width:100%;height:100%;object-fit:cover;" onerror="this.onerror=null;this.src='<?= html_escape($episode->cover_fallback_url) ?>';">
        </div>

        <div class="podcast-badge"><?= html_escape($podcast_title) ?></div>
        <h1 class="episode-title"><?= html_escape($episode->title) ?></h1>
        <p class="episode-desc"><?= html_escape($episode->description) ?></p>

        <!-- Player -->
        <div class="player-section">
            <div class="progress-container" id="progressBar">
                <div class="progress-fill" id="progressFill"></div>
                <div class="progress-handle" id="progressHandle"></div>
            </div>
            <div class="time-row">
                <span id="currentTime">0:00</span>
                <span id="totalTime">0:00</span>
            </div>
            <div class="controls">
                <!-- Rewind 15s -->
                <button class="control-btn" onclick="skip(-15)" title="Rewind 15 seconds">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 4v6h6"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>
                        <text x="12" y="16" text-anchor="middle" fill="currentColor" stroke="none" font-size="8" font-weight="700">15</text>
                    </svg>
                </button>

                <!-- Play/Pause -->
                <button class="control-btn play-btn" id="playBtn" onclick="togglePlay()">
                    <svg viewBox="0 0 24 24" id="playIcon"><path d="M8 5v14l11-7z"/></svg>
                </button>

                <!-- Forward 30s -->
                <button class="control-btn" onclick="skip(30)" title="Forward 30 seconds">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M23 4v6h-6"/><path d="M20.49 15a9 9 0 1 1-2.13-9.36L23 10"/>
                        <text x="12" y="16" text-anchor="middle" fill="currentColor" stroke="none" font-size="8" font-weight="700">30</text>
                    </svg>
                </button>

                <!-- Speed -->
                <button class="control-btn speed-btn" id="speedBtn" onclick="cycleSpeed()">1x</button>
            </div>
        </div>

        <!-- Share -->
<?php
    $share_url = base_url('share/podcast');
    $share_url_enc = urlencode($share_url);
    $share_text = urlencode(html_escape($episode->title) . ' - ' . html_escape($podcast_title));
    $share_body = urlencode(html_escape($episode->description) . ' ' . $share_url);
?>
        <div class="share-section">
            <div class="share-label">Share</div>
            <div class="share-buttons">
                <!-- Facebook -->
                <a class="share-btn" href="https://www.facebook.com/sharer.php?u=<?= $share_url_enc ?>" target="_blank" rel="noopener" onclick="recordShare('facebook')" title="Facebook">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg>
                </a>
                <!-- Twitter/X -->
                <a class="share-btn" href="https://twitter.com/intent/tweet?url=<?= $share_url_enc ?>&text=<?= $share_text ?>" target="_blank" rel="noopener" onclick="recordShare('twitter')" title="Twitter / X">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                </a>
                <!-- WhatsApp -->
                <a class="share-btn" href="https://api.whatsapp.com/send?text=<?= $share_body ?>" target="_blank" rel="noopener" onclick="recordShare('whatsapp')" title="WhatsApp">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                </a>
                <!-- SMS/Text -->
                <a class="share-btn" href="sms:?&body=<?= $share_body ?>" onclick="recordShare('sms')" title="Text Message">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                </a>
                <!-- Email -->
                <a class="share-btn" href="mailto:?subject=<?= $share_text ?>&body=<?= $share_body ?>" onclick="recordShare('email')" title="Email">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                </a>
                <!-- Copy Link -->
                <button class="share-btn share-btn-wide" onclick="copyLink()" title="Copy Link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>
                    Copy Link
                </button>
            </div>
        </div>

        <a href="<?= base_url() ?>" class="back-to-music">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>
            Listen to My Music
        </a>
    </div>

    <audio id="audio" preload="metadata">
        <source src="<?= html_escape($episode->stream_url) ?>" type="audio/mpeg">
        <source src="<?= html_escape($episode->fallback_url) ?>" type="audio/mpeg">
    </audio>

    <script>
    var audio = document.getElementById('audio');
    var playing = false;
    var speeds = [1, 1.25, 1.5, 2, 0.75];
    var speedIdx = 0;
    var listenedSeconds = 0;
    var playRecorded = false;
    var lastRecordTime = 0;
    var baseUrl = '<?= rtrim(base_url(), '/') ?>';

    // Device ID (match the main player's approach)
    var deviceId = localStorage.getItem('device_id');
    if (!deviceId) {
        deviceId = 'dev_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        localStorage.setItem('device_id', deviceId);
    }

    function formatTime(s) {
        if (isNaN(s) || !isFinite(s)) return '0:00';
        var h = Math.floor(s / 3600);
        var m = Math.floor((s % 3600) / 60);
        var sec = Math.floor(s % 60);
        if (h > 0) return h + ':' + (m < 10 ? '0' : '') + m + ':' + (sec < 10 ? '0' : '') + sec;
        return m + ':' + (sec < 10 ? '0' : '') + sec;
    }

    function togglePlay() {
        if (audio.paused) {
            audio.play().catch(function() {});
        } else {
            audio.pause();
        }
    }

    function skip(seconds) {
        audio.currentTime = Math.max(0, Math.min(audio.duration || 0, audio.currentTime + seconds));
    }

    function cycleSpeed() {
        speedIdx = (speedIdx + 1) % speeds.length;
        audio.playbackRate = speeds[speedIdx];
        document.getElementById('speedBtn').textContent = speeds[speedIdx] + 'x';
    }

    // Update UI
    audio.addEventListener('play', function() {
        playing = true;
        document.getElementById('playIcon').innerHTML = '<rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/>';
    });

    audio.addEventListener('pause', function() {
        playing = false;
        document.getElementById('playIcon').innerHTML = '<path d="M8 5v14l11-7z"/>';
    });

    audio.addEventListener('loadedmetadata', function() {
        document.getElementById('totalTime').textContent = formatTime(audio.duration);
    });

    audio.addEventListener('timeupdate', function() {
        var pct = audio.duration ? (audio.currentTime / audio.duration * 100) : 0;
        document.getElementById('progressFill').style.width = pct + '%';
        document.getElementById('progressHandle').style.left = pct + '%';
        document.getElementById('currentTime').textContent = formatTime(audio.currentTime);

        // Track listen time
        if (playing) {
            listenedSeconds += 0.25 * audio.playbackRate; // timeupdate fires ~4x/sec
        }

        // Record play after 20 seconds of listening
        var now = Date.now();
        if (listenedSeconds >= 20 && (now - lastRecordTime > 30000 || !playRecorded)) {
            recordPlay();
            lastRecordTime = now;
            playRecorded = true;
        }
    });

    // Progress bar seek
    var progressBar = document.getElementById('progressBar');

    function seekTo(e) {
        var rect = progressBar.getBoundingClientRect();
        var pct = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
        if (audio.duration) audio.currentTime = pct * audio.duration;
    }

    progressBar.addEventListener('click', seekTo);

    var dragging = false;
    progressBar.addEventListener('mousedown', function(e) { dragging = true; seekTo(e); });
    document.addEventListener('mousemove', function(e) { if (dragging) seekTo(e); });
    document.addEventListener('mouseup', function() { dragging = false; });

    // Touch seek
    progressBar.addEventListener('touchstart', function(e) {
        dragging = true;
        seekTo(e.touches[0]);
        e.preventDefault();
    });
    document.addEventListener('touchmove', function(e) {
        if (dragging) seekTo(e.touches[0]);
    });
    document.addEventListener('touchend', function() { dragging = false; });

    // Record play
    function recordPlay() {
        var pct = audio.duration ? Math.round(audio.currentTime / audio.duration * 100) : 0;
        var fd = new FormData();
        fd.append('episode', 'episode_1');
        fd.append('listened', Math.round(listenedSeconds));
        fd.append('percent', pct);

        fetch(baseUrl + '/podcast/record_play', {
            method: 'POST',
            body: fd,
            headers: { 'X-Device-Id': deviceId }
        }).catch(function() {});
    }

    // Record on page leave
    window.addEventListener('beforeunload', function() {
        if (listenedSeconds >= 5) recordPlay();
    });

    // Share tracking
    function recordShare(method) {
        var fd = new FormData();
        fd.append('share_type', 'podcast');
        fd.append('share_method', method);
        fetch(baseUrl + '/api/record_share', {
            method: 'POST',
            body: fd,
            headers: { 'X-Device-Id': deviceId }
        }).catch(function() {});
    }

    // Copy link
    function copyLink() {
        var url = baseUrl + '/share/podcast';
        recordShare('clipboard');
        navigator.clipboard.writeText(url).then(function() {
            toast('Link copied to clipboard!');
        }).catch(function() {
            var ta = document.createElement('textarea');
            ta.value = url;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            toast('Link copied!');
        });
    }

    // Toast
    function toast(msg) {
        var el = document.getElementById('toast');
        el.textContent = msg;
        el.classList.add('show');
        setTimeout(function() { el.classList.remove('show'); }, 2500);
    }

    // Pre-cache the podcast audio for offline playback
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.ready.then(function(reg) {
            if (reg.active) {
                reg.active.postMessage({ type: 'CACHE_AUDIO', url: '<?= addslashes(html_escape($episode->stream_url)) ?>' });
            }
        });
    }

    // Media Session API (lock screen controls)
    if ('mediaSession' in navigator) {
        navigator.mediaSession.metadata = new MediaMetadata({
            title: '<?= addslashes(html_escape($episode->title)) ?>',
            artist: 'Glenn Bennett',
            album: 'Podcast',
            artwork: [
                { src: '<?= addslashes(html_escape($episode->cover_url)) ?>', sizes: '512x512', type: 'image/jpeg' }
            ]
        });
        navigator.mediaSession.setActionHandler('play', function() { audio.play(); });
        navigator.mediaSession.setActionHandler('pause', function() { audio.pause(); });
        navigator.mediaSession.setActionHandler('seekbackward', function() { skip(-15); });
        navigator.mediaSession.setActionHandler('seekforward', function() { skip(30); });
    }
    </script>
</body>
</html>
