<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Admin extends CI_Controller {
    
    public function __construct() {
        parent::__construct();
        
        // Prevent caching of admin pages
        $this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        $this->output->set_header('Cache-Control: post-check=0, pre-check=0', false);
        $this->output->set_header('Pragma: no-cache');
        $this->output->set_header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
        
        // Check if user is admin
        if (!$this->session->userdata('is_admin')) {
            redirect('auth/login');
        }
        
        $this->load->model('album_model');
        $this->load->model('song_model');
        $this->load->model('user_model');
        
        // Ensure songs table has cover_filename column (added after initial schema)
        $fields = $this->db->list_fields('songs');
        if (!in_array('cover_filename', $fields)) {
            $this->db->query("ALTER TABLE songs ADD COLUMN cover_filename TEXT");
        }
    }
    
    /**
     * Admin dashboard
     */
    public function index() {
        $this->dashboard();
    }
    
    public function dashboard() {
        $this->load->config('music_config');
        $data = [];
        $music_path = rtrim($this->config->item('music_origin_path') ?: '', '/');
        $imgs_dir = $music_path . '/imgs/albums';
        
        // ========== LIBRARY STATS ==========
        $data['total_albums'] = $this->db->count_all('albums');
        $data['total_songs'] = $this->db->count_all('songs');
        
        // Total duration
        $dur_result = $this->db->query("SELECT SUM(duration) as total FROM songs")->row();
        $data['total_duration'] = $dur_result ? (int)$dur_result->total : 0;
        
        // ========== PLAY STATS (ALL TIME) - excluding admin devices ==========
        $play_stats = $this->db->query("
            SELECT 
                COUNT(CASE WHEN listened >= 20 THEN 1 END) as real_plays,
                COUNT(CASE WHEN listened < 20 THEN 1 END) as false_starts,
                COUNT(CASE WHEN percent >= 90 THEN 1 END) as complete_plays,
                SUM(CASE WHEN listened >= 20 THEN listened ELSE 0 END) as total_listen_time
            FROM play_history ph
            WHERE NOT EXISTS (SELECT 1 FROM devices d WHERE d.id = ph.device_id AND d.excluded = 1)
        ")->row();
        $data['total_plays'] = $play_stats ? (int)$play_stats->real_plays : 0;
        $data['false_starts'] = $play_stats ? (int)$play_stats->false_starts : 0;
        $data['complete_plays'] = $play_stats ? (int)$play_stats->complete_plays : 0;
        $data['total_listen_time'] = $play_stats ? (int)$play_stats->total_listen_time : 0;
        
        // ========== TODAY STATS - excluding admin devices ==========
        $today_stats = $this->db->query("
            SELECT 
                COUNT(CASE WHEN listened >= 20 THEN 1 END) as plays,
                COUNT(CASE WHEN percent >= 90 THEN 1 END) as complete,
                COUNT(CASE WHEN listened >= 20 AND percent < 50 THEN 1 END) as skips,
                COUNT(DISTINCT song_id) as unique_songs
            FROM play_history ph
            WHERE DATE(ph.played_at) = DATE('now')
            AND NOT EXISTS (SELECT 1 FROM devices d WHERE d.id = ph.device_id AND d.excluded = 1)
        ")->row();
        $data['today_plays'] = $today_stats ? (int)$today_stats->plays : 0;
        $data['today_complete'] = $today_stats ? (int)$today_stats->complete : 0;
        $data['today_skips'] = $today_stats ? (int)$today_stats->skips : 0;
        $data['today_unique_songs'] = $today_stats ? (int)$today_stats->unique_songs : 0;
        
        // Top song today
        $today_top = $this->db->query("
            SELECT s.title, COUNT(*) as plays
            FROM play_history ph
            JOIN songs s ON s.id = ph.song_id
            WHERE DATE(ph.played_at) = DATE('now') AND ph.listened >= 20
            AND NOT EXISTS (SELECT 1 FROM devices d WHERE d.id = ph.device_id AND d.excluded = 1)
            GROUP BY ph.song_id
            ORDER BY plays DESC
            LIMIT 1
        ")->row();
        $data['today_top_song'] = $today_top;
        
        // ========== THIS WEEK STATS - excluding admin devices ==========
        $week_stats = $this->db->query("
            SELECT 
                COUNT(CASE WHEN listened >= 20 THEN 1 END) as plays,
                COUNT(CASE WHEN percent >= 90 THEN 1 END) as complete,
                SUM(CASE WHEN listened >= 20 THEN listened ELSE 0 END) as listen_time,
                COUNT(DISTINCT ph.device_id) as unique_devices
            FROM play_history ph
            WHERE ph.played_at >= DATE('now', '-7 days')
            AND NOT EXISTS (SELECT 1 FROM devices d WHERE d.id = ph.device_id AND d.excluded = 1)
        ")->row();
        $data['week_plays'] = $week_stats ? (int)$week_stats->plays : 0;
        $data['week_complete'] = $week_stats ? (int)$week_stats->complete : 0;
        $data['week_listen_time'] = $week_stats ? (int)$week_stats->listen_time : 0;
        $data['week_devices'] = $week_stats ? (int)$week_stats->unique_devices : 0;
        
        // Top song this week - excluding admin devices
        $top_song = $this->db->query("
            SELECT s.title, COUNT(*) as plays
            FROM play_history ph
            JOIN songs s ON s.id = ph.song_id
            WHERE ph.played_at >= DATE('now', '-7 days') AND ph.listened >= 20
            AND NOT EXISTS (SELECT 1 FROM devices d WHERE d.id = ph.device_id AND d.excluded = 1)
            GROUP BY ph.song_id
            ORDER BY plays DESC
            LIMIT 1
        ")->row();
        $data['week_top_song'] = $top_song;
        
        // ========== SONGS DATA ==========
        $data['all_songs'] = $this->db->query("
            SELECT s.*, 
                   (SELECT a.title FROM albums a 
                    JOIN album_songs als ON als.album_id = a.id 
                    WHERE als.song_id = s.id LIMIT 1) as album_title,
                   (SELECT a.id FROM albums a 
                    JOIN album_songs als ON als.album_id = a.id 
                    WHERE als.song_id = s.id LIMIT 1) as album_id,
                   (SELECT COUNT(*) FROM play_history ph 
                    WHERE ph.song_id = s.id AND ph.listened >= 20) as play_count
            FROM songs s
            ORDER BY s.title
        ")->result();
        
        // ========== ALBUMS DATA ==========
        $data['albums'] = $this->db->query("
            SELECT a.*, 
                   (SELECT COUNT(*) FROM album_songs als WHERE als.album_id = a.id) as song_count
            FROM albums a
            ORDER BY a.title
        ")->result();
        
        // ========== PROBLEMS DETECTION ==========
        $problems = [];
        
        // Check for songs without covers
        $songs_without_covers = [];
        foreach ($data['all_songs'] as $song) {
            if (empty($song->cover_filename)) {
                $songs_without_covers[] = $song->title;
            }
        }
        if (!empty($songs_without_covers)) {
            $problems[] = [
                'type' => 'warning',
                'message' => count($songs_without_covers) . ' song(s) missing cover art',
                'details' => implode(', ', array_slice($songs_without_covers, 0, 5)) . (count($songs_without_covers) > 5 ? '...' : '')
            ];
        }
        
        // Check for albums without covers in imgs folder (skip "Unknown Album" - that's just Misc)
        $albums_without_covers = [];
        $available_imgs = [];
        if (is_dir($imgs_dir)) {
            $files = @scandir($imgs_dir);
            if ($files) {
                foreach ($files as $f) {
                    if ($f !== '.' && $f !== '..') {
                        $available_imgs[] = pathinfo($f, PATHINFO_FILENAME);
                    }
                }
            }
        }
        foreach ($data['albums'] as $album) {
            // Skip "Unknown Album" - it's just the Misc catch-all, doesn't need a cover
            if (strtolower($album->title) === 'unknown album') {
                continue;
            }
            $album_normalized = strtolower(str_replace(['_', ' ', '-'], '', $album->title));
            $found = false;
            foreach ($available_imgs as $img) {
                if (strtolower(str_replace(['_', ' ', '-'], '', $img)) === $album_normalized) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $albums_without_covers[] = $album->title;
            }
        }
        if (!empty($albums_without_covers)) {
            $problems[] = [
                'type' => 'warning',
                'message' => count($albums_without_covers) . ' album(s) missing cover in imgs/ folder',
                'details' => implode(', ', $albums_without_covers)
            ];
        }
        
        // Check for missing audio files
        $missing_files = [];
        if ($music_path && is_dir($music_path)) {
            foreach ($data['all_songs'] as $song) {
                $full_path = $music_path . '/' . $song->filename;
                if (!file_exists($full_path)) {
                    $missing_files[] = $song->title;
                }
            }
        }
        if (!empty($missing_files)) {
            $problems[] = [
                'type' => 'error',
                'message' => count($missing_files) . ' song file(s) missing on disk',
                'details' => implode(', ', $missing_files)
            ];
        }
        
        // Check for false starts
        if ($data['false_starts'] > 0) {
            $problems[] = [
                'type' => 'warning',
                'message' => $data['false_starts'] . ' false start(s) in play history',
                'details' => 'Click to purge plays under 20 seconds',
                'action' => 'purge'
            ];
        }
        
        // Count misc songs (not in any album) - not a problem, just info for Library section
        $misc_count = $this->db->query("SELECT COUNT(*) as c FROM songs WHERE NOT EXISTS (SELECT 1 FROM album_songs WHERE song_id = songs.id)")->row();
        $data['misc_songs'] = $misc_count ? (int)$misc_count->c : 0;
        
        $data['problems'] = $problems;
        
        // ========== CONFIG ==========
        $data['music_path'] = $music_path;
        $data['imgs_dir'] = $imgs_dir;
        $data['cdn_url'] = $this->config->item('music_cdn_url') ?: '';
        $data['cover_art_url'] = rtrim($this->config->item('cover_art_url') ?: '', '/');
        
        // Last scan
        $last_scan = $this->db->where('config_key', 'last_scan')->get('settings')->row();
        $data['last_scan'] = $last_scan ? $last_scan->config_value : 'Never';
        
        // Device count (exclude excluded devices)
        $device_count = $this->db->query("SELECT COUNT(*) as c FROM devices WHERE excluded = 0 OR excluded IS NULL")->row();
        $data['total_devices'] = $device_count ? (int)$device_count->c : 0;
        
        // Format total duration
        $total_secs = $data['total_duration'];
        $hours = floor($total_secs / 3600);
        $mins = floor(($total_secs % 3600) / 60);
        $data['total_duration'] = $hours . 'h ' . $mins . 'm';
        
        // Week skips (plays that didn't reach 50%)
        $week_skips = $this->db->query("
            SELECT COUNT(*) as skips
            FROM play_history ph
            WHERE ph.played_at >= DATE('now', '-7 days')
            AND ph.listened >= 20 AND ph.percent < 50
            AND NOT EXISTS (SELECT 1 FROM devices d WHERE d.id = ph.device_id AND d.excluded = 1)
        ")->row();
        $data['week_skips'] = $week_skips ? (int)$week_skips->skips : 0;
        
        // Unique songs played this week
        $week_unique = $this->db->query("
            SELECT COUNT(DISTINCT song_id) as cnt
            FROM play_history ph
            WHERE ph.played_at >= DATE('now', '-7 days')
            AND ph.listened >= 20
            AND NOT EXISTS (SELECT 1 FROM devices d WHERE d.id = ph.device_id AND d.excluded = 1)
        ")->row();
        $data['week_unique_songs'] = $week_unique ? (int)$week_unique->cnt : 0;
        
        // Top song this week as object
        $week_top = $this->db->query("
            SELECT s.title, COUNT(*) as plays
            FROM play_history ph
            JOIN songs s ON s.id = ph.song_id
            WHERE ph.played_at >= DATE('now', '-7 days') AND ph.listened >= 20
            AND NOT EXISTS (SELECT 1 FROM devices d WHERE d.id = ph.device_id AND d.excluded = 1)
            GROUP BY ph.song_id
            ORDER BY plays DESC
            LIMIT 1
        ")->row();
        $data['week_top_song'] = $week_top;
        
        // Recent plays
        $data['recent_plays'] = $this->db->query("
            SELECT ph.*, s.title, d.name as device_name
            FROM play_history ph
            JOIN songs s ON s.id = ph.song_id
            LEFT JOIN devices d ON d.id = ph.device_id
            WHERE ph.listened >= 20
            AND NOT EXISTS (SELECT 1 FROM devices d2 WHERE d2.id = ph.device_id AND d2.excluded = 1)
            ORDER BY ph.played_at DESC
            LIMIT 15
        ")->result();
        
        // Top songs all time
        $data['top_songs'] = $this->db->query("
            SELECT s.*, COUNT(ph.id) as play_count,
                   (SELECT a.title FROM albums a JOIN album_songs als ON als.album_id = a.id WHERE als.song_id = s.id LIMIT 1) as album_title
            FROM songs s
            JOIN play_history ph ON ph.song_id = s.id
            WHERE ph.listened >= 20
            AND NOT EXISTS (SELECT 1 FROM devices d WHERE d.id = ph.device_id AND d.excluded = 1)
            GROUP BY s.id
            ORDER BY play_count DESC
            LIMIT 10
        ")->result();
        
        // Recent complete plays (90%+ completion)
        $data['recent_complete'] = $this->db->query("
            SELECT ph.*, s.title, s.duration, d.name as device_name,
                   (SELECT a.title FROM albums a JOIN album_songs als ON als.album_id = a.id WHERE als.song_id = s.id LIMIT 1) as album_title
            FROM play_history ph
            JOIN songs s ON s.id = ph.song_id
            LEFT JOIN devices d ON d.id = ph.device_id
            WHERE ph.percent >= 90
            AND NOT EXISTS (SELECT 1 FROM devices d2 WHERE d2.id = ph.device_id AND d2.excluded = 1)
            ORDER BY ph.played_at DESC
            LIMIT 20
        ")->result();
        
        // Top complete songs (most full listens)
        $data['top_complete_songs'] = $this->db->query("
            SELECT s.title, COUNT(*) as complete_count,
                   (SELECT a.title FROM albums a JOIN album_songs als ON als.album_id = a.id WHERE als.song_id = s.id LIMIT 1) as album_title
            FROM play_history ph
            JOIN songs s ON s.id = ph.song_id
            WHERE ph.percent >= 90
            AND NOT EXISTS (SELECT 1 FROM devices d WHERE d.id = ph.device_id AND d.excluded = 1)
            GROUP BY s.id
            ORDER BY complete_count DESC
            LIMIT 10
        ")->result();
        
        $this->load->view('admin/dashboard_new', $data);
    }
    
    /**
     * Tools page - consolidated admin tools
     */
    public function tools() {
        $this->load->config('music_config');
        $data = [];
        
        $data['music_path'] = rtrim($this->config->item('music_origin_path') ?: '', '/');
        $data['cover_art_path'] = $this->config->item('cover_art_path') ?: '';
        $data['cdn_url'] = $this->config->item('music_cdn_url') ?: '';
        $data['min_songs'] = $this->config->item('min_songs_per_album') ?: 4;
        
        // Last scan
        $last_scan = $this->db->where('config_key', 'last_scan')->get('settings')->row();
        $data['last_scan'] = $last_scan ? $last_scan->config_value : 'Never';
        
        // False start count
        $false_starts = $this->db->query("SELECT COUNT(*) as cnt FROM play_history WHERE listened < 20")->row();
        $data['false_start_count'] = $false_starts ? (int)$false_starts->cnt : 0;
        
        $this->load->view('admin/tools', $data);
    }
    
    /**
     * Scan library for new music - also cleans up orphaned entries
     */
    public function scan_library() {
        set_time_limit(600); // 10 minutes
        ini_set('memory_limit', '512M');
        
        // Ensure songs table has cover_filename column
        $fields = $this->db->list_fields('songs');
        if (!in_array('cover_filename', $fields)) {
            $this->db->query("ALTER TABLE songs ADD COLUMN cover_filename TEXT");
        }
        
        // First, clean up orphaned entries (songs where file no longer exists)
        $removed = $this->_clean_orphaned_songs();
        
        // Then do the normal scan
        $stats = $this->song_model->scan_library();
        
        $message = sprintf(
            'Library scan complete: %d added, %d updated, %d removed, %d errors',
            $stats['added'],
            $stats['updated'],
            $removed,
            $stats['errors']
        );
        
        $this->session->set_flashdata('success', $message);
        redirect('admin');
    }
    
    /**
     * Helper: Clean orphaned songs from database (files that no longer exist)
     * @return int Number of removed entries
     */
    private function _clean_orphaned_songs() {
        $this->load->config('music_config');
        $music_path = rtrim($this->config->item('music_origin_path') ?: '', '/');
        
        $songs = $this->db->get('songs')->result();
        $removed = 0;
        
        foreach ($songs as $song) {
            $full_path = $music_path . '/' . $song->filename;
            if (!file_exists($full_path)) {
                // Delete from album_songs
                $this->db->where('song_id', $song->id)->delete('album_songs');
                // Delete play history
                $this->db->where('song_id', $song->id)->delete('play_history');
                // Delete favorites
                $this->db->where('song_id', $song->id)->delete('favorites');
                // Delete song
                $this->db->where('id', $song->id)->delete('songs');
                $removed++;
            }
        }
        
        return $removed;
    }
    
    /**
     * List all albums with songs
     */
    public function albums() {
        $albums = $this->album_model->get_all_albums();
        
        // Get songs for each album
        foreach ($albums as &$album) {
            $album->songs = $this->db
                ->select('songs.*, album_songs.track_number')
                ->join('album_songs', 'album_songs.song_id = songs.id')
                ->where('album_songs.album_id', $album->id)
                ->order_by('album_songs.track_number ASC, songs.title ASC')
                ->get('songs')
                ->result();
        }
        
        $data['albums'] = $albums;
        $this->load->view('admin/albums', $data);
    }
    
    /**
     * List all songs with album info and file system data
     */
    public function songs() {
        $this->load->config('music_config');
        $music_path = rtrim($this->config->item('music_origin_path') ?: '', '/');
        $cdn_url = rtrim($this->config->item('music_cdn_url') ?: '', '/');
        $min_songs = $this->config->item('min_songs_per_album') ?: 4;
        $exclude_dirs = $this->config->item('exclude_directories') ?: ['org', 'originals', 'backup'];
        
        // Get all songs from database with their album names and cover info
        $db_songs = $this->db->query("
            SELECT s.*, 
                   (SELECT a.title FROM albums a 
                    JOIN album_songs als ON als.album_id = a.id 
                    WHERE als.song_id = s.id LIMIT 1) as album_title,
                   (SELECT a.id FROM albums a 
                    JOIN album_songs als ON als.album_id = a.id 
                    WHERE als.song_id = s.id LIMIT 1) as album_id,
                   (SELECT a.cover_filename FROM albums a 
                    JOIN album_songs als ON als.album_id = a.id 
                    WHERE als.song_id = s.id LIMIT 1) as album_cover_filename,
                   (SELECT als.track_number FROM album_songs als 
                    WHERE als.song_id = s.id LIMIT 1) as track_number,
                   (SELECT COUNT(*) FROM play_history ph 
                    WHERE ph.song_id = s.id) as play_count
            FROM songs s
            ORDER BY s.artist, s.title
        ")->result();
        
        // Get album data
        $albums = $this->db->query("
            SELECT a.*, 
                   (SELECT COUNT(*) FROM album_songs als WHERE als.album_id = a.id) as song_count
            FROM albums a
            ORDER BY a.title
        ")->result();
        
        $cover_art_path = $this->config->item('cover_art_path') ?: FCPATH . 'uploads/covers/';
        $cover_art_url = rtrim($this->config->item('cover_art_url') ?: '', '/');
        
        $data['songs'] = $db_songs;
        $data['albums'] = $albums;
        $data['music_path'] = $music_path;
        $data['cdn_url'] = $cdn_url;
        $data['min_songs'] = $min_songs;
        $data['cover_art_path'] = $cover_art_path;
        $data['cover_art_url'] = $cover_art_url;
        
        // Scan filesystem for audio files
        $audio_files = [];
        $file_tags = [];
        
        if (is_dir($music_path)) {
            $this->_scan_dir_recursive($music_path, $music_path, $audio_files, ['mp3', 'm4a', 'flac', 'ogg', 'wav'], $exclude_dirs);
            
            // Try to get ID3 tags for each file (limit to reasonable number)
            $getid3_path = APPPATH . 'third_party/getid3/getid3.php';
            if (file_exists($getid3_path) && count($audio_files) <= 500) {
                require_once($getid3_path);
                $getID3 = new \getID3;
                
                foreach ($audio_files as $rel_path) {
                    $full_path = $music_path . '/' . $rel_path;
                    try {
                        $info = $getID3->analyze($full_path);
                        $tags = $info['tags']['id3v2'] ?? $info['tags']['id3v1'] ?? [];
                        
                        $file_tags[$rel_path] = [
                            'title' => $tags['title'][0] ?? pathinfo($rel_path, PATHINFO_FILENAME),
                            'artist' => $tags['artist'][0] ?? 'Unknown',
                            'album' => $tags['album'][0] ?? 'Unknown',
                            'year' => $tags['year'][0] ?? $tags['recording_time'][0] ?? null,
                            'track' => $tags['track_number'][0] ?? null,
                            'duration' => isset($info['playtime_seconds']) ? (int)round($info['playtime_seconds']) : 0,
                            'has_cover' => isset($info['comments']['picture']) || isset($info['id3v2']['APIC']),
                            'bitrate' => isset($info['audio']['bitrate']) ? round($info['audio']['bitrate'] / 1000) : null,
                            'format' => $info['fileformat'] ?? pathinfo($rel_path, PATHINFO_EXTENSION),
                            'filesize' => @filesize($full_path) ?: 0,
                            'modified' => @filemtime($full_path) ?: 0,
                        ];
                    } catch (\Exception $e) {
                        $file_tags[$rel_path] = ['error' => $e->getMessage()];
                    }
                }
            }
        }
        
        $data['audio_files'] = $audio_files;
        $data['file_tags'] = $file_tags;
        
        // Build a map of filename to cover URL from database
        $cover_map = [];
        foreach ($db_songs as $song) {
            if (!empty($song->cover_filename)) {
                $cover_map[$song->filename] = $cover_art_url . '/' . $song->cover_filename;
            }
        }
        
        // Group files by album tag to show how scanner would organize them
        $album_groups = [];
        foreach ($file_tags as $path => $tags) {
            if (isset($tags['error'])) continue;
            $album_name = $tags['album'] ?? 'Unknown';
            if (!isset($album_groups[$album_name])) {
                $album_groups[$album_name] = [
                    'artist' => $tags['artist'],
                    'year' => $tags['year'],
                    'songs' => []
                ];
            }
            $album_groups[$album_name]['songs'][] = [
                'path' => $path,
                'title' => $tags['title'],
                'track' => $tags['track'],
                'duration' => $tags['duration'],
                'has_cover' => $tags['has_cover'],
                'cover_url' => $cover_map[$path] ?? '',
            ];
        }
        
        // Sort albums and mark which would become "misc"
        ksort($album_groups);
        foreach ($album_groups as $name => &$group) {
            $group['is_misc'] = count($group['songs']) < $min_songs;
            // Sort songs by track number
            usort($group['songs'], function($a, $b) {
                $ta = $a['track'] ? (int)explode('/', $a['track'])[0] : 999;
                $tb = $b['track'] ? (int)explode('/', $b['track'])[0] : 999;
                return $ta - $tb;
            });
        }
        
        $data['album_groups'] = $album_groups;
        
        // Check which files are in DB vs filesystem
        $db_filenames = array_column($db_songs, 'filename');
        $data['files_not_in_db'] = array_diff($audio_files, $db_filenames);
        $data['db_not_on_disk'] = array_diff($db_filenames, $audio_files);
        
        $this->load->view('admin/songs', $data);
    }
    
    /**
     * Recursively scan directory for audio files
     */
    private function _scan_dir_recursive($dir, $base, &$files, $exts, $exclude) {
        if (!is_dir($dir) || !is_readable($dir)) return;
        $items = @scandir($dir);
        if (!$items) return;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || in_array($item, $exclude)) continue;
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->_scan_dir_recursive($path, $base, $files, $exts, $exclude);
            } elseif (is_file($path) && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $exts)) {
                $files[] = str_replace($base . '/', '', $path);
            }
        }
    }
    
    /**
     * Upload page - shows upload form and staged files
     */
    public function upload_song() {
        $this->load->config('music_config');
        $music_path = rtrim($this->config->item('music_origin_path') ?: '', '/');
        $staging_path = $music_path . '/_staging';
        
        // Ensure staging directory exists
        if (!is_dir($staging_path)) {
            @mkdir($staging_path, 0755, true);
        }
        
        $data = [
            'music_path' => $music_path,
            'staged_files' => []
        ];
        
        // Get staged files and extract their metadata
        if (is_dir($staging_path)) {
            $files = @scandir($staging_path);
            $getid3_path = APPPATH . 'third_party/getid3/getid3.php';
            $getID3 = null;
            if (file_exists($getid3_path)) {
                require_once($getid3_path);
                $getID3 = new \getID3;
            }
            
            if ($files) {
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') continue;
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    if (!in_array($ext, ['mp3', 'm4a', 'flac', 'ogg', 'wav'])) continue;
                    
                    $full_path = $staging_path . '/' . $file;
                    $metadata = [
                        'filename' => $file, 
                        'staged_path' => $full_path,
                        'title' => pathinfo($file, PATHINFO_FILENAME),
                        'artist' => null,
                        'album' => null,
                        'track' => null,
                        'duration' => 0,
                        'has_cover' => false
                    ];
                    
                    // Extract ID3 tags
                    if ($getID3) {
                        try {
                            $info = $getID3->analyze($full_path);
                            $tags = $info['tags']['id3v2'] ?? $info['tags']['id3v1'] ?? [];
                            
                            $metadata['title'] = $tags['title'][0] ?? $metadata['title'];
                            $metadata['artist'] = $tags['artist'][0] ?? null;
                            $metadata['album'] = $tags['album'][0] ?? null;
                            $metadata['track'] = isset($tags['track_number'][0]) ? explode('/', $tags['track_number'][0])[0] : null;
                            $metadata['duration'] = isset($info['playtime_seconds']) ? (int)round($info['playtime_seconds']) : 0;
                            $metadata['has_cover'] = isset($info['comments']['picture']) || isset($info['id3v2']['APIC']);
                        } catch (\Exception $e) {
                            // Keep defaults
                        }
                    }
                    
                    // Check if file already exists in songs folder
                    $dest_path = $music_path . '/' . $file;
                    $metadata['exists'] = file_exists($dest_path);
                    
                    $data['staged_files'][] = $metadata;
                }
            }
        }
        
        $this->load->view('admin/upload_song', $data);
    }
    
    /**
     * Handle file upload - uploads to staging folder
     */
    public function do_upload() {
        $this->load->config('music_config');
        $music_path = rtrim($this->config->item('music_origin_path') ?: '', '/');
        $staging_path = $music_path . '/_staging';
        
        // Ensure staging directory exists
        if (!is_dir($staging_path)) {
            if (!@mkdir($staging_path, 0755, true)) {
                $this->session->set_flashdata('error', 'Failed to create staging directory: ' . $staging_path);
                redirect('admin/upload_song');
                return;
            }
        }
        
        if (!empty($_FILES['audio_file']['name'])) {
            $original_name = $_FILES['audio_file']['name'];
            $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            
            // Validate extension
            $allowed = ['mp3', 'm4a', 'flac', 'ogg', 'wav'];
            if (!in_array($ext, $allowed)) {
                $this->session->set_flashdata('error', 'Invalid file type. Allowed: ' . implode(', ', $allowed));
                redirect('admin/upload_song');
                return;
            }
            
            // Sanitize filename but keep it recognizable
            $filename = preg_replace('/[^a-zA-Z0-9._\-\s]/', '', pathinfo($original_name, PATHINFO_FILENAME));
            $filename = trim($filename);
            if (empty($filename)) {
                $filename = 'upload_' . time();
            }
            $filename = $filename . '.' . $ext;
            
            $dest = $staging_path . '/' . $filename;
            
            // Handle duplicate filenames in staging
            $counter = 1;
            while (file_exists($dest)) {
                $filename = pathinfo($original_name, PATHINFO_FILENAME) . '_' . $counter . '.' . $ext;
                $dest = $staging_path . '/' . $filename;
                $counter++;
            }
            
            if (move_uploaded_file($_FILES['audio_file']['tmp_name'], $dest)) {
                $this->session->set_flashdata('success', 'File uploaded to staging: ' . $filename . ' - Review metadata below and click Import.');
            } else {
                $this->session->set_flashdata('error', 'Failed to upload file. Check permissions on: ' . $staging_path);
            }
        } else {
            $this->session->set_flashdata('error', 'No file selected');
        }
        
        redirect('admin/upload_song');
    }
    
    /**
     * Commit a staged file - move to songs folder and add to database
     */
    public function commit_upload() {
        $this->load->config('music_config');
        $music_path = rtrim($this->config->item('music_origin_path') ?: '', '/');
        $staged_file = $this->input->post('staged_file');
        
        if (!$staged_file || !file_exists($staged_file)) {
            $this->session->set_flashdata('error', 'Staged file not found');
            redirect('admin/upload_song');
            return;
        }
        
        // Security: ensure file is in staging folder
        $staging_path = $music_path . '/_staging';
        $real_staged = realpath($staged_file);
        $real_staging = realpath($staging_path);
        
        if (!$real_staged || !$real_staging || strpos($real_staged, $real_staging) !== 0) {
            $this->session->set_flashdata('error', 'Invalid file path');
            redirect('admin/upload_song');
            return;
        }
        
        $filename = basename($staged_file);
        $dest_path = $music_path . '/' . $filename;
        
        // Move file to songs folder
        if (rename($staged_file, $dest_path)) {
            // Add to database
            $song_id = $this->_add_single_song($dest_path, $filename);
            $this->session->set_flashdata('success', 'Imported: ' . $filename);
        } else {
            $this->session->set_flashdata('error', 'Failed to move file to songs folder. Check permissions.');
        }
        
        redirect('admin/upload_song');
    }
    
    /**
     * Cancel a staged upload - delete the staged file
     */
    public function cancel_upload() {
        $this->load->config('music_config');
        $music_path = rtrim($this->config->item('music_origin_path') ?: '', '/');
        $staged_file = $this->input->post('staged_file');
        
        if (!$staged_file) {
            $this->session->set_flashdata('error', 'No file specified');
            redirect('admin/upload_song');
            return;
        }
        
        // Security: ensure file is in staging folder
        $staging_path = $music_path . '/_staging';
        $real_staged = realpath($staged_file);
        $real_staging = realpath($staging_path);
        
        if ($real_staged && $real_staging && strpos($real_staged, $real_staging) === 0) {
            if (@unlink($staged_file)) {
                $this->session->set_flashdata('success', 'Cancelled: ' . basename($staged_file));
            } else {
                $this->session->set_flashdata('error', 'Failed to delete staged file');
            }
        } else {
            $this->session->set_flashdata('error', 'Invalid file path');
        }
        
        redirect('admin/upload_song');
    }
    
    /**
     * Commit all staged files
     */
    public function commit_all() {
        $this->load->config('music_config');
        $music_path = rtrim($this->config->item('music_origin_path') ?: '', '/');
        $staging_path = $music_path . '/_staging';
        
        $count = 0;
        $errors = [];
        
        if (is_dir($staging_path)) {
            $files = @scandir($staging_path);
            if ($files) {
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') continue;
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    if (!in_array($ext, ['mp3', 'm4a', 'flac', 'ogg', 'wav'])) continue;
                    
                    $src = $staging_path . '/' . $file;
                    $dest = $music_path . '/' . $file;
                    
                    if (rename($src, $dest)) {
                        $this->_add_single_song($dest, $file);
                        $count++;
                    } else {
                        $errors[] = $file;
                    }
                }
            }
        }
        
        if ($count > 0) {
            $msg = 'Imported ' . $count . ' file(s)';
            if (!empty($errors)) {
                $msg .= '. Failed: ' . implode(', ', $errors);
            }
            $this->session->set_flashdata('success', $msg);
        } else {
            $this->session->set_flashdata('error', 'No files imported');
        }
        
        redirect('admin/upload_song');
    }
    
    /**
     * Cancel all staged uploads
     */
    public function cancel_all() {
        $this->load->config('music_config');
        $music_path = rtrim($this->config->item('music_origin_path') ?: '', '/');
        $staging_path = $music_path . '/_staging';
        
        $count = 0;
        if (is_dir($staging_path)) {
            $files = @scandir($staging_path);
            if ($files) {
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') continue;
                    $full_path = $staging_path . '/' . $file;
                    if (is_file($full_path)) {
                        @unlink($full_path);
                        $count++;
                    }
                }
            }
        }
        
        $this->session->set_flashdata('success', 'Cancelled ' . $count . ' staged upload(s)');
        redirect('admin/upload_song');
    }
    
    /**
     * Add a single song to the database from file
     */
    private function _add_single_song($full_path, $filename) {
        $title = pathinfo($filename, PATHINFO_FILENAME);
        $artist = null;
        $duration = 0;
        
        $getid3_path = APPPATH . 'third_party/getid3/getid3.php';
        if (file_exists($getid3_path)) {
            require_once($getid3_path);
            $getID3 = new \getID3;
            
            try {
                $info = $getID3->analyze($full_path);
                $tags = $info['tags']['id3v2'] ?? $info['tags']['id3v1'] ?? [];
                
                $title = $tags['title'][0] ?? $title;
                $artist = $tags['artist'][0] ?? null;
                $duration = isset($info['playtime_seconds']) ? (int)round($info['playtime_seconds']) : 0;
            } catch (\Exception $e) {
                // Use defaults
            }
        }
        
        // Check if song already exists
        $existing = $this->db->get_where('songs', ['filename' => $filename])->row();
        
        $song_data = [
            'filename' => $filename,
            'title' => $title,
            'artist' => $artist,
            'duration' => $duration,
            'file_hash' => md5_file($full_path)
        ];
        
        if ($existing) {
            $this->db->where('id', $existing->id)->update('songs', $song_data);
            return $existing->id;
        } else {
            $this->db->insert('songs', $song_data);
            return $this->db->insert_id();
        }
    }
    
    /**
     * Delete a file from disk and database (AJAX)
     */
    public function delete_file() {
        $this->output->set_content_type('application/json');
        $filename = $this->input->post('filename');
        
        if (!$filename) {
            $this->output->set_output(json_encode(['success' => false, 'message' => 'Filename required']));
            return;
        }
        
        $this->load->config('music_config');
        $music_path = rtrim($this->config->item('music_origin_path') ?: '', '/');
        $full_path = $music_path . '/' . $filename;
        
        // Security check
        $real_music = realpath($music_path);
        $real_file = realpath($full_path);
        
        if (!$real_music || ($real_file && strpos($real_file, $real_music) !== 0)) {
            $this->output->set_output(json_encode(['success' => false, 'message' => 'Invalid path']));
            return;
        }
        
        $deleted_file = false;
        $deleted_db = false;
        
        if (file_exists($full_path)) {
            if (@unlink($full_path)) {
                $deleted_file = true;
            } else {
                $this->output->set_output(json_encode(['success' => false, 'message' => 'Failed to delete file']));
                return;
            }
        }
        
        $song = $this->db->get_where('songs', ['filename' => $filename])->row();
        if ($song) {
            $this->db->where('song_id', $song->id)->delete('album_songs');
            $this->db->where('song_id', $song->id)->delete('play_history');
            $this->db->where('song_id', $song->id)->delete('favorites');
            $this->db->where('id', $song->id)->delete('songs');
            $deleted_db = true;
        }
        
        $this->output->set_output(json_encode(['success' => true, 'deleted_file' => $deleted_file, 'deleted_db' => $deleted_db]));
    }
    
    /**
     * Clean orphaned database entries (AJAX)
     */
    public function clean_orphans() {
        $this->output->set_content_type('application/json');
        $removed = $this->_clean_orphaned_songs();
        $this->output->set_output(json_encode(['success' => true, 'removed' => $removed]));
    }
    
    /**
     * Edit song
     */
    /**
     * Generate PWA manifest icons from first album cover
     */
    public function generate_icons() {
        $this->load->config('music_config');
        
        // Get first album
        $album = $this->db->order_by('id', 'ASC')
                         ->limit(1)
                         ->get('albums')
                         ->row();
        
        if (!$album) {
            $this->session->set_flashdata('error', 'No albums found.');
            redirect('admin');
            return;
        }
        
        // Find the cover image by album title (same logic as Album_model::get_cover_url)
        $music_path = rtrim($this->config->item('music_origin_path') ?: '', '/');
        $imgs_dir = $music_path . '/imgs/albums';
        $local_path = null;
        $found_file = null;
        
        if (is_dir($imgs_dir)) {
            // Normalize album title for comparison
            $album_normalized = strtolower(str_replace(['_', ' ', '-'], '', $album->title));
            $exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            
            $files = @scandir($imgs_dir);
            if ($files) {
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') continue;
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    if (!in_array($ext, $exts)) continue;
                    
                    // Normalize filename for comparison
                    $file_basename = pathinfo($file, PATHINFO_FILENAME);
                    $file_normalized = strtolower(str_replace(['_', ' ', '-'], '', $file_basename));
                    
                    if ($file_normalized === $album_normalized) {
                        $local_path = $imgs_dir . '/' . $file;
                        $found_file = $file;
                        break;
                    }
                }
            }
        }
        
        if (!$local_path || !file_exists($local_path)) {
            $this->session->set_flashdata('error', 'Cover image not found for album "' . $album->title . '" in ' . $imgs_dir . '. Looking for file matching: ' . $album->title);
            redirect('admin');
            return;
        }
        
        // Load the image based on extension
        $ext = strtolower(pathinfo($local_path, PATHINFO_EXTENSION));
        $source_image = null;
        
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                $source_image = @imagecreatefromjpeg($local_path);
                break;
            case 'png':
                $source_image = @imagecreatefrompng($local_path);
                break;
            case 'webp':
                if (function_exists('imagecreatefromwebp')) {
                    $source_image = @imagecreatefromwebp($local_path);
                }
                break;
            case 'gif':
                $source_image = @imagecreatefromgif($local_path);
                break;
        }
        
        if (!$source_image) {
            $this->session->set_flashdata('error', 'Failed to load image. Format: ' . $ext . '. Path: ' . $local_path);
            redirect('admin');
            return;
        }
        
        // Icon sizes needed for manifest
        $sizes = [72, 96, 128, 144, 152, 192, 384, 512];
        
        // Output directory
        $output_dir = FCPATH . 'assets/images/';
        if (!is_dir($output_dir)) {
            mkdir($output_dir, 0755, true);
        }
        
        $source_width = imagesx($source_image);
        $source_height = imagesy($source_image);
        $generated = [];
        
        foreach ($sizes as $size) {
            // Create new image
            $icon = imagecreatetruecolor($size, $size);
            
            // Preserve transparency for PNG
            imagealphablending($icon, false);
            imagesavealpha($icon, true);
            $transparent = imagecolorallocatealpha($icon, 0, 0, 0, 127);
            imagefill($icon, 0, 0, $transparent);
            imagealphablending($icon, true);
            
            // Calculate crop to make square from center
            $min_dim = min($source_width, $source_height);
            $src_x = ($source_width - $min_dim) / 2;
            $src_y = ($source_height - $min_dim) / 2;
            
            // Resize and crop to square
            imagecopyresampled(
                $icon, $source_image,
                0, 0,
                $src_x, $src_y,
                $size, $size,
                $min_dim, $min_dim
            );
            
            // Save as PNG
            $output_path = $output_dir . "icon-{$size}.png";
            imagepng($icon, $output_path, 9);
            imagedestroy($icon);
            
            $generated[] = "icon-{$size}.png";
        }
        
        imagedestroy($source_image);
        
        $this->session->set_flashdata('success', 'Generated ' . count($generated) . ' icons from "' . $album->title . '" (' . $found_file . ')');
        redirect('admin');
    }
    
    /**
     * Devices list
     */
    public function devices() {
        // Ensure devices table exists
        $this->db->query("CREATE TABLE IF NOT EXISTS devices (
            id TEXT PRIMARY KEY,
            name TEXT,
            user_agent TEXT,
            first_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
            play_count INTEGER DEFAULT 0
        )");
        
        // Ensure device_id columns exist
        $fields = $this->db->list_fields('play_history');
        if (!in_array('device_id', $fields)) {
            $this->db->query("ALTER TABLE play_history ADD COLUMN device_id TEXT");
        }
        if (!in_array('duration', $fields)) {
            $this->db->query("ALTER TABLE play_history ADD COLUMN duration INTEGER DEFAULT 0");
        }
        if (!in_array('listened', $fields)) {
            $this->db->query("ALTER TABLE play_history ADD COLUMN listened INTEGER DEFAULT 0");
        }
        if (!in_array('percent', $fields)) {
            $this->db->query("ALTER TABLE play_history ADD COLUMN percent INTEGER DEFAULT 0");
        }
        $fields = $this->db->list_fields('favorites');
        if (!in_array('device_id', $fields)) {
            $this->db->query("ALTER TABLE favorites ADD COLUMN device_id TEXT");
        }
        
        $data = [];
        
        // Get all devices with play counts, favorite counts, and avg listen %
        $data['devices'] = $this->db->query("
            SELECT d.*,
                   (SELECT COUNT(*) FROM play_history ph WHERE ph.device_id = d.id) as total_plays,
                   (SELECT COUNT(*) FROM favorites f WHERE f.device_id = d.id) as total_favorites,
                   (SELECT ROUND(AVG(ph.percent)) FROM play_history ph 
                    WHERE ph.device_id = d.id AND ph.percent > 0) as avg_percent,
                   (SELECT SUM(ph.listened) FROM play_history ph 
                    WHERE ph.device_id = d.id) as total_listened,
                   (SELECT s.title FROM play_history ph 
                    JOIN songs s ON s.id = ph.song_id 
                    WHERE ph.device_id = d.id 
                    ORDER BY ph.played_at DESC LIMIT 1) as last_song,
                   (SELECT ph.played_at FROM play_history ph 
                    WHERE ph.device_id = d.id 
                    ORDER BY ph.played_at DESC LIMIT 1) as last_played
            FROM devices d
            ORDER BY d.last_seen DESC
        ")->result();
        
        // Summary stats - exclude excluded devices
        $data['total_devices'] = count($data['devices']);
        $data['active_today'] = 0;
        $today = date('Y-m-d');
        foreach ($data['devices'] as $d) {
            if ($d->last_seen && substr($d->last_seen, 0, 10) === $today && empty($d->excluded)) {
                $data['active_today']++;
            }
        }
        
        $this->load->view('admin/devices_new', $data);
    }
    
    /**
     * Device detail page - shows play history and favorites
     */
    public function device_detail($device_id) {
        // Ensure tables exist
        $this->db->query("CREATE TABLE IF NOT EXISTS devices (
            id TEXT PRIMARY KEY, name TEXT, user_agent TEXT,
            first_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
            play_count INTEGER DEFAULT 0
        )");
        
        $data = [];
        $data['device'] = $this->db->get_where('devices', ['id' => $device_id])->row();
        
        if (!$data['device']) {
            $this->session->set_flashdata('error', 'Device not found');
            redirect('admin/devices');
            return;
        }
        
        // Recent plays with listen duration
        $data['plays'] = $this->db->query("
            SELECT ph.played_at, ph.duration, ph.listened, ph.percent,
                   s.id as song_id, s.title, s.artist, s.cover_filename
            FROM play_history ph
            JOIN songs s ON s.id = ph.song_id
            WHERE ph.device_id = ?
            ORDER BY ph.played_at DESC
            LIMIT 100
        ", [$device_id])->result();
        
        // Favorites
        $data['favorites'] = $this->db->query("
            SELECT s.id as song_id, s.title, s.artist, s.cover_filename, f.created_at as favorited_at
            FROM favorites f
            JOIN songs s ON s.id = f.song_id
            WHERE f.device_id = ?
            ORDER BY f.created_at DESC
        ", [$device_id])->result();
        
        // Top songs with avg listen % and total listen time
        $data['top_songs'] = $this->db->query("
            SELECT s.title, s.artist, s.cover_filename, 
                   COUNT(*) as plays,
                   ROUND(AVG(CASE WHEN ph.percent > 0 THEN ph.percent END)) as avg_percent,
                   SUM(ph.listened) as total_listened
            FROM play_history ph
            JOIN songs s ON s.id = ph.song_id
            WHERE ph.device_id = ?
            GROUP BY ph.song_id
            ORDER BY plays DESC
            LIMIT 10
        ", [$device_id])->result();
        
        // Overall avg listen percentage for this device
        $avg_result = $this->db->query("
            SELECT ROUND(AVG(percent)) as avg_pct, SUM(listened) as total_listened
            FROM play_history 
            WHERE device_id = ? AND percent > 0
        ", [$device_id])->row();
        $data['avg_percent'] = $avg_result ? (int)$avg_result->avg_pct : 0;
        $data['total_listened'] = $avg_result ? (int)$avg_result->total_listened : 0;
        
        // Play activity by day (last 30 days)
        $data['daily_plays'] = $this->db->query("
            SELECT DATE(played_at) as play_date, COUNT(*) as plays,
                   SUM(listened) as day_listened,
                   ROUND(AVG(CASE WHEN percent > 0 THEN percent END)) as avg_pct
            FROM play_history
            WHERE device_id = ?
            AND played_at >= DATE('now', '-30 days')
            GROUP BY DATE(played_at)
            ORDER BY play_date DESC
        ", [$device_id])->result();
        
        $data['cover_art_url'] = rtrim($this->config->item('cover_art_url') ?: '', '/');
        
        $this->load->view('admin/device_detail', $data);
    }
    
    /**
     * Rename a device (AJAX)
     */
    public function device_rename() {
        $device_id = $this->input->post('device_id');
        $name = trim($this->input->post('name') ?? '');
        
        if (!$device_id) {
            $this->output->set_content_type('application/json');
            $this->output->set_output(json_encode(['success' => false, 'message' => 'Device ID required']));
            return;
        }
        
        $this->db->where('id', $device_id)->update('devices', ['name' => $name ?: null]);
        
        $this->output->set_content_type('application/json');
        $this->output->set_output(json_encode(['success' => true]));
    }
    
    /**
     * Purge false starts — delete play records with listened < 20s
     * Handles both orphaned (listened=0, no update_play called) and short plays
     * Optional device_id parameter to scope to a single device
     */
    public function purge_false_starts() {
        $device_id = $this->input->post('device_id') ?: $this->input->get('device_id');
        
        // Find false starts: listened < 20 (includes orphaned 0s)
        $this->db->where('listened <', 20);
        if ($device_id) {
            $this->db->where('device_id', $device_id);
        }
        $false_starts = $this->db->get('play_history')->result();
        $count = count($false_starts);
        
        if ($count > 0) {
            // Decrement device play counts
            $device_counts = [];
            foreach ($false_starts as $play) {
                if ($play->device_id) {
                    if (!isset($device_counts[$play->device_id])) $device_counts[$play->device_id] = 0;
                    $device_counts[$play->device_id]++;
                }
            }
            foreach ($device_counts as $did => $dec) {
                $this->db->where('id', $did)
                         ->set('play_count', "CASE WHEN play_count >= {$dec} THEN play_count - {$dec} ELSE 0 END", FALSE)
                         ->update('devices');
            }
            
            // Delete the false starts
            $this->db->where('listened <', 20);
            if ($device_id) {
                $this->db->where('device_id', $device_id);
            }
            $this->db->delete('play_history');
        }
        
        // Render result page
        $data = [
            'page_title' => 'Purge Complete',
            'removed' => $count,
            'device_id' => $device_id
        ];
        $this->load->view('admin/purge_result', $data);
    }
    
    /**
     * Clear ALL play history for a device
     */
    public function clear_device_history() {
        $this->output->set_content_type('application/json');
        $device_id = $this->input->post('device_id');
        
        if (!$device_id) {
            $this->output->set_output(json_encode(['success' => false, 'message' => 'Device ID required']));
            return;
        }
        
        // Count plays to remove
        $count = $this->db->where('device_id', $device_id)->count_all_results('play_history');
        
        // Delete all plays for this device
        $this->db->where('device_id', $device_id)->delete('play_history');
        
        // Reset device play count to 0
        $this->db->where('id', $device_id)->update('devices', ['play_count' => 0]);
        
        $this->output->set_output(json_encode(['success' => true, 'removed' => $count]));
    }
    
    /**
     * Delete a device and all its associated data
     */
    public function delete_device() {
        $this->output->set_content_type('application/json');
        $device_id = $this->input->post('device_id');
        
        if (!$device_id) {
            $this->output->set_output(json_encode(['success' => false, 'message' => 'Device ID required']));
            return;
        }
        
        // Delete all related data
        $this->db->where('device_id', $device_id)->delete('play_history');
        $this->db->where('device_id', $device_id)->delete('favorites');
        
        // Delete share history if table exists
        $tables = $this->db->list_tables();
        if (in_array('share_history', $tables)) {
            $this->db->where('device_id', $device_id)->delete('share_history');
        }
        if (in_array('share_clicks', $tables)) {
            $this->db->where('from_device_id', $device_id)->delete('share_clicks');
            $this->db->where('to_device_id', $device_id)->delete('share_clicks');
        }
        
        // Delete the device itself
        $this->db->where('id', $device_id)->delete('devices');
        
        $this->output->set_output(json_encode(['success' => true]));
    }
    
    /**
     * Toggle device excluded status (exclude from stats)
     * excluded values: 0 = not excluded, 1 = manually excluded, 2 = admin device (auto)
     */
    public function toggle_exclude() {
        $this->output->set_content_type('application/json');
        $device_id = $this->input->post('device_id');
        
        if (!$device_id) {
            $this->output->set_output(json_encode(['success' => false, 'message' => 'Device ID required']));
            return;
        }
        
        $device = $this->db->get_where('devices', ['id' => $device_id])->row();
        if (!$device) {
            $this->output->set_output(json_encode(['success' => false, 'message' => 'Device not found']));
            return;
        }
        
        // Toggle: if excluded (1 or 2) -> 0, if not excluded -> 1 (manual)
        $current = $device->excluded ?? 0;
        $new_status = ($current > 0) ? 0 : 1;
        $this->db->where('id', $device_id)->update('devices', ['excluded' => $new_status]);
        
        $this->output->set_output(json_encode(['success' => true, 'excluded' => $new_status]));
    }
    
    /**
     * Mark current device as admin device (auto-exclude)
     */
    public function mark_admin_device() {
        $this->output->set_content_type('application/json');
        $device_id = $this->input->post('device_id');
        
        if (!$device_id) {
            $this->output->set_output(json_encode(['success' => false, 'message' => 'Device ID required']));
            return;
        }
        
        // Set excluded = 2 for admin device
        $this->db->where('id', $device_id)->update('devices', ['excluded' => 2]);
        
        $this->output->set_output(json_encode(['success' => true]));
    }
    
    /**
     * Diagnostic: Check song durations and test getID3
     */
    public function check_durations() {
        $this->load->config('music_config');
        $music_path = rtrim($this->config->item('music_origin_path') ?: '', '/');
        
        // Get all songs
        $songs = $this->db->get('songs')->result();
        
        echo "<h2>Song Duration Check</h2>";
        echo "<p>Music path: " . htmlspecialchars($music_path) . "</p>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Title</th><th>DB Duration</th><th>File Exists</th><th>getID3 Duration</th></tr>";
        
        $getid3_path = APPPATH . 'third_party/getid3/getid3.php';
        $getID3 = null;
        if (file_exists($getid3_path)) {
            require_once($getid3_path);
            $getID3 = new getID3;
        }
        
        foreach ($songs as $song) {
            $full_path = $music_path . '/' . $song->filename;
            $exists = file_exists($full_path) ? 'Yes' : 'NO';
            $getid3_dur = '-';
            
            if ($getID3 && file_exists($full_path)) {
                $info = $getID3->analyze($full_path);
                $getid3_dur = isset($info['playtime_seconds']) ? round($info['playtime_seconds']) . 's' : 'NULL';
            }
            
            echo "<tr>";
            echo "<td>" . htmlspecialchars($song->title) . "</td>";
            echo "<td>" . ($song->duration ?: '0') . "s</td>";
            echo "<td>" . $exists . "</td>";
            echo "<td>" . $getid3_dur . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<br><p><a href='" . site_url('admin') . "'>Back to Dashboard</a></p>";
    }
    
    /**
     * Clear API cache
     */
    public function clear_cache() {
        // Clear any cached data (CodeIgniter cache or custom)
        $this->load->driver('cache', ['adapter' => 'file']);
        $this->cache->clean();
        
        $this->session->set_flashdata('success', 'Cache cleared successfully');
        redirect('admin/tools');
    }
    
    /**
     * Settings page
     */
    public function settings() {
        $this->load->config('music_config');
        $data = [];
        
        $data['music_path'] = $this->config->item('music_origin_path') ?: '';
        $data['music_cdn_url'] = $this->config->item('music_cdn_url') ?: '';
        $data['cover_art_path'] = $this->config->item('cover_art_path') ?: '';
        $data['cover_art_url'] = $this->config->item('cover_art_url') ?: '';
        $data['min_songs_per_album'] = $this->config->item('min_songs_per_album') ?: 4;
        $data['exclude_directories'] = $this->config->item('exclude_directories') ?: [];
        $data['admin_user_id'] = $this->config->item('admin_user_id') ?: 1;
        $data['default_artist'] = $this->config->item('default_artist') ?: '';
        $data['site_title'] = $this->config->item('site_title') ?: '';
        
        $this->load->view('admin/settings', $data);
    }
}
