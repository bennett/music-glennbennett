# Admin Technical Reference

Authentication, device tracking, and library scanner internals.

---

## Library Scanner

### Trigger
- Admin: Dashboard — "Scan Library" button
- URL: `/admin/scan_library`

### Behavior
1. Scan `/songs/` directory for MP3 files
2. For each MP3:
   - Read ID3 tags (title, artist, album, track, year)
   - Calculate file hash
   - Extract embedded cover art (if present)
   - Check if song exists in database (by filename)
   - Create or update song record
3. Group songs by album tag
4. Create/update album records
5. Link songs to albums via `album_songs`

### Album Creation Rules
- Albums created from unique MP3 "album" tag values
- Album artist = most common artist in album's songs
- Album year = most common year in album's songs

### Misc Album
The "Misc" album is a catch-all for songs that don't belong to a named album:
- Songs with **no album tag** in their MP3 metadata — assigned to "Misc"
- Songs with an album tag but **fewer songs than `min_album_songs`** (default: 2) — grouped into "Misc"
- The album name is always **"Misc"** (not "Unknown Album", not "Miscellaneous")
- **This is not an error** - it's expected behavior for standalone tracks, singles, or untagged files
- Misc songs appear in the player's album dropdown like any other album
- In the admin view, Misc songs are shown with a green border (informational, not a warning)

### Cover Art During Scan
1. Check `/songs/imgs/albums/` for matching cover (flexible matching)
2. If not found, extract embedded cover from MP3
3. Store extracted cover in `/songs/imgs/songs/`

---

## Debugging

### Debug Endpoints

| URL | Purpose |
|-----|---------|
| `/share/debug/{song_id}` | Debug song cover lookup (CDN URLs) |
| `/share/test_image/{song_id}` | Debug share image generation (filesystem) |
| `/share/fonts` | Check if required fonts are installed |
| `/diagnose` | Full system diagnostics |

### Share Image Debug (`/share/test_image/{song_id}`)

Shows step-by-step:
1. **Configuration** - Paths being used
2. **Directory listing** - Files in albums directory
3. **Song info** - Title, cover_filename from database
4. **Album lookup** - Which album, normalized name for matching
5. **Cover path** - Final filesystem path found
6. **GD support** - Image format support
7. **Generated image** - Actual output

### Common Issues

#### Share image shows placeholder (no cover)
1. Visit `/share/test_image/{song_id}`
2. Check "music_origin_path exists?" - should be true
3. Check "albums dir exists?" - should be true
4. Check file listing matches album name
5. Verify normalized names match

#### Album cover not found
```php
// Album title: "Milestones"
// Normalized: "milestones" (lowercase, no separators)

// File must normalize to same:
// milestones.jpg -> "milestones" (match)
// Milestones.jpg -> "milestones" (match)
// Mile_Stones.jpg -> "milestones" (match)
```

#### Facebook not showing image
1. Image must be publicly accessible
2. Use Facebook Sharing Debugger: `https://developers.facebook.com/tools/debug/`
3. Enter URL: `https://music.glennbennett.com/share/song/{id}`
4. Click "Scrape Again" to refresh cache

#### Fonts not working
1. Visit `/share/fonts`
2. Download missing fonts from Google Fonts
3. Upload to `/assets/fonts/`
4. Required: `PlayfairDisplay-Black.ttf`, `Montserrat-Regular.ttf`

### Diagnostic Checklists

#### Cover Art Not Loading
- `music_origin_path` set correctly in config?
- `/songs/imgs/albums/` directory exists on server?
- Album cover file exists with correct name?
- Filename normalizes to match album title?
- File readable by web server (permissions)?

#### CDN Not Serving Files
- Origin server accessible?
- File exists at origin path?
- CDN pull zone configured correctly?
- Try direct origin URL first

#### Share Image Generation
- GD library installed with JPEG/PNG/WebP support?
- Fonts uploaded to `/assets/fonts/`?
- Cover image loadable by GD?
- Memory limit sufficient for image processing?

### Logging

Add to controller for debugging:
```php
log_message('debug', 'Cover path: ' . $cover_path);
log_message('debug', 'File exists: ' . (file_exists($cover_path) ? 'yes' : 'no'));
```

Check logs at: `application/logs/log-{date}.php`
