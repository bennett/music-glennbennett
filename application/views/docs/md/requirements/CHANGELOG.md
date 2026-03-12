# Version History

**Current Version**: 3.1.5
**SW Version**: `const APP_VERSION = '3.1.5'`

| Version | Date | Changes |
|---------|------|---------|
| 3.1.5 | 2026-03-11 | Admin exclusion for podcast/promo stats (filters by excluded devices and admin IPs). Added IP tracking to podcast_plays and promo_views tables. |
| 3.1.4 | 2026-03-11 | Consolidated Share Current Song, Share Album, Share This App, and Show QR Code into a single collapsible "Share" menu item. |
| 3.1.3 | 2026-03-11 | Moved Visit GlennBennett.com into About collapsible sub-menu. About sub-items: About This App, Featured Podcast, How to Actually Listen, Visit GlennBennett.com. |
| 3.1.2 | 2026-03-11 | About menu item is now collapsible with chevron toggle. Sub-items: About This App, Featured Podcast Episode, How to Actually Listen. Podcast listed first. |
| 3.1.1 | 2026-03-11 | Promo Video and Featured Podcast now appear as sub-menu items under About in the player menu. |
| 3.1.0 | 2026-03-11 | Version bump to 3.1.x. Admin dashboard: combined Podcast and Promo stats into single "Other" card. |
| 3.0.101 | 2026-03-11 | Moved Promo Video and Featured Podcast menu items into the About modal as links to shorten the main menu. |
| 3.0.100 | 2026-03-11 | Dashboard: podcast/promo stats in tabs under "Other" AND as separate cards. Added "Other" count to Library Quick Stats row. |
| 3.0.99 | 2026-03-11 | Moved podcast and promo stats into tabbed dashboard view under "Other" section (per-period: Today/Week/Month/All Time). Removed separate standalone cards. |
| 3.0.98 | 2026-03-11 | Promo video page: new `/promos` page for "How to Actually Listen" video with native video player, social share buttons, view tracking via `promo_views` table, and admin dashboard stats. Menu link added. |
| 3.0.97 | 2026-03-11 | Fix admin 500 error: podcast stats used SQLite-specific query (`sqlite_master`) but production uses MySQL. Switched to CodeIgniter's `table_exists()`. Fixed podcast_plays CREATE TABLE to use MySQL syntax. |
| 3.0.96 | 2026-03-11 | Cache-bust podcast OG image URL to force Facebook/social media to refetch updated share image. |
| 3.0.95 | 2026-03-11 | Podcast share image: removed artist name, updated type label to "You Might Want to Check Out / This Featured Episode", added multi-line type label support in OG image generator. |
| 3.0.94 | 2026-03-11 | Podcast page: updated episode description to match cover art. Menu item renamed to "Featured Podcast" with clearer description. |
| 3.0.93 | 2026-03-11 | Podcast player: new `/podcast` page for "Algorithmic Amnesia: A Deep Dive" with audio player, cover art, 15s/30s skip, playback speed, and social share buttons (Facebook, X, WhatsApp, SMS, Email, Copy Link). Menu link added. OG image generation with podcast cover. Play tracking via `podcast_plays` table. Podcast stats section on admin dashboard. Auto-caches episode for offline via service worker. |
| 3.0.92 | 2026-03-09 | Auto-resume survives app kill: interrupted state persisted to localStorage so cold-start after iOS kills the PWA (e.g. podcast app took audio focus) auto-resumes from saved position. 30-minute time limit prevents stale auto-play. |
| 3.0.91 | 2026-03-08 | Auto-resume after CarPlay/Bluetooth reconnect: tracks involuntary pauses (OS interrupt vs user pause) and auto-resumes when audio session becomes active or app returns to foreground — matching native music app behavior. |
| 3.0.90 | 2026-03-08 | Fixed CarPlay resume after car restart: play handler now detects stale audio sources (timed-out network connection, lost buffer) and reloads from saved position instead of silently failing. Added Screen Wake Lock API to prevent screen dimming during playback. |
| 3.0.89 | 2026-03-01 | Fixed shared song links not loading the correct album: `?song=` deep links now look up the song's album (or load Misc Songs for unlinked songs) so `autoplaySong()` can find the track. Restyled share debug page (`/share/debug/{id}`) with dark-theme card layout and status badges. |
| 3.0.88 | 2026-02-27 | Audio ducking for CarPlay: set `navigator.audioSession.type = 'playback'` so iOS automatically lowers music volume when navigation directions or Siri speak. |
| 3.0.87 | 2026-02-27 | Fixed "No albums found" on initial load: JS now waits for DOM to be ready before auto-loading first album. Player controller now falls back to next available album if first fails to load. |
| 3.0.84 | 2026-02-26 | Expanded player reordered: cover → controls → progress → secondary controls → info. Controls now directly under cover art so they're always visible. Removed Howler.js references and deleted unused `howler.min.js`. |
| 3.0.80 | 2026-02-26 | Player bar moved up 24px from bottom so play controls aren't hidden behind mobile browser toolbar. |
| 3.0.78 | 2026-02-26 | Cover art cache busting fix: song cover filenames now based on `md5(image_data)` instead of `md5(song_filename)`, so new art = new filename = new CDN URL. Cover URL `?h=` param now uses `md5(cover_filename)` instead of MP3 file hash. Scanner detects cover_filename changes. |
| 3.0.77 | 2026-02-25 | Documentation reorganization: split REQUIREMENTS.md into organized directories with a `/docs` controller serving sidebar navigation. Parsedown library added. |
| 3.0.76 | 2026-02-25 | Public `/api/popular` endpoint: returns most-played songs with CORS headers for cross-origin access. Excludes admin/excluded devices. Supports `?limit=` param (default 10, max 50). |
| 3.0.75 | 2026-02-24 | Timezone handling hardened: all timestamps stored as explicit UTC (gmdate), removed broken MySQL SET time_zone, PHP timezone set to UTC in Admin. Admin views use to_pacific() for display. |
| 3.0.74 | 2026-02-24 | Admin IP auto-login: known admin IPs skip login form. New IPs saved on first login. All play history from admin IP devices purged on login. Stats queries also filter by admin IP. New `admin_ips` table. |
| 3.0.73 | 2026-02-24 | Auto-register admin devices: any device used to access admin is marked excluded=2 and its play history is purged. Fixed all 15 SQL stats queries to use `excluded > 0` (was `excluded = 1`, missing admin devices). |
| 3.0.72 | 2026-02-24 | Repeat mode defaults to 'all' (loop album) instead of 'off'. Documented in requirements. |
| 3.0.71 | 2026-02-24 | Cover Art grid thumbnails doubled from 100px to 200px minimum. |
| 3.0.70 | 2026-02-24 | Bumped cover art resize to 512x512 (was 300 for songs, 500 for albums) with quality 85. Matches MediaSession largest artwork size. Re-scan needed to regenerate existing covers. |
| 3.0.69 | 2026-02-24 | Cover Art tab: click-to-enlarge lightbox with arrow key navigation and song title caption. Skips missing covers when navigating. |
| 3.0.68 | 2026-02-24 | Added cache busting to all cover art URLs (album + song covers via CDN). Replaced Files tab with Cover Art grid showing thumbnails for all songs with missing covers highlighted. |
| 3.0.67 | 2026-02-24 | Shared song links no longer auto-play; song loads and expanded player opens but waits for user to tap play. |
| 3.0.66 | 2026-02-24 | Updated About modal with Glenn's personal copy; menu description changed to "About this app". |
| 3.0.65 | 2026-02-24 | Scanner only updates updated_at when file hash or metadata actually changed; scan message now shows unchanged count. |
| 3.0.64 | 2026-02-24 | Admin library song date column now shows updated_at instead of created_at, so re-scanned files show their last scan date. |
| 3.0.63 | 2026-02-23 | Audio session recovery: persist playback state to localStorage (save every 5s, on pause, on visibility hidden), restore on cold start with saved position (paused, no auto-play), re-set full MediaSession metadata on resume, harden play handler to recover stalled/errored audio, clear saved state when album finishes. |
| 3.0.62 | 2026-02-23 | Spotify-style full-screen player: tapping a song opens expanded player automatically, swipe-down gesture to dismiss, down-chevron minimize button with swipe pill indicator, auto-expands on shared song links. |
| 3.0.61 | 2026-02-23 | Added small hint labels under each stat number and list titles as reminders. |
| 3.0.60 | 2026-02-23 | Moved "Most Completed Songs" and "Top Songs" into the tabbed view so they show per-period data. |
| 3.0.59 | 2026-02-23 | Dashboard tabs restyled to pill/button style for clearer active state. |
| 3.0.58 | 2026-02-23 | Admin dashboard tabbed stats: unified Today/This Week/This Month/All Time tabs showing same 6 stats + top song + top 8 songs per period. |
| 3.0.57 | 2026-02-23 | Scanner now does full rebuild: truncates album_songs before rebuilding from MP3 tags, removes orphan songs, removes empty albums. |
| 3.0.56 | 2026-02-23 | Fixed CarPlay progress bar not starting at 0: setPositionState now called on loadedmetadata and synced every 5s. |
| 3.0.55 | 2026-02-22 | Misc album always shows stacked covers (ignores its cover_url in both JS and server template). |
| 3.0.54 | 2026-02-22 | Fixed flaky play button: added hasSource flag to track valid source state explicitly. |
| 3.0.53 | 2026-02-22 | Fixed progress bar grey track not visible; improved mainPlayToggle logic. |
| 3.0.52 | 2026-02-22 | Changed SW static asset strategy from cache-first to network-first. |
| 3.0.51 | 2026-02-22 | Removed artist name from track list and album info (single-artist app). |
| 3.0.50 | 2026-02-22 | Unified server-rendered album_content.php to match JS renderAlbum(). |
| 3.0.49 | 2026-02-22 | Fixed progress bar handle z-index. |
| 3.0.48 | 2026-02-22 | Track list text flush left; progress bar with draggable handle and touch scrubbing. |
| 3.0.47 | 2026-02-22 | Fixed album total duration showing scientific notation. |
| 3.0.46 | 2026-02-22 | Favorites and Misc moved to end of album dropdown; unfavoriting removes from list immediately. |
| 3.0.45 | 2026-02-22 | Stacked cover art for Favorites; song cover thumbnails in track list. |
| 3.0.44 | 2026-02-22 | Centralized favorites into Favorite_model; fixed checkFavoriteStatus, debounce, POST encoding. |
| 3.0.43 | 2026-02-22 | Reset playback position on track finish/skip; share images use Poppins font; fixed duplicate Misc album link. |
| 3.0.43 | 2026-02-21 | Fixed Facebook sharing: added OG tags to main player view; added fallback og:image; improved share image error handling. |
| 3.0.41 | 2026-02-21 | Fixed base_url to auto-detect host (was hardcoded to production). |
| 3.0.40 | 2026-02-21 | MediaSession: re-register ALL handlers on every track change; added setPositionState; multiple artwork sizes. |
| 3.0.38 | 2026-02-21 | CarPlay: removed seekbackward/seekforward handler registration (lets iOS show skip buttons). |
| 3.0.37 | 2026-02-21 | Updates auto-apply: Check for Updates closes menu and reloads immediately; new SW auto-activates. |
| 3.0.36 | 2026-02-21 | CarPlay: seekbackward/seekforward now map to previousTrack/nextTrack. |
| 3.0.35 | 2026-02-20 | Fixed auto-play on album switch; renamed "Unknown Album" to "Misc" everywhere. |
| 3.0.34 | 2026-02-20 | Fixed updates not applying: navigation requests now network-first; version query strings on JS/CSS. |
| 3.0.33 | 2026-02-20 | Fixed album switcher; favorites model uses device_id; "Unknown Album" renamed to "Misc". |
| 3.0.32 | 2026-02-20 | Fixed master Play button: added id="mainPlayBtn" with mainPlayToggle(). |
| 3.0.31 | 2026-02-20 | Dynamic music_origin_path resolution (local Herd + production). |
| 3.0.30 | 2026-02-20 | CarPlay: no-op seek handlers; dynamic og:image; MediaSession recovery on visibilitychange. |
| 3.0.29 | 2026-02-18 | Fixed update loop: synced menuVersion with sw.js; native share uses /share/ URLs. |
| 3.0.28 | 2026-02-18 | Fixed share URLs in navigator.share. |
| 3.0.27 | 2026-02-18 | Fixed share image paths, added CDN docs, debugging section. |
| 3.0.26 | 2026-02-18 | Restored flexible cover matching, added REQUIREMENTS.md. |
| 3.0.25 | 2026-02-18 | Simplified issue detection, misc is informational. |
| 3.0.24 | 2026-02-18 | Fixed $track_numbers variable error. |
| 3.0.23 | 2026-02-18 | Simplified cover art (BROKEN - removed flexible matching). |
| 3.0.22 | 2026-02-18 | Sort by file track#, sortable tables. |
| 3.0.21 | 2026-02-18 | Added File# column, track mismatch detection. |
| 3.0.20 | 2026-02-18 | Album diagnostics, multi-album detection. |
| 3.0.19 | 2026-02-18 | Songs detail view with updated_at. |
| 3.0.18 | 2026-02-18 | Complete field listing in albums view. |
| 3.0.17 | 2026-02-17 | Admin albums with track details. |
| 3.0.10-16 | 2026-02-17 | Share image generator development. |
| 3.0.x | 2026-01-30 | Initial flexible cover matching. |
