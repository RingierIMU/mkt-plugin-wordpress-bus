=== Ringier-Bus ===
Contributors: ringier, wkhayrattee
Tags: ringier, bus, api, cde
Requires at least: 4.7
Tested up to: 5.8.2
Stable tag: 1.0.0
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A plugin to push events to Ringier CDE via the BUS API whenever an article is created, updated or deleted.

== Description ==

### About | ringier-bus

A plugin to push events to the Ringier Content Distribution Engine (CDE) via the BUS API whenever an article is created, updated or deleted.

### List of Events

- ArticleCreated
- ArticleUpdated
- ArticleDeleted

### Logging

This plugin will expose two log files which will be saved inside your **wp-content/** folder:
1) an error log named **ringier_bus_plugin_error.log**

This will be made viewable to you by clicking on the sub-menu named "Bus API LOG".

You will also have the possibility to clear the log.

NOTE: By default we will always show you the latest 10 entries from the log

2) Info Log file named: **ringier_bus_plugin.log**

This is not viewable via the admin, I am considering providing this feature in future.

For now, you can inspect that log file by going (SSH-ing) into your server.

It has some good hints about the journey of your BUS API from seeing if the Plugin is ON or if any fields needs attention..etc.

## Viewing Scheduled Events

1) Events are sent AFTER 2 minutues.

This plugin will always scheduled the sending of the events to the BUS.
That is, whenever you create or edit an article, it will not send the event immediately. Instead it will wait for TWO MINUTES before sending the event to the BUS Endpoint.

2) To view all scheduled events, make use of this plugin: [Advanced Cron Manager](https://wordpress.org/plugins/advanced-cron-manager/)


== Installation ==

1) Install & activate the plugin

At this time, you will need to download the ZIP from its github repo.

But I am working to make this plugin accessible automatically to you right from the WordPress admin dashboard via "plugins > Add New > Search"

2) Click on the menu on your right "Bus API"
3) Fill in all your **Event Bus API details**
4) DO NOT FORGET to select "ON" for "Enable Bus API" field.

NOTE:
- There is a possibility for you to also have the plugin send messages (in case of any error) to your Slack Channel.
- If you leave any field regarding the Slack feature, the plugin will assume Slack option is OFF
- Likewise, if any of the Bus API fields is empty, the plugin will assume BUS Event is OFF even if you enabled it


== Screenshots ==

1. The admin page
2. On article dashboard, you can select a value for "Article Lifetime"

== Contributing ==

The best way to contribute to the development of this plugin is by participating on the GitHub project:

https://github.com/RingierIMU/mkt-plugin-wordpress-bus

There are many ways you can contribute:

* Raise an issue if you found one
* Create/send us a Pull Request with your bug fixes and/or new features
* Provide us with your feedback and/or suggestions for any improvement or enhancement
* Translation - this is an area we are yet to do

== Credits/Thanks ==

1) Wasseem Khayrattee - for creating and maintaining the plugin
2) Mishka Rasool - for conceiving/creating the banner and logo asset files


== Changelog ==

= 1.0.0 (November 19, 2021) =
* Initial release onto WordPress.org plugin repo with the initial code from phase 1 of this plugin

= 0.1.0 (September 26, 2021) =
* Initial commit of working code for the benefit of everyone who needs this plugin
