<?php
/**
 * Admin Controller - Upload Methods Replacement
 * Version: 1.0.0
 * 
 * INSTALLATION:
 * Replace the upload_song() method in Admin.php with the methods below.
 * Add the new helper methods (_add_single_song, etc.)
 * 
 * The new flow:
 * 1. upload_song() - Shows upload form and staged files
 * 2. do_upload() - Uploads to _staging folder
 * 3. commit_upload() - Moves from staging to /songs/
 * 4. cancel_upload() - Deletes staged file
 */

// =============================================================================
// REPLACE YOUR EXISTING upload_song() METHOD WITH THIS:
// =============================================================================

/**
 * Upload page - shows upload form and staged files
 */
public function upload_song() {
    $this->load->config('music_config');
    $music_path = rtrim($this->config->item('music_origin_path') ?: '', '/');
    $staging_path = $music_path . '/_staging';
    
    // Ensure staging directory exists
    if (!is_dir($staging_path)) {
        @mkdir($staging_path, 0755, true);
    }
    
    $data = [
        'music_path' => $music_path,
        'staged_files' => []
    ];
    
    // Get staged files and extract their metadata
    if (is_dir($staging_path)) {
        $files = @scandir($staging_path);
        $getid3_path = APPPATH . 'third_party/getid3/getid3.php';
        $getID3 = null;
        if (file_exists($getid3_path)) {
            require_once($getid3_path);
            $getID3 = new \getID3;
        }
        
        if ($files) {
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (!in_array($ext, ['mp3', 'm4a', 'flac', 'ogg', 'wav'])) continue;
                
                $full_path = $staging_path . '/' . $file;
                $metadata = [
                    'filename' => $file, 
                    'staged_path' => $full_path,
                    'title' => pathinfo($file, PATHINFO_FILENAME),
                    'artist' => null,
                    'album' => null,
                    'track' => null,
                    'duration' => 0,
                    'has_cover' => false
                ];
                
                // Extract ID3 tags
                if ($getID3) {
                    try {
                        $info = $getID3->analyze($full_path);
                        $tags = $info['tags']['id3v2'] ?? $info['tags']['id3v1'] ?? [];
                        
                        $metadata['title'] = $tags['title'][0] ?? $metadata['title'];
                        $metadata['artist'] = $tags['artist'][0] ?? null;
                        $metadata['album'] = $tags['album'][0] ?? null;
                        $metadata['track'] = isset($tags['track_number'][0]) ? explode('/', $tags['track_number'][0])[0] : null;
                        $metadata['duration'] = isset($info['playtime_seconds']) ? (int)round($info['playtime_seconds']) : 0;
                        $metadata['has_cover'] = isset($info['comments']['picture']) || isset($info['id3v2']['APIC']);
                    } catch (\Exception $e) {
                        // Keep defaults
                    }
                }
                
                // Check if file already exists in songs folder
                $dest_path = $music_path . '/' . $file;
                $metadata['exists'] = file_exists($dest_path);
                
                $data['staged_files'][] = $metadata;
            }
        }
    }
    
    $this->load->view('admin/upload_song', $data);
}

/**
 * Handle file upload - uploads to staging folder
 */
public function do_upload() {
    $this->load->config('music_config');
    $music_path = rtrim($this->config->item('music_origin_path') ?: '', '/');
    $staging_path = $music_path . '/_staging';
    
    // Ensure staging directory exists
    if (!is_dir($staging_path)) {
        if (!@mkdir($staging_path, 0755, true)) {
            $this->session->set_flashdata('error', 'Failed to create staging directory: ' . $staging_path);
            redirect('admin/upload_song');
            return;
        }
    }
    
    if (!empty($_FILES['audio_file']['name'])) {
        $original_name = $_FILES['audio_file']['name'];
        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        
        // Validate extension
        $allowed = ['mp3', 'm4a', 'flac', 'ogg', 'wav'];
        if (!in_array($ext, $allowed)) {
            $this->session->set_flashdata('error', 'Invalid file type. Allowed: ' . implode(', ', $allowed));
            redirect('admin/upload_song');
            return;
        }
        
        // Sanitize filename but keep it recognizable
        $filename = preg_replace('/[^a-zA-Z0-9._\-\s]/', '', pathinfo($original_name, PATHINFO_FILENAME));
        $filename = trim($filename);
        if (empty($filename)) {
            $filename = 'upload_' . time();
        }
        $filename = $filename . '.' . $ext;
        
        $dest = $staging_path . '/' . $filename;
        
        // Handle duplicate filenames in staging
        $counter = 1;
        while (file_exists($dest)) {
            $filename = pathinfo($original_name, PATHINFO_FILENAME) . '_' . $counter . '.' . $ext;
            $dest = $staging_path . '/' . $filename;
            $counter++;
        }
        
        if (move_uploaded_file($_FILES['audio_file']['tmp_name'], $dest)) {
            $this->session->set_flashdata('success', 'File uploaded to staging: ' . $filename . ' - Review metadata below and click Import.');
        } else {
            $this->session->set_flashdata('error', 'Failed to upload file. Check permissions on: ' . $staging_path);
        }
    } else {
        $this->session->set_flashdata('error', 'No file selected');
    }
    
    redirect('admin/upload_song');
}

/**
 * Commit a staged file - move to songs folder and add to database
 */
public function commit_upload() {
    $this->load->config('music_config');
    $music_path = rtrim($this->config->item('music_origin_path') ?: '', '/');
    $staged_file = $this->input->post('staged_file');
    
    if (!$staged_file || !file_exists($staged_file)) {
        $this->session->set_flashdata('error', 'Staged file not found');
        redirect('admin/upload_song');
        return;
    }
    
    // Security: ensure file is in staging folder
    $staging_path = $music_path . '/_staging';
    $real_staged = realpath($staged_file);
    $real_staging = realpath($staging_path);
    
    if (!$real_staged || !$real_staging || strpos($real_staged, $real_staging) !== 0) {
        $this->session->set_flashdata('error', 'Invalid file path');
        redirect('admin/upload_song');
        return;
    }
    
    $filename = basename($staged_file);
    $dest_path = $music_path . '/' . $filename;
    
    // Move file to songs folder
    if (rename($staged_file, $dest_path)) {
        // Add to database
        $song_id = $this->_add_single_song($dest_path, $filename);
        $this->session->set_flashdata('success', 'Imported: ' . $filename);
    } else {
        $this->session->set_flashdata('error', 'Failed to move file to songs folder. Check permissions.');
    }
    
    redirect('admin/upload_song');
}

/**
 * Cancel a staged upload - delete the staged file
 */
public function cancel_upload() {
    $this->load->config('music_config');
    $music_path = rtrim($this->config->item('music_origin_path') ?: '', '/');
    $staged_file = $this->input->post('staged_file');
    
    if (!$staged_file) {
        $this->session->set_flashdata('error', 'No file specified');
        redirect('admin/upload_song');
        return;
    }
    
    // Security: ensure file is in staging folder
    $staging_path = $music_path . '/_staging';
    $real_staged = realpath($staged_file);
    $real_staging = realpath($staging_path);
    
    if ($real_staged && $real_staging && strpos($real_staged, $real_staging) === 0) {
        if (@unlink($staged_file)) {
            $this->session->set_flashdata('success', 'Cancelled: ' . basename($staged_file));
        } else {
            $this->session->set_flashdata('error', 'Failed to delete staged file');
        }
    } else {
        $this->session->set_flashdata('error', 'Invalid file path');
    }
    
    redirect('admin/upload_song');
}

/**
 * Commit all staged files
 */
public function commit_all() {
    $this->load->config('music_config');
    $music_path = rtrim($this->config->item('music_origin_path') ?: '', '/');
    $staging_path = $music_path . '/_staging';
    
    $count = 0;
    $errors = [];
    
    if (is_dir($staging_path)) {
        $files = @scandir($staging_path);
        if ($files) {
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (!in_array($ext, ['mp3', 'm4a', 'flac', 'ogg', 'wav'])) continue;
                
                $src = $staging_path . '/' . $file;
                $dest = $music_path . '/' . $file;
                
                if (rename($src, $dest)) {
                    $this->_add_single_song($dest, $file);
                    $count++;
                } else {
                    $errors[] = $file;
                }
            }
        }
    }
    
    if ($count > 0) {
        $msg = 'Imported ' . $count . ' file(s)';
        if (!empty($errors)) {
            $msg .= '. Failed: ' . implode(', ', $errors);
        }
        $this->session->set_flashdata('success', $msg);
    } else {
        $this->session->set_flashdata('error', 'No files imported');
    }
    
    redirect('admin/upload_song');
}

/**
 * Cancel all staged uploads
 */
public function cancel_all() {
    $this->load->config('music_config');
    $music_path = rtrim($this->config->item('music_origin_path') ?: '', '/');
    $staging_path = $music_path . '/_staging';
    
    $count = 0;
    if (is_dir($staging_path)) {
        $files = @scandir($staging_path);
        if ($files) {
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                $full_path = $staging_path . '/' . $file;
                if (is_file($full_path)) {
                    @unlink($full_path);
                    $count++;
                }
            }
        }
    }
    
    $this->session->set_flashdata('success', 'Cancelled ' . $count . ' staged upload(s)');
    redirect('admin/upload_song');
}

/**
 * Add a single song to the database from file
 * @param string $full_path Full path to the audio file
 * @param string $filename Just the filename (relative path in /songs/)
 * @return int|null Song ID
 */
private function _add_single_song($full_path, $filename) {
    $title = pathinfo($filename, PATHINFO_FILENAME);
    $artist = null;
    $duration = 0;
    
    // Try to read ID3 tags
    $getid3_path = APPPATH . 'third_party/getid3/getid3.php';
    if (file_exists($getid3_path)) {
        require_once($getid3_path);
        $getID3 = new \getID3;
        
        try {
            $info = $getID3->analyze($full_path);
            $tags = $info['tags']['id3v2'] ?? $info['tags']['id3v1'] ?? [];
            
            $title = $tags['title'][0] ?? $title;
            $artist = $tags['artist'][0] ?? null;
            $duration = isset($info['playtime_seconds']) ? (int)round($info['playtime_seconds']) : 0;
        } catch (\Exception $e) {
            // Use defaults
        }
    }
    
    // Check if song already exists in database
    $existing = $this->db->get_where('songs', ['filename' => $filename])->row();
    
    $song_data = [
        'filename' => $filename,
        'title' => $title,
        'artist' => $artist,
        'duration' => $duration,
        'file_hash' => md5_file($full_path)
    ];
    
    if ($existing) {
        // Update existing record
        $this->db->where('id', $existing->id)->update('songs', $song_data);
        return $existing->id;
    } else {
        // Insert new record
        $this->db->insert('songs', $song_data);
        return $this->db->insert_id();
    }
}

/**
 * Delete a file from disk and database (AJAX endpoint)
 */
public function delete_file() {
    $this->output->set_content_type('application/json');
    $filename = $this->input->post('filename');
    
    if (!$filename) {
        $this->output->set_output(json_encode(['success' => false, 'message' => 'Filename required']));
        return;
    }
    
    $this->load->config('music_config');
    $music_path = rtrim($this->config->item('music_origin_path') ?: '', '/');
    $full_path = $music_path . '/' . $filename;
    
    // Security: ensure path is within music folder
    $real_music = realpath($music_path);
    $real_file = realpath($full_path);
    
    if (!$real_music || ($real_file && strpos($real_file, $real_music) !== 0)) {
        $this->output->set_output(json_encode(['success' => false, 'message' => 'Invalid file path']));
        return;
    }
    
    $deleted_file = false;
    $deleted_db = false;
    
    // Delete file from disk
    if (file_exists($full_path)) {
        if (@unlink($full_path)) {
            $deleted_file = true;
        } else {
            $this->output->set_output(json_encode(['success' => false, 'message' => 'Failed to delete file from disk']));
            return;
        }
    }
    
    // Delete from database
    $song = $this->db->get_where('songs', ['filename' => $filename])->row();
    if ($song) {
        $this->db->where('song_id', $song->id)->delete('album_songs');
        $this->db->where('song_id', $song->id)->delete('play_history');
        $this->db->where('song_id', $song->id)->delete('favorites');
        $this->db->where('id', $song->id)->delete('songs');
        $deleted_db = true;
    }
    
    $this->output->set_output(json_encode([
        'success' => true, 
        'deleted_file' => $deleted_file,
        'deleted_db' => $deleted_db
    ]));
}

/**
 * Clean orphaned database entries (AJAX endpoint)
 */
public function clean_orphans() {
    $this->output->set_content_type('application/json');
    
    $removed = $this->_clean_orphaned_songs();
    
    $this->output->set_output(json_encode(['success' => true, 'removed' => $removed]));
}

/**
 * Helper: Clean orphaned songs from database
 * @return int Number of removed entries
 */
private function _clean_orphaned_songs() {
    $this->load->config('music_config');
    $music_path = rtrim($this->config->item('music_origin_path') ?: '', '/');
    
    $songs = $this->db->get('songs')->result();
    $removed = 0;
    
    foreach ($songs as $song) {
        $full_path = $music_path . '/' . $song->filename;
        if (!file_exists($full_path)) {
            // Delete from album_songs
            $this->db->where('song_id', $song->id)->delete('album_songs');
            // Delete play history
            $this->db->where('song_id', $song->id)->delete('play_history');
            // Delete favorites
            $this->db->where('song_id', $song->id)->delete('favorites');
            // Delete song
            $this->db->where('id', $song->id)->delete('songs');
            $removed++;
        }
    }
    
    return $removed;
}

// =============================================================================
// REPLACE YOUR EXISTING scan_library() METHOD WITH THIS:
// =============================================================================

/**
 * Scan library - now also cleans up orphaned entries
 */
public function scan_library() {
    set_time_limit(600);
    ini_set('memory_limit', '512M');
    
    // Ensure songs table has cover_filename column
    $fields = $this->db->list_fields('songs');
    if (!in_array('cover_filename', $fields)) {
        $this->db->query("ALTER TABLE songs ADD COLUMN cover_filename TEXT");
    }
    
    // First, clean up orphaned entries (files that no longer exist)
    $removed = $this->_clean_orphaned_songs();
    
    // Then do the normal scan
    $stats = $this->song_model->scan_library();
    
    $message = sprintf(
        'Library scan complete: %d added, %d updated, %d removed, %d errors',
        $stats['added'],
        $stats['updated'],
        $removed,
        $stats['errors']
    );
    
    $this->session->set_flashdata('success', $message);
    redirect('admin');
}
