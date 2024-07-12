# Ringier-Bus WordPress Plugin #

![ringier bus banner](assets/banner.png)

**Contributors:** [RingierSA](https://profiles.wordpress.org/ringier/), [wkhayrattee](https://profiles.wordpress.org/wkhayrattee/)  
**Tags:** ringier, bus, api, cde   
**Requires at least:** 6.0  
**Tested up to:** 6.4.2  
**Stable tag:** 2.3.0  
**Requires PHP:** 8.0.2  
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

The plugin also creates two custom fields, available on the article edition page under "Event Bus".  
- The article lifetime is required by the CDE.  
- The second field, called "Hidden field", is for internal use. It is made to determine if the article is being created or updated, information which is not available by default on WordPress due to the way articles are saved.

## Installation ##

### PHP Version

This plugin requires *PHP version >= 8.0.2*.

### SETUP

1. The plugin is accessible from the WordPress admin via "Plugins > Add New > Search".  
2. Once you have installed the plugin, a Ringier Bus menu will appear. Please fill in the required fields to set up the plugin.  
3. In order to get an Event Bus node id, username and password, please contact the bus team via Slack or by email at bus@ringier.co.za to gain access to the Bus admin.   You will be able to add a new node onto the bus and set up your event destinations.
4. Ensure that the WordPress cron is active. This plugin relies on the WordPress cron system for scheduled tasks. If your cron system is not active, please refer to the WordPress Codex or consult with your web hosting provider to enable it.

## LOGS

This plugin exposes two log files, saved inside the wp-content/ folder:  
An error log file named ringier_bus_plugin_error.log, viewable in the admin by clicking on the submenu "Bus API LOG".  
An info log file named ringier_bus_plugin.log, currently not viewable in the admin but accessible on the server.

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

### 3. Modifying the JArticle Payload Data ###

You can customize the payload data for an article by using the **ringier_build_article_payload** filter. This filter allows you to modify the payload data before it is sent to the Ringier Event Bus.

Example:
```php
function custom_build_article_payload(array $payload_array, int $post_ID, WP_Post $post): array
{
    // Add a custom field to the payload for example
    $payload_array['custom_field'] = 'Custom Value';
    
    return $payload_array;
}
add_filter('ringier_build_article_payload', 'custom_build_article_payload', 10, 3);
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
