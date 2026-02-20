# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased] ##
### Changed ###
* (refactor) Unified `ArticleEvent` and `ArticlesEvent` into a single `ArticleEvent` class using 100% native WordPress APIs (`wp_remote_post()`, `BusTokenManager` with WP transients). Both real-time hook dispatch and batch sync tooling now share the same code path.
* (refactor) `BusHelper::sendToBus()` now delegates to `BusHelper::dispatchArticleEvent()`, removing all Guzzle/Symfony dependencies from the real-time event flow.
* (refactor) Renamed `dispatchArticlesEvent()` to `dispatchArticleEvent()` for consistency.
* (refactor) Replaced all 18 Twig templates with native PHP templates using `Utils::load_tpl()` and WordPress `load_template()`. Admin views (`AdminSettingsPage`, `AdminLogPage`) now use 100% native WordPress templating.

### Removed ###
* (dependency) Fully removed Monolog (`monolog/monolog`)
* (dependency) Fully removed Guzzle (`guzzlehttp/guzzle`) and Symfony Cache (`symfony/cache`)
* (dependency) Fully removed Timber (`timber/timber`) and Twig (`twig/twig`) — the plugin now has **zero production dependencies**
* (code) Removed legacy `Auth.php`, `AuthenticationInterface.php`, and `LoggingHandler.php` classes
* (code) Removed dead `BusHelper::getImageArrayForApi()` method
* (code) Removed unused `RINGIER_BUS_PLUGIN_CACHE_DIR` constant
* (code) Deleted all 18 `.twig` template files from `views/admin/`

### Fixed ###
* (bug) `ArticleDeleted` payload sent `false`/empty for `url` and `canonical` fields — `wp_get_canonical_url()` returns `false` for trashed (non-public) posts. Added `Utils::get_reliable_permalink()` which falls back to `get_permalink()` and strips the `__trashed` slug suffix WordPress appends during trash.
* (bug) `BusHelper::scheduleSendToBus()` — the admin-configured backoff duration was never applied on retry; `$minutesToRun` was unconditionally overwritten to `0` instead of using the configured value


## [3.6.0] - 2026-02-18 ##

### Added ###
* (payload) Added `taxon_tags[]` (array of TranslationObject) to the Article payload, populated with all tags associated with the article.
  * For standard `post` type: includes the built-in `post_tag` taxonomy.
  * For custom post types: includes any non-hierarchical (tag-like) custom taxonomy registered for that post type.

### Changed ###
* (code) Aligned `ArticlesEvent` date methods (`getOgArticlePublishedDate`, `getOgArticleModifiedDate`) with the 3.5.2 hardening applied to `ArticleEvent`: uses native `WP_Post` properties as source of truth and guards against null/zeroed GMT timestamps with a local-date fallback.


## [3.5.2] - 2026-02-04 ##

### Changed ###
* (code) Refactored `getOgArticlePublishedDate` and `getOgArticleModifiedDate` to use native `WP_Post` properties instead of Yoast SEO Indexables. This ensures the API uses the database "Source of Truth" and avoids data cross-contamination when posts share slugs with historical attachments.
* (code) Hardened `Utils::formatDate` to handle null or "zeroed" database timestamps (`0000-00-00 00:00:00`), ensuring strict RFC3339 compliance.


## [3.5.1] - 2026-01-29 ##

### Added ###
* (UI) Added **Resume from ID** functionality to the Batch Article Sync tool, allowing users to continue interrupted syncs from a specific Article ID.

### Changed ###
* (UI) Updated the sync status message to clarify when a sync is resuming versus starting fresh.


## [3.5.0] - 2026-01-21 ##

### Added ###
* (UI) **Batch Article Sync** tool added to the "BUS Tooling Page".
  * Features a "Recent First" sync strategy to prioritize the newest content.
  * Includes a Post Type selector (Radio button) to allow syncing specific custom post types.
  * Displays real-time progress logs in the admin dashboard.
* (code) Implemented a **Reverse ID Cursor** strategy for the Article Sync logic.
  * Ensures **O(1)** constant performance regardless of database size (efficiently handles 10k+ posts).
  * Replaces standard `OFFSET` pagination to prevent timeout issues on deep database queries.

### Changed ###
* (code) Major refactor of the `ArticleEvent` class to remove 3rd-party dependencies in favor of 100% native WP code:
  * Removed `GuzzleHttp\Client` in favor of native `wp_remote_post()`.
  * Removed `AuthenticationInterface` dependency; now uses `BusTokenManager` directly.


## [3.4.1] - 2025-12-02 ##

### Added ###
* (code) Added a check to see if the Ringier Author plugin is enabled
  * If yes, only send events for authors that have their public profile set to ENABLED
  * If that plugin is not present or disabled, it's business as usual

### Fixed ###
* (payload) TopicEvents: title, slug and url should be array of objects


## [3.4.0] - 2025-07-02 ##

### Added ###
* (payload) Added `canonical` to the Article payload


## [3.3.1] - 2025-07-01 ##

### Fixed ###

* (payload) parent_category should be one TranslationObject and not a list of TranslationObjects

## [3.3.0] - 2025-06-24 ##

### Added ###
* (payload) Custom Taxonomy support for category related properties within the Article payload
* (payload) Author Events:
  * AuthorCreated
  * AuthorUpdated
  * AuthorDeleted
* (payload) Topic Events:
  * TopicCreated
  * TopicUpdated
  * TopicDeleted
* (UI) Introduced checkboxes (Settings page) to toggle ON/OFF event sending for Authors Events
* (UI) Introduced checkboxes (Settings page) to toggle ON/OFF event sending for Topic Events (categories and tags).
* (UI) Tooling Menu for batch syncing and flushing transients
* (UI) Batch Syncing Mechanism with real-time progression updates during sync operations
  * batch syncing Topics events
  * batch syncing Author events
* (code) Used WordPress-native features like wp_remote_*() with the above new events in place of Guzzle/Symfony dependencies
* (code) Introduced new Enums for writer type, hook priorities, and author roles to improve clarity and reuse

### Changed ###
* Improved error reporting with contextual messages and consistent formatting
* (dependency) Replaced Monolog logging logic with native PHP for better control and performance
* (dependency) Used WordPress-native features like wp_remote_*() and transient in place of Guzzle/Symfony dependencies
* (code) Reduced transient expiry for recently created authors from 10s to 5s for more accurate event filtering
* (code) Adopted a pure WordPress templating approach for better separation of logic and templates
* (code) refactored logging message + removed redundant log inputs


### 3.2.0 (May 15, 2024) ###
* [NEW] UI + Logic: Added a checkbox (in settings page) to allow users to enable/disable the Quick Edit button
* [NEW] UI + Logic: Added a checkbox to let users select which custom post_type should be sent as Events
  * By default, only the default `post` post_type is sent as Events, unless custom types are explicitly enabled
* [UPDATE] Logic: Improved the event logging mechanism


### 3.1.0 (Oct 9, 2024) ###
* [NEW] Added Youtube videos to the event payload if there's any as part of the article
  * see PR#8 for more details
* [UPDATE] When description is not set by author, it defaulted to the excerpt. As a consequence hellip was being added to the description. This has been fixed to remove the hellip, as well as any other html entities/tags that might be present in the excerpt.


### 3.0.0 (Jul 15, 2024) ###

* [BREAKING] PHP Version | The code base now requires a minimum version of PHP 8.1+
* [NEW] Added three new custom filters to allow for more flexibility in the plugin's behavior (see readme file):
    - `ringier_bus_get_publication_reason` - allows you to filter the publication reason before it is sent to the BUS API
    - `ringier_bus_get_article_lifetime` - allows you to filter the article lifetime before it is sent to the BUS API
    - `ringier_build_article_payload` - allows you to filter the entire article payload before it is sent to the BUS API
* [UPDATE]: Changed the way events are sent:
    - on new article creation, an event will now be immediately sent (this is a requirement for internal CIET)
    - the event will still be queued to run or re-run (in the case of an article update) after the default 1 minute
* [UPDATE]: Harmonised page title and menu
* [UPDATE]: Updated composer dependencies
* [UPDATE]: Cache nonce now defaults to the plugin version number for consistency
* [UPDATE]: Add more intuitive prompts to guide user, for e.g provide the STAGING and PROD endpoints right there in the UI to be handy for them


### 2.3.0 (Oct 9, 2023) ###

* [UPDATE]: Transitioned from relying on the rest_after_insert_post hook to the more universally available transition_post_status hook.

*Reason*: We identified that some blogs were disabling the Gutenberg editor and as a result, not utilizing the new WordPress REST API. This meant that the rest_after_insert_post hook wasn't being triggered for those instances. To ensure consistent and robust post update handling across all blogs, regardless of their editor choice, we've shifted to the transition_post_status hook.

*Impact*: This change ensures that our logic remains consistent even in environments where Gutenberg is disabled or the REST API isn't being leveraged.

* [UPDATE]: Improved JSON handling and compression for Slack logging
  * Ensured safe JSON encoding with error checks
  * Utilized gzcompress for payload compression when available to prevent truncation in Slack notifications channel


### 2.2.0 (Oct 9, 2023) ###

* [NEW] Introduction of the possibility to add a custom Top level primary category - can ENABLE/DISABLED when needed
  * Addition of two new fields on the Settings page for the below
  * use-case: when you have several wordpress instance on the same root domain
  * by default, it will use the full domain as the primary category when enabled, with the flexibility for you to change it on the *settings page*

* [UPDATE] Refactored the logic for saving custom fields (on gutenberg) to work as soon as the plugin is active, irrespective if the BUS sync is OFF
* [FIX] There was a bug that could prevent the primary category of an article from being fetched from the fallback method if the one from Yoast fails


### 2.1.0 (Jul 18, 2023) ###

* [UPDATE] General updates to the JSON structure to match the new BUS Specs (See [PR#5](https://github.com/RingierIMU/mkt-plugin-wordpress-bus/pull/5)

  i) Check for the presence of the following new/updated variables:
    - images
    - lifetime
    - source_detail
    - publication_reason

  ii) the following variables simply had size limit adjustments
    - og_title
    - description
    - og_description
    - teaser

* [UPDATE] New widget for the new field publication reason on the Gutenberg editor
* [UPDATE] Updated composer dependencies:
  - guzzlehttp/guzzle to v7.5.3
  - symfony/cache to v6.0.19
  - ramsey/uuid to v4.7.4


### 2.0.0 (Dec 23, 2022) ###

* [BREAKING] PHP Version | The code base now requires a minimum version of PHP 8.0.2
* [BREAKING] PHP Version | The code base has been refactored to be PHP 8 compatible - but no PHP 8.1+ support yet since WordPress itself is not officially PHP 8.0 compatible to-date.
* [UPDATE] API | New field `Categories[]` has been introduced to the JSON request - see commit#e857e083fb33a9bd58374482105e2d3215bbd5f1
* [REFACTOR] Removal of the ACF plugin 3rd-party plugin in favor of doing things in native WordPress, see commit#b2e489b156ed12187403bb4599107972a61b4493


### 1.3.1 (Oct 18, 2022) ###
* [UPDATE] JSON | change page_type to content_type for sailthru vars


### 1.3.0 (Oct 12, 2022) ###
* [NEW] custom post_type event | handle triggering of events separately for custom post_type
* [NEW] custom fields on admin UI | allow showing of acf custom fields on custom post_type as well, excluding page for now


### 1.2.0 (Oct 04, 2022) ###
* [FIX] Events should not be triggered when "saving draft"
* [NEW] Logging | Add additional log message when an Event is not sent to know why
* [NEW] Addition of new logic for new field: primary_media_type


### 1.1.1 (Aug 16, 2022) ###
* [JSON Request] The API's field `description` field truncated to 2500 chars since the BUS API request will fail on more than 3000 chars.
* [Doc] The readme has been given some polishing


### 1.1.0 (Jul 27, 2022) ###
* [vendor] update ACF to v5.12.3
* Added Sailthru Tags & Vars to the JSON request
* Changes to BUS API
  * update BUS API version to v2.0.0
  * Main JSON - rename venture_config_id to node_id
  * Article JSON - rename venture_config_id to from
  * Article JSON rename venture_reference to reference


### 1.0.3 (April 14, 2022) ###
* update ACF to v5.12.2


### 1.0.2 (December 06, 2021) ###
* update symfony/cache to v5.4.0 - we will stick to 5.x for now because v6.x focuses on php v8+
* update ACF to v5.11.4


### 1.0.1 (November 25, 2021) ###
* Update ACF to latest v5.11.3


### 1.0.0 (November 19, 2021) ###
* Initial release onto WordPress.org plugin repo with the initial code from phase 1 of this plugin


### 0.1.0 (September 26, 2021) ###
* Initial commit of working code for the benefit of everyone who needs this plugin
