<?php
/**
 * Music Player Installation Script
 * Run this file once to initialize the application
 * Access via: https://music.glennbennett.com/install.php
 * 
 * IMPORTANT: Delete this file after installation!
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration
define('DB_PATH', __DIR__ . '/database/music.db');
define('SCHEMA_PATH', __DIR__ . '/database/schema.sql');
define('ADMIN_USERNAME', 'admin');
define('ADMIN_EMAIL', 'gbennett@tsgdev.com');
define('ADMIN_PASSWORD', '2276#midi');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Music Player Installation</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .step { background: #f5f5f5; padding: 15px; margin: 10px 0; border-left: 4px solid #4CAF50; }
        .error { border-left-color: #f44336; }
        .success { color: #4CAF50; }
        .error-text { color: #f44336; }
        h1 { color: #333; }
        button { background: #4CAF50; color: white; padding: 10px 20px; border: none; cursor: pointer; font-size: 16px; }
        button:hover { background: #45a049; }
        .warning { background: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107; }
    </style>
</head>
<body>
    <h1>🎵 Music Player Installation</h1>
    
    <div class="warning">
        <strong>⚠️ Security Warning:</strong> Delete this install.php file immediately after installation!
    </div>

<?php

// Check if already installed
if (file_exists(DB_PATH) && filesize(DB_PATH) > 0 && !isset($_GET['force'])) {
    echo '<div class="step error"><strong>⚠️ Already Installed</strong><br>';
    echo 'Database already exists. If you want to reinstall:<br>';
    echo '1. Backup your data if needed<br>';
    echo '2. <a href="?force=1"><button style="background:#f44336;">Force Reinstall (will delete existing data)</button></a></div>';
    echo '<p>Database location: ' . DB_PATH . '</p>';
    exit;
}

// Force reinstall - delete existing database
if (isset($_GET['force']) && file_exists(DB_PATH)) {
    unlink(DB_PATH);
    echo '<div class="step"><strong>🗑️ Deleted Existing Database</strong><br>Starting fresh installation...</div>';
}

// Installation steps
$steps = [];

// Step 1: Check requirements
$steps[] = check_requirements();

// Step 2: Create directories
$steps[] = create_directories();

// Step 3: Initialize database
$steps[] = initialize_database();

// Step 4: Create admin user
$steps[] = create_admin_user();

// Display results
echo '<h2>Installation Results</h2>';

$all_success = true;
foreach ($steps as $step) {
    $class = $step['success'] ? '' : 'error';
    $icon = $step['success'] ? '✅' : '❌';
    echo "<div class='step $class'><strong>$icon {$step['title']}</strong><br>{$step['message']}</div>";
    
    if (!$step['success']) {
        $all_success = false;
    }
}

if ($all_success) {
    echo '<div class="step">';
    echo '<h3 class="success">🎉 Installation Complete!</h3>';
    echo '<p>Your music player is ready to use.</p>';
    echo '<h4>Next Steps:</h4>';
    echo '<ol>';
    echo '<li><strong>DELETE THIS FILE (install.php)</strong> for security</li>';
    echo '<li>Login at: <a href="/admin">Admin Panel</a></li>';
    echo '<li>Username: <strong>' . ADMIN_USERNAME . '</strong></li>';
    echo '<li>Password: <strong>' . ADMIN_PASSWORD . '</strong></li>';
    echo '<li>Go to Admin → Scan Library to import your music</li>';
    echo '</ol>';
    echo '<p><a href="/admin"><button>Go to Admin Panel</button></a></p>';
    echo '</div>';
} else {
    echo '<div class="step error">';
    echo '<h3 class="error-text">Installation Failed</h3>';
    echo '<p>Please fix the errors above and try again.</p>';
    echo '</div>';
}

// Functions

function check_requirements() {
    $errors = [];
    
    // PHP version
    if (version_compare(PHP_VERSION, '7.4.0', '<')) {
        $errors[] = 'PHP 7.4 or higher required (you have ' . PHP_VERSION . ')';
    }
    
    // SQLite3
    if (!extension_loaded('sqlite3')) {
        $errors[] = 'SQLite3 extension not installed';
    }
    
    // GD Library
    if (!extension_loaded('gd')) {
        $errors[] = 'GD library not installed (needed for image processing)';
    }
    
    // cURL
    if (!extension_loaded('curl')) {
        $errors[] = 'cURL extension not installed';
    }
    
    // Write permissions
    $dirs = [
        __DIR__ . '/database',
        __DIR__ . '/uploads/covers',
        __DIR__ . '/application/cache',
        __DIR__ . '/application/logs'
    ];
    
    foreach ($dirs as $dir) {
        if (!is_writable($dir)) {
            $errors[] = 'Directory not writable: ' . $dir;
        }
    }
    
    if (empty($errors)) {
        return [
            'title' => 'System Requirements',
            'message' => 'All requirements met',
            'success' => true
        ];
    } else {
        return [
            'title' => 'System Requirements',
            'message' => implode('<br>', $errors),
            'success' => false
        ];
    }
}

function create_directories() {
    $dirs = [
        __DIR__ . '/database',
        __DIR__ . '/uploads',
        __DIR__ . '/uploads/covers',
        __DIR__ . '/application/cache',
        __DIR__ . '/application/cache/sessions',
        __DIR__ . '/application/logs'
    ];
    
    $created = [];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            if (mkdir($dir, 0755, true)) {
                $created[] = $dir;
            } else {
                return [
                    'title' => 'Create Directories',
                    'message' => 'Failed to create: ' . $dir,
                    'success' => false
                ];
            }
        }
    }
    
    $message = empty($created) ? 'All directories already exist' : 'Created: ' . implode(', ', $created);
    
    return [
        'title' => 'Create Directories',
        'message' => $message,
        'success' => true
    ];
}

function initialize_database() {
    try {
        // Create SQLite database
        $db = new SQLite3(DB_PATH);
        
        // Read schema
        if (!file_exists(SCHEMA_PATH)) {
            return [
                'title' => 'Initialize Database',
                'message' => 'Schema file not found: ' . SCHEMA_PATH,
                'success' => false
            ];
        }
        
        $schema = file_get_contents(SCHEMA_PATH);
        
        // Execute schema
        $result = $db->exec($schema);
        
        if ($result === false) {
            return [
                'title' => 'Initialize Database',
                'message' => 'Failed to create database: ' . $db->lastErrorMsg(),
                'success' => false
            ];
        }
        
        $db->close();
        
        return [
            'title' => 'Initialize Database',
            'message' => 'Database created successfully at: ' . DB_PATH,
            'success' => true
        ];
        
    } catch (Exception $e) {
        return [
            'title' => 'Initialize Database',
            'message' => 'Error: ' . $e->getMessage(),
            'success' => false
        ];
    }
}

function create_admin_user() {
    try {
        $db = new SQLite3(DB_PATH);
        
        // Hash password
        $password_hash = password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT);
        
        // Insert admin user
        $stmt = $db->prepare('INSERT OR REPLACE INTO users (username, email, password, is_admin, is_verified, created_at) VALUES (:username, :email, :password, 1, 1, :created_at)');
        $stmt->bindValue(':username', ADMIN_USERNAME, SQLITE3_TEXT);
        $stmt->bindValue(':email', ADMIN_EMAIL, SQLITE3_TEXT);
        $stmt->bindValue(':password', $password_hash, SQLITE3_TEXT);
        $stmt->bindValue(':created_at', date('Y-m-d H:i:s'), SQLITE3_TEXT);
        
        $result = $stmt->execute();
        
        if (!$result) {
            return [
                'title' => 'Create Admin User',
                'message' => 'Failed to create admin user: ' . $db->lastErrorMsg(),
                'success' => false
            ];
        }
        
        $db->close();
        
        return [
            'title' => 'Create Admin User',
            'message' => 'Admin user created successfully',
            'success' => true
        ];
        
    } catch (Exception $e) {
        return [
            'title' => 'Create Admin User',
            'message' => 'Error: ' . $e->getMessage(),
            'success' => false
        ];
    }
}

?>

</body>
</html>
