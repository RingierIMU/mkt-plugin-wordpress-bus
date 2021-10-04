<?php
/**
 *  Handles the Main plugin hooks
 *
 * @author Wasseem Khayrattee <wasseemk@ringier.co.za>
 * @github wkhayrattee
 */
namespace RingierBusPlugin;

use Timber\FunctionWrapper;
use Timber\Timber;

class BusPluginClass
{
    /**
     * Attached to activate_{ plugin_basename( __FILES__ ) } by register_activation_hook()
     * @static
     */
    public static function plugin_activation()
    {
        add_option( Enum::PLUGIN_KEY, true );
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
     * triggered when a user has deactivated the plugin
     */
    public static function plugin_uninstall()
    {
        logthis('plugin_uninstall hook called');
        delete_option( Enum::SETTINGS_PAGE_OPTION_NAME );
        delete_option( Enum::PLUGIN_KEY );
    }

    /**
     * Render the admin pages
     */
    public static function admin_init()
    {
        //if on plugin activation
        if ( get_option( Enum::PLUGIN_KEY ) ) {
            delete_option( Enum::PLUGIN_KEY );

            //initially turn the BUS_API OFF
            add_option(Enum::SETTINGS_PAGE_OPTION_NAME, ['field_bus_status' => 'off']);
        }
        //Now do normal stuff
        add_action('admin_menu', [self::class, 'handle_admin_ui']);
    }

    public static function handle_admin_ui()
    {
        $adminSettingsPage = new AdminSettingsPage();
        $adminSettingsPage->handle_admin_ui();
    }

    public static function register_wp_bus_plugin_scripts()
    {
        // Load styles
        // WordPress has many defaults here: https://developer.wordpress.org/themes/basics/including-css-javascript/#default-scripts-included-and-registered-by-wordpress
    }
}
