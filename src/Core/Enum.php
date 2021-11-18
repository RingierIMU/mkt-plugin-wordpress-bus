<?php

namespace RingierBusPlugin;

class Enum
{
    public const BUS_API_VERSION = '1.0.3';
    const PLUGIN_KEY = 'WP_BUS';
    const SETTINGS_PAGE_OPTION_GROUP = 'wp_bus_settingspage_group';
    const SETTINGS_PAGE_OPTION_NAME = 'wp_bus_settingspage_options';
    const RINGIER_LOG_FILE_MESSAGE = 'ringier_bus_plugin.log';
    const RINGIER_LOG_FILE_ERROR = 'ringier_bus_plugin_error.log';

    //Global ENV - use RBP to prevent name class, RBA as in Ringier Bus Plugin
    const ENV_BACKOFF_FOR_MINUTES = 'RBP_BACKOFF_FOR_MINUTES';
    const ENV_VENTURE_CONFIG = 'RBP_VENTURE_CONFIG';
    const ENV_BUS_ENDPOINT = 'RBP_BUS_ENDPOINT';
    const ENV_BUS_API_USERNAME = 'RBP_BUS_API_USERNAME';
    const ENV_BUS_API_PASSWORD = 'RBP_BUS_API_PASSWORD';
    const ENV_BUS_API_LOCALE = 'RBP_BUS_API_LOCALE';
    const ENV_BUS_APP_KEY = 'RBP_BUS_APP_KEY';
    const ENV_SLACK_HOOK_URL = 'RBP_SLACK_HOOK_URL';
    const ENV_SLACK_CHANNEL_NAME = 'RBP_SLACK_CHANNEL_NAME';
    const ENV_SLACK_BOT_NAME = 'RBP_SLACK_BOT_NAME';
    const ENV_SLACK_ENABLED = 'RBP_SLACK_ENABLED';

    //ADMIN SETTINGS PAGE
    const ADMIN_SETTINGS_PAGE_TITLE = 'Ringier Event Bus Settings';
    const ADMIN_SETTINGS_MENU_TITLE = 'Ringier Bus';
    const ADMIN_SETTINGS_MENU_SLUG = 'ringier-bus-api';
    const ADMIN_SETTINGS_SECTION_1 = 'ringier-bus-settings-section01';

    //FIELDS
    const FIELD_BUS_STATUS = 'field_bus_status';
    const FIELD_APP_LOCALE = 'field_bus_app_locale';
    const FIELD_APP_KEY = 'field_bus_app_key';
    const FIELD_VENTURE_CONFIG = 'field_venture_config';
    const FIELD_API_USERNAME = 'field_bus_api_username';
    const FIELD_API_PASSWORD = 'field_bus_api_password';
    const FIELD_API_ENDPOINT = 'field_bus_api_endpoint';
    const FIELD_BACKOFF_DURATION = 'field_bus_backoff_duration';
    const FIELD_SLACK_HOOK_URL = 'field_bus_slack_hook_url';
    const FIELD_SLACK_CHANNEL_NAME = 'field_bus_slack_channel_name';
    const FIELD_SLACK_BOT_NAME = 'field_bus_slack_bot_name';

    //ADMIN LOG PAGE
    const ADMIN_LOG_PAGE_TITLE = 'Ringier Bus API Log';
    const ADMIN_LOG_MENU_TITLE = 'Ringier Bus LOG';
    const ADMIN_LOG_MENU_SLUG = 'ringier-bus-api-log';
    const ADMIN_LOG_SECTION_1 = 'ringier-bus-api-log-section01';

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
    public const ACF_ARTICLE_LIFETIME_KEY = 'article_lifetime';
    public const ACF_IS_POST_NEW_KEY = 'is_post_new';
    public const ACF_IS_POST_NEW_DEFAULT_VALUE = 'not_new';
}
