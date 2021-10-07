<?php

namespace RingierBusPlugin;

class Enum
{
    public const BUS_API_VERSION = '1.0.3';
    const PLUGIN_KEY = 'WP_BUS';
    const SETTINGS_PAGE_OPTION_GROUP = 'wp_bus_settingspage_group';
    const SETTINGS_PAGE_OPTION_NAME = 'wp_bus_settingspage_options';

    //ADMIN SETTINGS PAGE
    const ADMIN_SETTINGS_PAGE_TITLE = 'Ringier Bus API Settings';
    const ADMIN_SETTINGS_MENU_TITLE = 'Bus API';
    const ADMIN_SETTINGS_MENU_SLUG = 'wp-bus-api';
    const ADMIN_SETTINGS_SECTION_1 = 'wp-bus-settings-section01';

    //FIELDS
    const FIELD_BUS_STATUS = 'field_bus_status';
    const FIELD_VENTURE_CONFIG = 'field_venture_config';
    const FIELD_API_USERNAME = 'field_bus_api_username';
    const FIELD_API_PASSWORD = 'field_bus_api_password';
    const FIELD_API_ENDPOINT = 'field_bus_api_endpoint';
    const FIELD_BACKOFF_DURATION = 'field_bus_backoff_duration';
    const FIELD_SLACK_HOOK_URL = 'field_bus_slack_hook_url';
    const FIELD_SLACK_CHANNEL_NAME = 'field_bus_slack_channel_name';
    const FIELD_SLACK_BOT_NAME = 'field_bus_slack_bot_name';

    //ADMIN LOG PAGE
    const ADMIN_LOG_PAGE_TITLE = 'Bus API Message Log';
    const ADMIN_LOG_MENU_TITLE = 'Message Log';
    const ADMIN_LOG_MENU_SLUG = 'wp-bus-log';

    //BUS API Related
    const HOOK_NAME_SCHEDULED_EVENTS = 'hookSendToBusScheduled';
    public const CACHE_NAMESPACE = 'RingierBusWordpressPlugin';
    const CACHE_KEY = 'bus_auth_token';

    const EVENT_ARTICLE_CREATED = 'ArticleCreated';
    const EVENT_ARTICLE_EDITED = 'ArticleUpdated';
    const EVENT_ARTICLE_DELETED = 'ArticleDeleted';

    public const JSON_FIELD_STATUS_ONLINE = 'online';
    public const JSON_FIELD_STATUS_OFFLINE = 'offline';
    public const JSON_FIELD_STATUS_DELETED = 'deleted';

    // ACF Fields
    public const ACF_IS_POST_NEW_KEY = 'is_post_new';
    public const ACF_IS_POST_NEW_DEFAULT_VALUE = 'not_new';
}
