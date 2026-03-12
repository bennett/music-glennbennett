# Albums API

Endpoints for retrieving albums and their songs.

---

## GET `/api/albums`

Returns a paginated list of all albums.

**Parameters:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `page` | int | 1 | Page number |

**Example Response:**
```json
{
  "success": true,
  "albums": [
    {
      "id": "2",
      "title": "Milestones",
      "artist": "Glenn Bennett",
      "year": "2024",
      "cover_url": "https://glb-songs.b-cdn.net/songs/imgs/albums/milestones.jpg",
      "song_count": "12"
    }
  ],
  "total": 5,
  "page": 1,
  "per_page": 50
}
```

---

## GET `/api/album/{id}`

Returns a single album with all its songs (sorted by track number).

**Parameters:**

| Param | Type | Description |
|-------|------|-------------|
| `id` | int | Album ID (numeric, URL segment) |

**Example Response:**
```json
{
  "success": true,
  "album": {
    "id": "2",
    "title": "Milestones",
    "artist": "Glenn Bennett",
    "year": "2024",
    "cover_url": "https://glb-songs.b-cdn.net/songs/imgs/albums/milestones.jpg"
  },
  "songs": [
    {
      "id": "1",
      "title": "Colorado Snow",
      "artist": "Glenn Bennett",
      "duration": "218",
      "track_number": "1",
      "stream_url": "https://glb-songs.b-cdn.net/songs/Colorado Snow.mp3?h=a1b2c3d4e5",
      "cover_url": "https://glb-songs.b-cdn.net/songs/imgs/songs/abc123.webp?h=a1b2c3d4"
    }
  ]
}
```

---

## GET `/api/misc`

Returns the virtual "Misc" album containing songs that don't belong to a named album. Songs with no album tag or with an album that has fewer than `min_album_songs` (default: 2) songs are grouped here.

**Example Response:**
```json
{
  "success": true,
  "album": {
    "id": "misc",
    "title": "Misc Songs",
    "cover_url": null
  },
  "songs": [
    {
      "id": "15",
      "title": "Demo Track",
      "artist": "Glenn Bennett",
      "duration": "180",
      "stream_url": "...",
      "cover_url": null
    }
  ]
}
```

---

## Album Object Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | string | Album ID (numeric, returned as string) |
| `title` | string | Album title |
| `artist` | string | Album artist |
| `year` | string | Release year |
| `cover_url` | string/null | Full CDN URL for cover art |
| `song_count` | string | Number of songs (in list view) |
| `cover_filename` | string | Database fallback cover path |
