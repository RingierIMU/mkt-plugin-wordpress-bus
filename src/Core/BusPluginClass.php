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
}
