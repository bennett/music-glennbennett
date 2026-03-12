<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Share Controller - Social sharing with dynamic OG images
 */
class Share extends CI_Controller {
    
    private $fonts_path;
    
    public function __construct() {
        parent::__construct();
        $this->load->model('Song_model');
        $this->load->model('Album_model');
        $this->fonts_path = FCPATH . 'assets/fonts/';
    }
    
    /**
     * Generate share image
     * /share/image?song=ID or /share/image?album=ID
     */
    public function image() {
        try {
            $song_id = $this->input->get('song');
            $album_id = $this->input->get('album');

            $title = 'Glenn Bennett Music';
            $type_label = '';
            $cover_path = null;  // Local filesystem path

            // Get paths from config - use music_origin_path (same as Album_model)
            $this->load->config('music_config');
            $music_path = rtrim($this->config->item('music_origin_path') ?: '', '/');
            $imgs_path = $music_path . '/imgs';

            if ($song_id) {
                $song = $this->Song_model->get_song($song_id);
                if ($song) {
                    $title = $song->title;
                    $type_label = 'A Song by';

                    // Try song's own cover from filesystem
                    if (!empty($song->cover_filename)) {
                        $path = $imgs_path . '/' . $song->cover_filename;
                        if (file_exists($path)) {
                            $cover_path = $path;
                        }
                    }

                    // Fall back to album cover
                    if (!$cover_path) {
                        $this->load->database();
                        $album_link = $this->db->select('album_id')->where('song_id', $song_id)->get('album_songs')->row();
                        if ($album_link) {
                            $album = $this->Album_model->get_album($album_link->album_id);
                            if ($album) {
                                $cover_path = $this->find_album_cover_path($album, $imgs_path);
                            }
                        }
                    }
                }
            } elseif ($album_id) {
                $album = $this->Album_model->get_album($album_id);
                if ($album) {
                    $title = $album->title;
                    $type_label = 'An Album by';
                    $cover_path = $this->find_album_cover_path($album, $imgs_path);
                }
            }

            $this->generate_image($title, $type_label, $cover_path);
        } catch (\Throwable $e) {
            log_message('error', '[Share] Image generation failed: ' . $e->getMessage());
            $this->generate_fallback_image();
        }
    }
    
    /**
     * Find album cover on filesystem using flexible matching
     * This mirrors the logic in Album_model::get_cover_url()
     */
    private function find_album_cover_path($album, $imgs_path) {
        $albums_dir = $imgs_path . '/albums';
        
        if (is_dir($albums_dir)) {
            // Normalize album title: lowercase, remove spaces/underscores/dashes
            $album_normalized = strtolower(str_replace(['_', ' ', '-'], '', $album->title));
            $exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            
            $files = @scandir($albums_dir);
            if ($files) {
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') continue;
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    if (!in_array($ext, $exts)) continue;
                    
                    // Normalize filename for comparison
                    $file_normalized = strtolower(str_replace(['_', ' ', '-'], '', pathinfo($file, PATHINFO_FILENAME)));
                    
                    if ($file_normalized === $album_normalized) {
                        return $albums_dir . '/' . $file;
                    }
                }
            }
        }
        
        // Fallback to cover_filename from database
        if (!empty($album->cover_filename)) {
            $path = $imgs_path . '/' . $album->cover_filename;
            if (file_exists($path)) {
                return $path;
            }
        }
        
        return null;
    }
    
    /**
     * Load image from local filesystem
     */
    private function load_image_file($path) {
        if (!file_exists($path)) return null;
        
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                return @imagecreatefromjpeg($path);
            case 'png':
                return @imagecreatefrompng($path);
            case 'gif':
                return @imagecreatefromgif($path);
            case 'webp':
                return @imagecreatefromwebp($path);
            default:
                // Try to detect from content
                return @imagecreatefromstring(file_get_contents($path));
        }
    }
    
    /**
     * Fetch URL content using curl (kept for debug purposes)
     */
    private function fetch_url($url) {
        // Try curl first
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; GlennBennettMusic/1.0)'
            ]);
            $data = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($code == 200 && $data) {
                return $data;
            }
        }
        
        // Fallback to file_get_contents
        $ctx = stream_context_create([
            'http' => ['timeout' => 10, 'user_agent' => 'Mozilla/5.0'],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
        ]);
        return @file_get_contents($url, false, $ctx);
    }
    
    /**
     * Check if URL returns 200
     */
    private function can_fetch_url($url) {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_NOBODY => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return $code == 200;
        }
        
        $headers = @get_headers($url);
        return $headers && strpos($headers[0], '200') !== false;
    }
    
    /**
     * Generate the actual image
     * @param string $title Song/album title
     * @param string $type_label "A Song by" or "An Album by"
     * @param string $cover_path Local filesystem path to cover image
     */
    private function generate_image($title, $type_label, $cover_path) {
        // 1200x630 for Facebook
        $width = 1200;
        $height = 630;

        $img = @imagecreatetruecolor($width, $height);
        if (!$img) {
            log_message('error', '[Share] imagecreatetruecolor failed - GD may not be available');
            $this->generate_fallback_image();
            return;
        }
        imagealphablending($img, true);
        
        // Background color (dark slate)
        $bg = imagecolorallocate($img, 53, 60, 74);
        imagefill($img, 0, 0, $bg);

        // Cover area - left side, edge to edge
        $cover_area_width = 570;
        $cover_loaded = false;

        // Load cover from local filesystem
        if ($cover_path && file_exists($cover_path)) {
            $cover_img = $this->load_image_file($cover_path);
            if ($cover_img) {
                $sw = imagesx($cover_img);
                $sh = imagesy($cover_img);

                // Crop to fill the cover area (like CSS object-fit: cover)
                $ratio = $cover_area_width / $height;
                $src_ratio = $sw / $sh;

                if ($src_ratio > $ratio) {
                    $ch = $sh;
                    $cw = $sh * $ratio;
                    $cx = ($sw - $cw) / 2;
                    $cy = 0;
                } else {
                    $cw = $sw;
                    $ch = $sw / $ratio;
                    $cx = 0;
                    $cy = ($sh - $ch) / 2;
                }

                imagecopyresampled($img, $cover_img, 0, 0, $cx, $cy, $cover_area_width, $height, $cw, $ch);
                imagedestroy($cover_img);
                $cover_loaded = true;
            }
        }

        // Placeholder if no cover
        if (!$cover_loaded) {
            $ph_bg = imagecolorallocate($img, 40, 48, 58);
            imagefilledrectangle($img, 0, 0, $cover_area_width - 1, $height - 1, $ph_bg);

            // Music note icon
            $note = imagecolorallocate($img, 75, 88, 105);
            $cx = $cover_area_width / 2;
            $cy = $height / 2;
            imagesetthickness($img, 6);
            imagefilledellipse($img, $cx - 45, $cy + 50, 55, 42, $note);
            imagefilledellipse($img, $cx + 45, $cy + 25, 55, 42, $note);
            imagesetthickness($img, 8);
            imageline($img, $cx - 18, $cy + 48, $cx - 18, $cy - 60, $note);
            imageline($img, $cx + 72, $cy + 23, $cx + 72, $cy - 85, $note);
            imagesetthickness($img, 10);
            imageline($img, $cx - 18, $cy - 60, $cx + 72, $cy - 85, $note);
        }

        // Text colors
        $white = imagecolorallocate($img, 255, 255, 255);
        $gray1 = imagecolorallocate($img, 200, 205, 212);
        $gray2 = imagecolorallocate($img, 140, 148, 160);

        $tx = $cover_area_width + 55;
        $max_w = $width - $tx - 50;

        $font_headline = $this->fonts_path . 'Poppins-Bold.ttf';
        $font_r = $this->fonts_path . 'Poppins-Regular.ttf';
        $has_fonts = file_exists($font_headline) && file_exists($font_r);

        if ($has_fonts) {
            $sz_title = 50;
            $sz_type = 22;
            $sz_artist = 34;
            $sz_brand = 18;

            $lines = $this->wrap_text($title, $font_headline, $sz_title, $max_w);
            $line_h = (int)($sz_title * 1.25);

            $body_line_h = (int)($sz_artist * 1.5);
            $gap_after_title = (int)($body_line_h * 0.9);
            $gap_after_type = (int)($sz_type * 0.8);

            // Calculate total block height for vertical centering
            $title_block = count($lines) * $line_h;
            $block_h = $title_block + $gap_after_title + $sz_type + $gap_after_type + $sz_artist;

            $y = ($height - $block_h) / 2;

            // Draw title lines
            foreach ($lines as $line) {
                imagettftext($img, $sz_title, 0, $tx, $y + $sz_title, $white, $font_headline, $line);
                $y += $line_h;
            }

            // Gap after title
            $y += $gap_after_title;

            // "A Song by"
            if ($type_label) {
                imagettftext($img, $sz_type, 0, $tx, $y + $sz_type, $gray2, $font_r, $type_label);
                $y += $sz_type + $gap_after_type;
            }

            // Artist name
            imagettftext($img, $sz_artist, 0, $tx, $y + $sz_artist, $gray1, $font_r, 'Glenn L. Bennett');

            // Branding at bottom right, right-aligned
            $brand1 = 'Glenn Bennett Music';
            $brand2 = 'glennbennett.com';
            $b1_box = imagettfbbox($sz_brand, 0, $font_r, $brand1);
            $b2_box = imagettfbbox($sz_brand - 4, 0, $font_r, $brand2);
            $b1_w = abs($b1_box[4] - $b1_box[0]);
            $b2_w = abs($b2_box[4] - $b2_box[0]);
            $right_edge = $width - 40;
            imagettftext($img, $sz_brand, 0, $right_edge - $b1_w, $height - 58, $gray2, $font_r, $brand1);
            imagettftext($img, $sz_brand - 4, 0, $right_edge - $b2_w, $height - 34, $gray2, $font_r, $brand2);
        } else {
            $y = $height / 2 - 60;
            imagestring($img, 5, $tx, $y, $title, $white);
            $y += 50;
            if ($type_label) {
                imagestring($img, 3, $tx, $y, $type_label, $gray2);
                $y += 28;
            }
            imagestring($img, 4, $tx, $y, 'Glenn L. Bennett', $gray1);
            imagestring($img, 3, $tx, $height - 45, 'Glenn Bennett Music', $gray2);
        }
        
        header('Content-Type: image/jpeg');
        header('Cache-Control: max-age=3600');
        imagejpeg($img, null, 90);
        imagedestroy($img);
        exit;
    }
    
    private function wrap_text($text, $font, $size, $max_w) {
        // Check if it fits on one line
        $box = imagettfbbox($size, 0, $font, $text);
        if (abs($box[4] - $box[0]) <= $max_w) {
            return [$text];
        }

        // Needs wrapping — prefer shorter first line so second line is longer
        $words = explode(' ', $text);
        if (count($words) <= 1) return [$text];

        $best_break = 1; // default: break after first word
        $best_diff = PHP_INT_MAX;

        for ($i = 1; $i < count($words); $i++) {
            $line1 = implode(' ', array_slice($words, 0, $i));
            $line2 = implode(' ', array_slice($words, $i));

            $w1 = abs(imagettfbbox($size, 0, $font, $line1)[4] - imagettfbbox($size, 0, $font, $line1)[0]);
            $w2 = abs(imagettfbbox($size, 0, $font, $line2)[4] - imagettfbbox($size, 0, $font, $line2)[0]);

            // Both lines must fit
            if ($w1 > $max_w || $w2 > $max_w) continue;

            // Prefer line2 >= line1 (longer second line)
            // Among valid options, pick where line2 is longest
            if ($w2 >= $w1 && $w2 > ($max_w - $best_diff)) {
                $best_break = $i;
                $best_diff = $max_w - $w2;
            }
        }

        $line1 = implode(' ', array_slice($words, 0, $best_break));
        $line2 = implode(' ', array_slice($words, $best_break));

        // If line2 is still too long, fall back to greedy wrap
        $w2 = abs(imagettfbbox($size, 0, $font, $line2)[4] - imagettfbbox($size, 0, $font, $line2)[0]);
        if ($w2 > $max_w) {
            // Greedy fallback
            $lines = [];
            $line = '';
            foreach ($words as $word) {
                $test = $line ? "$line $word" : $word;
                $bx = imagettfbbox($size, 0, $font, $test);
                if (abs($bx[4] - $bx[0]) > $max_w && $line) {
                    $lines[] = $line;
                    $line = $word;
                } else {
                    $line = $test;
                }
            }
            if ($line) $lines[] = $line;
            return array_slice($lines, 0, 2);
        }

        return [$line1, $line2];
    }
    
    private function sparkle($img, $x, $y, $c) {
        imagesetthickness($img, 2);
        imageline($img, $x, $y - 8, $x, $y + 8, $c);
        imageline($img, $x - 8, $y, $x + 8, $y, $c);
        imageline($img, $x - 5, $y - 5, $x + 5, $y + 5, $c);
        imageline($img, $x + 5, $y - 5, $x - 5, $y + 5, $c);
    }
    
    /**
     * Simple fallback image when generation fails
     */
    private function generate_fallback_image() {
        $img = @imagecreatetruecolor(1200, 630);
        if (!$img) {
            // GD completely unavailable - return a 1x1 pixel JPEG
            header('Content-Type: image/jpeg');
            header('Cache-Control: max-age=60');
            echo base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/xAAUAQEAAAAAAAAAAAAAAAAAAAAA/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAwDAQACEQMRAD8AKwA//9k=');
            exit;
        }
        $bg = imagecolorallocate($img, 53, 60, 74);
        imagefill($img, 0, 0, $bg);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagestring($img, 5, 480, 290, 'Glenn Bennett Music', $white);
        header('Content-Type: image/jpeg');
        header('Cache-Control: max-age=60');
        imagejpeg($img, null, 90);
        imagedestroy($img);
        exit;
    }

    public function song($song_id) {
        $song = $this->Song_model->get_song($song_id);
        if (!$song) { redirect('/player'); return; }
        $this->load->view('share/og_redirect', [
            'og_title' => $song->title . ' - Glenn Bennett',
            'og_description' => 'Listen to "' . $song->title . '" by Glenn L. Bennett',
            'og_image' => base_url('share/image?song=' . $song_id),
            'og_url' => base_url('?song=' . $song_id),
            'redirect_url' => base_url('?song=' . $song_id)
        ]);
    }
    
    public function album($album_id) {
        $album = $this->Album_model->get_album($album_id);
        if (!$album) { redirect('/player'); return; }
        $this->load->view('share/og_redirect', [
            'og_title' => $album->title . ' - Glenn Bennett',
            'og_description' => 'Listen to "' . $album->title . '" by Glenn L. Bennett',
            'og_image' => base_url('share/image?album=' . $album_id),
            'og_url' => base_url('?album=' . $album_id),
            'redirect_url' => base_url('?album=' . $album_id)
        ]);
    }
    
    public function debug($song_id = null) {
        echo "<h2>Share Debug</h2>";
        if (!$song_id) { echo "<p>/share/debug/SONG_ID</p>"; return; }
        
        echo "<h3>Step 1: Get raw song from DB</h3>";
        $this->load->database();
        $raw_song = $this->db->get_where('songs', ['id' => $song_id])->row();
        if (!$raw_song) { echo "<p>❌ Song ID $song_id not found in database</p>"; return; }
        
        echo "<p><b>Title:</b> " . htmlspecialchars($raw_song->title) . "</p>";
        echo "<p><b>cover_filename (raw):</b> " . htmlspecialchars($raw_song->cover_filename ?: 'NULL/empty') . "</p>";
        
        echo "<h3>Step 2: Song_model->get_song($song_id)</h3>";
        $song = $this->Song_model->get_song($song_id);
        echo "<p><b>cover_url after get_song:</b> " . htmlspecialchars($song->cover_url ?: 'NULL') . "</p>";
        echo "<p><b>stream_url:</b> " . htmlspecialchars($song->stream_url ?: 'NULL') . "</p>";
        
        echo "<h3>Step 3: Check album link</h3>";
        $album_link = $this->db->select('album_id')->where('song_id', $song_id)->get('album_songs')->row();
        if (!$album_link) {
            echo "<p>❌ Song not linked to any album</p>";
        } else {
            echo "<p>✅ Linked to album_id: " . $album_link->album_id . "</p>";
            
            echo "<h3>Step 4: Get album</h3>";
            $album = $this->Album_model->get_album($album_link->album_id);
            echo "<p><b>Album title:</b> " . htmlspecialchars($album->title) . "</p>";
            echo "<p><b>Album cover_url:</b> " . htmlspecialchars($album->cover_url ?: 'NULL') . "</p>";
            
            if ($album->cover_url) {
                echo "<p><b>Album cover preview:</b><br><img src='" . htmlspecialchars($album->cover_url) . "' style='max-width:150px'></p>";
                
                // Test fetching album cover
                if (function_exists('curl_init')) {
                    $ch = curl_init($album->cover_url);
                    curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_TIMEOUT => 5, CURLOPT_SSL_VERIFYPEER => false]);
                    curl_exec($ch);
                    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    echo "<p><b>Album cover curl test:</b> HTTP $code " . ($code == 200 ? "✅" : "❌") . "</p>";
                }
            }
        }
        
        echo "<h3>Step 5: Song_model->get_song_with_cover($song_id)</h3>";
        $song_with_cover = $this->Song_model->get_song_with_cover($song_id);
        echo "<p><b>Final cover_url:</b> " . htmlspecialchars($song_with_cover->cover_url ?: 'NULL') . "</p>";
        
        if (!empty($song_with_cover->album_title)) {
            echo "<p><b>Cover came from album:</b> " . htmlspecialchars($song_with_cover->album_title) . "</p>";
        }
        
        if ($song_with_cover->cover_url) {
            echo "<h3>Step 6: Test fetching cover</h3>";
            echo "<p><b>URL:</b> " . htmlspecialchars($song_with_cover->cover_url) . "</p>";
            
            // Test with curl
            if (function_exists('curl_init')) {
                $ch = curl_init($song_with_cover->cover_url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_SSL_VERIFYPEER => false
                ]);
                $data = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                
                echo "<p><b>Curl HTTP code:</b> $code</p>";
                if ($error) {
                    echo "<p><b>Curl error:</b> $error</p>";
                }
                if ($code == 200 && $data) {
                    echo "<p>✅ Curl fetched " . strlen($data) . " bytes</p>";
                } else {
                    echo "<p>❌ Curl failed</p>";
                }
            } else {
                echo "<p>⚠️ Curl not available</p>";
            }
            
            // Test with file_get_contents
            $data = @file_get_contents($song_with_cover->cover_url);
            if ($data) {
                echo "<p>✅ file_get_contents fetched " . strlen($data) . " bytes</p>";
            } else {
                echo "<p>❌ file_get_contents failed: " . (error_get_last()['message'] ?? 'Unknown') . "</p>";
            }
            
            echo "<p><b>Direct image test:</b><br><img src='" . htmlspecialchars($song_with_cover->cover_url) . "' style='max-width:200px' onerror=\"this.alt='Failed to load'\"></p>";
        } else {
            echo "<h3>❌ No cover URL available - placeholder will be shown</h3>";
        }
        
        echo "<hr><h3>Generated Share Image</h3>";
        echo "<p><img src='/share/image?song=$song_id' style='max-width:600px;border:1px solid #ccc'></p>";
    }
    
    public function fonts() {
        echo "<h2>Fonts</h2>";
        echo "<p>Path: " . $this->fonts_path . "</p>";
        foreach (['PlayfairDisplay-Black.ttf', 'Montserrat-Regular.ttf'] as $f) {
            $p = $this->fonts_path . $f;
            echo "<p>$f: " . (file_exists($p) ? "✅ " . filesize($p) . " bytes" : "❌ Missing") . "</p>";
        }
        echo "<h3>To install fonts:</h3>";
        echo "<ol>";
        echo "<li><a href='https://fonts.google.com/specimen/Playfair+Display'>Download Playfair Display</a> → PlayfairDisplay-Black.ttf</li>";
        echo "<li><a href='https://fonts.google.com/specimen/Montserrat'>Download Montserrat</a> → Montserrat-Regular.ttf</li>";
        echo "<li>Upload to: /assets/fonts/</li>";
        echo "</ol>";
    }
    
    /**
     * Debug the image generation process
     */
    public function test_image($song_id = 2) {
        echo "<h2>Share Image Debug (song_id=$song_id)</h2>";
        
        // Get config
        $this->load->config('music_config');
        $music_path = rtrim($this->config->item('music_origin_path') ?: '', '/');
        $imgs_path = $music_path . '/imgs';
        
        echo "<h3>Configuration</h3>";
        echo "<table border='1' cellpadding='8'>";
        echo "<tr><td><b>music_origin_path</b></td><td>" . htmlspecialchars($music_path) . "</td></tr>";
        echo "<tr><td><b>imgs_path</b></td><td>" . htmlspecialchars($imgs_path) . "</td></tr>";
        echo "<tr><td><b>albums_dir</b></td><td>" . htmlspecialchars($imgs_path . '/albums') . "</td></tr>";
        echo "<tr><td><b>music_origin_path exists?</b></td><td>" . (is_dir($music_path) ? "✅ Yes" : "❌ No") . "</td></tr>";
        echo "<tr><td><b>imgs_path exists?</b></td><td>" . (is_dir($imgs_path) ? "✅ Yes" : "❌ No") . "</td></tr>";
        echo "<tr><td><b>albums dir exists?</b></td><td>" . (is_dir($imgs_path . '/albums') ? "✅ Yes" : "❌ No") . "</td></tr>";
        echo "</table>";
        
        // List files in albums directory
        $albums_dir = $imgs_path . '/albums';
        if (is_dir($albums_dir)) {
            echo "<h4>Files in albums directory:</h4><ul>";
            $files = scandir($albums_dir);
            foreach ($files as $f) {
                if ($f === '.' || $f === '..') continue;
                echo "<li>" . htmlspecialchars($f) . "</li>";
            }
            echo "</ul>";
        }
        
        // Get song
        $song = $this->Song_model->get_song($song_id);
        if (!$song) { echo "<p>❌ Song ID $song_id not found</p>"; return; }
        
        echo "<h3>Song Info</h3>";
        echo "<p><b>Title:</b> " . htmlspecialchars($song->title) . "</p>";
        echo "<p><b>cover_filename:</b> " . htmlspecialchars($song->cover_filename ?: 'NULL') . "</p>";
        
        // Check song cover path
        $cover_path = null;
        if (!empty($song->cover_filename)) {
            $path = $imgs_path . '/' . $song->cover_filename;
            echo "<p><b>Song cover path:</b> " . htmlspecialchars($path) . "</p>";
            echo "<p><b>File exists:</b> " . (file_exists($path) ? "✅ Yes (" . filesize($path) . " bytes)" : "❌ No") . "</p>";
            if (file_exists($path)) {
                $cover_path = $path;
            }
        }
        
        // Check album cover
        $this->load->database();
        $album_link = $this->db->select('album_id')->where('song_id', $song_id)->get('album_songs')->row();
        if ($album_link) {
            $album = $this->Album_model->get_album($album_link->album_id);
            echo "<h3>Album Info</h3>";
            echo "<p><b>Album:</b> " . htmlspecialchars($album->title) . "</p>";
            echo "<p><b>Album normalized:</b> " . strtolower(str_replace(['_', ' ', '-'], '', $album->title)) . "</p>";
            
            $album_cover_path = $this->find_album_cover_path($album, $imgs_path);
            echo "<p><b>Album cover path:</b> " . htmlspecialchars($album_cover_path ?: 'NOT FOUND') . "</p>";
            if ($album_cover_path) {
                echo "<p><b>Album cover exists:</b> " . (file_exists($album_cover_path) ? "✅ Yes (" . filesize($album_cover_path) . " bytes)" : "❌ No") . "</p>";
            }
            
            if (!$cover_path && $album_cover_path) {
                $cover_path = $album_cover_path;
                echo "<p><b>Using:</b> Album cover (song cover not found)</p>";
            }
        }
        
        if (!$cover_path) {
            echo "<h3>❌ No cover found - placeholder will be used</h3>";
            echo "<p>Check that:</p><ul>";
            echo "<li>music_origin_path is set correctly in config</li>";
            echo "<li>/imgs/albums/ directory exists</li>";
            echo "<li>Album cover file exists with matching name</li>";
            echo "</ul>";
        } else {
            echo "<h3>Loading cover: " . htmlspecialchars($cover_path) . "</h3>";
            
            // Test loading image
            $cover_img = $this->load_image_file($cover_path);
            if (!$cover_img) {
                echo "<p>❌ Failed to load image with GD</p>";
                
                // Check GD support
                echo "<h4>GD Info:</h4>";
                $gd = gd_info();
                echo "<p>WebP Support: " . ($gd['WebP Support'] ? "✅" : "❌") . "</p>";
                echo "<p>JPEG Support: " . ($gd['JPEG Support'] ? "✅" : "❌") . "</p>";
                echo "<p>PNG Support: " . ($gd['PNG Support'] ? "✅" : "❌") . "</p>";
            } else {
                $sw = imagesx($cover_img);
                $sh = imagesy($cover_img);
                echo "<p>✅ Loaded image: {$sw}x{$sh}</p>";
                imagedestroy($cover_img);
            }
        }
        
        // Check fonts
        echo "<h3>Fonts</h3>";
        $font_headline = $this->fonts_path . 'PlayfairDisplay-Black.ttf';
        $font_r = $this->fonts_path . 'Montserrat-Regular.ttf';
        echo "<p>PlayfairDisplay-Black.ttf: " . (file_exists($font_headline) ? "✅" : "❌ Missing") . "</p>";
        echo "<p>Montserrat-Regular.ttf: " . (file_exists($font_r) ? "✅" : "❌ Missing") . "</p>";
        
        // Show generated image
        echo "<hr><h3>Generated Share Image</h3>";
        echo "<p><img src='/share/image?song=$song_id' style='max-width:600px;border:1px solid #ccc'></p>";
        
        // Facebook debug link
        echo "<h3>Facebook Debugger</h3>";
        $share_url = base_url('share/song/' . $song_id);
        echo "<p>Share URL: <a href='$share_url'>$share_url</a></p>";
        echo "<p><a href='https://developers.facebook.com/tools/debug/?q=" . urlencode($share_url) . "' target='_blank'>Open Facebook Sharing Debugger →</a></p>";
    }
}
