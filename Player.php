<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Player Controller
 * 
 * Handles the public-facing music player with server-side rendering
 */
class Player extends CI_Controller {
    
    public function __construct() {
        parent::__construct();
        $this->load->model('Album_model');
        $this->load->model('Song_model');
        $this->load->model('Favorite_model');
        $this->load->helper('url');
    }
    
    /**
     * Main player page - loads album data server-side
     */
    public function index($album_id = null) {
        // Get all albums for the dropdown
        $data['albums'] = $this->Album_model->get_all_albums();
        
        // Determine which album to show
        if ($album_id) {
            $data['current_album'] = $this->Album_model->get_album_with_songs($album_id);
        } elseif ($this->input->get('album')) {
            $data['current_album'] = $this->Album_model->get_album_with_songs($this->input->get('album'));
        } else {
            // Default to first album or last played (from cookie)
            $last_album = $this->input->cookie('last_album_id');
            if ($last_album) {
                $data['current_album'] = $this->Album_model->get_album_with_songs($last_album);
            }
            // If no last album or it failed to load, try the first available album
            if (empty($data['current_album']) && !empty($data['albums'])) {
                foreach ($data['albums'] as $album) {
                    $data['current_album'] = $this->Album_model->get_album_with_songs($album->id);
                    if ($data['current_album']) {
                        break;
                    }
                }
            }
        }
        
        // Check for direct song link
        if ($this->input->get('song')) {
            $data['autoplay_song_id'] = $this->input->get('song');
            $data['autoplay_song'] = $this->Song_model->get_song($this->input->get('song'));
        }
        
        // Check for misc songs
        $data['has_misc'] = $this->Song_model->count_misc_songs() > 0;
        
        $this->load->view('player/index', $data);
    }
    
    /**
     * Load album via AJAX (for switching albums without page reload)
     */
    public function album($id) {
        $this->output->set_content_type('application/json');
        
        if ($id === 'favorites') {
            $device_id = $this->input->get_request_header('X-Device-Id');
            $favorites = $this->Favorite_model->get_favorites($device_id);
            
            $this->output->set_output(json_encode([
                'success' => true,
                'album' => [
                    'id' => 'favorites',
                    'title' => 'Favorites',
                    'artist' => 'Glenn L. Bennett',
                    'cover_url' => null,
                    'songs' => $favorites
                ]
            ]));
            return;
        }
        
        if ($id === 'misc') {
            $misc = $this->Song_model->get_misc_songs();
            
            $this->output->set_output(json_encode([
                'success' => true,
                'album' => [
                    'id' => 'misc',
                    'title' => 'Misc Songs',
                    'artist' => 'Glenn L. Bennett',
                    'cover_url' => null,
                    'songs' => $misc
                ]
            ]));
            return;
        }
        
        $album = $this->Album_model->get_album_with_songs($id);
        
        if ($album) {
            $this->output->set_output(json_encode([
                'success' => true,
                'album' => $album
            ]));
        } else {
            $this->output->set_output(json_encode([
                'success' => false,
                'error' => 'Album not found'
            ]));
        }
    }
    
    /**
     * Record a play event
     */
    public function record_play() {
        $this->output->set_content_type('application/json');
        
        $song_id = $this->input->post('song_id');
        $device_id = $this->input->get_request_header('X-Device-Id');
        
        if ($song_id) {
            $this->Song_model->record_play($song_id, $device_id);
            $this->output->set_output(json_encode(['success' => true]));
        } else {
            $this->output->set_output(json_encode(['success' => false]));
        }
    }
    
    /**
     * Toggle favorite status
     */
    public function toggle_favorite() {
        $this->output->set_content_type('application/json');

        $song_id = $this->input->post('song_id');
        $device_id = $this->input->get_request_header('X-Device-Id');

        if ($song_id && $device_id) {
            $result = $this->Favorite_model->toggle_favorite($device_id, $song_id);
            $this->output->set_output(json_encode([
                'success' => true,
                'is_favorite' => $result['is_favorite']
            ]));
        } else {
            $this->output->set_output(json_encode(['success' => false]));
        }
    }

    /**
     * Check if a song is favorited
     */
    public function is_favorite() {
        $this->output->set_content_type('application/json');

        $song_id = $this->input->get('song_id');
        $device_id = $this->input->get_request_header('X-Device-Id');

        if ($song_id && $device_id) {
            $this->output->set_output(json_encode([
                'success' => true,
                'is_favorite' => $this->Favorite_model->is_favorite($device_id, $song_id)
            ]));
        } else {
            $this->output->set_output(json_encode(['success' => false, 'is_favorite' => false]));
        }
    }

    /**
     * Get favorites list
     */
    public function favorites() {
        $this->output->set_content_type('application/json');

        $device_id = $this->input->get_request_header('X-Device-Id');
        $favorites = $this->Favorite_model->get_favorites($device_id);

        $this->output->set_output(json_encode([
            'success' => true,
            'favorites' => $favorites
        ]));
    }
    
    /**
     * Log client-side events (for debugging)
     */
    public function log_event() {
        // Fire and forget - just accept the data
        $this->output->set_content_type('application/json');
        $this->output->set_output(json_encode(['success' => true]));
        
        // Optionally log to file for debugging
        if (ENVIRONMENT === 'development') {
            $data = $this->input->post();
            log_message('debug', 'Player event: ' . json_encode($data));
        }
    }
}
