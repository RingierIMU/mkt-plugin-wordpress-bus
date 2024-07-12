# Ringier-Bus WordPress Plugin #

![ringier bus banner](assets/banner.png)

**Contributors:** [RingierSA](https://profiles.wordpress.org/ringier/), [wkhayrattee](https://profiles.wordpress.org/wkhayrattee/)  
**Tags:** ringier, bus, api, cde   
**Requires at least:** 6.0  
**Tested up to:** 6.5.5  
**Stable tag:** 3.0.0  
**Requires PHP:** 8.1  
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

The **events are scheduled** to be sent to the Bus **within a 1-minute delay**. This is to allow WordPress to process the changes and update custom fields in the database, which is done asynchronously. You can view scheduled events by making use of the plugin "Advanced Cron Manager".

Here is a summary of the events sent to the Bus:
- If the article is newly created, we send it INSTANTLY - sent as **ArticleCreated**
    - But then we schedule it to run again after the normal 1 minute so that all custom data are sent properly - sent as **ArticleUpdated**
- For all existing articles that undergo an update, we schedule the event to run after the 1 minute interval

The plugin also creates two mandatory custom fields, available on the article editor page under "Event Bus" widget:  
- The article lifetime (lifetime)
- The publication reason (publication_reason)

We also expose custom filters to help you adjust these two fields and the payload sent to the BUS endpoint, see below.

## Installation ##

### PHP Version

This plugin requires *PHP version >= 8.1*.

### SETUP

1. The plugin is accessible from the WordPress admin via "Plugins > Add New > Search".
    - Search for "Ringier Bus" and click on "Install Now".
2. Once you have installed the plugin, a Ringier Bus menu will appear. Please fill in the required fields to set up the plugin.  
3. In order to get an Event Bus node id, username and password, please contact the bus team via Slack or by email at bus@ringier.co.za to gain access to the Bus admin.   You will be able to add a new node onto the bus and set up your event destinations.
4. Ensure that the WordPress cron is active. This plugin relies on the WordPress cron system for scheduling tasks. If your cron system is not active, please refer to the WordPress Codex or consult with your web hosting provider to enable it.

## LOGS

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
