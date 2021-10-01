<?php
/**
 *  Handles the Main plugin hooks
 *
 * @author Wasseem Khayrattee <wasseemk@ringier.co.za>
 * @github wkhayrattee
 */
namespace RingierBusPlugin;

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

    public static function admin_init()
    {
        add_action( 'admin_menu', [self::class, 'wp_bus_admin_page']);
//        add_action( 'admin_enqueue_scripts', [self::class, 'register_wp_bus_plugin_scripts']);
    }

    public static function wp_bus_admin_page()
    {
        $hookname = add_menu_page(
            'Ringier Bus API Settings',
            'Bus API',
            'manage_options',
            'wp-bus-api',
            [self::class, 'render_settings_page'],
            'dashicons-rest-api',
            20
        );

        add_submenu_page(
            'wp-bus-api',
            'Bus API Message Log',
            'Message Log',
            'manage_options',
            'wp-bus-log',
            [self::class, 'render_log_page']
        );

//        add_action('load-' . $hookname, 'wp_bus_admin_page_submit');
    }

    public static function register_wp_bus_plugin_scripts()
    {
        // Load styles
        // WordPress has many defaults here: https://developer.wordpress.org/themes/basics/including-css-javascript/#default-scripts-included-and-registered-by-wordpress
    }

    public static function render_settings_page()
    {
        global $title;

        print '<div class="wrap">';
        print "<h1>$title</h1>";

        submit_button( 'Click me!' );

        print '</div>';
    }
    public static function render_log_page()
    {
        global $title;

        print '<div class="wrap">';
        print "<h1>$title</h1>";

        submit_button( 'Click me!' );

        print '</div>';
    }
}
