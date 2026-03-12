<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Song_model extends CI_Model {
    
    public function __construct() {
        parent::__construct();
        $this->load->database();
    }
    
    public function scan_library() {
        $this->load->library('metadata_extractor');
        $this->load->model('album_model');
        $this->load->config('music_config');
        
        $stats = ['added' => 0, 'updated' => 0, 'albums_created' => 0, 'albums_updated' => 0, 'misc_songs' => 0, 'errors' => 0];
        
        $music_path = rtrim($this->config->item('music_origin_path'), '/');
        $exclude_dirs = $this->config->item('exclude_directories') ?: [];
        $min_songs = $this->config->item('min_songs_per_album') ?: 4;
        
        if (!is_dir($music_path)) {
            log_message('error', 'Music directory not found: ' . $music_path);
            return $stats;
        }
        
        $files = $this->scan_directory_recursive($music_path, $exclude_dirs);
        $albums_data = [];
        
        foreach ($files as $file_path) {
            try {
                $filename = str_replace($music_path . '/', '', $file_path);
                $metadata = $this->metadata_extractor->analyze($file_path);
                
                $album_name = $metadata['album'] ?: 'Unknown Album';
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
                
                $albums_data[$album_name]['songs'][] = [
                    'filename' => $filename, 'title' => $metadata['title'] ?: pathinfo($filename, PATHINFO_FILENAME),
                    'artist' => $artist_name, 'duration' => $metadata['duration'], 'file_hash' => md5_file($file_path),
                    'track_number' => $metadata['track_number'], 'position' => $position,
                    'cover_art' => $metadata['cover_art'] // Pass individual song cover
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
        
        $this->db->replace('settings', ['config_key' => 'last_scan', 'config_value' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        return $stats;
    }
    
    private function find_directory_cover($dir, $album_name) {
        $exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        
        // 1. Check imgs directory with flexible matching (case-insensitive, ignore underscores/spaces)
        $this->load->config('music_config');
        $music_path = rtrim($this->config->item('music_origin_path'), '/');
        $imgs_dir = $music_path . '/imgs';
        
        if (is_dir($imgs_dir)) {
            // Normalize album name for comparison (lowercase, remove spaces/underscores/dashes)
            $album_normalized = strtolower(str_replace(['_', ' ', '-'], '', $album_name));
            
            $files = @scandir($imgs_dir);
            if ($files) {
                foreach ($files as $f) {
                    if ($f === '.' || $f === '..') continue;
                    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                    if (!in_array($ext, $exts)) continue;
                    
                    // Normalize filename for comparison
                    $file_basename = pathinfo($f, PATHINFO_FILENAME);
                    $file_normalized = strtolower(str_replace(['_', ' ', '-'], '', $file_basename));
                    
                    if ($file_normalized === $album_normalized) {
                        $data = @file_get_contents($imgs_dir . '/' . $f);
                        if ($data) return ['data' => $data, 'mime' => 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext)];
                    }
                }
            }
        }
        
        // 2. Check song directory for cover files
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
        $data = ['filename' => $song['filename'], 'title' => $song['title'], 'artist' => $song['artist'], 'duration' => $song['duration'], 'file_hash' => $song['file_hash'], 'updated_at' => date('Y-m-d H:i:s')];
        
        // Save individual song cover if it has embedded art
        if (!empty($song['cover_art']) && isset($song['cover_art']['data'])) {
            $cover_filename = $this->save_song_cover($song['cover_art']['data'], $song['cover_art']['mime'] ?? 'image/jpeg', $song['filename']);
            if ($cover_filename) {
                $data['cover_filename'] = $cover_filename;
            }
        }
        
        if (!$existing) {
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert('songs', $data);
            $stats['added']++;
            return $this->db->insert_id();
        }
        
        // Always update cover if we extracted one from the MP3
        // (cover is already saved to disk by this point, just update DB reference)
        
        $this->db->where('id', $existing->id)->update('songs', $data);
        $stats['updated']++;
        return $existing->id;
    }
    
    private function save_song_cover($data, $mime, $song_filename) {
        $this->load->config('music_config');
        $path = rtrim($this->config->item('cover_art_path'), '/') . '/songs/';
        if (!is_dir($path)) mkdir($path, 0755, true);
        $filename = md5($song_filename) . '.webp';
        $img = @imagecreatefromstring($data);
        if ($img) {
            $w = imagesx($img); $h = imagesy($img);
            // Resize if too large (max 300px for song covers)
            if ($w > 300 || $h > 300) {
                $r = min(300/$w, 300/$h);
                $nw = (int)($w*$r); $nh = (int)($h*$r);
                $res = imagecreatetruecolor($nw, $nh);
                imagecopyresampled($res, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                imagedestroy($img); $img = $res;
            }
            imagewebp($img, $path . $filename, 80);
            imagedestroy($img);
            return 'songs/' . $filename;
        }
        return null;
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
    
    private function save_cover_art($data, $mime, $name) {
        $this->load->config('music_config');
        $path = rtrim($this->config->item('cover_art_path'), '/') . '/';
        if (!is_dir($path)) mkdir($path, 0755, true);
        $filename = md5($name) . '.webp';
        $img = @imagecreatefromstring($data);
        if ($img) {
            $w = imagesx($img); $h = imagesy($img);
            if ($w > 500 || $h > 500) {
                $r = min(500/$w, 500/$h);
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
    
    public function get_all_songs($limit = 50, $offset = 0) {
        $songs = $this->db->limit($limit, $offset)->order_by('artist, title')->get('songs')->result();
        foreach ($songs as &$s) {
            $s->stream_url = $this->get_cdn_url($s->filename, $s->file_hash ?? null);
            $s->cover_url = $this->get_song_cover_url($s);
        }
        return $songs;
    }
    
    public function count_all_songs() { return $this->db->count_all('songs'); }
    
    public function get_misc_songs() {
        $songs = $this->db->query("SELECT * FROM songs WHERE NOT EXISTS (SELECT 1 FROM album_songs WHERE song_id = songs.id) ORDER BY artist, title")->result();
        foreach ($songs as &$s) { 
            $s->stream_url = $this->get_cdn_url($s->filename, $s->file_hash ?? null); 
            $s->cover_url = $this->get_song_cover_url($s);
            $s->track_number = null; 
        }
        return $songs;
    }
    
    public function count_misc_songs() {
        return (int) $this->db->query("SELECT COUNT(*) as c FROM songs WHERE NOT EXISTS (SELECT 1 FROM album_songs WHERE song_id = songs.id)")->row()->c;
    }
    
    public function get_song($id) {
        $song = $this->db->get_where('songs', ['id' => $id])->row();
        if ($song) {
            $song->stream_url = $this->get_cdn_url($song->filename, $song->file_hash ?? null);
            $song->cover_url = $this->get_song_cover_url($song);
        }
        return $song;
    }
    
    /**
     * Get cover URL for a song - serves from local uploads
     */
    private function get_song_cover_url($song) {
        if (!empty($song->cover_filename)) {
            $this->load->config('music_config');
            $cdn_url = rtrim($this->config->item('cover_art_url'), '/');
            $url = $cdn_url . '/' . $song->cover_filename;
            // Cache-bust only when file changes (using file_hash)
            if (!empty($song->file_hash)) {
                $url .= '?h=' . substr($song->file_hash, 0, 8);
            }
            return $url;
        }
        return null;
    }
    
    public function record_play($song_id, $user_id = null) {
        $this->load->config('music_config');
        if ($user_id && $user_id == $this->config->item('admin_user_id')) return;
        $this->db->insert('play_history', ['song_id' => $song_id, 'user_id' => $user_id, 'played_at' => date('Y-m-d H:i:s')]);
    }
    
    private function get_cdn_url($filename, $file_hash = null) {
        $this->load->config('music_config');
        $cdn = $this->config->item('music_cdn_url');
        if (!$cdn) return '';
        $url = rtrim($cdn, '/') . '/' . ltrim($filename, '/');
        if ($file_hash) {
            $url .= '?h=' . substr($file_hash, 0, 10);
        }
        return $url;
    }
    
    public function search($query) {
        $songs = $this->db->group_start()->like('title', $query)->or_like('artist', $query)->group_end()->limit(30)->get('songs')->result();
        foreach ($songs as &$s) $s->stream_url = $this->get_cdn_url($s->filename, $s->file_hash ?? null);
        return $songs;
    }
    
    public function get_favorites($user_id) {
        $songs = $this->db->select('songs.*')->join('favorites', 'favorites.song_id = songs.id')->where('favorites.user_id', $user_id)->order_by('favorites.sort_order')->get('songs')->result();
        foreach ($songs as &$s) $s->stream_url = $this->get_cdn_url($s->filename, $s->file_hash ?? null);
        return $songs;
    }
    
    public function toggle_favorite($user_id, $song_id) {
        $exists = $this->db->get_where('favorites', ['user_id' => $user_id, 'song_id' => $song_id])->row();
        if ($exists) {
            $this->db->delete('favorites', ['user_id' => $user_id, 'song_id' => $song_id]);
            return ['favorited' => false];
        }
        $max = $this->db->select_max('sort_order')->where('user_id', $user_id)->get('favorites')->row();
        $this->db->insert('favorites', ['user_id' => $user_id, 'song_id' => $song_id, 'sort_order' => ($max->sort_order ?? 0) + 1, 'created_at' => date('Y-m-d H:i:s')]);
        return ['favorited' => true];
    }
}
