// Service Worker for Glenn Bennett Music Player
// ═══════════════════════════════════════════════════════════════════════════
const APP_VERSION = '3.1.5';
const CACHE_NAME = 'bennett-music-v' + APP_VERSION;
const AUDIO_CACHE = 'bennett-music-audio-v1';
const API_CACHE = 'bennett-music-api-v1';

// Files to cache on install
const STATIC_ASSETS = [
    '/',
    '/player',
    '/manifest.json',
    '/assets/css/music-player.css',
    '/assets/js/music-player.js'
];

// ═══════════════════════════════════════════════════════════════════════════
// INSTALL - Cache static assets
// ═══════════════════════════════════════════════════════════════════════════
self.addEventListener('install', (event) => {
    console.log('[SW] Installing v' + APP_VERSION);
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(STATIC_ASSETS).catch(() => {}))
            .then(() => self.skipWaiting())
    );
});

// ═══════════════════════════════════════════════════════════════════════════
// ACTIVATE - Clean old caches & precache all songs
// ═══════════════════════════════════════════════════════════════════════════
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating v' + APP_VERSION);
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    // Delete old version caches but keep audio cache
                    if (cacheName.startsWith('bennett-music-v') && cacheName !== CACHE_NAME) {
                        console.log('[SW] Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                    // Also clean up old naming convention
                    if (cacheName.startsWith('music-player-') && !cacheName.includes('audio')) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
        .then(() => self.clients.claim())
        .then(() => precacheAllSongs())
    );
});

// ═══════════════════════════════════════════════════════════════════════════
// PRECACHE ALL SONGS - Background download for offline playback
// ═══════════════════════════════════════════════════════════════════════════
async function precacheAllSongs() {
    console.log('[SW] Starting background precache of all songs...');
    
    try {
        // Fetch all albums
        const response = await fetch('/api/albums');
        const data = await response.json();
        
        if (!data.success || !data.albums) {
            console.log('[SW] Could not fetch albums for precaching');
            return;
        }
        
        const cache = await caches.open(AUDIO_CACHE);
        const songsToCache = [];
        
        // Collect all songs from all albums
        for (const album of data.albums) {
            try {
                const albumRes = await fetch('/api/album/' + album.id);
                const albumData = await albumRes.json();
                
                if (!albumData.success || !albumData.album || !albumData.album.songs) continue;
                
                for (const song of albumData.album.songs) {
                    if (!song.stream_url) continue;
                    
                    // Check if already cached
                    const existing = await cache.match(song.stream_url);
                    if (!existing) {
                        songsToCache.push({ url: song.stream_url, title: song.title });
                    }
                }
            } catch (e) {
                console.log('[SW] Error fetching album:', album.title);
            }
        }
        
        console.log('[SW] Songs to precache:', songsToCache.length);
        
        // Download one at a time with delay to not overwhelm slow connections
        for (let i = 0; i < songsToCache.length; i++) {
            const song = songsToCache[i];
            try {
                const res = await fetch(song.url);
                if (res.ok) {
                    await cache.put(song.url, res);
                    console.log('[SW] Precached (' + (i+1) + '/' + songsToCache.length + '):', song.title);
                }
            } catch (e) {
                console.log('[SW] Failed to precache:', song.title);
            }
            
            // Small delay between downloads to not hog bandwidth
            if (i < songsToCache.length - 1) {
                await new Promise(r => setTimeout(r, 1000));
            }
        }
        
        console.log('[SW] Precache complete');
        
    } catch (error) {
        console.log('[SW] Precache failed:', error);
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// FETCH - Handle requests
// ═══════════════════════════════════════════════════════════════════════════
self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);
    
    // Only handle http/https
    if (!url.protocol.startsWith('http')) {
        return;
    }
    
    // Skip chrome-extension and other non-http schemes
    if (url.protocol === 'chrome-extension:' || url.protocol === 'moz-extension:') {
        return;
    }
    
    // Handle API requests - network first, cache fallback
    if (url.pathname.startsWith('/api/')) {
        event.respondWith(handleApiRequest(request));
        return;
    }
    
    // Handle audio files - cache first for offline playback
    if (isAudioFile(url.pathname) || (url.hostname.includes('b-cdn.net') && isAudioFile(url.pathname))) {
        event.respondWith(handleAudioRequest(request));
        return;
    }
    
    // Handle cover images (including CDN) - cache first
    if (isImageFile(url.pathname) || url.pathname.includes('/covers/') || url.pathname.includes('/uploads/') || url.pathname.includes('/imgs/')) {
        event.respondWith(handleImageRequest(request));
        return;
    }
    
    // Handle CDN images separately
    if (url.hostname.includes('b-cdn.net') && isImageFile(url.pathname)) {
        event.respondWith(handleImageRequest(request));
        return;
    }
    
    // Handle static assets - cache first, network fallback
    event.respondWith(handleStaticRequest(request));
});

// ═══════════════════════════════════════════════════════════════════════════
// REQUEST HANDLERS
// ═══════════════════════════════════════════════════════════════════════════

async function handleApiRequest(request) {
    const url = new URL(request.url);
    
    // Never cache these endpoints
    const neverCache = ['/api/record_play', '/api/update_play', '/api/log_event', '/api/auto_scan'];
    if (neverCache.some(path => url.pathname.startsWith(path))) {
        return fetch(request).catch(() => new Response('{"error":"offline"}', {
            headers: { 'Content-Type': 'application/json' }
        }));
    }
    
    // Network first, cache fallback for other API calls
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(API_CACHE);
            cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        const cached = await caches.match(request);
        if (cached) return cached;
        return new Response('{"error":"offline"}', {
            headers: { 'Content-Type': 'application/json' }
        });
    }
}

async function handleAudioRequest(request) {
    const url = request.url;
    
    // CRITICAL: Range requests must go to network for proper streaming
    if (request.headers.get('range')) {
        // First check if we're offline - if so, serve from cache immediately
        if (!navigator.onLine) {
            const cached = await caches.match(url);
            if (cached) {
                console.log('[SW] Offline - audio from cache:', url.split('/').pop());
                return cached;
            }
            return new Response('Audio unavailable offline', { status: 503 });
        }
        
        // Online - fetch with Range for proper streaming
        try {
            const response = await fetch(request);
            
            // If we got a 206 (partial), kick off background cache of full file
            if (response.status === 206) {
                cacheFullAudioFile(url);
            }
            
            return response;
        } catch (error) {
            // Network error - try cache
            const cached = await caches.match(url);
            if (cached) {
                console.log('[SW] Network error - audio from cache:', url.split('/').pop());
                return cached;
            }
            return new Response('Audio unavailable', { status: 503 });
        }
    }
    
    // Non-range requests: check cache first for offline playback
    const cached = await caches.match(request);
    if (cached) {
        console.log('[SW] Audio from cache:', url.split('/').pop());
        return cached;
    }
    
    // Fetch from network and cache for offline
    try {
        const response = await fetch(request);
        if (response.ok && response.status === 200) {
            const cache = await caches.open(AUDIO_CACHE);
            cache.put(request, response.clone());
            console.log('[SW] Audio cached:', url.split('/').pop());
        }
        return response;
    } catch (error) {
        console.log('[SW] Audio fetch failed:', error);
        return new Response('Audio unavailable offline', { status: 503 });
    }
}

// Background cache full audio file (non-blocking)
function cacheFullAudioFile(url) {
    // Check if already cached
    caches.match(url).then(cached => {
        if (cached) return; // Already have it
        
        // Fetch full file without Range header
        fetch(url, { headers: {} }).then(response => {
            if (response.ok && response.status === 200) {
                caches.open(AUDIO_CACHE).then(cache => {
                    cache.put(url, response);
                    console.log('[SW] Background cached:', url.split('/').pop());
                });
            }
        }).catch(() => {}); // Silently fail - it's background
    });
}

async function handleImageRequest(request) {
    const cached = await caches.match(request);
    if (cached) return cached;
    
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        // Return placeholder or nothing for images
        return new Response('', { status: 404 });
    }
}

async function handleStaticRequest(request) {
    // Navigation requests (HTML pages): network-first so updates are immediate
    if (request.mode === 'navigate') {
        try {
            const response = await fetch(request);
            if (response.ok) {
                const cache = await caches.open(CACHE_NAME);
                cache.put(request, response.clone());
            }
            return response;
        } catch (error) {
            const cached = await caches.match(request);
            if (cached) return cached;
            const root = await caches.match('/');
            if (root) return root;
            return new Response('Offline', { status: 503 });
        }
    }

    // Static assets (JS/CSS/etc): network-first so version bumps take effect immediately
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        const cached = await caches.match(request);
        if (cached) return cached;
        return new Response('Offline', { status: 503 });
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════════════════════

function isAudioFile(pathname) {
    return /\.(mp3|m4a|aac|ogg|wav|flac)$/i.test(pathname);
}

function isImageFile(pathname) {
    return /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(pathname);
}

// ═══════════════════════════════════════════════════════════════════════════
// MESSAGE HANDLING - For version checks and cache control
// ═══════════════════════════════════════════════════════════════════════════
self.addEventListener('message', (event) => {
    // Return version info
    if (event.data === 'getVersion') {
        if (event.ports && event.ports[0]) {
            event.ports[0].postMessage({ type: 'version', version: APP_VERSION });
        }
        return;
    }
    
    // Skip waiting and activate immediately
    if (event.data === 'skipWaiting' || event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
        return;
    }
    
    // Cache specific audio file
    if (event.data && event.data.type === 'CACHE_AUDIO') {
        caches.open(AUDIO_CACHE).then(cache => {
            fetch(event.data.url).then(response => {
                if (response.ok) cache.put(event.data.url, response);
            }).catch(() => {});
        });
        return;
    }
    
    // Get list of cached audio files
    if (event.data && event.data.type === 'GET_CACHED_SONGS') {
        caches.open(AUDIO_CACHE).then(cache => {
            cache.keys().then(requests => {
                const urls = requests.map(r => r.url);
                if (event.ports && event.ports[0]) {
                    event.ports[0].postMessage({ songs: urls });
                }
            });
        });
        return;
    }
    
    // Clear all caches
    if (event.data && event.data.type === 'CLEAR_CACHE') {
        caches.keys().then(names => {
            Promise.all(names.map(name => caches.delete(name)));
        });
        return;
    }
});

console.log('[SW] Service Worker loaded, version:', APP_VERSION);
