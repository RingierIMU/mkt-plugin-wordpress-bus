<?php
/**
 * To handle everything regarding the main Admin Bus API Settings Page
 *
 * @author Wasseem Khayrattee <wasseemk@ringier.co.za>
 *
 * @github wkhayrattee
 */

namespace RingierBusPlugin;

use Timber\FunctionWrapper;
use Timber\Timber;

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
            null,
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
     * Handle & Render our Admin Settings Page
     */
    public static function renderSettingsPage()
    {
        global $title;

        if (!current_user_can('manage_options')) {
            return;
        }

        $settings_page_tpl = RINGIER_BUS_PLUGIN_VIEWS . 'admin' . RINGIER_BUS_DS . 'page_settings.twig';
        if (file_exists($settings_page_tpl)) {
            $context['admin_page_title'] = $title;
            $context['settings_fields'] = new FunctionWrapper('settings_fields', [Enum::SETTINGS_PAGE_OPTION_GROUP]);
            $context['do_settings_sections'] = new FunctionWrapper('do_settings_sections', [Enum::ADMIN_SETTINGS_MENU_SLUG]);
            $context['submit_button'] = new FunctionWrapper('submit_button', ['Save Settings']);

            Timber::render($settings_page_tpl, $context);
        }
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
        // Get the value of the setting we've registered with register_setting()
        $options = get_option(Enum::SETTINGS_PAGE_OPTION_NAME);

        $field_bus_status_tpl = RINGIER_BUS_PLUGIN_VIEWS . 'admin' . RINGIER_BUS_DS . 'field_bus_status_dropdown.twig';
        $bus_status_selected_on = $bus_status_selected_off = '';
        if (isset($options[$args['label_for']])) {
            $bus_status_selected_on = selected($options[ $args['label_for'] ], 'on', false);
            $bus_status_selected_off = selected($options[ $args['label_for'] ], 'off', false);
        }

        if (file_exists($field_bus_status_tpl)) {
            $context['field_bus_status_name'] = Enum::SETTINGS_PAGE_OPTION_NAME . '[' . esc_attr($args['label_for']) . ']';
            $context['label_for'] = esc_attr($args['label_for']);
            $context['field_custom_data'] = esc_attr($args['field_custom_data']);
            $context['field_custom_data_selected_on'] = esc_attr($bus_status_selected_on);
            $context['field_custom_data_selected_off'] = esc_attr($bus_status_selected_off);

            Timber::render($field_bus_status_tpl, $context);
        }
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
        self::render_field_tpl($args, 'field_venture_config.twig');
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
        self::render_field_tpl($args, 'field_app_locale.twig');
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
        self::render_field_tpl($args, 'field_app_key.twig');
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
        self::render_field_tpl($args, 'field_api_username.twig');
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
        self::render_field_tpl($args, 'field_api_password.twig');
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
        self::render_field_tpl($args, 'field_api_endpoint.twig');
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
        self::render_field_tpl($args, 'field_slack_hook_url.twig');
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
        self::render_field_tpl($args, 'field_slack_channel_name.twig');
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
        self::render_field_tpl($args, 'field_slack_bot_name.twig');
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
        self::render_field_tpl($args, 'field_backoff_duration.twig');
    }

    /**
     * REFACTORED METHODS
     *
     * @param $args
     * @param $tpl_name
     */
    private static function render_field_tpl($args, $tpl_name): void
    {
        // Get the value of the setting we've registered with register_setting()
        $options = get_option(Enum::SETTINGS_PAGE_OPTION_NAME);

        $field_tpl = RINGIER_BUS_PLUGIN_VIEWS . 'admin' . RINGIER_BUS_DS . $tpl_name;
        $field_value = '';
        if (isset($options[$args['label_for']])) {
            $field_value = $options[$args['label_for']];
        }

        if (file_exists($field_tpl)) {
            $context['field_name'] = Enum::SETTINGS_PAGE_OPTION_NAME . '[' . esc_attr($args['label_for']) . ']';
            $context['label_for'] = esc_attr($args['label_for']);
            $context['field_custom_data'] = esc_attr($args['field_custom_data']);
            $context['field_value'] = esc_attr($field_value);

            Timber::render($field_tpl, $context);
        }
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
        // Get the value of the setting we've registered with register_setting()
        $options = get_option(Enum::SETTINGS_PAGE_OPTION_NAME);

        $field_bus_status_tpl = RINGIER_BUS_PLUGIN_VIEWS . 'admin' . RINGIER_BUS_DS . 'field_validation_status_publication_reason.twig';
        $bus_status_selected_on = $bus_status_selected_off = '';
        if (isset($options[$args['label_for']])) {
            $bus_status_selected_on = selected($options[ $args['label_for'] ], 'on', false);
            $bus_status_selected_off = selected($options[ $args['label_for'] ], 'off', false);
        }

        if (file_exists($field_bus_status_tpl)) {
            $context['field_bus_status_name'] = Enum::SETTINGS_PAGE_OPTION_NAME . '[' . esc_attr($args['label_for']) . ']';
            $context['label_for'] = esc_attr($args['label_for']);
            $context['field_custom_data'] = esc_attr($args['field_custom_data']);
            $context['field_custom_data_selected_on'] = esc_attr($bus_status_selected_on);
            $context['field_custom_data_selected_off'] = esc_attr($bus_status_selected_off);

            Timber::render($field_bus_status_tpl, $context);
        }
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
        // Get the value of the setting we've registered with register_setting()
        $options = get_option(Enum::SETTINGS_PAGE_OPTION_NAME);

        $field_bus_status_tpl = RINGIER_BUS_PLUGIN_VIEWS . 'admin' . RINGIER_BUS_DS . 'field_validation_status_article_lifetime.twig';
        $bus_status_selected_on = $bus_status_selected_off = '';
        if (isset($options[$args['label_for']])) {
            $bus_status_selected_on = selected($options[ $args['label_for'] ], 'on', false);
            $bus_status_selected_off = selected($options[ $args['label_for'] ], 'off', false);
        }

        if (file_exists($field_bus_status_tpl)) {
            $context['field_bus_status_name'] = Enum::SETTINGS_PAGE_OPTION_NAME . '[' . esc_attr($args['label_for']) . ']';
            $context['label_for'] = esc_attr($args['label_for']);
            $context['field_custom_data'] = esc_attr($args['field_custom_data']);
            $context['field_custom_data_selected_on'] = esc_attr($bus_status_selected_on);
            $context['field_custom_data_selected_off'] = esc_attr($bus_status_selected_off);

            Timber::render($field_bus_status_tpl, $context);
        }
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
        // Get the value of the setting we've registered with register_setting()
        $options = get_option(Enum::SETTINGS_PAGE_OPTION_NAME);

        $field_status_alt_category_tpl = RINGIER_BUS_PLUGIN_VIEWS . 'admin' . RINGIER_BUS_DS . 'field_alternate_primary_category_selectbox.twig';
        $bus_status_selected_on = $bus_status_selected_off = '';
        if (isset($options[$args['label_for']])) {
            $bus_status_selected_on = selected($options[ $args['label_for'] ], 'on', false);
            $bus_status_selected_off = selected($options[ $args['label_for'] ], 'off', false);
        }

        if (file_exists($field_status_alt_category_tpl)) {
            //handle select box
            $context['field_bus_status_name'] = Enum::SETTINGS_PAGE_OPTION_NAME . '[' . esc_attr($args['label_for']) . ']';
            $context['label_for'] = esc_attr($args['label_for']);
            $context['field_custom_data'] = esc_attr($args['field_custom_data']);
            $context['field_custom_data_selected_on'] = esc_attr($bus_status_selected_on);
            $context['field_custom_data_selected_off'] = esc_attr($bus_status_selected_off);

            Timber::render($field_status_alt_category_tpl, $context);
        }
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
        // Get the value of the setting we've registered with register_setting()
        $options = get_option(Enum::SETTINGS_PAGE_OPTION_NAME);

        $field_tpl = RINGIER_BUS_PLUGIN_VIEWS . 'admin' . RINGIER_BUS_DS . 'field_alternate_primary_category_textbox.twig';
        $field_value = parse_url(get_site_url(), PHP_URL_HOST);
        if (isset($options[$args['label_for']])) {
            $field_value = $options[$args['label_for']];
        }

        if (file_exists($field_tpl)) {
            $context['field_name'] = Enum::SETTINGS_PAGE_OPTION_NAME . '[' . esc_attr($args['label_for']) . ']';
            $context['label_for'] = esc_attr($args['label_for']);
            $context['field_custom_data'] = esc_attr($args['field_custom_data']);
            $context['field_value'] = esc_attr($field_value);

            Timber::render($field_tpl, $context);
        }
    }
}
