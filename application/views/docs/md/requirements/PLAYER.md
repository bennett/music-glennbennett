# Player Interface

## Features
- Album browsing with cover art
- Track listing with play controls
- Now playing bar with progress scrubbing
- Shuffle and repeat modes (repeat all is the default — album loops continuously)
- Keyboard shortcuts
- Favorites (per-device, heart icon on each track)
- PWA support (installable, offline capable)
- CarPlay / lock screen controls via MediaSession API
- Background playback recovery after phone sleep or app suspension

## URL Parameters (Deep Links)
The player supports direct links to specific content via query parameters:

| Parameter | Example | Behavior |
|-----------|---------|----------|
| `?song=ID` | `/?song=1` | Loads the album containing the song (or Misc Songs if the song isn't in any album), then auto-selects the song in the expanded player |
| `?album=ID` | `/?album=3` | Loads the specified album |

Songs may or may not belong to a named album — both are normal states. Songs without an album association are **misc songs** and are grouped into a virtual "Misc Songs" album. When a deep link points to a misc song, the Player controller builds this pseudo-album on the fly so the track list includes the song. See [Sharing docs](SHARING.md#song-deep-links-song-parameter) for full details.

## Album Dropdown
- Lists all real albums from the database first
- "Favorites" option appears dynamically at the end (before Misc) only when the device has favorites
- "Misc Songs" option appears at the very end if there are unassigned songs
- Order: `[Album 1] [Album 2] ... [Favorites] [Misc Songs]`
- Switching albums loads the new album's tracks without interrupting playback until data is ready
- If album load fails, the dropdown reverts to the previous selection

## Track List Rendering
- Each track row shows: cover thumbnail (or track number), title, artist, duration
- **Songs with `cover_url`**: Show a 44x44 rounded thumbnail image (`.track-thumb`)
- **Songs without `cover_url`**: Show the track number as text (`.track-number`)
- Duration is calculated using `Number(s.duration)` — duration comes from API as a STRING
- Total album duration: `songs.reduce((sum, s) => sum + (Number(s.duration) || 0), 0)`

## Album Cover Display
- **Real albums with `cover_url`**: Show the album cover image (`.album-cover`)
- **Favorites with songs**: Show stacked covers — up to 3 unique song cover images fanned from bottom-left to upper-right with rotation and shadows (`.stacked-covers.count-N`)
- **Albums without cover**: Show a placeholder with the first letter of the title (`.album-cover.placeholder`)

## Play Button Behavior
- The master Play button (`id="mainPlayBtn"`) and per-track play buttons stay in sync
- `mainPlayToggle()` toggles play/pause state; if nothing is loaded, starts from track 1
- All play/pause icons update together via `updatePlayPauseIcons()`

## Keyboard Shortcuts

| Key | Action | Condition |
|-----|--------|-----------|
| Space | Play/Pause | Always |
| ← | Previous track | Expanded player open, or Cmd/Ctrl held |
| → | Next track | Expanded player open, or Cmd/Ctrl held |

---

## Favorites System

### Architecture
- **All favorites logic lives in `Favorite_model.php`** — no favorites code in Song_model, Album_model, or inline in controllers
- Favorites are per-device (identified by `device_id` stored in localStorage)
- There is no user account system — favorites are tied to the browser/device
- The `favorites` table uses `device_id` (VARCHAR) as part of the primary key, NOT `user_id`
- `Favorite_model::ensure_device()` auto-creates a device row before inserting favorites (FK constraint)

### Favorite_model Methods
| Method | Returns | Description |
|--------|---------|-------------|
| `is_favorite($device_id, $song_id)` | bool | Check if song is favorited |
| `get_favorites($device_id)` | array of song objects | All favorites with stream_url/cover_url populated |
| `toggle_favorite($device_id, $song_id)` | `['is_favorite' => bool]` | Add or remove favorite |
| `is_album_favorite($device_id, $album_id)` | bool | Check if album is favorited |
| `get_favorite_albums($device_id)` | array of album objects | All favorite albums with cover_url |
| `toggle_album_favorite($device_id, $album_id)` | `['is_favorite' => bool]` | Add or remove album favorite |

### Favorites in the Player (JS Behavior)
- **Heart icon**: Shows filled/unfilled based on actual server state (checked via `checkFavoriteStatus()` on every track change)
- **Toggle**: `toggleFavorite()` sends POST to `/player/toggle_favorite` with `X-Device-Id` header and `song_id` as form-encoded body
- **Debounce**: `_togglingFavorite` flag prevents rapid double-clicks from firing multiple requests
- **Unfavorite while viewing Favorites**: Song is immediately removed from the track list and the album re-renders
- **Dropdown position**: "Favorites" appears at the END of the album dropdown (before "Misc Songs"), not at the top
- **Dropdown sync**: `syncFavoritesOption()` adds/removes the Favorites option based on whether the device has any favorites
- **Stacked cover art**: When viewing Favorites, the album cover area shows a stack of up to 3 unique song cover images (fanned from bottom-left to upper-right with rotation and shadows). Falls back to "F" placeholder if no songs have covers.
