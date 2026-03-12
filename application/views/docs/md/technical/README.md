# Developer Reference

Technical documentation for working on the Bennett Music Player codebase.

---

## Development & Testing Environment

### Local Setup
The app runs locally via **Laravel Herd** at `http://music.test`. The codebase lives alongside the main `glennbennett.com` site on the same machine.

### Song Files Must Be on the Same Server
The MP3 files and cover art images are **not part of this repository**. They live in a sibling directory on the filesystem:

```
/Users/bennett/Herd/
├── music/                    <- This app (music.test)
├── glennbennett/
│   └── songs/                <- MP3 files + cover art (music_origin_path)
│       ├── *.mp3
│       └── imgs/
└── glennbennett.com/
    └── songs/                <- Alternate path (production layout)
```

The `music_origin_path` config resolves via `realpath()` trying both sibling paths:
```php
realpath(FCPATH . '/../glennbennett/songs')   // local dev
?: realpath(FCPATH . '/../glennbennett.com/songs')  // production
```

**If the song files aren't present on the machine, these features break:**
- Library scanner — can't find MP3 files to read metadata from
- Share image generation — can't load cover art for GD compositing (falls back to placeholder)
- Admin diagnostics — filesystem checks will fail

**These features still work without local song files:**
- Audio playback — streams from CDN (`glb-songs.b-cdn.net`), not from local filesystem
- Cover art display in the player — loaded from CDN URLs
- All player functionality — the app only needs the database and CDN

### Dev vs Production Differences
| Aspect | Local Dev (`music.test`) | Production (`music.glennbennett.com`) |
|--------|--------------------------|---------------------------------------|
| `base_url` | Auto-detected from `$_SERVER['HTTP_HOST']` | Auto-detected (same code) |
| Song files | `../glennbennett/songs/` | `../glennbennett.com/songs/` |
| Audio streaming | CDN (same as production) | CDN |
| Cover images | CDN (same as production) | CDN |
| Share images | Generated from local files | Generated from local files |
| Database | `music_local` via root@localhost | Production MySQL |
| PHP | PHP 8.1 via Herd | PHP 8.1 |

### Testing Complications
1. **JS/CSS changes take effect immediately** on `music.test` — no deployment needed. But phone testing requires the phone to be on the same network and accessing `music.test` (not the production URL).
2. **`base_url` must auto-detect** — previously hardcoded to production, which caused local page loads to pull JS/CSS from `music.glennbennett.com` instead of `music.test`. All local JS changes appeared to have no effect. See config.php.
3. **CDN caching** — audio and cover art are served from Bunny CDN. Changes to actual song files or cover images may take time to propagate. The `?h=` cache-busting parameter helps, but only after a rescan updates the file hash in the database.
4. **Share image testing** — can be done locally at `music.test/share/image?song=ID` and `music.test/admin/share_images`. But Facebook's crawler can only reach the production URL, so testing with the Facebook Sharing Debugger requires deploying first.

### How Code Updates Reach the Browser

**The update chain:**
1. Developer bumps version in 4 places (sw.js, index.php x2, requirements/CHANGELOG.md)
2. User loads/reloads the page
3. Browser detects `sw.js` changed — downloads and installs new SW
4. New SW calls `skipWaiting()` + `clients.claim()` — takes control immediately
5. New SW's `activate` event deletes old version caches
6. Next page load: fresh HTML (network-first) — has new `?v=X.Y.Z` query strings — fresh JS/CSS (network-first)

**As of v3.0.52, ALL request types use network-first** (HTML, JS, CSS, API calls). This means:
- When **online**: always fetches fresh from server, then caches for offline use
- When **offline**: falls back to cached version
- Audio and images are the exception — audio uses cache-first for offline playback

**Why this matters for development:**
- After changing code, a simple page reload should pick up changes immediately
- No need to manually clear caches, hard-refresh, or unregister service workers
- The `?v=3.0.X` query strings on JS/CSS tags act as cache-busters for any intermediate CDN or browser HTTP cache
- The only time a manual hard-refresh (`Cmd+Shift+R`) is needed is if the SW itself hasn't been updated yet

**If changes still aren't visible after reload:**
1. Check DevTools Console for the SW version: `[SW] Service Worker loaded, version: X.Y.Z`
2. If it shows an old version, the browser hasn't fetched the new `sw.js` yet — do a hard refresh
3. Check DevTools — Application — Service Workers — if "waiting to activate" is shown, click "skipWaiting"
4. Nuclear option: Application — Storage — Clear site data

**NEVER go back to cache-first for static assets.** Cache-first was the root cause of many "my changes don't show up" bugs. Network-first costs a tiny bit of latency but guarantees updates are visible immediately.

---

## Architecture

### Timezone Convention
- **All timestamps stored in UTC** — API uses `gmdate('Y-m-d H:i:s')`, Admin uses `date_default_timezone_set('UTC')`
- **Display in Pacific** — Admin views use `to_pacific()` helper (defined in `device_detail.php`, `dashboard_new.php`, `devices_new.php`) which converts from UTC to `America/Los_Angeles`
- **No MySQL timezone manipulation** — do NOT use `SET time_zone` (production lacks timezone tables, silently fails)
- **TIMESTAMP columns** (`played_at`, `first_seen`, `last_seen`) store UTC values; MySQL session timezone must not be changed

### Key Files
```
application/
├── config/
│   ├── config.php            # base_url (auto-detect!), session, etc.
│   └── music_config.php      # CDN URLs, paths, settings
├── controllers/
│   ├── Admin.php             # Admin interface
│   ├── Api.php               # Public JSON API (albums, songs, favorites, plays, shares)
│   ├── Player.php            # Main player page + player-specific AJAX endpoints
│   └── Share.php             # Social sharing images & OG redirect pages
├── models/
│   ├── Album_model.php       # Album CRUD & cover URLs
│   ├── Favorite_model.php    # ALL favorites logic (song + album favorites)
│   └── Song_model.php        # Song CRUD, URLs, library scanner
└── views/
    ├── admin/
    │   ├── albums.php        # Album management
    │   └── songs.php         # Library view with diagnostics
    └── player/
        └── index.php         # Main player interface (server-rendered first load)

assets/
├── css/
│   └── music-player.css      # All player styles
└── js/
    └── music-player.js       # All player JS (the ACTIVE file — NOT root music-player.js)

sw.js                          # Service worker (versioned, must stay in sync)
database/schema.sql            # Reference schema (old backups — don't edit)
```

### File Structure

#### Music Directory (`/songs/`)
```
/songs/
├── *.mp3                     # Audio files (flat, no subdirectories)
├── imgs/
│   ├── albums/               # Album cover art (manually placed)
│   │   ├── milestones.jpg
│   │   └── {album-name}.{ext}
│   └── songs/                # Song covers (extracted from ID3)
│       └── {hash}.webp
```

#### Audio Files
- Location: `/songs/*.mp3` (root level, flat structure)
- Format: MP3
- Naming: Matches song title (e.g., `Colorado Snow.mp3`)

#### Album Cover Images
- Location: `/songs/imgs/albums/`
- Naming: Matches album title with flexible matching
- Formats: jpg, jpeg, png, webp, gif

#### Song Cover Images
- Location: `/songs/imgs/songs/`
- Naming: `{hash}.webp` where hash is from file content
- Source: Extracted from MP3 ID3 embedded artwork during scan

---

## Configuration

### File: `application/config/config.php`

| Key | Purpose | Notes |
|-----|---------|-------|
| `base_url` | Auto-detected from `$_SERVER['HTTP_HOST']` | Do NOT hardcode. Auto-detect ensures local dev (music.test) and production (music.glennbennett.com) both work. Hardcoding to production causes local JS/CSS to load from the production server. |

### File: `application/config/music_config.php`

| Key | Purpose | Example |
|-----|---------|---------|
| `music_origin_path` | Filesystem path to music directory | `/home/user/public_html/songs` |
| `music_cdn_url` | CDN URL for audio streaming | `https://glb-songs.b-cdn.net/songs` |
| `cover_art_url` | CDN URL for cover images | `https://glb-songs.b-cdn.net/songs/imgs` |
| `cover_art_path` | Filesystem path for covers (legacy) | `/home/user/public_html/songs/imgs` |
| `min_album_songs` | Min songs to form album | `2` |

### Path Relationships
```
music_origin_path = /home/user/public_html/songs
                    ├── *.mp3 (audio files)
                    └── imgs/
                        ├── albums/ (album covers)
                        └── songs/ (extracted song covers)

music_cdn_url     = https://cdn.example.com/songs (serves same structure)
cover_art_url     = https://cdn.example.com/songs/imgs
```

---

## CDN Architecture

### Overview
The system uses a **pull-zone CDN** (Bunny CDN) to serve media files. The CDN pulls from the origin server on first request, then caches files at edge locations.

### How It Works
```
User Request -> CDN Edge -> (Cache Miss?) -> Origin Server
                  |                            |
              Cache Hit                    Fetch & Cache
                  |                            |
              Serve File <-<-<-<-<-<-<-<-<- Return to Edge
```

### URL Mapping
| Content Type | Origin Path | CDN URL |
|--------------|-------------|---------|
| Audio files | `/songs/*.mp3` | `https://glb-songs.b-cdn.net/songs/*.mp3` |
| Album covers | `/songs/imgs/albums/*.jpg` | `https://glb-songs.b-cdn.net/songs/imgs/albums/*.jpg` |
| Song covers | `/songs/imgs/songs/*.webp` | `https://glb-songs.b-cdn.net/songs/imgs/songs/*.webp` |

### CDN Provider: Bunny CDN
- **Pull Zone**: `glb-songs.b-cdn.net`
- **Origin**: `glennbennett.com/songs`
- **Cache**: Automatic, edge-based

### URL Construction in Code
```php
// Audio streaming URL
$stream_url = $cdn_url . '/' . $song->filename;
// Example: https://glb-songs.b-cdn.net/songs/Colorado Snow.mp3

// Album cover URL (from flexible matching)
$cover_url = $cover_art_url . '/albums/' . $matched_file;
// Example: https://glb-songs.b-cdn.net/songs/imgs/albums/milestones.jpg

// Song cover URL (from database)
$cover_url = $cover_art_url . '/' . $song->cover_filename;
// Example: https://glb-songs.b-cdn.net/songs/imgs/songs/abc123.webp
```

### Important Notes
1. **Origin must be accessible** - CDN pulls from origin on cache miss
2. **File paths must match** - CDN URL structure mirrors origin
3. **No trailing slashes** - URLs constructed without trailing slashes
4. **Case sensitive** - File names are case-sensitive on origin
