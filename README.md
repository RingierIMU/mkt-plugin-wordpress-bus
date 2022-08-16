# Ringier-Bus WordPress Plugin #

![ringier bus banner](assets/banner.png)

**Contributors:** [RingierSA](https://profiles.wordpress.org/ringier/), [wkhayrattee](https://profiles.wordpress.org/wkhayrattee/)  
**Tags:** ringier, bus, api, cde   
**Requires at least:** 5.7.0  
**Tested up to:** 6.0.1  
**Stable tag:** 1.1.1  
**License:** GPLv2 or later  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

A plugin to push events to the Ringier Event Bus when articles are created, updated or deleted.

### AUDIENCE

This plugin is made for Ringier businesses using WordPress and wanting to benefit from Hexagon solutions available via the Ringier Event Bus. It can be implemented by developers and non-developers.

### BENEFITS

The Hexagon solutions available via the Ringier Event Bus and compatible with this plugin include:  
- The syncing of articles with Sailthru media library,  
- The storage of article events in Ringier Datalake, from which they are retrieved by the Content Distribution Engine (CDE).  
You can also benefit from the Bus tooling such as event logging, event monitoring and alerting.

To learn more about Hexagon services, visit [https://hexagon.ringier.com/services/business-agility/](https://hexagon.ringier.com/services/business-agility/).


### HOW IT WORKS

The plugin automatically triggers events when articles are created, updated and deleted.  
Event names: ArticleCreated, ArticleUpdated and ArticleDeleted.

The events are scheduled to be sent to the Bus with a 2-minute delay. This is to allow WordPress to process the changes and update custom fields in the database, which is done asynchronously. You can view scheduled events by making use of the plugin "Advanced Cron Manager".

The plugin also creates two custom fields, available on the article edition page under "Event Bus".  
- The article lifetime is required by the CDE.  
- The second field, called "Hidden field", is for internal use. It is made to determine if the article is being created or updated, information which is not available by default on WordPress due to the way articles are saved.

## Installation ##

### SETUP

1. The plugin is accessible from the WordPress admin via "Plugins > Add New > Search".  
2. Once you have installed the plugin, a Ringier Bus menu will appear. Please fill in the required fields to set up the plugin.  
3. In order to get an Event Bus node id, username and password, please contact the bus team via Slack or by email at bus@ringier.co.za to gain access to the Bus admin.   You will be able to add a new node onto the bus and set up your event destinations.

## LOGS

This plugin exposes two log files, saved inside the wp-content/ folder:  
An error log file named ringier_bus_plugin_error.log, viewable in the admin by clicking on the submenu "Bus API LOG".  
An info log file named ringier_bus_plugin.log, currently not viewable in the admin but accessible on the server.

## Contributing ##

There are many ways you can contribute:  
- Raise an issue if you found one,  
- Provide us with your feedback and suggestions for improvement,  
- Create a Pull Request with your bug fixes and/or new features. GitHub repository: [https://github.com/RingierIMU/mkt-plugin-wordpress-bus](https://github.com/RingierIMU/mkt-plugin-wordpress-bus)

## Credits/Thanks ##

1) [Wasseem Khayrattee](https://github.com/wkhayrattee) - for creating and maintaining the plugin  
2) Mishka Rasool - for conceiving/creating the [banner](assets/banner.png) and [logo](assets/logo.png) asset files

## Changelog ##

See our [Changelog](CHANGELOG.md)
