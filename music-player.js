/**
 * Glenn Bennett Music Player
 * 
 * A music player using native HTML5 Audio API
 * Supports AirPlay, Bluetooth, and handles browser quirks
 *
 * @version 1.0.0
 */

class MusicPlayer {
    constructor(config = {}) {
        // Configuration
        this.config = {
            baseUrl: config.baseUrl || '',
            initialAlbum: config.initialAlbum || null,
            autoplaySongId: config.autoplaySongId || null,
            gapBetweenTracks: config.gapBetweenTracks || 2000, // ms
            ...config
        };
        
        // State
        this.sound = null;
        this.currentAlbum = this.config.initialAlbum;
        this.currentTrackIndex = 0;
        this.isPlaying = false;
        this.isShuffle = false;
        this.repeatMode = 'off'; // 'off', 'all', 'one'
        this.shuffleQueue = [];
        this.shuffleIdx = 0;
        this.deviceId = this.getDeviceId();
        this.gapTimeout = null;
        this.isLoading = false;
        
        // DOM element cache
        this.elements = {};
        
        this.init();
    }
    
    // ═══════════════════════════════════════════════════════════════════════
    // INITIALIZATION
    // ═══════════════════════════════════════════════════════════════════════
    
    init() {
        this.cacheElements();
        this.restoreState();
        this.setupEventListeners();
        this.setupMediaSession();
        this.setupOfflineDetection();
        
        if (this.currentAlbum?.songs?.length) {
            this.generateShuffleQueue();
        }
        
        // Handle autoplay from URL
        if (this.config.autoplaySongId) {
            this.autoplaySong(this.config.autoplaySongId);
        }
        
        console.log('[MusicPlayer] Initialized', { device: this.deviceId.substring(0, 12) });
    }
    
    cacheElements() {
        // Cache DOM elements for performance
        const ids = [
            'albumSelect', 'albumDisplay', 'playerBar', 'expandedPlayer',
            'miniProgress', 'miniTitle', 'miniArtist', 'miniCover',
            'miniPlayIcon', 'expandedPlayIcon', 'expandedTitle', 
            'expandedArtist', 'expandedAlbum', 'expandedCover',
            'progressFill', 'currentTime', 'totalTime',
            'shuffleBtn', 'repeatBtn', 'favoriteBtn',
            'offlineBanner', 'toast'
        ];
        
        ids.forEach(id => {
            this.elements[id] = document.getElementById(id);
        });
    }
    
    restoreState() {
        this.isShuffle = localStorage.getItem('shuffle') === 'true';
        this.repeatMode = localStorage.getItem('repeat') || 'off';
        this.updateShuffleBtn();
        this.updateRepeatBtn();
    }
    
    setupEventListeners() {
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            
            switch(e.key) {
                case ' ':
                    e.preventDefault();
                    this.togglePlayPause();
                    break;
                case 'ArrowRight':
                    this.nextTrack();
                    break;
                case 'ArrowLeft':
                    this.previousTrack();
                    break;
                case 's':
                case 'S':
                    this.toggleShuffle();
                    break;
                case 'r':
                case 'R':
                    this.toggleRepeat();
                    break;
                case 'Escape':
                    this.collapsePlayer();
                    break;
            }
        });
        
        // Swipe to close expanded player
        this.setupSwipeGesture();
    }
    
    setupSwipeGesture() {
        const expanded = this.elements.expandedPlayer;
        if (!expanded) return;
        
        let startY = 0, currentY = 0;
        
        expanded.addEventListener('touchstart', (e) => {
            startY = e.touches[0].clientY;
        }, { passive: true });
        
        expanded.addEventListener('touchmove', (e) => {
            currentY = e.touches[0].clientY;
            const diff = currentY - startY;
            if (diff > 0) {
                expanded.style.transform = `translateY(${diff}px)`;
            }
        }, { passive: true });
        
        expanded.addEventListener('touchend', () => {
            if (currentY - startY > 100) {
                this.collapsePlayer();
            }
            expanded.style.transform = '';
            startY = currentY = 0;
        });
    }
    
    setupMediaSession() {
        if (!('mediaSession' in navigator)) return;
        
        navigator.mediaSession.setActionHandler('play', () => this.togglePlayPause());
        navigator.mediaSession.setActionHandler('pause', () => this.togglePlayPause());
        navigator.mediaSession.setActionHandler('previoustrack', () => this.previousTrack());
        navigator.mediaSession.setActionHandler('nexttrack', () => this.nextTrack());
        
        // Disable seek buttons
        try { navigator.mediaSession.setActionHandler('seekbackward', null); } catch(e) {}
        try { navigator.mediaSession.setActionHandler('seekforward', null); } catch(e) {}
    }
    
    setupOfflineDetection() {
        window.addEventListener('online', () => {
            this.elements.offlineBanner?.classList.remove('show');
        });
        
        window.addEventListener('offline', () => {
            this.elements.offlineBanner?.classList.add('show');
        });
        
        if (!navigator.onLine) {
            this.elements.offlineBanner?.classList.add('show');
        }
    }
    
    getDeviceId() {
        let id = localStorage.getItem('deviceId');
        if (!id) {
            id = 'dev_' + Date.now().toString(36) + '_' + Math.random().toString(36).substr(2, 9);
            localStorage.setItem('deviceId', id);
        }
        return id;
    }
    
    // ═══════════════════════════════════════════════════════════════════════
    // PLAYBACK CONTROL
    // ═══════════════════════════════════════════════════════════════════════
    
    playTrack(index) {
        if (!this.currentAlbum?.songs?.length) return;
        if (index < 0 || index >= this.currentAlbum.songs.length) return;
        
        // Clear any pending gap timeout
        if (this.gapTimeout) {
            clearTimeout(this.gapTimeout);
            this.gapTimeout = null;
        }
        
        // Stop and unload previous sound
        if (this.sound) {
            this.sound.unload();
            this.sound = null;
        }
        
        this.currentTrackIndex = index;
        const track = this.currentAlbum.songs[index];
        
        console.log('[MusicPlayer] Playing:', track.title);
        
        // Update UI immediately
        this.updateNowPlaying(track);
        this.showPlayerBar();
        this.setLoading(true);
        
        // Create new Howl instance
        // html5: true is CRITICAL for AirPlay support and streaming large files
        this.sound = new Howl({
            src: [track.stream_url],
            html5: true,
            volume: this.getVolume(),
            
            onload: () => {
                console.log('[MusicPlayer] Loaded:', track.title, this.formatTime(this.sound.duration()));
                this.setLoading(false);
                if (this.elements.totalTime) {
                    this.elements.totalTime.textContent = this.formatTime(this.sound.duration());
                }
            },
            
            onloaderror: (id, error) => {
                console.error('[MusicPlayer] Load error:', track.title, error);
                this.setLoading(false);
                this.toast('Failed to load: ' + track.title);
                // Auto-skip after delay
                setTimeout(() => this.nextTrack(true), 1500);
            },
            
            onplay: () => {
                this.isPlaying = true;
                this.updatePlayPauseIcons();
                this.updateMediaSessionState();
                this.highlightCurrentTrack();
                this.startProgressUpdates();
            },
            
            onpause: () => {
                this.isPlaying = false;
                this.updatePlayPauseIcons();
                this.updateMediaSessionState();
                this.highlightCurrentTrack();
            },
            
            onstop: () => {
                this.isPlaying = false;
                this.updatePlayPauseIcons();
            },
            
            onend: () => {
                console.log('[MusicPlayer] Track ended:', track.title);
                this.handleTrackEnd();
            },
            
            onplayerror: (id, error) => {
                console.error('[MusicPlayer] Play error:', error);
                // Add a fallback for audio unlock
                this.sound.once('unlock', () => {
                    console.log('[MusicPlayer] Audio unlocked, retrying...');
                    this.sound.play();
                });
            }
        });
        
        // Start playing
        this.sound.play();
        
        // Record play on server (fire and forget)
        this.recordPlay(track.id);
    }
    
    handleTrackEnd() {
        if (this.repeatMode === 'one') {
            // Repeat single track
            this.sound.seek(0);
            this.sound.play();
        } else {
            // Gap between tracks
            this.gapTimeout = setTimeout(() => {
                this.gapTimeout = null;
                this.nextTrack(true);
            }, this.config.gapBetweenTracks);
        }
    }
    
    togglePlayPause() {
        if (!this.sound) {
            // Nothing loaded - start first track
            if (this.currentAlbum?.songs?.length) {
                this.playTrack(0);
            }
            return;
        }
        
        if (this.isPlaying) {
            this.sound.pause();
        } else {
            this.sound.play();
        }
    }
    
    nextTrack(auto = false) {
        if (!this.currentAlbum?.songs?.length) return;
        
        let next;
        if (this.isShuffle) {
            this.shuffleIdx = (this.shuffleIdx + 1) % this.shuffleQueue.length;
            next = this.shuffleQueue[this.shuffleIdx];
        } else {
            next = (this.currentTrackIndex + 1) % this.currentAlbum.songs.length;
            
            // Stop at end if repeat is off (only on auto-advance)
            if (this.repeatMode === 'off' && next === 0 && auto) {
                console.log('[MusicPlayer] End of album (repeat off)');
                this.sound?.stop();
                this.isPlaying = false;
                this.updatePlayPauseIcons();
                return;
            }
        }
        
        this.playTrack(next);
    }
    
    previousTrack() {
        if (!this.sound) return;
        
        // If more than 3 seconds in, restart current track
        if (this.sound.seek() > 3) {
            this.sound.seek(0);
            return;
        }
        
        let prev;
        if (this.isShuffle) {
            this.shuffleIdx = (this.shuffleIdx - 1 + this.shuffleQueue.length) % this.shuffleQueue.length;
            prev = this.shuffleQueue[this.shuffleIdx];
        } else {
            prev = (this.currentTrackIndex - 1 + this.currentAlbum.songs.length) % this.currentAlbum.songs.length;
        }
        
        this.playTrack(prev);
    }
    
    seek(event) {
        if (!this.sound) return;
        
        const bar = event.currentTarget;
        const rect = bar.getBoundingClientRect();
        const pct = Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width));
        const duration = this.sound.duration();
        
        if (duration) {
            this.sound.seek(pct * duration);
        }
    }
    
    setVolume(value) {
        const vol = Math.max(0, Math.min(1, value));
        localStorage.setItem('volume', Math.round(vol * 100));
        
        if (this.sound) {
            this.sound.volume(vol);
        }
        
        // Also set global volume for future sounds
        if (this.audio) this.audio.volume = vol;
    }
    
    getVolume() {
        return (parseInt(localStorage.getItem('volume')) || 80) / 100;
    }
    
    // ═══════════════════════════════════════════════════════════════════════
    // ALBUM LOADING
    // ═══════════════════════════════════════════════════════════════════════
    
    async loadAlbum(id) {
        try {
            const res = await fetch(`${this.config.baseUrl}/player/album/${id}`);
            const data = await res.json();
            
            if (data.success && data.album) {
                this.currentAlbum = data.album;
                this.currentTrackIndex = 0;
                this.generateShuffleQueue();
                
                // Store last album
                document.cookie = `last_album_id=${id}; max-age=31536000; path=/`;
                localStorage.setItem('lastAlbumId', id);
                
                // Update album selector
                if (this.elements.albumSelect) {
                    this.elements.albumSelect.value = id;
                }
                
                // Render album display
                this.renderAlbum();
                
                console.log('[MusicPlayer] Album loaded:', data.album.title);
            } else {
                throw new Error(data.error || 'Failed to load album');
            }
        } catch (e) {
            console.error('[MusicPlayer] Album load error:', e);
            this.toast('Failed to load album');
        }
    }
    
    renderAlbum() {
        const a = this.currentAlbum;
        if (!a || !this.elements.albumDisplay) return;
        
        const totalDuration = a.songs.reduce((sum, s) => sum + (s.duration || 0), 0);
        
        let html = `
            <div class="album-display">
                ${a.cover_url 
                    ? `<img src="${this.esc(a.cover_url)}" class="album-cover" alt="${this.esc(a.title)}">`
                    : this.getPlaceholder(a.title)}
                <div class="album-info">
                    <h2>${this.esc(a.title)}</h2>
                    <p>${this.esc(a.artist || '')}</p>
                </div>
                <div class="play-actions">
                    <button class="play-action-btn primary" onclick="player.playAll()">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                        Play
                    </button>
                    <button class="play-action-btn" onclick="player.shufflePlay()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="16 3 21 3 21 8"></polyline>
                            <line x1="4" y1="20" x2="21" y2="3"></line>
                            <polyline points="21 16 21 21 16 21"></polyline>
                            <line x1="15" y1="15" x2="21" y2="21"></line>
                            <line x1="4" y1="4" x2="9" y2="9"></line>
                        </svg>
                        Shuffle
                    </button>
                    <button class="play-action-btn" onclick="player.shareAlbum()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/>
                            <polyline points="16 6 12 2 8 6"/>
                            <line x1="12" y1="2" x2="12" y2="15"/>
                        </svg>
                        Share
                    </button>
                </div>
            </div>
            <div class="track-list">
        `;
        
        a.songs.forEach((song, i) => {
            const isPlaying = this.currentTrackIndex === i && this.isPlaying;
            const coverStyle = song.cover_url 
                ? '' 
                : `background: ${this.getColor(song.title)}; display: flex; align-items: center; justify-content: center;`;
            
            html += `
                <div class="track ${isPlaying ? 'playing' : ''}" data-index="${i}" onclick="player.playTrack(${i})">
                    ${song.cover_url 
                        ? `<img src="${this.esc(song.cover_url)}" class="track-cover" alt="">`
                        : `<div class="track-cover" style="${coverStyle}">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.5)" stroke-width="1.5">
                                <circle cx="12" cy="12" r="2" fill="rgba(255,255,255,0.5)"/>
                                <path d="M16.24 7.76a6 6 0 010 8.49"/>
                                <path d="M7.76 16.24a6 6 0 010-8.49"/>
                            </svg>
                        </div>`}
                    <div class="track-info">
                        <div class="track-title">${this.esc(song.title)}</div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        ${isPlaying ? '<div class="playing-bars"><span></span><span></span><span></span></div>' : ''}
                        <span class="track-duration">${this.formatTime(song.duration)}</span>
                    </div>
                </div>
            `;
        });
        
        html += `
            </div>
            <div class="album-meta">
                ${a.year ? `<span>${this.esc(a.year)}</span>` : ''}
                <span>${a.songs.length} songs</span>
                <span>${this.formatTime(totalDuration)}</span>
            </div>
        `;
        
        this.elements.albumDisplay.innerHTML = html;
    }
    
    playAll() {
        this.isShuffle = false;
        this.updateShuffleBtn();
        localStorage.setItem('shuffle', 'false');
        this.playTrack(0);
    }
    
    shufflePlay() {
        this.isShuffle = true;
        this.updateShuffleBtn();
        localStorage.setItem('shuffle', 'true');
        this.generateShuffleQueue();
        this.shuffleIdx = 0;
        this.playTrack(this.shuffleQueue[0]);
        this.toast('Shuffle on');
    }
    
    // ═══════════════════════════════════════════════════════════════════════
    // UI UPDATES
    // ═══════════════════════════════════════════════════════════════════════
    
    updateNowPlaying(track) {
        const cover = track.cover_url || this.currentAlbum?.cover_url || '';
        
        // Mini player
        if (this.elements.miniTitle) this.elements.miniTitle.textContent = track.title;
        if (this.elements.miniArtist) this.elements.miniArtist.textContent = track.artist || this.currentAlbum?.artist || '';
        if (this.elements.miniCover) this.elements.miniCover.src = cover;
        
        // Expanded player
        if (this.elements.expandedTitle) this.elements.expandedTitle.textContent = track.title;
        if (this.elements.expandedArtist) this.elements.expandedArtist.textContent = track.artist || this.currentAlbum?.artist || '';
        if (this.elements.expandedAlbum) this.elements.expandedAlbum.textContent = this.currentAlbum?.title?.toUpperCase() || '';
        if (this.elements.expandedCover) this.elements.expandedCover.src = cover;
        
        // Reset progress
        if (this.elements.miniProgress) this.elements.miniProgress.style.width = '0%';
        if (this.elements.progressFill) this.elements.progressFill.style.width = '0%';
        if (this.elements.currentTime) this.elements.currentTime.textContent = '0:00';
        if (this.elements.totalTime) this.elements.totalTime.textContent = this.formatTime(track.duration);
        
        // Media Session
        if ('mediaSession' in navigator) {
            navigator.mediaSession.metadata = new MediaMetadata({
                title: track.title,
                artist: track.artist || this.currentAlbum?.artist || '',
                album: this.currentAlbum?.title || '',
                artwork: cover ? [{ src: cover, sizes: '512x512', type: 'image/jpeg' }] : []
            });
        }
    }
    
    startProgressUpdates() {
        const update = () => {
            if (!this.sound || !this.isPlaying) return;
            
            const current = this.sound.seek() || 0;
            const duration = this.sound.duration() || 1;
            const pct = (current / duration) * 100;
            
            if (this.elements.miniProgress) this.elements.miniProgress.style.width = pct + '%';
            if (this.elements.progressFill) this.elements.progressFill.style.width = pct + '%';
            if (this.elements.currentTime) this.elements.currentTime.textContent = this.formatTime(current);
            
            requestAnimationFrame(update);
        };
        
        requestAnimationFrame(update);
    }
    
    updatePlayPauseIcons() {
        const playPath = '<path d="M8 5v14l11-7z"/>';
        const pausePath = '<rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/>';
        const icon = this.isPlaying ? pausePath : playPath;
        
        if (this.elements.miniPlayIcon) this.elements.miniPlayIcon.innerHTML = icon;
        if (this.elements.expandedPlayIcon) this.elements.expandedPlayIcon.innerHTML = icon;
    }
    
    highlightCurrentTrack() {
        document.querySelectorAll('.track').forEach((el) => {
            const idx = parseInt(el.dataset.index);
            const isThis = idx === this.currentTrackIndex;
            
            el.classList.toggle('playing', isThis && this.isPlaying);
            el.classList.toggle('loading', isThis && this.isLoading);
            
            // Update playing bars
            const existingBars = el.querySelector('.playing-bars');
            const durationSpan = el.querySelector('.track-duration');
            
            if (isThis && this.isPlaying && !existingBars && durationSpan) {
                const barsHtml = '<div class="playing-bars"><span></span><span></span><span></span></div>';
                durationSpan.insertAdjacentHTML('beforebegin', barsHtml);
            } else if ((!isThis || !this.isPlaying) && existingBars) {
                existingBars.remove();
            }
        });
    }
    
    setLoading(loading) {
        this.isLoading = loading;
        this.highlightCurrentTrack();
    }
    
    showPlayerBar() {
        this.elements.playerBar?.classList.add('visible');
    }
    
    expandPlayer() {
        this.elements.expandedPlayer?.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    
    collapsePlayer() {
        this.elements.expandedPlayer?.classList.remove('show');
        document.body.style.overflow = '';
    }
    
    updateMediaSessionState() {
        if ('mediaSession' in navigator) {
            navigator.mediaSession.playbackState = this.isPlaying ? 'playing' : 'paused';
        }
    }
    
    // ═══════════════════════════════════════════════════════════════════════
    // SHUFFLE & REPEAT
    // ═══════════════════════════════════════════════════════════════════════
    
    generateShuffleQueue() {
        if (!this.currentAlbum?.songs?.length) return;
        
        this.shuffleQueue = [...Array(this.currentAlbum.songs.length).keys()];
        
        // Fisher-Yates shuffle
        for (let i = this.shuffleQueue.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [this.shuffleQueue[i], this.shuffleQueue[j]] = [this.shuffleQueue[j], this.shuffleQueue[i]];
        }
        
        this.shuffleIdx = 0;
    }
    
    toggleShuffle() {
        this.isShuffle = !this.isShuffle;
        localStorage.setItem('shuffle', this.isShuffle);
        this.updateShuffleBtn();
        this.toast(this.isShuffle ? 'Shuffle on' : 'Shuffle off');
        
        if (this.isShuffle) {
            this.generateShuffleQueue();
        }
    }
    
    toggleRepeat() {
        const modes = ['off', 'all', 'one'];
        const idx = (modes.indexOf(this.repeatMode) + 1) % modes.length;
        this.repeatMode = modes[idx];
        localStorage.setItem('repeat', this.repeatMode);
        this.updateRepeatBtn();
        
        const labels = { off: 'Repeat off', all: 'Repeat all', one: 'Repeat one' };
        this.toast(labels[this.repeatMode]);
    }
    
    updateShuffleBtn() {
        this.elements.shuffleBtn?.classList.toggle('active', this.isShuffle);
    }
    
    updateRepeatBtn() {
        this.elements.repeatBtn?.classList.toggle('active', this.repeatMode !== 'off');
    }
    
    // ═══════════════════════════════════════════════════════════════════════
    // FAVORITES
    // ═══════════════════════════════════════════════════════════════════════
    
    async toggleFavorite() {
        const track = this.currentAlbum?.songs?.[this.currentTrackIndex];
        if (!track) return;
        
        try {
            const res = await fetch(`${this.config.baseUrl}/player/toggle_favorite`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Device-Id': this.deviceId
                },
                body: `song_id=${track.id}`
            });
            
            const data = await res.json();
            
            if (data.success) {
                this.toast(data.is_favorite ? 'Added to favorites' : 'Removed from favorites');
                this.updateFavoriteBtn(data.is_favorite);
            }
        } catch (e) {
            console.error('[MusicPlayer] Favorite toggle error:', e);
        }
    }
    
    updateFavoriteBtn(isFavorite) {
        const btn = this.elements.favoriteBtn;
        if (!btn) return;
        
        btn.classList.toggle('active', isFavorite);
        const svg = btn.querySelector('svg');
        if (svg) {
            svg.setAttribute('fill', isFavorite ? 'currentColor' : 'none');
        }
    }
    
    // ═══════════════════════════════════════════════════════════════════════
    // SHARING
    // ═══════════════════════════════════════════════════════════════════════
    
    shareAlbum() {
        // Use /share/album/ URL so Facebook crawler gets OG meta tags + custom image
        const url = `${window.location.origin}${this.config.baseUrl}/share/album/${this.currentAlbum?.id}`;
        this.share(this.currentAlbum?.title || 'Album', url);
    }
    
    shareSong() {
        const track = this.currentAlbum?.songs?.[this.currentTrackIndex];
        if (!track) return;
        
        // Use /share/song/ URL so Facebook crawler gets OG meta tags + custom image
        const url = `${window.location.origin}${this.config.baseUrl}/share/song/${track.id}`;
        this.share(track.title, url);
    }
    
    share(title, url) {
        if (navigator.share) {
            navigator.share({ title, url }).catch(() => {});
        } else {
            navigator.clipboard.writeText(url).then(() => {
                this.toast('Link copied!');
            }).catch(() => {
                this.toast('Could not copy link');
            });
        }
    }
    
    // ═══════════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════════
    
    recordPlay(songId) {
        if (!navigator.onLine) return;
        
        fetch(`${this.config.baseUrl}/player/record_play`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Device-Id': this.deviceId
            },
            body: `song_id=${songId}`
        }).catch(() => {});
    }
    
    autoplaySong(songId) {
        if (!this.currentAlbum?.songs) return;
        
        const index = this.currentAlbum.songs.findIndex(s => s.id == songId);
        if (index >= 0) {
            setTimeout(() => {
                this.playTrack(index);
                this.expandPlayer();
            }, 500);
        }
    }
    
    formatTime(seconds) {
        if (!seconds || isNaN(seconds)) return '0:00';
        const m = Math.floor(seconds / 60);
        const s = Math.floor(seconds % 60);
        return `${m}:${s.toString().padStart(2, '0')}`;
    }
    
    esc(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }
    
    getColor(title) {
        const colors = ['#e91e63', '#9c27b0', '#673ab7', '#3f51b5', '#2196f3', '#00bcd4', '#009688', '#4caf50', '#ff9800', '#ff5722'];
        let hash = 0;
        for (let i = 0; i < (title || 'x').length; i++) {
            hash = title.charCodeAt(i) + ((hash << 5) - hash);
        }
        return colors[Math.abs(hash) % colors.length];
    }
    
    getPlaceholder(title) {
        return `
            <div class="album-cover-placeholder" style="background: ${this.getColor(title)}">
                <svg width="90" height="90" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.4)" stroke-width="1.5">
                    <circle cx="12" cy="12" r="2" fill="rgba(255,255,255,0.4)"/>
                    <path d="M16.24 7.76a6 6 0 010 8.49"/>
                    <path d="M7.76 16.24a6 6 0 010-8.49"/>
                    <path d="M19.07 4.93a10 10 0 010 14.14"/>
                    <path d="M4.93 19.07a10 10 0 010-14.14"/>
                </svg>
            </div>
        `;
    }
    
    toast(message) {
        const toast = this.elements.toast;
        if (!toast) return;
        
        toast.textContent = message;
        toast.classList.add('show');
        
        setTimeout(() => toast.classList.remove('show'), 2500);
    }
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = MusicPlayer;
}
