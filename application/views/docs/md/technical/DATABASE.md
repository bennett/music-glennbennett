# Database Schema

Full schema reference for the Bennett Music Player database (9 tables).

---

## `albums` Table
| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary key |
| title | VARCHAR | Album name (from MP3 tag) |
| artist | VARCHAR | Album artist |
| year | INT | Release year |
| cover_filename | VARCHAR | Fallback cover path (if no filesystem match) |
| description | TEXT | Optional description |
| release_date | DATE | Optional release date |
| created_at | DATETIME | Record creation |
| updated_at | DATETIME | Last update |

## `songs` Table
| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary key |
| filename | VARCHAR | Audio filename (e.g., `Colorado Snow.mp3`) |
| title | VARCHAR | Song title (from MP3 tag) |
| artist | VARCHAR | Song artist (from MP3 tag) |
| duration | INT | Duration in seconds |
| file_hash | VARCHAR | MD5 hash of file (for change detection) |
| cover_filename | VARCHAR | Path to extracted cover image |
| created_at | DATETIME | When added to database |
| updated_at | DATETIME | Last metadata update |

## `album_songs` Table (Junction)
| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary key |
| album_id | INT | FK to albums |
| song_id | INT | FK to songs |
| track_number | INT | Track position (from MP3 tag) |

## `devices` Table
| Column | Type | Description |
|--------|------|-------------|
| id | VARCHAR(64) | Primary key (UUID from client localStorage) |
| name | VARCHAR(100) | Optional device name |
| user_agent | VARCHAR(500) | Browser user agent string |
| ip_address | VARCHAR(45) | Last known IP |
| excluded | TINYINT | Whether to exclude from stats |
| first_seen | TIMESTAMP | When device first appeared |
| last_seen | TIMESTAMP | Last activity (updated on every API call) |
| play_count | INT | Total plays from this device |

## `favorites` Table
| Column | Type | Description |
|--------|------|-------------|
| device_id | VARCHAR(64) | FK to devices.id |
| song_id | INT | FK to songs.id |
| sort_order | INT | Display order within device's favorites |
| created_at | DATETIME | When favorited |

**Primary key**: `(device_id, song_id)`

## `favorite_albums` Table
| Column | Type | Description |
|--------|------|-------------|
| device_id | VARCHAR(64) | FK to devices.id |
| album_id | INT | FK to albums.id |
| created_at | DATETIME | When favorited |

**Primary key**: `(device_id, album_id)`

## `play_history` Table
| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary key |
| song_id | INT | FK to songs |
| user_id | INT | FK to users (nullable) |
| device_id | VARCHAR(64) | FK to devices.id |
| played_at | DATETIME | When played |
| duration | INT | Song duration in seconds |
| listened | INT | Seconds actually listened |
| percent | TINYINT | Percentage of song listened (0-100) |

## `share_history` Table
| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary key |
| song_id | INT | FK to songs (0 if album share) |
| album_id | INT | FK to albums (0 if song share) |
| device_id | VARCHAR(64) | Device that shared |
| share_method | VARCHAR(50) | How shared (facebook, twitter, native, copy) |
| share_type | VARCHAR(20) | "song" or "album" |
| shared_at | TIMESTAMP | When shared |

## `share_clicks` Table
| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary key |
| song_id | INT | Song that was shared |
| album_id | INT | Album that was shared |
| from_device_id | VARCHAR(64) | Device that originally shared |
| to_device_id | VARCHAR(64) | Device that clicked the link |
| referrer | TEXT | HTTP referrer |
| share_type | VARCHAR(20) | "song" or "album" |
| clicked_at | TIMESTAMP | When clicked |

---

## Models

| Model | Responsibility |
|-------|---------------|
| `Album_model.php` | Album CRUD, cover URL resolution (filesystem + DB fallback) |
| `Song_model.php` | Song CRUD, stream/cover URL generation, library scanner |
| `Favorite_model.php` | ALL favorites logic (song + album), device auto-creation |
