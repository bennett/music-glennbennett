# API Reference

Public JSON API for the Bennett Music Player.

**Base URL**: `https://music.glennbennett.com`

---

## Overview

There are TWO controllers that serve JSON:
- **`Player.php`** (`/player/*`) — Used by the player UI internally
- **`Api.php`** (`/api/*`) — General-purpose API, documented here

All responses return `{ "success": true/false, ... }`.

---

## CORS

The `/api/popular` endpoint includes full CORS headers for cross-origin access:
```
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, OPTIONS
Access-Control-Allow-Headers: Content-Type, X-Device-Id
```

Other API endpoints are intended for same-origin use by the player.

---

## Device Identification

Requests can include an `X-Device-Id` header containing a UUID (stored in the client's localStorage). This is used for:
- Favorites (per-device, no user accounts)
- Play history tracking
- Share attribution

The device is auto-registered on first use via `_touch_device()`.

---

## Response Conventions

- **All numeric fields** (`duration`, `id`, `track_number`) are returned as **strings**, not numbers. Use `Number()` or `parseInt()` in JavaScript when doing arithmetic.
- **Favorites toggle** responses always use `is_favorite` (boolean) — never `favorited`.
- **Song objects** include `stream_url` and `cover_url` (populated by `Song_model::populate_song_urls()`).
- **Album objects** include `cover_url` (from `Album_model::get_cover_url()`).
- **POST data**: Player endpoints expect `application/x-www-form-urlencoded` (not JSON).

---

## Endpoints

| Endpoint | Method | Description | Details |
|----------|--------|-------------|---------|
| `/api/albums` | GET | List all albums (paginated) | [Albums](ALBUMS.md) |
| `/api/album/{id}` | GET | Single album with songs | [Albums](ALBUMS.md) |
| `/api/misc` | GET | Misc virtual album | [Albums](ALBUMS.md) |
| `/api/songs` | GET | List all songs (paginated) | [Songs](SONGS.md) |
| `/api/song/{id}` | GET | Single song with album info | [Songs](SONGS.md) |
| `/api/search?q=` | GET | Search songs and albums | [Songs](SONGS.md) |
| `/api/popular` | GET | Most-played songs | [Popular](POPULAR.md) |
| `/api/favorites` | GET | Device's favorite songs | Requires `X-Device-Id` |
| `/api/toggle_favorite` | POST | Toggle song favorite | Requires `X-Device-Id`, Body: `song_id` |
| `/api/toggle_favorite_album` | POST | Toggle album favorite | Requires `X-Device-Id`, Body: `album_id` |
| `/api/record_play` | POST | Record play start | Requires `X-Device-Id`, Body: `song_id` |
| `/api/update_play` | POST | Update play duration | Requires `X-Device-Id`, Body: `play_id`, `listened`, `duration` |
| `/api/record_share` | POST | Log outgoing share | Requires `X-Device-Id` |
| `/api/record_share_click` | POST | Log share click | Requires `X-Device-Id` |
| `/api/stats` | GET | Library statistics | |
| `/api/app_version` | GET | Current app version from sw.js | |
| `/api/auto_scan` | GET | Trigger library scan if files changed | |

---

## Virtual Albums

The player has two virtual albums that aren't real database records:

| ID | Title | Source | Cover Art |
|----|-------|--------|-----------|
| `"favorites"` | Favorites | `Favorite_model::get_favorites()` | Stacked covers from song art (1-3 images) |
| `"misc"` | Misc Songs | `Song_model::get_misc_songs()` | Placeholder "M" (no cover_url) |
