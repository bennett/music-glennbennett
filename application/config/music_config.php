<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Music Player Configuration
|--------------------------------------------------------------------------
| 
| Configure paths, URLs, and player settings for your music library.
| 
| IMPORTANT: Update these paths to match your server environment!
|
*/

// ============================================================================
// MUSIC FILE LOCATIONS
// ============================================================================

// Local filesystem path to your music files (where MP3s are stored)
// This is where the scanner looks for audio files
// Resolved via relative path - files MUST exist (they are the source of truth)
$config['music_origin_path'] = realpath(FCPATH . '/../glennbennett/songs')
    ?: realpath(FCPATH . '/../glennbennett.com/songs')
    ?: '';

// Public URL to the music files on origin server (for direct access if needed)
$config['music_origin_url'] = 'https://glennbennett.com/songs';

// CDN URL for streaming (Bunny CDN or similar)
// Leave empty to stream from origin
$config['music_cdn_url'] = 'https://glb-songs.b-cdn.net/songs';

// ============================================================================
// COVER ART STORAGE
// ============================================================================

// Local filesystem path for storing cover art images
// Structure: /songs/imgs/albums/ and /songs/imgs/songs/
// Derived from music_origin_path so they always stay in sync
$config['cover_art_path'] = $config['music_origin_path'] . '/imgs/';

// Cover art is served from CDN
// Song covers: {cdn}/imgs/songs/{hash}.webp
// Album covers: {cdn}/imgs/albums/{album}.jpg
$config['cover_art_url'] = 'https://glb-songs.b-cdn.net/songs/imgs/';

// ============================================================================
// SCANNER SETTINGS
// ============================================================================

// Directories to exclude from scanning (relative to music_origin_path)
// Common exclusions: 'org', 'originals', 'backup', 'temp'
// Scan only the root music directory — skip all subdirectories
$config['scan_root_only'] = true;

// Minimum number of songs required to create an album
// Songs from albums with fewer tracks will go to "Misc"
$config['min_songs_per_album'] = 4;

// ============================================================================
// PLAYER SETTINGS
// ============================================================================

// Default volume (0.0 to 1.0)
$config['default_volume'] = 0.8;

// Enable shuffle mode option
$config['enable_shuffle'] = TRUE;

// Enable repeat mode options (off, all, one)
$config['enable_repeat'] = TRUE;

// Enable favorites (requires user login for server-side storage)
$config['enable_favorites'] = TRUE;

// ============================================================================
// ADMIN & STATISTICS
// ============================================================================

// Admin user ID - plays from this user won't be counted in statistics
// Set to the ID of your admin account in the users table
$config['admin_user_id'] = 1;

// ============================================================================
// PAGINATION
// ============================================================================

// Number of songs per page in API responses
$config['songs_per_page'] = 50;

// Number of albums per page in API responses
$config['albums_per_page'] = 24;

// ============================================================================
// ARTIST INFORMATION
// ============================================================================

// Default artist name for the player header and misc songs
$config['default_artist'] = 'Glenn L. Bennett';

// Site/player title
$config['site_title'] = 'Glenn Bennett Music';
