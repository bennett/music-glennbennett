# MediaSession / CarPlay / Lock Screen

How CarPlay, lock screen controls, and background playback recovery work.

---

## CarPlay / Lock Screen (MediaSession)

- Uses the MediaSession API to show album art, title, and artist on lock screen and CarPlay
- **Previous/Next track**: registered as `previoustrack` / `nexttrack` handlers
- **Seek backward/forward**: Mapped to `previousTrack()` / `nextTrack()` so pressing them skips tracks
- **IMPORTANT**: All action handlers (play, pause, previoustrack, nexttrack, seekbackward, seekforward) must be re-registered every time track metadata changes. iOS Safari drops handlers when `navigator.mediaSession.metadata` is updated. The handlers are registered in both `setupMediaSession()` (init) and `updateMediaSession()` (every track change).
- **Position state**: `setPositionState()` is called with duration/position so iOS knows the track length
- **Artwork**: Multiple sizes provided (96-512px) for different display contexts (lock screen, Control Center, CarPlay)

---

## Background Playback Recovery

- When the page becomes visible again (`visibilitychange` event), the player calls `handleResume()`
- `handleResume()` re-registers MediaSession, syncs the play/pause state with the actual audio element state, and recovers stalled audio if needed
- `recoverPlayback()` saves the current position, reloads the audio source, and resumes from where it left off
