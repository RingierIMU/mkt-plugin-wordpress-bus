<?php
/**
 * To handle everything regarding our main Admin Bus API Settings Page
 *
 * @author Wasseem Khayrattee <wasseemk@ringier.co.za>
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
    public function handle_admin_ui()
    {
        $this->add_admin_pages();

        // Register a new setting for our page.
        register_setting(Enum::SETTINGS_PAGE_OPTION_GROUP, Enum::SETTINGS_PAGE_OPTION_NAME);

        // Register a new section in our page.
        add_settings_section(
            Enum::ADMIN_SETTINGS_SECTION_1,
            'Please fill in the below',
            [self::class, 'settings_section_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG
        );
    }

    public static function settings_section_callback($args)
    {
        //silence for now
    }

    public function add_admin_pages()
    {
        add_menu_page(
            Enum::ADMIN_SETTINGS_PAGE_TITLE,
            Enum::ADMIN_SETTINGS_MENU_TITLE,
            'manage_options',
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            [self::class, 'render_settings_page'],
            'dashicons-rest-api',
            20
        );
        $this->add_fields_via_settingsAPI();
    }

    /**
     * Handle & Render our Admin Settings Page
     */
    public static function render_settings_page()
    {
        global $title;

        if (! current_user_can('manage_options')) {
            return;
        }

        $timber = new Timber();
        $settings_page_tpl = WP_BUS_RINGIER_PLUGIN_VIEWS . 'admin' . DS . 'page_settings.twig';

        if (file_exists($settings_page_tpl)) {
            $context['admin_page_title'] = $title;
            $context['settings_fields'] = new FunctionWrapper('settings_fields', [Enum::SETTINGS_PAGE_OPTION_GROUP]);
            $context['do_settings_sections'] = new FunctionWrapper('do_settings_sections', [Enum::ADMIN_SETTINGS_MENU_SLUG]);
            $context['submit_button'] = new FunctionWrapper('submit_button', ['Save Settings']);

            $timber::render($settings_page_tpl, $context);
        }
        unset($timber);
    }

    public function add_fields_via_settingsAPI()
    {
        $this->add_field_bus_status();
        $this->add_field_venture_config();
        $this->add_field_api_username();
        $this->add_field_api_password();
        $this->add_field_api_endpoint();
        $this->add_field_slack_hoook_url();
        $this->add_field_slack_channel_name();
        $this->add_field_slack_bot_name();
        $this->add_field_backoff_duration();
    }

    /* ******************************************** */
    //FIELD - bus_status
    /* ******************************************** */
    public function add_field_bus_status()
    {
        add_settings_field(
            'wp_bus_' . Enum::FIELD_BUS_STATUS,
            // Use $args' label_for to populate the id inside the callback.
            'Enable Bus API',
            [self::class, 'field_bus_status_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_SETTINGS_SECTION_1,
            array(
                'label_for'         => Enum::FIELD_BUS_STATUS,
                'class'             => 'wp-bus-row',
                'field_custom_data' => Enum::FIELD_BUS_STATUS,
            )
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

        $timber = new Timber();
        $field_bus_status_tpl = WP_BUS_RINGIER_PLUGIN_VIEWS . 'admin' . DS . 'field_bus_status_dropdown.twig';

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

            $timber::render($field_bus_status_tpl, $context);
        }
        unset($timber);
    }

    /* ******************************************** */
    //FIELD - VENTURE CONFIG
    /* ******************************************** */
    public function add_field_venture_config()
    {
        add_settings_field(
            'wp_bus_' . Enum::FIELD_VENTURE_CONFIG,
            // Use $args' label_for to populate the id inside the callback.
            'Venture Config',
            [self::class, 'field_venture_config_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_SETTINGS_SECTION_1,
            array(
                'label_for'         => Enum::FIELD_VENTURE_CONFIG,
                'class'             => 'wp-bus-row',
                'field_custom_data' => Enum::FIELD_VENTURE_CONFIG,
            )
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

    /* ******************************************** */
    //FIELD - API USERNAME
    /* ******************************************** */
    public function add_field_api_username()
    {
        add_settings_field(
            'wp_bus_' . Enum::FIELD_API_USERNAME,
            // Use $args' label_for to populate the id inside the callback.
            'API Username',
            [self::class, 'field_api_username_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_SETTINGS_SECTION_1,
            array(
                'label_for'         => Enum::FIELD_API_USERNAME,
                'class'             => 'wp-bus-row',
                'field_custom_data' => Enum::FIELD_API_USERNAME,
            )
        );
    }

    public static function field_api_username_callback($args)
    {
        self::render_field_tpl($args, 'field_api_username.twig');
    }

    /* ******************************************** */
    //FIELD - API PASSWORD
    /* ******************************************** */
    public function add_field_api_password()
    {
        add_settings_field(
            'wp_bus_' . Enum::FIELD_API_PASSWORD,
            // Use $args' label_for to populate the id inside the callback.
            'API Password',
            [self::class, 'field_api_password_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_SETTINGS_SECTION_1,
            array(
                'label_for'         => Enum::FIELD_API_PASSWORD,
                'class'             => 'wp-bus-row',
                'field_custom_data' => Enum::FIELD_API_PASSWORD,
            )
        );
    }

    public static function field_api_password_callback($args)
    {
        self::render_field_tpl($args, 'field_api_password.twig');
    }

    /* ******************************************** */
    //FIELD - API Endpoint
    /* ******************************************** */
    public function add_field_api_endpoint()
    {
        add_settings_field(
            'wp_bus_' . Enum::FIELD_API_ENDPOINT,
            // Use $args' label_for to populate the id inside the callback.
            'API Endpoint (URL)',
            [self::class, 'field_api_endpoint_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_SETTINGS_SECTION_1,
            array(
                'label_for'         => Enum::FIELD_API_ENDPOINT,
                'class'             => 'wp-bus-row',
                'field_custom_data' => Enum::FIELD_API_ENDPOINT,
            )
        );
    }

    public static function field_api_endpoint_callback($args)
    {
        self::render_field_tpl($args, 'field_api_endpoint.twig');
    }

    /* ******************************************** */
    //FIELD - Slack Hook URL
    /* ******************************************** */
    public function add_field_slack_hoook_url()
    {
        add_settings_field(
            'wp_bus_' . Enum::FIELD_SLACK_HOOK_URL,
            // Use $args' label_for to populate the id inside the callback.
            'Slack Hook URL',
            [self::class, 'field_slack_hook_url_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_SETTINGS_SECTION_1,
            array(
                'label_for'         => Enum::FIELD_SLACK_HOOK_URL,
                'class'             => 'wp-bus-row',
                'field_custom_data' => Enum::FIELD_SLACK_HOOK_URL,
            )
        );
    }

    public static function field_slack_hook_url_callback($args)
    {
        self::render_field_tpl($args, 'field_slack_hook_url.twig');
    }

    /* ******************************************** */
    //FIELD - Slack Channel Name
    /* ******************************************** */
    public function add_field_slack_channel_name()
    {
        add_settings_field(
            'wp_bus_' . Enum::FIELD_SLACK_CHANNEL_NAME,
            // Use $args' label_for to populate the id inside the callback.
            'Slack Channel Name',
            [self::class, 'field_slack_channel_name_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_SETTINGS_SECTION_1,
            array(
                'label_for'         => Enum::FIELD_SLACK_CHANNEL_NAME,
                'class'             => 'wp-bus-row',
                'field_custom_data' => Enum::FIELD_SLACK_CHANNEL_NAME,
            )
        );
    }

    public static function field_slack_channel_name_callback($args)
    {
        self::render_field_tpl($args, 'field_slack_channel_name.twig');
    }

    /* ******************************************** */
    //FIELD - Slack Bot Name
    /* ******************************************** */
    public function add_field_slack_bot_name()
    {
        add_settings_field(
            'wp_bus_' . Enum::FIELD_SLACK_BOT_NAME,
            // Use $args' label_for to populate the id inside the callback.
            'Slack Bot Name',
            [self::class, 'field_slack_bot_name_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_SETTINGS_SECTION_1,
            array(
                'label_for'         => Enum::FIELD_SLACK_BOT_NAME,
                'class'             => 'wp-bus-row',
                'field_custom_data' => Enum::FIELD_SLACK_BOT_NAME,
            )
        );
    }

    public static function field_slack_bot_name_callback($args)
    {
        self::render_field_tpl($args, 'field_slack_bot_name.twig');
    }

    /* ******************************************** */
    //FIELD - Backoff Strategy (in Minutes)
    /* ******************************************** */
    public function add_field_backoff_duration()
    {
        add_settings_field(
            'wp_bus_' . Enum::FIELD_BACKOFF_DURATION,
            // Use $args' label_for to populate the id inside the callback.
            'Backoff Duration',
            [self::class, 'field_backoff_duration_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_SETTINGS_SECTION_1,
            array(
                'label_for'         => Enum::FIELD_BACKOFF_DURATION,
                'class'             => 'wp-bus-row',
                'field_custom_data' => Enum::FIELD_BACKOFF_DURATION,
            )
        );
    }

    public static function field_backoff_duration_callback($args)
    {
        self::render_field_tpl($args, 'field_backoff_duration.twig');
    }

    /* ******************************************** */
    //REFACTORED METHODS
    /* ******************************************** */
    /**
     * @param $args
     * @param $tpl_name
     */
    private static function render_field_tpl($args, $tpl_name): void
    {
        // Get the value of the setting we've registered with register_setting()
        $options = get_option(Enum::SETTINGS_PAGE_OPTION_NAME);

        $timber = new Timber();
        $field_tpl = WP_BUS_RINGIER_PLUGIN_VIEWS . 'admin' . DS . $tpl_name;

        $field_value = '';
        if (isset($options[$args['label_for']])) {
            $field_value = $options[$args['label_for']];
        }

        if (file_exists($field_tpl)) {
            $context['field_name'] = Enum::SETTINGS_PAGE_OPTION_NAME . '[' . esc_attr($args['label_for']) . ']';
            $context['label_for'] = esc_attr($args['label_for']);
            $context['field_custom_data'] = esc_attr($args['field_custom_data']);
            $context['field_value'] = esc_attr($field_value);

            $timber::render($field_tpl, $context);
        }
        unset($timber);
    }
}
