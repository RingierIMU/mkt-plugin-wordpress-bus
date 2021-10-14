# About | wp-bus

A plugin to push events to Ringier CDE via the BUS API whenever an article is created, updated or deleted.

## List of Events

- ArticleCreated
- ArticleUpdated
- ArticleDeleted

### Usage

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
