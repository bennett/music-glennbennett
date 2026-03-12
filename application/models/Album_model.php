<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Album_model extends CI_Model {
    
    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->load->config('music_config');
        $this->load->model('Song_model');
    }
    
    // =========================================================================
    // PUBLIC GETTERS
    // =========================================================================
    
    /**
     * Get all albums with song counts and durations
     */
    public function get_all_albums($limit = null, $offset = 0) {
        if ($limit) {
            $this->db->limit($limit, $offset);
        }
        
        $albums = $this->db->order_by('year DESC, title')->get('albums')->result();
        
        foreach ($albums as &$album) {
            $album->cover_url = $this->get_cover_url($album);
            $album->song_count = $this->db->where('album_id', $album->id)->count_all_results('album_songs');
            
            $duration_result = $this->db
                ->select_sum('songs.duration')
                ->join('album_songs', 'album_songs.song_id = songs.id')
                ->where('album_songs.album_id', $album->id)
                ->get('songs')
                ->row();
            $album->total_duration = $duration_result->duration ?? 0;
        }
        
        return $albums;
    }
    
    /**
     * Get single album with songs
     */
    public function get_album($id) {
        $album = $this->db->get_where('albums', ['id' => $id])->row();
        if (!$album) return null;
        
        $album->cover_url = $this->get_cover_url($album);
        
        // Get songs for this album
        $album->songs = $this->db
            ->select('songs.*, album_songs.track_number')
            ->join('album_songs', 'album_songs.song_id = songs.id')
            ->where('album_songs.album_id', $id)
            ->order_by('album_songs.track_number')
            ->get('songs')
            ->result();
        
        // Use Song_model to populate URLs for each song
        foreach ($album->songs as &$song) {
            $this->Song_model->populate_song_urls($song);
        }
        
        return $album;
    }
    
    /**
     * Alias for get_album (for compatibility)
     */
    public function get_album_with_songs($id) {
        return $this->get_album($id);
    }
    
    /**
     * Search albums
     */
    public function search($query) {
        $albums = $this->db
            ->group_start()
                ->like('title', $query)
                ->or_like('artist', $query)
                ->or_like('description', $query)
            ->group_end()
            ->limit(20)
            ->order_by('year DESC')
            ->get('albums')
            ->result();
        
        foreach ($albums as &$album) {
            $album->cover_url = $this->get_cover_url($album);
        }
        
        return $albums;
    }
    
    // =========================================================================
    // COVER URL - Album's domain
    // =========================================================================
    
    /**
     * Get cover art URL for album
     */
    public function get_cover_url($album) {
        $cover_art_url = $this->config->item('cover_art_url');
        $music_path = rtrim($this->config->item('music_origin_path') ?: '', '/');
        $imgs_dir = $music_path . '/imgs/albums';
        
        // FIRST: Check filesystem for album cover by flexible name matching
        // This is the primary source - album covers live in /songs/imgs/albums/
        if (is_dir($imgs_dir)) {
            // Normalize album title: lowercase, remove spaces/underscores/dashes
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
                    
                    // Flexible match: normalized names must be equal
                    if ($file_normalized === $album_normalized) {
                        // Found a match - return CDN URL with cache busting
                        $mtime = @filemtime($imgs_dir . '/' . $file);
                        $bust = $mtime ? '?t=' . $mtime : '';
                        if ($cover_art_url) {
                            return rtrim($cover_art_url, '/') . '/albums/' . $file . $bust;
                        }
                        return base_url('uploads/covers/albums/' . $file) . $bust;
                    }
                }
            }
        }
        
        // SECOND: Fall back to cover_filename from database
        // This is used for manually uploaded covers or embedded art
        if (!empty($album->cover_filename)) {
            $bust = !empty($album->updated_at) ? '?t=' . strtotime($album->updated_at) : '';
            if ($cover_art_url) {
                return rtrim($cover_art_url, '/') . '/' . $album->cover_filename . $bust;
            }
            return base_url('uploads/covers/' . $album->cover_filename) . $bust;
        }
        
        return null;
    }
    
    // =========================================================================
    // CRUD OPERATIONS
    // =========================================================================
    
    public function create_album($data) {
        $album_data = [
            'title' => $data['title'],
            'artist' => $data['artist'] ?? 'Glenn L. Bennett',
            'year' => $data['year'] ?? null,
            'cover_filename' => $data['cover_filename'] ?? null,
            'description' => $data['description'] ?? null,
            'release_date' => $data['release_date'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->insert('albums', $album_data);
        return $this->db->insert_id();
    }
    
    public function update_album($id, $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->where('id', $id)->update('albums', $data);
    }
    
    public function delete_album($id) {
        $this->db->delete('album_songs', ['album_id' => $id]);
        return $this->db->delete('albums', ['id' => $id]);
    }
    
    // =========================================================================
    // ALBUM-SONG RELATIONSHIPS
    // =========================================================================
    
    public function add_song_to_album($album_id, $song_id, $track_number = null) {
        if ($track_number === null) {
            $max = $this->db->select_max('track_number')->where('album_id', $album_id)->get('album_songs')->row();
            $track_number = ($max->track_number ?? 0) + 1;
        }
        
        $existing = $this->db->get_where('album_songs', ['album_id' => $album_id, 'song_id' => $song_id])->row();
        
        if ($existing) {
            return $this->db->where(['album_id' => $album_id, 'song_id' => $song_id])->update('album_songs', ['track_number' => $track_number]);
        }
        
        return $this->db->insert('album_songs', ['album_id' => $album_id, 'song_id' => $song_id, 'track_number' => $track_number]);
    }
    
    public function remove_song_from_album($album_id, $song_id) {
        return $this->db->delete('album_songs', ['album_id' => $album_id, 'song_id' => $song_id]);
    }
    
    public function get_song_albums($song_id) {
        return $this->db
            ->select('albums.*, album_songs.track_number')
            ->join('album_songs', 'album_songs.album_id = albums.id')
            ->where('album_songs.song_id', $song_id)
            ->order_by('albums.year DESC')
            ->get('albums')
            ->result();
    }
    
    // =========================================================================
    // UTILITIES
    // =========================================================================
    
    public function find_or_create($title, $artist = null, $year = null) {
        $this->db->where('title', $title);
        if ($artist) $this->db->where('artist', $artist);
        if ($year) $this->db->where('year', $year);
        $existing = $this->db->get('albums')->row();
        
        if ($existing) return $existing->id;
        
        return $this->create_album([
            'title' => $title,
            'artist' => $artist ?? 'Glenn L. Bennett',
            'year' => $year
        ]);
    }
    
    public function count_all() {
        return $this->db->count_all('albums');
    }
}
