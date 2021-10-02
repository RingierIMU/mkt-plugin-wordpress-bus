<?php
/**
 *  Handles the Main plugin hooks
 *
 * @author Wasseem Khayrattee <wasseemk@ringier.co.za>
 * @github wkhayrattee
 */
namespace RingierBusPlugin;

use Timber\Timber;

class BusPluginClass
{
    /**
     * Attached to activate_{ plugin_basename( __FILES__ ) } by register_activation_hook()
     * @static
     */
    public static function plugin_activation()
    {
        //nothing for now
        logthis('bus_plugin_activated');
    }

    /**
     * Should remove any scheduled events
     * NOTE: The database data cleaning is handled by uninstall.php
     * @static
     */
    public static function plugin_deactivation()
    {
        logthis('bus_plugin_deactivated');

        // TODO: Remove any scheduled cron jobs.
//        $akismet_cron_events = array(
//            'akismet_schedule_cron_recheck',
//            'akismet_scheduled_delete',
//        );
//
//        foreach ( $akismet_cron_events as $akismet_cron_event ) {
//            $timestamp = wp_next_scheduled( $akismet_cron_event );
//
//            if ( $timestamp ) {
//                wp_unschedule_event( $timestamp, $akismet_cron_event );
//            }
//        }
    }

    /**
     * Render the admin pages
     */
    public static function admin_init()
    {
        add_action( 'admin_menu', [self::class, 'handle_admin_ui']);
    }

    /**
     * Main method for handling the admin pages
     */
    public static function handle_admin_ui()
    {
        self::add_admin_pages();

        // Register a new setting for our page.
        register_setting( 'wp_bus_settingspage_group', 'wp_bus_settingspage_options' );

        // Register a new section in our page.
        add_settings_section(
            Enum::ADMIN_SETTINGS_SECTION_1,
            __( 'Please fill in the below', Enum::PLUGIN_KEY), [self::class, 'settings_section_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG
        );

        // Register a new field in the "wporg_section_developers" section, inside the "wporg" page.
        add_settings_field(
            'wp_bus_field_pill',
            // Use $args' label_for to populate the id inside the callback.
            __( 'Pill', 'wporg' ),
            [self::class, 'field_dropdown_callback'],
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_SETTINGS_SECTION_1,
            array(
                'label_for'         => 'wporg_field_pill',
                'class'             => 'wporg_row',
                'field_custom_data' => 'custom',
            )
        );
    }

    public static function settings_section_callback( $args ) {
        ?>
        <p id="<?php echo esc_attr( $args['id'] ); ?>"><?php esc_html_e( 'Follow the white rabbit.', 'wporg' ); ?></p>
        <?php
    }

    /**
     * Pill field callbakc function.
     *
     * WordPress has magic interaction with the following keys: label_for, class.
     * - the "label_for" key value is used for the "for" attribute of the <label>.
     * - the "class" key value is used for the "class" attribute of the <tr> containing the field.
     * Note: you can add custom key value pairs to be used inside your callbacks.
     *
     * @param array $args
     */
    public static function field_dropdown_callback( $args ) {
        // Get the value of the setting we've registered with register_setting()
        $options = get_option( 'wporg_options' );

        $timber = new Timber();
        $field_dropdown_tpl = WP_BUS_RINGIER_PLUGIN_VIEWS . 'admin' . DS . 'fields_settings_dropdown.twig';

        if (file_exists($field_dropdown_tpl)) {
            $context['label_for'] = esc_attr( $args['label_for'] );;
            $context['field_custom_data'] = esc_attr( $args['wporg_custom_data'] );;

            $timber::render($field_dropdown_tpl, $context);
        }
        unset($timber);
    }

    /**
     * Register the Admin pages for our plugin
     */
    public static function add_admin_pages()
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

        add_submenu_page(
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_LOG_PAGE_TITLE,
            Enum::ADMIN_LOG_MENU_TITLE,
            'manage_options',
            Enum::ADMIN_LOG_MENU_SLUG,
            [self::class, 'render_log_page']
        );
    }

    /**
     * Handle & Render our Admin Settings Page
     */
    public static function render_settings_page()
    {
        global $title;

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $timber = new Timber();
        $settings_page_tpl = WP_BUS_RINGIER_PLUGIN_VIEWS . 'admin' . DS . 'page_settings.twig';

        if (file_exists($settings_page_tpl)) {
            $context[] = '';

            $timber::render($settings_page_tpl, $context);
        }
        unset($timber);
    }

    public static function render_log_page()
    {
        global $title;

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        print '<div class="wrap">';
        print "<h1>$title</h1>";

        submit_button( 'Click me!' );

        print '</div>';
    }

    public static function register_wp_bus_plugin_scripts()
    {
        // Load styles
        // WordPress has many defaults here: https://developer.wordpress.org/themes/basics/including-css-javascript/#default-scripts-included-and-registered-by-wordpress
    }
}
