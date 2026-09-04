=== WSMaker — Training Management System, CRM & Event Manager ===
Contributors: frak68
Tags: training management system, workshop, events, event management, crm
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.1.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A full Training Management System (TMS) for WordPress: workshop and event scheduling, attendee CRM with communication tracking, and a social banner builder.

== Description ==

**WSMaker** is a full Training Management System (TMS) — not just a CRM, not just an LMS. It combines event and workshop scheduling, an attendee CRM with two-way communication tracking, and a marketing creative suite in one plugin, designed for course creators, educators, trainers, agencies, academies, and event organizers across any industry.

Full, human-readable source code (including the Vue.js source behind the compiled admin panel bundles in assets/dist/, under `admin-src/`) is publicly available at: [github.com/Francesco1968a/wsmaker](https://github.com/Francesco1968a/wsmaker) — to rebuild a bundle: `cd admin-src && npm install && WS_BUNDLE=<bundle-name> npx vite build` (bundle names match the `assets/dist/*.js` filenames).

Developed by [Francesco Verolino](https://francescoverolino.com/wsmaker)

Whether you run photography workshops, masterclasses, business seminars, art retreats, or online training, WSMaker provides the frictionless workflow to manage every aspect of your events.

### 🌟 Key Features

* **Event & Workshop Management**: Create and schedule single-day or multi-day workshops, define categories, maximum seats, pricing, and registration status.
* **Participant CRM**: Centralized attendee database with complete history, registration status (*Requested*, *Confirmed*, *Cancelled*), multi-person booking support, and custom attendee notes.
* **Interactive Communication Timeline**: Complete log of interactions with attendees (*Email sent*, *Replies received*, *Status updates*), backed by two-way IMAP/SMTP integration.
* **Smart T-15 Email Reminders**: Automated summary reminders dispatched 15 days before the event start date to confirmed participants.
* **Built-in Social Banner & Poster Studio**: In-browser graphic studio with real-time canvas rendering. Generate square (Instagram feed), portrait (Stories/Reels), and landscape (Facebook) promo posters with custom typography, background photo manipulation, and instant PNG export.
* **Anti-Spam & Rate Limiting Engine**: Native IP-based rate limiting and invisible honeypot protection to safeguard your registration endpoints against bot flooding.
* **Pure WordPress Native Admin**: Fast, clean interface respecting the official WordPress design system, lightweight and clutter-free.

== Installation ==

1. Upload the `wsmaker` folder to the `/wp-content/plugins/` directory, or install the ZIP file via **Plugins > Add New > Upload Plugin**.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **WSMaker > Settings** to configure your brand name, email settings (IMAP/SMTP for replies), and anti-spam preferences.
4. Create your first workshop under **WSMaker > Categories & Types** and **Events & Registrations**.

== External services ==

This plugin connects to the following external services:

* **WSMaker Global Hub — event syndication** ([wsmaker.pro](https://wsmaker.pro)): Off by default. Only when the administrator explicitly activates the "Global Hub Sync" module, published workshop data is sent to the WSMaker global directory to increase event discovery, on every publish/update of an event. This includes: title, description/excerpt, dates and location (city, country, address), category, price and deposit, seat availability, the featured image, the booking page URL, and the organizer's public profile (name, role, bio, photo, website, languages) as configured in the plugin's own settings.
  Terms of Service: [wsmaker.pro/terms](https://wsmaker.pro/terms/) — Privacy Policy: [wsmaker.pro/privacy-policy](https://wsmaker.pro/privacy-policy/)

* **WSMaker Global Hub — shared category list** ([wsmaker.pro](https://wsmaker.pro)): Used to keep the "Categories & Types" dropdown suggestions in sync with a shared, curated taxonomy list maintained centrally, so every installation offers the same well-organized set of workshop categories/event types instead of each site inventing its own. This is a one-way, anonymous GET request (no site URL, email, or identifying data is sent) that fires at most once every 24 hours (cached) when an admin opens that screen. If the service is unreachable, the plugin falls back to a small built-in default list — nothing else in the plugin depends on this call succeeding.
  Terms of Service: [wsmaker.pro/terms](https://wsmaker.pro/terms/) — Privacy Policy: [wsmaker.pro/privacy-policy](https://wsmaker.pro/privacy-policy/)

* **WSMaker PRO — license verification** ([api.francescoverolino.com](https://api.francescoverolino.com)): Only called when the administrator manually submits a PRO license key in WSMaker → Settings → License. Sends the entered license key and the site's home URL to verify the license and unlock PRO features; no other data is transmitted, and nothing is sent unless the admin actively submits that form.
  Privacy Policy: [francescoverolino.com/privacy](https://francescoverolino.com/privacy/)

* **ip-api.com — IP geolocation for the admin notification email** ([ip-api.com](http://ip-api.com)): Off by default. Only when the administrator explicitly enables "IP Geolocation" in Settings → General, the submitting visitor's IP address is sent to ip-api.com's free lookup endpoint after a new registration request, to add a city/country hint to the admin notification email. The result is cached for 7 days per IP. Nothing is sent unless this setting is turned on. The administrator can replace ip-api.com with their own geolocation provider's URL in the same settings section (e.g. for a service with commercial-use terms, or higher volume) — in that case this plugin instead contacts whatever URL the administrator has configured.
  Terms of Service: [ip-api.com/docs/legal](https://ip-api.com/docs/legal) — Privacy Policy: [ip-api.com/docs/legal](https://ip-api.com/docs/legal)

* **Jitsi Meet** ([meet.jit.si](https://meet.jit.si)): Only loaded on the "Virtual Classroom" shortcode output for an event whose organizer has selected "Jitsi Meet" as the virtual platform and left the link field empty (an automatically-generated public meet.jit.si room is used) or entered a meet.jit.si link. The visitor's browser loads Jitsi's external_api.js script directly from meet.jit.si and connects to Jitsi's public video-conferencing service to join the room; standard data shared with any Jitsi Meet session applies (see Jitsi's own policies). No data is sent unless a visitor actually opens a page containing an event configured for Jitsi.
  Terms of Service: [jitsi.org/meet-jit-si-terms-of-service](https://jitsi.org/meet-jit-si-terms-of-service/) — Privacy Policy: [jitsi.org/meet-jit-si-privacy](https://jitsi.org/meet-jit-si-privacy/)

== Frequently Asked Questions ==

= Is this plugin only for photography workshops? =
No! While it was battle-tested by photographers, WSMaker is completely versatile and works for any type of course, masterclass, seminar, retreat, coaching program, or event.

= Do I need third-party forms or CRM plugins? =
WSMaker includes its own attendee intake engine and CRM. However, it also integrates seamlessly with Fluent Forms and WordPress native shortcodes.

= How does the poster generator work? =
The poster builder operates entirely client-side using the HTML5 Canvas API. It renders high-resolution graphics directly in your browser with zero server CPU overhead and exports crystal-clear PNG files.

== Screenshots ==

1. **Dashboard & Metric Overview**: Real-time cockpit showing active events, seat occupancy, and pending requests.
2. **Participant CRM**: Searchable attendee directory with contact info and booking history.
3. **Poster & Banner Builder**: 3-column layout with customizable typography, colors, background zoom/positioning, and template saving.
4. **Settings & Security**: Native configuration panel with AES-256 encrypted mail credentials and rate limit protection.

== Changelog ==

= 1.1.6 =
* Full source-string i18n overhaul: the plugin's UI was hardcoded in Italian despite the public listing being in English. Flipped the PHP source strings to English (Italian now ships as a proper translation), and built i18n infrastructure for the Vue admin panels from scratch (they previously had none) — all 15 Vue panels plus the vanilla-JS Poster Templates screen now translate.
* Added 4 new complete translations: Spanish (es_ES), French (fr_FR), German (de_DE), and Brazilian Portuguese (pt_BR) — alongside the existing Italian (it_IT).
* Fixed 27 admin menu labels in the Italian translation that had a translated-looking but untranslated string (`msgstr` identical to the English `msgid`), so they silently stayed in English even with the Italian locale active.
* Repositioned the plugin as a Training Management System (TMS): updated title, tags, and description.
* Fixed several stale/broken references in this readme (old `workshop-suite` folder name, Italian menu labels, non-clickable bare URLs).

= 1.1.5 =
* Renamed the last remaining short-prefixed identifiers found by review: two shortcode tags (`[eventi_categoria]`→`[wsma_eventi_categoria]`, `[workshop_prossimo]`→`[wsma_workshop_prossimo]`), a transient cache key, an admin menu slug, and the Webhooks module's form/nonce action names — all now under the `wsma_`/`wsma-` namespace, no leftover `ws_`/`ws-` prefixes.
* Existing published pages using the two old shortcode tags were migrated to the new tags directly (no alias layer needed going forward).

= 1.1.4 =
* Removed the Custom CSS admin feature from this (free) plugin — arbitrary CSS/code insertion is no longer permitted per WordPress.org guidelines. (The equivalent feature remains available only in the separately-distributed PRO add-on, not reviewed by WordPress.org.)
* Disclosed the Jitsi Meet external service (used only by the optional virtual-classroom shortcode) and documented the plugin's non-compiled JS source location in the readme.
* Switched an unescaped HTML echo (`[ws_acquista]` connector output) to `wp_kses_post()`.
* Renamed the remaining short (`ws`/`wv`) prefixed global identifiers to the `wsma`/`WSMA` namespace: custom post types (`ws_evento`→`wsma_evento`, `ws_partecipante`→`wsma_partecipante`, `ws_iscrizione`→`wsma_iscrizione`), taxonomy (`categoria_evento`→`wsma_categoria_evento`), the license option/settings group, the Hub Sync legacy-backfill flag, and two internal options. A one-time migration on upgrade moves existing data to the new post type/taxonomy names automatically — no re-entry needed.
* Shortcode tags (`[ws_form_iscrizione]`, `[ws_acquista]`, `[ws_prezzo]`, `[ws_acconto]`, `[ws_proponente]`, `[ws_workshop_page]`, `[ws_aula_virtuale]`, `[ws_workshop_text]`) now have `wsma_`-prefixed primary equivalents; the original tags remain registered as permanent aliases so pages built before this update keep working unchanged.

= 1.1.3 =
* Fixed `[ws_form_iscrizione]` silently failing to submit whenever a page embeds more than one instance of the form (e.g. one inline plus one per date inside a "Request info" modal). The frontend script relied on `getElementById()` against a non-unique ID, so only the first instance on the page ever got its submit handler attached — every other instance fell back to a native browser form submission (page reload, no data sent, no error shown). Now every instance is wired up independently via its shared CSS classes.
* The admin notification email for a new registration request now includes the city and number of attendees the visitor entered (previously silently omitted).
* New optional setting (Settings → General, off by default): add a city/country hint to that same email, looked up from the visitor's IP via ip-api.com.

= 1.1.2 =
* Renamed the plugin from Workshop Suite to WSMaker (trademark conflict resolution requested by the WordPress.org review team) — new text domain `wsmaker`, updated branding throughout.
* Renamed all internal PHP class/interface/constant names and custom hook names from the `WS_`/`ws_` prefix to `WSM_`/`wsm_` for a properly unique namespace (no functional change; a corresponding update ships in the PRO add-on to keep both in sync).
* Switched all `date()`/`parse_url()` calls to their WordPress-safe equivalents (`current_time()`, `wp_date()`, `wp_parse_url()`) for correct timezone handling across installs.
* Hardened output escaping across admin screens and shortcodes; gated remaining debug `error_log()` calls behind `WP_DEBUG`.
* Fixed the Global Hub Sync module silently failing to fire since the `evento` → `ws_evento` post type rename (stale hook name).

= 1.1.0 =
* Category price and deposit fields, with `[ws_prezzo]`/`[ws_acconto]` shortcodes.
* Payment status tracking on registrations (pending / deposit paid / paid in full).
* Category price/deposit prefill in the attendee intake form.
* Category-scoped date filtering for the registration form shortcode.
* Category and event geo-location fields (city, country, address), with automatic fallback from event to category and syndication to the Global Hub.
* Full Vue 3 source rewrite of every admin panel (Calendario, Archivio, Messaggi, Riepilogo, Scheda Partecipante, Anagrafica, Ringraziamento, Dashboard) — no more editing minified bundles by hand.
* Monthly calendar view added to the Calendario panel, alongside the existing subscription/ICS card.
* Restored the frontend dark/light theme switch for shortcode-embedded admin panels (Settings → Default Frontend Theme); wp-admin's own pages are unaffected.
* New `[workshop_categorie]` shortcode for a dedicated frontend Categorie/Tipologia panel, alongside the existing `[workshop_admin]` (Eventi/Partecipanti).
* Removed several hundred lines of dead code (an abandoned course-access subsystem, an unreachable Locandine prototype, a handful of orphaned helper functions).
* Ongoing rebrand cleanup and internal hardening.

= 1.0.0 =
* Initial public release on WordPress.org.
* All-in-one Event Manager with category and format configuration.
* Comprehensive Attendee CRM with full interaction timeline.
* HTML5 Canvas Poster & Social Banner Builder with custom font rendering.
* Automated T-15 email reminders with WP Timezone synchronization.
* Configurable IP rate limiting and honeypot protection.
* Clean native WordPress Admin interface.
