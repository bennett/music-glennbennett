<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Metadata Extractor
 * 
 * Wrapper for getID3 library to extract audio file metadata.
 * Named specifically to avoid conflicts with getID3's internal classes.
 */
class Metadata_extractor {
    
    private $getID3 = null;
    
    public function __construct() {
        // Lazy load - don't include getID3 until needed
    }
    
    /**
     * Load getID3 library only when needed
     */
    private function init_getid3() {
        if (!$this->getID3) {
            $getid3_path = APPPATH . 'third_party/getid3/getid3.php';
            
            if (!file_exists($getid3_path)) {
                log_message('error', 'getID3 library not found at: ' . $getid3_path);
                throw new Exception('getID3 library not installed');
            }
            
            require_once($getid3_path);
            $this->getID3 = new getID3;
        }
    }
    
    /**
     * Analyze audio file and extract metadata
     * 
     * @param string $file_path Full path to audio file
     * @return array Metadata with title, artist, album, year, duration, cover_art
     */
    public function analyze($file_path) {
        $this->init_getid3();
        
        try {
            $file_info = $this->getID3->analyze($file_path);
            
            // Extract track number (may be "1/12" format)
            $track_raw = $this->get_tag($file_info, 'track_number');
            $track_number = null;
            if ($track_raw) {
                // Handle "1/12" format
                if (strpos($track_raw, '/') !== false) {
                    $track_number = (int) explode('/', $track_raw)[0];
                } else {
                    $track_number = (int) $track_raw;
                }
            }
            
            $metadata = [
                'title' => $this->get_tag($file_info, 'title'),
                'artist' => $this->get_tag($file_info, 'artist'),
                'album' => $this->get_tag($file_info, 'album'),
                'year' => $this->get_year($file_info),
                'track_number' => $track_number,
                'duration' => isset($file_info['playtime_seconds']) ? (int) round($file_info['playtime_seconds']) : 0,
                'bitrate' => isset($file_info['audio']['bitrate']) ? (int) round($file_info['audio']['bitrate'] / 1000) : 0,
                'sample_rate' => isset($file_info['audio']['sample_rate']) ? $file_info['audio']['sample_rate'] : 0,
                'cover_art' => $this->get_cover_art($file_info)
            ];
            
            return $metadata;
            
        } catch (Exception $e) {
            log_message('error', 'Metadata extraction error: ' . $e->getMessage());
            return $this->get_default_metadata();
        }
    }
    
    /**
     * Extract tag from file info array
     */
    private function get_tag($file_info, $tag) {
        // Map common tag names
        $tag_map = [
            'track_number' => ['track_number', 'track', 'tracknumber']
        ];
        
        $tags_to_check = isset($tag_map[$tag]) ? $tag_map[$tag] : [$tag];
        
        // Try ID3v2 first (most common and most complete)
        foreach ($tags_to_check as $t) {
            if (isset($file_info['tags']['id3v2'][$t][0])) {
                return trim($file_info['tags']['id3v2'][$t][0]);
            }
        }
        
        // Try ID3v1
        foreach ($tags_to_check as $t) {
            if (isset($file_info['tags']['id3v1'][$t][0])) {
                return trim($file_info['tags']['id3v1'][$t][0]);
            }
        }
        
        // Try other tag formats (APE, etc)
        if (isset($file_info['tags'])) {
            foreach ($file_info['tags'] as $tag_type => $tags) {
                foreach ($tags_to_check as $t) {
                    if (isset($tags[$t][0])) {
                        return trim($tags[$t][0]);
                    }
                }
            }
        }
        
        return null;
    }
    
    /**
     * Extract year from various tag locations
     */
    private function get_year($file_info) {
        // Try standard year tag
        $year = $this->get_tag($file_info, 'year');
        if ($year) {
            // Extract 4-digit year if present
            if (preg_match('/(\d{4})/', $year, $matches)) {
                return (int) $matches[1];
            }
        }
        
        // Try recording date
        $date = $this->get_tag($file_info, 'recording_date');
        if ($date && preg_match('/(\d{4})/', $date, $matches)) {
            return (int) $matches[1];
        }
        
        // Try release date
        $date = $this->get_tag($file_info, 'release_date');
        if ($date && preg_match('/(\d{4})/', $date, $matches)) {
            return (int) $matches[1];
        }
        
        return null;
    }
    
    /**
     * Extract cover art from file
     */
    private function get_cover_art($file_info) {
        // Check for embedded pictures (ID3v2 APIC)
        if (isset($file_info['comments']['picture'])) {
            foreach ($file_info['comments']['picture'] as $picture) {
                if (isset($picture['data']) && strlen($picture['data']) > 0) {
                    return [
                        'data' => $picture['data'],
                        'mime' => $picture['image_mime'] ?? 'image/jpeg'
                    ];
                }
            }
        }
        
        // Check id3v2 directly
        if (isset($file_info['id3v2']['APIC'])) {
            foreach ($file_info['id3v2']['APIC'] as $apic) {
                if (isset($apic['data']) && strlen($apic['data']) > 0) {
                    return [
                        'data' => $apic['data'],
                        'mime' => $apic['mime'] ?? 'image/jpeg'
                    ];
                }
            }
        }
        
        // Check APE tags
        if (isset($file_info['ape']['items']['Cover Art (Front)']['data'])) {
            return [
                'data' => $file_info['ape']['items']['Cover Art (Front)']['data'],
                'mime' => 'image/jpeg'
            ];
        }
        
        return null;
    }
    
    /**
     * Return default metadata structure
     */
    private function get_default_metadata() {
        return [
            'title' => null,
            'artist' => null,
            'album' => null,
            'year' => null,
            'track_number' => null,
            'duration' => 0,
            'bitrate' => 0,
            'sample_rate' => 0,
            'cover_art' => null
        ];
    }
    
    /**
     * Generate placeholder cover art with gradient background
     * 
     * @param string $title Song/album title
     * @return array Image data and mime type
     */
    public function generate_cover_art($title) {
        // Create 500x500 image
        $width = 500;
        $height = 500;
        $image = imagecreatetruecolor($width, $height);
        
        // Random gradient colors
        $gradients = [
            ['#667eea', '#764ba2'], // Purple
            ['#f093fb', '#f5576c'], // Pink
            ['#4facfe', '#00f2fe'], // Blue
            ['#43e97b', '#38f9d7'], // Green
            ['#fa709a', '#fee140'], // Orange
            ['#30cfd0', '#330867'], // Teal
            ['#a8edea', '#fed6e3'], // Pastel
            ['#ff9a9e', '#fecfef'], // Rose
            ['#5ee7df', '#b490ca'], // Mint purple
            ['#d299c2', '#fef9d7'], // Light pink
        ];
        
        $gradient = $gradients[array_rand($gradients)];
        
        // Convert hex to RGB
        $color1 = $this->hex_to_rgb($gradient[0]);
        $color2 = $this->hex_to_rgb($gradient[1]);
        
        // Create gradient (diagonal)
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $ratio = ($x + $y) / ($width + $height);
                $r = (int) ($color1[0] + ($color2[0] - $color1[0]) * $ratio);
                $g = (int) ($color1[1] + ($color2[1] - $color1[1]) * $ratio);
                $b = (int) ($color1[2] + ($color2[2] - $color1[2]) * $ratio);
                
                $color = imagecolorallocate($image, $r, $g, $b);
                imagesetpixel($image, $x, $y, $color);
            }
        }
        
        // Add text if we have a font
        $font_path = $this->get_font_path();
        
        if ($font_path && function_exists('imagettftext')) {
            $white = imagecolorallocate($image, 255, 255, 255);
            $shadow = imagecolorallocatealpha($image, 0, 0, 0, 60);
            
            // Word wrap title
            $max_width = 420;
            $font_size = 36;
            $wrapped_text = $this->wrap_text($title, $font_size, $max_width, $font_path);
            
            // Calculate text position (centered)
            $line_height = 50;
            $total_height = count($wrapped_text) * $line_height;
            $y_start = ($height / 2) - ($total_height / 2) + $font_size;
            
            foreach ($wrapped_text as $i => $line) {
                $bbox = imagettfbbox($font_size, 0, $font_path, $line);
                $text_width = $bbox[2] - $bbox[0];
                $x_pos = ($width - $text_width) / 2;
                $y_pos = $y_start + ($i * $line_height);
                
                // Shadow
                imagettftext($image, $font_size, 0, $x_pos + 2, $y_pos + 2, $shadow, $font_path, $line);
                // Text
                imagettftext($image, $font_size, 0, $x_pos, $y_pos, $white, $font_path, $line);
            }
        } else {
            // Fallback: use built-in font
            $white = imagecolorallocate($image, 255, 255, 255);
            $text = substr($title, 0, 30);
            $font_size = 5; // Built-in font size
            $text_width = imagefontwidth($font_size) * strlen($text);
            $x = ($width - $text_width) / 2;
            $y = $height / 2;
            imagestring($image, $font_size, $x, $y, $text, $white);
        }
        
        // Add music note icon
        $this->add_music_icon($image, $width, $height);
        
        // Convert to WebP
        ob_start();
        imagewebp($image, null, 85);
        $image_data = ob_get_clean();
        imagedestroy($image);
        
        return [
            'data' => $image_data,
            'mime' => 'image/webp'
        ];
    }
    
    /**
     * Add a simple music note decoration
     */
    private function add_music_icon($image, $width, $height) {
        $white = imagecolorallocatealpha($image, 255, 255, 255, 90);
        
        // Draw simple music note at bottom right
        $x = $width - 80;
        $y = $height - 80;
        
        // Note head
        imagefilledellipse($image, $x, $y, 30, 25, $white);
        
        // Stem
        imageline($image, $x + 15, $y, $x + 15, $y - 50, $white);
        imageline($image, $x + 14, $y, $x + 14, $y - 50, $white);
        
        // Flag
        imageline($image, $x + 15, $y - 50, $x + 30, $y - 35, $white);
        imageline($image, $x + 15, $y - 45, $x + 28, $y - 32, $white);
    }
    
    /**
     * Convert hex color to RGB
     */
    private function hex_to_rgb($hex) {
        $hex = str_replace('#', '', $hex);
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2))
        ];
    }
    
    /**
     * Wrap text to fit width
     */
    private function wrap_text($text, $font_size, $max_width, $font_path) {
        $words = explode(' ', $text);
        $lines = [];
        $current_line = '';
        
        foreach ($words as $word) {
            $test_line = $current_line . ($current_line ? ' ' : '') . $word;
            $bbox = imagettfbbox($font_size, 0, $font_path, $test_line);
            $width = $bbox[2] - $bbox[0];
            
            if ($width > $max_width && $current_line) {
                $lines[] = $current_line;
                $current_line = $word;
            } else {
                $current_line = $test_line;
            }
        }
        
        if ($current_line) {
            $lines[] = $current_line;
        }
        
        return array_slice($lines, 0, 4); // Max 4 lines
    }
    
    /**
     * Get system font path
     * Returns null if no suitable font found
     */
    private function get_font_path() {
        // Check if GD has FreeType support
        if (!function_exists('imagettftext')) {
            return null;
        }
        
        // Try common font locations
        $fonts = [
            // Linux
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
            '/usr/share/fonts/TTF/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
            
            // macOS
            '/System/Library/Fonts/Helvetica.ttc',
            '/Library/Fonts/Arial.ttf',
            
            // Windows
            'C:/Windows/Fonts/arial.ttf',
            'C:/Windows/Fonts/arialbd.ttf',
            
            // Bundled font (if available)
            APPPATH . '../assets/fonts/OpenSans-Bold.ttf',
            APPPATH . '../assets/fonts/Roboto-Bold.ttf',
            APPPATH . '../assets/fonts/default.ttf'
        ];
        
        foreach ($fonts as $font) {
            if (file_exists($font) && is_readable($font)) {
                return $font;
            }
        }
        
        return null;
    }
}
