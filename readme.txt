=== Ringier-Bus ===
Contributors: ringier, wkhayrattee
Tags: ringier, bus, api, cde
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 3.5.2
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A plugin to push events to the Ringier Event Bus when articles are created, updated or deleted.

## AUDIENCE ##

This plugin is made for Ringier businesses using WordPress and wanting to benefit from Hexagon solutions available via the Ringier Event Bus. It can be implemented by developers and non-developers.

## BENEFITS ##

The Hexagon solutions available via the Ringier Event Bus and compatible with this plugin include:
- The syncing of articles with Sailthru media library,
- The storage of article events in Ringier Datalake, from which they are retrieved by the Content Distribution Engine (CDE).
You can also benefit from the Bus tooling such as event logging, event monitoring and alerting.

To learn more about Hexagon services, visit [https://hexagon.ringier.com/services/business-agility/](https://hexagon.ringier.com/services/business-agility/).

## HOW IT WORKS ##

The plugin automatically triggers events when articles are created, updated and deleted.
Event names: ArticleCreated, ArticleUpdated and ArticleDeleted.

The **events are scheduled** to be sent to the Bus **within a 1-minute delay**. This is to allow WordPress to process the changes and update custom fields in the database, which is done asynchronously. You can view scheduled events by making use of the plugin "Advanced Cron Manager".

Here is a summary of the events sent to the Bus:
- If the article is newly created, we send it INSTANTLY - sent as **ArticleCreated**
    - But then we schedule it to run again after the normal 1 minute so that all custom data are sent properly - sent as **ArticleUpdated**
- For all existing articles that undergo an update, we schedule the event to run after the 1 minute interval

The plugin also creates two mandatory custom fields, available on the article editor page under "Event Bus" widget:
- The article lifetime (lifetime)
- The publication reason (publication_reason)

We also expose custom filters to help you adjust these two fields and the payload sent to the BUS endpoint, see below.

### LOGS ###

This plugin creates a log file (**ringier_bus_plugin_error_log**), saved inside the wp-content/ folder:
The error messages are viewable via the admin UI by clicking on the submenu "LOG".
You also have the flexibility to clear the log file via the UI itself.

## CUSTOM FILTERS ##

The plugin exposes three custom filters to help you adjust the plugin's JSON Payload that is sent to the BUS endpoint.

### 1. Modifying the Publication Reason ###

You can customize the publication reason for an article by using the **ringier_bus_get_publication_reason** filter. This filter allows you to modify the publication reason before it is sent to the Ringier Event Bus.

Example:
```php
function custom_publication_reason(string $publication_reason, int $post_ID): string
{
    // Your custom logic goes here
    return 'Custom Publication Reason';
}
add_filter('ringier_bus_get_publication_reason', 'custom_publication_reason', 10, 2);
```

### 2. Modifying the Article Lifetime Payload ###

You can customize the article lifetime for an article by using the **ringier_bus_get_article_lifetime** filter. This filter allows you to modify the article lifetime before it is sent to the Ringier Event Bus.

Example:
```php
function custom_article_lifetime(string $article_lifetime, int $post_ID): string
{
    // Your custom logic goes here
    return 'Custom Article Lifetime';
}
add_filter('ringier_bus_get_article_lifetime', 'custom_article_lifetime', 10, 2);
```

### 3. Modifying the Article Payload Data ###

You can customize the payload data for an article by using the **ringier_bus_build_article_payload** filter. This filter allows you to modify the payload data before it is sent to the Ringier Event Bus.

Example:
```php
function custom_build_article_payload(array $payload_array, int $post_ID, WP_Post $post): array
{
    // Add a custom field to the payload for example
    $payload_array['custom_field'] = 'Custom Value';

    return $payload_array;
}
add_filter('ringier_bus_build_article_payload', 'custom_build_article_payload', 10, 3);
```

### 4. Controlling Author Event Dispatch ###

You can control whether the Ringier Bus plugin should dispatch Author events for a specific user by using the **ringier_bus_should_dispatch_author_event** filter.
This filter allows you to override the default logic to force enablement or disablement based on your own custom logic - when you want full control over which authors get sent to the Event Bus.

Example:
```php
/**
 * Example: Prevent event dispatch for a specific user ID.
 */
add_filter('ringier_bus_should_dispatch_author_event', function (bool $should_dispatch, int $user_id): bool {

    // Example: Always send events for Administrators, ignoring the "Show Profile" checkbox
    if (user_can($user_id, 'administrator')) {
        return true;
    }

    // Example condition: never sync user ID 1234
    if ($user_id === 1234) {
        return false;
    }

    // Otherwise, use the default logic
    return $should_dispatch;
}, 10, 2);
```

This filter gives you full flexibility to:
- Disable syncing for certain authors
- Force syncing regardless of profile visibility
- Apply environment-specific rules (e.g., staging vs production)
- Implement client-specific dispatch policies

## Contributing ##

There are many ways you can contribute:
- Raise an issue if you found one,
- Provide us with your feedback and suggestions for improvement,
- Create a Pull Request with your bug fixes and/or new features. GitHub repository: [https://github.com/RingierIMU/mkt-plugin-wordpress-bus](https://github.com/RingierIMU/mkt-plugin-wordpress-bus)

## Credits/Thanks ##

1) Wasseem Khayrattee - for creating and maintaining the plugin
2) Mishka Rasool - for conceiving/creating the banner and logo asset files

== Installation ==

### PHP Version ###

This plugin requires *PHP version >= 8.1*.

### SETUP ###

1. The plugin is accessible from the WordPress admin via "Plugins > Add New > Search".
       - Search for "Ringier Bus" and click on "Install Now".
2. Once you have installed the plugin, a Ringier Bus menu will appear. Please fill in the required fields to set up the plugin.
3. In order to get an Event Bus node id, username and password, please contact the bus team via Slack or by email at bus@ringier.co.za to gain access to the Bus admin.   You will be able to add a new node onto the bus and set up your event destinations.
4. Ensure that the WordPress cron is active. This plugin relies on the WordPress cron system for scheduling tasks. If your cron system is not active, please refer to the WordPress Codex or consult with your web hosting provider to enable it.

== Screenshots ==

1. The admin page
2. On article dashboard, you can select a value for "Article Lifetime"

== Changelog ==

### [3.5.2] - 2026-02-04 ###

#### Changed ####
* (code) Refactored `getOgArticlePublishedDate` and `getOgArticleModifiedDate` to use native `WP_Post` properties instead of Yoast SEO Indexables. This ensures the API uses the database "Source of Truth" and avoids data cross-contamination when posts share slugs with historical attachments.
* (code) Hardened `Utils::formatDate` to handle null or "zeroed" database timestamps (`0000-00-00 00:00:00`), ensuring strict RFC3339 compliance.


### [3.5.1] - 2026-01-29 ###

#### Added ####
* (UI) Added **Resume from ID** functionality to the Batch Article Sync tool, allowing users to continue interrupted syncs from a specific Article ID.

#### Changed ####
* (UI) Updated the sync status message to clarify when a sync is resuming versus starting fresh.


### [3.5.0] - 2026-01-21 ###

#### Added ####
* (UI) **Batch Article Sync** tool added to the "BUS Tooling Page".
  * Features a "Recent First" sync strategy to prioritize the newest content.
  * Includes a Post Type selector (Radio button) to allow syncing specific custom post types.
  * Displays real-time progress logs in the admin dashboard.
* (code) Implemented a **Reverse ID Cursor** strategy for the Article Sync logic.
  * Ensures **O(1)** constant performance regardless of database size (efficiently handles 10k+ posts).
  * Replaces standard `OFFSET` pagination to prevent timeout issues on deep database queries.

#### Changed ####
* (code) Major refactor of the `ArticleEvent` class to remove 3rd-party dependencies in favor of 100% native WP code:
  * Removed `GuzzleHttp\Client` in favor of native `wp_remote_post()`.
  * Removed `AuthenticationInterface` dependency; now uses `BusTokenManager` directly.


### [3.4.1] - 2025-12-02 ###

#### Added ####
* (code) Added a check to see if the Ringier Author plugin is enabled
  * If yes, only send events for authors that have their public profile set to ENABLED
  * If that plugin is not present or disabled, it's business as usual

#### Fixed ####
* (payload) TopicEvents: title, slug and url should be array of objects


### [3.4.0] - 2025-07-02 ###

#### Added ####
* (payload) Added `canonical` to the Article payload


### [3.3.1] - 2025-07-01 ###

#### Fixed ####

* (payload) parent_category should be one TranslationObject and not a list of TranslationObjects


### [3.3.0] - 2025-06-24 ###

#### Added ####
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

#### Changed ####
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
    - see PR#8 for more details
* [UPDATE] When description is not set by author, it defaulted to the excerpt. As a consequence hellip was being added to the description. This has been fixed to remove the hellip, as well as any other html entities/tags that might be present in the excerpt.

### 3.0.0 (Jul 12, 2024) ###
* [BREAKING] PHP Version | The code base now requires a minimum version of PHP 8.1+
* [NEW] Added three new custom filters to allow for more flexibility in the plugin's behavior (see readme file):
    - `ringier_bus_get_publication_reason` - allows you to filter the publication reason before it is sent to the BUS API
    - `ringier_bus_get_article_lifetimez` - allows you to filter the article lifetime before it is sent to the BUS API
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

*Reason*:
We identified that some blogs were disabling the Gutenberg editor and as a result, not utilizing the new WordPress REST API. This meant that the rest_after_insert_post hook wasn't being triggered for those instances. To ensure consistent and robust post update handling across all blogs, regardless of their editor choice, we've shifted to the transition_post_status hook.

* [UPDATE]: Improved JSON handling and compression for Slack logging (see Changelog.md)

### 2.2.0 (Oct 9, 2023) ###
* [NEW] Introduction of the possibility to add a custom Top level primary category - can ENABLE/DISABLED when needed | See Changelog.md
* [UPDATE] Refactored the logic for saving custom fields (on gutenberg) to work as soon as the plugin is active, irrespective if the BUS sync is OFF
* [FIX] There was a bug that could prevent the primary category of an article from being fetched from the fallback method if the one from Yoast fails

### 2.1.0 (Jul 18, 2023) ###
* [UPDATE] General updates to the JSON structure to match the new BUS Specs - See Changelog.md
* [UPDATE] New widget for the new field publication reason on the Gutenberg editor
* [UPDATE] Updated composer dependencies

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

### 1.1.0 (Jul 27, 2022) ###
* update ACF to v5.12.2

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
