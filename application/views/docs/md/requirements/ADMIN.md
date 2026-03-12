# Admin Interface

## Dashboard (`/admin`)
- Statistics: albums, songs, plays, users
- Quick actions: Scan, Upload, etc.
- System info: paths, CDN URLs, last scan

## Library View (`/admin/songs`)

### Albums Tab
Shows each album with:
- Cover image (from filesystem or database)
- Title, artist, year, song count
- Diagnostic issues (if any)
- Song list sorted by **file track number**

### Table Columns
| Column | Source | Purpose |
|--------|--------|---------|
| Row | Display order | Position after sorting |
| File# | MP3 ID3 tag | Track number from file (source of truth) |
| DB# | database | Track number in database (reference only) |
| Title | MP3 ID3 tag | Song title |
| Artist (MP3) | MP3 ID3 tag | Artist from file |
| Album (MP3) | MP3 ID3 tag | Album from file (for mismatch detection) |

### Diagnostic Issues
| Issue | Severity | Meaning |
|-------|----------|---------|
| album-mismatch | Error | MP3 album tag != assigned album |
| MISSING-FILE | Error | Audio file not on disk |
| no-file-track | Warning | MP3 has no track number |

### Miscellaneous Section
- Shows songs not assigned to any album
- **Informational only** - not an error
- Green border (not yellow/red)

### Sortable Tables
- Click column header to sort
- Click again to reverse
- Arrows: sortable, ascending, descending

## Songs Detail Tab
Full table of all songs with:
- ID, title, artist, album, track#
- Filename, cover file, duration
- Play count, file hash
- Created/updated timestamps
- Status indicator

## Files Tab
- Lists all MP3 files on disk
- Shows which are in database
- Delete button for each file
