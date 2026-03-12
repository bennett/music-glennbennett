# Popular Songs API

Returns the most-played songs, excluding admin/test devices. This endpoint includes full CORS headers for cross-origin access.

---

## GET `/api/popular`

**Parameters:**

| Param | Type | Default | Max | Description |
|-------|------|---------|-----|-------------|
| `limit` | int | 10 | 50 | Number of songs to return |

**CORS Headers:**
```
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, OPTIONS
Access-Control-Allow-Headers: Content-Type, X-Device-Id
```

Supports `OPTIONS` preflight requests (responds with 204).

**Filtering:**
- Only counts plays with `listened >= 20` seconds (filters out false starts)
- Excludes plays from devices marked as `excluded > 0`
- Excludes plays from known admin IP addresses

**Example Request:**
```bash
curl "https://music.glennbennett.com/api/popular?limit=5"
```

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
      "play_count": "42",
      "album_id": "2",
      "album_title": "Milestones",
      "stream_url": "https://glb-songs.b-cdn.net/songs/Colorado Snow.mp3?h=a1b2c3d4e5",
      "cover_url": "https://glb-songs.b-cdn.net/songs/imgs/songs/abc123.webp?h=a1b2c3d4"
    },
    {
      "id": "5",
      "title": "Morning Light",
      "artist": "Glenn Bennett",
      "duration": "195",
      "play_count": "38",
      "album_id": "2",
      "album_title": "Milestones",
      "stream_url": "...",
      "cover_url": "..."
    }
  ]
}
```

**Song Object Fields (Popular):**

| Field | Type | Description |
|-------|------|-------------|
| `id` | string | Song ID |
| `title` | string | Song title |
| `artist` | string | Artist name |
| `duration` | string | Duration in seconds |
| `play_count` | string | Number of qualifying plays |
| `album_id` | string | Album ID (null if misc) |
| `album_title` | string | Album name (null if misc) |
| `stream_url` | string | CDN URL for audio |
| `cover_url` | string/null | CDN URL for cover art |
