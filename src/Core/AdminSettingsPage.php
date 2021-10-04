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

    /* ******************************************** */
    //FIELD - bus_status
    /* ******************************************** */
    public function add_fields_via_settingsAPI()
    {
        // field BUS STATUS - Register new field for BUS ON/OFF
        add_settings_field(
            'wp_bus_field_bus_status',
            // Use $args' label_for to populate the id inside the callback.
            'Enable Bus API',
            [self::class, 'field_bus_status_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_SETTINGS_SECTION_1,
            array(
                'label_for'         => 'field_bus_status',
                'class'             => 'wp-bus-row',
                'field_custom_data' => 'custom',
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
            $bus_status_selected_on = selected( $options[ $args['label_for'] ], 'on', false);
            $bus_status_selected_off = selected( $options[ $args['label_for'] ], 'off', false);
        }
        d($bus_status_selected_on);
        d($bus_status_selected_off);
        d(get_option('field_bus_status'));
        d(get_option('wp_bus_settingspage_options'));

        if (file_exists($field_bus_status_tpl)) {
            $context['field_bus_status_name'] = Enum::SETTINGS_PAGE_OPTION_NAME . '[' . esc_attr($args['label_for']) . ']';
            $context['label_for'] = esc_attr($args['label_for']);
            $context['field_custom_data'] = esc_attr($args['field_custom_data']);
            $context['field_custom_data_selected_on'] = $bus_status_selected_on;
            $context['field_custom_data_selected_off'] = $bus_status_selected_off;

            $timber::render($field_bus_status_tpl, $context);
        }
        unset($timber);
    }

    /* ******************************************** */
    //FIELD -
    /* ******************************************** */
}
