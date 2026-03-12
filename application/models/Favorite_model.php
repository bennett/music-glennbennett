<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Favorite_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->load->model('Song_model');
        $this->load->model('Album_model');
    }

    /**
     * Ensure a device row exists (favorites FK requires it)
     */
    private function ensure_device($device_id) {
        if (!$device_id) return;
        $exists = $this->db->where('id', $device_id)->count_all_results('devices');
        if (!$exists) {
            $ci =& get_instance();
            $this->db->insert('devices', [
                'id' => $device_id,
                'user_agent' => $ci->input->user_agent(),
                'ip_address' => $ci->input->ip_address(),
                'first_seen' => date('Y-m-d H:i:s'),
                'last_seen' => date('Y-m-d H:i:s')
            ]);
        }
    }

    // =========================================================================
    // SONG FAVORITES
    // =========================================================================

    /**
     * Check if a song is favorited by a device
     */
    public function is_favorite($device_id, $song_id) {
        return $this->db->where(['device_id' => $device_id, 'song_id' => $song_id])
                        ->count_all_results('favorites') > 0;
    }

    /**
     * Get all favorite songs for a device, with URLs populated
     */
    public function get_favorites($device_id) {
        $songs = $this->db->select('songs.*, favorites.created_at as favorited_at')
                          ->join('favorites', 'favorites.song_id = songs.id')
                          ->where('favorites.device_id', $device_id)
                          ->order_by('favorites.sort_order')
                          ->get('songs')
                          ->result();

        foreach ($songs as &$s) {
            $this->Song_model->populate_song_urls($s);
        }

        return $songs;
    }

    /**
     * Toggle a song favorite. Returns ['is_favorite' => bool]
     */
    public function toggle_favorite($device_id, $song_id) {
        $exists = $this->db->get_where('favorites', [
            'device_id' => $device_id,
            'song_id' => $song_id
        ])->row();

        if ($exists) {
            $this->db->delete('favorites', [
                'device_id' => $device_id,
                'song_id' => $song_id
            ]);
            return ['is_favorite' => false];
        }

        $this->ensure_device($device_id);

        $max = $this->db->select_max('sort_order')
                        ->where('device_id', $device_id)
                        ->get('favorites')
                        ->row();

        $this->db->insert('favorites', [
            'device_id' => $device_id,
            'song_id' => $song_id,
            'sort_order' => ($max->sort_order ?? 0) + 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return ['is_favorite' => true];
    }

    // =========================================================================
    // ALBUM FAVORITES
    // =========================================================================

    /**
     * Check if an album is favorited by a device
     */
    public function is_album_favorite($device_id, $album_id) {
        return $this->db->where(['device_id' => $device_id, 'album_id' => $album_id])
                        ->count_all_results('favorite_albums') > 0;
    }

    /**
     * Get all favorite albums for a device, with cover URLs
     */
    public function get_favorite_albums($device_id) {
        $albums = $this->db->select('albums.*, favorite_albums.created_at as favorited_at')
                           ->join('favorite_albums', 'favorite_albums.album_id = albums.id')
                           ->where('favorite_albums.device_id', $device_id)
                           ->order_by('favorite_albums.created_at DESC')
                           ->get('albums')
                           ->result();

        foreach ($albums as &$album) {
            $album->cover_url = $this->Album_model->get_cover_url($album);
        }

        return $albums;
    }

    /**
     * Toggle an album favorite. Returns ['is_favorite' => bool]
     */
    public function toggle_album_favorite($device_id, $album_id) {
        $exists = $this->db->get_where('favorite_albums', [
            'device_id' => $device_id,
            'album_id' => $album_id
        ])->row();

        if ($exists) {
            $this->db->delete('favorite_albums', [
                'device_id' => $device_id,
                'album_id' => $album_id
            ]);
            return ['is_favorite' => false];
        }

        $this->ensure_device($device_id);

        $this->db->insert('favorite_albums', [
            'device_id' => $device_id,
            'album_id' => $album_id,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return ['is_favorite' => true];
    }
}
