=== Ringier-Bus ===
Contributors: ringier, wkhayrattee
Tags: ringier, bus, api, cde
Requires at least: 6.0
Tested up to: 6.3.1
Stable tag: 2.2.0
Requires PHP: 8.0.2
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

The events are scheduled to be sent to the Bus with a 1-minute delay. This is to allow WordPress to process the changes and update custom fields in the database, which is done asynchronously. You can view scheduled events by making use of the plugin "Advanced Cron Manager".

The plugin also creates two custom fields, available on the article edition page under "Event Bus".
- The article lifetime is required by the CDE.
- The second field, called "Hidden field", is for internal use. It is made to determine if the article is being created or updated, information which is not available by default on WordPress due to the way articles are saved.

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

This plugin requires *PHP version >= 8.0.2*.
But no ***PHP 8.1+ support*** yet  since WordPress itself is not officially supported beyond PHP 8.0 at this point in time.


### SETUP ###

1. The plugin is accessible from the WordPress admin via "Plugins > Add New > Search".
2. Once you have installed the plugin, a Ringier Bus menu will appear. Please fill in the required fields to set up the plugin.
3. In order to get an Event Bus node id, username and password, please contact the bus team via Slack or by email at bus@ringier.co.za to gain access to the Bus admin.   You will be able to add a new node onto the bus and set up your event destinations.

### LOGS ###

This plugin exposes two log files, saved inside the wp-content/ folder:  
An error log file named ringier_bus_plugin_error.log, viewable in the admin by clicking on the submenu "Bus API LOG".  
An info log file named ringier_bus_plugin.log, currently not viewable in the admin but accessible on the server.

== Screenshots ==

1. The admin page
2. On article dashboard, you can select a value for "Article Lifetime"

== Changelog ==

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
