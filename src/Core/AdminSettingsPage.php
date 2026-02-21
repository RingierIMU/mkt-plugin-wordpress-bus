<?php
/**
 * To handle everything regarding the main Admin Bus API Settings Page
 *
 * @author Wasseem Khayrattee <wasseemk@ringier.co.za>
 *
 * @github wkhayrattee
 */

namespace RingierBusPlugin;

class AdminSettingsPage
{
    public function __construct()
    {
    }

    /**
     * Main method for handling the admin pages
     */
    public function handleAdminUI()
    {
        $this->addAdminPages();

        // Register a new setting for our page.
        register_setting(Enum::SETTINGS_PAGE_OPTION_GROUP, Enum::SETTINGS_PAGE_OPTION_NAME);

        // Register a new section in our page.
        add_settings_section(
            Enum::ADMIN_SETTINGS_SECTION_1,
            'Please fill in the below, mandatory are marked by an asterisk <span style="color:red;">*</span>',
            [self::class, 'settingsSectionCallback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG
        );
    }

    public static function settingsSectionCallback($args)
    {
        //silence for now
    }

    public function addAdminPages()
    {
        //The "Ringier Bus API Settings" main-PAGE
        add_menu_page(
            Enum::ADMIN_SETTINGS_PAGE_TITLE,
            Enum::ADMIN_SETTINGS_MENU_TITLE,
            'manage_options',
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            [self::class, 'renderParentPage'],
            'dashicons-rest-api',
            20
        );
        add_submenu_page(
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            'Ringier Bus - Settings',
            'Settings',
            'manage_options',
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            [self::class, 'renderSettingsPage']
        );

        //Fields for the "Ringier Bus API Settings" main-PAGE
        $this->addFieldsViaSettingsAPI();
    }

    /**
     * We’re using the renderSettingsPage() callback in add_menu_page() to explicitly define a render method
     * for the top-level admin menu. This prevents WordPress from falling back to a slug derived from the menu
     * title (e.g., toplevel_page_ringier-bus) and ensures consistent, predictable hook suffixes
     * (like ringier-bus-api_page_ringier-bus-sync-page). Even if the method outputs nothing,
     * it anchors the menu page to a stable, named callback — improving control and maintainability.
     */
    public static function renderParentPage(): void
    {
        //echo '<div class="wrap"><h1>Ringier BUS Settings</h1><p>Select a section from the submenu.</p></div>';
        return;
    }

    /**
     * Handle & Render our Admin Settings Page
     */
    public static function renderSettingsPage()
    {
        global $title;

        if (!current_user_can('manage_options')) {
            return;
        }

        Utils::load_tpl(
            RINGIER_BUS_PLUGIN_VIEWS . 'admin' . RINGIER_BUS_DS . 'page-settings.php',
            ['admin_page_title' => $title]
        );
    }

    public function addFieldsViaSettingsAPI()
    {
        $this->add_field_bus_status();
        $this->add_field_app_locale();
        $this->add_field_app_key();
        $this->add_field_venture_config();
        $this->add_field_api_username();
        $this->add_field_api_password();
        $this->add_field_api_endpoint();
        $this->add_field_backoff_duration();
        $this->add_field_slack_hoook_url();
        $this->add_field_slack_channel_name();
        $this->add_field_slack_bot_name();
        $this->add_field_validation_publication_reason();
        $this->add_field_validation_article_lifetime();
        $this->add_alternate_primary_category_selectbox();
        $this->add_alternate_primary_category_textbox();
        $this->add_field_google_youtube_api_key_textbox();
        $this->add_field_enable_quick_edit();
        $this->add_field_enable_custom_post_type_events();
        $this->add_field_enable_author_events();
        $this->add_field_enable_terms_events();
    }

    /**
     * FIELD - bus_status
     */
    public function add_field_bus_status()
    {
        add_settings_field(
            'wp_bus_' . Enum::FIELD_BUS_STATUS,
            // Use $args' label_for to populate the id inside the callback.
            'Enable Bus API<span style="color:red;">*</span>',
            [self::class, 'field_bus_status_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_SETTINGS_SECTION_1,
            [
                'label_for' => Enum::FIELD_BUS_STATUS,
                'class' => 'ringier-bus-row',
                'field_custom_data' => Enum::FIELD_BUS_STATUS,
            ]
        );
    }

    /**
     * field bus status callback function.
     *
     * WordPress has magic interaction with the following keys: label_for, class.
     * - the "label_for" key value is used for the "for" attribute of the <label>.
     * - the "class" key value is used for the "class" attribute of the <tr> containing the field.
     * Note: you can add custom key value pairs to be used inside your callbacks.
     *
     * @param array $args
     */
    public static function field_bus_status_callback($args)
    {
        $options = get_option(Enum::SETTINGS_PAGE_OPTION_NAME);

        $args['field_bus_status_name'] = Enum::SETTINGS_PAGE_OPTION_NAME . '[' . $args['label_for'] . ']';
        $args['field_selected_value'] = $options[$args['label_for']] ?? '';

        Utils::load_tpl(
            RINGIER_BUS_PLUGIN_VIEWS . 'admin' . RINGIER_BUS_DS . 'field-bus-status-dropdown.php',
            $args
        );
    }

    /**
     * FIELD - VENTURE CONFIG
     */
    public function add_field_venture_config()
    {
        add_settings_field(
            'wp_bus_' . Enum::FIELD_VENTURE_CONFIG,
            // Use $args' label_for to populate the id inside the callback.
            'Event Bus Node ID<span style="color:red;">*</span>',
            [self::class, 'field_venture_config_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_SETTINGS_SECTION_1,
            [
                'label_for' => Enum::FIELD_VENTURE_CONFIG,
                'class' => 'ringier-bus-row',
                'field_custom_data' => Enum::FIELD_VENTURE_CONFIG,
            ]
        );
    }

    /**
     * field venture_config callback function.
     *
     * @param array $args
     */
    public static function field_venture_config_callback($args)
    {
        self::render_field_tpl($args, 'field-venture-config.php');
    }

    /**
     * FIELD - API Locale
     */
    public function add_field_app_locale()
    {
        add_settings_field(
            'wp_bus_' . Enum::FIELD_APP_LOCALE,
            // Use $args' label_for to populate the id inside the callback.
            'Site Locale<span style="color:red;">*</span>',
            [self::class, 'field_app_locale_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_SETTINGS_SECTION_1,
            [
                'label_for' => Enum::FIELD_APP_LOCALE,
                'class' => 'ringier-bus-row',
                'field_custom_data' => Enum::FIELD_APP_LOCALE,
            ]
        );
    }

    public static function field_app_locale_callback($args)
    {
        self::render_field_tpl($args, 'field-app-locale.php');
    }

    /**
     * FIELD - APP KEY
     */
    public function add_field_app_key()
    {
        add_settings_field(
            'wp_bus_' . Enum::FIELD_APP_KEY,
            // Use $args' label_for to populate the id inside the callback.
            'Site Identifier<span style="color:red;">*</span>',
            [self::class, 'field_app_key_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_SETTINGS_SECTION_1,
            [
                'label_for' => Enum::FIELD_APP_KEY,
                'class' => 'ringier-bus-row',
                'field_custom_data' => Enum::FIELD_APP_KEY,
            ]
        );
    }

    public static function field_app_key_callback($args)
    {
        self::render_field_tpl($args, 'field-app-key.php');
    }

    /**
     * FIELD - API USERNAME
     */
    public function add_field_api_username()
    {
        add_settings_field(
            'wp_bus_' . Enum::FIELD_API_USERNAME,
            // Use $args' label_for to populate the id inside the callback.
            'Event Bus API Username<span style="color:red;">*</span>',
            [self::class, 'field_api_username_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_SETTINGS_SECTION_1,
            [
                'label_for' => Enum::FIELD_API_USERNAME,
                'class' => 'ringier-bus-row',
                'field_custom_data' => Enum::FIELD_API_USERNAME,
            ]
        );
    }

    public static function field_api_username_callback($args)
    {
        self::render_field_tpl($args, 'field-api-username.php');
    }

    /**
     * FIELD - API PASSWORD
     */
    public function add_field_api_password()
    {
        add_settings_field(
            'wp_bus_' . Enum::FIELD_API_PASSWORD,
            // Use $args' label_for to populate the id inside the callback.
            'Event Bus API Password<span style="color:red;">*</span>',
            [self::class, 'field_api_password_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_SETTINGS_SECTION_1,
            [
                'label_for' => Enum::FIELD_API_PASSWORD,
                'class' => 'ringier-bus-row',
                'field_custom_data' => Enum::FIELD_API_PASSWORD,
            ]
        );
    }

    public static function field_api_password_callback($args)
    {
        self::render_field_tpl($args, 'field-api-password.php');
    }

    /**
     * FIELD - API Endpoint
     */
    public function add_field_api_endpoint()
    {
        add_settings_field(
            'wp_bus_' . Enum::FIELD_API_ENDPOINT,
            // Use $args' label_for to populate the id inside the callback.
            'Event Bus API Endpoint (URL)<span style="color:red;">*</span>',
            [self::class, 'field_api_endpoint_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_SETTINGS_SECTION_1,
            [
                'label_for' => Enum::FIELD_API_ENDPOINT,
                'class' => 'ringier-bus-row',
                'field_custom_data' => Enum::FIELD_API_ENDPOINT,
            ]
        );
    }

    public static function field_api_endpoint_callback($args)
    {
        self::render_field_tpl($args, 'field-api-endpoint.php');
    }

    /**
     * FIELD - Slack Hook URL
     */
    public function add_field_slack_hoook_url()
    {
        add_settings_field(
            'wp_bus_' . Enum::FIELD_SLACK_HOOK_URL,
            // Use $args' label_for to populate the id inside the callback.
            'Slack Hook URL',
            [self::class, 'field_slack_hook_url_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_SETTINGS_SECTION_1,
            [
                'label_for' => Enum::FIELD_SLACK_HOOK_URL,
                'class' => 'ringier-bus-row',
                'field_custom_data' => Enum::FIELD_SLACK_HOOK_URL,
            ]
        );
    }

    public static function field_slack_hook_url_callback($args)
    {
        self::render_field_tpl($args, 'field-slack-hook-url.php');
    }

    /**
     * FIELD - Slack Channel Name
     */
    public function add_field_slack_channel_name()
    {
        add_settings_field(
            'wp_bus_' . Enum::FIELD_SLACK_CHANNEL_NAME,
            // Use $args' label_for to populate the id inside the callback.
            'Slack Channel Name (or ID)',
            [self::class, 'field_slack_channel_name_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_SETTINGS_SECTION_1,
            [
                'label_for' => Enum::FIELD_SLACK_CHANNEL_NAME,
                'class' => 'ringier-bus-row',
                'field_custom_data' => Enum::FIELD_SLACK_CHANNEL_NAME,
            ]
        );
    }

    public static function field_slack_channel_name_callback($args)
    {
        self::render_field_tpl($args, 'field-slack-channel-name.php');
    }

    /**
     * FIELD - Slack Bot Name
     */
    public function add_field_slack_bot_name()
    {
        add_settings_field(
            'wp_bus_' . Enum::FIELD_SLACK_BOT_NAME,
            // Use $args' label_for to populate the id inside the callback.
            'Slack Bot Name',
            [self::class, 'field_slack_bot_name_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_SETTINGS_SECTION_1,
            [
                'label_for' => Enum::FIELD_SLACK_BOT_NAME,
                'class' => 'ringier-bus-row',
                'field_custom_data' => Enum::FIELD_SLACK_BOT_NAME,
            ]
        );
    }

    public static function field_slack_bot_name_callback($args)
    {
        self::render_field_tpl($args, 'field-slack-bot-name.php');
    }

    /**
     * FIELD - Backoff Strategy (in Minutes)
     */
    public function add_field_backoff_duration()
    {
        add_settings_field(
            'wp_bus_' . Enum::FIELD_BACKOFF_DURATION,
            // Use $args' label_for to populate the id inside the callback.
            'Backoff Duration<span style="color:red;">*</span>',
            [self::class, 'field_backoff_duration_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_SETTINGS_SECTION_1,
            [
                'label_for' => Enum::FIELD_BACKOFF_DURATION,
                'class' => 'ringier-bus-row',
                'field_custom_data' => Enum::FIELD_BACKOFF_DURATION,
            ]
        );
    }

    public static function field_backoff_duration_callback($args)
    {
        self::render_field_tpl($args, 'field-backoff-duration.php');
    }

    /**
     * REFACTORED METHODS
     *
     * @param $args
     * @param $tpl_name
     */
    private static function render_field_tpl($args, $tpl_name): void
    {
        $options = get_option(Enum::SETTINGS_PAGE_OPTION_NAME);

        $field_value = '';
        if (isset($options[$args['label_for']])) {
            $field_value = $options[$args['label_for']];
        }

        $args['field_name'] = Enum::SETTINGS_PAGE_OPTION_NAME . '[' . $args['label_for'] . ']';
        $args['field_value'] = $field_value;

        Utils::load_tpl(
            RINGIER_BUS_PLUGIN_VIEWS . 'admin' . RINGIER_BUS_DS . $tpl_name,
            $args
        );
    }

    /**
     * FIELD - field_validation_publication_reason
     */
    public function add_field_validation_publication_reason(): void
    {
        add_settings_field(
            'wp_bus_' . Enum::FIELD_VALIDATION_PUBLICATION_REASON,
            // Use $args' label_for to populate the id inside the callback.
            'Enable validation for "Publication reason"',
            [self::class, 'field_validation_publication_reason_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_SETTINGS_SECTION_1,
            [
                'label_for' => Enum::FIELD_VALIDATION_PUBLICATION_REASON,
                'class' => 'ringier-bus-row validation-field first',
                'field_custom_data' => Enum::FIELD_VALIDATION_PUBLICATION_REASON,
            ]
        );
    }

    /**
     * field_validation_publication_reason callback function.
     *
     * WordPress has magic interaction with the following keys: label_for, class.
     * - the "label_for" key value is used for the "for" attribute of the <label>.
     * - the "class" key value is used for the "class" attribute of the <tr> containing the field.
     * Note: you can add custom key value pairs to be used inside your callbacks.
     *
     * @param array $args
     */
    public static function field_validation_publication_reason_callback($args): void
    {
        $options = get_option(Enum::SETTINGS_PAGE_OPTION_NAME);

        $args['field_bus_status_name'] = Enum::SETTINGS_PAGE_OPTION_NAME . '[' . $args['label_for'] . ']';
        $args['field_selected_value'] = $options[$args['label_for']] ?? '';

        Utils::load_tpl(
            RINGIER_BUS_PLUGIN_VIEWS . 'admin' . RINGIER_BUS_DS . 'field-validation-status-publication-reason.php',
            $args
        );
    }

    /**
     * FIELD - field_validation_article_lifetime
     */
    public function add_field_validation_article_lifetime(): void
    {
        add_settings_field(
            'wp_bus_' . Enum::FIELD_VALIDATION_ARTICLE_LIFETIME,
            // Use $args' label_for to populate the id inside the callback.
            'Enable validation for "Article lifetime"',
            [self::class, 'field_validation_article_lifetime_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_SETTINGS_SECTION_1,
            [
                'label_for' => Enum::FIELD_VALIDATION_ARTICLE_LIFETIME,
                'class' => 'ringier-bus-row validation-field',
                'field_custom_data' => Enum::FIELD_VALIDATION_ARTICLE_LIFETIME,
            ]
        );
    }

    /**
     * field_validation_article_lifetimes callback function.
     *
     * WordPress has magic interaction with the following keys: label_for, class.
     * - the "label_for" key value is used for the "for" attribute of the <label>.
     * - the "class" key value is used for the "class" attribute of the <tr> containing the field.
     * Note: you can add custom key value pairs to be used inside your callbacks.
     *
     * @param array $args
     */
    public static function field_validation_article_lifetime_callback($args): void
    {
        $options = get_option(Enum::SETTINGS_PAGE_OPTION_NAME);

        $args['field_bus_status_name'] = Enum::SETTINGS_PAGE_OPTION_NAME . '[' . $args['label_for'] . ']';
        $args['field_selected_value'] = $options[$args['label_for']] ?? '';

        Utils::load_tpl(
            RINGIER_BUS_PLUGIN_VIEWS . 'admin' . RINGIER_BUS_DS . 'field-validation-status-article-lifetime.php',
            $args
        );
    }

    /**
     * FIELD - field_status_alt_primary_category
     */
    public function add_alternate_primary_category_selectbox(): void
    {
        add_settings_field(
            'wp_bus_' . Enum::FIELD_STATUS_ALTERNATE_PRIMARY_CATEGORY,
            // Use $args' label_for to populate the id inside the callback.
            'Enable custom Top level Primary category',
            [self::class, 'field_alt_primary_category_selectbox_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_SETTINGS_SECTION_1,
            [
                'label_for' => Enum::FIELD_STATUS_ALTERNATE_PRIMARY_CATEGORY,
                'class' => 'ringier-bus-row alt-category-field first',
                'field_custom_data' => Enum::FIELD_STATUS_ALTERNATE_PRIMARY_CATEGORY,
            ]
        );
    }

    /**
     * field bus status callback function.
     *
     * @param $args
     */
    public static function field_alt_primary_category_selectbox_callback($args)
    {
        $options = get_option(Enum::SETTINGS_PAGE_OPTION_NAME);

        $args['field_bus_status_name'] = Enum::SETTINGS_PAGE_OPTION_NAME . '[' . $args['label_for'] . ']';
        $args['field_selected_value'] = $options[$args['label_for']] ?? '';

        Utils::load_tpl(
            RINGIER_BUS_PLUGIN_VIEWS . 'admin' . RINGIER_BUS_DS . 'field-alternate-primary-category-selectbox.php',
            $args
        );
    }

    /**
     * FIELD - field_text_alt_primary_category
     */
    public function add_alternate_primary_category_textbox(): void
    {
        add_settings_field(
            'wp_bus_' . Enum::FIELD_TEXT_ALTERNATE_PRIMARY_CATEGORY,
            // Use $args' label_for to populate the id inside the callback.
            'Primary category',
            [self::class, 'field_alt_primary_category_textbox_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_SETTINGS_SECTION_1,
            [
                'label_for' => Enum::FIELD_TEXT_ALTERNATE_PRIMARY_CATEGORY,
                'class' => 'ringier-bus-row alt-category-field',
                'field_custom_data' => Enum::FIELD_TEXT_ALTERNATE_PRIMARY_CATEGORY,
            ]
        );
    }

    /**
     * field field_text_alt_primary_category status callback function.
     *
     * @param $args
     */
    public static function field_alt_primary_category_textbox_callback($args): void
    {
        $options = get_option(Enum::SETTINGS_PAGE_OPTION_NAME);

        $field_value = parse_url(get_site_url(), PHP_URL_HOST);
        if (isset($options[$args['label_for']])) {
            $field_value = $options[$args['label_for']];
        }

        $args['field_name'] = Enum::SETTINGS_PAGE_OPTION_NAME . '[' . $args['label_for'] . ']';
        $args['field_value'] = $field_value;

        Utils::load_tpl(
            RINGIER_BUS_PLUGIN_VIEWS . 'admin' . RINGIER_BUS_DS . 'field-alternate-primary-category-textbox.php',
            $args
        );
    }

    /**
     * FIELD - field_google_youtube_api_key
     */
    public function add_field_google_youtube_api_key_textbox(): void
    {
        add_settings_field(
            'wp_bus_' . Enum::FIELD_GOOGLE_YOUTUBE_API_KEY,
            // Use $args' label_for to populate the id inside the callback.
            'Youtube API Key',
            [self::class, 'field_google_youtube_api_key_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_SETTINGS_SECTION_1,
            [
                'label_for' => Enum::FIELD_GOOGLE_YOUTUBE_API_KEY,
                'class' => 'ringier-bus-row alt-category-field first',
                'field_custom_data' => Enum::FIELD_GOOGLE_YOUTUBE_API_KEY,
            ]
        );
    }

    /**
     * field field_google_youtube_api_key callback
     *
     * @param $args
     */
    public static function field_google_youtube_api_key_callback($args): void
    {
        self::render_field_tpl($args, 'field-google-youtube-api-key.php');
    }

    /**
     * Adds the "Enable Quick Edit" checkbox field.
     */
    public function add_field_enable_quick_edit(): void
    {
        add_settings_field(
            'wp_bus_' . Enum::FIELD_ENABLE_QUICK_EDIT, // setting id
            'Enable Quick Edit button',
            [self::class, 'field_enable_quick_edit_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_SETTINGS_SECTION_1,
            [
                'label_for' => Enum::FIELD_ENABLE_QUICK_EDIT,
                'class' => 'ringier-bus-row first',
                'field_custom_data' => Enum::FIELD_ENABLE_QUICK_EDIT,
            ]
        );
    }

    /**
     * Callback to render "Enable Quick Edit" checkbox.
     *
     * @param array $args Arguments passed from the settings field.
     */
    public static function field_enable_quick_edit_callback(array $args): void
    {
        $options = get_option(Enum::SETTINGS_PAGE_OPTION_NAME);
        $args['is_checked'] = isset($options[$args['label_for']]) && $options[$args['label_for']] === 'on';

        Utils::load_tpl(
            RINGIER_BUS_PLUGIN_VIEWS . 'admin' . RINGIER_BUS_DS . 'field-enable-quick-edit.php',
            $args
        );
    }

    /**
     * field field_enable_custom_post_type_events
     */
    public function add_field_enable_custom_post_type_events(): void
    {
        add_settings_field(
            'wp_bus_' . Enum::FIELD_ALLOW_CUSTOM_POST_TYPES,
            'Enable custom post types Events',
            [self::class, 'field_enable_custom_post_type_events_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_SETTINGS_SECTION_1,
            [
                'label_for' => Enum::FIELD_ALLOW_CUSTOM_POST_TYPES,
                'class' => 'ringier-bus-row',
                'field_custom_data' => Enum::FIELD_ALLOW_CUSTOM_POST_TYPES,
            ]
        );
    }

    public static function field_enable_custom_post_type_events_callback($args): void
    {
        $options = get_option(Enum::SETTINGS_PAGE_OPTION_NAME);

        $args['master_checked'] = !empty($options[Enum::FIELD_ALLOW_CUSTOM_POST_TYPES]) && $options[Enum::FIELD_ALLOW_CUSTOM_POST_TYPES] === 'on';
        $args['allowed_post_types'] = $options[Enum::FIELD_ENABLED_CUSTOM_POST_TYPE_LIST] ?? [];

        Utils::load_tpl(
            RINGIER_BUS_PLUGIN_VIEWS . 'admin' . RINGIER_BUS_DS . 'field-custom-post-type-events.php',
            $args
        );
    }

    /**
     * field field_enable_author_events
     */
    public function add_field_enable_author_events(): void
    {
        add_settings_field(
            'wp_bus_' . Enum::FIELD_ENABLE_AUTHOR_EVENTS,
            'Enable Author Events',
            [self::class, 'field_enable_author_events_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_SETTINGS_SECTION_1,
            [
                'label_for' => Enum::FIELD_ENABLE_AUTHOR_EVENTS,
                'class' => 'ringier-bus-row first',
                'field_custom_data' => Enum::FIELD_ENABLE_AUTHOR_EVENTS,
            ]
        );
    }

    public static function field_enable_author_events_callback($args): void
    {
        $options = get_option(Enum::SETTINGS_PAGE_OPTION_NAME);
        $args['is_checked'] = isset($options[$args['label_for']]) && $options[$args['label_for']] === 'on';

        Utils::load_tpl(
            RINGIER_BUS_PLUGIN_VIEWS . 'admin' . RINGIER_BUS_DS . 'field-toggle-author-events-checkbox.php',
            $args
        );
    }

    /**
     * field field_enable_terms_events
     */
    public function add_field_enable_terms_events(): void
    {
        add_settings_field(
            'wp_bus_' . Enum::FIELD_ENABLE_TERMS_EVENTS,
            'Enable Topic Events',
            [self::class, 'field_enable_terms_events_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_SETTINGS_SECTION_1,
            [
                'label_for' => Enum::FIELD_ENABLE_TERMS_EVENTS,
                'class' => 'ringier-bus-row',
                'field_custom_data' => Enum::FIELD_ENABLE_TERMS_EVENTS,
            ]
        );
    }

    public static function field_enable_terms_events_callback($args): void
    {
        $options = get_option(Enum::SETTINGS_PAGE_OPTION_NAME);
        $args['is_checked'] = isset($options[$args['label_for']]) && $options[$args['label_for']] === 'on';

        Utils::load_tpl(
            RINGIER_BUS_PLUGIN_VIEWS . 'admin' . RINGIER_BUS_DS . 'field-toggle-terms-events-checkbox.php',
            $args
        );
    }
}
