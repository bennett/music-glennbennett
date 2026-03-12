/**
 * Glenn Bennett Music Player
 * 
 * Uses single persistent HTML5 Audio element for iOS background playback
 * Based on the proven PAS (Player-Album-Showcase) approach
 *
 * No AudioContext in use (pure HTML5 <audio>), so AudioContext resume is N/A.
 * If added later (visualizations, EQ), add AudioContext.resume() in handleResume().
 * 
 * @version 3.0.0
 */

class MusicPlayer {
    constructor(config = {}) {
        // Configuration
        this.config = {
            baseUrl: config.baseUrl || '',
            initialAlbum: config.initialAlbum || null,
            autoplaySongId: config.autoplaySongId || null,
            ...config
        };
        
        // Audio element - SINGLE persistent element (key for iOS background playback)
        this.audio = null;
        
        // State
        this.currentAlbum = this.config.initialAlbum;
        this.currentTrackIndex = 0;
        this.isPlaying = false;
        this.isShuffle = false;
        this.repeatMode = 'all'; // 'off', 'all', 'one' — default is 'all' (loop album)
        this.shuffleQueue = [];
        this.shuffleIdx = 0;
        this.deviceId = this.getDeviceId();
        this.isLoading = false;
        this.lastEndedTime = 0; // Debounce ended events
        this.hasSource = false; // True only when a valid track is loaded
        this.wakeLock = null; // Screen Wake Lock sentinel
        this._pauseIsUserAction = false; // Flag to distinguish user pause from OS interrupt
        this._interrupted = false; // True when paused by OS (CarPlay disconnect, etc.)
        
        // DOM element cache
        this.elements = {};
        
        this.init();
    }
    
    // ═══════════════════════════════════════════════════════════════════════
    // INITIALIZATION
    // ═══════════════════════════════════════════════════════════════════════
    
    init() {
        this.setupAudioElement();
        this.cacheElements();
        this.restoreState();
        this.setupEventListeners();
        this.setupMediaSession();
        this.setupProgressScrub();
        this.setupSwipeDown();
        this.setupOfflineDetection();
        
        if (this.currentAlbum?.songs?.length) {
            this.generateShuffleQueue();
        }

        // Add Favorites to dropdown only if device has favorites
        this.syncFavoritesOption();

        // If no initial album but albums exist in dropdown, load the first one
        // Use setTimeout to ensure DOM is fully ready with all options
        if (!this.currentAlbum) {
            setTimeout(() => {
                if (this.elements.albumSelect?.options?.length > 0) {
                    const firstRealAlbum = Array.from(this.elements.albumSelect.options)
                        .find(opt => opt.value && opt.value !== 'favorites' && opt.value !== 'misc');
                    if (firstRealAlbum) {
                        this.loadAlbum(firstRealAlbum.value);
                    }
                }
            }, 0);
        }

        // Handle autoplay from URL
        if (this.config.autoplaySongId) {
            this.autoplaySong(this.config.autoplaySongId);
        }
        
        console.log('[MusicPlayer] Initialized with single Audio element', { device: this.deviceId.substring(0, 12) });
    }
    
    setupAudioElement() {
        // Remove old audio element if exists
        const oldAudio = document.getElementById('audioPlayer');
        if (oldAudio) {
            oldAudio.pause();
            oldAudio.src = '';
            oldAudio.remove();
        }
        
        // Create single persistent audio element
        this.audio = document.createElement('audio');
        this.audio.id = 'audioPlayer';
        this.audio.preload = 'auto';
        document.body.appendChild(this.audio);

        // Tell iOS/CarPlay to duck our audio when other apps speak (navigation, Siri, etc.)
        // Also listen for audio session state changes — 'active' fires when CarPlay/Bluetooth
        // reconnects, allowing auto-resume without user interaction (like native music apps).
        if (navigator.audioSession) {
            navigator.audioSession.type = 'playback';
            try {
                navigator.audioSession.addEventListener('statechange', () => {
                    if (navigator.audioSession.state === 'active' && this._interrupted && this.hasSource) {
                        console.log('[MusicPlayer] Audio session active — auto-resuming');
                        this._interrupted = false;
                        this.resumeOrRecover();
                    }
                });
            } catch (e) { /* addEventListener not supported in this Safari version */ }
        }

        // Restore volume
        this.audio.volume = (localStorage.getItem('volume') || 80) / 100;
        
        const self = this;
        
        // Progress updates
        this.audio.addEventListener('timeupdate', function() {
            self.updateProgress();
        });
        
        // Metadata loaded — duration is now known
        this.audio.addEventListener('loadedmetadata', function() {
            console.log('[MusicPlayer] Loaded:', self.getCurrentTrack()?.title, self.formatTime(self.audio.duration));
            self.setLoading(false);
            if (self.elements.totalTime) {
                self.elements.totalTime.textContent = self.formatTime(self.audio.duration);
            }
            // Tell CarPlay/lock screen the track duration and that position is 0
            self.updateMediaSessionPosition();
        });
        
        // Track ended - CRITICAL for background playback
        this.audio.addEventListener('ended', function() {
            const now = Date.now();
            
            // Debounce - ignore if fired within 1 second
            if (now - self.lastEndedTime < 1000) {
                console.log('[MusicPlayer] Ended event debounced');
                return;
            }
            self.lastEndedTime = now;
            
            // Ignore if track never actually played
            if (!self.audio.duration || self.audio.duration === 0 || isNaN(self.audio.duration)) {
                console.log('[MusicPlayer] Ended ignored - no duration');
                return;
            }
            
            if (self.audio.currentTime < 0.5) {
                console.log('[MusicPlayer] Ended ignored - nothing played');
                return;
            }
            
            console.log('[MusicPlayer] Track ended normally');
            self.handleTrackEnd();
        });
        
        // Error handling
        this.audio.addEventListener('error', function(e) {
            // Ignore errors from intentionally clearing the source
            if (!self.hasSource) return;

            console.error('[MusicPlayer] Audio error:', e);
            self.setLoading(false);
            self.toast('Failed to load track');
            // Auto-skip after delay
            setTimeout(() => self.nextTrack(true), 1500);
        });
        
        // Play/Pause state sync
        this.audio.addEventListener('pause', function() {
            self.isPlaying = false;
            self.updatePlayPauseIcons();
            self.highlightCurrentTrack();
            self.savePlaybackState();
            self.releaseWakeLock();

            // Detect involuntary pause (OS interrupt: CarPlay disconnect, phone call, etc.)
            if (self._pauseIsUserAction) {
                self._pauseIsUserAction = false;
                self._interrupted = false;
                localStorage.removeItem('interruptedAt');
            } else if (self.hasSource) {
                console.log('[MusicPlayer] Involuntary pause detected (OS interrupt)');
                self._interrupted = true;
                try { localStorage.setItem('interruptedAt', String(Date.now())); } catch(e) {}
            }
        });

        this.audio.addEventListener('play', function() {
            self.isPlaying = true;
            self._interrupted = false;
            localStorage.removeItem('interruptedAt');
            self.updatePlayPauseIcons();
            self.updateMediaSessionState();
            self.highlightCurrentTrack();
            self.requestWakeLock();
        });
        
        // Waiting/buffering
        this.audio.addEventListener('waiting', function() {
            self.setLoading(true);
        });
        
        this.audio.addEventListener('canplay', function() {
            self.setLoading(false);
        });
    }
    
    cacheElements() {
        const ids = [
            'albumSelect', 'albumDisplay', 'playerBar', 'expandedPlayer',
            'miniProgress', 'miniTitle', 'miniArtist', 'miniCover',
            'miniPlayIcon', 'expandedPlayIcon', 'expandedTitle', 
            'expandedArtist', 'expandedAlbum', 'expandedCover',
            'progressFill', 'progressHandle', 'currentTime', 'totalTime',
            'shuffleBtn', 'repeatBtn', 'favoriteBtn',
            'offlineBanner', 'toast'
        ];
        
        ids.forEach(id => {
            this.elements[id] = document.getElementById(id);
        });
    }
    
    restoreState() {
        this.isShuffle = localStorage.getItem('shuffle') === 'true';
        this.repeatMode = localStorage.getItem('repeat') || 'all';
        this.updateShuffleBtn();
        this.updateRepeatBtn();
        this.restorePlaybackState();
    }
    
    setupEventListeners() {
        // Re-register MediaSession and recover audio after browser suspension
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                this.handleResume();
                // Re-acquire wake lock (released automatically when page goes hidden)
                if (this.isPlaying) this.requestWakeLock();
            } else {
                // Last chance to save state before iOS kills the PWA
                this.savePlaybackState();
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

            const expanded = this.elements.expandedPlayer?.classList.contains('show');

            switch(e.code) {
                case 'Space':
                    e.preventDefault();
                    this.togglePlayPause();
                    break;
                case 'ArrowRight':
                    if (expanded || e.metaKey || e.ctrlKey) {
                        e.preventDefault();
                        this.nextTrack();
                    }
                    break;
                case 'ArrowLeft':
                    if (expanded || e.metaKey || e.ctrlKey) {
                        e.preventDefault();
                        this.previousTrack();
                    }
                    break;
                case 'Escape':
                    if (expanded) {
                        this.collapsePlayer();
                    }
                    break;
            }
        });
    }
    
    setupMediaSession() {
        if (!('mediaSession' in navigator)) return;

        // Use play-only handler (not togglePlayPause) so lock screen/CarPlay play button
        // always resumes even when this.isPlaying is stale after suspension.
        // Play unconditionally (safe no-op if already playing) to catch OS-suspended audio.
        navigator.mediaSession.setActionHandler('play', () => {
            this.resumeOrRecover();
        });
        navigator.mediaSession.setActionHandler('pause', () => { this._pauseIsUserAction = true; this.audio.pause(); });
        navigator.mediaSession.setActionHandler('previoustrack', () => this.previousTrack());
        navigator.mediaSession.setActionHandler('nexttrack', () => this.nextTrack());

        // iOS Safari always shows seek circles on lock screen for web audio — we can't change
        // the icons to skip buttons (that's a native-app-only feature). But we CAN make the
        // seek buttons behave as prev/next track so they're still useful.
        try { navigator.mediaSession.setActionHandler('seekbackward', () => this.previousTrack()); } catch(e) {}
        try { navigator.mediaSession.setActionHandler('seekforward', () => this.nextTrack()); } catch(e) {}
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
    
    handleResume() {
        // Re-register handlers - WebKit can silently drop them after long suspension
        this.setupMediaSession();

        // Re-set full metadata (iOS drops it after long suspension, not just handlers)
        const track = this.getCurrentTrack();
        if (track) {
            const cover = track.cover_url || this.currentAlbum?.cover_url || '';
            this.updateMediaSession(track, cover);
        }

        // Sync JS state with actual audio state
        const actuallyPlaying = !this.audio.paused && !this.audio.ended;
        if (this.isPlaying !== actuallyPlaying) {
            this.isPlaying = actuallyPlaying;
            this.updatePlayPauseIcons();
            this.updateMediaSessionState();
        }

        // If we were playing but audio has stalled or errored, reload and resume
        if (this.isPlaying && (this.audio.error || this.audio.readyState < 2)) {
            console.log('[MusicPlayer] Recovering stalled audio after resume');
            this.recoverPlayback();
        }

        // Auto-resume after OS interrupt (CarPlay disconnect, phone call ended, etc.)
        // Native apps do this via AVAudioSession interruption callbacks; we emulate
        // by detecting the involuntary pause and resuming when the app comes back.
        if (this._interrupted && this.hasSource) {
            console.log('[MusicPlayer] Auto-resuming after OS interrupt');
            this._interrupted = false;
            this.resumeOrRecover();
        }
    }

    recoverPlayback() {
        const track = this.getCurrentTrack();
        if (!track?.stream_url) return;

        const savedTime = this.audio.currentTime;
        console.log('[MusicPlayer] Recovering playback at', savedTime + 's');

        this.audio.src = track.stream_url;
        this.hasSource = true;
        this.audio.load();

        this.audio.addEventListener('canplay', () => {
            if (savedTime > 0) this.audio.currentTime = savedTime;
            this.audio.play().catch(e => console.error('[MusicPlayer] Recovery play failed:', e));
        }, { once: true });
    }

    /**
     * Resume audio or recover if the source has gone stale.
     * Called from Media Session play handler (lock screen, CarPlay, Bluetooth).
     * After CarPlay disconnect/reconnect the audio source connection may have
     * timed out, so audio.play() either rejects or resolves but never advances.
     * This method detects both cases and falls back to a full source reload.
     */
    resumeOrRecover() {
        if (!this.hasSource) {
            this.playTrack(this.currentTrackIndex || 0);
            return;
        }

        if (this.audio.error) {
            this.recoverPlayback();
            return;
        }

        // If network connection to source is lost, reload immediately
        // NETWORK_NO_SOURCE (3) = source gone, NETWORK_EMPTY (0) = never loaded
        if (this.audio.networkState === 3 || this.audio.networkState === 0) {
            console.log('[MusicPlayer] Network source lost, recovering');
            this.recoverPlayback();
            return;
        }

        const timeBefore = this.audio.currentTime;

        this.audio.play().then(() => {
            // play() resolved — but audio may still be stuck buffering with
            // a stale source (e.g. after CarPlay reconnect). Check that
            // currentTime actually advances within 3 seconds.
            setTimeout(() => {
                if (!this.audio.paused && this.audio.currentTime === timeBefore) {
                    console.log('[MusicPlayer] Audio stalled after play(), recovering');
                    this.recoverPlayback();
                }
            }, 3000);
        }).catch(() => {
            // play() rejected — source is definitely stale, recover
            console.log('[MusicPlayer] play() rejected, recovering');
            this.recoverPlayback();
        });
    }

    savePlaybackState() {
        const track = this.getCurrentTrack();
        if (!track) return;
        try {
            localStorage.setItem('lastSongId', track.id);
            localStorage.setItem('lastPosition', Math.floor(this.audio.currentTime || 0));
            localStorage.setItem('lastAlbumId', this.currentAlbum?.id || '');
        } catch (e) {
            // QuotaExceededError — not critical
        }
    }

    restorePlaybackState() {
        // Shared link takes priority
        if (this.config.autoplaySongId) return;

        const songId = localStorage.getItem('lastSongId');
        if (!songId) return;

        const albumId = localStorage.getItem('lastAlbumId');
        // Only restore if same album is loaded (avoid cross-album confusion)
        if (albumId && String(this.currentAlbum?.id) !== String(albumId)) return;

        if (!this.currentAlbum?.songs?.length) return;

        const index = this.currentAlbum.songs.findIndex(s => String(s.id) === String(songId));
        if (index < 0) return;

        const track = this.currentAlbum.songs[index];
        const savedPosition = Number(localStorage.getItem('lastPosition')) || 0;

        // Check if we were interrupted (OS killed the app while music was playing).
        // If so, auto-resume instead of just showing the paused state.
        // Time limit: only auto-resume if the interruption was within the last 30 minutes.
        const interruptedAt = Number(localStorage.getItem('interruptedAt'));
        const shouldAutoResume = interruptedAt && (Date.now() - interruptedAt < 30 * 60 * 1000);

        console.log('[MusicPlayer] Restoring playback state:', track.title, 'at', savedPosition + 's',
            shouldAutoResume ? '(auto-resuming)' : '(paused)');

        this.currentTrackIndex = index;
        this.hasSource = true;
        this.audio.src = track.stream_url;
        this.audio.load();

        if (shouldAutoResume) {
            localStorage.removeItem('interruptedAt');
            this.audio.addEventListener('canplay', () => {
                if (savedPosition > 0 && savedPosition < this.audio.duration) {
                    this.audio.currentTime = savedPosition;
                }
                this.audio.play().catch(e => {
                    console.log('[MusicPlayer] Auto-resume blocked (needs gesture):', e);
                });
            }, { once: true });
            this.updateNowPlaying(track);
            this.showPlayerBar();
        } else {
            // Seek to saved position once metadata is loaded
            if (savedPosition > 0) {
                this.audio.addEventListener('loadedmetadata', () => {
                    if (savedPosition < this.audio.duration) {
                        this.audio.currentTime = savedPosition;
                    }
                }, { once: true });
            }
            // Update UI (mini player bar) but do NOT auto-play or expand
            this.updateNowPlaying(track);
            this.showPlayerBar();
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
    
    getCurrentTrack() {
        if (!this.currentAlbum?.songs?.length) return null;
        return this.currentAlbum.songs[this.currentTrackIndex];
    }
    
    // ═══════════════════════════════════════════════════════════════════════
    // PLAYBACK CONTROL - Uses single persistent Audio element
    // ═══════════════════════════════════════════════════════════════════════
    
    playTrack(index) {
        if (!this.currentAlbum?.songs?.length) return;
        if (index < 0 || index >= this.currentAlbum.songs.length) return;

        // Update stats for previous track before switching
        this.updatePlayStats();

        // Reset progress bar immediately
        this.resetProgress();

        this.currentTrackIndex = index;
        const track = this.currentAlbum.songs[index];
        
        console.log('[MusicPlayer] Playing:', track.title);
        
        // Update UI immediately
        this.updateNowPlaying(track);
        this.showPlayerBar();
        this.expandPlayer();
        this.setLoading(true);
        
        // Set source and play - SAME audio element, just change src
        this.audio.src = track.stream_url;
        this.hasSource = true;
        this.audio.load();
        
        this.audio.play().catch(e => {
            console.error('[MusicPlayer] Play failed:', e);
            // iOS often needs user gesture - we'll handle via unlock
        });
        
        // Record play on server
        this.recordPlay(track.id);
    }
    
    handleTrackEnd() {
        if (this.repeatMode === 'one') {
            // Repeat single track
            this.audio.currentTime = 0;
            this.audio.play();
        } else {
            // Next track - no gap needed, iOS handles it
            this.nextTrack(true);
        }
    }
    
    togglePlayPause() {
        if (!this.hasSource) {
            // Nothing loaded - start first track
            if (this.currentAlbum?.songs?.length) {
                this.playTrack(0);
            }
            return;
        }

        if (this.isPlaying) {
            this._pauseIsUserAction = true;
            this.audio.pause();
        } else {
            this.audio.play().catch(e => console.error('[MusicPlayer] Play failed:', e));
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
                this._pauseIsUserAction = true;
                this.audio.pause();
                this.audio.currentTime = 0;
                this.isPlaying = false;
                this.updatePlayPauseIcons();
                this.resetProgress();
                // Don't restore a finished album on next cold start
                localStorage.removeItem('lastSongId');
                localStorage.removeItem('lastPosition');
                return;
            }
        }
        
        this.playTrack(next);
    }
    
    previousTrack() {
        // If more than 3 seconds in, restart current track
        if (this.audio.currentTime > 3) {
            this.audio.currentTime = 0;
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
        if (!this.audio.duration) return;
        const bar = document.getElementById('progressBar');
        if (!bar) return;
        const rect = bar.getBoundingClientRect();
        const clientX = event.touches ? event.touches[0].clientX : event.clientX;
        const pct = Math.max(0, Math.min(1, (clientX - rect.left) / rect.width));
        this.audio.currentTime = pct * this.audio.duration;
    }

    setupProgressScrub() {
        const bar = document.getElementById('progressBar');
        if (!bar) return;

        let dragging = false;

        const getPosition = (e) => {
            const rect = bar.getBoundingClientRect();
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            return Math.max(0, Math.min(1, (clientX - rect.left) / rect.width));
        };

        const onStart = (e) => {
            if (!this.audio.duration) return;
            dragging = true;
            const pct = getPosition(e);
            this.audio.currentTime = pct * this.audio.duration;
            e.preventDefault();
        };

        const onMove = (e) => {
            if (!dragging || !this.audio.duration) return;
            const pct = getPosition(e);
            this.audio.currentTime = pct * this.audio.duration;
            this.updateProgress();
            e.preventDefault();
        };

        const onEnd = () => { dragging = false; };

        bar.addEventListener('mousedown', onStart);
        bar.addEventListener('touchstart', onStart, { passive: false });
        document.addEventListener('mousemove', onMove);
        document.addEventListener('touchmove', onMove, { passive: false });
        document.addEventListener('mouseup', onEnd);
        document.addEventListener('touchend', onEnd);
    }
    
    setVolume(value) {
        this.audio.volume = value / 100;
        const slider = document.getElementById('expVolumeSlider');
        if (slider) slider.value = value;
        localStorage.setItem('volume', value);
    }
    
    getVolume() {
        return Math.round(this.audio.volume * 100);
    }
    
    // ═══════════════════════════════════════════════════════════════════════
    // ALBUM MANAGEMENT
    // ═══════════════════════════════════════════════════════════════════════
    
    async loadAlbum(id) {
        const previousValue = this.elements.albumSelect?.value;

        try {
            const res = await fetch(`${this.config.baseUrl}/player/album/${id}`, {
                headers: { 'X-Device-Id': this.deviceId }
            });
            const data = await res.json();

            if (data.success && data.album) {
                // Stop current playback only after new album loaded successfully
                this.audio.pause();
                this.audio.src = '';
                this.hasSource = false;
                this.isPlaying = false;
                this.updatePlayPauseIcons();

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
            // Restore dropdown to previous selection
            if (this.elements.albumSelect && previousValue) {
                this.elements.albumSelect.value = previousValue;
            }
        }
    }
    
    renderAlbum() {
        const a = this.currentAlbum;
        if (!a || !this.elements.albumDisplay) return;
        
        const totalDuration = a.songs.reduce((sum, s) => sum + (Number(s.duration) || 0), 0);
        
        // Check if currently playing this album
        const isPlayingThisAlbum = this.isPlaying && this.audio.src;
        const playBtnIcon = isPlayingThisAlbum 
            ? '<svg viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>'
            : '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>';
        const playBtnText = isPlayingThisAlbum ? 'Pause' : 'Play';
        
        let html = `
            <div class="album-display">
                ${a.cover_url && a.title !== 'Misc'
                    ? `<img src="${this.esc(a.cover_url)}" class="album-cover" alt="${this.esc(a.title)}">`
                    : (a.songs?.length ? this.getStackedCovers(a.songs) : this.getPlaceholder(a.title))}
                <div class="album-info">
                    <h2>${this.esc(a.title)}</h2>
                </div>
                <div class="play-actions">
                    <button class="play-action-btn primary" id="mainPlayBtn" onclick="player.mainPlayToggle()">
                        ${playBtnIcon}
                        ${playBtnText}
                    </button>
                    <button class="play-action-btn" onclick="player.shufflePlay()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 3 21 3 21 8"></polyline><line x1="4" y1="20" x2="21" y2="3"></line><polyline points="21 16 21 21 16 21"></polyline><line x1="15" y1="15" x2="21" y2="21"></line><line x1="4" y1="4" x2="9" y2="9"></line></svg>
                        Shuffle
                    </button>
                    <button class="play-action-btn" onclick="player.shareAlbum()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>
                        Share
                    </button>
                </div>
                <div class="album-meta">${a.songs.length} songs · ${this.formatTime(totalDuration)}</div>
                <div class="track-list">
                    ${a.songs.map((s, i) => `
                        <div class="track ${this.currentTrackIndex === i && this.isPlaying ? 'playing' : ''}" 
                             data-index="${i}" 
                             onclick="player.playTrack(${i})">
                            ${s.cover_url
                                ? `<img src="${this.esc(s.cover_url)}" class="track-thumb" alt="">`
                                : `<span class="track-number">${i + 1}</span>`}
                            <div class="track-info">
                                <div class="track-title">${this.esc(s.title)}</div>
                            </div>
                            <span class="track-duration">${this.formatTime(s.duration || 0)}</span>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
        
        this.elements.albumDisplay.innerHTML = html;
    }
    
    // Main play button handler - plays from start if nothing playing, otherwise toggles
    mainPlayToggle() {
        // If currently playing, pause
        if (this.isPlaying) {
            this.audio.pause();
            return;
        }
        // If paused with a valid source, resume
        if (this.hasSource && this.audio.paused) {
            this.audio.play().catch(e => console.error('[MusicPlayer] Play failed:', e));
            return;
        }
        // Nothing loaded - start from track 0
        if (this.currentAlbum?.songs?.length) {
            this.playTrack(0);
        }
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
        if (this.elements.miniCover) {
            this.elements.miniCover.src = cover;
            this.elements.miniCover.style.display = cover ? 'block' : 'none';
        }
        
        // Expanded player
        if (this.elements.expandedTitle) this.elements.expandedTitle.textContent = track.title;
        if (this.elements.expandedArtist) this.elements.expandedArtist.textContent = track.artist || this.currentAlbum?.artist || '';
        if (this.elements.expandedAlbum) this.elements.expandedAlbum.textContent = this.currentAlbum?.title || '';
        if (this.elements.expandedCover) {
            this.elements.expandedCover.src = cover;
        }
        
        // Media Session
        this.updateMediaSession(track, cover);
        
        // Check if this track is a favorite
        this.checkFavoriteStatus(track.id);
        
        // Show share song menu item
        const shareSongMenu = document.getElementById('menuShareSong');
        if (shareSongMenu) {
            shareSongMenu.style.display = 'flex';
        }
    }
    
    updateProgress() {
        if (!this.audio.duration) return;

        const current = this.audio.currentTime;
        const duration = this.audio.duration;
        const pct = (current / duration) * 100;

        if (this.elements.miniProgress) this.elements.miniProgress.style.width = pct + '%';
        if (this.elements.progressFill) this.elements.progressFill.style.width = pct + '%';
        if (this.elements.progressHandle) this.elements.progressHandle.style.left = pct + '%';
        if (this.elements.currentTime) this.elements.currentTime.textContent = this.formatTime(current);

        // Sync CarPlay/lock screen position every ~5 seconds
        const now = Math.floor(current);
        if (now % 5 === 0 && now !== this._lastPositionSync) {
            this._lastPositionSync = now;
            this.updateMediaSessionPosition();
            this.savePlaybackState();
        }
    }

    resetProgress() {
        if (this.elements.miniProgress) this.elements.miniProgress.style.width = '0%';
        if (this.elements.progressFill) this.elements.progressFill.style.width = '0%';
        if (this.elements.progressHandle) this.elements.progressHandle.style.left = '0%';
        if (this.elements.currentTime) this.elements.currentTime.textContent = '0:00';
        if (this.elements.totalTime) this.elements.totalTime.textContent = '0:00';
    }
    
    updatePlayPauseIcons() {
        const playPath = '<path d="M8 5v14l11-7z"/>';
        const pausePath = '<rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/>';
        const icon = this.isPlaying ? pausePath : playPath;
        
        if (this.elements.miniPlayIcon) this.elements.miniPlayIcon.innerHTML = icon;
        if (this.elements.expandedPlayIcon) this.elements.expandedPlayIcon.innerHTML = icon;
        
        // Update main play button in album display
        const mainPlayBtn = document.getElementById('mainPlayBtn');
        if (mainPlayBtn) {
            const svgIcon = this.isPlaying 
                ? '<svg viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>'
                : '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>';
            const btnText = this.isPlaying ? 'Pause' : 'Play';
            mainPlayBtn.innerHTML = svgIcon + ' ' + btnText;
        }
        
        // Show/hide share song menu item
        const shareSongMenu = document.getElementById('menuShareSong');
        if (shareSongMenu) {
            shareSongMenu.style.display = this.hasSource ? 'flex' : 'none';
        }
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
        if (this.elements.playerBar) {
            this.elements.playerBar.classList.add('visible');
        }
    }
    
    expandPlayer() {
        if (this.elements.expandedPlayer) {
            this.elements.expandedPlayer.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    }

    collapsePlayer() {
        if (this.elements.expandedPlayer) {
            this.elements.expandedPlayer.classList.remove('show');
            this.elements.expandedPlayer.style.transform = '';
            document.body.style.overflow = '';
        }
    }

    setupSwipeDown() {
        const el = this.elements.expandedPlayer;
        if (!el) return;
        let startY = 0;
        let currentY = 0;
        let swiping = false;

        el.addEventListener('touchstart', (e) => {
            // Only swipe if scrolled to top (not mid-scroll)
            if (el.scrollTop > 0) return;
            startY = e.touches[0].clientY;
            currentY = startY;
            swiping = true;
            el.style.transition = 'none';
        }, { passive: true });

        el.addEventListener('touchmove', (e) => {
            if (!swiping) return;
            currentY = e.touches[0].clientY;
            const dy = currentY - startY;
            if (dy > 0) {
                el.style.transform = `translateY(${dy}px)`;
            }
        }, { passive: true });

        el.addEventListener('touchend', () => {
            if (!swiping) return;
            swiping = false;
            el.style.transition = '';
            const dy = currentY - startY;
            if (dy > 80) {
                this.collapsePlayer();
            } else {
                el.style.transform = '';
            }
        });
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
        
        if (this.isShuffle) {
            this.generateShuffleQueue();
            this.toast('Shuffle on');
        } else {
            this.toast('Shuffle off');
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
        if (this.elements.shuffleBtn) {
            this.elements.shuffleBtn.classList.toggle('active', this.isShuffle);
        }
    }
    
    updateRepeatBtn() {
        const btn = this.elements.repeatBtn;
        if (!btn) return;
        
        btn.classList.toggle('active', this.repeatMode !== 'off');
        
        // Update icon for repeat one
        const svg = btn.querySelector('svg');
        if (svg) {
            if (this.repeatMode === 'one') {
                svg.innerHTML = '<polyline points="17 1 21 5 17 9"></polyline><path d="M3 11V9a4 4 0 0 1 4-4h14"></path><polyline points="7 23 3 19 7 15"></polyline><path d="M21 13v2a4 4 0 0 1-4 4H3"></path><text x="12" y="14" font-size="8" fill="currentColor" text-anchor="middle">1</text>';
            } else {
                svg.innerHTML = '<polyline points="17 1 21 5 17 9"></polyline><path d="M3 11V9a4 4 0 0 1 4-4h14"></path><polyline points="7 23 3 19 7 15"></polyline><path d="M21 13v2a4 4 0 0 1-4 4H3"></path>';
            }
        }
    }
    
    // ═══════════════════════════════════════════════════════════════════════
    // MEDIA SESSION
    // ═══════════════════════════════════════════════════════════════════════
    
    updateMediaSession(track, cover) {
        if (!('mediaSession' in navigator)) return;

        navigator.mediaSession.metadata = new MediaMetadata({
            title: track.title,
            artist: track.artist || this.currentAlbum?.artist || 'Glenn Bennett',
            album: this.currentAlbum?.title || '',
            artwork: cover ? [
                { src: cover, sizes: '96x96', type: 'image/jpeg' },
                { src: cover, sizes: '128x128', type: 'image/jpeg' },
                { src: cover, sizes: '192x192', type: 'image/jpeg' },
                { src: cover, sizes: '256x256', type: 'image/jpeg' },
                { src: cover, sizes: '384x384', type: 'image/jpeg' },
                { src: cover, sizes: '512x512', type: 'image/jpeg' }
            ] : []
        });

        // Re-register action handlers on every track change.
        // iOS Safari may drop handlers when metadata changes.
        navigator.mediaSession.setActionHandler('play', () => {
            this.resumeOrRecover();
        });
        navigator.mediaSession.setActionHandler('pause', () => { this._pauseIsUserAction = true; this.audio.pause(); });
        navigator.mediaSession.setActionHandler('previoustrack', () => this.previousTrack());
        navigator.mediaSession.setActionHandler('nexttrack', () => this.nextTrack());
        try { navigator.mediaSession.setActionHandler('seekbackward', () => this.previousTrack()); } catch(e) {}
        try { navigator.mediaSession.setActionHandler('seekforward', () => this.nextTrack()); } catch(e) {}

        // Reset position — actual duration comes in loadedmetadata
        this.updateMediaSessionPosition();
    }

    updateMediaSessionPosition() {
        if (!('mediaSession' in navigator)) return;
        if (!this.audio.duration || !isFinite(this.audio.duration)) return;
        try {
            navigator.mediaSession.setPositionState({
                duration: this.audio.duration,
                playbackRate: this.audio.playbackRate,
                position: this.audio.currentTime
            });
        } catch (e) { /* ignore invalid state errors */ }
    }

    updateMediaSessionState() {
        if (!('mediaSession' in navigator)) return;
        navigator.mediaSession.playbackState = this.isPlaying ? 'playing' : 'paused';
    }

    // ═══════════════════════════════════════════════════════════════════════
    // SCREEN WAKE LOCK
    // ═══════════════════════════════════════════════════════════════════════

    async requestWakeLock() {
        if (!('wakeLock' in navigator)) return;
        if (this.wakeLock) return; // Already held
        try {
            this.wakeLock = await navigator.wakeLock.request('screen');
            this.wakeLock.addEventListener('release', () => { this.wakeLock = null; });
        } catch (e) {
            // Low battery, user preferences, or unsupported context — not critical
        }
    }

    releaseWakeLock() {
        if (!this.wakeLock) return;
        this.wakeLock.release();
        this.wakeLock = null;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // FAVORITES
    // ═══════════════════════════════════════════════════════════════════════
    
    async checkFavoriteStatus(songId) {
        if (!songId) {
            this.updateFavoriteBtn(false);
            return;
        }

        try {
            const res = await fetch(`${this.config.baseUrl}/player/is_favorite?song_id=${songId}`, {
                headers: { 'X-Device-Id': this.deviceId }
            });
            const data = await res.json();
            this.updateFavoriteBtn(data.success && data.is_favorite);
        } catch (e) {
            this.updateFavoriteBtn(false);
        }
    }

    async toggleFavorite() {
        const track = this.getCurrentTrack();
        if (!track) return;

        // Debounce rapid clicks
        if (this._togglingFavorite) return;
        this._togglingFavorite = true;

        try {
            const res = await fetch(`${this.config.baseUrl}/player/toggle_favorite`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Device-Id': this.deviceId
                },
                body: 'song_id=' + track.id
            });

            const data = await res.json();
            if (data.success) {
                this.toast(data.is_favorite ? 'Added to favorites' : 'Removed from favorites');
                this.updateFavoriteBtn(data.is_favorite);
                this.syncFavoritesOption();
                // If viewing Favorites and we just unfavorited, remove song from list
                if (!data.is_favorite && this.currentAlbum?.id === 'favorites') {
                    this.currentAlbum.songs = this.currentAlbum.songs.filter(s => s.id != track.id);
                    if (this.currentTrackIndex >= this.currentAlbum.songs.length) {
                        this.currentTrackIndex = Math.max(0, this.currentAlbum.songs.length - 1);
                    }
                    this.renderAlbum();
                }
            } else {
                this.toast('Could not update favorite');
            }
        } catch (e) {
            console.error('[MusicPlayer] Favorite toggle error:', e);
            this.toast('Could not update favorite');
        } finally {
            this._togglingFavorite = false;
        }
    }

    async syncFavoritesOption() {
        if (!this.elements.albumSelect) return;
        try {
            const res = await fetch(`${this.config.baseUrl}/player/favorites`, {
                headers: { 'X-Device-Id': this.deviceId }
            });
            const data = await res.json();
            const hasOption = this.elements.albumSelect.querySelector('option[value="favorites"]');
            if (data.success && data.favorites?.length > 0) {
                if (!hasOption) {
                    const opt = document.createElement('option');
                    opt.value = 'favorites';
                    opt.textContent = 'Favorites';
                    // Insert before Misc if it exists, otherwise append to end
                    const miscOpt = this.elements.albumSelect.querySelector('option[value="misc"]');
                    if (miscOpt) {
                        this.elements.albumSelect.insertBefore(opt, miscOpt);
                    } else {
                        this.elements.albumSelect.appendChild(opt);
                    }
                }
            } else if (hasOption) {
                hasOption.remove();
            }
        } catch (e) {
            // Silent - not critical
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
    // SERVER COMMUNICATION - Stats tracking
    // ═══════════════════════════════════════════════════════════════════════
    
    async recordPlay(songId) {
        // Store play start time for duration tracking
        this.playStartTime = Date.now();
        this.currentPlayId = null;
        
        try {
            const res = await fetch(`${this.config.baseUrl}/api/record_play`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Device-Id': this.deviceId
                },
                body: 'song_id=' + songId
            });
            const data = await res.json();
            if (data.success && data.play_id) {
                this.currentPlayId = data.play_id;
            }
        } catch (e) {
            // Silent fail - analytics not critical
        }
    }
    
    // Update play stats when track ends or changes
    async updatePlayStats() {
        if (!this.currentPlayId || !this.playStartTime) return;
        
        const listened = Math.round((Date.now() - this.playStartTime) / 1000);
        const duration = Math.round(this.audio.duration) || 0;
        
        try {
            await fetch(`${this.config.baseUrl}/api/update_play`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Device-Id': this.deviceId
                },
                body: `play_id=${this.currentPlayId}&listened=${listened}&duration=${duration}`
            });
        } catch (e) {
            // Silent fail
        }
        
        this.currentPlayId = null;
        this.playStartTime = null;
    }
    
    // ═══════════════════════════════════════════════════════════════════════
    // SHARING - With Facebook/Twitter compatibility
    // ═══════════════════════════════════════════════════════════════════════
    
    shareApp() {
        const url = location.origin;
        if (navigator.share) {
            navigator.share({ 
                title: 'Glenn Bennett Music', 
                text: 'Listen to original songs by Glenn Bennett', 
                url: url 
            }).catch(() => {});
        } else {
            this.copyToClipboard(url);
            this.toast('Link copied');
        }
    }
    
    shareAlbum() {
        if (!this.currentAlbum) return;
        
        // Use share endpoint for proper OG tags
        const shareUrl = location.origin + '/share/album/' + this.currentAlbum.id;
        
        if (navigator.share) {
            navigator.share({ 
                title: this.currentAlbum.title + ' - Glenn Bennett', 
                text: 'Listen to "' + this.currentAlbum.title + '" by Glenn Bennett', 
                url: shareUrl 
            }).catch(() => {});
        } else {
            // Show share options modal
            this.showShareOptions('album', this.currentAlbum.id, this.currentAlbum.title);
        }
    }
    
    shareCurrentTrack() {
        if (!this.currentAlbum?.songs?.length) return;
        const t = this.currentAlbum.songs[this.currentTrackIndex];
        
        // Use share endpoint for proper OG tags
        const shareUrl = location.origin + '/share/song/' + t.id;
        
        if (navigator.share) {
            navigator.share({ 
                title: t.title + ' - Glenn Bennett', 
                text: 'Listen to "' + t.title + '" by Glenn Bennett', 
                url: shareUrl 
            }).catch(() => {});
        } else {
            // Show share options modal
            this.showShareOptions('song', t.id, t.title);
        }
    }
    
    showShareOptions(type, id, title) {
        const shareUrl = location.origin + '/share/' + type + '/' + id;
        const directUrl = location.origin + '?' + type + '=' + id;
        const text = encodeURIComponent('Listen to "' + title + '" by Glenn Bennett');
        
        // Create share modal
        const modal = document.createElement('div');
        modal.className = 'share-modal';
        modal.innerHTML = `
            <div class="share-content">
                <h3>Share "${this.esc(title)}"</h3>
                <div class="share-buttons">
                    <button onclick="window.open('https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(shareUrl)}', '_blank', 'width=600,height=400')">
                        <svg viewBox="0 0 24 24" fill="#1877F2"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        Facebook
                    </button>
                    <button onclick="window.open('https://twitter.com/intent/tweet?url=${encodeURIComponent(shareUrl)}&text=${text}', '_blank', 'width=600,height=400')">
                        <svg viewBox="0 0 24 24" fill="#1DA1F2"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg>
                        Twitter
                    </button>
                    <button onclick="player.copyToClipboard('${directUrl}'); player.toast('Link copied'); this.closest('.share-modal').remove();">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                        Copy Link
                    </button>
                </div>
                <button class="share-close" onclick="this.closest('.share-modal').remove()">Cancel</button>
            </div>
        `;
        
        modal.onclick = (e) => { if (e.target === modal) modal.remove(); };
        document.body.appendChild(modal);
        
        // Add styles if not present
        if (!document.getElementById('shareModalStyles')) {
            const style = document.createElement('style');
            style.id = 'shareModalStyles';
            style.textContent = `
                .share-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.7); display: flex; align-items: center; justify-content: center; z-index: 1000; }
                .share-content { background: #1e293b; padding: 24px; border-radius: 16px; max-width: 320px; width: 90%; }
                .share-content h3 { margin: 0 0 16px; color: white; font-size: 18px; }
                .share-buttons { display: flex; flex-direction: column; gap: 12px; }
                .share-buttons button { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border: none; border-radius: 8px; background: #334155; color: white; font-size: 16px; cursor: pointer; }
                .share-buttons button:hover { background: #475569; }
                .share-buttons svg { width: 24px; height: 24px; }
                .share-close { width: 100%; margin-top: 16px; padding: 12px; border: 1px solid #475569; border-radius: 8px; background: transparent; color: #94a3b8; font-size: 16px; cursor: pointer; }
            `;
            document.head.appendChild(style);
        }
    }
    
    copyToClipboard(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).catch(() => {
                this.fallbackCopy(text);
            });
        } else {
            this.fallbackCopy(text);
        }
    }
    
    fallbackCopy(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        textarea.remove();
    }
    
    // ═══════════════════════════════════════════════════════════════════════
    // UTILITIES
    // ═══════════════════════════════════════════════════════════════════════
    
    formatTime(seconds) {
        if (!seconds || isNaN(seconds)) return '0:00';
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return mins + ':' + (secs < 10 ? '0' : '') + secs;
    }
    
    esc(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
    
    getPlaceholder(title) {
        const initial = (title || '?')[0].toUpperCase();
        return `<div class="album-cover placeholder">${initial}</div>`;
    }

    getStackedCovers(songs) {
        const covers = [];
        const seen = new Set();
        for (const s of songs) {
            if (s.cover_url && !seen.has(s.cover_url)) {
                seen.add(s.cover_url);
                covers.push(s.cover_url);
                if (covers.length >= 3) break;
            }
        }
        if (covers.length === 0) return this.getPlaceholder(this.currentAlbum?.title || '?');
        const count = covers.length;
        return `<div class="stacked-covers count-${count}">${covers.map(u => `<img src="${this.esc(u)}" alt="">`).join('')}</div>`;
    }
    
    toast(message) {
        const container = document.getElementById('toastContainer') || document.body;
        const toast = document.createElement('div');
        toast.className = 'toast show';
        toast.textContent = message;
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 2500);
    }
    
    autoplaySong(songId) {
        if (!this.currentAlbum?.songs?.length) return;

        const index = this.currentAlbum.songs.findIndex(s => s.id == songId);
        if (index < 0) return;

        const track = this.currentAlbum.songs[index];
        this.currentTrackIndex = index;

        // Load but don't play — user taps play when ready
        this.audio.src = track.stream_url;
        this.hasSource = true;
        this.audio.load();

        this.updateNowPlaying(track);
        this.showPlayerBar();
        this.expandPlayer();
    }

}
