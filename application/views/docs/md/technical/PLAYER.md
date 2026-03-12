# Player Technical Reference

How audio streaming, cover art, and the update system work under the hood.

---

## How Files Are Accessed (Streaming & Serving)

### Overview
Files are stored on the origin server filesystem but served to the browser via Bunny CDN. The app never streams audio or images directly from the origin — it always constructs CDN URLs.

### Audio Streaming
1. Database stores `songs.filename` (e.g., `Colorado Snow.mp3`)
2. `Song_model::get_stream_url()` builds the CDN URL:
   ```
   {music_cdn_url}/{filename}?h={file_hash_prefix}
   ```
3. Example: `https://glb-songs.b-cdn.net/songs/Colorado Snow.mp3?h=a1b2c3d4e5`
4. The `?h=` parameter is cache-busting — uses first 10 chars of the file's MD5 hash
5. If `music_cdn_url` is empty, no stream URL is generated (songs won't play)

### Song Cover Art URLs
1. Database stores `songs.cover_filename` (e.g., `songs/abc123.webp`)
2. `Song_model::get_cover_url()` builds the CDN URL:
   ```
   {cover_art_url}/{cover_filename}?h={md5_of_cover_filename}
   ```
3. Example: `https://glb-songs.b-cdn.net/songs/imgs/songs/abc123.webp?h=3520e4cd`
4. The `?h=` parameter uses `md5(cover_filename)` — so when cover art changes and the scanner generates a new filename, the cache-buster changes too, forcing the CDN/SW to fetch the new image
5. Falls back to `base_url('uploads/covers/{cover_filename}')` if no CDN URL configured
6. Returns `null` if song has no `cover_filename`

### Album Cover Art URLs
1. `Album_model::get_cover_url()` first scans the filesystem at `{music_origin_path}/imgs/albums/` using flexible name matching
2. If a matching file is found:
   ```
   {cover_art_url}/albums/{matched_filename}?t={file_mtime}
   ```
3. Example: `https://glb-songs.b-cdn.net/songs/imgs/albums/milestones.jpg?t=1769654398`
4. The `?t=` parameter uses the file's modification time — changes when the file is replaced on disk
5. If no filesystem match, falls back to `albums.cover_filename` from database:
   ```
   {cover_art_url}/{cover_filename}?t={updated_at_timestamp}
   ```

### Config Values (from `music_config.php`)
| Config Key | Purpose | Example |
|------------|---------|---------|
| `music_origin_path` | Local filesystem path to MP3s and images | `/path/to/glennbennett/songs` |
| `music_cdn_url` | CDN base URL for streaming audio | `https://glb-songs.b-cdn.net/songs` |
| `cover_art_url` | CDN base URL for cover images | `https://glb-songs.b-cdn.net/songs/imgs/` |
| `cover_art_path` | Local filesystem path for cover images | Derived from `music_origin_path` + `/imgs/` |

### Flow: Browser Requests a Song
```
Browser -> CDN URL (glb-songs.b-cdn.net/songs/Colorado Snow.mp3)
       -> Bunny CDN checks cache
       -> If miss: pulls from origin (glennbennett.com/songs/Colorado Snow.mp3)
       -> Caches and serves to browser
       -> Service Worker caches for offline playback
```

### Share Image Generation (Server-Side)
Share images (`/share/image?song=ID`) are the one case where the **server reads files directly from the filesystem** (not via CDN):
- `Share::image()` loads cover art from `{music_origin_path}/imgs/` using `imagecreatefromjpeg()` etc.
- This is because GD library needs a local file path, not a URL
- The generated JPEG is served directly from PHP, not cached on CDN

---

## Album Cover Art System

### Lookup Priority
1. **FIRST**: Scan filesystem `/songs/imgs/albums/` with flexible matching
2. **SECOND**: Fall back to `albums.cover_filename` from database

### Flexible Matching Algorithm
```php
// Normalize for comparison: lowercase, remove all separators
$album_normalized = strtolower(str_replace(['_', ' ', '-'], '', $album->title));
$file_normalized = strtolower(str_replace(['_', ' ', '-'], '', $file_basename));
if ($file_normalized === $album_normalized) { /* match! */ }
```

### Match Examples
| File | Album Title | Match? |
|------|-------------|--------|
| `milestones.jpg` | Milestones | Yes |
| `Milestones.jpg` | Milestones | Yes |
| `MILESTONES.PNG` | Milestones | Yes |
| `mile_stones.jpg` | Milestones | Yes |
| `Mile-Stones.jpg` | Milestones | Yes |
| `mile stones.jpg` | Milestones | Yes |

### Supported Formats
jpg, jpeg, png, webp, gif

### URL Construction
```php
// If found in filesystem:
$url = $cover_art_url . '/albums/' . $filename;
// Example: https://glb-songs.b-cdn.net/songs/imgs/albums/milestones.jpg

// If using database fallback:
$url = $cover_art_url . '/' . $album->cover_filename;
```

---

## Song Cover Art System

### Source
Extracted from MP3 ID3 embedded artwork during library scan.

### Storage
- Saved to `/songs/imgs/songs/{hash}.webp`
- Hash is `md5()` of the **image data** (not the filename or MP3 hash)
- When cover art changes in the MP3, the scanner generates a new hash = new filename = new CDN URL
- Converted to WebP format at 512x512 max, quality 85

### Lookup Priority
1. Song's own `cover_filename` from database
2. Fall back to album cover

### Cache Busting Strategy

All CDN URLs include a cache-busting parameter so browsers and the service worker fetch fresh content when files change:

| Content | URL Pattern | Cache Param | Changes When |
|---------|-------------|-------------|--------------|
| Audio | `{cdn}/{filename}?h=X` | `md5(file_hash)[:10]` | MP3 file replaced |
| Song covers | `{cdn}/imgs/{cover_filename}?h=X` | `md5(cover_filename)[:8]` | Cover art re-extracted (new image = new filename = new hash) |
| Album covers (filesystem) | `{cdn}/imgs/albums/{file}?t=X` | `filemtime()` | Image file replaced on disk |
| Album covers (DB fallback) | `{cdn}/imgs/{cover_filename}?t=X` | `strtotime(updated_at)` | Album record updated |

**Key design decision**: Song cover filenames are based on `md5(image_data)`, not the song filename. This means replacing a song's embedded artwork produces a completely different cover URL — no CDN purge needed.
