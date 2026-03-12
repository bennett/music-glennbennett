<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Docs extends CI_Controller {

    /**
     * Map URL section names to filesystem directories and display labels
     */
    private $sections = [
        'requirements' => [
            'dir'   => '',  // set in constructor
            'label' => 'Requirements',
            'pages' => [
                'overview'     => 'OVERVIEW.md',
                'player'       => 'PLAYER.md',
                'admin'        => 'ADMIN.md',
                'sharing'      => 'SHARING.md',
                'deployment'   => 'DEPLOYMENT.md',
                'known-issues' => 'KNOWN-ISSUES.md',
                'changelog'    => 'CHANGELOG.md',
            ],
        ],
        'technical' => [
            'dir'   => '',
            'label' => 'Technical Docs',
            'pages' => [
                'readme'        => 'README.md',
                'player'        => 'PLAYER.md',
                'admin'         => 'ADMIN.md',
                'media-session' => 'MEDIA-SESSION.md',
                'database'      => 'DATABASE.md',
            ],
        ],
        'api' => [
            'dir'   => '',
            'label' => 'API Reference',
            'pages' => [
                'readme'  => 'README.md',
                'songs'   => 'SONGS.md',
                'albums'  => 'ALBUMS.md',
                'popular' => 'POPULAR.md',
            ],
        ],
    ];

    public function __construct()
    {
        parent::__construct();
        $md = APPPATH . 'views/docs/md/';
        $this->sections['requirements']['dir'] = $md . 'requirements/';
        $this->sections['technical']['dir']    = $md . 'technical/';
        $this->sections['api']['dir']          = $md . 'api/';
    }

    /**
     * /docs → redirect to first page
     */
    public function index()
    {
        redirect('docs/requirements/overview');
    }

    /**
     * /docs/{section}/{slug}
     */
    public function page($section = null, $slug = null)
    {
        if (!$section || !$slug) {
            redirect('docs/requirements/overview');
            return;
        }

        // Sanitize
        $section = preg_replace('/[^a-z0-9\-]/', '', strtolower($section));
        $slug    = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));

        if (!isset($this->sections[$section])) {
            show_404();
            return;
        }

        $sec = $this->sections[$section];

        if (!isset($sec['pages'][$slug])) {
            show_404();
            return;
        }

        $file = $sec['dir'] . $sec['pages'][$slug];

        if (!file_exists($file)) {
            show_404();
            return;
        }

        $markdown = file_get_contents($file);
        $html     = $this->parse_markdown($markdown);
        $title    = $this->get_title_from_md($file);

        // Build sidebar data
        $sidebar = $this->build_sidebar($section, $slug);

        $this->load->view('docs/page', [
            'content'         => $html,
            'title'           => $title,
            'section'         => $section,
            'slug'            => $slug,
            'sidebar'         => $sidebar,
            'section_label'   => $sec['label'],
            'md_path'         => 'application/views/docs/md/' . $section . '/' . $sec['pages'][$slug],
        ]);
    }

    /**
     * Build sidebar navigation structure
     */
    private function build_sidebar($active_section, $active_slug)
    {
        $sidebar = [];

        foreach ($this->sections as $section_key => $sec) {
            $pages = [];
            foreach ($sec['pages'] as $slug => $filename) {
                $file  = $sec['dir'] . $filename;
                $label = file_exists($file)
                    ? $this->get_title_from_md($file)
                    : ucwords(str_replace('-', ' ', $slug));

                $pages[] = [
                    'slug'   => $slug,
                    'label'  => $label,
                    'url'    => site_url("docs/{$section_key}/{$slug}"),
                    'active' => ($section_key === $active_section && $slug === $active_slug),
                ];
            }

            $sidebar[] = [
                'key'    => $section_key,
                'label'  => $sec['label'],
                'pages'  => $pages,
                'active' => ($section_key === $active_section),
            ];
        }

        return $sidebar;
    }

    /**
     * Extract title from first # heading in markdown
     */
    private function get_title_from_md($file)
    {
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
        return ucwords(str_replace('-', ' ', basename($file, '.md')));
    }

    /**
     * Parse markdown to HTML (uses Parsedown if available, fallback otherwise)
     */
    private function parse_markdown($markdown)
    {
        $parsedown_path = APPPATH . 'third_party/Parsedown.php';
        if (file_exists($parsedown_path)) {
            require_once($parsedown_path);
            $parsedown = new Parsedown();
            $parsedown->setSafeMode(true);
            return $parsedown->text($markdown);
        }

        // Fallback markdown parser
        $html = htmlspecialchars($markdown, ENT_QUOTES, 'UTF-8');

        // Headers
        $html = preg_replace('/^#### (.+)$/m', '<h4>$1</h4>', $html);
        $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);

        // Code blocks (``` ... ```)
        $html = preg_replace_callback('/```(\w*)\n(.*?)```/s', function($m) {
            return '<pre><code>' . $m[2] . '</code></pre>';
        }, $html);

        // Inline code
        $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);

        // Bold and italic
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);

        // Links [text](url)
        $html = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $html);

        // Horizontal rules
        $html = preg_replace('/^---$/m', '<hr>', $html);

        // Tables
        $html = preg_replace_callback('/(\|.+\|\n)+/s', function($m) {
            $rows = explode("\n", trim($m[0]));
            $out = '<table>';
            foreach ($rows as $i => $row) {
                if (preg_match('/^\|[\s\-:|]+\|$/', $row)) continue; // skip separator
                $cells = array_map('trim', explode('|', trim($row, '|')));
                $tag = ($i === 0) ? 'th' : 'td';
                $out .= '<tr>';
                foreach ($cells as $cell) {
                    $out .= "<{$tag}>{$cell}</{$tag}>";
                }
                $out .= '</tr>';
            }
            return $out . '</table>';
        }, $html);

        // Checkbox lists
        $html = preg_replace('/^- \[ \] (.+)$/m', '<li class="todo">&#9744; $1</li>', $html);
        $html = preg_replace('/^- \[x\] (.+)$/m', '<li class="todo done">&#9745; $1</li>', $html);

        // Unordered lists
        $html = preg_replace('/^- (.+)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/(<li>.*?<\/li>\n?)+/s', '<ul>$0</ul>', $html);
        $html = str_replace('</ul><ul>', '', $html);

        // Paragraphs
        $html = preg_replace('/\n\n+/', '</p><p>', $html);
        $html = '<p>' . $html . '</p>';

        // Clean up block elements inside paragraphs
        $html = preg_replace('/<p>\s*(<(?:h[1-6]|hr|pre|table|ul|ol)>)/s', '$1', $html);
        $html = preg_replace('/(<\/(?:h[1-6]|hr|pre|table|ul|ol)>)\s*<\/p>/s', '$1', $html);
        $html = preg_replace('/<p>\s*<\/p>/', '', $html);

        return $html;
    }
}
