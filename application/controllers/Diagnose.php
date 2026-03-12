<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Diagnose extends CI_Controller {
    
    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->load->config('music_config');
    }
    
    public function index() {
        $html = $this->build_page();
        $this->output->set_output($html);
    }
    
    private function build_page() {
        $music_path = $this->config->item('music_origin_path') ?: '/home/tsgimh/glennbennett.com/songs';
        $cdn_url = $this->config->item('music_cdn_url') ?: '';
        $cover_path = $this->config->item('cover_art_path') ?: FCPATH . 'uploads/covers/';
        $min_songs = $this->config->item('min_songs_per_album') ?: 4;
        $root_only = $this->config->item('scan_root_only') ?: false;
        $exclude_dirs = $this->config->item('exclude_directories') ?: ['org'];
        
        ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Music Player Diagnostics</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .card { background: white; padding: 20px; margin: 15px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: #4CAF50; }
        .error { color: #f44336; }
        h1 { color: #667eea; }
        h2 { color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #667eea; color: white; }
        button { background: #667eea; color: white; padding: 10px 20px; border: none; cursor: pointer; border-radius: 5px; margin: 5px; }
        button:hover { background: #5a6fd6; }
        button.success { background: #4CAF50; }
        .fix-form { background: #e8f5e9; padding: 15px; border-radius: 5px; margin-top: 10px; }
        code { background: #eee; padding: 2px 6px; border-radius: 3px; }
        .file-list { max-height: 300px; overflow-y: auto; background: #f9f9f9; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>🔧 Music Player Diagnostics</h1>
    
    <div class="card" style="background: #fff3cd; border-left: 4px solid #ffc107;">
        <strong>⚠️ Security Warning:</strong> Delete this controller after use! (application/controllers/Diagnose.php)
    </div>

    <!-- 1. DATABASE -->
    <div class="card">
        <h2>1. Database Check</h2>
        <?php
        try {
            $tables = $this->db->list_tables();
            echo '<p class="success">✅ Database connected</p>';
            echo '<p><strong>Tables:</strong> ' . implode(', ', $tables) . '</p>';
            
            // Check if songs table has cover_filename column
            $fields = $this->db->list_fields('songs');
            if (!in_array('cover_filename', $fields)) {
                echo '<p class="error">⚠️ songs table missing cover_filename column</p>';
                if ($this->input->post('add_song_cover_column')) {
                    $this->db->query("ALTER TABLE songs ADD COLUMN cover_filename TEXT");
                    echo '<p class="success">✅ Added cover_filename column to songs table. Refresh the page.</p>';
                } else {
                    echo '<form method="POST"><button type="submit" name="add_song_cover_column" value="1" class="success">Add cover_filename column</button></form>';
                }
            } else {
                echo '<p class="success">✅ songs.cover_filename column exists</p>';
            }
        } catch (Exception $e) {
            echo '<p class="error">❌ Database error: ' . $e->getMessage() . '</p>';
        }
        ?>
    </div>

    <!-- 2. USERS -->
    <div class="card">
        <h2>2. Users Check</h2>
        <?php
        $users = $this->db->get('users')->result();
        if ($users) {
            echo '<table><tr><th>ID</th><th>Username</th><th>Admin</th><th>Pwd Length</th></tr>';
            foreach ($users as $u) {
                echo '<tr><td>'.$u->id.'</td><td>'.htmlspecialchars($u->username).'</td>';
                echo '<td>'.($u->is_admin?'✅':'❌').'</td><td>'.strlen($u->password).'</td></tr>';
            }
            echo '</table>';
        } else {
            echo '<p class="error">❌ No users found</p>';
        }
        ?>
    </div>

    <!-- 3. TEST LOGIN -->
    <div class="card">
        <h2>3. Test Login</h2>
        <?php
        $admin = $this->db->get_where('users', ['username' => 'admin'])->row();
        if ($admin) {
            $test_pass = '2276#midi';
            if (password_verify($test_pass, $admin->password)) {
                echo '<p class="success">✅ Password "' . $test_pass . '" works!</p>';
            } else {
                echo '<p class="error">❌ Password verification failed</p>';
            }
        } else {
            echo '<p class="error">❌ Admin user not found</p>';
        }
        ?>
    </div>

    <!-- 4. RESET ADMIN -->
    <div class="card">
        <h2>4. Reset Admin User</h2>
        <?php
        if ($this->input->post('reset_admin')) {
            $hash = password_hash('2276#midi', PASSWORD_DEFAULT);
            $this->db->delete('users', ['username' => 'admin']);
            $this->db->insert('users', [
                'username' => 'admin',
                'email' => 'gbennett@tsgdev.com',
                'password' => $hash,
                'is_admin' => 1,
                'is_verified' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            echo '<p class="success">✅ Admin reset! Username: admin / Password: 2276#midi</p>';
            echo '<p><a href="'.site_url('auth/login').'"><button>Try Login</button></a></p>';
        } else {
            echo '<form method="POST"><button type="submit" name="reset_admin" value="1">Reset Admin (admin / 2276#midi)</button></form>';
        }
        ?>
    </div>

    <!-- 5. SCANNER -->
    <div class="card">
        <h2>5. Scanner Diagnostics</h2>
        
        <h3>5.1 Configuration</h3>
        <table>
            <tr><th>Setting</th><th>Value</th><th>Status</th></tr>
            <tr>
                <td>music_origin_path</td>
                <td><code><?php echo htmlspecialchars($music_path); ?></code></td>
                <td class="<?php echo is_dir($music_path) && is_readable($music_path) ? 'success' : 'error'; ?>">
                    <?php echo is_dir($music_path) ? (is_readable($music_path) ? '✅ OK' : '❌ Not readable') : '❌ Not found'; ?>
                </td>
            </tr>
            <tr>
                <td>music_cdn_url</td>
                <td><code><?php echo htmlspecialchars($cdn_url); ?></code></td>
                <td>ℹ️</td>
            </tr>
            <tr>
                <td>cover_art_path</td>
                <td><code><?php echo htmlspecialchars($cover_path); ?></code></td>
                <td class="<?php echo is_dir($cover_path) && is_writable($cover_path) ? 'success' : 'error'; ?>">
                    <?php echo is_dir($cover_path) ? (is_writable($cover_path) ? '✅ OK' : '❌ Not writable') : '❌ Not found'; ?>
                </td>
            </tr>
            <tr><td>min_songs_per_album</td><td><?php echo $min_songs; ?></td><td>ℹ️</td></tr>
            <tr><td>exclude_directories</td><td><?php echo implode(', ', $exclude_dirs); ?></td><td>ℹ️</td></tr>
        </table>

        <h3>5.2 getID3 Library</h3>
        <?php
        $getid3_path = APPPATH . 'third_party/getid3/getid3.php';
        if (file_exists($getid3_path)) {
            echo '<p class="success">✅ getID3 found</p>';
            require_once($getid3_path);
            if (class_exists('getID3')) {
                echo '<p class="success">✅ getID3 class loaded</p>';
            }
        } else {
            echo '<p class="error">❌ getID3 NOT FOUND at: ' . $getid3_path . '</p>';
            echo '<p>Download from <a href="https://github.com/JamesHeinrich/getID3">GitHub</a> and extract to application/third_party/getid3/</p>';
        }
        ?>

        <h3>5.3 Music Directory</h3>
        <?php
        $audio_files = [];
        if (is_dir($music_path) && is_readable($music_path)) {
            if ($root_only) {
                foreach (scandir($music_path) as $item) {
                    if ($item === '.' || $item === '..') continue;
                    $path = $music_path . '/' . $item;
                    if (!is_dir($path)) {
                        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                        if (in_array($ext, ['mp3','m4a','flac','ogg','wav'])) $audio_files[] = $item;
                    }
                }
            } else {
                $audio_files = $this->scan_dir($music_path, $music_path, ['mp3','m4a','flac','ogg','wav'], $exclude_dirs);
            }
            echo '<p><strong>Audio files found:</strong> ' . count($audio_files) . '</p>';
            if (count($audio_files) > 0) {
                echo '<div class="file-list"><ul>';
                foreach (array_slice($audio_files, 0, 30) as $f) {
                    echo '<li>' . htmlspecialchars($f) . '</li>';
                }
                if (count($audio_files) > 30) echo '<li><em>... and ' . (count($audio_files) - 30) . ' more</em></li>';
                echo '</ul></div>';
            }
        } else {
            echo '<p class="error">❌ Cannot read music directory</p>';
        }
        ?>

        <h3>5.4 Cover Art (imgs directory)</h3>
        <?php
        $imgs_dir = $music_path . '/imgs';
        if (is_dir($imgs_dir)) {
            echo '<p class="success">✅ imgs directory found: <code>' . $imgs_dir . '</code></p>';
            $img_files = @scandir($imgs_dir);
            if ($img_files) {
                $img_files = array_filter($img_files, function($f) {
                    return !in_array($f, ['.', '..']) && in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), ['jpg','jpeg','png','webp','gif']);
                });
                echo '<p>Found ' . count($img_files) . ' images:</p>';
                echo '<div class="file-list"><ul>';
                foreach (array_slice(array_values($img_files), 0, 20) as $f) {
                    echo '<li>' . htmlspecialchars($f) . '</li>';
                }
                if (count($img_files) > 20) echo '<li><em>... and ' . (count($img_files) - 20) . ' more</em></li>';
                echo '</ul></div>';
            }
        } else {
            echo '<p class="error">❌ imgs directory not found at: <code>' . $imgs_dir . '</code></p>';
        }
        
        // Test cover art matching for albums in database
        $test_albums = $this->db->limit(5)->get('albums')->result();
        if ($test_albums && is_dir($imgs_dir)) {
            echo '<h4>Cover Art Matching Test:</h4>';
            echo '<table><tr><th>Album</th><th>Looking For</th><th>Found</th><th>Preview</th></tr>';
            foreach ($test_albums as $album) {
                $album_filename = str_replace(' ', '_', $album->title);
                $found_file = null;
                $exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                
                // Check exact match
                foreach ($exts as $ext) {
                    $test_path = $imgs_dir . '/' . $album_filename . '.' . $ext;
                    if (file_exists($test_path)) {
                        $found_file = $album_filename . '.' . $ext;
                        break;
                    }
                    // Try lowercase
                    $test_path = $imgs_dir . '/' . strtolower($album_filename) . '.' . $ext;
                    if (file_exists($test_path)) {
                        $found_file = strtolower($album_filename) . '.' . $ext;
                        break;
                    }
                }
                
                // If not found, scan for partial match
                if (!$found_file && isset($img_files)) {
                    $album_lower = strtolower($album_filename);
                    foreach ($img_files as $f) {
                        $basename = strtolower(pathinfo($f, PATHINFO_FILENAME));
                        if ($basename === $album_lower || strpos($basename, $album_lower) !== false) {
                            $found_file = $f;
                            break;
                        }
                    }
                }
                
                echo '<tr>';
                echo '<td>' . htmlspecialchars($album->title) . '</td>';
                echo '<td><code>' . htmlspecialchars($album_filename) . '.*</code></td>';
                echo '<td class="' . ($found_file ? 'success' : 'error') . '">' . ($found_file ? '✅ ' . htmlspecialchars($found_file) : '❌ Not found') . '</td>';
                if ($found_file) {
                    $img_url = $cdn_url . '/imgs/' . $found_file;
                    echo '<td><img src="' . htmlspecialchars($img_url) . '" style="max-width:80px;max-height:80px;border-radius:4px;"></td>';
                } else {
                    echo '<td>-</td>';
                }
                echo '</tr>';
            }
            echo '</table>';
        }
        ?>

        <?php if (file_exists($getid3_path) && count($audio_files) > 0): ?>
        <h3>5.5 Test Metadata (First 10 Songs)</h3>
        <?php
        $getID3 = new getID3;
        echo '<table><tr><th>File</th><th>Title</th><th>Artist</th><th>Album</th><th>Year</th><th>Track</th><th>Duration</th><th>Cover</th></tr>';
        
        foreach (array_slice($audio_files, 0, 10) as $audio_file) {
            $test_file = $music_path . '/' . $audio_file;
            try {
                $info = $getID3->analyze($test_file);
                $title = $info['tags']['id3v2']['title'][0] ?? $info['tags']['id3v1']['title'][0] ?? '-';
                $artist = $info['tags']['id3v2']['artist'][0] ?? $info['tags']['id3v1']['artist'][0] ?? '-';
                $album = $info['tags']['id3v2']['album'][0] ?? $info['tags']['id3v1']['album'][0] ?? '-';
                $year = $info['tags']['id3v2']['year'][0] ?? $info['tags']['id3v1']['year'][0] ?? '-';
                $track = $info['tags']['id3v2']['track_number'][0] ?? '-';
                $duration = isset($info['playtime_seconds']) ? gmdate('i:s', (int)$info['playtime_seconds']) : '-';
                
                // Check for embedded cover
                $has_cover = false;
                $cover_data = null;
                $cover_mime = 'image/jpeg';
                
                if (isset($info['comments']['picture'][0]['data'])) {
                    $has_cover = true;
                    $cover_data = $info['comments']['picture'][0]['data'];
                    $cover_mime = $info['comments']['picture'][0]['image_mime'] ?? 'image/jpeg';
                } elseif (isset($info['id3v2']['APIC'][0]['data'])) {
                    $has_cover = true;
                    $cover_data = $info['id3v2']['APIC'][0]['data'];
                    $cover_mime = $info['id3v2']['APIC'][0]['image_mime'] ?? 'image/jpeg';
                }
                
                echo '<tr>';
                echo '<td><small>' . htmlspecialchars(basename($audio_file)) . '</small></td>';
                echo '<td>' . htmlspecialchars($title) . '</td>';
                echo '<td>' . htmlspecialchars($artist) . '</td>';
                echo '<td>' . htmlspecialchars($album) . '</td>';
                echo '<td>' . htmlspecialchars($year) . '</td>';
                echo '<td>' . htmlspecialchars($track) . '</td>';
                echo '<td>' . $duration . '</td>';
                if ($has_cover && $cover_data) {
                    $base64 = base64_encode($cover_data);
                    echo '<td><img src="data:' . $cover_mime . ';base64,' . $base64 . '" style="max-width:60px;max-height:60px;border-radius:4px;"></td>';
                } else {
                    echo '<td class="error">❌</td>';
                }
                echo '</tr>';
            } catch (Exception $e) {
                echo '<tr><td colspan="8" class="error">Error: ' . htmlspecialchars(basename($audio_file)) . ' - ' . $e->getMessage() . '</td></tr>';
            }
        }
        echo '</table>';
        
        if (count($audio_files) > 10) {
            echo '<p><em>Showing first 10 of ' . count($audio_files) . ' files</em></p>';
        }
        ?>
        <?php endif; ?>

        <h3>5.6 Run Manual Scan</h3>
        <?php
        if ($this->input->post('run_scan')) {
            echo '<div style="background:#e3f2fd;padding:15px;border-radius:5px;">';
            $stats = $this->run_scan($music_path, $audio_files, $min_songs, $exclude_dirs);
            echo '<p class="success">✅ Scan complete!</p>';
            echo '<ul>';
            echo '<li>Songs processed: ' . $stats['songs'] . '</li>';
            echo '<li>Albums created: ' . $stats['albums_created'] . '</li>';
            echo '<li>Misc songs: ' . $stats['misc'] . '</li>';
            echo '<li>Errors: ' . $stats['errors'] . '</li>';
            echo '</ul></div>';
        }
        ?>
        <form method="POST"><button type="submit" name="run_scan" value="1" class="success">🔍 Run Manual Scan</button></form>
    </div>

    <!-- 6. DATABASE CONTENTS -->
    <div class="card">
        <h2>6. Database Contents</h2>
        <?php
        $album_count = $this->db->count_all('albums');
        $song_count = $this->db->count_all('songs');
        $linked = $this->db->query("SELECT COUNT(DISTINCT song_id) as c FROM album_songs")->row()->c;
        echo '<p>Albums: <strong>' . $album_count . '</strong> | Songs: <strong>' . $song_count . '</strong> | In albums: <strong>' . $linked . '</strong> | Misc: <strong>' . ($song_count - $linked) . '</strong></p>';
        
        if ($album_count > 0) {
            $albums = $this->db->query("SELECT a.*, (SELECT COUNT(*) FROM album_songs WHERE album_id=a.id) as cnt FROM albums a ORDER BY title")->result();
            
            foreach ($albums as $album) {
                echo '<div style="background:#f9f9f9; padding:15px; margin:15px 0; border-radius:8px; border-left:4px solid #667eea;">';
                
                // Album header with cover
                echo '<div style="display:flex; gap:15px; align-items:flex-start;">';
                
                // Handle clear cover action
                if ($this->input->post('clear_cover') == $album->id) {
                    $this->db->where('id', $album->id)->update('albums', ['cover_filename' => null]);
                    $album->cover_filename = null;
                    echo '<p class="success" style="color:#4CAF50;">✅ Cover cleared for ' . htmlspecialchars($album->title) . '</p>';
                }
                
                // Try to find cover - prioritize imgs directory over database
                $cover_url = null;
                $album_normalized = strtolower(str_replace(['_', ' ', '-'], '', $album->title));
                $found_file = null;
                
                // Scan imgs directory for a flexible match
                $imgs_dir = $music_path . '/imgs';
                if (is_dir($imgs_dir)) {
                    $files = @scandir($imgs_dir);
                    if ($files) {
                        foreach ($files as $file) {
                            if ($file === '.' || $file === '..') continue;
                            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                            if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) continue;
                            
                            $file_basename = pathinfo($file, PATHINFO_FILENAME);
                            $file_normalized = strtolower(str_replace(['_', ' ', '-'], '', $file_basename));
                            
                            if ($file_normalized === $album_normalized) {
                                $cover_url = $cdn_url . '/imgs/' . $file;
                                $found_file = $file;
                                break;
                            }
                        }
                    }
                }
                
                // Only fall back to database cover if no imgs/ cover found
                if (!$cover_url && $album->cover_filename) {
                    $cover_url = rtrim($this->config->item('cover_art_url'), '/') . '/' . $album->cover_filename;
                }
                
                if ($cover_url) {
                    echo '<img src="' . htmlspecialchars($cover_url) . '" style="width:100px;height:100px;object-fit:cover;border-radius:8px;">';
                } else {
                    echo '<div style="width:100px;height:100px;background:#ddd;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#999;">No Cover</div>';
                }
                
                echo '<div>';
                echo '<h3 style="margin:0 0 5px 0;">' . htmlspecialchars($album->title) . '</h3>';
                echo '<p style="margin:0;color:#666;">' . htmlspecialchars($album->artist ?? 'Unknown Artist') . ' • ' . ($album->year ?? 'No Year') . ' • ' . $album->cnt . ' songs</p>';
                echo '<p style="margin:5px 0 0 0;font-size:12px;color:#999;">ID: ' . $album->id . ' | DB Cover: ' . ($album->cover_filename ?: 'None') . '</p>';
                if ($found_file) {
                    echo '<p style="margin:2px 0 0 0;font-size:12px;color:#4CAF50;">✅ Found: imgs/' . htmlspecialchars($found_file) . '</p>';
                } else {
                    echo '<p style="margin:2px 0 0 0;font-size:12px;color:#ff9800;">⚠️ No match in imgs/ (looking for: ' . htmlspecialchars($album->title) . ')</p>';
                }
                if ($album->cover_filename) {
                    echo '<form method="POST" style="margin-top:5px;"><button type="submit" name="clear_cover" value="' . $album->id . '" style="background:#f44336;padding:4px 8px;font-size:11px;">Clear DB Cover</button></form>';
                }
                echo '</div>';
                echo '</div>';
                
                // Songs in album
                $songs = $this->db->query("
                    SELECT s.*, als.track_number 
                    FROM songs s 
                    JOIN album_songs als ON als.song_id = s.id 
                    WHERE als.album_id = " . (int)$album->id . " 
                    ORDER BY als.track_number ASC, s.title ASC
                ")->result();
                
                if ($songs) {
                    echo '<table style="margin-top:10px;font-size:13px;">';
                    echo '<tr><th>#</th><th>Title</th><th>Artist</th><th>Duration</th><th>Filename</th></tr>';
                    foreach ($songs as $song) {
                        $duration = $song->duration ? gmdate('i:s', $song->duration) : '-';
                        echo '<tr>';
                        echo '<td>' . ($song->track_number ?? '-') . '</td>';
                        echo '<td>' . htmlspecialchars($song->title) . '</td>';
                        echo '<td>' . htmlspecialchars($song->artist ?? '') . '</td>';
                        echo '<td>' . $duration . '</td>';
                        echo '<td><small>' . htmlspecialchars($song->filename) . '</small></td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                } else {
                    echo '<p style="color:#f44336;margin-top:10px;">❌ No songs linked to this album</p>';
                }
                
                echo '</div>';
            }
        }
        
        // Show misc songs (not in any album)
        $misc_songs = $this->db->query("
            SELECT s.* FROM songs s 
            WHERE NOT EXISTS (SELECT 1 FROM album_songs als WHERE als.song_id = s.id)
            ORDER BY s.artist, s.title
        ")->result();
        
        if ($misc_songs) {
            echo '<div style="background:#fff3cd; padding:15px; margin:15px 0; border-radius:8px; border-left:4px solid #ffc107;">';
            echo '<h3 style="margin:0 0 10px 0;">📁 Misc Songs (' . count($misc_songs) . ')</h3>';
            echo '<p style="margin:0 0 10px 0;color:#666;">Songs not assigned to any album (albums with fewer than ' . $min_songs . ' songs)</p>';
            echo '<table style="font-size:13px;">';
            echo '<tr><th>Title</th><th>Artist</th><th>Album Tag</th><th>Duration</th></tr>';
            foreach (array_slice($misc_songs, 0, 20) as $song) {
                $duration = $song->duration ? gmdate('i:s', $song->duration) : '-';
                echo '<tr>';
                echo '<td>' . htmlspecialchars($song->title) . '</td>';
                echo '<td>' . htmlspecialchars($song->artist ?? '') . '</td>';
                echo '<td><small>' . htmlspecialchars(dirname($song->filename)) . '</small></td>';
                echo '<td>' . $duration . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            if (count($misc_songs) > 20) {
                echo '<p><em>Showing first 20 of ' . count($misc_songs) . ' misc songs</em></p>';
            }
            echo '</div>';
        }
        ?>
    </div>

    <!-- 7. PHP INFO -->
    <div class="card">
        <h2>7. PHP Info</h2>
        <p>PHP: <?php echo PHP_VERSION; ?> | Memory: <?php echo ini_get('memory_limit'); ?> | Max Time: <?php echo ini_get('max_execution_time'); ?>s</p>
        <p>Extensions: 
        <?php
        foreach (['sqlite3','gd','curl','mbstring','fileinfo'] as $ext) {
            $ok = extension_loaded($ext);
            echo '<span class="'.($ok?'success':'error').'">'.$ext.($ok?'✅':'❌').'</span> ';
        }
        ?>
        </p>
    </div>

    <div class="card" style="background:#ffebee;border-left:4px solid #f44336;">
        <h3>🗑️ DELETE THIS FILE after use!</h3>
        <p>application/controllers/Diagnose.php</p>
    </div>
</body>
</html>
<?php
        return ob_get_clean();
    }
    
    private function scan_dir($dir, $base, $exts, $exclude) {
        $files = [];
        if (!is_dir($dir) || !is_readable($dir)) return $files;
        $items = @scandir($dir);
        if (!$items) return $files;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || in_array($item, $exclude)) continue;
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $files = array_merge($files, $this->scan_dir($path, $base, $exts, $exclude));
            } elseif (is_file($path) && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $exts)) {
                $files[] = str_replace($base . '/', '', $path);
            }
        }
        return $files;
    }
    
    private function run_scan($music_path, $audio_files, $min_songs, $exclude_dirs) {
        $getid3_path = APPPATH . 'third_party/getid3/getid3.php';
        require_once($getid3_path);
        $getID3 = new getID3;
        
        $stats = ['songs' => 0, 'albums_created' => 0, 'misc' => 0, 'errors' => 0];
        $albums_data = [];
        
        foreach ($audio_files as $filename) {
            try {
                $info = $getID3->analyze($music_path . '/' . $filename);
                $title = $info['tags']['id3v2']['title'][0] ?? $info['tags']['id3v1']['title'][0] ?? pathinfo($filename, PATHINFO_FILENAME);
                $artist = $info['tags']['id3v2']['artist'][0] ?? $info['tags']['id3v1']['artist'][0] ?? 'Unknown';
                $album = $info['tags']['id3v2']['album'][0] ?? $info['tags']['id3v1']['album'][0] ?? 'Unknown';
                $year = $info['tags']['id3v2']['year'][0] ?? $info['tags']['id3v1']['year'][0] ?? null;
                $duration = isset($info['playtime_seconds']) ? (int)round($info['playtime_seconds']) : 0;
                $track = $info['tags']['id3v2']['track_number'][0] ?? null;
                if ($track && strpos($track, '/') !== false) $track = explode('/', $track)[0];
                
                if (!isset($albums_data[$album])) {
                    $albums_data[$album] = ['year' => $year, 'artist' => $artist, 'songs' => []];
                }
                $albums_data[$album]['songs'][] = [
                    'filename' => $filename, 'title' => $title, 'artist' => $artist,
                    'duration' => $duration, 'track' => $track
                ];
                
                // Insert song
                $existing = $this->db->get_where('songs', ['filename' => $filename])->row();
                if ($existing) {
                    $this->db->where('id', $existing->id)->update('songs', [
                        'title' => $title, 'artist' => $artist, 'duration' => $duration,
                        'file_hash' => md5_file($music_path.'/'.$filename), 'updated_at' => date('Y-m-d H:i:s')
                    ]);
                } else {
                    $this->db->insert('songs', [
                        'filename' => $filename, 'title' => $title, 'artist' => $artist,
                        'duration' => $duration, 'file_hash' => md5_file($music_path.'/'.$filename),
                        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')
                    ]);
                }
                $stats['songs']++;
            } catch (Exception $e) {
                $stats['errors']++;
            }
        }
        
        // Create albums
        foreach ($albums_data as $album_name => $data) {
            if (count($data['songs']) >= $min_songs) {
                $existing = $this->db->get_where('albums', ['title' => $album_name])->row();
                if ($existing) {
                    $album_id = $existing->id;
                } else {
                    $this->db->insert('albums', [
                        'title' => $album_name, 'artist' => $data['artist'], 'year' => $data['year'],
                        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')
                    ]);
                    $album_id = $this->db->insert_id();
                    $stats['albums_created']++;
                }
                
                // Link songs
                $track_num = 1;
                foreach ($data['songs'] as $song) {
                    $song_row = $this->db->get_where('songs', ['filename' => $song['filename']])->row();
                    if ($song_row) {
                        $this->db->replace('album_songs', [
                            'album_id' => $album_id,
                            'song_id' => $song_row->id,
                            'track_number' => $song['track'] ?? $track_num
                        ]);
                    }
                    $track_num++;
                }
            } else {
                $stats['misc'] += count($data['songs']);
            }
        }
        
        // Update last scan
        $this->db->replace('settings', [
            'config_key' => 'last_scan',
            'config_value' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        return $stats;
    }
}
