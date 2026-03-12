<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= html_escape($video->title) ?> - Glenn Bennett Music</title>

    <!-- Open Graph -->
    <meta property="og:type" content="video.other">
    <meta property="og:title" content="<?= html_escape($video->title) ?> - Glenn Bennett Music">
    <meta property="og:description" content="<?= html_escape($video->description) ?>">
    <meta property="og:image" content="<?= base_url('share/image?promo=how_to_listen&v=1') ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:url" content="<?= base_url('promos') ?>">
    <meta property="og:site_name" content="Glenn Bennett Music">
    <meta property="og:video" content="<?= html_escape($video->video_url) ?>">
    <meta property="og:video:type" content="video/mp4">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= html_escape($video->title) ?> - Glenn Bennett Music">
    <meta name="twitter:description" content="<?= html_escape($video->description) ?>">
    <meta name="twitter:image" content="<?= base_url('share/image?promo=how_to_listen&v=1') ?>">

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

        .promo-card {
            background: rgba(0,0,0,0.2);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 30px;
            max-width: 500px;
            width: calc(100% - 40px);
            margin-top: calc(80px + env(safe-area-inset-top, 0px));
            text-align: center;
        }

        .promo-badge {
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

        .promo-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .promo-desc {
            font-size: 14px;
            opacity: 0.75;
            margin-bottom: 24px;
            line-height: 1.5;
        }

        /* Video Player */
        .video-container {
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 20px;
            background: black;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }
        .video-container video {
            width: 100%;
            display: block;
        }

        /* Share section */
        .share-section { text-align: center; }
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
            font-weight: 600;
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

        .toast-container { position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 2000; pointer-events: none; }
        .toast { background: rgba(0,0,0,0.85); padding: 12px 24px; border-radius: 30px; font-size: 14px; opacity: 0; transform: translateY(-20px); transition: all 0.3s; }
        .toast.show { opacity: 1; transform: translateY(0); }
    </style>
</head>
<body>
    <div class="toast-container"><div class="toast" id="toast"></div></div>

    <a href="<?= base_url() ?>" class="back-link">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Music
    </a>

    <div class="promo-card">
        <div class="promo-badge">Promo</div>
        <h1 class="promo-title"><?= html_escape($video->title) ?></h1>
        <p class="promo-desc"><?= html_escape($video->description) ?></p>

        <div class="video-container">
            <video id="video" controls playsinline preload="metadata"
                   poster="<?= base_url('share/image?promo=how_to_listen&v=1') ?>">
                <source src="<?= html_escape($video->video_url) ?>" type="video/mp4">
                <source src="<?= html_escape($video->fallback_url) ?>" type="video/mp4">
            </video>
        </div>

        <!-- Share -->
<?php
    $share_url = base_url('share/promo');
    $share_url_enc = urlencode($share_url);
    $share_text = urlencode(html_escape($video->title) . ' - Glenn Bennett Music');
    $share_body = urlencode(html_escape($video->description) . ' ' . $share_url);
?>
        <div class="share-section">
            <div class="share-label">Share</div>
            <div class="share-buttons">
                <a class="share-btn" href="https://www.facebook.com/sharer.php?u=<?= $share_url_enc ?>" target="_blank" rel="noopener" onclick="recordShare('facebook')" title="Facebook">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg>
                </a>
                <a class="share-btn" href="https://twitter.com/intent/tweet?url=<?= $share_url_enc ?>&text=<?= $share_text ?>" target="_blank" rel="noopener" onclick="recordShare('twitter')" title="Twitter / X">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                </a>
                <a class="share-btn" href="https://api.whatsapp.com/send?text=<?= $share_body ?>" target="_blank" rel="noopener" onclick="recordShare('whatsapp')" title="WhatsApp">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                </a>
                <a class="share-btn" href="sms:?&body=<?= $share_body ?>" onclick="recordShare('sms')" title="Text Message">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                </a>
                <a class="share-btn" href="mailto:?subject=<?= $share_text ?>&body=<?= $share_body ?>" onclick="recordShare('email')" title="Email">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                </a>
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

    <script>
    var baseUrl = '<?= rtrim(base_url(), '/') ?>';
    var deviceId = localStorage.getItem('device_id');
    if (!deviceId) {
        deviceId = 'dev_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        localStorage.setItem('device_id', deviceId);
    }

    var video = document.getElementById('video');
    var watchedSeconds = 0;
    var viewRecorded = false;
    var lastRecordTime = 0;

    video.addEventListener('timeupdate', function() {
        if (!video.paused) {
            watchedSeconds += 0.25;
        }
        var now = Date.now();
        if (watchedSeconds >= 10 && (now - lastRecordTime > 30000 || !viewRecorded)) {
            recordView();
            lastRecordTime = now;
            viewRecorded = true;
        }
    });

    window.addEventListener('beforeunload', function() {
        if (watchedSeconds >= 3) recordView();
    });

    function recordView() {
        var pct = video.duration ? Math.round(video.currentTime / video.duration * 100) : 0;
        var fd = new FormData();
        fd.append('promo', 'how_to_listen');
        fd.append('watched', Math.round(watchedSeconds));
        fd.append('percent', pct);
        fetch(baseUrl + '/promos/record_view', {
            method: 'POST',
            body: fd,
            headers: { 'X-Device-Id': deviceId }
        }).catch(function() {});
    }

    function recordShare(method) {
        var fd = new FormData();
        fd.append('share_type', 'promo');
        fd.append('share_method', method);
        fetch(baseUrl + '/api/record_share', {
            method: 'POST',
            body: fd,
            headers: { 'X-Device-Id': deviceId }
        }).catch(function() {});
    }

    function copyLink() {
        var url = baseUrl + '/share/promo';
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

    function toast(msg) {
        var el = document.getElementById('toast');
        el.textContent = msg;
        el.classList.add('show');
        setTimeout(function() { el.classList.remove('show'); }, 2500);
    }
    </script>
</body>
</html>
