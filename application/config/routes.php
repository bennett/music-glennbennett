<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
*/

// Player is the homepage
$route['default_controller'] = 'player';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

// ============================================================================
// API ROUTES
// ============================================================================

// Albums
$route['api/albums'] = 'api/albums';
$route['api/album/(:num)'] = 'api/album/$1';

// Misc songs (songs not in any album)
$route['api/misc'] = 'api/misc';

// Songs
$route['api/songs'] = 'api/songs';
$route['api/song/(:num)'] = 'api/song/$1';

// Search
$route['api/search'] = 'api/search';

// Favorites
$route['api/favorites'] = 'api/favorites';
$route['api/toggle_favorite'] = 'api/toggle_favorite';
$route['api/toggle_favorite_album'] = 'api/toggle_favorite_album';

// Playlists
$route['api/playlists'] = 'api/playlists';
$route['api/playlist/(:num)'] = 'api/playlist/$1';
$route['api/playlist/(:num)/add'] = 'api/playlist_add_song/$1';
$route['api/create_playlist'] = 'api/create_playlist';

// Play tracking
$route['api/record_play'] = 'api/record_play';
$route['api/update_play'] = 'api/update_play';

// Public
$route['api/popular'] = 'api/popular';

// Library management
$route['api/auto_scan'] = 'api/auto_scan';
$route['api/stats'] = 'api/stats';
$route['api/app_version'] = 'api/app_version';
$route['api/record_share'] = 'api/record_share';
$route['api/record_share_click'] = 'api/record_share_click';

// Promo Routes
$route['promos'] = 'promos/index';
$route['promos/record_view'] = 'promos/record_view';

// Podcast Routes
$route['podcast'] = 'podcast/index';
$route['podcast/record_play'] = 'podcast/record_play';

// Share Routes
$route['share/promo'] = 'share/promo';
$route['share/podcast'] = 'share/podcast';
$route['share/image'] = 'share/image';
$route['share/song/(:num)'] = 'share/song/$1';
$route['share/album/(:num)'] = 'share/album/$1';

// Music Player Routes
$route['player'] = 'player/index';
$route['player/album/(:any)'] = 'player/album/$1';
$route['player/record_play'] = 'player/record_play';
$route['player/toggle_favorite'] = 'player/toggle_favorite';
$route['player/favorites'] = 'player/favorites';
$route['player/log_event'] = 'player/log_event';

// ============================================================================
// ADMIN ROUTES
// ============================================================================

$route['admin'] = 'admin/index';
$route['admin/scan'] = 'admin/scan_library';
$route['admin/albums'] = 'admin/albums';
$route['admin/songs'] = 'admin/songs';
$route['admin/devices'] = 'admin/devices';
$route['admin/device_activity/(:any)'] = 'admin/device_activity/$1';
$route['admin/device/(:any)'] = 'admin/device_detail/$1';
$route['admin/device_rename'] = 'admin/device_rename';
$route['admin/purge_false_starts'] = 'admin/purge_false_starts';
$route['admin/clear_device_history'] = 'admin/clear_device_history';
$route['admin/delete_device'] = 'admin/delete_device';
$route['admin/toggle_exclude'] = 'admin/toggle_exclude';
$route['admin/(.+)'] = 'admin/$1';

// ============================================================================
// DOCS ROUTES
// ============================================================================

$route['docs'] = 'docs/index';
$route['docs/(:any)/(:any)'] = 'docs/page/$1/$2';
$route['docs/(:any)'] = 'docs/page/$1';

// ============================================================================
// AUTH ROUTES
// ============================================================================

$route['auth/login'] = 'auth/login';
$route['auth/logout'] = 'auth/logout';
$route['auth/register'] = 'auth/register';