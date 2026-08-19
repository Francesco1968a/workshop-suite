=== Workshop Suite — All-in-One Event Manager, CRM & Social Banner Builder ===
Contributors: francescoverolino
Tags: workshop, events, event management, crm, poster builder, social banners, masterclass
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The complete all-in-one suite for WordPress to manage workshops, courses and events, attendee CRM with communication tracking, and social banner builder.

== Description ==

**Workshop Suite** is an all-in-one event management, attendee CRM, and marketing creative suite designed for course creators, educators, trainers, agencies, academies, and event organizers across any industry.

Whether you run photography workshops, masterclasses, business seminars, art retreats, or online training, Workshop Suite provides the frictionless workflow to manage every aspect of your events.

### 🌟 Key Features

* **Event & Workshop Management**: Create and schedule single-day or multi-day workshops, define categories, maximum seats, pricing, and registration status.
* **Participant CRM**: Centralized attendee database with complete history, registration status (*Richiesta*, *Confermato*, *Abbandonato*), multi-person booking support, and custom attendee notes.
* **Interactive Communication Timeline**: Complete log of interactions with attendees (*Email sent*, *Replies received*, *Status updates*), backed by two-way IMAP/SMTP integration.
* **Smart T-15 Email Reminders**: Automated summary reminders dispatched 15 days before the event start date to confirmed participants.
* **Built-in Social Banner & Poster Studio**: In-browser graphic studio with real-time canvas rendering. Generate square (Instagram feed), portrait (Stories/Reels), and landscape (Facebook) promo posters with custom typography, background photo manipulation, and instant PNG export.
* **Anti-Spam & Rate Limiting Engine**: Native IP-based rate limiting and invisible honeypot protection to safeguard your registration endpoints against bot flooding.
* **Pure WordPress Native Admin**: Fast, clean interface respecting the official WordPress design system, lightweight and clutter-free.

== Installation ==

1. Upload the `workshop-suite` folder to the `/wp-content/plugins/` directory, or install the ZIP file via **Plugins > Add New > Upload Plugin**.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **Workshop Suite > Impostazioni** to configure your brand name, email settings (IMAP/SMTP for replies), and anti-spam preferences.
4. Create your first workshop under **Workshop Suite > Categoria e tipologia** and **Eventi | Partecipanti**.

== Privacy Policy & Third-Party Services ==

This plugin can optionally connect to an external cloud service:
* **Workshop Suite Global Hub** (https://workshopsuite.pro): Off by default. Only when the administrator explicitly activates the "Global Hub Sync" module, published workshop data is sent to the Workshop Suite global directory to increase event discovery, on every publish/update of an event. This includes: title, description/excerpt, dates and location (city, country, address), category, price and deposit, seat availability, the featured image, the booking page URL, and the organizer's public profile (name, role, bio, photo, website, languages) as configured in the plugin's own settings.
* **Terms of Service**: https://workshopsuite.pro/terms/
* **Privacy Policy**: https://workshopsuite.pro/privacy/

== Frequently Asked Questions ==

= Is this plugin only for photography workshops? =
No! While it was battle-tested by photographers, Workshop Suite is completely versatile and works for any type of course, masterclass, seminar, retreat, coaching program, or event.

= Do I need third-party forms or CRM plugins? =
Workshop Suite includes its own attendee intake engine and CRM. However, it also integrates seamlessly with Fluent Forms and WordPress native shortcodes.

= How does the poster generator work? =
The poster builder operates entirely client-side using the HTML5 Canvas API. It renders high-resolution graphics directly in your browser with zero server CPU overhead and exports crystal-clear PNG files.

== Screenshots ==

1. **Dashboard & Metric Overview**: Real-time cockpit showing active events, seat occupancy, and pending requests.
2. **Participant CRM**: Searchable attendee directory with contact info and booking history.
3. **Poster & Banner Builder**: 3-column layout with customizable typography, colors, background zoom/positioning, and template saving.
4. **Settings & Security**: Native configuration panel with AES-256 encrypted mail credentials and rate limit protection.

== Changelog ==

= 1.0.0 =
* Initial public release on WordPress.org.
* All-in-one Event Manager with category and format configuration.
* Comprehensive Attendee CRM with full interaction timeline.
* HTML5 Canvas Poster & Social Banner Builder with custom font rendering.
* Automated T-15 email reminders with WP Timezone synchronization.
* Configurable IP rate limiting and honeypot protection.
* Clean native WordPress Admin interface.
