<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User_model extends CI_Model {
    
    public function __construct() {
        parent::__construct();
        $this->load->database();
    }
    
    /**
     * Register new user
     */
    public function register($data) {
        $user_data = [
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'is_admin' => 0,
            'is_verified' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->insert('users', $user_data);
        return $this->db->insert_id();
    }
    
    /**
     * Login user
     */
    public function login($username, $password) {
        $user = $this->db
            ->where('username', $username)
            ->or_where('email', $username)
            ->get('users')
            ->row();
        
        if ($user && password_verify($password, $user->password)) {
            return $user;
        }
        
        return false;
    }
    
    /**
     * Get user by ID
     */
    public function get_user($id) {
        return $this->db->get_where('users', ['id' => $id])->row();
    }
    
    /**
     * Update user
     */
    public function update_user($id, $data) {
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $this->db->where('id', $id);
        return $this->db->update('users', $data);
    }
    
    /**
     * Check if username exists
     */
    public function username_exists($username) {
        $count = $this->db->where('username', $username)->count_all_results('users');
        return $count > 0;
    }
    
    /**
     * Check if email exists
     */
    public function email_exists($email) {
        $count = $this->db->where('email', $email)->count_all_results('users');
        return $count > 0;
    }
    
    /**
     * Get user statistics
     */
    public function get_user_stats($user_id) {
        $stats = [];
        
        // Favorite songs count
        $stats['favorite_songs'] = $this->db
            ->where('user_id', $user_id)
            ->count_all_results('favorites');
        
        // Favorite albums count
        $stats['favorite_albums'] = $this->db
            ->where('user_id', $user_id)
            ->count_all_results('favorite_albums');
        
        // Playlists count
        $stats['playlists'] = $this->db
            ->where('user_id', $user_id)
            ->count_all_results('playlists');
        
        // Total plays
        $stats['total_plays'] = $this->db
            ->where('user_id', $user_id)
            ->count_all_results('play_history');
        
        // Most played songs
        $stats['top_songs'] = $this->db
            ->select('songs.id, songs.title, songs.artist, COUNT(*) as play_count')
            ->join('songs', 'songs.id = play_history.song_id')
            ->where('play_history.user_id', $user_id)
            ->group_by('songs.id')
            ->order_by('play_count DESC')
            ->limit(10)
            ->get('play_history')
            ->result();
        
        return $stats;
    }
}
