# Overview

**Bennett Music Player** — A streaming music app for Glenn Bennett's original music.
**URL**: https://music.glennbennett.com
**Last Updated**: 2026-02-25

---

## Core Principles

### Source of Truth
**MP3 files are the source of record for all song and album-related information.**

- Song title — from MP3 ID3 tag
- Artist — from MP3 ID3 tag
- Album name — from MP3 ID3 tag
- Track number — from MP3 ID3 tag
- Year — from MP3 ID3 tag
- Embedded cover art — from MP3 ID3 tag

The database stores this information for performance, but the MP3 files are authoritative. If there's a conflict, the MP3 file is correct.

### Design Philosophy
1. **Simple over complex** - Don't add layers of abstraction
2. **Files are truth** - Database reflects files, not vice versa
3. **Flexible matching** - Accommodate variations in naming
4. **Fail gracefully** - Missing data shouldn't break the system

---

## Tech Stack

- **Framework**: CodeIgniter 3.x (PHP 8.1)
- **Database**: MySQL/MariaDB
- **Frontend**: Vanilla JavaScript, CSS
- **PWA**: Service Worker for offline capability
- **CDN**: Bunny CDN for media delivery
- **Hosting**: Laravel Herd (local dev), production at `music.glennbennett.com`
