# Service Worker & Deployment

## Service Worker (PWA)

### File: `sw.js`

### Features
- Caches static assets on install
- Offline playback via audio file caching
- Background precache of all songs on activate
- Update detection and in-app update banner

### Cache Strategy
- **Navigation requests (HTML pages)**: Network-first — always loads fresh HTML when online, falls back to cache when offline
- **Static assets (JS/CSS)**: Network-first — always loads fresh files when online, falls back to cache when offline. This ensures version bumps take effect immediately without manual cache clearing.
- **API calls**: Network-first, cache fallback for offline
- **Audio files**: Cache-first for offline playback; range requests go to network with background caching of full file
- **Images**: Cache-first

### Update Flow
1. Browser checks for new `sw.js` on each page load and hourly
2. If `sw.js` has changed, browser downloads and installs the new SW
3. New SW caches fresh static assets (JS/CSS/HTML) during install
4. Update banner appears: "New version available"
5. User clicks "Update" — new SW activates — `controllerchange` fires — page reloads
6. Page loads fresh HTML (network-first) with updated version

---

## Deployment

### FTP Connection
Uses an lftp bookmark called `music` which stores host + credentials:
- Host: `ftp.tsgimh.com`
- User: `music@music.glennbennett.com`
- Connect with: `open music` inside lftp commands
- `~/.lftprc` has `set ssl:verify-certificate no` (host SSL cert doesn't match hostname)
- Credentials also in `~/.netrc` (chmod 600) as fallback

**NEVER use `mirror -R`** on the whole project — it will overwrite production config with local config and break the site.

### Deploy Process (individual file puts, NOT mirror)
1. Back up live files we're about to overwrite:
   ```
   mkdir -p ~/Herd/music/backups/v{VERSION}-{YYYYMMDD}
   lftp -e "open music; \
   get sw.js -o ~/Herd/music/backups/v{VERSION}-{YYYYMMDD}/sw.js; \
   get assets/js/music-player.js -o ~/Herd/music/backups/v{VERSION}-{YYYYMMDD}/music-player.js; \
   ... (each changed file) \
   bye"
   ```
2. Write a `MANIFEST.txt` in the backup directory:
   ```
   # Local Path → Server Path
   application/models/Song_model.php → /application/models/Song_model.php
   sw.js → /sw.js
   ```
3. Upload only the changed files:
   ```
   lftp -e "open music; \
   put ~/Herd/music/sw.js -o sw.js; \
   put ~/Herd/music/assets/js/music-player.js -o assets/js/music-player.js; \
   ... (each changed file) \
   bye"
   ```
4. Verify the site loads

### Files That Must NEVER Be Uploaded
- `application/config/database.php` — production credentials differ from local
- `application/config/config.php` — works for both but no reason to touch it
- `.claude/` — local tooling only
- `backups/`, `deploy/` — local only

---

## Backup & Restore

### Backup Directory Structure
```
backups/
  production-config/              <- permanent, never overwritten
    database.php                  <- production DB credentials
    config.php                    <- production CodeIgniter config
  v3.0.56-20260223/               <- live files from BEFORE v3.0.56 deploy
    MANIFEST.txt                  <- maps local paths -> server paths
    application/models/...        <- files mirror server directory structure
    assets/js/...
    sw.js
  v3.0.57-20260223/
    MANIFEST.txt
    ...
```

### Backup Naming
`backups/v{VERSION}-{YYYYMMDD}/`
- Contains the **live production** files downloaded *before* that version was deployed
- Files are stored mirroring the server directory structure
- `MANIFEST.txt` maps each file to its server path for easy restore

### Restore Script
`backups/restore.sh`
```bash
# List all available backups
./backups/restore.sh

# Restore to the state before v3.0.57 was deployed
./backups/restore.sh v3.0.57-20260223

# Emergency: restore production database/config credentials
./backups/restore.sh production-config
```

The restore script reads `MANIFEST.txt`, shows which files will be uploaded, asks for confirmation, then uploads each file to its server path via `lftp put`.

---

## Version Sync
**FOUR places must stay in sync on every release:**
1. `sw.js` — `const APP_VERSION = '3.0.X'` (line 3)
2. `application/views/player/index.php` — `menuVersion` span (e.g., `v3.0.X`)
3. `application/views/player/index.php` — `?v=3.0.X` query strings on CSS and JS `<link>`/`<script>` tags
4. `requirements/CHANGELOG.md` — Current Version header + version history table row

---

## Rules for Development

### Before Making Changes
1. **Read the docs** (this directory and `/docs/`)
2. **Check relevant section** for existing behavior
3. **Understand the "why"** before changing

### Core Rules
1. **MP3 files are the source of truth** for song/album data
2. **Never remove filesystem scanning** for album covers
3. **Always use flexible matching** (case-insensitive, ignore separators)
4. **Misc songs are not errors** — they're songs without album assignment
5. **Track order comes from MP3 files**, not database
6. **Keep it simple** — avoid over-engineering

### After Making Changes
1. **Update relevant docs** with new behavior
2. **Add to version history** (`requirements/CHANGELOG.md`)
3. **Test the affected functionality**
4. **Bump version** in all 4 places

### Common Pitfalls (Things That Have Broken Before)

| Pitfall | What Went Wrong | Prevention |
|---------|----------------|------------|
| **base_url hardcoded** | Local dev loaded JS/CSS from production; all local changes had no effect | `base_url` must auto-detect from `$_SERVER['HTTP_HOST']` — NEVER hardcode |
| **API returns strings** | `duration` is `"218"` not `218`; JS `reduce` concatenated strings | Always use `Number()` or `parseInt()` when doing math on API values |
| **Favorites field name** | Some endpoints returned `favorited`, others `is_favorite` | ALL favorites responses must use `is_favorite` — centralized in `Favorite_model` |
| **POST encoding** | JS sent JSON but PHP expects form-encoded | Player POST requests use `application/x-www-form-urlencoded` with `key=value` body |
| **FK constraint on favorites** | Insert failed because device didn't exist | `Favorite_model::ensure_device()` creates the device row first |
| **Setting `audio.src = ''`** | Fires the `error` event, triggering `nextTrack()` | Guard in error handler: skip if `!this.audio.src` |
| **Service Worker caching** | Cache-first meant old SW served stale files | ALL assets now network-first (3.0.52). NEVER change back to cache-first. |
| **"Unknown Album" vs "Misc"** | Old DB records still said "Unknown Album" | Scanner renames on scan; name is always "Misc" |
| **Favorites code scattered** | Duplicate logic across models and controllers | ALL favorites logic in `Favorite_model.php` only |
| **database/ directory** | Old SQL backup files — editing has no effect | Don't edit files in `database/` |
