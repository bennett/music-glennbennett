<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Podcast Controller
 * Serves the podcast episode player page and tracks listens
 */
class Podcast extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->helper('url');
        $this->load->config('music_config');
    }

    /**
     * Podcast episode player page
     */
    public function index() {
        $cdn_url = rtrim($this->config->item('music_cdn_url') ?: '', '/');
        $origin_url = rtrim($this->config->item('music_origin_url') ?: '', '/');

        $data = [
            'podcast_title' => 'Algorithmic Amnesia: A Deep Dive',
            'episode' => (object)[
                'title' => 'Reclaiming Your Attention',
                'description' => "A deep dive into Glenn Bennett's innovative approach to experiencing music.",
                'stream_url' => $cdn_url . '/podcast/episode_1.mp3',
                'fallback_url' => $origin_url . '/podcast/episode_1.mp3',
                'cover_url' => $cdn_url . '/podcast/cover.jpg',
                'cover_fallback_url' => $origin_url . '/podcast/cover.jpg',
            ],
        ];

        $this->load->view('podcast/index', $data);
    }

    /**
     * Record a podcast play event (AJAX)
     */
    public function record_play() {
        $this->output->set_content_type('application/json');

        $device_id = $this->input->get_request_header('X-Device-Id');
        $listened = (int)$this->input->post('listened');
        $percent = (int)$this->input->post('percent');
        $episode = $this->input->post('episode') ?: 'episode_1';

        // Ensure podcast_plays table exists
        $this->load->database();
        if (!$this->db->table_exists('podcast_plays')) {
            $this->db->query("CREATE TABLE podcast_plays (
                id INT AUTO_INCREMENT PRIMARY KEY,
                episode VARCHAR(100) NOT NULL,
                device_id VARCHAR(100),
                ip_address VARCHAR(45),
                listened INT DEFAULT 0,
                percent INT DEFAULT 0,
                played_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } else {
            // Add ip_address column if missing
            $fields = $this->db->list_fields('podcast_plays');
            if (!in_array('ip_address', $fields)) {
                $this->db->query("ALTER TABLE podcast_plays ADD COLUMN ip_address VARCHAR(45)");
            }
        }

        $this->db->insert('podcast_plays', [
            'episode' => $episode,
            'device_id' => $device_id,
            'ip_address' => $this->input->ip_address(),
            'listened' => $listened,
            'percent' => $percent,
            'played_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->output->set_output(json_encode(['success' => true]));
    }
}
