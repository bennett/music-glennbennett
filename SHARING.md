# Share Images & Social Sharing

## How Facebook Sharing Works
When a URL is shared on Facebook, the Facebook crawler fetches the page and reads OG meta tags. These tags tell Facebook what title, description, and image to show in the post. The crawler does NOT execute JavaScript, so all OG data must be in the server-rendered HTML.

## Two Share Paths
1. **Share via app buttons** (Share Song/Share Album): Uses `/share/song/{id}` or `/share/album/{id}` — these are dedicated redirect pages with full OG tags. Crawlers read the tags; humans get redirected to the player.
2. **Direct URL sharing** (user copies/pastes the player URL): Uses the main player page which also has OG tags server-rendered based on `$autoplay_song_id` and `$current_album`.

## Open Graph Meta Tags
Both the player page and the share redirect pages include these required OG tags:
- `og:type` — Always `music.song`
- `og:title` — Song/album title + "Glenn Bennett", or "Glenn Bennett Music" for generic
- `og:description` — "Listen to [title] by Glenn L. Bennett", or generic description
- `og:image` — Points to `/share/image?song={id}` or `/share/image?album={id}` or `/share/image` for generic
- `og:image:width` — 1200
- `og:image:height` — 630
- `og:url` — Canonical URL
- `og:site_name` — "Glenn Bennett Music"
- Twitter Card tags are also included (`twitter:card`, `twitter:title`, `twitter:description`, `twitter:image`)

## Share Image Generation
- Endpoint: `/share/image?song={id}` or `/share/image?album={id}` or `/share/image` (generic)
- Generates 1200x630 JPEG using GD library
- Layout: album cover art on left side, title/artist text on right
- Falls back to album cover if song-specific cover not found
- Falls back to placeholder (music note icon) if no cover art found
- Falls back to simple text-only image if GD or fonts fail
- Fonts required: `PlayfairDisplay-Black.ttf`, `Montserrat-Regular.ttf` in `/assets/fonts/`
- If fonts are missing, uses built-in GD bitmap fonts (uglier but functional)
- Error handling: try/catch with `log_message()` — errors are logged, not suppressed

## Share Redirect Page
- Endpoint: `/share/song/{id}` or `/share/album/{id}`
- View: `application/views/share/og_redirect.php`
- HTML page with full OG tags + immediate redirect to player
- Facebook/Twitter crawlers read OG tags from this page
- Human visitors are redirected via `<meta http-equiv="refresh">` and JavaScript

## Native Share (navigator.share)
- Uses the `/share/song/{id}` URL so the shared link has proper OG tags
- Falls back to share options modal (Facebook/Twitter/Copy Link) if navigator.share unavailable

## Testing Facebook Share Images
1. **Check OG tags locally**: View page source of `http://music.test/share/song/{id}` — verify og:title, og:description, og:image are present
2. **Test image generation locally**: Visit `http://music.test/share/image?song={id}` — should render a JPEG image
3. **Use built-in debug tool**: Visit `http://music.test/share/test_image/{song_id}` — shows config paths, cover art status, font status, and the generated image
4. **Use built-in debug tool**: Visit `http://music.test/share/debug/{song_id}` — shows song data, cover URLs, and fetch tests
5. **Test on production**: After deploying, use the [Facebook Sharing Debugger](https://developers.facebook.com/tools/debug/) — paste the share URL and click "Debug". This shows exactly what Facebook sees.
6. **Scrape fresh**: If Facebook has cached old data, click "Scrape Again" in the debugger to force a re-fetch
7. **Check for errors**: In the debugger, look for warnings about missing tags or inaccessible images
