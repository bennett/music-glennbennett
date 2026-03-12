<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Help extends CI_Controller {
    
    private $md_path;
    
    public function __construct() {
        parent::__construct();
        $this->md_path = APPPATH . 'views/help/md/';
    }
    
    /**
     * List all help topics as JSON (for player modal)
     */
    public function index() {
        $topics = $this->get_topics();
        
        // If AJAX request, return JSON
        if ($this->input->is_ajax_request() || $this->input->get('format') === 'json') {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => true,
                    'topics' => $topics
                ]));
            return;
        }
        
        // Otherwise render HTML view
        $this->load->view('help/index', ['topics' => $topics]);
    }
    
    /**
     * Get a specific help topic
     */
    public function topic($slug = null) {
        if (!$slug) {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['success' => false, 'error' => 'No topic specified']));
            return;
        }
        
        // Sanitize slug
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));
        $file = $this->md_path . $slug . '.md';
        
        if (!file_exists($file)) {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['success' => false, 'error' => 'Topic not found']));
            return;
        }
        
        $markdown = file_get_contents($file);
        $html = $this->parse_markdown($markdown);
        $title = $this->get_title_from_md($file);
        $mtime = filemtime($file);
        
        // If AJAX request, return JSON
        if ($this->input->is_ajax_request() || $this->input->get('format') === 'json') {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => true,
                    'slug' => $slug,
                    'title' => $title,
                    'content' => $html,
                    'version' => $mtime
                ]));
            return;
        }
        
        // Otherwise render HTML view
        $this->load->view('help/topic', [
            'content' => $html,
            'title' => $title,
            'slug' => $slug
        ]);
    }
    
    /**
     * About page - separate endpoint for About modal
     */
    public function about() {
        $file = $this->md_path . 'about.md';
        
        if (!file_exists($file)) {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['success' => false, 'error' => 'About content not found']));
            return;
        }
        
        $markdown = file_get_contents($file);
        $html = $this->parse_markdown($markdown);
        $mtime = filemtime($file);
        
        // If AJAX request, return JSON
        if ($this->input->is_ajax_request() || $this->input->get('format') === 'json') {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => true,
                    'title' => 'About',
                    'content' => $html,
                    'version' => $mtime
                ]));
            return;
        }
        
        // Otherwise render HTML view
        $this->load->view('help/topic', [
            'content' => $html,
            'title' => 'About Glenn Bennett',
            'slug' => 'about'
        ]);
    }
    
    /**
     * Get all topics with cache-busting versions
     */
    private function get_topics() {
        $topics = [];
        
        // Define topic order and icons
        $topic_meta = [
            'about' => ['icon' => '👤', 'order' => 1],
            'getting-started' => ['icon' => '🚀', 'order' => 2],
            'features' => ['icon' => '✨', 'order' => 3],
            'sharing' => ['icon' => '📤', 'order' => 4],
            'installing' => ['icon' => '📱', 'order' => 5],
            'carplay' => ['icon' => '🚗', 'order' => 6],
            'faq' => ['icon' => '❓', 'order' => 99]
        ];
        
        if (!is_dir($this->md_path)) {
            return $topics;
        }
        
        foreach (glob($this->md_path . '*.md') as $file) {
            $slug = basename($file, '.md');
            $mtime = filemtime($file);
            $title = $this->get_title_from_md($file);
            $meta = $topic_meta[$slug] ?? ['icon' => '📄', 'order' => 50];
            
            $topics[] = [
                'slug' => $slug,
                'title' => $title,
                'icon' => $meta['icon'],
                'order' => $meta['order'],
                'version' => $mtime,
                'url' => site_url("help/topic/$slug?v=$mtime")
            ];
        }
        
        // Sort by order
        usort($topics, function($a, $b) {
            return $a['order'] - $b['order'];
        });
        
        return $topics;
    }
    
    /**
     * Extract title from first # heading in markdown
     */
    private function get_title_from_md($file) {
        $handle = fopen($file, 'r');
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if (strpos($line, '# ') === 0) {
                    fclose($handle);
                    return trim(substr($line, 2));
                }
            }
            fclose($handle);
        }
        // Fallback to filename
        return ucwords(str_replace('-', ' ', basename($file, '.md')));
    }
    
    /**
     * Parse markdown to HTML
     */
    private function parse_markdown($markdown) {
        // Use Parsedown if available
        $parsedown_path = APPPATH . 'third_party/Parsedown.php';
        if (file_exists($parsedown_path)) {
            require_once($parsedown_path);
            $parsedown = new Parsedown();
            $parsedown->setSafeMode(true);
            return $parsedown->text($markdown);
        }
        
        // Simple fallback markdown parser
        $html = htmlspecialchars($markdown, ENT_QUOTES, 'UTF-8');
        
        // Headers
        $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);
        
        // Bold and italic
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);
        
        // Links [text](url)
        $html = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $html);
        
        // Horizontal rules
        $html = preg_replace('/^---$/m', '<hr>', $html);
        
        // Line breaks - convert double newlines to paragraphs
        $html = preg_replace('/\n\n+/', '</p><p>', $html);
        $html = '<p>' . $html . '</p>';
        
        // Clean up empty paragraphs around block elements
        $html = preg_replace('/<p>\s*(<h[1-6]>)/', '$1', $html);
        $html = preg_replace('/(<\/h[1-6]>)\s*<\/p>/', '$1', $html);
        $html = preg_replace('/<p>\s*<hr>\s*<\/p>/', '<hr>', $html);
        $html = preg_replace('/<p>\s*<\/p>/', '', $html);
        
        // Lists (simple)
        $html = preg_replace('/^- (.+)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html);
        $html = str_replace('</ul><ul>', '', $html); // Merge adjacent lists
        
        return $html;
    }
}
