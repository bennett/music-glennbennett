<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Song_model extends CI_Model {
    
    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->load->config('music_config');
    }
    
    // =========================================================================
    // PUBLIC GETTERS
    // =========================================================================
    
    /**
     * Get single song by ID with URLs populated
     */
    public function get_song($id) {
        $song = $this->db->get_where('songs', ['id' => $id])->row();
        if ($song) {
            $this->populate_song_urls($song);
        }
        return $song;
    }
    
    /**
     * Get song with cover - falls back to album cover if song has none
     */
    public function get_song_with_cover($id) {
        $song = $this->get_song($id);
        if (!$song) return null;
        
        // If song has no cover, try to get album cover
        if (empty($song->cover_url)) {
            $album_link = $this->db->select('album_id')->where('song_id', $id)->get('album_songs')->row();
            if ($album_link) {
                $this->load->model('Album_model');
                $album = $this->Album_model->get_album($album_link->album_id);
                if ($album && !empty($album->cover_url)) {
                    $song->cover_url = $album->cover_url;
                    $song->album_title = $album->title;
                }
            }
        }
        
        return $song;
    }
    
    /**
     * Get all songs with pagination
     */
    public function get_all_songs($limit = 50, $offset = 0) {
        $songs = $this->db->limit($limit, $offset)->order_by('artist, title')->get('songs')->result();
        foreach ($songs as &$s) {
            $this->populate_song_urls($s);
        }
        return $songs;
    }
    
    /**
     * Get songs not in any album
     */
    public function get_misc_songs() {
        $songs = $this->db->query("SELECT * FROM songs WHERE NOT EXISTS (SELECT 1 FROM album_songs WHERE song_id = songs.id) ORDER BY artist, title")->result();
        foreach ($songs as &$s) {
            $this->populate_song_urls($s);
            $s->track_number = null;
        }
        return $songs;
    }
    
    /**
     * Search songs
     */
    public function search($query) {
        $songs = $this->db->group_start()->like('title', $query)->or_like('artist', $query)->group_end()->limit(30)->get('songs')->result();
        foreach ($songs as &$s) {
            $this->populate_song_urls($s);
        }
        return $songs;
    }
    
    // =========================================================================
    // URL GENERATION - Public methods for use by other models/controllers
    // =========================================================================
    
    /**
     * Populate a song object with stream_url and cover_url
     * Can be called by Album_model when loading songs
     */
    public function populate_song_urls(&$song) {
        $song->stream_url = $this->get_stream_url($song);
        $song->cover_url = $this->get_cover_url($song);
    }
    
    /**
     * Get streaming URL for a song (CDN or local)
     */
    public function get_stream_url($song) {
        $cdn = $this->config->item('music_cdn_url');
        if (!$cdn) return '';
        
        $url = rtrim($cdn, '/') . '/' . ltrim($song->filename, '/');
        
        // Cache busting
        if (!empty($song->file_hash)) {
            $url .= '?h=' . substr($song->file_hash, 0, 10);
        }
        
        return $url;
    }
    
    /**
     * Get cover image URL for a song
     */
    public function get_cover_url($song) {
        if (empty($song->cover_filename)) {
            return null;
        }
        
        $cdn_url = $this->config->item('cover_art_url');
        if (!$cdn_url) {
            return base_url('uploads/covers/' . $song->cover_filename);
        }
        
        $url = rtrim($cdn_url, '/') . '/' . $song->cover_filename;

        // Cache busting — use cover_filename hash so URL changes when cover art changes
        $url .= '?h=' . substr(md5($song->cover_filename), 0, 8);
        
        return $url;
    }
    
    // =========================================================================
    // COUNTS
    // =========================================================================
    
    public function count_all_songs() { 
        return $this->db->count_all('songs'); 
    }
    
    public function count_misc_songs() {
        return (int) $this->db->query("SELECT COUNT(*) as c FROM songs WHERE NOT EXISTS (SELECT 1 FROM album_songs WHERE song_id = songs.id)")->row()->c;
    }
    
    // =========================================================================
    // PLAY TRACKING
    // =========================================================================
    
    public function record_play($song_id, $user_id = null) {
        if ($user_id && $user_id == $this->config->item('admin_user_id')) return;
        $this->db->insert('play_history', ['song_id' => $song_id, 'user_id' => $user_id, 'played_at' => date('Y-m-d H:i:s')]);
    }
    
    // =========================================================================
    // LIBRARY SCANNING
    // =========================================================================
    
    public function scan_library() {
        $this->load->library('metadata_extractor');
        $this->load->model('album_model');

        // Rename any legacy "Unknown Album" to "Misc"
        $this->db->where('title', 'Unknown Album')
                  ->update('albums', ['title' => 'Misc']);

        $stats = ['added' => 0, 'updated' => 0, 'unchanged' => 0, 'removed' => 0, 'albums_created' => 0, 'albums_updated' => 0, 'misc_songs' => 0, 'errors' => 0];

        $music_path = rtrim($this->config->item('music_origin_path'), '/');
        $min_songs = $this->config->item('min_songs_per_album') ?: 4;
        $root_only = $this->config->item('scan_root_only') ?: false;

        if (!is_dir($music_path)) {
            log_message('error', 'Music directory not found: ' . $music_path);
            return $stats;
        }

        // Clear all album-song associations — they get rebuilt from MP3 tags below
        $this->db->truncate('album_songs');

        if ($root_only) {
            $files = $this->scan_directory_flat($music_path);
        } else {
            $exclude_dirs = $this->config->item('exclude_directories') ?: [];
            $files = $this->scan_directory_recursive($music_path, $exclude_dirs);
        }

        // Track which song filenames exist on disk so we can remove orphans after
        $seen_filenames = [];
        $albums_data = [];
        
        foreach ($files as $file_path) {
            try {
                $filename = str_replace($music_path . '/', '', $file_path);
                $metadata = $this->metadata_extractor->analyze($file_path);
                
                $album_name = $metadata['album'] ?: 'Misc';
                $artist_name = $metadata['artist'] ?: 'Unknown Artist';
                $position = $metadata['track_number'] ? (int) $metadata['track_number'] : 99;
                
                if (!isset($albums_data[$album_name])) {
                    $albums_data[$album_name] = [
                        'title' => $album_name, 'artist' => $artist_name, 'year' => $metadata['year'],
                        'cover_art' => null, 'cover_from_directory' => false, 'songs' => [], 'directory' => dirname($file_path)
                    ];
                    $dir_cover = $this->find_directory_cover(dirname($file_path), $album_name);
                    if ($dir_cover) {
                        $albums_data[$album_name]['cover_art'] = $dir_cover;
                        $albums_data[$album_name]['cover_from_directory'] = true;
                    }
                }
                
                if (!$albums_data[$album_name]['cover_from_directory'] && $metadata['cover_art'] && !$albums_data[$album_name]['cover_art']) {
                    $albums_data[$album_name]['cover_art'] = $metadata['cover_art'];
                }
                
                $seen_filenames[] = $filename;
                $albums_data[$album_name]['songs'][] = [
                    'filename' => $filename, 'title' => $metadata['title'] ?: pathinfo($filename, PATHINFO_FILENAME),
                    'artist' => $artist_name, 'duration' => $metadata['duration'], 'file_hash' => md5_file($file_path),
                    'track_number' => $metadata['track_number'], 'position' => $position,
                    'cover_art' => $metadata['cover_art']
                ];
            } catch (Exception $e) {
                $stats['errors']++;
                log_message('error', 'Scan error: ' . $e->getMessage());
            }
        }
        
        foreach ($albums_data as $album_data) {
            usort($album_data['songs'], function($a, $b) {
                return $a['position'] !== $b['position'] ? $a['position'] - $b['position'] : strcasecmp($a['title'], $b['title']);
            });
            
            if (count($album_data['songs']) >= $min_songs) {
                $album_id = $this->process_album($album_data, $stats);
                foreach ($album_data['songs'] as $i => $song) {
                    $song_id = $this->process_song($song, $stats);
                    $this->album_model->add_song_to_album($album_id, $song_id, $i + 1);
                }
            } else {
                foreach ($album_data['songs'] as $song) {
                    $this->process_song($song, $stats);
                    $stats['misc_songs']++;
                }
            }
        }
        
        // Remove orphan songs (in DB but no file on disk)
        if (!empty($seen_filenames)) {
            $orphans = $this->db->where_not_in('filename', $seen_filenames)->get('songs')->result();
            foreach ($orphans as $orphan) {
                $this->db->delete('album_songs', ['song_id' => $orphan->id]);
                $this->db->delete('favorites', ['song_id' => $orphan->id]);
                $this->db->delete('songs', ['id' => $orphan->id]);
                $stats['removed']++;
            }
        }

        // Remove empty albums (no songs linked)
        $all_albums = $this->db->get('albums')->result();
        foreach ($all_albums as $album) {
            $count = $this->db->where('album_id', $album->id)->count_all_results('album_songs');
            if ($count === 0) {
                $this->db->delete('albums', ['id' => $album->id]);
            }
        }

        $this->db->replace('settings', ['config_key' => 'last_scan', 'config_value' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        return $stats;
    }
    
    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================
    
    private function find_directory_cover($dir, $album_name) {
        $exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $music_path = rtrim($this->config->item('music_origin_path'), '/');
        $imgs_dir = $music_path . '/imgs';
        
        if (is_dir($imgs_dir)) {
            $album_normalized = strtolower(str_replace(['_', ' ', '-'], '', $album_name));
            $files = @scandir($imgs_dir);
            if ($files) {
                foreach ($files as $f) {
                    if ($f === '.' || $f === '..') continue;
                    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                    if (!in_array($ext, $exts)) continue;
                    $file_normalized = strtolower(str_replace(['_', ' ', '-'], '', pathinfo($f, PATHINFO_FILENAME)));
                    if ($file_normalized === $album_normalized) {
                        $data = @file_get_contents($imgs_dir . '/' . $f);
                        if ($data) return ['data' => $data, 'mime' => 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext)];
                    }
                }
            }
        }
        
        if (is_dir($dir)) {
            $patterns = ['cover.*', 'folder.*', 'album.*', 'front.*'];
            $files = @scandir($dir);
            if ($files) {
                foreach ($files as $f) {
                    if ($f === '.' || $f === '..') continue;
                    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                    if (!in_array($ext, $exts)) continue;
                    foreach ($patterns as $p) {
                        if (fnmatch($p, strtolower($f))) {
                            $data = @file_get_contents($dir . '/' . $f);
                            if ($data) return ['data' => $data, 'mime' => 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext)];
                        }
                    }
                }
            }
        }
        
        return null;
    }
    
    private function process_album($data, &$stats) {
        $this->load->model('album_model');
        $existing = $this->db->get_where('albums', ['title' => $data['title']])->row();
        $cover = null;
        if ($data['cover_art'] && isset($data['cover_art']['data'])) {
            $cover = $this->save_cover_art($data['cover_art']['data'], $data['cover_art']['mime'], $data['title']);
        }
        if (!$existing) {
            $id = $this->album_model->create_album(['title' => $data['title'], 'artist' => $data['artist'], 'year' => $data['year'], 'cover_filename' => $cover]);
            $stats['albums_created']++;
        } else {
            $id = $existing->id;
            $upd = [];
            if ($cover) $upd['cover_filename'] = $cover;
            if ($data['year']) $upd['year'] = $data['year'];
            if ($data['artist'] && $data['artist'] !== 'Unknown Artist' && empty($existing->artist)) $upd['artist'] = $data['artist'];
            if (!empty($upd)) $this->album_model->update_album($id, $upd);
            $stats['albums_updated']++;
        }
        return $id;
    }
    
    private function process_song($song, &$stats) {
        $existing = $this->db->get_where('songs', ['filename' => $song['filename']])->row();
        $data = ['filename' => $song['filename'], 'title' => $song['title'], 'artist' => $song['artist'], 'duration' => $song['duration'], 'file_hash' => $song['file_hash']];

        if (!empty($song['cover_art']) && isset($song['cover_art']['data'])) {
            $cover_filename = $this->save_song_cover($song['cover_art']['data'], $song['cover_art']['mime'] ?? 'image/jpeg', $song['filename']);
            if ($cover_filename) {
                $data['cover_filename'] = $cover_filename;
            }
        }

        if (!$existing) {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
            $this->db->insert('songs', $data);
            $stats['added']++;
            return $this->db->insert_id();
        }

        // Only update if something actually changed
        $changed = $existing->file_hash !== $song['file_hash']
            || $existing->title !== $song['title']
            || $existing->artist !== $song['artist']
            || (int)$existing->duration !== (int)$song['duration']
            || (isset($data['cover_filename']) && $existing->cover_filename !== $data['cover_filename']);

        if ($changed) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            $this->db->where('id', $existing->id)->update('songs', $data);
            $stats['updated']++;
        } else {
            $stats['unchanged']++;
        }
        return $existing->id;
    }
    
    private function save_song_cover($data, $mime, $song_filename) {
        $path = rtrim($this->config->item('cover_art_path'), '/') . '/songs/';
        if (!is_dir($path)) mkdir($path, 0755, true);
        $filename = md5($data) . '.webp';
        $img = @imagecreatefromstring($data);
        if ($img) {
            $w = imagesx($img); $h = imagesy($img);
            if ($w > 512 || $h > 512) {
                $r = min(512/$w, 512/$h);
                $nw = (int)($w*$r); $nh = (int)($h*$r);
                $res = imagecreatetruecolor($nw, $nh);
                imagecopyresampled($res, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                imagedestroy($img); $img = $res;
            }
            imagewebp($img, $path . $filename, 85);
            imagedestroy($img);
            return 'songs/' . $filename;
        }
        return null;
    }
    
    private function save_cover_art($data, $mime, $name) {
        $path = rtrim($this->config->item('cover_art_path'), '/') . '/';
        if (!is_dir($path)) mkdir($path, 0755, true);
        $filename = md5($name) . '.webp';
        $img = @imagecreatefromstring($data);
        if ($img) {
            $w = imagesx($img); $h = imagesy($img);
            if ($w > 512 || $h > 512) {
                $r = min(512/$w, 512/$h);
                $nw = (int)($w*$r); $nh = (int)($h*$r);
                $res = imagecreatetruecolor($nw, $nh);
                imagecopyresampled($res, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                imagedestroy($img); $img = $res;
            }
            imagewebp($img, $path . $filename, 85);
            imagedestroy($img);
            return $filename;
        }
        return null;
    }
    
    private function scan_directory_flat($dir) {
        $files = [];
        if (!is_dir($dir)) return $files;
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            if (!is_dir($path)) {
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if (in_array($ext, ['mp3', 'm4a', 'flac', 'ogg', 'wav'])) $files[] = $path;
            }
        }
        return $files;
    }

    private function scan_directory_recursive($dir, $exclude = []) {
        $files = [];
        if (!is_dir($dir)) return $files;
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                if (!in_array($item, $exclude)) $files = array_merge($files, $this->scan_directory_recursive($path, $exclude));
            } else {
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if (in_array($ext, ['mp3', 'm4a', 'flac', 'ogg', 'wav'])) $files[] = $path;
            }
        }
        return $files;
    }
}
