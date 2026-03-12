<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auth extends CI_Controller {
    
    public function __construct() {
        parent::__construct();
        $this->load->model('user_model');
    }
    
    public function login() {
        // If already logged in, redirect
        if ($this->session->userdata('user_id')) {
            if ($this->session->userdata('is_admin')) {
                redirect('admin');
            } else {
                redirect('player');
            }
        }

        // Auto-login from known admin IPs
        if ($this->_try_ip_auto_login()) {
            redirect('admin');
            return;
        }

        $error = '';

        // Handle login
        if ($this->input->post()) {
            $username = $this->input->post('username');
            $password = $this->input->post('password');

            $user = $this->user_model->login($username, $password);

            if ($user) {
                $this->session->set_userdata([
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'is_admin' => $user->is_admin,
                    'logged_in' => TRUE
                ]);

                // Save admin IP and purge all admin IP stats
                if ($user->is_admin) {
                    $this->_save_admin_ip();
                    $this->_purge_admin_ip_stats();
                    redirect('admin');
                } else {
                    redirect('player');
                }
            } else {
                $error = 'Invalid username or password';
            }
        }

        // Output HTML directly
        $this->output_login_page($error);
    }
    
    public function logout() {
        $this->session->sess_destroy();
        redirect('auth/login');
    }
    
    /**
     * Check if current IP is a known admin IP and auto-authenticate
     */
    private function _try_ip_auto_login() {
        $this->load->database();
        $ip = $this->input->ip_address();

        // Ensure table exists
        $this->db->query("CREATE TABLE IF NOT EXISTS admin_ips (
            ip VARCHAR(45) PRIMARY KEY,
            first_seen DATETIME NOT NULL,
            last_seen DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $row = $this->db->get_where('admin_ips', ['ip' => $ip])->row();
        if (!$row) {
            return false;
        }

        // Known admin IP — find the admin user and set session
        $admin = $this->db->get_where('users', ['is_admin' => 1])->row();
        if (!$admin) {
            return false;
        }

        $this->session->set_userdata([
            'user_id' => $admin->id,
            'username' => $admin->username,
            'email' => $admin->email,
            'is_admin' => $admin->is_admin,
            'logged_in' => TRUE
        ]);

        // Update last_seen and purge admin stats
        $this->db->where('ip', $ip)->update('admin_ips', [
            'last_seen' => gmdate('Y-m-d H:i:s')
        ]);
        $this->_purge_admin_ip_stats();

        return true;
    }

    /**
     * Save current IP as a known admin IP
     */
    private function _save_admin_ip() {
        $this->load->database();
        $ip = $this->input->ip_address();
        $now = gmdate('Y-m-d H:i:s');

        $existing = $this->db->get_where('admin_ips', ['ip' => $ip])->row();
        if ($existing) {
            $this->db->where('ip', $ip)->update('admin_ips', ['last_seen' => $now]);
        } else {
            $this->db->insert('admin_ips', [
                'ip' => $ip,
                'first_seen' => $now,
                'last_seen' => $now
            ]);
        }
    }

    /**
     * Purge all play history from devices that have a known admin IP.
     * Also marks those devices as excluded=2.
     */
    private function _purge_admin_ip_stats() {
        $this->load->database();

        // Find all devices whose ip_address is in admin_ips
        $admin_devices = $this->db->query("
            SELECT d.id FROM devices d
            WHERE d.ip_address IN (SELECT ip FROM admin_ips)
        ")->result();

        if (empty($admin_devices)) {
            return;
        }

        $device_ids = array_column($admin_devices, 'id');

        // Delete all play history for these devices
        $this->db->where_in('device_id', $device_ids)->delete('play_history');

        // Mark them as admin devices and reset play counts
        $this->db->where_in('id', $device_ids)->update('devices', [
            'excluded' => 2,
            'play_count' => 0
        ]);
    }

    private function output_login_page($error = '') {
        $site_url = site_url('auth/login');
        $error_html = $error ? '<div class="error">' . htmlspecialchars($error) . '</div>' : '';
        
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Login - Music Player</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-container {
            background: white;
            border-radius: 15px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        h1 {
            color: #667eea;
            margin-bottom: 10px;
            text-align: center;
        }
        p {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        input:focus {
            outline: none;
            border-color: #667eea;
        }
        button {
            width: 100%;
            padding: 14px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        button:hover {
            background: #5568d3;
        }
        .error {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #fcc;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>🎵 Music Player</h1>
        <p>Login to your account</p>
        
        $error_html
        
        <form method="post" action="$site_url">
            <div class="form-group">
                <label>Username or Email</label>
                <input type="text" name="username" required autofocus>
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            
            <button type="submit">Login</button>
        </form>
        
        <p style="margin-top: 20px; font-size: 14px; color: #aaa;">
            Bennett Music Admin
        </p>
    </div>
</body>
</html>
HTML;
        
        $this->output->set_output($html);
    }
}