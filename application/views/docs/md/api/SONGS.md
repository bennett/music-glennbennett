# Songs API

Endpoints for retrieving and searching songs.

---

## GET `/api/songs`

Returns a paginated list of all songs.

**Parameters:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `page` | int | 1 | Page number |

**Example Response:**
```json
{
  "success": true,
  "songs": [
    {
      "id": "1",
      "filename": "Colorado Snow.mp3",
      "title": "Colorado Snow",
      "artist": "Glenn Bennett",
      "duration": "218",
      "file_hash": "a1b2c3d4e5f6...",
      "cover_filename": "songs/abc123.webp",
      "stream_url": "https://glb-songs.b-cdn.net/songs/Colorado Snow.mp3?h=a1b2c3d4e5",
      "cover_url": "https://glb-songs.b-cdn.net/songs/imgs/songs/abc123.webp?h=a1b2c3d4",
      "created_at": "2026-01-30 12:00:00",
      "updated_at": "2026-02-20 15:30:00"
    }
  ],
  "total": 45,
  "page": 1,
  "per_page": 50
}
```

---

## GET `/api/song/{id}`

Returns a single song with its album information.

**Parameters:**

| Param | Type | Description |
|-------|------|-------------|
| `id` | int | Song ID (URL segment) |

**Example Response:**
```json
{
  "success": true,
  "song": {
    "id": "1",
    "title": "Colorado Snow",
    "artist": "Glenn Bennett",
    "duration": "218",
    "filename": "Colorado Snow.mp3",
    "stream_url": "https://glb-songs.b-cdn.net/songs/Colorado Snow.mp3?h=a1b2c3d4e5",
    "cover_url": "https://glb-songs.b-cdn.net/songs/imgs/songs/abc123.webp?h=a1b2c3d4",
    "album_id": "2",
    "album_title": "Milestones"
  }
}
```

---

## GET `/api/search?q=`

Search songs and albums by title.

**Parameters:**

| Param | Type | Description |
|-------|------|-------------|
| `q` | string | Search query (required) |

**Example Response:**
```json
{
  "success": true,
  "songs": [
    {
      "id": "1",
      "title": "Colorado Snow",
      "artist": "Glenn Bennett",
      "duration": "218",
      "stream_url": "...",
      "cover_url": "..."
    }
  ],
  "albums": [
    {
      "id": "2",
      "title": "Milestones",
      "cover_url": "..."
    }
  ]
}
```

---

## Song Object Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | string | Song ID (numeric, returned as string) |
| `filename` | string | Audio filename |
| `title` | string | Song title (from MP3 ID3 tag) |
| `artist` | string | Artist name |
| `duration` | string | Duration in seconds (returned as string!) |
| `file_hash` | string | MD5 hash of the MP3 file |
| `cover_filename` | string/null | Path to cover image file |
| `stream_url` | string | Full CDN URL for audio streaming |
| `cover_url` | string/null | Full CDN URL for cover art |
| `created_at` | string | ISO datetime when added |
| `updated_at` | string | ISO datetime of last update |

**Important:** `duration` and `id` are returned as strings. Always use `Number()` when doing arithmetic in JavaScript.
