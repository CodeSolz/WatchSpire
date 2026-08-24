=== WatchSpire – Form, Email & Uptime Monitor with Failure Alerts ===
Contributors: codesolz, m.tuhin
Tags: contact form, email delivery, uptime monitor, broken links, server errors
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Catch silent WordPress failures before they cost you leads, traffic, or trust.

== Description ==

A WordPress site doesn't have to be completely down to be broken.

Your **contact form not sending email**, **form submissions not arriving**, an approaching **SSL expiry**, sudden **404 errors**, or unexpected **site downtime** can quietly cost you leads and customers long before anyone reports the problem.

**WatchSpire is your WordPress early-warning system.**

It monitors form delivery, email health, SSL certificate expiry, uptime, 404 and server errors, broken links, site changes, and AI crawler activity — all from one dashboard.

**WatchSpire watches the signals that matter and tells you when something isn't right.**

Instead of checking several plugins, logs, and dashboards, WatchSpire brings your site's operational health into one place — helping you spot problems earlier and understand what is happening across your WordPress site.

### One dashboard. The signals that matter.

* **Form Delivery Monitoring** — know when real form submissions fail to send.
* **Submission Gap Detection** — spot unusual periods when forms suddenly stop receiving leads.
* **Mail Health & Risk Score** — detect mail failures, SMTP configuration problems, and sender-domain mismatches.
* **SSL Certificate Expiry** — get advance warnings before your certificate expires or stops covering your hostname.
* **404 & Server Error Monitoring** — identify broken URLs and server failures before they become widespread problems.
* **Uptime Self-Checks** — monitor your homepage and important pages for errors and abnormal responses.
* **Broken Links & Images** — scan safely in small background batches without hammering your server.
* **WordPress Change Log** — see plugin, theme, core, and important configuration changes around the time an issue appeared.
* **AI Crawler Activity** — discover when GPTBot, ClaudeBot, PerplexityBot, and other AI crawlers visit your site.
* **Weekly Health Summary** — get a concise overview of what happened on your site without living inside the WordPress dashboard.

### Built for WordPress, not around it

WatchSpire is designed to stay lightweight. Heavy scans run in controlled background batches, link scanning is disabled until you enable it, and monitoring data stays in your own WordPress database.

There are no artificial limits on the number of supported forms, pages, or links you can monitor.

### Privacy-first by design

WatchSpire does not send your monitoring history, form submissions, customer information, or usage analytics to us.

Form field contents are never stored. Only operational metadata such as delivery status, form ID, timestamps, response codes, and aggregated counts are recorded.

WatchSpire does not contact any third-party service. Its checks talk to your own site, and — only if you turn on broken link scanning — to the URLs your own content already links to.

### Form delivery monitoring

Forms can fail silently.

A visitor can successfully press **Submit**, see a success message, and leave — while the notification email never reaches your mail system.

WatchSpire listens to real submission and mail outcomes from supported form plugins and records whether delivery succeeded or failed.

Supported form builders include:

* Contact Form 7
* WPForms
* Gravity Forms
* Elementor Pro Forms
* Fluent Forms
* Forminator

When a supported form reports a delivery failure, WatchSpire can alert you immediately.

**No field contents are stored. No customer messages are recorded.**

Only the information required to understand the health of the form is retained.

### Know when a form suddenly goes quiet

Not every form problem produces an error.

Sometimes leads simply stop arriving.

WatchSpire builds a submission baseline for each form and can detect when a normally active form experiences an unusual period of silence.

It doesn't treat every form the same.

A contact form that normally receives five submissions a day and a quotation form that receives one submission a week have completely different normal patterns.

WatchSpire waits until enough history exists before evaluating submission gaps, helping reduce noisy or meaningless alerts.

### SSL certificate expiry monitoring

An expired certificate can take an otherwise healthy website offline.

WatchSpire reads your site's own certificate directly and monitors:

* SSL certificate validity
* Certificate expiration
* Hostname coverage
* Certificate issuer

Certificate warnings can be raised before expiration at:

* 30 days
* 14 days
* 7 days
* 1 day

The certificate is read straight from your own server over TLS. No third-party lookup service is involved.

### Mail health & risk score

WordPress email can appear to work until the day it doesn't.

WatchSpire looks for common mail configuration risks including:

* WordPress relying on PHP `mail()`
* No SMTP plugin detected
* `wp_mail()` failures
* From-address and site-domain mismatches
* Other mail configuration warning signs

WatchSpire can also perform a scheduled test send to an email address you configure.

WatchSpire tells you whether WordPress successfully handed the message to its configured mail system.

### 404 & server error monitoring

A few broken URLs may be harmless.

A sudden increase can mean something important changed.

WatchSpire aggregates HTTP errors so your database isn't filled with thousands of duplicate rows.

It can monitor:

* 404 Not Found errors
* 5xx server errors
* Repeated errors affecting the same URL
* Error frequency and recent activity

Known bot noise and common junk requests can be filtered so you can focus on problems affecting real pages.

### Uptime self-checks

WatchSpire checks your homepage and a configurable important page from inside WordPress.

It can detect:

* 404 responses
* 500 responses
* Slow responses
* Soft 404 pages
* Maintenance-mode problems
* Fatal-error or broken-page responses

This gives you another signal when an important page stops behaving normally.

**Important:** because WatchSpire runs on the website itself, it cannot alert you from that same server if the entire server becomes unreachable.

### Broken links & images — without hammering your server

Broken-link scanners have a reputation for consuming server resources.

WatchSpire is deliberately conservative.

Scanning is:

* **Off by default**
* Enabled only when you choose
* Processed in small background batches
* Pausable
* Resumable
* Cancellable
* Automatically adjusted for constrained hosting environments

WatchSpire checks links and images in supported content sources and gives you clear results with recheck and ignore controls.

Large scans continue in the background instead of trying to process an entire website in one request.

### See what changed before the problem appeared

A site starts failing.

The next question is usually:

**What changed?**

WatchSpire keeps a timeline of important WordPress activity including:

* Plugin updates
* Plugin activations
* Plugin deactivations
* Plugin deletions
* Theme changes
* WordPress core updates
* Auto-updates
* Selected important settings changes

When an issue appears, you can compare it with recent site changes instead of trying to remember what happened hours or days earlier.

### See which AI crawlers are visiting your site

AI crawlers are becoming another part of how websites are discovered and consumed.

WatchSpire can identify activity from supported crawlers including:

* GPTBot
* ChatGPT-User
* OAI-SearchBot
* ClaudeBot
* Claude-User
* Claude-SearchBot
* PerplexityBot
* Perplexity-User
* Google-Extended
* CCBot
* Bytespider
* Amazonbot
* Applebot-Extended
* Meta external agents
* And more

WatchSpire records compact daily aggregates instead of creating one database row for every request.

You can see:

* Which AI crawlers visited
* How many requests they made
* Successful responses
* 404 responses
* 5xx responses

WatchSpire can also inspect your `robots.txt` rules to help you understand which supported AI crawlers are currently allowed or blocked.

### More than an uptime monitor

A green homepage does not necessarily mean a healthy WordPress site.

Your homepage can return `200 OK` while:

* Your contact form stops delivering leads
* WordPress email fails
* Important pages return errors
* Your SSL certificate approaches expiration
* Images disappear
* Links break
* A plugin update changes important functionality
* A normally active form suddenly stops receiving submissions

Traditional uptime tools mainly answer:

**"Is the website responding?"**

WatchSpire helps you answer a more important question:

**"Is my WordPress site actually healthy?"**

### No limits, no trial

WatchSpire is fully functional. There is no trial period, no license key, and no locked feature.

You are not restricted to:

* One form
* A handful of pages
* Five broken links
* A time-limited trial
* A monitoring quota

Monitor as many supported forms, pages, and links as your site reasonably needs.

== Installation ==

1. Install WatchSpire from **Plugins → Add New** inside WordPress, or upload it to `/wp-content/plugins/watchspire`.
2. Activate WatchSpire.
3. Follow the quick setup wizard.
4. Confirm the email address where you want to receive alerts.
5. Choose the monitors you want enabled.
6. Optionally enable broken-link and image scanning.
7. Open **WatchSpire** from your WordPress admin menu.

The onboarding process is skippable and does not prevent you from using the plugin.

== Frequently Asked Questions ==

= Does WatchSpire slow down my website? =

WatchSpire is designed specifically to minimize front-end impact.

Heavy work runs as scheduled background tasks instead of being performed during normal page loads.

Broken-link scanning is disabled by default and uses small resumable batches when enabled. WatchSpire can also reduce scanning workloads automatically on hosting environments with lower memory or execution-time limits.

= Does WatchSpire store my form submissions? =

No.

WatchSpire stores only the operational information required for monitoring, such as the form integration, form ID, delivery outcome, error state, and timestamp.

**Form field contents and customer personal data are never stored.**

= Can WatchSpire tell whether an email reached the inbox? =

WatchSpire detects supported mail failures and whether WordPress successfully handed a message to its configured mail system.

That is different from confirming that the message reached a recipient's inbox, which WatchSpire does not do.

= What data leaves my website? =

Nothing is sent to WatchSpire, to CodeSolz, or to any other third-party service. WatchSpire has no external API, no license check, and no analytics.

Your monitoring history stays in your own WordPress database.

The only outbound HTTP requests WatchSpire makes are to your own site (the uptime self-check and your `robots.txt`), and — only if you turn on broken link scanning — to the URLs your own content already links to, in order to see whether they still respond.

= Can WatchSpire detect when my entire website is offline? =

No, and no plugin running on that same server can.

When the server itself is offline, WordPress cannot execute code and therefore cannot send an alert from that same server.

WatchSpire provides internal uptime self-checking, which catches errors, slow responses, soft 404s, and maintenance lockouts on a server that is still running. Pair it with an external uptime service of your choice for true outside-in downtime detection.

= Which form plugins does WatchSpire support? =

WatchSpire supports monitoring integrations for:

* Contact Form 7
* WPForms
* Gravity Forms
* Elementor Pro Forms
* Fluent Forms
* Forminator

WatchSpire detects which supported form plugins are active and displays the relevant integrations.

= Does WatchSpire create fake form submissions? =

No.

WatchSpire only observes real form activity. It never submits your forms for you.

= Does link scanning start automatically? =

No.

Broken-link and image scanning is deliberately disabled by default.

You decide whether to enable it.

This prevents unexpected workloads on large websites and shared hosting environments.

= Does WatchSpire track me? =

No. WatchSpire performs no usage analytics and no tracking of any kind.

= Is WatchSpire a SaaS service? =

No.

WatchSpire runs entirely inside your WordPress installation and stores its monitoring data in your own WordPress database.

There is no WatchSpire cloud platform, and no account is required.

= Will WatchSpire flood my inbox with repeated alerts? =

WatchSpire includes alert deduplication and rate limiting.

Repeated identical failures can be grouped instead of generating an email every time the same problem occurs.

WatchSpire can also send a recovery notification when a failing monitor returns to a healthy state.

= How long does WatchSpire keep monitoring data? =

WatchSpire uses a default monitoring-data retention period of 30 days.

Data and privacy controls are available from WatchSpire settings.

== Screenshots ==

1. **WatchSpire Dashboard** — see site health, monitor status, recent failures, checks, response times, submissions, 404s, WordPress changes, and AI crawler activity.
2. **Monitors Status** — view every active, warning, failing, or paused monitor with its most recent result.
3. **Broken Links & Images** — scan content in controlled background batches with progress, filters, pause, resume, recheck, and ignore actions.
4. **Change Log** — review WordPress core, plugin, theme, user, and important configuration changes.
5. **Settings** — manage monitors, alerts, scanning, scheduling, retention, privacy, and other WatchSpire preferences.

== Changelog ==

= 1.0.0 =

* Added an "Upgrade to Pro" item to the WatchSpire menu and to the plugin's row on the Plugins screen, pointing at the separately distributed WatchSpire Pro add-on. Both disappear automatically once that add-on is active. No WatchSpire feature depends on it.
* Added Dashboard and Settings shortcuts, plus Support and rating links, to the plugin's row on the Plugins screen.
* Added two dismissible admin notices, shown only on other admin screens and never on WatchSpire's own: a welcome pointer after activation, and a review request once WatchSpire has been installed for two weeks. The review notice offers "I'll do it later" and "Please don't bother me again", and the latter is remembered permanently.
* Added extension points so companion add-ons can extend the dashboard: `watchspire_dashboard_allowed_ranges` (add further look-back windows), `watchspire_dashboard_custom_period` and `watchspire_dashboard_custom_range_ui` (supply a different custom date-range control), and `watchspire_dashboard_crawler_breakdown` (supply a different AI-crawler breakdown).
* All of these are additive only. The 7, 30, and 90-day ranges, the custom start/end date range, and the per-bot AI crawler breakdown remain fully available in WatchSpire itself with no add-on installed, and no add-on can remove them.
* Fixed admin notices being injected into the middle of WatchSpire's page headers. Each screen now carries an accessible page heading that WordPress uses as its notice anchor, so core, plugin, and update notices appear above the page chrome instead of inside the header row.
* Fixed the WP-Cron reliability notice rendering full-bleed at the very top of the admin page, above other plugins' notices. It now appears inside the WatchSpire screen it refers to, directly under the page header.
* Added `AdminMenu::render_own_notices()` as a public helper so a screen that lays out its own page chrome can place WatchSpire's notice explicitly. WatchSpire's own screens continue to let WordPress print all admin notices in their standard position.

= 0.1.0 =

* Initial WatchSpire release.
* Added site-health monitoring dashboard.
* Added SSL certificate expiry monitoring.
* Added mail-health monitoring.
* Added form delivery monitoring for supported form builders.
* Added submission-gap detection.
* Added 404 and server-error monitoring.
* Added homepage and key-page uptime self-checks.
* Added optional broken-link and image scanner.
* Added WordPress change log.
* Added AI crawler activity monitoring.
* Added alert deduplication and recovery notifications.
* Added weekly health summaries.
* Added monitoring-data retention and privacy controls.

== Upgrade Notice ==

= 1.0.0 =

First stable release. Adds Plugins-screen shortcuts, dismissible welcome and review notices, and developer extension points for companion add-ons. No existing functionality changes.


== External services ==

WatchSpire does not use, connect to, or depend on any third-party or external service.

It has no API of its own, performs no license check, sends no analytics, and transmits no data to WatchSpire, to CodeSolz, or to anyone else. All monitoring data is created and stored in your own WordPress database.

For completeness, these are the only outbound HTTP requests the plugin can make, and all of them are to your own site or to addresses your own content already points at:

**1. Uptime self-check — your own site**

WatchSpire requests your own homepage and the optional key page you configure, in order to record the HTTP status code and response time. The request goes to your own server. Nothing is sent anywhere else.

**2. robots.txt read — your own site**

WatchSpire reads your own `/robots.txt` so it can show which AI crawlers you currently allow or block. The request goes to your own server.

**3. Broken link & image scanning — URLs your own content links to (off by default)**

This feature is disabled until you explicitly enable it in **WatchSpire → Settings → Scanning**.

Once enabled, WatchSpire sends an HTTP HEAD (falling back to GET) request to each link and image URL found in your own content, in order to record whether that URL still responds. Where a link points to another website, the request naturally reaches that website — exactly as a visitor clicking the link would. Only the URL already published in your content is requested; no data about you, your site, or your visitors is added to it.

The SSL certificate check is not an external service either: WatchSpire opens a direct TLS connection to your own hostname and reads the certificate your own server presents.
