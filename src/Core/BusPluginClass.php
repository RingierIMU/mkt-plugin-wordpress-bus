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
        add_option(Enum::PLUGIN_KEY, true);
        logthis('Activation: set plugin_key to true');
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
        delete_option(Enum::SETTINGS_PAGE_OPTION_NAME);
        delete_option(Enum::PLUGIN_KEY);
    }

    /**
     * Render the admin pages
     */
    public static function admin_init()
    {
        //if on plugin activation
        if (get_option(Enum::PLUGIN_KEY)) {
            logthis('POST Activation: delete plugin_key now');
            delete_option(Enum::PLUGIN_KEY);

            //initially turn the BUS_API OFF
            update_option(Enum::SETTINGS_PAGE_OPTION_NAME, [Enum::FIELD_BUS_STATUS => 'off']);
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

    /**
     * We will leverage some magic from ACF plugin
     * Plugin url: https://wordpress.org/plugins/advanced-custom-fields/
     *
     * We want to offload responsibility of managing custom fields to ACF
     * Simply because it does it so nicely and with powerful flexibility
     */
    public static function inject_acf()
    {
        // Define path and URL to the ACF plugin.
        define('MY_ACF_PATH', WP_BUS_RINGIER_PLUGIN_DIR . 'includes/acf/');
        define('MY_ACF_URL', WP_BUS_RINGIER_PLUGIN_DIR_URL . 'includes/acf/');

        // Include the ACF plugin.
        include_once(MY_ACF_PATH . 'acf.php');

        // Customize the url setting to fix incorrect asset URLs.
        add_filter('acf/settings/url', [self::class, 'acf_settings_url']);

        //Hide the ACF admin menu item | not necessary for now
//        add_filter('acf/settings/show_admin', [self::class, 'acf_settings_show_admin']);

        //Load our custom fields using php structure
        add_action('acf/init', [self::class, 'register_acf_custom_fields']);

        //Load json storage
        //NOTE: this does not seem to work, bypassing to use PHP way instead (above line)
//        add_filter('acf/settings/load_json', [self::class, 'acf_local_json_storage']);
//        add_filter('acf/settings/save_json', [self::class, 'acf_local_json_storage']);
    }

    /**
     * Customize the url setting to fix incorrect asset URLs
     * ref: https://www.advancedcustomfields.com/resources/including-acf-within-a-plugin-or-theme/
     *
     * @param $url
     * @return string
     */
    public static function acf_settings_url($url)
    {
        return MY_ACF_URL;
    }

    /**
     * Hide the ACF admin menu item
     *
     * @param $show_admin
     * @return false
     */
    public static function acf_settings_show_admin($show_admin)
    {
        return false;
    }

    /**
     * Local JSON saves field group and field settings as .json files
     * Idea is similar to caching & dramatically speeds up ACF + allows for versioning our field settings
     * ref: https://www.advancedcustomfields.com/resources/local-json/
     *
     * @return string
     */
    public static function acf_local_json_storage($paths)
    {
        // remove original path (optional)
        unset($paths[0]);
        // path to our json exports having our custom fields
        $paths[] = WP_BUS_RINGIER_PLUGIN_DIR . 'includes/acf-json';

        return $paths;
    }

    /**
     * register the is_post_new custom field
     */
    public static function register_acf_custom_fields()
    {
        acf_add_local_field_group(array(
            'key' => 'group_609a881d82e8f',
            'title' => 'hidden_fields',
            'fields' => array(
                array(
                    'key' => 'field_609a883e62def',
                    'label' => 'is_post_new',
                    'name' => 'is_post_new',
                    'type' => 'text',
                    'instructions' => 'do not use this, only for internal programmatic usage.',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => array(
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ),
                    'default_value' => 'not_new',
                    'placeholder' => '',
                    'prepend' => '',
                    'append' => '',
                    'maxlength' => '',
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'post',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position' => 'side',
            'style' => 'seamless',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => array(
                0 => 'permalink',
                1 => 'the_content',
                2 => 'excerpt',
                3 => 'discussion',
                4 => 'comments',
                5 => 'revisions',
                6 => 'slug',
                7 => 'author',
                8 => 'format',
                9 => 'page_attributes',
                10 => 'featured_image',
                11 => 'categories',
                12 => 'tags',
                13 => 'send-trackbacks',
            ),
            'active' => true,
            'description' => '',
        ));
    }
}
