<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Playlist_model extends CI_Model {
    
    public function __construct() {
        parent::__construct();
        $this->load->database();
    }
    
    /**
     * Get user playlists
     */
    public function get_user_playlists($user_id) {
        $playlists = $this->db
            ->where('user_id', $user_id)
            ->order_by('name')
            ->get('playlists')
            ->result();
        
        foreach ($playlists as &$playlist) {
            $playlist->song_count = $this->db
                ->where('playlist_id', $playlist->id)
                ->count_all_results('playlist_songs');
        }
        
        return $playlists;
    }
    
    /**
     * Get playlist with songs
     */
    public function get_playlist($id, $user_id = null) {
        $query = $this->db->where('id', $id);
        
        if ($user_id) {
            $query->where('user_id', $user_id);
        }
        
        $playlist = $query->get('playlists')->row();
        
        if (!$playlist) {
            return null;
        }
        
        // Get songs
        $playlist->songs = $this->db
            ->select('songs.*, playlist_songs.sort_order')
            ->join('playlist_songs', 'playlist_songs.song_id = songs.id')
            ->where('playlist_songs.playlist_id', $id)
            ->order_by('playlist_songs.sort_order')
            ->get('songs')
            ->result();
        
        // Add stream URLs
        $this->load->config('music_config');
        foreach ($playlist->songs as &$song) {
            $song->stream_url = $this->get_cdn_url($song->filename);
        }
        
        return $playlist;
    }
    
    /**
     * Create playlist
     */
    public function create_playlist($user_id, $name, $description = null) {
        $data = [
            'user_id' => $user_id,
            'name' => $name,
            'description' => $description,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->insert('playlists', $data);
        return $this->db->insert_id();
    }
    
    /**
     * Update playlist
     */
    public function update_playlist($id, $user_id, $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $this->db->where('id', $id);
        $this->db->where('user_id', $user_id);
        return $this->db->update('playlists', $data);
    }
    
    /**
     * Delete playlist
     */
    public function delete_playlist($id, $user_id) {
        $this->db->where('id', $id);
        $this->db->where('user_id', $user_id);
        return $this->db->delete('playlists');
    }
    
    /**
     * Add song to playlist
     */
    public function add_song($playlist_id, $song_id) {
        // Check if already exists
        $existing = $this->db->get_where('playlist_songs', [
            'playlist_id' => $playlist_id,
            'song_id' => $song_id
        ])->row();
        
        if ($existing) {
            return false;
        }
        
        // Get max sort order
        $max_order = $this->db
            ->select_max('sort_order')
            ->where('playlist_id', $playlist_id)
            ->get('playlist_songs')
            ->row()
            ->sort_order ?? 0;
        
        return $this->db->insert('playlist_songs', [
            'playlist_id' => $playlist_id,
            'song_id' => $song_id,
            'sort_order' => $max_order + 1,
            'added_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Remove song from playlist
     */
    public function remove_song($playlist_id, $song_id) {
        return $this->db->delete('playlist_songs', [
            'playlist_id' => $playlist_id,
            'song_id' => $song_id
        ]);
    }
    
    /**
     * Update playlist song order
     */
    public function update_song_order($playlist_id, $song_ids) {
        $order = 1;
        foreach ($song_ids as $song_id) {
            $this->db->where([
                'playlist_id' => $playlist_id,
                'song_id' => $song_id
            ]);
            $this->db->update('playlist_songs', ['sort_order' => $order++]);
        }
        return true;
    }
    
    private function get_cdn_url($filename) {
        $this->load->config('music_config');
        $base = rtrim($this->config->item('bunny_cdn_url'), '/');
        $path = $this->config->item('bunny_music_path');
        return $base . $path . '/' . ltrim($filename, '/');
    }
}
