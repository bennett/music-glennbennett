<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Promos Controller
 * Serves promotional video content
 */
class Promos extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->helper('url');
        $this->load->config('music_config');
    }

    /**
     * How to Actually Listen promo video page
     */
    public function index() {
        $cdn_url = rtrim($this->config->item('music_cdn_url') ?: '', '/');
        $origin_url = rtrim($this->config->item('music_origin_url') ?: '', '/');

        $data = [
            'video' => (object)[
                'title' => 'How to Actually Listen',
                'description' => "A short guide to getting the most out of Glenn Bennett Music.",
                'video_url' => $cdn_url . '/promos/How_to_Actually_Listen.mp4',
                'fallback_url' => $origin_url . '/promos/How_to_Actually_Listen.mp4',
            ],
        ];

        $this->load->view('promos/index', $data);
    }

    /**
     * Record a promo view event (AJAX)
     */
    public function record_view() {
        $this->output->set_content_type('application/json');

        $device_id = $this->input->get_request_header('X-Device-Id');
        $watched = (int)$this->input->post('watched');
        $percent = (int)$this->input->post('percent');
        $promo = $this->input->post('promo') ?: 'how_to_listen';

        $this->load->database();
        if (!$this->db->table_exists('promo_views')) {
            $this->db->query("CREATE TABLE promo_views (
                id INT AUTO_INCREMENT PRIMARY KEY,
                promo VARCHAR(100) NOT NULL,
                device_id VARCHAR(100),
                ip_address VARCHAR(45),
                watched INT DEFAULT 0,
                percent INT DEFAULT 0,
                viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } else {
            // Add ip_address column if missing
            $fields = $this->db->list_fields('promo_views');
            if (!in_array('ip_address', $fields)) {
                $this->db->query("ALTER TABLE promo_views ADD COLUMN ip_address VARCHAR(45)");
            }
        }

        $this->db->insert('promo_views', [
            'promo' => $promo,
            'device_id' => $device_id,
            'ip_address' => $this->input->ip_address(),
            'watched' => $watched,
            'percent' => $percent,
            'viewed_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->output->set_output(json_encode(['success' => true]));
    }
}
