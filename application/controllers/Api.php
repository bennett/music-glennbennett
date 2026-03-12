<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Api extends CI_Controller {
    
    private $device_id = null;
    
    public function __construct() {
        parent::__construct();
        $this->load->model('album_model');
        $this->load->model('song_model');
        $this->load->model('playlist_model');
        $this->load->model('favorite_model');
        $this->output->set_content_type('application/json');
        
        // Ensure devices table exists
        $this->_ensure_devices_table();
        
        // Get device ID from header or param
        $this->device_id = $this->input->get_request_header('X-Device-Id') 
            ?: $this->input->get('device_id') 
            ?: $this->input->post('device_id');
        
        // Auto-register device if we have an ID
        if ($this->device_id) {
            $this->_touch_device();
        }
    }
    
    /**
     * Ensure devices table and device_id column on favorites/play_history exist
     * Note: Tables should already exist from migration, this is just a safety check
     */
    private function _ensure_devices_table() {
        // For MySQL, tables are created via migration script
        // These CREATE TABLE statements use MySQL syntax as fallback
        
        $this->db->query("CREATE TABLE IF NOT EXISTS devices (
            id VARCHAR(64) PRIMARY KEY,
            name VARCHAR(100),
            user_agent VARCHAR(500),
            ip_address VARCHAR(45),
            excluded TINYINT UNSIGNED DEFAULT 0,
            first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            play_count INT UNSIGNED DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Share tracking tables
        $this->db->query("CREATE TABLE IF NOT EXISTS share_history (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            song_id INT UNSIGNED,
            album_id INT UNSIGNED,
            device_id VARCHAR(64),
            share_method VARCHAR(50) DEFAULT 'unknown',
            share_type VARCHAR(20) DEFAULT 'song',
            shared_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $this->db->query("CREATE TABLE IF NOT EXISTS admin_ips (
            ip VARCHAR(45) PRIMARY KEY,
            first_seen DATETIME NOT NULL,
            last_seen DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS share_clicks (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            song_id INT UNSIGNED DEFAULT 0,
            album_id INT UNSIGNED DEFAULT 0,
            from_device_id VARCHAR(64),
            to_device_id VARCHAR(64),
            referrer TEXT,
            share_type VARCHAR(20) DEFAULT 'song',
            clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    
    /**
     * Update device last_seen timestamp, auto-register if new
     */
    private function _touch_device() {
        $ip = $this->input->ip_address();
        $existing = $this->db->get_where('devices', ['id' => $this->device_id])->row();
        if ($existing) {
            $this->db->where('id', $this->device_id)->update('devices', [
                'last_seen' => gmdate('Y-m-d H:i:s'),
                'ip_address' => $ip
            ]);
        } else {
            $this->db->insert('devices', [
                'id' => $this->device_id,
                'user_agent' => $this->input->user_agent(),
                'ip_address' => $ip,
                'first_seen' => gmdate('Y-m-d H:i:s'),
                'last_seen' => gmdate('Y-m-d H:i:s')
            ]);
        }
    }
    
    /**
     * Serve app icon from first album cover
     */
    public function app_icon() {
        $this->load->config('music_config');
        $cdn_url = rtrim($this->config->item('music_cdn_url'), '/');
        
        // Get first album
        $albums = $this->album_model->get_all_albums(1, 0);
        
        $icon_url = null;
        if (!empty($albums) && !empty($albums[0]->cover_url)) {
            $icon_url = $albums[0]->cover_url;
        }
        
        if ($icon_url) {
            // Fetch the image and output it
            $image_data = @file_get_contents($icon_url);
            if ($image_data) {
                $this->output->set_content_type('image/jpeg');
                $this->output->set_output($image_data);
                return;
            }
        }
        
        // Fallback - redirect to default icon
        redirect('/assets/images/icon-192.png');
    }
    
    public function albums() {
        $page = max(1, (int) ($this->input->get('page') ?: 1));
        $limit = $this->config->item('albums_per_page') ?: 24;
        $albums = $this->album_model->get_all_albums($limit, ($page - 1) * $limit);
        $misc_count = $this->song_model->count_misc_songs();
        
        $this->output->set_output(json_encode([
            'success' => true, 'albums' => $albums, 'has_misc' => $misc_count > 0, 'misc_count' => $misc_count, 'page' => $page
        ]));
    }
    
    public function album($id) {
        if (!$id || !is_numeric($id)) { $this->_error('Invalid album ID', 400); return; }
        $album = $this->album_model->get_album($id);
        if (!$album) { $this->_error('Album not found', 404); return; }
        $this->output->set_output(json_encode(['success' => true, 'album' => $album]));
    }
    
    public function misc() {
        $this->load->config('music_config');
        $songs = $this->song_model->get_misc_songs();
        
        // Generate mashup cover URL from songs that have covers
        $cover_songs = array_filter($songs, function($s) { return !empty($s->cover_url); });
        $mashup_cover = null;
        
        if (count($cover_songs) > 0) {
            // Create a data URL with cover info for client-side mashup generation
            $covers = array_slice(array_values(array_map(function($s) { return $s->cover_url; }, $cover_songs)), 0, 4);
            $mashup_cover = json_encode(['type' => 'mashup', 'covers' => $covers]);
        }
        
        $misc_album = (object) [
            'id' => 'misc', 'title' => 'Misc', 'artist' => 'Glenn L. Bennett', 'year' => null,
            'cover_url' => null, 'cover_mashup' => $mashup_cover, 'songs' => $songs
        ];
        $this->output->set_output(json_encode(['success' => true, 'album' => $misc_album]));
    }
    
    public function songs() {
        $page = max(1, (int) ($this->input->get('page') ?: 1));
        $limit = $this->config->item('songs_per_page') ?: 50;
        $songs = $this->song_model->get_all_songs($limit, ($page - 1) * $limit);
        $this->output->set_output(json_encode(['success' => true, 'songs' => $songs, 'page' => $page]));
    }
    
    public function song($id) {
        if (!$id || !is_numeric($id)) { $this->_error('Invalid song ID', 400); return; }
        $song = $this->song_model->get_song($id);
        if (!$song) { $this->_error('Song not found', 404); return; }
        $song->albums = $this->album_model->get_song_albums($id);
        $this->output->set_output(json_encode(['success' => true, 'song' => $song]));
    }
    
    public function search() {
        $q = trim($this->input->get('q') ?? '');
        if (strlen($q) < 2) {
            $this->output->set_output(json_encode(['success' => true, 'songs' => [], 'albums' => []]));
            return;
        }
        $this->output->set_output(json_encode([
            'success' => true, 'songs' => $this->song_model->search($q), 'albums' => $this->album_model->search($q)
        ]));
    }
    
    public function favorites() {
        $device_id = $this->device_id;
        if (!$device_id) { $this->_error('Device ID required', 400); return; }

        $favorites = $this->favorite_model->get_favorites($device_id);
        $this->output->set_output(json_encode(['success' => true, 'favorites' => $favorites]));
    }
    
    public function toggle_favorite() {
        $device_id = $this->device_id;
        if (!$device_id) { $this->_error('Device ID required', 400); return; }

        $song_id = $this->input->post('song_id');
        if (!$song_id) { $this->_error('Song ID required', 400); return; }

        $result = $this->favorite_model->toggle_favorite($device_id, $song_id);
        $this->output->set_output(json_encode(['success' => true, 'is_favorite' => $result['is_favorite']]));
    }
    
    public function toggle_favorite_album() {
        $device_id = $this->device_id;
        if (!$device_id) { $this->_error('Device ID required', 400); return; }
        $album_id = $this->input->post('album_id');
        if (!$album_id) { $this->_error('Album ID required', 400); return; }

        $result = $this->favorite_model->toggle_album_favorite($device_id, $album_id);
        $this->output->set_output(json_encode(['success' => true, 'is_favorite' => $result['is_favorite']]));
    }
    
    public function playlists() {
        $user_id = $this->session->userdata('user_id');
        if (!$user_id) { $this->_error('Authentication required', 401); return; }
        $this->output->set_output(json_encode(['success' => true, 'playlists' => $this->playlist_model->get_user_playlists($user_id)]));
    }
    
    public function playlist($id) {
        $playlist = $this->playlist_model->get_playlist($id);
        if (!$playlist) { $this->_error('Playlist not found', 404); return; }
        $this->output->set_output(json_encode(['success' => true, 'playlist' => $playlist]));
    }
    
    public function create_playlist() {
        $user_id = $this->session->userdata('user_id');
        if (!$user_id) { $this->_error('Authentication required', 401); return; }
        $name = trim($this->input->post('name') ?? '');
        if (!$name) { $this->_error('Playlist name required', 400); return; }
        $playlist_id = $this->playlist_model->create_playlist($user_id, $name, $this->input->post('description'));
        $this->output->set_output(json_encode(['success' => true, 'playlist_id' => $playlist_id]));
    }
    
    public function playlist_add_song($playlist_id) {
        $user_id = $this->session->userdata('user_id');
        if (!$user_id) { $this->_error('Authentication required', 401); return; }
        $song_id = $this->input->post('song_id');
        if (!$song_id) { $this->_error('Song ID required', 400); return; }
        $result = $this->playlist_model->add_song($playlist_id, $song_id);
        $this->output->set_output(json_encode(['success' => $result, 'message' => $result ? 'Song added' : 'Song already in playlist']));
    }
    
    public function record_play() {
        $song_id = $this->input->post('song_id');
        if (!$song_id) { $this->output->set_output(json_encode(['success' => false])); return; }
        
        $device_id = $this->device_id;
        
        // Ensure listen tracking columns exist (MySQL safe)
        $fields = $this->db->list_fields('play_history');
        if (!in_array('duration', $fields)) {
            $this->db->query("ALTER TABLE play_history ADD COLUMN duration INT UNSIGNED DEFAULT 0");
        }
        if (!in_array('listened', $fields)) {
            $this->db->query("ALTER TABLE play_history ADD COLUMN listened INT UNSIGNED DEFAULT 0");
        }
        if (!in_array('percent', $fields)) {
            $this->db->query("ALTER TABLE play_history ADD COLUMN percent TINYINT UNSIGNED DEFAULT 0");
        }
        
        // Insert play record
        $this->db->insert('play_history', [
            'song_id' => $song_id, 
            'device_id' => $device_id,
            'user_id' => $this->session->userdata('user_id'),
            'played_at' => gmdate('Y-m-d H:i:s'),
            'duration' => 0,
            'listened' => 0,
            'percent' => 0
        ]);
        
        $play_id = $this->db->insert_id();
        
        // Increment device play count
        if ($device_id) {
            $this->db->where('id', $device_id)->set('play_count', 'play_count + 1', FALSE)->update('devices');
        }
        
        $this->output->set_output(json_encode(['success' => true, 'play_id' => $play_id]));
    }
    
    /**
     * Update play record with listen duration
     * Removes false starts (under 20 seconds)
     */
    public function update_play() {
        $play_id = $this->input->post('play_id');
        $listened = (int) $this->input->post('listened');
        $duration = (int) $this->input->post('duration');
        
        if (!$play_id) { $this->output->set_output(json_encode(['success' => false])); return; }
        
        // False start - under 20 seconds: delete the play record entirely
        if ($listened < 20) {
            $play = $this->db->get_where('play_history', ['id' => $play_id])->row();
            if ($play) {
                $this->db->where('id', $play_id)->delete('play_history');
                // Reverse the device play count increment
                if ($play->device_id) {
                    $this->db->where('id', $play->device_id)
                             ->where('play_count >', 0)
                             ->set('play_count', 'play_count - 1', FALSE)
                             ->update('devices');
                }
            }
            $this->output->set_output(json_encode(['success' => true, 'removed' => true, 'reason' => 'false_start']));
            return;
        }
        
        $percent = ($duration > 0) ? min(100, round(($listened / $duration) * 100)) : 0;
        
        $this->db->where('id', $play_id)->update('play_history', [
            'listened' => $listened,
            'duration' => $duration,
            'percent' => $percent
        ]);
        
        $this->output->set_output(json_encode(['success' => true, 'percent' => $percent]));
    }
    
    public function auto_scan() {
        $this->load->config('music_config');
        $last = $this->db->get_where('settings', ['config_key' => 'last_scan'])->row();
        $last_time = $last && $last->config_value ? strtotime($last->config_value) : 0;
        $music_path = $this->config->item('music_origin_path');
        
        if (!is_dir($music_path)) {
            $this->output->set_output(json_encode(['success' => false, 'message' => 'Music directory not found']));
            return;
        }
        
        if (filemtime($music_path) > $last_time) {
            $stats = $this->song_model->scan_library();
            $this->output->set_output(json_encode(['success' => true, 'updated' => true, 'stats' => $stats]));
        } else {
            $this->output->set_output(json_encode(['success' => true, 'updated' => false, 'last_scan' => $last ? $last->config_value : null]));
        }
    }
    
    public function stats() {
        $stats = [
            'total_albums' => $this->db->count_all('albums'),
            'total_songs' => $this->db->count_all('songs'),
            'total_plays' => $this->db->count_all('play_history'),
            'misc_songs' => $this->song_model->count_misc_songs()
        ];
        $dur = $this->db->select_sum('duration')->get('songs')->row();
        $stats['total_duration'] = $dur->duration ?? 0;
        $last = $this->db->get_where('settings', ['config_key' => 'last_scan'])->row();
        $stats['last_scan'] = $last ? $last->config_value : null;
        $this->output->set_output(json_encode(['success' => true, 'stats' => $stats]));
    }
    
    /**
     * Record an outgoing share (user tapped share button)
     * POST params: song_id, album_id, share_method
     */
    public function record_share() {
        $song_id = $this->input->post('song_id') ?: 0;
        $album_id = $this->input->post('album_id') ?: 0;
        $method = $this->input->post('share_method') ?: 'unknown';
        $share_type = $this->input->post('share_type') ?: 'song';
        
        $this->db->insert('share_history', [
            'song_id' => $song_id,
            'album_id' => $album_id,
            'device_id' => $this->device_id,
            'share_method' => $method,
            'share_type' => $share_type,
            'shared_at' => gmdate('Y-m-d H:i:s')
        ]);
        
        // Add share_type column if missing (upgrade from older schema) - safe for MySQL
        // Column already exists in new schema, so this is a no-op but kept for backwards compatibility
        
        $this->output->set_output(json_encode(['success' => true, 'share_id' => $this->db->insert_id()]));
    }
    
    /**
     * Record an incoming share click (someone opened a shared link)
     * POST params: song_id, album_id, from_device_id, referrer
     */
    public function record_share_click() {
        $song_id = $this->input->post('song_id') ?: 0;
        $album_id = $this->input->post('album_id') ?: 0;
        $from_device = $this->input->post('from_device_id');
        $referrer = $this->input->post('referrer') ?: '';
        $share_type = $this->input->post('share_type') ?: 'song';
        
        // share_type column already exists in schema
        
        $this->db->insert('share_clicks', [
            'song_id' => $song_id,
            'album_id' => $album_id,
            'from_device_id' => $from_device,
            'to_device_id' => $this->device_id,
            'referrer' => $referrer,
            'share_type' => $share_type,
            'clicked_at' => gmdate('Y-m-d H:i:s')
        ]);
        
        $this->output->set_output(json_encode(['success' => true]));
    }
    
    /**
     * Return the current app version by reading sw.js from disk
     * This bypasses the service worker entirely since it's a real server endpoint
     */
    public function app_version() {
        $version = null;
        
        // Try multiple possible locations for sw.js
        $paths = [
            FCPATH . 'sw.js',
            FCPATH . '../sw.js',
            $_SERVER['DOCUMENT_ROOT'] . '/sw.js',
            dirname(FCPATH) . '/sw.js'
        ];
        
        foreach ($paths as $sw_path) {
            if (file_exists($sw_path)) {
                $content = file_get_contents($sw_path);
                if (preg_match("/APP_VERSION\s*=\s*'([^']+)'/", $content, $m)) {
                    $version = $m[1];
                    break;
                }
            }
        }
        
        $this->output
            ->set_content_type('application/json')
            ->set_header('Cache-Control: no-cache, no-store, must-revalidate')
            ->set_output(json_encode(['version' => $version]));
    }
    
    /**
     * Public endpoint: most popular songs by play count
     * GET /api/popular?limit=10
     * Returns CORS headers for cross-origin access
     */
    public function popular() {
        // CORS headers
        $this->output
            ->set_header('Access-Control-Allow-Origin: *')
            ->set_header('Access-Control-Allow-Methods: GET, OPTIONS')
            ->set_header('Access-Control-Allow-Headers: Content-Type, X-Device-Id');

        // Handle preflight
        if ($this->input->method() === 'options') {
            $this->output->set_status_header(204);
            return;
        }

        $limit = max(1, min(50, (int) ($this->input->get('limit') ?: 10)));

        $this->load->config('music_config');

        $sql = "
            SELECT s.*, pc.play_count,
                   (SELECT als.album_id FROM album_songs als WHERE als.song_id = s.id LIMIT 1) as album_id,
                   (SELECT a.title FROM albums a JOIN album_songs als2 ON a.id = als2.album_id WHERE als2.song_id = s.id LIMIT 1) as album_title
            FROM (
                SELECT ph.song_id, COUNT(ph.id) as play_count
                FROM play_history ph
                WHERE ph.listened >= 20
                  AND NOT EXISTS (
                      SELECT 1 FROM devices d
                      WHERE d.id = ph.device_id
                      AND (d.excluded > 0 OR d.ip_address IN (SELECT ip FROM admin_ips))
                  )
                GROUP BY ph.song_id
                ORDER BY play_count DESC
                LIMIT ?
            ) pc
            JOIN songs s ON s.id = pc.song_id
            ORDER BY pc.play_count DESC
        ";

        $songs = $this->db->query($sql, [$limit])->result();

        foreach ($songs as &$song) {
            $this->song_model->populate_song_urls($song);
            $song->play_count = (int) $song->play_count;
            if ($song->album_id) {
                $song->album_id = (int) $song->album_id;
            }
        }

        $this->output->set_output(json_encode([
            'success' => true,
            'songs' => $songs,
            'limit' => $limit
        ]));
    }

    private function _error($msg, $code = 400) {
        $this->output->set_status_header($code);
        $this->output->set_output(json_encode(['success' => false, 'message' => $msg, 'error_code' => $code]));
    }
 
 
// New functions
   
    /**
     * Log client-side playback events for debugging
     * POST /api/log_event
     * 
     * Writes to: application/logs/playback_YYYY-MM-DD.log
     */
    public function log_event() {
        // Only accept POST
        if ($this->input->method() !== 'post') {
            return $this->_json(['error' => 'POST required'], 405);
        }
        
        $type = $this->input->post('type') ?: 'unknown';
        $message = $this->input->post('message') ?: '';
        $data = $this->input->post('data') ?: '{}';
        $device_id = $this->input->post('device_id') ?: 
                     $this->input->get_request_header('X-Device-Id') ?: 
                     'unknown';
        
        // Build log line
        $timestamp = gmdate('Y-m-d H:i:s');
        $log_line = sprintf(
            "[%s] [%s] [%s] %s %s\n",
            $timestamp,
            strtoupper($type),
            substr($device_id, 0, 20),
            $message,
            $data
        );
        
        // Write to daily log file
        $log_dir = APPPATH . 'logs/';
        $log_file = $log_dir . 'playback_' . date('Y-m-d') . '.log';
        
        // Ensure directory exists
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        // Append to file
        file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
        
        return $this->_json(['success' => true]);
    }

    /**
     * View recent playback logs (for admin debugging)
     * GET /api/playback_logs
     * 
     * Optional params:
     *   ?date=YYYY-MM-DD (defaults to today)
     *   ?lines=100 (number of lines, max 500)
     *   ?type=error (filter by type)
     */
    public function playback_logs() {
        // Optional: Check admin auth
        // if (!$this->session->userdata('is_admin')) {
        //     return $this->_json(['error' => 'Unauthorized'], 401);
        // }
        
        $date = $this->input->get('date') ?: date('Y-m-d');
        $lines = min((int)($this->input->get('lines') ?: 100), 500);
        $type_filter = $this->input->get('type');
        
        $log_file = APPPATH . 'logs/playback_' . $date . '.log';
        
        if (!file_exists($log_file)) {
            return $this->_json([
                'success' => true,
                'date' => $date,
                'logs' => [],
                'message' => 'No logs for this date'
            ]);
        }
        
        // Read file and get last N lines
        $all_lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        // Filter by type if specified
        if ($type_filter) {
            $type_upper = strtoupper($type_filter);
            $all_lines = array_filter($all_lines, function($line) use ($type_upper) {
                return strpos($line, '[' . $type_upper . ']') !== false;
            });
            $all_lines = array_values($all_lines); // Re-index
        }
        
        // Get last N lines
        $recent_lines = array_slice($all_lines, -$lines);
        
        // Reverse so newest is first
        $recent_lines = array_reverse($recent_lines);
        
        return $this->_json([
            'success' => true,
            'date' => $date,
            'total_lines' => count($all_lines),
            'showing' => count($recent_lines),
            'logs' => $recent_lines
        ]);
    }

    /**
     * Helper to output JSON response
     */
    private function _json($data, $status = 200) {
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode($data));
    }

}
