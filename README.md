<p align="center">
    <a href="https://wordpress.org/plugins/watchspire">
        <img src="https://ps.w.org/watchspire/assets/icon-128x128.png" alt="Watchspire"/>
    </a>
</p>

<p align="center">
    <img alt="GitHub last commit" src="https://img.shields.io/github/last-commit/CodeSolz/watchspire">
    <img alt="GitHub code size in bytes" src="https://img.shields.io/github/languages/code-size/CodeSolz/watchspire"><br>
    <a href="https://wordpress.org/plugins/watchspire">
        <img alt="WordPress version" src="https://img.shields.io/wordpress/plugin/wp-version/watchspire.svg">
    </a>
    <a href="https://wordpress.org/plugins/watchspire">
        <img alt="Tested WordPress version" src="https://img.shields.io/wordpress/plugin/tested/watchspire.svg">
    </a>
    <a href="https://wordpress.org/plugins/watchspire">
        <img alt="Plugin version" src="https://img.shields.io/wordpress/plugin/v/watchspire.svg">
    </a>
    <a href="https://wordpress.org/plugins/watchspire">
        <img alt="WordPress.org rating" src="https://img.shields.io/wordpress/plugin/rating/watchspire.svg">
    </a>
    <br>
    <a href="https://wordpress.org/plugins/watchspire">
        <img alt="Monthly downloads" src="https://img.shields.io/wordpress/plugin/dm/watchspire.svg">
    </a>
    <a href="https://wordpress.org/plugins/watchspire">
        <img alt="Total downloads" src="https://img.shields.io/wordpress/plugin/dt/watchspire.svg">
    </a>
    <a href="https://wordpress.org/plugins/watchspire">
        <img alt="Active installs" src="https://img.shields.io/wordpress/plugin/installs/watchspire.svg">
    </a>
    <br><br>
    <a href="https://codesolz.net">
        <img alt="Created by M.Tuhin" src="https://img.shields.io/badge/Created%20By-M.Tuhin-brightgreen.svg">
    </a>
</p>

<h2 align="center">Watchspire — WordPress Site Health, Form & Uptime Monitor</h2>

<p align="center">
    <strong>Catch silent WordPress failures before they cost you leads, traffic, or trust.</strong>
</p>

### Description

A WordPress site doesn't have to be completely down to be broken.

Your **contact form not sending email**, **form submissions not arriving**, an approaching **SSL expiry**, sudden **404 errors**, or unexpected **site downtime** can quietly cost you leads and customers long before anyone reports the problem.

**Watchspire is your WordPress early-warning system.**

It monitors form delivery, email health, SSL and domain expiry, uptime, 404 and server errors, broken links, site changes, and AI crawler activity — all from one dashboard.

**Watchspire watches the signals that matter and tells you when something isn't right.**

Instead of checking several plugins, logs, and dashboards, Watchspire brings your site's operational health into one place so you can spot problems earlier and understand what is happening across your WordPress site.

### One Dashboard. The Signals That Matter.

* **Form Delivery Monitoring** — know when real form submissions fail to send.
* **Submission Gap Detection** — spot unusual periods when normally active forms suddenly go quiet.
* **Mail Health & Risk Score** — detect mail failures, SMTP configuration risks, and sender-domain mismatches.
* **SSL & Domain Expiry** — get advance warnings before certificates or domains expire.
* **404 & Server Error Monitoring** — identify broken URLs and 5xx failures before they become widespread.
* **Uptime Self-Checks** — monitor your homepage and an important page for abnormal responses.
* **Broken Links & Images** — scan safely in controlled background batches without hammering your server.
* **WordPress Change Log** — see plugin, theme, core, auto-update, and selected configuration changes.
* **AI Crawler Activity** — discover when GPTBot, ClaudeBot, PerplexityBot, and other supported AI crawlers visit.
* **Weekly Health Summary** — receive a concise overview of important site-health activity.

### Why Watchspire?

A green homepage does not necessarily mean a healthy WordPress site.

Your homepage can still return `200 OK` while:

* A contact form stops delivering leads
* WordPress email starts failing
* A normally active form suddenly receives nothing
* Important URLs begin returning errors
* An SSL certificate approaches expiry
* Links or images break
* A plugin, theme, or core update changes important behavior

Traditional uptime tools mainly answer:

> **Is the website responding?**

Watchspire helps you answer a broader question:

> **Is my WordPress site actually healthy?**

### Supported Form Plugins

Watchspire provides passive form-delivery monitoring for:

* Contact Form 7
* WPForms
* Gravity Forms
* Elementor Pro Forms
* Fluent Forms
* Forminator

Watchspire records monitoring outcomes and metadata only. **Form field contents and customer personal data are never stored.**

### Built for WordPress, Not Around It

Watchspire is designed to stay lightweight.

* Heavy checks run in scheduled background jobs.
* Broken-link scanning is **off by default**.
* Link and image scans run in small, resumable batches.
* Workloads can be reduced automatically on constrained hosting environments.
* AI crawler activity is stored as daily aggregates instead of one record per request.
* Monitoring history follows configurable retention rules.

The monitoring plugin should never become the performance problem.

### Privacy-First by Design

Your monitoring data stays in your WordPress database.

Watchspire Free does not send your monitoring history, form contents, customer information, or usage analytics to Watchspire servers.

The only default third-party lookup initiated by the Free plugin is a cached RDAP request used to retrieve public domain-expiry information while the SSL & Domain Expiry monitor is enabled.

### Free Means Genuinely Useful

Watchspire Free is not a time-limited trial.

There are no artificial limits such as:

* One form only
* A handful of pages
* A small broken-link quota
* A seven-day monitoring trial

The Free plugin focuses on **passive detection** — watching what already happens on your WordPress site and helping you identify problems.

### Watchspire Pro — Active Testing & Correlation

**Watchspire Free watches. Watchspire Pro tests.**

Watchspire Pro adds active testing and advanced reliability workflows for site owners, WooCommerce stores, developers, and agencies.

Planned Pro capabilities include:

* Synthetic form testing
* IMAP inbox-delivery verification
* Automatic testing after plugin, theme, and WordPress updates
* Failure-to-change cause correlation
* Baseline anomaly detection
* Slack, Discord, Microsoft Teams, Telegram, and generic webhook alerts
* Direct SMTP alerts independent of WordPress `wp_mail()`
* External heartbeat monitoring for true downtime detection
* WooCommerce checkout health testing
* Order pipeline and Action Scheduler monitoring
* Revenue anomaly detection
* Agency reports and white-label options

The goal is not only to tell you:

> **Something broke.**

Watchspire Pro is designed to help answer:

> **What most likely caused it?**

Learn more about **[Watchspire Pro](https://codesolz.net/our-products/wordpress-plugin/watchspire)**.

### How to Get Started

* **Step 1 — Install & Activate:** Install Watchspire from the WordPress Plugins screen or upload the plugin manually.
* **Step 2 — Complete Quick Setup:** Confirm your alert email and choose which monitors to enable.
* **Step 3 — Open Watchspire:** Visit **Watchspire → Dashboard** for your site's operational overview.
* **Step 4 — Review Monitors:** Check SSL, mail, uptime, HTTP errors, forms, and other health signals.
* **Step 5 — Optional Link Scan:** Enable Broken Links & Images scanning only when you want it.
* **Step 6 — Watch the Timeline:** Use the Change Log to understand what changed around new problems.

### Main Admin Areas

* **Dashboard** — site health, checks, failures, response time, submissions, errors, and recent activity
* **Monitors** — status and history for enabled health monitors
* **Broken Links & Images** — controlled background scanning with pause, resume, recheck, and ignore
* **Change Log** — plugin, theme, core, auto-update, and selected configuration events
* **Reports** — health trends, uptime, failures, HTTP errors, form activity, and response-time history
* **Settings** — monitors, alerts, scanning, scheduling, retention, and privacy controls

### Forum and Feature Requests

<blockquote>
  <strong>For quick support, feature requests, and bug reporting</strong>
  <ul>
    <li>
      Visit
      <a target="_blank" href="https://codesolz.net/our-products/wordpress-plugin/watchspire/?utm_source=github&utm_medium=README&utm_campaign=watchspire">
        the Watchspire product page
      </a>
      for product information and support.
    </li>
    <li>
      For dedicated support or feature requests, email
      <a target="_blank" href="mailto:support@codesolz.net">support@codesolz.net</a>.
    </li>
  </ul>
</blockquote>

### WordPress Free Plugins by CodeSolz

- [Browse CodeSolz plugins on WordPress.org](https://profiles.wordpress.org/codesolz/#content-plugins)

### Credits

- *Created & supported by [M.Tuhin](https://codesolz.net/) and the [CodeSolz Support Team](https://codesolz.net/)*
- *Watchspire product page: [CodeSolz.net](https://codesolz.net/our-products/wordpress-plugin/watchspire/)*
- *Dedicated support: [support@codesolz.net](mailto:support@codesolz.net)*

<a href="https://codesolz.net/our-products/wordpress-plugin/watchspire/">
  <img src="https://static.codesolz.net/cs/logo.webp" alt="CodeSolz"/>
</a>
